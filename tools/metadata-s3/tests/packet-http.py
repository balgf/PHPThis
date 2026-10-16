"""Two-file real PHP-SAPI proof with durable SQLite and a synthetic, private S3 peer."""
import fcntl
import hashlib
import http.client
import json
import os
from pathlib import Path
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
from packet_transport import verify_transport

HERE = Path(__file__).resolve().parent
SCHEMA = (HERE.parent / 'schema.sql.fixture').read_text()
BOUNDARY = b'packet-proof'
END = b'--' + BOUNDARY + b'--\r\n'
RETRY = 'a' * 32
SUCCESS = ['PutObject:primary', 'HeadObject:primary', 'PutObject:supporting', 'HeadObject:supporting']


def require(condition, case):
    if not condition:
        raise RuntimeError('Packet HTTP proof: ' + case)


def field(name, value):
    value = value.encode() if isinstance(value, str) else value
    return b'--' + BOUNDARY + b'\r\nContent-Disposition: form-data; name="' + name.encode() + b'"\r\n\r\n' + value + b'\r\n'


def upload(role, data):
    return b'--' + BOUNDARY + b'\r\nContent-Disposition: form-data; name="' + role.encode() + b'"; filename="../../private-sentinel.svg"\r\nContent-Type: text/html\r\n\r\n' + data + b'\r\n'


def valid(label='private-sentinel label', note=None, retry=RETRY, primary=b'primary', supporting=b'supporting', reverse=False):
    files = upload('primary', primary) + upload('supporting', supporting)
    if reverse:
        files = upload('supporting', supporting) + upload('primary', primary)
    return field('label', label) + field('request_id', retry) + (b'' if note is None else field('note', note)) + files + END


def rows(root):
    with sqlite3.connect(root / 'documents.sqlite') as db:
        db.row_factory = sqlite3.Row
        return [dict(row) for row in db.execute('SELECT * FROM document_packets ORDER BY rowid')]


def plan(root, fault='', crash_on=''):
    (root / 'plan.json').write_text(json.dumps({'fault': fault, 'crash_on': crash_on}))


def reset(root, fault='', trigger=None):
    (root / 'documents.sqlite').unlink(missing_ok=True)
    with sqlite3.connect(root / 'documents.sqlite') as db:
        db.executescript(SCHEMA)
        if trigger:
            db.executescript(trigger)
    os.chmod(root / 'documents.sqlite', 0o600)
    (root / 'packet-remote.json').write_text('{}')
    os.chmod(root / 'packet-remote.json', 0o600)
    plan(root, fault)


def request(port, body, auth='Bearer synthetic-owner-one', path='/document-packets', media=None):
    headers = {'Content-Type': media or 'multipart/form-data; boundary=packet-proof', 'Content-Length': str(len(body))}
    if auth is not None:
        headers['Authorization'] = auth
    connection = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    try:
        connection.request('POST', path, body, headers)
        response = connection.getresponse()
        data = response.read(4097)
        require(len(data) <= 4096 and b'private-sentinel' not in data, 'bounded public response')
        require(response.getheader('Cache-Control') == 'private, no-store', 'private response')
        require(re.fullmatch('[0-9a-f]{32}', response.getheader('X-Request-ID', '')) is not None, 'correlation')
        return response.status, data
    finally:
        connection.close()


def worker(root, command, identity, expected=0, owner=1):
    result = subprocess.run([shutil.which('php'), '-d', 'display_errors=0', '-d', 'memory_limit=64M',
                             str(HERE / 'packet-worker.php'), command, identity, str(owner)], cwd=root,
                            env={'PATH': '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'}, capture_output=True, timeout=5)
    require(result.returncode == expected and result.stderr == b'', 'fresh worker exit and redaction')
    receipt = json.loads((root / 'worker-receipt.json').read_text())
    require(all(not call.startswith('PutObject') for call in receipt['calls']), 'maintenance never resends Put')
    require(receipt.get('statements', 0) <= 32 and len(receipt['calls']) <= (8 if command == 'purge-packet' else 4), 'worker resource bounds')
    return receipt


