<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_PHASES = [
    'prepare',
    'generate',
    'freeze',
    'score',
    'validate',
    'retain',
    'cleanup',
];
const AGENT_EVALUATION_CONTROLLER_EVIDENCE_VERSION = 1;

/** @return array{model_tokens:int,wall_seconds:int,repair_turns:int,command_output_bytes:int} */
function agentEvaluationControllerCalibrationBudgets(int $revision = 1): array
{
    if (!in_array($revision, [1, 2], true)) {
        throw new RuntimeException('Calibration revision must be one or two.');
    }
    return ['model_tokens' => $revision === 1 ? 200_000 : 1_000_000, 'wall_seconds' => 1_200, 'repair_turns' => 0, 'command_output_bytes' => 4_194_304];
}

/** @return array{limit_units:int,input_cents_per_million:int,cached_cents_per_million:int,output_cents_per_million:int} */
function agentEvaluationControllerCalibrationSpending(): array
{
    return ['limit_units' => 100_000_000, 'input_cents_per_million' => 250,
        'cached_cents_per_million' => 25, 'output_cents_per_million' => 1500];
}

/** @return array{limit_units:int,input_cents_per_million:int,cached_cents_per_million:int,output_cents_per_million:int} */
function agentEvaluationControllerExplanationSpending(): array
{
    return ['limit_units' => 60_000_000, 'input_cents_per_million' => 250,
        'cached_cents_per_million' => 25, 'output_cents_per_million' => 1500];
}

function agentEvaluationControllerCalibrationPrompt(int $revision = 1): string
{
    agentEvaluationControllerCalibrationBudgets($revision);
    if ($revision === 2) {
        return "Calibration working allowance: you have 1,000,000 cumulative input plus output tokens across all model requests, "
            . "at most 200,000 counted input tokens per request, 1,200 seconds, and a host-enforced API token-charge ceiling of USD 1.00 for this attempt. "
            . "Repeated context, including cached input, counts toward the cumulative allowance. "
            . "Read all authority required by AGENTS.md and the task, then work from API.md, the existing handler, and the nearest public tests. "
            . "For application discovery, use targeted commands such as `rg --files src tests evaluation .ai` and `rg -n '<symbol>' src tests evaluation API.md .ai`. "
            . "Do not recursively inventory or search /candidate, the whole vendor tree, or all framework documentation. "
            . "For PHPThis, read the required installed consumer contract and knowledge map, follow their exact task route, and open only the named guide or source files needed for this change. "
            . "Search a specific installed file or directory only when the routed authority or unresolved task fact requires it; preserve every mandatory authority read. "
            . "Batch independent focused reads, bound output, and avoid repeated reads unless a specific missing fact or change requires them. "
            . "Once the contract and relevant code are clear, implement, run composer check promptly, and use its concrete diagnostics for focused repairs within this allowance. "
            . "All task requirements, protected files, edit limits, and mandatory checks remain in force. "
            . "The host may refuse a request before dispatch if its input exceeds the per-request cap or either remaining allowance cannot cover its input and minimum output.\n";
    }
    return "Calibration working allowance: you have 200,000 cumulative input plus output tokens across all model requests, "
        . "1,200 seconds, and a host-enforced API token-charge ceiling of USD 1.00 for this attempt. "
        . "Repeated context, including cached input, counts toward the token allowance. "
        . "Read the required authority and task-specific source, then implement and run the public checks within that allowance. "
        . "Use targeted reads and concise command output; avoid redundant broad inventories of dependencies. "
        . "All task requirements, protected files, edit limits, and mandatory authority remain in force. "
        . "The host may refuse the next request before dispatch if either remaining allowance cannot cover its input and minimum output.\n";
}

/** @return array<string,mixed> */
function agentEvaluationControllerCalibrationPolicy(int $revision = 1): array
{
    return ['kind' => 'calibration-v' . $revision, 'comparative_claims' => false,
        'budgets' => agentEvaluationControllerCalibrationBudgets($revision), 'spending' => agentEvaluationControllerCalibrationSpending(),
        'prompt_sha256' => hash('sha256', agentEvaluationControllerCalibrationPrompt($revision)),
        ...($revision === 2 ? ['request_input_tokens' => 200_000] : [])];
}

/** @param array<string,mixed> $policy */
function agentEvaluationControllerCalibrationRevision(array $policy): int
{
    return match ($policy['kind'] ?? null) {
        'calibration-v1' => 1,
        'calibration-v2' => 2,
        default => throw new RuntimeException('Calibration policy kind is not admitted.'),
    };
}

/** @param array<string,mixed> $attempt */
function agentEvaluationControllerCalibrationAttemptRevision(array $attempt): int
{
    return match ($attempt['kind'] ?? null) {
        'calibration-attempt-v1' => 1,
        'calibration-attempt-v2' => 2,
        default => throw new RuntimeException('Calibration attempt kind is not admitted.'),
    };
}

/** @param array<string,mixed> $policy */
function agentEvaluationControllerValidateCalibrationPolicy(array $policy): void
{
    $revision = agentEvaluationControllerCalibrationRevision($policy);
    agentEvaluationRequireExactKeys($policy, ['kind', 'comparative_claims', 'budgets', 'spending', 'prompt_sha256',
        ...($revision === 2 ? ['request_input_tokens'] : [])], 'calibration policy');
    if ($policy['comparative_claims'] !== false || ($revision === 2 && $policy['request_input_tokens'] !== 200_000)
        || $policy['prompt_sha256'] !== hash('sha256', agentEvaluationControllerCalibrationPrompt($revision))) {
        throw new RuntimeException('Calibration must bind its fixed separate purpose and explicit budget instructions.');
    }
    agentEvaluationValidateRunBudgets(agentEvaluationRequireObject($policy, 'budgets', 'calibration policy'), agentEvaluationControllerCalibrationBudgets($revision));
    $spending = agentEvaluationRequireObject($policy, 'spending', 'calibration policy');
    $expected = agentEvaluationControllerCalibrationSpending();
    agentEvaluationRequireExactKeys($spending, array_keys($expected), 'calibration spending');
    foreach ($expected as $name => $value) {
        if ($spending[$name] !== $value) {
            throw new RuntimeException('Calibration must enforce its exact one-dollar token-charge ceiling and reviewed prices.');
        }
    }
}

/**
 * Source task identities remain unchanged; this record cannot validate as a v2 comparison attempt.
 * @param array<string,mixed> $configuration
 * @param array{slot:int,round:int,task_id:string,condition:string} $slot
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function agentEvaluationControllerPlannedCalibrationAttempt(array $configuration, array $slot, array $input): array
{
    $policy = agentEvaluationRequireObject($configuration, 'calibration', 'calibration plan');
    agentEvaluationControllerValidateCalibrationPolicy($policy);
    $revision = agentEvaluationControllerCalibrationRevision($policy);
    $attempt = agentEvaluationControllerPlannedComparisonAttempt($configuration, $slot, $input);
    unset($attempt['protocol_sha256']);
    return [...$attempt, 'schema_version' => 1, 'kind' => 'calibration-attempt-v' . $revision, 'comparative_claims' => false,
        'source_protocol_sha256' => $configuration['protocol_sha256'],
        'calibration_sha256' => hash('sha256', agentEvaluationJson(agentEvaluationControllerCalibrationPolicy($revision)))];
}

/** @param array<string,mixed> $attempt */
function agentEvaluationControllerValidateCalibrationAttemptState(array $attempt): void
{
    $revision = agentEvaluationControllerCalibrationAttemptRevision($attempt);
    if (($attempt['schema_version'] ?? null) !== 1
        || ($attempt['comparative_claims'] ?? null) !== false
        || ($attempt['calibration_sha256'] ?? null) !== hash('sha256', agentEvaluationJson(agentEvaluationControllerCalibrationPolicy($revision)))) {
        throw new RuntimeException('Calibration attempt must bind its distinct fixed policy and record kind.');
    }
    $status = agentEvaluationRequireString($attempt, 'status', 'calibration attempt');
    $phase = agentEvaluationRequireString($attempt, 'phase', 'calibration attempt');
    $termination = agentEvaluationRequireString($attempt, 'termination_reason', 'calibration attempt');
    if (!in_array($status, ['complete', 'failed', 'not_run'], true)
        || !in_array($phase, ['planned', 'prepare', 'generate', 'freeze', 'score', 'validate', 'retain', 'cleanup', 'finished'], true)
        || preg_match('/\A[a-zA-Z0-9_.-]{1,128}\z/D', $termination) !== 1
        || ($status === 'complete' && ($phase !== 'finished' || $termination !== 'completed'))
        || ($status === 'failed' && (in_array($phase, ['planned', 'finished'], true) || $termination === 'completed'))
        || ($status === 'not_run' && ($phase !== 'planned' || $termination !== 'calibration_aborted'))
        || ($attempt['repair_turns'] ?? null) !== 0) {
        throw new RuntimeException('Calibration final state, termination, and repair allowance are inconsistent.');
    }
    $usage = agentEvaluationRequireObject($attempt, 'usage', 'calibration attempt');
    agentEvaluationValidateUsage($usage, agentEvaluationControllerCalibrationBudgets($revision)['model_tokens']);
    foreach (['cached_tokens' => 'input_tokens', 'reasoning_tokens' => 'output_tokens'] as $part => $total) {
        if (is_int($usage[$part]) && is_int($usage[$total]) && $usage[$part] > $usage[$total]) {
            throw new RuntimeException('Calibration usage subsets cannot exceed observed totals.');
        }
    }
    if (!array_key_exists('elapsed_milliseconds', $attempt)) {
        throw new RuntimeException('Calibration elapsed time must be explicit.');
    }
    $elapsed = $attempt['elapsed_milliseconds'];
    if ($elapsed !== null && (!is_int($elapsed) || $elapsed < 0 || $elapsed > 86_400_000)) {
        throw new RuntimeException('Calibration elapsed time must be a bounded integer or null.');
    }
    $unknown = agentEvaluationRequireObject($attempt, 'unknown_metrics', 'calibration attempt');
    $expectedUnknown = agentEvaluationControllerComparisonUnknownMetrics($usage, $elapsed);
    ksort($unknown, SORT_STRING);
    ksort($expectedUnknown, SORT_STRING);
    if ($unknown !== $expectedUnknown) {
        throw new RuntimeException('Calibration unknown metrics must match the retained observations.');
    }
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'calibration attempt');
    if (count($artifacts) > 64) {
        throw new RuntimeException('Calibration artifact inventory exceeds its fixed bound.');
    }
    foreach ($artifacts as $name => $value) {
        agentEvaluationRequireRelativePath($name, 'calibration artifact');
        if (str_contains($name, '/')) {
            throw new RuntimeException('Calibration artifacts must use flat retained paths.');
        }
        $descriptor = agentEvaluationValueObject($value, 'calibration artifact');
        agentEvaluationRequireExactKeys($descriptor, ['bytes', 'sha256'], 'calibration artifact');
        if (agentEvaluationRequireNonNegativeInteger($descriptor, 'bytes', 'calibration artifact') > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
            throw new RuntimeException('Calibration artifact exceeds its byte bound.');
        }
        agentEvaluationRequireHash(agentEvaluationRequireString($descriptor, 'sha256', 'calibration artifact'), 'calibration artifact');
    }
    if ($status === 'not_run' && ($artifacts !== [] || $elapsed !== null
        || $usage !== ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null])) {
        throw new RuntimeException('Unrun calibration must have no artifacts or observed measurements.');
    }
}

/**
 * Validate calibration-only provenance and money claims without executing any process.
 * Failed preparation may retain only a prefix of its evidence; complete or generated
 * attempts require the source, effective prompt, profile, and policy bindings.
 *
 * @param array<string,mixed> $attempt
 * @return array{policy:array{limit_units:int,input_cents_per_million:int,cached_cents_per_million:int,output_cents_per_million:int},settled_units:int,reserved_units:int}|null
 */
function agentEvaluationControllerValidateCalibrationEvidence(array $attempt, string $evidenceRoot): ?array
{
    $revision = agentEvaluationControllerCalibrationAttemptRevision($attempt);
    $modelTokens = agentEvaluationControllerCalibrationBudgets($revision)['model_tokens'];
    agentEvaluationControllerValidateComparisonAttemptMeasurements($attempt, $evidenceRoot, $modelTokens, $revision);
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'calibration evidence');
    $complete = ($attempt['status'] ?? null) === 'complete';
    if ($complete || isset($artifacts['proxy.json'])) {
        foreach (['profile.json', 'calibration.json', 'source-prompt.md', 'prompt.md', 'task.json', 'workspace-policy.json'] as $name) {
            if (!isset($artifacts[$name])) {
                throw new RuntimeException('Generated calibration requires every fixed preparation binding.');
            }
        }
    }
    if ($complete && !isset($artifacts['evidence-manifest.json'])) {
        throw new RuntimeException('Completed calibration requires its retained evidence manifest.');
    }
    if (isset($artifacts['calibration.json'])) {
        agentEvaluationControllerValidateCalibrationPolicy(agentEvaluationJsonFile($evidenceRoot . '/calibration.json'));
        agentEvaluationRequireFileHash($evidenceRoot . '/calibration.json',
            agentEvaluationRequireString($attempt, 'calibration_sha256', 'calibration evidence'), 'calibration policy evidence');
    }
    if (isset($artifacts['task.json'])) {
        agentEvaluationRequireFileHash($evidenceRoot . '/task.json',
            agentEvaluationRequireString($attempt, 'task_manifest_sha256', 'calibration evidence'), 'calibration source task');
        $task = agentEvaluationJsonFile($evidenceRoot . '/task.json');
        if (isset($artifacts['source-prompt.md'])) {
            $prompt = agentEvaluationRequireObject($task, 'prompt', 'calibration source task');
            agentEvaluationRequireFileHash($evidenceRoot . '/source-prompt.md',
                agentEvaluationRequireString($prompt, 'sha256', 'calibration source prompt'), 'calibration source prompt');
        }
    }
    if (isset($artifacts['profile.json'])) {
        agentEvaluationRequireFileHash($evidenceRoot . '/profile.json',
            agentEvaluationRequireString($attempt, 'profile_sha256', 'calibration evidence'), 'calibration effective profile');
        $profile = agentEvaluationJsonFile($evidenceRoot . '/profile.json');
        $model = agentEvaluationRequireObject($profile, 'model', 'calibration profile');
        $settings = agentEvaluationValueObject($model['settings'] ?? null, 'calibration profile settings');
        agentEvaluationControllerProxyValidateSpending(agentEvaluationRequireString($model, 'id', 'calibration model'),
            agentEvaluationRequireString($settings, 'reasoning_effort', 'calibration model'), agentEvaluationControllerCalibrationSpending());
        if (($model['provider'] ?? null) !== 'openai' || ($profile['condition'] ?? null) !== ($attempt['condition'] ?? null)) {
            throw new RuntimeException('Calibration profile must bind the admitted provider and condition.');
        }
        agentEvaluationValidateRunBudgets(agentEvaluationRequireObject($profile, 'budgets', 'calibration profile'), agentEvaluationControllerCalibrationBudgets($revision));
    }
    if (isset($artifacts['evidence-manifest.json'])) {
        $manifest = agentEvaluationJsonFile($evidenceRoot . '/evidence-manifest.json');
        if (($manifest['schema_version'] ?? null) !== AGENT_EVALUATION_CONTROLLER_EVIDENCE_VERSION
            || ($manifest['controller_version'] ?? null) !== AGENT_EVALUATION_CONTROLLER_VERSION
            || ($manifest['run_id'] ?? null) !== ($attempt['run_id'] ?? null) || ($manifest['task_id'] ?? null) !== ($attempt['task_id'] ?? null)
            || ($manifest['task_revision'] ?? null) !== ($attempt['task_revision'] ?? null) || ($manifest['condition'] ?? null) !== ($attempt['condition'] ?? null)
            || ($manifest['synthetic'] ?? null) !== false || ($manifest['comparative_claims'] ?? null) !== false
            || ($manifest['comparison_execution'] ?? null) !== false || ($manifest['calibration_execution'] ?? null) !== true
            || ($manifest['expected_phase_order'] ?? null) !== AGENT_EVALUATION_CONTROLLER_PHASES
            || ($complete && ($manifest['observed_phases'] ?? null) !== AGENT_EVALUATION_CONTROLLER_PHASES)) {
            throw new RuntimeException('Calibration evidence manifest must retain its exact separate lifecycle identity.');
        }
        $listed = agentEvaluationRequireObject($manifest, 'artifacts', 'calibration evidence manifest');
        $expected = $artifacts;
        unset($expected['evidence-manifest.json']);
        agentEvaluationRequireExactKeys($listed, array_keys($expected), 'calibration manifest artifacts');
        foreach ($expected as $name => $value) {
            $left = agentEvaluationValueObject($value, 'calibration artifact');
            $right = agentEvaluationValueObject($listed[$name], 'calibration manifest artifact');
            agentEvaluationRequireExactKeys($right, ['bytes', 'sha256'], 'calibration manifest artifact');
            if ($left['bytes'] !== $right['bytes'] || $left['sha256'] !== $right['sha256']) {
                throw new RuntimeException('Calibration manifest and attempt artifact identities disagree.');
            }
        }
    }
    if (!isset($artifacts['proxy.json'])) {
        return null;
    }
    $proxy = agentEvaluationJsonFile($evidenceRoot . '/proxy.json');
    $ledger = agentEvaluationRequireObject($proxy, 'ledger', 'calibration money evidence');
    $spending = agentEvaluationControllerProxySpendingLedger($ledger);
    if ($spending === null || $spending['policy'] !== agentEvaluationControllerCalibrationSpending()
        || ($ledger['model'] ?? null) !== 'gpt-5.4-2026-03-05' || ($ledger['reasoning_effort'] ?? null) !== 'high'
        || ($ledger['token_budget'] ?? null) !== $modelTokens || ($proxy['synthetic_upstream'] ?? null) !== false
        || ($proxy['upstream_origin'] ?? null) !== 'https://api.openai.com') {
        throw new RuntimeException('Calibration money evidence must bind the exact approved policy, model, and token allowance.');
    }
    $input = agentEvaluationRequireNonNegativeInteger($ledger, 'input_tokens', 'calibration money evidence');
    $output = agentEvaluationRequireNonNegativeInteger($ledger, 'output_tokens', 'calibration money evidence');
    $reservedInput = agentEvaluationRequireNonNegativeInteger($ledger, 'reserved_input', 'calibration money evidence');
    $reservedOutput = agentEvaluationRequireNonNegativeInteger($ledger, 'reserved_output', 'calibration money evidence');
    $cached = $ledger['cached_tokens'] ?? null;
    if ($input > $modelTokens || $output > $modelTokens || $reservedInput > 200_000
        || $input + $output + $reservedInput + $reservedOutput > $modelTokens
        || ($cached !== null && (!is_int($cached) || $cached < 0 || $cached > $input))) {
        throw new RuntimeException('Calibration monetary totals must use bounded validated token subsets.');
    }
    $uncachedCost = $input * 250 + $output * 1500;
    $discount = $uncachedCost - $spending['settled_units'];
    if ($discount < 0 || $discount > $input * 225 || $discount % 225 !== 0
        || (is_int($cached) && $discount !== $cached * 225)) {
        throw new RuntimeException('Calibration settled units disagree with the retained token totals.');
    }
    $requestHash = $ledger['request_sha256'] ?? null;
    if (!array_key_exists('request_sha256', $ledger) || ($requestHash !== null
        && (!is_string($requestHash) || preg_match('/\A[a-f0-9]{64}\z/D', $requestHash) !== 1))) {
        throw new RuntimeException('Calibration pending request identity is invalid.');
    }
    return $spending;
}

