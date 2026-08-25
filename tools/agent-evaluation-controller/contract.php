<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_VERSION = 2;
const AGENT_EVALUATION_CONTROLLER_TASK_ID = 'change.simple-ping';
const AGENT_EVALUATION_CONTROLLER_TASK_REVISION = 23;
const AGENT_EVALUATION_CONTROLLER_OCI_ONLY = true;
const AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY = true;
const AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK = true;
const AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER = 'codex-exec';
const AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER = 'fake-codex';
const AGENT_EVALUATION_CONTROLLER_FAKE_CONDITION = 'repository-only-controller-v0.2-fake';
const AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_VERSION = 'fixture-1';
const AGENT_EVALUATION_CONTROLLER_FAKE_MODEL = 'fake-codex-v1';
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
    agentEvaluationControllerRequireFixedTask($task);
    agentEvaluationRequireExactKeys($request, ['run_id', 'task_id'], 'controller request');
    $runId = agentEvaluationRequireString($request, 'run_id', 'controller request');

    if (preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1) {
        throw new RuntimeException('Controller request run ID must be exactly 32 lowercase hexadecimal characters.');
    }

    $taskId = agentEvaluationRequireString($request, 'task_id', 'controller request');

    if ($taskId !== AGENT_EVALUATION_CONTROLLER_TASK_ID || $taskId !== $task['id']) {
        throw new RuntimeException('Controller request must select change.simple-ping.');
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
 *   budgets: array<string, mixed>,
 *   isolation: array<string, mixed>
 * }
 */
function agentEvaluationControllerValidateProfile(array $profile, array $task, bool $synthetic): array
{
    agentEvaluationControllerRequireFixedTask($task);
    agentEvaluationRequireExactKeys(
        $profile,
        ['condition', 'runner', 'model', 'context', 'tools', 'budgets', 'isolation'],
        'controller execution profile',
    );

    $condition = agentEvaluationControllerBoundedLabel(
        agentEvaluationRequireNonEmptyString($profile, 'condition', 'controller execution profile'),
        'Controller execution-profile condition',
    );

    if ($synthetic && $condition !== AGENT_EVALUATION_CONTROLLER_FAKE_CONDITION) {
        throw new RuntimeException('Controller synthetic condition must equal its fixed fixture identity.');
    }
    $runner = agentEvaluationRequireObject($profile, 'runner', 'controller execution profile');
    agentEvaluationRequireExactKeys($runner, ['name', 'version'], 'controller runner profile');
    $runnerName = agentEvaluationRequireString($runner, 'name', 'controller runner profile');
    $expectedRunner = $synthetic
        ? AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER
        : AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER;

    if ($runnerName !== $expectedRunner) {
        throw new RuntimeException("Controller execution profile must use the fixed {$expectedRunner} runner.");
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
    agentEvaluationControllerValidateModelProfile($model, $synthetic);
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
    agentEvaluationControllerValidateToolsProfile($tools, $synthetic);
    $budgets = agentEvaluationRequireObject($profile, 'budgets', 'controller execution profile');
    /** @var array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $taskBudgets */
    $taskBudgets = $task['budgets'];
    agentEvaluationValidateRunBudgets($budgets, $taskBudgets);
    $isolation = agentEvaluationRequireObject($profile, 'isolation', 'controller execution profile');
    agentEvaluationControllerValidateIsolationProfile($isolation, $taskBudgets, $synthetic);

    return [
        'condition' => $condition,
        'runner' => ['name' => $runnerName, 'version' => $runnerVersion],
        'model' => $model,
        'context' => $context,
        'tools' => $tools,
        'budgets' => $budgets,
        'isolation' => $isolation,
    ];
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
            'Controller v0.2 supports only change.simple-ping revision 23 without comparative claims.',
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
    agentEvaluationControllerRequireFixedTask($task);
    $directory = $task['directory'] ?? null;

    if (!is_string($directory)) {
        throw new RuntimeException('Controller task directory is unavailable.');
    }

    $document = agentEvaluationJsonFile($directory . '/task.json');
    $policy = agentEvaluationRequireObject($document, 'workspace_policy', 'controller task document');
    agentEvaluationValidateWorkspacePolicy($policy, AGENT_EVALUATION_CONTROLLER_TASK_ID);

    return [
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
        'max_changed_files' => agentEvaluationRequirePositiveInteger(
            $policy,
            'max_changed_files',
            'controller workspace policy',
        ),
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
}

/** @param array<string, mixed> $model */
function agentEvaluationControllerValidateModelProfile(array $model, bool $synthetic): void
{
    $expectedProvider = $synthetic ? 'synthetic' : 'openai';

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

        if (
            $id !== AGENT_EVALUATION_CONTROLLER_FAKE_MODEL
            || $revision !== AGENT_EVALUATION_CONTROLLER_FAKE_MODEL_REVISION
        ) {
            throw new RuntimeException('Controller synthetic model identity must equal its fixed fixture identity.');
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
function agentEvaluationControllerValidateToolsProfile(array $tools, bool $synthetic): void
{
    if ($synthetic) {
        if ($tools !== []) {
            throw new RuntimeException('Controller synthetic runner must not expose agent tools.');
        }

        return;
    }

    $expected = [
        [
            'name' => 'shell',
            'version' => null,
            'permissions' => ['workspace-read', 'workspace-write', 'process-execute'],
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

    $expectedStrings = $synthetic
        ? [
            'launcher' => 'synthetic-test',
            'credential_broker' => 'none',
            'network' => 'none',
            'descendant_cleanup' => 'in-process-fixture',
        ]
        : [
            'launcher' => 'docker-oci',
            'credential_broker' => 'responses-api-run-proxy',
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
