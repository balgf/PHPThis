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

function proveStarterOuterHttpFailure(string $projectRoot): void
{
    $outerParent = $projectRoot . '/tmp';

    if (!is_dir($outerParent) && !mkdir($outerParent, 0700) && !is_dir($outerParent)) {
        throw new RuntimeException('Unable to create the starter outer-boundary test directory.');
    }

    $outerDirectory = $outerParent . '/outer-http-' . bin2hex(random_bytes(8));
    $outerLogPath = $outerDirectory . '/server.log';
    $outerProcess = null;

    try {
        if (
            !mkdir($outerDirectory . '/public', 0700, true)
            || !copy($projectRoot . '/public/index.php', $outerDirectory . '/public/index.php')
            || !copy(__DIR__ . '/fixtures/failing-bootstrap.php', $outerDirectory . '/bootstrap.php')
            || !symlink($projectRoot . '/vendor', $outerDirectory . '/vendor')
            || file_put_contents($outerLogPath, '') !== 0
            || !chmod($outerLogPath, 0600)
        ) {
            throw new RuntimeException('Unable to create the starter outer-boundary server tree.');
        }

        [$outerProcess, $outerPort] = startStarterHttpServer($outerDirectory, $outerLogPath);
        $outerResponse = requestStarterHttpServer($outerPort);
        proc_terminate($outerProcess);
        proc_close($outerProcess);
        $outerProcess = null;
        $outerLog = file_get_contents($outerLogPath);
        $outerHeaders = array_change_key_case($outerResponse['headers'], CASE_LOWER);
        $genericResponse = (new \PHPThis\Http\UnknownFailureBoundary())->respond();

        if (
            $outerResponse['status'] !== 500
            || $outerResponse['body'] !== $genericResponse->body
            || ($outerHeaders['content-type'] ?? null) !== 'application/json; charset=utf-8'
            || ($outerHeaders['cache-control'] ?? null) !== 'private, no-store'
            || isset($outerHeaders['x-request-id'])
            || !is_string($outerLog)
        ) {
            throw new RuntimeException('The starter outer HTTP boundary must return one generic response.');
        }

        assertStarterApplicationLogPayload(
            $outerLog,
            'application.http_outer_failure',
            'application.http_outer_failure failure_class=StarterSafeSapiOuterFailure',
            'starter outer HTTP failure',
        );

        foreach ([
            'starter-bootstrap-private-sentinel',
            'SQLSTATE',
            '/private/starter/bootstrap.php',
            'Fatal error',
            'Uncaught',
            'Stack trace',
        ] as $forbidden) {
            if (str_contains($outerResponse['body'], $forbidden) || str_contains($outerLog, $forbidden)) {
                throw new RuntimeException('The starter outer HTTP boundary disclosed private failure data.');
            }
        }

        if (str_contains($outerResponse['body'], 'StarterSafeSapiOuterFailure')) {
            throw new RuntimeException('The starter outer HTTP response disclosed its safe event class.');
        }
    } finally {
        if (is_resource($outerProcess)) {
            proc_terminate($outerProcess);
            proc_close($outerProcess);
        }

        removeStarterTestDirectory($outerDirectory);
    }
}

function assertStarterApplicationLogPayload(
    string $log,
    string $eventName,
    string $expectedPayload,
    string $context,
): void {
    $lines = preg_split('/\R/', $log);
    $payloads = [];
    $builtInServerTimestamp = '/\A\[[A-Z][a-z]{2} [A-Z][a-z]{2} '
        . '[ 0-9][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4}\] /D';

    if (!is_array($lines)) {
        throw new RuntimeException('Unable to split the ' . $context . ' log.');
    }

    foreach ($lines as $line) {
        $applicationLine = preg_replace($builtInServerTimestamp, '', $line, 1);

        if (!is_string($applicationLine)) {
            throw new RuntimeException('Unable to normalize the ' . $context . ' log.');
        }

        if (str_contains($applicationLine, $eventName)) {
            $payloads[] = $applicationLine;
        }
    }

    if ($payloads !== [$expectedPayload]) {
        throw new RuntimeException('The ' . $context . ' application log payload changed.');
    }
}

