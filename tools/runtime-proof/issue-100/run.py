"""Run only disposable, network-isolated proof containers; retain evidence and always clean up."""
import argparse
import json
import hashlib
import tarfile
import shutil
import os
import subprocess
import time
import uuid
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--benchmark', action='store_true')
parser.add_argument('--framework', type=Path, required=True)
args = parser.parse_args()
base = Path(__file__).resolve().parent
stamp = time.strftime('%Y%m%dT%H%M%S', time.gmtime())
output = args.framework.resolve() / 'tmp' / 'swoole-proof' / (stamp + '-' + uuid.uuid4().hex[:8])
output.mkdir(parents=True)
source_files = sorted(path for folder in ('application', 'runtime') for path in (base / folder).rglob('*') if path.is_file())
source_files += [base / name for name in ('run.py', 'analyse.neon', 'seed.py', 'test_http.py', 'verify_summaries.py', 'benchmark.py', 'check-syntax.php.fixture')]
with tarfile.open(output / 'source.tar.gz', 'w:gz') as archive:
    for path in source_files:
        archive.add(path, arcname=str(path.relative_to(base)), recursive=False)
work = output / 'work'
for path in source_files:
    destination = work / path.relative_to(base)
    if destination.name.endswith('.php.fixture'):
        destination = destination.with_suffix('')
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, destination)
source_hashes = {str(path.relative_to(base)): hashlib.sha256(path.read_bytes()).hexdigest() for path in source_files}
(output / 'source-hashes.json').write_text(json.dumps(source_hashes, indent=2) + '\n')
identity = 'phpthis-swoole-100-' + uuid.uuid4().hex[:8]
server = identity + '-server'
client = identity + '-client'
owned = []

def command(argv, *, timeout=30, check=True):
    result = subprocess.run(argv, capture_output=True, text=True, timeout=timeout)
    if check and result.returncode:
        raise RuntimeError(f'{argv[:5]} failed ({result.returncode}): {result.stdout[-2000:]} {result.stderr[-2000:]}')
    return result

def execute(*argv, timeout=30):
    return command(['docker', 'exec', server, *argv], timeout=timeout).stdout

def client_run(script, filename, timeout=90):
    owned.append(client)
    result = command([
        'docker', 'run', '--name', client, '--label', 'phpthis.proof=' + identity,
        '--network', 'container:' + server, '--read-only', '--user', '33:33',
        '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
        '--cpus', '1', '--memory', '256m', '--pids-limit', '32',
        '--tmpfs', '/tmp:rw,size=16m', '--mount', f'type=bind,source={work},target=/proof,readonly',
        image, 'python3', '/proof/' + script,
    ], timeout=timeout, check=False)
    (output / filename).write_text(result.stdout)
    (output / (filename + '.stderr')).write_text(result.stderr)
    command(['docker', 'rm', client])
    owned.remove(client)
    if result.returncode:
        raise RuntimeError(f'{script} failed; see {output / (filename + ".stderr")}')

def wait_ready(port):
    deadline = time.monotonic() + 15
    code = f"import http.client; c=http.client.HTTPConnection('127.0.0.1',{port},timeout=1); c.request('GET','/health'); r=c.getresponse(); assert r.status==200; assert r.read()==b'{{\"status\":\"ok\"}}\\n'"
    while time.monotonic() < deadline:
        result = command(['docker', 'exec', server, 'python3', '-c', code], check=False)
        if result.returncode == 0:
            return
        time.sleep(.1)
    raise RuntimeError(f'Server readiness failed on {port}: {result.stderr}')

def stop_swoole():
    pid = int(execute('cat', '/tmp/swoole.pid').strip())
    assert pid > 1
    start = time.monotonic()
    execute('python3', '-c', 'import os, signal, sys; os.kill(int(sys.argv[1]), signal.SIGTERM)', str(pid))
    while time.monotonic() - start < 10:
        alive = command(['docker', 'exec', server, 'python3', '-c', 'import os, sys; os.kill(int(sys.argv[1]), 0)', str(pid)], check=False)
        if alive.returncode:
            return (time.monotonic() - start) * 1000
        time.sleep(.1)
    raise RuntimeError('Swoole did not terminate within 10 seconds.')

