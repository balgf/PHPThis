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
    bool $binaryInput = false,
): array {
    $request = agentEvaluationControllerValidateProcessRequest(
        $arguments,
        $workingDirectory,
        $environment,
        $standardInput,
        $wallSeconds,
        $outputBytes,
        $binaryInput,
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
    } catch (Throwable $failure) {
        agentEvaluationControllerTerminateProcess($process, $processId, $processGroupCreated, 9);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($process);
        if (!agentEvaluationControllerWaitForProcessGroupAbsence($processId, $processGroupCreated, 1_000_000)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_INTERRUPTED_PROCESS_CLEANUP_FAILED', 0, $failure);
        }
        throw $failure;
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
    bool $binaryInput = false,
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
        (!$binaryInput && str_contains($standardInput, "\0"))
        || strlen($standardInput) > ($binaryInput ? 16_777_216 : AGENT_EVALUATION_CONTROLLER_PROCESS_STDIN_BYTES)
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

/**
 * @param resource $process
 */
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

/**
 * @param resource $handshake
 */
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

const AGENT_EVALUATION_CONTROLLER_OCI_UID = 65_534;
const AGENT_EVALUATION_CONTROLLER_OCI_SCORE_UID = 65_533;
const AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_BYTES = 16_777_216;
const AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_BYTES = 805_306_368;
const AGENT_EVALUATION_CONTROLLER_OCI_TMP_BYTES = 184_549_376;
const AGENT_EVALUATION_CONTROLLER_OCI_SHM_BYTES = 16_777_216;

/**
 * @param array<string, mixed> $engine
 * @return array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>, identity: array<string, mixed>}
 */
function agentEvaluationControllerOciEngineFields(array $engine): array
{
    return [
        'binary' => agentEvaluationRequireString($engine, 'binary', 'OCI engine'),
        'socket' => agentEvaluationRequireString($engine, 'socket', 'OCI engine'),
        'config_root' => agentEvaluationRequireString($engine, 'config_root', 'OCI engine'),
        'control_root' => agentEvaluationRequireString($engine, 'control_root', 'OCI engine'),
        'configuration' => agentEvaluationRequireObject($engine, 'configuration', 'OCI engine'),
        'identity' => array_key_exists('identity', $engine) && $engine['identity'] !== []
            ? agentEvaluationRequireObject($engine, 'identity', 'OCI engine') : [],
    ];
}

/** @return array<string, string> */
function agentEvaluationControllerOciResourceNames(mixed $names): array
{
    if (!is_array($names)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESOURCE_LEDGER_INVALID');
    }

    $validated = [];

    foreach ($names as $role => $name) {
        if (!is_string($role) || !is_string($name)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESOURCE_LEDGER_INVALID');
        }

        $validated[$role] = $name;
    }

    return $validated;
}

/**
 * @param array<string, mixed> $resources
 * @return array{
 *   engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>},
 *   owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>,
 *   generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string
 * }
 */
function agentEvaluationControllerOciResourceState(array $resources): array
{
    $generation = $resources['generation'] ?? null;
    $stopped = $resources['generation_stopped'] ?? null;
    $destroyed = $resources['generation_destroyed'] ?? null;
    $frozen = $resources['frozen'] ?? null;

    if (($generation !== null && !is_string($generation)) || !is_bool($stopped) || !is_bool($destroyed) || !is_bool($frozen)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESOURCE_LEDGER_INVALID');
    }

    return [
        'engine' => agentEvaluationControllerOciEngineFields(agentEvaluationRequireObject($resources, 'engine', 'OCI ledger')),
        'owner' => agentEvaluationRequireString($resources, 'owner', 'OCI ledger'),
        'run_id' => agentEvaluationRequireString($resources, 'run_id', 'OCI ledger'),
        'containers' => agentEvaluationControllerOciResourceNames($resources['containers'] ?? null),
        'volumes' => agentEvaluationControllerOciResourceNames($resources['volumes'] ?? null),
        'generation' => $generation,
        'generation_stopped' => $stopped,
        'generation_destroyed' => $destroyed,
        'frozen' => $frozen,
        'candidate_target' => agentEvaluationRequireString($resources, 'candidate_target', 'OCI ledger'),
    ];
}

/**
 * @param array<string, mixed> $configuration
 * @return array<string, mixed>
 */
function agentEvaluationControllerOciPreflight(array $configuration, string $controlRoot): array
{
    foreach (['proc_open', 'pcntl_exec', 'pcntl_async_signals', 'pcntl_signal', 'pcntl_signal_get_handler',
        'posix_setsid', 'posix_getpid', 'posix_kill', 'curl_init', 'curl_exec', 'curl_version'] as $function) {
        if (!function_exists($function)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_HOST_CONTROL_UNAVAILABLE');
        }
    }
    $curl = curl_version();
    if (!is_array($curl) || !is_int($curl['features'] ?? null) || ($curl['features'] & CURL_VERSION_SSL) === 0) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_HOST_TLS_UNAVAILABLE');
    }
    $binary = $configuration['docker_binary'] ?? null;
    $socket = $configuration['docker_socket'] ?? null;
    if (!is_string($binary) || realpath($binary) !== $binary || !is_file($binary) || !is_executable($binary)
        || !is_string($socket) || !str_starts_with($socket, '/') || is_link($socket)
        || filetype($socket) !== 'socket' || preg_match('/[\x00-\x20\x7F]/', $socket) === 1) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ENGINE_UNAVAILABLE');
    }
    agentEvaluationControllerExistingRoot($controlRoot, 'OCI control root');
    $configRoot = $controlRoot . '/docker-config';
    agentEvaluationControllerFreshAbsoluteTarget($configRoot, 'OCI Docker configuration');
    if (!mkdir($configRoot, 0700) || file_put_contents($configRoot . '/config.json', "{}\n") === false) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONFIG_FAILED');
    }
    $engine = ['binary' => $binary, 'socket' => $socket, 'config_root' => $configRoot,
        'control_root' => $controlRoot, 'configuration' => $configuration];
    $version = agentEvaluationControllerOciJsonCommand($engine, ['version', '--format', '{{json .Server}}']);
    $info = agentEvaluationControllerOciJsonCommand($engine, ['info', '--format',
        '{"os":{{json .OSType}},"cgroup":{{json .CgroupVersion}},"memory":{{json .MemoryLimit}},"swap":{{json .SwapLimit}},"pids":{{json .PidsLimit}},"cpu":{{json .CPUCfsQuota}}}']);
    if (($version['Os'] ?? null) !== 'linux' || !is_string($version['Version'] ?? null)
        || ($info['os'] ?? null) !== 'linux' || ($info['cgroup'] ?? null) !== '2'
        || ($info['memory'] ?? null) !== true || ($info['swap'] ?? null) !== true
        || ($info['pids'] ?? null) !== true || ($info['cpu'] ?? null) !== true) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESOURCE_CONTROL_UNAVAILABLE');
    }
    $identities = [];
    foreach (['generation', 'scoring'] as $role) {
        $reference = $configuration[$role . '_image'] ?? null;
        if (!is_string($reference) || preg_match('/\A[a-z0-9][a-z0-9._:\/-]*@sha256:[a-f0-9]{64}\z/D', $reference) !== 1) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_IMAGE_NOT_PINNED');
        }
        $identity = agentEvaluationControllerOciJsonCommand($engine,
            ['image', 'inspect', '--format', '{{json .}}', $reference]);
        if (($identity['Os'] ?? null) !== 'linux'
            || !in_array($identity['Architecture'] ?? null, ['arm64', 'amd64'], true)
            || !is_string($identity['Id'] ?? null)
            || !is_array($identity['RepoDigests'] ?? null)
            || !in_array($reference, $identity['RepoDigests'], true)
            || !is_array($identity['Config'] ?? null)
            || ($identity['Config']['Volumes'] ?? []) !== []) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_IMAGE_IDENTITY_INVALID');
        }
        $identities[$role] = ['image_reference' => $reference, 'image_id' => $identity['Id'],
            'architecture' => $identity['Architecture']];
    }
    $engine['identity'] = ['engine_version' => $version['Version'], 'cgroup_version' => '2',
        'images' => $identities, 'network' => 'none-with-fixed-broker-pipe'];
    $engine['identity']['toolchains'] = agentEvaluationControllerOciVerifyToolchains($engine);
    return $engine;
}

/**
 * @param array<string, mixed> $engine
 * @param list<string> $arguments
 * @return array{exit_code: int, stdout: string, stderr: string, elapsed_milliseconds: int, timed_out: bool, output_limit_exceeded: bool, termination_reason: string, cleanup: array{process_group_created: bool, terminate_sent: bool, kill_sent: bool, process_reaped: bool, process_group_absent: bool}}
 */
