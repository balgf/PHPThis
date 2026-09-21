<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $record
 * @param array{
 *   id: string,
 *   revision: int,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int}
 * } $task
 */
function agentEvaluationValidateRunRecord(array $record, array $task): void
{
    agentEvaluationRequireExactKeys(
        $record,
        [
            'schema_version',
            'run_id',
            'task_id',
            'task_revision',
            'task_manifest_sha256',
            'rubric_sha256',
            'base_revision',
            'base_fixture_sha256',
            'prepared_dependencies_manifest_path',
            'prepared_dependencies_manifest_sha256',
            'condition',
            'model',
            'context',
            'tools',
            'budgets',
            'usage',
            'timing',
            'repair_turns',
            'termination_reason',
            'events_path',
            'events_sha256',
            'candidate_patch_path',
            'candidate_patch_sha256',
        ],
        'run record',
    );

    if (agentEvaluationRequireInteger($record, 'schema_version', 'run record') !== 1) {
        throw new RuntimeException('Run record must use schema version 1.');
    }

    agentEvaluationRequireNonEmptyString($record, 'run_id', 'run record');

    if (agentEvaluationRequireString($record, 'task_id', 'run record') !== $task['id']) {
        throw new RuntimeException('Run record task ID does not match the selected task.');
    }

    if (agentEvaluationRequireInteger($record, 'task_revision', 'run record') !== $task['revision']) {
        throw new RuntimeException('Run record task revision does not match the selected task.');
    }

    if (agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'task_manifest_sha256', 'run record'),
        'run record task manifest',
    ) !== $task['manifest_sha256']) {
        throw new RuntimeException('Run record task manifest hash does not match the selected task.');
    }

    if (agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'rubric_sha256', 'run record'),
        'run record rubric',
    ) !== $task['rubric']['sha256']) {
        throw new RuntimeException('Run record rubric hash does not match the selected task.');
    }

    $baseRevision = agentEvaluationRequireString($record, 'base_revision', 'run record');

    if (preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/D', $baseRevision) !== 1) {
        throw new RuntimeException('Run record base revision must be one lowercase 40- or 64-character Git object ID.');
    }

    if (agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'base_fixture_sha256', 'run record'),
        'run record base fixture',
    ) !== $task['base']['fixture_sha256']) {
        throw new RuntimeException('Run record base fixture hash does not match the selected task revision.');
    }

    agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'prepared_dependencies_manifest_path', 'run record'),
        'run record prepared-dependencies manifest path',
    );
    agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'prepared_dependencies_manifest_sha256', 'run record'),
        'run record prepared-dependencies manifest',
    );

    agentEvaluationRequireNonEmptyString($record, 'condition', 'run record');
    agentEvaluationValidateModel(agentEvaluationRequireObject($record, 'model', 'run record'));
    agentEvaluationValidateContext(agentEvaluationRequireObject($record, 'context', 'run record'));
    agentEvaluationValidateTools(agentEvaluationRequireList($record, 'tools', 'run record'));
    agentEvaluationValidateRunBudgets(
        agentEvaluationRequireObject($record, 'budgets', 'run record'),
        $task['budgets'],
    );
    agentEvaluationValidateUsage(
        agentEvaluationRequireObject($record, 'usage', 'run record'),
        $task['budgets']['model_tokens'],
    );
    agentEvaluationValidateTiming(
        agentEvaluationRequireObject($record, 'timing', 'run record'),
        $task['budgets']['wall_seconds'],
    );
    $repairTurns = agentEvaluationRequireNonNegativeInteger($record, 'repair_turns', 'run record');

    if ($repairTurns > $task['budgets']['repair_turns']) {
        throw new RuntimeException('Run record repair turns exceed the task budget.');
    }
    agentEvaluationRequireNonEmptyString($record, 'termination_reason', 'run record');
    agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'events_path', 'run record'),
        'run record events path',
    );
    agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'events_sha256', 'run record'),
        'run record events',
    );
    agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'candidate_patch_path', 'run record'),
        'run record candidate patch path',
    );
    agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'candidate_patch_sha256', 'run record'),
        'run record candidate patch',
    );
}

/** @param array<string, mixed> $model */
function agentEvaluationValidateModel(array $model): void
{
    agentEvaluationRequireExactKeys($model, ['provider', 'id', 'revision', 'settings'], 'run record model');
    agentEvaluationRequireNonEmptyString($model, 'provider', 'run record model');
    agentEvaluationRequireNonEmptyString($model, 'id', 'run record model');
    agentEvaluationRequireNullableString($model, 'revision', 'run record model');
    agentEvaluationRequireJsonObjectValue($model['settings'] ?? null, 'run record model field settings');
}

/** @param array<string, mixed> $context */
function agentEvaluationValidateContext(array $context): void
{
    agentEvaluationRequireExactKeys($context, ['bundle_id', 'bundle_sha256'], 'run record context');
    $bundleId = agentEvaluationRequireNullableString($context, 'bundle_id', 'run record context');
    $hash = $context['bundle_sha256'] ?? null;

    if (($bundleId === null) !== ($hash === null)) {
        throw new RuntimeException('Run record context bundle ID and hash must both be null or both be present.');
    }

    if ($hash !== null) {
        if (!is_string($hash)) {
            throw new RuntimeException('Run record context bundle hash must be a string or null.');
        }

        agentEvaluationRequireHash($hash, 'run record context bundle');
    }
}

/** @param list<mixed> $tools */
function agentEvaluationValidateTools(array $tools): void
{
    $seen = [];

    foreach ($tools as $index => $value) {
        $tool = agentEvaluationValueObject($value, "run record tool {$index}");
        agentEvaluationRequireExactKeys($tool, ['name', 'version', 'permissions'], "run record tool {$index}");
        $name = agentEvaluationRequireNonEmptyString($tool, 'name', "run record tool {$index}");
        $version = agentEvaluationRequireNullableString($tool, 'version', "run record tool {$index}");
        $identity = strlen($name) . ':' . $name . ':' . ($version === null ? 'null' : strlen($version) . ':' . $version);

        if (isset($seen[$identity])) {
            throw new RuntimeException('Run record tools must use unique name and version identities.');
        }

        $seen[$identity] = true;
        $permissions = agentEvaluationRequireStringList($tool, 'permissions', "run record tool {$index}");

        if (count(array_unique($permissions, SORT_STRING)) !== count($permissions)) {
            throw new RuntimeException("Run record tool {$index} permissions must be unique.");
        }
    }
}

/**
 * @param array<string, mixed> $budgets
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $taskBudgets
 */
