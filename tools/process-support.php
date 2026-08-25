<?php

declare(strict_types=1);

const PHPTHIS_MAINTAINER_PROCESS_READ_BYTES = 8_192;
const PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS = 20_000;
const PHPTHIS_MAINTAINER_PROCESS_TERMINATION_GRACE_MICROSECONDS = 250_000;
const PHPTHIS_MAINTAINER_PROCESS_GROUP_HANDSHAKE_MICROSECONDS = 500_000;
const PHPTHIS_MAINTAINER_PROCESS_KILL_WAIT_MICROSECONDS = 1_000_000;

/**
 * Run one maintainer-owned array command with bounded capture and cleanup.
 *
 * The helper creates and verifies a dedicated process group when the PHP CLI
 * exposes the required POSIX process functions. Other platforms retain the
 * directly opened child as their complete supported termination scope.
 *
 * @param array<mixed> $command
 * @param array<mixed>|null $environment
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runBoundedMaintainerProcess(
    array $command,
    string $workingDirectory,
    ?array $environment,
    int $wallMilliseconds,
    int $stdoutBytes,
    int $stderrBytes,
): array {
    $request = phpthisValidateMaintainerProcessRequest(
        $command,
        $workingDirectory,
        $environment,
        $wallMilliseconds,
        $stdoutBytes,
        $stderrBytes,
    );
    $command = $request['command'];
    $workingDirectory = $request['working_directory'];
    $environment = $request['environment'];
    $processGroupSupported = phpthisMaintainerProcessGroupSupported();
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $openedCommand = DIRECTORY_SEPARATOR === '/'
        ? phpthisResolveMaintainerProcessCommand($command, $workingDirectory)
        : $command;

    if ($processGroupSupported) {
        $descriptors[3] = ['pipe', 'w'];
        $descriptors[4] = ['pipe', 'r'];
        $openedCommand = [
            PHP_BINARY,
            '-r',
            phpthisMaintainerProcessGroupProgram(),
            ...$openedCommand,
        ];
    }

    $pipes = [];
    $started = hrtime(true);
    $deadline = $started + ($wallMilliseconds * 1_000_000);
    $process = @proc_open(
        $openedCommand,
        $descriptors,
        $pipes,
        $workingDirectory,
        $environment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('PHPTHIS_MAINTAINER_PROCESS_START_FAILED');
    }

    if (
        !isset($pipes[0], $pipes[1], $pipes[2])
        || !is_resource($pipes[0])
        || !is_resource($pipes[1])
        || !is_resource($pipes[2])
        || (
            $processGroupSupported
            && (
                !isset($pipes[3], $pipes[4])
                || !is_resource($pipes[3])
                || !is_resource($pipes[4])
            )
        )
    ) {
        $stopped = phpthisAbortMaintainerProcessStart($process, $pipes);
        throw new RuntimeException(
            $stopped
                ? 'PHPTHIS_MAINTAINER_PROCESS_PIPE_SETUP_FAILED'
                : 'PHPTHIS_MAINTAINER_PROCESS_CLEANUP_FAILED',
        );
    }

    $status = proc_get_status($process);
    $processId = $status['pid'] > 0 ? $status['pid'] : null;
    $processGroupCreated = false;

    if ($processGroupSupported) {
        $processGroupCreated = phpthisReadMaintainerProcessGroupHandshake(
            $pipes[3],
            $processId,
            $deadline,
        );
        fclose($pipes[3]);

        if (!$processGroupCreated) {
            $stopped = phpthisAbortMaintainerProcessStart(
                $process,
                [$pipes[0], $pipes[1], $pipes[2], $pipes[4]],
            );
            throw new RuntimeException(
                $stopped
                    ? (
                        hrtime(true) >= $deadline
                            ? 'PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT'
                            : 'PHPTHIS_MAINTAINER_PROCESS_GROUP_SETUP_FAILED'
                    )
                    : 'PHPTHIS_MAINTAINER_PROCESS_CLEANUP_FAILED',
            );
        }

        $acknowledged = phpthisWriteMaintainerProcessGroupAcknowledgement(
            $pipes[4],
            $deadline,
        );
        fclose($pipes[4]);

        if (!$acknowledged) {
            $stopped = phpthisAbortMaintainerProcessStart(
                $process,
                [$pipes[0], $pipes[1], $pipes[2]],
                $processId,
                true,
            );
            throw new RuntimeException(
                $stopped
                    ? (
                        hrtime(true) >= $deadline
                            ? 'PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT'
                            : 'PHPTHIS_MAINTAINER_PROCESS_GROUP_SETUP_FAILED'
                    )
                    : 'PHPTHIS_MAINTAINER_PROCESS_CLEANUP_FAILED',
            );
        }
    }

    fclose($pipes[0]);

    if (!@stream_set_blocking($pipes[1], false) || !@stream_set_blocking($pipes[2], false)) {
        $stopped = phpthisAbortMaintainerProcessStart(
            $process,
            [$pipes[1], $pipes[2]],
            $processId,
            $processGroupCreated,
        );
        throw new RuntimeException(
            $stopped
                ? 'PHPTHIS_MAINTAINER_PROCESS_PIPE_SETUP_FAILED'
                : 'PHPTHIS_MAINTAINER_PROCESS_CLEANUP_FAILED',
        );
    }

    $standardOutputOpen = true;
    $standardErrorOpen = true;
    $standardOutput = '';
    $standardError = '';
    $failure = null;
    $terminationStarted = null;
    $killAttempted = false;
    $exitCode = -1;
    $running = true;

    try {
        while ($running || $standardOutputOpen || $standardErrorOpen) {
            $now = hrtime(true);

            if ($failure === null && $now >= $deadline) {
                $failure = 'PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT';
                $terminationStarted = $now;
                phpthisTerminateMaintainerProcess(
                    $process,
                    $processId,
                    $processGroupCreated,
                    15,
                );
            }

            if (
                $failure !== null
                && !$killAttempted
                && $terminationStarted !== null
                && ($now - $terminationStarted)
                    >= (PHPTHIS_MAINTAINER_PROCESS_TERMINATION_GRACE_MICROSECONDS * 1_000)
            ) {
                phpthisTerminateMaintainerProcess(
                    $process,
                    $processId,
                    $processGroupCreated,
                    9,
                );
                $killAttempted = true;
            }

            $read = [];

            if ($standardOutputOpen) {
                $read[] = $pipes[1];
            }

            if ($standardErrorOpen) {
                $read[] = $pipes[2];
            }

            if ($read !== []) {
                $write = null;
                $except = null;
                $selected = @stream_select(
                    $read,
                    $write,
                    $except,
                    0,
                    PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS,
                );

                if ($selected === false) {
                    $read = [];
                    usleep(PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS);
                }
            } else {
                usleep(PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS);
            }

            foreach ($read as $stream) {
                $chunk = @fread($stream, PHPTHIS_MAINTAINER_PROCESS_READ_BYTES);

                if (!is_string($chunk)) {
                    if ($failure === null) {
                        $failure = 'PHPTHIS_MAINTAINER_PROCESS_OUTPUT_READ_FAILED';
                        $terminationStarted = hrtime(true);
                        phpthisTerminateMaintainerProcess(
                            $process,
                            $processId,
                            $processGroupCreated,
                            15,
                        );
                    }

                    $chunk = '';
                }

                if ($chunk !== '') {
                    if ($stream === $pipes[1]) {
                        $remaining = max(0, $stdoutBytes - strlen($standardOutput));
                        $standardOutput .= substr($chunk, 0, $remaining);

                        if ($failure === null && strlen($chunk) > $remaining) {
                            $failure = 'PHPTHIS_MAINTAINER_PROCESS_OUTPUT_LIMIT';
                        }
                    } else {
                        $remaining = max(0, $stderrBytes - strlen($standardError));
                        $standardError .= substr($chunk, 0, $remaining);

                        if ($failure === null && strlen($chunk) > $remaining) {
                            $failure = 'PHPTHIS_MAINTAINER_PROCESS_OUTPUT_LIMIT';
                        }
                    }

                    if ($failure !== null && $terminationStarted === null) {
                        $terminationStarted = hrtime(true);
                        phpthisTerminateMaintainerProcess(
                            $process,
                            $processId,
                            $processGroupCreated,
                            15,
                        );
                    }
                }

                if (feof($stream)) {
                    fclose($stream);

                    if ($stream === $pipes[1]) {
                        $standardOutputOpen = false;
                    } else {
                        $standardErrorOpen = false;
                    }
                }
            }

            $status = proc_get_status($process);
            $running = $status['running'];

            if (!$running && $status['exitcode'] >= 0) {
                $exitCode = $status['exitcode'];
            }

            if (!$running && !$standardOutputOpen && !$standardErrorOpen) {
                break;
            }

            if (
                $failure !== null
                && $killAttempted
                && $terminationStarted !== null
                && (hrtime(true) - $terminationStarted)
                    >= (PHPTHIS_MAINTAINER_PROCESS_KILL_WAIT_MICROSECONDS * 1_000)
            ) {
                break;
            }
        }
    } finally {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }

    if ($running) {
        phpthisTerminateMaintainerProcess(
            $process,
            $processId,
            $processGroupCreated,
            9,
        );
        $stopDeadline = hrtime(true)
            + (PHPTHIS_MAINTAINER_PROCESS_KILL_WAIT_MICROSECONDS * 1_000);

        while ($running && hrtime(true) < $stopDeadline) {
            usleep(PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS);
            $status = proc_get_status($process);
            $running = $status['running'];

            if (!$running && $status['exitcode'] >= 0) {
                $exitCode = $status['exitcode'];
            }
        }
    }

    if ($running) {
        throw new RuntimeException('PHPTHIS_MAINTAINER_PROCESS_CLEANUP_FAILED');
    }

    $closedExitCode = proc_close($process);

    if ($exitCode < 0 && $closedExitCode >= 0) {
        $exitCode = $closedExitCode;
    }

    $processGroupAbsent = phpthisMaintainerProcessGroupAbsent(
        $processId,
        $processGroupCreated,
    );

    if (!$processGroupAbsent) {
        phpthisSignalMaintainerProcessGroup($processId, $processGroupCreated, 15);
        $processGroupAbsent = phpthisWaitForMaintainerProcessGroupAbsence(
            $processId,
            $processGroupCreated,
            PHPTHIS_MAINTAINER_PROCESS_TERMINATION_GRACE_MICROSECONDS,
        );
    }

    if (!$processGroupAbsent) {
        phpthisSignalMaintainerProcessGroup($processId, $processGroupCreated, 9);
        $processGroupAbsent = phpthisWaitForMaintainerProcessGroupAbsence(
            $processId,
            $processGroupCreated,
            PHPTHIS_MAINTAINER_PROCESS_KILL_WAIT_MICROSECONDS,
        );
    }

    if (!$processGroupAbsent) {
        throw new RuntimeException('PHPTHIS_MAINTAINER_PROCESS_CLEANUP_FAILED');
    }

    if ($failure !== null) {
        throw new RuntimeException($failure);
    }

    return [
        'exit_code' => $exitCode >= 0 ? $exitCode : 1,
        'stdout' => $standardOutput,
        'stderr' => $standardError,
    ];
}

/**
 * @param array<mixed> $command
 * @param array<mixed>|null $environment
 * @return array{
 *   command: non-empty-list<string>,
 *   working_directory: non-empty-string,
 *   environment: array<string, string>|null
 * }
 */
