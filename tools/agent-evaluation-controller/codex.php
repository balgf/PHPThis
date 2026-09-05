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
const AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES = 4_194_304;
const AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT = 128;

/** @return list<string> */
function agentEvaluationControllerLiveCodexArguments(string $model, string $reasoningEffort): array
{
    if (
        preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $model) !== 1
        || !in_array($reasoningEffort, ['low', 'medium', 'high', 'xhigh', 'max', 'ultra'], true)
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_MODEL_INVALID');
    }

    return [
        AGENT_EVALUATION_CONTROLLER_FUTURE_CODEX_PATH,
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
        'model_reasoning_effort="' . $reasoningEffort . '"',
        '-c',
        'model_auto_compact_token_limit=40001',
        '-c',
        'model_provider="phpthis-run-proxy"',
        '-c',
        'model_providers.phpthis-run-proxy={name="PHPThis run proxy",base_url="http://127.0.0.1:8765/v1",wire_api="responses",requires_openai_auth=false,supports_websockets=false,request_max_retries=0,stream_max_retries=0,stream_idle_timeout_ms=1200000}',
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
function agentEvaluationControllerLiveCodexEnvironment(): array
{
    return [
        'LANG' => 'C',
        'LC_ALL' => 'C',
        'PATH' => '/usr/local/bin:/usr/bin:/bin',
        'HOME' => '/tmp/phpthis-home',
        'CODEX_HOME' => '/tmp/phpthis-codex',
    ];
}

/** @return array<string, mixed> */
function agentEvaluationControllerProxyState(string $model, string $reasoningEffort, int $tokenBudget): array
{
    agentEvaluationControllerLiveCodexArguments($model, $reasoningEffort);

    if ($tokenBudget < 1 || $tokenBudget > 40_000) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_BUDGET_INVALID');
    }

    return [
        'model' => $model,
        'reasoning_effort' => $reasoningEffort,
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
        'request_sha256' => null,
        'blocked' => false,
        'failure_reason' => null,
    ];
}

/**
 * @param array<string, mixed> $state
 * @return array{count_json: string, request: array<string, mixed>}
 */
