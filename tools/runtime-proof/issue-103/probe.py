"""Container-only probe; exercise an unmodified canonical front controller."""
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
fixture = source / 'tools/runtime-proof/issue-103'
app = Path('/tmp/peer-app')
(app / 'example/public').mkdir(parents=True)
shutil.copyfile(source / 'autoload.php', app / 'autoload.php')
shutil.copyfile(source / 'example/public/index.php', app / 'example/public/index.php')
shutil.copyfile(source / 'tests/fixtures/peer-bootstrap.php.fixture', app / 'example/bootstrap.php')
(app / 'src').symlink_to(source / 'src', target_is_directory=True)
(app / 'example/src').symlink_to(source / 'example/src', target_is_directory=True)
Path('/tmp/peer-config').mkdir()
for config in ['nginx.conf', 'fpm.conf', 'fastcgi.conf']:
    shutil.copyfile(fixture / config, Path('/tmp/peer-config') / config)
children = []
logs = []
results = []
try:
    for name, command, port in [
        ('fpm', ['php-fpm', '--nodaemonize', '--fpm-config', '/tmp/peer-config/fpm.conf'], 9000),
        ('nginx', ['nginx', '-c', '/tmp/peer-config/nginx.conf', '-g', 'daemon off;'], 8080),
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
                if time.monotonic() >= deadline:
                    raise RuntimeError(name + ' startup timed out')
                time.sleep(0.05)
    for name, host, port, status, expected in [
        ('direct_ipv4', '127.0.0.1', 8080, 200, {'peer': '127.0.0.1'}),
        ('direct_ipv6', '::1', 8080, 200, {'peer': '::1'}),
        ('rewritten_remote_addr', '127.0.0.1', 8081, 200, {'peer': '198.51.100.42'}),
        ('preserved_original_peer', '127.0.0.1', 8082, 200, {'peer': '127.0.0.1'}),
        ('missing_required_peer', '127.0.0.1', 8083, 403, {'code': 'forbidden'}),
        ('malformed_peer', '127.0.0.1', 8084, 400, {'code': 'invalid_request'}),
        ('empty_peer', '127.0.0.1', 8085, 400, {'code': 'invalid_request'}),
    ]:
        connection = http.client.HTTPConnection(host, port, timeout=3)
        try:
            connection.request('POST', '/health', body='', headers={
                'X-Forwarded-For': '198.51.100.42', 'Forwarded': 'for=203.0.113.9',
                'X-Real-IP': '203.0.113.10', 'Remote-Addr': '203.0.113.11',
            })
            response = connection.getresponse()
            body = response.read(4097)
            if response.status != status or json.loads(body) != expected:
                raise RuntimeError('Unexpected response for ' + name + ': ' + repr(body))
            if not re.fullmatch('[a-f0-9]{32}', response.getheader('X-Request-ID', '')):
                raise RuntimeError('Missing correlation for ' + name)
            cache = 'no-store' if status == 400 else 'private, no-store'
            if response.getheader('Content-Type') != 'application/json':
                raise RuntimeError('Wrong content type for ' + name)
            if response.getheader('Cache-Control') != cache:
                raise RuntimeError('Wrong cache policy for ' + name)
            results.append({'case': name, 'status': status, 'body': expected, 'result': 'pass'})
        finally:
            connection.close()
    log = Path('/tmp/php-errors.log').read_text()
    if log.count('application.request_summary') != len(results) or any(
        text in log for text in ['private-sentinel', '198.51.100.42', '203.0.113.', '"peer"']
    ):
        raise RuntimeError('Terminal summaries are missing or disclose peer input')
    paths = [
        'autoload.php', 'example/public/index.php', 'tests/fixtures/peer-bootstrap.php.fixture',
        'src/Http/PeerAddress.php', 'src/Http/Request.php', 'src/Http/RequestReader.php',
    ] + [str(path.relative_to(source)) for path in sorted(fixture.iterdir()) if path.name != 'evidence.json' and path.is_file()]
    print(json.dumps({
        'scope': 'Local canonical front controller with synthetic peer inspection handler; no production certification',
        'php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True),
        'nginx': subprocess.check_output(['nginx', '-v'], stderr=subprocess.STDOUT, text=True).strip(),
        'source_sha256': {path: hashlib.sha256((source / path).read_bytes()).hexdigest() for path in paths},
        'cases': results,
        'terminal_summaries': len(results),
        'private_log_redaction': 'pass',
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