def run():
    cases = []
    with tempfile.TemporaryDirectory(prefix='packet-http-', dir=HERE.parents[2] / 'tmp') as temporary:
        root = Path(temporary)
        os.chmod(root, 0o700)
        (root / 'uploads').mkdir(mode=0o700)
        (root / 'documents.lock').touch(mode=0o600)
        reset(root)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        command = [shutil.which('php'), '-d', 'enable_post_data_reading=0', '-d', 'file_uploads=1', '-d', 'display_errors=0',
                   '-d', 'display_startup_errors=0', '-d', 'log_errors=1', '-d', 'error_reporting=-1', '-d', 'zend.exception_ignore_args=1',
                   '-d', 'memory_limit=64M', '-d', 'max_execution_time=10', '-d', 'mbstring.encoding_translation=0',
                   '-d', 'upload_tmp_dir=' + str(root / 'uploads'), '-S', f'127.0.0.1:{port}', str(HERE / 'router.php')]
        with (root / 'server.log').open('wb') as log:
            process = subprocess.Popen(command, cwd=root, env={'PATH': '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'}, stdout=log, stderr=log)
            try:
                for _ in range(100):
                    require(process.poll() is None, 'server startup')
                    try:
                        with socket.create_connection(('127.0.0.1', port), timeout=.1):
                            break
                    except OSError:
                        time.sleep(.02)
                else:
                    raise RuntimeError('Packet server startup timeout')

                def case(name, body, expected, calls=(), keep=False, fault='', trigger=None, auth='Bearer synthetic-owner-one', media=None):
                    if not keep:
                        reset(root, fault, trigger)
                    before = rows(root)
                    status, data = request(port, body, auth, media=media)
                    receipt = json.loads((root / 'receipt.json').read_text())
                    require(status == expected, f'{name} expected {expected}, got {status}')
                    require(receipt['packet_calls'] == list(calls), name + ' exact remote sequence')
                    require(receipt['statements'] == 0 and receipt['packet_statements'] <= 32, name + ' separate bounded database source')
                    require(receipt['summary_attempts'] == 1 and 'private-sentinel' not in receipt['summary'], name + ' terminal redaction')
                    after = rows(root)
                    if expected in (400, 401, 403, 413, 415, 422) or 'bad_' in fault:
                        require(before == after and receipt['packet_statements'] == 0, name + ' no protected SQL')
                    if expected == 201:
                        row = after[-1]
                        require(len(after) == len(before) + 1 and row['state'] == 'accepted', name + ' one accepted packet')
                        require(row['primary_state'] == row['supporting_state'] == 'verified', name + ' both versions verified')
                        require(receipt['packet_seen'] and receipt['packet_statements'] == 14, name + ' durable ownership and constant work')
                        require(json.loads(data) == {'id': row['packet_id'], 'status': 'accepted'}, name + ' finite receipt')
                        objects = json.loads((root / 'packet-remote.json').read_text())
                        for role in ('primary', 'supporting'):
                            key = f"private-documents/v1/packets/{row['packet_id']}/{role}"
                            require(objects[key]['metadata'] == {'profile': 'private-document-packet-v1', 'operation': row['packet_id'],
                                                                'role': role, 'ownership': row[role + '_nonce']}, name + ' exact role marker')
                            require(row[role + '_version'] == 'version-' + role, name + ' exact returned version')
                    require(not list((root / 'uploads').iterdir()), name + ' PHP temporary cleanup')
                    cases.append(name)
                    return after, data

                case('complete-packet', valid(), 201, SUCCESS)
                original = rows(root)[0]['packet_id']
                _, replay = case('lost-response-repeat', valid(), 200, keep=True)
                require(json.loads(replay)['id'] == original and len(rows(root)) == 1, 'same result no duplicate reservation')
                for name, body in [('changed-label', valid(label='changed')), ('changed-note', valid(note='')),
                                   ('changed-primary', valid(primary=b'changed')), ('changed-supporting', valid(supporting=b'changed')),
                                   ('swapped-file-roles', valid(primary=b'supporting', supporting=b'primary'))]:
                    case(name, body, 409, keep=True)
                case('same-retry-different-owner', valid(), 201, SUCCESS, keep=True, auth='Bearer synthetic-owner-two')
                require(rows(root)[1]['packet_id'] != original and rows(root)[1]['owner_id'] == 2, 'owner namespace')
                case('reverse-part-order', valid(reverse=True), 201, SUCCESS)
                case('exact-byte-limits', valid(label='é' * 60, note='é' * 512, primary=b'p' * 65536, supporting=b's' * 65536), 201, SUCCESS)
                case('empty-note', valid(note=''), 201, SUCCESS)
                require(rows(root)[0]['note'] == '', 'empty note retained')
                case('exact-whitespace', valid(label='  private-sentinel  '), 201, SUCCESS)
                require(rows(root)[0]['label'] == '  private-sentinel  ', 'no implicit normalization')
                for name, body, expected in [
                    ('missing-primary', field('label', 'ok') + field('request_id', RETRY) + upload('supporting', b's') + END, 400),
                    ('missing-supporting', field('label', 'ok') + field('request_id', RETRY) + upload('primary', b'p') + END, 400),
                    ('unknown-file', valid()[:-len(END)] + upload('third', b'x') + END, 400),
                    ('unknown-before-values', field('label', '') + field('request_id', RETRY) + field('owner_id', 'secret') + upload('primary', b'p') + upload('supporting', b's') + END, 400),
                    ('nested-field', field('label[]', 'x') + field('request_id', RETRY) + upload('primary', b'p') + upload('supporting', b's') + END, 400),
                    ('missing-retry', field('label', 'ok') + upload('primary', b'p') + upload('supporting', b's') + END, 400),
                    ('empty-label', valid(label=''), 422), ('label-too-long', valid(label='x' * 121), 422),
                    ('invalid-utf8', valid(label=b'\xff'), 422), ('control-label', valid(label='a\nb'), 422),
                    ('control-note', valid(note='a\x00b'), 422), ('uppercase-retry', valid(retry='A' * 32), 422),
                    ('short-retry', valid(retry='a' * 31), 422), ('long-retry', valid(retry='a' * 33), 422),
                    ('empty-primary', valid(primary=b''), 422), ('empty-supporting', valid(supporting=b''), 422),
                    ('oversized-primary', valid(primary=b'x' * 65537), 413), ('oversized-supporting', valid(supporting=b'x' * 65537), 413),
                    ('oversized-note', valid(note='x' * 1025), 413), ('aggregate-fields', valid(label='x' * 121, note='x' * 1024), 413),
                    ('raw-request-cap', valid(primary=b'x' * 196608), 413),
                    ('native-part-count', field('label', 'x') * 33 + field('request_id', RETRY) + upload('primary', b'p') + upload('supporting', b's') + END, 400)]:
                    case(name, body, expected)
                case('no-auth', valid(), 401, auth=None)
                case('denied', valid(), 403, auth='Bearer denied')
                case('auth-before-values', valid(label=''), 401, auth=None)
                case('wrong-media', b'{}', 415, media='application/json')
                for role in ('primary', 'supporting'):
                    for fault in ('bad_path', 'bad_size'):
                        case(role + '-' + fault, valid(), 500, fault=role + ':' + fault)
                case('native-duplicate-last-role', field('label', 'private-sentinel') + field('request_id', RETRY)
                     + upload('primary', b'old') + upload('primary', b'primary') + upload('supporting', b'supporting') + END, 201, SUCCESS)
                for role in ('primary', 'supporting'):
                    for fault in ('put400', 'put403', 'put409', 'put500', 'put_timeout', 'lost_put', 'null_version', 'checksum', 'put412'):
                        calls = ['PutObject:primary'] if role == 'primary' else SUCCESS[:3]
                        case(role + '-' + fault, valid(), 500, calls, fault=role + ':' + fault)
                        record = rows(root)[0]
                        require(record['state'] != 'accepted' and record['label'] is not None, 'write failure retains reservation')
                        case(role + '-' + fault + '-repeat', valid(), 202, keep=True)
                    for fault in ('head_denied', 'wrong_nonce', 'wrong_role', 'wrong_packet', 'extra_metadata', 'wrong_kms', 'storage_class'):
                        case(role + '-' + fault, valid(), 500, SUCCESS[:2] if role == 'primary' else SUCCESS, fault=role + ':' + fault)
                for name, condition, calls in [
                    ('reservation-failure', None, []),
                    ('primary-claim-failure', "NEW.primary_state = 'write_started'", []),
                    ('primary-stored-failure', "NEW.primary_state = 'stored'", SUCCESS[:1]),
                    ('primary-verified-failure', "NEW.primary_state = 'verified'", SUCCESS[:2]),
                    ('supporting-claim-failure', "NEW.supporting_state = 'write_started'", SUCCESS[:2]),
                    ('supporting-stored-failure', "NEW.supporting_state = 'stored'", SUCCESS[:3]),
                    ('supporting-verified-failure', "NEW.supporting_state = 'verified'", SUCCESS),
                    ('acceptance-failure', "NEW.state = 'accepted'", SUCCESS)]:
                    trigger = ("CREATE TRIGGER fail_packet BEFORE INSERT ON document_packets BEGIN SELECT RAISE(ABORT, 'synthetic'); END;"
                               if condition is None else f"CREATE TRIGGER fail_packet BEFORE UPDATE ON document_packets WHEN {condition} BEGIN SELECT RAISE(ABORT, 'synthetic'); END;")
                    case(name, valid(), 500, calls, trigger=trigger)
                    if condition is not None:
                        require(rows(root)[0]['state'] != 'accepted', name + ' non-serving state')
                case('expired-at-acceptance', valid(), 500, SUCCESS, fault='expire_after_supporting_head')
                case('expired-repeat', valid(), 410, keep=True)
                reset(root)
                with (root / 'documents.lock').open('rb') as held:
                    fcntl.flock(held, fcntl.LOCK_EX | fcntl.LOCK_NB)
                    case('concurrent-writer-rejected-before-reservation', valid(), 503, keep=True)
                for index in range(4):
                    case('packet-quota-fill-' + str(index), valid(retry=f'{index:032x}'), 201, SUCCESS, keep=index != 0)
                case('packet-owner-quota', valid(), 503, keep=True)
                single = field('label', 'private-sentinel') + upload('document', b'x') + END
                require(request(port, single, path='/documents')[0] == 503, 'one-file route cannot bypass packet quota')
                cases.append('shared-quota-single-after-packets')
                reset(root)
                for _ in range(7):
                    require(request(port, single, path='/documents')[0] == 201, 'seed single-file quota')
                case('shared-quota-packet-after-singles', valid(), 503, keep=True)

                # Real fresh-process interrupted reconciliation and deletion, using retained provider state.
                case('lost-second-write-for-recovery', valid(), 500, SUCCESS[:3], fault='supporting:lost_put')
                identity = rows(root)[0]['packet_id']
                plan(root, crash_on='HeadObject:supporting')
                worker(root, 'reconcile-packet', identity, expected=99)
                require(rows(root)[0]['state'] != 'accepted', 'worker death cannot expose a partial packet')
                plan(root)
                require(worker(root, 'reconcile-packet', identity)['outcome'] == 'reconciled', 'new process resumes interrupted recovery')
                case('recovered-submission-repeat', valid(), 200, keep=True)
                with sqlite3.connect(root / 'documents.sqlite') as db:
                    now = int(time.time())
                    db.execute('UPDATE document_packets SET created_at=?, expires_at=?', (now - 86400, now))
                plan(root, crash_on='DeleteObject:primary')
                worker(root, 'purge-packet', identity, expected=99)
                require(rows(root)[0]['primary_delete_attempts'] == 1, 'deletion claim survives process termination')
                plan(root)
                require(worker(root, 'purge-packet', identity)['outcome'] == 'deleted', 'new process completes exact cleanup')
                require(rows(root)[0]['primary_delete_attempts'] == 2 and rows(root)[0]['label'] is None, 'persisted attempt accounting and description erasure')
                case('deleted-submission-repeat', valid(), 410, keep=True)
                cases.append('fresh-process-crash-recovery-and-cleanup')
                reset(root)
                require(verify_transport(root, port, request, valid) == 12, 'actual SDK and cURL packet wire contract')
                cases.append('native-upload-and-12-SDK-loopback-requests')
            finally:
                process.terminate()
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=5)
        native_log = (root / 'server.log').read_bytes()
        require(len(native_log) <= 262144 and b'private-sentinel' not in native_log, 'bounded redacted native log')
    receipt = {'status': 'PASS', 'cases': cases, 'case_count': len(cases), 'evidence': 'REAL_PHP_HTTP_SQLITE_SYNTHETIC_SDK', 'aws_calls': 0}
    (HERE.parents[2] / 'tmp/packet-s3-http.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print(f'PASS packet HTTP: {len(cases)} cases; fresh-process recovery; no AWS calls')


if __name__ == '__main__':
    run()