/** @param array<string,mixed> $campaign */
function agentEvaluationControllerRunCalibration(string $root, array $campaign): void
{
    $configuration = agentEvaluationRequireObject($campaign, 'configuration', 'calibration');
    $policy = agentEvaluationRequireObject($configuration, 'calibration', 'calibration');
    agentEvaluationControllerValidateCalibrationPolicy($policy);
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'calibration');
    if ($approval['runs'] !== 6 || $approval['spending_ceiling_usd'] !== '6.00'
        || str_contains(strtolower(agentEvaluationRequireString($approval, 'reference', 'calibration')), 'pending')) {
        throw new RuntimeException('Calibration execution requires approval of exactly six runs and six dollars.');
    }
    $credential = \getenv('OPENAI_API_KEY');
    if (!is_string($credential) || $credential === '' || strlen($credential) > 4096 || preg_match('/[\x00-\x20\x7F]/', $credential) === 1) {
        throw new RuntimeException('Calibration requires the host-only OPENAI_API_KEY.');
    }
    $parent = dirname($root) . '/agent-evaluation-calibrations';
    if (!file_exists($parent) && !mkdir($parent, 0700)) {
        throw new RuntimeException('Unable to create the calibration evidence parent.');
    }
    agentEvaluationControllerExistingRoot($parent, 'calibration evidence parent');
    $id = agentEvaluationRequireString($configuration, 'campaign_id', 'calibration');
    $directory = agentEvaluationControllerFreshAbsoluteTarget($parent . '/' . $id, 'calibration evidence');
    if (!mkdir($directory, 0700) || !mkdir($directory . '/attempts', 0700) || !mkdir($directory . '/runs', 0700)) {
        throw new RuntimeException('Unable to create fresh calibration directories.');
    }
    agentEvaluationControllerWriteArtifact($directory, 'configuration.json', agentEvaluationRequireString($campaign, 'bytes', 'calibration'));
    agentEvaluationControllerWriteArtifact($directory, 'schedule.json', agentEvaluationJson($campaign['schedule']));
    agentEvaluationControllerRetainComparisonImplementation($root, $directory,
        agentEvaluationRequireString($configuration, 'implementation_sha256', 'calibration'));
    $attempts = agentEvaluationRequireList($campaign, 'attempts', 'calibration');
    $inputs = agentEvaluationRequireObject($campaign, 'inputs', 'calibration');
    if (count($attempts) !== 6) {
        throw new RuntimeException('Calibration must retain exactly six predeclared attempts.');
    }
    foreach ($attempts as $value) {
        $attempt = agentEvaluationValueObject($value, 'calibration attempt');
        agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.planned.json', agentEvaluationRequireInteger($attempt, 'slot', 'calibration')),
            agentEvaluationJson($attempt));
    }
    $abort = false;
    foreach ($attempts as $value) {
        $attempt = agentEvaluationValueObject($value, 'calibration attempt');
        $slot = agentEvaluationRequireInteger($attempt, 'slot', 'calibration');
        $runId = agentEvaluationRequireString($attempt, 'run_id', 'calibration');
        $inputKey = agentEvaluationRequireString($attempt, 'task_id', 'calibration attempt') . ':'
            . agentEvaluationRequireString($attempt, 'condition', 'calibration attempt');
        $input = agentEvaluationValueObject($inputs[$inputKey], 'calibration input');
        $evidenceRoot = $directory . '/runs/' . $runId . '/evidence';
        if ($abort) {
            $attempt['status'] = 'not_run';
            $attempt['termination_reason'] = 'calibration_aborted';
        } else {
            $attempt['status'] = 'running';
            $attempt['phase'] = 'prepare';
            agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.started.json', $slot), agentEvaluationJson($attempt));
            try {
                $execution = agentEvaluationRequireObject($input, 'execution', 'calibration input');
                $outcome = agentEvaluationControllerExecuteControlled($root,
                    agentEvaluationRequireString($execution, 'prepared_dependencies', 'calibration execution'),
                    $directory . '/runs/' . $runId, ['run_id' => $runId, 'task_id' => $attempt['task_id']],
                    agentEvaluationRequireObject($execution, 'profile', 'calibration execution'), null, $execution, $credential,
                    ['task' => $input['task'], 'condition' => $input['condition'], 'holdout' => $input['holdout']], $policy);
                foreach (['status', 'phase', 'termination_reason'] as $name) {
                    $attempt[$name] = $outcome[$name];
                }
                $attempt['usage'] = $outcome['generation_usage'];
                $attempt['elapsed_milliseconds'] = $outcome['generation_elapsed_milliseconds'];
            } catch (Throwable) {
                $attempt['status'] = 'failed';
                $attempt['phase'] = 'prepare';
                $attempt['termination_reason'] = 'calibration_controller_failed';
                $abort = true;
            }
            if (is_dir($evidenceRoot) && !is_link($evidenceRoot)) {
                $attempt['artifacts'] = agentEvaluationControllerComparisonArtifacts($evidenceRoot);
            }
            $abort = $abort || agentEvaluationControllerCalibrationMustStop($attempt, $evidenceRoot);
        }
        $attempt['unknown_metrics'] = agentEvaluationControllerComparisonUnknownMetrics(
            agentEvaluationRequireObject($attempt, 'usage', 'calibration usage'), $attempt['elapsed_milliseconds']);
        agentEvaluationControllerValidateCalibrationAttemptState($attempt);
        $path = agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.json', $slot), agentEvaluationJson($attempt));
        $score = agentEvaluationControllerCalibrationScore($attempt, agentEvaluationFileHash($path, 'calibration attempt'),
            agentEvaluationRequireObject($input, 'holdout', 'calibration'), $evidenceRoot);
        agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.score.json', $slot), agentEvaluationJson($score));
        fwrite(STDOUT, agentEvaluationJson(['kind' => 'calibration-progress', 'slot' => $slot,
            'status' => $attempt['status'], 'automated_status' => $score['automated_status']]));
    }
}

/** @param array<string,mixed> $attempt */
function agentEvaluationControllerCalibrationMustStop(array $attempt, string $evidenceRoot): bool
{
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'calibration attempt');
    if (!isset($artifacts['proxy.json'], $artifacts['cleanup.json'], $artifacts['oci-cleanup.json'])) {
        return true;
    }
    $spending = agentEvaluationControllerValidateCalibrationEvidence($attempt, $evidenceRoot);
    $cleanup = agentEvaluationJsonFile($evidenceRoot . '/cleanup.json');
    $oci = agentEvaluationJsonFile($evidenceRoot . '/oci-cleanup.json');
    $proxy = agentEvaluationJsonFile($evidenceRoot . '/proxy.json');
    $ledger = agentEvaluationRequireObject($proxy, 'ledger', 'calibration proxy');
    if ($spending === null) {
        return true;
    }
    if (($cleanup['status'] ?? null) !== 'pass' || ($oci['status'] ?? null) !== 'pass' || ($oci['verified'] ?? null) !== true
        || ($oci['containers_remaining'] ?? null) !== 0 || ($oci['volumes_remaining'] ?? null) !== 0
        || ($ledger['reserved_input'] ?? null) !== 0 || ($ledger['reserved_output'] ?? null) !== 0
        || $spending['reserved_units'] !== 0 || !array_key_exists('request_sha256', $ledger)) {
        return true;
    }
    $quota = in_array($attempt['termination_reason'] ?? null, ['model_token_limit', 'model_input_limit', 'spending_limit'], true)
        && $attempt['termination_reason'] === ($ledger['failure_reason'] ?? null);
    if ($ledger['request_sha256'] !== null && !$quota) {
        return true;
    }
    return !($attempt['status'] === 'complete' || $attempt['phase'] === 'freeze'
        || ($attempt['phase'] === 'generate' && ($quota
            || in_array($attempt['termination_reason'], ['wall_time_limit', 'output_limit', 'memory_limit'], true))));
}

/**
 * @param array<string,mixed> $attempt
 * @param array<string,mixed> $holdout
 * @return array<string,mixed>
 */
function agentEvaluationControllerCalibrationScore(array $attempt, string $hash, array $holdout, string $evidenceRoot): array
{
    agentEvaluationControllerValidateCalibrationAttemptState($attempt);
    agentEvaluationControllerValidateCalibrationEvidence($attempt, $evidenceRoot);
    $revision = agentEvaluationControllerCalibrationAttemptRevision($attempt);
    return [...agentEvaluationControllerComparisonScoreFromEvidence($attempt, $hash, $holdout, $evidenceRoot,
            agentEvaluationControllerCalibrationBudgets($revision)['model_tokens'], $revision),
        'schema_version' => 1, 'kind' => 'calibration-score-v' . agentEvaluationControllerCalibrationAttemptRevision($attempt), 'comparative_claims' => false];
}

/**
 * @param array<string,mixed> $campaign
 * @return array<string,mixed>
 */
function agentEvaluationControllerCalibrationReport(string $root, array $campaign): array
{
    $configuration = agentEvaluationRequireObject($campaign, 'configuration', 'calibration report');
    $id = agentEvaluationRequireString($configuration, 'campaign_id', 'calibration report');
    if (preg_match('/\A[a-f0-9]{32}\z/D', $id) !== 1) {
        throw new RuntimeException('Calibration reporting requires one fixed campaign identity.');
    }
    $directory = agentEvaluationControllerExistingRoot(dirname($root) . '/agent-evaluation-calibrations/' . $id, 'calibration report');
    $attemptRoot = agentEvaluationControllerExistingRoot($directory . '/attempts', 'calibration report attempt root');
    $runRoot = agentEvaluationControllerExistingRoot($directory . '/runs', 'calibration report run root');
    $configurationPath = agentEvaluationControllerValidateRetainedArtifact($directory, 'configuration.json', AGENT_EVALUATION_MAX_JSON_BYTES);
    $configurationHash = agentEvaluationRequireHash(agentEvaluationRequireString($campaign, 'sha256', 'calibration'), 'calibration report configuration');
    if (hash('sha256', agentEvaluationRequireString($campaign, 'bytes', 'calibration report')) !== $configurationHash) {
        throw new RuntimeException('Calibration report configuration bytes and identity disagree.');
    }
    agentEvaluationRequireFileHash($configurationPath, $configurationHash, 'calibration retained configuration');
    if (agentEvaluationControllerComparisonCodeHash($directory . '/implementation') !== $configuration['implementation_sha256']) {
        throw new RuntimeException('Calibration implementation snapshot changed.');
    }
    $schedulePath = agentEvaluationControllerValidateRetainedArtifact($directory, 'schedule.json', AGENT_EVALUATION_MAX_JSON_BYTES);
    agentEvaluationRequireFileHash($schedulePath, hash('sha256', agentEvaluationJson($campaign['schedule'])), 'calibration retained schedule');
    $expectedPlans = agentEvaluationRequireList($campaign, 'attempts', 'calibration report');
    if (count($expectedPlans) !== 6) {
        throw new RuntimeException('Calibration reporting cannot omit a planned attempt.');
    }
    $ledgerEntries = scandir($attemptRoot);
    if ($ledgerEntries === false || count($ledgerEntries) > 26) {
        throw new RuntimeException('Calibration attempt ledger exceeds its fixed file inventory.');
    }
    $ledgerPaths = [$configurationPath, $schedulePath];
    foreach ($ledgerEntries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (preg_match('/\A0[1-6](?:\.planned|\.started|\.score)?\.json\z/D', $entry) !== 1) {
            throw new RuntimeException('Calibration attempt ledger contains an unplanned entry.');
        }
        $ledgerPaths[] = agentEvaluationControllerValidateRetainedArtifact($attemptRoot, $entry, AGENT_EVALUATION_MAX_JSON_BYTES);
    }
    agentEvaluationRequireDistinctFileIdentities($ledgerPaths);
    $rows = [];
    $plannedRunIds = [];
    $knownRunDirectories = [];
    $inputs = agentEvaluationRequireObject($campaign, 'inputs', 'calibration report');
    foreach ($expectedPlans as $index => $value) {
        $planned = agentEvaluationValueObject($value, 'calibration planned attempt');
        $slot = agentEvaluationRequireInteger($planned, 'slot', 'calibration planned attempt');
        $runId = agentEvaluationRequireString($planned, 'run_id', 'calibration planned attempt');
        if ($slot !== $index + 1 || preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1 || isset($plannedRunIds[$runId])) {
            throw new RuntimeException('Calibration planned slots and execution identities must be distinct and ordered.');
        }
        $plannedRunIds[$runId] = true;
        $prefix = $attemptRoot . '/' . sprintf('%02d', $slot);
        agentEvaluationRequireFileHash($prefix . '.planned.json', hash('sha256', agentEvaluationJson($planned)), 'calibration planned identity');
        $startedPath = $prefix . '.started.json';
        $started = file_exists($startedPath);
        $finalRetained = file_exists($prefix . '.json');
        $scoreRetained = file_exists($prefix . '.score.json');
        if ($started) {
            $expectedStart = [...$planned, 'status' => 'running', 'phase' => 'prepare'];
            agentEvaluationRequireFileHash($startedPath, hash('sha256', agentEvaluationJson($expectedStart)), 'calibration started identity');
            $knownRunDirectories[$runId] = true;
        }
        if (!$finalRetained && $scoreRetained) {
            throw new RuntimeException('Calibration score requires its retained final attempt.');
        }
        if (!$started && (file_exists($runRoot . '/' . $runId) || is_link($runRoot . '/' . $runId))) {
            throw new RuntimeException('An unstarted calibration slot has an unexpected execution directory.');
        }
        if (!$finalRetained) {
            $rows[] = ['slot' => $slot, 'status' => 'unfinished', 'score' => null];
            continue;
        }
        $attempt = agentEvaluationJsonFile($prefix . '.json');
        agentEvaluationRequireExactKeys($attempt, array_keys($planned), 'calibration retained attempt');
        foreach ($planned as $name => $expected) {
            if (!in_array($name, ['status', 'phase', 'termination_reason', 'usage', 'elapsed_milliseconds', 'unknown_metrics', 'artifacts'], true)
                && $attempt[$name] !== $expected) {
                throw new RuntimeException('Calibration attempt identity changed after planning.');
            }
        }
        agentEvaluationControllerValidateCalibrationAttemptState($attempt);
        if ($attempt['status'] === 'not_run') {
            if ($started || agentEvaluationRequireObject($attempt, 'artifacts', 'unrun calibration') !== []
                || $attempt['phase'] !== 'planned' || $attempt['termination_reason'] !== 'calibration_aborted') {
                throw new RuntimeException('Unrun calibration must have no generation or artifacts.');
            }
        } elseif (!$started) {
            throw new RuntimeException('Calibration final execution requires its retained started attempt.');
        }
        $inputKey = agentEvaluationRequireString($attempt, 'task_id', 'calibration attempt') . ':'
            . agentEvaluationRequireString($attempt, 'condition', 'calibration attempt');
        $input = agentEvaluationValueObject($inputs[$inputKey], 'calibration report input');
        $evidence = $runRoot . '/' . $runId . '/evidence';
        $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'calibration report');
        if (file_exists($evidence) || is_link($evidence)) {
            $actual = agentEvaluationControllerComparisonArtifacts($evidence);
            if (agentEvaluationControllerCanonicalObservation(agentEvaluationValueObject($actual, 'calibration actual evidence inventory'))
                !== agentEvaluationControllerCanonicalObservation($artifacts)) {
                throw new RuntimeException('Calibration report artifact inventory must equal every retained evidence file.');
            }
        } elseif ($artifacts !== []) {
            throw new RuntimeException('Calibration attempt names evidence absent from its retained run directory.');
        }
        $spending = agentEvaluationControllerValidateCalibrationEvidence($attempt, $evidence);
        $score = null;
        if ($scoreRetained) {
            $score = agentEvaluationControllerCalibrationScore($attempt, agentEvaluationFileHash($prefix . '.json', 'calibration attempt'),
                agentEvaluationRequireObject($input, 'holdout', 'calibration input'), $evidence);
            agentEvaluationRequireFileHash($prefix . '.score.json', hash('sha256', agentEvaluationJson($score)), 'calibration recomputed score');
        }
        $rows[] = ['slot' => $slot, 'task_id' => $attempt['task_id'], 'condition' => $attempt['condition'],
            'status' => $attempt['status'], 'phase' => $attempt['phase'], 'termination_reason' => $attempt['termination_reason'],
            'usage' => $attempt['usage'], 'elapsed_milliseconds' => $attempt['elapsed_milliseconds'], 'spending' => $spending,
            'score' => $score, 'score_status' => $score === null ? 'missing' : 'retained', 'evidence_root' => $evidence];
    }
    $runEntries = scandir($runRoot);
    if ($runEntries === false || count($runEntries) > 8) {
        throw new RuntimeException('Calibration execution directory inventory exceeds its fixed planned attempts.');
    }
    foreach ($runEntries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!isset($knownRunDirectories[$entry])) {
            throw new RuntimeException('Calibration evidence contains an execution without its planned started attempt.');
        }
        agentEvaluationControllerExistingRoot($runRoot . '/' . $entry, 'calibration retained execution directory');
    }
    return ['schema_version' => 1, 'kind' => 'calibration-report-v' . agentEvaluationControllerCalibrationRevision(
            agentEvaluationRequireObject($configuration, 'calibration', 'calibration report')), 'comparative_claims' => false,
        'campaign_id' => $id, 'configuration_sha256' => $campaign['sha256'], 'implementation_sha256' => $configuration['implementation_sha256'],
        'calibration' => $configuration['calibration'], 'planned_runs' => 6, 'spending_ceiling_usd' => '6.00', 'rows' => $rows,
        'correctness_rates_available' => false, 'human_review' => 'pending',
        'limitation' => 'One calibration per task and condition cannot establish framework correctness rates or replace the sixty-trial study.'];
}

/**
 * This opt-in campaign uses the same admitted tasks, fixed process owner, and
 * generation/freeze/scoring lifecycle as the smoke controller.
 * @param list<string> $arguments
 */
function agentEvaluationControllerComparisonMain(string $root, array $arguments): int
{
    agentEvaluationControllerRequireArgumentCount($arguments, 3, 'comparison-{prepare,run,report} <configuration.json>');
    $calibration = str_starts_with($arguments[1], 'calibration-');
    $campaign = agentEvaluationControllerReadComparisonConfiguration($root, $arguments[2], $calibration);
    if (in_array($arguments[1], ['comparison-prepare', 'calibration-prepare'], true)) {
        fwrite(STDOUT, agentEvaluationJson([
            'status' => 'prepared-no-trials', 'campaign_id' => $campaign['configuration']['campaign_id'],
            'configuration_sha256' => $campaign['sha256'], 'schedule' => $campaign['schedule'],
            'planned_attempts' => $campaign['attempts'], 'paid_authorization' => false,
        ]));
        return 0;
    }
    if ($calibration) {
        if ($arguments[1] === 'calibration-run') {
            agentEvaluationControllerRunCalibration($root, $campaign);
        } elseif ($arguments[1] !== 'calibration-report') {
            throw new RuntimeException('Unknown calibration command.');
        }
        fwrite(STDOUT, agentEvaluationJson(agentEvaluationControllerCalibrationReport($root, $campaign)));
        return 0;
    }
    if ($arguments[1] === 'comparison-run') {
        agentEvaluationControllerRunComparisonCampaign($root, $campaign);
    } elseif ($arguments[1] !== 'comparison-report') {
        throw new RuntimeException('Unknown comparison command.');
    }
    fwrite(STDOUT, agentEvaluationJson(agentEvaluationControllerComparisonReport($root, $campaign)));
    return 0;
}

/** @return string */
function agentEvaluationControllerComparisonCodeHash(string $root): string
{
    $manifest = '';
    foreach (['agent-evaluation', 'agent-evaluation-controller'] as $directory) {
        $tree = agentEvaluationControllerDescribeTree($root . '/tools/' . $directory, 'comparison implementation', true);
        $manifest .= $directory . ' ' . $tree['sha256'] . "\n";
        $manifest .= $directory . '.php ' . agentEvaluationFileHash($root . '/tools/' . $directory . '.php', 'comparison entrypoint') . "\n";
    }
    return hash('sha256', $manifest);
}

/**
 * @return array{configuration:array<string,mixed>,bytes:string,sha256:string,schedule:list<array{slot:int,round:int,task_id:string,condition:string}>,inputs:array<string,array<string,mixed>>,attempts:list<array<string,mixed>>}
 */
