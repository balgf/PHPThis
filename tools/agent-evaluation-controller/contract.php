<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_VERSION = 2;
const AGENT_EVALUATION_CONTROLLER_TASK_ID = 'change.simple-ping';
const AGENT_EVALUATION_CONTROLLER_TASK_REVISION = 28;
const AGENT_EVALUATION_CONTROLLER_OCI_ONLY = true;
const AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY = true;
const AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK = true;
const AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER = 'codex-exec';
const AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER = 'fake-codex';
const AGENT_EVALUATION_CONTROLLER_RUNNER_GEMINI = 'gemini-exec';
const AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_GEMINI = 'fake-gemini';
const AGENT_EVALUATION_CONTROLLER_GEMINI_CREDENTIAL_BROKER = 'gemini-api-run-proxy';
const AGENT_EVALUATION_CONTROLLER_FAKE_CONDITION = 'repository-only-controller-v0.2-fake';
const AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_VERSION = 'fixture-1';
const AGENT_EVALUATION_CONTROLLER_FAKE_MODEL = 'fake-codex-v1';
const AGENT_EVALUATION_CONTROLLER_FAKE_GEMINI_MODEL = 'fake-gemini-v1';
const AGENT_EVALUATION_CONTROLLER_FAKE_MODEL_REVISION = 'fixture-1';
const AGENT_EVALUATION_CONTROLLER_FAKE_UID = 65_534;
const AGENT_EVALUATION_CONTROLLER_CPU_MILLIS = 1_000;
const AGENT_EVALUATION_CONTROLLER_MEMORY_BYTES = 1_073_741_824;
const AGENT_EVALUATION_CONTROLLER_DISK_BYTES = 1_073_741_824;
const AGENT_EVALUATION_CONTROLLER_PROCESS_LIMIT = 64;

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $task
 * @return array{run_id: string, task_id: string}
 */
function agentEvaluationControllerValidateRequest(array $request, array $task): array
{
    agentEvaluationControllerRequireAdmittedTask($task);
    agentEvaluationRequireExactKeys($request, ['run_id', 'task_id'], 'controller request');
    $runId = agentEvaluationRequireString($request, 'run_id', 'controller request');

    if (preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1) {
        throw new RuntimeException('Controller request run ID must be exactly 32 lowercase hexadecimal characters.');
    }

    $taskId = agentEvaluationRequireString($request, 'task_id', 'controller request');

    if ($taskId !== $task['id']) {
        throw new RuntimeException(isset($task['selected_condition']) || ($task['schema_version'] ?? null) === 3
            ? 'Controller request must select its exact admitted task.'
            : 'Controller request must select change.simple-ping.');
    }

    return ['run_id' => $runId, 'task_id' => $taskId];
}

/**
 * @param array<string, mixed> $profile
 * @param array<string, mixed> $task
 * @return array{
 *   condition: string,
 *   runner: array{name: string, version: string},
 *   model: array<string, mixed>,
 *   context: array<string, mixed>,
 *   tools: list<mixed>,
 *   transport_tools?: array{kind: string, count: int, sha256: string}|null,
 *   budgets: array<string, mixed>,
 *   isolation: array<string, mixed>
 * }
 */
