"""Real private worker/console faults; all storage is synthetic and test-owned."""
import datetime
import hashlib
import io
import json
import math
import os
from pathlib import Path
import shutil
import signal
import sqlite3
import struct
import subprocess
import tempfile
import time
import wave

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which('php')
assert PHP
samples = [round(4096 * math.sin(2 * math.pi * 1000 * i / 16000)) for i in range(16000)]
expected = io.BytesIO()
with wave.open(expected, 'wb') as writer:
    writer.setnchannels(1)
    writer.setsampwidth(2)
    writer.setframerate(16000)
    writer.writeframes(struct.pack('<16000h', *samples))
DATA = expected.getvalue()
assert len(DATA) == 32044


def php_string(value):
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"


def running(pid):
    state = subprocess.run(['ps', '-o', 'stat=', '-p', str(pid)],
                           capture_output=True, timeout=2).stdout.strip()
    return bool(state) and not state.startswith(b'Z')


results = []
with tempfile.TemporaryDirectory(prefix='generated-tone-', dir=ROOT.parents[1] / 'tmp') as temporary:
    clone = Path(temporary)
    (clone / 'src').mkdir()
    (clone / 'worker').mkdir()
    sources = ['Values.php', 'ExportLock.php', 'S3Settings.php', 'ReferenceEnvironment.php',
               'CredentialProvider.php', 'InventoryS3.php', 'InventoryExports.php',
               'ExportInvocation.php', 'TestToneBytes.php', 'TestToneProducer.php']
    for name in sources:
        shutil.copyfile(ROOT / 'src' / name, clone / 'src' / name)
    shutil.copyfile(ROOT / 'console.php', clone / 'console.php')
    bootstrap = (ROOT / 'bootstrap.php').read_text().replace(
        "dirname(__DIR__, 2) . '/autoload.php'", php_string(ROOT.parents[1] / 'autoload.php')).replace(
        "__DIR__ . '/dependencies.php'", php_string(ROOT / 'dependencies.php'))
    bootstrap += '\nrequire_once ' + php_string(ROOT / 'tests/Fixture.php') + ';\n'
    (clone / 'bootstrap.php').write_text(bootstrap)
    # Replace only the infrastructure composition in this isolated test copy. No network handler exists.
    (clone / 'src/Composition.php').write_text('''<?php
declare(strict_types=1);
namespace GeneratedS3Recipe;
function exportS3Client(S3Settings $configuration): \\Aws\\S3\\S3Client {
    file_put_contents(dirname(__DIR__) . '/composition-reached', 'synthetic');
    return (new Tests\\SyntheticS3($configuration->target))->client();
}
''')
    (clone / 'probe.php').write_text('''<?php
declare(strict_types=1);
require __DIR__ . '/src/Values.php';
require __DIR__ . '/src/TestToneBytes.php';
require __DIR__ . '/src/TestToneProducer.php';
try { fwrite(STDOUT, GeneratedS3Recipe\\TestToneProducer::generate()->bytes->value); }
catch (Throwable) { fwrite(STDERR, "unavailable\\n"); exit(1); }
''')
    storage = clone / 'storage'
    storage.mkdir(mode=0o700)
    database = storage / 'inventory.sqlite'
    with sqlite3.connect(database) as db:
        db.executescript((ROOT / 'schema.sql.fixture').read_text())
    database.chmod(0o600)
    (storage / 'exports.lock').touch(mode=0o600)
    ca = clone / 'fixture-ca'
    ca.write_bytes(b'synthetic reviewed CA')
    environment = {
        'PHPTHIS_EXPORT_WRITER_ROOT': str(storage), 'PHPTHIS_EXPORT_WRITER_BUCKET': 'fixture-bucket',
        'PHPTHIS_EXPORT_WRITER_REGION': 'us-east-1', 'PHPTHIS_EXPORT_WRITER_ACCOUNT': '000000000000',
        'PHPTHIS_EXPORT_WRITER_KMS': 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001',
        'PHPTHIS_EXPORT_WRITER_CA_PATH': str(ca), 'PHPTHIS_EXPORT_WRITER_CA_SHA256': hashlib.sha256(ca.read_bytes()).hexdigest(),
        'PHPTHIS_EXPORT_WRITER_ACCESS_KEY': 'ASIA' + 'A' * 16, 'PHPTHIS_EXPORT_WRITER_SECRET': 's' * 40,
        'PHPTHIS_EXPORT_WRITER_TOKEN': 't' * 32, 'PHPTHIS_EXPORT_WRITER_EXPIRES': str(int(time.time()) + 3600),
        'PHPTHIS_TONE_SENTINEL': 'synthetic',
    }
    worker = clone / 'worker/test-tone.php'
    original = (ROOT / 'worker/test-tone.php').read_text()
    prefix = '<?php\n\ndeclare(strict_types=1);\n'
    assert original.startswith(prefix)
    body = original[len(prefix):]
    marker = clone / 'composition-reached'
    pid_file = clone / 'worker/observed-pid'

    def run(arguments):
        started = time.monotonic()
        process = subprocess.Popen([PHP, '-d', 'display_errors=0', *arguments], cwd=clone,
                                   env=environment, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                   start_new_session=True)
        try:
            out, err = process.communicate(timeout=6)
        except subprocess.TimeoutExpired:
            os.killpg(process.pid, signal.SIGKILL)
            process.communicate(timeout=2)
            raise AssertionError('producer escaped its parent deadline') from None
        if pid_file.exists():
            child = int(pid_file.read_text())
            if running(child):
                os.killpg(process.pid, signal.SIGKILL)
                raise AssertionError('owned worker still running')
        return process.returncode, out, err, round(time.monotonic() - started, 3)

    worker.write_text(original)
    code, output, error, elapsed = run([str(clone / 'probe.php')])
    assert (code, error, output) == (0, b'', DATA)
    with wave.open(io.BytesIO(output), 'rb') as reader:
        assert reader.getparams() == (1, 2, 16000, 16000, 'NONE', 'not compressed')
        assert struct.unpack('<16000h', reader.readframes(16001)) == tuple(samples)
    results.append({'case': 'independent_wave_samples', 'elapsed_seconds': elapsed})

    code, output, error, elapsed = run([str(clone / 'console.php'), 'generate-test-tone'])
    assert (code, output, error) == (0, b'{"command":"generate-test-tone","outcome":"generated"}\n', b'')
    with sqlite3.connect(database) as db:
        assert db.execute('SELECT size_bytes, sha256, state FROM exports').fetchall() == [
            (32044, hashlib.sha256(DATA).hexdigest(), 'accepted')]
    assert marker.exists()
    results.append({'case': 'real_console_tone', 'elapsed_seconds': elapsed})
    code, output, error, elapsed = run([str(clone / 'console.php'), 'generate'])
    assert (code, output, error) == (0, b'{"command":"generate","outcome":"generated"}\n', b'')
    with sqlite3.connect(database) as db:
        before = db.execute('SELECT * FROM exports ORDER BY export_id').fetchall()
        assert len(before) == 2
    results.append({'case': 'retained_csv_compatibility', 'elapsed_seconds': elapsed})

    cases = {
        'empty': '', 'partial': 'echo "RIFF";',
        'malformed_header': body.replace("'RIFF'", "'RIFX'"),
        'altered_samples': body.replace('1567', '1568'),
        'extra_byte': body + 'echo "x";',
        'stdout_flood': 'echo str_repeat("x", 2 * 1024 * 1024);',
        'stderr_flood': 'fwrite(STDERR, str_repeat("x", 2 * 1024 * 1024));',
        'diagnostic': body + 'fwrite(STDERR, "private diagnostic");',
        'nonzero_complete': body + 'exit(7);',
        'failure': 'exit(7);', 'exception': 'throw new RuntimeException("private failure");',
        'deadline_before_output': 'set_time_limit(0); usleep(6_000_000);',
        'deadline_after_output': body + 'set_time_limit(0); usleep(6_000_000);',
        'cpu_limit': 'while (true) {}',
        'memory_limit': '$x = str_repeat("x", 32 * 1024 * 1024);',
    }
    for name, source in cases.items():
        marker.unlink(missing_ok=True)
        pid_file.unlink(missing_ok=True)
        worker.write_text(prefix + 'file_put_contents(__DIR__ . "/observed-pid", (string) getmypid());\n' + source)
        code, output, error, elapsed = run([str(clone / 'console.php'), 'generate-test-tone'])
        assert (code, output, error) == (1, b'', b'{"error":"command_failed"}\n'), name
        assert not marker.exists(), name + ': S3 composition reached'
        with sqlite3.connect(database) as db:
            assert db.execute('SELECT * FROM exports ORDER BY export_id').fetchall() == before, name
        results.append({'case': name, 'elapsed_seconds': elapsed, 'worker_stopped': True,
                        'no_s3_composition_or_metadata_change': True})

    for name, guard in {
        'closed_stdin': 'stream_get_contents(STDIN) !== ""',
        'environment_exclusion': 'array_diff(array_keys(getenv()), ["PHP_INI_SCAN_DIR"]) !== [] || getenv("PHPTHIS_TONE_SENTINEL") !== false',
        'fixed_cwd': 'getcwd() !== __DIR__',
        'no_descendants': 'function_exists("proc_open") || function_exists("pcntl_fork") || function_exists("shell_exec")',
    }.items():
        pid_file.unlink(missing_ok=True)
        worker.write_text(prefix + 'if (' + guard + ') { exit(1); }\n' + body)
        code, output, error, elapsed = run([str(clone / 'probe.php')])
        assert (code, output, error) == (0, DATA, b''), name
        results.append({'case': name, 'elapsed_seconds': elapsed})

    for name in ['symlink', 'hardlink', 'directory', 'missing']:
        worker.unlink()
        if name == 'symlink':
            worker.symlink_to(ROOT / 'worker/test-tone.php')
        elif name == 'hardlink':
            # The second link is test-owned; never alter the source checkout's link count.
            (clone / 'copy.php').write_text(original)
            os.link(clone / 'copy.php', worker)
        elif name == 'directory':
            worker.mkdir()
        marker.unlink(missing_ok=True)
        code, output, error, elapsed = run([str(clone / 'console.php'), 'generate-test-tone'])
        assert (code, output, error) == (1, b'', b'{"error":"command_failed"}\n'), name
        assert not marker.exists()
        if name == 'directory':
            worker.rmdir()
        elif worker.is_symlink() or worker.exists():
            worker.unlink()
        worker.write_text(original)
        results.append({'case': name, 'elapsed_seconds': elapsed})

    for argument in ['/untrusted/path.wav', '--frequency=1000', '--duration=1']:
        marker.unlink(missing_ok=True)
        code, output, error, elapsed = run([str(clone / 'console.php'), 'generate-test-tone', argument])
        assert (code, output, error) == (2, b'', b'{"error":"invalid_arguments"}\n')
        assert not marker.exists()
    results.append({'case': 'unsupported_inputs_before_composition'})

    # A deployment supervisor must own the whole process scope when the parent itself is killed.
    worker.write_text(prefix + 'file_put_contents(__DIR__ . "/observed-pid", (string) getmypid()); set_time_limit(0); usleep(6_000_000);')
    pid_file.unlink(missing_ok=True)
    parent = subprocess.Popen([PHP, str(clone / 'console.php'), 'generate-test-tone'], cwd=clone,
                              env=environment, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                              start_new_session=True)
    try:
        deadline = time.monotonic() + 2
        while (not pid_file.exists() or not pid_file.read_text().isdigit()) and time.monotonic() < deadline:
            time.sleep(0.01)
        assert pid_file.exists() and pid_file.read_text().isdigit(), 'interruption handshake missing'
        child = int(pid_file.read_text())
        os.killpg(parent.pid, signal.SIGKILL)
        parent.communicate(timeout=2)
        deadline = time.monotonic() + 1
        while running(child) and time.monotonic() < deadline:
            time.sleep(0.01)
        assert not running(child) and not marker.exists()
        with sqlite3.connect(database) as db:
            assert db.execute('SELECT * FROM exports ORDER BY export_id').fetchall() == before
        results.append({'case': 'supervised_parent_interruption_before_storage'})
    finally:
        if parent.poll() is None:
            os.killpg(parent.pid, signal.SIGKILL)
            parent.communicate(timeout=2)

report = {'scope': 'REFERENCE_ONLY', 'verified_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'cases': results, 'bytes': len(DATA), 'independent_wave_frames': len(samples),
          'aws_calls': 0, 'temporary_storage_removed': True}
bound_sources = [ROOT / 'src' / name for name in sources] + [ROOT / 'console.php', ROOT / 'bootstrap.php',
    ROOT / 'schema.sql.fixture', ROOT / 'tests/Fixture.php', ROOT / 'tests/tone-process.py', ROOT / 'worker/test-tone.php']
report['source_sha256'] = {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
                         for path in sorted(bound_sources)}
(ROOT.parents[1] / 'tmp/generated-tone-process.json').write_text(json.dumps(report, indent=2) + '\n')
print(f'PASS generated tone process: {len(results)} cases; REFERENCE_ONLY')
