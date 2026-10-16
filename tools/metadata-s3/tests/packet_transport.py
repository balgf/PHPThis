"""Actual pinned SDK/cURL wire evidence, including native PHP upload handles."""
import base64
import hashlib
import http.server
import json
import shutil
import sqlite3
import subprocess
import threading
import time
import urllib.parse
from pathlib import Path


def verify_transport(root, php_port, request, valid):
    observed, failures, objects = [], [], {}
    here = Path(__file__).resolve().parent
    kms = 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001'

    class Peer(http.server.BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def identity(self):
            url = urllib.parse.urlsplit(self.path)
            if self.headers.get('x-amz-expected-bucket-owner') != '000000000000':
                failures.append('account')
            if 'private-sentinel' in str(self.headers):
                failures.append('private-description')
            return url.path.removeprefix('/fixture-bucket/'), urllib.parse.parse_qs(url.query, keep_blank_values=True)

        def do_PUT(self):
            key, query = self.identity()
            role = key.rsplit('/', 1)[-1]
            observed.append('Put:' + role)
            count = int(self.headers.get('Content-Length', '-1'))
            if count < 1 or count > 65536:
                failures.append('body-bound')
                self.send_error(400)
                return
            body = self.rfile.read(count)
            checksum = base64.b64encode(hashlib.sha256(body).digest()).decode()
            metadata = {name.lower().removeprefix('x-amz-meta-'): value for name, value in self.headers.items() if name.lower().startswith('x-amz-meta-')}
            if (query or self.headers.get('If-None-Match') != '*' or self.headers.get('x-amz-checksum-sha256') != checksum
                    or self.headers.get('x-amz-server-side-encryption') != 'aws:kms'
                    or self.headers.get('x-amz-server-side-encryption-aws-kms-key-id') != kms
                    or self.headers.get('x-amz-storage-class') != 'STANDARD'
                    or self.headers.get('x-amz-server-side-encryption-bucket-key-enabled') != 'false'
                    or self.headers.get('Content-Type') != 'application/octet-stream'
                    or metadata.get('profile') != 'private-document-packet-v1' or metadata.get('role') != role
                    or set(metadata) != {'profile', 'operation', 'role', 'ownership'}
                    or key != 'private-documents/v1/packets/' + metadata.get('operation', '') + '/' + role):
                failures.append('put-contract')
            objects[key] = {'body': body, 'version': 'wire-' + role, 'checksum': checksum, 'metadata': metadata}
            self.send_response(200)
            self.send_header('x-amz-version-id', 'wire-' + role)
            self.send_header('x-amz-checksum-sha256', checksum)
            self.send_header('Content-Length', '0')
            self.end_headers()

        def do_HEAD(self):
            key, query = self.identity()
            observed.append('Head:' + key.rsplit('/', 1)[-1])
            obj = objects[key]
            if query != {'versionId': [obj['version']]} or self.headers.get('x-amz-checksum-mode') != 'ENABLED':
                failures.append('head-contract')
            self.send_response(200)
            for name, value in reversed(list(obj['metadata'].items())):
                self.send_header('x-amz-meta-' + name, value)
            for name, value in {'Content-Length': str(len(obj['body'])), 'Content-Type': 'application/octet-stream',
                                'x-amz-version-id': obj['version'], 'x-amz-checksum-sha256': obj['checksum'],
                                'x-amz-server-side-encryption': 'aws:kms', 'x-amz-server-side-encryption-aws-kms-key-id': kms,
                                'x-amz-server-side-encryption-bucket-key-enabled': 'false'}.items():
                self.send_header(name, value)
            self.end_headers()

        def do_GET(self):
            _, query = self.identity()
            key = query.get('prefix', [''])[0]
            observed.append('List:' + key.rsplit('/', 1)[-1])
            if query != {'versions': [''], 'prefix': [key], 'max-keys': ['2']}:
                failures.append('list-contract')
            obj = objects.get(key)
            version = '' if obj is None else (f'<Version><Key>{key}</Key><VersionId>{obj["version"]}</VersionId>'
                                             f'<IsLatest>true</IsLatest><Size>{len(obj["body"])}</Size></Version>')
            body = ('<ListVersionsResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><IsTruncated>false</IsTruncated>'
                    + version + '</ListVersionsResult>').encode()
            self.send_response(200)
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def do_DELETE(self):
            key, query = self.identity()
            observed.append('Delete:' + key.rsplit('/', 1)[-1])
            obj = objects[key]
            if query != {'versionId': [obj['version']]}:
                failures.append('delete-contract')
            del objects[key]
            self.send_response(204)
            self.send_header('x-amz-version-id', obj['version'])
            self.end_headers()

    with http.server.ThreadingHTTPServer(('127.0.0.1', 0), Peer) as server:
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            (root / 'plan.json').write_text(json.dumps({'fault': '', 'loopback_port': server.server_port}))
            status, data = request(php_port, valid())
            if status != 201:
                raise RuntimeError('Packet wire upload failed: status ' + str(status))
            identity = json.loads(data)['id']
            if request(php_port, valid())[0] != 200 or len(observed) != 4:
                raise RuntimeError('Packet wire repeat resent a write')
            with sqlite3.connect(root / 'documents.sqlite') as db:
                db.execute("UPDATE document_packets SET state='reconciliation_required', primary_state='stored', supporting_state='stored'")
            def maintain(command):
                result = subprocess.run([shutil.which('php'), '-d', 'display_errors=0', str(here / 'packet-worker.php'), command, identity, '1'],
                                        cwd=root, env={'PATH': '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'}, capture_output=True, timeout=10)
                if result.returncode or result.stderr:
                    raise RuntimeError('Packet wire maintenance failed')
                return json.loads(result.stdout)['outcome']
            if maintain('reconcile-packet') != 'reconciled':
                raise RuntimeError('Packet wire recovery failed')
            with sqlite3.connect(root / 'documents.sqlite') as db:
                now = int(time.time())
                db.execute('UPDATE document_packets SET created_at=?, expires_at=?', (now - 86400, now))
            if maintain('purge-packet') != 'deleted':
                raise RuntimeError('Packet wire deletion failed')
        finally:
            server.shutdown()
            thread.join(timeout=2)
    expected = ['Put:primary', 'Head:primary', 'Put:supporting', 'Head:supporting',
                'List:primary', 'Head:primary', 'List:supporting', 'Head:supporting',
                'Delete:primary', 'List:primary', 'Delete:supporting', 'List:supporting']
    if failures or observed != expected or objects:
        raise RuntimeError('Packet wire assertions failed: ' + str(failures) + '; ' + str(observed))
    return len(observed)