function agentEvaluationControllerValidateProfile(array $profile, array $task, bool $synthetic, bool $calibration = false, int $calibrationRevision = 1): array
{
    agentEvaluationControllerRequireAdmittedTask($task);
    $explanation = ($task['schema_version'] ?? null) === 3;
    agentEvaluationRequireExactKeys(
        $profile,
        ['condition', 'runner', 'model', 'context', 'tools',
            ...($explanation ? ['transport_tools'] : []), 'budgets', 'isolation'],
        'controller execution profile',
    );

    $condition = agentEvaluationControllerBoundedLabel(
        agentEvaluationRequireNonEmptyString($profile, 'condition', 'controller execution profile'),
        'Controller execution-profile condition',
    );
    if (isset($task['selected_condition']) && ($synthetic || $condition !== $task['selected_condition'])) {
        throw new RuntimeException('Comparison execution must use its exact admitted live condition.');
    }

    if ($explanation && $condition !== 'repository-only') {
        throw new RuntimeException('Explanation execution must use its fixed repository-only condition.');
    }

    if ($synthetic && !$explanation && $condition !== AGENT_EVALUATION_CONTROLLER_FAKE_CONDITION) {
        throw new RuntimeException('Controller synthetic condition must equal its fixed fixture identity.');
    }
    $runner = agentEvaluationRequireObject($profile, 'runner', 'controller execution profile');
    agentEvaluationRequireExactKeys($runner, ['name', 'version'], 'controller runner profile');
    $runnerName = agentEvaluationRequireString($runner, 'name', 'controller runner profile');
    $allowedRunners = $synthetic
        ? [AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER, AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_GEMINI]
        : [AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER, AGENT_EVALUATION_CONTROLLER_RUNNER_GEMINI];

    if (!in_array($runnerName, $allowedRunners, true)) {
        throw new RuntimeException($synthetic
            ? 'Controller execution profile must use the fixed fake-codex or fake-gemini runner.'
            : 'Controller execution profile must use the fixed codex-exec or gemini-exec runner.');
    }

    $runnerVersion = agentEvaluationControllerBoundedLabel(
        agentEvaluationRequireNonEmptyString($runner, 'version', 'controller runner profile'),
        'Controller runner version',
    );

    if ($synthetic && $runnerVersion !== AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_VERSION) {
        throw new RuntimeException('Controller synthetic runner version must equal its fixed fixture identity.');
    }
    $model = agentEvaluationRequireObject($profile, 'model', 'controller execution profile');
    agentEvaluationValidateModel($model);
    agentEvaluationControllerValidateModelProfile($model, $synthetic, $runnerName);
    $context = agentEvaluationRequireObject($profile, 'context', 'controller execution profile');
    agentEvaluationValidateContext($context);
    $bundleId = $context['bundle_id'] ?? null;

    if (is_string($bundleId)) {
        agentEvaluationControllerBoundedLabel($bundleId, 'Controller context bundle ID');
    }

    if (
        $synthetic
        && ($context['bundle_id'] !== null || $context['bundle_sha256'] !== null)
    ) {
        throw new RuntimeException('Controller synthetic context must be the fixed empty bundle identity.');
    }

    $tools = agentEvaluationRequireList($profile, 'tools', 'controller execution profile');
    agentEvaluationValidateTools($tools);
    $tools = array_map(
        static fn (mixed $tool): array => agentEvaluationValueObject($tool, 'controller tool profile'),
        $tools,
    );
    agentEvaluationControllerValidateToolsProfile($tools, $synthetic, $explanation);
    $transportTools = $explanation
        ? agentEvaluationNormalizeExplanationTransportTools(
            $profile['transport_tools'],
            'controller explanation transport tools',
        )
        : null;
    if ($explanation) {
        $taskProfile = agentEvaluationNormalizeExplanationExecutionProfile(
            agentEvaluationRequireObject($task, 'execution_profile', 'explanation task'),
            'explanation task execution profile',
        );
        $runProfile = agentEvaluationNormalizeExplanationExecutionProfile(
            ['condition' => $condition, 'runner' => ['name' => $runnerName, 'version' => $runnerVersion],
                'model' => $model, 'context' => $context, 'tools' => $tools,
                'transport_tools' => $transportTools],
            'controller explanation execution profile',
        );
        if ((!$synthetic && $runProfile !== $taskProfile)
            || ($synthetic && ($runProfile['condition'] !== $taskProfile['condition']
                || $runProfile['runner']['name'] !== AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER
                || $runProfile['context'] !== $taskProfile['context']
                || $runProfile['tools'] !== $taskProfile['tools']
                || $runProfile['transport_tools'] !== null))) {
            throw new RuntimeException('Explanation execution must use its exact pinned read-only task profile.');
        }
    }
    $budgets = agentEvaluationRequireObject($profile, 'budgets', 'controller execution profile');
    /** @var array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $taskBudgets */
    $taskBudgets = $task['budgets'];
    if ($calibration) {
        if ($synthetic || ($task['schema_version'] ?? null) !== 2) {
            throw new RuntimeException('Calibration requires an admitted live comparison fixture.');
        }
        $taskBudgets = agentEvaluationControllerCalibrationBudgets($calibrationRevision);
    }
    agentEvaluationValidateRunBudgets($budgets, $taskBudgets);
    $isolation = agentEvaluationRequireObject($profile, 'isolation', 'controller execution profile');
    agentEvaluationControllerValidateIsolationProfile($isolation, $taskBudgets, $synthetic, $runnerName);

    $validated = [
        'condition' => $condition,
        'runner' => ['name' => $runnerName, 'version' => $runnerVersion],
        'model' => $model,
        'context' => $context,
        'tools' => $tools,
        'budgets' => $budgets,
        'isolation' => $isolation,
    ];
    if ($explanation) {
        $validated['transport_tools'] = $transportTools;
    }
    return $validated;
}

/** @param array<string, mixed> $task */
function agentEvaluationControllerRequireFixedTask(array $task): void
{
    if (
        ($task['id'] ?? null) !== AGENT_EVALUATION_CONTROLLER_TASK_ID
        || ($task['revision'] ?? null) !== AGENT_EVALUATION_CONTROLLER_TASK_REVISION
        || ($task['comparative_claims'] ?? null) !== false
    ) {
        throw new RuntimeException(
            'Controller v0.2 supports only change.simple-ping revision 28 without comparative claims.',
        );
    }

    if (!is_array($task['budgets'] ?? null) || !is_array($task['base'] ?? null)) {
        throw new RuntimeException('Controller task is missing its validated v0.1 budgets or base identity.');
    }

    $directory = $task['directory'] ?? null;

    if (!is_string($directory)) {
        throw new RuntimeException('Controller task is missing its validated v0.1 directory.');
    }

    $authoritative = agentEvaluationTask(dirname(dirname($directory)), AGENT_EVALUATION_CONTROLLER_TASK_ID);

    if ($authoritative !== $task) {
        throw new RuntimeException('Controller task must equal the selected authoritative v0.1 task revision.');
    }
}

/**
 * @param array<string, mixed> $task
 * @param array<string, mixed> $condition
 * @return array<string, mixed>
 */
function agentEvaluationControllerAdmitComparisonTask(array $task, array $condition): array
{
    $directory = agentEvaluationRequireString($task, 'directory', 'comparison task');
    $id = agentEvaluationRequireString($task, 'id', 'comparison task');
    $authoritative = agentEvaluationComparisonTask(dirname(dirname($directory)), $id);
    if ($authoritative !== $task || !in_array($condition, $authoritative['conditions'], true)) {
        throw new RuntimeException('Comparison execution must select the exact authoritative task and condition.');
    }
    return [...$authoritative, 'selected_condition' => agentEvaluationRequireString($condition, 'id', 'comparison condition'),
        'base' => agentEvaluationRequireObject($condition, 'base', 'comparison condition'),
        'workspace_policy' => agentEvaluationRequireObject($condition, 'workspace_policy', 'comparison condition')];
}