function agentEvaluationControllerReadComparisonConfiguration(string $root, string $path, bool $calibration = false): array
{
    agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_JSON_BYTES, 'comparison configuration');
    $bytes = file_get_contents($path, false, null, 0, AGENT_EVALUATION_MAX_JSON_BYTES + 1);
    if (!is_string($bytes)) {
        throw new RuntimeException('Comparison configuration is unreadable.');
    }
    $configuration = agentEvaluationValueObject(agentEvaluationJsonValue($bytes, 'comparison configuration'), 'comparison configuration');
    agentEvaluationRequireExactKeys($configuration, [
        'schema_version', 'campaign_id', 'source_revision', 'protocol_sha256', 'implementation_sha256',
        'profile', 'engine', 'approval', 'pricing', 'model_revision_note', 'inputs',
        ...($calibration ? ['calibration'] : []),
    ], 'comparison configuration');
    $kit = $root . '/tools/agent-evaluation';
    agentEvaluationComparisonProtocol($kit);
    $id = agentEvaluationRequireString($configuration, 'campaign_id', 'comparison configuration');
    $revision = agentEvaluationRequireString($configuration, 'source_revision', 'comparison configuration');
    if ($configuration['schema_version'] !== 1 || preg_match('/\A[a-f0-9]{32}\z/D', $id) !== 1
        || preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/D', $revision) !== 1
        || $revision !== agentEvaluationControllerComparisonRepositoryRevision($root)
        || $configuration['protocol_sha256'] !== agentEvaluationFileHash($kit . '/comparison-v1.json', 'comparison protocol')
        || $configuration['implementation_sha256'] !== agentEvaluationControllerComparisonCodeHash($root)
    ) {
        throw new RuntimeException('Comparison configuration must bind its exact protocol and implementation bytes.');
    }
    $calibrationRevision = 1;
    if ($calibration) {
        $calibrationPolicy = agentEvaluationRequireObject($configuration, 'calibration', 'calibration configuration');
        agentEvaluationControllerValidateCalibrationPolicy($calibrationPolicy);
        $calibrationRevision = agentEvaluationControllerCalibrationRevision($calibrationPolicy);
    }
    $profile = agentEvaluationRequireObject($configuration, 'profile', 'comparison configuration');
    if (($profile['condition'] ?? null) !== ($calibration ? 'calibration-v' . $calibrationRevision : 'comparison-v1')) {
        throw new RuntimeException('Comparison shared profile must use its fixed template condition.');
    }
    $model = agentEvaluationRequireObject($profile, 'model', 'comparison profile');
    $revisionNote = agentEvaluationRequireNonEmptyString($configuration, 'model_revision_note', 'comparison configuration');
    if (strlen($revisionNote) > 256 || preg_match('/[\x00-\x1F\x7F]/', $revisionNote) === 1) {
        throw new RuntimeException('Comparison model revision provenance requires a bounded explanation.');
    }
    $pricing = agentEvaluationRequireObject($configuration, 'pricing', 'comparison configuration');
    agentEvaluationRequireExactKeys($pricing, ['date', 'source', 'model', 'service_tier', 'input_cents_per_million', 'cached_cents_per_million', 'output_cents_per_million'], 'comparison pricing');
    if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', agentEvaluationRequireString($pricing, 'date', 'comparison pricing')) !== 1
        || !in_array($pricing['source'], ['https://developers.openai.com/api/docs/pricing', 'https://openai.com/api/pricing/'], true)
        || $pricing['model'] !== ($model['id'] ?? null) || $pricing['service_tier'] !== 'standard'
    ) {
        throw new RuntimeException('Comparison pricing must name its dated official standard model prices.');
    }
    foreach (['input_cents_per_million', 'cached_cents_per_million', 'output_cents_per_million'] as $name) {
        if (agentEvaluationRequirePositiveInteger($pricing, $name, 'comparison pricing') > 100_000) {
            throw new RuntimeException('Comparison listed token price exceeds the bounded proposal.');
        }
    }
    if ($pricing['cached_cents_per_million'] > $pricing['input_cents_per_million']) {
        throw new RuntimeException('Comparison cached-input price cannot exceed ordinary input price.');
    }
    if ($calibration) {
        agentEvaluationControllerValidateCalibrationPolicy(agentEvaluationRequireObject($configuration, 'calibration', 'calibration configuration'));
        $settings = agentEvaluationValueObject($model['settings'] ?? null, 'calibration settings');
        agentEvaluationControllerProxyValidateSpending(agentEvaluationRequireString($model, 'id', 'calibration model'),
            agentEvaluationRequireString($settings, 'reasoning_effort', 'calibration settings'), agentEvaluationControllerCalibrationSpending());
        if ($model['id'] !== 'gpt-5.4-2026-03-05'
            || $pricing['input_cents_per_million'] !== 250 || $pricing['cached_cents_per_million'] !== 25
            || $pricing['output_cents_per_million'] !== 1500) {
            throw new RuntimeException('Calibration requires its exact reviewed snapshot and standard prices.');
        }
    }
    $inputValues = agentEvaluationRequireList($configuration, 'inputs', 'comparison configuration');
    if (count($inputValues) !== 6) {
        throw new RuntimeException('Comparison configuration must freeze exactly six task/condition preparations.');
    }
    $inputs = [];
    foreach ($inputValues as $value) {
        $input = agentEvaluationValueObject($value, 'comparison prepared input');
        agentEvaluationRequireExactKeys($input, ['task_id', 'condition', 'task_manifest_sha256', 'source_context_sha256',
            'prepared_dependencies', 'prepared_lock', 'prepared_dependencies_sha256', 'prepared_lock_sha256',
            'installed_context_sha256', 'holdout_path'], 'comparison prepared input');
        $task = agentEvaluationComparisonTask($kit, agentEvaluationRequireString($input, 'task_id', 'comparison input'));
        $conditionId = agentEvaluationRequireString($input, 'condition', 'comparison input');
        $condition = null;
        foreach ($task['conditions'] as $candidate) {
            if ($candidate['id'] === $conditionId) {
                $condition = $candidate;
            }
        }
        if ($condition === null || $input['task_manifest_sha256'] !== $task['manifest_sha256']
            || $input['source_context_sha256'] !== $condition['context_manifest']['sha256']
        ) {
            throw new RuntimeException('Comparison preparation does not match its task and visible source context.');
        }
        $key = $task['id'] . ':' . $conditionId;
        if (isset($inputs[$key])) {
            throw new RuntimeException('Comparison preparations must use distinct task/condition pairs.');
        }
        $selected = agentEvaluationControllerAdmitComparisonTask($task, $condition);
        $execution = agentEvaluationControllerValidateLiveConfiguration([
            'profile' => [...$profile, 'condition' => $conditionId], 'engine' => $configuration['engine'],
            'approval' => $configuration['approval'],
            'prepared_dependencies' => $input['prepared_dependencies'], 'prepared_lock' => $input['prepared_lock'],
            'prepared_dependencies_sha256' => $input['prepared_dependencies_sha256'], 'prepared_lock_sha256' => $input['prepared_lock_sha256'],
        ], $selected, $calibration ? 6 : 60, $calibration, $calibrationRevision);
        $dependencies = agentEvaluationRequireString($execution, 'prepared_dependencies', 'comparison dependencies');
        $holdoutPath = agentEvaluationRequireString($input, 'holdout_path', 'comparison input');
        $holdoutReal = realpath($holdoutPath);
        if (!is_string($holdoutReal) || $holdoutReal !== $holdoutPath || is_link($holdoutPath)
            || agentEvaluationControllerPathsOverlap($root, $holdoutReal)
            || agentEvaluationControllerPathsOverlap($dependencies, $holdoutReal)
        ) {
            throw new RuntimeException('Private comparison expectations must be canonical external files outside every generation input.');
        }
        $holdout = agentEvaluationControllerReadComparisonHoldout($holdoutPath, $task);
        $installedContext = agentEvaluationControllerInstalledComparisonContext($dependencies);
        if ($input['installed_context_sha256'] !== hash('sha256', agentEvaluationJson($installedContext))) {
            throw new RuntimeException('Installed dependency documentation context changed after preparation.');
        }
        $inputs[$key] = ['task' => $task, 'condition' => $condition, 'execution' => $execution,
            'holdout' => $holdout, 'holdout_path' => $holdoutPath, 'installed_context' => $installedContext];
    }
    agentEvaluationControllerValidateComparisonHoldoutVisibility($inputs);
    $context = agentEvaluationRequireObject($profile, 'context', 'comparison shared profile');
    if (($context['bundle_id'] ?? null) !== null || ($context['bundle_sha256'] ?? null) !== null) {
        throw new RuntimeException('The fixed comparison supplies only its source and installed package context.');
    }
    $schedule = agentEvaluationComparisonSchedule($kit);
    if ($calibration) {
        $schedule = array_slice($schedule, 0, 6);
    }
    $attempts = [];
    foreach ($schedule as $slot) {
        $input = $inputs[$slot['task_id'] . ':' . $slot['condition']] ?? null;
        if ($input === null) {
            throw new RuntimeException('Comparison schedule contains a missing preparation.');
        }
        $attempts[] = $calibration ? agentEvaluationControllerPlannedCalibrationAttempt($configuration, $slot, $input)
            : agentEvaluationControllerPlannedComparisonAttempt($configuration, $slot, $input);
    }
    return ['configuration' => $configuration, 'bytes' => $bytes, 'sha256' => hash('sha256', $bytes),
        'schedule' => $schedule, 'inputs' => $inputs, 'attempts' => $attempts];
}

/** @return array{scope:string,files:list<array{path:string,sha256:string,bytes:int}>,bytes:int,words:int} */
function agentEvaluationControllerInstalledComparisonContext(string $dependencies): array
{
    $tree = agentEvaluationControllerDescribeTree($dependencies, 'installed comparison context', true);
    $files = [];
    $bytes = 0;
    $words = 0;
    foreach ($tree['files'] as $path => $file) {
        if (preg_match('/\.(?:md|rst|txt)\z/iD', $path) !== 1) {
            continue;
        }
        $source = file_get_contents($dependencies . '/' . $path);
        if (!is_string($source)) {
            throw new RuntimeException('Installed comparison documentation is unreadable.');
        }
        $files[] = ['path' => $path, 'sha256' => hash('sha256', $source), 'bytes' => strlen($source)];
        $bytes += strlen($source);
        $parts = preg_split('/[\x09-\x0D\x20]+/', trim($source), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new RuntimeException('Unable to count installed comparison context.');
        }
        $words += count($parts);
    }
    return ['scope' => 'installed-dependency-documentation', 'files' => $files, 'bytes' => $bytes, 'words' => $words];
}

/**
 * @param array<string,mixed> $configuration
 * @param array{slot:int,round:int,task_id:string,condition:string} $slot
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function agentEvaluationControllerPlannedComparisonAttempt(array $configuration, array $slot, array $input): array
{
    $task = agentEvaluationRequireObject($input, 'task', 'comparison preparation');
    $condition = agentEvaluationRequireObject($input, 'condition', 'comparison preparation');
    $base = agentEvaluationRequireObject($condition, 'base', 'comparison condition');
    $execution = agentEvaluationRequireObject($input, 'execution', 'comparison preparation');
    $holdout = agentEvaluationRequireObject(agentEvaluationRequireObject($task, 'checks', 'comparison task'), 'holdout', 'comparison task');
    $id = agentEvaluationRequireString($configuration, 'campaign_id', 'comparison configuration');
    $usage = ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null];
    $attempt = ['schema_version' => 2, 'campaign_id' => $id, 'slot' => $slot['slot'],
        'run_id' => substr(hash('sha256', $id . ':' . $slot['slot']), 0, 32), 'task_id' => $slot['task_id'],
        'task_revision' => $task['revision'], 'condition' => $slot['condition'], 'protocol_sha256' => $configuration['protocol_sha256'],
        'task_manifest_sha256' => $task['manifest_sha256'], 'source_revision' => $configuration['source_revision'],
        'base_fixture_sha256' => $base['fixture_sha256'], 'prepared_dependencies_manifest_sha256' => $execution['prepared_dependencies_sha256'],
        'prepared_lock_sha256' => $execution['prepared_lock_sha256'], 'profile_sha256' => hash('sha256', agentEvaluationJson($execution['profile'])),
        'holdout_sha256' => $holdout['sha256'], 'status' => 'planned', 'phase' => 'planned', 'termination_reason' => null,
        'usage' => $usage, 'elapsed_milliseconds' => null, 'repair_turns' => 0,
        'unknown_metrics' => agentEvaluationControllerComparisonUnknownMetrics($usage, null), 'artifacts' => new stdClass()];
    agentEvaluationValidateComparisonRunRecord($attempt, $task, $slot, $id,
        agentEvaluationRequireString($configuration, 'protocol_sha256', 'comparison configuration'));
    return $attempt;
}

/**
 * @param array<string,mixed> $usage
 * @return array<string,string>
 */
function agentEvaluationControllerComparisonUnknownMetrics(array $usage, mixed $elapsed): array
{
    $unknown = [];
    foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens'] as $name) {
        if (($usage[$name] ?? null) === null) {
            $unknown[$name] = 'No authoritative provider total was retained for this category.';
        }
    }
    if ($elapsed === null) {
        $unknown['elapsed_milliseconds'] = 'No completed generation timing observation was retained.';
    }
    return [...$unknown, 'public_check_repairs' => 'Requires review of the actual generation evidence.',
        'human_interventions' => 'Requires a separately recorded accountable human review.',
        'reviewer_effort' => 'Requires an actual reviewer timing observation.'];
}

/** @param list<string> $arguments */
function agentEvaluationControllerMain(array $arguments): int
{
    $root = dirname(__DIR__, 2);
    $kit = $root . '/tools/agent-evaluation';
    $command = $arguments[1] ?? 'help';

    try {
        if (in_array($command, ['comparison-prepare', 'comparison-report', 'comparison-run', 'calibration-prepare', 'calibration-report', 'calibration-run'], true)) {
            return agentEvaluationControllerComparisonMain($root, $arguments);
        }
        if ($command === 'validate') {
            agentEvaluationControllerRequireArgumentCount($arguments, 2, 'validate');
            $task = agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID);
            agentEvaluationControllerRequireFixedTask($task);
            agentEvaluationControllerRequireAdmittedTask(agentEvaluationExplanationTask($kit));
            fwrite(
                STDOUT,
                "PASS agent evaluation controller v0.2: synthetic lifecycle installed; live execution fails closed\n",
            );

            return 0;
        }

        if ($command === 'preflight') {
            agentEvaluationControllerRequireArgumentCount($arguments, 3, 'preflight <configuration.json>');
            $configuration = agentEvaluationControllerReadLiveConfiguration($arguments[2]);
            $controlRoot = agentEvaluationControllerCreatePreflightRoot();
            $interruptState = null;
            try {
                $interruptState = agentEvaluationControllerInstallInterruptHandlers();
                $engine = agentEvaluationControllerOciPreflight(
                    agentEvaluationRequireObject($configuration, 'engine', 'controller live configuration'),
                    $controlRoot,
                );
                fwrite(STDOUT, agentEvaluationJson(['status' => 'pass', 'engine' => $engine['identity']]));
            } finally {
                try {
                    $ledger = agentEvaluationControllerReadOciRecoveryLedger($controlRoot);
                    if ($ledger !== null && ($ledger['containers'] !== [] || $ledger['volumes'] !== [])) {
                        fwrite(STDERR, 'OCI cleanup requires review; recovery ledger retained at ' . $controlRoot . "/owned-resources.json\n");
                    } else {
                        agentEvaluationControllerRemoveTree($controlRoot);
                    }
                } finally {
                    if ($interruptState !== null) {
                        agentEvaluationControllerRestoreInterruptHandlers($interruptState);
                    }
                }
            }
            return 0;
        }

        if ($command === 'explanation-preflight') {
            agentEvaluationControllerRequireArgumentCount(
                $arguments,
                3,
                'explanation-preflight <configuration.json>',
            );
            $task = agentEvaluationExplanationTask($kit);
            $configuration = agentEvaluationControllerReadExplanationLiveConfiguration($arguments[2], $task);
            agentEvaluationControllerValidateExplanationPreflightInputs(
                $root,
                $configuration,
                $task,
            );
            $controlRoot = agentEvaluationControllerCreatePreflightRoot();
            $interruptState = null;
            try {
                $interruptState = agentEvaluationControllerInstallInterruptHandlers();
                $engine = agentEvaluationControllerOciPreflight(
                    agentEvaluationRequireObject($configuration, 'engine', 'controller explanation configuration'),
                    $controlRoot,
                );
                fwrite(STDOUT, agentEvaluationJson(['status' => 'pass', 'engine' => $engine['identity']]));
            } finally {
                try {
                    $ledger = agentEvaluationControllerReadOciRecoveryLedger($controlRoot);
                    if ($ledger !== null && ($ledger['containers'] !== [] || $ledger['volumes'] !== [])) {
                        fwrite(STDERR, 'OCI cleanup requires review; recovery ledger retained at '
                            . $controlRoot . "/owned-resources.json\n");
                    } else {
                        agentEvaluationControllerRemoveTree($controlRoot);
                    }
                } finally {
                    if ($interruptState !== null) {
                        agentEvaluationControllerRestoreInterruptHandlers($interruptState);
                    }
                }
            }
            return 0;
        }

        if ($command === 'run') {
            if (count($arguments) !== 3 && count($arguments) !== 4) {
                throw new RuntimeException('run <run-id> <configuration.json> received an unexpected number of arguments.');
            }
            $runId = $arguments[2];

            $task = agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID);
            agentEvaluationControllerValidateRequest(
                ['run_id' => $runId, 'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID],
                $task,
            );
            if (!isset($arguments[3])) {
                throw new RuntimeException(
                    AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE
                    . ': an approved explicit live configuration and all OCI controls are required.',
                );
            }
            $configuration = agentEvaluationControllerReadLiveConfiguration($arguments[3]);
            $approval = agentEvaluationRequireObject($configuration, 'approval', 'controller smoke approval');
            if ($approval['spending_ceiling_usd'] === '0.00') {
                throw new RuntimeException('A zero-spend integration approval cannot authorize a paid run.');
            }
            $credential = \getenv('OPENAI_API_KEY');
            if (!is_string($credential) || $credential === '' || strlen($credential) > 4_096 || preg_match('/[\x00-\x20\x7F]/', $credential) === 1) {
                throw new RuntimeException('Live execution requires the host-only OPENAI_API_KEY.');
            }
            $runsRoot = dirname($root) . '/agent-evaluation-runs';
            if (!file_exists($runsRoot) && !mkdir($runsRoot, 0700)) {
                throw new RuntimeException('Unable to prepare the fixed evaluation evidence parent.');
            }
            agentEvaluationControllerExistingRoot($runsRoot, 'evaluation evidence parent');
            $result = agentEvaluationControllerExecuteLive(
                $root,
                $runsRoot . '/' . $runId,
                ['run_id' => $runId, 'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID],
                $configuration,
                $credential,
            );
            fwrite(STDOUT, agentEvaluationJson($result));
            return $result['automated_status'] === 'pass' ? 0 : 1;
        }

        if ($command === 'explanation-run') {
            agentEvaluationControllerRequireArgumentCount(
                $arguments,
                4,
                'explanation-run <run-id> <configuration.json>',
            );
            $runId = $arguments[2];
            $task = agentEvaluationExplanationTask($kit);
            $request = agentEvaluationControllerValidateRequest(
                ['run_id' => $runId, 'task_id' => AGENT_EVALUATION_EXPLANATION_TASK_ID],
                $task,
            );
            $configuration = agentEvaluationControllerReadExplanationLiveConfiguration($arguments[3], $task);
            agentEvaluationControllerRequireExplanationApprovalRunId($configuration, $runId);
            $approval = agentEvaluationRequireObject($configuration, 'approval', 'controller explanation approval');
            if ($approval['spending_ceiling_usd'] !== '0.60') {
                throw new RuntimeException('A paid explanation run requires its exact enforced USD 0.60 ceiling.');
            }
            $credential = \getenv('OPENAI_API_KEY');
            if (!is_string($credential) || $credential === '' || strlen($credential) > 4_096
                || preg_match('/[\x00-\x20\x7F]/', $credential) === 1
            ) {
                throw new RuntimeException('Live explanation execution requires the host-only OPENAI_API_KEY.');
            }
            $runsRoot = dirname($root) . '/agent-evaluation-runs';
            if (!file_exists($runsRoot) && !mkdir($runsRoot, 0700)) {
                throw new RuntimeException('Unable to prepare the fixed evaluation evidence parent.');
            }
            agentEvaluationControllerExistingRoot($runsRoot, 'evaluation evidence parent');
            $result = agentEvaluationControllerExecuteExplanationLive(
                $root,
                $runsRoot . '/' . $runId,
                $request,
                $configuration,
                $credential,
                $task,
            );
            fwrite(STDOUT, agentEvaluationJson($result));
            return $result['automated_status'] === 'pass' ? 0 : 1;
        }

        if ($command === 'help') {
            if (count($arguments) !== 1 && count($arguments) !== 2) {
                throw new RuntimeException('help received an unexpected number of arguments.');
            }

            fwrite(
                STDOUT,
                "Usage:\n"
                . "  php tools/agent-evaluation-controller.php validate\n"
                . "  php tools/agent-evaluation-controller.php preflight <configuration.json>\n"
                . "  php tools/agent-evaluation-controller.php run <32-lowercase-hex-run-id> <configuration.json>\n\n"
                . "  php tools/agent-evaluation-controller.php explanation-preflight <configuration.json>\n"
                . "  php tools/agent-evaluation-controller.php explanation-run <32-lowercase-hex-run-id> <configuration.json>\n\n"
                . "  php tools/agent-evaluation-controller.php comparison-{prepare,run,report} <configuration.json>\n"
                . "  php tools/agent-evaluation-controller.php calibration-{prepare,run,report} <configuration.json>\n\n"
                . "Live execution is opt-in and fails closed unless every ADR 048 OCI and proxy control passes.\n",
            );

            return 0;
        }

        throw new RuntimeException("Unknown agent-evaluation-controller command: {$command}.");
    } catch (Throwable $throwable) {
        fwrite(STDERR, "FAIL agent evaluation controller: {$throwable->getMessage()}\n");

        return 1;
    }
}

/**
 * @param list<string> $arguments
 */
function agentEvaluationControllerRequireArgumentCount(array $arguments, int $expected, string $usage): void
{
    if (count($arguments) !== $expected) {
        throw new RuntimeException("{$usage} received an unexpected number of arguments.");
    }
}

