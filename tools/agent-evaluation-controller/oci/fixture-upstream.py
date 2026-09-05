"""Deterministic local Responses fixture; never forwards a request anywhere.

Imported only by the opt-in integration test. The real Codex binary receives a
reviewed command from this fixture, executes it inside OCI, then receives a final
message. This is protocol and containment evidence, not model-quality evidence.
"""

import json
import threading
from http.server import BaseHTTPRequestHandler, HTTPServer

INPUT_TOKENS = 100
OUTPUT_TOKENS = 100

HEALTH_ROUTES = """<?php

declare(strict_types=1);

namespace App;

use PHPThis\\Routing\\Route;

final class HealthRoutes
{
    /** @return list<Route> */
    public static function create(): array
    {
        return [
            new Route('GET', '/health', new HealthHandler()),
            new Route('GET', '/ping', new PingHandler()),
        ];
    }
}
"""
PING_HANDLER = """<?php

declare(strict_types=1);

namespace App;

use PHPThis\\Http\\Request;
use PHPThis\\Http\\RequestHandler;
use PHPThis\\Http\\Response;

final class PingHandler implements RequestHandler
{
    public function handle(Request $request): Response
    {
        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            body: "{\\\"status\\\":\\\"pong\\\"}\\n",
        );
    }
}
"""
PING_TEST = """
$ping = $application->handle(new Request('GET', '/ping'));
$expectSame(200, $ping->status, 'GET /ping must return 200.');
$expectSame("{\\\"status\\\":\\\"pong\\\"}\\n", $ping->body, 'GET /ping must return the exact body.');

"""
SCORING_TEST = """
if (!is_file('/usr/local/bin/codex')) {
    $expectSame(false, is_file('/usr/local/bin/codex'), 'Scoring must not contain Codex.');
    $expectSame(false, is_file('/opt/phpthis/relay.py'), 'Scoring must not contain the generation relay.');
    $expectSame(false, @file_put_contents(dirname(__DIR__) . '/src/PingHandler.php', 'forbidden mutation'), 'Frozen candidate must be read-only.');
    $expectSame(false, @file_put_contents('/scorer/public.php', 'forbidden mutation'), 'Public scorer must be read-only.');
    $expectSame(false, @file_put_contents('/candidate/oci-write.control', 'forbidden mutation'), 'Frozen candidate root must be read-only.');
    $expectSame(false, @file_put_contents('/scorer/oci-write.control', 'forbidden mutation'), 'Public scorer root must be read-only.');
    fwrite(STDOUT, "PASS OCI scoring immutability controls\\n");
}
"""

CONTAINMENT = """
import errno, os, pathlib, socket
assert os.getuid() == 65534
assert not pathlib.Path('/scorer').exists()
assert not pathlib.Path('/candidate/.git').exists()
assert not pathlib.Path('/var/run/docker.sock').exists()
try: os.listdir('/root/.codex')
except OSError as failure: assert failure.errno in [errno.ENOENT, errno.EACCES]
else: raise RuntimeError('host auth directory was accessible')
assert not pathlib.Path('/tmp/phpthis-codex/auth.json').exists()
assert 'PHPTHIS_OCI_AMBIENT_SENTINEL' not in os.environ
assert 'OPENAI_API_KEY' not in os.environ
assert os.statvfs('/candidate/vendor').f_flag & os.ST_RDONLY
for path, mode in [('/proc/1/fd/0',os.O_RDONLY),('/proc/1/fd/1',os.O_WRONLY),('/proc/1/mem',os.O_RDONLY)]:
    try: descriptor = os.open(path, mode)
    except PermissionError: pass
    else:
        os.close(descriptor)
        raise RuntimeError('relay descriptor boundary failed')
for path in ['/opt/phpthis/relay.py','/candidate/vendor/oci-write.control','/root/oci-write.control']:
    try:
        descriptor = os.open(path, os.O_WRONLY|os.O_CREAT, 0o600)
    except OSError as failure:
        assert failure.errno in [errno.EACCES, errno.EROFS]
    else:
        os.close(descriptor)
        raise RuntimeError('read-only filesystem boundary failed')
for address in ['1.1.1.1','169.254.169.254']:
    try:
        with socket.create_connection((address,80),timeout=0.2):
            raise RuntimeError('arbitrary network route was available')
    except OSError: pass
status = pathlib.Path('/proc/self/status').read_text()
assert 'CapEff:\\t0000000000000000' in status
assert 'NoNewPrivs:\\t1' in status
print('PASS OCI candidate containment controls', flush=True)
"""


