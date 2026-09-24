<?php

declare(strict_types=1);

define('PHPTHIS_AGENT_EVALUATION_LIBRARY_ONLY', true);

require __DIR__ . '/agent-evaluation.php';
require __DIR__ . '/process-support.php';

$root = dirname(__DIR__);
$kit = $root . '/tools/agent-evaluation';
agentEvaluationComparisonProtocolControls($kit);
agentEvaluationComparisonSourceContextBoundsControls();
agentEvaluationComparisonSharedReferenceControls();
agentEvaluationComparisonInstrumentationControls($kit);
agentEvaluationComparisonRouteControls($kit);
$tasks = agentEvaluationValidateKit($kit);
agentEvaluationExplanationContractControls($kit);

agentEvaluationTest(
    count($tasks) === 1 && $tasks[0]['id'] === 'change.simple-ping',
    'The v1 kit must expose exactly the explicit public smoke task.',
);
agentEvaluationComparisonTaskControls($kit);
agentEvaluationComparisonRecordControls($kit);

$task = agentEvaluationTask($kit, 'change.simple-ping');
$sourceFixtureHash = agentEvaluationSourceFixtureHash($root . '/skeleton');
agentEvaluationTest(
    $sourceFixtureHash === $task['base']['fixture_sha256'],
    'The pinned source-skeleton fixture digest changed without a task revision.',
);
$syntheticDependenciesManifest = '100644 '
    . hash('sha256', "synthetic dependency\n")
    . " vendor/example.php\n";
$prompt = file_get_contents($task['directory'] . '/' . $task['prompt']['path']);
$scorer = file_get_contents($task['directory'] . '/' . $task['public_scorer']['path']);

agentEvaluationTest(is_string($prompt) && str_contains($prompt, 'GET /ping'), 'The frozen task prompt changed.');
agentEvaluationTest(is_string($scorer), 'The public scorer fixture is unreadable.');

if (is_string($scorer)) {
    $scorerTokens = token_get_all($scorer, TOKEN_PARSE);
    agentEvaluationTest($scorerTokens !== [], 'The public scorer fixture must contain parseable PHP tokens.');
}

$runRecord = [
    'schema_version' => 1,
    'run_id' => 'synthetic-run-001',
    'task_id' => $task['id'],
    'task_revision' => $task['revision'],
    'task_manifest_sha256' => $task['manifest_sha256'],
    'rubric_sha256' => $task['rubric']['sha256'],
    'base_revision' => str_repeat('b', 40),
    'base_fixture_sha256' => $task['base']['fixture_sha256'],
    'prepared_dependencies_manifest_path' => 'prepared-dependencies.manifest',
    'prepared_dependencies_manifest_sha256' => hash('sha256', $syntheticDependenciesManifest),
    'condition' => 'repository-only',
    'model' => [
        'provider' => 'synthetic',
        'id' => 'fixture-model',
        'revision' => null,
        'settings' => ['temperature' => 0],
    ],
    'context' => [
        'bundle_id' => null,
        'bundle_sha256' => null,
    ],
    'tools' => [
        [
            'name' => 'shell',
            'version' => null,
            'permissions' => ['workspace-read', 'workspace-write'],
        ],
    ],
    'budgets' => [
        'model_tokens' => 1_000_000,
        'wall_seconds' => 1_200,
        'repair_turns' => 1,
        'command_output_bytes' => 4_194_304,
    ],
    'usage' => [
        'input_tokens' => null,
        'output_tokens' => null,
        'cached_tokens' => null,
        'reasoning_tokens' => null,
    ],
    'timing' => [
        'started_at' => '2026-08-09T16:00:00Z',
        'finished_at' => '2026-08-09T16:10:00Z',
    ],
    'repair_turns' => 0,
    'termination_reason' => 'completed',
    'events_path' => 'events.jsonl',
    'events_sha256' => hash('sha256', "{\"event\":\"synthetic\"}\n"),
    'candidate_patch_path' => 'candidate.patch',
    'candidate_patch_sha256' => hash('sha256', "synthetic patch\n"),
];
agentEvaluationValidateRunRecord($runRecord, $task);

$runRecordHash = str_repeat('d', 64);
$scoreRecord = [
    'schema_version' => 1,
    'run_id' => $runRecord['run_id'],
    'task_id' => $task['id'],
    'task_revision' => $task['revision'],
    'run_record_sha256' => $runRecordHash,
    'prompt_sha256' => $task['prompt']['sha256'],
    'scorer_sha256' => $task['public_scorer']['sha256'],
    'candidate_patch_sha256' => $runRecord['candidate_patch_sha256'],
    'admissible' => true,
    'mandatory_checks' => [
        'manifest_valid' => true,
        'application_check' => true,
        'public_scorer' => true,
        'resource_bounds' => true,
        'workspace_policy' => true,
    ],
    'dimensions' => [
        'observable_behavior' => 100,
        'boundary_behavior' => 100,
        'resource_bounds' => 100,
        'application_gate' => 100,
        'change_locality' => 100,
    ],
    'weighted_score' => 100,
    'automated_status' => 'pass',
    'human_review' => 'pending',
    'notes' => ['Synthetic validator control only.'],
];
agentEvaluationValidateScoreRecord($scoreRecord, $task, $runRecord, $runRecordHash);

$wrongRevision = $runRecord;
$wrongRevision['task_revision'] = $task['revision'] + 1;
agentEvaluationExpectFailure(
    static function () use ($wrongRevision, $task): void {
        agentEvaluationValidateRunRecord($wrongRevision, $task);
    },
    'Run record task revision does not match the selected task.',
);

$wrongBaseFixture = $runRecord;
$wrongBaseFixture['base_fixture_sha256'] = str_repeat('f', 64);
agentEvaluationExpectFailure(
    static function () use ($wrongBaseFixture, $task): void {
        agentEvaluationValidateRunRecord($wrongBaseFixture, $task);
    },
    'Run record base fixture hash does not match the selected task revision.',
);

$wrongBudget = $runRecord;
$wrongBudget['budgets']['wall_seconds'] = 1_201;
agentEvaluationExpectFailure(
    static function () use ($wrongBudget, $task): void {
        agentEvaluationValidateRunRecord($wrongBudget, $task);
    },
    'Run record budgets do not match the selected task.',
);

$priorBudget = $runRecord;
$priorBudget['budgets']['model_tokens'] = 200_000;
agentEvaluationExpectFailure(
    static function () use ($priorBudget, $task): void {
        agentEvaluationValidateRunRecord($priorBudget, $task);
    },
    'Run record budgets do not match the selected task.',
);

$excessRepair = $runRecord;
$excessRepair['repair_turns'] = 2;
agentEvaluationExpectFailure(
    static function () use ($excessRepair, $task): void {
        agentEvaluationValidateRunRecord($excessRepair, $task);
    },
    'Run record repair turns exceed the task budget.',
);

$nonCanonicalTime = $runRecord;
$nonCanonicalTime['timing']['started_at'] = '2026-08-10T00:00:00+08:00';
agentEvaluationExpectFailure(
    static function () use ($nonCanonicalTime, $task): void {
        agentEvaluationValidateRunRecord($nonCanonicalTime, $task);
    },
    'Run record timing field started_at must use canonical UTC seconds.',
);

$excessUsage = $runRecord;
$excessUsage['usage']['input_tokens'] = 1_000_001;
agentEvaluationExpectFailure(
    static function () use ($excessUsage, $task): void {
        agentEvaluationValidateRunRecord($excessUsage, $task);
    },
    'Run record usage field input_tokens exceeds the task model-token budget.',
);

$emptyArraySettings = $runRecord;
$emptyArraySettings['model']['settings'] = [];
agentEvaluationExpectFailure(
    static function () use ($emptyArraySettings, $task): void {
        agentEvaluationValidateRunRecord($emptyArraySettings, $task);
    },
    'run record model field settings must be a JSON object.',
);

$duplicateTools = $runRecord;
$duplicateTools['tools'][] = $duplicateTools['tools'][0];
$duplicateTools['tools'][1]['permissions'] = ['workspace-read'];
agentEvaluationExpectFailure(
    static function () use ($duplicateTools, $task): void {
        agentEvaluationValidateRunRecord($duplicateTools, $task);
    },
    'Run record tools must use unique name and version identities.',
);

$excessTotalUsage = $runRecord;
$excessTotalUsage['usage']['input_tokens'] = 500_000;
$excessTotalUsage['usage']['output_tokens'] = 500_001;
agentEvaluationExpectFailure(
    static function () use ($excessTotalUsage, $task): void {
        agentEvaluationValidateRunRecord($excessTotalUsage, $task);
    },
    'Run record reported input and output tokens exceed the total task model-token budget.',
);

$hiddenFailure = $scoreRecord;
$hiddenFailure['mandatory_checks']['public_scorer'] = false;
agentEvaluationExpectFailure(
    static function () use ($hiddenFailure, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($hiddenFailure, $task, $runRecord, $runRecordHash);
    },
    'A failed public scorer cannot retain complete observable and boundary dimensions.',
);

$inadmissibleManifest = $scoreRecord;
$inadmissibleManifest['mandatory_checks']['manifest_valid'] = false;
$inadmissibleManifest['automated_status'] = 'fail';
agentEvaluationExpectFailure(
    static function () use ($inadmissibleManifest, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($inadmissibleManifest, $task, $runRecord, $runRecordHash);
    },
    'An admissible score requires valid manifests and workspace policy.',
);

$wrongRunHash = $scoreRecord;
$wrongRunHash['run_record_sha256'] = str_repeat('e', 64);
agentEvaluationExpectFailure(
    static function () use ($wrongRunHash, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($wrongRunHash, $task, $runRecord, $runRecordHash);
    },
    'Score record run-record hash does not match the validated run record.',
);

$wrongRunId = $scoreRecord;
$wrongRunId['run_id'] = 'different-run';
agentEvaluationExpectFailure(
    static function () use ($wrongRunId, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($wrongRunId, $task, $runRecord, $runRecordHash);
    },
    'Score record run ID does not match the validated run record.',
);

$wrongScorerHash = $scoreRecord;
$wrongScorerHash['scorer_sha256'] = str_repeat('e', 64);
agentEvaluationExpectFailure(
    static function () use ($wrongScorerHash, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($wrongScorerHash, $task, $runRecord, $runRecordHash);
    },
    'Score record scorer hash does not match the selected task.',
);

$wrongScorePatchHash = $scoreRecord;
$wrongScorePatchHash['candidate_patch_sha256'] = str_repeat('e', 64);
agentEvaluationExpectFailure(
    static function () use ($wrongScorePatchHash, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($wrongScorePatchHash, $task, $runRecord, $runRecordHash);
    },
    'Score record candidate patch hash does not match the validated run record.',
);

$inventedWeight = $scoreRecord;
$inventedWeight['weighted_score'] = 99;
agentEvaluationExpectFailure(
    static function () use ($inventedWeight, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($inventedWeight, $task, $runRecord, $runRecordHash);
    },
    'Weighted score does not match the fixed evaluation dimensions.',
);

agentEvaluationExpectFailure(
    static function (): void {
        agentEvaluationRequireRelativePath("events\n.jsonl", 'synthetic path');
    },
    'synthetic path must be one normalized relative path.',
);

agentEvaluationExpectFailure(
    static function (): void {
        agentEvaluationValidateWorkspacePolicy(
            [
                'allowed_existing_paths' => ['src'],
                'allowed_new_paths' => ['tests/new.php'],
                'protected_paths' => ['src/Routes.php'],
                'max_changed_files' => 2,
                'max_added_lines' => 10,
                'max_deleted_lines' => 10,
            ],
            'synthetic.overlap',
        );
    },
    'Task synthetic.overlap cannot overlap permitted and protected paths.',
);

agentEvaluationExpectFailure(
    static function (): void {
        agentEvaluationValidateWorkspacePolicy(
            [
                'allowed_existing_paths' => ['src/Same.php'],
                'allowed_new_paths' => ['src/Same.php'],
                'protected_paths' => ['vendor'],
                'max_changed_files' => 1,
                'max_added_lines' => 10,
                'max_deleted_lines' => 10,
            ],
            'synthetic.same-path',
        );
    },
    'Task synthetic.same-path cannot overlap existing and new permitted paths.',
);

$missingMandatoryCheck = $scoreRecord;
unset($missingMandatoryCheck['mandatory_checks']['manifest_valid']);
agentEvaluationExpectFailure(
    static function () use ($missingMandatoryCheck, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($missingMandatoryCheck, $task, $runRecord, $runRecordHash);
    },
    'score record mandatory checks must contain exactly: application_check, manifest_valid, public_scorer, resource_bounds, workspace_policy.',
);

$zeroResourceBounds = $scoreRecord;
$zeroResourceBounds['dimensions']['resource_bounds'] = 0;
$zeroResourceBounds['weighted_score'] = 85;
agentEvaluationExpectFailure(
    static function () use ($zeroResourceBounds, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($zeroResourceBounds, $task, $runRecord, $runRecordHash);
    },
    'A successful resource-bound check requires the complete resource dimension.',
);

$falsePerfectScore = $scoreRecord;
$falsePerfectScore['automated_status'] = 'fail';
agentEvaluationExpectFailure(
    static function () use ($falsePerfectScore, $task, $runRecord, $runRecordHash): void {
        agentEvaluationValidateScoreRecord($falsePerfectScore, $task, $runRecord, $runRecordHash);
    },
    'Automated status does not match the admissibility, mandatory checks, and critical dimensions.',
);

agentEvaluationExpectFailure(
    static function (): void {
        agentEvaluationRequireArgumentCount(['agent-evaluation.php', 'validate', 'extra'], 2, 'validate');
    },
    'validate received an unexpected number of arguments.',
);

$temporaryKit = sys_get_temp_dir() . '/phpthis-agent-evaluation-' . bin2hex(random_bytes(8));

try {
    agentEvaluationCopyDirectory($kit, $temporaryKit);
    agentEvaluationComparisonCopiedKitControls($temporaryKit);
    $eventsPath = $temporaryKit . '/events.jsonl';
    $candidatePatchPath = $temporaryKit . '/candidate.patch';
    $dependenciesManifestPath = $temporaryKit . '/prepared-dependencies.manifest';

    if (
        file_put_contents($eventsPath, "{\"event\":\"synthetic\"}\n") === false
        || file_put_contents($candidatePatchPath, "synthetic patch\n") === false
        || file_put_contents($dependenciesManifestPath, $syntheticDependenciesManifest) === false
    ) {
        throw new RuntimeException('Unable to create synthetic run artifacts.');
    }

    agentEvaluationValidateRunArtifacts($runRecord, $temporaryKit);

    $sameArtifact = $runRecord;
    $sameArtifact['candidate_patch_path'] = $sameArtifact['events_path'];
    $sameArtifact['candidate_patch_sha256'] = $sameArtifact['events_sha256'];
    agentEvaluationExpectFailure(
        static function () use ($sameArtifact, $temporaryKit): void {
            agentEvaluationValidateRunArtifacts($sameArtifact, $temporaryKit);
        },
        'Run events, candidate patch, and dependency manifest must use distinct artifact paths.',
    );

    $hardLinkPath = $temporaryKit . '/events-alias.jsonl';

    if (!link($eventsPath, $hardLinkPath)) {
        throw new RuntimeException('Unable to create the hard-link artifact control.');
    }

    try {
        $hardLinkedArtifact = $runRecord;
        $hardLinkedArtifact['candidate_patch_path'] = 'events-alias.jsonl';
        $hardLinkedArtifact['candidate_patch_sha256'] = $hardLinkedArtifact['events_sha256'];
        agentEvaluationExpectFailure(
            static function () use ($hardLinkedArtifact, $temporaryKit): void {
                agentEvaluationValidateRunArtifacts($hardLinkedArtifact, $temporaryKit);
            },
            'Run artifacts must not use hard-linked files.',
        );
    } finally {
        if (!unlink($hardLinkPath)) {
            throw new RuntimeException('Unable to remove the hard-link artifact control.');
        }
    }

    $malformedDependenciesPath = $temporaryKit . '/malformed-dependencies.manifest';

    if (file_put_contents($malformedDependenciesPath, "not a dependency manifest\n") === false) {
        throw new RuntimeException('Unable to create the malformed dependency-manifest control.');
    }

    $malformedDependencies = $runRecord;
    $malformedDependencies['prepared_dependencies_manifest_path'] = 'malformed-dependencies.manifest';
    $malformedDependencies['prepared_dependencies_manifest_sha256'] = hash(
        'sha256',
        "not a dependency manifest\n",
    );
    agentEvaluationExpectFailure(
        static function () use ($malformedDependencies, $temporaryKit): void {
            agentEvaluationValidateRunArtifacts($malformedDependencies, $temporaryKit);
        },
        'Prepared-dependencies manifest has an invalid line.',
    );

    $wrongArtifactHash = $runRecord;
    $wrongArtifactHash['candidate_patch_sha256'] = str_repeat('f', 64);
    agentEvaluationExpectFailure(
        static function () use ($wrongArtifactHash, $temporaryKit): void {
            agentEvaluationValidateRunArtifacts($wrongArtifactHash, $temporaryKit);
        },
        'candidate patch artifact SHA-256 does not match its recorded hash.',
    );

    $outsideArtifact = $temporaryKit . '-outside.patch';
    $escapeLink = $temporaryKit . '/escape';

    if (
        file_put_contents($outsideArtifact, "outside\n") === false
        || !symlink(dirname($outsideArtifact), $escapeLink)
    ) {
        throw new RuntimeException('Unable to create the artifact-containment control.');
    }

    try {
        $escapedArtifact = $runRecord;
        $escapedArtifact['events_path'] = 'escape/' . basename($outsideArtifact);
        $escapedArtifact['events_sha256'] = hash('sha256', "outside\n");
        agentEvaluationExpectFailure(
            static function () use ($escapedArtifact, $temporaryKit): void {
                agentEvaluationValidateRunArtifacts($escapedArtifact, $temporaryKit);
            },
            'run events artifact must remain inside the run artifact root.',
        );
    } finally {
        if (!unlink($escapeLink) || !unlink($outsideArtifact)) {
            throw new RuntimeException('Unable to remove the artifact-containment control.');
        }
    }

    $duplicateJsonPath = $temporaryKit . '/duplicate.json';

    if (file_put_contents($duplicateJsonPath, "{\"run_id\":\"first\",\"run_id\":\"second\"}\n") === false) {
        throw new RuntimeException('Unable to create the duplicate-name JSON control.');
    }

    agentEvaluationExpectFailure(
        static function () use ($duplicateJsonPath): void {
            agentEvaluationJsonFile($duplicateJsonPath);
        },
        'JSON input contains a duplicate object name.',
    );

    $numericSettingPath = $temporaryKit . '/numeric-setting.json';

    if (file_put_contents($numericSettingPath, "{\"settings\":{\"0\":\"exact\"}}\n") === false) {
        throw new RuntimeException('Unable to create the numeric-setting JSON control.');
    }

    $numericSetting = agentEvaluationJsonFile($numericSettingPath);
    agentEvaluationRequireJsonObjectValue(
        $numericSetting['settings'] ?? null,
        'numeric-setting JSON control',
    );

    $schemaPath = $temporaryKit . '/schema/task.schema.json';
    $originalSchema = file_get_contents($schemaPath);

    if (!is_string($originalSchema) || file_put_contents($schemaPath, $originalSchema . "\n") === false) {
        throw new RuntimeException('Unable to mutate copied schema control.');
    }

    agentEvaluationExpectFailure(
        static function () use ($temporaryKit): void {
            agentEvaluationValidateKit($temporaryKit);
        },
        'schema/task.schema.json SHA-256 does not match its recorded hash.',
    );

    if (file_put_contents($schemaPath, $originalSchema) === false) {
        throw new RuntimeException('Unable to restore copied schema control.');
    }

    $mutatedPrompt = $temporaryKit . '/tasks/change.simple-ping/prompt.md';
    $originalPrompt = file_get_contents($mutatedPrompt);

    if (!is_string($originalPrompt)) {
        throw new RuntimeException('Unable to read copied prompt control.');
    }

    if (file_put_contents($mutatedPrompt, $originalPrompt . "\nUnreviewed mutation.\n") === false) {
        throw new RuntimeException('Unable to mutate copied prompt control.');
    }

    agentEvaluationExpectFailure(
        static function () use ($temporaryKit): void {
            agentEvaluationValidateKit($temporaryKit);
        },
        'task change.simple-ping prompt SHA-256 does not match its recorded hash.',
    );

    $manifestPath = $temporaryKit . '/tasks/change.simple-ping/task.json';
    $manifestSource = file_get_contents($manifestPath);

    if (!is_string($manifestSource)) {
        throw new RuntimeException('Unable to read the copied task-manifest control.');
    }

    $manifest = json_decode($manifestSource, true, AGENT_EVALUATION_MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);

    if (!is_array($manifest) || !is_array($manifest['prompt'] ?? null)) {
        throw new RuntimeException('Copied task-manifest control has an invalid shape.');
    }

    $mutatedPromptHash = hash_file('sha256', $mutatedPrompt);

    if (!is_string($mutatedPromptHash)) {
        throw new RuntimeException('Unable to hash the mutated prompt control.');
    }

    $manifest['prompt']['sha256'] = $mutatedPromptHash;

    if (file_put_contents($manifestPath, agentEvaluationJson($manifest)) === false) {
        throw new RuntimeException('Unable to align the copied manifest with the mutated prompt.');
    }

    agentEvaluationExpectFailure(
        static function () use ($temporaryKit): void {
            agentEvaluationValidateKit($temporaryKit);
        },
        'Task change.simple-ping manifest SHA-256 does not match its pinned revision.',
    );
} finally {
    agentEvaluationRemoveDirectory($temporaryKit);
}

fwrite(
    STDOUT,
    "PASS agent evaluation kit self-test: inventory, hashes, run records, score gates, and negative controls\n",
);

function agentEvaluationTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param Closure(): void $callback */
function agentEvaluationExpectFailure(Closure $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        agentEvaluationTest($exception->getMessage() === $message, 'Unexpected validator failure: ' . $exception->getMessage());

        return;
    }

    throw new RuntimeException('Expected validator failure was not reported.');
}

function agentEvaluationCopyDirectory(string $source, string $target): void
{
    if (!is_dir($source) || file_exists($target) || !mkdir($target, 0700, true)) {
        throw new RuntimeException('Unable to create the agent-evaluation fixture copy.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || $entry->isLink()) {
            throw new RuntimeException('Agent-evaluation fixtures must contain no symlink.');
        }

        $relative = substr($entry->getPathname(), strlen($source) + 1);

        if ($relative === '') {
            throw new RuntimeException('Unable to determine an agent-evaluation fixture path.');
        }

        $destination = $target . '/' . $relative;

        if ($entry->isDir()) {
            if (!mkdir($destination, 0700)) {
                throw new RuntimeException('Unable to copy an agent-evaluation fixture directory.');
            }

            continue;
        }

        if (!$entry->isFile() || !copy($entry->getPathname(), $destination)) {
            throw new RuntimeException('Unable to copy an agent-evaluation fixture file.');
        }
    }
}

function agentEvaluationRemoveDirectory(string $directory): void
{
    $prefix = sys_get_temp_dir() . '/phpthis-agent-evaluation-';

    if (!str_starts_with($directory, $prefix) || !is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || $entry->isLink()) {
            throw new RuntimeException('Temporary agent-evaluation cleanup encountered a symlink.');
        }

        if ($entry->isDir()) {
            if (!rmdir($entry->getPathname())) {
                throw new RuntimeException('Unable to remove a temporary agent-evaluation directory.');
            }

            continue;
        }

        if (!$entry->isFile() || !unlink($entry->getPathname())) {
            throw new RuntimeException('Unable to remove a temporary agent-evaluation file.');
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove the temporary agent-evaluation root.');
    }
}

function agentEvaluationSourceFixtureHash(string $directory): string
{
    return hash('sha256', agentEvaluationSourceFixtureManifest($directory));
}

function agentEvaluationSourceFixtureManifest(string $directory): string
{
    if (!is_dir($directory) || is_link($directory)) {
        throw new RuntimeException('The source-skeleton fixture must be one real directory.');
    }

    $lines = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || !$entry->isFile() || $entry->isLink()) {
            throw new RuntimeException('The source-skeleton fixture must contain only regular files and directories.');
        }

        $relative = substr($entry->getPathname(), strlen($directory) + 1);
        $hash = hash_file('sha256', $entry->getPathname());

        if ($relative === '' || !is_string($hash)) {
            throw new RuntimeException('Unable to describe one source-skeleton fixture file.');
        }

        $relative = agentEvaluationRequireRelativePath($relative, 'source-skeleton fixture path');
        $mode = $entry->isExecutable() ? '100755' : '100644';
        $lines[] = "{$mode} {$hash} {$relative}";
    }

    sort($lines, SORT_STRING);

    return implode("\n", $lines) . "\n";
}

