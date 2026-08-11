<?php

declare(strict_types=1);

const AGENT_EVALUATION_TASK_REVISIONS = [
    'change.simple-ping' => [
        'revision' => 5,
        'manifest_sha256' => 'e0b668ec3bbaf1fbdc184483a9976213a4a02a22ccc87d06fbcb343ad43aa747',
    ],
];

/**
 * @return list<array{
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   directory: string
 * }>
 */
function agentEvaluationValidateKit(string $kit): array
{
    $inventory = agentEvaluationJsonValueFile($kit . '/tasks.json');

    if (
        !is_array($inventory)
        || !array_is_list($inventory)
        || $inventory !== array_keys(AGENT_EVALUATION_TASK_REVISIONS)
    ) {
        throw new RuntimeException('The agent-evaluation task inventory must equal the pinned non-empty revision order.');
    }

    $schemaHashes = [
        'task.schema.json' => '55b47a225496cabbaf928c21e5aa0934fe8eb1eeed7dde26b926bb2390cd1327',
        'run.schema.json' => 'f5e9c84592da0064c35cacd54dbdfbe9a05b71ff35483bea841f4fe998234942',
        'score.schema.json' => 'd32d675230d5cfb15c539d91cf6525ba7d993c543436bde4300b89342b51f233',
    ];

    foreach ($schemaHashes as $schema => $hash) {
        agentEvaluationRequireFileHash($kit . '/schema/' . $schema, $hash, "schema/{$schema}");
        $document = agentEvaluationJsonFile($kit . '/schema/' . $schema);
        agentEvaluationRequireString($document, '$schema', "schema/{$schema}");
        agentEvaluationRequireString($document, 'title', "schema/{$schema}");
    }

    $tasks = [];

    foreach (AGENT_EVALUATION_TASK_REVISIONS as $taskId => $pinnedRevision) {
        $task = agentEvaluationTaskDocument($kit, $taskId);

        if ($task['revision'] !== $pinnedRevision['revision']) {
            throw new RuntimeException("Task {$taskId} revision does not match its pinned identity.");
        }

        if (!hash_equals($pinnedRevision['manifest_sha256'], $task['manifest_sha256'])) {
            throw new RuntimeException("Task {$taskId} manifest SHA-256 does not match its pinned revision.");
        }

        $tasks[] = $task;
    }

    return $tasks;
}

/**
 * @return array{
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   directory: string
 * }
 */
function agentEvaluationTask(string $kit, string $taskId): array
{
    $tasks = agentEvaluationValidateKit($kit);

    foreach ($tasks as $task) {
        if ($task['id'] === $taskId) {
            return $task;
        }
    }

    throw new RuntimeException("Unknown agent-evaluation task: {$taskId}.");
}

/**
 * @return array{
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   directory: string
 * }
 */
