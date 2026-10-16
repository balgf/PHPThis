"""Exercise pinned SDK/Guzzle against synthetic loopback HTTP, never AWS."""
import base64
import datetime
import hashlib
import http.server
import json
import io
import math
from pathlib import Path
import shutil
import subprocess
import struct
import threading
import time
import urllib.parse
import wave

ROOT = Path(__file__).resolve().parent
DATA = b'x' * 65_536
CHECKSUM = base64.b64encode(hashlib.sha256(DATA).digest()).decode()
tone_buffer = io.BytesIO()
with wave.open(tone_buffer, 'wb') as writer:
    writer.setnchannels(1)
    writer.setsampwidth(2)
    writer.setframerate(16000)
    writer.writeframes(struct.pack('<16000h', *[round(4096 * math.sin(2 * math.pi * 1000 * i / 16000)) for i in range(16000)]))
TONE_DATA = tone_buffer.getvalue()
TONE_CHECKSUM = base64.b64encode(hashlib.sha256(TONE_DATA).digest()).decode()
TONE_CASES = ['tone_valid', 'tone_oversized', 'tone_chunked_oversized', 'tone_corrupted']
TONE_IDS = {format(14 - i, 'x') * 32: case for i, case in enumerate(TONE_CASES)}
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
        is_tone = self.path.split('/')[-2] == 'e' * 32
        data, checksum = (TONE_DATA, TONE_CHECKSUM) if is_tone else (DATA, CHECKSUM)
        length = int(self.headers.get('Content-Length', '-1'))
        valid = (length == len(data) and self.rfile.read(min(max(length, 0), len(data) + 1)) == data
                 and self.headers.get('If-None-Match') == '*'
                 and self.headers.get('x-amz-checksum-sha256') == checksum
                 and self.headers.get('Content-Type') == 'application/octet-stream'
                 and self.headers.get('x-amz-meta-profile') == 'private-documents-v1'
                 and self.headers.get('x-amz-expected-bucket-owner') == '000000000000'
                 and self.headers.get('x-amz-server-side-encryption') == 'aws:kms'
                 and self.headers.get('x-amz-server-side-encryption-aws-kms-key-id') == KMS)
        if not valid:
            failures.append('put_request')
        observed.append('tone_put' if is_tone else 'put')
        self.send_response(200)
        self.send_header('x-amz-version-id', 'fixture-version')
        self.send_header('x-amz-checksum-sha256', checksum)
        self.send_header('Content-Length', '0')
        self.end_headers()

    def do_HEAD(self):
        url = urllib.parse.urlsplit(self.path)
        if (url.path.split('/')[-2] != 'e' * 32 or urllib.parse.parse_qs(url.query) != {'versionId': ['fixture-version']}
                or self.headers.get('x-amz-checksum-mode') != 'ENABLED'
                or self.headers.get('x-amz-expected-bucket-owner') != '000000000000'):
            failures.append('tone_head_request')
        observed.append('tone_head')
        self.send_response(200)
        for key, value in {
            'x-amz-version-id': 'fixture-version', 'x-amz-checksum-sha256': TONE_CHECKSUM,
            'x-amz-server-side-encryption': 'aws:kms', 'x-amz-server-side-encryption-aws-kms-key-id': KMS,
            'x-amz-server-side-encryption-bucket-key-enabled': 'false', 'x-amz-meta-profile': 'private-documents-v1',
            'Content-Type': 'application/octet-stream', 'Content-Length': str(len(TONE_DATA)),
        }.items():
            self.send_header(key, value)
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
        identity = url.path.split('/')[-2]
        case = TONE_IDS[identity] if identity in TONE_IDS else CASES[int(identity, 16) - 1]
        if (urllib.parse.parse_qs(url.query) != {'versionId': ['fixture-version']}
                or self.headers.get('x-amz-checksum-mode') != 'ENABLED'
                or self.headers.get('x-amz-expected-bucket-owner') != '000000000000'):
            failures.append('get_request')
        observed.append(case)
        data, checksum = (TONE_DATA, TONE_CHECKSUM) if case.startswith('tone_') else (DATA, CHECKSUM)
        case = case.removeprefix('tone_')
        if case == 'timeout':
            time.sleep(1)
        status = {'denied': 403, 'missing': 404, 'redirect': 302}.get(case, 200)
        payload = data
        if case in ['oversized', 'chunked_oversized']:
            payload += b'x'
        if case == 'corrupted':
            payload = b'y' + data[1:]
        if status != 200:
            payload = b'<Error><Code>AccessDenied</Code></Error>'
        self.send_response(status)
        self.send_header('x-amz-version-id', 'wrong' if case == 'wrong_version' else 'fixture-version')
        self.send_header('x-amz-checksum-sha256', base64.b64encode(b'0' * 32).decode() if case == 'wrong_checksum' else checksum)
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
    assert observed == ['put'] + CASES + ['oversized_control', 'tone_put', 'tone_head'] + TONE_CASES, observed
    result = json.loads(run.stdout)
    result['requests_observed'] = len(observed)
    result['automatic_retries_or_redirects'] = 0
    result['verified_at'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    source_files = [*ROOT.parent.joinpath('src').glob('*.php'), *ROOT.parent.joinpath('worker').glob('*.php'), *ROOT.glob('*.php'),
                    ROOT/'transport.py', ROOT/'tone-process.py', ROOT.parent/'console.php', ROOT.parent/'bootstrap.php',
                    ROOT.parent/'composer.json', ROOT.parent/'composer.lock', ROOT.parent/'schema.sql.fixture']
    result['source_sha256'] = {str(path.relative_to(ROOT.parent)): hashlib.sha256(path.read_bytes()).hexdigest()
                             for path in sorted(source_files)}
    producer = json.loads((ROOT.parents[2] / 'tmp/generated-tone-process.json').read_text())
    assert producer['scope'] == 'REFERENCE_ONLY' and producer['aws_calls'] == 0
    assert len(producer['cases']) == 28 and producer['temporary_storage_removed']
    assert all(result['source_sha256'][name] == digest for name, digest in producer['source_sha256'].items()), 'stale tone proof'
    result['producer_process'] = producer
    (ROOT.parents[2] / 'tmp/generated-s3-transport.json').write_text(json.dumps(result, indent=2) + '\n')
    print('PASS generated S3 transport: 24 exact requests; REFERENCE_ONLY')
