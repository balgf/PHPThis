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
// One buffered upstream response; independent of retained Codex command output.
const AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES = 4_194_304;
const AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT = 128;
const AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_KIND = 'codex-responses-local-tools-v1';
const AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_COUNT = 4;
const AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_SHA256 = '3392681cd5b82960557ffe2ce5b0ba1e223cc0f97a226432a7a43353475d6aed';
// Sequential wire bytes, never a buffer allocation or a larger per-response allowance.
const AGENT_EVALUATION_CONTROLLER_PROXY_RUN_RESPONSE_BYTES = AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT
    * AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES;

/** @param array<string, mixed>|null $spending
 * @return list<string>
 */
function agentEvaluationControllerLiveCodexArguments(string $model, string $reasoningEffort, ?array $spending = null, ?int $modelTokenBudget = null): array
{
    if (
        preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $model) !== 1
        || !in_array($reasoningEffort, ['low', 'medium', 'high', 'xhigh', 'max', 'ultra'], true)
    ) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_MODEL_INVALID');
    }
    $spending = agentEvaluationControllerProxyValidateSpending($model, $reasoningEffort, $spending);
    $effectiveBudget = $modelTokenBudget ?? ($spending === null ? 40_000 : 200_000);
    if ($effectiveBudget < 1 || ($spending === null && $effectiveBudget > 40_000)
        || ($spending !== null && $effectiveBudget > 200_000 && $effectiveBudget !== 1_000_000)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_BUDGET_INVALID');
    }
    $compactionLimit = $spending === null ? 40_001 : ($effectiveBudget === 1_000_000 ? 1_000_001 : 200_001);

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
        'model_auto_compact_token_limit=' . $compactionLimit,
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

/** @param array<string, mixed>|null $spending
 * @return array<string, mixed>
 */
