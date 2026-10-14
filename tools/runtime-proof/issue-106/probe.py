"""Container-only HTTP/1.1 proof; all identity and credential data are synthetic."""
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
fixture = source / 'tools/runtime-proof/issue-106'
app = Path('/tmp/machine-app')
(app / 'example/public').mkdir(parents=True)
shutil.copyfile(source / 'autoload.php', app / 'autoload.php')
shutil.copyfile(source / 'example/public/index.php', app / 'example/public/index.php')
shutil.copyfile(fixture / 'bootstrap.php.fixture', app / 'example/bootstrap.php')
(app / 'src').symlink_to(source / 'src', target_is_directory=True)
(app / 'example/src').symlink_to(source / 'example/src', target_is_directory=True)
Path('/tmp/machine-config').mkdir()
for config in ['nginx.conf', 'fpm.conf', 'fastcgi.conf', 'headers.js']:
    shutil.copyfile(fixture / config, Path('/tmp/machine-config') / config)

credential = 'mk1_' + 'a' * 64
valid = [('Example-Machine-Key', credential)]
target = '/accounts/42/documents/Doc_Visible'
# name, raw field pairs, target, body, expected status, reached PHP
cases = [
    ('single', valid, target, b'', 200, True),
    ('field_name_case', [('eXaMpLe-MaChInE-kEy', credential)], target, b'', 200, True),
    ('http_field_whitespace', [('Example-Machine-Key', ' ' + credential + ' ')], target, b'', 200, True),
    ('missing', [], target, b'', 403, True),
    ('malformed', [('Example-Machine-Key', 'mk1_bad')], target, b'', 403, True),
    ('wrong_secret', [('Example-Machine-Key', 'mk1_' + 'b' * 64)], target, b'', 403, True),
    ('over_profile_bound', [('Example-Machine-Key', credential + 'a')], target, b'', 403, True),
    ('duplicate_identical', valid + valid, target, b'', 403, False),
    ('duplicate_case', valid + [('example-machine-key', credential)], target, b'', 403, False),
    ('duplicate_different', valid + [('Example-Machine-Key', 'mk1_bad')], target, b'', 403, False),
    ('duplicate_empty', valid + [('Example-Machine-Key', '')], target, b'', 403, False),
    ('underscore_alias', [('Example_Machine_Key', credential)], target, b'', 403, False),
    ('alias_collision', valid + [('Example_Machine_Key', credential)], target, b'', 403, False),
    ('dot_alias', [('Example.Machine.Key', credential)], target, b'', 403, False),
    ('dot_alias_collision', valid + [('Example.Machine.Key', credential)], target, b'', 403, False),
    ('bearer_conflict', valid + [('Authorization', 'Bearer SyntheticBearerMarker')], target, b'', 403, False),
    ('empty_bearer_conflict', valid + [('Authorization', '')], target, b'', 403, False),
    ('empty_cookie_conflict', valid + [('Cookie', '')], target, b'', 403, False),
    ('alternate_source_only', [('X-API-Key', credential)], target, b'', 403, False),
    ('proxy_authorization', valid + [('Proxy-Authorization', credential)], target, b'', 403, False),
    ('query_credential', valid, target + '?access_token=' + credential, b'', 403, True),
    ('nested_query', valid, target + '?api_key[nested]=' + credential, b'', 403, True),
    ('body_credential', valid, target, b'{"credential":"SyntheticBodyMarker"}', 403, True),
    ('raw_field_maximum', valid + [('X-Padding-' + str(i), 'x') for i in range(61)], target, b'', 200, True),
    ('raw_field_count', valid + [('X-Padding-' + str(i), 'x') for i in range(62)], target, b'', 403, False),
    ('native_header_limit', [('Example-Machine-Key', 'a' * 8193)], target, b'', 400, False),
]
children = []
logs = []
results = []
try:
    for name, command, port in [
        ('fpm', ['php-fpm', '--nodaemonize', '--fpm-config', '/tmp/machine-config/fpm.conf'], 9000),
        ('nginx', ['nginx', '-c', '/tmp/machine-config/nginx.conf', '-g', 'daemon off;'], 8080),
    ]:
        log = open('/tmp/' + name + '-process.log', 'wb')
        logs.append(log)
        child = subprocess.Popen(command, stdout=log, stderr=log)
        children.append(child)
        deadline = time.monotonic() + 5
        while True:
            if child.poll() is not None:
                # No request has been sent yet: retain bounded configuration diagnostics.
                detail = Path('/tmp/' + name + '-process.log').read_text()[:2048]
                raise RuntimeError(name + ' exited during startup: ' + detail)
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.2):
                    break
            except OSError:
                if time.monotonic() >= deadline:
                    raise RuntimeError(name + ' startup timed out')
                time.sleep(0.05)
    for name, headers, path, request_body, status, php in cases:
        connection = http.client.HTTPConnection('127.0.0.1', 8080, timeout=3)
        try:
            connection.putrequest('GET', path, skip_accept_encoding=True)
            for key, value in headers:
                connection.putheader(key, value)
            connection.putheader('Content-Length', str(len(request_body)))
            connection.endheaders(request_body)
            response = connection.getresponse()
            body = response.read(4097)
            if len(body) > 4096 or response.status != status:
                raise RuntimeError('Unexpected response status or bound for ' + name)
            expected = {'data': {'account_id': 42, 'key': 'Doc_Visible', 'title': 'Machine document'}} if status == 200 else {
                'error': {'code': 'forbidden' if status == 403 else 'invalid_request',
                          'message': 'Request is forbidden.' if status == 403 else 'Request is invalid.'}}
            try:
                representation = json.loads(body)
            except json.JSONDecodeError:
                raise RuntimeError('Non-JSON response for ' + name) from None
            if representation != expected:
                raise RuntimeError('Unexpected response representation for ' + name)
            correlation = response.getheader('X-Request-ID', '')
            if bool(re.fullmatch('[a-f0-9]{32}', correlation)) != php or (not php and correlation):
                raise RuntimeError('Incorrect dispatch boundary for ' + name)
            if response.getheader('Cache-Control') != 'private, no-store':
                raise RuntimeError('Wrong cache policy for ' + name)
            if not response.getheader('Content-Type', '').startswith('application/json'):
                raise RuntimeError('Wrong media type for ' + name)
            if response.getheader('WWW-Authenticate') is not None or response.getheader('Retry-After') is not None:
                raise RuntimeError('Unexpected challenge or retry signal for ' + name)
            results.append({'case': name, 'status': status, 'php_dispatch': php, 'result': 'pass'})
        finally:
            connection.close()
    private_markers = [credential, 'mk1_bad', 'SyntheticBearerMarker', 'SyntheticBodyMarker', 'PrivateVerifierMarker', 'access_token=', 'api_key[nested]']
    all_logs = ''
    for path in Path('/tmp').glob('*.log'):
        if path.stat().st_size > 65536:
            raise RuntimeError('Diagnostic output exceeded its test bound')
        all_logs += path.read_text()
    if any(marker in all_logs for marker in private_markers):
        raise RuntimeError('A private diagnostic disclosed request input')
    summaries = [json.loads(line[line.index('{'):]) for line in all_logs.splitlines() if 'application.request_summary' in line]
    dispatched = [case for case in results if case['php_dispatch']]
    if len(summaries) != len(dispatched):
        raise RuntimeError('Unexpected terminal-summary count')
    for summary, result in zip(summaries, dispatched, strict=True):
        if summary['response_status'] != result['status'] or summary['query_count'] != (1 if result['status'] == 200 else 0):
            raise RuntimeError('Denied request reached protected work or summary order changed')
    paths = ['autoload.php', 'example/public/index.php', 'src/Http/Request.php', 'src/Http/RequestReader.php']
    paths += [str(path.relative_to(source)) for path in sorted((source / 'tests/fixtures/named-header').glob('*.php'))]
    paths += [str(path.relative_to(source)) for path in sorted(fixture.iterdir()) if path.name != 'evidence.json' and path.is_file()]
    print(json.dumps({
        'scope': 'Network-isolated HTTP/1.1 with synthetic policies; no TLS or production verifier certification',
        'php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True, timeout=3),
        'nginx': subprocess.check_output(['nginx', '-v'], stderr=subprocess.STDOUT, text=True, timeout=3).strip(),
        'njs_package': subprocess.check_output(['dpkg-query', '-W', '-f=${Version}', 'libnginx-mod-http-js'], text=True, timeout=3),
        'source_sha256': {path: hashlib.sha256((source / path).read_bytes()).hexdigest() for path in paths},
        'cases': results, 'terminal_summaries': len(summaries), 'private_log_redaction': 'pass',
        'denied_protected_queries': 0,
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