function phpthisValidateMaintainerProcessRequest(
    array $command,
    string $workingDirectory,
    ?array $environment,
    int $wallMilliseconds,
    int $stdoutBytes,
    int $stderrBytes,
): array {
    if (!array_is_list($command) || $command === []) {
        throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_COMMAND_INVALID');
    }

    $validatedCommand = [];

    foreach ($command as $offset => $argument) {
        if (
            !is_string($argument)
            || ($offset === 0 && $argument === '')
            || str_contains($argument, "\0")
        ) {
            throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_COMMAND_INVALID');
        }

        $validatedCommand[] = $argument;
    }

    $resolvedWorkingDirectory = str_contains($workingDirectory, "\0")
        ? false
        : @realpath($workingDirectory);

    if (!is_string($resolvedWorkingDirectory) || !is_dir($resolvedWorkingDirectory)) {
        throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_WORKING_DIRECTORY_INVALID');
    }

    $validatedEnvironment = null;

    if ($environment !== null) {
        $validatedEnvironment = [];

        foreach ($environment as $name => $value) {
            if (
                !is_string($name)
                || !is_string($value)
                || str_contains($name, "\0")
                || str_contains($value, "\0")
            ) {
                throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_ENVIRONMENT_INVALID');
            }

            $validatedEnvironment[$name] = $value;
        }
    }

    if ($wallMilliseconds < 1 || $wallMilliseconds > 3_600_000) {
        throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_WALL_LIMIT_INVALID');
    }

    if ($stdoutBytes < 1 || $stdoutBytes > 67_108_864) {
        throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_STDOUT_LIMIT_INVALID');
    }

    if ($stderrBytes < 1 || $stderrBytes > 67_108_864) {
        throw new InvalidArgumentException('PHPTHIS_MAINTAINER_PROCESS_STDERR_LIMIT_INVALID');
    }

    return [
        'command' => $validatedCommand,
        'working_directory' => $resolvedWorkingDirectory,
        'environment' => $validatedEnvironment,
    ];
}