function agentEvaluationControllerProxyState(string $model, string $reasoningEffort, int $tokenBudget, ?array $spending = null): array
{
    agentEvaluationControllerLiveCodexArguments($model, $reasoningEffort, $spending, $tokenBudget);
    $spending = agentEvaluationControllerProxyValidateSpending($model, $reasoningEffort, $spending);

    $state = [
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
        'transport_tools' => null,
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
 * Fixed approved standard prices below the 272k input tier; one USD is 100000000 units.
 * Each token costs its cents-per-million rate in these integer units.
 *
 * @param array<string, mixed>|null $spending
 * @return array{limit_units: int, input_cents_per_million: int, cached_cents_per_million: int, output_cents_per_million: int}|null
 */
function agentEvaluationControllerProxyValidateSpending(string $model, string $reasoningEffort, ?array $spending): ?array
{
    if ($spending === null) {
        return null;
    }
    agentEvaluationRequireExactKeys($spending, ['limit_units', 'input_cents_per_million', 'cached_cents_per_million', 'output_cents_per_million'], 'proxy spending policy');
    $limit = agentEvaluationRequirePositiveInteger($spending, 'limit_units', 'proxy spending policy');
    if ($model !== 'gpt-5.4-2026-03-05' || $reasoningEffort !== 'high' || $limit > 100_000_000
        || $spending['input_cents_per_million'] !== 250 || $spending['cached_cents_per_million'] !== 25
        || $spending['output_cents_per_million'] !== 1500) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_SPENDING_POLICY_INVALID');
    }
    return ['limit_units' => $limit, 'input_cents_per_million' => 250, 'cached_cents_per_million' => 25, 'output_cents_per_million' => 1500];
}

/**
 * Settled units conservatively price missing cached usage as uncached input.
 * Outstanding units remain reserved until an entire response passes usage validation.
 *
 * @param array<string, mixed> $state
 * @return array{policy: array{limit_units: int, input_cents_per_million: int, cached_cents_per_million: int, output_cents_per_million: int}, settled_units: int, reserved_units: int}|null
 */
function agentEvaluationControllerProxySpendingLedger(array $state): ?array
{
    $tokenBudget = agentEvaluationRequirePositiveInteger($state, 'token_budget', 'proxy spending ledger');
    if (!array_key_exists('spending', $state)) {
        if ($tokenBudget > 40_000) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_SPENDING_LEDGER_INVALID');
        }
        return null;
    }
    $spending = agentEvaluationRequireObject($state, 'spending', 'proxy spending ledger');
    agentEvaluationRequireExactKeys($spending, ['policy', 'settled_units', 'reserved_units'], 'proxy spending ledger');
    $policy = agentEvaluationControllerProxyValidateSpending(
        agentEvaluationRequireString($state, 'model', 'proxy spending ledger'),
        agentEvaluationRequireString($state, 'reasoning_effort', 'proxy spending ledger'),
        agentEvaluationRequireObject($spending, 'policy', 'proxy spending ledger'),
    );
    if ($policy === null) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_SPENDING_LEDGER_INVALID');
    }
    $settled = agentEvaluationRequireNonNegativeInteger($spending, 'settled_units', 'proxy spending ledger');
    $reserved = agentEvaluationRequireNonNegativeInteger($spending, 'reserved_units', 'proxy spending ledger');
    $input = agentEvaluationRequireNonNegativeInteger($state, 'reserved_input', 'proxy spending ledger');
    $output = agentEvaluationRequireNonNegativeInteger($state, 'reserved_output', 'proxy spending ledger');
    $settledInput = agentEvaluationRequireNonNegativeInteger($state, 'input_tokens', 'proxy spending ledger');
    $settledOutput = agentEvaluationRequireNonNegativeInteger($state, 'output_tokens', 'proxy spending ledger');
    if (($tokenBudget > 200_000 && $tokenBudget !== 1_000_000)
        || $settled > $policy['limit_units'] || $reserved > $policy['limit_units'] - $settled
        || $input > 200_000 || $output > 66_666 || $settledInput > 1_000_000 || $settledOutput > 1_000_000
        || $settledInput + $settledOutput + $input + $output > $tokenBudget
        || ($output === 0 && $input !== 0)
        || $reserved !== $input * $policy['input_cents_per_million'] + $output * $policy['output_cents_per_million']) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_SPENDING_LEDGER_INVALID');
    }
    foreach (['cached_tokens' => $settledInput, 'reasoning_tokens' => $settledOutput] as $name => $total) {
        $category = $state[$name] ?? null;
        if ($category !== null && (!is_int($category) || $category < 0 || $category > $total)) {
            throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_SPENDING_LEDGER_INVALID');
        }
    }
    return ['policy' => $policy, 'settled_units' => $settled, 'reserved_units' => $reserved];
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
        agentEvaluationControllerProxySpendingLedger($state);

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
        $transportTools = agentEvaluationControllerProxyTransportToolsIdentity($request['tools'] ?? []);
        $observedTransportTools = $state['transport_tools'] ?? null;
        if ($observedTransportTools !== null && $observedTransportTools !== $transportTools) {
            throw new RuntimeException('Proxy wire tools changed between requests.');
        }
        $state['transport_tools'] = $transportTools;
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
        $spending = agentEvaluationControllerProxySpendingLedger($state);
        $remaining = agentEvaluationRequirePositiveInteger($state, 'token_budget', 'proxy')
            - agentEvaluationRequireNonNegativeInteger($state, 'input_tokens', 'proxy')
            - agentEvaluationRequireNonNegativeInteger($state, 'output_tokens', 'proxy');

        if ($inputTokens > $remaining - 16) {
            $state['failure_reason'] = 'model_token_limit';
            throw new RuntimeException('Proxy token budget is exhausted.');
        }
        if ($spending !== null && $inputTokens > 200_000) {
            $state['failure_reason'] = 'model_input_limit';
            throw new RuntimeException('Proxy request input exceeds the fixed standard-price allowance.');
        }

        $requestedOutput = $request['max_output_tokens'] ?? $remaining;

        if (!is_int($requestedOutput) || $requestedOutput < 16) {
            throw new RuntimeException('Proxy output reservation is invalid.');
        }

        $outputTokens = min($requestedOutput, $remaining - $inputTokens);
        if ($spending !== null) {
            $moneyRemaining = $spending['policy']['limit_units'] - $spending['settled_units'];
            $inputCost = $inputTokens * $spending['policy']['input_cents_per_million'];
            $outputRate = $spending['policy']['output_cents_per_million'];
            if ($inputCost > $moneyRemaining - 16 * $outputRate) {
                $state['failure_reason'] = 'spending_limit';
                throw new RuntimeException('Proxy spending budget is exhausted.');
            }
            $outputTokens = min($outputTokens, intdiv($moneyRemaining - $inputCost, $outputRate));
            $spending['reserved_units'] = $inputCost + $outputTokens * $outputRate;
            $state['spending'] = $spending;
        }
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
    $stage = 'availability';
    $state['last_response_bytes'] = strlen($sse) <= AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES ? strlen($sse) : null;
    $state['last_response_sha256'] = $state['last_response_bytes'] === null ? null : hash('sha256', $sse);
    $state['response_rejection_stage'] = null;
    $state['response_observation'] = null;
    $state['response_event_observation'] = null;
    $state['provider_error_event_seen'] = false;
    $state['provider_error_observation'] = null;
    $event = [];
    $eventName = null;
    $dataEventOrdinal = 0;
    try {
        if (
            ($state['blocked'] ?? true) !== false
            || agentEvaluationRequireNonNegativeInteger($state, 'reserved_output', 'proxy') < 16
        ) {
            throw new RuntimeException('Proxy response is unavailable.');
        }

        $stage = 'response_bytes';
        $requestCount = agentEvaluationRequireNonNegativeInteger($state, 'request_count', 'proxy');
        $priorResponseBytes = agentEvaluationRequireNonNegativeInteger($state, 'response_bytes', 'proxy');
        if (
            $requestCount < 1
            || $requestCount > AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_LIMIT
            || strlen($sse) > AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES
            || $priorResponseBytes > ($requestCount - 1) * AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES
        ) {
            throw new RuntimeException('Proxy response byte accounting is invalid.');
        }

        // Both operands are validated above; their sum cannot exceed the derived run bound.
        $responseBytes = $priorResponseBytes + strlen($sse);
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
            $stage = 'sse_frame';

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

            $dataEventOrdinal++;
            $json = implode("\n", $data);

            if ($json === '[DONE]' && $terminalSeen) {
                continue;
            }

            $stage = 'sse_order';
            if ($terminalSeen) {
                throw new RuntimeException('Proxy SSE has data after its terminal event.');
            }

            $stage = 'sse_json';
            $event = agentEvaluationControllerProxyJsonObject($json);
            $stage = 'sse_event_identity';
            $type = agentEvaluationRequireString($event, 'type', 'proxy event');
            if ($type === 'error' && ($eventName === null || $eventName === $type)) {
                $stage = 'provider_error_event';
                $state['provider_error_event_seen'] = true;
                $state['provider_error_observation'] = agentEvaluationControllerProxyErrorObservation($event);
            }

            if (($eventName !== null && $eventName !== $type)
                || ($type !== 'keepalive' && !str_starts_with($type, 'response.'))) {
                throw new RuntimeException('Proxy SSE event identity is invalid.');
            }

            // This exact transport event has no response, output, or usage authority.
            if ($type === 'keepalive') {
                continue;
            }

            if (in_array($type, ['response.completed', 'response.incomplete', 'response.failed'], true)) {
                $terminalSeen = true;
                $terminalType = $type;
                $value = $event['response'] ?? null;
                $stage = 'terminal_response';

                if (!$value instanceof stdClass) {
                    throw new RuntimeException('Proxy terminal response is invalid.');
                }

                $response = agentEvaluationControllerProxyObjectMembers($value);
                $state['response_observation'] = agentEvaluationControllerProxyResponseObservation($response, $type, $state);
            }
        }

        $stage = 'terminal_identity';
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
        $stage = 'usage_object';

        if (!$usageValue instanceof stdClass) {
            throw new RuntimeException('Proxy response usage is missing.');
        }

        $usage = agentEvaluationControllerProxyObjectMembers($usageValue);
        $stage = 'usage_input_tokens';
        $input = agentEvaluationRequireNonNegativeInteger($usage, 'input_tokens', 'proxy usage');
        $stage = 'usage_output_tokens';
        $output = agentEvaluationRequireNonNegativeInteger($usage, 'output_tokens', 'proxy usage');
        $stage = 'usage_total_tokens';
        $total = agentEvaluationRequireNonNegativeInteger($usage, 'total_tokens', 'proxy usage');

        $stage = 'usage_reservation';
        if (
            $input !== ($state['reserved_input'] ?? null)
            || $output > agentEvaluationRequireNonNegativeInteger($state, 'reserved_output', 'proxy')
            || $total !== $input + $output
        ) {
            throw new RuntimeException('Proxy response usage exceeds or disagrees with its reservation.');
        }

        $stage = 'usage_cached_tokens';
        $cached = agentEvaluationControllerProxyDetail($usage['input_tokens_details'] ?? null, 'cached_tokens', $input);
        $stage = 'usage_reasoning_tokens';
        $reasoning = agentEvaluationControllerProxyDetail($usage['output_tokens_details'] ?? null, 'reasoning_tokens', $output);
        $spending = agentEvaluationControllerProxySpendingLedger($state);
        if ($spending !== null) {
            $stage = 'spending_settlement';
            $cachedInput = $cached ?? 0;
            $cost = ($input - $cachedInput) * $spending['policy']['input_cents_per_million']
                + $cachedInput * $spending['policy']['cached_cents_per_million']
                + $output * $spending['policy']['output_cents_per_million'];
            if ($cost > $spending['reserved_units']) {
                throw new RuntimeException('Proxy spending usage exceeds its reservation.');
            }
            $spending['settled_units'] += $cost;
            $spending['reserved_units'] = 0;
        }
        $stage = 'usage_aggregate';
        $state['input_tokens'] = agentEvaluationRequireNonNegativeInteger($state, 'input_tokens', 'proxy') + $input;
        $state['output_tokens'] = agentEvaluationRequireNonNegativeInteger($state, 'output_tokens', 'proxy') + $output;

        foreach (['cached_tokens' => $cached, 'reasoning_tokens' => $reasoning] as $name => $category) {
            $previous = $state[$name] ?? null;
            $state[$name] = is_int($previous) && $category !== null ? $previous + $category : null;
        }

        $state['reserved_input'] = 0;
        $state['reserved_output'] = 0;
        $state['request_sha256'] = null;
        if ($spending !== null) {
            $state['spending'] = $spending;
        }

        $stage = 'terminal_status';
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
        $state['response_rejection_stage'] = $stage;
        if ($stage === 'sse_event_identity') {
            $state['response_event_observation'] = agentEvaluationControllerProxyEventObservation($event, $eventName, $dataEventOrdinal);
        }
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED');
    }
}

