<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_RUNNER_CODEX = 'codex-exec';
const AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_CODEX = 'fake-codex';
const AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE = 'AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE';
const AGENT_EVALUATION_CONTROLLER_FUTURE_OCI_LAUNCHER = 'docker-oci';
const AGENT_EVALUATION_CONTROLLER_FUTURE_CODEX_PATH = '/usr/local/bin/codex';
const AGENT_EVALUATION_CONTROLLER_FUTURE_CREDENTIAL_BROKER = 'responses-api-run-proxy';
const AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES = 1_048_576;
const AGENT_EVALUATION_CONTROLLER_MAX_EVENT_BYTES = 1_048_576;
const AGENT_EVALUATION_CONTROLLER_MAX_EVENTS = 4_096;

/**
 * @param array{
 *   model_tokens: int,
 *   wall_seconds: int,
 *   repair_turns: int,
 *   command_output_bytes: int
 * } $budgets
 * @param array<string, mixed> $isolation
 * @return array{
 *   runner: string,
 *   events: list<array<string, mixed>>,
 *   events_jsonl: string,
 *   response: string,
 *   usage: array{
 *     input_tokens: int|null,
 *     output_tokens: int|null,
 *     cached_tokens: int|null,
 *     reasoning_tokens: int|null
 *   },
 *   termination_reason: string,
 *   process: array{
 *     exit_code: int,
 *     stdout: string,
 *     stderr: string,
 *     elapsed_milliseconds: int,
 *     timed_out: bool,
 *     output_limit_exceeded: bool,
 *     termination_reason: string,
 *     cleanup: array{
 *       process_group_created: bool,
 *       terminate_sent: bool,
 *       kill_sent: bool,
 *       process_reaped: bool,
 *       process_group_absent: bool
 *     }
 *   }
 * }
 */
function agentEvaluationControllerRunCodex(
    string $candidateDirectory,
    string $prompt,
    string $model,
    string $reasoningEffort,
    array $budgets,
    array $isolation,
    bool $fakeForTests = false,
): array {
    $candidateRoot = agentEvaluationControllerValidateCodexRequest(
        $candidateDirectory,
        $prompt,
        $model,
        $reasoningEffort,
        $budgets,
    );

    if (!$fakeForTests) {
        agentEvaluationControllerValidateFutureIsolationProfile($isolation, $budgets, 'generation');
        throw new RuntimeException(
            AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE
            . ': v0.2 records the pinned OCI and credential-proxy contract but does not invoke it.',
        );
    }

    if (!agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_FAKE_CODEX_TEST_ONLY');
    }

    agentEvaluationControllerValidateIsolationProfile($isolation, $budgets, true);

    $fixture = __DIR__ . '/fixtures/fake-codex.php';

    if (!is_file($fixture) || is_link($fixture)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_FAKE_CODEX_MISSING');
    }

    $arguments = [
        PHP_BINARY,
        $fixture,
        'exec',
        '--ephemeral',
        '--ignore-user-config',
        '--ignore-rules',
        '--strict-config',
        '--skip-git-repo-check',
        '--sandbox',
        'workspace-write',
        '--json',
        '--model',
        $model,
        '--cd',
        $candidateRoot,
        '-c',
        'approval_policy="never"',
        '-c',
        'model_reasoning_effort="' . $reasoningEffort . '"',
        '-c',
        'shell_environment_policy.inherit="none"',
        '-',
    ];
    $process = agentEvaluationControllerRunProcess(
        $arguments,
        $candidateRoot,
        agentEvaluationControllerMinimalProcessEnvironment(),
        $prompt,
        $budgets['wall_seconds'],
        $budgets['command_output_bytes'],
    );
    $parsed = agentEvaluationControllerParseCodexEvents(
        $process['stdout'],
        $budgets['model_tokens'],
    );
    $terminationReason = agentEvaluationControllerCodexTerminationReason($process, $parsed);

    return [
        'runner' => AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_CODEX,
        'events' => $parsed['events'],
        'events_jsonl' => $process['stdout'],
        'response' => $parsed['response'],
        'usage' => $parsed['usage'],
        'termination_reason' => $terminationReason,
        'process' => $process,
    ];
}

function agentEvaluationControllerTestingEnabled(): bool
{
    $constants = get_defined_constants();

    return ($constants['PHPTHIS_AGENT_EVALUATION_CONTROLLER_TESTING'] ?? null) === true;
}