function agentEvaluationPinnedExplanationSourceManifest(string $repositoryRoot): string
{
    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-explanation-source-' . bin2hex(random_bytes(8));
    $index = $temporary . '/index';
    $source = $temporary . '/source';

    if (!mkdir($temporary, 0700) || !mkdir($source, 0700)) {
        throw new RuntimeException('Unable to create the pinned explanation source fixture control.');
    }

    try {
        $readTree = runBoundedMaintainerProcess(
            [
                '/usr/bin/git',
                'read-tree',
                '--index-output=' . $index,
                AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION,
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );

        if ($readTree['exit_code'] !== 0 || $readTree['stdout'] !== '' || $readTree['stderr'] !== '') {
            throw new RuntimeException('Unable to read the pinned explanation source tree.');
        }

        $checkout = runBoundedMaintainerProcess(
            [
                '/usr/bin/env',
                'GIT_INDEX_FILE=' . $index,
                '/usr/bin/git',
                'checkout-index',
                '--all',
                '--prefix=' . $source . '/',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );

        if ($checkout['exit_code'] !== 0 || $checkout['stdout'] !== '' || $checkout['stderr'] !== '') {
            throw new RuntimeException('Unable to materialize the pinned explanation source tree.');
        }

        $manifest = agentEvaluationSourceFixtureManifest($source);

        if (hash('sha256', $manifest) !== AGENT_EVALUATION_EXPLANATION_SOURCE_FIXTURE_SHA256) {
            throw new RuntimeException('The materialized explanation source tree does not match its pinned fixture.');
        }

        return $manifest;
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
}

/**
 * @param array<string, mixed> $scoreRecord
 * @return array<string, mixed>
 */
function agentEvaluationRefreshExplanationStructuralScore(
    string $artifactRoot,
    string $runBytes,
    array $scoreRecord,
): array {
    $document = agentEvaluationExplanationStructuralEvidenceDocument('live-model', $artifactRoot);
    $documentBytes = agentEvaluationJson($document);
    $scoreRecord['run_record_sha256'] = hash('sha256', $runBytes);
    $scoreRecord['structural_evidence_sha256'] = hash('sha256', $documentBytes);

    if (
        file_put_contents($artifactRoot . '/structural-evidence.json', $documentBytes) === false
        || file_put_contents($artifactRoot . '/score.json', agentEvaluationJson($scoreRecord)) === false
    ) {
        throw new RuntimeException('Unable to refresh explanation structural-evidence controls.');
    }

    return $scoreRecord;
}

/**
 * @param array<string, mixed> $runRecord
 * @param array<string, mixed> $scoreRecord
 * @return array<string, mixed>
 */
function agentEvaluationWriteExplanationOuterEvidence(
    string $artifactRoot,
    array $runRecord,
    array $scoreRecord,
): array {
    $executionKind = agentEvaluationRequireString($runRecord, 'execution_kind', 'explanation run fixture');
    $validation = [
        'v3_run_record' => 'pass',
        'v3_score_record' => 'pass',
        'structural_status' => agentEvaluationRequireString(
            $scoreRecord,
            'automated_status',
            'explanation score fixture',
        ),
        'semantic_review' => 'pending',
    ];

    if (file_put_contents($artifactRoot . '/validation.json', agentEvaluationJson($validation)) === false) {
        throw new RuntimeException('Unable to write explanation validation fixture.');
    }

    $names = agentEvaluationExplanationOuterEvidencePaths($executionKind);
    if ($executionKind === 'live-model' && is_file($artifactRoot . '/owned-resources.json')) {
        $names[] = 'owned-resources.json';
        sort($names, SORT_STRING);
    }
    $pendingScoreBytes = agentEvaluationJson(agentEvaluationExplanationPendingScore($scoreRecord));
    $artifacts = [];

    foreach ($names as $name) {
        $path = $artifactRoot . '/' . $name;
        $bytes = $name === 'score.json' ? $pendingScoreBytes : file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException("Unable to read explanation outer fixture {$name}.");
        }
        $artifacts[$name] = ['bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }

    $cleanup = agentEvaluationJsonFile($artifactRoot . '/cleanup.json');
    $phases = ['prepare', 'generate', 'freeze', 'score', 'validate', 'retain', 'cleanup'];
    $manifest = [
        'schema_version' => 1,
        'controller_version' => 2,
        'run_id' => agentEvaluationRequireString($runRecord, 'run_id', 'explanation run fixture'),
        'task_id' => agentEvaluationRequireString($runRecord, 'task_id', 'explanation run fixture'),
        'task_revision' => agentEvaluationRequireInteger(
            $runRecord,
            'task_revision',
            'explanation run fixture',
        ),
        'synthetic' => $executionKind === 'synthetic-control',
        'comparative_claims' => false,
        'explanation_execution' => true,
        'condition' => 'repository-only',
        'expected_phase_order' => $phases,
        'observed_phases' => $phases,
        'primary_failure' => $cleanup['primary_failure'] ?? null,
        'cleanup_failure' => $cleanup['cleanup_failure'] ?? null,
        'artifacts' => $artifacts,
    ];

    if (file_put_contents($artifactRoot . '/evidence-manifest.json', agentEvaluationJson($manifest)) === false) {
        throw new RuntimeException('Unable to write explanation evidence-manifest fixture.');
    }

    return $manifest;
}

function agentEvaluationExplanationEntrypointReadControls(string $kit): void
{
    $directory = sys_get_temp_dir() . '/phpthis-explanation-entrypoints-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Unable to create entrypoint-read control directory.');
    }
    try {
        if (!mkdir($directory . '/.ai', 0700)) {
            throw new RuntimeException('Unable to create entrypoint-read guide directory.');
        }
        $expected = '';
        foreach (['VISION.md', '.ai/README.md', '.ai/rules.md', '.ai/change-workflow.md', '.ai/strict-profile.md'] as $path) {
            $source = runBoundedMaintainerProcess(
                ['/usr/bin/git', 'show', AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION . ':' . $path],
                dirname($kit, 2), null, 5_000, 32_768, 4_096,
            );
            agentEvaluationTest($source['exit_code'] === 0 && $source['stderr'] === '',
                'The entrypoint-read control must use each exact pinned file.');
            if (file_put_contents($directory . '/' . $path, $source['stdout']) !== strlen($source['stdout'])) {
                throw new RuntimeException('Unable to write entrypoint-read control.');
            }
            $expected .= $source['stdout'];
            $section = runBoundedMaintainerProcess(
                ['python3', '-I', '-B', '-c', AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON, $path, '1', '120'],
                $directory, null, 5_000, 16_384, 4_096,
            );
            agentEvaluationTest($section['exit_code'] !== 0 && $section['stdout'] === '',
                'The current section reader must reject former 1-120 entrypoint reads without partial source.');
        }
        $batch = runBoundedMaintainerProcess(
            ['/bin/sh', '-c', AGENT_EVALUATION_EXPLANATION_ENTRYPOINT_COMMAND],
            $directory, null, 5_000, 32_768, 4_096,
        );
        agentEvaluationTest($batch['exit_code'] === 0 && $batch['stderr'] === '' && $batch['stdout'] === $expected,
            'The exact prompted entrypoint command must read all five complete files in order in one invocation.');
    } finally {
        agentEvaluationRemoveDirectory($directory);
    }
}

function agentEvaluationExplanationBoundedReadControls(string $kit): void
{
    $directory = sys_get_temp_dir() . '/phpthis-explanation-read-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Unable to create bounded-read control directory.');
    }
    try {
        $guide = runBoundedMaintainerProcess(
            ['/usr/bin/git', 'show', AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION . ':.ai/file-transfers.md'],
            dirname($kit, 2), null, 5_000, 32_768, 4_096,
        );
        agentEvaluationTest($guide['exit_code'] === 0 && $guide['stderr'] === '',
            'The read command control must use the exact pinned guide.');
        $router = runBoundedMaintainerProcess(
            ['/usr/bin/git', 'show', AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION . ':docs/file-transfers/README.md'],
            dirname($kit, 2), null, 5_000, 32_768, 4_096,
        );
        $referenceTest = runBoundedMaintainerProcess(
            ['/usr/bin/git', 'cat-file', '-e',
                AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION . ':tools/test-consumer-project/amazon-s3-file-transfers.php'],
            dirname($kit, 2), null, 5_000, 4_096, 4_096,
        );
        $verification = runBoundedMaintainerProcess(
            ['/usr/bin/git', 'show', AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION
                . ':docs/file-transfers/amazon-s3-verification.md'],
            dirname($kit, 2), null, 5_000, 131_072, 4_096,
        );
        agentEvaluationTest(
            $router['exit_code'] === 0 && $router['stderr'] === ''
                && $referenceTest['exit_code'] === 0 && $referenceTest['stdout'] === ''
                && $referenceTest['stderr'] === ''
                && $verification['exit_code'] === 0 && $verification['stderr'] === ''
                && str_contains($guide['stdout'], '`tools/test-consumer-project/amazon-s3-file-transfers.php`')
                && str_contains($router['stdout'], '`tools/test-consumer-project/amazon-s3-file-transfers.php`')
                && str_contains($guide['stdout'], 'consumer')
                && str_contains($verification['stdout'], 'Copy this exact source to `tools/verify-amazon-s3-file-transfer-source.php`.'),
            'The pinned file-transfer route must resolve the synthetic reference test and consumer-owned checker template.',
        );
        $guidePath = $directory . '/pinned-guide.md';
        if (file_put_contents($guidePath, $guide['stdout']) !== strlen($guide['stdout'])) {
            throw new RuntimeException('Unable to write the pinned guide window control.');
        }
        $guideWindows = '';
        foreach ([[1, 20], [21, 40], [41, 60], [61, 80]] as [$start, $end]) {
            $window = runBoundedMaintainerProcess(
                ['python3', '-I', '-B', '-c', AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON,
                    $guidePath, (string) $start, (string) $end],
                $directory, null, 5_000, 16_384, 4_096,
            );
            agentEvaluationTest(
                $window['exit_code'] === 0 && $window['stderr'] === ''
                    && $window['stdout'] !== '' && strlen($window['stdout']) <= 8192,
                'Each 20-line guide window must fit the existing byte cap and return complete source.',
            );
            $guideWindows .= $window['stdout'];
        }
        agentEvaluationTest($guideWindows === $guide['stdout'],
            'The ordered 20-line windows must reconstruct the exact pinned guide.');
        $guideLines = explode("\n", $guide['stdout']);
        $s3Window = implode("\n", array_slice($guideLines, 65, 5)) . "\n";
        $s3Section = implode("\n", array_slice($guideLines, 65, -1)) . "\n";
        $controls = [
            [$guide['stdout'], 1, 70, null],
            [$guide['stdout'], 66, 70, $s3Window],
            [str_repeat('x', 8192), 1, 1, str_repeat('x', 8192)],
            [str_repeat('x', 8193), 1, 1, null],
            [str_repeat('é', 4096), 1, 1, str_repeat('é', 4096)],
            [str_repeat('é', 4097), 1, 1, null],
            [str_repeat("x\n", 21), 1, 21, null],
            [str_repeat("x\n", 21), 1, 20, str_repeat("x\n", 20)],
            [str_repeat("x\n", 21), 2, 21, str_repeat("x\n", 20)],
            ["first\r\nlast", 1, 2, "first\r\nlast"],
            ["\xFF", 1, 1, null],
            ["line\n", 0, 1, null],
            ["line\n", 2, 1, null],
            ["line\n", 1, 2, "line\n"],
            ["line\n", 1, 20, "line\n"],
            ["line\n", 1, 21, null],
            ["line\n", 2, 2, null],
            ['', 1, 20, null],
            [$guide['stdout'], 1, 120, null],
            [$guide['stdout'], 66, 85, $s3Section],
            ["first\r\nlast", 2, 20, 'last'],
            ["\xFF", 1, 20, null],
        ];
        foreach ($controls as [$bytes, $start, $end, $expected]) {
            $file = $directory . '/selected document.md';
            if (file_put_contents($file, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Unable to write bounded-read control.');
            }
            $read = runBoundedMaintainerProcess(
                ['python3', '-I', '-B', '-c', AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON, $file, (string) $start, (string) $end],
                $directory, null, 5_000, 16_384, 4_096,
            );
            agentEvaluationTest($expected === null
                ? ($read['exit_code'] !== 0 && $read['stdout'] === '')
                : ($read['exit_code'] === 0 && $read['stderr'] === '' && $read['stdout'] === $expected
                    && strlen($read['stdout']) <= 8192),
                'The exact prompted reader must preserve complete UTF-8/line bytes or fail without emitting source.');
        }
        foreach ([
            ['docs/file-transfers/amazon-s3.md', 157, 260],
            ['docs/file-transfers/amazon-s3-verification.md', 20, 82],
            ['docs/file-transfers/amazon-s3.md', 201, 260],
        ] as [$path, $start, $end]) {
            $source = runBoundedMaintainerProcess(
                ['/usr/bin/git', 'show', AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION . ':' . $path],
                dirname($kit, 2), null, 5_000, 131_072, 4_096,
            );
            agentEvaluationTest($source['exit_code'] === 0 && $source['stderr'] === '',
                'The overwide-read regression must use the exact pinned linked document.');
            $file = $directory . '/observed-overwide.md';
            if (file_put_contents($file, $source['stdout']) !== strlen($source['stdout'])) {
                throw new RuntimeException('Unable to write observed overwide-read control.');
            }
            $overwide = runBoundedMaintainerProcess(
                ['python3', '-I', '-B', '-c', AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON,
                    $file, (string) $start, (string) $end],
                $directory, null, 5_000, 16_384, 4_096,
            );
            $window = runBoundedMaintainerProcess(
                ['python3', '-I', '-B', '-c', AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON,
                    $file, (string) $start, (string) ($start + 19)],
                $directory, null, 5_000, 16_384, 4_096,
            );
            agentEvaluationTest($overwide['exit_code'] !== 0 && $overwide['stdout'] === ''
                && $window['exit_code'] === 0 && $window['stdout'] !== ''
                && strlen($window['stdout']) <= 8192,
                'Observed overwide linked-document reads must reject without source while a 20-line window succeeds.');
        }
    } finally {
        agentEvaluationRemoveDirectory($directory);
    }
}

function agentEvaluationExplanationBoundedSearchControls(): void
{
    $directory = sys_get_temp_dir() . '/phpthis-explanation-search-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Unable to create bounded-search control directory.');
    }
    try {
        $first = $directory . '/first.md';
        $second = $directory . '/second.md';
        file_put_contents($first, "# First\nordinary text\n## Profile boundary\n");
        file_put_contents($second, "# Second\n## Evidence boundary\n");
        $search = static function (string $pattern, array $paths) use ($directory): array {
            return runBoundedMaintainerProcess(
                ['python3', '-I', '-B', '-c', AGENT_EVALUATION_EXPLANATION_BOUNDED_SEARCH_PYTHON,
                    $pattern, ...$paths],
                $directory, null, 5_000, 16_384, 4_096,
            );
        };
        $headings = $search('^#{1,6} ', [$first, $second]);
        agentEvaluationTest(
            $headings['exit_code'] === 0 && $headings['stderr'] === ''
                && $headings['stdout'] === "{$first}:1:# First\n{$first}:3:## Profile boundary\n"
                    . "{$second}:1:# Second\n{$second}:2:## Evidence boundary\n",
            'The prompted search must return complete headed matches with exact paths and line numbers.',
        );
        $noMatch = $search('not-present', [$first]);
        agentEvaluationTest($noMatch['exit_code'] === 0 && $noMatch['stdout'] === ''
            && $noMatch['stderr'] === '', 'An empty bounded search must be an ordinary result.');
        $snapshotPaths = [];
        foreach ([
            'docs/file-transfers/README.md',
            'docs/file-transfers/amazon-s3.md',
            'docs/file-transfers/amazon-s3-verification.md',
            'docs/consumer-profile.md',
        ] as $index => $path) {
            $source = runBoundedMaintainerProcess(
                ['/usr/bin/git', 'show', AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION . ':' . $path],
                dirname(__DIR__), null, 5_000, 131_072, 4_096,
            );
            agentEvaluationTest($source['exit_code'] === 0 && $source['stderr'] === '',
                'The search regression must use the exact pinned linked documents.');
            $snapshotPath = $directory . '/linked-' . $index . '.md';
            file_put_contents($snapshotPath, $source['stdout']);
            $snapshotPaths[] = $snapshotPath;
        }
        $observedBroadSearch = $search('^#|amazon-s3|local', $snapshotPaths);
        agentEvaluationTest($observedBroadSearch['exit_code'] !== 0
            && $observedBroadSearch['stdout'] === '',
            'The observed broad linked-document search must be rejected without partial source.');
        $snapshotHeadings = $search('^#{1,6} ', $snapshotPaths);
        agentEvaluationTest($snapshotHeadings['exit_code'] === 0
            && $snapshotHeadings['stderr'] === ''
            && str_contains($snapshotHeadings['stdout'], 'Amazon S3')
            && strlen($snapshotHeadings['stdout']) <= 4096,
            'Heading discovery across the same linked documents must remain available and bounded.');
        $exactBytes = $directory . '/exact-bytes.md';
        $prefix = $exactBytes . ':1:';
        file_put_contents($exactBytes, '# ' . str_repeat('x', 4096 - strlen($prefix) - 3) . "\n");
        $byteBoundary = $search('^#', [$exactBytes]);
        agentEvaluationTest($byteBoundary['exit_code'] === 0 && $byteBoundary['stderr'] === ''
            && strlen($byteBoundary['stdout']) === 4096,
            'The bounded search must admit exactly 4,096 complete output bytes.');
        $exactMatches = $directory . '/exact-matches.md';
        file_put_contents($exactMatches, str_repeat("# heading\n", 40));
        $matchBoundary = $search('^#', ['exact-matches.md']);
        agentEvaluationTest($matchBoundary['exit_code'] === 0 && $matchBoundary['stderr'] === ''
            && substr_count($matchBoundary['stdout'], "\n") === 40,
            'The bounded search must admit exactly 40 complete matches.');
        $oversized = $directory . '/oversized.md';
        file_put_contents($oversized, '# ' . str_repeat('x', 4096) . "\n");
        $tooMany = $directory . '/too-many.md';
        file_put_contents($tooMany, str_repeat("# heading\n", 41));
        $invalidUtf8 = $directory . '/invalid.md';
        file_put_contents($invalidUtf8, "# \xFF\n");
        foreach ([
            ['^#', [$oversized]],
            ['^#', ['too-many.md']],
            ['^#', [$invalidUtf8]],
            ['^#', []],
            ['^#', [$first, $second, $first, $second, $first]],
        ] as [$pattern, $paths]) {
            $rejected = $search($pattern, $paths);
            agentEvaluationTest($rejected['exit_code'] !== 0 && $rejected['stdout'] === '',
                'Oversized, invalid, or unscoped searches must fail without emitting partial source.');
        }
    } finally {
        agentEvaluationRemoveDirectory($directory);
    }
}