/**
 * Called only after an identity rejection; no event payload or unknown label is retained.
 * Reasons follow the existing type check and header comparison order.
 *
 * @param array<string, mixed> $event
 * @return array{authority: string, data_event_ordinal: int|null, identity_reason: string|null, type_family: string|null, header_matches_type: bool|null, fields: array<string, array{present: bool, kind: string, bytes: int|null, sha256: string|null, over_limit: bool}>}
 */
function agentEvaluationControllerProxyEventObservation(array $event, ?string $eventName, int $dataEventOrdinal): array
{
    $typePresent = array_key_exists('type', $event);
    $type = $event['type'] ?? null;
    $reason = null;
    if (!$typePresent) {
        $reason = 'missing_type';
    } elseif (!is_string($type)) {
        $reason = 'nonstring_type';
    } elseif ($eventName !== null && $eventName !== $type) {
        $reason = 'header_type_mismatch';
    } elseif ($type === '') {
        $reason = 'empty_type';
    } elseif ($type !== 'error' && !str_starts_with($type, 'response.')) {
        $reason = 'unsupported_type_family';
    }
    $fields = [];
    foreach (['event_name' => [$eventName !== null, $eventName], 'type' => [$typePresent, $type]] as $name => [$present, $value]) {
        $string = is_string($value);
        $overLimit = $string && strlen($value) > AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES;
        $fields[$name] = [
            'present' => $present,
            'kind' => !$present ? 'missing' : ($value === null ? 'null' : ($string ? 'string' : 'other')),
            'bytes' => $string && !$overLimit ? strlen($value) : null,
            'sha256' => $string && !$overLimit ? hash('sha256', $value) : null,
            'over_limit' => $overLimit,
        ];
    }
    return [
        'authority' => 'unvalidated-provider-event-observation',
        'data_event_ordinal' => $dataEventOrdinal >= 1 && $dataEventOrdinal <= AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES ? $dataEventOrdinal : null,
        'identity_reason' => $reason,
        'type_family' => is_string($type) ? (str_starts_with($type, 'response.') ? 'response' : ($type === 'error' ? 'error' : 'other')) : null,
        'header_matches_type' => $eventName !== null && is_string($type) ? $eventName === $type : null,
        'fields' => $fields,
    ];
}

