<?php

declare(strict_types=1);

const AGENT_EVALUATION_TASK_REVISIONS = [
    'change.simple-ping' => [
        'schema_version' => 1,
        'revision' => 27,
        'manifest_sha256' => '931f08f7e5a9307aadf6add85d8bedded3cc5e5df59bb740f64583000b5a6c07',
    ],
    'change.protected-endpoint' => [
        'schema_version' => 2,
        'revision' => 7,
        'manifest_sha256' => '0edb91edd860338959edad56d4725ca0b18057d4b6e1b373d9f1e4aab8624649',
    ],
    'repair.transaction-rollback' => [
        'schema_version' => 2,
        'revision' => 7,
        'manifest_sha256' => '70a615f2c43897fdca2e4934d7a29e132aa00a471a38d9df6d245ced52bdc524',
    ],
    'change.filtered-collection' => [
        'schema_version' => 2,
        'revision' => 7,
        'manifest_sha256' => '492beb6546c111cc57706766e3fd44a0da85ba9d5dbd7a298a6eb9df03eb8e32',
    ],
];

const AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256 = '4112bfec48681b01cf24edf30a4115da4538a1e662b19c485cfffde69f5ca6ab';
const AGENT_EVALUATION_COMPARISON_TASK_SCHEMA_SHA256 = '7ad4659623022d9884bf5f7a15fd35d6dc3b7cc676b3cc76cab6e3f7ff114ea8';
const AGENT_EVALUATION_COMPARISON_PROTOCOL_SCHEMA_SHA256 = '69e834fb1ce2869586a891831b5f0543415611940430650ecc1438678c2cd874';

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
        'task-v2.schema.json' => AGENT_EVALUATION_COMPARISON_TASK_SCHEMA_SHA256,
        'comparison-protocol-v1.schema.json' => AGENT_EVALUATION_COMPARISON_PROTOCOL_SCHEMA_SHA256,
        'run-v2.schema.json' => '626bf4903096e08e517685214bee11a204640c15e63307d65aecc14fb48db457',
        'score-v2.schema.json' => '7f5d68e3b4056961e1dbcfe85159a0378b67f11cd9c8d597f71bd148b4c79364',
    ];

    foreach ($schemaHashes as $schema => $hash) {
        agentEvaluationRequireFileHash($kit . '/schema/' . $schema, $hash, "schema/{$schema}");
        $document = agentEvaluationJsonFile($kit . '/schema/' . $schema);
        agentEvaluationRequireString($document, '$schema', "schema/{$schema}");
        agentEvaluationRequireString($document, 'title', "schema/{$schema}");
    }

    agentEvaluationComparisonProtocol($kit);
    $tasks = [];

    foreach (AGENT_EVALUATION_TASK_REVISIONS as $taskId => $pinnedRevision) {
        $version = $pinnedRevision['schema_version'];
        $task = $version === 1
            ? agentEvaluationTaskDocument($kit, $taskId)
            : agentEvaluationComparisonTaskDocument($kit, $taskId);

        if ($task['revision'] !== $pinnedRevision['revision']) {
            throw new RuntimeException("Task {$taskId} revision does not match its pinned identity.");
        }

        if (!hash_equals($pinnedRevision['manifest_sha256'], $task['manifest_sha256'])) {
            throw new RuntimeException("Task {$taskId} manifest SHA-256 does not match its pinned revision.");
        }

        if ($version === 1) {
            $tasks[] = agentEvaluationTaskDocument($kit, $taskId);
        }
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

/**
 * @return array{schema_version: int, id: string, revision: int, task_schema_version: int,
 *   conditions: list<string>, trials_per_task_condition: int,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   schedule: array{kind: string}, attempt_policy: string, holdout_policy: string,
 *   reporting: array{primary_metric: string, denominator: string, group_by: list<string>, unknown_metrics: string, human_review: string},
 *   sha256: string}
 */
function agentEvaluationComparisonProtocol(string $kit): array
{
    agentEvaluationRequireFileHash($kit . '/schema/comparison-protocol-v1.schema.json', AGENT_EVALUATION_COMPARISON_PROTOCOL_SCHEMA_SHA256, 'comparison protocol schema');
    agentEvaluationRequireFileHash($kit . '/comparison-v1.json', AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256, 'comparison protocol');
    return [...agentEvaluationValidateComparisonProtocolDocument(agentEvaluationJsonFile($kit . '/comparison-v1.json')),
        'sha256' => AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256];
}

/**
 * @param array<string, mixed> $document
 * @return array{schema_version: int, id: string, revision: int, task_schema_version: int,
 *   conditions: list<string>, trials_per_task_condition: int,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   schedule: array{kind: string}, attempt_policy: string, holdout_policy: string,
 *   reporting: array{primary_metric: string, denominator: string, group_by: list<string>, unknown_metrics: string, human_review: string}}
 */
function agentEvaluationValidateComparisonProtocolDocument(array $document): array
{
    $expected = [
        'schema_version' => 1, 'id' => 'bounded-comparison-v1', 'revision' => 1, 'task_schema_version' => 2,
        'conditions' => ['phpthis', 'plain-php'], 'trials_per_task_condition' => 10,
        'budgets' => ['model_tokens' => 40_000, 'wall_seconds' => 1_200, 'repair_turns' => 0, 'command_output_bytes' => 4_194_304],
        'schedule' => ['kind' => 'balanced-rotating-v1'],
        'attempt_policy' => 'retain-every-start-no-replacement-v1', 'holdout_policy' => 'external-post-freeze-v1',
        'reporting' => ['primary_metric' => 'correct_completion_rate', 'denominator' => 'planned-trials',
            'group_by' => ['task_id', 'condition'], 'unknown_metrics' => 'null-with-reason', 'human_review' => 'required-for-claims'],
    ];
    agentEvaluationRequireExactKeys($document, array_keys($expected), 'comparison protocol');
    foreach (['schema_version', 'id', 'revision', 'task_schema_version', 'conditions', 'trials_per_task_condition', 'attempt_policy', 'holdout_policy'] as $field) {
        if ($document[$field] !== $expected[$field]) {
            throw new RuntimeException('Comparison protocol fields do not match the fixed reviewed version.');
        }
    }
    foreach (['budgets', 'schedule', 'reporting'] as $field) {
        $members = agentEvaluationRequireObject($document, $field, 'comparison protocol');
        agentEvaluationRequireExactKeys($members, array_keys($expected[$field]), 'comparison protocol ' . $field);
        foreach ($expected[$field] as $key => $value) {
            if ($members[$key] !== $value) {
                throw new RuntimeException('Comparison protocol fields do not match the fixed reviewed version.');
            }
        }
    }
    return $expected;
}

/**
 * Describe exact candidate paths and modes; an explicitly supplied shared bundle mounts at docs/phpstan.
 * @return array{manifest: string, sha256: string, bytes: int,
 *   files: array<string, array{source_path: string, sha256: string, mode: string, bytes: int}>}
 */
function agentEvaluationComparisonFixture(string $directory, ?string $sharedReferenceDirectory = null): array
{
    $files = [];
    $identities = [];
    $directories = [];
    $bytes = 0;
    $entries = 0;
    $sources = ['' => $directory];
    if ($sharedReferenceDirectory !== null) {
        $sources['docs/phpstan'] = $sharedReferenceDirectory;
    }
    foreach ($sources as $prefix => $sourceDirectory) {
        $root = realpath($sourceDirectory);
        if (!is_string($root) || $root !== $sourceDirectory || !is_dir($root) || is_link($root)) {
            throw new RuntimeException('Comparison fixture must be one canonical real directory.');
        }
        if ($prefix !== '') {
            foreach ([...array_keys($identities), ...array_keys($directories)] as $identity) {
                if (agentEvaluationPathIsWithin($identity, $prefix)) {
                    throw new RuntimeException('Comparison local fixture must not occupy the shared reference destination.');
                }
            }
            foreach (['docs', 'docs/phpstan'] as $parent) {
                if (isset($directories[$parent]) && $directories[$parent] !== $parent) {
                    throw new RuntimeException('Comparison fixture directory identities collide.');
                }
                if (!isset($directories[$parent])) {
                    $directories[$parent] = $parent;
                    $entries++;
                }
            }
        }
        if ($entries > 8_192) {
            throw new RuntimeException('Comparison fixture contains an unsupported entry or exceeds its entry bound.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            $entries++;
            if (!$entry instanceof SplFileInfo || $entry->isLink() || $entries > 8_192) {
                throw new RuntimeException('Comparison fixture contains an unsupported entry or exceeds its entry bound.');
            }
            $source = $entry->getPathname();
            $sourceRelative = substr($source, strlen($root) + 1);
            if (agentEvaluationPathIsWithin($sourceRelative, '.git') || agentEvaluationPathIsWithin($sourceRelative, 'vendor')) {
                throw new RuntimeException('Comparison fixture path exceeds its bound or overlaps an isolated input.');
            }
            $relative = $prefix === '' ? $sourceRelative : $prefix . '/' . $sourceRelative;
            agentEvaluationRequireRelativePath($relative, 'comparison source fixture path');
            if (strlen($relative) > 263 || preg_match('/[\x00-\x20\x7F]/', $relative) === 1
                || agentEvaluationPathIsWithin($relative, '.git') || agentEvaluationPathIsWithin($relative, 'vendor')) {
                throw new RuntimeException('Comparison fixture path exceeds its bound or overlaps an isolated input.');
            }
            if ($entry->isDir()) {
                $identity = strtolower($relative);
                if (isset($directories[$identity]) && $directories[$identity] !== $relative) {
                    throw new RuntimeException('Comparison fixture directory identities collide.');
                }
                $directories[$identity] = $relative;
                continue;
            }
            if (!$entry->isFile()) {
                throw new RuntimeException('Comparison fixture contains a forbidden special file.');
            }
            $metadata = lstat($source);
            if (!is_array($metadata) || $metadata['nlink'] !== 1 || !in_array($metadata['mode'] & 07777, [0644, 0755], true)) {
                throw new RuntimeException('Comparison fixture file must have one regular reviewed identity and mode.');
            }
            if (str_ends_with($relative, '.fixture')) {
                $relative = substr($relative, 0, -8);
            }
            agentEvaluationRequireRelativePath($relative, 'comparison materialized fixture path');
            if (strlen($relative) > 255 || agentEvaluationPathIsWithin($relative, '.git') || agentEvaluationPathIsWithin($relative, 'vendor')) {
                throw new RuntimeException('Comparison fixture path exceeds its bound or overlaps an isolated input.');
            }
            $identity = strtolower($relative);
            if (isset($identities[$identity]) || count($files) >= 4_096) {
                throw new RuntimeException('Comparison fixture materialization collides or exceeds its file bound.');
            }
            agentEvaluationRequireBoundedFile($source, AGENT_EVALUATION_MAX_JSON_BYTES, 'comparison fixture file');
            $size = filesize($source);
            if (!is_int($size)) {
                throw new RuntimeException('Unable to size one comparison fixture file.');
            }
            $bytes += $size;
            if ($bytes > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
                throw new RuntimeException('Comparison fixture exceeds its aggregate byte bound.');
            }
            $identities[$identity] = true;
            $files[$relative] = ['source_path' => $source, 'sha256' => agentEvaluationFileHash($source, 'comparison fixture file'),
                'mode' => ($metadata['mode'] & 0111) !== 0 ? '100755' : '100644', 'bytes' => $size];
        }
    }

    if ($files === []) {
        throw new RuntimeException('Comparison fixture must contain regular files.');
    }
    ksort($files, SORT_STRING);
    $lines = [];
    foreach ($files as $relative => $file) {
        if (isset($directories[strtolower($relative)])) {
            throw new RuntimeException('Comparison fixture materialization has a file-directory collision.');
        }
        $parent = dirname($relative);
        while ($parent !== '.') {
            if (isset($identities[strtolower($parent)])) {
                throw new RuntimeException('Comparison fixture materialization has a file-directory collision.');
            }
            $parent = dirname($parent);
        }
        $lines[] = $file['mode'] . ' ' . $file['sha256'] . ' ' . $relative;
    }
    sort($lines, SORT_STRING);
    $manifest = implode("\n", $lines) . "\n";
    return ['manifest' => $manifest, 'sha256' => hash('sha256', $manifest), 'bytes' => $bytes, 'files' => $files];
}

/**
 * @param array<string, mixed> $descriptor
 * @return array{path: string, sha256: string}
 */
function agentEvaluationComparisonArtifact(array $descriptor, string $directory, string $expectedPath): array
{
    agentEvaluationRequireExactKeys($descriptor, ['path', 'sha256'], 'comparison artifact');
    $path = agentEvaluationRequireString($descriptor, 'path', 'comparison artifact');
    if ($path !== $expectedPath) {
        throw new RuntimeException('Comparison artifact path must match its fixed task location.');
    }
    $hash = agentEvaluationRequireHash(agentEvaluationRequireString($descriptor, 'sha256', 'comparison artifact'), 'comparison artifact');
    $source = agentEvaluationContainedArtifactPath($directory, $path, 'comparison artifact');
    agentEvaluationRequireBoundedFile($source, AGENT_EVALUATION_MAX_JSON_BYTES, 'comparison artifact');
    $size = filesize($source);
    if (!is_int($size) || $size < 1) {
        throw new RuntimeException('Comparison artifacts must contain bounded nonempty bytes.');
    }
    agentEvaluationRequireFileHash($source, $hash, 'comparison artifact');
    return ['path' => $path, 'sha256' => $hash];
}

/**
 * @param array<string, mixed> $policy
 * @param array<string, array{source_path: string, sha256: string, mode: string, bytes: int}> $files
 * @return array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}
 */
function agentEvaluationComparisonWorkspacePolicy(array $policy, array $files): array
{
    agentEvaluationRequireExactKeys($policy, ['allowed_existing_paths', 'allowed_new_paths', 'protected_paths', 'max_changed_files', 'max_added_lines', 'max_deleted_lines'], 'comparison workspace policy');
    $lists = [];
    foreach (['allowed_existing_paths', 'allowed_new_paths', 'protected_paths'] as $key) {
        $values = agentEvaluationRequireStringList($policy, $key, 'comparison workspace policy');
        if (count($values) > 128 || count(array_unique($values, SORT_STRING)) !== count($values) || ($key === 'protected_paths' && $values === [])) {
            throw new RuntimeException('Comparison workspace paths must be unique and bounded.');
        }
        foreach ($values as $path) {
            agentEvaluationRequireRelativePath($path, 'comparison workspace path');
            if (strlen($path) > 255 || preg_match('/[\x00-\x20\x7F]/', $path) === 1
                || ($key === 'allowed_existing_paths' && !isset($files[$path])) || ($key === 'allowed_new_paths' && isset($files[$path]))) {
                throw new RuntimeException('Comparison workspace policy must match its materialized fixture.');
            }
        }
        $lists[$key] = $values;
    }
    if ($lists['allowed_existing_paths'] === [] && $lists['allowed_new_paths'] === []) {
        throw new RuntimeException('Comparison workspace policy must permit a bounded change.');
    }
    foreach ($lists['allowed_existing_paths'] as $existing) {
        foreach ($lists['allowed_new_paths'] as $new) {
            if (agentEvaluationPathIsWithin($existing, $new) || agentEvaluationPathIsWithin($new, $existing)) {
                throw new RuntimeException('Comparison permitted paths must not overlap.');
            }
        }
    }
    foreach (array_merge($lists['allowed_existing_paths'], $lists['allowed_new_paths']) as $allowed) {
        foreach ($lists['protected_paths'] as $protected) {
            if (agentEvaluationPathIsWithin($allowed, $protected) || agentEvaluationPathIsWithin($protected, $allowed)) {
                throw new RuntimeException('Comparison permitted paths must not overlap protected paths.');
            }
        }
    }
    foreach (['.git', 'composer.json', 'composer.lock', 'vendor', 'evaluation/observe.php'] as $required) {
        $covered = false;
        foreach ($lists['protected_paths'] as $protected) {
            $covered = $covered || agentEvaluationPathIsWithin($required, $protected);
        }
        if (!$covered) {
            throw new RuntimeException('Comparison policy must protect the fixed gate, dependencies, and observation driver.');
        }
    }
    $changed = agentEvaluationRequirePositiveInteger($policy, 'max_changed_files', 'comparison workspace policy');
    $added = agentEvaluationRequireNonNegativeInteger($policy, 'max_added_lines', 'comparison workspace policy');
    $deleted = agentEvaluationRequireNonNegativeInteger($policy, 'max_deleted_lines', 'comparison workspace policy');
    if ($changed > 128 || $added > 4_096 || $deleted > 4_096) {
        throw new RuntimeException('Comparison workspace change limits exceed their fixed maxima.');
    }
    return ['allowed_existing_paths' => $lists['allowed_existing_paths'], 'allowed_new_paths' => $lists['allowed_new_paths'],
        'protected_paths' => $lists['protected_paths'], 'max_changed_files' => $changed, 'max_added_lines' => $added, 'max_deleted_lines' => $deleted];
}

/**
 * @param array<string, array{source_path: string, sha256: string, mode: string, bytes: int}> $files
 * @return array{scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int}
 */
function agentEvaluationComparisonSourceContext(string $path, array $files): array
{
    $document = agentEvaluationJsonFile($path);
    agentEvaluationRequireExactKeys($document, ['schema_version', 'files'], 'comparison source context');
    $entries = agentEvaluationRequireList($document, 'files', 'comparison source context');
    if (($document['schema_version'] ?? null) !== 1 || $entries === [] || count($entries) > 4_096) {
        throw new RuntimeException('Comparison source context must use the fixed bounded manifest version.');
    }
    $normalized = [];
    $seen = [];
    $bytes = 0;
    $words = 0;
    foreach ($entries as $entry) {
        $descriptor = agentEvaluationValueObject($entry, 'comparison context file');
        agentEvaluationRequireExactKeys($descriptor, ['path', 'sha256'], 'comparison context file');
        $relative = agentEvaluationRequireRelativePath(agentEvaluationRequireString($descriptor, 'path', 'comparison context file'), 'comparison context file');
        $hash = agentEvaluationRequireHash(agentEvaluationRequireString($descriptor, 'sha256', 'comparison context file'), 'comparison context file');
        if (!isset($files[$relative]) || isset($seen[$relative]) || $files[$relative]['sha256'] !== $hash) {
            throw new RuntimeException('Comparison source context must name unique exact materialized fixture files.');
        }
        $source = file_get_contents($files[$relative]['source_path']);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read one comparison source context file.');
        }
        $bytes += strlen($source);
        $trimmed = trim($source, " \t\r\n\f\v");
        $tokens = $trimmed === '' ? [] : preg_split('/[ \t\r\n\f\v]+/', $trimmed);
        if ($tokens === false) {
            throw new RuntimeException('Unable to measure comparison source context.');
        }
        $words += count($tokens);
        $seen[$relative] = true;
        $normalized[] = ['path' => $relative, 'sha256' => $hash];
    }
    $paths = array_keys($seen);
    $ordered = $paths;
    sort($ordered, SORT_STRING);
    if ($paths !== $ordered) {
        throw new RuntimeException('Comparison source context files must be byte-sorted.');
    }
    return ['scope' => 'source-only', 'files' => $normalized, 'bytes' => $bytes, 'words' => $words];
}

/**
 * @return list<array{schema_version: int, id: string, revision: int, kind: string,
 *   protocol: array{id: string, revision: int, sha256: string},
 *   prompt: array{path: string, sha256: string}, rubric: array{path: string, sha256: string},
 *   conditions: list<array{id: string, base: array{path: string, fixture_sha256: string, directory: string, reference_directory: string},
 *     context_manifest: array{path: string, sha256: string, scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int},
 *     workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}}>,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   checks: array{application_check: string, holdout: array{id: string, revision: int, sha256: string}},
 *   manifest_sha256: string, directory: string}>
 */
function agentEvaluationComparisonTasks(string $kit): array
{
    agentEvaluationValidateKit($kit);
    $tasks = [];
    foreach (AGENT_EVALUATION_TASK_REVISIONS as $id => $pin) {
        if ($pin['schema_version'] === 2) {
            $tasks[] = agentEvaluationComparisonTaskDocument($kit, $id);
        }
    }
    return $tasks;
}

/**
 * @return array{schema_version: int, id: string, revision: int, kind: string,
 *   protocol: array{id: string, revision: int, sha256: string},
 *   prompt: array{path: string, sha256: string}, rubric: array{path: string, sha256: string},
 *   conditions: list<array{id: string, base: array{path: string, fixture_sha256: string, directory: string, reference_directory: string},
 *     context_manifest: array{path: string, sha256: string, scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int},
 *     workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}}>,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   checks: array{application_check: string, holdout: array{id: string, revision: int, sha256: string}},
 *   manifest_sha256: string, directory: string}
 */
function agentEvaluationComparisonTask(string $kit, string $taskId): array
{
    foreach (agentEvaluationComparisonTasks($kit) as $task) {
        if ($task['id'] === $taskId) {
            return $task;
        }
    }
    throw new RuntimeException('Unknown bounded comparison task.');
}

/**
 * @return array{schema_version: int, id: string, revision: int, kind: string,
 *   protocol: array{id: string, revision: int, sha256: string},
 *   prompt: array{path: string, sha256: string}, rubric: array{path: string, sha256: string},
 *   conditions: list<array{id: string, base: array{path: string, fixture_sha256: string, directory: string, reference_directory: string},
 *     context_manifest: array{path: string, sha256: string, scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int},
 *     workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}}>,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   checks: array{application_check: string, holdout: array{id: string, revision: int, sha256: string}},
 *   manifest_sha256: string, directory: string}
 */
function agentEvaluationComparisonTaskDocument(string $kit, string $taskId): array
{
    $pin = AGENT_EVALUATION_TASK_REVISIONS[$taskId] ?? null;
    $kitRoot = realpath($kit);
    if (!is_array($pin) || $pin['schema_version'] !== 2 || !is_string($kitRoot)) {
        throw new RuntimeException('Unknown bounded comparison task.');
    }
    $candidate = $kitRoot . '/tasks/' . $taskId;
    $directory = realpath($candidate);
    if (!is_string($directory) || $directory !== $candidate || !is_dir($directory) || is_link($directory)) {
        throw new RuntimeException('Comparison task must remain in its canonical inventory directory.');
    }
    $document = agentEvaluationJsonFile($directory . '/task.json');
    agentEvaluationRequireExactKeys($document, ['schema_version', 'id', 'revision', 'kind', 'protocol', 'prompt', 'rubric', 'conditions', 'budgets', 'checks'], 'comparison task');
    $revision = agentEvaluationRequirePositiveInteger($document, 'revision', 'comparison task');
    $kind = agentEvaluationRequireString($document, 'kind', 'comparison task');
    if (($document['schema_version'] ?? null) !== 2 || ($document['id'] ?? null) !== $taskId
        || $revision !== $pin['revision'] || $kind !== (str_starts_with($taskId, 'repair.') ? 'repair' : 'implementation')) {
        throw new RuntimeException('Comparison task identity must match its explicit pinned version and kind.');
    }
    $protocol = agentEvaluationComparisonProtocol($kitRoot);
    $binding = agentEvaluationRequireObject($document, 'protocol', 'comparison task');
    agentEvaluationRequireExactKeys($binding, ['id', 'revision', 'sha256'], 'comparison task protocol');
    foreach (['id', 'revision', 'sha256'] as $field) {
        if ($binding[$field] !== $protocol[$field]) {
            throw new RuntimeException('Comparison task must bind the exact reviewed protocol.');
        }
    }
    $budgets = agentEvaluationValidateBudgets(agentEvaluationRequireObject($document, 'budgets', 'comparison task'), $taskId);
    if ($budgets !== $protocol['budgets']) {
        throw new RuntimeException('Comparison task budgets must equal the fixed matched protocol.');
    }
    $prompt = agentEvaluationComparisonArtifact(agentEvaluationRequireObject($document, 'prompt', 'comparison task'), $directory, 'prompt.md');
    $rubric = agentEvaluationComparisonArtifact(agentEvaluationRequireObject($document, 'rubric', 'comparison task'), $directory, 'rubric.md');
    $conditionValues = agentEvaluationRequireList($document, 'conditions', 'comparison task');
    if (count($conditionValues) !== 2) {
        throw new RuntimeException('Comparison task must define exactly the two matched conditions.');
    }
    $conditions = [];
    foreach ($conditionValues as $index => $value) {
        $condition = agentEvaluationValueObject($value, 'comparison condition');
        agentEvaluationRequireExactKeys($condition, ['id', 'base', 'context_manifest', 'workspace_policy'], 'comparison condition');
        $id = agentEvaluationRequireString($condition, 'id', 'comparison condition');
        if ($id !== $protocol['conditions'][$index]) {
            throw new RuntimeException('Comparison conditions must retain the fixed protocol order.');
        }
        $base = agentEvaluationRequireObject($condition, 'base', 'comparison condition');
        agentEvaluationRequireExactKeys($base, ['path', 'fixture_sha256'], 'comparison base');
        $path = agentEvaluationRequireString($base, 'path', 'comparison base');
        $hash = agentEvaluationRequireHash(agentEvaluationRequireString($base, 'fixture_sha256', 'comparison base'), 'comparison base');
        if ($path !== 'fixtures/' . $id) {
            throw new RuntimeException('Comparison base must use its fixed condition fixture directory.');
        }
        $referenceDirectory = $kitRoot . '/references/phpstan';
        $fixture = agentEvaluationComparisonFixture($directory . '/' . $path, $referenceDirectory);
        if ($fixture['sha256'] !== $hash) {
            throw new RuntimeException('Comparison base hash must match the exact materialized fixture.');
        }
        foreach (['composer.json', 'evaluation/observe.php'] as $required) {
            if (!isset($fixture['files'][$required])) {
                throw new RuntimeException('Comparison fixture is missing its fixed gate or observation driver.');
            }
        }
        $context = agentEvaluationComparisonArtifact(agentEvaluationRequireObject($condition, 'context_manifest', 'comparison condition'), $directory, 'context/' . $id . '.json');
        $sourceContext = agentEvaluationComparisonSourceContext($directory . '/' . $context['path'], $fixture['files']);
        $conditions[] = ['id' => $id, 'base' => ['path' => $path, 'fixture_sha256' => $hash, 'directory' => $directory . '/' . $path, 'reference_directory' => $referenceDirectory],
            'context_manifest' => [...$context, ...$sourceContext],
            'workspace_policy' => agentEvaluationComparisonWorkspacePolicy(agentEvaluationRequireObject($condition, 'workspace_policy', 'comparison condition'), $fixture['files'])];
    }
    $checks = agentEvaluationRequireObject($document, 'checks', 'comparison task');
    agentEvaluationRequireExactKeys($checks, ['application_check', 'holdout'], 'comparison checks');
    $holdout = agentEvaluationRequireObject($checks, 'holdout', 'comparison checks');
    agentEvaluationRequireExactKeys($holdout, ['id', 'revision', 'sha256'], 'comparison holdout');
    $holdoutId = agentEvaluationRequireString($holdout, 'id', 'comparison holdout');
    $holdoutRevision = agentEvaluationRequirePositiveInteger($holdout, 'revision', 'comparison holdout');
    $holdoutHash = agentEvaluationRequireHash(agentEvaluationRequireString($holdout, 'sha256', 'comparison holdout'), 'comparison holdout');
    if (($checks['application_check'] ?? null) !== 'composer check' || $holdoutId !== $taskId . '.holdout' || $holdoutRevision !== 1) {
        throw new RuntimeException('Comparison checks must name the fixed gate and external holdout identity.');
    }
    return ['schema_version' => 2, 'id' => $taskId, 'revision' => $revision, 'kind' => $kind,
        'protocol' => ['id' => $protocol['id'], 'revision' => $protocol['revision'], 'sha256' => $protocol['sha256']],
        'prompt' => $prompt, 'rubric' => $rubric, 'conditions' => $conditions, 'budgets' => $budgets,
        'checks' => ['application_check' => 'composer check', 'holdout' => ['id' => $holdoutId, 'revision' => $holdoutRevision, 'sha256' => $holdoutHash]],
        'manifest_sha256' => agentEvaluationFileHash($directory . '/task.json', 'comparison task'), 'directory' => $directory];
}

/** @return list<array{slot: int, round: int, task_id: string, condition: string}> */
function agentEvaluationComparisonSchedule(string $kit): array
{
    $tasks = agentEvaluationComparisonTasks($kit);
    $schedule = [];
    for ($round = 0; $round < 10; $round++) {
        for ($position = 0; $position < 3; $position++) {
            $index = ($round + $position) % 3;
            $conditions = ($round + $index) % 2 === 0 ? ['phpthis', 'plain-php'] : ['plain-php', 'phpthis'];
            foreach ($conditions as $condition) {
                $schedule[] = ['slot' => count($schedule) + 1, 'round' => $round, 'task_id' => $tasks[$index]['id'], 'condition' => $condition];
            }
        }
    }
    return $schedule;
}
