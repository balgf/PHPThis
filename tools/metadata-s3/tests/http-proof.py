#!/usr/bin/env python3
"""Maintained reference proof: real PHP multipart, real SQLite, synthetic SDK results."""
import fcntl
import hashlib
import http.client
import json
import os
import re
from pathlib import Path
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time

HERE = Path(__file__).resolve().parent
BOUNDARY = b"metadata-s3-proof"
END = b"--" + BOUNDARY + b"--\r\n"
PAYLOAD = b"synthetic opaque document private-sentinel"
LABEL = "private-sentinel label"
SCHEMA = (HERE.parent / 'schema.sql.fixture').read_text()


def require(condition, name):
    if not condition:
        raise RuntimeError("Metadata S3 HTTP failed: " + name)


def field(name, value):
    data = value.encode("utf-8") if isinstance(value, str) else value
    return b"--" + BOUNDARY + b'\r\nContent-Disposition: form-data; name="' + name.encode() + b'"\r\n\r\n' + data + b"\r\n"


def upload(name="document", data=PAYLOAD, filename="opaque.bin", media="application/octet-stream"):
    return b"--" + BOUNDARY + b'\r\nContent-Disposition: form-data; name="' + name.encode() + b'"; filename="' + filename.encode() + b'"\r\nContent-Type: ' + media.encode() + b"\r\n\r\n" + data + b"\r\n"


def valid(label=LABEL, note=None, data=PAYLOAD):
    return field("label", label) + (b"" if note is None else field("note", note)) + upload(data=data) + END


def rows(root):
    with sqlite3.connect(root / "documents.sqlite") as database:
        database.row_factory = sqlite3.Row
        return [dict(row) for row in database.execute("SELECT * FROM documents ORDER BY rowid")]


def reset(root, fault="", trigger=None, count=0, owner=1, history=False):
    database_path = root / "documents.sqlite"
    database_path.unlink(missing_ok=True)
    with sqlite3.connect(database_path) as database:
        database.executescript(SCHEMA)
        if trigger is not None:
            database.executescript(trigger)
        for i in range(count):
            database.execute("INSERT INTO documents (document_id, owner_id, label, note, size_bytes, sha256, state, version_id, created_at, expires_at, target) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                             (f"{i:032x}", owner if owner else i + 10, None if history else "prior", None, 1, "0" * 64,
                              "deleted" if history else "pending", "prior-version" if history else None,
                              1, 86401, "0" * 64))
    os.chmod(database_path, 0o600)
    (root / "plan.json").write_text(json.dumps({"fault": fault}))
    os.chmod(root / "plan.json", 0o600)


def request(port, body, auth="Bearer synthetic-owner-one", content_type=None, path="/documents"):
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
    headers = {"Content-Type": content_type or "multipart/form-data; boundary=" + BOUNDARY.decode(),
               "Content-Length": str(len(body))}
    if auth is not None:
        headers["Authorization"] = auth
    try:
        connection.request("POST", path, body, headers)
        response = connection.getresponse()
        data = response.read(4097)
        require(len(data) <= 4096, "response bound")
        require(b"private-sentinel" not in data, "public metadata redaction")
        require(response.getheader("Cache-Control") == "private, no-store", "private response cache")
        require(re.fullmatch(r"[0-9a-f]{32}", response.getheader("X-Request-ID", "")) is not None, "generated correlation")
        return response.status, data
    finally:
        connection.close()


