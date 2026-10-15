"""Finite real-process probes; never used by the application console."""
import json
import os
from pathlib import Path
import re
import select
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
from process_support import IMAGE, capture

PHP = sys.argv[2]
CHILDREN = []


def environment(role):
    # A worker never inherits verifier/producer credentials, container controls,
    # or the invoking shell's unrelated environment.
    prefix = 'PHPTHIS_MYSQL_JOBS_' + role + '_'
    return {'PATH': os.environ['PATH'], 'LANG': 'C',
            prefix + 'DSN': os.environ[prefix + 'DSN'],
            prefix + 'PASSWORD': os.environ[prefix + 'PASSWORD']}


def start(arguments, role='WORKER'):
    child = subprocess.Popen([PHP, '-d', 'display_errors=0', '-d', 'log_errors=0', *arguments],
                             cwd=ROOT, env=environment(role), stdin=subprocess.PIPE,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, bufsize=0)
    CHILDREN.append(child)
    return child


def ready(child):
    deadline = time.monotonic() + 5
    value = b''
    while value != b'READY\n':
        remaining = deadline - time.monotonic()
        if remaining <= 0 or not select.select([child.stdout], [], [], remaining)[0]:
            raise RuntimeError('probe_ready_timeout')
        part = os.read(child.stdout.fileno(), 1)
        value += part
        if not part or len(value) > 6:
            raise RuntimeError('probe_ready_invalid')


def finish(child, expected, code=0, error=b''):
    if capture(child, timeout=5, limit=4096) != (code, expected, error):
        raise RuntimeError('probe_output_mismatch')


def stop(child, graceful=False):
    if child.poll() is None:
        child.terminate() if graceful else child.kill()
        try:
            child.wait(timeout=0.5)
        except subprocess.TimeoutExpired:
            child.kill()
            child.wait(timeout=1)
    code, out, err = capture(child, timeout=1, limit=4096)
    if code not in (-9, -15) or out or err:
        raise RuntimeError('probe_termination_failed')


def console(outcome):
    finish(start(['console.php', 'jobs:run-one']),
           ('{"command":"jobs:run-one","outcome":"' + outcome + '"}\n').encode())


def docker(*args):
    result = subprocess.run(['docker', *args], capture_output=True, timeout=10)
    if result.returncode != 0 or len(result.stdout) > 65536 or len(result.stderr) > 65536:
        raise RuntimeError('fixture_control_failed')
    return result.stdout


def restart_database():
    run_id = os.environ['PHPTHIS_MYSQL_JOBS_RUN_ID']
    if re.fullmatch('[0-9a-f]{32}', run_id) is None:
        raise RuntimeError('invalid_fixture_identity')
    name = 'phpthis-jobs-' + run_id
    inspected = json.loads(docker('inspect', name))[0]
    labels = inspected['Config']['Labels']
    if labels.get('phpthis.mysql-jobs.owner') != 'issue-107' or labels.get('phpthis.mysql-jobs.run') != run_id:
        raise RuntimeError('fixture_ownership_mismatch')
    writer = start(['tools/probe-worker.php', 'uncommitted-pause'], 'PRODUCER')
    ready(writer)
    docker('kill', '--signal=KILL', name)
    stop(writer)
    docker('start', name)
    deadline = time.monotonic() + 60
    while True:
        probe = subprocess.run(['docker', 'exec', name, 'sh', '-c',
                                'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -Nse "SELECT 1"'],
                               capture_output=True, timeout=5)
        if probe.returncode == 0 and probe.stdout == b'1\n':
            return
        if time.monotonic() >= deadline:
            raise RuntimeError('fixture_restart_timeout')
        time.sleep(0.2)