/** @param array<string, mixed> $task */
function agentEvaluationControllerRequireAdmittedTask(array $task): void
{
    if (($task['schema_version'] ?? null) === 3) {
        $directory = agentEvaluationRequireString($task, 'directory', 'explanation task');
        $authoritative = agentEvaluationExplanationTask(dirname(dirname($directory)));
        if ($authoritative !== $task) {
            throw new RuntimeException('Explanation task does not match its authoritative pinned revision.');
        }
        return;
    }
    if (($task['schema_version'] ?? null) !== 2) {
        agentEvaluationControllerRequireFixedTask($task);
        return;
    }
    $selected = agentEvaluationRequireString($task, 'selected_condition', 'admitted comparison task');
    $original = $task;
    unset($original['selected_condition'], $original['base'], $original['workspace_policy']);
    foreach (agentEvaluationRequireList($original, 'conditions', 'admitted comparison task') as $value) {
        $condition = agentEvaluationValueObject($value, 'admitted comparison condition');
        if (($condition['id'] ?? null) === $selected && agentEvaluationControllerAdmitComparisonTask($original, $condition) === $task) {
            return;
        }
    }
    throw new RuntimeException('Comparison task does not match its admitted condition.');
}

/** @param array<string, mixed> $identity */
function agentEvaluationControllerValidateComparisonDatabase(array $identity): void
{
    $toolchains = agentEvaluationRequireObject($identity, 'toolchains', 'comparison runtime identity');
    $generation = agentEvaluationRequireObject($toolchains, 'generation', 'comparison runtime identity');
    $scoring = agentEvaluationRequireObject($toolchains, 'scoring', 'comparison runtime identity');
    $database = agentEvaluationRequireObject($generation, 'database', 'comparison generation database');
    if (!in_array('sqlite', agentEvaluationRequireStringList($database, 'pdo_drivers', 'comparison database'), true)
        || !agentEvaluationRequireBoolean($database, 'sqlite_json1', 'comparison database')
        || $database !== agentEvaluationRequireObject($scoring, 'database', 'comparison scoring database')) {
        throw new RuntimeException('Comparison requires matching available PDO SQLite and JSON1 in both runtime images.');
    }
    foreach (['pdo_sqlite_version', 'sqlite_version'] as $name) {
        if (preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', agentEvaluationRequireString($database, $name, 'comparison database')) !== 1) {
            throw new RuntimeException('Comparison database versions must be exact observed identities.');
        }
    }
}

/** @param array<string, array<string, mixed>> $inputs */
function agentEvaluationControllerValidateComparisonHoldoutVisibility(array $inputs): void
{
    foreach ($inputs as $privateInput) {
        $privatePath = agentEvaluationRequireString($privateInput, 'holdout_path', 'comparison private input');
        foreach ($inputs as $visibleInput) {
            $visibleExecution = agentEvaluationRequireObject($visibleInput, 'execution', 'comparison visible input');
            $visibleRoot = agentEvaluationRequireString($visibleExecution, 'prepared_dependencies', 'comparison visible input');
            if (agentEvaluationControllerPathsOverlap($visibleRoot, $privatePath)) {
                throw new RuntimeException('Every private holdout must be outside every condition generation input.');
            }
        }
    }
}

/**
 * @param array<string, mixed> $task
 * @return array{
 *   allowed_existing_paths: list<string>,
 *   allowed_new_paths: list<string>,
 *   protected_paths: list<string>,
 *   max_changed_files: int,
 *   max_added_lines: int,
 *   max_deleted_lines: int
 * }
 */
function agentEvaluationControllerWorkspacePolicy(array $task): array
{
    agentEvaluationControllerRequireAdmittedTask($task);
    $directory = $task['directory'] ?? null;

    if (!is_string($directory)) {
        throw new RuntimeException('Controller task directory is unavailable.');
    }

    $zeroWrite = ($task['schema_version'] ?? null) === 3;
    if ($zeroWrite) {
        $policy = agentEvaluationRequireObject($task, 'workspace_policy', 'explanation task');
    } elseif (isset($task['selected_condition'])) {
        $policy = agentEvaluationRequireObject($task, 'workspace_policy', 'admitted comparison task');
    } else {
        $document = agentEvaluationJsonFile($directory . '/task.json');
        $policy = agentEvaluationRequireObject($document, 'workspace_policy', 'controller task document');
        agentEvaluationValidateWorkspacePolicy($policy, AGENT_EVALUATION_CONTROLLER_TASK_ID);
    }

    return agentEvaluationControllerNormalizeWorkspacePolicy($policy, $zeroWrite);
}

/**
 * @param array<string, mixed> $policy
 * @return array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>,
 *   max_changed_files: int, max_added_lines: int, max_deleted_lines: int}
 */
function agentEvaluationControllerNormalizeWorkspacePolicy(array $policy, bool $zeroWrite = false): array
{
    agentEvaluationRequireExactKeys($policy, ['allowed_existing_paths', 'allowed_new_paths', 'protected_paths',
        'max_changed_files', 'max_added_lines', 'max_deleted_lines'], 'controller workspace policy');
    foreach (['allowed_existing_paths', 'allowed_new_paths', 'protected_paths'] as $key) {
        foreach (agentEvaluationRequireStringList($policy, $key, 'controller workspace policy') as $path) {
            agentEvaluationRequireRelativePath($path, 'controller workspace policy');
        }
    }
    $normalized = [
        'allowed_existing_paths' => agentEvaluationRequireStringList(
            $policy,
            'allowed_existing_paths',
            'controller workspace policy',
        ),
        'allowed_new_paths' => agentEvaluationRequireStringList(
            $policy,
            'allowed_new_paths',
            'controller workspace policy',
        ),
        'protected_paths' => agentEvaluationRequireStringList(
            $policy,
            'protected_paths',
            'controller workspace policy',
        ),
        'max_changed_files' => $zeroWrite
            ? agentEvaluationRequireNonNegativeInteger($policy, 'max_changed_files', 'controller workspace policy')
            : agentEvaluationRequirePositiveInteger($policy, 'max_changed_files', 'controller workspace policy'),
        'max_added_lines' => agentEvaluationRequireNonNegativeInteger(
            $policy,
            'max_added_lines',
            'controller workspace policy',
        ),
        'max_deleted_lines' => agentEvaluationRequireNonNegativeInteger(
            $policy,
            'max_deleted_lines',
            'controller workspace policy',
        ),
    ];

    if ($zeroWrite && $normalized !== [
        'allowed_existing_paths' => [], 'allowed_new_paths' => [], 'protected_paths' => [],
        'max_changed_files' => 0, 'max_added_lines' => 0, 'max_deleted_lines' => 0,
    ]) {
        throw new RuntimeException('Explanation workspace policy must prohibit every candidate mutation.');
    }

    return $normalized;
}