function agentEvaluationValidateRunBudgets(array $budgets, array $taskBudgets): void
{
    agentEvaluationRequireExactKeys(
        $budgets,
        ['model_tokens', 'wall_seconds', 'repair_turns', 'command_output_bytes'],
        'run record budgets',
    );
    $actual = [
        'model_tokens' => agentEvaluationRequirePositiveInteger($budgets, 'model_tokens', 'run record budgets'),
        'wall_seconds' => agentEvaluationRequirePositiveInteger($budgets, 'wall_seconds', 'run record budgets'),
        'repair_turns' => agentEvaluationRequireNonNegativeInteger($budgets, 'repair_turns', 'run record budgets'),
        'command_output_bytes' => agentEvaluationRequirePositiveInteger(
            $budgets,
            'command_output_bytes',
            'run record budgets',
        ),
    ];

    if ($actual !== $taskBudgets) {
        throw new RuntimeException('Run record budgets do not match the selected task.');
    }
}

/** @param array<string, mixed> $usage */
function agentEvaluationValidateUsage(array $usage, int $modelTokenBudget): void
{
    agentEvaluationRequireExactKeys(
        $usage,
        ['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens'],
        'run record usage',
    );

    $values = [];

    foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens'] as $name) {
        $value = agentEvaluationRequireNullableNonNegativeInteger($usage, $name, 'run record usage');
        $values[$name] = $value;

        if ($value !== null && $value > $modelTokenBudget) {
            throw new RuntimeException("Run record usage field {$name} exceeds the task model-token budget.");
        }
    }

    if (
        is_int($values['input_tokens'])
        && is_int($values['output_tokens'])
        && ($values['input_tokens'] + $values['output_tokens']) > $modelTokenBudget
    ) {
        throw new RuntimeException('Run record reported input and output tokens exceed the total task model-token budget.');
    }
}

/** @param array<string, mixed> $timing */
function agentEvaluationValidateTiming(array $timing, int $wallSeconds): void
{
    agentEvaluationRequireExactKeys($timing, ['started_at', 'finished_at'], 'run record timing');
    $startedAt = agentEvaluationRequireNonEmptyString($timing, 'started_at', 'run record timing');
    $finishedAt = agentEvaluationRequireNonEmptyString($timing, 'finished_at', 'run record timing');

    $startedTimestamp = agentEvaluationTimestamp($startedAt, 'started_at');
    $finishedTimestamp = agentEvaluationTimestamp($finishedAt, 'finished_at');

    if ($finishedTimestamp < $startedTimestamp) {
        throw new RuntimeException('Run record finish time must not precede its start time.');
    }

    if (($finishedTimestamp - $startedTimestamp) > $wallSeconds) {
        throw new RuntimeException('Run record elapsed time exceeds the task wall-time budget.');
    }
}

function agentEvaluationTimestamp(string $value, string $field): int
{
    if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $value) !== 1) {
        throw new RuntimeException("Run record timing field {$field} must use canonical UTC seconds.");
    }

    $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$timestamp instanceof DateTimeImmutable
        || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
        || $timestamp->format('Y-m-d\TH:i:s\Z') !== $value
    ) {
        throw new RuntimeException("Run record timing field {$field} is not a valid canonical UTC timestamp.");
    }

    return $timestamp->getTimestamp();
}

/** @param array<string, mixed> $record */
function agentEvaluationValidateRunArtifacts(array $record, string $recordDirectory): void
{
    $artifactRoot = realpath($recordDirectory);

    if (!is_string($artifactRoot) || !is_dir($artifactRoot)) {
        throw new RuntimeException('Run artifact root is unavailable.');
    }

    $eventsPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'events_path', 'run record'),
        'run record events path',
    );
    $candidatePatchPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'candidate_patch_path', 'run record'),
        'run record candidate patch path',
    );
    $dependenciesManifestPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'prepared_dependencies_manifest_path', 'run record'),
        'run record prepared-dependencies manifest path',
    );

    if (count(array_unique([$eventsPath, $candidatePatchPath, $dependenciesManifestPath], SORT_STRING)) !== 3) {
        throw new RuntimeException('Run events, candidate patch, and dependency manifest must use distinct artifact paths.');
    }

    $events = agentEvaluationContainedArtifactPath($artifactRoot, $eventsPath, 'run events artifact');
    $candidatePatch = agentEvaluationContainedArtifactPath(
        $artifactRoot,
        $candidatePatchPath,
        'candidate patch artifact',
    );
    $dependenciesManifest = agentEvaluationContainedArtifactPath(
        $artifactRoot,
        $dependenciesManifestPath,
        'prepared-dependencies manifest artifact',
    );

    if (count(array_unique([$events, $candidatePatch, $dependenciesManifest], SORT_STRING)) !== 3) {
        throw new RuntimeException('Run events, candidate patch, and dependency manifest must resolve to distinct files.');
    }

    agentEvaluationRequireDistinctFileIdentities([$events, $candidatePatch, $dependenciesManifest]);

    agentEvaluationRequireBoundedFile($events, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'run events artifact');
    agentEvaluationRequireBoundedFile($candidatePatch, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'candidate patch artifact');
    agentEvaluationRequireBoundedFile(
        $dependenciesManifest,
        AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
        'prepared-dependencies manifest artifact',
    );
    agentEvaluationValidateDependencyManifest($dependenciesManifest);
    agentEvaluationRequireFileHash(
        $events,
        agentEvaluationRequireString($record, 'events_sha256', 'run record'),
        'run events artifact',
    );
    agentEvaluationRequireFileHash(
        $candidatePatch,
        agentEvaluationRequireString($record, 'candidate_patch_sha256', 'run record'),
        'candidate patch artifact',
    );
    agentEvaluationRequireFileHash(
        $dependenciesManifest,
        agentEvaluationRequireString($record, 'prepared_dependencies_manifest_sha256', 'run record'),
        'prepared-dependencies manifest artifact',
    );
}

/** @param list<string> $paths */
function agentEvaluationRequireDistinctFileIdentities(array $paths): void
{
    $identities = [];

    foreach ($paths as $path) {
        $metadata = agentEvaluationFileMetadata($path);

        if (!is_array($metadata) || !is_int($metadata['dev'] ?? null) || !is_int($metadata['ino'] ?? null)) {
            throw new RuntimeException('Run artifact file identity is unavailable.');
        }

        if (($metadata['nlink'] ?? null) !== 1) {
            throw new RuntimeException('Run artifacts must not use hard-linked files.');
        }

        $identity = $metadata['dev'] . ':' . $metadata['ino'];

        if (isset($identities[$identity])) {
            throw new RuntimeException('Run artifacts must use distinct filesystem identities.');
        }

        $identities[$identity] = true;
    }
}

function agentEvaluationFileMetadata(string $path): mixed
{
    return stat($path);
}

function agentEvaluationValidateDependencyManifest(string $path): void
{
    agentEvaluationDependencyManifestFiles($path);
}

/**
 * @return array<string, array{mode: string, sha256: string}>
 */
