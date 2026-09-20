<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_LIVE_GEMINI_UNAVAILABLE = 'AGENT_EVALUATION_CONTROLLER_LIVE_GEMINI_UNAVAILABLE';
const AGENT_EVALUATION_CONTROLLER_FUTURE_GEMINI_PATH = '/usr/local/bin/gemini';
const AGENT_EVALUATION_CONTROLLER_GEMINI_MAX_PROMPT_BYTES = 1_048_576;
const AGENT_EVALUATION_CONTROLLER_GEMINI_MAX_EVENT_BYTES = 1_048_576;
const AGENT_EVALUATION_CONTROLLER_GEMINI_MAX_EVENTS = 4_096;
const AGENT_EVALUATION_CONTROLLER_GEMINI_PROXY_RESPONSE_BYTES = 4_194_304;
const AGENT_EVALUATION_CONTROLLER_GEMINI_PROXY_REQUEST_LIMIT = 128;
const AGENT_EVALUATION_CONTROLLER_GEMINI_PROXY_RUN_RESPONSE_BYTES = AGENT_EVALUATION_CONTROLLER_GEMINI_PROXY_REQUEST_LIMIT
    * AGENT_EVALUATION_CONTROLLER_GEMINI_PROXY_RESPONSE_BYTES;

/**
 * @param array<string, mixed>|null $spending
 * @return list<string>
 */
function agentEvaluationControllerLiveGeminiArguments(
    string $model,
    string $thinkingBudget,
    ?array $spending = null,
    ?int $modelTokenBudget = null,
): array {
    if (
        preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $model) !== 1
        || !in_array($thinkingBudget, ['low', 'medium', 'high', 'max', 'off'], true)
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_MODEL_INVALID');
    }

    $effectiveBudget = $modelTokenBudget ?? ($spending === null ? 40_000 : 200_000);
    if ($effectiveBudget < 1 || ($spending === null && $effectiveBudget > 40_000)
        || ($spending !== null && $effectiveBudget > 200_000 && $effectiveBudget !== 1_000_000)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_BUDGET_INVALID');
    }

    return [
        AGENT_EVALUATION_CONTROLLER_FUTURE_GEMINI_PATH,
        'exec',
        '--ephemeral',
        '--ignore-user-config',
        '--ignore-rules',
        '--strict-config',
        '--skip-git-repo-check',
        '--sandbox',
        'danger-full-access',
        '--json',
        '--color',
        'never',
        '--model',
        $model,
        '--cd',
        '/candidate',
        '-c',
        'approval_policy="never"',
        '-c',
        'thinking_budget="' . $thinkingBudget . '"',
        '-c',
        'model_provider="phpthis-gemini-proxy"',
        '-c',
        'model_providers.phpthis-gemini-proxy={name="PHPThis Gemini proxy",base_url="http://127.0.0.1:8765/v1beta",wire_api="gemini",requires_auth=false,supports_websockets=false,request_max_retries=0,stream_max_retries=0,stream_idle_timeout_ms=1200000}',
        '-c',
        'shell_environment_policy.inherit="none"',
        '-c',
        'shell_environment_policy.set={PATH="/usr/local/bin:/usr/bin:/bin",HOME="/tmp/phpthis-home",LANG="C",LC_ALL="C"}',
        '-c',
        'shell_environment_policy.experimental_use_profile=false',
        '-c',
        'web_search="disabled"',
        '-c',
        'agents.enabled=false',
        '-c',
        'features.multi_agent=false',
        '-c',
        'features.apps=false',
        '-c',
        'features.hooks=false',
        '-c',
        'features.remote_plugin=false',
        '-c',
        'features.plugins=false',
        '-c',
        'features.memories=false',
        '-c',
        'features.goals=false',
        '-c',
        'features.shell_snapshot=false',
        '-c',
        'features.unified_exec=false',
        '-c',
        'features.enable_request_compression=false',
        '-c',
        'features.skill_mcp_dependency_install=false',
        '-c',
        'features.view_image=false',
        '-c',
        'features.image_generation=false',
        '-c',
        'features.computer_use=false',
        '-c',
        'features.browser_use=false',
        '-c',
        'features.workspace_dependencies=false',
        '-c',
        'mcp_servers={}',
        '-',
    ];
}

/** @return array<string, string> */
function agentEvaluationControllerLiveGeminiEnvironment(): array
{
    return [
        'LANG' => 'C',
        'LC_ALL' => 'C',
        'PATH' => '/usr/local/bin:/usr/bin:/bin',
        'HOME' => '/tmp/phpthis-home',
        'GEMINI_HOME' => '/tmp/phpthis-gemini',
    ];
}