function agentEvaluationTaskDocument(string $kit, string $taskId): array
{
    $kitRoot = realpath($kit);
    $directoryCandidate = $kit . '/tasks/' . $taskId;
    $directory = realpath($directoryCandidate);

    if (
        !is_string($kitRoot)
        || !is_string($directory)
        || !is_dir($directory)
        || is_link($directoryCandidate)
        || !str_starts_with($directory, $kitRoot . DIRECTORY_SEPARATOR)
    ) {
        throw new RuntimeException("Task {$taskId} directory must remain inside the evaluation kit.");
    }

    $document = agentEvaluationJsonFile($directory . '/task.json');
    agentEvaluationRequireExactKeys(
        $document,
        [
            'schema_version',
            'id',
            'revision',
            'kind',
            'prompt',
            'rubric',
            'base',
            'workspace_policy',
            'budgets',
            'checks',
            'comparative_claims',
        ],
        "task {$taskId}",
    );

    if (agentEvaluationRequireInteger($document, 'schema_version', "task {$taskId}") !== 1) {
        throw new RuntimeException("Task {$taskId} must use schema version 1.");
    }

    $id = agentEvaluationRequireString($document, 'id', "task {$taskId}");

    if ($id !== $taskId) {
        throw new RuntimeException("Task inventory ID {$taskId} does not match task document ID {$id}.");
    }

    $revision = agentEvaluationRequirePositiveInteger($document, 'revision', "task {$taskId}");
    $kind = agentEvaluationRequireString($document, 'kind', "task {$taskId}");

    if ($kind !== 'implementation') {
        throw new RuntimeException("Task {$taskId} has an unsupported kind: {$kind}.");
    }

    $prompt = agentEvaluationRequireObject($document, 'prompt', "task {$taskId}");
    agentEvaluationRequireExactKeys($prompt, ['path', 'sha256'], "task {$taskId} prompt");
    $promptPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($prompt, 'path', "task {$taskId} prompt"),
        "task {$taskId} prompt",
    );
    $promptHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($prompt, 'sha256', "task {$taskId} prompt"),
        "task {$taskId} prompt",
    );
    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $promptPath, "task {$taskId} prompt"),
        $promptHash,
        "task {$taskId} prompt",
    );

    $rubric = agentEvaluationRequireObject($document, 'rubric', "task {$taskId}");
    agentEvaluationRequireExactKeys($rubric, ['path', 'sha256'], "task {$taskId} rubric");
    $rubricPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($rubric, 'path', "task {$taskId} rubric"),
        "task {$taskId} rubric",
    );
    $rubricHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($rubric, 'sha256', "task {$taskId} rubric"),
        "task {$taskId} rubric",
    );
    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $rubricPath, "task {$taskId} rubric"),
        $rubricHash,
        "task {$taskId} rubric",
    );

    $base = agentEvaluationRequireObject($document, 'base', "task {$taskId}");
    agentEvaluationRequireExactKeys(
        $base,
        ['fixture', 'tree', 'fixture_sha256'],
        "task {$taskId} base",
    );

    if (agentEvaluationRequireString($base, 'fixture', "task {$taskId} base") !== 'source-skeleton') {
        throw new RuntimeException("Task {$taskId} must use the explicit source-skeleton fixture.");
    }

    $baseTree = agentEvaluationRequireString($base, 'tree', "task {$taskId} base");

    if (preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/D', $baseTree) !== 1) {
        throw new RuntimeException("Task {$taskId} base tree must be one lowercase Git object ID.");
    }

    $baseFixtureHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($base, 'fixture_sha256', "task {$taskId} base"),
        "task {$taskId} base fixture",
    );

    agentEvaluationValidateWorkspacePolicy(
        agentEvaluationRequireObject($document, 'workspace_policy', "task {$taskId}"),
        $taskId,
    );
    $budgets = agentEvaluationValidateBudgets(
        agentEvaluationRequireObject($document, 'budgets', "task {$taskId}"),
        $taskId,
    );

    $checks = agentEvaluationRequireObject($document, 'checks', "task {$taskId}");
    agentEvaluationRequireExactKeys($checks, ['application_check', 'public_scorer'], "task {$taskId} checks");

    if (agentEvaluationRequireString($checks, 'application_check', "task {$taskId} checks") !== 'composer check') {
        throw new RuntimeException("Task {$taskId} must retain the complete application check.");
    }

    $scorer = agentEvaluationRequireObject($checks, 'public_scorer', "task {$taskId} checks");
    agentEvaluationRequireExactKeys($scorer, ['path', 'sha256'], "task {$taskId} public scorer");
    $scorerPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($scorer, 'path', "task {$taskId} public scorer"),
        "task {$taskId} public scorer",
    );
    $scorerHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($scorer, 'sha256', "task {$taskId} public scorer"),
        "task {$taskId} public scorer",
    );
    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $scorerPath, "task {$taskId} public scorer"),
        $scorerHash,
        "task {$taskId} public scorer",
    );

    $comparativeClaims = agentEvaluationRequireBoolean($document, 'comparative_claims', "task {$taskId}");

    if ($comparativeClaims) {
        throw new RuntimeException("Public smoke task {$taskId} cannot authorize comparative claims.");
    }

    return [
        'id' => $id,
        'revision' => $revision,
        'kind' => $kind,
        'comparative_claims' => $comparativeClaims,
        'prompt' => ['path' => $promptPath, 'sha256' => $promptHash],
        'rubric' => ['path' => $rubricPath, 'sha256' => $rubricHash],
        'public_scorer' => ['path' => $scorerPath, 'sha256' => $scorerHash],
        'manifest_sha256' => agentEvaluationFileHash($directory . '/task.json', "task {$taskId} manifest"),
        'base' => ['tree' => $baseTree, 'fixture_sha256' => $baseFixtureHash],
        'budgets' => $budgets,
        'directory' => $directory,
    ];
}

