"""Exercise pinned SDK/Guzzle against synthetic loopback HTTP, never AWS."""
import base64
import datetime
import hashlib
import http.server
import json
from pathlib import Path
import shutil
import subprocess
import threading
import time
import urllib.parse

ROOT = Path(__file__).resolve().parent
DATA = b'x' * 65_536
CHECKSUM = base64.b64encode(hashlib.sha256(DATA).digest()).decode()
KMS = 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001'
CASES = ['valid', 'oversized', 'chunked_oversized', 'short', 'wrong_version',
         'wrong_checksum', 'corrupted', 'denied', 'missing', 'redirect', 'timeout',
         'wrong_kms', 'bucket_key', 'metadata', 'storage', 'encoded']
observed = []
failures = []


class Handler(http.server.BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *args):
        pass

    def do_PUT(self):
        length = int(self.headers.get('Content-Length', '-1'))
        valid = (length == len(DATA) and self.rfile.read(min(max(length, 0), len(DATA) + 1)) == DATA
                 and self.headers.get('If-None-Match') == '*'
                 and self.headers.get('x-amz-checksum-sha256') == CHECKSUM
                 and self.headers.get('x-amz-expected-bucket-owner') == '000000000000'
                 and self.headers.get('x-amz-server-side-encryption') == 'aws:kms'
                 and self.headers.get('x-amz-server-side-encryption-aws-kms-key-id') == KMS)
        if not valid:
            failures.append('put_request')
        observed.append('put')
        self.send_response(200)
        self.send_header('x-amz-version-id', 'fixture-version')
        self.send_header('x-amz-checksum-sha256', CHECKSUM)
        self.send_header('Content-Length', '0')
        self.end_headers()

    def do_GET(self):
        url = urllib.parse.urlsplit(self.path)
        if 'versions' in url.query:
            observed.append('oversized_control')
            self.send_response(200)
            self.send_header('Content-Length', '20000')
            self.end_headers()
            try:
                self.wfile.write(b'x' * 20000)
            except (BrokenPipeError, ConnectionResetError):
                pass
            self.close_connection = True
            return
        case = CASES[int(url.path.split('/')[-2], 16) - 1]
        if (urllib.parse.parse_qs(url.query) != {'versionId': ['fixture-version']}
                or self.headers.get('x-amz-checksum-mode') != 'ENABLED'
                or self.headers.get('x-amz-expected-bucket-owner') != '000000000000'):
            failures.append('get_request')
        observed.append(case)
        if case == 'timeout':
            time.sleep(1)
        status = {'denied': 403, 'missing': 404, 'redirect': 302}.get(case, 200)
        payload = DATA
        if case in ['oversized', 'chunked_oversized']:
            payload += b'x'
        if case == 'corrupted':
            payload = b'y' + DATA[1:]
        if status != 200:
            payload = b'<Error><Code>AccessDenied</Code></Error>'
        self.send_response(status)
        self.send_header('x-amz-version-id', 'wrong' if case == 'wrong_version' else 'fixture-version')
        self.send_header('x-amz-checksum-sha256', base64.b64encode(b'0' * 32).decode() if case == 'wrong_checksum' else CHECKSUM)
        self.send_header('x-amz-server-side-encryption', 'aws:kms')
        self.send_header('x-amz-server-side-encryption-aws-kms-key-id', 'wrong' if case == 'wrong_kms' else KMS)
        self.send_header('x-amz-server-side-encryption-bucket-key-enabled', 'true' if case == 'bucket_key' else 'false')
        self.send_header('x-amz-meta-profile', 'wrong' if case == 'metadata' else 'private-documents-v1')
        if case == 'storage':
            self.send_header('x-amz-storage-class', 'STANDARD')
        if case == 'encoded':
            self.send_header('Content-Encoding', 'gzip')
        self.send_header('Content-Type', 'application/octet-stream')
        self.send_header('Connection', 'close')
        if case == 'redirect':
            self.send_header('Location', 'http://127.0.0.1:1/forbidden')
        if case == 'chunked_oversized':
            self.send_header('Transfer-Encoding', 'chunked')
        else:
            self.send_header('Content-Length', str(len(payload)))
        try:
            self.end_headers()
            if case == 'short':
                self.wfile.write(payload[:16])
            elif case == 'chunked_oversized':
                for start in range(0, len(payload), 8192):
                    part = payload[start:start+8192]
                    self.wfile.write(f'{len(part):x}\r\n'.encode() + part + b'\r\n')
                self.wfile.write(b'0\r\n\r\n')
            else:
                self.wfile.write(payload)
        except (BrokenPipeError, ConnectionResetError):
            pass
        self.close_connection = True


with http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler) as server:
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        run = subprocess.run([shutil.which('php'), '-d', 'display_errors=0', '-d', 'memory_limit=64M', str(ROOT / 'transport.php'), str(server.server_port)],
                             cwd=ROOT, env={'PATH': '/usr/local/bin:/usr/bin:/bin'},
                             capture_output=True, timeout=20)
    finally:
        server.shutdown()
        thread.join(timeout=2)
    if run.returncode != 0:
        raise RuntimeError(run.stderr.decode())
    assert run.stderr == b'' and not failures, failures
    assert observed == ['put'] + CASES + ['oversized_control'], observed
    result = json.loads(run.stdout)
    result['requests_observed'] = len(observed)
    result['automatic_retries_or_redirects'] = 0
    result['verified_at'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    source_files = [*ROOT.parent.joinpath('src').glob('*.php'), *ROOT.glob('*.php'),
                    ROOT/'transport.py', ROOT.parent/'console.php', ROOT.parent/'bootstrap.php',
                    ROOT.parent/'composer.json', ROOT.parent/'composer.lock']
    result['source_sha256'] = {str(path.relative_to(ROOT.parent)): hashlib.sha256(path.read_bytes()).hexdigest()
                             for path in sorted(source_files)}
    (ROOT.parents[2] / 'tmp/generated-s3-transport.json').write_text(json.dumps(result, indent=2) + '\n')
    print('PASS generated S3 transport: 18 exact requests; REFERENCE_ONLY')