function agentEvaluationExplanationContractControls(string $kit): void
{
    $task = agentEvaluationExplanationTask($kit);
    agentEvaluationTest(
        str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            'For an unfamiliar guide or linked document, begin with selected windows of at most 20 lines.',
        ) && str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            'Batch independent reads only when their combined output fits 8,192 bytes',
        ) && str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            'For line-based content searches after the entrypoint batch, use the bounded command below',
        ) && str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            AGENT_EVALUATION_EXPLANATION_BOUNDED_SEARCH_PYTHON,
        ) && str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            'For content searches, use one exact file and one specific term.',
        ) && str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            'Inspect concrete execution-path source and its nearest test before answering.',
        ) && str_contains(
            AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX,
            'the next assistant message must be the final answer',
        ),
        'The explanation prompt must bound reads and avoid repeated broad linked-document searches.',
    );
    agentEvaluationTest(
        $task['schema_version'] === 3
        && $task['id'] === AGENT_EVALUATION_EXPLANATION_TASK_ID
        && $task['revision'] === 12
        && $task['kind'] === 'explanation'
        && $task['comparative_claims'] === false,
        'The explanation task must retain its explicit schema-v3 identity.',
    );
    agentEvaluationTest(
        $task['budgets'] === [
            'model_tokens' => 200_000,
            'wall_seconds' => 1_200,
            'repair_turns' => 0,
            'command_output_bytes' => 4_194_304,
        ],
        'The revised explanation task must admit 200,000 cumulative tokens with the other limits fixed.',
    );
    agentEvaluationExplanationEntrypointReadControls($kit);
    agentEvaluationExplanationBoundedReadControls($kit);
    agentEvaluationExplanationBoundedSearchControls();
    agentEvaluationTest(
        $task['base'] === [
            'fixture' => 'tracked-maintainer-source',
            'revision' => AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION,
            'tree' => AGENT_EVALUATION_EXPLANATION_SOURCE_TREE,
            'fixture_sha256' => AGENT_EVALUATION_EXPLANATION_SOURCE_FIXTURE_SHA256,
        ],
        'The explanation task must retain its pinned tracked maintainer source.',
    );
    agentEvaluationTest(
        $task['workspace_policy'] === [
            'allowed_existing_paths' => [],
            'allowed_new_paths' => [],
            'protected_paths' => [],
            'max_changed_files' => 0,
            'max_added_lines' => 0,
            'max_deleted_lines' => 0,
        ],
        'The explanation task must retain its zero-write policy.',
    );
    agentEvaluationTest(
        $task['execution_profile'] === agentEvaluationExplanationLiveExecutionProfile(),
        'The explanation task must retain its exact live execution profile.',
    );
    $sourcePrompt = file_get_contents($task['directory'] . '/' . $task['prompt']['path']);
    agentEvaluationTest(
        $sourcePrompt === "Review whether a consumer may switch its adopted local file profile to Amazon S3.\n",
        'The explanation source prompt changed from the frozen routing-review seed.',
    );
    agentEvaluationTest(
        is_string($sourcePrompt)
        && agentEvaluationExplanationEffectivePrompt($sourcePrompt) === $sourcePrompt
            . "\n"
            . AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX
            . "\n"
        && $task['prompt']['effective_sha256'] === AGENT_EVALUATION_EXPLANATION_EFFECTIVE_PROMPT_SHA256
        && hash('sha256', agentEvaluationExplanationEffectivePrompt($sourcePrompt))
            === $task['prompt']['effective_sha256'],
        'The explanation effective prompt must append the frozen read-only and bounded-reading instructions.',
    );
    agentEvaluationExpectFailure(
        static function (): void {
            agentEvaluationExplanationEffectivePrompt('not newline terminated');
        },
        'Explanation source prompt must be non-empty newline-terminated text without NUL bytes.',
    );
    agentEvaluationExpectFailure(
        static function (): void {
            agentEvaluationValidateWorkspacePolicy(
                [
                    'allowed_existing_paths' => [],
                    'allowed_new_paths' => [],
                    'protected_paths' => [],
                    'max_changed_files' => 0,
                    'max_added_lines' => 0,
                    'max_deleted_lines' => 0,
                ],
                'synthetic.v1-zero',
            );
        },
        'task synthetic.v1-zero workspace policy field allowed_existing_paths must contain at least one path.',
    );
    $writableExplanationPolicy = $task['workspace_policy'];
    $writableExplanationPolicy['allowed_new_paths'] = ['answer.md'];
    agentEvaluationExpectFailure(
        static function () use ($writableExplanationPolicy): void {
            agentEvaluationValidateExplanationWorkspacePolicy(
                $writableExplanationPolicy,
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
            );
        },
        'Explanation workspace policy must prohibit every candidate mutation.',
    );

    $preparedLockBytes = file_get_contents(dirname($kit, 2) . '/composer.lock');
    $preparedInstalledMetadataBytes = file_get_contents(
        dirname($kit, 2) . '/vendor/composer/installed.json',
    );
    if (!is_string($preparedLockBytes) || !is_string($preparedInstalledMetadataBytes)) {
        throw new RuntimeException('Unable to read the pinned explanation Composer provenance.');
    }
    $preparedInstalledPackages = agentEvaluationExplanationInstalledComposerPackages(
        agentEvaluationValueObject(
            agentEvaluationJsonValue(
                $preparedInstalledMetadataBytes,
                'explanation installed Composer test metadata',
            ),
            'explanation installed Composer test metadata',
        ),
    );
    $dependencyManifestLines = [
        '100644 ' . hash('sha256', $preparedInstalledMetadataBytes) . ' composer/installed.json',
    ];
    foreach (array_keys($preparedInstalledPackages) as $packageName) {
        $dependencyManifestLines[] = '100644 '
            . hash('sha256', $packageName . "\n")
            . ' '
            . $packageName
            . '/provenance.fixture';
    }
    sort($dependencyManifestLines, SORT_STRING);
    $dependencies = implode("\n", $dependencyManifestLines) . "\n";
    $responseText = 'The switch is conditional on deliberate profile adoption and consumer evidence.';
    $commandText = 'pwd';
    $events = '{"type":"thread.started","thread_id":"thread-1"}'
        . "\n"
        . '{"type":"turn.started"}'
        . "\n"
        . '{"type":"item.started","item":{"type":"command_execution","id":"command-1","command":"pwd"}}'
        . "\n"
        . '{"type":"item.completed","item":{"type":"command_execution","id":"command-1","command":"pwd"}}'
        . "\n"
        . '{"type":"item.completed","item":{"id":"message-1","type":"agent_message","text":"'
        . $responseText
        . '"}}'
        . "\n"
        . '{"type":"turn.completed","usage":{"input_tokens":1000,"cached_input_tokens":100,'
        . '"output_tokens":500,"reasoning_output_tokens":200}}'
        . "\n";
    $response = $responseText . "\n";
    $run = [
        'schema_version' => 3,
        'execution_kind' => 'live-model',
        'run_id' => '00000000000000000000000000000070',
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'task_manifest_sha256' => $task['manifest_sha256'],
        'prompt_sha256' => $task['prompt']['sha256'],
        'effective_prompt_sha256' => $task['prompt']['effective_sha256'],
        'rubric_sha256' => $task['rubric']['sha256'],
        'base_revision' => $task['base']['revision'],
        'base_tree' => $task['base']['tree'],
        'base_fixture_sha256' => $task['base']['fixture_sha256'],
        'prepared_dependencies_manifest_path' => 'prepared-dependencies.manifest',
        'prepared_dependencies_manifest_sha256' => hash('sha256', $dependencies),
        'prepared_lock_path' => 'dependencies.lock',
        'prepared_lock_sha256' => hash('sha256', $preparedLockBytes),
        'prepared_installed_metadata_path' => 'dependencies.installed.json',
        'prepared_installed_metadata_sha256' => hash('sha256', $preparedInstalledMetadataBytes),
        'prepared_installed_package_count' => count($preparedInstalledPackages),
        'condition' => $task['execution_profile']['condition'],
        'runner' => $task['execution_profile']['runner'],
        'model' => $task['execution_profile']['model'],
        'context' => $task['execution_profile']['context'],
        'tools' => $task['execution_profile']['tools'],
        'transport_tools' => $task['execution_profile']['transport_tools'],
        'budgets' => $task['budgets'],
        'usage' => [
            'input_tokens' => 1_000,
            'output_tokens' => 500,
            'cached_tokens' => 100,
            'reasoning_tokens' => 200,
        ],
        'timing' => [
            'started_at' => '2026-09-19T01:00:00Z',
            'finished_at' => '2026-09-19T01:10:00Z',
        ],
        'repair_turns' => 0,
        'termination_reason' => 'completed',
        'events_path' => 'events.jsonl',
        'events_sha256' => hash('sha256', $events),
        'candidate_patch_path' => 'candidate.patch',
        'candidate_patch_sha256' => hash('sha256', ''),
        'response_path' => 'response.txt',
        'response_sha256' => hash('sha256', $response),
    ];
    agentEvaluationValidateExplanationRunRecord($run, $task);
    $higherUsageRun = $run;
    $higherUsageRun['usage']['input_tokens'] = 150_000;
    agentEvaluationValidateExplanationRunRecord($higherUsageRun, $task);
    $staleBudgetRun = $run;
    $staleBudgetRun['budgets']['model_tokens'] = 100_000;
    agentEvaluationExpectFailure(
        static function () use ($staleBudgetRun, $task): void {
            agentEvaluationValidateExplanationRunRecord($staleBudgetRun, $task);
        },
        'Run record budgets do not match the selected task.',
    );
    $invalidExplanationRunId = $run;
    $invalidExplanationRunId['run_id'] = 'x';
    agentEvaluationExpectFailure(
        static function () use ($invalidExplanationRunId, $task): void {
            agentEvaluationValidateExplanationRunRecord($invalidExplanationRunId, $task);
        },
        'Explanation run ID must use 32 lowercase hexadecimal characters.',
    );

    $providerDrift = $run;
    $providerDrift['model'] = [...$run['model'], 'provider' => 'other'];
    $modelDrift = $run;
    $modelDrift['model'] = [...$run['model'], 'id' => 'gpt-5.4'];
    $effortDrift = $run;
    $effortDrift['model'] = [
        ...$run['model'],
        'settings' => ['reasoning_effort' => 'medium'],
    ];
    $contextDrift = $run;
    $contextDrift['context'] = [
        'bundle_id' => 'unexpected-context',
        'bundle_sha256' => str_repeat('a', 64),
    ];
    $permissionDrift = $run;
    $permissionDrift['tools'] = [[
        'name' => 'shell',
        'version' => null,
        'permissions' => ['workspace-read'],
    ]];
    $toolDrift = $run;
    $toolDrift['tools'] = [[
        'name' => 'filesystem',
        'version' => null,
        'permissions' => ['workspace-read', 'process-execute'],
    ]];
    $emptyTools = $run;
    $emptyTools['tools'] = [];
    $transportKindDrift = $run;
    $transportKindDrift['transport_tools'] = [
        ...$run['transport_tools'],
        'kind' => 'other-transport-v1',
    ];
    $transportCountDrift = $run;
    $transportCountDrift['transport_tools'] = [
        ...$run['transport_tools'],
        'count' => 3,
    ];
    $transportHashDrift = $run;
    $transportHashDrift['transport_tools'] = [
        ...$run['transport_tools'],
        'sha256' => str_repeat('a', 64),
    ];
    $missingLiveTransport = $run;
    $missingLiveTransport['transport_tools'] = null;
    $runnerVersionDrift = $run;
    $runnerVersionDrift['runner'] = [...$run['runner'], 'version' => '0.153.0'];

    foreach (
        [
            $providerDrift,
            $modelDrift,
            $effortDrift,
            $contextDrift,
            $permissionDrift,
            $toolDrift,
            $emptyTools,
            $transportKindDrift,
            $transportCountDrift,
            $transportHashDrift,
            $missingLiveTransport,
            $runnerVersionDrift,
        ]
        as $driftedProfile
    ) {
        agentEvaluationExpectFailure(
            static function () use ($driftedProfile, $task): void {
                agentEvaluationValidateExplanationRunRecord($driftedProfile, $task);
            },
            'Live explanation run profile does not match the pinned task profile.',
        );
    }

    $syntheticRun = $run;
    $syntheticRun['execution_kind'] = 'synthetic-control';
    $syntheticRun['run_id'] = '00000000000000000000000000000071';
    $syntheticRun['model'] = [
        'provider' => 'synthetic',
        'id' => 'fixture-model',
        'revision' => null,
        'settings' => ['reasoning_effort' => 'high'],
    ];
    $syntheticRun['runner'] = ['name' => 'fake-codex', 'version' => 'fixture-1'];
    $syntheticRun['transport_tools'] = null;
    $syntheticRun['prepared_lock_path'] = null;
    $syntheticRun['prepared_lock_sha256'] = null;
    $syntheticRun['prepared_installed_metadata_path'] = null;
    $syntheticRun['prepared_installed_metadata_sha256'] = null;
    $syntheticRun['prepared_installed_package_count'] = null;
    agentEvaluationValidateExplanationRunRecord($syntheticRun, $task);
    $syntheticTransportIdentity = $syntheticRun;
    $syntheticTransportIdentity['transport_tools'] = $task['execution_profile']['transport_tools'];
    agentEvaluationExpectFailure(
        static function () use ($syntheticTransportIdentity, $task): void {
            agentEvaluationValidateExplanationRunRecord($syntheticTransportIdentity, $task);
        },
        'Synthetic explanation control must keep the read-only profile, null transport identity, and synthetic provider label.',
    );

    $writePermission = $run;
    $writePermission['tools'] = [[
        'name' => 'shell',
        'version' => null,
        'permissions' => ['workspace-read', 'process-execute', 'workspace-write'],
    ]];
    agentEvaluationExpectFailure(
        static function () use ($writePermission, $task): void {
            agentEvaluationValidateExplanationRunRecord($writePermission, $task);
        },
        'Explanation run tools cannot claim candidate workspace write permission.',
    );
    $wrongBase = $run;
    $wrongBase['base_revision'] = str_repeat('a', 40);
    agentEvaluationExpectFailure(
        static function () use ($wrongBase, $task): void {
            agentEvaluationValidateExplanationRunRecord($wrongBase, $task);
        },
        'Explanation run record base does not match the pinned tracked maintainer source.',
    );
    $nonEmptyPatchRecord = $run;
    $nonEmptyPatchRecord['candidate_patch_sha256'] = hash('sha256', "mutation\n");
    agentEvaluationExpectFailure(
        static function () use ($nonEmptyPatchRecord, $task): void {
            agentEvaluationValidateExplanationRunRecord($nonEmptyPatchRecord, $task);
        },
        'Explanation run record must bind an empty candidate patch.',
    );
    $nullUsage = $run;
    $nullUsage['usage']['cached_tokens'] = null;
    agentEvaluationExpectFailure(
        static function () use ($nullUsage, $task): void {
            agentEvaluationValidateExplanationRunRecord($nullUsage, $task);
        },
        'Completed explanation run usage must contain integer token values.',
    );
    $excessCachedUsage = $run;
    $excessCachedUsage['usage']['input_tokens'] = 99;
    $excessCachedUsage['usage']['cached_tokens'] = 100;
    agentEvaluationExpectFailure(
        static function () use ($excessCachedUsage, $task): void {
            agentEvaluationValidateExplanationRunRecord($excessCachedUsage, $task);
        },
        'Explanation cached and reasoning tokens cannot exceed their provider totals.',
    );
    $excessReasoningUsage = $run;
    $excessReasoningUsage['usage']['output_tokens'] = 199;
    $excessReasoningUsage['usage']['reasoning_tokens'] = 200;
    agentEvaluationExpectFailure(
        static function () use ($excessReasoningUsage, $task): void {
            agentEvaluationValidateExplanationRunRecord($excessReasoningUsage, $task);
        },
        'Explanation cached and reasoning tokens cannot exceed their provider totals.',
    );

    $runHash = str_repeat('d', 64);
    $unknownDimensions = [];

    foreach (
        [
            'route_selection',
            'necessary_concern_coverage',
            'unsupported_claims',
            'answer_correctness',
            'repairs',
            'clarification',
        ] as $name
    ) {
        $unknownDimensions[$name] = [
            'status' => 'unknown',
            'evidence' => 'Pending accountable review of the retained response and events.',
        ];
    }

    $score = [
        'schema_version' => 3,
        'run_id' => $run['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'run_record_sha256' => $runHash,
        'prompt_sha256' => $task['prompt']['sha256'],
        'effective_prompt_sha256' => $task['prompt']['effective_sha256'],
        'rubric_sha256' => $task['rubric']['sha256'],
        'response_sha256' => $run['response_sha256'],
        'candidate_patch_sha256' => $run['candidate_patch_sha256'],
        'structural_evidence_path' => 'structural-evidence.json',
        'structural_evidence_sha256' => str_repeat('f', 64),
        'admissible' => true,
        'structural_checks' => [
            'task_identity' => true,
            'response_integrity' => true,
            'workspace_unchanged' => true,
            'resource_bounds' => true,
            'external_actions_approved' => true,
            'cleanup' => true,
        ],
        'automated_status' => 'pass',
        'human_review' => [
            'status' => 'pending',
            'reviewer' => null,
            'dimensions' => $unknownDimensions,
            'reason' => 'Semantic correctness requires a separate accountable human review.',
        ],
        'correct_completion' => null,
    ];
    agentEvaluationValidateExplanationScoreRecord($score, $task, $run, $runHash);

    $pendingDecision = $score;
    $pendingDecision['human_review']['dimensions']['route_selection']['status'] = 'pass';
    agentEvaluationExpectFailure(
        static function () use ($pendingDecision, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($pendingDecision, $task, $run, $runHash);
        },
        'A pending explanation review requires a null reviewer and unknown dimensions.',
    );
    $pendingCompletion = $score;
    $pendingCompletion['correct_completion'] = false;
    agentEvaluationExpectFailure(
        static function () use ($pendingCompletion, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($pendingCompletion, $task, $run, $runHash);
        },
        'A pending explanation review requires unknown correct completion.',
    );

    $passed = $score;
    $passed['human_review']['status'] = 'pass';
    $passed['human_review']['reviewer'] = 'maintainer-1';
    $passed['human_review']['reason'] = 'The retained response and event evidence satisfy every required semantic judgment.';

    foreach (
        ['route_selection', 'necessary_concern_coverage', 'unsupported_claims', 'answer_correctness']
        as $name
    ) {
        $passed['human_review']['dimensions'][$name] = [
            'status' => 'pass',
            'evidence' => "Retained response evidence supports {$name}.",
        ];
    }

    $passed['correct_completion'] = true;
    agentEvaluationValidateExplanationScoreRecord($passed, $task, $run, $runHash);

    $failed = $passed;
    $failed['human_review']['status'] = 'fail';
    $failed['human_review']['dimensions']['answer_correctness'] = [
        'status' => 'fail',
        'evidence' => 'The retained response incorrectly presents profile adoption as automatic.',
    ];
    $failed['human_review']['reason'] = 'Answer correctness failed the frozen rubric.';
    $failed['correct_completion'] = false;
    agentEvaluationValidateExplanationScoreRecord($failed, $task, $run, $runHash);

    $syntheticPending = $score;
    $syntheticPending['run_id'] = $syntheticRun['run_id'];
    agentEvaluationValidateExplanationScoreRecord($syntheticPending, $task, $syntheticRun, $runHash);
    $syntheticSemanticCompletion = $passed;
    $syntheticSemanticCompletion['run_id'] = $syntheticRun['run_id'];
    agentEvaluationExpectFailure(
        static function () use ($syntheticSemanticCompletion, $task, $syntheticRun, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord(
                $syntheticSemanticCompletion,
                $task,
                $syntheticRun,
                $runHash,
            );
        },
        'Synthetic explanation controls cannot claim semantic review or correct completion.',
    );

    $falseFail = $passed;
    $falseFail['human_review']['status'] = 'fail';
    $falseFail['correct_completion'] = false;
    agentEvaluationExpectFailure(
        static function () use ($falseFail, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($falseFail, $task, $run, $runHash);
        },
        'Explanation human review status must match its six semantic dimensions.',
    );
    $falseFailureCompletion = $failed;
    $falseFailureCompletion['correct_completion'] = true;
    agentEvaluationExpectFailure(
        static function () use ($falseFailureCompletion, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($falseFailureCompletion, $task, $run, $runHash);
        },
        'Explanation correct completion must combine structural and human review status.',
    );

    $falsePass = $failed;
    $falsePass['human_review']['status'] = 'pass';
    $falsePass['correct_completion'] = true;
    agentEvaluationExpectFailure(
        static function () use ($falsePass, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($falsePass, $task, $run, $runHash);
        },
        'Explanation human review status must match its six semantic dimensions.',
    );
    $unaccountablePass = $passed;
    $unaccountablePass['human_review']['reviewer'] = null;
    agentEvaluationExpectFailure(
        static function () use ($unaccountablePass, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($unaccountablePass, $task, $run, $runHash);
        },
        'A completed explanation review requires an accountable reviewer.',
    );
    $wrongResponseBinding = $score;
    $wrongResponseBinding['response_sha256'] = str_repeat('e', 64);
    agentEvaluationExpectFailure(
        static function () use ($wrongResponseBinding, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($wrongResponseBinding, $task, $run, $runHash);
        },
        'Explanation score artifacts do not match the validated task and run records.',
    );
    $wrongEffectivePromptBinding = $score;
    $wrongEffectivePromptBinding['effective_prompt_sha256'] = str_repeat('e', 64);
    agentEvaluationExpectFailure(
        static function () use ($wrongEffectivePromptBinding, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($wrongEffectivePromptBinding, $task, $run, $runHash);
        },
        'Explanation score artifacts do not match the validated task and run records.',
    );
    $wrongRunEffectivePrompt = $run;
    $wrongRunEffectivePrompt['effective_prompt_sha256'] = str_repeat('e', 64);
    agentEvaluationExpectFailure(
        static function () use ($score, $task, $wrongRunEffectivePrompt, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($score, $task, $wrongRunEffectivePrompt, $runHash);
        },
        'Explanation score effective prompt does not match the validated run record.',
    );
    $hiddenStructuralFailure = $score;
    $hiddenStructuralFailure['structural_checks']['cleanup'] = false;
    agentEvaluationExpectFailure(
        static function () use ($hiddenStructuralFailure, $task, $run, $runHash): void {
            agentEvaluationValidateExplanationScoreRecord($hiddenStructuralFailure, $task, $run, $runHash);
        },
        'Explanation admissibility must equal every structural automated check.',
    );
    foreach (
        ['task_identity', 'response_integrity', 'workspace_unchanged', 'resource_bounds', 'external_actions_approved']
        as $checkName
    ) {
        $falseCompletedCheck = $score;
        $falseCompletedCheck['structural_checks'][$checkName] = false;
        $falseCompletedCheck['admissible'] = false;
        $falseCompletedCheck['automated_status'] = 'fail';
        agentEvaluationExpectFailure(
            static function () use ($falseCompletedCheck, $task, $run, $runHash): void {
                agentEvaluationValidateExplanationScoreRecord($falseCompletedCheck, $task, $run, $runHash);
            },
            'A completed explanation run requires every non-cleanup structural check to pass.',
        );
    }
    $structuralFailureWithSemanticPass = $passed;
    $structuralFailureWithSemanticPass['structural_checks']['cleanup'] = false;
    $structuralFailureWithSemanticPass['admissible'] = false;
    $structuralFailureWithSemanticPass['automated_status'] = 'fail';
    $structuralFailureWithSemanticPass['correct_completion'] = false;
    agentEvaluationValidateExplanationScoreRecord(
        $structuralFailureWithSemanticPass,
        $task,
        $run,
        $runHash,
    );

    $artifactRoot = sys_get_temp_dir() . '/phpthis-agent-evaluation-explanation-' . bin2hex(random_bytes(8));

    if (!mkdir($artifactRoot, 0700)) {
        throw new RuntimeException('Unable to create explanation artifact controls.');
    }

    try {
        $repositoryRoot = dirname($kit, 2);
        $taskManifest = file_get_contents($task['directory'] . '/task.json');
        $rubric = file_get_contents($task['directory'] . '/' . $task['rubric']['path']);
        $trackedSourceManifest = agentEvaluationPinnedExplanationSourceManifest($repositoryRoot);
        $effectivePrompt = is_string($sourcePrompt)
            ? agentEvaluationExplanationEffectivePrompt($sourcePrompt)
            : '';
        $candidateManifest = $trackedSourceManifest;
        $generationImageDigest = 'sha256:' . str_repeat('b', 64);
        $generationImage = 'registry.invalid/phpthis/agent-evaluation-generation@' . $generationImageDigest;
        $scoringImage = 'registry.invalid/phpthis/agent-evaluation-scoring@sha256:' . str_repeat('c', 64);
        $profile = [
            'condition' => $run['condition'],
            'runner' => $run['runner'],
            'model' => $run['model'],
            'context' => $run['context'],
            'tools' => $run['tools'],
            'transport_tools' => $run['transport_tools'],
            'budgets' => $run['budgets'],
            'isolation' => [
                'launcher' => 'docker-oci',
                'image_reference' => $generationImage,
                'image_digest' => $generationImageDigest,
                'credential_broker' => 'responses-api-run-proxy',
                'network' => 'proxy-only',
                'root_read_only' => true,
                'capabilities_dropped' => true,
                'no_new_privileges' => true,
                'candidate_git_absent' => true,
                'dependencies_read_only' => true,
                'uid' => 65_534,
                'cpu_millis' => 1_000,
                'memory_bytes' => 1_073_741_824,
                'disk_bytes' => 1_073_741_824,
                'processes' => 64,
                'wall_seconds' => $run['budgets']['wall_seconds'],
                'model_tokens' => $run['budgets']['model_tokens'],
                'output_bytes' => $run['budgets']['command_output_bytes'],
                'descendant_cleanup' => 'container-destroy',
            ],
        ];
        $databaseIdentity = [
            'pdo_drivers' => ['sqlite'],
            'pdo_sqlite_version' => '8.4.19',
            'sqlite_json1' => true,
            'sqlite_version' => '3.49.1',
        ];
        $ociPreflight = [
            'engine_version' => '27.3.1',
            'cgroup_version' => '2',
            'images' => [
                'generation' => [
                    'image_reference' => $generationImage,
                    'image_id' => 'sha256:' . str_repeat('d', 64),
                    'architecture' => 'arm64',
                ],
                'scoring' => [
                    'image_reference' => $scoringImage,
                    'image_id' => 'sha256:' . str_repeat('e', 64),
                    'architecture' => 'arm64',
                ],
            ],
            'network' => 'none-with-fixed-broker-pipe',
            'toolchains' => [
                'generation' => [
                    'php_version' => '8.4.19',
                    'composer_version' => '2.8.12',
                    'python_version' => '3.13.7',
                    'codex_version' => '0.153.1',
                    'relay_sha256' => AGENT_EVALUATION_EXPLANATION_RELAY_SHA256,
                    'database' => $databaseIdentity,
                ],
                'scoring' => [
                    'php_version' => '8.4.19',
                    'composer_version' => '2.8.12',
                    'python_version' => '3.13.7',
                    'codex_version' => null,
                    'relay_sha256' => null,
                    'database' => $databaseIdentity,
                ],
            ],
        ];
        $generationProcess = [
            'exit_code' => 0,
            'stdout' => 'retained separately as events.jsonl',
            'stderr' => 'retained separately as generation.stderr',
            'termination_reason' => 'completed',
            'elapsed_milliseconds' => 600_000,
            'timed_out' => false,
            'output_limit_exceeded' => false,
            'cleanup' => ['container_stopped' => true, 'oom_killed' => false, 'pid' => 0],
            'resource_observation' => [
                'memory_events' => ['oom' => 0, 'oom_kill' => 0],
                'pids_events' => ['max' => 0],
                'disk_free_bytes' => [
                    'candidate' => 1,
                    'tmp' => 1,
                    'workspace_tmp' => 1,
                    'cache' => 1,
                    'shm' => 1,
                ],
            ],
            'synthetic_upstream' => false,
            'failure_code' => null,
            'upstream_failure' => null,
        ];
        $externalActions = [
            'approved' => true,
            'network' => 'none',
            'socket_attempt_telemetry' => null,
            'host_proxy_requests' => 1,
            'proxy_blocked' => false,
            'observed_commands' => [[
                'item_id' => 'command-1',
                'sha256' => hash('sha256', $commandText),
                'bytes' => strlen($commandText),
            ]],
            'file_change_events' => 0,
        ];
        $freeze = [
            'candidate_sha256' => hash('sha256', $candidateManifest),
            'patch_sha256' => hash('sha256', ''),
            'changed_files' => [],
            'added_lines' => 0,
            'deleted_lines' => 0,
        ];
        $generationCleanup = [
            'status' => 'pass',
            'oci' => ['status' => 'pass', 'generation_destroyed' => true],
            'removed' => ['candidate', 'baseline', 'dependencies'],
        ];
        $cleanup = [
            'status' => 'pass',
            'removed' => [],
            'primary_failure' => null,
            'cleanup_failure' => null,
        ];
        $approval = [
            'reference' => 'issue-70-approved-live-run',
            'model' => $run['model']['id'],
            'runs' => 1,
            'spending_ceiling_usd' => '0.60',
            'run_id' => $run['run_id'],
        ];
        $proxy = [
            'candidate_operation' => 'POST /v1/responses',
            'upstream_operations' => ['POST /v1/responses/input_tokens', 'POST /v1/responses'],
            'upstream_origin' => 'https://api.openai.com',
            'synthetic_upstream' => false,
            'transport' => 'container-loopback-to-stdio',
            'provider_reported_usage' => $run['usage'],
            'runner_reported_usage' => $run['usage'],
            'ledger' => [
                'model' => $run['model']['id'],
                'reasoning_effort' => $run['model']['settings']['reasoning_effort'],
                'token_budget' => 200_000,
                'input_tokens' => 1_000,
                'output_tokens' => 500,
                'cached_tokens' => 100,
                'reasoning_tokens' => 200,
                'reserved_input' => 0,
                'reserved_output' => 0,
                'request_sha256' => null,
                'failure_reason' => null,
                'transport_tools' => $run['transport_tools'],
                'blocked' => false,
                'request_count' => 1,
                'observed_request_count' => 1,
                'spending' => [
                    'policy' => [
                        'limit_units' => 60_000_000,
                        'input_cents_per_million' => 250,
                        'cached_cents_per_million' => 25,
                        'output_cents_per_million' => 1_500,
                    ],
                    'settled_units' => 977_500,
                    'reserved_units' => 0,
                ],
            ],
        ];
        $ociCleanup = [
            'verified' => true,
            'status' => 'pass',
            'containers_remaining' => 0,
            'volumes_remaining' => 0,
        ];
        $runBytes = agentEvaluationJson($run);

        if (
            !is_string($taskManifest)
            || !is_string($rubric)
            || !is_string($sourcePrompt)
            || $effectivePrompt === ''
            || file_put_contents($artifactRoot . '/events.jsonl', $events) === false
            || file_put_contents($artifactRoot . '/generation.stderr', "retained generation stderr\n") === false
            || file_put_contents($artifactRoot . '/candidate.patch', '') === false
            || file_put_contents($artifactRoot . '/prepared-dependencies.manifest', $dependencies) === false
            || file_put_contents($artifactRoot . '/response.txt', $response) === false
            || file_put_contents($artifactRoot . '/task.json', $taskManifest) === false
            || file_put_contents($artifactRoot . '/source-prompt.md', $sourcePrompt) === false
            || file_put_contents($artifactRoot . '/rubric.md', $rubric) === false
            || file_put_contents(
                $artifactRoot . '/workspace-policy.json',
                agentEvaluationJson($task['workspace_policy']),
            ) === false
            || file_put_contents($artifactRoot . '/profile.json', agentEvaluationJson($profile)) === false
            || file_put_contents($artifactRoot . '/tracked-source.manifest', $trackedSourceManifest) === false
            || file_put_contents($artifactRoot . '/prompt.md', $effectivePrompt) === false
            || file_put_contents($artifactRoot . '/generation-process.json', agentEvaluationJson($generationProcess)) === false
            || file_put_contents($artifactRoot . '/external-actions.json', agentEvaluationJson($externalActions)) === false
            || file_put_contents($artifactRoot . '/candidate.manifest', $candidateManifest) === false
            || file_put_contents($artifactRoot . '/freeze.json', agentEvaluationJson($freeze)) === false
            || file_put_contents($artifactRoot . '/generation-cleanup.json', agentEvaluationJson($generationCleanup)) === false
            || file_put_contents($artifactRoot . '/cleanup.json', agentEvaluationJson($cleanup)) === false
            || file_put_contents($artifactRoot . '/approval.json', agentEvaluationJson($approval)) === false
            || file_put_contents(
                $artifactRoot . '/dependencies.installed.json',
                $preparedInstalledMetadataBytes,
            ) === false
            || file_put_contents($artifactRoot . '/dependencies.lock', $preparedLockBytes) === false
            || file_put_contents($artifactRoot . '/oci-preflight.json', agentEvaluationJson($ociPreflight)) === false
            || file_put_contents($artifactRoot . '/proxy.json', agentEvaluationJson($proxy)) === false
            || file_put_contents($artifactRoot . '/oci-cleanup.json', agentEvaluationJson($ociCleanup)) === false
            || file_put_contents($artifactRoot . '/run.json', $runBytes) === false
        ) {
            throw new RuntimeException('Unable to write explanation artifact controls.');
        }

        agentEvaluationValidateExplanationRunArtifacts($run, $artifactRoot);
        $structuralEvidence = agentEvaluationExplanationStructuralEvidenceDocument('live-model', $artifactRoot);
        $structuralEvidenceBytes = agentEvaluationJson($structuralEvidence);
        $cliScore = $score;
        $cliScore['run_record_sha256'] = hash('sha256', $runBytes);
        $cliScore['structural_evidence_sha256'] = hash('sha256', $structuralEvidenceBytes);
        $scoreBytes = agentEvaluationJson($cliScore);

        if (
            file_put_contents($artifactRoot . '/structural-evidence.json', $structuralEvidenceBytes) === false
            || file_put_contents($artifactRoot . '/score.json', $scoreBytes) === false
        ) {
            throw new RuntimeException('Unable to write explanation CLI controls.');
        }

        agentEvaluationValidateExplanationScoreRecord(
            $cliScore,
            $task,
            $run,
            hash('sha256', $runBytes),
        );
        agentEvaluationValidateExplanationScoreArtifacts($cliScore, $run, $artifactRoot);

        $wrongPreparedLockBytes = $preparedLockBytes . "\n";
        $wrongPreparedLockRun = $run;
        $wrongPreparedLockRun['prepared_lock_sha256'] = hash('sha256', $wrongPreparedLockBytes);
        if (
            file_put_contents($artifactRoot . '/dependencies.lock', $wrongPreparedLockBytes)
                !== strlen($wrongPreparedLockBytes)
        ) {
            throw new RuntimeException('Unable to write the wrong retained prepared-lock control.');
        }
        $wrongPreparedLockScore = agentEvaluationRefreshExplanationStructuralScore(
            $artifactRoot,
            $runBytes,
            $cliScore,
        );
        agentEvaluationExpectFailure(
            static function () use ($wrongPreparedLockScore, $wrongPreparedLockRun, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts(
                    $wrongPreparedLockScore,
                    $wrongPreparedLockRun,
                    $artifactRoot,
                );
            },
            'Explanation retained prepared lock does not match the tracked source composer.lock.',
        );
        if (
            file_put_contents($artifactRoot . '/dependencies.lock', $preparedLockBytes)
                !== strlen($preparedLockBytes)
        ) {
            throw new RuntimeException('Unable to restore the retained prepared-lock control.');
        }
        $cliScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);

        $duplicateInstalledMetadata = agentEvaluationJsonValue(
            $preparedInstalledMetadataBytes,
            'duplicate installed Composer metadata control',
        );
        if (!is_object($duplicateInstalledMetadata)) {
            throw new RuntimeException('Unable to decode the duplicate installed Composer metadata control.');
        }
        $duplicateInstalledMetadata = (array) $duplicateInstalledMetadata;
        $duplicateInstalledPackages = $duplicateInstalledMetadata['packages'] ?? null;
        if (!is_array($duplicateInstalledPackages) || !array_is_list($duplicateInstalledPackages)) {
            throw new RuntimeException('Unable to prepare the duplicate installed Composer package control.');
        }
        $duplicateInstalledPackages[] = (object) [
            'name' => 'phpthis/framework',
            'version' => 'dev-main',
            'install-path' => '../phpthis/framework',
        ];
        $duplicateInstalledMetadata['packages'] = $duplicateInstalledPackages;
        $duplicateInstalledMetadataBytes = agentEvaluationJson($duplicateInstalledMetadata);
        $duplicateInstalledRun = $run;
        $duplicateInstalledRun['prepared_installed_metadata_sha256'] = hash(
            'sha256',
            $duplicateInstalledMetadataBytes,
        );
        $duplicateInstalledManifest = str_replace(
            '100644 ' . hash('sha256', $preparedInstalledMetadataBytes) . ' composer/installed.json',
            '100644 ' . hash('sha256', $duplicateInstalledMetadataBytes) . ' composer/installed.json',
            $dependencies,
        );
        $duplicateInstalledLines = explode("\n", rtrim($duplicateInstalledManifest, "\n"));
        sort($duplicateInstalledLines, SORT_STRING);
        $duplicateInstalledManifest = implode("\n", $duplicateInstalledLines) . "\n";
        $duplicateInstalledRun['prepared_dependencies_manifest_sha256'] = hash(
            'sha256',
            $duplicateInstalledManifest,
        );
        if (
            file_put_contents(
                $artifactRoot . '/dependencies.installed.json',
                $duplicateInstalledMetadataBytes,
            ) !== strlen($duplicateInstalledMetadataBytes)
            || file_put_contents(
                $artifactRoot . '/prepared-dependencies.manifest',
                $duplicateInstalledManifest,
            ) !== strlen($duplicateInstalledManifest)
        ) {
            throw new RuntimeException('Unable to write the duplicate installed Composer package control.');
        }
        $duplicateInstalledScore = agentEvaluationRefreshExplanationStructuralScore(
            $artifactRoot,
            $runBytes,
            $cliScore,
        );
        agentEvaluationExpectFailure(
            static function () use ($duplicateInstalledScore, $duplicateInstalledRun, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts(
                    $duplicateInstalledScore,
                    $duplicateInstalledRun,
                    $artifactRoot,
                );
            },
            'Explanation prepared dependencies expose a duplicate phpthis/framework package path.',
        );
        if (
            file_put_contents(
                $artifactRoot . '/dependencies.installed.json',
                $preparedInstalledMetadataBytes,
            ) !== strlen($preparedInstalledMetadataBytes)
            || file_put_contents($artifactRoot . '/prepared-dependencies.manifest', $dependencies)
                !== strlen($dependencies)
        ) {
            throw new RuntimeException('Unable to restore the installed Composer metadata control.');
        }
        $cliScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);

        $duplicateDependencyLines = explode("\n", substr($dependencies, 0, -1));
        $duplicateDependencyLines[] = '100644 '
            . hash('sha256', "duplicate framework source\n")
            . ' phpthis/framework/src/Duplicate.php';
        sort($duplicateDependencyLines, SORT_STRING);
        $duplicateDependencies = implode("\n", $duplicateDependencyLines) . "\n";
        $duplicateDependencyRun = $run;
        $duplicateDependencyRun['prepared_dependencies_manifest_sha256'] = hash(
            'sha256',
            $duplicateDependencies,
        );
        if (
            file_put_contents($artifactRoot . '/prepared-dependencies.manifest', $duplicateDependencies)
                !== strlen($duplicateDependencies)
        ) {
            throw new RuntimeException('Unable to write the duplicate framework manifest control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($cliScore, $duplicateDependencyRun, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts(
                    $cliScore,
                    $duplicateDependencyRun,
                    $artifactRoot,
                );
            },
            'Explanation prepared dependencies expose a duplicate phpthis/framework package path.',
        );
        if (
            file_put_contents($artifactRoot . '/prepared-dependencies.manifest', $dependencies)
                !== strlen($dependencies)
        ) {
            throw new RuntimeException('Unable to restore the prepared-dependencies manifest control.');
        }

        $changedCommandEvents = str_replace('"command":"pwd"', '"command":"ls"', $events);
        $changedCommandRun = $run;
        $changedCommandRun['events_sha256'] = hash('sha256', $changedCommandEvents);
        if (file_put_contents($artifactRoot . '/events.jsonl', $changedCommandEvents) === false) {
            throw new RuntimeException('Unable to write the changed-command replay control.');
        }
        agentEvaluationValidateExplanationRunArtifacts($changedCommandRun, $artifactRoot);
        agentEvaluationExpectFailure(
            static function () use ($cliScore, $changedCommandRun, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($cliScore, $changedCommandRun, $artifactRoot);
            },
            'Live explanation evidence contains an unapproved external action.',
        );

        $fileChangeEvents = str_replace(
            '{"type":"item.completed","item":{"id":"message-1","type":"agent_message"',
            '{"type":"item.completed","item":{"id":"change-1","type":"file_change"}}' . "\n"
                . '{"type":"item.completed","item":{"id":"message-1","type":"agent_message"',
            $events,
        );
        $fileChangeRun = $run;
        $fileChangeRun['events_sha256'] = hash('sha256', $fileChangeEvents);
        if (file_put_contents($artifactRoot . '/events.jsonl', $fileChangeEvents) === false) {
            throw new RuntimeException('Unable to write the file-change replay control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($fileChangeRun, $artifactRoot): void {
                agentEvaluationValidateExplanationRunArtifacts($fileChangeRun, $artifactRoot);
            },
            'Explanation events artifact contains a file-change item.',
        );

        $eventReplayControls = [
            [
                $events . '{"type":"item.completed","item":{"id":"late","type":"agent_message","text":"late"}}' . "\n",
                'Explanation events artifact contains data after its terminal event.',
            ],
            [
                str_replace(
                    '{"type":"item.completed","item":{"id":"message-1","type":"agent_message"',
                    '{"type":"item.delta","item":{"id":"message-1","type":"agent_message"',
                    $events,
                ),
                'Explanation events artifact contains an unapproved event or item type.',
            ],
            [
                str_replace(
                    '{"type":"item.completed","item":{"type":"command_execution","id":"command-1","command":"pwd"}}' . "\n",
                    '',
                    $events,
                ),
                'Explanation events artifact contains an incomplete command lifecycle.',
            ],
            [
                str_replace(
                    '{"type":"thread.started","thread_id":"thread-1"}' . "\n" . '{"type":"turn.started"}',
                    '{"type":"turn.started"}' . "\n" . '{"type":"thread.started","thread_id":"thread-1"}',
                    $events,
                ),
                'Explanation events artifact has an invalid turn lifecycle.',
            ],
            [
                str_replace('"cached_input_tokens":100', '"cached_input_tokens":99', $events),
                'Explanation terminal event usage does not match the validated run record.',
            ],
        ];
        foreach ($eventReplayControls as [$tamperedEvents, $expectedFailure]) {
            $tamperedEventRun = $run;
            $tamperedEventRun['events_sha256'] = hash('sha256', $tamperedEvents);
            if (file_put_contents($artifactRoot . '/events.jsonl', $tamperedEvents) === false) {
                throw new RuntimeException('Unable to write an explanation event-lifecycle replay control.');
            }
            agentEvaluationExpectFailure(
                static function () use ($tamperedEventRun, $artifactRoot): void {
                    agentEvaluationValidateExplanationRunArtifacts($tamperedEventRun, $artifactRoot);
                },
                $expectedFailure,
            );
        }

        $excessEvents = str_repeat("{\"type\":\"turn.started\"}\n", AGENT_EVALUATION_EXPLANATION_MAX_EVENTS)
            . '{"type":"item.completed","item":{"type":"agent_message","text":"'
            . $responseText
            . '"}}' . "\n";
        $excessEventRun = $run;
        $excessEventRun['events_sha256'] = hash('sha256', $excessEvents);
        if (file_put_contents($artifactRoot . '/events.jsonl', $excessEvents) === false) {
            throw new RuntimeException('Unable to write the event-count replay control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($excessEventRun, $artifactRoot): void {
                agentEvaluationValidateExplanationRunArtifacts($excessEventRun, $artifactRoot);
            },
            'Explanation events artifact exceeds its fixed event-count bound.',
        );

        if (file_put_contents($artifactRoot . '/events.jsonl', $events) === false) {
            throw new RuntimeException('Unable to restore the explanation event replay controls.');
        }
        $expectedStructuralPaths = implode(', ', agentEvaluationExplanationStructuralEvidencePaths('live-model'));
        $missingArtifactDocument = $structuralEvidence;
        unset($missingArtifactDocument['artifacts']['proxy.json']);
        $missingArtifactBytes = agentEvaluationJson($missingArtifactDocument);
        $missingArtifactScore = $cliScore;
        $missingArtifactScore['structural_evidence_sha256'] = hash('sha256', $missingArtifactBytes);

        if (file_put_contents($artifactRoot . '/structural-evidence.json', $missingArtifactBytes) === false) {
            throw new RuntimeException('Unable to write the missing structural-artifact control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($missingArtifactScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($missingArtifactScore, $run, $artifactRoot);
            },
            'explanation structural evidence artifacts must contain exactly: ' . $expectedStructuralPaths . '.',
        );

        $unlistedArtifactDocument = $structuralEvidence;
        $unlistedArtifactDocument['artifacts']['unlisted.json'] = str_repeat('a', 64);
        ksort($unlistedArtifactDocument['artifacts'], SORT_STRING);
        $unlistedArtifactBytes = agentEvaluationJson($unlistedArtifactDocument);
        $unlistedArtifactScore = $cliScore;
        $unlistedArtifactScore['structural_evidence_sha256'] = hash('sha256', $unlistedArtifactBytes);

        if (file_put_contents($artifactRoot . '/structural-evidence.json', $unlistedArtifactBytes) === false) {
            throw new RuntimeException('Unable to write the unlisted structural-artifact control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($unlistedArtifactScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($unlistedArtifactScore, $run, $artifactRoot);
            },
            'explanation structural evidence artifacts must contain exactly: ' . $expectedStructuralPaths . '.',
        );

        if (
            file_put_contents($artifactRoot . '/structural-evidence.json', $structuralEvidenceBytes) === false
            || file_put_contents($artifactRoot . '/score.json', $scoreBytes) === false
            || file_put_contents($artifactRoot . '/freeze.json', agentEvaluationJson([
                ...$freeze,
                'changed_files' => ['src/Changed.php'],
            ])) === false
        ) {
            throw new RuntimeException('Unable to write the changed-workspace evidence control.');
        }
        $changedWorkspaceScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($changedWorkspaceScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($changedWorkspaceScore, $run, $artifactRoot);
            },
            'Explanation freeze evidence does not prove an unchanged workspace.',
        );

        if (file_put_contents($artifactRoot . '/freeze.json', agentEvaluationJson($freeze)) === false) {
            throw new RuntimeException('Unable to restore the unchanged-workspace evidence control.');
        }
        $tamperedCandidateManifest = $candidateManifest
            . '100644 '
            . hash('sha256', "untracked\n")
            . " untracked.txt\n";
        $tamperedCandidateFreeze = [
            ...$freeze,
            'candidate_sha256' => hash('sha256', $tamperedCandidateManifest),
        ];
        if (
            file_put_contents($artifactRoot . '/candidate.manifest', $tamperedCandidateManifest) === false
            || file_put_contents($artifactRoot . '/freeze.json', agentEvaluationJson($tamperedCandidateFreeze)) === false
        ) {
            throw new RuntimeException('Unable to write the candidate-manifest replay control.');
        }
        $tamperedCandidateScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($tamperedCandidateScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($tamperedCandidateScore, $run, $artifactRoot);
            },
            'Explanation freeze evidence does not prove an unchanged workspace.',
        );
        if (
            file_put_contents($artifactRoot . '/candidate.manifest', $candidateManifest) === false
            || file_put_contents($artifactRoot . '/freeze.json', agentEvaluationJson($freeze)) === false
        ) {
            throw new RuntimeException('Unable to restore the candidate-manifest replay control.');
        }
        $resourceDrift = [...$generationProcess, 'timed_out' => true];
        if (file_put_contents($artifactRoot . '/generation-process.json', agentEvaluationJson($resourceDrift)) === false) {
            throw new RuntimeException('Unable to write the resource-drift evidence control.');
        }
        $resourceDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($resourceDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($resourceDriftScore, $run, $artifactRoot);
            },
            'Explanation generation process evidence does not describe bounded completion.',
        );

        if (file_put_contents($artifactRoot . '/generation-process.json', agentEvaluationJson($generationProcess)) === false) {
            throw new RuntimeException('Unable to restore the resource evidence control.');
        }
        $unapprovedActions = [...$externalActions, 'approved' => false];
        if (file_put_contents($artifactRoot . '/external-actions.json', agentEvaluationJson($unapprovedActions)) === false) {
            throw new RuntimeException('Unable to write the unapproved-action evidence control.');
        }
        $unapprovedActionScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($unapprovedActionScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($unapprovedActionScore, $run, $artifactRoot);
            },
            'Explanation external-action evidence does not prove approved read-only execution.',
        );

        if (file_put_contents($artifactRoot . '/external-actions.json', agentEvaluationJson($externalActions)) === false) {
            throw new RuntimeException('Unable to restore the approved-action evidence control.');
        }
        $cleanupFailureScore = $cliScore;
        $cleanupFailureScore['structural_checks'] = [
            ...agentEvaluationRequireObject($cliScore, 'structural_checks', 'cleanup failure control'),
            'cleanup' => false,
        ];
        $cleanupFailureScore['admissible'] = false;
        $cleanupFailureScore['automated_status'] = 'fail';
        $cleanupFailureScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cleanupFailureScore);
        agentEvaluationValidateExplanationScoreRecord(
            $cleanupFailureScore,
            $task,
            $run,
            hash('sha256', $runBytes),
        );
        agentEvaluationExpectFailure(
            static function () use ($cleanupFailureScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($cleanupFailureScore, $run, $artifactRoot);
            },
            'Explanation cleanup score does not match final cleanup evidence.',
        );

        foreach (
            [
                ['reference' => 'pending-review', 'spending_ceiling_usd' => '0.60'],
                ['reference' => 'approval-placeholder', 'spending_ceiling_usd' => '0.60'],
                ['reference' => 'issue-70-approved-live-run', 'spending_ceiling_usd' => '0.01'],
                ['reference' => 'issue-70-approved-live-run', 'spending_ceiling_usd' => '1.00'],
            ]
            as $approvalDrift
        ) {
            if (file_put_contents($artifactRoot . '/approval.json', agentEvaluationJson([
                ...$approval,
                ...$approvalDrift,
            ])) === false) {
                throw new RuntimeException('Unable to write the approval-drift evidence control.');
            }
            $approvalDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
            agentEvaluationExpectFailure(
                static function () use ($approvalDriftScore, $run, $artifactRoot): void {
                    agentEvaluationValidateExplanationScoreArtifacts($approvalDriftScore, $run, $artifactRoot);
                },
                'Explanation approval evidence does not bind the exact reviewed live run and ceiling.',
            );
        }

        if (file_put_contents($artifactRoot . '/approval.json', agentEvaluationJson($approval)) === false) {
            throw new RuntimeException('Unable to restore the approval evidence control.');
        }
        $runnerDriftProfile = $profile;
        $runnerDriftProfile['runner']['version'] = '0.153.0';
        if (file_put_contents($artifactRoot . '/profile.json', agentEvaluationJson($runnerDriftProfile)) === false) {
            throw new RuntimeException('Unable to write the retained runner-drift control.');
        }
        $runnerDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($runnerDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($runnerDriftScore, $run, $artifactRoot);
            },
            'Explanation retained profile does not match the validated run record.',
        );

        $isolationDriftProfile = $profile;
        $isolationDriftProfile['isolation']['network'] = 'none';
        if (file_put_contents($artifactRoot . '/profile.json', agentEvaluationJson($isolationDriftProfile)) === false) {
            throw new RuntimeException('Unable to write the retained isolation-drift control.');
        }
        $isolationDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($isolationDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($isolationDriftScore, $run, $artifactRoot);
            },
            'Explanation retained isolation profile does not match its fixed execution kind.',
        );

        if (file_put_contents($artifactRoot . '/profile.json', agentEvaluationJson($profile)) === false) {
            throw new RuntimeException('Unable to restore the retained profile evidence control.');
        }
        $imageDriftPreflight = $ociPreflight;
        $imageDriftPreflight['images']['generation']['image_reference'] =
            'registry.invalid/phpthis/agent-evaluation-other@sha256:' . str_repeat('f', 64);
        if (file_put_contents($artifactRoot . '/oci-preflight.json', agentEvaluationJson($imageDriftPreflight)) === false) {
            throw new RuntimeException('Unable to write the OCI image-drift control.');
        }
        $imageDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($imageDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($imageDriftScore, $run, $artifactRoot);
            },
            'Explanation OCI preflight generation image does not match the retained isolation profile.',
        );

        foreach (
            [
                ['codex_version' => '0.153.0'],
                ['relay_sha256' => str_repeat('a', 64)],
            ]
            as $toolchainDrift
        ) {
            $toolchainDriftPreflight = $ociPreflight;
            $toolchainDriftPreflight['toolchains']['generation'] = [
                ...$toolchainDriftPreflight['toolchains']['generation'],
                ...$toolchainDrift,
            ];
            if (file_put_contents(
                $artifactRoot . '/oci-preflight.json',
                agentEvaluationJson($toolchainDriftPreflight),
            ) === false) {
                throw new RuntimeException('Unable to write the OCI toolchain-drift control.');
            }
            $toolchainDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
            agentEvaluationExpectFailure(
                static function () use ($toolchainDriftScore, $run, $artifactRoot): void {
                    agentEvaluationValidateExplanationScoreArtifacts($toolchainDriftScore, $run, $artifactRoot);
                },
                'Explanation OCI preflight does not match the pinned generation client and relay.',
            );
        }

        $phpDriftPreflight = $ociPreflight;
        $phpDriftPreflight['toolchains']['generation']['php_version'] = '9.9.9';
        if (file_put_contents($artifactRoot . '/oci-preflight.json', agentEvaluationJson($phpDriftPreflight)) === false) {
            throw new RuntimeException('Unable to write the OCI PHP-drift control.');
        }
        $phpDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($phpDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($phpDriftScore, $run, $artifactRoot);
            },
            'Explanation OCI preflight requires the supported PHP 8.4 toolchain.',
        );

        $composerDriftPreflight = $ociPreflight;
        $composerDriftPreflight['toolchains']['scoring']['composer_version'] = '2.8.11';
        if (file_put_contents(
            $artifactRoot . '/oci-preflight.json',
            agentEvaluationJson($composerDriftPreflight),
        ) === false) {
            throw new RuntimeException('Unable to write the OCI Composer-drift control.');
        }
        $composerDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($composerDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($composerDriftScore, $run, $artifactRoot);
            },
            'Explanation generation and scoring toolchains must share PHP and Composer identities.',
        );

        if (file_put_contents($artifactRoot . '/oci-preflight.json', agentEvaluationJson($ociPreflight)) === false) {
            throw new RuntimeException('Unable to restore the OCI preflight evidence control.');
        }
        $transportDriftProxy = $proxy;
        $transportDriftProxy['ledger']['transport_tools']['sha256'] = str_repeat('a', 64);
        if (file_put_contents($artifactRoot . '/proxy.json', agentEvaluationJson($transportDriftProxy)) === false) {
            throw new RuntimeException('Unable to write the proxy transport-drift control.');
        }
        $transportDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($transportDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($transportDriftScore, $run, $artifactRoot);
            },
            'Explanation proxy evidence does not match the validated live run.',
        );

        foreach (
            [
                ['token_budget', 40_000],
                ['token_budget', 100_000],
                ['token_budget', 199_999],
                ['input_tokens', 999],
                ['output_tokens', 499],
                ['cached_tokens', 99],
                ['reasoning_tokens', 199],
                ['reserved_input', 1],
                ['reserved_output', 1],
                ['request_sha256', str_repeat('a', 64)],
                ['failure_reason', 'model_token_limit'],
                ['model', 'other-model'],
                ['reasoning_effort', 'medium'],
            ] as [$field, $value]
        ) {
            $ledgerDriftProxy = $proxy;
            $ledgerDriftProxy['ledger'][$field] = $value;
            if (file_put_contents($artifactRoot . '/proxy.json', agentEvaluationJson($ledgerDriftProxy)) === false) {
                throw new RuntimeException('Unable to write a proxy ledger-drift control.');
            }
            $ledgerDriftScore = agentEvaluationRefreshExplanationStructuralScore(
                $artifactRoot,
                $runBytes,
                $cliScore,
            );
            agentEvaluationExpectFailure(
                static function () use ($ledgerDriftScore, $run, $artifactRoot): void {
                    agentEvaluationValidateExplanationScoreArtifacts($ledgerDriftScore, $run, $artifactRoot);
                },
                'Explanation proxy ledger does not match the completed run usage and zero-reservation state.',
            );
        }

        $missingLedgerFieldProxy = $proxy;
        unset($missingLedgerFieldProxy['ledger']['token_budget']);
        if (file_put_contents(
            $artifactRoot . '/proxy.json',
            agentEvaluationJson($missingLedgerFieldProxy),
        ) === false) {
            throw new RuntimeException('Unable to write the missing proxy ledger-field control.');
        }
        $missingLedgerFieldScore = agentEvaluationRefreshExplanationStructuralScore(
            $artifactRoot,
            $runBytes,
            $cliScore,
        );
        agentEvaluationExpectFailure(
            static function () use ($missingLedgerFieldScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($missingLedgerFieldScore, $run, $artifactRoot);
            },
            'Explanation proxy ledger does not match the completed run usage and zero-reservation state.',
        );

        $spendingDriftProxy = $proxy;
        $spendingDriftProxy['ledger']['spending']['policy']['limit_units'] = 59_999_999;
        if (file_put_contents($artifactRoot . '/proxy.json', agentEvaluationJson($spendingDriftProxy)) === false) {
            throw new RuntimeException('Unable to write the proxy spending-drift control.');
        }
        $spendingDriftScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        agentEvaluationExpectFailure(
            static function () use ($spendingDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($spendingDriftScore, $run, $artifactRoot);
            },
            'Explanation proxy evidence does not bind the exact approved spending policy.',
        );

        $settlementDriftProxy = $proxy;
        $settlementDriftProxy['ledger']['spending']['settled_units'] = 977_499;
        if (file_put_contents($artifactRoot . '/proxy.json', agentEvaluationJson($settlementDriftProxy)) === false) {
            throw new RuntimeException('Unable to write the proxy settlement-drift control.');
        }
        $settlementDriftScore = agentEvaluationRefreshExplanationStructuralScore(
            $artifactRoot,
            $runBytes,
            $cliScore,
        );
        agentEvaluationExpectFailure(
            static function () use ($settlementDriftScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($settlementDriftScore, $run, $artifactRoot);
            },
            'Explanation proxy evidence does not match the validated live run.',
        );

        if (file_put_contents($artifactRoot . '/proxy.json', agentEvaluationJson($proxy)) === false) {
            throw new RuntimeException('Unable to restore the proxy evidence control.');
        }
        $cliScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        $structuralEvidence = agentEvaluationExplanationStructuralEvidenceDocument('live-model', $artifactRoot);
        $structuralEvidenceBytes = agentEvaluationJson($structuralEvidence);
        $scoreBytes = agentEvaluationJson($cliScore);
        $missingProxyBytes = file_get_contents($artifactRoot . '/proxy.json');
        if (!is_string($missingProxyBytes) || !unlink($artifactRoot . '/proxy.json')) {
            throw new RuntimeException('Unable to prepare the missing structural-evidence file control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($cliScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($cliScore, $run, $artifactRoot);
            },
            'explanation structural evidence artifact proxy.json must remain inside the run artifact root.',
        );
        if (file_put_contents($artifactRoot . '/proxy.json', $missingProxyBytes) === false) {
            throw new RuntimeException('Unable to restore the missing structural-evidence file control.');
        }

        $dependencyLockBytes = file_get_contents($artifactRoot . '/dependencies.lock');
        if (
            !is_string($dependencyLockBytes)
            || !unlink($artifactRoot . '/dependencies.lock')
            || !link($artifactRoot . '/approval.json', $artifactRoot . '/dependencies.lock')
        ) {
            throw new RuntimeException('Unable to prepare the aliased structural-evidence control.');
        }
        $aliasedDocument = $structuralEvidence;
        $aliasedDocument['artifacts']['dependencies.lock'] = hash('sha256', agentEvaluationJson($approval));
        $aliasedDocumentBytes = agentEvaluationJson($aliasedDocument);
        $aliasedScore = $cliScore;
        $aliasedScore['structural_evidence_sha256'] = hash('sha256', $aliasedDocumentBytes);
        $aliasedRun = $run;
        $aliasedRun['prepared_lock_sha256'] = hash('sha256', agentEvaluationJson($approval));
        if (file_put_contents($artifactRoot . '/structural-evidence.json', $aliasedDocumentBytes) === false) {
            throw new RuntimeException('Unable to write the aliased structural-evidence descriptor.');
        }
        agentEvaluationExpectFailure(
            static function () use ($aliasedScore, $aliasedRun, $artifactRoot): void {
                agentEvaluationValidateExplanationScoreArtifacts($aliasedScore, $aliasedRun, $artifactRoot);
            },
            'Run artifacts must not use hard-linked files.',
        );
        if (
            !unlink($artifactRoot . '/dependencies.lock')
            || file_put_contents($artifactRoot . '/dependencies.lock', $dependencyLockBytes) === false
        ) {
            throw new RuntimeException('Unable to restore the aliased structural-evidence control.');
        }

        $cliScore = agentEvaluationRefreshExplanationStructuralScore($artifactRoot, $runBytes, $cliScore);
        $structuralEvidence = agentEvaluationExplanationStructuralEvidenceDocument('live-model', $artifactRoot);
        $structuralEvidenceBytes = agentEvaluationJson($structuralEvidence);
        $scoreBytes = agentEvaluationJson($cliScore);
        $outerManifest = agentEvaluationWriteExplanationOuterEvidence($artifactRoot, $run, $cliScore);
        agentEvaluationValidateExplanationOuterEvidence($cliScore, $run, $artifactRoot);
        $outerManifestBytes = agentEvaluationJson($outerManifest);
        $validationBytes = (string) file_get_contents($artifactRoot . '/validation.json');
        foreach (['validation.json' => 'explanation validation evidence', 'evidence-manifest.json' => 'explanation evidence manifest'] as $missingName => $missingOwner) {
            $savedBytes = (string) file_get_contents($artifactRoot . '/' . $missingName);
            unlink($artifactRoot . '/' . $missingName);
            agentEvaluationExpectFailure(
                static function () use ($cliScore, $run, $artifactRoot): void {
                    agentEvaluationValidateExplanationOuterEvidence($cliScore, $run, $artifactRoot);
                },
                $missingOwner . ' must remain inside the run artifact root.',
            );
            file_put_contents($artifactRoot . '/' . $missingName, $savedBytes);
        }
        $wrongPhases = $outerManifest;
        $wrongPhases['observed_phases'] = ['prepare'];
        file_put_contents($artifactRoot . '/evidence-manifest.json', agentEvaluationJson($wrongPhases));
        agentEvaluationExpectFailure(
            static function () use ($cliScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationOuterEvidence($cliScore, $run, $artifactRoot);
            },
            'Explanation evidence manifest does not match its final lifecycle.',
        );
        $wrongInventory = $outerManifest;
        $wrongArtifacts = agentEvaluationRequireObject($outerManifest, 'artifacts', 'outer evidence control');
        $wrongArtifacts['response.txt'] = [
            ...agentEvaluationRequireObject($wrongArtifacts, 'response.txt', 'outer evidence control'),
            'sha256' => str_repeat('0', 64),
        ];
        $wrongInventory['artifacts'] = $wrongArtifacts;
        file_put_contents($artifactRoot . '/evidence-manifest.json', agentEvaluationJson($wrongInventory));
        agentEvaluationExpectFailure(
            static function () use ($cliScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationOuterEvidence($cliScore, $run, $artifactRoot);
            },
            'Explanation evidence manifest artifact response.txt does not match final evidence.',
        );
        file_put_contents($artifactRoot . '/evidence-manifest.json', $outerManifestBytes);
        $wrongValidation = agentEvaluationJsonFile($artifactRoot . '/validation.json');
        $wrongValidation['structural_status'] = 'fail';
        file_put_contents($artifactRoot . '/validation.json', agentEvaluationJson($wrongValidation));
        agentEvaluationExpectFailure(
            static function () use ($cliScore, $run, $artifactRoot): void {
                agentEvaluationValidateExplanationOuterEvidence($cliScore, $run, $artifactRoot);
            },
            'Explanation validation evidence does not match the replayed score.',
        );
        file_put_contents($artifactRoot . '/validation.json', $validationBytes);
        $reviewedScore = $cliScore;
        $reviewedScore['human_review'] = $passed['human_review'];
        $reviewedScore['correct_completion'] = true;
        file_put_contents($artifactRoot . '/score.json', agentEvaluationJson($reviewedScore));
        agentEvaluationValidateExplanationScoreRecord($reviewedScore, $task, $run, hash('sha256', $runBytes));
        agentEvaluationValidateExplanationOuterEvidence($reviewedScore, $run, $artifactRoot);
        file_put_contents($artifactRoot . '/score.json', $scoreBytes);
        $listResult = runBoundedMaintainerProcess(
            [PHP_BINARY, 'tools/agent-evaluation.php', 'list'],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        $listed = agentEvaluationJsonValue($listResult['stdout'], 'explanation CLI list output');
        $listedExplanation = is_array($listed) && array_is_list($listed) && $listed !== []
            ? agentEvaluationValueObject($listed[count($listed) - 1], 'explanation CLI list item')
            : null;
        agentEvaluationTest(
            $listResult['exit_code'] === 0
            && $listResult['stderr'] === ''
            && is_array($listed)
            && array_is_list($listed)
            && $listedExplanation === [
                'schema_version' => 3,
                'id' => AGENT_EVALUATION_EXPLANATION_TASK_ID,
                'revision' => 12,
                'kind' => 'explanation',
                'comparative_claims' => false,
            ],
            'The CLI must list the explicit explanation task.',
        );
        $promptResult = runBoundedMaintainerProcess(
            [PHP_BINARY, 'tools/agent-evaluation.php', 'prompt', AGENT_EVALUATION_EXPLANATION_TASK_ID],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $promptResult['exit_code'] === 0
            && $promptResult['stderr'] === ''
            && $promptResult['stdout'] === $sourcePrompt,
            'The CLI must emit the exact frozen explanation source prompt.',
        );
        $runResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-run',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $runResult === [
                'exit_code' => 0,
                'stdout' => "PASS agent evaluation run record: explain.file-profile-s3\n",
                'stderr' => '',
            ],
            'The CLI must validate a complete explanation run and its bound response.',
        );
        $scoreResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-score',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
                $artifactRoot . '/score.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $scoreResult === [
                'exit_code' => 0,
                'stdout' => "PASS agent evaluation score record: explain.file-profile-s3\n",
                'stderr' => '',
            ],
            'The CLI must validate a structurally complete pending explanation score.',
        );

        $splitRoot = $artifactRoot . '/split-score';
        if (
            !mkdir($splitRoot, 0700)
            || file_put_contents($splitRoot . '/score.json', $scoreBytes) === false
        ) {
            throw new RuntimeException('Unable to write the split-directory explanation score control.');
        }
        $splitScoreResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-score',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
                $splitRoot . '/score.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $splitScoreResult === [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => "FAIL agent evaluation: Explanation run and score records must share one existing evidence directory.\n",
            ],
            'The CLI must reject explanation run and score records from split evidence directories.',
        );

        if (file_put_contents($artifactRoot . '/cleanup.json', agentEvaluationJson($cleanup) . "\n") === false) {
            throw new RuntimeException('Unable to write the tampered structural-evidence CLI control.');
        }
        $tamperedStructuralResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-score',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
                $artifactRoot . '/score.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $tamperedStructuralResult === [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => "FAIL agent evaluation: explanation structural evidence artifact cleanup.json SHA-256 does not match its recorded hash.\n",
            ],
            'The CLI must reject a tampered structural-evidence artifact.',
        );
        if (file_put_contents($artifactRoot . '/cleanup.json', agentEvaluationJson($cleanup)) === false) {
            throw new RuntimeException('Unable to restore the structural-evidence CLI control.');
        }

        $mismatchedResponse = "A different retained response.\n";
        $mismatchedResponseRun = $run;
        $mismatchedResponseRun['response_sha256'] = hash('sha256', $mismatchedResponse);
        if (
            file_put_contents($artifactRoot . '/response.txt', $mismatchedResponse) === false
            || file_put_contents($artifactRoot . '/run.json', agentEvaluationJson($mismatchedResponseRun)) === false
        ) {
            throw new RuntimeException('Unable to write the response/event mismatch CLI control.');
        }
        $mismatchedResponseResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-run',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $mismatchedResponseResult === [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => "FAIL agent evaluation: Explanation response artifact must equal the last completed agent message.\n",
            ],
            'The CLI must reject a response that disagrees with the last completed agent message.',
        );
        if (
            file_put_contents($artifactRoot . '/response.txt', $response) === false
            || file_put_contents($artifactRoot . '/run.json', $runBytes) === false
        ) {
            throw new RuntimeException('Unable to restore the response/event CLI control.');
        }

        $tamperedRun = $run;
        $tamperedRun['response_sha256'] = str_repeat('e', 64);

        if (file_put_contents($artifactRoot . '/run.json', agentEvaluationJson($tamperedRun)) === false) {
            throw new RuntimeException('Unable to write the tampered explanation CLI run control.');
        }

        $tamperedRunResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-run',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $tamperedRunResult['exit_code'] === 1
            && $tamperedRunResult['stdout'] === ''
            && $tamperedRunResult['stderr'] === "FAIL agent evaluation: explanation response artifact SHA-256 does not match its recorded hash.\n",
            'The CLI must reject a tampered explanation run response binding.',
        );

        if (file_put_contents($artifactRoot . '/run.json', $runBytes) === false) {
            throw new RuntimeException('Unable to restore the explanation CLI run control.');
        }

        $tamperedScore = $cliScore;
        $tamperedScore['response_sha256'] = str_repeat('e', 64);

        if (file_put_contents($artifactRoot . '/score.json', agentEvaluationJson($tamperedScore)) === false) {
            throw new RuntimeException('Unable to write the tampered explanation CLI score control.');
        }

        $tamperedScoreResult = runBoundedMaintainerProcess(
            [
                PHP_BINARY,
                'tools/agent-evaluation.php',
                'validate-score',
                AGENT_EVALUATION_EXPLANATION_TASK_ID,
                $artifactRoot . '/run.json',
                $artifactRoot . '/score.json',
            ],
            $repositoryRoot,
            null,
            30_000,
            AGENT_EVALUATION_MAX_JSON_BYTES,
            AGENT_EVALUATION_MAX_JSON_BYTES,
        );
        agentEvaluationTest(
            $tamperedScoreResult['exit_code'] === 1
            && $tamperedScoreResult['stdout'] === ''
            && $tamperedScoreResult['stderr'] === "FAIL agent evaluation: Explanation score artifacts do not match the validated task and run records.\n",
            'The CLI must reject a tampered explanation score response binding.',
        );

        $nonEmptyPatch = $run;
        $nonEmptyPatch['candidate_patch_sha256'] = hash('sha256', "mutation\n");

        if (file_put_contents($artifactRoot . '/candidate.patch', "mutation\n") === false) {
            throw new RuntimeException('Unable to mutate the explanation candidate-patch control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($nonEmptyPatch, $artifactRoot): void {
                agentEvaluationValidateExplanationRunArtifacts($nonEmptyPatch, $artifactRoot);
            },
            'Explanation candidate patch must be empty.',
        );

        if (file_put_contents($artifactRoot . '/candidate.patch', '') === false) {
            throw new RuntimeException('Unable to restore the explanation candidate-patch control.');
        }

        $aliasedResponse = $run;
        $aliasedResponse['response_path'] = $aliasedResponse['events_path'];
        $aliasedResponse['response_sha256'] = $aliasedResponse['events_sha256'];
        agentEvaluationExpectFailure(
            static function () use ($aliasedResponse, $artifactRoot): void {
                agentEvaluationValidateExplanationRunArtifacts($aliasedResponse, $artifactRoot);
            },
            'Explanation run artifacts must use distinct relative paths.',
        );

        if (file_put_contents($artifactRoot . '/response.txt', $response . "tampered\n") === false) {
            throw new RuntimeException('Unable to tamper with explanation response control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($run, $artifactRoot): void {
                agentEvaluationValidateExplanationRunArtifacts($run, $artifactRoot);
            },
            'explanation response artifact SHA-256 does not match its recorded hash.',
        );
        $emptyResponse = $run;
        $emptyResponse['response_sha256'] = hash('sha256', '');

        if (file_put_contents($artifactRoot . '/response.txt', '') === false) {
            throw new RuntimeException('Unable to empty the explanation response control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($emptyResponse, $artifactRoot): void {
                agentEvaluationValidateExplanationRunArtifacts($emptyResponse, $artifactRoot);
            },
            'Explanation response artifact must contain bounded non-empty text.',
        );
    } finally {
        agentEvaluationRemoveDirectory($artifactRoot);
    }

    $copiedKit = sys_get_temp_dir() . '/phpthis-agent-evaluation-explanation-kit-' . bin2hex(random_bytes(8));

    try {
        agentEvaluationCopyDirectory($kit, $copiedKit);
        $promptPath = $copiedKit . '/tasks/' . AGENT_EVALUATION_EXPLANATION_TASK_ID . '/prompt.md';
        $promptBytes = file_get_contents($promptPath);

        if (!is_string($promptBytes) || file_put_contents($promptPath, $promptBytes . "tampered\n") === false) {
            throw new RuntimeException('Unable to mutate the copied explanation prompt control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($copiedKit): void {
                agentEvaluationValidateKit($copiedKit);
            },
            'explanation prompt SHA-256 does not match its recorded hash.',
        );

        if (file_put_contents($promptPath, $promptBytes) === false) {
            throw new RuntimeException('Unable to restore the copied explanation prompt control.');
        }

        $rubricPath = $copiedKit . '/tasks/' . AGENT_EVALUATION_EXPLANATION_TASK_ID . '/rubric.md';
        $rubricBytes = file_get_contents($rubricPath);

        if (!is_string($rubricBytes) || file_put_contents($rubricPath, $rubricBytes . "tampered\n") === false) {
            throw new RuntimeException('Unable to mutate the copied explanation rubric control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($copiedKit): void {
                agentEvaluationValidateKit($copiedKit);
            },
            'explanation rubric SHA-256 does not match its recorded hash.',
        );

        if (file_put_contents($rubricPath, $rubricBytes) === false) {
            throw new RuntimeException('Unable to restore the copied explanation rubric control.');
        }

        $manifestPath = $copiedKit . '/tasks/' . AGENT_EVALUATION_EXPLANATION_TASK_ID . '/task.json';
        $manifestBytes = file_get_contents($manifestPath);

        if (!is_string($manifestBytes) || file_put_contents($manifestPath, $manifestBytes . "\n") === false) {
            throw new RuntimeException('Unable to mutate the copied explanation manifest control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($copiedKit): void {
                agentEvaluationValidateKit($copiedKit);
            },
            'Task explain.file-profile-s3 manifest SHA-256 does not match its pinned revision.',
        );

        if (file_put_contents($manifestPath, $manifestBytes) === false) {
            throw new RuntimeException('Unable to restore the copied explanation manifest control.');
        }

        $effectivePromptManifest = agentEvaluationValueObject(
            agentEvaluationJsonValue($manifestBytes, 'copied explanation manifest'),
            'copied explanation manifest',
        );
        $effectivePromptDescriptor = agentEvaluationRequireObject(
            $effectivePromptManifest,
            'prompt',
            'copied explanation manifest',
        );
        foreach (['2d9f731008a4a0d41c9ccc32ceabc2365f39a547be389f35b8f98e6fd496b043',
            '33038bfb324e53b2ab0704284dc78087ff85f948a2170e19b1f10925ecff79f6',
            '0d62291e24f81b8a5a68e6bc3b825682c14ca23f3f4574f48a68d0deede699a6',
            '02dca53c3943d6f5cf06ca485daee2aebee79f637fe114b261964d058a27e21e',
            'bc69afd57bde6a57d6ba39540340a8b23f285df5ae66959577fe1b65cb85bdfa',
            'e0d43884d6c38a1ab2eba3e6575da15d13f9f9450c48683225c020a42f562b99',
            '4e9fc1550b7acf8aea364eac05050be5720920806a1a28c858267a3b24a1ec1c',
            '12a4d8099c764e0ba77641cfdb5077e6e184900732e375d68ee47c6adeddeb12'] as $oldPromptHash) {
            $effectivePromptDescriptor['effective_sha256'] = $oldPromptHash;
            $effectivePromptManifest['prompt'] = $effectivePromptDescriptor;

            if (file_put_contents($manifestPath, agentEvaluationJson($effectivePromptManifest)) === false) {
                throw new RuntimeException('Unable to prepare the older effective-prompt rejection control.');
            }

            agentEvaluationExpectFailure(
                static function () use ($copiedKit): void {
                    agentEvaluationExplanationTaskDocument($copiedKit, AGENT_EVALUATION_EXPLANATION_TASK_ID);
                },
                'Explanation effective prompt hash does not match the exact source and suffix bytes.',
            );
        }

        if (file_put_contents($manifestPath, $manifestBytes) === false) {
            throw new RuntimeException('Unable to restore the copied explanation effective-prompt control.');
        }

        foreach ([40_000, 100_000] as $oldTokenBudget) {
            $staleBudgetManifest = agentEvaluationValueObject(
                agentEvaluationJsonValue($manifestBytes, 'copied explanation manifest'),
                'copied explanation manifest',
            );
            $staleBudgets = agentEvaluationRequireObject(
                $staleBudgetManifest,
                'budgets',
                'copied explanation manifest',
            );
            $staleBudgets['model_tokens'] = $oldTokenBudget;
            $staleBudgetManifest['budgets'] = $staleBudgets;

            if (file_put_contents($manifestPath, agentEvaluationJson($staleBudgetManifest)) === false) {
                throw new RuntimeException('Unable to prepare the stale explanation budget control.');
            }

            agentEvaluationExpectFailure(
                static function () use ($copiedKit): void {
                    agentEvaluationExplanationTaskDocument($copiedKit, AGENT_EVALUATION_EXPLANATION_TASK_ID);
                },
                'Explanation task budgets must equal the fixed bounded protocol.',
            );
        }

        if (file_put_contents($manifestPath, $manifestBytes) === false) {
            throw new RuntimeException('Unable to restore the copied explanation budget control.');
        }

        $schemaPath = $copiedKit . '/schema/score-v3.schema.json';
        $schemaBytes = file_get_contents($schemaPath);

        if (!is_string($schemaBytes) || file_put_contents($schemaPath, $schemaBytes . "\n") === false) {
            throw new RuntimeException('Unable to mutate the copied explanation schema control.');
        }

        agentEvaluationExpectFailure(
            static function () use ($copiedKit): void {
                agentEvaluationValidateKit($copiedKit);
            },
            'schema/score-v3.schema.json SHA-256 does not match its recorded hash.',
        );
    } finally {
        agentEvaluationRemoveDirectory($copiedKit);
    }
}