function agentEvaluationControllerOciCommand(
    array $engine,
    array $arguments,
    string $input = '',
    int $wallSeconds = 30,
    int $outputBytes = 1_048_576,
    bool $binaryInput = false,
): array {
    $engine = agentEvaluationControllerOciEngineFields($engine);
    return agentEvaluationControllerRunProcess(
        [$engine['binary'], '--config', $engine['config_root'], '--host', 'unix://' . $engine['socket'], ...$arguments],
        $engine['control_root'], ['LANG' => 'C', 'LC_ALL' => 'C', 'PATH' => '/usr/bin:/bin'],
        $input, $wallSeconds, $outputBytes, $binaryInput,
    );
}

/**
 * @param array<string, mixed> $engine
 * @param list<string> $arguments
 * @return array<string, mixed>
 */
function agentEvaluationControllerOciJsonCommand(array $engine, array $arguments): array
{
    $result = agentEvaluationControllerOciCommand($engine, $arguments);
    if ($result['exit_code'] !== 0 || $result['termination_reason'] !== 'completed') {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_COMMAND_FAILED');
    }
    try {
        $value = json_decode($result['stdout'], true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESPONSE_INVALID');
    }
    if (!is_array($value)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESPONSE_INVALID');
    }
    $members = [];
    foreach ($value as $key => $member) {
        if (!is_string($key)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESPONSE_INVALID');
        }
        $members[$key] = $member;
    }
    return $members;
}

/**
 * @param array<string, mixed> $engine
 * @return array<string, mixed>
 */
function agentEvaluationControllerOciPrepare(array $engine, string $runId, string $candidateRoot, string $dependenciesRoot): array
{
    $engine = agentEvaluationControllerOciEngineFields($engine);
    if (preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RUN_ID_INVALID');
    }
    agentEvaluationControllerDescribeTree($candidateRoot, 'OCI candidate input', false);
    foreach (['tmp', 'vendor'] as $mountpoint) {
        if (file_exists($candidateRoot . '/' . $mountpoint) || is_link($candidateRoot . '/' . $mountpoint)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_MOUNTPOINT_COLLISION');
        }
    }
    $dependencyTree = agentEvaluationControllerDescribeTree($dependenciesRoot, 'OCI dependency input', true);
    foreach (array_keys($dependencyTree['files']) as $path) {
        if ($path === '.phpthis' || str_starts_with($path, '.phpthis/')) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_PREPARED_CACHE_NOT_EMPTY');
        }
    }
    $resources = ['engine' => $engine, 'owner' => 'phpthis-eval-' . bin2hex(random_bytes(16)),
        'run_id' => $runId, 'containers' => [], 'volumes' => [], 'generation' => null,
        'generation_stopped' => false, 'generation_destroyed' => false, 'frozen' => false, 'candidate_target' => $candidateRoot];
    try {
        foreach (['candidate' => AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_BYTES,
            'dependencies' => AGENT_EVALUATION_CONTROLLER_DISK_BYTES] as $role => $bytes) {
            $name = $resources['owner'] . '-' . $role;
            $pendingRole = 'pending-creation-' . $role;
            $resources['volumes'][$pendingRole] = $name;
            agentEvaluationControllerOciWriteLedger($resources);
            $result = agentEvaluationControllerOciCommand($engine, ['volume', 'create', '--driver', 'local',
                '--label', 'org.phpthis.evaluation.owner=' . $resources['owner'], '--opt', 'type=tmpfs',
                '--label', 'org.phpthis.evaluation.run_id=' . $runId,
                '--opt', 'device=tmpfs', '--opt', 'o=size=' . $bytes . ',uid=65534,gid=65534,mode=0755,nosuid,nodev', $name]);
            if ($result['exit_code'] !== 0 || $result['termination_reason'] !== 'completed' || trim($result['stdout']) !== $name) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_VOLUME_CREATE_FAILED');
            }
            unset($resources['volumes'][$pendingRole]);
            $resources['volumes'][$role] = $name;
            agentEvaluationControllerOciWriteLedger($resources);
            $volume = agentEvaluationControllerOciJsonCommand($engine, ['volume', 'inspect', '--format', '{{json .}}', $name]);
            $options = agentEvaluationRequireObject($volume, 'Options', 'OCI volume');
            if (($volume['Driver'] ?? null) !== 'local' || ($options['type'] ?? null) !== 'tmpfs'
                || ($options['device'] ?? null) !== 'tmpfs'
                || ($options['o'] ?? null) !== 'size=' . $bytes . ',uid=65534,gid=65534,mode=0755,nosuid,nodev') {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_VOLUME_POLICY_INVALID');
            }
        }
        $mounts = [
            'type=volume,src=' . $resources['volumes']['candidate'] . ',dst=/candidate,readonly,volume-nocopy',
            'type=volume,src=' . $resources['volumes']['dependencies'] . ',dst=/dependencies,readonly,volume-nocopy',
        ];
        $holder = agentEvaluationControllerOciCreateContainer($resources, 'holder', 'generation', $mounts,
            ['/bin/sleep', '86400']);
        agentEvaluationControllerOciRequireCommand($engine, ['start', $holder]);
        $prepare = agentEvaluationControllerOciCreateContainer($resources, 'prepare', 'generation', [
            'type=volume,src=' . $resources['volumes']['candidate'] . ',dst=/candidate,volume-nocopy',
            'type=volume,src=' . $resources['volumes']['dependencies'] . ',dst=/dependencies,volume-nocopy',
        ], ['/bin/sleep', '86400']);
        $archive = agentEvaluationControllerOciCandidateArchive($candidateRoot);
        $copy = agentEvaluationControllerOciCommand($engine, ['cp', '--archive', '-', $prepare . ':/candidate/'],
            $archive, 30, 1_048_576, true);
        if ($copy['exit_code'] !== 0 || $copy['termination_reason'] !== 'completed') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_COPY_FAILED');
        }
        $mountpointArchive = agentEvaluationControllerOciTarHeader('vendor/', 0755, 0, '5')
            . agentEvaluationControllerOciTarHeader('tmp/', 0755, 0, '5') . str_repeat("\0", 1024);
        $mountpointCopy = agentEvaluationControllerOciCommand($engine, ['cp', '--archive', '-', $prepare . ':/candidate/'],
            $mountpointArchive, 30, 1_048_576, true);
        if ($mountpointCopy['exit_code'] !== 0 || $mountpointCopy['termination_reason'] !== 'completed') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_COPY_FAILED');
        }
        agentEvaluationControllerOciRequireCommand($engine, ['cp', $dependenciesRoot . '/.', $prepare . ':/dependencies/'], 120);
        $cacheMountpoint = agentEvaluationControllerOciTarHeader('.phpthis/', 0755, 0, '5') . str_repeat("\0", 1024);
        $cacheCopy = agentEvaluationControllerOciCommand($engine, ['cp', '--archive', '-', $prepare . ':/dependencies/'],
            $cacheMountpoint, 30, 1_048_576, true);
        if ($cacheCopy['exit_code'] !== 0 || $cacheCopy['termination_reason'] !== 'completed') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CACHE_MOUNTPOINT_FAILED');
        }
        agentEvaluationControllerOciDestroyContainer($resources, 'prepare');
        $generation = agentEvaluationControllerOciCreateContainer($resources, 'generation', 'generation', [
            'type=volume,src=' . $resources['volumes']['candidate'] . ',dst=/candidate,volume-nocopy',
            'type=volume,src=' . $resources['volumes']['dependencies'] . ',dst=/candidate/vendor,readonly,volume-nocopy',
        ], ['/usr/bin/python3', '/opt/phpthis/relay.py']);
        $resources['generation'] = $generation;
        return $resources;
    } catch (Throwable $failure) {
        $cleanup = agentEvaluationControllerOciCleanup($resources);
        if (!$cleanup['verified']) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_PREPARATION_AND_CLEANUP_FAILED', 0, $failure);
        }
        throw $failure;
    }
}