def candidate_command(mode):
    statements = [CONTAINMENT]
    if mode == "relay-signal":
        # Python installs a SIGINT handler. A namespace init process ignores
        # signals without handlers, so SIGKILL would not prove this DoS boundary.
        statements.append("import signal, time\ntime.sleep(1)\nos.kill(1, signal.SIGINT)\n")
    elif mode == "output-limit":
        statements.append("import os\nos.write(1,b'O' * 5_000_000)\n")
    elif mode == "wall-limit":
        statements.append("import time\ntime.sleep(30)\n")
    elif mode == "pids-limit":
        statements.append("""
import signal, time
children = []
try:
    for attempt in range(200):
        try: child = os.fork()
        except BlockingIOError: break
        if child == 0:
            time.sleep(10)
            os._exit(0)
        children.append(child)
finally:
    for child in children:
        os.kill(child,signal.SIGKILL)
        os.waitpid(child,0)
""")
    elif mode == "memory-limit":
        statements.append("blocks=[]\nwhile True: blocks.append(bytearray(32*1024*1024))\n")
    elif mode == "disk-limit":
        statements.append("""
with open('/candidate/tmp/oci-disk-bound.control','wb',buffering=0) as output:
    try:
        while True: output.write(b'D' * 1024 * 1024)
    except OSError as failure:
        assert failure.errno == errno.ENOSPC
print('PASS OCI disk bound reached')
""")
    elif mode == "workspace-escape":
        statements.append("p=pathlib.Path('/candidate/src/Unexpected.php')\np.write_text('<?php\\n')\nassert p.read_text()=='<?php\\n'\nprint('PASS OCI unlisted file mutation',flush=True)\n")
    elif mode == "symlink":
        statements.append("p='/candidate/src/PingHandler.php'\nos.symlink('/tmp',p)\nassert pathlib.Path(p).is_symlink() and os.readlink(p)=='/tmp'\nprint('PASS OCI symlink mutation',flush=True)\n")
    elif mode == "file-mode":
        statements.append("p='/candidate/src/HealthHandler.php'\nos.chmod(p,0o600)\nassert os.stat(p).st_mode & 0o777 == 0o600\nprint('PASS OCI file mode mutation',flush=True)\n")
    else:
        assertions = PING_TEST + (SCORING_TEST if mode == "scoring-boundary" else "")
        statements.extend([
            "pathlib.Path('/candidate/src/HealthRoutes.php').write_text(" + repr(HEALTH_ROUTES) + ")\n",
            "pathlib.Path('/candidate/src/PingHandler.php').write_text(" + repr(PING_HANDLER) + ")\n",
            "p=pathlib.Path('/candidate/tests/run.php')\ns=p.read_text()\n"
            "anchor='$health = $application->handle(new Request(\\'GET\\', \\'/health\\'));\\n'\n"
            "assert s.count(anchor)==1\np.write_text(s.replace(anchor,anchor+" + repr(assertions) + "))\n",
            "import subprocess\nsubprocess.run(['/usr/local/bin/composer','check'],cwd='/candidate',check=True)\n"
            "print('PASS OCI generation containment and application checks',flush=True)\n",
            "import time\nchild=os.fork()\nif child==0:\n"
            "    os.setsid()\n    os.close(0)\n    os.close(1)\n    os.close(2)\n"
            "    time.sleep(30)\n    os._exit(0)\n"
            "print('PASS escaped-session descendant created for OCI cleanup')\n",
        ])
    return "python3 - <<'PHPTHIS_OCI_FIXTURE'\n" + "".join(statements) + "\nPHPTHIS_OCI_FIXTURE"


def event(value):
    return "event: " + value["type"] + "\ndata: " + json.dumps(value, separators=(",", ":")) + "\n\n"


