"""Check the actual pinned SDK/cURL control path against a synthetic loopback peer."""
import http.server
from pathlib import Path
import shutil
import subprocess
import threading
import time
import urllib.parse

ROOT = Path(__file__).resolve().parent
KEY = 'private-documents/v1/' + 'a' * 32 + '/content'
CASES = ['valid', 'oversized', 'redirect', 'denied', 'timeout', 'truncated', 'sibling', 'valid_generated']
observed = []
failures = []


class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        case = CASES[len(observed)] if len(observed) < len(CASES) else 'unexpected'
        observed.append(case)
        query = urllib.parse.parse_qs(urllib.parse.urlsplit(self.path).query, keep_blank_values=True)
        if (query != {'versions': [''], 'prefix': [KEY], 'max-keys': ['2']}
                or self.headers.get('x-amz-expected-bucket-owner') != '000000000000'
                or 'private-sentinel' in str(self.headers)):
            failures.append('request_authority_or_private_metadata')
        if case == 'timeout':
            time.sleep(1)
        status = {'redirect': 302, 'denied': 403}.get(case, 200)
        key = KEY + '-other' if case == 'sibling' else KEY
        truncated = 'true' if case == 'truncated' else 'false'
        payload = (f'<ListVersionsResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/">'
                   f'<IsTruncated>{truncated}</IsTruncated><Version><Key>{key}</Key>'
                   '<VersionId>fixture-version</VersionId><IsLatest>true</IsLatest><Size>1</Size>'
                   '</Version></ListVersionsResult>').encode()
        if case == 'oversized':
            payload = b'x' * 20000
        if status != 200:
            payload = b'<Error><Code>AccessDenied</Code></Error>'
        self.send_response(status)
        self.send_header('Content-Length', str(len(payload)))
        if case == 'redirect':
            self.send_header('Location', 'http://127.0.0.1:1/forbidden')
        try:
            self.end_headers()
            self.wfile.write(payload)
        except (BrokenPipeError, ConnectionResetError):
            pass

    def do_DELETE(self):
        observed.append('delete')
        url = urllib.parse.urlsplit(self.path)
        if (url.path != '/fixture-bucket/' + KEY
                or urllib.parse.parse_qs(url.query) != {'versionId': ['fixture-version']}
                or self.headers.get('x-amz-expected-bucket-owner') != '000000000000'):
            failures.append('delete_authority')
        self.send_response(204)
        self.send_header('x-amz-version-id', 'fixture-version')
        self.end_headers()


with http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler) as server:
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        result = subprocess.run([shutil.which('php'), '-d', 'display_errors=0', '-d', 'memory_limit=64M',
                                 str(ROOT / 'transport.php'), str(server.server_port)], cwd=ROOT,
                                env={'PATH': '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'}, capture_output=True, timeout=15)
    finally:
        server.shutdown()
        thread.join(timeout=2)
    if result.returncode != 0 or result.stderr or failures or observed != CASES + ['delete']:
        raise RuntimeError(f'Metadata S3 transport failed: {result.stderr!r}; {failures}; {observed}')
    print(result.stdout.decode(), end='')