/**
 * @param array<string, mixed> $resources
 * @param list<string> $mounts
 * @param non-empty-list<string> $command
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciCreateContainer(array &$resources, string $role, string $imageRole, array $mounts, array $command): string
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    $name = $resources['owner'] . '-' . $role;
    $uid = $imageRole === 'scoring' ? AGENT_EVALUATION_CONTROLLER_OCI_SCORE_UID : AGENT_EVALUATION_CONTROLLER_OCI_UID;
    $candidateScratch = in_array($role, ['generation', 'score-application-check', 'score-public-scorer'], true);
    $temporaryBytes = $candidateScratch ? 117_440_512 : AGENT_EVALUATION_CONTROLLER_OCI_TMP_BYTES;
    $arguments = ['create', '--pull', 'never', '--name', $name,
        '--label', 'org.phpthis.evaluation.owner=' . $resources['owner'], '--read-only',
        '--label', 'org.phpthis.evaluation.run_id=' . $resources['run_id'],
        '--user', $uid . ':' . $uid, '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true',
        '--network', 'none', '--ipc', 'private', '--cgroupns', 'private', '--pids-limit', '64',
        '--cpus', '1.0', '--memory', '1073741824', '--memory-swap', '1073741824',
        '--shm-size', '16777216', '--ulimit', 'core=0:0', '--ulimit', 'nofile=1024:1024',
        '--restart', 'no', '--log-driver', 'none', '--no-healthcheck', '--stop-timeout', '1',
        '--tmpfs', '/tmp:rw,nosuid,nodev,noexec,size=' . $temporaryBytes . ',mode=1777',
        '--env', 'PATH=/usr/local/bin:/usr/bin:/bin', '--env', 'HOME=/tmp/phpthis-home',
        '--workdir', '/candidate', '--interactive', '--entrypoint', $command[0]];
    $candidateCache = in_array($role, ['generation', 'score-application-check', 'score-public-scorer'], true);
    if ($candidateCache) {
        $arguments[] = '--tmpfs';
        $arguments[] = '/candidate/vendor/.phpthis:rw,nosuid,nodev,noexec,size=67108864,mode=1777';
    }
    if ($candidateScratch) {
        $arguments[] = '--tmpfs';
        $arguments[] = '/candidate/tmp:rw,nosuid,nodev,noexec,size=67108864,mode=1777';
    }
    foreach ($mounts as $mount) {
        $arguments[] = '--mount';
        $arguments[] = $mount;
    }
    $arguments[] = agentEvaluationRequireString($resources['engine']['configuration'], $imageRole . '_image', 'OCI image configuration');
    foreach (array_slice($command, 1) as $argument) {
        $arguments[] = $argument;
    }
    $pendingRole = 'pending-creation-' . $role;
    $resources['containers'][$pendingRole] = $name;
    agentEvaluationControllerOciWriteLedger($resources);
    $result = agentEvaluationControllerOciCommand($resources['engine'], $arguments);
    if ($result['exit_code'] !== 0 || $result['termination_reason'] !== 'completed'
        || preg_match('/\A[a-f0-9]{64}\n?\z/D', $result['stdout']) !== 1) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_CREATE_FAILED');
    }
    unset($resources['containers'][$pendingRole]);
    $resources['containers'][$role] = $name;
    agentEvaluationControllerOciWriteLedger($resources);
    $container = agentEvaluationControllerOciJsonCommand($resources['engine'], ['inspect', '--format', '{{json .}}', $name]);
    agentEvaluationControllerOciInspectPolicy($container, $uid, $mounts, $resources['owner'], $candidateCache, $candidateScratch);
    return $name;
}

/**
 * @param array<string, mixed> $container
 * @param list<string> $mounts
 */
function agentEvaluationControllerOciInspectPolicy(array $container, int $uid, array $mounts, string $owner, bool $candidateCache = false, bool $candidateScratch = false): void
{
    $host = agentEvaluationRequireObject($container, 'HostConfig', 'OCI container');
    $config = agentEvaluationRequireObject($container, 'Config', 'OCI container');
    $labels = agentEvaluationRequireObject($config, 'Labels', 'OCI container configuration');
    $logConfig = agentEvaluationRequireObject($host, 'LogConfig', 'OCI host configuration');
    $tmpfs = agentEvaluationRequireObject($host, 'Tmpfs', 'OCI host configuration');
    ksort($tmpfs, SORT_STRING);
    $temporaryBytes = $candidateScratch ? 117_440_512 : AGENT_EVALUATION_CONTROLLER_OCI_TMP_BYTES;
    $expectedTmpfs = ['/tmp' => 'rw,nosuid,nodev,noexec,size=' . $temporaryBytes . ',mode=1777'];
    if ($candidateCache) {
        $expectedTmpfs['/candidate/vendor/.phpthis'] = 'rw,nosuid,nodev,noexec,size=67108864,mode=1777';
    }
    if ($candidateScratch) {
        $expectedTmpfs['/candidate/tmp'] = 'rw,nosuid,nodev,noexec,size=67108864,mode=1777';
    }
    ksort($expectedTmpfs, SORT_STRING);
    if (($config['User'] ?? null) !== $uid . ':' . $uid) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_POLICY_INVALID:User');
    }
    if (($labels['org.phpthis.evaluation.owner'] ?? null) !== $owner) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_POLICY_INVALID:Owner');
    }
    $expectedHost = [
        'ReadonlyRootfs' => true, 'Privileged' => false, 'CapDrop' => ['ALL'],
        'SecurityOpt' => ['no-new-privileges=true'], 'NetworkMode' => 'none', 'PidMode' => '',
        'IpcMode' => 'private', 'CgroupnsMode' => 'private', 'PidsLimit' => 64, 'NanoCpus' => 1_000_000_000,
        'Memory' => 1_073_741_824, 'MemorySwap' => 1_073_741_824, 'ShmSize' => 16_777_216,
        'PublishAllPorts' => false,
    ];
    foreach ($expectedHost as $field => $expected) {
        if (($host[$field] ?? null) !== $expected) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_POLICY_INVALID:' . $field);
        }
    }
    foreach (['CapAdd', 'Binds', 'Devices', 'PortBindings'] as $field) {
        if (($host[$field] ?? []) !== []) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_POLICY_INVALID:' . $field);
        }
    }
    if ($tmpfs !== $expectedTmpfs || ($logConfig['Type'] ?? null) !== 'none') {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_POLICY_INVALID:TmpfsOrLogConfig');
    }
    $actual = $host['Mounts'] ?? [];
    if (!is_array($actual) || count($actual) !== count($mounts)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_MOUNTS_INVALID');
    }
    foreach ($mounts as $index => $specification) {
        $fields = explode(',', $specification);
        $source = substr($fields[1], 4);
        $target = substr($fields[2], 4);
        $entry = agentEvaluationValueObject($actual[$index] ?? null, 'OCI mount');
        $volumeOptions = agentEvaluationRequireObject($entry, 'VolumeOptions', 'OCI mount');
        if (($entry['Type'] ?? null) !== 'volume' || ($entry['Source'] ?? null) !== $source
            || ($entry['Target'] ?? null) !== $target
            || ($entry['ReadOnly'] ?? false) !== in_array('readonly', $fields, true)
            || ($volumeOptions['NoCopy'] ?? null) !== true) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_MOUNTS_INVALID');
        }
    }
}

/**
 * @param array<string, mixed> $engine
 * @param list<string> $arguments
 */
function agentEvaluationControllerOciRequireCommand(array $engine, array $arguments, int $wallSeconds = 30): void
{
    $result = agentEvaluationControllerOciCommand($engine, $arguments, '', $wallSeconds);
    if ($result['exit_code'] !== 0 || $result['termination_reason'] !== 'completed') {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_COMMAND_FAILED');
    }
}

function agentEvaluationControllerOciCandidateArchive(string $root): string
{
    $tree = agentEvaluationControllerDescribeTree($root, 'OCI candidate archive', true);
    $archive = '';
    foreach ($tree['directories'] as $path => $mode) {
        $archive .= agentEvaluationControllerOciTarHeader($path . '/', 0755, 0, '5');
    }
    foreach ($tree['files'] as $path => $file) {
        $bytes = file_get_contents($root . '/' . $path);
        if (!is_string($bytes) || !hash_equals($file['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SOURCE_CHANGED');
        }
        $archive .= agentEvaluationControllerOciTarHeader($path, $file['mode'] === '100755' ? 0755 : 0644,
            strlen($bytes), '0') . $bytes . str_repeat("\0", (512 - strlen($bytes) % 512) % 512);
        if (strlen($archive) > AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_BYTES - 1024) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_LIMIT');
        }
    }
    return $archive . str_repeat("\0", 1024);
}

function agentEvaluationControllerOciTarHeader(string $path, int $mode, int $size, string $type): string
{
    $name = $path;
    $prefix = '';
    if (strlen($name) > 100) {
        $position = strrpos(rtrim($path, '/'), '/');
        if ($position === false) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_PATH_LIMIT');
        }
        $prefix = substr($path, 0, $position);
        $name = substr($path, $position + 1);
    }
    if (strlen($name) > 100 || strlen($prefix) > 155) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_PATH_LIMIT');
    }
    $header = str_pad($name, 100, "\0") . sprintf('%07o', $mode) . "\0"
        . sprintf('%07o', AGENT_EVALUATION_CONTROLLER_OCI_UID) . "\0"
        . sprintf('%07o', AGENT_EVALUATION_CONTROLLER_OCI_UID) . "\0"
        . sprintf('%011o', $size) . "\0" . "00000000000\0" . '        ' . $type
        . str_repeat("\0", 100) . "ustar\0" . '00' . str_repeat("\0", 80)
        . str_pad($prefix, 155, "\0") . str_repeat("\0", 12);
    $checksum = 0;
    for ($index = 0; $index < 512; $index++) {
        $checksum += ord($header[$index]);
    }
    return substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
}

/**
 * @return array<string, array{directory: bool, mode: int, bytes: string}>
 */