function agentEvaluationDependencyManifestFiles(string $path): array
{
    $source = file_get_contents($path);

    if (!is_string($source) || $source === '' || !str_ends_with($source, "\n") || str_contains($source, "\r")) {
        throw new RuntimeException('Prepared-dependencies manifest must be non-empty canonical LF text.');
    }

    $lines = explode("\n", substr($source, 0, -1));
    $sorted = $lines;
    sort($sorted, SORT_STRING);

    if ($lines !== $sorted || count(array_unique($lines, SORT_STRING)) !== count($lines)) {
        throw new RuntimeException('Prepared-dependencies manifest lines must be unique and byte-sorted.');
    }

    $seenPaths = [];

    foreach ($lines as $line) {
        if (preg_match('/\A(100644|100755) ([a-f0-9]{64}) (.+)\z/D', $line, $matches) !== 1) {
            throw new RuntimeException('Prepared-dependencies manifest has an invalid line.');
        }

        $dependencyPath = agentEvaluationRequireRelativePath($matches[3], 'prepared-dependencies manifest path');

        if (strlen($dependencyPath) > 4_096 || isset($seenPaths[$dependencyPath])) {
            throw new RuntimeException('Prepared-dependencies manifest paths must be unique and bounded.');
        }

        $seenPaths[$dependencyPath] = [
            'mode' => $matches[1],
            'sha256' => $matches[2],
        ];
    }

    return $seenPaths;
}

/**
 * @param array<string, mixed> $lock
 * @return array<string, array{
 *   version: string,
 *   source_reference: string|null,
 *   dist_reference: string|null,
 *   dev: bool
 * }>
 */
function agentEvaluationExplanationComposerLockPackages(array $lock): array
{
    $packages = [];

    foreach ([['packages', false], ['packages-dev', true]] as [$field, $dev]) {
        foreach (agentEvaluationRequireList($lock, $field, 'tracked explanation composer.lock') as $value) {
            $package = agentEvaluationValueObject($value, 'tracked explanation Composer package');
            $name = agentEvaluationExplanationComposerPackageName(
                $package,
                'tracked explanation Composer package',
            );

            if (isset($packages[$name])) {
                throw new RuntimeException(
                    'Explanation prepared Composer metadata does not match the admitted lock.',
                );
            }

            $packages[$name] = [
                'version' => agentEvaluationExplanationComposerPackageVersion(
                    $package,
                    'tracked explanation Composer package',
                ),
                'source_reference' => agentEvaluationExplanationComposerPackageReference(
                    $package['source'] ?? null,
                    'tracked explanation Composer package source',
                ),
                'dist_reference' => agentEvaluationExplanationComposerPackageReference(
                    $package['dist'] ?? null,
                    'tracked explanation Composer package dist',
                ),
                'dev' => $dev,
            ];
        }
    }

    ksort($packages, SORT_STRING);

    return $packages;
}

/**
 * @param array<string, mixed> $installed
 * @return array<string, array{
 *   version: string,
 *   source_reference: string|null,
 *   dist_reference: string|null,
 *   dev: bool
 * }>
 */
function agentEvaluationExplanationInstalledComposerPackages(array $installed): array
{
    $devPackageNames = agentEvaluationRequireStringList(
        $installed,
        'dev-package-names',
        'explanation installed Composer metadata',
    );
    $sortedDevPackageNames = $devPackageNames;
    sort($sortedDevPackageNames, SORT_STRING);

    if (
        count(array_unique($sortedDevPackageNames, SORT_STRING)) !== count($sortedDevPackageNames)
        || !is_bool($installed['dev'] ?? null)
        || $installed['dev'] !== ($sortedDevPackageNames !== [])
    ) {
        throw new RuntimeException(
            'Explanation prepared Composer metadata does not match the admitted lock.',
        );
    }

    $devPackages = array_fill_keys($sortedDevPackageNames, true);
    $packages = [];

    foreach (
        agentEvaluationRequireList(
            $installed,
            'packages',
            'explanation installed Composer metadata',
        ) as $value
    ) {
        $package = agentEvaluationValueObject($value, 'explanation installed Composer package');
        $name = agentEvaluationExplanationComposerPackageName(
            $package,
            'explanation installed Composer package',
        );

        if ($name === 'phpthis/framework') {
            throw new RuntimeException(
                'Explanation prepared dependencies expose a duplicate phpthis/framework package path.',
            );
        }

        if (
            agentEvaluationRequireNonEmptyString(
                $package,
                'install-path',
                'explanation installed Composer package',
            ) !== '../' . $name
            || isset($packages[$name])
        ) {
            throw new RuntimeException(
                'Explanation prepared Composer metadata does not match the admitted lock.',
            );
        }

        $packages[$name] = [
            'version' => agentEvaluationExplanationComposerPackageVersion(
                $package,
                'explanation installed Composer package',
            ),
            'source_reference' => agentEvaluationExplanationComposerPackageReference(
                $package['source'] ?? null,
                'explanation installed Composer package source',
            ),
            'dist_reference' => agentEvaluationExplanationComposerPackageReference(
                $package['dist'] ?? null,
                'explanation installed Composer package dist',
            ),
            'dev' => isset($devPackages[$name]),
        ];
    }

    foreach (array_keys($devPackages) as $name) {
        if (!isset($packages[$name])) {
            throw new RuntimeException(
                'Explanation prepared Composer metadata does not match the admitted lock.',
            );
        }
    }

    ksort($packages, SORT_STRING);

    return $packages;
}

/** @param array<string, mixed> $package */
function agentEvaluationExplanationComposerPackageName(array $package, string $owner): string
{
    $name = agentEvaluationRequireNonEmptyString($package, 'name', $owner);

    if (
        strlen($name) > 255
        || preg_match('/\A[a-z0-9_.-]+\/[a-z0-9_.-]+\z/D', $name) !== 1
    ) {
        throw new RuntimeException(
            'Explanation prepared Composer metadata does not match the admitted lock.',
        );
    }

    return $name;
}

/** @param array<string, mixed> $package */
function agentEvaluationExplanationComposerPackageVersion(array $package, string $owner): string
{
    $version = agentEvaluationRequireNonEmptyString($package, 'version', $owner);

    if (strlen($version) > 255 || preg_match('/[\x00-\x1F\x7F]/', $version) === 1) {
        throw new RuntimeException(
            'Explanation prepared Composer metadata does not match the admitted lock.',
        );
    }

    return $version;
}

function agentEvaluationExplanationComposerPackageReference(mixed $value, string $owner): ?string
{
    if ($value === null) {
        return null;
    }

    $source = agentEvaluationValueObject($value, $owner);
    $reference = $source['reference'] ?? null;

    if ($reference === null) {
        return null;
    }

    if (
        !is_string($reference)
        || $reference === ''
        || strlen($reference) > 255
        || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1
    ) {
        throw new RuntimeException(
            'Explanation prepared Composer metadata does not match the admitted lock.',
        );
    }

    return $reference;
}

/**
 * @param array<string, mixed> $record
 * @param array<string, mixed> $task
 */
