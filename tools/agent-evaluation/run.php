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
