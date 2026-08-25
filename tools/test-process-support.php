<?php

declare(strict_types=1);

require_once __DIR__ . '/process-support.php';

$root = dirname(__DIR__);
$workspace = sys_get_temp_dir() . '/phpthis-process-proof-' . bin2hex(random_bytes(12));
$capabilityVariant = getenv('PHPTHIS_PROCESS_PROOF_CAPABILITY_VARIANT') === '1';

if (!mkdir($workspace, 0700)) {
    throw new RuntimeException('Unable to create the process-support proof workspace.');
}

try {
    $resolvedWorkspace = realpath($workspace);

    if (!is_string($resolvedWorkspace)) {
        throw new RuntimeException('Unable to resolve the process-support proof workspace.');
    }

    $ordinary = runBoundedMaintainerProcess(
        [
            PHP_BINARY,
            '-r',
            <<<'PHP'
fwrite(STDOUT, getcwd() . "\n" . getenv('PHPTHIS_PROCESS_PROOF') . "\n");
fwrite(STDERR, "separate-error\n");
exit(7);
PHP,
        ],
        $workspace,
        ['PHPTHIS_PROCESS_PROOF' => 'synthetic'],
        5_000,
        65_536,
        65_536,
    );
    processSupportProofRequire(
        $ordinary === [
            'exit_code' => 7,
            'stdout' => $resolvedWorkspace . "\nsynthetic\n",
            'stderr' => "separate-error\n",
        ],
        'The bounded process helper changed exit, stream, working-directory, or environment behavior.',
    );

    $shellSentinel = $workspace . '/shell-sentinel';
    $inertArgument = '; touch ' . $shellSentinel;
    $arrayCommand = runBoundedMaintainerProcess(
        [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode([$argv[1], $argv[2]], JSON_THROW_ON_ERROR));', $inertArgument, ''],
        $workspace,
        null,
        5_000,
        65_536,
        65_536,
    );
    processSupportProofRequire(
        $arrayCommand['exit_code'] === 0
            && $arrayCommand['stdout'] === json_encode([$inertArgument, ''], JSON_THROW_ON_ERROR)
            && $arrayCommand['stderr'] === ''
            && !file_exists($shellSentinel),
        'The bounded process helper did not preserve array-command shell isolation.',
    );

    $minimalDeadlineFailure = processSupportProofFailure(
        static fn (): array => runBoundedMaintainerProcess(
            [PHP_BINARY, '-r', 'usleep(500_000);'],
            $workspace,
            null,
            1,
            65_536,
            65_536,
        ),
    );
    processSupportProofRequire(
        $minimalDeadlineFailure === 'PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT',
        'The caller wall limit did not bound process-group setup.',
    );

    if (function_exists('stream_socket_pair')) {
        $handshakePair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if (!is_array($handshakePair) || count($handshakePair) !== 2) {
            throw new RuntimeException('Unable to create the process-group handshake deadline proof.');
        }

        $handshakeStarted = hrtime(true);

        try {
            $handshakeAccepted = phpthisReadMaintainerProcessGroupHandshake(
                $handshakePair[0],
                1,
                $handshakeStarted + 10_000_000,
            );
        } finally {
            foreach ($handshakePair as $handshakeStream) {
                if (is_resource($handshakeStream)) {
                    fclose($handshakeStream);
                }
            }
        }

        processSupportProofRequire(
            !$handshakeAccepted && hrtime(true) - $handshakeStarted < 250_000_000,
            'The process-group handshake ignored the caller wall deadline.',
        );
    }

    $fallbackPidPath = $workspace . '/fallback-descendant.pid';

    if (function_exists('pcntl_fork') && function_exists('posix_kill')) {
        $fallbackResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-d',
                'disable_functions=pcntl_exec',
                '-r',
                <<<'PHP'
require $argv[1];
$pidPath = $argv[3];
$descendant = null;
try {
    $failure = null;
    try {
        runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-r',
                <<<'CHILD'
$child = pcntl_fork();
if ($child === -1) {
    exit(3);
}
if ($child === 0) {
    while (true) {
        usleep(20_000);
    }
}
file_put_contents($argv[1], (string) $child);
CHILD,
                $pidPath,
            ],
            $argv[2],
            null,
            750,
            65_536,
            65_536,
        );
    } catch (RuntimeException $caught) {
        $failure = $caught->getMessage();
    }

    $source = file_get_contents($pidPath);
    $descendant = is_string($source) ? filter_var($source, FILTER_VALIDATE_INT) : null;
    if ($failure !== 'PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT' || !is_int($descendant)) {
        exit(4);
    }
} finally {
    if (is_int($descendant)) {
        posix_kill($descendant, 9);
        $deadline = hrtime(true) + 1_000_000_000;
        while (posix_kill($descendant, 0) && hrtime(true) < $deadline) {
            usleep(20_000);
        }
    }
}
fwrite(STDOUT, "FALLBACK_FINITE\n");
PHP,
                __DIR__ . '/process-support.php',
                $workspace,
                $fallbackPidPath,
            ],
            $workspace,
            null,
            5_000,
            65_536,
            65_536,
        );
        processSupportProofRequire(
            $fallbackResult === [
                'exit_code' => 0,
                'stdout' => "FALLBACK_FINITE\n",
                'stderr' => '',
            ],
            'The direct-child fallback did not remain finite when a descendant retained its pipes.',
        );
        processSupportProofRequireRecordedPidsAreAbsent($fallbackPidPath);
    }

    $deadlock = runBoundedMaintainerProcess(
        [
            PHP_BINARY,
            '-r',
            <<<'PHP'
$remaining = 262_144;
while ($remaining > 0) {
    $written = fwrite(STDERR, str_repeat('e', min(8_192, $remaining)));
    if (!is_int($written) || $written < 1) {
        exit(4);
    }
    $remaining -= $written;
}
fwrite(STDOUT, "stdout-after-stderr\n");
PHP,
        ],
        $workspace,
        null,
        5_000,
        65_536,
        524_288,
    );
    processSupportProofRequire(
        $deadlock['exit_code'] === 0
            && $deadlock['stdout'] === "stdout-after-stderr\n"
            && strlen($deadlock['stderr']) === 262_144
            && trim($deadlock['stderr'], 'e') === '',
        'The bounded process helper did not drain stdout and stderr concurrently.',
    );

    $timeoutPidPath = $workspace . '/timeout-pids';
    $timeoutLockPath = $workspace . '/timeout.lock';
    $timeoutStarted = hrtime(true);
    $timeoutFailure = processSupportProofFailure(
        static fn (): array => runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-r',
                processSupportProofStallProgram(),
                $timeoutPidPath,
                phpthisMaintainerProcessGroupSupported() ? 'fork' : 'direct',
                $timeoutLockPath,
            ],
            $workspace,
            null,
            1_500,
            65_536,
            65_536,
        ),
    );
    processSupportProofRequire(
        $timeoutFailure === 'PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT',
        'The bounded process helper did not report its exact wall limit.',
    );
    processSupportProofRequire(
        hrtime(true) - $timeoutStarted < 5_000_000_000,
        'The bounded process helper exceeded its generous timeout cleanup bound.',
    );
    processSupportProofRequireLockReleased($timeoutLockPath);
    processSupportProofRequireRecordedPidsAreAbsent($timeoutPidPath);

    $outputPidPath = $workspace . '/output-limit-pid';
    $outputLockPath = $workspace . '/output-limit.lock';
    $outputSentinel = 'output-limit-sensitive-sentinel';
    $outputFailure = processSupportProofFailure(
        static fn (): array => runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-r',
                <<<'PHP'
file_put_contents($argv[1], (string) getmypid());
$lock = fopen($argv[3], 'c+b');
if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
    exit(4);
}
$chunk = str_repeat($argv[2], 1_024);
$remaining = 65_536;
while ($remaining > 0) {
    $written = fwrite(STDERR, substr($chunk, 0, min(strlen($chunk), $remaining)));
    if (!is_int($written) || $written < 1) {
        exit(5);
    }
    $remaining -= $written;
}
while (true) {
    usleep(20_000);
}
PHP,
                $outputPidPath,
                $outputSentinel,
                $outputLockPath,
            ],
            $workspace,
            null,
            5_000,
            65_536,
            32_768,
        ),
    );
    processSupportProofRequire(
        $outputFailure === 'PHPTHIS_MAINTAINER_PROCESS_OUTPUT_LIMIT'
            && !str_contains($outputFailure, $outputSentinel),
        'The bounded process helper did not report a fixed redacted output limit.',
    );
    processSupportProofRequireLockReleased($outputLockPath);
    processSupportProofRequireRecordedPidsAreAbsent($outputPidPath);

    $stdoutPidPath = $workspace . '/stdout-limit-pid';
    $stdoutLockPath = $workspace . '/stdout-limit.lock';
    $stdoutFailure = processSupportProofFailure(
        static fn (): array => runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-r',
                <<<'PHP'
file_put_contents($argv[1], (string) getmypid());
$lock = fopen($argv[2], 'c+b');
if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
    exit(4);
}
while (true) {
    $written = fwrite(STDOUT, str_repeat('o', 8_192));
    if (!is_int($written) || $written < 1) {
        exit(5);
    }
}
PHP,
                $stdoutPidPath,
                $stdoutLockPath,
            ],
            $workspace,
            null,
            5_000,
            32_768,
            65_536,
        ),
    );
    processSupportProofRequire(
        $stdoutFailure === 'PHPTHIS_MAINTAINER_PROCESS_OUTPUT_LIMIT',
        'The bounded process helper did not enforce the stdout limit.',
    );
    processSupportProofRequireLockReleased($stdoutLockPath);
    processSupportProofRequireRecordedPidsAreAbsent($stdoutPidPath);

    $rawEnvironment = runBoundedMaintainerProcess(
        [
            PHP_BINARY,
            '-r',
            <<<'PHP'
$value = getenv('PHPTHIS_PROCESS_EMPTY');
fwrite(STDOUT, $value === '' ? "EMPTY\n" : "NOT_EMPTY\n");
PHP,
        ],
        $workspace,
        ['' => 'PHPTHIS_PROCESS_EMPTY='],
        5_000,
        65_536,
        65_536,
    );
    processSupportProofRequire(
        $rawEnvironment['exit_code'] === 0
            && $rawEnvironment['stdout'] === "EMPTY\n"
            && $rawEnvironment['stderr'] === '',
        'The bounded process helper changed raw empty environment delivery.',
    );

    $relativeBin = $workspace . '/relative-bin';

    if (!mkdir($relativeBin, 0700)) {
        throw new RuntimeException('Unable to create the relative executable proof directory.');
    }

    $relativeExecutable = $relativeBin . '/relative-process-proof';

    if (!symlink(PHP_BINARY, $relativeExecutable)) {
        throw new RuntimeException('Unable to create the relative executable proof.');
    }

    $relativeResult = runBoundedMaintainerProcess(
        [
            'relative-bin/relative-process-proof',
            '-r',
            'fwrite(STDOUT, "RELATIVE_PATH_OK\\n");',
        ],
        $workspace,
        ['PATH' => '/definitely/missing'],
        5_000,
        65_536,
        65_536,
    );
    processSupportProofRequire(
        $relativeResult === [
            'exit_code' => 0,
            'stdout' => "RELATIVE_PATH_OK\n",
            'stderr' => '',
        ],
        'The bounded process helper did not resolve a relative executable against the child working directory.',
    );

    $originalWorkingDirectory = getcwd();

    if (!is_string($originalWorkingDirectory) || !chdir(dirname($workspace))) {
        throw new RuntimeException('Unable to enter the relative working-directory proof parent.');
    }

    try {
        $relativeWorkingDirectoryResult = runBoundedMaintainerProcess(
            [
                'relative-bin/relative-process-proof',
                '-r',
                'fwrite(STDOUT, getcwd() . "\\n");',
            ],
            basename($workspace),
            ['PATH' => '/definitely/missing'],
            5_000,
            65_536,
            65_536,
        );
    } finally {
        if (!chdir($originalWorkingDirectory)) {
            throw new RuntimeException('Unable to restore the process-support proof working directory.');
        }
    }

    processSupportProofRequire(
        $relativeWorkingDirectoryResult === [
            'exit_code' => 0,
            'stdout' => $resolvedWorkspace . "\n",
            'stderr' => '',
        ],
        'The bounded process helper did not canonicalize a relative working directory before command resolution.',
    );

    $originalPath = getenv('PATH');

    if (!putenv('PATH=relative-bin')) {
        throw new RuntimeException('Unable to install the relative PATH proof input.');
    }

    try {
        $ambientRelativePathResult = runBoundedMaintainerProcess(
            [
                'relative-process-proof',
                '-r',
                'fwrite(STDOUT, "RELATIVE_AMBIENT_PATH_OK\\n");',
            ],
            $workspace,
            ['PATH' => '/definitely/missing'],
            5_000,
            65_536,
            65_536,
        );
    } finally {
        $restoredPath = is_string($originalPath)
            ? putenv('PATH=' . $originalPath)
            : putenv('PATH');

        if (!$restoredPath) {
            throw new RuntimeException('Unable to restore PATH after its relative-entry proof.');
        }
    }

    processSupportProofRequire(
        $ambientRelativePathResult === [
            'exit_code' => 0,
            'stdout' => "RELATIVE_AMBIENT_PATH_OK\n",
            'stderr' => '',
        ],
        'The bounded process helper changed relative ambient PATH lookup semantics.',
    );

    if (!$capabilityVariant) {
        $ambientPathResult = runBoundedMaintainerProcess(
            ['git', '--version'],
            $workspace,
            ['PATH' => '/definitely/missing'],
            5_000,
            65_536,
            65_536,
        );
        processSupportProofRequire(
            $ambientPathResult['exit_code'] === 0
                && str_starts_with($ambientPathResult['stdout'], 'git version ')
                && $ambientPathResult['stderr'] === '',
            'The bounded process helper changed proc_open bare-executable lookup semantics.',
        );
    }

    $orphanPidPath = $workspace . '/orphan-pid';

    if (phpthisMaintainerProcessGroupSupported() && function_exists('pcntl_fork')) {
        $orphanResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-r',
                <<<'PHP'
$child = pcntl_fork();
if ($child === -1) {
    exit(3);
}
if ($child === 0) {
    fclose(STDOUT);
    fclose(STDERR);
    while (true) {
        usleep(20_000);
    }
}
file_put_contents($argv[1], (string) $child);
PHP,
                $orphanPidPath,
            ],
            $workspace,
            null,
            5_000,
            65_536,
            65_536,
        );
        processSupportProofRequire(
            $orphanResult['exit_code'] === 0
                && $orphanResult['stdout'] === ''
                && $orphanResult['stderr'] === '',
            'The same-group orphan control changed its direct-child result.',
        );
        processSupportProofRequireRecordedPidsAreAbsent($orphanPidPath);
    }
} finally {
    foreach ([
        $workspace . '/timeout-pids',
        $workspace . '/timeout.lock',
        $workspace . '/fallback-descendant.pid',
        $workspace . '/output-limit-pid',
        $workspace . '/output-limit.lock',
        $workspace . '/stdout-limit-pid',
        $workspace . '/stdout-limit.lock',
        $workspace . '/orphan-pid',
        $workspace . '/relative-bin/relative-process-proof',
    ] as $path) {
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove a process-support proof PID file.');
        }
    }

    if (is_dir($workspace . '/relative-bin') && !rmdir($workspace . '/relative-bin')) {
        throw new RuntimeException('Unable to remove the relative executable proof directory.');
    }

    if (is_dir($workspace) && !rmdir($workspace)) {
        throw new RuntimeException('Unable to remove the process-support proof workspace.');
    }
}