def fixture_preflight():
    run_id = os.environ['PHPTHIS_MYSQL_JOBS_RUN_ID']
    if re.fullmatch('[0-9a-f]{32}', run_id) is None:
        raise RuntimeError('invalid_fixture_identity')
    inspected = json.loads(docker('inspect', 'phpthis-jobs-' + run_id))[0]
    labels = inspected['Config']['Labels']
    if (labels.get('phpthis.mysql-jobs.owner') != 'issue-107'
            or labels.get('phpthis.mysql-jobs.run') != run_id
            or int(labels['phpthis.mysql-jobs.expires']) <= time.time()
            or inspected['Config']['Image'] != IMAGE or not inspected['State']['Running']):
        raise RuntimeError('fixture_ownership_mismatch')
    ports = inspected['NetworkSettings']['Ports']['3306/tcp']
    if len(ports) != 1 or ports[0]['HostIp'] != '127.0.0.1':
        raise RuntimeError('fixture_network_mismatch')
    expected = 'mysql:host=127.0.0.1;port=' + ports[0]['HostPort'] + ';dbname=phpthis_jobs;charset=utf8mb4'
    for role in ('VERIFICATION', 'PRODUCER', 'WORKER'):
        if os.environ['PHPTHIS_MYSQL_JOBS_' + role + '_DSN'] != expected:
            raise RuntimeError('fixture_dsn_mismatch')
    mounts = inspected['Mounts']
    if len(mounts) != 1 or mounts[0]['Type'] != 'volume' or mounts[0]['Destination'] != '/var/lib/mysql':
        raise RuntimeError('fixture_storage_mismatch')


def run(case):
    if case == 'fixture-preflight':
        fixture_preflight()
    elif case == 'parallel':
        first = start(['tools/probe-worker.php', 'claim-finish-wait'])
        second = start(['tools/probe-worker.php', 'claim-finish-wait'])
        ready(first)
        ready(second)
        first.stdin.write(b'GO\n')
        second.stdin.write(b'GO\n')
        finish(first, b'COMPLETED\n')
        finish(second, b'COMPLETED\n')
    elif case in ('claim-crash', 'effect-crash', 'publish-loss', 'deadline'):
        probe = {'claim-crash': 'claim-pause', 'effect-crash': 'effect-pause',
                 'publish-loss': 'publish-pause', 'deadline': 'effect-pause'}[case]
        child = start(['tools/probe-worker.php', probe], 'PRODUCER' if case == 'publish-loss' else 'WORKER')
        started = time.monotonic()
        ready(child)
        if case == 'deadline':
            time.sleep(max(0, 5 - (time.monotonic() - started)))
        stop(child, graceful=case == 'deadline')
    elif case == 'stale':
        child = start(['tools/probe-worker.php', 'claim-wait'])
        ready(child)
        finish(start(['tools/probe-worker.php', 'expire'], 'VERIFICATION'), b'EXPIRED\n')
        console('completed')
        child.stdin.write(b'GO\n')
        finish(child, b'STALE_REJECTED\n')
    elif case in ('supervise', 'idle-pacing'):
        began = time.monotonic()
        for index in range(3):
            if index:
                time.sleep(1)
            console('completed' if case == 'supervise' else 'idle')
        if time.monotonic() - began < 2:
            raise RuntimeError('supervisor_pacing_failed')
    elif case == 'database-crash':
        restart_database()
    elif case in ('completed', 'idle', 'dead_lettered', 'retry_scheduled'):
        console(case)
    elif case == 'console-rejection':
        finish(start(['console.php', 'unknown']), b'', 2, b'{"error":"invalid_command"}\n')
        finish(start(['console.php', 'jobs:run-one', 'extra']), b'', 2, b'{"error":"invalid_command"}\n')
    else:
        raise RuntimeError('unknown_process_case')


try:
    run(sys.argv[1])
    print('PROCESSES PASS')
except Exception:
    print('PROCESSES FAIL', file=sys.stderr)
    raise SystemExit(1)
finally:
    for owned in CHILDREN:
        if owned.poll() is None:
            owned.kill()
            owned.wait(timeout=1)