function agentEvaluationControllerOciReadArchive(string $archive): array
{
    if (strlen($archive) < 1024 || strlen($archive) > AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_BYTES
        || strlen($archive) % 512 !== 0) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_INVALID');
    }
    $entries = [];
    $folded = [];
    $offset = 0;
    $terminated = false;
    while ($offset + 512 <= strlen($archive)) {
        $header = substr($archive, $offset, 512);
        $offset += 512;
        if ($header === str_repeat("\0", 512)) {
            if (substr($archive, $offset) !== str_repeat("\0", strlen($archive) - $offset)) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_TRAILER_INVALID');
            }
            $terminated = strlen($archive) - $offset >= 512;
            break;
        }
        $checksum = agentEvaluationControllerOciTarNumber(substr($header, 148, 8));
        $check = substr_replace($header, '        ', 148, 8);
        $actual = 0;
        for ($index = 0; $index < 512; $index++) {
            $actual += ord($check[$index]);
        }
        if ($checksum !== $actual || !in_array(substr($header, 257, 6), ["ustar\0", 'ustar '], true)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_HEADER_INVALID');
        }
        $name = agentEvaluationControllerOciTarString(substr($header, 0, 100));
        $prefix = agentEvaluationControllerOciTarString(substr($header, 345, 155));
        $path = ($prefix === '' ? '' : $prefix . '/') . $name;
        $type = $header[156];
        $directory = $type === '5';
        $size = agentEvaluationControllerOciTarNumber(substr($header, 124, 12));
        $mode = agentEvaluationControllerOciTarNumber(substr($header, 100, 8));
        if (!in_array($type, ['0', "\0", '5'], true) || ($directory && $size !== 0)
            || !in_array($mode, [0444, 0555, 0600, 0644, 0700, 0755], true)
            || ($directory && !in_array($mode, [0555, 0700, 0755], true))
            || agentEvaluationControllerOciTarString(substr($header, 157, 100)) !== '') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_ENTRY_INVALID');
        }
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        $path = $directory ? rtrim($path, '/') : $path;
        if (($path === '' || $path === '.') && $directory) {
            continue;
        }
        agentEvaluationControllerValidateTreePath($path, 'OCI exported candidate');
        if (preg_match('/\A[A-Za-z0-9._\/-]+\z/D', $path) !== 1) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_PATH_ALPHABET');
        }
        if (isset($entries[$path]) || count($entries) >= AGENT_EVALUATION_CONTROLLER_MAX_TREE_FILES
            || $size > AGENT_EVALUATION_MAX_ARTIFACT_BYTES || $offset + $size > strlen($archive)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_ENTRY_LIMIT');
        }
        $segments = explode('/', $path);
        $parent = '';
        foreach ($segments as $segment) {
            $parent = $parent === '' ? $segment : $parent . '/' . $segment;
            $key = strtolower($parent);
            if (isset($folded[$key]) && $folded[$key] !== $parent) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_CASE_COLLISION');
            }
            $folded[$key] = $parent;
        }
        $bytes = substr($archive, $offset, $size);
        $offset += $size + ((512 - $size % 512) % 512);
        $entries[$path] = ['directory' => $directory, 'mode' => $mode, 'bytes' => $bytes];
    }
    if (!$terminated) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_NOT_TERMINATED');
    }
    foreach ($entries as $path => $entry) {
        $parent = dirname($path);
        while ($parent !== '.') {
            if (isset($entries[$parent]) && !$entries[$parent]['directory']) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_PARENT_COLLISION');
            }
            $parent = dirname($parent);
        }
        if ($path === 'vendor' || str_starts_with($path, 'vendor/')) {
            if (!$entry['directory'] || $path !== 'vendor') {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_DEPENDENCY_ESCAPE');
            }
            unset($entries[$path]);
        }
    }
    ksort($entries, SORT_STRING);
    return $entries;
}

function agentEvaluationControllerOciTarNumber(string $field): int
{
    $digits = trim($field, "\0 ");
    if ($digits === '' || preg_match('/\A[0-7]{1,11}\z/D', $digits) !== 1) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_NUMBER_INVALID');
    }
    $value = octdec($digits);
    if (!is_int($value) || $value < 0 || $value > AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_BYTES) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_NUMBER_LIMIT');
    }
    return $value;
}

function agentEvaluationControllerOciTarString(string $field): string
{
    $null = strpos($field, "\0");
    if ($null === false) {
        return $field;
    }
    if (substr($field, $null) !== str_repeat("\0", strlen($field) - $null)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_STRING_INVALID');
    }
    return substr($field, 0, $null);
}