if (!$capabilityVariant) {
    foreach (['pcntl_exec', 'posix_get_last_error'] as $disabledFunction) {
        $capabilityResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                '-d',
                'disable_functions=' . $disabledFunction,
                __FILE__,
            ],
            $root,
            [
                'PHPTHIS_PROCESS_PROOF_CAPABILITY_VARIANT' => '1',
                'TMPDIR' => sys_get_temp_dir(),
            ],
            30_000,
            65_536,
            65_536,
        );
        processSupportProofRequire(
            $capabilityResult === [
                'exit_code' => 0,
                'stdout' => "PASS bounded maintainer process support: streams, limits, redaction, and cleanup\n",
                'stderr' => '',
            ],
            'The bounded process helper failed its capability-disabled fallback proof.',
        );
    }
}

fwrite(STDOUT, "PASS bounded maintainer process support: streams, limits, redaction, and cleanup\n");

function processSupportProofRequire(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param Closure(): array{exit_code: int, stdout: string, stderr: string} $operation
 */
function processSupportProofFailure(Closure $operation): string
{
    try {
        $operation();
    } catch (RuntimeException $failure) {
        return $failure->getMessage();
    }

    throw new RuntimeException('The bounded process operation unexpectedly completed.');
}

function processSupportProofStallProgram(): string
{
    return <<<'PHP'
$pids = [getmypid()];
$lock = fopen($argv[3], 'c+b');
if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
    exit(4);
}
if (($argv[2] ?? '') === 'fork' && function_exists('pcntl_fork')) {
    $child = pcntl_fork();
    if ($child === -1) {
        exit(3);
    }
    if ($child === 0) {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, static function (): void {});
        }
        while (true) {
            usleep(20_000);
        }
    }
    $pids[] = $child;
}
file_put_contents($argv[1], implode("\n", $pids));
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function (): void {});
}
while (true) {
    usleep(20_000);
}
PHP;
}

