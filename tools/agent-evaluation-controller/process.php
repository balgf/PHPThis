<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_PROCESS_READ_BYTES = 8_192;
const AGENT_EVALUATION_CONTROLLER_PROCESS_STDIN_BYTES = 1_048_576;
const AGENT_EVALUATION_CONTROLLER_PROCESS_POLL_MICROSECONDS = 20_000;
const AGENT_EVALUATION_CONTROLLER_PROCESS_TERMINATION_GRACE_MICROSECONDS = 250_000;
const AGENT_EVALUATION_CONTROLLER_PROCESS_GROUP_HANDSHAKE_MICROSECONDS = 500_000;

/**
 * @param array<mixed> $arguments
 * @param array<mixed> $environment
 * @return array{
 *   exit_code: int,
 *   stdout: string,
 *   stderr: string,
 *   elapsed_milliseconds: int,
 *   timed_out: bool,
 *   output_limit_exceeded: bool,
 *   termination_reason: string,
 *   cleanup: array{
 *     process_group_created: bool,
 *     terminate_sent: bool,
 *     kill_sent: bool,
 *     process_reaped: bool,
 *     process_group_absent: bool
 *   }
 * }
 */
function agentEvaluationControllerRunProcess(
    array $arguments,
    string $workingDirectory,
    array $environment,
    string $standardInput,
    int $wallSeconds,
    int $outputBytes,
): array {
    $request = agentEvaluationControllerValidateProcessRequest(
        $arguments,
        $workingDirectory,
        $environment,
        $standardInput,
        $wallSeconds,
        $outputBytes,
    );
    $arguments = $request['arguments'];
    $environment = $request['environment'];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
        3 => ['pipe', 'w'],
    ];
    $pipes = [];
    $started = hrtime(true);
    $groupWrapper = <<<'PHP'
