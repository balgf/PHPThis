#!/usr/bin/python3
"""Fixed container-local HTTP transport for the host-only Responses proxy.

Only the trusted controller owns stdin/stdout. No socket crosses the container's
network namespace; the container always runs with Docker's network mode none.
"""

import base64
import binascii
import ctypes
import json
import os
import selectors
import signal
import socket
import subprocess
import sys
import threading

PORT = 8765
REQUEST_LIMIT = 1_048_576
RESPONSE_LIMIT = 4_194_304
FRAME_LIMIT = 6_291_456
OUTPUT_LIMIT = 4_194_304
EVENT_LIMIT = 1_048_576
HEADER_LIMIT = 16_384
OUTPUT_LOCK = threading.Lock()
STOP = threading.Event()


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError("duplicate JSON name")
        result[key] = value
    return result


def decode_json(source):
    return json.loads(source, object_pairs_hook=unique_object)


def emit(value):
    data = json.dumps(value, separators=(",", ":"), ensure_ascii=True).encode() + b"\n"
    if len(data) > FRAME_LIMIT:
        raise ValueError("frame bound")
    with OUTPUT_LOCK:
        sys.stdout.buffer.write(data)
        sys.stdout.buffer.flush()


def frame():
    data = sys.stdin.buffer.readline(FRAME_LIMIT + 1)
    if not data.endswith(b"\n") or len(data) > FRAME_LIMIT:
        raise ValueError("incomplete or excessive controller frame")
    value = decode_json(data)
    if not isinstance(value, dict):
        raise ValueError("controller frame is not an object")
    return value