/**
 * @param array<string, mixed> $policy
 * @return array<string, mixed>
 */
function agentEvaluationControllerWorkspacePolicyEvidence(array $policy, bool $zeroWrite = false): array
{
    return ['schema_version' => 1, 'kind' => 'generation-workspace-policy-v1',
        'policy' => agentEvaluationControllerNormalizeWorkspacePolicy($policy, $zeroWrite)];
}

/** @param array<string, mixed> $policy */
function agentEvaluationControllerGenerationPrompt(string $sourcePrompt, array $policy, ?int $calibrationRevision = null): string
{
    if ($sourcePrompt === '' || strlen($sourcePrompt) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES || str_contains($sourcePrompt, "\0")) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_PROMPT_INVALID');
    }
    $policy = agentEvaluationControllerNormalizeWorkspacePolicy($policy);
    $prompt = $sourcePrompt . "\n\n" . <<<'POLICY'
## Enforced evaluation workspace policy (v1)

The following policy is enforced when the candidate is frozen, even when the application check passes. Allowed paths are exact file paths relative to the candidate root, not directory permissions. Change or delete existing files only in allowed_existing_paths; create files only in allowed_new_paths. Do not add helper files outside those lists. All other files are read-only. Protected paths also protect their descendants. Preserve the directory structure and existing file modes; new files must be ordinary non-executable files. The three numeric limits apply to the entire candidate patch, including tests. If the task cannot be completed within these limits and the application contract, report the conflict instead of widening the change.

POLICY;
    $prompt .= "\n```json\n" . agentEvaluationJson($policy) . "```\n";
    if ($calibrationRevision !== null) {
        $prompt .= "\n\n" . agentEvaluationControllerCalibrationPrompt($calibrationRevision);
    }
    if (strlen($prompt) > AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_LIVE_PROMPT_INVALID');
    }
    return $prompt;
}

/**
 * Validate source and effective prompts against the hash-bound task document,
 * without loading any dependency, fixture, or private scoring input.
 *
 * @param array<string, mixed> $taskDocument
 */
function agentEvaluationControllerValidatePromptEvidence(string $evidenceRoot, array $taskDocument, ?string $condition, ?int $calibrationRevision = null): void
{
    $policy = null;
    if (($taskDocument['schema_version'] ?? null) === 2) {
        foreach (agentEvaluationRequireList($taskDocument, 'conditions', 'generation source task') as $value) {
            $selected = agentEvaluationValueObject($value, 'generation source condition');
            if (($selected['id'] ?? null) === $condition) {
                if ($policy !== null) {
                    throw new RuntimeException('Generation policy requires one exact task condition.');
                }
                $policy = agentEvaluationRequireObject($selected, 'workspace_policy', 'generation source condition');
            }
        }
    } elseif ($condition === null) {
        $policy = agentEvaluationRequireObject($taskDocument, 'workspace_policy', 'generation source task');
    }
    if ($policy === null) {
        throw new RuntimeException('Generation policy requires one exact task condition.');
    }
    $policyBytes = agentEvaluationJson(agentEvaluationControllerWorkspacePolicyEvidence($policy));
    agentEvaluationRequireFileHash($evidenceRoot . '/workspace-policy.json', hash('sha256', $policyBytes), 'generation workspace policy');
    $descriptor = agentEvaluationRequireObject($taskDocument, 'prompt', 'generation source task');
    agentEvaluationRequireFileHash($evidenceRoot . '/source-prompt.md',
        agentEvaluationRequireString($descriptor, 'sha256', 'generation source prompt'), 'generation source prompt');
    $source = file_get_contents($evidenceRoot . '/source-prompt.md', false, null, 0, AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES + 1);
    if (!is_string($source)) {
        throw new RuntimeException('Generation source prompt is unavailable.');
    }
    $prompt = agentEvaluationControllerGenerationPrompt($source, $policy, $calibrationRevision);
    agentEvaluationRequireFileHash($evidenceRoot . '/prompt.md', hash('sha256', $prompt), 'generation effective prompt');
}