function agentEvaluationComparisonInstrumentationControls(string $kit): void
{
    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-instrumentation-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700)) {
        throw new RuntimeException('Unable to prepare comparison instrumentation controls.');
    }
    $program = <<<'PHP'
<?php
declare(strict_types=1);

$fixture = $argv[1];
$dataRoot = $argv[2];
require $argv[3];
require $fixture . '/src/Evaluation/ObservationTrace.php.fixture';
require $fixture . '/src/Evaluation/ObservedStatement.php.fixture';
require $fixture . '/src/Evaluation/ObservedPDO.php.fixture';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function database(string $path): PDO
{
    $observer = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $observer->exec('CREATE TABLE entries (id INTEGER PRIMARY KEY, value TEXT NOT NULL UNIQUE)');
    return $observer;
}

$observer = database($dataRoot . '/counts.sqlite');
$trace = new App\Evaluation\ObservationTrace();
$connection = new App\Evaluation\ObservedPDO('sqlite:' . $dataRoot . '/counts.sqlite', $trace);
$failedSql = 'SELECT missing_column FROM entries';
$frameworkTrace = new PHPThis\Database\QueryTrace(256);
$framework = PHPThis\Database\Connection::connect('sqlite:' . $dataRoot . '/counts.sqlite', new PHPThis\Database\QueryBudget(256), $frameworkTrace);
try {
    $framework->selectAllRows($failedSql, []);
    throw new RuntimeException('Expected framework prepare failure.');
} catch (PDOException) {
}
try {
    $connection->prepare($failedSql);
    throw new RuntimeException('Expected native prepare failure.');
} catch (PDOException) {
}
$failedPreparation = $trace->snapshot();
$frameworkPreparation = $frameworkTrace->snapshot();
check($failedPreparation['statements'] === 1 && $failedPreparation['failures'] === 1
    && $failedPreparation['statements'] === $frameworkPreparation['statements']
    && $failedPreparation['failures'] === $frameworkPreparation['failures'],
    'Native preparation failures must match framework statement and failure observations.');

