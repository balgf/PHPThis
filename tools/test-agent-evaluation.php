<?php

declare(strict_types=1);

define('PHPTHIS_AGENT_EVALUATION_LIBRARY_ONLY', true);

require __DIR__ . '/agent-evaluation.php';

$root = dirname(__DIR__);
$kit = $root . '/tools/agent-evaluation';
$tasks = agentEvaluationValidateKit($kit);

agentEvaluationTest(
    count($tasks) === 1 && $tasks[0]['id'] === 'change.simple-ping',
    'The v1 kit must expose exactly the explicit public smoke task.',
);

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
        'model_tokens' => 40_000,
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
$excessUsage['usage']['input_tokens'] = 40_001;
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
$excessTotalUsage['usage']['input_tokens'] = 20_000;
$excessTotalUsage['usage']['output_tokens'] = 20_001;
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

    return hash('sha256', implode("\n", $lines) . "\n");
}