function agentEvaluationValidateExplanationRunRecord(array $record, array $task): void
{
    $owner = 'explanation run record';
    agentEvaluationRequireExactKeys(
        $record,
        [
            'schema_version',
            'execution_kind',
            'run_id',
            'task_id',
            'task_revision',
            'task_manifest_sha256',
            'prompt_sha256',
            'effective_prompt_sha256',
            'rubric_sha256',
            'base_revision',
            'base_tree',
            'base_fixture_sha256',
            'prepared_dependencies_manifest_path',
            'prepared_dependencies_manifest_sha256',
            'prepared_lock_path',
            'prepared_lock_sha256',
            'prepared_installed_metadata_path',
            'prepared_installed_metadata_sha256',
            'prepared_installed_package_count',
            'condition',
            'runner',
            'model',
            'context',
            'tools',
            'transport_tools',
            'budgets',
            'usage',
            'timing',
            'repair_turns',
            'termination_reason',
            'events_path',
            'events_sha256',
            'candidate_patch_path',
            'candidate_patch_sha256',
            'response_path',
            'response_sha256',
        ],
        $owner,
    );

    if (
        ($task['schema_version'] ?? null) !== 3
        || ($task['id'] ?? null) !== AGENT_EVALUATION_EXPLANATION_TASK_ID
        || ($task['kind'] ?? null) !== 'explanation'
    ) {
        throw new RuntimeException('Explanation run validation requires the explicit schema-v3 task.');
    }

    if (agentEvaluationRequireInteger($record, 'schema_version', $owner) !== 3) {
        throw new RuntimeException('Explanation run record must use schema version 3.');
    }

    $executionKind = agentEvaluationRequireString($record, 'execution_kind', $owner);

    if (!in_array($executionKind, ['live-model', 'synthetic-control'], true)) {
        throw new RuntimeException('Explanation run execution kind must be live-model or synthetic-control.');
    }

    $runId = agentEvaluationRequireString($record, 'run_id', $owner);

    if (preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1) {
        throw new RuntimeException('Explanation run ID must use 32 lowercase hexadecimal characters.');
    }

    if (
        agentEvaluationRequireString($record, 'task_id', $owner) !== $task['id']
        || agentEvaluationRequireInteger($record, 'task_revision', $owner) !== $task['revision']
    ) {
        throw new RuntimeException('Explanation run record task identity does not match the selected task.');
    }

    $prompt = agentEvaluationRequireObject($task, 'prompt', 'explanation task');
    $rubric = agentEvaluationRequireObject($task, 'rubric', 'explanation task');

    foreach (
        [
            'task_manifest_sha256' => agentEvaluationRequireString(
                $task,
                'manifest_sha256',
                'explanation task',
            ),
            'prompt_sha256' => agentEvaluationRequireString($prompt, 'sha256', 'explanation task prompt'),
            'effective_prompt_sha256' => agentEvaluationRequireString(
                $prompt,
                'effective_sha256',
                'explanation task prompt',
            ),
            'rubric_sha256' => agentEvaluationRequireString($rubric, 'sha256', 'explanation task rubric'),
        ] as $name => $expected
    ) {
        $actual = agentEvaluationRequireHash(
            agentEvaluationRequireString($record, $name, $owner),
            "{$owner} {$name}",
        );

        if (!hash_equals($expected, $actual)) {
            throw new RuntimeException('Explanation run record task artifacts do not match the selected task.');
        }
    }

    $base = agentEvaluationRequireObject($task, 'base', 'explanation task');

    if (
        agentEvaluationRequireString($record, 'base_revision', $owner) !== ($base['revision'] ?? null)
        || agentEvaluationRequireString($record, 'base_tree', $owner) !== ($base['tree'] ?? null)
        || agentEvaluationRequireHash(
            agentEvaluationRequireString($record, 'base_fixture_sha256', $owner),
            $owner . ' base fixture',
        ) !== ($base['fixture_sha256'] ?? null)
    ) {
        throw new RuntimeException('Explanation run record base does not match the pinned tracked maintainer source.');
    }

    agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($record, 'prepared_dependencies_manifest_path', $owner),
        $owner . ' prepared-dependencies manifest path',
    );
    agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'prepared_dependencies_manifest_sha256', $owner),
        $owner . ' prepared-dependencies manifest',
    );
    $preparedLockPath = $record['prepared_lock_path'] ?? null;
    $preparedLockSha256 = $record['prepared_lock_sha256'] ?? null;
    $installedMetadataPath = $record['prepared_installed_metadata_path'] ?? null;
    $installedMetadataSha256 = $record['prepared_installed_metadata_sha256'] ?? null;
    $installedPackageCount = $record['prepared_installed_package_count'] ?? null;

    if ($executionKind === 'live-model') {
        if (
            $preparedLockPath !== 'dependencies.lock'
            || !is_string($preparedLockSha256)
            || $installedMetadataPath !== 'dependencies.installed.json'
            || !is_string($installedMetadataSha256)
            || !is_int($installedPackageCount)
            || $installedPackageCount < 0
            || $installedPackageCount > 4_000
        ) {
            throw new RuntimeException(
                'Live explanation run must bind its retained lock and installed Composer metadata.',
            );
        }
        agentEvaluationRequireHash($preparedLockSha256, $owner . ' prepared lock');
        agentEvaluationRequireHash(
            $installedMetadataSha256,
            $owner . ' installed Composer metadata',
        );
    } elseif (
        $preparedLockPath !== null
        || $preparedLockSha256 !== null
        || $installedMetadataPath !== null
        || $installedMetadataSha256 !== null
        || $installedPackageCount !== null
    ) {
        throw new RuntimeException(
            'Synthetic explanation controls cannot claim prepared dependency-provenance artifacts.',
        );
    }

    $runProfile = agentEvaluationNormalizeExplanationExecutionProfile(
        [
            'condition' => agentEvaluationRequireString($record, 'condition', $owner),
            'runner' => agentEvaluationRequireObject($record, 'runner', $owner),
            'model' => agentEvaluationRequireObject($record, 'model', $owner),
            'context' => agentEvaluationRequireObject($record, 'context', $owner),
            'tools' => agentEvaluationRequireList($record, 'tools', $owner),
            'transport_tools' => $record['transport_tools'],
        ],
        $owner . ' execution profile',
    );
    $taskProfile = agentEvaluationNormalizeExplanationExecutionProfile(
        agentEvaluationRequireObject($task, 'execution_profile', 'explanation task'),
        'explanation task execution profile',
    );

    foreach ($runProfile['tools'] as $tool) {
        $permissions = $tool['permissions'];

        if (in_array('workspace-write', $permissions, true)) {
            throw new RuntimeException('Explanation run tools cannot claim candidate workspace write permission.');
        }
    }

    if ($executionKind === 'live-model' && $runProfile !== $taskProfile) {
        throw new RuntimeException('Live explanation run profile does not match the pinned task profile.');
    }

    if (
        $executionKind === 'synthetic-control'
        && (
            $runProfile['condition'] !== $taskProfile['condition']
            || $runProfile['runner']['name'] !== 'fake-codex'
            || $runProfile['context'] !== $taskProfile['context']
            || $runProfile['tools'] !== $taskProfile['tools']
            || $runProfile['transport_tools'] !== null
            || $runProfile['model']['provider'] !== 'synthetic'
        )
    ) {
        throw new RuntimeException('Synthetic explanation control must keep the read-only profile, null transport identity, and synthetic provider label.');
    }

    $taskBudgets = agentEvaluationValidateBudgets(
        agentEvaluationRequireObject($task, 'budgets', 'explanation task'),
        AGENT_EVALUATION_EXPLANATION_TASK_ID,
    );
    agentEvaluationValidateRunBudgets(agentEvaluationRequireObject($record, 'budgets', $owner), $taskBudgets);
    $usage = agentEvaluationRequireObject($record, 'usage', $owner);
    agentEvaluationValidateUsage($usage, $taskBudgets['model_tokens']);
    $observedUsage = [];

    foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens'] as $name) {
        $value = $usage[$name] ?? null;

        if (!is_int($value)) {
            throw new RuntimeException('Completed explanation run usage must contain integer token values.');
        }

        $observedUsage[$name] = $value;
    }

    if (
        $observedUsage['cached_tokens'] > $observedUsage['input_tokens']
        || $observedUsage['reasoning_tokens'] > $observedUsage['output_tokens']
    ) {
        throw new RuntimeException('Explanation cached and reasoning tokens cannot exceed their provider totals.');
    }
    agentEvaluationValidateTiming(
        agentEvaluationRequireObject($record, 'timing', $owner),
        $taskBudgets['wall_seconds'],
    );

    if (
        agentEvaluationRequireNonNegativeInteger($record, 'repair_turns', $owner) !== 0
        || agentEvaluationRequireString($record, 'termination_reason', $owner) !== 'completed'
    ) {
        throw new RuntimeException('Explanation run record must complete without a post-score repair turn.');
    }

    foreach (['events_path', 'candidate_patch_path', 'response_path'] as $name) {
        agentEvaluationRequireRelativePath(
            agentEvaluationRequireString($record, $name, $owner),
            "{$owner} {$name}",
        );
    }

    foreach (['events_sha256', 'response_sha256'] as $name) {
        agentEvaluationRequireHash(
            agentEvaluationRequireString($record, $name, $owner),
            "{$owner} {$name}",
        );
    }

    if (
        agentEvaluationRequireHash(
            agentEvaluationRequireString($record, 'candidate_patch_sha256', $owner),
            $owner . ' candidate patch',
        ) !== hash('sha256', '')
    ) {
        throw new RuntimeException('Explanation run record must bind an empty candidate patch.');
    }
}

