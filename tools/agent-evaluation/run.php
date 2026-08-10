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

        $seenPaths[$dependencyPath] = true;
    }
}