/**
 * @param non-empty-list<string> $command
 * @return non-empty-list<string>
 */
function phpthisResolveMaintainerProcessCommand(
    array $command,
    string $workingDirectory,
): array {
    $executable = $command[0];
    $resolvedExecutable = null;

    if (str_contains($executable, '/')) {
        $candidate = str_starts_with($executable, '/')
            ? $executable
            : $workingDirectory . '/' . $executable;

        if (is_file($candidate) && is_executable($candidate)) {
            $resolvedExecutable = $candidate;
        }
    } else {
        $path = getenv('PATH');

        if (!is_string($path)) {
            $path = '/usr/bin:/bin';
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $resolvedDirectory = $directory === ''
                ? $workingDirectory
                : (
                    str_starts_with($directory, '/')
                        ? $directory
                        : $workingDirectory . '/' . $directory
                );

            $candidate = $resolvedDirectory . '/' . $executable;

            if (is_file($candidate) && is_executable($candidate)) {
                $resolvedExecutable = $candidate;
                break;
            }
        }
    }

    if ($resolvedExecutable === null) {
        throw new RuntimeException('PHPTHIS_MAINTAINER_PROCESS_EXECUTABLE_NOT_FOUND');
    }

    return [$resolvedExecutable, ...array_slice($command, 1)];
}