/** @param array<string, mixed> $record */
function agentEvaluationValidateExplanationRunArtifacts(array $record, string $artifactRoot): void
{
    $root = realpath($artifactRoot);

    if (!is_string($root) || !is_dir($root)) {
        throw new RuntimeException('Explanation run artifact root is unavailable.');
    }

    $descriptors = [
        'events' => ['path' => 'events_path', 'hash' => 'events_sha256'],
        'candidate patch' => ['path' => 'candidate_patch_path', 'hash' => 'candidate_patch_sha256'],
        'prepared-dependencies manifest' => [
            'path' => 'prepared_dependencies_manifest_path',
            'hash' => 'prepared_dependencies_manifest_sha256',
        ],
        'response' => ['path' => 'response_path', 'hash' => 'response_sha256'],
    ];
    if (($record['execution_kind'] ?? null) === 'live-model') {
        $descriptors['prepared lock'] = [
            'path' => 'prepared_lock_path',
            'hash' => 'prepared_lock_sha256',
        ];
        $descriptors['installed Composer metadata'] = [
            'path' => 'prepared_installed_metadata_path',
            'hash' => 'prepared_installed_metadata_sha256',
        ];
    }
    $relativePaths = [];
    $paths = [];
    $resolvedPaths = [];

    foreach ($descriptors as $name => $descriptor) {
        $relative = agentEvaluationRequireRelativePath(
            agentEvaluationRequireString($record, $descriptor['path'], 'explanation run record'),
            "explanation {$name} artifact path",
        );
        $path = agentEvaluationContainedArtifactPath($root, $relative, "explanation {$name} artifact");
        agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, "explanation {$name} artifact");
        agentEvaluationRequireFileHash(
            $path,
            agentEvaluationRequireString($record, $descriptor['hash'], 'explanation run record'),
            "explanation {$name} artifact",
        );
        $relativePaths[] = $relative;
        $paths[] = $path;
        $resolvedPaths[$name] = $path;
    }

    if (count(array_unique($relativePaths, SORT_STRING)) !== count($relativePaths)) {
        throw new RuntimeException('Explanation run artifacts must use distinct relative paths.');
    }

    agentEvaluationRequireDistinctFileIdentities($paths);
    $events = $resolvedPaths['events'];
    $candidatePatch = $resolvedPaths['candidate patch'];
    $dependenciesManifest = $resolvedPaths['prepared-dependencies manifest'];
    $response = $resolvedPaths['response'];

    if (filesize($candidatePatch) !== 0) {
        throw new RuntimeException('Explanation candidate patch must be empty.');
    }

    $dependencyFiles = agentEvaluationDependencyManifestFiles($dependenciesManifest);
    $preparedLock = $resolvedPaths['prepared lock'] ?? null;
    $installedMetadata = $resolvedPaths['installed Composer metadata'] ?? null;
    if (is_string($preparedLock) && is_string($installedMetadata)) {
        $recordedMetadataSha256 = agentEvaluationRequireString(
            $record,
            'prepared_installed_metadata_sha256',
            'explanation run record',
        );
        if (
            ($dependencyFiles['composer/installed.json']['sha256'] ?? null)
                !== $recordedMetadataSha256
        ) {
            throw new RuntimeException(
                'Explanation prepared-dependencies manifest does not bind retained Composer metadata.',
            );
        }
        foreach (array_keys($dependencyFiles) as $dependencyPath) {
            if (
                $dependencyPath === 'phpthis/framework'
                || str_starts_with($dependencyPath, 'phpthis/framework/')
            ) {
                throw new RuntimeException(
                    'Explanation prepared dependencies expose a duplicate phpthis/framework package path.',
                );
            }
        }
        $lockedPackages = agentEvaluationExplanationComposerLockPackages(
            agentEvaluationJsonFile($preparedLock),
        );
        $installedPackages = agentEvaluationExplanationInstalledComposerPackages(
            agentEvaluationJsonFile($installedMetadata),
        );
        if (
            $lockedPackages !== $installedPackages
            || count($installedPackages) !== agentEvaluationRequireNonNegativeInteger(
                $record,
                'prepared_installed_package_count',
                'explanation run record',
            )
        ) {
            throw new RuntimeException(
                'Explanation retained Composer metadata does not match the admitted lock and package count.',
            );
        }
        foreach (array_keys($installedPackages) as $packageName) {
            $prefix = $packageName . '/';
            $present = false;
            foreach (array_keys($dependencyFiles) as $dependencyPath) {
                if (str_starts_with($dependencyPath, $prefix)) {
                    $present = true;
                    break;
                }
            }
            if (!$present) {
                throw new RuntimeException(
                    'Explanation prepared-dependencies manifest omits an installed Composer package path.',
                );
            }
        }
    }
    $eventEvidence = agentEvaluationExplanationEventEvidence($events, $response);
    $recordedUsage = agentEvaluationRequireObject($record, 'usage', 'explanation run record');

    if ($eventEvidence['usage'] !== [
        'input_tokens' => agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'input_tokens',
            'explanation run record usage',
        ),
        'output_tokens' => agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'output_tokens',
            'explanation run record usage',
        ),
        'cached_tokens' => agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'cached_tokens',
            'explanation run record usage',
        ),
        'reasoning_tokens' => agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'reasoning_tokens',
            'explanation run record usage',
        ),
    ]) {
        throw new RuntimeException('Explanation terminal event usage does not match the validated run record.');
    }
}