/** @param array<string, mixed> $model */
function agentEvaluationControllerValidateModelProfile(
    array $model,
    bool $synthetic,
    string $runnerName = AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER,
): void {
    $expectedProvider = $synthetic
        ? 'synthetic'
        : ($runnerName === AGENT_EVALUATION_CONTROLLER_RUNNER_GEMINI ? 'google' : 'openai');

    if (($model['provider'] ?? null) !== $expectedProvider) {
        throw new RuntimeException("Controller model profile must use provider {$expectedProvider}.");
    }

    $id = $model['id'] ?? null;

    if (!is_string($id)) {
        throw new RuntimeException('Controller model profile ID must be a string.');
    }

    if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $id) !== 1) {
        throw new RuntimeException('Controller model ID must use the fixed bounded runner grammar.');
    }

    $revision = $model['revision'] ?? null;

    if (is_string($revision)) {
        agentEvaluationControllerBoundedLabel($revision, 'Controller model revision');
    }
    $settings = agentEvaluationValueObject($model['settings'] ?? null, 'controller model settings');

    if ($synthetic) {
        agentEvaluationRequireExactKeys($settings, ['deterministic'], 'controller synthetic model settings');

        if (($settings['deterministic'] ?? null) !== true) {
            throw new RuntimeException('Controller synthetic model profile must be deterministic.');
        }

        $expectedModel = $runnerName === AGENT_EVALUATION_CONTROLLER_RUNNER_FAKE_GEMINI
            ? AGENT_EVALUATION_CONTROLLER_FAKE_GEMINI_MODEL
            : AGENT_EVALUATION_CONTROLLER_FAKE_MODEL;

        if (
            $id !== $expectedModel
            || $revision !== AGENT_EVALUATION_CONTROLLER_FAKE_MODEL_REVISION
        ) {
            throw new RuntimeException('Controller synthetic model identity must equal its fixed fixture identity.');
        }

        return;
    }

    if ($runnerName === AGENT_EVALUATION_CONTROLLER_RUNNER_GEMINI) {
        agentEvaluationRequireExactKeys($settings, ['thinking_budget'], 'controller live model settings');
        $thinkingBudget = agentEvaluationRequireString($settings, 'thinking_budget', 'controller live model settings');

        if (!in_array($thinkingBudget, ['low', 'medium', 'high', 'max', 'off'], true)) {
            throw new RuntimeException(
                'Controller live thinking budget must be low, medium, high, max, or off.',
            );
        }

        return;
    }

    agentEvaluationRequireExactKeys($settings, ['reasoning_effort'], 'controller live model settings');
    $reasoningEffort = agentEvaluationRequireString($settings, 'reasoning_effort', 'controller live model settings');

    if (!in_array($reasoningEffort, ['low', 'medium', 'high', 'xhigh', 'max', 'ultra'], true)) {
        throw new RuntimeException(
            'Controller live reasoning effort must be low, medium, high, xhigh, max, or ultra.',
        );
    }
}

/** @param list<mixed> $tools */
function agentEvaluationControllerValidateToolsProfile(array $tools, bool $synthetic, bool $explanation = false): void
{
    if ($synthetic && !$explanation) {
        if ($tools !== []) {
            throw new RuntimeException('Controller synthetic runner must not expose agent tools.');
        }

        return;
    }

    $expected = [
        [
            'name' => 'shell',
            'version' => null,
            'permissions' => $explanation
                ? ['workspace-read', 'process-execute']
                : ['workspace-read', 'workspace-write', 'process-execute'],
        ],
    ];

    if ($tools !== $expected) {
        throw new RuntimeException('Controller live runner must expose only the fixed bounded shell tool profile.');
    }
}

/**
 * @param array<string, mixed> $isolation
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets
 */
function agentEvaluationControllerValidateIsolationProfile(
    array $isolation,
    array $budgets,
    bool $synthetic,
    string $runnerName = AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER,
): void {
    agentEvaluationRequireExactKeys(
        $isolation,
        [
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
        ],
        'controller isolation profile',
    );

    $expectedBroker = $runnerName === AGENT_EVALUATION_CONTROLLER_RUNNER_GEMINI
        ? AGENT_EVALUATION_CONTROLLER_GEMINI_CREDENTIAL_BROKER
        : 'responses-api-run-proxy';

    $expectedStrings = $synthetic
        ? [
            'launcher' => 'synthetic-test',
            'credential_broker' => 'none',
            'network' => 'none',
            'descendant_cleanup' => 'in-process-fixture',
        ]
        : [
            'launcher' => 'docker-oci',
            'credential_broker' => $expectedBroker,
            'network' => 'proxy-only',
            'descendant_cleanup' => 'container-destroy',
        ];

    foreach ($expectedStrings as $name => $expected) {
        if (($isolation[$name] ?? null) !== $expected) {
            throw new RuntimeException("Controller isolation field {$name} must equal {$expected}.");
        }
    }

    $digest = $isolation['image_digest'] ?? null;
    $imageReference = $isolation['image_reference'] ?? null;

    if ($synthetic) {
        if ($imageReference !== null || $digest !== null) {
            throw new RuntimeException('Controller synthetic isolation profile must not claim an OCI image reference.');
        }
    } elseif (!is_string($digest) || preg_match('/\Asha256:[a-f0-9]{64}\z/D', $digest) !== 1) {
        throw new RuntimeException('Controller live isolation profile requires one pinned OCI SHA-256 image digest.');
    } elseif (
        !is_string($imageReference)
        || $imageReference === ''
        || strlen($imageReference) > 255
        || preg_match('/[\x00-\x20\x7F]/', $imageReference) === 1
        || substr_count($imageReference, '@') !== 1
        || preg_match(
            '/\A[a-z0-9][a-z0-9._:\/-]*@sha256:[a-f0-9]{64}\z/D',
            $imageReference,
        ) !== 1
        || !str_ends_with($imageReference, '@' . $digest)
    ) {
        throw new RuntimeException('Controller live isolation profile requires one bounded digest-pinned OCI image reference.');
    }

    foreach (
        [
            'root_read_only',
            'capabilities_dropped',
            'no_new_privileges',
            'candidate_git_absent',
            'dependencies_read_only',
        ] as $name
    ) {
        if (($isolation[$name] ?? null) !== true) {
            throw new RuntimeException("Controller isolation field {$name} must be true.");
        }
    }

    $uid = agentEvaluationRequirePositiveInteger($isolation, 'uid', 'controller isolation profile');

    if ($synthetic && $uid !== AGENT_EVALUATION_CONTROLLER_FAKE_UID) {
        throw new RuntimeException('Controller synthetic isolation UID must equal its fixed fixture identity.');
    }

    if ($uid > 2_147_483_647) {
        throw new RuntimeException('Controller isolation UID is outside the supported positive range.');
    }

    $expectedIntegers = [
        'cpu_millis' => AGENT_EVALUATION_CONTROLLER_CPU_MILLIS,
        'memory_bytes' => AGENT_EVALUATION_CONTROLLER_MEMORY_BYTES,
        'disk_bytes' => AGENT_EVALUATION_CONTROLLER_DISK_BYTES,
        'processes' => AGENT_EVALUATION_CONTROLLER_PROCESS_LIMIT,
        'wall_seconds' => $budgets['wall_seconds'],
        'model_tokens' => $budgets['model_tokens'],
        'output_bytes' => $budgets['command_output_bytes'],
    ];

    foreach ($expectedIntegers as $name => $expected) {
        if (agentEvaluationRequirePositiveInteger($isolation, $name, 'controller isolation profile') !== $expected) {
            throw new RuntimeException("Controller isolation field {$name} does not match its fixed budget.");
        }
    }
}