/** @return array<string, string> */
function agentEvaluationControllerMinimalProcessEnvironment(): array
{
    return [
        'LANG' => 'C',
        'LC_ALL' => 'C',
        'PATH' => '/usr/bin:/bin',
    ];
}

/**
 * @param array{
 *   model_tokens: int,
 *   wall_seconds: int,
 *   repair_turns: int,
 *   command_output_bytes: int
 * } $budgets
 */
function agentEvaluationControllerValidateCodexRequest(
    string $candidateDirectory,
    string $prompt,
    string $model,
    string $reasoningEffort,
    array $budgets,
): string {
    $candidateRoot = realpath($candidateDirectory);

    if (
        !is_string($candidateRoot)
        || !is_dir($candidateRoot)
        || is_link($candidateDirectory)
        || file_exists($candidateRoot . '/.git')
        || is_link($candidateRoot . '/.git')
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_CODEX_CANDIDATE_INVALID');
    }

    if (
        $prompt === ''
        || strlen($prompt) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES
        || str_contains($prompt, "\0")
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_CODEX_PROMPT_INVALID');
    }

    if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $model) !== 1) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_CODEX_MODEL_INVALID');
    }

    if (!in_array($reasoningEffort, ['low', 'medium', 'high', 'xhigh', 'max', 'ultra'], true)) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_CODEX_REASONING_INVALID');
    }

    agentEvaluationControllerValidateBudgets($budgets);

    return $candidateRoot;
}

/**
 * @param array<string, mixed> $budgets
 */
function agentEvaluationControllerValidateBudgets(array $budgets): void
{
    $expectedKeys = [
        'model_tokens',
        'wall_seconds',
        'repair_turns',
        'command_output_bytes',
    ];
    $actualKeys = array_keys($budgets);
    sort($expectedKeys, SORT_STRING);
    sort($actualKeys, SORT_STRING);

    if ($actualKeys !== $expectedKeys) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_BUDGET_FIELDS_INVALID');
    }

    if (
        !is_int($budgets['model_tokens'])
        || !is_int($budgets['wall_seconds'])
        || !is_int($budgets['repair_turns'])
        || !is_int($budgets['command_output_bytes'])
        || $budgets['model_tokens'] < 1
        || $budgets['model_tokens'] > 1_000_000
        || $budgets['wall_seconds'] < 1
        || $budgets['wall_seconds'] > 86_400
        || $budgets['repair_turns'] < 0
        || $budgets['repair_turns'] > 10
        || $budgets['command_output_bytes'] < 1
        || $budgets['command_output_bytes'] > 16_777_216
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_BUDGET_INVALID');
    }
}

/**
 * @param array<string, mixed> $profile
 * @param array{
 *   model_tokens: int,
 *   wall_seconds: int,
 *   repair_turns: int,
 *   command_output_bytes: int
 * } $budgets
 */