try:
    image_info = json.loads(command(['docker', 'image', 'inspect', 'phpthis-swoole-proof:issue100']).stdout)[0]
    image = image_info['Id']
    metadata = {'image': image, 'base_image': 'php@sha256:7b0f2acae67bd1678a12abcb359023c4e4fd168a87f4704f5a75c96f0a5406d5',
                'architecture': image_info['Architecture'], 'server_cpu_quota': 1, 'client_cpu_quota': 1,
                'server_memory_mib': 512, 'client_memory_mib': 256,
                'framework_commit': command(['git', '-C', str(args.framework), 'rev-parse', 'HEAD']).stdout.strip()}
    (output / 'metadata.json').write_text(json.dumps(metadata, indent=2) + '\n')
    owned.append(server)
    command(['docker', 'run', '-d', '--init', '--name', server, '--label', 'phpthis.proof=' + identity,
             '--network', 'none', '--read-only', '--user', '33:33', '--cap-drop', 'ALL',
             '--security-opt', 'no-new-privileges', '--cpus', '1', '--memory', '512m', '--pids-limit', '64',
             '--tmpfs', '/tmp:rw,size=128m', '--tmpfs', '/fixture:rw,size=16m',
             '--mount', f'type=bind,source={args.framework.resolve()},target=/framework,readonly',
             '--mount', f'type=bind,source={work},target=/proof,readonly',
             '--mount', f'type=bind,source={work / "application"},target=/app,readonly',
             image, 'sleep', 'infinity'])
    (output / 'syntax.log').write_text(execute('php', '/proof/check-syntax.php'))
    (output / 'analysis.log').write_text(execute('php', '-d', 'disable_functions=proc_open',
        '/framework/vendor/bin/phpstan', 'analyse', '--configuration=/proof/analyse.neon',
        '--autoload-file=/app/autoload.php', '--no-progress', '--memory-limit=384M', timeout=90))
    execute('python3', '/proof/seed.py', '/fixture/proof.sqlite')
    execute('nginx', '-c', '/proof/runtime/nginx.conf')
    execute('php-fpm', '-D', '-y', '/usr/local/etc/php-fpm.conf')
    execute('php', '-v')
    command(['docker', 'exec', '-d', server, 'php', '/app/swoole.php', '--overlap-probe'])
    wait_ready(8080)
    wait_ready(8081)
    client_run('test_http.py', 'behavior.json')
    (output / 'summary.log').write_text(execute('cat', '/tmp/phpthis-errors.log'))
    summary = command(['python3', str(work / 'verify_summaries.py'), str(output / 'behavior.json'), str(output / 'summary.log')])
    (output / 'summary-check.json').write_text(summary.stdout)
    print('PASS HTTP parity, repeated/concurrent isolation, query budgets, rollback and canonical summaries.', flush=True)
    execute('chmod', '000', '/fixture/proof.sqlite')
    try:
        outer_probe = '''import http.client, json
results=[]
for port in (8080,8081):
 c=http.client.HTTPConnection('127.0.0.1',port,timeout=5)
 c.request('GET','/health')
 r=c.getresponse(); body=r.read(); headers={k.lower():v for k,v in r.getheaders()}
 assert r.status==500 and 'x-request-id' not in headers
 assert json.loads(body)=={'error':{'code':'internal_server_error','message':'Internal server error.'}}
 results.append({'port':port,'generic':True,'correlation_absent':True})
 c.close()
print(json.dumps(results))'''
        (output / 'outer-failure.json').write_text(execute('python3', '-c', outer_probe))
    finally:
        execute('chmod', '666', '/fixture/proof.sqlite')
    wait_ready(8080)
    wait_ready(8081)
    print('PASS pre-coordinator composition failure and recovery on both runtimes.', flush=True)
    metadata['probe_shutdown_ms'] = stop_swoole()
    (output / 'metadata.json').write_text(json.dumps(metadata, indent=2) + '\n')
    if args.benchmark:
        command(['docker', 'exec', '-d', server, 'php', '/app/swoole.php'])
        wait_ready(8081)
        client_run('benchmark.py', 'benchmark.jsonl', timeout=240)
        metadata['benchmark_shutdown_ms'] = stop_swoole()
        print('PASS matched repeated database HTTP measurements.', flush=True)
    (output / 'metadata.json').write_text(json.dumps(metadata, indent=2) + '\n')
except Exception as error:
    (output / 'failure.txt').write_text(str(error) + '\n')
    for path in ('/tmp/phpthis-errors.log', '/tmp/swoole.log', '/tmp/fpm.log', '/tmp/nginx-error.log'):
        result = command(['docker', 'exec', server, 'cat', path], check=False)
        (output / Path(path).name).write_text(result.stdout + result.stderr)
    raise
finally:
    for name in reversed(owned):
        command(['docker', 'rm', '-f', name], check=False)
    leftovers = command(['docker', 'ps', '-aq', '--filter', 'label=phpthis.proof=' + identity]).stdout.strip()
    (output / 'cleanup.json').write_text(json.dumps({'remaining_containers': leftovers.splitlines(), 'named_volumes_created': 0}) + '\n')
    if leftovers:
        raise RuntimeError('Owned containers remain after cleanup.')
    shutil.rmtree(work)
    print('Evidence:', output, flush=True)