function agentEvaluationControllerProxyRequest(string $body, array &$state): array
{
    $stage = 'availability';
    try {
        $observed = agentEvaluationRequireNonNegativeInteger($state, 'observed_request_count', 'proxy');
        $state['observed_request_count'] = min($observed, AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT) + 1;
        $state['last_request_sha256'] = hash('sha256', $body);
        if (
            ($state['blocked'] ?? true) !== false
            || ($state['request_sha256'] ?? null) !== null
            || ($state['reserved_output'] ?? -1) !== 0
            || agentEvaluationRequireNonNegativeInteger($state, 'request_count', 'proxy')
                >= AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT
            || $observed >= AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT
            || strlen($body) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES
        ) {
            throw new RuntimeException('Proxy is unavailable.');
        }

        $stage = 'json';
        $request = agentEvaluationControllerProxyJsonObject($body);
        $allowed = [
            'model', 'input', 'instructions', 'tools', 'tool_choice', 'parallel_tool_calls',
            'reasoning', 'text', 'include', 'store', 'stream', 'max_output_tokens',
            'service_tier', 'truncation', 'metadata', 'prompt_cache_key', 'client_metadata',
        ];

        $stage = 'fields';
        if (array_diff(array_keys($request), $allowed) !== []) {
            throw new RuntimeException('Proxy request fields are unsupported.');
        }
        $stage = 'client_metadata';
        if (array_key_exists('client_metadata', $request)) {
            if (!$request['client_metadata'] instanceof stdClass) {
                throw new RuntimeException('Proxy client metadata must be an object.');
            }
            // Codex sends client transport hints that are not model input or approved upstream headers.
            unset($request['client_metadata']);
        }

        $stage = 'policy';
        if (
            ($request['model'] ?? null) !== ($state['model'] ?? null)
            || ($request['stream'] ?? null) !== true
            || ($request['store'] ?? false) !== false
            || !array_key_exists('input', $request)
            || (isset($request['truncation']) && $request['truncation'] !== 'disabled')
            || (isset($request['service_tier']) && $request['service_tier'] !== 'default')
        ) {
            throw new RuntimeException('Proxy request policy does not match.');
        }

        $stage = 'reasoning';
        $reasoning = $request['reasoning'] ?? null;

        if (
            !$reasoning instanceof stdClass
            || ($reasoning->effort ?? null) !== ($state['reasoning_effort'] ?? null)
        ) {
            throw new RuntimeException('Proxy reasoning setting does not match.');
        }

        $stage = 'input';
        agentEvaluationControllerProxyValidateInput($request['input']);
        $stage = 'tools';
        agentEvaluationControllerProxyValidateTools($request['tools'] ?? []);
        $stage = 'tool_choice';
        $toolChoice = $request['tool_choice'] ?? 'auto';

        if (!in_array($toolChoice, ['auto', 'none', 'required'], true)) {
            throw new RuntimeException('Proxy supports only the local tool list and literal selection policy.');
        }

        $stage = 'output_limit';
        if (isset($request['max_output_tokens']) && (!is_int($request['max_output_tokens']) || $request['max_output_tokens'] < 16)) {
            throw new RuntimeException('Proxy requested output limit is invalid.');
        }

        $stage = 'normalization';
        $request['store'] = false;
        $request['truncation'] = 'disabled';
        $request['service_tier'] = 'default';
        $count = array_intersect_key($request, array_flip([
            'model', 'input', 'instructions', 'parallel_tool_calls', 'reasoning',
            'text', 'tool_choice', 'tools', 'truncation',
        ]));
        $state['request_sha256'] = hash('sha256', agentEvaluationControllerProxyEncodeRequest($request));

        return ['count_json' => agentEvaluationControllerProxyEncodeRequest($count), 'request' => $request];
    } catch (Throwable) {
        $state['blocked'] = true;
        $state['request_rejection_stage'] = $stage;
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_REJECTED');
    }
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $state
 */
function agentEvaluationControllerProxyReserve(array $request, string $countResponse, array &$state): string
{
    try {
        if (
            ($state['blocked'] ?? true) !== false
            || ($state['reserved_output'] ?? -1) !== 0
            || ($state['request_sha256'] ?? null) !== hash('sha256', agentEvaluationControllerProxyEncodeRequest($request))
        ) {
            throw new RuntimeException('Proxy request is not pending.');
        }

        $count = agentEvaluationControllerProxyJsonObject($countResponse);
        agentEvaluationRequireExactKeys($count, ['object', 'input_tokens'], 'proxy input count');

        if (($count['object'] ?? null) !== 'response.input_tokens') {
            throw new RuntimeException('Proxy count identity is invalid.');
        }

        $inputTokens = agentEvaluationRequireNonNegativeInteger($count, 'input_tokens', 'proxy count');
        $remaining = agentEvaluationRequirePositiveInteger($state, 'token_budget', 'proxy')
            - agentEvaluationRequireNonNegativeInteger($state, 'input_tokens', 'proxy')
            - agentEvaluationRequireNonNegativeInteger($state, 'output_tokens', 'proxy');

        if ($inputTokens > $remaining - 16) {
            $state['failure_reason'] = 'model_token_limit';
            throw new RuntimeException('Proxy token budget is exhausted.');
        }

        $requestedOutput = $request['max_output_tokens'] ?? $remaining;

        if (!is_int($requestedOutput) || $requestedOutput < 16) {
            throw new RuntimeException('Proxy output reservation is invalid.');
        }

        $outputTokens = min($requestedOutput, $remaining - $inputTokens);
        $state['reserved_input'] = $inputTokens;
        $state['reserved_output'] = $outputTokens;
        $state['request_count'] = agentEvaluationRequireNonNegativeInteger($state, 'request_count', 'proxy') + 1;
        $request['max_output_tokens'] = $outputTokens;

        return agentEvaluationControllerProxyEncodeRequest($request);
    } catch (Throwable) {
        $state['blocked'] = true;
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_RESERVATION_REJECTED');
    }
}

/**
 * @param array<string, mixed> $state
 * @return array{input_tokens: int, output_tokens: int, cached_tokens: int|null, reasoning_tokens: int|null}
 */
function agentEvaluationControllerProxyComplete(string $sse, array &$state): array
{
    try {
        $responseBytes = agentEvaluationRequireNonNegativeInteger($state, 'response_bytes', 'proxy') + strlen($sse);

        if (
            ($state['blocked'] ?? true) !== false
            || agentEvaluationRequireNonNegativeInteger($state, 'reserved_output', 'proxy') < 16
            || $responseBytes > AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES
        ) {
            throw new RuntimeException('Proxy response is unavailable.');
        }

        $state['response_bytes'] = $responseBytes;
        $response = null;
        $terminalSeen = false;
        $terminalType = null;
        $frames = explode("\n\n", str_replace("\r\n", "\n", $sse));

        foreach ($frames as $frame) {
            if (trim($frame) === '') {
                continue;
            }

            $data = [];
            $eventName = null;

            foreach (explode("\n", $frame) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5), ' ');
                } elseif (str_starts_with($line, 'event:') && $eventName === null) {
                    $eventName = ltrim(substr($line, 6), ' ');
                } elseif ($line !== '' && !str_starts_with($line, ':')) {
                    throw new RuntimeException('Proxy SSE frame is unsupported.');
                }
            }

            if ($data === []) {
                continue;
            }

            $json = implode("\n", $data);

            if ($json === '[DONE]' && $terminalSeen) {
                continue;
            }

            if ($terminalSeen) {
                throw new RuntimeException('Proxy SSE has data after its terminal event.');
            }

            $event = agentEvaluationControllerProxyJsonObject($json);
            $type = agentEvaluationRequireString($event, 'type', 'proxy event');

            if (($eventName !== null && $eventName !== $type) || !str_starts_with($type, 'response.')) {
                throw new RuntimeException('Proxy SSE event identity is invalid.');
            }

            if (in_array($type, ['response.completed', 'response.incomplete', 'response.failed'], true)) {
                $terminalSeen = true;
                $terminalType = $type;
                $value = $event['response'] ?? null;

                if (!$value instanceof stdClass) {
                    throw new RuntimeException('Proxy terminal response is invalid.');
                }

                $response = agentEvaluationControllerProxyObjectMembers($value);
            }
        }

        if (
            $response === null
            || ($response['model'] ?? null) !== ($state['model'] ?? null)
            || !in_array($response['status'] ?? null, ['completed', 'incomplete', 'failed'], true)
            || $terminalType !== 'response.' . $response['status']
            || (isset($response['service_tier']) && $response['service_tier'] !== 'default')
        ) {
            throw new RuntimeException('Proxy response identity is invalid.');
        }

        $usageValue = $response['usage'] ?? null;

        if (!$usageValue instanceof stdClass) {
            throw new RuntimeException('Proxy response usage is missing.');
        }

        $usage = agentEvaluationControllerProxyObjectMembers($usageValue);
        $input = agentEvaluationRequireNonNegativeInteger($usage, 'input_tokens', 'proxy usage');
        $output = agentEvaluationRequireNonNegativeInteger($usage, 'output_tokens', 'proxy usage');
        $total = agentEvaluationRequireNonNegativeInteger($usage, 'total_tokens', 'proxy usage');

        if (
            $input !== ($state['reserved_input'] ?? null)
            || $output > agentEvaluationRequireNonNegativeInteger($state, 'reserved_output', 'proxy')
            || $total !== $input + $output
        ) {
            throw new RuntimeException('Proxy response usage exceeds or disagrees with its reservation.');
        }

        $cached = agentEvaluationControllerProxyDetail($usage['input_tokens_details'] ?? null, 'cached_tokens', $input);
        $reasoning = agentEvaluationControllerProxyDetail($usage['output_tokens_details'] ?? null, 'reasoning_tokens', $output);
        $state['input_tokens'] = agentEvaluationRequireNonNegativeInteger($state, 'input_tokens', 'proxy') + $input;
        $state['output_tokens'] = agentEvaluationRequireNonNegativeInteger($state, 'output_tokens', 'proxy') + $output;

        foreach (['cached_tokens' => $cached, 'reasoning_tokens' => $reasoning] as $name => $category) {
            $previous = $state[$name] ?? null;
            $state[$name] = is_int($previous) && $category !== null ? $previous + $category : null;
        }

        $state['reserved_input'] = 0;
        $state['reserved_output'] = 0;
        $state['request_sha256'] = null;

        if ($response['status'] !== 'completed') {
            $details = $response['incomplete_details'] ?? null;

            if ($details instanceof stdClass && ($details->reason ?? null) === 'max_output_tokens') {
                $state['failure_reason'] = 'model_token_limit';
            }

            throw new RuntimeException('Proxy terminal response was not completed.');
        }

        return ['input_tokens' => $input, 'output_tokens' => $output, 'cached_tokens' => $cached, 'reasoning_tokens' => $reasoning];
    } catch (Throwable) {
        $state['blocked'] = true;
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED');
    }
}