/**
 * Error diagnostics never accept a response or settle its token reservation.
 * Prefer documented top-level fields; never merge a conflicting nested envelope.
 * Unknown strings are fingerprinted within the response bound, never retained.
 *
 * @param array<string, mixed> $event
 * @return array{authority: string, envelope_source: string, top_level_fields_present: bool, nested_error_present: bool, nested_error_is_object: bool, code: string|null, type: string|null, parameter_root: string|null, fields: array<string, array{present: bool, kind: string, bytes: int|null, sha256: string|null, over_limit: bool}>}
 */
function agentEvaluationControllerProxyErrorObservation(array $event): array
{
    $topLevelFields = array_key_exists('code', $event) || array_key_exists('param', $event) || array_key_exists('message', $event);
    $nested = $event['error'] ?? null;
    $useNested = !$topLevelFields && $nested instanceof stdClass;
    $source = $useNested ? get_object_vars($nested) : $event;
    $fields = [];
    foreach (['code', 'type', 'param', 'message'] as $name) {
        $present = array_key_exists($name, $source);
        $value = $source[$name] ?? null;
        $string = is_string($value);
        $overLimit = $string && strlen($value) > AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES;
        $fields[$name] = [
            'present' => $present,
            'kind' => !$present ? 'missing' : ($value === null ? 'null' : ($string ? 'string' : 'other')),
            'bytes' => $string && !$overLimit ? strlen($value) : null,
            'sha256' => $string && !$overLimit ? hash('sha256', $value) : null,
            'over_limit' => $overLimit,
        ];
    }
    $code = $source['code'] ?? null;
    $type = $source['type'] ?? null;
    $param = $source['param'] ?? null;
    $root = null;
    if (is_string($param) && strlen($param) <= 1024
        && preg_match('/\A([a-z][a-z0-9_]*)(?:\.[A-Za-z_][A-Za-z0-9_]*|\[(?:0|[1-9][0-9]{0,8})\])*\z/D', $param, $matches) === 1
        && in_array($matches[1], [
            'model', 'input', 'instructions', 'tools', 'tool_choice', 'parallel_tool_calls',
            'reasoning', 'text', 'include', 'store', 'stream', 'max_output_tokens',
            'service_tier', 'truncation', 'metadata', 'prompt_cache_key', 'client_metadata',
        ], true)) {
        $root = $matches[1];
    }
    return [
        'authority' => 'unvalidated-provider-error-observation',
        'envelope_source' => $useNested ? 'nested-error-object' : 'top-level',
        'top_level_fields_present' => $topLevelFields,
        'nested_error_present' => array_key_exists('error', $event),
        'nested_error_is_object' => $nested instanceof stdClass,
        'code' => is_string($code) && in_array($code, [
            'invalid_type', 'invalid_value', 'unsupported_value', 'unsupported_parameter',
            'unknown_parameter', 'missing_required_parameter', 'invalid_request_error',
            'context_length_exceeded', 'model_not_found', 'rate_limit_exceeded',
            'insufficient_quota', 'invalid_api_key', 'server_error', 'internal_server_error',
            'credit_balance_exhausted',
        ], true) ? $code : null,
        'type' => is_string($type) && in_array($type, [
            'error', 'invalid_request_error', 'authentication_error', 'permission_error',
            'not_found_error', 'rate_limit_error', 'server_error', 'api_error',
            'insufficient_quota',
        ], true) ? $type : null,
        'parameter_root' => $root,
        'fields' => $fields,
    ];
}