if (
    !function_exists('posix_setsid')
    || !function_exists('posix_getpid')
    || !function_exists('pcntl_exec')
) {
    exit(126);
}
$group = posix_setsid();
$handshake = fopen('php://fd/3', 'wb');
if (!is_int($group) || $group < 1 || !is_resource($handshake)) {
    exit(126);
}
fwrite($handshake, 'READY:' . posix_getpid() . "\n");
fclose($handshake);
$executable = $argv[1] ?? null;
if (!is_string($executable) || $executable === '') {
    exit(126);
}
pcntl_exec($executable, array_slice($argv, 2));
exit(127);
PHP;
    $wrappedArguments = [PHP_BINARY, '-r', $groupWrapper, ...$arguments];
    $process = proc_open(
        $wrappedArguments,
        $descriptors,
        $pipes,
        $workingDirectory,
        $environment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROCESS_START_FAILED');
    }

    if (
        !isset($pipes[0], $pipes[1], $pipes[2], $pipes[3])
        || !is_resource($pipes[0])
        || !is_resource($pipes[1])
        || !is_resource($pipes[2])
        || !is_resource($pipes[3])
    ) {
        proc_terminate($process, 9);
        proc_close($process);
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROCESS_PIPE_SETUP_FAILED');
    }

    $status = proc_get_status($process);
    $processId = $status['pid'] > 0 ? $status['pid'] : null;
    $processGroupCreated = agentEvaluationControllerReadProcessGroupHandshake(
        $pipes[3],
        $processId,
    );
    fclose($pipes[3]);
    stream_set_blocking($pipes[0], false);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $standardInputOffset = 0;
    $standardInputOpen = true;
    $standardOutputOpen = true;
    $standardErrorOpen = true;
    $standardOutput = '';
    $standardError = '';
    $retainedOutputBytes = 0;
    $timedOut = false;
    $outputLimitExceeded = false;
    $terminateSent = false;
    $killSent = false;
    $exitCode = -1;
    $running = true;
    $terminationStarted = null;
    $deadline = $started + ($wallSeconds * 1_000_000_000);

    if ($standardInput === '') {
        fclose($pipes[0]);
        $standardInputOpen = false;
    }

    try {
        while ($running || $standardOutputOpen || $standardErrorOpen) {
            $now = hrtime(true);

            if (!$timedOut && !$outputLimitExceeded && $now >= $deadline) {
                $timedOut = true;
                $terminationStarted = $now;
                $terminateSent = agentEvaluationControllerTerminateProcess(
                    $process,
                    $processId,
                    $processGroupCreated,
                    15,
                );
            }

            if (
                ($timedOut || $outputLimitExceeded)
                && !$killSent
                && is_int($terminationStarted)
                && ($now - $terminationStarted)
                    >= (AGENT_EVALUATION_CONTROLLER_PROCESS_TERMINATION_GRACE_MICROSECONDS * 1_000)
            ) {
                $killSent = agentEvaluationControllerTerminateProcess(
                    $process,
                    $processId,
                    $processGroupCreated,
                    9,
                );
            }

            if ($standardInputOpen && $standardInputOffset >= strlen($standardInput)) {
                fclose($pipes[0]);
                $standardInputOpen = false;
            }

            $read = [];
            $write = [];

            if ($standardOutputOpen) {
                $read[] = $pipes[1];
            }

            if ($standardErrorOpen) {
                $read[] = $pipes[2];
            }

            if ($standardInputOpen && !$timedOut && !$outputLimitExceeded) {
                $write[] = $pipes[0];
            }

            if ($read !== [] || $write !== []) {
                $except = null;
                $selected = @stream_select($read, $write, $except, 0, AGENT_EVALUATION_CONTROLLER_PROCESS_POLL_MICROSECONDS);

                if ($selected === false) {
                    $read = [];
                    $write = [];
                    usleep(AGENT_EVALUATION_CONTROLLER_PROCESS_POLL_MICROSECONDS);
                }
            } else {
                usleep(AGENT_EVALUATION_CONTROLLER_PROCESS_POLL_MICROSECONDS);
            }

            if ($write !== [] && $standardInputOpen) {
                $written = fwrite(
                    $pipes[0],
                    substr($standardInput, $standardInputOffset, AGENT_EVALUATION_CONTROLLER_PROCESS_READ_BYTES),
                );

                if ($written === false) {
                    fclose($pipes[0]);
                    $standardInputOpen = false;
                } else {
                    $standardInputOffset += $written;
                }
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, AGENT_EVALUATION_CONTROLLER_PROCESS_READ_BYTES);

                if (!is_string($chunk)) {
                    $chunk = '';
                }

                if ($chunk !== '') {
                    $remaining = max(0, $outputBytes - $retainedOutputBytes);
                    $retained = substr($chunk, 0, $remaining);

                    if ($stream === $pipes[1]) {
                        $standardOutput .= $retained;
                    } else {
                        $standardError .= $retained;
                    }

                    $retainedOutputBytes += strlen($retained);

                    if (!$outputLimitExceeded && strlen($chunk) > $remaining) {
                        $outputLimitExceeded = true;
                        $terminationStarted = hrtime(true);
                        $terminateSent = agentEvaluationControllerTerminateProcess(
                            $process,
                            $processId,
                            $processGroupCreated,
                            15,
                        ) || $terminateSent;
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

            if (
                !$running
                && !$standardOutputOpen
                && !$standardErrorOpen
            ) {
                break;
            }

            if (
                ($timedOut || $outputLimitExceeded)
                && $killSent
                && is_int($terminationStarted)
                && (hrtime(true) - $terminationStarted) >= 1_000_000_000
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

    $closedExitCode = proc_close($process);

    if ($exitCode < 0 && $closedExitCode >= 0) {
        $exitCode = $closedExitCode;
    }

    $processReaped = !$running || $closedExitCode >= 0;
    $processGroupAbsent = agentEvaluationControllerProcessGroupAbsent(
        $processId,
        $processGroupCreated,
    );

    if (!$processGroupAbsent) {
        $terminateSent = agentEvaluationControllerSignalProcessGroup(
            $processId,
            $processGroupCreated,
            15,
        ) || $terminateSent;
        $processGroupAbsent = agentEvaluationControllerWaitForProcessGroupAbsence(
            $processId,
            $processGroupCreated,
            AGENT_EVALUATION_CONTROLLER_PROCESS_TERMINATION_GRACE_MICROSECONDS,
        );
    }

    if (!$processGroupAbsent) {
        $killSent = agentEvaluationControllerSignalProcessGroup(
            $processId,
            $processGroupCreated,
            9,
        ) || $killSent;
        $processGroupAbsent = agentEvaluationControllerWaitForProcessGroupAbsence(
            $processId,
            $processGroupCreated,
            1_000_000,
        );
    }
    $elapsedMilliseconds = agentEvaluationControllerElapsedMilliseconds($started);
    $terminationReason = $timedOut
        ? 'wall_time_limit'
        : ($outputLimitExceeded
            ? 'output_limit'
            : (!$processReaped || !$processGroupAbsent
                ? 'cleanup_failed'
                : ($exitCode === 0 ? 'completed' : 'process_failed')));

    return [
        'exit_code' => $exitCode,
        'stdout' => $standardOutput,
        'stderr' => $standardError,
        'elapsed_milliseconds' => $elapsedMilliseconds,
        'timed_out' => $timedOut,
        'output_limit_exceeded' => $outputLimitExceeded,
        'termination_reason' => $terminationReason,
        'cleanup' => [
            'process_group_created' => $processGroupCreated,
            'terminate_sent' => $terminateSent,
            'kill_sent' => $killSent,
            'process_reaped' => $processReaped,
            'process_group_absent' => $processGroupAbsent,
        ],
    ];
}

/**
 * @param array<mixed> $arguments
 * @param array<mixed> $environment
 * @return array{
 *   arguments: non-empty-list<non-empty-string>,
 *   environment: array<string, string>
 * }
 */
function agentEvaluationControllerValidateProcessRequest(
    array $arguments,
    string $workingDirectory,
    array $environment,
    string $standardInput,
    int $wallSeconds,
    int $outputBytes,
): array {
    if (!array_is_list($arguments) || $arguments === []) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_ARGV_INVALID');
    }

    $validatedArguments = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || $argument === '' || str_contains($argument, "\0")) {
            throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_ARGV_INVALID');
        }

        $validatedArguments[] = $argument;
    }

    $executable = $validatedArguments[0];

    if (
        !str_starts_with($executable, DIRECTORY_SEPARATOR)
        || !is_file($executable)
        || is_link($executable)
        || !is_executable($executable)
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_EXECUTABLE_INVALID');
    }

    $resolvedWorkingDirectory = realpath($workingDirectory);

    if (
        !is_string($resolvedWorkingDirectory)
        || $resolvedWorkingDirectory !== $workingDirectory
        || !is_dir($workingDirectory)
        || is_link($workingDirectory)
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_WORKING_DIRECTORY_INVALID');
    }

    $validatedEnvironment = [];

    foreach ($environment as $name => $value) {
        if (
            !is_string($name)
            || !is_string($value)
            || preg_match('/\A[A-Z_][A-Z0-9_]*\z/D', $name) !== 1
            || str_contains($value, "\0")
        ) {
            throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_ENVIRONMENT_INVALID');
        }

        $validatedEnvironment[$name] = $value;
    }

    ksort($validatedEnvironment, SORT_STRING);
    $minimalEnvironment = [
        'LANG' => 'C',
        'LC_ALL' => 'C',
        'PATH' => '/usr/bin:/bin',
    ];

    if ($validatedEnvironment !== $minimalEnvironment) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_ENVIRONMENT_NOT_MINIMAL');
    }

    if (
        str_contains($standardInput, "\0")
        || strlen($standardInput) > AGENT_EVALUATION_CONTROLLER_PROCESS_STDIN_BYTES
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_STDIN_INVALID');
    }

    if ($wallSeconds < 1 || $wallSeconds > 86_400) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_WALL_LIMIT_INVALID');
    }

    if ($outputBytes < 1 || $outputBytes > 16_777_216) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_PROCESS_OUTPUT_LIMIT_INVALID');
    }

    return [
        'arguments' => $validatedArguments,
        'environment' => $validatedEnvironment,
    ];
}