function phpthisMaintainerProcessGroupSupported(): bool
{
    return function_exists('pcntl_exec')
        && function_exists('posix_setsid')
        && function_exists('posix_getpid')
        && function_exists('posix_kill')
        && function_exists('posix_get_last_error');
}

function phpthisMaintainerProcessGroupProgram(): string
{
    return <<<'PHP'
if (
    !function_exists('posix_setsid')
    || !function_exists('posix_getpid')
    || !function_exists('pcntl_exec')
) {
    exit(126);
}
$group = posix_setsid();
$handshake = fopen('php://fd/3', 'wb');
$acknowledgement = fopen('php://fd/4', 'rb');
if (
    !is_int($group)
    || $group < 1
    || !is_resource($handshake)
    || !is_resource($acknowledgement)
) {
    exit(126);
}
$ready = 'READY:' . posix_getpid() . "\n";
$readyOffset = 0;
while ($readyOffset < strlen($ready)) {
    $written = fwrite($handshake, substr($ready, $readyOffset));
    if (!is_int($written) || $written < 1) {
        exit(126);
    }
    $readyOffset += $written;
}
fclose($handshake);
$go = stream_get_contents($acknowledgement, 4);
fclose($acknowledgement);
if ($go !== "GO\n") {
    exit(126);
}
$executable = $argv[1] ?? null;
if (!is_string($executable) || $executable === '') {
    exit(126);
}
pcntl_exec($executable, array_slice($argv, 2));
exit(127);
PHP;
}

/** @param resource $process */
function phpthisTerminateMaintainerProcess(
    mixed $process,
    ?int $processId,
    bool $processGroupCreated,
    int $signal,
): bool {
    $sent = false;

    if ($processGroupCreated && $processId !== null && function_exists('posix_kill')) {
        $sent = posix_kill(-$processId, $signal);
    }

    return proc_terminate($process, $signal) || $sent;
}