/**
 * @return array{
 *   commands: list<array{item_id: string, sha256: string, bytes: int}>,
 *   file_change_events: int,
 *   usage: array{input_tokens: int, output_tokens: int, cached_tokens: int, reasoning_tokens: int}
 * }
 */
function agentEvaluationExplanationEventEvidence(string $events, string $response): array
{
    $eventBytes = file_get_contents($events);
    $responseBytes = file_get_contents($response);

    if (
        !is_string($eventBytes)
        || $eventBytes === ''
        || !str_ends_with($eventBytes, "\n")
        || str_contains($eventBytes, "\0")
    ) {
        throw new RuntimeException('Explanation events artifact must contain retained event evidence.');
    }

    if (
        !is_string($responseBytes)
        || trim($responseBytes) === ''
        || strlen($responseBytes) > AGENT_EVALUATION_MAX_JSON_BYTES
        || str_contains($responseBytes, "\0")
    ) {
        throw new RuntimeException('Explanation response artifact must contain bounded non-empty text.');
    }

    $eventLines = explode("\n", $eventBytes);
    array_pop($eventLines);

    if (count($eventLines) > AGENT_EVALUATION_EXPLANATION_MAX_EVENTS) {
        throw new RuntimeException('Explanation events artifact exceeds its fixed event-count bound.');
    }

    $lastAgentMessage = null;
    /** @var array<string, array{item_id: string, sha256: string, bytes: int}> $commands */
    $commands = [];
    /** @var array<string, true> $commandCompleted */
    $commandCompleted = [];
    /** @var array<string, array{type: string, seen: bool, completed: bool}> $itemStates */
    $itemStates = [];
    $fileChangeEvents = 0;
    $threadStarted = false;
    $turnStarted = false;
    $terminalSeen = false;
    $usage = null;

    foreach ($eventLines as $index => $line) {
        if ($line === '' || strlen($line) > AGENT_EVALUATION_MAX_JSON_BYTES) {
            throw new RuntimeException('Explanation events artifact contains an invalid bounded JSONL record.');
        }

        $event = agentEvaluationValueObject(
            agentEvaluationJsonValue($line, "explanation event record {$index}"),
            "explanation event record {$index}",
        );
        $eventType = agentEvaluationRequireString($event, 'type', "explanation event record {$index}");

        if ($terminalSeen) {
            throw new RuntimeException('Explanation events artifact contains data after its terminal event.');
        }

        if ($eventType === 'thread.started') {
            agentEvaluationRequireNonEmptyString(
                $event,
                'thread_id',
                "explanation event record {$index}",
            );

            if ($threadStarted || $turnStarted) {
                throw new RuntimeException('Explanation events artifact has an invalid thread lifecycle.');
            }

            $threadStarted = true;
            continue;
        }

        if ($eventType === 'turn.started') {
            if (!$threadStarted || $turnStarted) {
                throw new RuntimeException('Explanation events artifact has an invalid turn lifecycle.');
            }

            $turnStarted = true;
            continue;
        }

        if ($eventType === 'turn.completed') {
            if (!$turnStarted) {
                throw new RuntimeException('Explanation events artifact has an invalid turn lifecycle.');
            }

            $rawUsage = agentEvaluationRequireObject(
                $event,
                'usage',
                "explanation event record {$index}",
            );
            $expectedUsageKeys = [
                'input_tokens',
                'cached_input_tokens',
                'output_tokens',
                'reasoning_output_tokens',
            ];
            if (array_key_exists('cache_write_input_tokens', $rawUsage)) {
                $expectedUsageKeys[] = 'cache_write_input_tokens';
            }
            agentEvaluationRequireExactKeys(
                $rawUsage,
                $expectedUsageKeys,
                "explanation event record {$index} usage",
            );
            $inputTokens = agentEvaluationRequireNonNegativeInteger(
                $rawUsage,
                'input_tokens',
                "explanation event record {$index} usage",
            );
            $outputTokens = agentEvaluationRequireNonNegativeInteger(
                $rawUsage,
                'output_tokens',
                "explanation event record {$index} usage",
            );
            $cachedTokens = agentEvaluationRequireNonNegativeInteger(
                $rawUsage,
                'cached_input_tokens',
                "explanation event record {$index} usage",
            );
            $reasoningTokens = agentEvaluationRequireNonNegativeInteger(
                $rawUsage,
                'reasoning_output_tokens',
                "explanation event record {$index} usage",
            );
            $cacheWriteTokens = array_key_exists('cache_write_input_tokens', $rawUsage)
                ? agentEvaluationRequireNonNegativeInteger(
                    $rawUsage,
                    'cache_write_input_tokens',
                    "explanation event record {$index} usage",
                )
                : 0;

            if (
                $cachedTokens > $inputTokens
                || $cacheWriteTokens > $inputTokens
                || $reasoningTokens > $outputTokens
            ) {
                throw new RuntimeException('Explanation terminal event contains inconsistent usage evidence.');
            }

            $usage = [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_tokens' => $cachedTokens,
                'reasoning_tokens' => $reasoningTokens,
            ];
            $terminalSeen = true;
            continue;
        }

        if (
            !$turnStarted
            || !in_array($eventType, ['item.started', 'item.updated', 'item.completed'], true)
        ) {
            throw new RuntimeException('Explanation events artifact contains an unapproved event or item type.');
        }

        $item = agentEvaluationValueObject(
            $event['item'] ?? null,
            "explanation event record {$index} item",
        );
        $itemId = agentEvaluationRequireNonEmptyString(
            $item,
            'id',
            "explanation event record {$index} item",
        );
        $itemType = agentEvaluationRequireString($item, 'type', "explanation event record {$index} item");

        if (
            isset($itemStates[$itemId])
            && (
                $itemStates[$itemId]['type'] !== $itemType
                || $itemStates[$itemId]['completed']
                || ($eventType === 'item.started' && $itemStates[$itemId]['seen'])
            )
        ) {
            throw new RuntimeException('Explanation events artifact contains an invalid item lifecycle.');
        }

        $itemStates[$itemId] ??= ['type' => $itemType, 'seen' => false, 'completed' => false];
        $itemStates[$itemId]['seen'] = true;
        if ($eventType === 'item.completed') {
            $itemStates[$itemId]['completed'] = true;
        }

        if ($itemType === 'file_change') {
            $fileChangeEvents++;
            continue;
        }

        if (!in_array($itemType, ['agent_message', 'reasoning', 'command_execution', 'todo_list'], true)) {
            throw new RuntimeException('Explanation events artifact contains an unapproved event or item type.');
        }

        if ($itemType === 'command_execution') {
            $command = $item['command'] ?? null;

            if (!is_string($command)) {
                if ($eventType === 'item.completed') {
                    throw new RuntimeException('Explanation events artifact contains inconsistent command evidence.');
                }

                continue;
            }

            $descriptor = [
                'item_id' => $itemId,
                'sha256' => hash('sha256', $command),
                'bytes' => strlen($command),
            ];

            if (isset($commands[$itemId]) && $commands[$itemId] !== $descriptor) {
                throw new RuntimeException('Explanation events artifact contains inconsistent command evidence.');
            }

            $commands[$itemId] = $descriptor;
            if ($eventType === 'item.completed') {
                $commandCompleted[$itemId] = true;
            }
        }

        if ($eventType === 'item.completed' && $itemType === 'agent_message') {
            $lastAgentMessage = agentEvaluationRequireString(
                $item,
                'text',
                "explanation event record {$index} item",
            );
        }
    }

    if (!$threadStarted || !$turnStarted || !$terminalSeen || $usage === null) {
        throw new RuntimeException('Explanation events artifact does not contain one completed Codex lifecycle.');
    }

    foreach (array_keys($commands) as $itemId) {
        if (($commandCompleted[$itemId] ?? false) !== true) {
            throw new RuntimeException('Explanation events artifact contains an incomplete command lifecycle.');
        }
    }

    if ($fileChangeEvents !== 0) {
        throw new RuntimeException('Explanation events artifact contains a file-change item.');
    }

    if ($lastAgentMessage === null || $responseBytes !== $lastAgentMessage . "\n") {
        throw new RuntimeException('Explanation response artifact must equal the last completed agent message.');
    }

    return [
        'commands' => array_values($commands),
        'file_change_events' => $fileChangeEvents,
        'usage' => $usage,
    ];
}