/** @param array<string, mixed> $task */
function agentEvaluationControllerValidateEffectivePromptEvidence(
    string $evidenceRoot,
    array $task,
    ?string $condition,
    ?int $calibrationRevision,
): void {
    if (($task['schema_version'] ?? null) !== 3) {
        agentEvaluationControllerValidatePromptEvidence(
            $evidenceRoot,
            agentEvaluationJsonFile($evidenceRoot . '/task.json'),
            $condition,
            $calibrationRevision,
        );
        return;
    }
    if ($condition !== null || $calibrationRevision !== null) {
        throw new RuntimeException('Explanation prompt cannot select a comparison condition or calibration suffix.');
    }
    $promptDescriptor = agentEvaluationRequireObject($task, 'prompt', 'explanation task prompt');
    agentEvaluationRequireFileHash(
        $evidenceRoot . '/source-prompt.md',
        agentEvaluationRequireString($promptDescriptor, 'sha256', 'explanation task prompt'),
        'explanation source prompt',
    );
    $source = file_get_contents(
        $evidenceRoot . '/source-prompt.md',
        false,
        null,
        0,
        AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES + 1,
    );
    if (!is_string($source)) {
        throw new RuntimeException('Explanation source prompt is unavailable.');
    }
    $effective = agentEvaluationExplanationEffectivePrompt($source);
    $effectiveHash = agentEvaluationRequireString(
        $promptDescriptor,
        'effective_sha256',
        'explanation task prompt',
    );
    if (!hash_equals($effectiveHash, hash('sha256', $effective))) {
        throw new RuntimeException('Explanation task effective prompt hash does not match its fixed prompt transformation.');
    }
    agentEvaluationRequireFileHash(
        $evidenceRoot . '/prompt.md',
        $effectiveHash,
        'explanation effective prompt',
    );
    $policy = agentEvaluationControllerWorkspacePolicy($task);
    $policyBytes = agentEvaluationJson(agentEvaluationControllerWorkspacePolicyEvidence($policy, true));
    agentEvaluationRequireFileHash(
        $evidenceRoot . '/workspace-policy.json',
        hash('sha256', $policyBytes),
        'explanation workspace policy',
    );
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $profile
 * @return array{
 *   run_id: string,
 *   evidence_root: string,
 *   run_record_path: string,
 *   score_record_path: string,
 *   evidence_manifest_path: string,
 *   automated_status: string,
 *   weighted_score: int,
 *   cleanup: array{status: string, removed: list<string>}
 * }
 */
function agentEvaluationControllerExecuteSynthetic(
    string $repositoryRoot,
    string $preparedDependencies,
    string $runRoot,
    array $request,
    array $profile,
    ?string $testFailureMode = null,
): array {
    if (!agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_SYNTHETIC_EXECUTION_TEST_ONLY');
    }

    return agentEvaluationControllerSmokeResult(agentEvaluationControllerExecuteControlled(
        $repositoryRoot, $preparedDependencies, $runRoot, $request, $profile, $testFailureMode, null, '',
    ));
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $configuration
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,weighted_score:int,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerExecuteLive(
    string $repositoryRoot,
    string $runRoot,
    array $request,
    array $configuration,
    #[SensitiveParameter] string $credential,
): array {
    return agentEvaluationControllerSmokeResult(agentEvaluationControllerExecuteControlled(
        $repositoryRoot,
        agentEvaluationRequireString($configuration, 'prepared_dependencies', 'controller live configuration'),
        $runRoot,
        $request,
        agentEvaluationRequireObject($configuration, 'profile', 'controller live configuration'),
        null,
        $configuration,
        $credential,
    ));
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $configuration
 * @param array<string, mixed> $task
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,human_review:string,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerExecuteExplanationLive(
    string $repositoryRoot,
    string $runRoot,
    array $request,
    array $configuration,
    #[SensitiveParameter] string $credential,
    array $task,
): array {
    agentEvaluationControllerRequireExplanationApprovalRunId(
        $configuration,
        agentEvaluationRequireString($request, 'run_id', 'controller explanation request'),
    );
    return agentEvaluationControllerExplanationResult(agentEvaluationControllerExecuteControlled(
        $repositoryRoot,
        agentEvaluationRequireString($configuration, 'prepared_dependencies', 'controller explanation configuration'),
        $runRoot,
        $request,
        agentEvaluationRequireObject($configuration, 'profile', 'controller explanation configuration'),
        null,
        $configuration,
        $credential,
        null,
        null,
        $task,
    ));
}

/**
 * @param array<string, mixed> $result
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,human_review:string,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerExplanationResult(array $result): array
{
    $cleanup = agentEvaluationRequireObject($result, 'cleanup', 'explanation lifecycle result');
    return ['run_id' => agentEvaluationRequireString($result, 'run_id', 'explanation lifecycle result'),
        'evidence_root' => agentEvaluationRequireString($result, 'evidence_root', 'explanation lifecycle result'),
        'run_record_path' => agentEvaluationRequireString($result, 'run_record_path', 'explanation lifecycle result'),
        'score_record_path' => agentEvaluationRequireString($result, 'score_record_path', 'explanation lifecycle result'),
        'evidence_manifest_path' => agentEvaluationRequireString($result, 'evidence_manifest_path', 'explanation lifecycle result'),
        'automated_status' => agentEvaluationRequireString($result, 'automated_status', 'explanation lifecycle result'),
        'human_review' => agentEvaluationRequireString($result, 'human_review', 'explanation lifecycle result'),
        'cleanup' => ['status' => agentEvaluationRequireString($cleanup, 'status', 'explanation cleanup'),
            'removed' => agentEvaluationRequireStringList($cleanup, 'removed', 'explanation cleanup')]];
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $profile
 * @param array<string, mixed> $task
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,human_review:string,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerExecuteExplanationSynthetic(
    string $repositoryRoot,
    string $preparedDependencies,
    string $runRoot,
    array $request,
    array $profile,
    array $task,
    ?string $testFailureMode = null,
): array {
    if (!agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_SYNTHETIC_EXECUTION_TEST_ONLY');
    }
    return agentEvaluationControllerExplanationResult(agentEvaluationControllerExecuteControlled(
        $repositoryRoot,
        $preparedDependencies,
        $runRoot,
        $request,
        $profile,
        $testFailureMode,
        null,
        '',
        null,
        null,
        $task,
    ));
}

/**
 * @param array<string, mixed> $result
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,weighted_score:int,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerSmokeResult(array $result): array
{
    $cleanup = agentEvaluationRequireObject($result, 'cleanup', 'smoke lifecycle result');
    return ['run_id' => agentEvaluationRequireString($result, 'run_id', 'smoke lifecycle result'),
        'evidence_root' => agentEvaluationRequireString($result, 'evidence_root', 'smoke lifecycle result'),
        'run_record_path' => agentEvaluationRequireString($result, 'run_record_path', 'smoke lifecycle result'),
        'score_record_path' => agentEvaluationRequireString($result, 'score_record_path', 'smoke lifecycle result'),
        'evidence_manifest_path' => agentEvaluationRequireString($result, 'evidence_manifest_path', 'smoke lifecycle result'),
        'automated_status' => agentEvaluationRequireString($result, 'automated_status', 'smoke lifecycle result'),
        'weighted_score' => agentEvaluationRequireInteger($result, 'weighted_score', 'smoke lifecycle result'),
        'cleanup' => ['status' => agentEvaluationRequireString($cleanup, 'status', 'smoke cleanup'),
            'removed' => agentEvaluationRequireStringList($cleanup, 'removed', 'smoke cleanup')]];
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $configuration
 * @param array<string, mixed> $comparisonContext
 * @return array<string, mixed>
 */
function agentEvaluationControllerExecuteComparisonLive(
    string $repositoryRoot,
    string $runRoot,
    array $request,
    array $configuration,
    #[SensitiveParameter] string $credential,
    array $comparisonContext,
): array {
    return agentEvaluationControllerExecuteControlled($repositoryRoot,
        agentEvaluationRequireString($configuration, 'prepared_dependencies', 'comparison configuration'),
        $runRoot, $request, agentEvaluationRequireObject($configuration, 'profile', 'comparison configuration'),
        null, $configuration, $credential, $comparisonContext);
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $profile
 * @param array<string, mixed>|null $execution
 * @param array<string, mixed>|null $comparisonContext
 * @param array<string, mixed>|null $calibration
 * @param array<string, mixed>|null $explanationTask
 * @return array<string, mixed>
 */
function agentEvaluationControllerExecuteControlled(
    string $repositoryRoot,
    string $preparedDependencies,
    string $runRoot,
    array $request,
    array $profile,
    ?string $testFailureMode,
    ?array $execution,
    #[SensitiveParameter] string $credential,
    ?array $comparisonContext = null,
    ?array $calibration = null,
    ?array $explanationTask = null,
): array {
    $synthetic = $execution === null;
    if ($synthetic && !agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_SYNTHETIC_EXECUTION_TEST_ONLY');
    }

    if (!in_array($testFailureMode, [null, 'generate', 'generate-and-cleanup', 'cleanup'], true)) {
        throw new RuntimeException('Synthetic controller failure mode is not one fixed test control.');
    }

    $root = agentEvaluationControllerExistingRoot($repositoryRoot, 'controller repository root');
    $freshRunRoot = agentEvaluationControllerFreshAbsoluteTarget($runRoot, 'controller run root');

    if (agentEvaluationControllerPathsOverlap($freshRunRoot, $root)) {
        throw new RuntimeException(
            'Controller run root must be separate from the maintainer repository.',
        );
    }

    if ($comparisonContext !== null && $explanationTask !== null) {
        throw new RuntimeException('One execution cannot select both comparison and explanation tasks.');
    }
    $kit = $root . '/tools/agent-evaluation';
    $explanation = $explanationTask !== null;
    $smokeTask = $comparisonContext === null && !$explanation
        ? agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID)
        : null;
    if ($comparisonContext !== null) {
        if ($synthetic) {
            throw new RuntimeException('Comparison execution requires the existing OCI runner.');
        }
        agentEvaluationRequireExactKeys($comparisonContext, ['task', 'condition', 'holdout'], 'comparison execution context');
        $task = agentEvaluationControllerAdmitComparisonTask(
            agentEvaluationRequireObject($comparisonContext, 'task', 'comparison execution context'),
            agentEvaluationRequireObject($comparisonContext, 'condition', 'comparison execution context'),
        );
        $holdout = agentEvaluationRequireObject($comparisonContext, 'holdout', 'comparison execution context');
    } elseif ($explanation) {
        $authoritativeExplanation = agentEvaluationExplanationTask($kit);
        if ($explanationTask !== $authoritativeExplanation) {
            throw new RuntimeException('Explanation execution must select its exact authoritative task.');
        }
        $task = $authoritativeExplanation;
        $holdout = null;
    } else {
        $task = $smokeTask;
        $holdout = null;
    }
    if ($task === null) {
        throw new RuntimeException('Controller execution requires one admitted task.');
    }
    $validatedRequest = agentEvaluationControllerValidateRequest($request, $task);
    if ($calibration !== null) {
        if ($comparisonContext === null) {
            throw new RuntimeException('Calibration requires the existing live fixture lifecycle.');
        }
        agentEvaluationControllerValidateCalibrationPolicy($calibration);
    }
    $calibrationRevision = $calibration === null ? 1 : agentEvaluationControllerCalibrationRevision($calibration);
    $validatedProfile = agentEvaluationControllerValidateProfile($profile, $task, $synthetic, $calibration !== null, $calibrationRevision);
    $directory = agentEvaluationRequireString($task, 'directory', 'controller task');
    $promptDescriptor = agentEvaluationRequireObject($task, 'prompt', 'controller task');
    $rubricDescriptor = agentEvaluationRequireObject($task, 'rubric', 'controller task');
    $promptPath = $directory . '/' . agentEvaluationRequireString($promptDescriptor, 'path', 'controller prompt');
    $taskManifestPath = $directory . '/task.json';
    $rubricPath = $directory . '/' . agentEvaluationRequireString($rubricDescriptor, 'path', 'controller rubric');
    $scorerPath = $smokeTask === null ? null : $smokeTask['directory'] . '/' . $smokeTask['public_scorer']['path'];
    $taskBudgets = agentEvaluationValidateBudgets(agentEvaluationRequireObject($task, 'budgets', 'controller task'), $validatedRequest['task_id']);
    $prompt = file_get_contents($promptPath);
    $taskManifest = file_get_contents($taskManifestPath);
    $rubric = file_get_contents($rubricPath);

    if (!is_string($prompt) || !is_string($taskManifest) || !is_string($rubric)) {
        throw new RuntimeException('Controller task, prompt, or rubric is unreadable.');
    }

    agentEvaluationRequireFileHash($promptPath, agentEvaluationRequireString($promptDescriptor, 'sha256', 'controller prompt'), 'controller prompt');
    agentEvaluationRequireFileHash($rubricPath, agentEvaluationRequireString($rubricDescriptor, 'sha256', 'controller rubric'), 'controller rubric');
    $sourcePrompt = $prompt;
    $workspacePolicy = agentEvaluationControllerWorkspacePolicy($task);
    $prompt = $explanation
        ? agentEvaluationExplanationEffectivePrompt($sourcePrompt)
        : agentEvaluationControllerGenerationPrompt(
            $sourcePrompt,
            $workspacePolicy,
            $calibration === null ? null : $calibrationRevision,
        );
    if ($scorerPath !== null) {
        agentEvaluationRequireFileHash($scorerPath, $smokeTask['public_scorer']['sha256'], 'controller public scorer');
    }

    $workspace = null;
    $oci = null;
    $controlRoot = null;
    /** @var list<string> $observedPhases */
    $observedPhases = [];
    $phase = 'prepare';
    $primaryFailure = null;
    $cleanupFailure = null;
    /** @var array{status: string, removed: list<string>} $cleanup */
    $cleanup = ['status' => 'not_started', 'removed' => []];
    $success = null;
    $generation = null;
    $comparisonResults = null;
    $score = null;
    $explanationRunRecord = null;
    $explanationRunRecordHash = null;
    $explanationScoreChecks = null;
    $interruptState = $synthetic ? null : agentEvaluationControllerInstallInterruptHandlers();

    try {
        agentEvaluationControllerEnterPhase($observedPhases, 'prepare');
        $sourceFixture = $explanation ? $root : ($comparisonContext === null
            ? $root . '/skeleton'
            : agentEvaluationRequireString(
                agentEvaluationRequireObject($task, 'base', 'comparison task'),
                'directory',
                'comparison base',
            ));
        $workspace = agentEvaluationControllerPrepareWorkspace(
            $sourceFixture,
            $preparedDependencies,
            $runRoot,
            $task,
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            $explanation ? 'tracked-source.manifest' : 'source-skeleton.manifest',
            $workspace['baseline_manifest'],
        );
        agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'prompt.md', $prompt);
        agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'source-prompt.md', $sourcePrompt);
        agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'workspace-policy.json',
            agentEvaluationJson(agentEvaluationControllerWorkspacePolicyEvidence($workspacePolicy, $explanation)));
        if ($calibration !== null) {
            agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'calibration.json', agentEvaluationJson($calibration));
        }
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'task.json',
            $taskManifest,
        );
        agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'rubric.md', $rubric);
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'profile.json',
            agentEvaluationJson($validatedProfile),
        );
        agentEvaluationRequireFileHash($workspace['evidence_root'] . '/task.json',
            agentEvaluationRequireString($task, 'manifest_sha256', 'generation source task'), 'generation source task');
        agentEvaluationControllerValidateEffectivePromptEvidence(
            $workspace['evidence_root'],
            $task,
            $comparisonContext === null ? null : agentEvaluationRequireString($task, 'selected_condition', 'generation condition'),
            $calibration === null ? null : $calibrationRevision,
        );

        if ($execution !== null) {
            $lockPath = agentEvaluationRequireString($execution, 'prepared_lock', 'controller live configuration');
            agentEvaluationRequireBoundedFile($lockPath, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'live prepared lock');
            $lockBytes = file_get_contents($lockPath);
            $lockHash = agentEvaluationRequireString($execution, 'prepared_lock_sha256', 'controller live configuration');
            $dependencyHash = agentEvaluationRequireString($execution, 'prepared_dependencies_sha256', 'controller live configuration');
            if (!is_string($lockBytes) || !hash_equals($lockHash, hash('sha256', $lockBytes)) || !hash_equals($dependencyHash, $workspace['dependency_manifest_sha256'])) {
                throw new RuntimeException('Live prepared inputs changed before generation.');
            }
            if ($explanation) {
                $dependencyProvenance = agentEvaluationControllerValidateExplanationDependencyProvenance(
                    $workspace['candidate_root'],
                    $workspace['dependencies_root'],
                    $lockPath,
                    $lockHash,
                );
                $installedMetadata = file_get_contents(
                    $workspace['dependencies_root'] . '/composer/installed.json',
                );
                if (
                    !is_string($installedMetadata)
                    || !hash_equals(
                        $dependencyProvenance['installed_metadata_sha256'],
                        hash('sha256', $installedMetadata),
                    )
                ) {
                    throw new RuntimeException(
                        'Explanation prepared Composer metadata changed before retention.',
                    );
                }
                agentEvaluationControllerWriteArtifact(
                    $workspace['evidence_root'],
                    'dependencies.installed.json',
                    $installedMetadata,
                );
            }
            agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'dependencies.lock', $lockBytes);
            $controlRoot = agentEvaluationControllerCreatePreflightRoot();
            $engine = agentEvaluationControllerOciPreflight(
                agentEvaluationRequireObject($execution, 'engine', 'controller live configuration'),
                $controlRoot,
            );
            agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'oci-preflight.json', agentEvaluationJson($engine['identity']));
            if ($comparisonContext !== null) {
                agentEvaluationControllerValidateComparisonDatabase(agentEvaluationRequireObject($engine, 'identity', 'comparison engine'));
            }
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'], 'approval.json',
                agentEvaluationJson(agentEvaluationRequireObject($execution, 'approval', 'controller live configuration')),
            );
            $oci = agentEvaluationControllerOciPrepare(
                $engine, $validatedRequest['run_id'], $workspace['candidate_root'], $workspace['dependencies_root'],
                $explanation,
            );
        }

        $phase = 'generate';
        agentEvaluationControllerEnterPhase($observedPhases, 'generate');
        agentEvaluationControllerInjectSyntheticFailure($workspace, $testFailureMode);
        $startedAt = agentEvaluationControllerUtcNow();
        $runnerName = $validatedProfile['runner']['name'];
        if ($runnerName === AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_GEMINI || $runnerName === AGENT_EVALUATION_CONTROLLER_RUNNER_GEMINI) {
            $modelId = agentEvaluationRequireString($validatedProfile['model'], 'id', 'controller model profile');
            $modelSettings = agentEvaluationValueObject($validatedProfile['model']['settings'] ?? null, 'controller model settings');
            $thinkingBudget = $synthetic ? 'high' : agentEvaluationRequireString($modelSettings, 'thinking_budget', 'controller model settings');
            $generation = $oci === null ? agentEvaluationControllerRunGemini(
                $workspace['candidate_root'],
                $prompt,
                $modelId,
                $thinkingBudget,
                $taskBudgets,
                $validatedProfile['isolation'],
                true,
            ) : throw new RuntimeException(
                AGENT_EVALUATION_CONTROLLER_LIVE_GEMINI_UNAVAILABLE
                . ': live OCI execution for gemini-exec is not yet supported in controller',
            );
        } else {
            $generation = $oci === null ? agentEvaluationControllerRunCodex(
                $workspace['candidate_root'],
                $prompt,
                agentEvaluationRequireString($validatedProfile['model'], 'id', 'controller model profile'),
                'high',
                $taskBudgets,
                $validatedProfile['isolation'],
                true,
            ) : agentEvaluationControllerRunLiveCodex(
                $oci,
                $prompt,
                $validatedProfile,
                $credential,
                $explanation
                    ? agentEvaluationControllerExplanationSpending()
                    : ($calibration === null ? null : agentEvaluationControllerCalibrationSpending()),
            );
        }
        $finishedAt = agentEvaluationControllerUtcNow();
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'events.jsonl',
            $generation['events_jsonl'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation.stderr',
            $generation['process']['stderr'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation-process.json',
            agentEvaluationJson([
                ...$generation['process'],
                'stdout' => 'retained separately as events.jsonl',
                'stderr' => 'retained separately as generation.stderr',
            ]),
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'response.txt',
            $generation['response'] . "\n",
        );
        if (!$synthetic) {
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'], 'proxy.json',
                agentEvaluationJson(agentEvaluationRequireObject($generation, 'proxy_evidence', 'live generation evidence')),
            );
        }

        if ($generation['termination_reason'] !== 'completed') {
            throw new RuntimeException('Generation did not complete within every fixed bound.');
        }

        if ($explanation) {
            agentEvaluationControllerValidateExplanationResponse(
                $generation['events'],
                agentEvaluationRequireString($generation, 'response', 'explanation generation'),
            );
        }

        $externalActionsApproved = $synthetic
            ? ($explanation
                ? agentEvaluationControllerExplanationActionsApproved($generation['events'])
                : agentEvaluationControllerSyntheticExternalActionsApproved($generation['events']))
            : ($generation['external_actions_approved'] ?? false) === true
                && (!$explanation || agentEvaluationControllerExplanationActionsApproved($generation['events']));

        if (!$externalActionsApproved) {
            throw new RuntimeException('Generation reported an unapproved action.');
        }
        if ($synthetic) {
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'],
                'external-actions.json',
                agentEvaluationJson($explanation ? [
                    'approved' => true,
                    'network_attempts' => 0,
                    'process_tool_calls' => 0,
                    'file_change_events' => 0,
                    'changed_paths' => [],
                ] : [
                    'approved' => true,
                    'network_attempts' => 0,
                    'process_tool_calls' => 0,
                    'changed_paths' => ['src/HealthRoutes.php', 'src/PingHandler.php', 'tests/run.php'],
                ]),
            );
        } else {
            $externalActionEvidence = agentEvaluationRequireObject(
                $generation,
                'external_actions',
                'live generation evidence',
            );
            if ($explanation) {
                $externalActionEvidence['approved'] = $externalActionsApproved;
                $externalActionEvidence['file_change_events'] = agentEvaluationControllerExplanationFileChangeEvents(
                    $generation['events'],
                );
            }
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'],
                'external-actions.json',
                agentEvaluationJson($externalActionEvidence),
            );
        }

        $phase = 'freeze';
        agentEvaluationControllerEnterPhase($observedPhases, 'freeze');
        agentEvaluationRequireFileHash($workspace['evidence_root'] . '/task.json',
            agentEvaluationRequireString($task, 'manifest_sha256', 'generation source task'), 'generation source task');
        agentEvaluationControllerValidateEffectivePromptEvidence(
            $workspace['evidence_root'],
            $task,
            $comparisonContext === null ? null : agentEvaluationRequireString($task, 'selected_condition', 'generation condition'),
            $calibration === null ? null : $calibrationRevision,
        );
        if ($oci !== null) {
            agentEvaluationControllerOciStopGeneration($oci);
            agentEvaluationControllerOciExportCandidate($oci, $workspace['candidate_root']);
        }
        $freeze = agentEvaluationControllerFreezeWorkspace($workspace, $task);
        if ($explanation) {
            if ($freeze['changed_files'] !== [] || $freeze['added_lines'] !== 0 || $freeze['deleted_lines'] !== 0) {
                throw new RuntimeException('Explanation generation changed its pinned read-only workspace.');
            }
            $freeze['patch'] = '';
            $freeze['patch_sha256'] = hash('sha256', '');
        }
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'candidate.patch',
            $freeze['patch'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'candidate.manifest',
            $freeze['candidate_manifest'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'freeze.json',
            agentEvaluationJson([
                'candidate_sha256' => $freeze['candidate_sha256'],
                'patch_sha256' => $freeze['patch_sha256'],
                'changed_files' => $freeze['changed_files'],
                'added_lines' => $freeze['added_lines'],
                'deleted_lines' => $freeze['deleted_lines'],
            ]),
        );
        $scoringWorkspace = $explanation ? null : agentEvaluationControllerCreateScoringWorkspace(
            $workspace,
            $workspace['run_root'] . '/scoring',
            $freeze,
        );
        $ociGenerationCleanup = $oci === null ? null : agentEvaluationControllerOciDestroyGeneration($oci);
        $generationRemoved = agentEvaluationControllerRemoveGenerationWorkspace($workspace);
        $generationCleanup = count($generationRemoved) === 3
            && ($ociGenerationCleanup === null || $ociGenerationCleanup['status'] === 'pass');
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation-cleanup.json',
            agentEvaluationJson([
                'status' => $generationCleanup ? 'pass' : 'fail',
                ...($ociGenerationCleanup === null ? [] : ['oci' => $ociGenerationCleanup]),
                'removed' => array_map(
                    static fn (string $path): string => basename($path),
                    $generationRemoved,
                ),
            ]),
        );

        $phase = 'score';
        agentEvaluationControllerEnterPhase($observedPhases, 'score');
        if ($explanation) {
            if (!$generationCleanup) {
                throw new RuntimeException('Explanation scoring requires complete generation cleanup.');
            }
            $explanationScoreChecks = ['task_identity' => true, 'response_integrity' => true,
                'workspace_unchanged' => true, 'resource_bounds' => true,
                'external_actions_approved' => $externalActionsApproved, 'cleanup' => false];
        } elseif ($comparisonContext !== null) {
            if (!$generationCleanup) {
                throw new RuntimeException('Comparison scoring requires destroyed generation and its pinned holdout.');
            }
            if ($scoringWorkspace === null) {
                throw new RuntimeException('Comparison scoring workspace is unavailable.');
            }
            $comparisonResults = agentEvaluationControllerScoreComparisonCandidate(
                $oci, $scoringWorkspace['candidate_root'], $holdout, $workspace['evidence_root'],
            );
        } else {
        if ($scoringWorkspace === null || $scorerPath === null) {
            throw new RuntimeException('Implementation scoring inputs are unavailable.');
        }
        agentEvaluationRequireFileHash(
            $scorerPath,
            $smokeTask['public_scorer']['sha256'],
            'pre-score public scorer',
        );
        $scoringChecks = [
            'manifest_valid' => true,
            'workspace_policy' => true,
            'frozen_before_scoring' => true,
            'scorer_integrity' => true,
            'external_actions_approved' => $externalActionsApproved,
            'generation_cleanup' => $generationCleanup,
        ];
        $score = $oci === null ? agentEvaluationControllerScoreFrozenCandidate(
            $scoringWorkspace['candidate_root'],
            $scorerPath,
            $scoringChecks,
            $taskBudgets,
            $validatedProfile['isolation'],
            true,
        ) : agentEvaluationControllerScoreLiveCandidate(
            $oci, $scoringWorkspace['candidate_root'], $scorerPath, $scoringChecks, $validatedProfile,
            $workspace['evidence_root'],
        );
        agentEvaluationRequireFileHash(
            $scorerPath,
            $smokeTask['public_scorer']['sha256'],
            'post-score public scorer',
        );
        if ($synthetic) {
            agentEvaluationControllerRetainScoringEvidence($workspace['evidence_root'], $score['evidence']);
        }
        }
        if (!$explanation) {
            if ($scoringWorkspace === null) {
                throw new RuntimeException('Implementation scoring workspace is unavailable.');
            }
            agentEvaluationControllerValidateReadOnlyScoringCandidate(
                $scoringWorkspace['candidate_root'], $freeze['candidate_manifest'], $freeze['candidate_sha256'],
            );
        }

        $phase = 'validate';
        agentEvaluationControllerEnterPhase($observedPhases, 'validate');
        if ($explanation) {
            $explanationRunRecord = agentEvaluationControllerExplanationRunRecord(
                $validatedRequest,
                $validatedProfile,
                $task,
                $workspace,
                $generation,
                $startedAt,
                $finishedAt,
                $synthetic,
            );
            $runRecordPath = agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'],
                'run.json',
                agentEvaluationJson($explanationRunRecord),
            );
            agentEvaluationValidateExplanationRunRecord($explanationRunRecord, $task);
            agentEvaluationValidateExplanationRunArtifacts($explanationRunRecord, $workspace['evidence_root']);
            $explanationRunRecordHash = agentEvaluationFileHash($runRecordPath, 'explanation run record');
            $phase = 'retain';
            agentEvaluationControllerEnterPhase($observedPhases, 'retain');
            $success = ['run_id' => $validatedRequest['run_id'], 'run_record_path' => $runRecordPath,
                'score_record_path' => $workspace['evidence_root'] . '/score.json', 'automated_status' => 'fail',
                'human_review' => 'pending'];
        } elseif ($comparisonContext !== null) {
            agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'validation.json', agentEvaluationJson([
                'frozen_candidate' => 'pass', 'task_admission' => 'pass',
                'attempt_record' => 'campaign-finalization-after-cleanup',
            ]));
            $phase = 'retain';
            agentEvaluationControllerEnterPhase($observedPhases, 'retain');
            $success = ['run_id' => $validatedRequest['run_id']];
        } else {
        if ($smokeTask === null || $score === null) {
            throw new RuntimeException('Implementation run validation inputs are unavailable.');
        }
        $runRecord = agentEvaluationControllerRunRecord(
            $validatedRequest,
            $validatedProfile,
            $smokeTask,
            $workspace,
            $generation,
            $startedAt,
            $finishedAt,
        );
        $runRecordPath = agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'run.json',
            agentEvaluationJson($runRecord),
        );
        agentEvaluationValidateRunRecord($runRecord, $smokeTask);
        agentEvaluationValidateRunArtifacts($runRecord, $workspace['evidence_root']);
        $runRecordHash = agentEvaluationFileHash($runRecordPath, 'controller run record');
        $scoreRecord = agentEvaluationControllerScoreRecord(
            $validatedRequest,
            $smokeTask,
            $runRecord,
            $runRecordHash,
            $score,
            $synthetic,
        );
        $scoreRecordPath = agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'score.json',
            agentEvaluationJson($scoreRecord),
        );
        agentEvaluationValidateScoreRecord($scoreRecord, $smokeTask, $runRecord, $runRecordHash);
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'validation.json',
            agentEvaluationJson([
                'v1_run_record' => 'pass',
                'v1_score_record' => 'pass',
            ]),
        );

        $phase = 'retain';
        agentEvaluationControllerEnterPhase($observedPhases, 'retain');
        $success = [
            'run_id' => $validatedRequest['run_id'],
            'run_record_path' => $runRecordPath,
            'score_record_path' => $scoreRecordPath,
            'automated_status' => agentEvaluationRequireString(
                $scoreRecord,
                'automated_status',
                'controller score record',
            ),
            'weighted_score' => agentEvaluationRequireInteger(
                $scoreRecord,
                'weighted_score',
                'controller score record',
            ),
        ];
        }
        agentEvaluationControllerInjectSyntheticCleanupFailure($workspace, $testFailureMode);
    } catch (Throwable $throwable) {
        $primaryFailure = [
            'phase' => $phase,
            'class' => $throwable::class,
        ];
        if (preg_match('/\AAGENT_EVALUATION_CONTROLLER_[A-Z0-9_]+\z/D', $throwable->getMessage()) === 1) {
            $primaryFailure['code'] = $throwable->getMessage();
        }
        $freezeReason = $phase === 'freeze' ? agentEvaluationControllerFreezeFailureReason($throwable) : null;
        if ($freezeReason !== null) {
            $primaryFailure['reason_code'] = $freezeReason;
        }
    } finally {
        if ($oci !== null) {
            try {
                $ociCleanup = agentEvaluationControllerOciCleanup($oci);
                if (is_array($workspace)) {
                    agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'oci-cleanup.json', agentEvaluationJson($ociCleanup));
                }
                if ($ociCleanup['status'] !== 'pass') {
                    throw new RuntimeException('OCI resource cleanup was not verified.');
                }
            } catch (Throwable $throwable) {
                $cleanupFailure = ['class' => $throwable::class];
            }
        }
        if ($controlRoot !== null) {
            try {
                $ledger = agentEvaluationControllerReadOciRecoveryLedger($controlRoot);
                if ($ledger !== null && is_array($workspace)) {
                    agentEvaluationControllerWriteArtifact(
                        $workspace['evidence_root'], 'owned-resources.json', agentEvaluationJson($ledger),
                    );
                    if ($ledger['containers'] !== [] || $ledger['volumes'] !== []) {
                        $cleanupFailure = ['class' => RuntimeException::class];
                    }
                }
                agentEvaluationControllerRemoveTree($controlRoot);
            } catch (Throwable $throwable) {
                $cleanupFailure = ['class' => $throwable::class];
            }
        }
        if (is_array($workspace)) {
            try {
                agentEvaluationControllerEnterCleanupPhase($observedPhases);
                $cleanup = [
                    'status' => $cleanupFailure === null ? 'pass' : 'fail',
                    'removed' => agentEvaluationControllerCleanupWorkspace($workspace),
                ];
            } catch (Throwable $throwable) {
                $cleanup = ['status' => 'fail', 'removed' => []];
                $cleanupFailure = ['class' => $throwable::class];
            }

            if (is_dir($workspace['evidence_root']) && !is_link($workspace['evidence_root'])) {
                try {
                    agentEvaluationControllerWriteArtifact(
                        $workspace['evidence_root'],
                        'cleanup.json',
                        agentEvaluationJson([
                            'status' => $cleanup['status'],
                            'removed' => array_map(
                                static fn (string $path): string => basename($path),
                                $cleanup['removed'],
                            ),
                            'primary_failure' => $primaryFailure,
                            'cleanup_failure' => $cleanupFailure,
                        ]),
                    );
                    if ($explanation
                        && $explanationRunRecord !== null
                        && $explanationRunRecordHash !== null
                        && $explanationScoreChecks !== null) {
                        $structuralEvidence = agentEvaluationControllerRetainExplanationStructuralEvidence(
                            $workspace['evidence_root'],
                            $synthetic,
                        );
                        $finalScore = agentEvaluationControllerRetainExplanationScore(
                            $workspace['evidence_root'],
                            $validatedRequest,
                            $task,
                            $explanationRunRecord,
                            $explanationRunRecordHash,
                            $structuralEvidence['sha256'],
                            $explanationScoreChecks,
                            $cleanup['status'] === 'pass' && $cleanupFailure === null,
                        );
                        if ($success !== null) {
                            $success['score_record_path'] = $finalScore['path'];
                            $success['automated_status'] = $finalScore['automated_status'];
                        }
                    }
                    $manifest = agentEvaluationControllerEvidenceManifest(
                        $workspace['evidence_root'],
                        $validatedRequest['run_id'],
                        $observedPhases,
                        $primaryFailure,
                        $cleanupFailure,
                        $synthetic,
                        $comparisonContext !== null || $explanation ? $task : null,
                    );
                    if ($calibration !== null) {
                        $manifest['comparison_execution'] = false;
                        $manifest['calibration_execution'] = true;
                    }
                    agentEvaluationControllerWriteArtifact(
                        $workspace['evidence_root'],
                        'evidence-manifest.json',
                        agentEvaluationJson($manifest),
                    );
                } catch (Throwable $throwable) {
                    $cleanupFailure ??= ['class' => $throwable::class];
                    $cleanup['status'] = 'fail';
                    if ($explanation
                        && $explanationRunRecord !== null
                        && $explanationRunRecordHash !== null
                        && $explanationScoreChecks !== null) {
                        try {
                            agentEvaluationControllerReplaceFinalArtifact(
                                $workspace['evidence_root'],
                                'cleanup.json',
                                agentEvaluationJson([
                                    'status' => 'fail',
                                    'removed' => array_map(
                                        static fn (string $path): string => basename($path),
                                        $cleanup['removed'],
                                    ),
                                    'primary_failure' => $primaryFailure,
                                    'cleanup_failure' => $cleanupFailure,
                                ]),
                            );
                            $structuralEvidence = agentEvaluationControllerRetainExplanationStructuralEvidence(
                                $workspace['evidence_root'],
                                $synthetic,
                            );
                            $finalScore = agentEvaluationControllerRetainExplanationScore(
                                $workspace['evidence_root'],
                                $validatedRequest,
                                $task,
                                $explanationRunRecord,
                                $explanationRunRecordHash,
                                $structuralEvidence['sha256'],
                                $explanationScoreChecks,
                                false,
                            );
                            if ($success !== null) {
                                $success['score_record_path'] = $finalScore['path'];
                                $success['automated_status'] = 'fail';
                            }
                            $manifest = agentEvaluationControllerEvidenceManifest(
                                $workspace['evidence_root'],
                                $validatedRequest['run_id'],
                                $observedPhases,
                                $primaryFailure,
                                $cleanupFailure,
                                $synthetic,
                                $task,
                            );
                            agentEvaluationControllerReplaceFinalArtifact(
                                $workspace['evidence_root'],
                                'evidence-manifest.json',
                                agentEvaluationJson($manifest),
                            );
                        } catch (Throwable) {
                            // The original finalization failure remains authoritative.
                        }
                    }
                }
            }
        }
    }

    if ($interruptState !== null) {
        agentEvaluationControllerRestoreInterruptHandlers($interruptState);
    }

    if ($comparisonContext !== null) {
        $completed = $primaryFailure === null && $cleanupFailure === null && $success !== null && $workspace !== null;
        $generationUsage = $generation === null
            ? ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null]
            : $generation['usage'];
        $termination = $completed ? 'completed' : ($cleanupFailure !== null ? 'cleanup_failed'
            : ($primaryFailure['code'] ?? ($generation !== null && $generation['termination_reason'] !== 'completed'
                ? $generation['termination_reason'] : 'process_failed')));
        return ['run_id' => $validatedRequest['run_id'], 'evidence_root' => $workspace['evidence_root'] ?? null,
            'status' => $completed ? 'complete' : 'failed', 'phase' => $completed ? 'finished' : ($cleanupFailure !== null ? 'cleanup' : $phase),
            'termination_reason' => $termination, 'generation_usage' => $generationUsage,
            'generation_elapsed_milliseconds' => $generation['process']['elapsed_milliseconds'] ?? null,
            'comparison_results' => $comparisonResults, 'cleanup' => $cleanup,
            'primary_failure' => $primaryFailure, 'cleanup_failure' => $cleanupFailure,
            'evidence_manifest_path' => $workspace === null ? null : $workspace['evidence_root'] . '/evidence-manifest.json'];
    }
    if ($primaryFailure !== null || $cleanupFailure !== null) {
        throw new RuntimeException(agentEvaluationControllerFailureMessage($primaryFailure, $cleanupFailure));
    }
    if ($success === null || $workspace === null) {
        throw new RuntimeException('Controller completion requires retained results and a prepared workspace.');
    }

    $result = [
        'run_id' => $success['run_id'],
        'evidence_root' => $workspace['evidence_root'],
        'run_record_path' => agentEvaluationRequireString($success, 'run_record_path', 'smoke completion'),
        'score_record_path' => agentEvaluationRequireString($success, 'score_record_path', 'smoke completion'),
        'evidence_manifest_path' => $workspace['evidence_root'] . '/evidence-manifest.json',
        'automated_status' => agentEvaluationRequireString($success, 'automated_status', 'smoke completion'),
        'cleanup' => $cleanup,
    ];
    if ($explanation) {
        $result['human_review'] = agentEvaluationRequireString(
            $success,
            'human_review',
            'explanation completion',
        );
    } else {
        $result['weighted_score'] = agentEvaluationRequireInteger($success, 'weighted_score', 'smoke completion');
    }
    return $result;
}