def unbase64(value, limit):
    if not isinstance(value, str) or len(value) > ((limit + 2) // 3) * 4:
        raise ValueError("encoded body bound")
    try:
        data = base64.b64decode(value, validate=True)
    except (binascii.Error, ValueError) as error:
        raise ValueError("invalid encoded body") from error
    if len(data) > limit:
        raise ValueError("decoded body bound")
    return data


def startup():
    value = frame()
    if set(value) != {"type", "prompt_base64", "arguments", "environment"} or value["type"] != "start":
        raise ValueError("invalid startup frame")
    arguments = value["arguments"]
    environment = value["environment"]
    # The controller constructs the entire fixed invocation before any candidate
    # process starts. This check prevents this image becoming a generic launcher.
    if (
        not isinstance(arguments, list)
        or not 10 <= len(arguments) <= 100
        or arguments[:2] != ["/usr/local/bin/codex", "exec"]
        or not all(isinstance(arg, str) and len(arg) <= 4096 and "\0" not in arg for arg in arguments)
        or arguments[-1] != "-"
        or "--cd" not in arguments
        or arguments[arguments.index("--cd") + 1] != "/candidate"
    ):
        raise ValueError("invalid fixed Codex invocation")
    required = {
        "LANG": "C", "LC_ALL": "C", "PATH": "/usr/local/bin:/usr/bin:/bin",
        "HOME": "/tmp/phpthis-home", "CODEX_HOME": "/tmp/phpthis-codex",
    }
    if environment != required:
        raise ValueError("invalid minimal Codex environment")
    for path in (required["HOME"], required["CODEX_HOME"]):
        os.makedirs(path, mode=0o700, exist_ok=False)
    return arguments, environment, unbase64(value["prompt_base64"], REQUEST_LIMIT)


def read_request(connection):
    connection.settimeout(15)
    data = b""
    while b"\r\n\r\n" not in data:
        block = connection.recv(min(4096, HEADER_LIMIT + 1 - len(data)))
        if not block:
            raise ValueError("incomplete HTTP headers")
        data += block
        if len(data) > HEADER_LIMIT:
            raise ValueError("HTTP header bound")
    source, body = data.split(b"\r\n\r\n", 1)
    lines = source.decode("ascii").split("\r\n")
    if lines[0] != "POST /v1/responses HTTP/1.1":
        raise ValueError("unapproved HTTP operation")
    headers = {}
    for line in lines[1:]:
        if ":" not in line or line[:1].isspace():
            raise ValueError("malformed HTTP header")
        key, content = line.split(":", 1)
        key = key.lower()
        if key in headers or not key or any(c not in "abcdefghijklmnopqrstuvwxyz0123456789-" for c in key):
            raise ValueError("duplicate or invalid HTTP header")
        headers[key] = content.strip()
    if (
        headers.get("host") != "127.0.0.1:8765"
        or headers.get("content-type") != "application/json"
        or "transfer-encoding" in headers
        or "content-encoding" in headers
        or "authorization" in headers
        or not headers.get("content-length", "").isascii()
        or not headers.get("content-length", "").isdigit()
    ):
        raise ValueError("unapproved HTTP framing")
    length = int(headers["content-length"])
    if not 1 <= length <= REQUEST_LIMIT or len(body) > length:
        raise ValueError("HTTP body bound")
    while len(body) < length:
        block = connection.recv(min(65536, length - len(body)))
        if not block:
            raise ValueError("incomplete HTTP body")
        body += block
    return body


def serve(listener):
    sequence = 0
    try:
        while not STOP.is_set():
            try:
                connection, _ = listener.accept()
            except socket.timeout:
                continue
            with connection:
                body = read_request(connection)
                sequence += 1
                emit({"type": "request", "id": sequence, "method": "POST", "path": "/v1/responses",
                      "body_base64": base64.b64encode(body).decode("ascii")})
                response = frame()
                if set(response) != {"id", "status", "body_base64"} or response["id"] != sequence or response["status"] != 200:
                    raise ValueError("unapproved host response")
                result = unbase64(response["body_base64"], RESPONSE_LIMIT)
                connection.sendall(b"HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nConnection: close\r\nContent-Length: "
                                   + str(len(result)).encode("ascii") + b"\r\n\r\n" + result)
    except Exception:
        STOP.set()
        # Static diagnostics never echo candidate HTTP bytes or controller data.
        sys.stderr.write("AGENT_EVALUATION_RELAY_PROTOCOL_FAILED\n")
        sys.stderr.flush()


def resources():
    def counters(path, names):
        with open(path, "r", encoding="ascii") as source:
            text = source.read(4097)
        if len(text) > 4096:
            raise ValueError("cgroup counter bound")
        values = dict(line.split() for line in text.splitlines())
        result = {name: int(values[name]) for name in names}
        if any(value < 0 for value in result.values()):
            raise ValueError("invalid cgroup counter")
        return result

    free = {}
    for name, path in {"candidate": "/candidate", "tmp": "/tmp", "workspace_tmp": "/candidate/tmp",
                       "cache": "/candidate/vendor/.phpthis", "shm": "/dev/shm"}.items():
        value = os.statvfs(path)
        free[name] = value.f_bavail * value.f_frsize
    return {"type": "resources",
            "memory_events": counters("/sys/fs/cgroup/memory.events", ["oom", "oom_kill"]),
            "pids_events": counters("/sys/fs/cgroup/pids.events", ["max"]),
            "disk_free_bytes": free}


def main():
    os.umask(0o077)
    arguments, environment, prompt = startup()
    # Same-UID candidate processes may cause a failed run by signaling PID 1,
    # but must not inspect or reopen the controller-owned transport descriptors.
    # Linux resets dumpability for the exec'd Codex child; this trusted parent
    # remains non-dumpable throughout its lifetime. No added capability is used.
    libc = ctypes.CDLL(None, use_errno=True)
    if libc.prctl(4, 0, 0, 0, 0) != 0 or libc.prctl(3, 0, 0, 0, 0) != 0:
        raise ValueError("relay process inspection boundary unavailable")
    listener = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    listener.bind(("127.0.0.1", PORT))
    listener.listen(1)
    listener.settimeout(0.2)
    server = threading.Thread(target=serve, args=(listener,), daemon=True)
    server.start()
    child = subprocess.Popen(arguments, cwd="/candidate", env=environment, stdin=subprocess.PIPE,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True,
                             umask=0o022)
    try:
        child.stdin.write(prompt)
        child.stdin.close()
        selector = selectors.DefaultSelector()
        selector.register(child.stdout, selectors.EVENT_READ, "event")
        selector.register(child.stderr, selectors.EVENT_READ, "stderr")
        pending = b""
        total = 0
        while selector.get_map():
            if STOP.is_set():
                raise ValueError("relay protocol failed")
            for selected, _ in selector.select(0.2):
                block = os.read(selected.fd, 65536)
                if not block:
                    selector.unregister(selected.fileobj)
                    continue
                total += len(block)
                if total > OUTPUT_LIMIT:
                    raise ValueError("Codex output bound")
                if selected.data == "stderr":
                    emit({"type": "stderr", "data_base64": base64.b64encode(block).decode("ascii")})
                    continue
                pending += block
                while b"\n" in pending:
                    line, pending = pending.split(b"\n", 1)
                    if not line or len(line) > EVENT_LIMIT:
                        raise ValueError("Codex event bound")
                    event = decode_json(line)
                    if not isinstance(event, dict):
                        raise ValueError("Codex event is not an object")
                    emit({"type": "event", "event": event})
                if len(pending) > EVENT_LIMIT:
                    raise ValueError("Codex event bound")
        if pending or STOP.is_set():
            raise ValueError("incomplete Codex output")
        status = child.wait(timeout=5)
        STOP.set()
        listener.close()
        emit(resources())
        emit({"type": "finished", "exit_code": status})
    finally:
        STOP.set()
        listener.close()
        try:
            os.killpg(child.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        child.wait(timeout=5)


if __name__ == "__main__":
    try:
        main()
    except Exception:
        sys.stderr.write("AGENT_EVALUATION_RELAY_FAILED\n")
        sys.exit(1)