function agentEvaluationControllerValidateFutureIsolationProfile(
    array $profile,
    array $budgets,
    string $phase,
): void {
    $expectedKeys = [
        'launcher',
        'image_reference',
        'image_digest',
        'credential_broker',
        'network',
        'root_read_only',
        'capabilities_dropped',
        'no_new_privileges',
        'candidate_git_absent',
        'dependencies_read_only',
        'uid',
        'cpu_millis',
        'memory_bytes',
        'disk_bytes',
        'processes',
        'wall_seconds',
        'model_tokens',
        'output_bytes',
        'descendant_cleanup',
    ];
    $actualKeys = array_keys($profile);
    sort($expectedKeys, SORT_STRING);
    sort($actualKeys, SORT_STRING);

    if ($actualKeys !== $expectedKeys) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_PREFLIGHT_FIELDS_INVALID');
    }

    $digest = $profile['image_digest'] ?? null;
    $reference = $profile['image_reference'] ?? null;
    $generation = $phase === 'generation';

    if (
        !in_array($phase, ['generation', 'scoring'], true)
        || ($profile['launcher'] ?? null) !== AGENT_EVALUATION_CONTROLLER_FUTURE_OCI_LAUNCHER
        || !is_string($digest)
        || preg_match('/\Asha256:[a-f0-9]{64}\z/D', $digest) !== 1
        || !is_string($reference)
        || !str_ends_with($reference, '@' . $digest)
        || strlen($reference) > 256
        || preg_match('/[\x00-\x20\x7F]/', $reference) === 1
        || ($profile['credential_broker'] ?? null) !== ($generation
            ? AGENT_EVALUATION_CONTROLLER_FUTURE_CREDENTIAL_BROKER
            : 'none')
        || ($profile['network'] ?? null) !== ($generation ? 'proxy-only' : 'none')
        || ($profile['root_read_only'] ?? null) !== true
        || ($profile['capabilities_dropped'] ?? null) !== true
        || ($profile['no_new_privileges'] ?? null) !== true
        || ($profile['candidate_git_absent'] ?? null) !== true
        || ($profile['dependencies_read_only'] ?? null) !== true
        || !is_int($profile['uid'] ?? null)
        || $profile['uid'] < 1
        || $profile['uid'] > 2_147_483_647
        || ($profile['cpu_millis'] ?? null) !== AGENT_EVALUATION_CONTROLLER_CPU_MILLIS
        || ($profile['memory_bytes'] ?? null) !== AGENT_EVALUATION_CONTROLLER_MEMORY_BYTES
        || ($profile['disk_bytes'] ?? null) !== AGENT_EVALUATION_CONTROLLER_DISK_BYTES
        || ($profile['processes'] ?? null) !== AGENT_EVALUATION_CONTROLLER_PROCESS_LIMIT
        || ($profile['wall_seconds'] ?? null) !== $budgets['wall_seconds']
        || ($profile['model_tokens'] ?? null) !== $budgets['model_tokens']
        || ($profile['output_bytes'] ?? null) !== $budgets['command_output_bytes']
        || ($profile['descendant_cleanup'] ?? null) !== 'container-destroy'
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_OCI_PREFLIGHT_INVALID');
    }
}

/**
 * @return array{
 *   events: list<array<string, mixed>>,
 *   response: string,
 *   usage: array{
 *     input_tokens: int|null,
 *     output_tokens: int|null,
 *     cached_tokens: int|null,
 *     reasoning_tokens: int|null
 *   },
 *   valid: bool,
 *   completed: bool,
 *   failed: bool,
 *   token_limit_exceeded: bool
 * }
 */
function agentEvaluationControllerParseCodexEvents(string $jsonLines, int $modelTokenBudget): array
{
    $events = [];
    $response = '';
    $usage = [
        'input_tokens' => null,
        'output_tokens' => null,
        'cached_tokens' => null,
        'reasoning_tokens' => null,
    ];
    $valid = true;
    $completed = false;
    $failed = false;
    $threadStarted = false;
    $turnStarted = false;
    $terminalEvent = null;
    $lines = explode("\n", $jsonLines);

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        if (
            count($events) >= AGENT_EVALUATION_CONTROLLER_MAX_EVENTS
            || strlen($line) > AGENT_EVALUATION_CONTROLLER_MAX_EVENT_BYTES
        ) {
            $valid = false;
            break;
        }

        try {
            $event = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
            $offset = 0;
            agentEvaluationControllerScanJsonValue($line, $offset);
            agentEvaluationControllerSkipJsonWhitespace($line, $offset);

            if ($offset !== strlen($line)) {
                throw new RuntimeException('Codex event JSON could not be scanned completely.');
            }
        } catch (JsonException | RuntimeException) {
            $valid = false;
            break;
        }

        if (!is_array($event) || array_is_list($event) || !is_string($event['type'] ?? null)) {
            $valid = false;
            break;
        }

        /** @var array<string, mixed> $event */
        $type = $event['type'];

        if ($terminalEvent !== null) {
            $valid = false;
            break;
        }

        if (!in_array(
            $type,
            [
                'thread.started',
                'turn.started',
                'turn.completed',
                'turn.failed',
                'item.started',
                'item.updated',
                'item.completed',
                'error',
            ],
            true,
        )) {
            $valid = false;
            break;
        }

        $events[] = $event;

        if ($type === 'thread.started') {
            if (
                $threadStarted
                || $turnStarted
                || !is_string($event['thread_id'] ?? null)
                || $event['thread_id'] === ''
            ) {
                $valid = false;
                break;
            }

            $threadStarted = true;
        } elseif ($type === 'turn.started') {
            if (!$threadStarted || $turnStarted) {
                $valid = false;
                break;
            }

            $turnStarted = true;
        } elseif (str_starts_with($type, 'item.')) {
            if (!$turnStarted) {
                $valid = false;
                break;
            }

            $item = $event['item'] ?? null;

            if (
                !is_array($item)
                || array_is_list($item)
                || !is_string($item['id'] ?? null)
                || $item['id'] === ''
                || !is_string($item['type'] ?? null)
                || $item['type'] === ''
            ) {
                $valid = false;
                break;
            }

            if ($type === 'item.completed' && $item['type'] === 'agent_message') {
                $text = $item['text'] ?? null;

                if (!is_string($text)) {
                    $valid = false;
                    break;
                }

                $response = $text;
            }
        } elseif ($type === 'turn.completed') {
            if (!$turnStarted || $completed || $failed) {
                $valid = false;
                break;
            }

            $parsedUsage = agentEvaluationControllerParseCodexUsage($event['usage'] ?? null);

            if ($parsedUsage === null) {
                $valid = false;
                break;
            }

            $usage = $parsedUsage;
            $completed = true;
            $terminalEvent = 'turn.completed';
        } elseif ($type === 'turn.failed' || $type === 'error') {
            if (!$turnStarted) {
                $valid = false;
                break;
            }

            $failed = true;
            $terminalEvent = $type;
        }
    }

    if (!$threadStarted || !$turnStarted || $terminalEvent === null || ($completed === $failed)) {
        $valid = false;
    }

    $tokenLimitExceeded = agentEvaluationControllerUsageExceedsBudget($usage, $modelTokenBudget);

    return [
        'events' => $events,
        'response' => $response,
        'usage' => $usage,
        'valid' => $valid,
        'completed' => $completed,
        'failed' => $failed,
        'token_limit_exceeded' => $tokenLimitExceeded,
    ];
}