/**
 * @param array<string, mixed> $resources
 * @return array{stopped: bool, oom_killed: bool, exit_code: int, pid: int}
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciStopGeneration(array &$resources): array
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    $name = $resources['generation'];
    if (!is_string($name)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_GENERATION_NOT_CREATED');
    }
    $state = agentEvaluationControllerOciJsonCommand($resources['engine'], ['inspect', '--format', '{{json .State}}', $name]);
    if (($state['Running'] ?? null) === true) {
        agentEvaluationControllerOciRequireCommand($resources['engine'], ['kill', '--signal', 'KILL', $name]);
        $state = agentEvaluationControllerOciJsonCommand($resources['engine'], ['inspect', '--format', '{{json .State}}', $name]);
    }
    if (($state['Running'] ?? null) !== false || ($state['Pid'] ?? null) !== 0
        || ($state['Paused'] ?? null) !== false || ($state['Restarting'] ?? null) !== false) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_GENERATION_STOP_UNVERIFIED');
    }
    $exitCode = $state['ExitCode'] ?? null;
    if (!is_int($exitCode) || !is_bool($state['OOMKilled'] ?? null)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_GENERATION_STOP_UNVERIFIED');
    }
    $resources['generation_stopped'] = true;
    return ['stopped' => true, 'oom_killed' => $state['OOMKilled'], 'exit_code' => $exitCode, 'pid' => 0];
}

/**
 * @param array<string, mixed> $resources
 * @return array<string, mixed>
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciExportCandidate(array &$resources, string $target): array
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    if ($resources['generation_stopped'] !== true || $resources['generation_destroyed'] !== false) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_PHASE_INVALID');
    }
    agentEvaluationControllerOciStopGeneration($resources);
    $archive = agentEvaluationControllerOciCommand($resources['engine'],
        ['cp', $resources['containers']['holder'] . ':/candidate/.', '-'], '', 30,
        AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_BYTES);
    if ($archive['exit_code'] !== 0 || $archive['termination_reason'] !== 'completed') {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_FAILED');
    }
    $entries = agentEvaluationControllerOciReadArchive($archive['stdout']);
    if (($entries['tmp'] ?? null) !== ['directory' => true, 'mode' => 0755, 'bytes' => '']) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_SCRATCH_CHANGED');
    }
    unset($entries['tmp']);
    foreach ($entries as $entry) {
        if (($entry['directory'] && $entry['mode'] !== 0755)
            || (!$entry['directory'] && !in_array($entry['mode'], [0644, 0755], true))) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_MODE_CHANGED');
        }
    }
    foreach (array_keys($entries) as $path) {
        if (str_starts_with($path, 'tmp/')) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_SCRATCH_CHANGED');
        }
    }
    agentEvaluationControllerExistingRoot($target, 'OCI original candidate');
    if ($target !== $resources['candidate_target']) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_TARGET_INVALID');
    }
    agentEvaluationControllerRemoveTree($target);
    if (!mkdir($target, 0700)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_CREATE_FAILED');
    }
    foreach ($entries as $path => $entry) {
        $destination = $target . '/' . $path;
        $parent = $entry['directory'] ? $destination : dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0755, true)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_CREATE_FAILED');
        }
        if (!$entry['directory'] && (file_put_contents($destination, $entry['bytes'], LOCK_EX) === false
            || !chmod($destination, ($entry['mode'] & 0111) !== 0 ? 0755 : 0644))) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_WRITE_FAILED');
        }
    }
    $expectedLines = [];
    foreach ($entries as $path => $entry) {
        $expectedLines[] = $entry['directory'] ? '040000 directory ' . $path
            : (($entry['mode'] & 0111) !== 0 ? '100755' : '100644') . ' ' . hash('sha256', $entry['bytes']) . ' ' . $path;
    }
    sort($expectedLines, SORT_STRING);
    $expectedManifest = implode("\n", $expectedLines) . ($expectedLines === [] ? '' : "\n");
    $actual = agentEvaluationControllerDescribeTree($target, 'OCI materialized candidate', true);
    if ($expectedManifest !== agentEvaluationControllerFrozenTreeManifest($actual)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EXPORT_MATERIALIZATION_MISMATCH');
    }
    $resources['frozen'] = true;
    return ['archive_sha256' => hash('sha256', $archive['stdout']), 'archive_bytes' => strlen($archive['stdout']),
        'entries' => count($entries), 'generation_stopped' => true];
}

/**
 * @param array<string, mixed> $resources
 * @return array{status: string, generation_destroyed: bool}
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciDestroyGeneration(array &$resources): array
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    if ($resources['generation_stopped'] !== true || $resources['frozen'] !== true) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_DESTROY_PHASE_INVALID');
    }
    agentEvaluationControllerOciDestroyContainer($resources, 'generation');
    $resources['generation_destroyed'] = true;
    return ['status' => 'pass', 'generation_destroyed' => true];
}

/**
 * @param array<string, mixed> $resources
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciDestroyContainer(array &$resources, string $role): void
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    if (str_starts_with($role, 'pending-creation-')) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CREATION_UNSETTLED');
    }
    $name = $resources['containers'][$role] ?? null;
    if (!is_string($name)) {
        return;
    }
    $existing = agentEvaluationControllerOciCommand($resources['engine'], ['container', 'ls', '--all',
        '--filter', 'name=^/' . $name . '$', '--format', '{{.ID}}']);
    if ($existing['exit_code'] === 0 && $existing['termination_reason'] === 'completed' && $existing['stdout'] === '') {
        unset($resources['containers'][$role]);
        agentEvaluationControllerOciWriteLedger($resources);
        return;
    }
    $container = agentEvaluationControllerOciJsonCommand($resources['engine'], ['inspect', '--format', '{{json .}}', $name]);
    $configuration = agentEvaluationRequireObject($container, 'Config', 'OCI cleanup container');
    $labels = agentEvaluationRequireObject($configuration, 'Labels', 'OCI cleanup container configuration');
    if (($labels['org.phpthis.evaluation.owner'] ?? null) !== $resources['owner']
        || ($labels['org.phpthis.evaluation.run_id'] ?? null) !== $resources['run_id']) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CLEANUP_OWNER_MISMATCH');
    }
    agentEvaluationControllerOciRequireCommand($resources['engine'], ['rm', '--force', $name]);
    $remaining = agentEvaluationControllerOciCommand($resources['engine'], ['container', 'ls', '--all',
        '--filter', 'name=^/' . $name . '$', '--format', '{{.ID}}']);
    if ($remaining['exit_code'] !== 0 || $remaining['termination_reason'] !== 'completed' || $remaining['stdout'] !== '') {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_DESTRUCTION_UNVERIFIED');
    }
    unset($resources['containers'][$role]);
    agentEvaluationControllerOciWriteLedger($resources);
}

/**
 * @param array<string, mixed> $resources
 * @return array{verified: bool, status: string, containers_remaining: int, volumes_remaining: int}
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciCleanup(array &$resources): array
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    foreach (array_reverse(array_keys($resources['containers'])) as $role) {
        try {
            agentEvaluationControllerOciDestroyContainer($resources, $role);
        } catch (Throwable) {
            // Every remaining resource is attempted; unresolved names remain in the ledger.
        }
    }
    foreach ($resources['volumes'] as $role => $name) {
        try {
            agentEvaluationControllerOciDestroyVolume($resources, $role);
        } catch (Throwable) {
            // Keep each unresolved owned resource visible in the cleanup result.
        }
    }
    $verified = $resources['containers'] === [] && $resources['volumes'] === [];
    return ['verified' => $verified, 'status' => $verified ? 'pass' : 'fail',
        'containers_remaining' => count($resources['containers']), 'volumes_remaining' => count($resources['volumes'])];
}

/**
 * @param array<string, mixed> $resources
 * @param array<string, mixed> $profile
 * @return array<string, mixed>
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciRunGeneration(
    array &$resources,
    string $prompt,
    array $profile,
    #[SensitiveParameter] string $credential,
): array {
    $resources = agentEvaluationControllerOciResourceState($resources);
    $modelProfile = agentEvaluationRequireObject($profile, 'model', 'live model profile');
    $model = agentEvaluationRequireString($modelProfile, 'id', 'live model profile');
    $settings = agentEvaluationValueObject($modelProfile['settings'] ?? null, 'live model settings');
    $effort = agentEvaluationRequireString($settings, 'reasoning_effort', 'live model settings');
    $budgets = agentEvaluationRequireObject($profile, 'budgets', 'live model profile');
    $proxy = agentEvaluationControllerProxyState($model, $effort, agentEvaluationRequirePositiveInteger($budgets, 'model_tokens', 'live model budgets'));
    $initial = agentEvaluationJson(['type' => 'start', 'prompt_base64' => base64_encode($prompt),
        'arguments' => agentEvaluationControllerLiveCodexArguments($model, $effort),
        'environment' => agentEvaluationControllerLiveCodexEnvironment()]);
    // Relay framing is one line, independent of the human-readable artifact JSON format.
    $queued = json_encode(json_decode($initial, true, 64, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR) . "\n";
    $engine = $resources['engine'];
    $pipes = [];
    $started = hrtime(true);
    $wallSeconds = agentEvaluationRequirePositiveInteger($budgets, 'wall_seconds', 'live model budgets');
    $outputBytes = agentEvaluationRequirePositiveInteger($budgets, 'command_output_bytes', 'live model budgets');
    if ($wallSeconds > 1200 || $outputBytes > 4_194_304 || !is_string($resources['generation'])) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_GENERATION_REQUEST_INVALID');
    }
    $deadline = $started + $wallSeconds * 1_000_000_000;
    $process = proc_open([$engine['binary'], '--config', $engine['config_root'], '--host', 'unix://' . $engine['socket'],
        'start', '--attach', '--interactive', $resources['generation']],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        $engine['control_root'], ['LANG' => 'C', 'LC_ALL' => 'C', 'PATH' => '/usr/bin:/bin'], ['bypass_shell' => true]);
    if (!is_resource($process) || count($pipes) !== 3) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RELAY_START_FAILED');
    }
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    $pending = '';
    $events = '';
    $stderr = '';
    $finished = false;
    $exitCode = -1;
    $reason = 'process_failed';
    $timedOut = false;
    $outputLimited = false;
    $requestId = 0;
    $resourceObservation = null;
    $resourceReason = null;
    $failureCode = null;
    $attachExitCode = -1;
    $cleanup = ['container_stopped' => false, 'oom_killed' => false, 'pid' => -1];
    try {
        while (true) {
            if (hrtime(true) >= $deadline) {
                $timedOut = true;
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_WALL_LIMIT');
            }
            $read = [$pipes[1], $pipes[2]];
            $write = $queued === '' ? [] : [$pipes[0]];
            $except = null;
            if (@stream_select($read, $write, $except, 0, 20_000) === false) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RELAY_IO_FAILED');
            }
            if ($write !== []) {
                $written = fwrite($pipes[0], substr($queued, 0, 8192));
                if ($written === false) {
                    throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RELAY_INPUT_FAILED');
                }
                $queued = substr($queued, $written);
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if (!is_string($chunk)) {
                    throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RELAY_OUTPUT_FAILED');
                }
                if ($stream === $pipes[2]) {
                    $stderr .= $chunk;
                } else {
                    $pending .= $chunk;
                    if (strlen($pending) > 6_291_456) {
                        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_FRAME_LIMIT');
                    }
                    while (($newline = strpos($pending, "\n")) !== false) {
                        $line = substr($pending, 0, $newline);
                        $pending = substr($pending, $newline + 1);
                        $frame = agentEvaluationControllerProxyJsonObject($line);
                        if ($finished) {
                            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_FRAME_AFTER_FINISH');
                        }
                        if (($frame['type'] ?? null) === 'request') {
                            agentEvaluationRequireExactKeys($frame, ['type', 'id', 'method', 'path', 'body_base64'], 'OCI request frame');
                            $requestId++;
                            if ($resourceObservation !== null || ($frame['id'] ?? null) !== $requestId || ($frame['method'] ?? null) !== 'POST'
                                || ($frame['path'] ?? null) !== '/v1/responses' || $queued !== ''
                                || !is_string($frame['body_base64'] ?? null)) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_REQUEST_INVALID');
                            }
                            $body = base64_decode($frame['body_base64'], true);
                            if (!is_string($body) || strlen($body) > 1_048_576) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_REQUEST_LIMIT');
                            }
                            $request = agentEvaluationControllerProxyRequest($body, $proxy);
                            $remaining = $wallSeconds * 1000 - agentEvaluationControllerElapsedMilliseconds($started);
                            if ($remaining <= 0) {
                                $timedOut = true;
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_WALL_LIMIT');
                            }
                            $count = agentEvaluationControllerOciUpstream('input_tokens', $request['count_json'], $credential, $remaining);
                            $approved = agentEvaluationControllerProxyReserve($request['request'], $count, $proxy);
                            $remaining = $wallSeconds * 1000 - agentEvaluationControllerElapsedMilliseconds($started);
                            if ($remaining <= 0) {
                                $timedOut = true;
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_WALL_LIMIT');
                            }
                            $response = agentEvaluationControllerOciUpstream('responses', $approved, $credential, $remaining);
                            agentEvaluationControllerProxyComplete($response, $proxy);
                            $queued = json_encode(['id' => $requestId, 'status' => 200,
                                'body_base64' => base64_encode($response)], JSON_THROW_ON_ERROR) . "\n";
                        } elseif (($frame['type'] ?? null) === 'event') {
                            agentEvaluationRequireExactKeys($frame, ['type', 'event'], 'OCI event frame');
                            if (!($frame['event'] ?? null) instanceof stdClass) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_EVENT_INVALID');
                            }
                            $events .= json_encode($frame['event'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
                        } elseif (($frame['type'] ?? null) === 'stderr') {
                            agentEvaluationRequireExactKeys($frame, ['type', 'data_base64'], 'OCI stderr frame');
                            $bytes = is_string($frame['data_base64'] ?? null) ? base64_decode($frame['data_base64'], true) : false;
                            if (!is_string($bytes)) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_STDERR_INVALID');
                            }
                            $stderr .= $bytes;
                        } elseif (($frame['type'] ?? null) === 'resources') {
                            if ($resourceObservation !== null) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESOURCE_DUPLICATE');
                            }
                            agentEvaluationRequireExactKeys($frame, ['type', 'memory_events', 'pids_events', 'disk_free_bytes'], 'OCI resources');
                            $memory = agentEvaluationValueObject($frame['memory_events'], 'OCI memory events');
                            $pids = agentEvaluationValueObject($frame['pids_events'], 'OCI PID events');
                            $disk = agentEvaluationValueObject($frame['disk_free_bytes'], 'OCI disk evidence');
                            agentEvaluationRequireExactKeys($memory, ['oom', 'oom_kill'], 'OCI memory events');
                            agentEvaluationRequireExactKeys($pids, ['max'], 'OCI PID events');
                            agentEvaluationRequireExactKeys($disk, ['candidate', 'tmp', 'workspace_tmp', 'cache', 'shm'], 'OCI disk evidence');
                            foreach (['oom', 'oom_kill'] as $counter) {
                                if (agentEvaluationRequireNonNegativeInteger($memory, $counter, 'OCI memory events') !== 0) {
                                    $resourceReason = 'memory_limit';
                                }
                            }
                            if (agentEvaluationRequireNonNegativeInteger($pids, 'max', 'OCI PID events') !== 0) {
                                $resourceReason = 'process_limit';
                            }
                            foreach (['candidate', 'tmp', 'workspace_tmp', 'cache', 'shm'] as $mount) {
                                if (agentEvaluationRequireNonNegativeInteger($disk, $mount, 'OCI disk evidence') === 0) {
                                    $resourceReason = 'disk_limit';
                                }
                            }
                            $resourceObservation = ['memory_events' => $memory, 'pids_events' => $pids, 'disk_free_bytes' => $disk];
                            if ($resourceReason !== null) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RESOURCE_EXHAUSTED');
                            }
                        } elseif (($frame['type'] ?? null) === 'finished') {
                            agentEvaluationRequireExactKeys($frame, ['type', 'exit_code'], 'OCI completion frame');
                            if ($resourceObservation === null || !is_int($frame['exit_code'] ?? null) || $frame['exit_code'] < 0 || $frame['exit_code'] > 255) {
                                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_COMPLETION_INVALID');
                            }
                            $finished = true;
                            $exitCode = $frame['exit_code'];
                        } else {
                            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_FRAME_INVALID');
                        }
                        if (strlen($events) + strlen($stderr) > $outputBytes) {
                            $outputLimited = true;
                            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_OUTPUT_LIMIT');
                        }
                    }
                }
                if (strlen($events) + strlen($stderr) > $outputBytes) {
                    $outputLimited = true;
                    throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_OUTPUT_LIMIT');
                }
            }
            $status = proc_get_status($process);
            if (!$status['running'] && $status['exitcode'] >= 0) {
                $attachExitCode = $status['exitcode'];
            }
            if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                if (!$finished || $pending !== '' || $queued !== '' || $attachExitCode !== 0) {
                    throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RELAY_INCOMPLETE');
                }
                $reason = $exitCode === 0 ? 'completed' : 'process_failed';
                break;
            }
        }
    } catch (Throwable $failure) {
        $timedOut = $timedOut || agentEvaluationControllerElapsedMilliseconds($started) >= $wallSeconds * 1000;
        $reason = $timedOut ? 'wall_time_limit' : ($outputLimited ? 'output_limit' : ($resourceReason ?? 'process_failed'));
        $message = $failure->getMessage();
        $failureCode = strlen($message) <= 160 && preg_match('/\AAGENT_EVALUATION_CONTROLLER_[A-Z0-9_]+\z/D', $message) === 1
            ? $message : 'AGENT_EVALUATION_CONTROLLER_OCI_PROTOCOL_FAILED';
        $proxy['blocked'] = true;
    } finally {
        try {
            $stopped = agentEvaluationControllerOciStopGeneration($resources);
            $cleanup = ['container_stopped' => true, 'oom_killed' => $stopped['oom_killed'], 'pid' => 0];
            if ($stopped['oom_killed']) {
                $reason = 'memory_limit';
            }
        } catch (Throwable) {
            $reason = 'cleanup_failed';
        }
        proc_terminate($process, 9);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
    $events = substr($events, 0, $outputBytes);
    $stderr = substr($stderr, 0, max(0, $outputBytes - strlen($events)));
    return ['events_jsonl' => $events, 'stderr' => $stderr, 'exit_code' => $reason === 'completed' ? $exitCode : -1,
        'termination_reason' => $reason, 'elapsed_milliseconds' => agentEvaluationControllerElapsedMilliseconds($started),
        'timed_out' => $timedOut, 'output_limit_exceeded' => $outputLimited, 'proxy' => $proxy, 'cleanup' => $cleanup,
        'resource_observation' => $resourceObservation,
        'failure_code' => $failureCode,
        'synthetic_upstream' => agentEvaluationControllerOciSyntheticUpstream()];
}

function agentEvaluationControllerOciUpstream(
    string $operation,
    string $body,
    #[SensitiveParameter] string $credential,
    int $wallMilliseconds,
): string {
    if (!in_array($operation, ['input_tokens', 'responses'], true) || !function_exists('curl_init')
        || strlen($body) > 1_048_576 || $wallMilliseconds < 1) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_UNAVAILABLE');
    }
    $synthetic = agentEvaluationControllerOciSyntheticUpstream();
    if ((!$synthetic && ($credential === '' || preg_match('/[\x00-\x20\x7F]/', $credential) === 1))
        || ($synthetic && $credential !== '')) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_CREDENTIAL_INVALID');
    }
    $base = $synthetic ? 'http://127.0.0.1:18765' : 'https://api.openai.com';
    $path = $operation === 'input_tokens' ? '/v1/responses/input_tokens' : '/v1/responses';
    $handle = curl_init($base . $path);
    $response = '';
    $limit = $operation === 'input_tokens' ? 1_048_576 : 4_194_304;
    $headers = ['Content-Type: application/json', 'Accept: ' . ($operation === 'responses' ? 'text/event-stream' : 'application/json')];
    if (!$synthetic) {
        $headers[] = 'Authorization: Bearer ' . $credential;
    }
    curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_CONNECTTIMEOUT_MS => min(10000, $wallMilliseconds),
        CURLOPT_TIMEOUT_MS => min(120000, $wallMilliseconds), CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => $synthetic ? CURLPROTO_HTTP : CURLPROTO_HTTPS, CURLOPT_PROXY => '',
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response, $limit): int {
            if (strlen($response) + strlen($chunk) > $limit) {
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        }]);
    $success = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    if ($success !== true || $status !== 200) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_FAILED');
    }
    return $response;
}

/**
 * @param array<string, mixed> $resources
 * @return array<string, mixed>
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciRunScore(array &$resources, string $frozenRoot, string $scorerPath, string $commandSlot): array
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    if ($resources['generation_destroyed'] !== true || !in_array($commandSlot, ['application-check', 'public-scorer'], true)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_PHASE_INVALID');
    }
    agentEvaluationControllerDescribeTree($frozenRoot, 'OCI frozen score input', true);
    if (file_exists($frozenRoot . '/tmp') || is_link($frozenRoot . '/tmp')) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_SCRATCH_COLLISION');
    }
    agentEvaluationRequireBoundedFile($scorerPath, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'OCI public scorer');
    if (is_link($scorerPath)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORER_INVALID');
    }
    $engine = $resources['engine'];
    $volumeRoles = [];
    $containerRoles = [];
    $result = null;
    $destroyed = true;
    try {
        foreach (['score-candidate', 'score-public'] as $purpose) {
            $role = $purpose . '-' . $commandSlot;
            $volumeRoles[] = $role;
            $name = $resources['owner'] . '-' . $role;
            $pendingRole = 'pending-creation-' . $role;
            $resources['volumes'][$pendingRole] = $name;
            agentEvaluationControllerOciWriteLedger($resources);
            $bytes = $purpose === 'score-candidate' ? AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_BYTES : 4_194_304;
            $creation = agentEvaluationControllerOciCommand($engine, ['volume', 'create', '--driver', 'local',
                '--label', 'org.phpthis.evaluation.owner=' . $resources['owner'], '--opt', 'type=tmpfs',
                '--label', 'org.phpthis.evaluation.run_id=' . $resources['run_id'],
                '--opt', 'device=tmpfs', '--opt', 'o=size=' . $bytes . ',uid=65533,gid=65533,mode=0755,nosuid,nodev', $name]);
            if ($creation['exit_code'] !== 0 || $creation['termination_reason'] !== 'completed' || trim($creation['stdout']) !== $name) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_VOLUME_FAILED');
            }
            unset($resources['volumes'][$pendingRole]);
            $resources['volumes'][$role] = $name;
            agentEvaluationControllerOciWriteLedger($resources);
        }
        $candidateVolume = $resources['volumes']['score-candidate-' . $commandSlot];
        $publicVolume = $resources['volumes']['score-public-' . $commandSlot];
        $holderRole = 'score-holder-' . $commandSlot;
        $containerRoles[] = $holderRole;
        $holder = agentEvaluationControllerOciCreateContainer($resources, $holderRole, 'scoring', [
            'type=volume,src=' . $candidateVolume . ',dst=/candidate,readonly,volume-nocopy',
            'type=volume,src=' . $publicVolume . ',dst=/scorer,readonly,volume-nocopy',
        ], ['/bin/sleep', '86400']);
        agentEvaluationControllerOciRequireCommand($engine, ['start', $holder]);
        $prepareRole = 'score-prepare-' . $commandSlot;
        $containerRoles[] = $prepareRole;
        $prepare = agentEvaluationControllerOciCreateContainer($resources, $prepareRole, 'scoring', [
            'type=volume,src=' . $candidateVolume . ',dst=/candidate,volume-nocopy',
            'type=volume,src=' . $publicVolume . ',dst=/scorer,volume-nocopy',
        ], ['/bin/sleep', '86400']);
        $archive = agentEvaluationControllerOciCandidateArchive($frozenRoot);
        $copy = agentEvaluationControllerOciCommand($engine, ['cp', '--archive', '-', $prepare . ':/candidate/'],
            $archive, 30, 1_048_576, true);
        if ($copy['exit_code'] !== 0 || $copy['termination_reason'] !== 'completed') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_COPY_FAILED');
        }
        $mountpointArchive = agentEvaluationControllerOciTarHeader('vendor/', 0755, 0, '5')
            . agentEvaluationControllerOciTarHeader('tmp/', 0755, 0, '5') . str_repeat("\0", 1024);
        $mountpointCopy = agentEvaluationControllerOciCommand($engine, ['cp', '--archive', '-', $prepare . ':/candidate/'],
            $mountpointArchive, 30, 1_048_576, true);
        if ($mountpointCopy['exit_code'] !== 0 || $mountpointCopy['termination_reason'] !== 'completed') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_COPY_FAILED');
        }
        agentEvaluationControllerOciRequireCommand($engine, ['cp', $scorerPath, $prepare . ':/scorer/public.php']);
        agentEvaluationControllerOciDestroyContainer($resources, $prepareRole);
        $scoreRole = 'score-' . $commandSlot;
        $containerRoles[] = $scoreRole;
        $command = $commandSlot === 'application-check'
            ? ['/usr/local/bin/composer', '--no-interaction', 'check']
            : ['/usr/local/bin/php', '/scorer/public.php', '/candidate'];
        $score = agentEvaluationControllerOciCreateContainer($resources, $scoreRole, 'scoring', [
            'type=volume,src=' . $candidateVolume . ',dst=/candidate,readonly,volume-nocopy',
            'type=volume,src=' . $resources['volumes']['dependencies'] . ',dst=/candidate/vendor,readonly,volume-nocopy',
            'type=volume,src=' . $publicVolume . ',dst=/scorer,readonly,volume-nocopy',
        ], $command);
        $result = agentEvaluationControllerOciCommand($engine, ['start', '--attach', $score], '', 1200, 4_194_304);
        $state = agentEvaluationControllerOciJsonCommand($engine, ['inspect', '--format', '{{json .State}}', $score]);
        if (agentEvaluationRequireBoolean($state, 'Running', 'OCI scoring state')) {
            agentEvaluationControllerOciRequireCommand($engine, ['kill', '--signal', 'KILL', $score]);
            $state = agentEvaluationControllerOciJsonCommand($engine, ['inspect', '--format', '{{json .State}}', $score]);
        }
        $running = agentEvaluationRequireBoolean($state, 'Running', 'OCI scoring state');
        $pid = agentEvaluationRequireNonNegativeInteger($state, 'Pid', 'OCI scoring state');
        $containerExit = $state['ExitCode'] ?? null;
        if ($running || $pid !== 0 || !is_int($containerExit)
            || agentEvaluationRequireBoolean($state, 'Paused', 'OCI scoring state')
            || agentEvaluationRequireBoolean($state, 'Restarting', 'OCI scoring state')) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_STOP_UNVERIFIED');
        }
        $result['oom_killed'] = agentEvaluationRequireBoolean($state, 'OOMKilled', 'OCI scoring state');
        $containerStatus = agentEvaluationRequireString($state, 'Status', 'OCI scoring state');
        $startedAt = agentEvaluationRequireString($state, 'StartedAt', 'OCI scoring state');
        $errorPresent = agentEvaluationRequireString($state, 'Error', 'OCI scoring state') !== '';
        if (!in_array($containerStatus, ['created', 'exited', 'dead'], true)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,9})?Z\z/D', $startedAt) !== 1) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_STOP_UNVERIFIED');
        }
        $result['container_started'] = $containerStatus === 'exited'
            && !str_starts_with($startedAt, '0001-') && !$errorPresent;
        $result['container_state'] = ['running' => false, 'pid' => $pid, 'exit_code' => $containerExit,
            'oom_killed' => $result['oom_killed'], 'status' => $containerStatus,
            'started_at' => $startedAt, 'runtime_error_present' => $errorPresent];
        $result['image_reference'] = agentEvaluationRequireString($engine['configuration'], 'scoring_image', 'OCI scoring configuration');
        $result['uid'] = AGENT_EVALUATION_CONTROLLER_OCI_SCORE_UID;
        $result['network'] = 'none';
        $result['resource_event_telemetry'] = 'unavailable-after-scoring-exit';
        if (!$result['container_started']) {
            $result['exit_code'] = -1;
            $result['termination_reason'] = 'container_start_failed';
        } elseif ($result['oom_killed']) {
            $result['exit_code'] = -1;
            $result['termination_reason'] = 'memory_limit';
        } elseif ($containerExit !== 0 && $result['termination_reason'] === 'completed') {
            $result['exit_code'] = $containerExit;
            $result['termination_reason'] = 'process_failed';
        }
        // The fixed public scorer never receives a writable candidate mount.
        $post = agentEvaluationControllerOciCommand($engine, ['cp', $holder . ':/candidate/.', '-'], '', 30,
            AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_BYTES);
        if ($post['exit_code'] !== 0 || $post['termination_reason'] !== 'completed') {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_FREEZE_CHECK_FAILED');
        }
        $beforeEntries = agentEvaluationControllerOciReadArchive($archive);
        $afterEntries = agentEvaluationControllerOciReadArchive($post['stdout']);
        if (($afterEntries['tmp'] ?? null) !== ['directory' => true, 'mode' => 0755, 'bytes' => '']) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_SCORE_SCRATCH_CHANGED');
        }
        unset($afterEntries['tmp']);
        if ($beforeEntries !== $afterEntries) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_POST_FREEZE_MUTATION');
        }
    } finally {
        foreach (array_reverse($containerRoles) as $role) {
            try {
                agentEvaluationControllerOciDestroyContainer($resources, $role);
            } catch (Throwable) {
                $destroyed = false;
            }
        }
        foreach (array_reverse($volumeRoles) as $role) {
            try {
                agentEvaluationControllerOciDestroyVolume($resources, $role);
            } catch (Throwable) {
                $destroyed = false;
            }
        }
    }
    $result['container_destroyed'] = $destroyed;
    if (!$destroyed) {
        $result['exit_code'] = -1;
        $result['termination_reason'] = 'cleanup_failed';
    }
    return $result;
}

/**
 * @param array<string, mixed> $resources
 * @param-out array{engine: array{binary: string, socket: string, config_root: string, control_root: string, configuration: array<string, mixed>}, owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>, generation: string|null, generation_stopped: bool, generation_destroyed: bool, frozen: bool, candidate_target: string} $resources
 */