def run():
    names = []
    php = shutil.which("php")
    require(php is not None, "PHP available")
    with tempfile.TemporaryDirectory(prefix="http-", dir=HERE.parents[2] / "tmp") as temporary:
        root = Path(temporary)
        os.chmod(root, 0o700)
        (root / "uploads").mkdir(mode=0o700)
        (root / "documents.lock").touch(mode=0o600)
        reset(root)
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        log_path = root / "server.log"
        with log_path.open("wb") as log:
            os.chmod(log_path, 0o600)
            command = [php, "-d", "enable_post_data_reading=0", "-d", "file_uploads=1", "-d", "display_errors=0",
                       "-d", "display_startup_errors=0", "-d", "log_errors=1", "-d", "error_reporting=-1", "-d", "zend.exception_ignore_args=1",
                       "-d", "memory_limit=64M", "-d", "max_execution_time=10", "-d", "mbstring.encoding_translation=0",
                       "-d", "upload_tmp_dir=" + str(root / "uploads"), "-S", f"127.0.0.1:{port}", str(HERE / "router.php")]
            process = subprocess.Popen(command, cwd=root, stdout=log, stderr=log)
            try:
                for _ in range(100):
                    require(process.poll() is None, "server startup")
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                            break
                    except OSError:
                        time.sleep(0.02)
                else:
                    raise RuntimeError("Metadata S3 HTTP server timeout")

                def case(name, body, status, calls=(), state=None, label=LABEL, note=None, payload=PAYLOAD,
                         auth="Bearer synthetic-owner-one", fault="", trigger=None, count=0, owner=1,
                         history=False, content_type=None, keep=False):
                    if not keep:
                        reset(root, fault, trigger, count, owner, history)
                    before = rows(root)
                    actual_status, public = request(port, body, auth, content_type)
                    require(actual_status == status, name + " status (observed " + str(actual_status) + ")")
                    receipt = json.loads((root / "receipt.json").read_text())
                    after = rows(root)
                    require(receipt["summary_attempts"] == 1, name + " one terminal sink attempt")
                    summary = json.loads(receipt["summary"])
                    require(summary["response_status"] == status and "private-sentinel" not in receipt["summary"], name + " summary status and redaction")
                    require(receipt["calls"] == list(calls), name + " finite S3 calls")
                    require(after[:len(before)] == before, name + " prior ownership unchanged")
                    if state is None:
                        require(after == before, name + " zero protected writes")
                        if not trigger and count == 0:
                            require(receipt["statements"] == 0, name + " zero protected SQL")
                    else:
                        require(len(after) == len(before) + 1, name + " one atomic ownership row")
                        record = after[-1]
                        require(receipt["pending_seen"], name + " durable reservation precedes SDK write")
                        require(record["state"] == state and record["owner_id"] == owner, name + " state and trusted owner")
                        require(record["label"] == (None if state == "failed" else label)
                                and record["note"] == (None if state == "failed" else note), name + " exact typed metadata or terminal purge")
                        require(record["size_bytes"] == len(payload) and record["sha256"] == hashlib.sha256(payload).hexdigest(), name + " exact byte binding")
                        require(record["expires_at"] - record["created_at"] == 86400, name + " unchanged retention")
                        if receipt["present"]:
                            require(receipt["key"] == "private-documents/v1/" + record["document_id"] + "/content", name + " server-owned object identity")
                            require(receipt["sha256"] == record["sha256"], name + " stored object bytes")
                        if state in ("stored", "accepted") or "HeadObject" in calls:
                            require(record["version_id"] == "synthetic-version", name + " exact version")
                        if status == 201:
                            require(receipt["statements"] == 7, name + " constant six operation statements plus test reservation observation")
                            require(json.loads(public) == {"id": record["document_id"]}, name + " finite success representation")
                    require(not list((root / "uploads").iterdir()), name + " PHP temporary cleanup")
                    names.append(name)

                success_calls = ("PutObject", "HeadObject")
                case("throwing-summary-sink", valid(), 201, success_calls, "accepted", fault="sink_failure")
                case("label-only", valid(), 201, success_calls, "accepted")
                case("optional-note", valid(note="private-sentinel note"), 201, success_calls, "accepted", note="private-sentinel note")
                case("explicit-empty-note", valid(note=""), 201, success_calls, "accepted", note="")
                case("UTF8-byte-boundaries", valid("é" * 60, "é" * 512), 201, success_calls, "accepted", label="é" * 60, note="é" * 512)
                case("maximum-file", valid(data=b"x" * 65536), 201, success_calls, "accepted", payload=b"x" * 65536)
                case("no-implicit-trimming", valid("  private-sentinel  "), 201, success_calls, "accepted", label="  private-sentinel  ")
                text = "{\"not-json\": <script>private-sentinel</script>"
                case("opaque-text-no-JSON-HTML-classification", valid(note=text), 201, success_calls, "accepted", note=text)
                case("hostile-filename-type-ignored", field("label", LABEL) + upload(filename="../../private-sentinel.svg", media="text/html") + END, 201, success_calls, "accepted")
                case("missing-label", upload() + END, 400)
                case("unknown-field", field("label", LABEL) + field("unknown", "x") + upload() + END, 400)
                for name in ("owner_id", "bucket", "key", "inspected"):
                    case("reject-caller-" + name, field("label", LABEL) + field(name, "private-sentinel") + upload() + END, 400)
                case("unknown-before-invalid-value", field("label", "") + field("unknown", "x") + upload() + END, 400)
                case("nested-label", field("label[]", "x") + upload() + END, 400)
                case("empty-label", valid(""), 422)
                case("label-one-byte-over", valid("x" * 121), 422)
                case("label-invalid-UTF8", valid(b"\xff"), 422)
                case("note-invalid-UTF8", valid(note=b"\xff"), 422)
                case("label-control", valid("a\nb"), 422)
                case("note-control", valid(note="a\x00b"), 422)
                case("note-one-byte-over", valid(note="x" * 1025), 413)
                case("aggregate-one-byte-over", valid("x" * 121, "x" * 1024), 413)
                case("request-too-large", valid(data=b"x" * 131072), 413)
                case("file-too-large", valid(data=b"x" * 65537), 413)
                case("empty-file", valid(data=b""), 422)
                case("missing-file", field("label", LABEL) + END, 400)
                case("second-named-file", field("label", LABEL) + upload() + upload("extra") + END, 400)
                case("wrong-file-name", field("label", LABEL) + upload("other") + END, 400)
                case("wrong-media", b"{}", 415, content_type="application/json")
                case("injected-non-upload-path", valid(), 500, fault="bad_path")
                case("injected-reported-size-mismatch", valid(), 500, fault="bad_size")
                case("unauthenticated", valid(), 401, auth=None)
                case("unauthorized", valid(), 403, auth="Bearer synthetic-denied")
                case("verifier-uncertainty", valid(), 500, auth="Bearer synthetic-outage")
                case("authorization-before-domain-validation", valid(""), 401, auth=None)
                case("native-transport-before-authentication", valid(note="x" * 1025), 413, auth=None)
                case("collapsed-duplicate-label-last-value", field("label", "") + field("label", LABEL) + upload() + END, 201, success_calls, "accepted")
                case("collapsed-duplicate-label-invalid-last", field("label", LABEL) + field("label", "") + upload() + END, 422)
                case("collapsed-duplicate-file-last-value", field("label", LABEL) + upload(data=b"first") + upload() + END, 201, success_calls, "accepted")
                case("raw-duplicate-count-limit", field("label", LABEL) * 33 + upload() + END, 400)
                case("database-reservation-failure", valid(), 500, trigger="CREATE TRIGGER fail BEFORE INSERT ON documents BEGIN SELECT RAISE(ABORT, 'synthetic'); END;")
                case("database-stored-failure", valid(), 500, ("PutObject",), "pending", trigger="CREATE TRIGGER fail BEFORE UPDATE ON documents WHEN NEW.state = 'stored' BEGIN SELECT RAISE(ABORT, 'synthetic'); END;")
                case("database-failed-purge-failure", valid(), 500, ("PutObject",), "pending", fault="put412", trigger="CREATE TRIGGER fail BEFORE UPDATE ON documents WHEN NEW.state = 'failed' BEGIN SELECT RAISE(ABORT, 'synthetic'); END;")
                case("database-accepted-failure", valid(), 500, success_calls, "stored", trigger="CREATE TRIGGER fail BEFORE UPDATE ON documents WHEN NEW.state = 'accepted' BEGIN SELECT RAISE(ABORT, 'synthetic'); END;")
                for fault in ("put403", "put409", "put500", "lost_put", "null_version"):
                    case("S3-" + fault, valid(), 500, ("PutObject",), "reconciliation_required", fault=fault)
                case("S3-collision", valid(), 500, ("PutObject",), "failed", fault="put412")
                for fault in ("head_denied", "wrong_kms", "wrong_metadata", "storage_class"):
                    case("S3-" + fault, valid(), 500, success_calls, "reconciliation_required", fault=fault)
                case("principal-quota", valid(), 503, count=8)
                case("deployment-quota", valid(), 503, count=32, owner=0)
                case("identity-history-quota", valid(), 503, count=256, history=True)
                reset(root)
                with (root / "documents.lock").open("rb") as locked:
                    fcntl.flock(locked, fcntl.LOCK_EX | fcntl.LOCK_NB)
                    case("admission-conflict", valid(), 503, keep=True)
                case("owner-one-binding", valid("first private-sentinel"), 201, success_calls, "accepted", label="first private-sentinel")
                case("owner-two-binding", valid("second private-sentinel", data=b"second bytes"), 201, success_calls, "accepted", label="second private-sentinel", payload=b"second bytes", auth="Bearer synthetic-owner-two", owner=2, keep=True)
            finally:
                process.terminate()
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=5)
        # Exercise the real checked-in composition with a complete synthetic tuple.
        # It denies access, so it must neither alter rows nor contact an external service.
        reset(root)
        ca = root / "reviewed-ca"
        ca.write_text("synthetic CA bytes")
        environment = {"PATH": os.environ.get("PATH", "/usr/bin:/bin")}
        settings = {"ROOT": str(root), "BUCKET": "fixture-bucket", "REGION": "us-east-1",
                    "ACCOUNT": "000000000000", "KMS": "arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001",
                    "CA_PATH": str(ca), "CA_SHA256": hashlib.sha256(ca.read_bytes()).hexdigest(),
                    "ACCESS_KEY": "ASIA" + "A" * 16, "SECRET": "s" * 40, "TOKEN": "t" * 32,
                    "EXPIRES": str(int(time.time()) + 3600)}
        environment.update({"PHPTHIS_DOCUMENT_WRITER_" + key: value for key, value in settings.items()})
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        command[-2:] = [f"127.0.0.1:{port}", str(HERE.parent / "public/index.php")]
        with log_path.open("ab") as log:
            process = subprocess.Popen(command, cwd=root, env=environment, stdout=log, stderr=log)
            try:
                for _ in range(100):
                    require(process.poll() is None, "checked composition startup")
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                            break
                    except OSError:
                        time.sleep(0.02)
                else:
                    raise RuntimeError("Checked composition startup timeout")
                for auth, expected in [(None, 401), ("Bearer synthetic-owner-one", 403)]:
                    status, _ = request(port, valid(note="private-sentinel"), auth)
                    require(status == expected and rows(root) == [], "checked composition denies before SQL")
                    names.append("checked-composition-denial-" + str(expected))
                    packet = field("label", LABEL) + field("request_id", "a" * 32) + upload("primary") + upload("supporting") + END
                    status, _ = request(port, packet, auth, path="/document-packets")
                    require(status == expected and rows(root) == [], "checked packet composition denies before SQL")
                    with sqlite3.connect(root / "documents.sqlite") as database:
                        require(database.execute("SELECT COUNT(*) FROM document_packets").fetchone()[0] == 0, "checked packet composition leaves no reservation")
                    names.append("checked-packet-composition-denial-" + str(expected))
            finally:
                process.terminate()
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=5)
        require(log_path.stat().st_size <= 131072, "bounded private server log")
        require(b"private-sentinel" not in log_path.read_bytes(), "native log redaction")
    receipt = {"status": "PASS", "cases": names, "case_count": len(names), "evidence": "REAL_PHP_HTTP_AND_SQLITE_WITH_SYNTHETIC_SDK_RESULTS", "aws_calls": 0}
    (HERE.parents[2] / "tmp/metadata-s3-http.json").write_text(json.dumps(receipt, indent=2) + "\n")
    print("PASS metadata S3 HTTP: " + str(len(names)) + " cases; synthetic SDK only; no AWS calls")


if __name__ == "__main__":
    run()