/**
 * Scan raw JSON separately from json_decode() so duplicate object names cannot
 * be hidden by PHP's last-value-wins decoding behavior.
 */
function agentEvaluationControllerScanJsonValue(string $source, int &$offset): void
{
    agentEvaluationControllerSkipJsonWhitespace($source, $offset);
    $character = $source[$offset] ?? '';

    if ($character === '{') {
        agentEvaluationControllerScanJsonObject($source, $offset);
        return;
    }

    if ($character === '[') {
        agentEvaluationControllerScanJsonArray($source, $offset);
        return;
    }

    if ($character === '"') {
        agentEvaluationControllerScanJsonString($source, $offset);
        return;
    }

    $start = $offset;
    $length = strlen($source);

    while ($offset < $length && !str_contains(" \t\r\n,]}", $source[$offset])) {
        $offset++;
    }

    if ($offset === $start) {
        throw new RuntimeException('Codex event JSON contains an invalid value token.');
    }
}

function agentEvaluationControllerScanJsonObject(string $source, int &$offset): void
{
    $offset++;
    agentEvaluationControllerSkipJsonWhitespace($source, $offset);

    if (($source[$offset] ?? '') === '}') {
        $offset++;
        return;
    }

    $seen = [];

    while (true) {
        agentEvaluationControllerSkipJsonWhitespace($source, $offset);
        $name = agentEvaluationControllerScanJsonString($source, $offset);
        $identity = strlen($name) . ':' . $name;

        if (isset($seen[$identity])) {
            throw new RuntimeException('Codex event JSON contains a duplicate object name.');
        }

        $seen[$identity] = true;
        agentEvaluationControllerSkipJsonWhitespace($source, $offset);

        if (($source[$offset] ?? '') !== ':') {
            throw new RuntimeException('Codex event JSON object is missing a name separator.');
        }

        $offset++;
        agentEvaluationControllerScanJsonValue($source, $offset);
        agentEvaluationControllerSkipJsonWhitespace($source, $offset);
        $separator = $source[$offset] ?? '';

        if ($separator === '}') {
            $offset++;
            return;
        }

        if ($separator !== ',') {
            throw new RuntimeException('Codex event JSON object is missing an item separator.');
        }

        $offset++;
    }
}

function agentEvaluationControllerScanJsonArray(string $source, int &$offset): void
{
    $offset++;
    agentEvaluationControllerSkipJsonWhitespace($source, $offset);

    if (($source[$offset] ?? '') === ']') {
        $offset++;
        return;
    }

    while (true) {
        agentEvaluationControllerScanJsonValue($source, $offset);
        agentEvaluationControllerSkipJsonWhitespace($source, $offset);
        $separator = $source[$offset] ?? '';

        if ($separator === ']') {
            $offset++;
            return;
        }

        if ($separator !== ',') {
            throw new RuntimeException('Codex event JSON array is missing an item separator.');
        }

        $offset++;
    }
}