function agentEvaluationControllerBoundedLabel(string $value, string $owner): string
{
    if (
        $value === ''
        || strlen($value) > 128
        || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
    ) {
        throw new RuntimeException("{$owner} must be non-empty bounded text without control characters.");
    }

    return $value;
}

/** @return array<string, mixed> */
function agentEvaluationControllerReadLiveConfiguration(string $path): array
{
    $size = is_file($path) && !is_link($path) ? filesize($path) : false;

    if (!is_int($size) || $size < 1 || $size > 65_536) {
        throw new RuntimeException('Live configuration must be one bounded regular JSON file.');
    }

    return agentEvaluationControllerValidateLiveConfiguration(agentEvaluationJsonFile($path));
}

/** @param array<string, mixed> $task
 * @return array<string, mixed>
 */
function agentEvaluationControllerReadExplanationLiveConfiguration(string $path, array $task): array
{
    $size = is_file($path) && !is_link($path) ? filesize($path) : false;
    if (!is_int($size) || $size < 1 || $size > 65_536) {
        throw new RuntimeException('Explanation live configuration must be one bounded regular JSON file.');
    }
    return agentEvaluationControllerValidateLiveConfiguration(
        agentEvaluationJsonFile($path),
        $task,
        1,
        false,
        1,
        true,
    );
}

/** @param array<string, mixed> $task
 * @return array<string, mixed>
 */
function agentEvaluationControllerReadExplanationOciControlConfiguration(
    string $path,
    array $task,
    #[SensitiveParameter] string $credential,
): array {
    $size = is_file($path) && !is_link($path) ? filesize($path) : false;
    if (!is_int($size) || $size < 1 || $size > 65_536) {
        throw new RuntimeException('Explanation OCI control configuration must be one bounded regular JSON file.');
    }
    return agentEvaluationControllerValidateExplanationOciControlConfiguration(
        agentEvaluationJsonFile($path),
        $task,
        $credential,
    );
}

/**
 * @param array<string, mixed> $configuration
 * @param array<string, mixed> $task
 * @return array<string, mixed>
 */
function agentEvaluationControllerValidateExplanationOciControlConfiguration(
    array $configuration,
    array $task,
    #[SensitiveParameter] string $credential,
): array {
    if (!agentEvaluationControllerTestingEnabled()
        || !agentEvaluationControllerOciSyntheticUpstream()
        || $credential !== ''
    ) {
        throw new RuntimeException('Explanation OCI control requires its test-only zero-provider boundary.');
    }
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'explanation OCI control configuration');
    agentEvaluationRequireExactKeys(
        $approval,
        ['reference', 'model', 'runs', 'spending_ceiling_usd', 'run_id'],
        'explanation OCI control approval',
    );
    $taskProfile = agentEvaluationNormalizeExplanationExecutionProfile(
        agentEvaluationRequireObject($task, 'execution_profile', 'explanation task'),
        'explanation task execution profile',
    );
    $controlRunId = agentEvaluationRequireString($approval, 'run_id', 'explanation OCI control approval');
    if (($approval['reference'] ?? null) !== 'synthetic-oci-explanation-control'
        || ($approval['model'] ?? null) !== $taskProfile['model']['id']
        || ($approval['runs'] ?? null) !== 1
        || ($approval['spending_ceiling_usd'] ?? null) !== '0.00'
        || preg_match('/\A[a-f0-9]{32}\z/D', $controlRunId) !== 1
    ) {
        throw new RuntimeException('Explanation OCI control requires its exact synthetic zero-spend approval.');
    }
    $productionShape = $configuration;
    $productionApproval = $approval;
    $productionApproval['spending_ceiling_usd'] = '0.60';
    $productionShape['approval'] = $productionApproval;
    $validated = agentEvaluationControllerValidateLiveConfiguration(
        $productionShape,
        $task,
        1,
        false,
        1,
        true,
    );
    $validated['approval'] = $approval;
    return $validated;
}

/**
 * @param array<string, mixed> $configuration
 * @param array<string, mixed>|null $comparisonTask
 * @return array<string, mixed>
 */
