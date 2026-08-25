<?php

declare(strict_types=1);

const STARTER_PROCESS_READ_BYTES = 8_192;
const STARTER_PROCESS_POLL_MICROSECONDS = 20_000;
const STARTER_PROCESS_TERMINATION_GRACE_MICROSECONDS = 250_000;
const STARTER_PROCESS_KILL_WAIT_MICROSECONDS = 1_000_000;

/**
 * @param list<string> $arguments
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runStarterPhpProcess(
    array $arguments,
    string $workingDirectory,
    int $wallMilliseconds,
    int $stdoutBytes,
    int $stderrBytes,
): array {
    $process = @proc_open(
        [PHP_BINARY, ...$arguments],
        [0 => ['pipe', 'rb'], 1 => ['pipe', 'wb'], 2 => ['pipe', 'wb']],
        $pipes,
        $workingDirectory,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('STARTER_PROCESS_START_FAILED');
    }

    if (
        !isset($pipes[0], $pipes[1], $pipes[2])
        || !is_resource($pipes[0])
        || !is_resource($pipes[1])
        || !is_resource($pipes[2])
    ) {
        $stopped = stopStarterProcess($process, $pipes);
        throw new RuntimeException(
            $stopped ? 'STARTER_PROCESS_PIPE_FAILED' : 'STARTER_PROCESS_CLEANUP_FAILED',
        );
    }

    fclose($pipes[0]);

    if (!@stream_set_blocking($pipes[1], false) || !@stream_set_blocking($pipes[2], false)) {
        $stopped = stopStarterProcess($process, [$pipes[1], $pipes[2]]);
        throw new RuntimeException(
            $stopped ? 'STARTER_PROCESS_PIPE_FAILED' : 'STARTER_PROCESS_CLEANUP_FAILED',
        );
    }

    $started = hrtime(true);
    $deadline = $started + ($wallMilliseconds * 1_000_000);
    $stdout = '';
    $stderr = '';
    $stdoutOpen = true;
    $stderrOpen = true;
    $running = true;
    $exitCode = -1;
    $failure = null;
    $terminationStarted = null;
    $killAttempted = false;

    try {
        while ($running || $stdoutOpen || $stderrOpen) {
            $now = hrtime(true);

            if ($failure === null && $now >= $deadline) {
                $failure = 'STARTER_PROCESS_WALL_LIMIT';
                $terminationStarted = $now;
                proc_terminate($process, 15);
            }

            if (
                $failure !== null
                && !$killAttempted
                && $terminationStarted !== null
                && ($now - $terminationStarted)
                    >= (STARTER_PROCESS_TERMINATION_GRACE_MICROSECONDS * 1_000)
            ) {
                proc_terminate($process, 9);
                $killAttempted = true;
            }

            $read = [];

            if ($stdoutOpen) {
                $read[] = $pipes[1];
            }

            if ($stderrOpen) {
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
                    STARTER_PROCESS_POLL_MICROSECONDS,
                );

                if ($selected === false) {
                    $read = [];
                    usleep(STARTER_PROCESS_POLL_MICROSECONDS);
                }
            } else {
                usleep(STARTER_PROCESS_POLL_MICROSECONDS);
            }

            foreach ($read as $stream) {
                $chunk = @fread($stream, STARTER_PROCESS_READ_BYTES);

                if (!is_string($chunk)) {
                    if ($failure === null) {
                        $failure = 'STARTER_PROCESS_READ_FAILED';
                        $terminationStarted = hrtime(true);
                        proc_terminate($process, 15);
                    }

                    $chunk = '';
                }

                if ($chunk !== '') {
                    if ($stream === $pipes[1]) {
                        $remaining = max(0, $stdoutBytes - strlen($stdout));
                        $stdout .= substr($chunk, 0, $remaining);

                        if ($failure === null && strlen($chunk) > $remaining) {
                            $failure = 'STARTER_PROCESS_OUTPUT_LIMIT';
                        }
                    } else {
                        $remaining = max(0, $stderrBytes - strlen($stderr));
                        $stderr .= substr($chunk, 0, $remaining);

                        if ($failure === null && strlen($chunk) > $remaining) {
                            $failure = 'STARTER_PROCESS_OUTPUT_LIMIT';
                        }
                    }

                    if ($failure !== null && $terminationStarted === null) {
                        $terminationStarted = hrtime(true);
                        proc_terminate($process, 15);
                    }
                }

                if (feof($stream)) {
                    fclose($stream);

                    if ($stream === $pipes[1]) {
                        $stdoutOpen = false;
                    } else {
                        $stderrOpen = false;
                    }
                }
            }

            $status = proc_get_status($process);
            $running = $status['running'];

            if (!$running && $status['exitcode'] >= 0) {
                $exitCode = $status['exitcode'];
            }

            if (!$running && !$stdoutOpen && !$stderrOpen) {
                break;
            }

            if (
                $failure !== null
                && $killAttempted
                && $terminationStarted !== null
                && (hrtime(true) - $terminationStarted)
                    >= (STARTER_PROCESS_KILL_WAIT_MICROSECONDS * 1_000)
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
        proc_terminate($process, 9);
        $stopDeadline = hrtime(true) + (STARTER_PROCESS_KILL_WAIT_MICROSECONDS * 1_000);

        while ($running && hrtime(true) < $stopDeadline) {
            usleep(STARTER_PROCESS_POLL_MICROSECONDS);
            $status = proc_get_status($process);
            $running = $status['running'];

            if (!$running && $status['exitcode'] >= 0) {
                $exitCode = $status['exitcode'];
            }
        }
    }

    if ($running) {
        throw new RuntimeException('STARTER_PROCESS_CLEANUP_FAILED');
    }

    $closedExitCode = proc_close($process);

    if ($exitCode < 0 && $closedExitCode >= 0) {
        $exitCode = $closedExitCode;
    }

    if ($failure !== null) {
        throw new RuntimeException($failure);
    }

    return [
        'exit_code' => $exitCode >= 0 ? $exitCode : 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @param resource $process
 * @param array<mixed> $pipes
 */
function stopStarterProcess(mixed $process, array $pipes): bool
{
    proc_terminate($process, 9);

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $running = true;
    $deadline = hrtime(true) + (STARTER_PROCESS_KILL_WAIT_MICROSECONDS * 1_000);

    while ($running && hrtime(true) < $deadline) {
        $status = proc_get_status($process);
        $running = $status['running'];

        if ($running) {
            usleep(STARTER_PROCESS_POLL_MICROSECONDS);
        }
    }

    if ($running) {
        return false;
    }

    proc_close($process);

    return true;
}

/**
 * @param Closure(): array{exit_code: int, stdout: string, stderr: string} $operation
 */
function starterProcessFailure(Closure $operation): string
{
    try {
        $operation();
    } catch (RuntimeException $failure) {
        return $failure->getMessage();
    }

    throw new RuntimeException('Starter process unexpectedly completed.');
}