/** @param array<string, mixed> $policy */
function agentEvaluationValidateWorkspacePolicy(array $policy, string $taskId): void
{
    agentEvaluationRequireExactKeys(
        $policy,
        [
            'allowed_existing_paths',
            'allowed_new_paths',
            'protected_paths',
            'max_changed_files',
            'max_added_lines',
            'max_deleted_lines',
        ],
        "task {$taskId} workspace policy",
    );
    agentEvaluationRequirePathList($policy, 'allowed_existing_paths', "task {$taskId} workspace policy");
    agentEvaluationRequirePathList($policy, 'allowed_new_paths', "task {$taskId} workspace policy");
    agentEvaluationRequirePathList($policy, 'protected_paths', "task {$taskId} workspace policy");
    $allowedExistingPaths = agentEvaluationRequireStringList(
        $policy,
        'allowed_existing_paths',
        "task {$taskId} workspace policy",
    );
    $protectedPaths = agentEvaluationRequireStringList(
        $policy,
        'protected_paths',
        "task {$taskId} workspace policy",
    );
    $allowedNewPaths = agentEvaluationRequireStringList(
        $policy,
        'allowed_new_paths',
        "task {$taskId} workspace policy",
    );

    foreach ($allowedExistingPaths as $existingPath) {
        foreach ($allowedNewPaths as $newPath) {
            if (
                agentEvaluationPathIsWithin($existingPath, $newPath)
                || agentEvaluationPathIsWithin($newPath, $existingPath)
            ) {
                throw new RuntimeException("Task {$taskId} cannot overlap existing and new permitted paths.");
            }
        }
    }

    foreach (array_merge($allowedExistingPaths, $allowedNewPaths) as $allowedPath) {
        foreach ($protectedPaths as $protectedPath) {
            if (
                agentEvaluationPathIsWithin($allowedPath, $protectedPath)
                || agentEvaluationPathIsWithin($protectedPath, $allowedPath)
            ) {
                throw new RuntimeException("Task {$taskId} cannot overlap permitted and protected paths.");
            }
        }
    }

    agentEvaluationRequirePositiveInteger($policy, 'max_changed_files', "task {$taskId} workspace policy");
    agentEvaluationRequireNonNegativeInteger($policy, 'max_added_lines', "task {$taskId} workspace policy");
    agentEvaluationRequireNonNegativeInteger($policy, 'max_deleted_lines', "task {$taskId} workspace policy");
}

function agentEvaluationPathIsWithin(string $path, string $ancestor): bool
{
    return $path === $ancestor || str_starts_with($path, $ancestor . '/');
}

/**
 * @param array<string, mixed> $budgets
 * @return array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int}
 */
function agentEvaluationValidateBudgets(array $budgets, string $taskId): array
{
    agentEvaluationRequireExactKeys(
        $budgets,
        ['model_tokens', 'wall_seconds', 'repair_turns', 'command_output_bytes'],
        "task {$taskId} budgets",
    );
    return [
        'model_tokens' => agentEvaluationRequirePositiveInteger($budgets, 'model_tokens', "task {$taskId} budgets"),
        'wall_seconds' => agentEvaluationRequirePositiveInteger($budgets, 'wall_seconds', "task {$taskId} budgets"),
        'repair_turns' => agentEvaluationRequireNonNegativeInteger($budgets, 'repair_turns', "task {$taskId} budgets"),
        'command_output_bytes' => agentEvaluationRequirePositiveInteger(
            $budgets,
            'command_output_bytes',
            "task {$taskId} budgets",
        ),
    ];
}