/** @return array<string, mixed> */
function agentEvaluationControllerProxyJsonObject(string $json): array
{
    if (strlen($json) > AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES) {
        throw new RuntimeException('Proxy JSON exceeds its bound.');
    }

    $value = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
    $offset = 0;
    agentEvaluationControllerScanJsonValue($json, $offset);
    agentEvaluationControllerSkipJsonWhitespace($json, $offset);

    if (!$value instanceof stdClass || $offset !== strlen($json)) {
        throw new RuntimeException('Proxy JSON must be one complete object without duplicate members.');
    }

    return agentEvaluationControllerProxyObjectMembers($value);
}

/** @param array<string, mixed> $request */
function agentEvaluationControllerProxyEncodeRequest(array $request): string
{
    $json = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (strlen($json) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES) {
        throw new RuntimeException('Proxy normalized request exceeds its byte bound.');
    }

    return $json;
}

/** @return array<string, mixed> */
function agentEvaluationControllerProxyObjectMembers(stdClass $value): array
{
    $members = [];

    foreach (get_object_vars($value) as $name => $member) {
        if (!is_string($name)) {
            throw new RuntimeException('Proxy object members must use textual names.');
        }

        $members[$name] = $member;
    }

    return $members;
}

function agentEvaluationControllerProxyDetail(mixed $details, string $name, int $maximum): ?int
{
    if ($details === null) {
        return null;
    }

    if (!$details instanceof stdClass) {
        throw new RuntimeException('Proxy usage detail is invalid.');
    }

    $properties = get_object_vars($details);

    if (!array_key_exists($name, $properties)) {
        return null;
    }

    $value = $properties[$name];

    if (!is_int($value) || $value < 0 || $value > $maximum) {
        throw new RuntimeException('Proxy usage detail is outside its parent category.');
    }

    return $value;
}