function phpthisMaintainerProcessGroupAbsent(?int $processId, bool $processGroupCreated): bool
{
    if (!$processGroupCreated) {
        return true;
    }

    if ($processId === null || !function_exists('posix_kill')) {
        return false;
    }

    if (posix_kill(-$processId, 0)) {
        return false;
    }

    return function_exists('posix_get_last_error') && posix_get_last_error() === 3;
}

/**
 * @param resource $process
 * @param array<mixed> $pipes
 */
function phpthisAbortMaintainerProcessStart(
    mixed $process,
    array $pipes,
    ?int $processId = null,
    bool $processGroupCreated = false,
): bool {
    phpthisTerminateMaintainerProcess(
        $process,
        $processId,
        $processGroupCreated,
        9,
    );

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $running = true;
    $deadline = hrtime(true) + (PHPTHIS_MAINTAINER_PROCESS_KILL_WAIT_MICROSECONDS * 1_000);

    while ($running && hrtime(true) < $deadline) {
        $status = proc_get_status($process);
        $running = $status['running'];

        if ($running) {
            usleep(PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS);
        }
    }

    if ($running) {
        return false;
    }

    proc_close($process);

    if (
        !phpthisWaitForMaintainerProcessGroupAbsence(
            $processId,
            $processGroupCreated,
            PHPTHIS_MAINTAINER_PROCESS_KILL_WAIT_MICROSECONDS,
        )
    ) {
        return false;
    }

    return true;
}

function phpthisSignalMaintainerProcessGroup(
    ?int $processId,
    bool $processGroupCreated,
    int $signal,
): bool {
    if (!$processGroupCreated || $processId === null || !function_exists('posix_kill')) {
        return false;
    }

    return posix_kill(-$processId, $signal);
}

function phpthisWaitForMaintainerProcessGroupAbsence(
    ?int $processId,
    bool $processGroupCreated,
    int $microseconds,
): bool {
    $deadline = hrtime(true) + ($microseconds * 1_000);

    while (hrtime(true) < $deadline) {
        if (phpthisMaintainerProcessGroupAbsent($processId, $processGroupCreated)) {
            return true;
        }

        usleep(PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS);
    }

    return phpthisMaintainerProcessGroupAbsent($processId, $processGroupCreated);
}

/** @param resource $handshake */
function phpthisReadMaintainerProcessGroupHandshake(
    mixed $handshake,
    ?int $processId,
    int|float $callerDeadline,
): bool
{
    if ($processId === null || !is_resource($handshake)) {
        return false;
    }

    if (!@stream_set_blocking($handshake, false)) {
        return false;
    }
    $source = '';
    $deadline = min(
        $callerDeadline,
        hrtime(true) + (PHPTHIS_MAINTAINER_PROCESS_GROUP_HANDSHAKE_MICROSECONDS * 1_000),
    );

    while (hrtime(true) < $deadline && !str_contains($source, "\n")) {
        $read = [$handshake];
        $write = null;
        $except = null;
        $selected = @stream_select(
            $read,
            $write,
            $except,
            0,
            PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS,
        );

        if ($selected === false || $selected === 0) {
            continue;
        }

        $chunk = @fread($handshake, 64);

        if (!is_string($chunk)) {
            return false;
        }

        if ($chunk === '' && feof($handshake)) {
            return false;
        }

        $source .= $chunk;

        if (strlen($source) > 64) {
            return false;
        }
    }

    return $source === 'READY:' . $processId . "\n";
}

/** @param resource $acknowledgement */
function phpthisWriteMaintainerProcessGroupAcknowledgement(
    mixed $acknowledgement,
    int|float $deadline,
): bool {
    if (!is_resource($acknowledgement) || !@stream_set_blocking($acknowledgement, false)) {
        return false;
    }

    $source = "GO\n";
    $offset = 0;

    while ($offset < strlen($source) && hrtime(true) < $deadline) {
        $read = null;
        $write = [$acknowledgement];
        $except = null;
        $selected = @stream_select(
            $read,
            $write,
            $except,
            0,
            PHPTHIS_MAINTAINER_PROCESS_POLL_MICROSECONDS,
        );

        if ($selected === false || $selected === 0) {
            continue;
        }

        $written = @fwrite($acknowledgement, substr($source, $offset));

        if (!is_int($written) || $written < 1) {
            return false;
        }

        $offset += $written;
    }

    return $offset === strlen($source);
}