$insertSql = 'INSERT INTO entries (id, value) VALUES (:id, :value)';
$statement = $connection->prepare($insertSql);
check($statement instanceof PDOStatement && $trace->snapshot()['statements'] === 1,
    'Successful prepare must not count an additional executed statement.');
$statement->execute(['id' => 1, 'value' => 'first']);
$statement->execute(['id' => 2, 'value' => 'second']);
try {
    $statement->execute(['id' => 1, 'value' => 'duplicate']);
    throw new RuntimeException('Expected native execution failure.');
} catch (PDOException) {
}
$queried = $connection->query('SELECT id FROM entries ORDER BY id');
check($queried instanceof PDOStatement, 'The query control requires a real observed statement.');
$queried->closeCursor();
$queried->execute();
$connection->query('SELECT id FROM entries ORDER BY id');
$connection->exec("UPDATE entries SET value = 'updated' WHERE id = 1");
$connection->exec("UPDATE entries SET value = 'updated-again' WHERE id = 1");
try {
    $connection->query($failedSql);
    throw new RuntimeException('Expected query failure.');
} catch (PDOException) {
}
try {
    $connection->exec('UPDATE missing_table SET value = 1');
    throw new RuntimeException('Expected exec failure.');
} catch (PDOException) {
}
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
check($connection->prepare($failedSql) === false, 'Silent prepare failure must remain false.');
check($statement->execute(['id' => 1, 'value' => 'duplicate']) === false, 'Silent execute failure must remain false.');
check($connection->exec('UPDATE missing_table SET value = 1') === false, 'Silent exec failure must remain false.');
check($connection->query($failedSql) === false, 'Silent query failure must remain false.');
check($connection->exec("UPDATE entries SET value = 'unused' WHERE id = 999") === 0, 'Zero affected rows must remain success.');
check($statement->execute(['id' => 3, 'value' => 'third']), 'Successful reuse after failure must remain observable.');
$snapshot = $trace->snapshot();
check($snapshot['statements'] === 17 && $snapshot['failures'] === 8 && $snapshot['truncated'] === false,
    'Every execution and failed preparation must be counted exactly once.');