/** @return array{owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>}|null */
function agentEvaluationControllerReadOciRecoveryLedger(string $controlRoot): ?array
{
    $path = $controlRoot . '/owned-resources.json';
    if (!file_exists($path) && !is_link($path)) {
        return null;
    }
    agentEvaluationRequireBoundedFile($path, 32_768, 'OCI recovery ledger');
    $ledger = agentEvaluationJsonFile($path);
    agentEvaluationRequireExactKeys($ledger, ['owner', 'run_id', 'containers', 'volumes'], 'OCI recovery ledger');
    $owner = agentEvaluationRequireNonEmptyString($ledger, 'owner', 'OCI recovery ledger');
    $runId = agentEvaluationRequireNonEmptyString($ledger, 'run_id', 'OCI recovery ledger');
    $resources = [];
    foreach (['containers', 'volumes'] as $kind) {
        $resources[$kind] = [];
        $members = $ledger[$kind] === [] ? [] : agentEvaluationRequireObject($ledger, $kind, 'OCI recovery ledger');
        foreach ($members as $role => $name) {
            if (!is_string($name) || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}\z/D', $name) !== 1) {
                throw new RuntimeException('OCI recovery ledger contains an invalid resource name.');
            }
            $resources[$kind][$role] = $name;
        }
    }
    return ['owner' => $owner, 'run_id' => $runId, 'containers' => $resources['containers'], 'volumes' => $resources['volumes']];
}

/**
 * @param array<string, mixed> $workspace
 */
function agentEvaluationControllerInjectSyntheticFailure(array $workspace, ?string $mode): void
{
    if ($mode === null || $mode === 'cleanup') {
        return;
    }

    if ($mode === 'generate-and-cleanup') {
        $runRoot = agentEvaluationRequireString($workspace, 'run_root', 'controller workspace');
        $unexpected = $runRoot . '/unexpected-cleanup.control';

        if (file_put_contents($unexpected, "fixed cleanup failure\n", LOCK_EX) === false) {
            throw new RuntimeException('Unable to prepare the fixed cleanup-failure control.');
        }
    }

    throw new RuntimeException('Fixed synthetic generation failure.');
}

/** @param array<string, mixed> $workspace */
function agentEvaluationControllerInjectSyntheticCleanupFailure(array $workspace, ?string $mode): void
{
    if ($mode !== 'cleanup') {
        return;
    }

    $runRoot = agentEvaluationRequireString($workspace, 'run_root', 'controller workspace');
    $unexpected = $runRoot . '/unexpected-cleanup.control';

    if (file_put_contents($unexpected, "fixed final cleanup failure\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to prepare the fixed final cleanup-failure control.');
    }
}

/**
 * @param array{phase: string, class: string}|null $primaryFailure
 * @param array{class: string}|null $cleanupFailure
 */
function agentEvaluationControllerFailureMessage(?array $primaryFailure, ?array $cleanupFailure): string
{
    $primary = $primaryFailure === null
        ? 'none'
        : $primaryFailure['phase'] . ':' . $primaryFailure['class'];
    $cleanup = $cleanupFailure === null ? 'none' : $cleanupFailure['class'];

    return "AGENT_EVALUATION_CONTROLLER_RUN_FAILED primary={$primary} cleanup={$cleanup}";
}

/** @param list<string> $observed */
function agentEvaluationControllerEnterPhase(array &$observed, string $phase): void
{
    $expected = AGENT_EVALUATION_CONTROLLER_PHASES[count($observed)] ?? null;

    if ($phase !== $expected) {
        throw new RuntimeException('Controller phase order changed before ' . $phase . '.');
    }

    $observed[] = $phase;
}

/** @param list<string> $observed */
function agentEvaluationControllerEnterCleanupPhase(array &$observed): void
{
    if (in_array('cleanup', $observed, true)) {
        throw new RuntimeException('Controller cleanup phase was entered more than once.');
    }

    $observed[] = 'cleanup';
}

function agentEvaluationControllerUtcNow(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
}

function agentEvaluationControllerCreatePreflightRoot(): string
{
    $base = realpath(sys_get_temp_dir());
    if (!is_string($base)) {
        throw new RuntimeException('Controller temporary base is unavailable.');
    }
    $target = $base . '/phpthis-oci-control-' . bin2hex(random_bytes(16));
    if (!mkdir($target, 0700) || !chmod($target, 0700)) {
        throw new RuntimeException('Unable to create the private OCI control directory.');
    }
    return $target;
}

/**
 * @param array<string, mixed> $configuration
 * @param array<string, mixed> $task
 */
function agentEvaluationControllerValidateExplanationPreflightInputs(
    string $repositoryRoot,
    array $configuration,
    array $task,
): void {
    $temporaryRoot = agentEvaluationControllerCreatePreflightRoot();

    try {
        $workspace = agentEvaluationControllerPrepareWorkspace(
            $repositoryRoot,
            agentEvaluationRequireString(
                $configuration,
                'prepared_dependencies',
                'controller explanation configuration',
            ),
            $temporaryRoot . '/workspace',
            $task,
        );
        $dependencyHash = agentEvaluationRequireString(
            $configuration,
            'prepared_dependencies_sha256',
            'controller explanation configuration',
        );
        if (!hash_equals($dependencyHash, $workspace['dependency_manifest_sha256'])) {
            throw new RuntimeException('Live prepared inputs changed before generation.');
        }
        agentEvaluationControllerValidateExplanationDependencyProvenance(
            $workspace['candidate_root'],
            $workspace['dependencies_root'],
            agentEvaluationRequireString(
                $configuration,
                'prepared_lock',
                'controller explanation configuration',
            ),
            agentEvaluationRequireString(
                $configuration,
                'prepared_lock_sha256',
                'controller explanation configuration',
            ),
        );
    } finally {
        agentEvaluationControllerRemoveTree($temporaryRoot);
    }
}

function agentEvaluationControllerWriteArtifact(string $evidenceRoot, string $name, string $bytes): string
{
    agentEvaluationRequireRelativePath($name, 'controller evidence filename');

    $emptyStream = $bytes === '' && in_array(
        $name,
        ['events.jsonl', 'generation.stderr', 'candidate.patch'],
        true,
    );
    if (str_contains($name, '/') || ($bytes === '' && !$emptyStream) || strlen($bytes) > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
        throw new RuntimeException('Controller evidence artifact must be bounded flat content; only observed streams may be empty.');
    }

    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'controller evidence root');
    $path = $root . '/' . $name;

    if (file_exists($path) || is_link($path)) {
        throw new RuntimeException("Controller evidence artifact already exists: {$name}.");
    }

    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($path, 0600)) {
        throw new RuntimeException("Unable to retain controller evidence artifact {$name}.");
    }

    return agentEvaluationControllerValidateRetainedArtifact(
        $root,
        $name,
        AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
    );
}

