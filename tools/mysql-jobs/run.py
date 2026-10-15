"""Own one disposable MySQL service for the application reference's real gate."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import uuid

from process_support import IMAGE, owned_group
ROOT = Path(__file__).resolve().parent
parser = argparse.ArgumentParser()
parser.add_argument('--cleanup-run')
parser.add_argument('--evidence', type=Path, default=ROOT.parents[1] / 'tmp/mysql-jobs-evidence.json')
args = parser.parse_args()


def docker(*arguments, input_bytes=None, timeout=15):
    result = subprocess.run(['docker', *arguments], input=input_bytes, capture_output=True, timeout=timeout)
    if result.returncode != 0 or len(result.stdout) > 65536 or len(result.stderr) > 65536:
        raise RuntimeError('mysql_fixture_docker_failed')
    return result.stdout.decode().strip()


def inspect_owned(name, run_id):
    data = json.loads(docker('inspect', name))[0]
    labels = data['Config']['Labels']
    if labels.get('phpthis.mysql-jobs.run') != run_id or labels.get('phpthis.mysql-jobs.owner') != 'issue-107':
        raise RuntimeError('mysql_fixture_ownership_mismatch')
    return data


def prove_stale_cleanup(active_name, active_id):
    # The actual CLI must refuse the live run; a separate expired, never-started
    # fixture establishes exact-ID deletion without touching the active service.
    active = subprocess.run([sys.executable, str(ROOT / 'run.py'), '--cleanup-run', active_id],
                            capture_output=True, timeout=20)
    if active.returncode == 0:
        raise RuntimeError('active_fixture_was_removed')
    inspect_owned(active_name, active_id)
    expired = uuid.uuid4().hex
    expired_name = 'phpthis-jobs-' + expired
    created_expired = False
    try:
        docker('create', '--name', expired_name, '--pull', 'never',
               '--label', 'phpthis.mysql-jobs.owner=issue-107',
               '--label', 'phpthis.mysql-jobs.run=' + expired,
               '--label', 'phpthis.mysql-jobs.expires=' + str(int(time.time()) - 1), IMAGE)
        created_expired = True
        result = subprocess.run([sys.executable, str(ROOT / 'run.py'), '--cleanup-run', expired],
                                capture_output=True, timeout=20)
        if result.returncode != 0 or result.stdout != b'PASS exact abandoned MySQL run cleanup\n' or result.stderr:
            raise RuntimeError('stale_fixture_cleanup_failed')
        created_expired = False
        inspect_owned(active_name, active_id)
    finally:
        if created_expired:
            inspect_owned(expired_name, expired)
            docker('rm', '--force', '--volumes', expired_name)


if args.cleanup_run:
    run_id = uuid.UUID(args.cleanup_run).hex
    name = 'phpthis-jobs-' + run_id
    data = inspect_owned(name, run_id)
    if int(data['Config']['Labels']['phpthis.mysql-jobs.expires']) > time.time():
        raise RuntimeError('mysql_fixture_not_abandoned')
    docker('rm', '--force', '--volumes', name)
    print('PASS exact abandoned MySQL run cleanup')
    raise SystemExit(0)

php = subprocess.run(['php', '-r', 'echo json_encode(["php" => PHP_VERSION, "pdo_mysql" => phpversion("pdo_mysql"), "mysqlnd" => phpversion("mysqlnd")]);'], capture_output=True, timeout=5, check=True)
if len(php.stdout) > 256:
    raise RuntimeError('invalid_client_version_report')
client_versions = json.loads(php.stdout)
# setup-php selects the available patch of its requested minor series. Retain
# exact observed clients and reject future drift before provisioning a service.
if client_versions not in (
    {'php': '8.4.19', 'pdo_mysql': '8.4.19', 'mysqlnd': 'mysqlnd 8.4.19'},
    {'php': '8.4.26', 'pdo_mysql': '8.4.26', 'mysqlnd': 'mysqlnd 8.4.26'},
):
    raise RuntimeError('unreviewed_client_version')
print('MYSQL JOBS CLIENT ' + json.dumps(client_versions, sort_keys=True), flush=True)

run_id = uuid.uuid4().hex
name = 'phpthis-jobs-' + run_id
print('MYSQL JOBS FIXTURE ' + run_id, flush=True)
passwords = {role: secrets.token_hex(32) for role in ['root', 'producer', 'worker']}
created = False
with tempfile.TemporaryDirectory(prefix='phpthis-jobs-') as directory:
    private = Path(directory)
    try:
        env_file = private / 'mysql.env'
        env_file.write_text('MYSQL_ROOT_HOST=%\nMYSQL_ROOT_PASSWORD=' + passwords['root'] + '\n')
        env_file.chmod(0o600)
        # Docker may reassign an automatic host port after a crash/restart.
        # Pick an unused loopback port and bind that explicit value for the run.
        # A race for the port fails container startup rather than changing it.
        with socket.socket() as reservation:
            reservation.bind(('127.0.0.1', 0))
            host_port = reservation.getsockname()[1]
        docker('create', '--name', name, '--pull', 'never', '--env-file', str(env_file),
               '--label', 'phpthis.mysql-jobs.owner=issue-107', '--label', 'phpthis.mysql-jobs.run=' + run_id,
               '--label', 'phpthis.mysql-jobs.expires=' + str(int(time.time()) + 1200),
               '--publish', f'127.0.0.1:{host_port}:3306', '--memory', '512m', '--cpus', '1', '--pids-limit', '128',
               IMAGE, '--innodb-flush-log-at-trx-commit=1', '--sync-binlog=1', '--log-bin=mysql-bin',
               '--binlog-format=ROW', '--innodb-buffer-pool-size=64M')
        created = True
        prove_stale_cleanup(name, run_id)
        docker('start', name)
        deadline = time.monotonic() + 75
        while True:
            # The service-local shell receives no command-line credential.
            probe = subprocess.run(['docker', 'exec', name, 'sh', '-c',
                                    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -Nse "SELECT 1"'],
                                   capture_output=True, timeout=5)
            if probe.returncode == 0 and probe.stdout == b'1\n':
                break
            if time.monotonic() >= deadline:
                raise RuntimeError('mysql_fixture_startup_timeout')
            time.sleep(0.2)
        sql = (ROOT / 'schema.sql.fixture').read_text()
        # Fixture provisioning only: values are locally generated lowercase hex.
        sql += "\nCREATE USER 'phpthis_jobs_producer'@'%' IDENTIFIED BY '" + passwords['producer'] + "';\n"
        sql += "CREATE USER 'phpthis_jobs_worker'@'%' IDENTIFIED BY '" + passwords['worker'] + "';\n"
        sql += "GRANT SELECT, INSERT ON phpthis_jobs.welcome_requests TO 'phpthis_jobs_producer'@'%';\n"
        sql += "GRANT SELECT, INSERT ON phpthis_jobs.application_jobs TO 'phpthis_jobs_producer'@'%';\n"
        sql += "GRANT SELECT ON phpthis_jobs.welcome_requests TO 'phpthis_jobs_worker'@'%';\n"
        sql += "GRANT SELECT, INSERT, UPDATE (idempotency_key) ON phpthis_jobs.welcome_deliveries TO 'phpthis_jobs_worker'@'%';\n"
        sql += "GRANT SELECT, UPDATE (status, attempts_started, available_at, lease_token, lease_expires_at, updated_at, completed_at, dead_at, last_failure_code) ON phpthis_jobs.application_jobs TO 'phpthis_jobs_worker'@'%';\n"
        docker('exec', '-i', name, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot', input_bytes=sql.encode())
        port = inspect_owned(name, run_id)['NetworkSettings']['Ports']['3306/tcp'][0]['HostPort']
        dsn = f'mysql:host=127.0.0.1;port={port};dbname=phpthis_jobs;charset=utf8mb4'
        environment = {'PATH': os.environ['PATH'], 'LANG': 'C', 'COMPOSER_HOME': str(private / 'composer')}
        for profile, role in [('VERIFICATION', 'root'), ('PRODUCER', 'producer'), ('WORKER', 'worker')]:
            environment['PHPTHIS_MYSQL_JOBS_' + profile + '_DSN'] = dsn
            environment['PHPTHIS_MYSQL_JOBS_' + profile + '_PASSWORD'] = passwords[role]
        environment['PHPTHIS_MYSQL_JOBS_RUN_ID'] = run_id
        wrong_namespace = dict(environment)
        wrong_namespace['PHPTHIS_MYSQL_JOBS_RUN_ID'] = uuid.uuid4().hex
        rejected = owned_group(['php', 'tools/verify-jobs.php'], cwd=ROOT, env=wrong_namespace, timeout=20)
        if rejected != (1, b'', b'JOBS VERIFY FAIL\n'):
            raise RuntimeError('unowned_fixture_was_not_rejected')
        code, out, err = owned_group([shutil.which('composer'), '--no-interaction', 'check'], cwd=ROOT,
                                    env=environment, timeout=300)
        if code != 0:
            # Gate output is fixed and credential-free; do not print native diagnostics.
            raise RuntimeError('mysql_jobs_gate_failed')
        if out != b'JOBS VERIFY PASS\n':
            raise RuntimeError('mysql_jobs_gate_output_mismatch')
        version = docker('exec', name, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -Nse "SELECT VERSION()"')
        if version != '8.4.11':
            raise RuntimeError('unreviewed_service_version')
        sources = sorted(path for path in ROOT.rglob('*') if path.is_file() and path.suffix in ('.php', '.py', '.fixture') and '__pycache__' not in path.parts)
        sources += [ROOT / 'composer.json', ROOT.parents[1] / 'src/Database/Connection.php']
        sources += sorted((ROOT.parents[1] / 'example/src/Jobs').glob('*.php'))
        evidence = {'checked_at_utc': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
                    'mysql': version, 'image': IMAGE, **client_versions,
                    'command': 'python3 tools/mysql-jobs/run.py',
                    'modules': ['publication', 'delivery', 'operations'],
                    'concurrent_processes': 2, 'lease_seconds': 30,
                    'queue_size_samples': [1, 501], 'maximum_delivery_statements': 20,
                    'source_sha256': {str(path.relative_to(ROOT.parents[1])): hashlib.sha256(path.read_bytes()).hexdigest() for path in sources}}
    finally:
        if created:
            owned = inspect_owned(name, run_id)
            volumes = [mount['Name'] for mount in owned['Mounts'] if mount['Type'] == 'volume']
            docker('rm', '--force', '--volumes', name)
            if docker('ps', '-aq', '--filter', 'name=^/' + name + '$'):
                raise RuntimeError('fixture_container_survived_cleanup')
            for volume in volumes:
                removed = subprocess.run(['docker', 'volume', 'inspect', volume], capture_output=True, timeout=10)
                if removed.returncode == 0:
                    raise RuntimeError('fixture_volume_survived_cleanup')

evidence['cleanup'] = 'owned processes stopped; container and anonymous volume removed; active/stale cleanup controls passed'
args.evidence.parent.mkdir(parents=True, exist_ok=True)
args.evidence.write_text(json.dumps(evidence, indent=2) + '\n')
print('JOBS VERIFY PASS')