$insertObservation = null;
foreach ($snapshot['queries'] as $query) {
    if ($query['fingerprint'] === 'sha256:' . hash('sha256', $insertSql)) {
        $insertObservation = $query;
    }
}
check($insertObservation === ['fingerprint' => 'sha256:' . hash('sha256', $insertSql), 'executions' => 5, 'failures' => 2],
    'Repeated prepared executions must preserve their one shared fingerprint and actual failures.');
check($observer->query('SELECT COUNT(*) FROM entries')->fetchColumn() === 3,
    'The separate observer must see only the three successful insertions.');

$bounds = [];
foreach (['exec', 'query', 'execute', 'prepare'] as $operation) {
    $path = $dataRoot . '/bound-' . $operation . '.sqlite';
    $durable = database($path);
    $boundedTrace = new App\Evaluation\ObservationTrace();
    $bounded = new App\Evaluation\ObservedPDO('sqlite:' . $path, $boundedTrace);
    $preparedMutation = $bounded->prepare("INSERT INTO entries (id, value) VALUES (257, 'outside-bound')");
    $read = $bounded->prepare('SELECT 1');
    check($preparedMutation instanceof PDOStatement && $read instanceof PDOStatement, 'Bound controls require real prepared statements.');
    for ($index = 0; $index < 256; $index++) {
        $read->execute();
        $read->closeCursor();
    }
    $rejected = false;
    try {
        match ($operation) {
            'exec' => $bounded->exec("INSERT INTO entries (id, value) VALUES (257, 'outside-bound')"),
            'query' => $bounded->query("INSERT INTO entries (id, value) VALUES (257, 'outside-bound')"),
            'execute' => $preparedMutation->execute(),
            'prepare' => $bounded->prepare('SELECT missing_column FROM entries'),
        };
    } catch (RuntimeException $exception) {
        $rejected = $exception::class === RuntimeException::class && $exception->getMessage() === 'Observation query bound exceeded.';
    }
    check($rejected, 'The 257th statement must fail before its native operation.');
    $limited = $boundedTrace->snapshot();
    check($limited['statements'] === 256 && $limited['failures'] === 0 && $limited['truncated'] === false,
        'Rejected over-budget work must not fabricate an executed statement or failure.');
    check($durable->query('SELECT COUNT(*) FROM entries')->fetchColumn() === 0,
        'The separate observer must prove no over-budget durable mutation.');
    $bounds[$operation] = ['statements' => $limited['statements'], 'durable_rows' => 0];
}
fwrite(STDOUT, json_encode(['prepare_failure' => ['statements' => 1, 'failures' => 1],
    'executions' => ['statements' => $snapshot['statements'], 'failures' => $snapshot['failures']],
    'bounds' => $bounds], JSON_THROW_ON_ERROR) . "\n");
PHP;
    try {
        $path = $temporary . '/control.php';
        if (file_put_contents($path, $program) !== strlen($program)) {
            throw new RuntimeException('Unable to write comparison instrumentation controls.');
        }
        foreach (['change.protected-endpoint', 'repair.transaction-rollback', 'change.filtered-collection'] as $taskId) {
            $dataRoot = $temporary . '/' . $taskId;
            if (!mkdir($dataRoot, 0700)) {
                throw new RuntimeException('Unable to prepare a comparison instrumentation database.');
            }
            $result = runBoundedMaintainerProcess(
                [PHP_BINARY, $path, $kit . '/tasks/' . $taskId . '/fixtures/plain-php', $dataRoot, dirname($kit, 2) . '/vendor/autoload.php'],
                $temporary,
                [],
                20_000,
                65_536,
                65_536,
            );
            agentEvaluationTest($result['exit_code'] === 0 && $result['stderr'] === '',
                'The native comparison observer controls must pass: ' . $result['stdout'] . $result['stderr']);
            $observed = agentEvaluationValueObject(agentEvaluationJsonValue($result['stdout'], 'comparison instrumentation result'), 'comparison instrumentation result');
            agentEvaluationTest(($observed['executions'] ?? null) instanceof stdClass,
                'The bounded native observer control must retain its completed count observations.');
        }
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
}

function agentEvaluationComparisonRouteControls(string $kit): void
{
    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-routes-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700)) {
        throw new RuntimeException('Unable to prepare comparison route controls.');
    }
    $program = <<<'PHP'
<?php
declare(strict_types=1);

$fixture = $argv[1];
$taskId = $argv[2];
$condition = $argv[3];
require $argv[4];
require $fixture . '/src/Evaluation/Principal.php.fixture';
require $fixture . '/src/Evaluation/Tenant.php.fixture';
require $fixture . '/src/Evaluation/Failure.php.fixture';
require $fixture . '/src/Evaluation/Policy.php.fixture';
if ($condition === 'plain-php') {
    require $fixture . '/src/Evaluation/Response.php.fixture';
    require $fixture . '/src/Evaluation/ObservationTrace.php.fixture';
    require $fixture . '/src/Evaluation/ObservedStatement.php.fixture';
    require $fixture . '/src/Evaluation/ObservedPDO.php.fixture';
}
require $fixture . '/src/Evaluation/DocumentHandler.php.fixture';
require $fixture . '/src/Evaluation/Application.php.fixture';
$trace = $condition === 'phpthis' ? new PHPThis\Database\QueryTrace(256) : new App\Evaluation\ObservationTrace();
$connection = $condition === 'phpthis'
    ? PHPThis\Database\Connection::connect('sqlite::memory:', new PHPThis\Database\QueryBudget(256), $trace)
    : new App\Evaluation\ObservedPDO('sqlite::memory:', $trace);
$policy = new App\Evaluation\Policy(false, 7, 42, true);
$application = new App\Evaluation\Application($connection, $policy);
$method = $taskId === 'repair.transaction-rollback' ? 'POST' : 'GET';
$suffix = $taskId === 'change.protected-endpoint' ? '/1' : '';
$path = '/accounts/42/documents' . $suffix;
$headers = ['authorization' => 'Bearer public-fixture'];
$expectedHeaders = ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'];
$paths = ['', 'relative', '/absent', $path . ' ', $path . "\t", $path . "\0", $path . '?query', $path . '#fragment',
    '/accounts/0/documents' . $suffix, '/accounts/042/documents' . $suffix,
    '/accounts/-1/documents' . $suffix, '/accounts/' . PHP_INT_MAX . '0/documents' . $suffix];
if ($suffix !== '') {
    $paths[] = '/accounts/42/documents/' . PHP_INT_MAX . '0';
}
$requests = [];
foreach ($paths as $invalidPath) {
    $requests[] = [$method, $invalidPath];
}
foreach (['', strtolower($method), $method . ' ', 'OTHER', $method === 'GET' ? 'POST' : 'GET'] as $invalidMethod) {
    $requests[] = [$invalidMethod, $path];
}
foreach ($requests as [$requestMethod, $requestPath]) {
    $response = $application->handle($requestMethod, $requestPath, [], $headers, '');
    if ($response->status !== 404 || $response->headers !== $expectedHeaders
        || $response->body !== "{\"error\":{\"code\":\"not_found\"}}\n"
        || $policy->steps() !== [] || $trace->snapshot()['statements'] !== 0) {
        throw new RuntimeException('Malformed or unmatched routes must return normalized404 before policy or SQL.');
    }
}
$health = $application->handle('GET', '/health', [], [], '');
if ($health->status !== 200 || $health->headers !== $expectedHeaders || $health->body !== "{\"status\":\"ok\"}\n"
    || $policy->steps() !== [] || $trace->snapshot()['statements'] !== 0) {
    throw new RuntimeException('The unrelated health route must remain unchanged.');
}
if ($condition === 'phpthis') {
    $invalidHeader = $application->handle($method, $path, [], ['Authorization' => 'invalid-native-header-name'], '');
    if ($invalidHeader->status !== 500 || $invalidHeader->headers !== $expectedHeaders
        || $invalidHeader->body !== "{\"error\":{\"code\":\"internal_server_error\"}}\n" || $policy->steps() !== []) {
        throw new RuntimeException('A matched route must still construct Request and preserve non-path failure mapping.');
    }
}
$valid = $application->handle($method, $path, [], $headers, '');
$expectedStatus = $taskId === 'change.protected-endpoint' ? 404 : 401;
$expectedSteps = $taskId === 'change.protected-endpoint' ? [] : ['authenticate'];
if ($valid->status !== $expectedStatus || $valid->headers !== $expectedHeaders
    || $policy->steps() !== $expectedSteps || $trace->snapshot()['statements'] !== 0) {
    throw new RuntimeException('Matched routes must retain their existing stub or authentication behavior.');
}
fwrite(STDOUT, json_encode(['rejected_routes' => count($requests), 'health_status' => $health->status,
    'matched_status' => $valid->status, 'statements' => $trace->snapshot()['statements']], JSON_THROW_ON_ERROR) . "\n");
PHP;
    try {
        $path = $temporary . '/control.php';
        if (file_put_contents($path, $program) !== strlen($program)) {
            throw new RuntimeException('Unable to write comparison route controls.');
        }
        foreach (['change.protected-endpoint', 'repair.transaction-rollback', 'change.filtered-collection'] as $taskId) {
            foreach (['phpthis', 'plain-php'] as $condition) {
                $result = runBoundedMaintainerProcess(
                    [PHP_BINARY, $path, $kit . '/tasks/' . $taskId . '/fixtures/' . $condition,
                        $taskId, $condition, dirname($kit, 2) . '/vendor/autoload.php'],
                    $temporary,
                    [],
                    20_000,
                    65_536,
                    65_536,
                );
                agentEvaluationTest($result['exit_code'] === 0 && $result['stderr'] === '',
                    'The matched route controls must pass: ' . $result['stdout'] . $result['stderr']);
                $observed = agentEvaluationValueObject(agentEvaluationJsonValue($result['stdout'], 'comparison route result'), 'comparison route result');
                agentEvaluationTest(($observed['rejected_routes'] ?? null) === ($taskId === 'change.protected-endpoint' ? 18 : 17)
                    && ($observed['statements'] ?? null) === 0,
                    'Every task and condition must complete all malformed-route controls without SQL.');
            }
        }
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
}

function agentEvaluationComparisonProtocolControls(string $kit): void
{
    $document = agentEvaluationJsonFile($kit . '/comparison-v1.json');
    $protocol = agentEvaluationComparisonProtocol($kit);
    agentEvaluationTest(
        $protocol['trials_per_task_condition'] === 10
        && $protocol['conditions'] === ['phpthis', 'plain-php']
        && $protocol['budgets'] === ['model_tokens' => 40_000, 'wall_seconds' => 1_200, 'repair_turns' => 0, 'command_output_bytes' => 4_194_304]
        && $protocol['reporting']['primary_metric'] === 'correct_completion_rate'
        && $protocol['sha256'] === hash_file('sha256', $kit . '/comparison-v1.json'),
        'The comparison protocol must freeze matched finite trials and its reviewed identity.',
    );
    $budgets = agentEvaluationRequireObject($document, 'budgets', 'protocol control');
    $reporting = agentEvaluationRequireObject($document, 'reporting', 'protocol control');
    foreach ([
        ['trials_per_task_condition' => 9],
        ['conditions' => ['plain-php', 'phpthis']],
        ['budgets' => [...$budgets, 'repair_turns' => 1]],
        ['budgets' => [...$budgets, 'model_tokens' => 40_001]],
        ['holdout_policy' => 'public-smoke'],
        ['attempt_policy' => 'replace-failures'],
        ['reporting' => [...$reporting, 'denominator' => 'completed-trials']],
        ['reporting' => [...$reporting, 'unknown_metrics' => 'zero']],
        ['reporting' => [...$reporting, 'human_review' => 'optional']],
    ] as $mutation) {
        agentEvaluationExpectFailure(
            static function () use ($document, $mutation): void {
                agentEvaluationValidateComparisonProtocolDocument([...$document, ...$mutation]);
            },
            'Comparison protocol fields do not match the fixed reviewed version.',
        );
    }

    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-comparison-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700) || !mkdir($temporary . '/fixture', 0755)) {
        throw new RuntimeException('Unable to prepare comparison fixture controls.');
    }
    try {
        $directory = realpath($temporary . '/fixture');
        if (!is_string($directory)) {
            throw new RuntimeException('Unable to resolve comparison fixture controls.');
        }
        $source = "<?php\ndeclare(strict_types=1);\n";
        $readme = "One two\nthree\n";
        if (file_put_contents($directory . '/Example.php.fixture', $source) !== strlen($source)
            || file_put_contents($directory . '/README.md', $readme) !== strlen($readme)
            || !chmod($directory . '/Example.php.fixture', 0644) || !chmod($directory . '/README.md', 0644)) {
            throw new RuntimeException('Unable to write comparison fixture controls.');
        }
        $fixture = agentEvaluationComparisonFixture($directory);
        $expectedManifest = '100644 ' . hash('sha256', $source) . " Example.php\n"
            . '100644 ' . hash('sha256', $readme) . " README.md\n";
        agentEvaluationTest(
            $fixture['manifest'] === $expectedManifest && $fixture['sha256'] === hash('sha256', $expectedManifest)
            && $fixture['bytes'] === strlen($source) + strlen($readme),
            'Comparison source identities must bind exact materialized names, bytes, and modes.',
        );
        $context = ['schema_version' => 1, 'files' => [['path' => 'README.md', 'sha256' => hash('sha256', $readme)]]];
        if (file_put_contents($temporary . '/context.json', agentEvaluationJson($context)) === false) {
            throw new RuntimeException('Unable to write comparison context control.');
        }
        $measured = agentEvaluationComparisonSourceContext($temporary . '/context.json', $fixture['files']);
        agentEvaluationTest(
            $measured['scope'] === 'source-only' && $measured['bytes'] === strlen($readme) && $measured['words'] === 3,
            'Source context accounting must state its scope and exact byte/ASCII-whitespace word method.',
        );
        if (file_put_contents($directory . '/Example.php', $source) !== strlen($source) || !chmod($directory . '/Example.php', 0644)) {
            throw new RuntimeException('Unable to write materialization collision control.');
        }
        try {
            agentEvaluationExpectFailure(
                static function () use ($directory): void {
                    agentEvaluationComparisonFixture($directory);
                },
                'Comparison fixture materialization collides or exceeds its file bound.',
            );
        } finally {
            unlink($directory . '/Example.php');
        }
        if (!symlink($temporary . '/context.json', $directory . '/escaped.json')) {
            throw new RuntimeException('Unable to create comparison fixture containment control.');
        }
        try {
            agentEvaluationExpectFailure(
                static function () use ($directory): void {
                    agentEvaluationComparisonFixture($directory);
                },
                'Comparison fixture contains an unsupported entry or exceeds its entry bound.',
            );
        } finally {
            unlink($directory . '/escaped.json');
        }
        $policy = ['allowed_existing_paths' => ['Example.php'], 'allowed_new_paths' => [],
            'protected_paths' => ['.git', 'composer.json', 'composer.lock', 'vendor', 'evaluation/observe.php'],
            'max_changed_files' => 1, 'max_added_lines' => 100, 'max_deleted_lines' => 100];
        $validated = agentEvaluationComparisonWorkspacePolicy($policy, $fixture['files']);
        agentEvaluationTest($validated['allowed_new_paths'] === [], 'A repair task can preserve an empty new-file allowance in v2.');
        agentEvaluationExpectFailure(
            static function () use ($policy, $fixture): void {
                agentEvaluationComparisonWorkspacePolicy([...$policy, 'allowed_new_paths' => ['vendor/forged.php']], $fixture['files']);
            },
            'Comparison permitted paths must not overlap protected paths.',
        );
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
}

function agentEvaluationComparisonSourceContextBoundsControls(): void
{
    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-context-bounds-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700)) {
        throw new RuntimeException('Unable to prepare comparison context boundary controls.');
    }
    try {
        $directory = $temporary . '/fixture';
        if (!mkdir($directory, 0755)) {
            throw new RuntimeException('Unable to prepare comparison context boundary fixture.');
        }
        $directory = realpath($directory);
        if (!is_string($directory)) {
            throw new RuntimeException('Unable to resolve comparison context boundary fixture.');
        }
        $entries = [];
        $bytes = 0;
        for ($index = 0; $index < 4_096; $index++) {
            $relative = sprintf('reference-%04d.md', $index);
            $source = "Reference {$index}\n";
            if (file_put_contents($directory . '/' . $relative, $source) !== strlen($source)
                || !chmod($directory . '/' . $relative, 0644)) {
                throw new RuntimeException('Unable to write comparison context boundary source.');
            }
            $entries[] = ['path' => $relative, 'sha256' => hash('sha256', $source)];
            $bytes += strlen($source);
        }
        $fixture = agentEvaluationComparisonFixture($directory);
        $path = $temporary . '/context.json';
        $context = ['schema_version' => 1, 'files' => $entries];
        $encoded = agentEvaluationJson($context);
        if (file_put_contents($path, $encoded) !== strlen($encoded)) {
            throw new RuntimeException('Unable to write comparison context boundary manifest.');
        }
        $measured = agentEvaluationComparisonSourceContext($path, $fixture['files']);
        agentEvaluationTest(
            count($fixture['files']) === 4_096 && $fixture['bytes'] === $bytes
            && $measured === ['scope' => 'source-only', 'files' => $entries, 'bytes' => $bytes, 'words' => 8_192],
            'Source context must admit and fully account for every exact file at the fixture file limit.',
        );
        foreach ([
            ['path' => $entries[4_095]['path'], 'sha256' => str_repeat('0', 64)],
            ['path' => 'reference-missing.md', 'sha256' => $entries[4_095]['sha256']],
        ] as $invalid) {
            $changed = $entries;
            $changed[4_095] = $invalid;
            $encoded = agentEvaluationJson(['schema_version' => 1, 'files' => $changed]);
            if (file_put_contents($path, $encoded) !== strlen($encoded)) {
                throw new RuntimeException('Unable to write comparison context identity boundary control.');
            }
            agentEvaluationExpectFailure(
                static function () use ($path, $fixture): void {
                    agentEvaluationComparisonSourceContext($path, $fixture['files']);
                },
                'Comparison source context must name unique exact materialized fixture files.',
            );
        }
        // Reuse an admitted file so the count rejection must precede duplicate validation.
        $entries[] = $entries[0];
        $encoded = agentEvaluationJson(['schema_version' => 1, 'files' => $entries]);
        if (file_put_contents($path, $encoded) !== strlen($encoded)) {
            throw new RuntimeException('Unable to write comparison context over-limit control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($path, $fixture): void {
                agentEvaluationComparisonSourceContext($path, $fixture['files']);
            },
            'Comparison source context must use the fixed bounded manifest version.',
        );
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
}

