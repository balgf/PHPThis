"""Local native multipart proof through the canonical PHPThis front controller."""
import hashlib
import http.client
import json
from pathlib import Path
import re
import shutil
import socket
import subprocess
import time

source = Path('/framework')
fixture = source / 'tools/runtime-proof/issue-104'
app = Path('/tmp/multipart-app')
(app / 'example/public').mkdir(parents=True)
(app / 'uploads').mkdir(mode=0o700)
shutil.copyfile(source / 'autoload.php', app / 'autoload.php')
shutil.copyfile(source / 'example/public/index.php', app / 'example/public/index.php')
shutil.copyfile(source / 'tests/fixtures/multipart-bootstrap.php.fixture', app / 'example/bootstrap.php')
(app / 'src').symlink_to(source / 'src', target_is_directory=True)
(app / 'example/src').symlink_to(source / 'example/src', target_is_directory=True)
fpm = '[global]\npid = /tmp/multipart-fpm.pid\nerror_log = /tmp/multipart-fpm.log\ndaemonize = no\n'
nginx = '''worker_processes 1;
pid /tmp/multipart-nginx.pid;
error_log /tmp/multipart-nginx.log warn;
events { worker_connections 32; }
http {
access_log off;
client_body_temp_path /tmp/client-body;
proxy_temp_path /tmp/proxy;
fastcgi_temp_path /tmp/fastcgi;
uwsgi_temp_path /tmp/uwsgi;
scgi_temp_path /tmp/scgi;
client_max_body_size 256k;
client_body_timeout 2s;
'''
for index, automatic, uploads in [(0, 'off', 'on'), (1, 'on', 'on'), (2, 'off', 'off')]:
    fpm += f'''
[proof{index}]
listen = 127.0.0.1:{9000 + index}
pm = static
pm.max_children = 1
pm.max_requests = 100
catch_workers_output = yes
clear_env = yes
php_admin_flag[enable_post_data_reading] = {automatic}
php_admin_flag[file_uploads] = {uploads}
php_admin_value[post_max_size] = 64
php_admin_value[upload_max_filesize] = 1
php_admin_value[max_input_vars] = 1
php_admin_value[max_file_uploads] = 1
php_admin_value[max_multipart_body_parts] = 1
php_admin_value[max_input_nesting_level] = 4
php_admin_value[upload_tmp_dir] = /tmp/multipart-app/uploads
php_admin_flag[session.upload_progress.enabled] = off
php_admin_flag[mbstring.encoding_translation] = off
php_admin_flag[display_errors] = off
php_admin_flag[display_startup_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /tmp/php-errors.log
php_admin_value[error_reporting] = -1
php_admin_flag[zend.exception_ignore_args] = on
'''
    nginx += f'''server {{
listen 127.0.0.1:{8080 + index};
location / {{
fastcgi_param REQUEST_METHOD $request_method;
fastcgi_param REQUEST_URI $request_uri;
fastcgi_param SCRIPT_FILENAME /tmp/multipart-app/example/public/index.php;
fastcgi_param SCRIPT_NAME /index.php;
fastcgi_param CONTENT_TYPE $content_type;
fastcgi_param CONTENT_LENGTH $content_length;
fastcgi_param REMOTE_ADDR $remote_addr;
fastcgi_request_buffering on;
fastcgi_pass 127.0.0.1:{9000 + index};
fastcgi_read_timeout 3s;
}}
}}
'''
(app / 'fpm.conf').write_text(fpm)
(app / 'nginx.conf').write_text(nginx + '}\n')
def field(name, value='v'):
    return f'--phpthis-test\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'
def upload(name, value='abc', filename='client-private.txt'):
    return f'--phpthis-test\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n{value}\r\n'