function agentEvaluationControllerProxyValidateInput(mixed $input): void
{
    if (is_string($input)) {
        return;
    }

    if (!is_array($input) || !array_is_list($input)) {
        throw new RuntimeException('Proxy supports only inline text and local tool history.');
    }

    foreach ($input as $item) {
        if (!$item instanceof stdClass) {
            throw new RuntimeException('Proxy input item is invalid.');
        }

        $value = get_object_vars($item);
        $type = $value['type'] ?? 'message';

        if (!in_array($type, [
            'message', 'reasoning', 'function_call', 'function_call_output',
            'custom_tool_call', 'custom_tool_call_output', 'local_shell_call', 'local_shell_call_output',
        ], true)) {
            throw new RuntimeException('Proxy input item requires unsupported external state.');
        }

        if (
            in_array($type, ['function_call_output', 'custom_tool_call_output', 'local_shell_call_output'], true)
            && !is_string($value['output'] ?? null)
        ) {
            throw new RuntimeException('Proxy local tool output must be inline text.');
        }

        if ($type === 'message') {
            $content = $value['content'] ?? null;

            if (is_string($content)) {
                continue;
            }

            if (!is_array($content) || !array_is_list($content)) {
                throw new RuntimeException('Proxy message content is invalid.');
            }

            foreach ($content as $part) {
                if (
                    !$part instanceof stdClass
                    || !in_array($part->type ?? null, ['input_text', 'output_text', 'refusal'], true)
                ) {
                    throw new RuntimeException('Proxy permits only inline textual message content.');
                }
            }
        }
    }
}