/**
 * @param array<string, mixed>|null $spending
 * @return array{
 *   model: string,
 *   thinking_budget: string,
 *   token_budget: int,
 *   input_tokens: int,
 *   output_tokens: int,
 *   cached_tokens: int,
 *   reasoning_tokens: int,
 *   reserved_input: int,
 *   reserved_output: int,
 *   request_count: int,
 *   observed_request_count: int,
 *   last_request_sha256: string|null,
 *   response_bytes: int,
 *   last_response_sha256: string|null,
 *   last_response_bytes: int|null,
 *   response_rejection_stage: string|null,
 *   response_observation: array<string, mixed>|null,
 *   response_event_observation: array<string, mixed>|null,
 *   provider_error_event_seen: bool,
 *   provider_error_observation: array<string, mixed>|null,
 *   request_sha256: string|null,
 *   blocked: bool,
 *   failure_reason: string|null,
 *   spending?: array{policy: array<string, mixed>, settled_units: int, reserved_units: int}
 * }
 */
function agentEvaluationControllerGeminiProxyState(
    string $model,
    string $thinkingBudget,
    int $tokenBudget,
    ?array $spending = null,
): array {
    agentEvaluationControllerLiveGeminiArguments($model, $thinkingBudget, $spending, $tokenBudget);

    $state = [
        'model' => $model,
        'thinking_budget' => $thinkingBudget,
        'token_budget' => $tokenBudget,
        'input_tokens' => 0,
        'output_tokens' => 0,
        'cached_tokens' => 0,
        'reasoning_tokens' => 0,
        'reserved_input' => 0,
        'reserved_output' => 0,
        'request_count' => 0,
        'observed_request_count' => 0,
        'last_request_sha256' => null,
        'response_bytes' => 0,
        'last_response_sha256' => null,
        'last_response_bytes' => null,
        'response_rejection_stage' => null,
        'response_observation' => null,
        'response_event_observation' => null,
        'provider_error_event_seen' => false,
        'provider_error_observation' => null,
        'request_sha256' => null,
        'blocked' => false,
        'failure_reason' => null,
    ];

    if ($spending !== null) {
        $state['spending'] = ['policy' => $spending, 'settled_units' => 0, 'reserved_units' => 0];
    }

    return $state;
}

/**
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets
 */
function agentEvaluationControllerValidateGeminiRequest(
    string $candidateDirectory,
    string $prompt,
    string $model,
    string $thinkingBudget,
    array $budgets,
): string {
    $candidateRoot = realpath($candidateDirectory);

    if (
        $candidateRoot === false
        || !is_dir($candidateRoot)
        || is_link($candidateDirectory)
        || basename($candidateRoot) === ''
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_CANDIDATE_ROOT_INVALID');
    }

    if (
        $prompt === ''
        || strlen($prompt) > AGENT_EVALUATION_CONTROLLER_GEMINI_MAX_PROMPT_BYTES
        || str_contains($prompt, "\0")
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROMPT_INVALID');
    }

    if (
        preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $model) !== 1
        || !in_array($thinkingBudget, ['low', 'medium', 'high', 'max', 'off'], true)
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_MODEL_INVALID');
    }

    if (
        $budgets['model_tokens'] < 1
        || $budgets['wall_seconds'] < 1
        || $budgets['repair_turns'] < 0
        || $budgets['command_output_bytes'] < 1
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_BUDGETS_INVALID');
    }

    return $candidateRoot;
}

/**
 * @return array{
 *   events: list<array<string, mixed>>,
 *   response: string,
 *   usage: array{input_tokens: int, output_tokens: int, cached_tokens: int|null, reasoning_tokens: int|null}
 * }
 */