function agentEvaluationWriteComparisonControl(string $path, string $bytes): void
{
    if (file_put_contents($path, $bytes) !== strlen($bytes) || !chmod($path, 0644)) {
        throw new RuntimeException('Unable to write one shared-reference control.');
    }
    clearstatcache(true, $path);
}

function agentEvaluationComparisonSharedReferenceControls(): void
{
    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-shared-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700) || !mkdir($temporary . '/local', 0755) || !mkdir($temporary . '/shared', 0755)) {
        throw new RuntimeException('Unable to prepare shared-reference controls.');
    }
    try {
        $local = realpath($temporary . '/local');
        $shared = realpath($temporary . '/shared');
        if (!is_string($local) || !is_string($shared)) {
            throw new RuntimeException('Unable to resolve shared-reference controls.');
        }
        $localBytes = "Local fixture\n";
        $referenceBytes = "Pinned diagnostic\n";
        agentEvaluationWriteComparisonControl($local . '/README.md', $localBytes);
        agentEvaluationWriteComparisonControl($shared . '/example.type.md', $referenceBytes);
        $raw = agentEvaluationComparisonFixture($local);
        $composed = agentEvaluationComparisonFixture($local, $shared);
        $lines = ['100644 ' . hash('sha256', $localBytes) . ' README.md',
            '100644 ' . hash('sha256', $referenceBytes) . ' docs/phpstan/example.type.md'];
        sort($lines, SORT_STRING);
        $manifest = implode("\n", $lines) . "\n";
        agentEvaluationTest(
            array_keys($raw['files']) === ['README.md']
            && $composed['manifest'] === $manifest && $composed['sha256'] === hash('sha256', $manifest)
            && $composed['bytes'] === strlen($localBytes) + strlen($referenceBytes)
            && $composed['files']['docs/phpstan/example.type.md']['source_path'] === $shared . '/example.type.md',
            'Shared composition must be explicit and preserve exact materialized paths, bytes, modes and source identities.',
        );
        agentEvaluationExpectFailure(
            static function () use ($local, $temporary): void {
                agentEvaluationComparisonFixture($local, $temporary . '/missing');
            },
            'Comparison fixture must be one canonical real directory.',
        );
        if (!symlink($shared, $temporary . '/linked-root')) {
            throw new RuntimeException('Unable to create shared-root symlink control.');
        }
        try {
            agentEvaluationExpectFailure(
                static function () use ($local, $temporary): void {
                    agentEvaluationComparisonFixture($local, $temporary . '/linked-root');
                },
                'Comparison fixture must be one canonical real directory.',
            );
        } finally {
            unlink($temporary . '/linked-root');
        }
        if (!symlink($local . '/README.md', $shared . '/linked.md')) {
            throw new RuntimeException('Unable to create shared-member symlink control.');
        }
        try {
            agentEvaluationExpectFailure(
                static function () use ($local, $shared): void {
                    agentEvaluationComparisonFixture($local, $shared);
                },
                'Comparison fixture contains an unsupported entry or exceeds its entry bound.',
            );
        } finally {
            unlink($shared . '/linked.md');
        }
        if (!link($shared . '/example.type.md', $shared . '/linked.md')) {
            throw new RuntimeException('Unable to create shared-member hardlink control.');
        }
        try {
            agentEvaluationExpectFailure(
                static function () use ($local, $shared): void {
                    agentEvaluationComparisonFixture($local, $shared);
                },
                'Comparison fixture file must have one regular reviewed identity and mode.',
            );
        } finally {
            unlink($shared . '/linked.md');
            clearstatcache();
        }
        if (!chmod($shared . '/example.type.md', 0600)) {
            throw new RuntimeException('Unable to prepare shared-member mode control.');
        }
        try {
            agentEvaluationExpectFailure(
                static function () use ($local, $shared): void {
                    agentEvaluationComparisonFixture($local, $shared);
                },
                'Comparison fixture file must have one regular reviewed identity and mode.',
            );
        } finally {
            chmod($shared . '/example.type.md', 0644);
            clearstatcache();
        }
        foreach (['empty', 'identical', 'renamed', 'case-folded', 'parent-file'] as $collision) {
            if ($collision === 'parent-file') {
                agentEvaluationWriteComparisonControl($local . '/docs', 'parent');
            } else {
                $prefix = $collision === 'case-folded' ? '/DOCS/PHPSTAN' : '/docs/phpstan';
                if ($collision === 'renamed') {
                    mkdir($local . '/docs', 0755);
                    agentEvaluationWriteComparisonControl($local . '/docs/phpstan.fixture', $referenceBytes);
                } else {
                    mkdir($local . $prefix, 0755, true);
                    if ($collision === 'identical') {
                        agentEvaluationWriteComparisonControl($local . $prefix . '/example.type.md', $referenceBytes);
                    }
                }
            }
            try {
                agentEvaluationExpectFailure(
                    static function () use ($local, $shared): void {
                        agentEvaluationComparisonFixture($local, $shared);
                    },
                    $collision === 'parent-file'
                        ? 'Comparison fixture materialization has a file-directory collision.'
                        : 'Comparison local fixture must not occupy the shared reference destination.',
                );
            } finally {
                if ($collision === 'parent-file') {
                    unlink($local . '/docs');
                } else {
                    agentEvaluationRemoveDirectory($temporary . '/local' . ($collision === 'case-folded' ? '/DOCS' : '/docs'));
                }
                clearstatcache();
            }
        }
        agentEvaluationWriteComparisonControl($shared . '/oversized.md', str_repeat('x', AGENT_EVALUATION_MAX_JSON_BYTES + 1));
        try {
            agentEvaluationExpectFailure(
                static function () use ($local, $shared): void {
                    agentEvaluationComparisonFixture($local, $shared);
                },
                'comparison fixture file exceeds its bounded file size.',
            );
        } finally {
            unlink($shared . '/oversized.md');
        }
        for ($index = 0; $index < 4_094; $index++) {
            agentEvaluationWriteComparisonControl($local . '/file-' . $index . '.md', 'x');
        }
        agentEvaluationTest(count(agentEvaluationComparisonFixture($local, $shared)['files']) === 4_096,
            'The combined local and shared file count must admit exactly the existing fixture limit.');
        agentEvaluationWriteComparisonControl($shared . '/overflow.md', 'x');
        try {
            agentEvaluationExpectFailure(
                static function () use ($local, $shared): void {
                    agentEvaluationComparisonFixture($local, $shared);
                },
                'Comparison fixture materialization collides or exceeds its file bound.',
            );
        } finally {
            unlink($shared . '/overflow.md');
            for ($index = 0; $index < 4_094; $index++) {
                unlink($local . '/file-' . $index . '.md');
            }
        }
        for ($index = 0; $index < 15; $index++) {
            agentEvaluationWriteComparisonControl($local . '/bytes-' . $index . '.md', str_repeat('x', AGENT_EVALUATION_MAX_JSON_BYTES));
        }
        $tail = str_repeat('x', AGENT_EVALUATION_MAX_JSON_BYTES - strlen($localBytes) - strlen($referenceBytes));
        agentEvaluationWriteComparisonControl($local . '/tail.md', $tail);
        agentEvaluationTest(agentEvaluationComparisonFixture($local, $shared)['bytes'] === AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
            'The combined local and shared bytes must admit exactly the existing aggregate limit.');
        agentEvaluationWriteComparisonControl($local . '/tail.md', $tail . 'x');
        agentEvaluationExpectFailure(
            static function () use ($local, $shared): void {
                agentEvaluationComparisonFixture($local, $shared);
            },
            'Comparison fixture exceeds its aggregate byte bound.',
        );
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
}

/** @param array<string, array{source_path: string, sha256: string, mode: string, bytes: int}> $files */
function agentEvaluationComparisonInstructionRoutesExist(string $instructions, array $files, string $framework): bool
{
    $count = preg_match_all('/`([^`]+\.md)`/', $instructions, $matches);
    if (!is_int($count) || $count === 0) {
        return false;
    }
    foreach ($matches[1] as $route) {
        // A documented read command can end in the same literal path as a direct reference.
        if (preg_match('~(?:^|[ \t])([A-Za-z0-9_./-]+\.md)$~D', $route, $pathMatch) !== 1) {
            return false;
        }
        $route = $pathMatch[1];
        $installedPrefix = 'vendor/phpthis/framework/';
        $source = str_starts_with($route, $installedPrefix)
            ? $framework . '/' . substr($route, strlen($installedPrefix))
            : ($files[$route]['source_path'] ?? null);
        if (!is_string($source) || !is_file($source) || is_link($source)) {
            return false;
        }
    }
    return true;
}

function agentEvaluationComparisonTaskControls(string $kit): void
{
    $tasks = agentEvaluationComparisonTasks($kit);
    $schema = agentEvaluationJsonFile($kit . '/schema/task-v2.schema.json');
    $properties = agentEvaluationRequireObject($schema, 'properties', 'comparison task schema control');
    $revisionSchema = agentEvaluationRequireObject($properties, 'revision', 'comparison task schema control');
    agentEvaluationTest(
        $revisionSchema === ['type' => 'integer', 'minimum' => 1],
        'The comparison task schema must allow positive revisions while PHP admission pins each registered revision.',
    );
    $minimumRevision = agentEvaluationRequireInteger($revisionSchema, 'minimum', 'comparison task schema control');
    $referenceDirectory = $kit . '/references/phpstan';
    $referenceManifest = agentEvaluationComparisonPhpstanReference($referenceDirectory);
    agentEvaluationTest(
        array_column($tasks, 'id') === ['change.protected-endpoint', 'repair.transaction-rollback', 'change.filtered-collection'],
        'The same explicit inventory must expose exactly the three versioned comparison tasks.',
    );
    foreach ($tasks as $task) {
        agentEvaluationTest(
            $task['revision'] >= $minimumRevision
            && $task['revision'] === AGENT_EVALUATION_TASK_REVISIONS[$task['id']]['revision'],
            'Every current comparison task must satisfy the published revision schema and its exact registered revision.',
        );
        $conditionFiles = [];
        foreach ($task['conditions'] as $condition) {
            $fixtureRoot = $condition['base']['directory'];
            $fixture = agentEvaluationComparisonFixture($fixtureRoot, $condition['base']['reference_directory']);
            $conditionFiles[$condition['id']] = $fixture['files'];
            $bundleLines = [];
            $contextReferences = [];
            foreach ($fixture['files'] as $relative => $file) {
                if (str_starts_with($relative, 'docs/phpstan/')) {
                    $bundleLines[] = $file['mode'] . ' ' . $file['sha256'] . ' ' . substr($relative, strlen('docs/phpstan/'));
                    $contextReferences[] = ['path' => $relative, 'sha256' => $file['sha256']];
                }
            }
            sort($bundleLines, SORT_STRING);
            $actualContextReferences = array_values(array_filter($condition['context_manifest']['files'],
                static fn (array $entry): bool => str_starts_with($entry['path'], 'docs/phpstan/')));
            agentEvaluationTest(
                $condition['base']['reference_directory'] === $referenceDirectory
                && !file_exists($fixtureRoot . '/docs/phpstan')
                && $fixture['sha256'] === $condition['base']['fixture_sha256']
                && implode("\n", $bundleLines) . "\n" === $referenceManifest
                && $actualContextReferences === $contextReferences,
                'One shared reference must preserve all six exact fixture identities and their complete materialized context.',
            );
            agentEvaluationTest(
                in_array('docs/phpstan', $condition['workspace_policy']['protected_paths'], true),
                'The offline PHPStan reference must remain protected candidate context.',
            );
            foreach (['AGENTS.md', '.ai/testing.md'] as $route) {
                $guide = file_get_contents($fixtureRoot . '/' . $route);
                agentEvaluationTest(
                    is_string($guide) && agentEvaluationComparisonInstructionRoutesExist($guide, $fixture['files'], dirname($kit, 2)),
                    'Both conditions must resolve their application instruction and testing documentation routes.',
                );
            }
        }
        $fixture = $task['conditions'][0]['base']['directory'];
        $instructions = file_get_contents($fixture . '/AGENTS.md');
        if (!is_string($instructions)) {
            throw new RuntimeException('Unable to read comparison PHPThis instructions.');
        }
        agentEvaluationTest(
            agentEvaluationComparisonInstructionRoutesExist($instructions, $conditionFiles['phpthis'], dirname($kit, 2)),
            'Comparison PHPThis instructions must resolve every Markdown route from the consumer root, including installed framework docs.',
        );
        $missingRoute = str_replace('vendor/phpthis/framework/docs/knowledge-map.md', 'docs/knowledge-map.md', $instructions);
        agentEvaluationTest(
            !agentEvaluationComparisonInstructionRoutesExist($missingRoute, $conditionFiles['phpthis'], dirname($kit, 2)),
            'A consumer-root knowledge map route must fail when only the installed framework document exists.',
        );
        foreach ($conditionFiles as $files) {
            $testingInstructions = file_get_contents($files['.ai/testing.md']['source_path']);
            if (!is_string($testingInstructions)) {
                throw new RuntimeException('Unable to read comparison testing instructions.');
            }
            $missingSupplemental = $files;
            unset($missingSupplemental['docs/phpstan/phpdoc-types.md']);
            agentEvaluationTest(
                !agentEvaluationComparisonInstructionRoutesExist($testingInstructions, $missingSupplemental, dirname($kit, 2)),
                'Each testing guide must require its supplemental PHPDoc reference to resolve from the candidate root.',
            );
        }
        $manifest = agentEvaluationJsonFile($task['directory'] . '/task.json');
        $checks = agentEvaluationRequireObject($manifest, 'checks', 'comparison task control');
        $holdout = agentEvaluationRequireObject($checks, 'holdout', 'comparison task control');
        agentEvaluationTest(
            array_column($task['conditions'], 'id') === ['phpthis', 'plain-php']
            && $task['checks']['holdout']['id'] === $task['id'] . '.holdout'
            && !array_key_exists('path', $holdout)
            && !array_key_exists('comparative_claims', $manifest),
            'Comparison task declarations must bind matched conditions and external data identity without authorizing claims.',
        );
        agentEvaluationExpectFailure(
            static function () use ($kit, $task): void {
                agentEvaluationTask($kit, $task['id']);
            },
            'Unknown agent-evaluation task: ' . $task['id'] . '.',
        );
    }
    $schedule = agentEvaluationComparisonSchedule($kit);
    agentEvaluationTest(count($schedule) === 60, 'The frozen comparison schedule must have exactly sixty planned slots.');
    $counts = [];
    $first = [];
    foreach ($schedule as $index => $slot) {
        agentEvaluationTest($slot['slot'] === $index + 1 && $slot['round'] === intdiv($index, 6), 'Comparison slots must be sequential and grouped in ten fixed rounds.');
        $key = $slot['task_id'] . ':' . $slot['condition'];
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        if ($index % 2 === 0) {
            $first[$key] = ($first[$key] ?? 0) + 1;
        }
    }
    agentEvaluationTest(count($counts) === 6 && count($first) === 6, 'Every comparison task and condition must retain planned and first-position counts.');
    foreach ($counts as $key => $count) {
        agentEvaluationTest($count === 10 && $first[$key] === 5, 'Each task-condition pair must have ten trials and five first positions.');
    }
}

function agentEvaluationComparisonPhpstanReference(string $directory): string
{
    $owner = 'comparison offline PHPStan reference';
    $sources = agentEvaluationJsonFile($directory . '/sources.json');
    $commit = 'd8ed7dd9d5ccfc37324392be86f3ac6d79effd52';
    agentEvaluationTest(
        ($sources['schema_version'] ?? null) === 1
        && ($sources['bundle_version'] ?? null) === 3
        && ($sources['repository_url'] ?? null) === 'https://github.com/phpstan/phpstan'
        && ($sources['upstream_commit'] ?? null) === $commit,
        'Offline PHPStan sources must identify their reviewed official repository and immutable commit.',
    );
    $identifiers = agentEvaluationRequireObject($sources, 'identifiers', $owner);
    $identifierNames = array_keys($identifiers);
    sort($identifierNames, SORT_STRING);
    // Independently reviewed from the complete pinned upstream errors tree, excluding CLAUDE.md.
    $identifierHash = '0a1305f9d14e26b5be7b62845f2e851a5d5628663cdf5d86a4be65026302cb27';
    agentEvaluationTest(
        count($identifierNames) === 1_109
        && hash('sha256', implode("\n", $identifierNames) . "\n") === $identifierHash,
        'The offline reference must contain the complete reviewed upstream diagnostic inventory.',
    );
    $coverage = agentEvaluationRequireObject($sources, 'coverage', $owner);
    $catalog = agentEvaluationRequireObject($coverage, 'official_catalog_source', $owner);
    $tree = agentEvaluationRequireObject($coverage, 'official_tree_source', $owner);
    agentEvaluationTest(
        ($coverage['diagnostic_count'] ?? null) === 1_109
        && ($coverage['diagnostic_bytes'] ?? null) === 1_301_830
        && ($coverage['official_catalog_identifier_count'] ?? null) === 1_105
        && ($coverage['catalog_identifiers_all_documented'] ?? null) === true
        && ($coverage['sorted_identifier_sha256'] ?? null) === $identifierHash
        && ($coverage['additional_authored_identifiers'] ?? null) === [
            'parameter.void', 'sortArray.empty', 'sortArray.list', 'sortArray.singleElement',
        ]
        && ($catalog['source_git_blob_sha1'] ?? null) === 'e628905f218b46e0a6a3504d2404b666a6bab91c'
        && ($catalog['sha256'] ?? null) === '8d1877cd4a6ad738064032f2e61d628a7efa8198e307bd6cf96c54d1e734d824'
        && ($tree['errors_tree_git_sha1'] ?? null) === '0d6cf13ecf17c5fa647c1ab615654d4d17fb96a0'
        && ($tree['truncated'] ?? null) === false,
        'Offline diagnostic coverage must retain the reviewed complete tree and catalog identities.',
    );
    $supplemental = agentEvaluationRequireObject($sources, 'supplemental', $owner);
    agentEvaluationRequireExactKeys($supplemental, ['phpdoc-types'], $owner);
    $license = agentEvaluationRequireObject($sources, 'license', $owner);
    agentEvaluationTest(($license['spdx_id'] ?? null) === 'MIT', 'The offline reference must preserve the upstream license.');
    $entries = ['LICENSE' => $license];
    foreach ($identifiers as $identifier => $value) {
        $entries[$identifier . '.md'] = agentEvaluationValueObject($value, $owner);
    }
    $entries['phpdoc-types.md'] = agentEvaluationRequireObject($supplemental, 'phpdoc-types', $owner);
    foreach ($entries as $path => $entry) {
        $sourcePath = match ($path) {
            'LICENSE' => 'LICENSE',
            'phpdoc-types.md' => 'website/src/writing-php-code/phpdoc-types.md',
            default => 'website/errors/' . $path,
        };
        agentEvaluationTest(
            ($entry['path'] ?? null) === $path
            && ($entry['source_path'] ?? null) === $sourcePath
            && ($entry['source_url'] ?? null) === 'https://raw.githubusercontent.com/phpstan/phpstan/' . $commit . '/' . $sourcePath,
            'Every offline payload must resolve to its exact pinned official source.',
        );
        $bytes = file_get_contents($directory . '/' . $path);
        if (!is_string($bytes)) {
            throw new RuntimeException('An offline PHPStan payload is unavailable.');
        }
        agentEvaluationTest(
            strlen($bytes) === ($entry['bytes'] ?? null)
            && hash('sha256', $bytes) === ($entry['sha256'] ?? null)
            && hash('sha1', 'blob ' . strlen($bytes) . "\0" . $bytes) === ($entry['source_git_blob_sha1'] ?? null),
            'Offline payload bytes must match both their SHA-256 and upstream Git blob identity.',
        );
    }
    $fixture = agentEvaluationComparisonFixture($directory);
    $expectedPaths = [...array_keys($entries), 'README.md', 'sources.json'];
    sort($expectedPaths, SORT_STRING);
    agentEvaluationTest(array_keys($fixture['files']) === $expectedPaths, 'The offline reference must have exactly its reviewed source and wrapper inventory.');
    return $fixture['manifest'];
}

function agentEvaluationComparisonCopiedKitControls(string $kit): void
{
    $inventoryPath = $kit . '/tasks.json';
    $inventoryBytes = file_get_contents($inventoryPath);
    if (!is_string($inventoryBytes)) {
        throw new RuntimeException('Unable to read comparison inventory control.');
    }
    try {
        if (file_put_contents($inventoryPath, agentEvaluationJson(['change.simple-ping', 'change.protected-endpoint', 'repair.transaction-rollback', 'change.filtered-collection', 'change.protected-endpoint'])) === false) {
            throw new RuntimeException('Unable to write comparison inventory control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($kit): void {
                agentEvaluationValidateKit($kit);
            },
            'The agent-evaluation task inventory must equal the pinned non-empty revision order.',
        );
    } finally {
        if (file_put_contents($inventoryPath, $inventoryBytes) !== strlen($inventoryBytes)) {
            throw new RuntimeException('Unable to restore comparison inventory control.');
        }
        clearstatcache(true, $inventoryPath);
    }
    $id = 'change.protected-endpoint';
    $path = $kit . '/tasks/' . $id . '/task.json';
    $original = file_get_contents($path);
    if (!is_string($original)) {
        throw new RuntimeException('Unable to read comparison task control.');
    }
    $document = agentEvaluationJsonFile($path);
    $conditions = agentEvaluationRequireList($document, 'conditions', 'comparison task control');
    $first = agentEvaluationValueObject($conditions[0] ?? null, 'comparison task control');
    $budgets = agentEvaluationRequireObject($document, 'budgets', 'comparison task control');
    $checks = agentEvaluationRequireObject($document, 'checks', 'comparison task control');
    $holdout = agentEvaluationRequireObject($checks, 'holdout', 'comparison task control');
    $protocol = agentEvaluationRequireObject($document, 'protocol', 'comparison task control');
    $revision = agentEvaluationRequireInteger($document, 'revision', 'comparison task control');
    $controls = [
        [[...$document, 'schema_version' => 1], 'Comparison task identity must match its explicit pinned version and kind.'],
        [[...$document, 'revision' => 0], 'comparison task field revision must be positive.'],
        [[...$document, 'revision' => -1], 'comparison task field revision must be positive.'],
        [[...$document, 'revision' => null], 'comparison task field revision must be an integer.'],
        [[...$document, 'revision' => true], 'comparison task field revision must be an integer.'],
        [[...$document, 'revision' => '6'], 'comparison task field revision must be an integer.'],
        [[...$document, 'revision' => 1.5], 'comparison task field revision must be an integer.'],
        [[...$document, 'revision' => $revision - 1], 'Comparison task identity must match its explicit pinned version and kind.'],
        [[...$document, 'revision' => $revision + 1], 'Comparison task identity must match its explicit pinned version and kind.'],
        [[...$document, 'budgets' => [...$budgets, 'model_tokens' => 40_001]], 'Comparison task budgets must equal the fixed matched protocol.'],
        [[...$document, 'protocol' => [...$protocol, 'sha256' => str_repeat('0', 64)]], 'Comparison task must bind the exact reviewed protocol.'],
        [[...$document, 'conditions' => array_reverse($conditions)], 'Comparison conditions must retain the fixed protocol order.'],
        [[...$document, 'conditions' => [[...$first, 'prompt' => 'condition-specific prompt'], $conditions[1]]],
            'comparison condition must contain exactly: base, context_manifest, id, workspace_policy.'],
        [[...$document, 'checks' => [...$checks, 'holdout' => [...$holdout, 'path' => 'public/holdout.json']]],
            'comparison holdout must contain exactly: id, revision, sha256.'],
    ];
    try {
        foreach ($controls as [$mutation, $expected]) {
            if (file_put_contents($path, agentEvaluationJson($mutation)) === false) {
                throw new RuntimeException('Unable to write comparison task control.');
            }
            clearstatcache(true, $path);
            agentEvaluationExpectFailure(
                static function () use ($kit, $id): void {
                    agentEvaluationComparisonTaskDocument($kit, $id);
                },
                $expected,
            );
        }
    } finally {
        if (file_put_contents($path, $original) !== strlen($original)) {
            throw new RuntimeException('Unable to restore comparison task control.');
        }
        clearstatcache(true, $path);
    }
    $referenceDirectory = $kit . '/references/phpstan';
    $sourcesPath = $referenceDirectory . '/sources.json';
    $sourcesBytes = file_get_contents($sourcesPath);
    if (!is_string($sourcesBytes)) {
        throw new RuntimeException('Unable to read the copied diagnostic inventory control.');
    }
    try {
        $incomplete = agentEvaluationJsonFile($sourcesPath);
        $incomplete['identifiers'] = agentEvaluationRequireObject($incomplete, 'identifiers', 'diagnostic inventory control');
        $incomplete['coverage'] = agentEvaluationRequireObject($incomplete, 'coverage', 'diagnostic inventory control');
        unset($incomplete['identifiers']['return.phpDocType']);
        $remaining = array_keys($incomplete['identifiers']);
        sort($remaining, SORT_STRING);
        $incomplete['coverage']['diagnostic_count'] = count($remaining);
        $incomplete['coverage']['sorted_identifier_sha256'] = hash('sha256', implode("\n", $remaining) . "\n");
        $encoded = agentEvaluationJson($incomplete);
        if (file_put_contents($sourcesPath, $encoded) !== strlen($encoded)) {
            throw new RuntimeException('Unable to write the incomplete diagnostic inventory control.');
        }
        agentEvaluationExpectFailure(
            static function () use ($referenceDirectory): void {
                agentEvaluationComparisonPhpstanReference($referenceDirectory);
            },
            'The offline reference must contain the complete reviewed upstream diagnostic inventory.',
        );
    } finally {
        if (file_put_contents($sourcesPath, $sourcesBytes) !== strlen($sourcesBytes)) {
            throw new RuntimeException('Unable to restore the copied diagnostic inventory control.');
        }
        clearstatcache(true, $sourcesPath);
    }
    $referencePath = $referenceDirectory . '/return.type.md';
    $referenceBytes = file_get_contents($referencePath);
    if (!is_string($referenceBytes)) {
        throw new RuntimeException('Unable to read the copied offline reference control.');
    }
    try {
        foreach ([null, $referenceBytes . "\nAltered source.\n"] as $replacement) {
            if ($replacement === null) {
                if (!unlink($referencePath)) {
                    throw new RuntimeException('Unable to remove the copied reference control.');
                }
            } elseif (file_put_contents($referencePath, $replacement) !== strlen($replacement)) {
                throw new RuntimeException('Unable to alter the copied reference control.');
            }
            clearstatcache(true, $referencePath);
            foreach (['change.protected-endpoint', 'repair.transaction-rollback', 'change.filtered-collection'] as $affectedTask) {
                agentEvaluationExpectFailure(
                    static function () use ($kit, $affectedTask): void {
                        agentEvaluationComparisonTaskDocument($kit, $affectedTask);
                    },
                    'Comparison base hash must match the exact materialized fixture.',
                );
            }
        }
    } finally {
        if (file_put_contents($referencePath, $referenceBytes) !== strlen($referenceBytes)) {
            throw new RuntimeException('Unable to restore the copied reference control.');
        }
        clearstatcache(true, $referencePath);
    }
    agentEvaluationValidateKit($kit);
}