/**
 * Version 2 records include every planned slot; absence of generated artifacts is
 * represented explicitly rather than inventing a completed version-1 run.
 *
 * @param array<string, mixed> $record
 * @param array<string, mixed> $task
 * @param array{slot:int,round:int,task_id:string,condition:string} $slot
 */
function agentEvaluationValidateComparisonRunRecord(
    array $record,
    array $task,
    array $slot,
    string $campaignId,
    string $protocolHash,
): void {
    $owner = 'comparison attempt';
    agentEvaluationRequireExactKeys($record, [
        'schema_version', 'campaign_id', 'slot', 'run_id', 'task_id', 'task_revision',
        'condition', 'protocol_sha256', 'task_manifest_sha256', 'source_revision',
        'base_fixture_sha256', 'prepared_dependencies_manifest_sha256', 'prepared_lock_sha256',
        'profile_sha256', 'holdout_sha256', 'status', 'phase', 'termination_reason',
        'usage', 'elapsed_milliseconds', 'repair_turns', 'unknown_metrics', 'artifacts',
    ], $owner);
    if (agentEvaluationRequireInteger($record, 'schema_version', $owner) !== 2
        || agentEvaluationRequireString($record, 'campaign_id', $owner) !== $campaignId
        || preg_match('/\A[a-f0-9]{32}\z/D', $campaignId) !== 1
        || agentEvaluationRequireInteger($record, 'slot', $owner) !== $slot['slot']
        || $slot['slot'] < 1 || $slot['slot'] > 60
        || agentEvaluationRequireString($record, 'task_id', $owner) !== $slot['task_id']
        || agentEvaluationRequireString($task, 'id', $owner) !== $slot['task_id']
        || agentEvaluationRequireString($record, 'condition', $owner) !== $slot['condition']
        || !in_array($slot['condition'], ['phpthis', 'plain-php'], true)
        || agentEvaluationRequireInteger($record, 'task_revision', $owner) !== agentEvaluationRequireInteger($task, 'revision', $owner)
    ) {
        throw new RuntimeException('Comparison attempt must match its fixed campaign slot and task revision.');
    }
    $expectedRunId = substr(hash('sha256', $campaignId . ':' . $slot['slot']), 0, 32);
    if (agentEvaluationRequireString($record, 'run_id', $owner) !== $expectedRunId) {
        throw new RuntimeException('Comparison run ID must equal its deterministic campaign slot identity.');
    }
    foreach ([
        'protocol_sha256', 'task_manifest_sha256', 'base_fixture_sha256',
        'prepared_dependencies_manifest_sha256', 'prepared_lock_sha256', 'profile_sha256', 'holdout_sha256',
    ] as $name) {
        agentEvaluationRequireHash(agentEvaluationRequireString($record, $name, $owner), $owner . ' ' . $name);
    }
    if ($record['protocol_sha256'] !== $protocolHash
        || $record['task_manifest_sha256'] !== agentEvaluationRequireString($task, 'manifest_sha256', $owner)
    ) {
        throw new RuntimeException('Comparison attempt protocol and task hashes must match their frozen identities.');
    }
    $sourceRevision = agentEvaluationRequireString($record, 'source_revision', $owner);
    if (preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/D', $sourceRevision) !== 1) {
        throw new RuntimeException('Comparison source revision must be one exact Git object ID.');
    }
    $conditions = agentEvaluationRequireList($task, 'conditions', $owner);
    $matched = false;
    foreach ($conditions as $conditionValue) {
        $condition = agentEvaluationValueObject($conditionValue, $owner . ' condition');
        if (($condition['id'] ?? null) !== $slot['condition']) {
            continue;
        }
        $base = agentEvaluationRequireObject($condition, 'base', $owner);
        $matched = $record['base_fixture_sha256'] === ($base['fixture_sha256'] ?? null);
    }
    $checks = agentEvaluationRequireObject($task, 'checks', $owner);
    $holdout = agentEvaluationRequireObject($checks, 'holdout', $owner);
    if (!$matched || $record['holdout_sha256'] !== ($holdout['sha256'] ?? null)) {
        throw new RuntimeException('Comparison fixture and private holdout must match the selected condition and task.');
    }
    $status = agentEvaluationRequireString($record, 'status', $owner);
    $phase = agentEvaluationRequireString($record, 'phase', $owner);
    if (!in_array($status, ['planned', 'running', 'complete', 'failed', 'not_run'], true)
        || !in_array($phase, ['planned', 'prepare', 'generate', 'freeze', 'score', 'validate', 'retain', 'cleanup', 'finished'], true)
    ) {
        throw new RuntimeException('Comparison attempt state is not one fixed lifecycle state.');
    }
    $termination = agentEvaluationRequireNullableString($record, 'termination_reason', $owner);
    if ($termination !== null && (strlen($termination) > 128 || preg_match('/\A[a-zA-Z0-9_.-]+\z/D', $termination) !== 1)) {
        throw new RuntimeException('Comparison termination must be one bounded structural code.');
    }
    if ((in_array($status, ['planned', 'not_run'], true) && $phase !== 'planned')
        || ($status === 'complete' && ($phase !== 'finished' || $termination !== 'completed'))
        || ($status === 'failed' && ($termination === null || $termination === 'completed'))
        || ($status === 'planned' && $termination !== null)
        || ($status === 'not_run' && $termination === null)
        || ($status === 'running' && ($termination !== null || in_array($phase, ['planned', 'finished'], true)))
        || ($status === 'failed' && in_array($phase, ['planned', 'finished'], true))
    ) {
        throw new RuntimeException('Comparison attempt state and termination are inconsistent.');
    }
    agentEvaluationValidateUsage(agentEvaluationRequireObject($record, 'usage', $owner), 40_000);
    $observedUsage = agentEvaluationRequireObject($record, 'usage', $owner);
    foreach (['cached_tokens' => 'input_tokens', 'reasoning_tokens' => 'output_tokens'] as $category => $total) {
        if (is_int($observedUsage[$category]) && is_int($observedUsage[$total]) && $observedUsage[$category] > $observedUsage[$total]) {
            throw new RuntimeException('Comparison token categories cannot exceed their known provider totals.');
        }
    }
    if (agentEvaluationRequireInteger($record, 'repair_turns', $owner) !== 0) {
        throw new RuntimeException('The fixed comparison permits zero post-score repair turns.');
    }
    $elapsed = $record['elapsed_milliseconds'];
    if ($elapsed !== null && (!is_int($elapsed) || $elapsed < 0 || $elapsed > 86_400_000)) {
        throw new RuntimeException('Comparison elapsed time must be an observed bounded millisecond count or null.');
    }
    $unknown = agentEvaluationRequireObject($record, 'unknown_metrics', $owner);
    $usage = agentEvaluationRequireObject($record, 'usage', $owner);
    $expectedUnknown = [];
    foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens'] as $name) {
        if ($usage[$name] === null) {
            $expectedUnknown[] = $name;
        }
    }
    if ($elapsed === null) {
        $expectedUnknown[] = 'elapsed_milliseconds';
    }
    // These require an actual observation or reviewer record; the controller's
    // fixed zero repair-turn budget cannot manufacture their values.
    foreach (['public_check_repairs', 'human_interventions', 'reviewer_effort'] as $name) {
        $expectedUnknown[] = $name;
    }
    agentEvaluationRequireExactKeys($unknown, $expectedUnknown, $owner . ' unknown metrics');
    foreach ($expectedUnknown as $name) {
        $reason = agentEvaluationRequireNonEmptyString($unknown, $name, $owner);
        if (strlen($reason) > 256 || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1) {
            throw new RuntimeException('Unknown comparison metrics require a bounded single-line reason.');
        }
    }
    $artifacts = agentEvaluationRequireObject($record, 'artifacts', $owner);
    if (count($artifacts) > 64) {
        throw new RuntimeException('Comparison attempt artifact inventory exceeds 64 entries.');
    }
    foreach ($artifacts as $name => $descriptorValue) {
        agentEvaluationRequireRelativePath($name, $owner . ' artifact');
        if (str_contains($name, '/') || in_array($name, ['attempt.json', 'comparison-score.json'], true)) {
            throw new RuntimeException('Comparison artifact inventory must use distinct flat inputs without self-reference.');
        }
        $descriptor = agentEvaluationValueObject($descriptorValue, $owner . ' artifact');
        agentEvaluationRequireExactKeys($descriptor, ['bytes', 'sha256'], $owner . ' artifact');
        $bytes = agentEvaluationRequireNonNegativeInteger($descriptor, 'bytes', $owner . ' artifact');
        if ($bytes > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
            throw new RuntimeException('Comparison retained artifact exceeds its size bound.');
        }
        agentEvaluationRequireHash(agentEvaluationRequireString($descriptor, 'sha256', $owner), $owner);
    }
    if (in_array($status, ['planned', 'not_run'], true)
        && ($artifacts !== [] || $elapsed !== null || array_filter($usage, static fn (mixed $value): bool => $value !== null) !== [])
    ) {
        throw new RuntimeException('An unstarted comparison slot cannot claim execution artifacts, timing, or usage.');
    }
    if ($status === 'complete') {
        foreach (['profile.json', 'events.jsonl', 'candidate.patch', 'application-check.json', 'observation-results.json', 'cleanup.json'] as $required) {
            if (!isset($artifacts[$required])) {
                throw new RuntimeException('A completed comparison attempt lacks required retained evidence.');
            }
        }
    }
}