function agentEvaluationControllerValidateLiveConfiguration(
    array $configuration,
    ?array $comparisonTask = null,
    int $approvedRuns = 1,
    bool $calibration = false,
    int $calibrationRevision = 1,
    bool $explanation = false,
): array
{
    if ($explanation) {
        if ($calibration || $approvedRuns !== 1 || ($comparisonTask['schema_version'] ?? null) !== 3) {
            throw new RuntimeException('Explanation live configuration must bind its one explicit schema-v3 run.');
        }
    } elseif ((!$calibration && !in_array($approvedRuns, [1, 60], true))
        || ($calibration && ($approvedRuns !== 6 || $comparisonTask === null))
        || ($comparisonTask === null) !== ($approvedRuns === 1)) {
        throw new RuntimeException('Live configuration must bind one smoke run or the fixed 60-slot comparison.');
    }
    agentEvaluationRequireExactKeys(
        $configuration,
        [
            'profile', 'engine', 'prepared_dependencies', 'prepared_lock',
            'prepared_dependencies_sha256', 'prepared_lock_sha256', 'approval',
        ],
        'controller live configuration',
    );
    $task = $comparisonTask ?? agentEvaluationTask(dirname(__DIR__) . '/agent-evaluation', AGENT_EVALUATION_CONTROLLER_TASK_ID);
    $profile = agentEvaluationControllerValidateProfile(
        agentEvaluationRequireObject($configuration, 'profile', 'controller live configuration'),
        $task,
        false,
        $calibration,
        $calibrationRevision,
    );
    $context = agentEvaluationRequireObject($profile, 'context', 'controller live profile');

    if (($comparisonTask === null || $explanation)
        && (($context['bundle_id'] ?? null) !== null || ($context['bundle_sha256'] ?? null) !== null)
    ) {
        throw new RuntimeException('The public smoke runner accepts repository context without an additional bundle.');
    }

    $engine = agentEvaluationRequireObject($configuration, 'engine', 'controller live configuration');
    agentEvaluationRequireExactKeys(
        $engine,
        [
            'docker_binary', 'docker_socket', 'generation_image', 'scoring_image',
            'generation_toolchain', 'scoring_toolchain',
        ],
        'controller OCI configuration',
    );
    foreach (['docker_binary', 'docker_socket'] as $name) {
        $value = agentEvaluationRequireNonEmptyString($engine, $name, 'controller OCI configuration');
        if (!str_starts_with($value, '/') || strlen($value) > 1_024 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RuntimeException('Controller OCI paths must be bounded absolute paths.');
        }
    }
    foreach (['generation_image', 'scoring_image'] as $name) {
        $value = agentEvaluationRequireNonEmptyString($engine, $name, 'controller OCI configuration');
        if (strlen($value) > 255 || preg_match('/\A[a-z0-9][a-z0-9._:\/-]*@sha256:[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new RuntimeException('Controller OCI images must use exact digest-pinned references.');
        }
    }
    $isolation = agentEvaluationRequireObject($profile, 'isolation', 'controller live profile');
    if ($isolation['uid'] !== 65534) {
        throw new RuntimeException('The live generation profile must record its fixed OCI identity 65534.');
    }
    if ($engine['generation_image'] !== $isolation['image_reference'] || $engine['generation_image'] === $engine['scoring_image']) {
        throw new RuntimeException('Generation identity must match the profile and scoring must use its separate image.');
    }
    foreach (['generation_toolchain', 'scoring_toolchain'] as $name) {
        $toolchain = agentEvaluationRequireObject($engine, $name, 'controller OCI configuration');
        agentEvaluationRequireExactKeys(
            $toolchain,
            ['php_version', 'composer_version', 'python_version', 'codex_version', 'relay_sha256'],
            'controller OCI toolchain',
        );
        foreach (['php_version', 'composer_version', 'python_version'] as $versionName) {
            $version = agentEvaluationRequireNonEmptyString($toolchain, $versionName, 'controller OCI toolchain');
            if (preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $version) !== 1) {
                throw new RuntimeException('Controller toolchain requires exact three-part versions.');
            }
        }
        if (!str_starts_with(agentEvaluationRequireString($toolchain, 'php_version', 'controller OCI toolchain'), '8.4.')) {
            throw new RuntimeException('Controller toolchain requires the supported PHP 8.4 minor.');
        }
        if ($name === 'generation_toolchain') {
            $runner = agentEvaluationRequireObject($profile, 'runner', 'controller live profile');
            if (agentEvaluationRequireNonEmptyString($toolchain, 'codex_version', 'controller OCI toolchain') !== $runner['version']) {
                throw new RuntimeException('Pinned Codex version must match the recorded runner version.');
            }
            $relaySha256 = agentEvaluationRequireHash(
                agentEvaluationRequireString($toolchain, 'relay_sha256', 'controller OCI toolchain'),
                'controller relay',
            );
            if (
                $explanation
                && !hash_equals(AGENT_EVALUATION_EXPLANATION_RELAY_SHA256, $relaySha256)
            ) {
                throw new RuntimeException(
                    'Explanation generation toolchain must use its exact pinned relay identity.',
                );
            }
        } elseif ($toolchain['codex_version'] !== null || $toolchain['relay_sha256'] !== null) {
            throw new RuntimeException('Scoring must have neither Codex nor the generation relay.');
        }
        $engine[$name] = $toolchain;
    }
    $generationToolchain = agentEvaluationRequireObject($engine, 'generation_toolchain', 'controller OCI configuration');
    $scoringToolchain = agentEvaluationRequireObject($engine, 'scoring_toolchain', 'controller OCI configuration');
    foreach (['php_version', 'composer_version'] as $versionName) {
        if ($generationToolchain[$versionName] !== $scoringToolchain[$versionName]) {
            throw new RuntimeException('Generation and scoring must use the same exact PHP and Composer versions.');
        }
    }

    $dependencies = agentEvaluationControllerExistingRoot(
        agentEvaluationRequireNonEmptyString($configuration, 'prepared_dependencies', 'controller live configuration'),
        'live prepared dependencies',
    );
    $lock = agentEvaluationRequireNonEmptyString($configuration, 'prepared_lock', 'controller live configuration');
    if (!str_starts_with($lock, '/') || !is_file($lock) || is_link($lock)) {
        throw new RuntimeException('Live preparation requires an explicit regular lock file outside the candidate.');
    }
    agentEvaluationRequireBoundedFile($lock, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'live prepared lock');
    foreach (['prepared_dependencies_sha256', 'prepared_lock_sha256'] as $name) {
        agentEvaluationRequireHash(
            agentEvaluationRequireString($configuration, $name, 'controller live configuration'),
            'controller prepared input',
        );
    }
    $lockHash = agentEvaluationRequireString($configuration, 'prepared_lock_sha256', 'controller live configuration');
    $dependencyHash = agentEvaluationRequireString($configuration, 'prepared_dependencies_sha256', 'controller live configuration');
    agentEvaluationRequireFileHash($lock, $lockHash, 'live prepared lock');
    $tree = agentEvaluationControllerDescribeTree($dependencies, 'live prepared dependencies', true);
    if (!hash_equals($dependencyHash, $tree['sha256']) || !is_file($dependencies . '/autoload.php')) {
        throw new RuntimeException('Live prepared dependencies do not match their reviewed manifest identity.');
    }
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'controller live configuration');
    agentEvaluationRequireExactKeys(
        $approval,
        ['reference', 'model', 'runs', 'spending_ceiling_usd', ...($explanation || $comparisonTask === null ? ['run_id'] : [])],
        'controller smoke approval record',
    );
    $approvalReference = agentEvaluationControllerBoundedLabel(
        agentEvaluationRequireNonEmptyString($approval, 'reference', 'controller smoke approval record'),
        'Controller approval reference',
    );
    $model = agentEvaluationRequireObject($profile, 'model', 'controller live profile');
    $ceiling = agentEvaluationRequireString($approval, 'spending_ceiling_usd', 'controller smoke approval record');
    if ($approval['model'] !== $model['id'] || $approval['runs'] !== $approvedRuns || preg_match('/\A(?:0|[1-9][0-9]{0,3})\.[0-9]{2}\z/D', $ceiling) !== 1) {
        throw new RuntimeException($approvedRuns === 1
            ? 'The smoke approval must name the exact model, one run, and a bounded decimal spending ceiling.'
            : 'The approval must name the exact model, approved run count, and bounded decimal spending ceiling.');
    }
    if ($explanation || $comparisonTask === null) {
        $approvedRunId = agentEvaluationRequireString($approval, 'run_id', 'controller explanation approval');
        if (preg_match('/\A[a-f0-9]{32}\z/D', $approvedRunId) !== 1) {
            throw new RuntimeException(($explanation ? 'Explanation' : 'Smoke')
                . ' approval must bind one exact 32-character lowercase hexadecimal run ID.');
        }
        $pending = $approvalReference === 'pending' && $ceiling === '0.00';
        $approved = $ceiling === '0.60'
            && preg_match('/(?:pending|placeholder)/i', $approvalReference) !== 1;
        if (!$pending && !$approved) {
            throw new RuntimeException(
                ($explanation ? 'Explanation' : 'Smoke')
                . ' configuration requires exact pending/0.00 preflight or accountable approved/0.60 state.',
            );
        }
        $settings = agentEvaluationValueObject($model['settings'] ?? null, 'controller single-run model settings');
        if (!$explanation && ($model['id'] !== 'gpt-5.4-2026-03-05'
            || ($settings['reasoning_effort'] ?? null) !== 'high')) {
            throw new RuntimeException('Smoke spending policy requires the exact priced model and high reasoning effort.');
        }
    }

    return [
        ...$configuration,
        'profile' => $profile,
        'engine' => $engine,
        'approval' => $approval,
        'prepared_dependencies' => $dependencies,
    ];
}