function agentEvaluationComparisonRecordControls(string $kit): void
{
    $publicTask = agentEvaluationComparisonTask($kit, 'change.protected-endpoint');
    $expected = ['response' => ['status' => 200, 'headers' => new stdClass(), 'body' => ['data' => []]],
        'policy_steps' => ['authenticate', 'resolve', 'authorize'],
        'query' => ['min_statements' => 2, 'max_statements' => 2, 'failures' => 0, 'max_fingerprint_executions' => 1],
        'durable_state' => new stdClass(), 'in_transaction' => false];
    $holdout = ['schema_version' => 1, 'id' => 'synthetic.control.holdout', 'revision' => 1,
        'task_id' => $publicTask['id'],
        'cases' => [
            ['id' => 'synthetic.small', 'input' => ['size' => 1], 'expect' => $expected],
            ['id' => 'synthetic.large', 'input' => ['size' => 20], 'expect' => $expected],
        ],
        'scaling_groups' => [['id' => 'synthetic.growth', 'case_ids' => ['synthetic.small', 'synthetic.large'], 'max_statement_growth' => 0]]];
    $holdoutBytes = agentEvaluationJson($holdout);
    $holdoutHash = hash('sha256', $holdoutBytes);
    $task = [...$publicTask, 'checks' => ['application_check' => 'composer check',
        'holdout' => ['id' => $holdout['id'], 'revision' => 1, 'sha256' => $holdoutHash]]];
    $campaignId = str_repeat('a', 32);
    $slot = ['slot' => 1, 'round' => 0, 'task_id' => $task['id'], 'condition' => 'phpthis'];
    $unknown = [];
    foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens', 'elapsed_milliseconds',
        'public_check_repairs', 'human_interventions', 'reviewer_effort'] as $name) {
        $unknown[$name] = 'Synthetic unstarted slot; no observation exists.';
    }
    $planned = ['schema_version' => 2, 'campaign_id' => $campaignId, 'slot' => 1,
        'run_id' => substr(hash('sha256', $campaignId . ':1'), 0, 32), 'task_id' => $task['id'],
        'task_revision' => $task['revision'], 'condition' => 'phpthis',
        'protocol_sha256' => $task['protocol']['sha256'], 'task_manifest_sha256' => $task['manifest_sha256'],
        'source_revision' => str_repeat('b', 40), 'base_fixture_sha256' => $task['conditions'][0]['base']['fixture_sha256'],
        'prepared_dependencies_manifest_sha256' => str_repeat('c', 64), 'prepared_lock_sha256' => str_repeat('d', 64),
        'profile_sha256' => hash('sha256', "{}\n"), 'holdout_sha256' => $holdoutHash,
        'status' => 'planned', 'phase' => 'planned', 'termination_reason' => null,
        'usage' => ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null],
        'elapsed_milliseconds' => null, 'repair_turns' => 0, 'unknown_metrics' => $unknown, 'artifacts' => new stdClass()];
    agentEvaluationValidateComparisonRunRecord($planned, $task, $slot, $campaignId, $task['protocol']['sha256']);
    $notRun = [...$planned, 'status' => 'not_run', 'termination_reason' => 'campaign_aborted'];
    agentEvaluationValidateComparisonRunRecord($notRun, $task, $slot, $campaignId, $task['protocol']['sha256']);
    $running = [...$planned, 'status' => 'running', 'phase' => 'generate'];
    agentEvaluationValidateComparisonRunRecord($running, $task, $slot, $campaignId, $task['protocol']['sha256']);
    $failed = [...$running, 'status' => 'failed', 'termination_reason' => 'provider_unavailable'];
    agentEvaluationValidateComparisonRunRecord($failed, $task, $slot, $campaignId, $task['protocol']['sha256']);
    $artifactBytes = ['profile.json' => "{}\n", 'events.jsonl' => '', 'candidate.patch' => '',
        'application-check.json' => "{}\n", 'observation-results.json' => "{}\n", 'cleanup.json' => "{}\n"];
    $artifacts = [];
    foreach ($artifactBytes as $name => $bytes) {
        $artifacts[$name] = ['bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }
    $usage = ['input_tokens' => 100, 'output_tokens' => 20, 'cached_tokens' => 10, 'reasoning_tokens' => 5];
    $complete = [...$planned, 'status' => 'complete', 'phase' => 'finished', 'termination_reason' => 'completed',
        'usage' => $usage, 'elapsed_milliseconds' => 1_000, 'artifacts' => $artifacts,
        'unknown_metrics' => ['public_check_repairs' => 'Not observed.', 'human_interventions' => 'Not classified.', 'reviewer_effort' => 'Review pending.']];
    agentEvaluationValidateComparisonRunRecord($complete, $task, $slot, $campaignId, $task['protocol']['sha256']);
    foreach ([
        [[...$complete, 'task_manifest_sha256' => str_repeat('0', 64)], 'Comparison attempt protocol and task hashes must match their frozen identities.'],
        [[...$complete, 'run_id' => str_repeat('f', 32)], 'Comparison run ID must equal its deterministic campaign slot identity.'],
        [[...$complete, 'phase' => 'generate'], 'Comparison attempt state and termination are inconsistent.'],
        [[...$failed, 'termination_reason' => 'completed'], 'Comparison attempt state and termination are inconsistent.'],
        [[...$complete, 'artifacts' => new stdClass()], 'A completed comparison attempt lacks required retained evidence.'],
        [[...$complete, 'usage' => [...$usage, 'cached_tokens' => 101]], 'Comparison token categories cannot exceed their known provider totals.'],
        [[...$complete, 'usage' => [...$usage, 'reasoning_tokens' => 21]], 'Comparison token categories cannot exceed their known provider totals.'],
        [[...$complete, 'repair_turns' => 1], 'The fixed comparison permits zero post-score repair turns.'],
        [[...$planned, 'artifacts' => $artifacts], 'An unstarted comparison slot cannot claim execution artifacts, timing, or usage.'],
    ] as [$record, $message]) {
        agentEvaluationExpectFailure(
            static function () use ($record, $task, $slot, $campaignId): void {
                agentEvaluationValidateComparisonRunRecord($record, $task, $slot, $campaignId, $task['protocol']['sha256']);
            },
            $message,
        );
    }
    $temporary = sys_get_temp_dir() . '/phpthis-agent-evaluation-comparison-records-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700)) {
        throw new RuntimeException('Unable to prepare comparison retained-artifact controls.');
    }
    try {
        $artifactRoot = realpath($temporary);
        if (!is_string($artifactRoot)) {
            throw new RuntimeException('Unable to resolve comparison retained-artifact controls.');
        }
        foreach ($artifactBytes as $name => $bytes) {
            if (file_put_contents($temporary . '/' . $name, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Unable to write comparison retained-artifact control.');
            }
        }
        agentEvaluationValidateComparisonRunArtifacts($complete, $artifactRoot);
        $badArtifact = [...$complete, 'artifacts' => [...$artifacts, 'events.jsonl' => ['bytes' => 1, 'sha256' => hash('sha256', '')]]];
        agentEvaluationExpectFailure(
            static function () use ($badArtifact, $artifactRoot): void {
                agentEvaluationValidateComparisonRunArtifacts($badArtifact, $artifactRoot);
            },
            'Comparison artifact size does not match its retained descriptor.',
        );
    } finally {
        agentEvaluationRemoveDirectory($temporary);
    }
    $case = ['id' => 'synthetic.small', 'process_admissible' => true, 'observation_valid' => true,
        'response' => true, 'policy_order' => true, 'durable_state' => true, 'transaction_closed' => true,
        'query_bounds' => true, 'statements' => 2];
    $cases = [$case, [...$case, 'id' => 'synthetic.large']];
    $group = ['id' => 'synthetic.growth', 'case_ids' => ['synthetic.small', 'synthetic.large'], 'counts' => [2, 2], 'passed' => true];
    $review = ['status' => 'pending', 'reviewer' => null, 'semantic_correctness' => null,
        'instrumentation_integrity' => null, 'review_seconds' => null, 'justified_interventions' => null,
        'unnecessary_interventions' => null, 'public_check_repairs' => null, 'reason' => 'Synthetic validator control; no human review performed.'];
    $attemptHash = hash('sha256', agentEvaluationJson($complete));
    $score = ['schema_version' => 2, 'campaign_id' => $campaignId, 'slot' => 1, 'run_id' => $complete['run_id'],
        'task_id' => $task['id'], 'condition' => 'phpthis', 'attempt_sha256' => $attemptHash,
        'holdout_sha256' => $holdoutHash, 'admissible' => true, 'application_gate' => 'pass',
        'cases' => $cases, 'scaling' => [$group], 'automated_status' => 'pass', 'human_review' => $review,
        'correct_completion' => null];
    agentEvaluationValidateComparisonScoreRecord($score, $complete, $attemptHash, $holdoutBytes);
    $reviewed = [...$review, 'status' => 'pass', 'reviewer' => 'synthetic-validator-fixture',
        'semantic_correctness' => true, 'instrumentation_integrity' => true];
    agentEvaluationValidateComparisonScoreRecord([...$score, 'human_review' => $reviewed, 'correct_completion' => true], $complete, $attemptHash, $holdoutBytes);
    $reviewFailed = [...$reviewed, 'status' => 'fail', 'instrumentation_integrity' => false];
    agentEvaluationValidateComparisonScoreRecord([...$score, 'human_review' => $reviewFailed, 'correct_completion' => false], $complete, $attemptHash, $holdoutBytes);
    foreach ([
        [[...$score, 'attempt_sha256' => str_repeat('0', 64)], 'Comparison score does not bind the retained attempt bytes.'],
        [[...$score, 'cases' => [$case]], 'Comparison score exceeds its fixed case or scaling bounds.'],
        [[...$score, 'scaling' => []], 'Comparison score exceeds its fixed case or scaling bounds.'],
        [[...$score, 'cases' => [$case, $case]], 'Comparison score case IDs must be unique bounded labels.'],
        [[...$score, 'scaling' => [[...$group, 'counts' => [2, 3]]]], 'Comparison scaling score references an absent case.'],
        [[...$score, 'scaling' => [[...$group, 'passed' => false]]], 'Comparison scaling status must equal its fixed zero-growth rule.'],
        [[...$score, 'cases' => [[...$case, 'response' => false], $cases[1]]], 'Comparison automated status must be derived from every mandatory result.'],
        [[...$score, 'cases' => [[...$case, 'statements' => null], $cases[1]]], 'Comparison resource success requires a valid count observation.'],
        [[...$score, 'cases' => [[...$case, 'statements' => 1], $cases[1]]], 'Comparison resource success contradicts its observed statement count.'],
        [[...$score, 'cases' => [[...$case, 'statements' => 3], $cases[1]]], 'Comparison resource success contradicts its observed statement count.'],
        [[...$score, 'correct_completion' => true], 'Correct completion must include the separate human semantic and instrumentation review.'],
        [[...$score, 'human_review' => [...$review, 'review_seconds' => 0]], 'A pending human review cannot fabricate reviewer decisions or effort.'],
    ] as [$record, $message]) {
        agentEvaluationExpectFailure(
            static function () use ($record, $complete, $attemptHash, $holdoutBytes): void {
                agentEvaluationValidateComparisonScoreRecord($record, $complete, $attemptHash, $holdoutBytes);
            },
            $message,
        );
    }
    agentEvaluationExpectFailure(
        static function () use ($score, $failed, $attemptHash, $holdoutBytes): void {
            agentEvaluationValidateComparisonScoreRecord($score, $failed, $attemptHash, $holdoutBytes);
        },
        'An incomplete or failed execution cannot claim comparison admissibility.',
    );
    agentEvaluationExpectFailure(
        static function () use ($score, $complete, $attemptHash, $holdoutBytes): void {
            agentEvaluationValidateComparisonScoreRecord($score, $complete, $attemptHash, $holdoutBytes . "\n");
        },
        'Comparison score validation requires the exact bounded private holdout bytes.',
    );
    foreach (['{"cases":[],"cases":[]}', '{"usage":{"input_tokens":1,"input\\u005ftokens":2}}'] as $duplicate) {
        agentEvaluationExpectFailure(
            static function () use ($duplicate): void {
                agentEvaluationJsonValue($duplicate, 'synthetic duplicate control');
            },
            'JSON input contains a duplicate object name.',
        );
    }
    foreach (['{"cases":[}', '{"cases":[]} trailing', '[1,]'] as $malformed) {
        $rejected = false;
        try {
            agentEvaluationJsonValue($malformed, 'synthetic malformed control');
        } catch (JsonException) {
            $rejected = true;
        }
        agentEvaluationTest($rejected, 'Malformed JSON cannot become an observed comparison record.');
    }
    agentEvaluationComparisonAggregationControls($kit, $complete, $score);
}

/**
 * @param array<string,mixed> $complete
 * @param array<string,mixed> $score
 */
function agentEvaluationComparisonAggregationControls(string $kit, array $complete, array $score): void
{
    $schedule = agentEvaluationComparisonSchedule($kit);
    $pricing = ['input_cents_per_million' => 100, 'cached_cents_per_million' => 10, 'output_cents_per_million' => 400];
    $review = agentEvaluationRequireObject($score, 'human_review', 'aggregation control');
    $rows = [];
    foreach ($schedule as $slot) {
        $correct = $slot['condition'] === 'phpthis' || $slot['round'] < 5;
        $rows[] = ['slot' => $slot['slot'],
            'attempt' => [...$complete, 'slot' => $slot['slot'], 'task_id' => $slot['task_id'], 'condition' => $slot['condition']],
            'score' => [...$score, 'slot' => $slot['slot'], 'task_id' => $slot['task_id'], 'condition' => $slot['condition'],
                'human_review' => [...$review, 'status' => $correct ? 'pass' : 'fail', 'reviewer' => 'synthetic-review-control',
                    'semantic_correctness' => $correct, 'instrumentation_integrity' => true], 'correct_completion' => $correct],
            'started' => true, 'final_retained' => true,
            'metrics' => ['scoring_elapsed_milliseconds' => 100, 'changed_files' => 2, 'added_lines' => 40, 'deleted_lines' => 10]];
    }
    $report = agentEvaluationAggregateComparisonResults($schedule, $rows, $pricing);
    agentEvaluationTest($report['complete'] === true && $report['correctness_rates_available'] === true,
        'All sixty reviewed actual outcomes must unlock fixed group rates.');
    foreach (agentEvaluationRequireList($report, 'groups', 'aggregation control') as $value) {
        $group = agentEvaluationValueObject($value, 'aggregation group control');
        $correct = agentEvaluationRequireObject($group, 'correct_completion', 'aggregation control');
        $expectedCount = $group['condition'] === 'phpthis' ? 10 : 5;
        agentEvaluationTest($group['planned_denominator'] === 10 && $correct['count'] === $expectedCount
            && $correct['rate'] === $expectedCount / 10 && $group['automated_passes'] === 10,
            'Group correctness rates must retain ten planned trials and remain separate from automated passes.');
        $interval = agentEvaluationRequireObject($correct, 'wilson_95', 'aggregation control');
        $lower = $interval['lower'];
        $upper = $interval['upper'];
        agentEvaluationTest(is_float($lower) && is_float($upper) && $lower >= 0.0 && $upper <= 1.0
            && $lower < $expectedCount / 10 && $upper >= $expectedCount / 10,
            'Group uncertainty must remain a bounded Wilson interval including its observed rate.');
    }
    foreach (agentEvaluationRequireList($report, 'task_differences', 'aggregation control') as $value) {
        $difference = agentEvaluationValueObject($value, 'aggregation difference control');
        agentEvaluationTest($difference['phpthis_minus_plain_php'] === 0.5,
            'Each task difference must use the matched ten-trial condition rates.');
    }
    $totals = agentEvaluationRequireObject($report, 'totals', 'aggregation control');
    $pooled = agentEvaluationRequireObject($totals, 'correct_completion', 'aggregation control');
    $charge = agentEvaluationRequireObject($totals, 'estimated_api_charge_usd', 'aggregation control');
    $totalCharge = $charge['total'];
    agentEvaluationTest($totals['planned_denominator'] === 60 && $pooled['rate'] === null
        && is_float($totalCharge) && abs($totalCharge - 0.01026) < 0.000000001,
        'Reporting must avoid pooled correctness claims and charge cached/reasoning subsets exactly once.');
    $omitted = agentEvaluationAggregateComparisonResults($schedule, array_slice($rows, 0, 59), $pricing);
    agentEvaluationTest($omitted['complete'] === false && $omitted['missing_or_unfinished_slots'] === [60],
        'An omitted final trial must remain an explicit missing slot.');
    foreach (agentEvaluationRequireList($omitted, 'groups', 'aggregation control') as $value) {
        $group = agentEvaluationValueObject($value, 'incomplete aggregation group');
        $correct = agentEvaluationRequireObject($group, 'correct_completion', 'incomplete aggregation group');
        agentEvaluationTest($correct['rate'] === null && $correct['wilson_95'] === null && is_string($correct['reason']),
            'One incomplete campaign slot must suppress every condition rate and uncertainty interval.');
    }
    $pending = $rows;
    $pending[0] = [...$pending[0], 'score' => [...$score, 'human_review' => $review, 'correct_completion' => null]];
    $pendingReport = agentEvaluationAggregateComparisonResults($schedule, $pending, $pricing);
    agentEvaluationTest($pendingReport['complete'] === true && $pendingReport['correctness_rates_available'] === false
        && $pendingReport['pending_review_slots'] === [1],
        'Pending human review must keep correctness rates unavailable even after sixty actual outcomes.');
    $running = $rows;
    $running[0] = [...$running[0], 'attempt' => [...$running[0]['attempt'], 'status' => 'running', 'phase' => 'generate', 'termination_reason' => null],
        'score' => null, 'final_retained' => false];
    $runningReport = agentEvaluationAggregateComparisonResults($schedule, $running, $pricing);
    agentEvaluationTest($runningReport['complete'] === false && $runningReport['missing_or_unfinished_slots'] === [1],
        'An interrupted started attempt cannot become a completed trial from its marker alone.');
    $notRun = $rows;
    $notRun[0] = [...$notRun[0], 'attempt' => [...$notRun[0]['attempt'], 'status' => 'not_run', 'phase' => 'planned', 'termination_reason' => 'campaign_aborted'],
        'score' => null, 'started' => false];
    $notRunReport = agentEvaluationAggregateComparisonResults($schedule, $notRun, $pricing);
    agentEvaluationTest($notRunReport['complete'] === false && $notRunReport['missing_or_unfinished_slots'] === [1],
        'A retained not-run decision cannot replace an actual attempt in the primary denominator.');
    $budgetFailed = $rows;
    $failedScore = agentEvaluationRequireObject($budgetFailed[0], 'score', 'aggregation failure control');
    $budgetFailed[0] = [...$budgetFailed[0],
        'attempt' => [...$budgetFailed[0]['attempt'], 'status' => 'failed', 'phase' => 'generate', 'termination_reason' => 'token_limit'],
        'score' => [...$failedScore, 'admissible' => false, 'automated_status' => 'fail', 'correct_completion' => false]];
    $failedReport = agentEvaluationAggregateComparisonResults($schedule, $budgetFailed, $pricing);
    $failureTotals = agentEvaluationRequireObject($failedReport, 'totals', 'aggregation failure control');
    $failures = agentEvaluationRequireList($failureTotals, 'failures', 'aggregation failure control');
    $failure = agentEvaluationValueObject($failures[0] ?? null, 'aggregation failure control');
    agentEvaluationTest($failedReport['complete'] === true && $failedReport['correctness_rates_available'] === true
        && $failure['slot'] === 1 && $failure['phase'] === 'generate' && $failure['termination_reason'] === 'token_limit',
        'A reviewed exhausted-budget attempt must remain an actual failed outcome with its retained reason.');
    $unknown = $rows;
    $usage = agentEvaluationRequireObject($unknown[0]['attempt'], 'usage', 'aggregation usage control');
    $unknown[0] = [...$unknown[0], 'attempt' => [...$unknown[0]['attempt'], 'usage' => [...$usage, 'cached_tokens' => null]]];
    $unknownReport = agentEvaluationAggregateComparisonResults($schedule, $unknown, $pricing);
    $unknownTotals = agentEvaluationRequireObject($unknownReport, 'totals', 'aggregation usage control');
    $unknownCharge = agentEvaluationRequireObject($unknownTotals, 'estimated_api_charge_usd', 'aggregation usage control');
    $metrics = agentEvaluationRequireObject($unknownTotals, 'metrics', 'aggregation usage control');
    $cached = agentEvaluationRequireObject($metrics, 'cached_tokens', 'aggregation usage control');
    agentEvaluationTest($unknownCharge['total'] === null && $unknownCharge['unknown_started_attempts'] === 1
        && is_string($unknownCharge['reason']) && $cached['total'] === null && $cached['observed_subtotal'] === 590,
        'Unknown cached usage must preserve the known subtotal and an unavailable total charge with a reason.');
    $duplicate = $rows;
    $duplicate[59] = $duplicate[0];
    agentEvaluationExpectFailure(
        static function () use ($schedule, $duplicate, $pricing): void {
            agentEvaluationAggregateComparisonResults($schedule, $duplicate, $pricing);
        },
        'Comparison aggregation cannot duplicate or replace a planned observation.',
    );
    $none = agentEvaluationAggregateComparisonResults($schedule, [], $pricing);
    agentEvaluationTest($none['actual_outcomes_with_scores'] === 0 && $none['correctness_rates_available'] === false,
        'An unexecuted plan cannot manufacture actual outcomes or comparison rates.');
}