/** @param array<string, mixed> $record */
function agentEvaluationValidateComparisonRunArtifacts(array $record, string $artifactRoot): void
{
    $artifacts = agentEvaluationRequireObject($record, 'artifacts', 'comparison retained artifacts');
    $paths = [];
    foreach ($artifacts as $name => $value) {
        $descriptor = agentEvaluationValueObject($value, 'comparison artifact descriptor');
        $path = agentEvaluationContainedArtifactPath($artifactRoot, $name, 'comparison retained artifact');
        agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'comparison retained artifact');
        if (filesize($path) !== agentEvaluationRequireInteger($descriptor, 'bytes', 'comparison retained artifact')) {
            throw new RuntimeException('Comparison artifact size does not match its retained descriptor.');
        }
        agentEvaluationRequireFileHash($path, agentEvaluationRequireString($descriptor, 'sha256', 'comparison retained artifact'), 'comparison retained artifact');
        $paths[] = $path;
    }
    agentEvaluationRequireDistinctFileIdentities($paths);
    $profile = $artifacts['profile.json'] ?? null;
    if ($profile !== null && agentEvaluationValueObject($profile, 'comparison profile descriptor')['sha256'] !== ($record['profile_sha256'] ?? null)) {
        throw new RuntimeException('Comparison profile provenance must bind the retained profile bytes.');
    }
}