function agentEvaluationControllerProxyValidateTools(mixed $tools): void
{
    if (!is_array($tools) || !array_is_list($tools)) {
        throw new RuntimeException('Proxy tool list is invalid.');
    }

    foreach ($tools as $tool) {
        if (!$tool instanceof stdClass) {
            throw new RuntimeException('Proxy tool is invalid.');
        }

        $type = $tool->type ?? null;

        if ($type === 'namespace') {
            agentEvaluationControllerProxyValidateTools($tool->tools ?? null);
        } elseif (!in_array($type, ['function', 'custom', 'local_shell'], true)) {
            throw new RuntimeException('Proxy refuses provider-hosted tools and external destinations.');
        }
    }
}

/**
 * @param array<string, mixed> $resources
 * @param array<string, mixed> $profile
 * @return array{
 *   runner: string,
 *   events: list<array<string, mixed>>,
 *   events_jsonl: string,
 *   response: string,
 *   usage: array{input_tokens: int|null, output_tokens: int|null, cached_tokens: int|null, reasoning_tokens: int|null},
 *   termination_reason: string,
 *   process: array{stdout: string, stderr: string, termination_reason: string},
 *   proxy_evidence: array<string, mixed>,
 *   external_actions: array{approved: bool, network: string, socket_attempt_telemetry: null, host_proxy_requests: int, proxy_blocked: bool, observed_commands: list<array{item_id: string, sha256: string, bytes: int}>},
 *   external_actions_approved: bool
 * }
 */