/**
 * These structural observations are never authoritative usage or acceptance.
 * Invalid or excessive diagnostic fields become null without changing parsing.
 *
 * @param array<string, mixed> $response
 * @param array<string, mixed> $state
 * @return array{authority: string, response_id: string|null, terminal_event: string|null, status: string|null, model_matches: bool, service_tier_matches: bool, error_present: bool, usage_is_object: bool, usage: array{input_tokens: int|null, output_tokens: int|null, total_tokens: int|null, cached_tokens: int|null, reasoning_tokens: int|null}}
 */
function agentEvaluationControllerProxyResponseObservation(array $response, string $terminalType, array $state): array
{
    $id = $response['id'] ?? null;
    $status = $response['status'] ?? null;
    $usage = $response['usage'] ?? null;
    $inputDetails = $usage instanceof stdClass ? ($usage->input_tokens_details ?? null) : null;
    $outputDetails = $usage instanceof stdClass ? ($usage->output_tokens_details ?? null) : null;
    return [
        'authority' => 'unvalidated-provider-observation',
        'response_id' => is_string($id) && preg_match('/\Aresp_[A-Za-z0-9]{1,128}\z/D', $id) === 1 ? $id : null,
        'terminal_event' => in_array($terminalType, ['response.completed', 'response.incomplete', 'response.failed'], true) ? $terminalType : null,
        'status' => is_string($status) && in_array($status, ['completed', 'incomplete', 'failed', 'queued', 'in_progress', 'cancelled'], true) ? $status : null,
        'model_matches' => is_string($response['model'] ?? null) && $response['model'] === ($state['model'] ?? null),
        'service_tier_matches' => !isset($response['service_tier']) || $response['service_tier'] === 'default',
        'error_present' => isset($response['error']),
        'usage_is_object' => $usage instanceof stdClass,
        'usage' => [
            'input_tokens' => agentEvaluationControllerProxyObservedTokenCount($usage instanceof stdClass ? ($usage->input_tokens ?? null) : null),
            'output_tokens' => agentEvaluationControllerProxyObservedTokenCount($usage instanceof stdClass ? ($usage->output_tokens ?? null) : null),
            'total_tokens' => agentEvaluationControllerProxyObservedTokenCount($usage instanceof stdClass ? ($usage->total_tokens ?? null) : null),
            'cached_tokens' => agentEvaluationControllerProxyObservedTokenCount($inputDetails instanceof stdClass ? ($inputDetails->cached_tokens ?? null) : null),
            'reasoning_tokens' => agentEvaluationControllerProxyObservedTokenCount($outputDetails instanceof stdClass ? ($outputDetails->reasoning_tokens ?? null) : null),
        ],
    ];
}