function agentEvaluationControllerOciDestroyVolume(array &$resources, string $role): void
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    if (str_starts_with($role, 'pending-creation-')) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CREATION_UNSETTLED');
    }
    foreach (array_keys($resources['containers']) as $containerRole) {
        if (str_starts_with($containerRole, 'pending-creation-')) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CREATION_UNSETTLED');
        }
    }
    $name = $resources['volumes'][$role] ?? null;
    if (!is_string($name)) {
        return;
    }
    $existing = agentEvaluationControllerOciCommand($resources['engine'], ['volume', 'ls',
        '--filter', 'name=^' . $name . '$', '--format', '{{.Name}}']);
    if ($existing['exit_code'] === 0 && $existing['termination_reason'] === 'completed' && $existing['stdout'] === '') {
        unset($resources['volumes'][$role]);
        agentEvaluationControllerOciWriteLedger($resources);
        return;
    }
    $volume = agentEvaluationControllerOciJsonCommand($resources['engine'], ['volume', 'inspect', '--format', '{{json .}}', $name]);
    $labels = agentEvaluationRequireObject($volume, 'Labels', 'OCI cleanup volume');
    if (($labels['org.phpthis.evaluation.owner'] ?? null) !== $resources['owner']
        || ($labels['org.phpthis.evaluation.run_id'] ?? null) !== $resources['run_id']) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_CLEANUP_OWNER_MISMATCH');
    }
    agentEvaluationControllerOciRequireCommand($resources['engine'], ['volume', 'rm', $name]);
    $remaining = agentEvaluationControllerOciCommand($resources['engine'], ['volume', 'ls', '--filter', 'name=^' . $name . '$', '--format', '{{.Name}}']);
    if ($remaining['exit_code'] !== 0 || $remaining['termination_reason'] !== 'completed' || $remaining['stdout'] !== '') {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_VOLUME_DESTRUCTION_UNVERIFIED');
    }
    unset($resources['volumes'][$role]);
    agentEvaluationControllerOciWriteLedger($resources);
}