/** @return array{0: resource, 1: int} */
function startStarterHttpServer(string $projectRoot, string $logPath): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

    if (!is_resource($socket)) {
        throw new RuntimeException('STARTER_HTTP_PORT_FAILED');
    }

    $socketName = stream_socket_get_name($socket, false);
    fclose($socket);
    $separator = is_string($socketName) ? strrpos($socketName, ':') : false;
    $portValue = $separator === false ? null : substr($socketName, $separator + 1);
    $port = is_string($portValue) ? filter_var($portValue, FILTER_VALIDATE_INT) : false;

    if (!is_int($port) || $port < 1 || $port > 65_535) {
        throw new RuntimeException('STARTER_HTTP_PORT_FAILED');
    }

    $process = @proc_open(
        [
            PHP_BINARY,
            '-d',
            'error_reporting=-1',
            '-d',
            'display_errors=0',
            '-d',
            'display_startup_errors=0',
            '-d',
            'log_errors=1',
            '-d',
            'zend.exception_ignore_args=1',
            '-S',
            '127.0.0.1:' . $port,
            '-t',
            $projectRoot . '/public',
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ],
        $pipes,
        $projectRoot,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('STARTER_HTTP_START_FAILED');
    }

    fclose($pipes[0]);
    $deadline = hrtime(true) + 5_000_000_000;

    do {
        $probe = @fsockopen('127.0.0.1', $port, $probeError, $probeMessage, 0.05);

        if (is_resource($probe)) {
            fclose($probe);

            return [$process, $port];
        }

        usleep(10_000);
    } while (hrtime(true) < $deadline);

    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('STARTER_HTTP_READY_FAILED');
}

/** @return array{status: int, headers: array<string, string>, body: string} */
function requestStarterHttpServer(int $port): array
{
    $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 2.0);

    if (!is_resource($socket)) {
        throw new RuntimeException('STARTER_HTTP_CONNECT_FAILED');
    }

    stream_set_timeout($socket, 5);
    $request = "GET /health?debug=1&environment=local HTTP/1.1\r\n"
        . 'Host: 127.0.0.1:' . $port . "\r\n"
        . "X-Debug: on\r\n"
        . "X-Environment: local\r\n"
        . "Cookie: debug=on; environment=local\r\n"
        . "Connection: close\r\n\r\n";

    if (fwrite($socket, $request) !== strlen($request)) {
        fclose($socket);
        throw new RuntimeException('STARTER_HTTP_WRITE_FAILED');
    }

    $raw = stream_get_contents($socket);
    fclose($socket);

    if (!is_string($raw)) {
        throw new RuntimeException('STARTER_HTTP_READ_FAILED');
    }

    $parts = explode("\r\n\r\n", $raw, 2);
    $head = $parts[0];
    $body = $parts[1] ?? '';
    $lines = explode("\r\n", $head);
    $statusLine = array_shift($lines);

    if (preg_match('/\AHTTP\/1\.[01] ([0-9]{3}) /D', $statusLine, $match) !== 1) {
        throw new RuntimeException('STARTER_HTTP_STATUS_FAILED');
    }

    $headers = [];

    foreach ($lines as $line) {
        $separator = strpos($line, ':');

        if ($separator === false) {
            throw new RuntimeException('STARTER_HTTP_HEADER_FAILED');
        }

        $headers[substr($line, 0, $separator)] = ltrim(substr($line, $separator + 1));
    }

    return ['status' => (int) $match[1], 'headers' => $headers, 'body' => $body];
}

function removeStarterTestDirectory(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('STARTER_TEST_FILE_CLEANUP_FAILED');
        }

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $entries = scandir($path);

    if (!is_array($entries)) {
        throw new RuntimeException('STARTER_TEST_DIRECTORY_READ_FAILED');
    }

    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeStarterTestDirectory($path . '/' . $entry);
        }
    }

    if (!rmdir($path)) {
        throw new RuntimeException('STARTER_TEST_DIRECTORY_CLEANUP_FAILED');
    }
}