function agentEvaluationControllerScanJsonString(string $source, int &$offset): string
{
    if (($source[$offset] ?? '') !== '"') {
        throw new RuntimeException('Codex event JSON object expected a string name.');
    }

    $start = $offset;
    $length = strlen($source);
    $offset++;

    while ($offset < $length) {
        $character = $source[$offset];

        if ($character === '"') {
            $offset++;
            $value = json_decode(
                substr($source, $start, $offset - $start),
                false,
                2,
                JSON_THROW_ON_ERROR,
            );

            if (!is_string($value)) {
                throw new RuntimeException('Codex event JSON string did not decode to a string.');
            }

            return $value;
        }

        if ($character === '\\') {
            $escape = $source[$offset + 1] ?? '';
            $offset += $escape === 'u' ? 6 : 2;
            continue;
        }

        $offset++;
    }

    throw new RuntimeException('Codex event JSON contains an unterminated string.');
}

function agentEvaluationControllerSkipJsonWhitespace(string $source, int &$offset): void
{
    $length = strlen($source);

    while ($offset < $length && str_contains(" \t\r\n", $source[$offset])) {
        $offset++;
    }
}

/**
 * @return array{
 *   input_tokens: int|null,
 *   output_tokens: int|null,
 *   cached_tokens: int|null,
 *   reasoning_tokens: int|null
 * }|null
 */
function agentEvaluationControllerParseCodexUsage(mixed $value): ?array
{
    if (!is_array($value) || array_is_list($value)) {
        return null;
    }

    $mapping = [
        'input_tokens' => 'input_tokens',
        'output_tokens' => 'output_tokens',
        'cached_tokens' => 'cached_input_tokens',
        'reasoning_tokens' => 'reasoning_output_tokens',
    ];
    $knownSources = array_values($mapping);

    foreach (array_keys($value) as $name) {
        if (!is_string($name) || !in_array($name, $knownSources, true)) {
            return null;
        }
    }

    $usage = [];

    foreach ($mapping as $target => $source) {
        if (!array_key_exists($source, $value)) {
            $usage[$target] = null;
            continue;
        }

        $tokens = $value[$source];
        if (!is_int($tokens) || $tokens < 0) {
            return null;
        }

        $usage[$target] = $tokens;
    }

    /** @var array{input_tokens: int<0, max>|null, output_tokens: int<0, max>|null, cached_tokens: int<0, max>|null, reasoning_tokens: int<0, max>|null} $usage */
    return $usage;
}

/**
 * @param array{
 *   input_tokens: int|null,
 *   output_tokens: int|null,
 *   cached_tokens: int|null,
 *   reasoning_tokens: int|null
 * } $usage
 */
function agentEvaluationControllerUsageExceedsBudget(array $usage, int $modelTokenBudget): bool
{
    foreach ($usage as $tokens) {
        if (is_int($tokens) && $tokens > $modelTokenBudget) {
            return true;
        }
    }

    return is_int($usage['input_tokens'])
        && is_int($usage['output_tokens'])
        && ($usage['input_tokens'] + $usage['output_tokens']) > $modelTokenBudget;
}

/**
 * @param array{
 *   exit_code: int,
 *   timed_out: bool,
 *   output_limit_exceeded: bool,
 *   cleanup: array{
 *     process_group_created: bool,
 *     process_reaped: bool,
 *     process_group_absent: bool
 *   }
 * } $process
 * @param array{
 *   valid: bool,
 *   completed: bool,
 *   failed: bool,
 *   token_limit_exceeded: bool
 * } $parsed
 */
function agentEvaluationControllerCodexTerminationReason(array $process, array $parsed): string
{
    if ($process['timed_out']) {
        return 'wall_time_limit';
    }

    if ($process['output_limit_exceeded']) {
        return 'output_limit';
    }

    if (
        !$process['cleanup']['process_group_created']
        || !$process['cleanup']['process_reaped']
        || !$process['cleanup']['process_group_absent']
    ) {
        return 'cleanup_failed';
    }

    if ($process['exit_code'] !== 0) {
        return 'runner_failed';
    }

    if (!$parsed['valid']) {
        return 'invalid_runner_output';
    }

    if ($parsed['token_limit_exceeded']) {
        return 'model_token_limit';
    }

    if ($parsed['failed'] || !$parsed['completed']) {
        return 'runner_failed';
    }

    return 'completed';
}
