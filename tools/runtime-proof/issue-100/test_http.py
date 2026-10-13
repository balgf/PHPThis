"""Black-box parity and isolation checks against the two disposable loopback servers."""
import concurrent.futures
import http.client
import json
import re
import sys

PORTS = (8080, 8081)
IDS = set()
RECORDS = []

def request(port, path, method='GET', body=None, headers=None):
    connection = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    try:
        connection.request(method, path, body=body, headers=headers or {})
        response = connection.getresponse()
        payload = response.read(65537)
        assert len(payload) <= 65536
        return response.status, dict(response.getheaders()), payload
    finally:
        connection.close()

def check(port, path, status=200, method='GET', body=None, headers=None, queries=None):
    actual, fields, payload = request(port, path, method, body, headers)
    assert actual == status, (port, path, actual, payload)
    lowered = {name.lower(): value for name, value in fields.items()}
    identity = lowered.get('x-request-id')
    assert identity and re.fullmatch('[a-f0-9]{32}', identity), (path, fields)
    assert identity not in IDS
    IDS.add(identity)
    assert 'set-cookie' not in lowered
    assert b'PRIVATE_' not in payload and b'Fatal error' not in payload and b'Stack trace' not in payload
    RECORDS.append({'port': port, 'path': path, 'id': identity, 'status': status, 'queries': queries})
    if status in (404, 405):
        assert lowered['content-type'].startswith('text/plain')
        assert payload == (b'Not Found\n' if status == 404 else b'Method Not Allowed\n')
        return payload.decode()
    return json.loads(payload)

for port in PORTS:
    assert check(port, '/health', queries=0) == {'status': 'ok'}
    runtime = check(port, '/runtime', queries=0)
    assert runtime['php'] == '8.4.19' and runtime['swoole'] == '6.2.3'
    assert runtime['sapi'] == ('fpm-fcgi' if port == 8080 else 'cli')
    assert runtime['errors'] & runtime['all_errors'] == runtime['all_errors']
    assert runtime['display_errors'] == '' and runtime['display_startup_errors'] == ''
    assert runtime['log_errors'] == '1' and runtime['ignore_args'] == '1'
    assert runtime['opcache'] == '1' and runtime['opcache_cli'] == '1' and runtime['jit'] == '0'
    check(port, '/health', 400, headers={f'X-Limit-{i}': 'x' for i in range(65)}, queries=0)
    check(port, '/health', 400, headers={'X-Limit': 'x' * 8193}, queries=0)
    check(port, '/' + 'x' * 8192, 400, queries=0)
    for limit in (2, 50):
        value = check(port, f'/documents?tenant=1&limit={limit}', queries=1)
        assert [row['id'] for row in value['data']] == list(range(1001, 1001 + limit))
        assert all(row['events'] == 3 for row in value['data'])
    for path in ('/documents?tenant[]=1', '/documents?tenant=3', '/documents?limit=0',
                 '/documents?limit=51', '/documents?marker=x%27OR1', '/documents?unknown=x'):
        check(port, path, 400, queries=0)
    check(port, '/absent', 404, queries=0)
    check(port, '/health', 405, 'POST', queries=0)
    check(port, '/echo', 415, 'POST', 'x', {'Content-Type': 'text/plain'}, 0)
    check(port, '/echo', 400, 'POST', '{', {'Content-Type': 'application/json'}, 0)
    assert check(port, '/echo', 200, 'POST', '{"marker":"isolated"}', {'Content-Type': 'application/json'}, 0) == {'marker': 'isolated'}
    check(port, '/echo', 413, 'POST', 'x' * 1025, {'Content-Type': 'application/json'}, 0)
    check(port, '/fault', 500, queries=0)
    check(port, '/budget', 500, queries=1)
    check(port, '/rollback', 500, 'POST', queries=1)
    assert check(port, '/count', queries=1) == {'count': 0}
    # Repeat in the same persistent worker after both unknown failures and budget exhaustion.
    for index in range(24):
        tenant = index % 2 + 1
        result = check(port, f'/documents?tenant={tenant}&limit=2&marker=sequential{index}', queries=1)
        assert result['marker'] == f'sequential{index}'
        assert [row['id'] for row in result['data']] == [tenant * 1000 + 1, tenant * 1000 + 2]
    # One Swoole worker yields after fresh composition during this test mode.
    paths = [f'/documents?tenant={i % 2 + 1}&limit=2&marker=parallel{i}' for i in range(64)]
    with concurrent.futures.ThreadPoolExecutor(max_workers=16) as pool:
        results = list(pool.map(lambda path: request(port, path), paths))
    for index, (status, fields, payload) in enumerate(results):
        lowered = {name.lower(): value for name, value in fields.items()}
        identity = lowered.get('x-request-id')
        assert status == 200 and identity not in IDS and re.fullmatch('[a-f0-9]{32}', identity)
        IDS.add(identity)
        value = json.loads(payload)
        assert value['marker'] == f'parallel{index}'
        assert [row['id'] for row in value['data']] == [(index % 2 + 1) * 1000 + 1, (index % 2 + 1) * 1000 + 2]
        RECORDS.append({'port': port, 'path': paths[index], 'id': identity, 'status': 200, 'queries': 1})

json.dump({'passed': True, 'checked_requests': len(RECORDS), 'records': RECORDS}, sys.stdout, indent=2)
print()