function agentEvaluationControllerProxyObservedTokenCount(mixed $value): ?int
{
    return is_int($value) && $value >= 0 && $value <= 1_000_000_000 ? $value : null;
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

/** @return array{kind: string, count: int, sha256: string} */
function agentEvaluationControllerProxyTransportToolsIdentity(mixed $tools): array
{
    if (!is_array($tools) || !array_is_list($tools)) {
        throw new RuntimeException('Proxy tool list is invalid.');
    }
    if (count($tools) !== AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_COUNT) {
        throw new RuntimeException('Proxy tool count does not match its pinned Codex transport.');
    }
    $expected = [
        ['type' => 'function', 'name' => 'exec_command'],
        ['type' => 'function', 'name' => 'write_stdin'],
        ['type' => 'function', 'name' => 'request_user_input'],
        ['type' => 'custom', 'name' => 'apply_patch'],
    ];
    foreach ($tools as $index => $tool) {
        if (!$tool instanceof stdClass
            || ($tool->type ?? null) !== $expected[$index]['type']
            || ($tool->name ?? null) !== $expected[$index]['name']) {
            throw new RuntimeException('Proxy tool identity does not match its pinned Codex transport.');
        }
    }
    $canonical = json_encode(
        agentEvaluationControllerProxyCanonicalJsonValue($tools),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    $sha256 = hash('sha256', $canonical);
    if (!hash_equals(AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_SHA256, $sha256)) {
        throw new RuntimeException('Proxy tool schema does not match its pinned Codex transport.');
    }
    return ['kind' => AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_KIND,
        'count' => AGENT_EVALUATION_CONTROLLER_PROXY_TRANSPORT_TOOLS_COUNT, 'sha256' => $sha256];
}

function agentEvaluationControllerProxyCanonicalJsonValue(mixed $value): mixed
{
    if ($value instanceof stdClass) {
        $members = get_object_vars($value);
        ksort($members, SORT_STRING);
        foreach ($members as $name => $member) {
            $members[$name] = agentEvaluationControllerProxyCanonicalJsonValue($member);
        }
        return $members;
    }
    if (is_array($value)) {
        if (!array_is_list($value)) {
            throw new RuntimeException('Proxy canonical JSON arrays must be lists.');
        }
        return array_map(agentEvaluationControllerProxyCanonicalJsonValue(...), $value);
    }
    if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return $value;
    }
    throw new RuntimeException('Proxy canonical JSON value is invalid.');
}

/**
 * @return array{schema_version:int,operation:string,category:string,http_status:int|null,curl_code:int|null,response_limit_exceeded:bool}|null
 */
function agentEvaluationControllerUpstreamFailureObservation(
    string $operation,
    bool $transferSucceeded,
    mixed $httpStatus,
    mixed $curlCode,
    bool $responseLimitExceeded,
): ?array {
    if (!in_array($operation, ['input_tokens', 'responses'], true)) {
        throw new RuntimeException('Upstream failure operation is invalid.');
    }
    if ($transferSucceeded && $httpStatus === 200 && !$responseLimitExceeded) {
        return null;
    }
    $category = 'http_status';
    if ($responseLimitExceeded) {
        $category = 'response_limit';
    } elseif (!$transferSucceeded) {
        $category = 'curl';
    }
    return [
        'schema_version' => 1,
        'operation' => $operation,
        'category' => $category,
        'http_status' => is_int($httpStatus) && $httpStatus >= 100 && $httpStatus <= 599 ? $httpStatus : null,
        'curl_code' => is_int($curlCode) && $curlCode >= 0 && $curlCode <= 999 ? $curlCode : null,
        'response_limit_exceeded' => $responseLimitExceeded,
    ];
}

/** @param array<string,mixed> $generation */
function agentEvaluationControllerValidateUpstreamFailureEvidence(array $generation): void
{
    if (!array_key_exists('upstream_failure', $generation) || $generation['upstream_failure'] === null) {
        return;
    }
    $observation = agentEvaluationRequireObject($generation, 'upstream_failure', 'generation process');
    agentEvaluationRequireExactKeys($observation, [
        'schema_version', 'operation', 'category', 'http_status', 'curl_code', 'response_limit_exceeded',
    ], 'upstream failure observation');
    $operation = agentEvaluationRequireString($observation, 'operation', 'upstream failure observation');
    $category = agentEvaluationRequireString($observation, 'category', 'upstream failure observation');
    $limited = agentEvaluationRequireBoolean($observation, 'response_limit_exceeded', 'upstream failure observation');
    $status = $observation['http_status'];
    $curlCode = $observation['curl_code'];
    if (($observation['schema_version'] ?? null) !== 1
        || !in_array($operation, ['input_tokens', 'responses'], true)
        || !in_array($category, ['response_limit', 'curl', 'http_status'], true)
        || ($status !== null && (!is_int($status) || $status < 100 || $status > 599))
        || ($curlCode !== null && (!is_int($curlCode) || $curlCode < 0 || $curlCode > 999))
        || $limited !== ($category === 'response_limit')
        || ($category === 'http_status' && $status === 200)
    ) {
        throw new RuntimeException('Upstream failure observation is invalid.');
    }
    if (($generation['failure_code'] ?? null) !== 'AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_FAILED'
        || agentEvaluationRequireString($generation, 'termination_reason', 'generation process') === 'completed'
    ) {
        throw new RuntimeException('Upstream failure observation requires its failed generation operation.');
    }
}

/**
 * @param array<string, mixed> $resources
 * @param array<string, mixed> $profile
 * @param array<string, mixed>|null $spending
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
    ?array $spending = null,
): array {
    $budgetValues = agentEvaluationRequireObject($profile, 'budgets', 'live Codex profile');
    $budgets = [
        'model_tokens' => agentEvaluationRequirePositiveInteger($budgetValues, 'model_tokens', 'live Codex budget'),
        'wall_seconds' => agentEvaluationRequirePositiveInteger($budgetValues, 'wall_seconds', 'live Codex budget'),
        'repair_turns' => agentEvaluationRequireNonNegativeInteger($budgetValues, 'repair_turns', 'live Codex budget'),
        'command_output_bytes' => agentEvaluationRequirePositiveInteger($budgetValues, 'command_output_bytes', 'live Codex budget'),
    ];
    agentEvaluationControllerValidateBudgets($budgets);
    if ($spending !== null) {
        $model = agentEvaluationRequireObject($profile, 'model', 'live Codex profile');
        $settings = agentEvaluationValueObject($model['settings'] ?? null, 'live Codex settings');
        agentEvaluationControllerProxyState(agentEvaluationRequireString($model, 'id', 'live Codex model'),
            agentEvaluationRequireString($settings, 'reasoning_effort', 'live Codex settings'), $budgets['model_tokens'], $spending);
    }
    agentEvaluationControllerValidateFutureIsolationProfile(
        agentEvaluationRequireObject($profile, 'isolation', 'live Codex profile'),
        $budgets,
        'generation',
    );

    if ($prompt === '' || strlen($prompt) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES || str_contains($prompt, "\0")) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_PROMPT_INVALID');
    }

    $process = agentEvaluationControllerOciRunGeneration($resources, $prompt, $profile, $credential, $spending);
    agentEvaluationControllerValidateUpstreamFailureEvidence($process);
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
    } elseif ($termination === 'process_failed' && ($proxy['failure_reason'] ?? null) === 'model_input_limit') {
        $termination = 'model_input_limit';
    } elseif ($termination === 'process_failed' && ($proxy['failure_reason'] ?? null) === 'spending_limit') {
        $termination = 'spending_limit';
    }

    if ($termination === 'completed') {
        if (($cleanup['container_stopped'] ?? false) !== true || ($cleanup['oom_killed'] ?? true) !== false) {
            $termination = 'cleanup_failed';
        } elseif (($proxy['failure_reason'] ?? null) === 'model_token_limit') {
            $termination = 'model_token_limit';
        } elseif (($proxy['failure_reason'] ?? null) === 'model_input_limit') {
            $termination = 'model_input_limit';
        } elseif (($proxy['failure_reason'] ?? null) === 'spending_limit') {
            $termination = 'spending_limit';
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
            'upstream_failure' => $process['upstream_failure'] ?? null,
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
    ?string $credentialBroker = null,
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
    $expectedBroker = $credentialBroker ?? AGENT_EVALUATION_CONTROLLER_FUTURE_CREDENTIAL_BROKER;

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
            ? $expectedBroker
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