/**
 * @return array{async: bool, sigint: callable|int, sigterm: callable|int}
 */
function agentEvaluationControllerInstallInterruptHandlers(): array
{
    if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal_get_handler')) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_INTERRUPT_CONTROL_UNAVAILABLE');
    }
    $previous = ['async' => pcntl_async_signals(), 'sigint' => pcntl_signal_get_handler(SIGINT),
        'sigterm' => pcntl_signal_get_handler(SIGTERM)];
    pcntl_async_signals(true);
    $handler = static function (int $signal): void {
        // Further catchable interruption cannot interrupt bounded container cleanup.
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_signal(SIGTERM, SIG_IGN);
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_INTERRUPTED');
    };
    if (!pcntl_signal(SIGINT, $handler) || !pcntl_signal(SIGTERM, $handler)) {
        agentEvaluationControllerRestoreInterruptHandlers($previous);
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_INTERRUPT_CONTROL_UNAVAILABLE');
    }
    return $previous;
}

/**
 * @param array{async: bool, sigint: callable|int, sigterm: callable|int} $previous
 */
function agentEvaluationControllerRestoreInterruptHandlers(array $previous): void
{
    pcntl_signal(SIGINT, $previous['sigint']);
    pcntl_signal(SIGTERM, $previous['sigterm']);
    pcntl_async_signals($previous['async']);
}