function agentEvaluationControllerParseGeminiEvents(string $stdout, int $modelTokenBudget): array
{
    $lines = preg_split('/\R/', $stdout);

    if (!is_array($lines)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_GEMINI_EVENTS_INVALID');
    }

    /** @var list<array<string, mixed>> $events */
    $events = [];
    $response = '';
    $inputTokens = 0;
    $outputTokens = 0;
    $cachedTokens = null;
    $reasoningTokens = null;
    $eventCount = 0;

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }

        $eventCount++;
        if ($eventCount > AGENT_EVALUATION_CONTROLLER_GEMINI_MAX_EVENTS || strlen($line) > AGENT_EVALUATION_CONTROLLER_GEMINI_MAX_EVENT_BYTES) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_GEMINI_EVENTS_OVERFLOW');
        }

        try {
            $decoded = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_GEMINI_EVENTS_MALFORMED', 0, $e);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_GEMINI_EVENTS_MALFORMED');
        }

        /** @var array<string, mixed> $decoded */
        $type = $decoded['type'] ?? null;
        if (!is_string($type)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_GEMINI_EVENTS_MALFORMED');
        }

        if ($type === 'item.completed') {
            $item = $decoded['item'] ?? null;
            if (is_array($item) && ($item['type'] ?? null) === 'agent_message' && is_string($item['text'] ?? null)) {
                $response = $item['text'];
            }
        }

        if ($type === 'turn.completed' && is_array($decoded['usage'] ?? null)) {
            $usage = $decoded['usage'];
            if (is_int($usage['input_tokens'] ?? null)) {
                $inputTokens += $usage['input_tokens'];
            }
            if (is_int($usage['output_tokens'] ?? null)) {
                $outputTokens += $usage['output_tokens'];
            }
            if (is_int($usage['cached_input_tokens'] ?? null)) {
                $cachedTokens = ($cachedTokens ?? 0) + $usage['cached_input_tokens'];
            }
            if (is_int($usage['reasoning_output_tokens'] ?? null)) {
                $reasoningTokens = ($reasoningTokens ?? 0) + $usage['reasoning_output_tokens'];
            }
        }

        $events[] = $decoded;
    }

    if ($inputTokens + $outputTokens > $modelTokenBudget) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_GEMINI_TOKEN_BUDGET_EXCEEDED');
    }

    return [
        'events' => $events,
        'response' => $response,
        'usage' => [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'reasoning_tokens' => $reasoningTokens,
        ],
    ];
}

/**
 * @param array<string, mixed> $process
 * @param array<string, mixed> $parsedEvents
 */
function agentEvaluationControllerGeminiTerminationReason(array $process, array $parsedEvents): string
{
    $exitCode = $process['exit_code'] ?? null;

    if ($exitCode === 0) {
        return 'completed';
    }

    if (($process['timed_out'] ?? false) === true) {
        return 'wall_time_limit';
    }

    if (($process['output_limit_exceeded'] ?? false) === true) {
        return 'output_limit';
    }

    return 'runner_failed';
}

/**
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets
 * @param array<string, mixed> $isolation
 * @return array{
 *   runner: string,
 *   events: list<array<string, mixed>>,
 *   events_jsonl: string,
 *   response: string,
 *   usage: array{input_tokens: int, output_tokens: int, cached_tokens: int|null, reasoning_tokens: int|null},
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
 *   },
 *   termination_reason: string
 * }
 */
function agentEvaluationControllerRunGemini(
    string $candidateDirectory,
    string $prompt,
    string $model,
    string $thinkingBudget,
    array $budgets,
    array $isolation,
    bool $fakeForTests = false,
): array {
    $candidateRoot = agentEvaluationControllerValidateGeminiRequest(
        $candidateDirectory,
        $prompt,
        $model,
        $thinkingBudget,
        $budgets,
    );

    if (!$fakeForTests) {
        agentEvaluationControllerValidateFutureIsolationProfile($isolation, $budgets, 'generation', AGENT_EVALUATION_CONTROLLER_GEMINI_CREDENTIAL_BROKER);
        throw new RuntimeException(
            AGENT_EVALUATION_CONTROLLER_LIVE_GEMINI_UNAVAILABLE
            . ': v0.2 records the pinned OCI and Gemini credential-proxy contract but does not invoke it directly.',
        );
    }

    if (!agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_FAKE_GEMINI_TEST_ONLY');
    }

    agentEvaluationControllerValidateIsolationProfile($isolation, $budgets, true, AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_GEMINI);

    $fixture = __DIR__ . '/fixtures/fake-gemini.php';

    if (!is_file($fixture) || is_link($fixture)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_FAKE_GEMINI_MISSING');
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
        'thinking_budget="' . $thinkingBudget . '"',
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

    $parsed = agentEvaluationControllerParseGeminiEvents(
        $process['stdout'],
        $budgets['model_tokens'],
    );

    $terminationReason = agentEvaluationControllerGeminiTerminationReason($process, $parsed);

    return [
        'runner' => AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_GEMINI,
        'events' => $parsed['events'],
        'events_jsonl' => $process['stdout'],
        'response' => $parsed['response'],
        'usage' => $parsed['usage'],
        'process' => $process,
        'termination_reason' => $terminationReason,
    ];
}