function agentEvaluationControllerReplaceFinalArtifact(
    string $evidenceRoot,
    string $name,
    string $bytes,
): string {
    if (!in_array($name, [
        'cleanup.json', 'structural-evidence.json', 'score.json', 'validation.json', 'evidence-manifest.json',
    ], true)
        || $bytes === '' || strlen($bytes) > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
        throw new RuntimeException('Controller final artifact replacement is outside its fixed boundary.');
    }
    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'controller evidence root');
    $path = $root . '/' . $name;
    if (!file_exists($path) && !is_link($path)) {
        return agentEvaluationControllerWriteArtifact($root, $name, $bytes);
    }
    agentEvaluationControllerValidateRetainedArtifact(
        $root,
        $name,
        AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
    );
    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($path, 0600)) {
        throw new RuntimeException("Unable to replace controller final artifact {$name}.");
    }
    return agentEvaluationControllerValidateRetainedArtifact(
        $root,
        $name,
        AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
    );
}

/** @return array{document: array<string, mixed>, path: string, sha256: string} */
function agentEvaluationControllerRetainExplanationStructuralEvidence(
    string $evidenceRoot,
    bool $synthetic,
): array {
    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'explanation evidence root');
    $document = agentEvaluationExplanationStructuralEvidenceDocument(
        $synthetic ? 'synthetic-control' : 'live-model',
        $root,
    );
    $path = agentEvaluationControllerReplaceFinalArtifact(
        $root,
        'structural-evidence.json',
        agentEvaluationJson($document),
    );
    return ['document' => $document, 'path' => $path,
        'sha256' => agentEvaluationFileHash($path, 'explanation structural evidence')];
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $task
 * @param array<string, mixed> $runRecord
 * @param array<string, bool> $checks
 * @return array{path: string, automated_status: string}
 */
function agentEvaluationControllerRetainExplanationScore(
    string $evidenceRoot,
    array $request,
    array $task,
    array $runRecord,
    string $runRecordHash,
    string $structuralEvidenceHash,
    array $checks,
    bool $cleanupPassed,
): array {
    $checks['cleanup'] = $cleanupPassed;
    $score = agentEvaluationControllerExplanationScoreRecord(
        $request,
        $task,
        $runRecord,
        $runRecordHash,
        $structuralEvidenceHash,
        $checks,
    );
    $path = agentEvaluationControllerReplaceFinalArtifact(
        $evidenceRoot,
        'score.json',
        agentEvaluationJson($score),
    );
    agentEvaluationValidateExplanationScoreRecord($score, $task, $runRecord, $runRecordHash);
    agentEvaluationValidateExplanationScoreArtifacts($score, $runRecord, $evidenceRoot);
    $automatedStatus = agentEvaluationRequireString($score, 'automated_status', 'explanation score record');
    agentEvaluationControllerReplaceFinalArtifact(
        $evidenceRoot,
        'validation.json',
        agentEvaluationJson([
            'v3_run_record' => 'pass',
            'v3_score_record' => 'pass',
            'structural_status' => $automatedStatus,
            'semantic_review' => 'pending',
        ]),
    );
    return ['path' => $path, 'automated_status' => $automatedStatus];
}

/** @param list<array<string, mixed>> $events */
function agentEvaluationControllerSyntheticExternalActionsApproved(array $events): bool
{
    $allowedPaths = ['src/HealthRoutes.php', 'src/PingHandler.php', 'tests/run.php'];
    $fileChanges = 0;
    $messages = 0;

    foreach ($events as $event) {
        $eventType = $event['type'] ?? null;

        if (in_array($eventType, ['thread.started', 'turn.started', 'turn.completed'], true)) {
            continue;
        }

        if ($eventType !== 'item.completed') {
            return false;
        }

        $item = $event['item'] ?? null;

        if (!is_array($item) || array_is_list($item)) {
            return false;
        }

        $type = $item['type'] ?? null;

        if ($type === 'agent_message') {
            $messages++;
            continue;
        }

        if ($type !== 'file_change') {
            return false;
        }

        $paths = $item['paths'] ?? null;

        if (!is_array($paths) || !array_is_list($paths) || $paths !== $allowedPaths) {
            return false;
        }

        $fileChanges++;
    }

    return $fileChanges === 1 && $messages === 1;
}

/** @param list<array<string, mixed>> $events */
function agentEvaluationControllerValidateExplanationResponse(array $events, string $response): void
{
    $messages = [];
    foreach ($events as $event) {
        if (($event['type'] ?? null) !== 'item.completed') {
            continue;
        }
        $item = $event['item'] ?? null;
        if (is_array($item) && ($item['type'] ?? null) === 'agent_message') {
            $text = $item['text'] ?? null;
            if (!is_string($text)) {
                throw new RuntimeException('Explanation final agent message is invalid.');
            }
            $messages[] = $text;
        }
    }
    if ($messages === [] || trim($response) === '' || $messages[array_key_last($messages)] !== $response) {
        throw new RuntimeException('Explanation generation requires a nonempty final agent message.');
    }
}

/** @param list<array<string, mixed>> $events */
function agentEvaluationControllerExplanationFileChangeEvents(array $events): int
{
    $changes = 0;
    foreach ($events as $event) {
        $eventType = $event['type'] ?? null;
        if (!is_string($eventType) || !str_starts_with($eventType, 'item.')) {
            continue;
        }
        $item = $event['item'] ?? null;
        if (is_array($item) && ($item['type'] ?? null) === 'file_change') {
            $changes++;
        }
    }
    return $changes;
}

/** @param list<array<string, mixed>> $events */
function agentEvaluationControllerExplanationActionsApproved(array $events): bool
{
    foreach ($events as $event) {
        $eventType = $event['type'] ?? null;
        if (in_array($eventType, ['thread.started', 'turn.started', 'turn.completed'], true)) {
            continue;
        }
        if (!is_string($eventType) || !str_starts_with($eventType, 'item.')) {
            return false;
        }
        $item = $event['item'] ?? null;
        $type = is_array($item) ? ($item['type'] ?? null) : null;
        if (!is_string($type) || !in_array(
            $type,
            ['agent_message', 'reasoning', 'command_execution', 'todo_list'],
            true,
        )) {
            return false;
        }
    }
    return agentEvaluationControllerExplanationFileChangeEvents($events) === 0;
}

/**
 * @param array<string, mixed> $workspace
 * @return list<string>
 */
function agentEvaluationControllerRemoveGenerationWorkspace(array $workspace): array
{
    $removed = [];

    foreach (['candidate_root', 'baseline_root', 'dependencies_root'] as $field) {
        $target = $workspace[$field] ?? null;

        if (!is_string($target)) {
            throw new RuntimeException('Controller generation-cleanup target is unavailable.');
        }

        $validated = agentEvaluationControllerValidateCleanupTarget($workspace, $target);
        agentEvaluationControllerRemoveTree($validated);
        $removed[] = $validated;
    }

    return $removed;
}

/**
 * @param array<string, mixed> $evidence
 */