end = '--phpthis-test--\r\n'
maximum = ''.join(field('f' + str(i), '') for i in range(32)) + ''.join(upload('u' + str(i), '') for i in range(8))
cases = [
    ('text_only', field('title', 'private-sentinel') + field('empty', '') + end, 200, 1, 8080),
    ('mixed_multiple', field('enabled', 'false') + upload('first') + upload('second', '') + end, 200, 1, 8080),
    ('all_counts_exact', maximum + end, 200, 1, 8080),
    ('duplicate_field_overflow', field('same') * 33 + end, 400, 0, 8080),
    ('duplicate_file_overflow', upload('same') * 9 + end, 400, 0, 8080),
    ('raw_part_overflow', upload('empty', '', '') * 41 + end, 400, 0, 8080),
    ('whole_request_overflow', field('a', 'x' * 131072) + end, 413, 0, 8080),
    ('file_size_error', upload('a', 'x' * 513) + upload('b') + end, 413, 1, 8080),
    ('aggregate_file_overflow', upload('a', 'x' * 350) + upload('b', 'x' * 351) + end, 413, 0, 8080),
    ('nested_fields', field('a[]') + end, 400, 0, 8080),
    ('collision', field('a') + upload('a') + end, 400, 0, 8080),
    ('normalized_duplicates', field('a.b', 'first') + field('a b', 'last') + end, 200, 1, 8080),
    ('missing_closing_boundary', field('title'), 200, 1, 8080),
    ('discarded_malformed_file', upload('bad[') + field('title') + end, 200, 1, 8080),
    ('partial_file', upload('a'), 400, 1, 8080),
    ('automatic_parsing_rejected', field('title') + end, 500, 0, 8081),
    ('uploads_disabled_rejected', field('title') + end, 500, 0, 8082),
]
children, logs, results = [], [], []
try:
    for name, command, port in [
        ('fpm', ['php-fpm', '--nodaemonize', '--fpm-config', str(app / 'fpm.conf')], 9000),
        ('nginx', ['nginx', '-c', str(app / 'nginx.conf'), '-g', 'daemon off;'], 8080),
    ]:
        log = open('/tmp/' + name + '-process.log', 'wb')
        logs.append(log)
        child = subprocess.Popen(command, stdout=log, stderr=log)
        children.append(child)
        deadline = time.monotonic() + 5
        while True:
            if child.poll() is not None:
                raise RuntimeError(name + ' exited during startup')
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.2):
                    break
            except OSError:
                if time.monotonic() > deadline:
                    raise RuntimeError(name + ' startup timed out')
                time.sleep(0.05)
    for name, payload, status, calls, port in cases:
        log_path = Path('/tmp/php-errors.log')
        before = log_path.read_text() if log_path.exists() else ''
        connection = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
        try:
            connection.request('POST', '/inspect/7', body=payload.encode(), headers={'Content-Type': 'multipart/form-data; boundary=phpthis-test'})
            response = connection.getresponse()
            result = json.loads(response.read(262145))
            if response.status != status:
                raise RuntimeError(f'{name}: unexpected HTTP status {response.status}')
            if not re.fullmatch('[a-f0-9]{32}', response.getheader('X-Request-ID', '')):
                raise RuntimeError(name + ': missing correlation')
            if status in [400, 413] and result != {'code': 'invalid_request' if status == 400 else 'request_body_too_large'}:
                raise RuntimeError(name + ': failure not generic')
            if status == 200:
                if result['route'] != 7 or result['settings'] != {'automatic': '0', 'uploads': '1'}:
                    raise RuntimeError(name + ': route or effective SAPI settings mismatch')
                for file in result['files'].values() if isinstance(result['files'], dict) else []:
                    if file['error'] == 'Success' and file['bytes'] != file['actual']:
                        raise RuntimeError(name + ': native upload size/provenance mismatch')
            expected_fields = {
                'text_only': {'title': 'private-sentinel', 'empty': ''},
                'mixed_multiple': {'enabled': 'false'},
                'normalized_duplicates': {'a_b': 'last'},
                'missing_closing_boundary': {'title': 'v'},
                'discarded_malformed_file': {'title': 'v'},
            }
            if name in expected_fields and result['fields'] != expected_fields[name]:
                raise RuntimeError(name + ': normalized fields changed')
            if name == 'mixed_multiple' and result['files'] != {'first': {'error': 'Success', 'bytes': 3, 'actual': 3}, 'second': {'error': 'Success', 'bytes': 0, 'actual': 0}}:
                raise RuntimeError('Mixed file representation changed')
            after = log_path.read_text()
            if after.count('multipart.fixture_handler') - before.count('multipart.fixture_handler') != calls or after.count('application.request_summary') - before.count('application.request_summary') != 1:
                raise RuntimeError(name + ': handler/terminal evidence mismatch')
            results.append({'case': name, 'status': status, 'handler_calls': calls, 'result': 'pass'})
        finally:
            connection.close()
    before = Path('/tmp/php-errors.log').read_text()
    with socket.create_connection(('127.0.0.1', 8080), timeout=3) as client:
        client.sendall(b'POST /inspect/7 HTTP/1.1\r\nHost: localhost\r\nContent-Type: multipart/form-data; boundary=phpthis-test\r\nContent-Length: 999\r\nConnection: close\r\n\r\n' + (field('title') + end).encode())
        client.shutdown(socket.SHUT_WR)
        response = client.recv(8192)
        if response != b'' or Path('/tmp/php-errors.log').read_text() != before:
            raise RuntimeError('Short HTTP body was not closed before PHP dispatch')
    results.append({'case': 'short_http_body', 'outcome': 'connection_closed_before_php', 'result': 'pass'})
    if list((app / 'uploads').iterdir()):
        raise RuntimeError('Native temporary files survived request termination')
    log = Path('/tmp/php-errors.log').read_text()
    if 'client-private' in log or 'private-sentinel' in log:
        raise RuntimeError('Untrusted payload entered the private log')
    paths = [
        'autoload.php', 'example/public/index.php', 'tests/fixtures/multipart-bootstrap.php.fixture',
        'src/Http/MultipartForm.php', 'src/Http/MultipartLimits.php', 'src/Http/Request.php',
        'src/Http/RequestReader.php', 'src/Http/RequestBoundary.php',
        'example/src/Observability/TerminalRequestCoordinator.php',
    ] + [str(path.relative_to(source)) for path in sorted(fixture.iterdir()) if path.name != 'evidence.json' and path.is_file()]
    print(json.dumps({
        'scope': 'Canonical PHPThis front controller in local Nginx/FPM; synthetic inspection action; no production or storage-profile certification',
        'php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True),
        'nginx': subprocess.check_output(['nginx', '-v'], stderr=subprocess.STDOUT, text=True).strip(),
        'source_sha256': {path: hashlib.sha256((source / path).read_bytes()).hexdigest() for path in paths},
        'cases': results, 'temporary_cleanup': 'verified', 'redaction': 'verified',
        'limits': {'request_bytes': 131072, 'file_bytes': 512, 'total_file_bytes': 700, 'fields': 32, 'files': 8, 'parts': 40},
    }, indent=2))
finally:
    for child in reversed(children):
        child.terminate()
        try:
            child.wait(timeout=3)
        except subprocess.TimeoutExpired:
            child.kill()
            child.wait(timeout=3)
    for log in logs:
        log.close()