function agentEvaluationControllerRunLiveCodex(
    array &$resources,
    string $prompt,
    array $profile,
    #[SensitiveParameter]
    string $credential,
): array {
    $budgetValues = agentEvaluationRequireObject($profile, 'budgets', 'live Codex profile');
    $budgets = [
        'model_tokens' => agentEvaluationRequirePositiveInteger($budgetValues, 'model_tokens', 'live Codex budget'),
        'wall_seconds' => agentEvaluationRequirePositiveInteger($budgetValues, 'wall_seconds', 'live Codex budget'),
        'repair_turns' => agentEvaluationRequireNonNegativeInteger($budgetValues, 'repair_turns', 'live Codex budget'),
        'command_output_bytes' => agentEvaluationRequirePositiveInteger($budgetValues, 'command_output_bytes', 'live Codex budget'),
    ];
    agentEvaluationControllerValidateBudgets($budgets);
    agentEvaluationControllerValidateFutureIsolationProfile(
        agentEvaluationRequireObject($profile, 'isolation', 'live Codex profile'),
        $budgets,
        'generation',
    );

    if ($prompt === '' || strlen($prompt) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES || str_contains($prompt, "\0")) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_PROMPT_INVALID');
    }

    $process = agentEvaluationControllerOciRunGeneration($resources, $prompt, $profile, $credential);
    $eventsJsonl = agentEvaluationRequireString($process, 'events_jsonl', 'live Codex process');
    $parsed = agentEvaluationControllerParseCodexEvents($eventsJsonl, $budgets['model_tokens'], true);
    $proxy = agentEvaluationRequireObject($process, 'proxy', 'live Codex process');
    $usage = agentEvaluationControllerProxyAggregateUsage($proxy);
    $termination = agentEvaluationRequireString($process, 'termination_reason', 'live Codex process');
    $cleanup = agentEvaluationRequireObject($process, 'cleanup', 'live Codex process');
    $externalActions = agentEvaluationControllerLiveExternalActions($parsed['events'], $proxy);
    $syntheticUpstream = ($process['synthetic_upstream'] ?? false) === true;

    if ($termination === 'process_failed' && ($proxy['failure_reason'] ?? null) === 'model_token_limit') {
        $termination = 'model_token_limit';
    }

    if ($termination === 'completed') {
        if (($cleanup['container_stopped'] ?? false) !== true || ($cleanup['oom_killed'] ?? true) !== false) {
            $termination = 'cleanup_failed';
        } elseif (($proxy['failure_reason'] ?? null) === 'model_token_limit') {
            $termination = 'model_token_limit';
        } elseif (
            ($proxy['blocked'] ?? true) !== false
            || ($proxy['reserved_output'] ?? -1) !== 0
            || agentEvaluationRequireNonNegativeInteger($proxy, 'request_count', 'live proxy') < 1
        ) {
            $termination = 'runner_failed';
        } elseif (!$parsed['valid'] || !$parsed['completed'] || $parsed['failed']) {
            $termination = 'invalid_runner_output';
        } elseif (($process['exit_code'] ?? null) !== 0 || !$externalActions['approved']) {
            $termination = 'runner_failed';
        }
    }

    if ($termination === 'completed') {
        foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens'] as $category) {
            $reported = $parsed['usage'][$category];
            $observed = $usage[$category];

            if ($reported !== null && $observed !== null && $reported !== $observed) {
                $termination = 'invalid_runner_output';
            }
        }
    }

    return [
        'runner' => AGENT_EVALUATION_CONTROLLER_RUNNER_CODEX,
        'events' => $parsed['events'],
        'events_jsonl' => $eventsJsonl,
        'response' => $parsed['response'],
        'usage' => $usage,
        'termination_reason' => $termination,
        'process' => [
            'exit_code' => $process['exit_code'] ?? null,
            'stdout' => $eventsJsonl,
            'stderr' => agentEvaluationRequireString($process, 'stderr', 'live Codex process'),
            'termination_reason' => $termination,
            'elapsed_milliseconds' => agentEvaluationRequireNonNegativeInteger($process, 'elapsed_milliseconds', 'live Codex process'),
            'timed_out' => agentEvaluationRequireBoolean($process, 'timed_out', 'live Codex process'),
            'output_limit_exceeded' => agentEvaluationRequireBoolean($process, 'output_limit_exceeded', 'live Codex process'),
            'cleanup' => $cleanup,
            'resource_observation' => $process['resource_observation'] ?? null,
            'synthetic_upstream' => $syntheticUpstream,
            'failure_code' => $process['failure_code'] ?? null,
        ],
        'proxy_evidence' => [
            'candidate_operation' => 'POST /v1/responses',
            'upstream_operations' => ['POST /v1/responses/input_tokens', 'POST /v1/responses'],
            'upstream_origin' => $syntheticUpstream ? 'http://127.0.0.1:18765' : 'https://api.openai.com',
            'synthetic_upstream' => $syntheticUpstream,
            'transport' => 'container-loopback-to-stdio',
            'provider_reported_usage' => $usage,
            'runner_reported_usage' => $parsed['usage'],
            'ledger' => $proxy,
        ],
        'external_actions' => $externalActions,
        'external_actions_approved' => $termination === 'completed' && $externalActions['approved'],
    ];
}

/**
 * @param array<string, mixed> $proxy
 * @return array{input_tokens: int|null, output_tokens: int|null, cached_tokens: int|null, reasoning_tokens: int|null}
 */