function agentEvaluationControllerRetainScoringEvidence(string $evidenceRoot, array $evidence): void
{
    $application = $evidence['application_check'] ?? null;
    $scorer = $evidence['public_scorer'] ?? null;
    $resource = $evidence['resource_inspection'] ?? null;

    if (!is_array($application) || !is_array($scorer) || !is_array($resource)) {
        throw new RuntimeException('Controller scoring evidence has an invalid shape.');
    }

    agentEvaluationControllerWriteArtifact(
        $evidenceRoot,
        'application-check.json',
        agentEvaluationJson($application),
    );
    agentEvaluationControllerWriteArtifact(
        $evidenceRoot,
        'public-scorer.json',
        agentEvaluationJson($scorer),
    );
    agentEvaluationControllerWriteArtifact(
        $evidenceRoot,
        'resource-inspection.json',
        agentEvaluationJson($resource),
    );
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $profile
 * @param array<string, mixed> $task
 * @param array<string, mixed> $workspace
 * @param array<string, mixed> $generation
 * @return array<string, mixed>
 */
function agentEvaluationControllerRunRecord(
    array $request,
    array $profile,
    array $task,
    array $workspace,
    array $generation,
    string $startedAt,
    string $finishedAt,
): array {
    $rubric = agentEvaluationValueObject($task['rubric'] ?? null, 'controller task rubric');
    $base = agentEvaluationValueObject($task['base'] ?? null, 'controller task base');
    $evidenceRoot = agentEvaluationRequireString($workspace, 'evidence_root', 'controller workspace');

    return [
        'schema_version' => 1,
        'run_id' => $request['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'task_manifest_sha256' => $task['manifest_sha256'],
        'rubric_sha256' => agentEvaluationRequireString($rubric, 'sha256', 'controller task rubric'),
        'base_revision' => agentEvaluationRequireString($base, 'tree', 'controller task base'),
        'base_fixture_sha256' => $workspace['base_fixture_sha256'],
        'prepared_dependencies_manifest_path' => 'prepared-dependencies.manifest',
        'prepared_dependencies_manifest_sha256' => $workspace['dependency_manifest_sha256'],
        'condition' => $profile['condition'],
        'model' => $profile['model'],
        'context' => $profile['context'],
        'tools' => $profile['tools'],
        'budgets' => $profile['budgets'],
        'usage' => $generation['usage'],
        'timing' => ['started_at' => $startedAt, 'finished_at' => $finishedAt],
        'repair_turns' => 0,
        'termination_reason' => $generation['termination_reason'],
        'events_path' => 'events.jsonl',
        'events_sha256' => agentEvaluationFileHash(
            $evidenceRoot . '/events.jsonl',
            'controller events',
        ),
        'candidate_patch_path' => 'candidate.patch',
        'candidate_patch_sha256' => agentEvaluationFileHash(
            $evidenceRoot . '/candidate.patch',
            'controller candidate patch',
        ),
    ];
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $profile
 * @param array<string, mixed> $task
 * @param array<string, mixed> $workspace
 * @param array<string, mixed> $generation
 * @return array<string, mixed>
 */
function agentEvaluationControllerExplanationRunRecord(
    array $request,
    array $profile,
    array $task,
    array $workspace,
    array $generation,
    string $startedAt,
    string $finishedAt,
    bool $synthetic,
): array {
    $prompt = agentEvaluationRequireObject($task, 'prompt', 'explanation task prompt');
    $rubric = agentEvaluationRequireObject($task, 'rubric', 'explanation task rubric');
    $base = agentEvaluationRequireObject($task, 'base', 'explanation task base');
    $evidenceRoot = agentEvaluationRequireString($workspace, 'evidence_root', 'explanation workspace');
    $transportTools = null;
    if (!$synthetic) {
        $proxyEvidence = agentEvaluationRequireObject($generation, 'proxy_evidence', 'explanation generation');
        $proxyLedger = agentEvaluationRequireObject($proxyEvidence, 'ledger', 'explanation proxy evidence');
        $transportTools = agentEvaluationNormalizeExplanationTransportTools(
            $proxyLedger['transport_tools'] ?? null,
            'observed explanation transport tools',
        );
        $configuredTransportTools = agentEvaluationNormalizeExplanationTransportTools(
            $profile['transport_tools'] ?? null,
            'configured explanation transport tools',
        );
        if ($transportTools === null || $transportTools !== $configuredTransportTools) {
            throw new RuntimeException('Explanation run transport tools do not match the proxy-observed identity.');
        }
    }
    $installedMetadataPath = null;
    $installedMetadataSha256 = null;
    $installedPackageCount = null;
    if (!$synthetic) {
        $installedMetadataPath = 'dependencies.installed.json';
        $installedMetadataArtifact = $evidenceRoot . '/' . $installedMetadataPath;
        $installedMetadataSha256 = agentEvaluationFileHash(
            $installedMetadataArtifact,
            'explanation installed Composer metadata',
        );
        $installedPackageCount = count(agentEvaluationExplanationInstalledComposerPackages(
            agentEvaluationJsonFile($installedMetadataArtifact),
        ));
    }
    return [
        'schema_version' => 3,
        'execution_kind' => $synthetic ? 'synthetic-control' : 'live-model',
        'run_id' => $request['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'task_manifest_sha256' => $task['manifest_sha256'],
        'prompt_sha256' => agentEvaluationRequireString($prompt, 'sha256', 'explanation task prompt'),
        'effective_prompt_sha256' => agentEvaluationRequireString(
            $prompt,
            'effective_sha256',
            'explanation task prompt',
        ),
        'rubric_sha256' => agentEvaluationRequireString($rubric, 'sha256', 'explanation task rubric'),
        'base_revision' => agentEvaluationRequireString($base, 'revision', 'explanation task base'),
        'base_tree' => agentEvaluationRequireString($base, 'tree', 'explanation task base'),
        'base_fixture_sha256' => $workspace['base_fixture_sha256'],
        'prepared_dependencies_manifest_path' => 'prepared-dependencies.manifest',
        'prepared_dependencies_manifest_sha256' => $workspace['dependency_manifest_sha256'],
        'prepared_lock_path' => $synthetic ? null : 'dependencies.lock',
        'prepared_lock_sha256' => $synthetic ? null : agentEvaluationFileHash(
            $evidenceRoot . '/dependencies.lock',
            'explanation prepared lock',
        ),
        'prepared_installed_metadata_path' => $installedMetadataPath,
        'prepared_installed_metadata_sha256' => $installedMetadataSha256,
        'prepared_installed_package_count' => $installedPackageCount,
        'condition' => $profile['condition'],
        'runner' => $profile['runner'],
        'model' => $profile['model'],
        'context' => $profile['context'],
        'tools' => $profile['tools'],
        'transport_tools' => $transportTools,
        'budgets' => $profile['budgets'],
        'usage' => $generation['usage'],
        'timing' => ['started_at' => $startedAt, 'finished_at' => $finishedAt],
        'repair_turns' => 0,
        'termination_reason' => $generation['termination_reason'],
        'events_path' => 'events.jsonl',
        'events_sha256' => agentEvaluationFileHash($evidenceRoot . '/events.jsonl', 'explanation events'),
        'candidate_patch_path' => 'candidate.patch',
        'candidate_patch_sha256' => agentEvaluationFileHash($evidenceRoot . '/candidate.patch', 'explanation patch'),
        'response_path' => 'response.txt',
        'response_sha256' => agentEvaluationFileHash($evidenceRoot . '/response.txt', 'explanation response'),
    ];
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $task
 * @param array<string, mixed> $runRecord
 * @param array<string, mixed> $checks
 * @return array<string, mixed>
 */
function agentEvaluationControllerExplanationScoreRecord(
    array $request,
    array $task,
    array $runRecord,
    string $runRecordHash,
    string $structuralEvidenceHash,
    array $checks,
): array {
    $expectedChecks = ['task_identity', 'response_integrity', 'workspace_unchanged', 'resource_bounds',
        'external_actions_approved', 'cleanup'];
    if (array_keys($checks) !== $expectedChecks) {
        throw new RuntimeException('Explanation scoring requires every structural check.');
    }
    foreach ($checks as $check) {
        if (!is_bool($check)) {
            throw new RuntimeException('Explanation structural checks must be Boolean.');
        }
    }
    $structuralPass = !in_array(false, $checks, true);
    $unknown = static fn (string $evidence): array => ['status' => 'unknown', 'evidence' => $evidence];
    return [
        'schema_version' => 3,
        'run_id' => $request['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'run_record_sha256' => $runRecordHash,
        'prompt_sha256' => $runRecord['prompt_sha256'],
        'effective_prompt_sha256' => $runRecord['effective_prompt_sha256'],
        'rubric_sha256' => $runRecord['rubric_sha256'],
        'response_sha256' => $runRecord['response_sha256'],
        'candidate_patch_sha256' => $runRecord['candidate_patch_sha256'],
        'structural_evidence_path' => 'structural-evidence.json',
        'structural_evidence_sha256' => $structuralEvidenceHash,
        'admissible' => $structuralPass,
        'structural_checks' => $checks,
        'automated_status' => $structuralPass ? 'pass' : 'fail',
        'human_review' => [
            'status' => 'pending',
            'reviewer' => null,
            'dimensions' => [
                'route_selection' => $unknown('Pending accountable review of the retained response and events.'),
                'necessary_concern_coverage' => $unknown('Pending accountable review of the retained response and events.'),
                'unsupported_claims' => $unknown('Pending accountable review of the retained response and events.'),
                'answer_correctness' => $unknown('Pending accountable review of the retained response and events.'),
                'repairs' => $unknown('Pending accountable review of the retained response and events.'),
                'clarification' => $unknown('Pending accountable review of the retained response and events.'),
            ],
            'reason' => 'Semantic correctness requires a separate accountable human review.',
        ],
        'correct_completion' => null,
    ];
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $task
 * @param array<string, mixed> $runRecord
 * @param array<string, mixed> $score
 * @return array<string, mixed>
 */
function agentEvaluationControllerScoreRecord(
    array $request,
    array $task,
    array $runRecord,
    string $runRecordHash,
    array $score,
    bool $synthetic = true,
): array {
    $prompt = agentEvaluationValueObject($task['prompt'] ?? null, 'controller task prompt');
    $publicScorer = agentEvaluationValueObject(
        $task['public_scorer'] ?? null,
        'controller task public scorer',
    );

    return [
        'schema_version' => 1,
        'run_id' => $request['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'run_record_sha256' => $runRecordHash,
        'prompt_sha256' => agentEvaluationRequireString($prompt, 'sha256', 'controller task prompt'),
        'scorer_sha256' => agentEvaluationRequireString(
            $publicScorer,
            'sha256',
            'controller task public scorer',
        ),
        'candidate_patch_sha256' => $runRecord['candidate_patch_sha256'],
        'admissible' => $score['admissible'],
        'mandatory_checks' => $score['mandatory_checks'],
        'dimensions' => $score['dimensions'],
        'weighted_score' => $score['weighted_score'],
        'automated_status' => $score['automated_status'],
        'human_review' => 'pending',
        'notes' => [
            $synthetic
                ? 'Deterministic fake-controller smoke only; no model, real candidate gate, OCI, or comparison.'
                : 'Public OCI/Codex smoke with actual application gate and public scorer; inspect proxy evidence for provider or deterministic fixture identity. No comparative claim.',
        ],
    ];
}

/**
 * @param list<string> $observedPhases
 * @param array<string, string>|null $primaryFailure
 * @param array<string, mixed>|null $selectedTask
 * @param array<string, string>|null $cleanupFailure
 * @return array<string, mixed>
 */
function agentEvaluationControllerEvidenceManifest(
    string $evidenceRoot,
    string $runId,
    array $observedPhases,
    ?array $primaryFailure,
    ?array $cleanupFailure,
    bool $synthetic = true,
    ?array $selectedTask = null,
): array {
    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'controller evidence root');
    $entries = scandir($root);

    if ($entries === false) {
        throw new RuntimeException('Unable to enumerate retained controller evidence.');
    }

    $artifacts = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'evidence-manifest.json') {
            continue;
        }

        $path = agentEvaluationControllerValidateRetainedArtifact(
            $root,
            $entry,
            AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
        );
        $bytes = filesize($path);

        if (!is_int($bytes)) {
            throw new RuntimeException("Unable to size retained controller artifact {$entry}.");
        }

        $artifacts[$entry] = [
            'bytes' => $bytes,
            'sha256' => agentEvaluationFileHash($path, "controller evidence {$entry}"),
        ];
    }

    ksort($artifacts, SORT_STRING);

    if ($primaryFailure === null && $cleanupFailure === null && $observedPhases !== AGENT_EVALUATION_CONTROLLER_PHASES) {
        throw new RuntimeException('Successful controller evidence must retain the complete exact phase order.');
    }

    return [
        'schema_version' => AGENT_EVALUATION_CONTROLLER_EVIDENCE_VERSION,
        'controller_version' => AGENT_EVALUATION_CONTROLLER_VERSION,
        'run_id' => $runId,
        'task_id' => $selectedTask === null ? AGENT_EVALUATION_CONTROLLER_TASK_ID : $selectedTask['id'],
        'task_revision' => $selectedTask === null ? AGENT_EVALUATION_CONTROLLER_TASK_REVISION : $selectedTask['revision'],
        'synthetic' => $synthetic,
        'comparative_claims' => false,
        ...($selectedTask !== null && isset($selectedTask['selected_condition'])
            ? ['comparison_execution' => true, 'condition' => $selectedTask['selected_condition']]
            : []),
        ...($selectedTask !== null && ($selectedTask['schema_version'] ?? null) === 3
            ? ['explanation_execution' => true, 'condition' => 'repository-only']
            : []),
        'expected_phase_order' => AGENT_EVALUATION_CONTROLLER_PHASES,
        'observed_phases' => $observedPhases,
        'primary_failure' => $primaryFailure,
        'cleanup_failure' => $cleanupFailure,
        'artifacts' => $artifacts,
    ];
}

/** @param array<string,mixed> $campaign */
function agentEvaluationControllerRunComparisonCampaign(string $root, array $campaign): void
{
    $configuration = agentEvaluationRequireObject($campaign, 'configuration', 'comparison campaign');
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'comparison campaign');
    $pricing = agentEvaluationRequireObject($configuration, 'pricing', 'comparison campaign');
    $ceiling = agentEvaluationRequireString($approval, 'spending_ceiling_usd', 'comparison approval');
    $maximumPrice = max(agentEvaluationRequireInteger($pricing, 'input_cents_per_million', 'comparison pricing'),
        agentEvaluationRequireInteger($pricing, 'output_cents_per_million', 'comparison pricing'));
    $parts = explode('.', $ceiling);
    $ceilingCents = intval($parts[0], 10) * 100 + intval($parts[1] ?? '0', 10);
    $requiredCents = (int) ceil(60 * 40_000 * $maximumPrice / 1_000_000);
    if ($ceilingCents < $requiredCents || $ceilingCents === 0
        || str_contains(strtolower(agentEvaluationRequireString($approval, 'reference', 'comparison approval')), 'pending')
    ) {
        throw new RuntimeException('Paid comparison requires the approved exact model, 60 runs, and sufficient reviewed spending ceiling; preparation is not approval.');
    }
    $credential = \getenv('OPENAI_API_KEY');
    if (!is_string($credential) || $credential === '' || strlen($credential) > 4_096 || preg_match('/[\x00-\x20\x7F]/', $credential) === 1) {
        throw new RuntimeException('Paid comparison requires the host-only OPENAI_API_KEY.');
    }
    $parent = dirname($root) . '/agent-evaluation-runs';
    if (!file_exists($parent) && !mkdir($parent, 0700)) {
        throw new RuntimeException('Unable to prepare the fixed comparison evidence parent.');
    }
    agentEvaluationControllerExistingRoot($parent, 'comparison evidence parent');
    $id = agentEvaluationRequireString($configuration, 'campaign_id', 'comparison campaign');
    $campaignRoot = agentEvaluationControllerFreshAbsoluteTarget($parent . '/' . $id, 'comparison evidence root');
    if (!mkdir($campaignRoot, 0700) || !mkdir($campaignRoot . '/attempts', 0700) || !mkdir($campaignRoot . '/runs', 0700)) {
        throw new RuntimeException('Unable to create the private comparison campaign directories.');
    }
    agentEvaluationControllerWriteArtifact($campaignRoot, 'configuration.json', agentEvaluationRequireString($campaign, 'bytes', 'comparison campaign'));
    agentEvaluationControllerWriteArtifact($campaignRoot, 'schedule.json', agentEvaluationJson($campaign['schedule']));
    agentEvaluationControllerRetainComparisonImplementation($root, $campaignRoot,
        agentEvaluationRequireString($configuration, 'implementation_sha256', 'comparison campaign'));
    $attempts = agentEvaluationRequireList($campaign, 'attempts', 'comparison campaign');
    $inputs = agentEvaluationRequireObject($campaign, 'inputs', 'comparison campaign');
    foreach ($attempts as $value) {
        $attempt = agentEvaluationValueObject($value, 'planned comparison attempt');
        $slot = agentEvaluationRequireInteger($attempt, 'slot', 'comparison attempt');
        agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', sprintf('%02d.planned.json', $slot), agentEvaluationJson($attempt));
    }
    $abort = false;
    foreach ($attempts as $value) {
        $attempt = agentEvaluationValueObject($value, 'comparison attempt');
        $slot = agentEvaluationRequireInteger($attempt, 'slot', 'comparison attempt');
        $runId = agentEvaluationRequireString($attempt, 'run_id', 'comparison attempt');
        if ($abort) {
            $attempt['status'] = 'not_run';
            $attempt['termination_reason'] = 'campaign_aborted';
        } else {
            $attempt['status'] = 'running';
            $attempt['phase'] = 'prepare';
            agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', sprintf('%02d.started.json', $slot), agentEvaluationJson($attempt));
            $key = agentEvaluationRequireString($attempt, 'task_id', 'comparison attempt') . ':' . agentEvaluationRequireString($attempt, 'condition', 'comparison attempt');
            $input = agentEvaluationValueObject($inputs[$key], 'comparison preparation');
            $task = agentEvaluationRequireObject($input, 'task', 'comparison preparation');
            $condition = agentEvaluationRequireObject($input, 'condition', 'comparison preparation');
            $holdout = agentEvaluationRequireObject($input, 'holdout', 'comparison preparation');
            try {
                $outcome = agentEvaluationControllerExecuteComparisonLive($root, $campaignRoot . '/runs/' . $runId,
                    ['run_id' => $runId, 'task_id' => $attempt['task_id']],
                    agentEvaluationRequireObject($input, 'execution', 'comparison preparation'), $credential,
                    ['task' => $task, 'condition' => $condition, 'holdout' => $holdout]);
                foreach (['status', 'phase', 'termination_reason'] as $name) {
                    $attempt[$name] = $outcome[$name];
                }
                $attempt['usage'] = agentEvaluationRequireObject($outcome, 'generation_usage', 'comparison outcome');
                $attempt['elapsed_milliseconds'] = $outcome['generation_elapsed_milliseconds'];
                $attempt['unknown_metrics'] = agentEvaluationControllerComparisonUnknownMetrics($attempt['usage'], $attempt['elapsed_milliseconds']);
                $evidenceRoot = $outcome['evidence_root'];
                if (is_string($evidenceRoot)) {
                    $attempt['artifacts'] = agentEvaluationControllerComparisonArtifacts($evidenceRoot);
                }
                $abort = agentEvaluationControllerComparisonMustStop($attempt, is_string($evidenceRoot) ? $evidenceRoot : null);
            } catch (Throwable) {
                // Structural failure only: never retain raw exceptions that might
                // contain host credentials or silently retry an ambiguous request.
                $attempt['status'] = 'failed';
                $attempt['phase'] = 'prepare';
                $attempt['termination_reason'] = 'campaign_controller_failed';
                $evidenceRoot = $campaignRoot . '/runs/' . $runId . '/evidence';
                if (is_dir($evidenceRoot) && !is_link($evidenceRoot)) {
                    $attempt['artifacts'] = agentEvaluationControllerComparisonArtifacts($evidenceRoot);
                }
                $abort = true;
            }
        }
        $retainedArtifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'comparison attempt');
        if (!isset($retainedArtifacts['proxy.json'])) {
            $attempt['usage'] = ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null];
        }
        if (!isset($retainedArtifacts['generation-process.json'])) {
            $attempt['elapsed_milliseconds'] = null;
        }
        $attempt['unknown_metrics'] = agentEvaluationControllerComparisonUnknownMetrics(
            agentEvaluationRequireObject($attempt, 'usage', 'comparison attempt'), $attempt['elapsed_milliseconds']);
        $attemptPath = agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', sprintf('%02d.json', $slot), agentEvaluationJson($attempt));
        $inputKey = agentEvaluationRequireString($attempt, 'task_id', 'comparison attempt') . ':' . agentEvaluationRequireString($attempt, 'condition', 'comparison attempt');
        $input = agentEvaluationValueObject($inputs[$inputKey], 'comparison preparation');
        $task = agentEvaluationRequireObject($input, 'task', 'comparison preparation');
        $schedule = agentEvaluationRequireList($campaign, 'schedule', 'comparison campaign');
        $scheduleSlot = agentEvaluationValueObject($schedule[$slot - 1], 'comparison schedule slot');
        agentEvaluationValidateComparisonRunRecord($attempt, $task, [
            'slot' => agentEvaluationRequireInteger($scheduleSlot, 'slot', 'comparison schedule slot'),
            'round' => agentEvaluationRequireInteger($scheduleSlot, 'round', 'comparison schedule slot'),
            'task_id' => agentEvaluationRequireString($scheduleSlot, 'task_id', 'comparison schedule slot'),
            'condition' => agentEvaluationRequireString($scheduleSlot, 'condition', 'comparison schedule slot'),
        ], $id, agentEvaluationRequireString($configuration, 'protocol_sha256', 'comparison campaign'));
        $evidence = $campaignRoot . '/runs/' . $runId . '/evidence';
        $score = agentEvaluationControllerComparisonScoreFromEvidence($attempt, agentEvaluationFileHash($attemptPath, 'comparison attempt'),
            agentEvaluationRequireObject($input, 'holdout', 'comparison preparation'), $evidence);
        agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', sprintf('%02d.score.json', $slot), agentEvaluationJson($score));
        fwrite(STDOUT, agentEvaluationJson(['slot' => $slot, 'status' => $attempt['status'], 'automated_status' => $score['automated_status']]));
    }
}

/** @return array<string,array{bytes:int,sha256:string}>|stdClass */
function agentEvaluationControllerComparisonArtifacts(string $evidenceRoot): array|stdClass
{
    $tree = agentEvaluationControllerDescribeTree($evidenceRoot, 'comparison evidence', false);
    $artifacts = [];
    foreach ($tree['files'] as $name => $file) {
        if (str_contains($name, '/')) {
            throw new RuntimeException('Comparison evidence inventory requires flat retained files.');
        }
        $path = $evidenceRoot . '/' . $name;
        $bytes = filesize($path);
        if (!is_int($bytes)) {
            throw new RuntimeException('Comparison evidence size is unavailable.');
        }
        $artifacts[$name] = ['bytes' => $bytes, 'sha256' => agentEvaluationFileHash($path, 'comparison evidence')];
    }
    return $artifacts === [] ? new stdClass() : $artifacts;
}

/**
 * Recompute all automated components from hash-bound raw evidence rather than
 * accepting editable score booleans as observations.
 * @param array<string,mixed> $attempt
 * @param array<string,mixed> $holdout
 * @return array<string,mixed>
 */
function agentEvaluationControllerComparisonScoreFromEvidence(array $attempt, string $attemptHash, array $holdout, string $evidenceRoot, int $modelTokenBudget = 40_000, ?int $calibrationRevision = null): array
{
    agentEvaluationControllerValidateComparisonAttemptMeasurements($attempt, $evidenceRoot, $modelTokenBudget, $calibrationRevision);
    $results = agentEvaluationControllerEmptyComparisonResults($holdout);
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'comparison attempt');
    if ($artifacts !== []) {
        agentEvaluationValidateComparisonRunArtifacts($attempt, $evidenceRoot);
    }
    $admissible = ($attempt['status'] ?? null) === 'complete';
    if (isset($artifacts['cleanup.json'])) {
        $cleanup = agentEvaluationJsonFile($evidenceRoot . '/cleanup.json');
        $admissible = $admissible && ($cleanup['status'] ?? null) === 'pass';
    } else {
        $admissible = false;
    }
    if (isset($artifacts['application-check.json'])) {
        $application = agentEvaluationControllerComparisonProcessEvidence($evidenceRoot . '/application-check.json');
        if (($application['command_slot'] ?? null) !== 'comparison-application-check'
            || ($application['scorer_mounted'] ?? null) !== false || ($application['standard_input_bytes'] ?? null) !== 0
            || ($application['standard_input_sha256'] ?? null) !== hash('sha256', '')
        ) {
            throw new RuntimeException('Comparison application gate must bind its fixed command and empty input.');
        }
        $results['application_gate'] = agentEvaluationControllerLiveCheckPassed($application) ? 'pass' : 'fail';
        $admissible = $admissible && agentEvaluationControllerLiveCheckAdmissible($application);
    }
    foreach (agentEvaluationRequireList($holdout, 'cases', 'comparison holdout') as $index => $value) {
        $name = sprintf('observation-%02d.json', $index + 1);
        if (!isset($artifacts[$name])) {
            continue;
        }
        $case = agentEvaluationValueObject($value, 'comparison case');
        $process = agentEvaluationControllerComparisonProcessEvidence($evidenceRoot . '/' . $name);
        $inputBytes = agentEvaluationControllerOciScoringSlot('observation', agentEvaluationJson(agentEvaluationRequireObject($case, 'input', 'comparison case')))['standard_input'];
        if (($process['command_slot'] ?? null) !== 'observation' || ($process['scorer_mounted'] ?? null) !== false
            || ($process['standard_input_sha256'] ?? null) !== hash('sha256', $inputBytes)
            || ($process['standard_input_bytes'] ?? null) !== strlen($inputBytes)
        ) {
            throw new RuntimeException('Comparison observation does not bind its fixed input and command slot.');
        }
        $results['cases'][$index] = ['id' => agentEvaluationRequireString($case, 'id', 'comparison case'),
            'process_admissible' => agentEvaluationControllerLiveCheckPassed($process),
            ...agentEvaluationControllerCompareObservation(agentEvaluationRequireString($process, 'stdout', 'comparison process'),
                agentEvaluationRequireObject($case, 'expect', 'comparison case'))];
    }
    $results['scaling'] = agentEvaluationControllerComparisonScaling($holdout, $results['cases']);
    $passed = $admissible && $results['application_gate'] === 'pass';
    foreach ($results['cases'] as $case) {
        foreach (['process_admissible', 'observation_valid', 'response', 'policy_order', 'durable_state', 'transaction_closed', 'query_bounds'] as $name) {
            $passed = $passed && $case[$name] === true;
        }
    }
    foreach ($results['scaling'] as $group) {
        $passed = $passed && $group['passed'] === true;
    }
    return ['schema_version' => 2, 'campaign_id' => $attempt['campaign_id'], 'slot' => $attempt['slot'],
        'run_id' => $attempt['run_id'], 'task_id' => $attempt['task_id'], 'condition' => $attempt['condition'],
        'attempt_sha256' => $attemptHash, 'holdout_sha256' => $attempt['holdout_sha256'], 'admissible' => $admissible,
        'application_gate' => $results['application_gate'], 'cases' => $results['cases'], 'scaling' => $results['scaling'],
        'automated_status' => $passed ? 'pass' : 'fail',
        'human_review' => ['status' => 'pending', 'reviewer' => null, 'semantic_correctness' => null, 'instrumentation_integrity' => null,
            'review_seconds' => null, 'justified_interventions' => null, 'unnecessary_interventions' => null,
            'public_check_repairs' => null, 'reason' => 'Accountable human semantic and instrumentation review has not occurred.'],
        'correct_completion' => null];
}

/** @return array<string,mixed> */
function agentEvaluationControllerComparisonProcessEvidence(string $path): array
{
    agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'comparison process evidence');
    $bytes = file_get_contents($path, false, null, 0, AGENT_EVALUATION_MAX_ARTIFACT_BYTES + 1);
    if (!is_string($bytes)) {
        throw new RuntimeException('Comparison process evidence is unreadable.');
    }
    $process = agentEvaluationValueObject(agentEvaluationJsonValue($bytes, 'comparison process evidence', AGENT_EVALUATION_MAX_ARTIFACT_BYTES), 'comparison process evidence');
    if (array_key_exists('stream_encoding', $process)) {
        if ($process['stream_encoding'] !== 'base64') {
            throw new RuntimeException('Comparison retained process stream encoding is unsupported.');
        }
        foreach (['stdout', 'stderr'] as $name) {
            $encoded = agentEvaluationRequireString($process, $name, 'comparison process evidence');
            $decoded = base64_decode($encoded, true);
            if (!is_string($decoded) || base64_encode($decoded) !== $encoded || strlen($decoded) > 4_194_304) {
                throw new RuntimeException('Comparison process stream encoding exceeds its canonical bound.');
            }
            $process[$name] = $decoded;
        }
    }
    return $process;
}

function agentEvaluationControllerComparisonRepositoryRevision(string $root): string
{
    $git = realpath('/usr/bin/git');
    if (!is_string($git)) {
        throw new RuntimeException('Comparison provenance requires the fixed Git executable.');
    }
    $process = agentEvaluationControllerRunProcess([$git, '--no-optional-locks', 'rev-parse', '--verify', 'HEAD'],
        $root, ['LANG' => 'C', 'LC_ALL' => 'C', 'PATH' => '/usr/bin:/bin'], '', 10, 4_096);
    $revision = trim($process['stdout']);
    if ($process['exit_code'] !== 0 || $process['termination_reason'] !== 'completed'
        || !agentEvaluationControllerProcessCleanupPassed($process['cleanup'])
        || preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/D', $revision) !== 1
    ) {
        throw new RuntimeException('Comparison source revision could not be verified.');
    }
    return $revision;
}

/**
 * Report only evidence tied to the retained campaign plan. This read-only path
 * never starts generation, retries an attempt, or supplies private expectations
 * to a candidate process.
 * @param array<string,mixed> $campaign
 * @return array<string,mixed>
 */