/**
 * @param array<string, mixed> $engine
 * @return array<string, mixed>
 */
function agentEvaluationControllerOciVerifyToolchains(array $engine): array
{
    $engine = agentEvaluationControllerOciEngineFields($engine);
    $resources = ['engine' => $engine, 'owner' => 'phpthis-eval-' . bin2hex(random_bytes(16)),
        'run_id' => str_repeat('0', 32), 'containers' => [], 'volumes' => [], 'generation' => null,
        'generation_stopped' => false, 'generation_destroyed' => false, 'frozen' => false,
        'candidate_target' => $engine['control_root']];
    $observed = [];
    $program = <<<'PYTHON'
import hashlib,json,os,platform,re,socket,subprocess,sys
role=sys.argv[1]
def command(argv):
    value=subprocess.run(argv,stdin=subprocess.DEVNULL,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=10,check=True)
    if len(value.stdout)+len(value.stderr)>32768: raise ValueError('output bound')
    return value.stdout.decode().strip()
php=command(['/usr/local/bin/php','-r','echo PHP_VERSION;'])
composer=command(['/usr/local/bin/composer','--version','--no-ansi'])
match=re.match(r'Composer version ([0-9]+\.[0-9]+\.[0-9]+)(?: |$)',composer)
if match is None: raise ValueError('composer identity')
codex=None
relay=None
if role=='generation':
    output=command(['/usr/local/bin/codex','--version'])
    found=re.fullmatch(r'codex-cli ([0-9]+\.[0-9]+\.[0-9]+)',output)
    if found is None: raise ValueError('codex identity')
    codex=found.group(1)
    relay=hashlib.sha256(open('/opt/phpthis/relay.py','rb').read(1048577)).hexdigest()
elif os.path.exists('/usr/local/bin/codex') or os.path.exists('/opt/phpthis/relay.py'):
    raise ValueError('scoring runner exposure')
actual={'php_version':php,'composer_version':match.group(1),'python_version':platform.python_version(),'codex_version':codex,'relay_sha256':relay}
with open('/opt/phpthis/toolchain.json','rb') as source:
    declared=source.read(8193)
if len(declared)>8192 or json.loads(declared)!=actual: raise ValueError('metadata mismatch')
status={line.split(':',1)[0]:line.split(':',1)[1].strip() for line in open('/proc/self/status') if ':' in line}
if os.geteuid()==0 or status.get('CapEff')!='0000000000000000' or status.get('NoNewPrivs')!='1':
    raise ValueError('privilege policy')
def text(path):
    with open(path) as source: value=source.read(4097)
    if len(value)>4096: raise ValueError('control bound')
    return value.strip()
if text('/sys/fs/cgroup/memory.max')!='1073741824' or text('/sys/fs/cgroup/memory.swap.max')!='0' or text('/sys/fs/cgroup/pids.max')!='64' or text('/sys/fs/cgroup/cpu.max')!='100000 100000':
    raise ValueError('cgroup enforcement')
for _,interface in socket.if_nameindex():
    if interface!='lo' and int(text('/sys/class/net/'+interface+'/flags'),16)&1:
        raise ValueError('active external interface')
if any(line.strip() for line in open('/proc/net/route').readlines()[1:]):
    raise ValueError('IPv4 route')
if any(line.split()[-1]!='lo' for line in open('/proc/net/ipv6_route') if line.strip()):
    raise ValueError('IPv6 route')
print(json.dumps(actual,sort_keys=True))
PYTHON;
    try {
        foreach (['generation', 'scoring'] as $role) {
            $name = agentEvaluationControllerOciCreateContainer($resources, 'preflight-' . $role, $role, [],
                ['/usr/bin/python3', '-c', $program, $role]);
            $result = agentEvaluationControllerOciCommand($engine, ['start', '--attach', $name], '', 45, 32_768);
            if ($result['exit_code'] !== 0 || $result['termination_reason'] !== 'completed') {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_TOOLCHAIN_PROOF_FAILED');
            }
            $identity = json_decode($result['stdout'], true, 16, JSON_THROW_ON_ERROR);
            $expected = $engine['configuration'][$role . '_toolchain'];
            if (!is_array($identity) || !is_array($expected)) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_TOOLCHAIN_IDENTITY_INVALID');
            }
            ksort($identity, SORT_STRING);
            ksort($expected, SORT_STRING);
            if ($identity !== $expected) {
                throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_TOOLCHAIN_IDENTITY_INVALID');
            }
            $observed[$role] = $identity;
            agentEvaluationControllerOciDestroyContainer($resources, 'preflight-' . $role);
        }
    } finally {
        $cleanup = agentEvaluationControllerOciCleanup($resources);
        if (!$cleanup['verified']) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_PREFLIGHT_CLEANUP_FAILED');
        }
    }
    return $observed;
}

function agentEvaluationControllerOciSyntheticUpstream(): bool
{
    $constants = get_defined_constants();
    return agentEvaluationControllerTestingEnabled()
        && ($constants['AGENT_EVALUATION_CONTROLLER_OCI_TEST_UPSTREAM'] ?? null) === true;
}

/** @param array<string, mixed> $resources */
function agentEvaluationControllerOciWriteLedger(array $resources): void
{
    $resources = agentEvaluationControllerOciResourceState($resources);
    $engine = agentEvaluationControllerOciEngineFields($resources['engine']);
    $path = $engine['control_root'] . '/owned-resources.json';
    $pending = $engine['control_root'] . '/owned-resources.pending';
    $bytes = agentEvaluationJson(['owner' => $resources['owner'], 'run_id' => $resources['run_id'],
        'containers' => (object) $resources['containers'], 'volumes' => (object) $resources['volumes']]);
    if (strlen($bytes) > 32_768 || is_link($path) || is_link($pending)
        || file_put_contents($pending, $bytes, LOCK_EX) !== strlen($bytes)
        || !chmod($pending, 0600) || !rename($pending, $path)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_RECOVERY_LEDGER_FAILED');
    }
}