function agentEvaluationControllerProxyAggregateUsage(array $proxy): array
{
    if (agentEvaluationRequireNonNegativeInteger($proxy, 'reserved_output', 'proxy ledger') > 0) {
        return ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null];
    }

    $input = agentEvaluationRequireNonNegativeInteger($proxy, 'input_tokens', 'proxy ledger');
    $output = agentEvaluationRequireNonNegativeInteger($proxy, 'output_tokens', 'proxy ledger');
    $cached = $proxy['cached_tokens'] ?? null;
    $reasoning = $proxy['reasoning_tokens'] ?? null;

    if (
        $input + $output > agentEvaluationRequirePositiveInteger($proxy, 'token_budget', 'proxy ledger')
        || ($cached !== null && (!is_int($cached) || $cached < 0 || $cached > $input))
        || ($reasoning !== null && (!is_int($reasoning) || $reasoning < 0 || $reasoning > $output))
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_LEDGER_INVALID');
    }

    return ['input_tokens' => $input, 'output_tokens' => $output, 'cached_tokens' => $cached, 'reasoning_tokens' => $reasoning];
}

/**
 * @param list<array<string, mixed>> $events
 * @param array<string, mixed> $proxy
 * @return array{approved: bool, network: string, socket_attempt_telemetry: null, host_proxy_requests: int, proxy_blocked: bool, observed_commands: list<array{item_id: string, sha256: string, bytes: int}>}
 */
function agentEvaluationControllerLiveExternalActions(array $events, array $proxy): array
{
    $approved = ($proxy['blocked'] ?? true) === false;
    $commands = [];

    foreach ($events as $event) {
        $eventType = $event['type'] ?? null;

        if (!is_string($eventType) || !str_starts_with($eventType, 'item.')) {
            continue;
        }

        $item = $event['item'] ?? null;

        if (!is_array($item) || !is_string($item['type'] ?? null)) {
            $approved = false;
            continue;
        }

        if (!in_array($item['type'], ['agent_message', 'reasoning', 'command_execution', 'file_change', 'todo_list'], true)) {
            $approved = false;
        }

        if ($item['type'] === 'command_execution') {
            if (!is_string($item['id'] ?? null)) {
                $approved = false;
                continue;
            }
            if (!is_string($item['command'] ?? null)) {
                if ($eventType === 'item.completed') {
                    $approved = false;
                }
                continue;
            }
            $command = ['item_id' => $item['id'], 'sha256' => hash('sha256', $item['command']), 'bytes' => strlen($item['command'])];
            if (isset($commands[$item['id']]) && $commands[$item['id']] !== $command) {
                $approved = false;
                continue;
            }
            $commands[$item['id']] = $command;
        }
    }

    return [
        'approved' => $approved,
        'network' => 'none',
        'socket_attempt_telemetry' => null,
        'host_proxy_requests' => agentEvaluationRequireNonNegativeInteger($proxy, 'observed_request_count', 'proxy ledger'),
        'proxy_blocked' => ($proxy['blocked'] ?? true) !== false,
        'observed_commands' => array_values($commands),
    ];
}

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
function agentEvaluationControllerParseCodexEvents(string $jsonLines, int $modelTokenBudget, bool $liveUsage = false): array
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

            $parsedUsage = agentEvaluationControllerParseCodexUsage($event['usage'] ?? null, $liveUsage);

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
function agentEvaluationControllerParseCodexUsage(mixed $value, bool $liveUsage = false): ?array
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
    if ($liveUsage) {
        $knownSources[] = 'cache_write_input_tokens';
    }

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

    if ($liveUsage) {
        foreach (['cached_input_tokens' => 'input_tokens', 'cache_write_input_tokens' => 'input_tokens', 'reasoning_output_tokens' => 'output_tokens'] as $detail => $total) {
            if (!array_key_exists($detail, $value)) {
                continue;
            }
            $tokens = $value[$detail];
            if (!is_int($tokens) || $tokens < 0 || (is_int($value[$total] ?? null) && $tokens > $value[$total])) {
                return null;
            }
        }
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