/** @param array<string, mixed> $configuration */
function agentEvaluationControllerRequireSmokeApprovalRunId(array $configuration, string $runId): void
{
    if (preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1) {
        throw new RuntimeException('Smoke execution requires one exact bounded run ID.');
    }
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'controller smoke configuration');
    $approvedRunId = agentEvaluationRequireString($approval, 'run_id', 'controller smoke approval');
    if (!hash_equals($approvedRunId, $runId)) {
        throw new RuntimeException('Smoke approval is bound to a different run ID.');
    }
    $reference = agentEvaluationRequireNonEmptyString($approval, 'reference', 'controller smoke approval');
    $profile = agentEvaluationRequireObject($configuration, 'profile', 'controller smoke configuration');
    $model = agentEvaluationRequireObject($profile, 'model', 'controller smoke profile');
    if (preg_match('/(?:pending|placeholder)/i', $reference) === 1
        || ($approval['runs'] ?? null) !== 1
        || ($approval['model'] ?? null) !== ($model['id'] ?? null)
        || ($approval['spending_ceiling_usd'] ?? null) !== '0.60'
    ) {
        throw new RuntimeException('Smoke execution requires its exact accountable USD 0.60 approval.');
    }
}

/** @param array<string, mixed> $configuration */
function agentEvaluationControllerRequireExplanationApprovalRunId(
    array $configuration,
    string $runId,
): void {
    if (preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1) {
        throw new RuntimeException('Explanation execution requires one exact bounded run ID.');
    }
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'controller explanation configuration');
    $approvedRunId = agentEvaluationRequireString($approval, 'run_id', 'controller explanation approval');
    $reference = agentEvaluationRequireNonEmptyString($approval, 'reference', 'controller explanation approval');
    $profile = agentEvaluationRequireObject($configuration, 'profile', 'controller explanation configuration');
    $model = agentEvaluationRequireObject($profile, 'model', 'controller explanation profile');
    if (!hash_equals($approvedRunId, $runId)) {
        throw new RuntimeException('Explanation approval is bound to a different run ID.');
    }
    if (preg_match('/(?:pending|placeholder)/i', $reference) === 1
        || ($approval['runs'] ?? null) !== 1
        || ($approval['model'] ?? null) !== ($model['id'] ?? null)
        || ($approval['spending_ceiling_usd'] ?? null) !== '0.60'
    ) {
        throw new RuntimeException('Explanation execution requires its exact accountable USD 0.60 approval.');
    }
}