function agentEvaluationControllerComparisonReport(string $root, array $campaign): array
{
    $configuration = agentEvaluationRequireObject($campaign, 'configuration', 'comparison report');
    $campaignId = agentEvaluationRequireString($configuration, 'campaign_id', 'comparison report');
    if (preg_match('/\A[a-f0-9]{32}\z/D', $campaignId) !== 1) {
        throw new RuntimeException('Comparison reporting requires one fixed campaign identity.');
    }
    $campaignRoot = agentEvaluationControllerExistingRoot(dirname($root) . '/agent-evaluation-runs/' . $campaignId, 'comparison report root');
    $attemptRoot = agentEvaluationControllerExistingRoot($campaignRoot . '/attempts', 'comparison report attempt root');
    $runRoot = agentEvaluationControllerExistingRoot($campaignRoot . '/runs', 'comparison report run root');
    $configurationPath = agentEvaluationControllerValidateRetainedArtifact($campaignRoot, 'configuration.json', AGENT_EVALUATION_MAX_JSON_BYTES);
    $configurationHash = agentEvaluationRequireHash(agentEvaluationRequireString($campaign, 'sha256', 'comparison report'), 'comparison report configuration');
    if (hash('sha256', agentEvaluationRequireString($campaign, 'bytes', 'comparison report')) !== $configurationHash) {
        throw new RuntimeException('Comparison report configuration bytes and identity disagree.');
    }
    agentEvaluationRequireFileHash($configurationPath, $configurationHash, 'retained comparison configuration');
    if (agentEvaluationControllerComparisonCodeHash($campaignRoot . '/implementation')
        !== agentEvaluationRequireString($configuration, 'implementation_sha256', 'comparison report')
    ) {
        throw new RuntimeException('Comparison reporting requires the exact retained implementation snapshot.');
    }
    $scheduleValues = agentEvaluationRequireList($campaign, 'schedule', 'comparison report');
    $schedule = [];
    foreach ($scheduleValues as $value) {
        $slot = agentEvaluationValueObject($value, 'comparison report slot');
        agentEvaluationRequireExactKeys($slot, ['slot', 'round', 'task_id', 'condition'], 'comparison report slot');
        $schedule[] = ['slot' => agentEvaluationRequireInteger($slot, 'slot', 'comparison report slot'),
            'round' => agentEvaluationRequireInteger($slot, 'round', 'comparison report slot'),
            'task_id' => agentEvaluationRequireString($slot, 'task_id', 'comparison report slot'),
            'condition' => agentEvaluationRequireString($slot, 'condition', 'comparison report slot')];
    }
    if ($schedule !== agentEvaluationComparisonSchedule($root . '/tools/agent-evaluation')) {
        throw new RuntimeException('Comparison report schedule must equal the frozen protocol order.');
    }
    $schedulePath = agentEvaluationControllerValidateRetainedArtifact($campaignRoot, 'schedule.json', AGENT_EVALUATION_MAX_JSON_BYTES);
    agentEvaluationRequireFileHash($schedulePath, hash('sha256', agentEvaluationJson($schedule)), 'retained comparison schedule');
    $expectedPlans = agentEvaluationRequireList($campaign, 'attempts', 'comparison report');
    $inputs = agentEvaluationRequireObject($campaign, 'inputs', 'comparison report');
    if (count($expectedPlans) !== 60) {
        throw new RuntimeException('Comparison reporting cannot omit a planned attempt.');
    }
    $ledgerEntries = scandir($attemptRoot);
    if ($ledgerEntries === false || count($ledgerEntries) > 242) {
        throw new RuntimeException('Comparison attempt ledger exceeds its fixed file inventory.');
    }
    $ledgerPaths = [$configurationPath, $schedulePath];
    foreach ($ledgerEntries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (preg_match('/\A(?:0[1-9]|[1-5][0-9]|60)(?:\.planned|\.started|\.score)?\.json\z/D', $entry) !== 1) {
            throw new RuntimeException('Comparison attempt ledger contains an unplanned entry.');
        }
        $ledgerPaths[] = agentEvaluationControllerValidateRetainedArtifact($attemptRoot, $entry, AGENT_EVALUATION_MAX_JSON_BYTES);
    }
    agentEvaluationRequireDistinctFileIdentities($ledgerPaths);
    $observations = [];
    $attemptReports = [];
    $contextReports = [];
    $knownRunDirectories = [];
    $protocolHash = agentEvaluationRequireString($configuration, 'protocol_sha256', 'comparison report');
    foreach ($schedule as $index => $slot) {
        $number = $slot['slot'];
        $prefix = sprintf('%02d', $number);
        $inputKey = $slot['task_id'] . ':' . $slot['condition'];
        $input = agentEvaluationValueObject($inputs[$inputKey] ?? null, 'comparison report prepared input');
        $task = agentEvaluationRequireObject($input, 'task', 'comparison report');
        $planned = agentEvaluationValueObject($expectedPlans[$index], 'comparison report expected plan');
        if (!is_file($attemptRoot . '/' . $prefix . '.planned.json')) {
            throw new RuntimeException('Comparison reporting cannot omit a planned attempt.');
        }
        $plannedPath = agentEvaluationControllerValidateRetainedArtifact($attemptRoot, $prefix . '.planned.json', AGENT_EVALUATION_MAX_JSON_BYTES);
        agentEvaluationRequireFileHash($plannedPath, hash('sha256', agentEvaluationJson($planned)), 'retained planned comparison attempt');
        agentEvaluationValidateComparisonRunRecord($planned, $task, $slot, $campaignId, $protocolHash);
        if (($planned['status'] ?? null) !== 'planned') {
            throw new RuntimeException('Comparison report requires every immutable initial planned state.');
        }
        $runId = agentEvaluationRequireString($planned, 'run_id', 'comparison report');
        $evidenceRoot = $runRoot . '/' . $runId . '/evidence';
        $startedPath = $attemptRoot . '/' . $prefix . '.started.json';
        $finalPath = $attemptRoot . '/' . $prefix . '.json';
        $scorePath = $attemptRoot . '/' . $prefix . '.score.json';
        $started = file_exists($startedPath);
        if ($started) {
            $knownRunDirectories[$runId] = true;
        }
        $finalRetained = file_exists($finalPath);
        $scoreRetained = file_exists($scorePath);
        $attempt = $planned;
        if ($started) {
            $attempt = agentEvaluationJsonFile($startedPath);
            $expectedStart = [...$planned, 'status' => 'running', 'phase' => 'prepare'];
            agentEvaluationRequireFileHash($startedPath, hash('sha256', agentEvaluationJson($expectedStart)), 'retained started comparison attempt');
            agentEvaluationControllerValidateComparisonReportAttempt($attempt, $planned, $task, $slot, $campaignId, $protocolHash);
        }
        if ($finalRetained) {
            $attempt = agentEvaluationJsonFile($finalPath);
            agentEvaluationControllerValidateComparisonReportAttempt($attempt, $planned, $task, $slot, $campaignId, $protocolHash);
        }
        $status = agentEvaluationRequireString($attempt, 'status', 'comparison report');
        if (($started && in_array($status, ['planned', 'not_run'], true))
            || (!$started && in_array($status, ['running', 'complete', 'failed'], true))
            || (!$finalRetained && $scoreRetained)
        ) {
            throw new RuntimeException('Comparison attempt start, final state, and score ledger are inconsistent.');
        }
        $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'comparison report');
        if ($finalRetained && file_exists($evidenceRoot)) {
            $actual = agentEvaluationControllerComparisonArtifacts($evidenceRoot);
            if (agentEvaluationControllerCanonicalObservation(agentEvaluationValueObject($actual, 'comparison actual evidence inventory'))
                !== agentEvaluationControllerCanonicalObservation($artifacts)
            ) {
                throw new RuntimeException('Comparison report artifact inventory must equal every retained evidence file.');
            }
        } elseif ($artifacts !== []) {
            throw new RuntimeException('Comparison attempt names evidence absent from its retained run directory.');
        }
        if (!$started && file_exists($runRoot . '/' . $runId)) {
            throw new RuntimeException('An unstarted comparison slot has an unexpected execution directory.');
        }
        $score = null;
        $attemptHash = $finalRetained ? agentEvaluationFileHash($finalPath, 'retained comparison attempt') : null;
        if ($scoreRetained) {
            if (!is_string($attemptHash)) {
                throw new RuntimeException('Comparison score requires its retained final attempt.');
            }
            $holdoutPath = agentEvaluationRequireString($input, 'holdout_path', 'comparison report');
            $holdout = agentEvaluationControllerReadComparisonHoldout($holdoutPath, $task);
            $holdoutBytes = file_get_contents($holdoutPath, false, null, 0, AGENT_EVALUATION_MAX_JSON_BYTES + 1);
            if (!is_string($holdoutBytes)) {
                throw new RuntimeException('Comparison reporting cannot read its exact private holdout identity.');
            }
            $score = agentEvaluationJsonFile($scorePath);
            $recomputed = agentEvaluationControllerComparisonScoreFromEvidence($attempt, $attemptHash, $holdout, $evidenceRoot);
            $automated = $score;
            unset($automated['human_review'], $automated['correct_completion'], $recomputed['human_review'], $recomputed['correct_completion']);
            if (agentEvaluationControllerCanonicalObservation($automated) !== agentEvaluationControllerCanonicalObservation($recomputed)) {
                throw new RuntimeException('Comparison report refuses automated scores that differ from the retained raw evidence.');
            }
            agentEvaluationValidateComparisonScoreRecord($score, $attempt, $attemptHash, $holdoutBytes);
        } elseif ($artifacts !== []) {
            agentEvaluationValidateComparisonRunArtifacts($attempt, $evidenceRoot);
        }
        if ($finalRetained && !$scoreRetained) {
            agentEvaluationControllerValidateComparisonAttemptMeasurements($attempt, $evidenceRoot);
        }
        $metrics = $finalRetained ? agentEvaluationControllerComparisonRetainedMetrics($attempt, $evidenceRoot,
            count(agentEvaluationRequireList(agentEvaluationRequireObject($input, 'holdout', 'comparison report'), 'cases', 'comparison report')))
            : [];
        $observations[] = ['slot' => $number, 'attempt' => $attempt, 'score' => $score,
            'started' => $started, 'final_retained' => $finalRetained, 'metrics' => $metrics];
        $attemptReports[] = ['slot' => $number, 'planned_path' => 'attempts/' . $prefix . '.planned.json',
            'started_path' => $started ? 'attempts/' . $prefix . '.started.json' : null,
            'attempt_path' => $finalRetained ? 'attempts/' . $prefix . '.json' : null,
            'attempt_sha256' => $attemptHash, 'score_path' => $scoreRetained ? 'attempts/' . $prefix . '.score.json' : null,
            'evidence_path' => $started ? 'runs/' . $runId . '/evidence' : null,
            'attempt' => $attempt, 'score' => $score, 'recorded_metrics' => $metrics];
        if (!isset($contextReports[$inputKey])) {
            $condition = agentEvaluationRequireObject($input, 'condition', 'comparison report');
            $sourceContext = agentEvaluationRequireObject($condition, 'context_manifest', 'comparison report');
            $installedContext = agentEvaluationRequireObject($input, 'installed_context', 'comparison report');
            $contextReports[$inputKey] = ['task_id' => $slot['task_id'], 'condition' => $slot['condition'],
                'source_context' => ['scope' => $sourceContext['scope'], 'sha256' => $sourceContext['sha256'],
                    'files' => $sourceContext['files'], 'bytes' => $sourceContext['bytes'], 'words' => $sourceContext['words']],
                'installed_documentation_context' => $installedContext];
        }
    }
    $runEntries = scandir($runRoot);
    if ($runEntries === false || count($runEntries) > 62) {
        throw new RuntimeException('Comparison execution directory inventory exceeds its fixed planned attempts.');
    }
    foreach ($runEntries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!isset($knownRunDirectories[$entry])) {
            throw new RuntimeException('Comparison evidence contains an execution without its planned started attempt.');
        }
        agentEvaluationControllerExistingRoot($runRoot . '/' . $entry, 'comparison retained execution directory');
    }
    $summary = agentEvaluationAggregateComparisonResults($schedule, $observations, agentEvaluationRequireObject($configuration, 'pricing', 'comparison report'));
    return ['schema_version' => 1, 'campaign_id' => $campaignId, 'configuration_sha256' => $configurationHash,
        'protocol_sha256' => $protocolHash, 'source_revision' => $configuration['source_revision'],
        'implementation_sha256' => $configuration['implementation_sha256'],
        'model_revision_note' => $configuration['model_revision_note'], 'pricing' => $configuration['pricing'],
        'evidence_root' => $campaignRoot, 'summary' => $summary, 'contexts' => array_values($contextReports),
        'attempts' => $attemptReports,
        'limitations' => ['The condition combines runtime, guidance, diagnostics, and dependencies; it does not isolate their individual effects.',
            'Query observations require separate semantic and instrumentation review; they do not establish query plans or production performance.',
            'Unavailable usage, timing, repairs, or reviewer effort remain null with a reason; subset tokens are not added twice.',
            'Sixty fixed attempts on authored SQLite tasks and one selected model do not establish general framework superiority.']];
}

/**
 * @param array<string,mixed> $attempt
 * @param array<string,mixed> $planned
 * @param array<string,mixed> $task
 * @param array{slot:int,round:int,task_id:string,condition:string} $slot
 */
function agentEvaluationControllerValidateComparisonReportAttempt(array $attempt, array $planned, array $task, array $slot, string $campaignId, string $protocolHash): void
{
    agentEvaluationValidateComparisonRunRecord($attempt, $task, $slot, $campaignId, $protocolHash);
    foreach ($planned as $name => $value) {
        if (in_array($name, ['status', 'phase', 'termination_reason', 'usage', 'elapsed_milliseconds', 'unknown_metrics', 'artifacts'], true)) {
            continue;
        }
        if (($attempt[$name] ?? null) !== $value) {
            throw new RuntimeException('Comparison retained attempt changed one frozen planned identity or setting.');
        }
    }
}

/**
 * @param array<string,mixed> $attempt
 * @return array<string,int|null>
 */
function agentEvaluationControllerComparisonRetainedMetrics(array $attempt, string $evidenceRoot, int $caseCount): array
{
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'comparison metrics');
    $result = ['scoring_elapsed_milliseconds' => null, 'changed_files' => null, 'added_lines' => null, 'deleted_lines' => null];
    if (isset($artifacts['freeze.json'])) {
        $freeze = agentEvaluationJsonFile($evidenceRoot . '/freeze.json');
        $patch = agentEvaluationValueObject($artifacts['candidate.patch'] ?? null, 'comparison freeze patch');
        if (($freeze['patch_sha256'] ?? null) !== ($patch['sha256'] ?? null)) {
            throw new RuntimeException('Comparison edit metrics must bind the retained frozen patch.');
        }
        $changed = agentEvaluationRequireStringList($freeze, 'changed_files', 'comparison freeze');
        $result['changed_files'] = count($changed);
        $result['added_lines'] = agentEvaluationRequireNonNegativeInteger($freeze, 'added_lines', 'comparison freeze');
        $result['deleted_lines'] = agentEvaluationRequireNonNegativeInteger($freeze, 'deleted_lines', 'comparison freeze');
        if (count($changed) > 2 || count(array_unique($changed)) !== count($changed)
            || $result['added_lines'] > 1_024 || $result['deleted_lines'] > 512
        ) {
            throw new RuntimeException('Comparison frozen edit observations exceed the matched task policy.');
        }
    }
    $names = ['application-check.json'];
    for ($index = 1; $index <= $caseCount; $index++) {
        $names[] = sprintf('observation-%02d.json', $index);
    }
    $elapsed = 0;
    foreach ($names as $name) {
        if (!isset($artifacts[$name])) {
            return $result;
        }
        $process = agentEvaluationControllerComparisonProcessEvidence($evidenceRoot . '/' . $name);
        $milliseconds = $process['elapsed_milliseconds'] ?? null;
        if (!is_int($milliseconds) || $milliseconds < 0 || $milliseconds > 86_400_000) {
            return $result;
        }
        $elapsed += $milliseconds;
    }
    $result['scoring_elapsed_milliseconds'] = $elapsed;
    return $result;
}

/** @param array<string,mixed> $attempt */
function agentEvaluationControllerComparisonMustStop(array $attempt, ?string $evidenceRoot): bool
{
    if (($attempt['status'] ?? null) === 'complete' || ($attempt['phase'] ?? null) === 'freeze') {
        return false;
    }
    if (($attempt['phase'] ?? null) !== 'generate' || $evidenceRoot === null
        || !in_array($attempt['termination_reason'] ?? null, ['model_token_limit', 'wall_time_limit', 'output_limit', 'memory_limit'], true)
    ) {
        return true;
    }
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'comparison attempt');
    if (!isset($artifacts['proxy.json'], $artifacts['cleanup.json'])) {
        return true;
    }
    agentEvaluationValidateComparisonRunArtifacts($attempt, $evidenceRoot);
    $proxy = agentEvaluationJsonFile($evidenceRoot . '/proxy.json');
    $ledger = agentEvaluationRequireObject($proxy, 'ledger', 'comparison proxy');
    $cleanup = agentEvaluationJsonFile($evidenceRoot . '/cleanup.json');
    if (($cleanup['status'] ?? null) !== 'pass' || ($ledger['reserved_input'] ?? null) !== 0
        || ($ledger['reserved_output'] ?? null) !== 0
        || !array_key_exists('request_sha256', $ledger)
    ) {
        return true;
    }
    if ($ledger['request_sha256'] !== null && (!is_string($ledger['request_sha256'])
        || preg_match('/\A[a-f0-9]{64}\z/D', $ledger['request_sha256']) !== 1)
    ) {
        return true;
    }
    // A quota rejection before dispatch has no outstanding billed reservation.
    // Other bounded process failures continue only with no pending provider request.
    if ($attempt['termination_reason'] === 'model_token_limit' && ($ledger['failure_reason'] ?? null) === 'model_token_limit') {
        return false;
    }
    return $ledger['request_sha256'] !== null;
}

/**
 * Measurement claims are admitted only when they match their retained provider
 * ledger and process observation. Missing evidence remains explicitly unknown.
 *
 * @param array<string, mixed> $attempt
 */
function agentEvaluationControllerValidateComparisonAttemptMeasurements(array $attempt, string $evidenceRoot, int $modelTokenBudget = 40_000, ?int $calibrationRevision = null): void
{
    $artifacts = agentEvaluationRequireObject($attempt, 'artifacts', 'comparison measurement evidence');
    if ($artifacts !== []) {
        agentEvaluationValidateComparisonRunArtifacts($attempt, $evidenceRoot);
    }
    if (($attempt['status'] ?? null) === 'complete'
        || in_array($attempt['phase'] ?? null, ['freeze', 'score', 'validate', 'retain', 'finished'], true)
        || isset($artifacts['proxy.json']) || isset($artifacts['generation-process.json']) || isset($artifacts['events.jsonl'])) {
        foreach (['source-prompt.md', 'workspace-policy.json', 'prompt.md', 'task.json'] as $name) {
            if (!isset($artifacts[$name])) {
                throw new RuntimeException('Generated evidence requires source, workspace policy, effective prompt, and task bindings.');
            }
        }
        agentEvaluationRequireFileHash($evidenceRoot . '/task.json',
            agentEvaluationRequireString($attempt, 'task_manifest_sha256', 'generation task identity'), 'generation source task');
        agentEvaluationControllerValidatePromptEvidence($evidenceRoot, agentEvaluationJsonFile($evidenceRoot . '/task.json'),
            agentEvaluationRequireString($attempt, 'condition', 'generation condition'), $calibrationRevision);
    }
    $usage = agentEvaluationRequireObject($attempt, 'usage', 'comparison measurements');
    if (!in_array($modelTokenBudget, [40_000, 200_000, 1_000_000], true)) {
        throw new RuntimeException('Measurement budget must select the fixed comparison or calibration allowance.');
    }
    agentEvaluationValidateUsage($usage, $modelTokenBudget);
    if (!array_key_exists('elapsed_milliseconds', $attempt)) {
        throw new RuntimeException('Comparison timing must be an explicit observation or null.');
    }
    $unknownUsage = ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null];
    $proxy = null;
    $ledger = null;
    $measuredUsage = $unknownUsage;
    if (isset($artifacts['proxy.json'])) {
        $proxy = agentEvaluationJsonFile($evidenceRoot . '/proxy.json');
        $ledger = agentEvaluationRequireObject($proxy, 'ledger', 'comparison measurement proxy');
        $measuredUsage = agentEvaluationControllerProxyAggregateUsage($ledger);
    }
    foreach ($measuredUsage as $name => $value) {
        if ($usage[$name] !== $value) {
            throw new RuntimeException('Comparison token measurements must match the retained provider ledger or remain unknown.');
        }
    }
    $generation = null;
    $elapsed = null;
    if (isset($artifacts['generation-process.json'])) {
        $generation = agentEvaluationJsonFile($evidenceRoot . '/generation-process.json');
        agentEvaluationControllerValidateUpstreamFailureEvidence($generation);
        $elapsed = agentEvaluationRequireNonNegativeInteger($generation, 'elapsed_milliseconds', 'comparison generation timing');
        if ($elapsed > 86_400_000) {
            throw new RuntimeException('Comparison generation timing exceeds its retained measurement bound.');
        }
    }
    if ($attempt['elapsed_milliseconds'] !== $elapsed) {
        throw new RuntimeException('Comparison elapsed time must match retained generation timing or remain unknown.');
    }
    if (($attempt['status'] ?? null) !== 'complete') {
        return;
    }
    if ($proxy === null || $generation === null
        || ($proxy['synthetic_upstream'] ?? null) !== false
        || ($proxy['upstream_origin'] ?? null) !== 'https://api.openai.com'
        || agentEvaluationRequireNonNegativeInteger($ledger, 'request_count', 'comparison completed proxy') < 1
        || ($ledger['reserved_input'] ?? null) !== 0 || ($ledger['reserved_output'] ?? null) !== 0
        || !array_key_exists('request_sha256', $ledger) || $ledger['request_sha256'] !== null
        || ($ledger['blocked'] ?? null) !== false
        || !array_key_exists('failure_reason', $ledger) || $ledger['failure_reason'] !== null
    ) {
        throw new RuntimeException('A completed comparison requires a real settled provider request and retained measurements.');
    }
    $generationCleanup = agentEvaluationRequireObject($generation, 'cleanup', 'comparison generation process');
    if (($generation['termination_reason'] ?? null) !== 'completed' || ($generation['exit_code'] ?? null) !== 0
        || ($generation['synthetic_upstream'] ?? null) !== false
        || ($generation['timed_out'] ?? null) !== false || ($generation['output_limit_exceeded'] ?? null) !== false
        || ($generationCleanup['container_stopped'] ?? null) !== true || ($generationCleanup['oom_killed'] ?? null) !== false
        || ($generationCleanup['pid'] ?? null) !== 0
    ) {
        throw new RuntimeException('A completed comparison requires a terminal nonsynthetic generation process.');
    }
    foreach (['generation-cleanup.json', 'oci-cleanup.json', 'cleanup.json'] as $name) {
        if (!isset($artifacts[$name])) {
            throw new RuntimeException('A completed comparison requires retained generation and final cleanup proof.');
        }
    }
    $generationRemoved = agentEvaluationJsonFile($evidenceRoot . '/generation-cleanup.json');
    $generationOci = agentEvaluationRequireObject($generationRemoved, 'oci', 'comparison generation destruction');
    $ociCleanup = agentEvaluationJsonFile($evidenceRoot . '/oci-cleanup.json');
    $cleanup = agentEvaluationJsonFile($evidenceRoot . '/cleanup.json');
    if (($generationRemoved['status'] ?? null) !== 'pass' || ($generationOci['status'] ?? null) !== 'pass'
        || ($generationOci['generation_destroyed'] ?? null) !== true
        || ($ociCleanup['status'] ?? null) !== 'pass' || ($ociCleanup['verified'] ?? null) !== true
        || ($ociCleanup['containers_remaining'] ?? null) !== 0 || ($ociCleanup['volumes_remaining'] ?? null) !== 0
        || ($cleanup['status'] ?? null) !== 'pass'
        || !array_key_exists('primary_failure', $cleanup) || $cleanup['primary_failure'] !== null
        || !array_key_exists('cleanup_failure', $cleanup) || $cleanup['cleanup_failure'] !== null
    ) {
        throw new RuntimeException('A completed comparison requires verified generation destruction and final cleanup.');
    }
}

function agentEvaluationControllerRetainComparisonImplementation(string $root, string $campaignRoot, string $expectedHash): void
{
    agentEvaluationRequireHash($expectedHash, 'comparison implementation snapshot');
    $snapshot = agentEvaluationControllerFreshAbsoluteTarget($campaignRoot . '/implementation', 'comparison implementation snapshot');
    if (!mkdir($snapshot, 0700) || !mkdir($snapshot . '/tools', 0700)) {
        throw new RuntimeException('Unable to retain the fixed comparison implementation snapshot.');
    }
    foreach (['agent-evaluation', 'agent-evaluation-controller'] as $name) {
        agentEvaluationControllerCopyTree($root . '/tools/' . $name, $snapshot . '/tools/' . $name,
            'comparison implementation snapshot', true);
        $entrypoint = file_get_contents($root . '/tools/' . $name . '.php');
        if (!is_string($entrypoint)) {
            throw new RuntimeException('Comparison implementation entrypoint is unreadable.');
        }
        agentEvaluationControllerWriteArtifact($snapshot . '/tools', $name . '.php', $entrypoint);
    }
    if (agentEvaluationControllerComparisonCodeHash($snapshot) !== $expectedHash) {
        throw new RuntimeException('Comparison implementation changed before its snapshot was retained.');
    }
}