function processSupportProofRequireRecordedPidsAreAbsent(string $path): void
{
    $contents = file_get_contents($path);

    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('The bounded process proof did not record its child scope.');
    }

    $canObserveProcessIds = function_exists('posix_kill')
        && function_exists('posix_get_last_error');

    foreach (explode("\n", $contents) as $pidSource) {
        if (preg_match('/\A[1-9][0-9]*\z/D', $pidSource) !== 1) {
            throw new RuntimeException('The bounded process proof recorded an invalid process ID.');
        }

        $pid = filter_var($pidSource, FILTER_VALIDATE_INT);

        if (!is_int($pid)) {
            throw new RuntimeException('The bounded process proof process ID did not narrow to an integer.');
        }

        if (!$canObserveProcessIds) {
            continue;
        }

        $deadline = hrtime(true) + 1_000_000_000;

        while (phpthisProcessSupportProofPidExists($pid) && hrtime(true) < $deadline) {
            usleep(PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS);
        }

        if (phpthisProcessSupportProofPidExists($pid)) {
            throw new RuntimeException('The bounded process helper left a child process behind.');
        }
    }
}

function processSupportProofRequireLockReleased(string $path): void
{
    $lock = fopen($path, 'c+b');

    if (!is_resource($lock)) {
        throw new RuntimeException('The bounded process proof lock could not be opened.');
    }

    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('The bounded process helper left its supported child scope alive.');
        }

        flock($lock, LOCK_UN);
    } finally {
        fclose($lock);
    }
}

function phpthisProcessSupportProofPidExists(int $pid): bool
{
    if (!function_exists('posix_kill') || !function_exists('posix_get_last_error')) {
        throw new LogicException('Process-ID observation is unavailable.');
    }

    if (posix_kill($pid, 0)) {
        return true;
    }

    return posix_get_last_error() !== 3;
}