def response_sse(model, item, sequence):
    response = {
        "id": "resp_phpthis_" + str(sequence), "object": "response", "created_at": 1,
        "model": model, "status": "in_progress", "output": [],
    }
    result = event({"type": "response.created", "response": response})
    initial_item = dict(item, arguments="") if item["type"] == "function_call" else dict(item, content=[])
    result += event({"type": "response.output_item.added", "output_index": 0, "item": initial_item})
    if item["type"] == "function_call":
        result += event({"type": "response.function_call_arguments.delta", "output_index": 0,
                         "item_id": item["id"], "delta": item["arguments"]})
        result += event({"type": "response.function_call_arguments.done", "output_index": 0,
                         "item_id": item["id"], "arguments": item["arguments"]})
    else:
        part = item["content"][0]
        result += event({"type": "response.content_part.added", "output_index": 0, "content_index": 0,
                         "item_id": item["id"], "part": dict(part, text="")})
        result += event({"type": "response.output_text.delta", "output_index": 0, "content_index": 0,
                         "item_id": item["id"], "delta": part["text"]})
        result += event({"type": "response.output_text.done", "output_index": 0, "content_index": 0,
                         "item_id": item["id"], "text": part["text"]})
        result += event({"type": "response.content_part.done", "output_index": 0, "content_index": 0,
                         "item_id": item["id"], "part": part})
    result += event({"type": "response.output_item.done", "output_index": 0, "item": item})
    response = dict(response, status="completed", output=[item], usage={
        "input_tokens": INPUT_TOKENS, "output_tokens": OUTPUT_TOKENS,
        "total_tokens": INPUT_TOKENS + OUTPUT_TOKENS,
        "input_tokens_details": {"cached_tokens": 0},
        "output_tokens_details": {"reasoning_tokens": 0},
    })
    return result + event({"type": "response.completed", "response": response})


class FixtureServer(HTTPServer):
    # Permit a previous completed fixture's TCP TIME_WAIT sockets, while never
    # allowing a second active listener to share the reviewed fixed endpoint.
    allow_reuse_address = True
    allow_reuse_port = False

    def __init__(self):
        super().__init__(("127.0.0.1", 18765), FixtureHandler)
        self.mode = "complete"
        self.requests = []
        self.responses = 0
        self.failure = None

    def reset(self, mode):
        self.mode = mode
        self.requests = []
        self.responses = 0
        self.failure = None


class FixtureHandler(BaseHTTPRequestHandler):
    def log_message(self, *_):
        pass

    def do_POST(self):
        try:
            length = int(self.headers.get("Content-Length", "0"))
            if not 0 < length <= 1_048_576 or self.path not in ["/v1/responses/input_tokens", "/v1/responses"]:
                raise ValueError("unapproved fixture operation")
            body = json.loads(self.rfile.read(length))
            self.server.requests.append({"path": self.path, "model": body.get("model")})
            if self.path.endswith("/input_tokens"):
                count = 40_000 if self.server.mode == "token-limit" else INPUT_TOKENS
                result = json.dumps({"object": "response.input_tokens", "input_tokens": count}).encode()
                content_type = "application/json"
            else:
                self.server.responses += 1
                if self.server.responses == 1:
                    names = [tool.get("name") for tool in body.get("tools", []) if tool.get("type") == "function"]
                    if "exec_command" not in names:
                        raise ValueError("pinned Codex exec_command tool is unavailable: " + repr(names))
                    item = {"type": "function_call", "id": "fc_phpthis_1", "call_id": "call_phpthis_1",
                            "name": "exec_command", "arguments": json.dumps({"cmd": candidate_command(self.server.mode), "workdir": "/candidate",
                                                                              "yield_time_ms": 30_000, "max_output_tokens": 8_000})}
                elif self.server.responses == 2:
                    final_text = "O" * 32_768 if self.server.mode == "output-bound" else "Deterministic OCI fixture completed."
                    item = {"type": "message", "id": "msg_phpthis_1", "role": "assistant", "status": "completed",
                            "content": [{"type": "output_text", "text": final_text, "annotations": []}]}
                else:
                    raise ValueError("unexpected fixture repair turn")
                result = response_sse(body["model"], item, self.server.responses).encode()
                content_type = "text/event-stream"
            self.send_response(200)
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(len(result)))
            self.end_headers()
            self.wfile.write(result)
        except Exception as failure:
            self.server.failure = str(failure)
            self.send_error(500, "Deterministic fixture failed")


def start_fixture():
    server = FixtureServer()
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    return server, thread