function agentEvaluationControllerElapsedMilliseconds(int|float $started): int
{
    $elapsed = hrtime(true) - $started;

    if (is_int($elapsed)) {
        return intdiv(max(0, $elapsed), 1_000_000);
    }

    return max(0, (int) floor($elapsed / 1_000_000));
}

/** Fixed test-fixture primitive used only to prove same-group descendant cleanup. */
function agentEvaluationControllerSpawnSyntheticDescendant(): int
{
    if (!function_exists('pcntl_fork')) {
        return -1;
    }

    return pcntl_fork();
}

/** @param resource $process */
function agentEvaluationControllerTerminateProcess(
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

function agentEvaluationControllerProcessGroupAbsent(?int $processId, bool $processGroupCreated): bool
{
    if (!$processGroupCreated || $processId === null || !function_exists('posix_kill')) {
        return false;
    }

    return !posix_kill(-$processId, 0);
}

function agentEvaluationControllerSignalProcessGroup(
    ?int $processId,
    bool $processGroupCreated,
    int $signal,
): bool {
    if (!$processGroupCreated || $processId === null || !function_exists('posix_kill')) {
        return false;
    }

    return posix_kill(-$processId, $signal);
}

function agentEvaluationControllerWaitForProcessGroupAbsence(
    ?int $processId,
    bool $processGroupCreated,
    int $microseconds,
): bool {
    $deadline = hrtime(true) + ($microseconds * 1_000);

    while (hrtime(true) < $deadline) {
        if (agentEvaluationControllerProcessGroupAbsent($processId, $processGroupCreated)) {
            return true;
        }

        usleep(AGENT_EVALUATION_CONTROLLER_PROCESS_POLL_MICROSECONDS);
    }

    return agentEvaluationControllerProcessGroupAbsent($processId, $processGroupCreated);
}

/** @param resource $handshake */
function agentEvaluationControllerReadProcessGroupHandshake(mixed $handshake, ?int $processId): bool
{
    if ($processId === null || !is_resource($handshake)) {
        return false;
    }

    stream_set_blocking($handshake, false);
    $source = '';
    $deadline = hrtime(true)
        + (AGENT_EVALUATION_CONTROLLER_PROCESS_GROUP_HANDSHAKE_MICROSECONDS * 1_000);

    while (hrtime(true) < $deadline && !str_contains($source, "\n")) {
        $read = [$handshake];
        $write = null;
        $except = null;
        $selected = @stream_select(
            $read,
            $write,
            $except,
            0,
            AGENT_EVALUATION_CONTROLLER_PROCESS_POLL_MICROSECONDS,
        );

        if ($selected === false || $selected === 0) {
            continue;
        }

        $chunk = fread($handshake, 64);

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
