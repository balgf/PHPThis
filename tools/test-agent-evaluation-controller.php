<?php

declare(strict_types=1);

define('PHPTHIS_AGENT_EVALUATION_CONTROLLER_LIBRARY_ONLY', true);
define('PHPTHIS_AGENT_EVALUATION_CONTROLLER_TESTING', true);

require __DIR__ . '/agent-evaluation-controller.php';

$root = dirname(__DIR__);
$temporaryBase = realpath(sys_get_temp_dir());

if ($temporaryBase === false) {
    throw new RuntimeException('Controller self-test temporary base is unavailable.');
}

$temporaryRoot = $temporaryBase . '/phpthis-agent-evaluation-controller-test-' . bin2hex(random_bytes(16));

if (!mkdir($temporaryRoot, 0700) || !chmod($temporaryRoot, 0700)) {
    throw new RuntimeException('Unable to create the controller self-test root.');
}

$previousUmask = umask(0077);

try {
    $dependencies = $temporaryRoot . '/prepared-dependencies';

    if (!mkdir($dependencies, 0700) || !chmod($dependencies, 0700)) {
        throw new RuntimeException('Unable to create synthetic prepared dependencies.');
    }

    $dependencyFile = $dependencies . '/fixture.lock';
    $dependencyBytes = "synthetic locked dependency\n";

    if (
        file_put_contents($dependencyFile, $dependencyBytes, LOCK_EX) !== strlen($dependencyBytes)
        || !chmod($dependencyFile, 0644)
    ) {
        throw new RuntimeException('Unable to write synthetic prepared dependencies.');
    }

    $task = agentEvaluationTask($root . '/tools/agent-evaluation', AGENT_EVALUATION_CONTROLLER_TASK_ID);
    $profile = agentEvaluationControllerSyntheticProfile($task['budgets']);
    $syntheticIsolation = agentEvaluationValueObject(
        $profile['isolation'] ?? null,
        'controller synthetic isolation profile',
    );
    $sentinelName = 'PHPTHIS_AGENT_EVALUATION_SECRET_SENTINEL';
    $sentinelValue = 'controller-secret-sentinel-do-not-retain';
    putenv($sentinelName . '=' . $sentinelValue);

    try {
        $result = agentEvaluationControllerExecuteSynthetic(
            $root,
            $dependencies,
            $temporaryRoot . '/run',
            [
                'run_id' => '00000000000000000000000000000042',
                'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID,
            ],
            $profile,
        );
    } finally {
        putenv($sentinelName);
    }

    agentEvaluationControllerTest(
        $result['automated_status'] === 'pass' && $result['weighted_score'] === 100,
        'The deterministic controller lifecycle must derive one complete synthetic pass.',
    );
    agentEvaluationControllerTest(
        $result['cleanup']['status'] === 'pass'
        && array_map('basename', $result['cleanup']['removed']) === ['scoring'],
        'The final cleanup must remove the remaining scoring workspace.',
    );
    agentEvaluationControllerTest(
        !is_dir($temporaryRoot . '/run/candidate')
        && !is_dir($temporaryRoot . '/run/baseline')
        && !is_dir($temporaryRoot . '/run/dependencies')
        && !is_dir($temporaryRoot . '/run/scoring')
        && is_dir($result['evidence_root']),
        'Only retained evidence may survive the successful synthetic lifecycle.',
    );

    $runRecord = agentEvaluationJsonFile($result['run_record_path']);
    $scoreRecord = agentEvaluationJsonFile($result['score_record_path']);
    agentEvaluationValidateRunRecord($runRecord, $task);
    agentEvaluationValidateRunArtifacts($runRecord, $result['evidence_root']);
    agentEvaluationValidateScoreRecord(
        $scoreRecord,
        $task,
        $runRecord,
        agentEvaluationFileHash($result['run_record_path'], 'controller self-test run record'),
    );
    $wrongScoreLink = $scoreRecord;
    $wrongScoreLink['run_id'] = '00000000000000000000000000000099';
    agentEvaluationControllerExpectFailure(
        static function () use ($wrongScoreLink, $task, $runRecord, $result): void {
            agentEvaluationValidateScoreRecord(
                $wrongScoreLink,
                $task,
                $runRecord,
                agentEvaluationFileHash(
                    $result['run_record_path'],
                    'controller self-test linked run record',
                ),
            );
        },
        'Score record run ID does not match the validated run record.',
    );
    $evidenceManifest = agentEvaluationJsonFile($result['evidence_manifest_path']);
    agentEvaluationControllerTest(
        ($evidenceManifest['expected_phase_order'] ?? null) === AGENT_EVALUATION_CONTROLLER_PHASES
        && ($evidenceManifest['observed_phases'] ?? null) === AGENT_EVALUATION_CONTROLLER_PHASES
        && ($evidenceManifest['synthetic'] ?? null) === true
        && ($evidenceManifest['comparative_claims'] ?? null) === false,
        'Retained evidence must record the exact complete non-comparative synthetic phase order.',
    );

    $requiredEvidence = [
        'application-check.json',
        'candidate.manifest',
        'candidate.patch',
        'cleanup.json',
        'events.jsonl',
        'external-actions.json',
        'freeze.json',
        'generation-cleanup.json',
        'generation-process.json',
        'generation.stderr',
        'prepared-dependencies.manifest',
        'profile.json',
        'prompt.md',
        'public-scorer.json',
        'resource-inspection.json',
        'response.txt',
        'rubric.md',
        'run.json',
        'score.json',
        'source-prompt.md',
        'source-skeleton.manifest',
        'task.json',
        'validation.json',
        'workspace-policy.json',
    ];
    $manifestArtifacts = agentEvaluationValueObject(
        $evidenceManifest['artifacts'] ?? null,
        'controller evidence artifacts',
    );
    agentEvaluationControllerTest(
        str_contains((string) file_get_contents($result['evidence_root'] . '/generation.stderr'),
            'Received prompt SHA256: ' . agentEvaluationFileHash($result['evidence_root'] . '/prompt.md', 'delivered prompt')),
        'The fake process must receive the exact retained effective prompt on stdin.',
    );

    agentEvaluationControllerTest(
        array_keys($manifestArtifacts) === $requiredEvidence,
        'Evidence manifest must bind the complete exact retained artifact set; observed: '
            . implode(', ', array_keys($manifestArtifacts)),
    );

    foreach ($requiredEvidence as $artifact) {
        $path = agentEvaluationControllerValidateRetainedArtifact(
            $result['evidence_root'],
            $artifact,
            AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
        );
        $descriptor = agentEvaluationValueObject(
            $manifestArtifacts[$artifact] ?? null,
            "controller evidence descriptor {$artifact}",
        );
        agentEvaluationRequireExactKeys(
            $descriptor,
            ['bytes', 'sha256'],
            "controller evidence descriptor {$artifact}",
        );
        $source = file_get_contents($path);
        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);

        agentEvaluationControllerTest(
            is_string($source) && !str_contains($source, $sentinelValue),
            'Ambient secret sentinels must not enter retained controller evidence.',
        );
        agentEvaluationControllerTest(
            is_int($bytes)
            && is_string($sha256)
            && ($descriptor['bytes'] ?? null) === $bytes
            && ($descriptor['sha256'] ?? null) === $sha256,
            "Evidence manifest must bind exact bytes and SHA-256 for {$artifact}.",
        );
    }

    agentEvaluationControllerValidateRetainedArtifact(
        $result['evidence_root'],
        'evidence-manifest.json',
        AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
    );
    $boundedArtifact = $result['evidence_root'] . '/bounded-artifact.control';

    if (file_put_contents($boundedArtifact, 'xx', LOCK_EX) !== 2 || !chmod($boundedArtifact, 0600)) {
        throw new RuntimeException('Unable to prepare the retained-artifact bound control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($result): void {
            agentEvaluationControllerValidateRetainedArtifact(
                $result['evidence_root'],
                'bounded-artifact.control',
                1,
            );
        },
        'retained artifact exceeds its bounded file size.',
    );

    if (!unlink($boundedArtifact)) {
        throw new RuntimeException('Unable to remove the retained-artifact bound control.');
    }

    $events = file_get_contents($result['evidence_root'] . '/events.jsonl');
    agentEvaluationControllerTest(
        is_string($events)
        && str_contains($events, 'src/PingHandler.php')
        && !str_contains($events, 'holdout.php.fixture'),
        'Generation events must record the bounded candidate change without exposing the scorer.',
    );

    agentEvaluationControllerExpectFailure(
        static function () use ($task): void {
            agentEvaluationControllerValidateRequest(
                ['run_id' => str_repeat('0', 32), 'task_id' => 'unlisted.task'],
                $task,
            );
        },
        'Controller request must select change.simple-ping.',
    );

    $wrongRunner = $profile;
    $wrongRunnerRunner = agentEvaluationValueObject(
        $wrongRunner['runner'] ?? null,
        'controller self-test runner',
    );
    $wrongRunnerRunner['name'] = AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER;
    $wrongRunner['runner'] = $wrongRunnerRunner;
    agentEvaluationControllerExpectFailure(
        static function () use ($wrongRunner, $task): void {
            agentEvaluationControllerValidateProfile($wrongRunner, $task, true);
        },
        'Controller execution profile must use the fixed fake-codex runner.',
    );
    $forgedProfile = $profile;
    $forgedProfile['condition'] = 'unrecorded-synthetic-condition';
    agentEvaluationControllerExpectFailure(
        static function () use ($forgedProfile, $task): void {
            agentEvaluationControllerValidateProfile($forgedProfile, $task, true);
        },
        'Controller synthetic condition must equal its fixed fixture identity.',
    );
    $forgedUidProfile = $profile;
    $forgedUidIsolation = agentEvaluationValueObject(
        $forgedUidProfile['isolation'] ?? null,
        'controller forged-UID isolation',
    );
    $forgedUidIsolation['uid'] = AGENT_EVALUATION_CONTROLLER_FAKE_UID - 1;
    $forgedUidProfile['isolation'] = $forgedUidIsolation;
    agentEvaluationControllerExpectFailure(
        static function () use ($forgedUidProfile, $task): void {
            agentEvaluationControllerValidateProfile($forgedUidProfile, $task, true);
        },
        'Controller synthetic isolation UID must equal its fixed fixture identity.',
    );
    agentEvaluationControllerExpectFailure(
        static function () use ($root, $dependencies, $profile): void {
            agentEvaluationControllerExecuteSynthetic(
                $root,
                $dependencies,
                $root . '/controller-run-inside-repository',
                [
                    'run_id' => '00000000000000000000000000000043',
                    'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID,
                ],
                $profile,
            );
        },
        'Controller run root must be separate from the maintainer repository.',
    );
    agentEvaluationControllerTest(
        !file_exists($root . '/controller-run-inside-repository'),
        'Repository-overlap rejection must occur before any candidate path is created.',
    );
    $failedRunRoot = $temporaryRoot . '/failed-controller-run';
    agentEvaluationControllerExpectFailure(
        static function () use ($root, $dependencies, $failedRunRoot, $profile): void {
            agentEvaluationControllerExecuteSynthetic(
                $root,
                $dependencies,
                $failedRunRoot,
                [
                    'run_id' => '00000000000000000000000000000044',
                    'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID,
                ],
                $profile,
                'generate',
            );
        },
        'AGENT_EVALUATION_CONTROLLER_RUN_FAILED primary=generate:RuntimeException cleanup=none',
    );
    $failedManifest = agentEvaluationJsonFile($failedRunRoot . '/evidence/evidence-manifest.json');
    $failedPrimary = agentEvaluationValueObject(
        $failedManifest['primary_failure'] ?? null,
        'failed controller primary outcome',
    );
    agentEvaluationControllerTest(
        ($failedManifest['observed_phases'] ?? null) === ['prepare', 'generate', 'cleanup']
        && ($failedPrimary['phase'] ?? null) === 'generate'
        && ($failedPrimary['class'] ?? null) === RuntimeException::class
        && !isset($failedPrimary['reason_code'])
        && ($failedManifest['cleanup_failure'] ?? null) === null
        && !is_dir($failedRunRoot . '/candidate')
        && !is_dir($failedRunRoot . '/baseline')
        && !is_dir($failedRunRoot . '/dependencies'),
        'A failed synthetic lifecycle must retain its partial phase and complete cleanup evidence.',
    );
    agentEvaluationControllerRemoveTree($failedRunRoot);

    $combinedFailureRoot = $temporaryRoot . '/combined-failure-run';
    agentEvaluationControllerExpectFailure(
        static function () use ($root, $dependencies, $combinedFailureRoot, $profile): void {
            agentEvaluationControllerExecuteSynthetic(
                $root,
                $dependencies,
                $combinedFailureRoot,
                [
                    'run_id' => '00000000000000000000000000000045',
                    'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID,
                ],
                $profile,
                'generate-and-cleanup',
            );
        },
        'AGENT_EVALUATION_CONTROLLER_RUN_FAILED primary=generate:RuntimeException cleanup=RuntimeException',
    );
    $combinedManifest = agentEvaluationJsonFile(
        $combinedFailureRoot . '/evidence/evidence-manifest.json',
    );
    $combinedPrimary = agentEvaluationValueObject(
        $combinedManifest['primary_failure'] ?? null,
        'combined controller primary outcome',
    );
    $combinedCleanup = agentEvaluationValueObject(
        $combinedManifest['cleanup_failure'] ?? null,
        'combined controller cleanup outcome',
    );
    agentEvaluationControllerTest(
        ($combinedManifest['observed_phases'] ?? null) === ['prepare', 'generate', 'cleanup']
        && ($combinedPrimary['phase'] ?? null) === 'generate'
        && ($combinedPrimary['class'] ?? null) === RuntimeException::class
        && ($combinedCleanup['class'] ?? null) === RuntimeException::class
        && file_exists($combinedFailureRoot . '/unexpected-cleanup.control'),
        'Cleanup failure must be retained beside, and never replace, the primary failure.',
    );

    if (!unlink($combinedFailureRoot . '/unexpected-cleanup.control')) {
        throw new RuntimeException('Unable to remove the fixed combined-failure control.');
    }

    agentEvaluationControllerRemoveTree($combinedFailureRoot);

    $duplicateNames = agentEvaluationControllerParseCodexEvents(
        "{\"type\":\"thread.started\",\"t\\u0079pe\":\"turn.started\",\"thread_id\":\"x\"}\n",
        $task['budgets']['model_tokens'],
    );
    agentEvaluationControllerTest(
        $duplicateNames['valid'] === false,
        'Codex JSONL must reject escaped-equivalent duplicate object names.',
    );
    $postTerminal = agentEvaluationControllerParseCodexEvents(
        "{\"type\":\"thread.started\",\"thread_id\":\"x\"}\n"
        . "{\"type\":\"turn.started\"}\n"
        . "{\"type\":\"turn.completed\",\"usage\":{\"input_tokens\":1,\"cached_input_tokens\":0,\"output_tokens\":1,\"reasoning_output_tokens\":0}}\n"
        . "{\"type\":\"item.completed\",\"item\":{\"type\":\"agent_message\",\"text\":\"late\"}}\n",
        $task['budgets']['model_tokens'],
    );
    agentEvaluationControllerTest(
        $postTerminal['valid'] === false,
        'Codex JSONL must reject every event after the terminal event.',
    );
    $attemptedCommand = agentEvaluationControllerParseCodexEvents(
        "{\"type\":\"thread.started\",\"thread_id\":\"x\"}\n"
        . "{\"type\":\"turn.started\"}\n"
        . "{\"type\":\"item.started\",\"item\":{\"id\":\"command_1\",\"type\":\"command_execution\",\"command\":\"curl https://example.invalid\"}}\n"
        . "{\"type\":\"item.completed\",\"item\":{\"id\":\"item_1\",\"type\":\"file_change\",\"status\":\"completed\",\"paths\":[\"src/HealthRoutes.php\",\"src/PingHandler.php\",\"tests/run.php\"]}}\n"
        . "{\"type\":\"item.completed\",\"item\":{\"id\":\"item_2\",\"type\":\"agent_message\",\"text\":\"done\"}}\n"
        . "{\"type\":\"turn.completed\",\"usage\":{\"input_tokens\":1,\"cached_input_tokens\":0,\"output_tokens\":1,\"reasoning_output_tokens\":0}}\n",
        $task['budgets']['model_tokens'],
    );
    agentEvaluationControllerTest(
        $attemptedCommand['valid']
        && !agentEvaluationControllerSyntheticExternalActionsApproved($attemptedCommand['events']),
        'Any observed command or external-action item must make the synthetic run inadmissible.',
    );
    $wrongPhaseOrder = [];
    agentEvaluationControllerExpectFailure(
        static function () use (&$wrongPhaseOrder): void {
            agentEvaluationControllerEnterPhase($wrongPhaseOrder, 'generate');
        },
        'Controller phase order changed before generate.',
    );

    agentEvaluationControllerTestProxyControls();
    agentEvaluationControllerTestProxySpending();
    agentEvaluationControllerTestProxyCalibrationV2();
    agentEvaluationControllerTestProxyResponseByteBounds();
    agentEvaluationControllerTestProxyKeepalive();
    agentEvaluationControllerTestResponseDiagnostics();
    agentEvaluationControllerTestProviderErrorDiagnostics();
    agentEvaluationControllerTestEventIdentityDiagnostics();
    agentEvaluationControllerTestObservationSlot();
    agentEvaluationControllerTestComparisonWorkspace($root, $dependencies, $temporaryRoot);
    agentEvaluationControllerTestSharedReferenceSnapshot($root, $temporaryRoot);
    agentEvaluationControllerTestComparisonObservations();
    agentEvaluationControllerTestComparisonCleanupSequence($root, $temporaryRoot);
    agentEvaluationControllerTestComparisonContinuation($temporaryRoot);
    agentEvaluationControllerTestComparisonBinaryEvidence($temporaryRoot);
    agentEvaluationControllerTestGenerationPolicy($root, $temporaryRoot);
    agentEvaluationControllerTestUpstreamFailureObservations();
    agentEvaluationControllerTestComparisonMeasurements($root, $temporaryRoot);
    agentEvaluationControllerTestLiveConfiguration($root, $temporaryRoot, $task['budgets']);
    agentEvaluationControllerTestLiveFailureEvidence($temporaryRoot);
    agentEvaluationControllerTestUnsettledCleanup($temporaryRoot);
    agentEvaluationControllerTestLiveUsage();
    agentEvaluationControllerTestArchiveControls($root);
    agentEvaluationControllerTestImageResolution($temporaryRoot);
    agentEvaluationControllerTestProcessBounds($root);
    agentEvaluationControllerTestCliGrammar($root);
    agentEvaluationControllerTestWorkspaceControls(
        $root,
        $dependencies,
        $temporaryRoot,
        $task,
    );

    $failedScore = agentEvaluationControllerDeriveScore([
        'admissible' => false,
        'manifest_valid' => true,
        'workspace_policy' => true,
        'application_check' => true,
        'public_scorer' => true,
        'resource_bounds' => true,
    ]);
    agentEvaluationControllerTest(
        $failedScore['automated_status'] === 'fail' && $failedScore['weighted_score'] === 100,
        'Inadmissibility must override a complete numeric synthetic score.',
    );

    $liveCandidate = $temporaryRoot . '/live-candidate';
    agentEvaluationControllerCopyTree($root . '/skeleton', $liveCandidate, 'live-unavailable control', true);
    $insideScorer = $liveCandidate . '/inside-scorer.php';

    if (file_put_contents($insideScorer, "<?php\n", LOCK_EX) !== 6 || !chmod($insideScorer, 0644)) {
        throw new RuntimeException('Unable to prepare the scorer-boundary control.');
    }

    agentEvaluationControllerExpectFailureContains(
        static function () use ($liveCandidate, $insideScorer, $task, $syntheticIsolation): void {
            agentEvaluationControllerScoreFrozenCandidate(
                $liveCandidate,
                $insideScorer,
                [
                    'manifest_valid' => true,
                    'workspace_policy' => true,
                    'frozen_before_scoring' => true,
                    'scorer_integrity' => true,
                    'external_actions_approved' => true,
                    'generation_cleanup' => true,
                ],
                $task['budgets'],
                $syntheticIsolation,
                true,
            );
        },
        'AGENT_EVALUATION_CONTROLLER_SCORER_BOUNDARY_INVALID',
    );
    $tamperedScorer = $temporaryRoot . '/tampered-scorer.php';
    $publicScorer = $root . '/tools/agent-evaluation/tasks/change.simple-ping/public/holdout.php.fixture';

    if (!copy($publicScorer, $tamperedScorer) || file_put_contents($tamperedScorer, "\n", FILE_APPEND) === false) {
        throw new RuntimeException('Unable to prepare the scorer-integrity control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($tamperedScorer, $task): void {
            agentEvaluationRequireFileHash(
                $tamperedScorer,
                $task['public_scorer']['sha256'],
                'controller scorer-integrity control',
            );
        },
        'controller scorer-integrity control SHA-256 does not match its recorded hash.',
    );
    $liveIsolation = agentEvaluationControllerLiveIsolationProfile($task['budgets']);
    $liveScoringIsolation = $liveIsolation;
    $liveScoringIsolation['credential_broker'] = 'none';
    $liveScoringIsolation['network'] = 'none';
    agentEvaluationControllerExpectFailureContains(
        static function () use ($liveCandidate, $task, $liveIsolation): void {
            agentEvaluationControllerRunCodex(
                $liveCandidate,
                'Synthetic live-unavailable control.',
                'gpt-5.6-codex',
                'high',
                $task['budgets'],
                $liveIsolation,
            );
        },
        AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE,
    );
    agentEvaluationControllerExpectFailureContains(
        static function () use ($liveCandidate, $root, $task, $liveScoringIsolation): void {
            agentEvaluationControllerScoreFrozenCandidate(
                $liveCandidate,
                $root . '/tools/agent-evaluation/tasks/change.simple-ping/public/holdout.php.fixture',
                [
                    'manifest_valid' => true,
                    'workspace_policy' => true,
                    'frozen_before_scoring' => true,
                    'scorer_integrity' => true,
                    'external_actions_approved' => true,
                    'generation_cleanup' => true,
                ],
                $task['budgets'],
                $liveScoringIsolation,
            );
        },
        AGENT_EVALUATION_CONTROLLER_LIVE_SCORING_UNAVAILABLE,
    );

    agentEvaluationControllerTestComparisonReport($root, $temporaryRoot);

    fwrite(
        STDOUT,
        "PASS agent evaluation controller self-test: lifecycle, isolation contracts, evidence, and cleanup controls\n",
    );
} finally {
    umask($previousUmask);
    agentEvaluationControllerTestRemoveTemporaryRoot($temporaryRoot);
}

/**
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets
 * @return array<string, mixed>
 */
function agentEvaluationControllerSyntheticProfile(array $budgets): array
{
    return [
        'condition' => AGENT_EVALUATION_CONTROLLER_FAKE_CONDITION,
        'runner' => [
            'name' => AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER,
            'version' => AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_VERSION,
        ],
        'model' => [
            'provider' => 'synthetic',
            'id' => AGENT_EVALUATION_CONTROLLER_FAKE_MODEL,
            'revision' => AGENT_EVALUATION_CONTROLLER_FAKE_MODEL_REVISION,
            'settings' => ['deterministic' => true],
        ],
        'context' => ['bundle_id' => null, 'bundle_sha256' => null],
        'tools' => [],
        'budgets' => $budgets,
        'isolation' => [
            'launcher' => 'synthetic-test',
            'image_reference' => null,
            'image_digest' => null,
            'credential_broker' => 'none',
            'network' => 'none',
            'root_read_only' => true,
            'capabilities_dropped' => true,
            'no_new_privileges' => true,
            'candidate_git_absent' => true,
            'dependencies_read_only' => true,
            'uid' => AGENT_EVALUATION_CONTROLLER_FAKE_UID,
            'cpu_millis' => AGENT_EVALUATION_CONTROLLER_CPU_MILLIS,
            'memory_bytes' => AGENT_EVALUATION_CONTROLLER_MEMORY_BYTES,
            'disk_bytes' => AGENT_EVALUATION_CONTROLLER_DISK_BYTES,
            'processes' => AGENT_EVALUATION_CONTROLLER_PROCESS_LIMIT,
            'wall_seconds' => $budgets['wall_seconds'],
            'model_tokens' => $budgets['model_tokens'],
            'output_bytes' => $budgets['command_output_bytes'],
            'descendant_cleanup' => 'in-process-fixture',
        ],
    ];
}

/**
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets
 * @return array<string, mixed>
 */
function agentEvaluationControllerLiveIsolationProfile(array $budgets): array
{
    $digest = 'sha256:' . str_repeat('a', 64);

    return [
        'launcher' => AGENT_EVALUATION_CONTROLLER_FUTURE_OCI_LAUNCHER,
        'image_reference' => 'registry.invalid/phpthis/agent-evaluation@' . $digest,
        'image_digest' => $digest,
        'credential_broker' => AGENT_EVALUATION_CONTROLLER_FUTURE_CREDENTIAL_BROKER,
        'network' => 'proxy-only',
        'root_read_only' => true,
        'capabilities_dropped' => true,
        'no_new_privileges' => true,
        'candidate_git_absent' => true,
        'dependencies_read_only' => true,
        'uid' => AGENT_EVALUATION_CONTROLLER_FAKE_UID,
        'cpu_millis' => AGENT_EVALUATION_CONTROLLER_CPU_MILLIS,
        'memory_bytes' => AGENT_EVALUATION_CONTROLLER_MEMORY_BYTES,
        'disk_bytes' => AGENT_EVALUATION_CONTROLLER_DISK_BYTES,
        'processes' => AGENT_EVALUATION_CONTROLLER_PROCESS_LIMIT,
        'wall_seconds' => $budgets['wall_seconds'],
        'model_tokens' => $budgets['model_tokens'],
        'output_bytes' => $budgets['command_output_bytes'],
        'descendant_cleanup' => 'container-destroy',
    ];
}

function agentEvaluationControllerTestGenerationPolicy(string $root, string $temporaryRoot): void
{
    $count = 0;
    foreach (agentEvaluationComparisonTasks($root . '/tools/agent-evaluation') as $task) {
        $source = (string) file_get_contents($task['directory'] . '/' . $task['prompt']['path']);
        $taskBytes = (string) file_get_contents($task['directory'] . '/task.json');
        foreach ($task['conditions'] as $condition) {
            $policy = agentEvaluationControllerWorkspacePolicy(agentEvaluationControllerAdmitComparisonTask($task, $condition));
            foreach ([null, 1, 2] as $revision) {
                $evidence = $temporaryRoot . '/prompt-policy-' . ++$count;
                if (!mkdir($evidence, 0700)) { throw new RuntimeException('Unable to prepare policy evidence control.'); }
                $prompt = agentEvaluationControllerGenerationPrompt($source, $policy, $revision);
                $suffix = $revision === null ? '' : "\n\n" . agentEvaluationControllerCalibrationPrompt($revision);
                $tail = substr($prompt, strlen($source) + 2);
                agentEvaluationControllerTest(str_starts_with($prompt, $source . "\n\n## Enforced evaluation workspace policy (v1)\n")
                    && preg_match('/```json\n(.*?)```/s', $tail, $match) === 1
                    && json_decode($match[1], true, 512, JSON_THROW_ON_ERROR) === $condition['workspace_policy']
                    && ($suffix === '' || str_ends_with($prompt, $suffix)),
                    'Every condition and calibration revision must disclose exactly its enforced six-field policy before the unchanged suffix.');
                $wrapper = agentEvaluationControllerWorkspacePolicyEvidence($policy);
                agentEvaluationControllerTest(array_keys($wrapper) === ['schema_version', 'kind', 'policy']
                    && $wrapper['schema_version'] === 1 && $wrapper['kind'] === 'generation-workspace-policy-v1'
                    && $wrapper['policy'] === $condition['workspace_policy'],
                    'Generation policy evidence must contain only its version and exact condition policy.');
                $documents = ['task.json' => $taskBytes, 'source-prompt.md' => $source,
                    'workspace-policy.json' => agentEvaluationJson($wrapper), 'prompt.md' => $prompt,
                    'generation-process.json' => agentEvaluationJson(['elapsed_milliseconds' => 0])];
                $attempt = ['status' => 'failed', 'condition' => $condition['id'], 'task_manifest_sha256' => $task['manifest_sha256'],
                    'usage' => ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null],
                    'elapsed_milliseconds' => 0];
                foreach (['valid', 'policy-and-prompt', 'source', 'legacy', 'version', 'kind', 'suffix', 'condition',
                    'stripped-freeze', 'stripped-score', 'stripped-validate', 'stripped-retain',
                    'missing-source-prompt.md', 'missing-workspace-policy.json', 'missing-prompt.md', 'missing-task.json'] as $mode) {
                    $changed = $documents;
                    $observed = $attempt;
                    if ($mode === 'policy-and-prompt') {
                        $widened = $policy;
                        $widened['allowed_new_paths'][] = 'src/ExtraHelper.php';
                        $widened['max_changed_files']++;
                        $changed['workspace-policy.json'] = agentEvaluationJson(agentEvaluationControllerWorkspacePolicyEvidence($widened));
                        $changed['prompt.md'] = agentEvaluationControllerGenerationPrompt($source, $widened, $revision);
                    }
                    if ($mode === 'source') { $changed['source-prompt.md'] .= "\nChanged task\n"; }
                    if ($mode === 'legacy') { $changed['prompt.md'] = $source . $suffix; }
                    if ($mode === 'version') { $changed['workspace-policy.json'] = agentEvaluationJson([...$wrapper, 'schema_version' => 2]); }
                    if ($mode === 'kind') { $changed['workspace-policy.json'] = agentEvaluationJson([...$wrapper, 'kind' => 'unapproved-policy']); }
                    if ($mode === 'suffix') { $changed['prompt.md'] = agentEvaluationControllerGenerationPrompt($source, $policy, $revision === 1 ? 2 : 1); }
                    if ($mode === 'condition') { $observed['condition'] = $task['conditions'][$condition['id'] === $task['conditions'][0]['id'] ? 1 : 0]['id']; }
                    if (str_starts_with($mode, 'missing-')) { unset($changed[substr($mode, 8)]); }
                    if (str_starts_with($mode, 'stripped-')) {
                        $changed = [];
                        $observed['phase'] = substr($mode, 9);
                        $observed['elapsed_milliseconds'] = null;
                    }
                    foreach ($documents as $name => $_) {
                        $path = $evidence . '/' . $name;
                        if (is_file($path) && !unlink($path)) { throw new RuntimeException('Unable to replace prompt evidence control.'); }
                    }
                    foreach ($changed as $name => $bytes) { agentEvaluationControllerWriteArtifact($evidence, $name, $bytes); }
                    // Rehash every artifact: a self-consistent altered prompt still cannot widen the original task policy.
                    $observed['artifacts'] = agentEvaluationControllerComparisonArtifacts($evidence);
                    $accepted = true;
                    try { agentEvaluationControllerValidateComparisonAttemptMeasurements($observed, $evidence, 40_000, $revision); }
                    catch (RuntimeException) { $accepted = false; }
                    agentEvaluationControllerTest($accepted === ($mode === 'valid'),
                        'Generated failed-attempt evidence must retain its exact source, selected policy, and effective prompt: ' . $mode);
                }
            }
            foreach (['allowed_existing_paths', 'allowed_new_paths'] as $emptyKey) {
                $oneSided = $policy;
                $oneSided[$emptyKey] = [];
                agentEvaluationControllerTest(agentEvaluationControllerWorkspacePolicyEvidence($oneSided)['policy'] === $oneSided,
                    'Policy rendering must preserve comparison policies permitting only existing edits or only new files.');
            }
        }
    }
    agentEvaluationControllerTest($count === 18, 'Policy controls must cover all six conditions in ordinary and both calibration modes.');
    $boundTask = agentEvaluationComparisonTasks($root . '/tools/agent-evaluation')[0];
    $policy = agentEvaluationControllerWorkspacePolicy(agentEvaluationControllerAdmitComparisonTask($boundTask, $boundTask['conditions'][0]));
    $overhead = strlen(agentEvaluationControllerGenerationPrompt('x', $policy, 2)) - 1;
    $largest = str_repeat('x', AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES - $overhead);
    agentEvaluationControllerTest(strlen(agentEvaluationControllerGenerationPrompt($largest, $policy, 2)) === AGENT_EVALUATION_CONTROLLER_MAX_PROMPT_BYTES,
        'The effective prompt bound must include the policy block and calibration suffix.');
    foreach (['', "invalid\0prompt", $largest . 'x'] as $invalid) {
        agentEvaluationControllerExpectFailure(static function () use ($invalid, $policy): void {
            agentEvaluationControllerGenerationPrompt($invalid, $policy, 2);
        }, 'AGENT_EVALUATION_CONTROLLER_LIVE_PROMPT_INVALID');
    }
    agentEvaluationControllerExpectFailureContains(static function () use ($policy): void {
        agentEvaluationControllerWorkspacePolicyEvidence([...$policy, 'private_path' => '/do-not-disclose/secret']);
    }, 'controller workspace policy must contain exactly:');
}

function agentEvaluationControllerTestUpstreamFailureObservations(): void
{
    $secret = 'synthetic-upstream-secret-never-retained';
    foreach (['input_tokens', 'responses'] as $operation) {
        agentEvaluationControllerTest(
            agentEvaluationControllerUpstreamFailureObservation($operation, true, 200, 0, false) === null
            && agentEvaluationControllerUpstreamFailureObservation($operation, true, 200, 28, false) === null
            && agentEvaluationControllerUpstreamFailureObservation($operation, true, 200, $secret, false) === null,
            'Successful transfer and exact HTTP 200 must preserve the existing success criterion independently of curl metadata.');
        $http = agentEvaluationControllerUpstreamFailureObservation($operation, true, 429, 0, false);
        agentEvaluationControllerTest($http === ['schema_version' => 1, 'operation' => $operation, 'category' => 'http_status',
            'http_status' => 429, 'curl_code' => 0, 'response_limit_exceeded' => false],
            'Non-200 upstream status must retain only its exact bounded structural observation.');
        $curl = agentEvaluationControllerUpstreamFailureObservation($operation, false, 503, 28, false);
        agentEvaluationControllerTest($curl === ['schema_version' => 1, 'operation' => $operation, 'category' => 'curl',
            'http_status' => 503, 'curl_code' => 28, 'response_limit_exceeded' => false],
            'A failed transfer must take category precedence over its observed HTTP status.');
        foreach ([false, true] as $transferSucceeded) {
            $limited = agentEvaluationControllerUpstreamFailureObservation($operation, $transferSucceeded, 200, 23, true);
            agentEvaluationControllerTest($limited === ['schema_version' => 1, 'operation' => $operation, 'category' => 'response_limit',
                'http_status' => 200, 'curl_code' => 23, 'response_limit_exceeded' => true],
                'Callback-owned response overflow must take precedence even over the impossible successful-transfer combination.');
        }
        foreach ([[100, 100], [599, 599], [99, null], [600, null], [0, null], [-1, null],
            [PHP_INT_MAX, null], ['200', null], [200.0, null], [true, null], [null, null],
            [[$secret], null], [new stdClass(), null]] as [$status, $expectedStatus]) {
            $observation = agentEvaluationControllerUpstreamFailureObservation($operation, false, $status, 28, false);
            if ($observation === null) {
                throw new RuntimeException('A failed transfer must retain a bounded observation.');
            }
            agentEvaluationControllerTest($observation['http_status'] === $expectedStatus
                && !str_contains(agentEvaluationJson($observation), $secret),
                'Only integer HTTP statuses within the exact inclusive range may survive the diagnostic projection.');
        }
        foreach ([[0, 0], [999, 999], [-1, null], [1_000, null], [PHP_INT_MAX, null], ['28', null],
            [28.0, null], [false, null], [null, null], [$secret, null], [[$secret], null],
            [new stdClass(), null]] as [$code, $expectedCode]) {
            $observation = agentEvaluationControllerUpstreamFailureObservation($operation, false, 0, $code, false);
            if ($observation === null) {
                throw new RuntimeException('A failed transfer must retain a bounded observation.');
            }
            agentEvaluationControllerTest($observation['http_status'] === null
                && $observation['curl_code'] === $expectedCode
                && !str_contains(agentEvaluationJson($observation), $secret),
                'Only bounded integer curl codes may survive; unavailable HTTP status and hostile raw metadata remain absent.');
        }
    }
    agentEvaluationControllerExpectFailure(static function () use ($secret): void {
        agentEvaluationControllerUpstreamFailureObservation($secret, false, 500, 0, false);
    }, 'Upstream failure operation is invalid.');
    agentEvaluationControllerExpectFailure(static function (): void {
        agentEvaluationControllerOciUpstream('unsupported', '', '', 1);
    }, 'AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_UNAVAILABLE');

    $observation = agentEvaluationControllerUpstreamFailureObservation('input_tokens', true, 429, 0, false);
    if ($observation === null) {
        throw new RuntimeException('The HTTP refusal control must retain its observation.');
    }
    $generation = ['termination_reason' => 'process_failed', 'failure_code' => 'AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_FAILED',
        'upstream_failure' => $observation];
    foreach (['process_failed', 'wall_time_limit', 'memory_limit', 'cleanup_failed'] as $reason) {
        agentEvaluationControllerValidateUpstreamFailureEvidence([...$generation, 'termination_reason' => $reason]);
    }
    agentEvaluationControllerValidateUpstreamFailureEvidence(['termination_reason' => 'completed']);
    agentEvaluationControllerValidateUpstreamFailureEvidence(['termination_reason' => 'completed', 'upstream_failure' => null]);
    agentEvaluationControllerValidateUpstreamFailureEvidence([...$generation, 'upstream_failure' => null]);
    $invalid = [
        [...$observation, 'headers' => $secret], [...$observation, 'schema_version' => 2],
        [...$observation, 'operation' => $secret], [...$observation, 'category' => $secret],
        [...$observation, 'http_status' => 99], [...$observation, 'http_status' => 600],
        [...$observation, 'http_status' => '429'], [...$observation, 'http_status' => 429.0],
        [...$observation, 'curl_code' => -1], [...$observation, 'curl_code' => 1_000],
        [...$observation, 'curl_code' => '0'], [...$observation, 'response_limit_exceeded' => 'false'],
        [...$observation, 'category' => 'response_limit'], [...$observation, 'category' => 'curl', 'response_limit_exceeded' => true],
        [...$observation, 'http_status' => 200], $secret, [],
    ];
    $missing = $observation;
    unset($missing['http_status']);
    $invalid[] = $missing;
    foreach ($invalid as $value) {
        $rejected = false;
        try {
            agentEvaluationControllerValidateUpstreamFailureEvidence([...$generation, 'upstream_failure' => $value]);
        } catch (RuntimeException) {
            $rejected = true;
        }
        agentEvaluationControllerTest($rejected,
            'Malformed, unbounded, inconsistent or secret-bearing upstream observations must fail exact-shape validation.');
    }
    foreach ([['termination_reason' => 'completed'], ['failure_code' => 'AGENT_EVALUATION_CONTROLLER_OCI_RELAY_INCOMPLETE'],
        ['failure_code' => null]] as $change) {
        agentEvaluationControllerExpectFailure(static function () use ($generation, $change): void {
            agentEvaluationControllerValidateUpstreamFailureEvidence([...$generation, ...$change]);
        }, 'Upstream failure observation requires its failed generation operation.');
    }
}

function agentEvaluationControllerTestComparisonMeasurements(string $root, string $temporaryRoot): void
{
    $task = agentEvaluationComparisonTasks($root . '/tools/agent-evaluation')[0];
    $condition = $task['conditions'][0];
    $policy = agentEvaluationControllerWorkspacePolicy(agentEvaluationControllerAdmitComparisonTask($task, $condition));
    $source = (string) file_get_contents($task['directory'] . '/' . $task['prompt']['path']);
    $unknown = ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null];
    $known = ['input_tokens' => 10, 'output_tokens' => 4, 'cached_tokens' => 2, 'reasoning_tokens' => 1];
    $baseLedger = [...agentEvaluationControllerProxyState('gpt-5.2-codex', 'high', 40_000), ...$known,
        'request_count' => 1, 'observed_request_count' => 1];
    $baseDocuments = [
        'proxy.json' => ['ledger' => $baseLedger, 'synthetic_upstream' => false, 'upstream_origin' => 'https://api.openai.com'],
        'generation-process.json' => ['elapsed_milliseconds' => 87, 'termination_reason' => 'completed', 'exit_code' => 0,
            'synthetic_upstream' => false, 'timed_out' => false, 'output_limit_exceeded' => false,
            'cleanup' => ['container_stopped' => true, 'oom_killed' => false, 'pid' => 0]],
        'generation-cleanup.json' => ['status' => 'pass', 'oci' => ['status' => 'pass', 'generation_destroyed' => true]],
        'oci-cleanup.json' => ['status' => 'pass', 'verified' => true, 'containers_remaining' => 0, 'volumes_remaining' => 0],
        'cleanup.json' => ['status' => 'pass', 'primary_failure' => null, 'cleanup_failure' => null],
    ];
    foreach (['absent-valid', 'absent-prepare', 'absent-generate', 'absent-cleanup', 'absent-tokens', 'absent-time', 'failed-known', 'complete-valid', 'wrong-tokens', 'wrong-time',
        'inflight-unknown', 'inflight-claimed', 'complete-inflight', 'synthetic', 'wrong-origin', 'no-request',
        'unsettled-hash', 'process-failed', 'generation-not-stopped', 'destroy-failed', 'missing-cleanup', 'cleanup-failed',
        'upstream-count-failed', 'upstream-create-failed', 'upstream-count-malformed', 'upstream-create-malformed',
        'upstream-stale-complete', 'upstream-null-complete', 'upstream-code-mismatch',
        'upstream-wall-precedence', 'upstream-memory-precedence', 'upstream-cleanup-precedence'] as $mode) {
        $evidence = $temporaryRoot . '/measurements-' . $mode;
        if (!mkdir($evidence, 0700)) {
            throw new RuntimeException('Unable to create the measurement evidence control.');
        }
        $documents = $baseDocuments;
        $attempt = ['status' => 'complete', 'usage' => $known, 'elapsed_milliseconds' => 87];
        if (str_starts_with($mode, 'absent-')) {
            $documents = [];
            $attempt = ['status' => 'failed', 'usage' => $unknown, 'elapsed_milliseconds' => null];
        }
        if (in_array($mode, ['absent-prepare', 'absent-generate', 'absent-cleanup'], true)) { $attempt['phase'] = substr($mode, 7); }
        if ($mode === 'absent-tokens') { $attempt['usage']['input_tokens'] = 0; }
        if ($mode === 'absent-time') { $attempt['elapsed_milliseconds'] = 0; }
        if ($mode === 'failed-known') { $attempt['status'] = 'failed'; $documents['generation-process.json']['termination_reason'] = 'process_failed'; }
        if ($mode === 'wrong-tokens') { $attempt['usage']['input_tokens'] = 11; }
        if ($mode === 'wrong-time') { $attempt['elapsed_milliseconds'] = 88; }
        if (in_array($mode, ['inflight-unknown', 'inflight-claimed', 'complete-inflight'], true)) {
            $documents['proxy.json']['ledger']['reserved_input'] = 20;
            $documents['proxy.json']['ledger']['reserved_output'] = 100;
            $documents['proxy.json']['ledger']['request_sha256'] = str_repeat('a', 64);
            $attempt['status'] = $mode === 'complete-inflight' ? 'complete' : 'failed';
            $attempt['usage'] = $mode === 'inflight-claimed' ? $known : $unknown;
        }
        if ($mode === 'synthetic') { $documents['proxy.json']['synthetic_upstream'] = true; }
        if ($mode === 'wrong-origin') { $documents['proxy.json']['upstream_origin'] = 'http://127.0.0.1:18765'; }
        if ($mode === 'no-request') { $documents['proxy.json']['ledger']['request_count'] = 0; }
        if ($mode === 'unsettled-hash') { $documents['proxy.json']['ledger']['request_sha256'] = str_repeat('a', 64); }
        if ($mode === 'process-failed') { $documents['generation-process.json']['termination_reason'] = 'process_failed'; }
        if ($mode === 'generation-not-stopped') { $documents['generation-process.json']['cleanup']['container_stopped'] = false; }
        if ($mode === 'destroy-failed') { $documents['generation-cleanup.json']['oci']['generation_destroyed'] = false; }
        if ($mode === 'missing-cleanup') { unset($documents['generation-cleanup.json']); }
        if ($mode === 'cleanup-failed') { $documents['cleanup.json']['status'] = 'fail'; }
        if (str_starts_with($mode, 'upstream-')) {
            $documents['generation-process.json']['upstream_failure'] = null;
            if ($mode !== 'upstream-null-complete') {
                $create = in_array($mode, ['upstream-create-failed', 'upstream-create-malformed'], true);
                $documents['generation-process.json']['failure_code'] = 'AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_FAILED';
                $documents['generation-process.json']['upstream_failure'] = agentEvaluationControllerUpstreamFailureObservation(
                    $create ? 'responses' : 'input_tokens', false, 503, 28, false);
                if ($mode !== 'upstream-stale-complete') {
                    $attempt['status'] = 'failed';
                    $attempt['phase'] = 'generate';
                    $documents['generation-process.json']['termination_reason'] = 'process_failed';
                    $documents['generation-process.json']['exit_code'] = -1;
                    $documents['proxy.json']['ledger']['blocked'] = true;
                    $documents['proxy.json']['ledger']['observed_request_count'] = 2;
                    $documents['proxy.json']['ledger']['request_sha256'] = str_repeat('b', 64);
                    $documents['proxy.json']['ledger']['spending'] = [
                        'policy' => agentEvaluationControllerCalibrationSpending(), 'settled_units' => 8_050, 'reserved_units' => 0,
                    ];
                }
                if ($create) {
                    $documents['proxy.json']['ledger']['request_count'] = 2;
                    $documents['proxy.json']['ledger']['reserved_input'] = 20;
                    $documents['proxy.json']['ledger']['reserved_output'] = 100;
                    $documents['proxy.json']['ledger']['spending']['reserved_units'] = 155_000;
                    $attempt['usage'] = $unknown;
                }
                if (in_array($mode, ['upstream-count-malformed', 'upstream-create-malformed'], true)) {
                    $observation = agentEvaluationRequireObject($documents['generation-process.json'], 'upstream_failure', 'measurement control');
                    $documents['generation-process.json']['upstream_failure'] = [...$observation, 'http_status' => 'synthetic-secret-not-a-status'];
                }
                if ($mode === 'upstream-code-mismatch') {
                    $documents['generation-process.json']['failure_code'] = 'AGENT_EVALUATION_CONTROLLER_OCI_RELAY_INCOMPLETE';
                }
                foreach (['upstream-wall-precedence' => 'wall_time_limit', 'upstream-memory-precedence' => 'memory_limit',
                    'upstream-cleanup-precedence' => 'cleanup_failed'] as $precedenceMode => $reason) {
                    if ($mode === $precedenceMode) {
                        $documents['generation-process.json']['termination_reason'] = $reason;
                    }
                }
            }
        }
        foreach ($documents as $name => $document) {
            agentEvaluationControllerWriteArtifact($evidence, $name, agentEvaluationJson($document));
        }
        if ($documents !== []) {
            foreach (['task.json' => (string) file_get_contents($task['directory'] . '/task.json'),
                'source-prompt.md' => $source, 'workspace-policy.json' => agentEvaluationJson(agentEvaluationControllerWorkspacePolicyEvidence($policy)),
                'prompt.md' => agentEvaluationControllerGenerationPrompt($source, $policy)] as $name => $bytes) {
                agentEvaluationControllerWriteArtifact($evidence, $name, $bytes);
            }
            $attempt['condition'] = $condition['id'];
            $attempt['task_manifest_sha256'] = $task['manifest_sha256'];
        }
        $attempt['artifacts'] = agentEvaluationControllerComparisonArtifacts($evidence);
        $beforeAttempt = $attempt;
        $beforeProxy = isset($documents['proxy.json']) ? file_get_contents($evidence . '/proxy.json') : null;
        $accepted = true;
        try {
            agentEvaluationControllerValidateComparisonAttemptMeasurements($attempt, $evidence);
        } catch (RuntimeException) {
            $accepted = false;
        }
        agentEvaluationControllerTest($attempt === $beforeAttempt
            && (!isset($documents['proxy.json']) || file_get_contents($evidence . '/proxy.json') === $beforeProxy),
            'Diagnostic evidence admission must not rewrite token usage, money, reservations or attempt outcomes: ' . $mode);
        agentEvaluationControllerTest($accepted === in_array($mode, ['absent-valid', 'absent-prepare', 'absent-generate', 'absent-cleanup', 'failed-known', 'complete-valid', 'inflight-unknown',
            'upstream-count-failed', 'upstream-create-failed', 'upstream-null-complete',
            'upstream-wall-precedence', 'upstream-memory-precedence', 'upstream-cleanup-precedence'], true),
            'Comparison measurement admission must bind actual retained tokens, timing, provider execution, and cleanup: ' . $mode);
    }
}

function agentEvaluationControllerTestComparisonBinaryEvidence(string $temporaryRoot): void
{
    $evidence = $temporaryRoot . '/comparison-binary-evidence';
    if (!mkdir($evidence, 0700)) {
        throw new RuntimeException('Unable to create the binary evidence control.');
    }
    $binary = str_repeat("\0", AGENT_EVALUATION_MAX_JSON_BYTES + 1) . "\xFF\xFE";
    $process = ['exit_code' => 0, 'stdout' => $binary, 'stderr' => "diagnostic\0\xFF", 'termination_reason' => 'completed'];
    $encoded = agentEvaluationControllerComparisonProcessJson($process);
    $path = agentEvaluationControllerWriteArtifact($evidence, 'binary-process.json', $encoded);
    $decoded = agentEvaluationControllerComparisonProcessEvidence($path);
    agentEvaluationControllerTest($decoded['stdout'] === $binary && $decoded['stderr'] === $process['stderr']
        && $decoded['exit_code'] === 0 && $decoded['stream_encoding'] === 'base64'
        && strlen($encoded) < AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
        'Binary candidate output larger than the observation JSON bound must round-trip without UTF-8 failure or escaping expansion.');
    agentEvaluationControllerTest(!agentEvaluationControllerCompareObservation($binary, [])['observation_valid'],
        'Retained binary process evidence must remain an invalid observation rather than a printed verdict.');
    agentEvaluationControllerExpectFailureContains(
        static function () use ($encoded): void { agentEvaluationJsonValue($encoded, 'ordinary record'); },
        'ordinary record must contain bounded JSON bytes.',
    );
    foreach (['malformed' => 'A===', 'noncanonical' => 'YQ', 'whitespace' => "YQ==\n"] as $name => $invalid) {
        $invalidPath = agentEvaluationControllerWriteArtifact($evidence, $name . '.json', agentEvaluationJson([
            'stream_encoding' => 'base64', 'stdout' => $invalid, 'stderr' => '',
        ]));
        agentEvaluationControllerExpectFailureContains(
            static function () use ($invalidPath): void { agentEvaluationControllerComparisonProcessEvidence($invalidPath); },
            'Comparison process stream encoding exceeds its canonical bound.',
        );
    }
}

function agentEvaluationControllerTestComparisonContinuation(string $temporaryRoot): void
{
    $inputs = [
        'a' => ['holdout_path' => $temporaryRoot . '/private/holdout.json', 'execution' => ['prepared_dependencies' => $temporaryRoot . '/visible-a']],
        'b' => ['holdout_path' => $temporaryRoot . '/private/holdout.json', 'execution' => ['prepared_dependencies' => $temporaryRoot . '/visible-b']],
    ];
    agentEvaluationControllerValidateComparisonHoldoutVisibility($inputs);
    $changed = $inputs;
    $changed['a']['holdout_path'] = $temporaryRoot . '/visible-b/holdout.json';
    agentEvaluationControllerExpectFailureContains(
        static function () use ($changed): void { agentEvaluationControllerValidateComparisonHoldoutVisibility($changed); },
        'Every private holdout must be outside every condition generation input.',
    );
    $changed['a']['holdout_path'] = $temporaryRoot . '/visible-b-other/holdout.json';
    agentEvaluationControllerValidateComparisonHoldoutVisibility($changed);
    foreach (['safe', 'reserved', 'pending', 'missing-request-state', 'cleanup-failed', 'quota-rejected', 'unknown-generation'] as $mode) {
        $evidence = $temporaryRoot . '/continuation-' . $mode;
        if (!mkdir($evidence, 0700)) {
            throw new RuntimeException('Unable to create the continuation evidence control.');
        }
        $ledger = agentEvaluationControllerProxyState('gpt-5.2-codex', 'high', 40_000);
        if ($mode === 'reserved') { $ledger['reserved_input'] = 1; $ledger['reserved_output'] = 100; }
        if ($mode === 'pending' || $mode === 'quota-rejected') { $ledger['request_sha256'] = str_repeat('a', 64); }
        if ($mode === 'missing-request-state') { unset($ledger['request_sha256']); }
        if ($mode === 'quota-rejected') { $ledger['failure_reason'] = 'model_token_limit'; $ledger['blocked'] = true; }
        agentEvaluationControllerWriteArtifact($evidence, 'proxy.json', agentEvaluationJson(['ledger' => $ledger]));
        agentEvaluationControllerWriteArtifact($evidence, 'cleanup.json', agentEvaluationJson(['status' => $mode === 'cleanup-failed' ? 'fail' : 'pass']));
        $attempt = ['status' => 'failed', 'phase' => 'generate',
            'termination_reason' => $mode === 'quota-rejected' ? 'model_token_limit' : ($mode === 'unknown-generation' ? 'process_failed' : 'wall_time_limit'),
            'artifacts' => agentEvaluationControllerComparisonArtifacts($evidence)];
        $mustStop = agentEvaluationControllerComparisonMustStop($attempt, $evidence);
        agentEvaluationControllerTest($mustStop === !in_array($mode, ['safe', 'quota-rejected'], true),
            'Only a known bounded failure with verified cleanup and no outstanding billed reservation may continue.');
    }
}

function agentEvaluationControllerTestComparisonObservations(): void
{
    $expected = ['response' => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'],
        'body' => ['ok' => true, 'items' => [['id' => 7]]]],
        'policy_steps' => ['authentication', 'tenant', 'authorization', 'handler'],
        'query' => ['min_statements' => 2, 'max_statements' => 2, 'failures' => 0, 'max_fingerprint_executions' => 1],
        'durable_state' => ['documents' => [['id' => 7]], 'events' => [], 'outbox' => []], 'in_transaction' => false];
    $observed = ['schema_version' => 1,
        'response' => ['status' => 200, 'headers' => ['content-type' => 'application/json'],
            'body' => json_encode($expected['response']['body'], JSON_THROW_ON_ERROR)],
        'policy_steps' => $expected['policy_steps'],
        'query_trace' => ['statements' => 2, 'failures' => 0, 'queries' => [
            ['fingerprint' => 'sha256:' . str_repeat('a', 64), 'executions' => 1, 'failures' => 0],
            ['fingerprint' => 'sha256:' . str_repeat('b', 64), 'executions' => 1, 'failures' => 0],
        ], 'truncated' => false], 'durable_state' => $expected['durable_state'], 'in_transaction' => false];
    $raw = json_encode($observed, JSON_THROW_ON_ERROR);
    agentEvaluationControllerTest(agentEvaluationControllerCompareObservation($raw, $expected) === [
        'observation_valid' => true, 'response' => true, 'policy_order' => true, 'durable_state' => true,
        'transaction_closed' => true, 'query_bounds' => true, 'statements' => 2,
    ], 'A complete matching raw observation must pass each independently derived comparison component.');
    foreach (['', "PASS\n", '[]', '{"schema_version":1,' . substr($raw, 1)] as $invalid) {
        agentEvaluationControllerTest(!agentEvaluationControllerCompareObservation($invalid, $expected)['observation_valid'],
            'Empty output, printed verdicts, non-objects, and duplicate JSON members cannot be observations.');
    }
    foreach ([
        ['schema_version', '1', 'observation_valid'],
        ['policy_steps', ['handler', 'authentication', 'tenant', 'authorization'], 'policy_order'],
        ['durable_state', ['documents' => [], 'events' => [], 'outbox' => []], 'durable_state'],
        ['in_transaction', true, 'transaction_closed'],
    ] as [$field, $value, $failedCheck]) {
        $changed = $observed;
        $changed[$field] = $value;
        agentEvaluationControllerTest(
            agentEvaluationControllerCompareObservation(json_encode($changed, JSON_THROW_ON_ERROR), $expected)[$failedCheck] === false,
            'A forged version, reordered policy, changed durable data, or open transaction must fail its comparison.',
        );
    }
    foreach (['[]', '{"ok":true,"items":{"0":{"id":7}}}'] as $body) {
        $changed = $observed;
        $changed['response']['body'] = $body;
        agentEvaluationControllerTest(!agentEvaluationControllerCompareObservation(json_encode($changed, JSON_THROW_ON_ERROR), $expected)['response'],
            'JSON objects and arrays must retain distinct types, including nested numerically named object members.');
    }
    foreach (['sum', 'truncated', 'duplicate'] as $mode) {
        $changed = $observed;
        if ($mode === 'sum') {
            $changed['query_trace']['statements'] = 3;
        } elseif ($mode === 'truncated') {
            $changed['query_trace']['truncated'] = true;
        } else {
            $changed['query_trace']['queries'][1]['fingerprint'] = 'sha256:' . str_repeat('a', 64);
        }
        $compared = agentEvaluationControllerCompareObservation(json_encode($changed, JSON_THROW_ON_ERROR), $expected);
        agentEvaluationControllerTest(!$compared['query_bounds'], 'Inconsistent sums, truncated traces, and duplicate query entries cannot pass resource checks.');
    }
    $database = ['pdo_drivers' => ['sqlite'], 'pdo_sqlite_version' => '8.4.19', 'sqlite_json1' => true, 'sqlite_version' => '3.40.1'];
    $identity = ['toolchains' => ['generation' => ['database' => $database], 'scoring' => ['database' => $database]]];
    agentEvaluationControllerValidateComparisonDatabase($identity);
    foreach (['missing-sqlite', 'missing-json1', 'different-version'] as $mode) {
        $changed = $identity;
        if ($mode === 'missing-sqlite') { $changed['toolchains']['generation']['database']['pdo_drivers'] = []; }
        elseif ($mode === 'missing-json1') { $changed['toolchains']['generation']['database']['sqlite_json1'] = false; }
        else { $changed['toolchains']['scoring']['database']['sqlite_version'] = '3.41.0'; }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($changed): void { agentEvaluationControllerValidateComparisonDatabase($changed); },
            'Comparison requires matching available PDO SQLite and JSON1 in both runtime images.',
        );
    }
}

function agentEvaluationControllerTestComparisonWorkspace(string $root, string $dependencies, string $temporaryRoot): void
{
    $referenceDirectory = $root . '/tools/agent-evaluation/references/phpstan';
    $referenceTree = agentEvaluationControllerDescribeTree($referenceDirectory, 'shared reference control', true);
    $relativeReference = 'docs/phpstan/return.phpDocType.md';
    $sourceReference = $referenceDirectory . '/return.phpDocType.md';
    $referenceBytes = file_get_contents($sourceReference);
    $sourceMetadata = lstat($sourceReference);
    if (!is_string($referenceBytes) || !is_array($sourceMetadata)) {
        throw new RuntimeException('Unable to inspect the shared reference control source.');
    }
    $identities = [$sourceMetadata['dev'] . ':' . $sourceMetadata['ino'] => true];
    $workspaces = [];
    $admittedTasks = [];
    try {
        foreach (agentEvaluationComparisonTasks($root . '/tools/agent-evaluation') as $task) {
            foreach ($task['conditions'] as $condition) {
                $admitted = agentEvaluationControllerAdmitComparisonTask($task, $condition);
                $workspace = agentEvaluationControllerPrepareWorkspace($condition['base']['directory'], $dependencies,
                    $temporaryRoot . '/comparison-' . $task['id'] . '-' . $condition['id'], $admitted);
                $workspaces[] = $workspace;
                $admittedTasks[] = $admitted;
                agentEvaluationControllerTest(
                    is_file($workspace['candidate_root'] . '/evaluation/observe.php')
                    && !file_exists($workspace['candidate_root'] . '/evaluation/observe.php.fixture')
                    && $workspace['base_fixture_sha256'] === $condition['base']['fixture_sha256']
                    && agentEvaluationControllerWorkspacePolicy($admitted) === $condition['workspace_policy'],
                    'Each comparison condition must materialize its exact source bytes and retain its own protected policy.',
                );
                foreach (['candidate_root' => 0644, 'baseline_root' => 0444] as $key => $expectedMode) {
                    $copy = $workspace[$key] . '/' . $relativeReference;
                    $metadata = lstat($copy);
                    if (!is_array($metadata)) {
                        throw new RuntimeException('Unable to inspect one copied reference identity.');
                    }
                    $tree = agentEvaluationControllerDescribeTree($workspace[$key] . '/docs/phpstan', 'copied reference control', true);
                    agentEvaluationControllerTest(
                        !is_link($copy) && $metadata['nlink'] === 1
                        && ($metadata['mode'] & 07777) === $expectedMode
                        && !isset($identities[$metadata['dev'] . ':' . $metadata['ino']])
                        && $tree['sha256'] === $referenceTree['sha256'],
                        'Every candidate and read-only baseline must contain an independent exact reference snapshot.',
                    );
                    $identities[$metadata['dev'] . ':' . $metadata['ino']] = true;
                }
                $freeze = agentEvaluationControllerFreezeWorkspace($workspace, $admitted);
                agentEvaluationControllerTest($freeze['patch'] === "PHPTHIS-CANDIDATE-PATCH-V1\n" && $freeze['changed_files'] === [],
                    'An unchanged comparison candidate must retain the existing patch header with no changed paths.');
                $patchPath = agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'candidate.patch', $freeze['patch']);
                agentEvaluationControllerTest(file_get_contents($patchPath) === $freeze['patch'], 'The unchanged candidate patch must be retained exactly.');
                $driver = $workspace['candidate_root'] . '/evaluation/observe.php';
                $driverBytes = file_get_contents($driver);
                if (!is_string($driverBytes) || file_put_contents($driver, $driverBytes . "\n// unapproved observation change\n") === false) {
                    throw new RuntimeException('Unable to create the comparison protected-path control.');
                }
                agentEvaluationControllerExpectFailureContains(
                    static function () use ($workspace, $admitted): void {
                        agentEvaluationControllerFreezeWorkspace($workspace, $admitted);
                    }, 'Candidate changed protected path evaluation/observe.php.',
                );
                if (file_put_contents($driver, $driverBytes) !== strlen($driverBytes)) {
                    throw new RuntimeException('Unable to restore the comparison protected-path control.');
                }
                $forged = $admitted;
                $forged['base'] = ['directory' => $root . '/skeleton', 'fixture_sha256' => str_repeat('a', 64)];
                agentEvaluationControllerExpectFailureContains(
                    static function () use ($forged): void { agentEvaluationControllerRequireAdmittedTask($forged); },
                    'Comparison task does not match its admitted condition.',
                );
                $wrongTask = $task;
                $wrongTask['revision']++;
                agentEvaluationControllerExpectFailureContains(
                    static function () use ($wrongTask, $condition): void {
                        agentEvaluationControllerAdmitComparisonTask($wrongTask, $condition);
                    }, 'Comparison execution must select the exact authoritative task and condition.',
                );
            }
        }
        agentEvaluationControllerTest(count($workspaces) === 6 && count($identities) === 13,
            'Six live workspaces must own twelve reference file identities separate from the shared source.');
        $firstWorkspace = $workspaces[0];
        $firstTask = $admittedTasks[0];
        $changedReference = $firstWorkspace['candidate_root'] . '/' . $relativeReference;
        if (file_put_contents($changedReference, $referenceBytes . "\nchanged candidate only\n") === false) {
            throw new RuntimeException('Unable to mutate one isolated candidate reference.');
        }
        agentEvaluationControllerTest(file_get_contents($sourceReference) === $referenceBytes,
            'Editing a candidate reference must not change the shared source.');
        foreach ($workspaces as $index => $workspace) {
            agentEvaluationControllerTest(
                file_get_contents($workspace['baseline_root'] . '/' . $relativeReference) === $referenceBytes
                && ($index === 0 || file_get_contents($workspace['candidate_root'] . '/' . $relativeReference) === $referenceBytes),
                'Editing one candidate reference must not change its baseline or any other snapshot.',
            );
        }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($firstWorkspace, $firstTask): void {
                agentEvaluationControllerFreezeWorkspace($firstWorkspace, $firstTask);
            }, 'Candidate changed protected path docs/phpstan/return.phpDocType.md.',
        );
        // Keep this failure control disposable even if the overlap guard regresses.
        $overlapReferenceDirectory = $workspaces[1]['candidate_root'] . '/docs/phpstan';
        $overlapBase = agentEvaluationRequireObject($firstTask, 'base', 'comparison overlap control');
        $overlapSource = agentEvaluationRequireString($overlapBase, 'directory', 'comparison overlap control');
        $overlapHash = agentEvaluationRequireString($overlapBase, 'fixture_sha256', 'comparison overlap control');
        agentEvaluationControllerExpectFailureContains(
            static function () use ($overlapSource, $overlapHash, $overlapReferenceDirectory): void {
                agentEvaluationControllerMaterializeComparisonFixture($overlapSource,
                    $overlapReferenceDirectory . '/forbidden-control', $overlapHash, $overlapReferenceDirectory);
            }, 'Comparison materialization target must be separate from its source inputs.',
        );
        agentEvaluationControllerTest(!file_exists($overlapReferenceDirectory . '/forbidden-control'),
            'A rejected materialization overlap must not write inside the shared source.');
    } finally {
        foreach ($workspaces as $workspace) {
            agentEvaluationControllerCleanupWorkspace($workspace);
        }
    }
}

function agentEvaluationControllerTestSharedReferenceSnapshot(string $root, string $temporaryRoot): void
{
    $sourceContainer = $temporaryRoot . '/shared-source-implementation';
    $snapshotContainer = $temporaryRoot . '/shared-retained-implementation';
    if (!mkdir($sourceContainer, 0700) || !mkdir($snapshotContainer, 0700)) {
        throw new RuntimeException('Unable to create shared-reference implementation controls.');
    }
    $expected = agentEvaluationControllerComparisonCodeHash($root);
    agentEvaluationControllerRetainComparisonImplementation($root, $sourceContainer, $expected);
    $source = $sourceContainer . '/implementation';
    agentEvaluationControllerRetainComparisonImplementation($source, $snapshotContainer, $expected);
    $snapshot = $snapshotContainer . '/implementation';
    $relativeReference = '/tools/agent-evaluation/references/phpstan/return.phpDocType.md';
    $bytes = file_get_contents($source . $relativeReference);
    if (!is_string($bytes) || file_put_contents($source . $relativeReference, $bytes . "\nchanged copied source\n") === false) {
        throw new RuntimeException('Unable to mutate the disposable shared source.');
    }
    agentEvaluationControllerTest(
        agentEvaluationControllerComparisonCodeHash($source) !== $expected
        && agentEvaluationControllerComparisonCodeHash($snapshot) === $expected
        && file_get_contents($root . $relativeReference) === $bytes,
        'Shared source bytes must participate in the implementation identity and remain independent after retention.',
    );
    $task = agentEvaluationComparisonTaskDocument($snapshot . '/tools/agent-evaluation', 'change.protected-endpoint');
    $condition = $task['conditions'][0];
    $target = $temporaryRoot . '/shared-stale-materialization';
    agentEvaluationControllerExpectFailureContains(
        static function () use ($source, $condition, $target): void {
            agentEvaluationControllerMaterializeComparisonFixture(
                $source . '/tools/agent-evaluation/tasks/change.protected-endpoint/fixtures/phpthis', $target,
                $condition['base']['fixture_sha256'], $source . '/tools/agent-evaluation/references/phpstan');
        }, 'Comparison materialization requires its exact admitted fixture hash.',
    );
    agentEvaluationControllerTest(!file_exists($target), 'Changed shared bytes must fail before materialization creates a target.');
    if (!unlink($source . $relativeReference)) {
        throw new RuntimeException('Unable to remove the disposable source reference.');
    }
    agentEvaluationControllerTest(
        $condition['base']['reference_directory'] === $snapshot . '/tools/agent-evaluation/references/phpstan',
        'Retained task admission must resolve shared sources inside its own implementation snapshot.',
    );
    $target = $temporaryRoot . '/shared-snapshot-materialization';
    agentEvaluationControllerMaterializeComparisonFixture($condition['base']['directory'], $target,
        $condition['base']['fixture_sha256'], $condition['base']['reference_directory']);
    $tree = agentEvaluationControllerDescribeTree($target, 'retained shared source materialization', true);
    agentEvaluationControllerTest(
        $tree['sha256'] === $condition['base']['fixture_sha256']
        && file_get_contents($target . '/docs/phpstan/return.phpDocType.md') === $bytes,
        'A retained implementation must materialize its exact fixture after the original copied reference is changed and removed.',
    );
}

function agentEvaluationControllerTestImageResolution(string $temporaryRoot): void
{
    $reference = 'registry.invalid:5000/phpthis/generation@sha256:' . str_repeat('a', 64);
    $repository = 'registry.invalid:5000/phpthis/generation';
    $id = 'sha256:' . str_repeat('b', 64);
    $otherId = 'sha256:' . str_repeat('c', 64);
    $row = ['id' => $id, 'repository' => $repository, 'digest' => 'sha256:' . str_repeat('a', 64)];
    $line = json_encode($row, JSON_THROW_ON_ERROR) . "\n";
    $identity = ['Id' => $id, 'Os' => 'linux', 'Architecture' => 'arm64', 'RepoDigests' => [$reference], 'Config' => ['Volumes' => null]];
    $expected = ['image_reference' => $reference, 'image_id' => $id, 'architecture' => 'arm64'];
    agentEvaluationControllerTest(
        agentEvaluationControllerOciImageRepository($reference) === $repository
        && agentEvaluationControllerOciImageCandidates($reference, $line . $line) === [$id]
        && agentEvaluationControllerOciImageCandidates($reference, '') === []
        && agentEvaluationControllerOciSelectImageIdentity($reference, [$id => $identity]) === $expected
        && agentEvaluationControllerOciSelectImageIdentity($reference, [$id => [...$identity, 'Config' => ['Env' => []]]]) === $expected,
        'An exact repository digest must resolve through a full inspected ID, deduplicating repeated tag rows.',
    );
    $unrelated = [...$identity, 'Id' => $otherId, 'RepoDigests' => ['other/repository@sha256:' . str_repeat('a', 64)]];
    agentEvaluationControllerTest(
        agentEvaluationControllerOciSelectImageIdentity($reference, [$otherId => $unrelated, $id => $identity]) === $expected,
        'The local index only locates candidates; the configured repository and digest must match inspected metadata together.',
    );
    foreach (['generation:latest', $id, 'generation:latest@sha256:' . str_repeat('a', 64),
        'registry.invalid/*@sha256:' . str_repeat('a', 64), '/generation@sha256:' . str_repeat('a', 64),
        'registry.invalid/../generation@sha256:' . str_repeat('a', 64), $reference . "\n"] as $invalid) {
        agentEvaluationControllerExpectFailureContains(
            static function () use ($invalid): void { agentEvaluationControllerOciImageRepository($invalid); },
            'AGENT_EVALUATION_CONTROLLER_OCI_IMAGE_NOT_PINNED',
        );
    }
    foreach ([substr($line, 0, -1), $line . "\n", "[]\n", "{\"id\":1,\"id\":2}\n",
        json_encode([...$row, 'id' => 'b123'], JSON_THROW_ON_ERROR) . "\n",
        json_encode([...$row, 'repository' => 'other/repository'], JSON_THROW_ON_ERROR) . "\n",
        json_encode([...$row, 'digest' => 'latest'], JSON_THROW_ON_ERROR) . "\n",
        json_encode([...$row, 'extra' => true], JSON_THROW_ON_ERROR) . "\n",
        str_repeat($line, 129), str_repeat('x', 65_537)] as $invalid) {
        $rejected = false;
        try { agentEvaluationControllerOciImageCandidates($reference, $invalid); } catch (RuntimeException) { $rejected = true; }
        agentEvaluationControllerTest($rejected, 'Malformed, foreign, truncated, or excessive image-index output must fail closed.');
    }
    $many = '';
    for ($index = 0; $index < 33; $index++) {
        $many .= json_encode([...$row, 'id' => 'sha256:' . hash('sha256', 'bounded-image-' . $index)], JSON_THROW_ON_ERROR) . "\n";
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($reference, $many): void { agentEvaluationControllerOciImageCandidates($reference, $many); },
        'AGENT_EVALUATION_CONTROLLER_OCI_IMAGE_INDEX_LIMIT',
    );
    foreach ([[], [$id => [...$identity, 'RepoDigests' => []]], [$id => [...$identity, 'Id' => $otherId]],
        [$id => [...$identity, 'RepoDigests' => [$reference, 1]]], [$id => [...$identity, 'Os' => 'windows']],
        [$id => [...$identity, 'Architecture' => 'unknown']], [$id => [...$identity, 'Config' => ['Volumes' => ['/host' => []]]]],
        [$id => $identity, $otherId => [...$identity, 'Id' => $otherId]]] as $invalid) {
        agentEvaluationControllerExpectFailureContains(
            static function () use ($reference, $invalid): void { agentEvaluationControllerOciSelectImageIdentity($reference, $invalid); },
            'AGENT_EVALUATION_CONTROLLER_OCI_IMAGE_',
        );
    }
    $control = $temporaryRoot . '/image-resolution-control';
    if (!mkdir($control, 0700)) { throw new RuntimeException('Image-resolution fixture directory failed.'); }
    $engine = ['binary' => $control . '/docker-fixture', 'socket' => $control . '/no-daemon.sock',
        'config_root' => $control, 'control_root' => $control, 'configuration' => ['generation_image' => $reference],
        'identity' => ['images' => ['generation' => $expected]]];
    agentEvaluationControllerTest(agentEvaluationControllerOciVerifiedImageId($engine, 'generation') === $id,
        'Container launch must use the image ID already bound to its configured digest.');
    foreach ([[...$engine, 'identity' => []], [...$engine, 'configuration' => ['generation_image' => 'other/image@sha256:' . str_repeat('a', 64)]]] as $invalid) {
        $rejected = false;
        try { agentEvaluationControllerOciVerifiedImageId($invalid, 'generation'); } catch (RuntimeException) { $rejected = true; }
        agentEvaluationControllerTest($rejected, 'Missing or mismatched preflight identity must never authorize container creation.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($engine): void { agentEvaluationControllerOciVerifiedImageId($engine, 'unreviewed'); },
        'AGENT_EVALUATION_CONTROLLER_OCI_IMAGE_ROLE_INVALID',
    );
    agentEvaluationControllerOciValidateContainerImage(['Image' => $id, 'Config' => ['Image' => $id]], $id);
    foreach ([['Image' => $otherId, 'Config' => ['Image' => $id]], ['Image' => $id, 'Config' => ['Image' => $reference]],
        ['Config' => ['Image' => $id]]] as $invalid) {
        agentEvaluationControllerExpectFailureContains(
            static function () use ($invalid, $id): void { agentEvaluationControllerOciValidateContainerImage($invalid, $id); },
            'AGENT_EVALUATION_CONTROLLER_OCI_CONTAINER_IMAGE_INVALID',
        );
    }
    $fixture = ['reference' => $reference, 'repository' => $repository, 'id' => $id, 'index' => $line . $line, 'identity' => $identity];
    $program = <<<'PHP'
<?php
declare(strict_types=1);
$fixture = json_decode(file_get_contents(__DIR__ . '/fixture.json'), true, 32, JSON_THROW_ON_ERROR);
$arguments = array_slice($argv, 5);
file_put_contents(__DIR__ . '/commands.jsonl', json_encode($arguments, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
if (array_slice($arguments, 0, 2) === ['image', 'ls'] && end($arguments) === $fixture['repository']) {
    fwrite(STDOUT, $fixture['index']); exit(0);
}
if (array_slice($arguments, 0, 2) === ['image', 'inspect'] && end($arguments) === $fixture['id']) {
    fwrite(STDOUT, json_encode($fixture['identity'], JSON_THROW_ON_ERROR) . "\n"); exit(0);
}
fwrite(STDERR, "Synthetic reference lookup unavailable.\n"); exit(27);
PHP;
    if (file_put_contents($control . '/fixture.json', agentEvaluationJson($fixture), LOCK_EX) === false
        || file_put_contents($engine['binary'], '#!' . PHP_BINARY . "\n" . $program, LOCK_EX) === false
        || !chmod($engine['binary'], 0700)) {
        throw new RuntimeException('Image-resolution fake client preparation failed.');
    }
    $unavailable = agentEvaluationControllerOciCommand($engine, ['image', 'inspect', '--format', '{{json .}}', $reference]);
    agentEvaluationControllerTest($unavailable['exit_code'] === 27
        && agentEvaluationControllerOciResolveImage($engine, $reference) === $expected,
        'Resolution must succeed through the verified image ID when the exact immutable reference lookup fails.');
    $commands = file_get_contents($control . '/commands.jsonl');
    if (!is_string($commands)) { throw new RuntimeException('Image-resolution command evidence missing.'); }
    $lines = explode("\n", trim($commands));
    agentEvaluationControllerTest(count($lines) === 3
        && json_decode($lines[1], true, 16, JSON_THROW_ON_ERROR) === ['image', 'ls', '--no-trunc', '--digests', '--format',
            '{"id":{{json .ID}},"repository":{{json .Repository}},"digest":{{json .Digest}}}', $repository]
        && str_ends_with($lines[2], json_encode($id, JSON_THROW_ON_ERROR) . ']'),
        'Resolution must use one exact repository query and one deduplicated full-ID inspection without reference retry.');
}

function agentEvaluationControllerTestObservationSlot(): void
{
    $input = "{\n\"case_id\":\"case-1\",\"input\":{\"value\":\"; touch /scorer/spoof\"}\n}";
    $slot = agentEvaluationControllerOciScoringSlot('observation', $input);
    agentEvaluationControllerTest(
        $slot['command'] === ['/usr/local/bin/php', '/candidate/evaluation/observe.php']
        && !$slot['scorer_mounted']
        && $slot['standard_input'] === "{\"case_id\":\"case-1\",\"input\":{\"value\":\"; touch /scorer/spoof\"}}\n",
        'Observation case data must remain one private stdin line with fixed argv and no scorer mount.',
    );
    foreach ([
        ['observation', ''], ['observation', 'null'], ['observation', '[]'], ['observation', '{broken'],
        ['observation', '{"data":"' . str_repeat('a', AGENT_EVALUATION_CONTROLLER_PROCESS_STDIN_BYTES) . '"}'],
        ['public-scorer', '{}'], ['application-check', '{}'], ['comparison-application-check', '{}'], ['custom-command', ''],
    ] as [$name, $invalid]) {
        agentEvaluationControllerExpectFailureContains(
            static function () use ($name, $invalid): void {
                agentEvaluationControllerOciScoringSlot($name, $invalid);
            },
            $name === 'observation' ? 'AGENT_EVALUATION_CONTROLLER_OCI_OBSERVATION_INPUT_INVALID'
                : 'AGENT_EVALUATION_CONTROLLER_OCI_SCORE_PHASE_INVALID',
        );
    }
    agentEvaluationControllerTest(
        agentEvaluationControllerOciScoringSlot('comparison-application-check', '') === [
            'command' => ['/usr/local/bin/composer', '--no-interaction', 'check'],
            'standard_input' => '', 'scorer_mounted' => false,
        ],
        'The comparison application gate must have fixed Composer argv without a scorer mount or holdout stdin.',
    );
    agentEvaluationControllerTest(
        agentEvaluationControllerOciScoringSlot('application-check', '') === [
            'command' => ['/usr/local/bin/composer', '--no-interaction', 'check'],
            'standard_input' => '', 'scorer_mounted' => true,
        ] && agentEvaluationControllerOciScoringSlot('public-scorer', '') === [
            'command' => ['/usr/local/bin/php', '/scorer/public.php', '/candidate'],
            'standard_input' => '', 'scorer_mounted' => true,
        ],
        'The two existing v1 scoring slots must retain their literal commands and empty stdin.',
    );
}

function agentEvaluationControllerTestComparisonCleanupSequence(string $root, string $temporaryRoot): void
{
    // Load the production scoring module in a bounded child. Only the OCI
    // boundary is synthetic; no container, candidate code, or private case runs.
    $program = <<<'PHP'
<?php
declare(strict_types=1);
define('PHPTHIS_AGENT_EVALUATION_LIBRARY_ONLY', true);
require $argv[1] . '/tools/agent-evaluation.php';
foreach (['contract', 'workspace', 'scoring', 'controller'] as $module) {
    require $argv[1] . '/tools/agent-evaluation-controller/' . $module . '.php';
}
function agentEvaluationControllerOciRunApplicationCheck(array &$resources, string $root): array
{
    $resources['calls'][] = 'application';
    if ($resources['mutate']) {
        file_put_contents($root . '/fixture.txt', "changed synthetic input\n", LOCK_EX);
    }
    return $resources['application'];
}
function agentEvaluationControllerOciRunObservation(array &$resources, string $root, string $input): array
{
    $resources['calls'][] = json_decode($input, true, 16, JSON_THROW_ON_ERROR)['id'];
    $process = array_shift($resources['observations']);
    if (!is_array($process)) { throw new RuntimeException('Unexpected synthetic observation.'); }
    return $process;
}
function check(bool $passed, string $message): void
{
    if (!$passed) { throw new RuntimeException($message); }
}
$expected = ['response' => ['status' => 200, 'headers' => ['content-type' => 'application/json'], 'body' => ['ok' => true]],
    'policy_steps' => [], 'query' => ['min_statements' => 0, 'max_statements' => 0, 'failures' => 0, 'max_fingerprint_executions' => 0],
    'durable_state' => ['items' => []], 'in_transaction' => false];
$holdout = ['cases' => [
    ['id' => 'first', 'input' => ['id' => 'first'], 'expect' => $expected],
    ['id' => 'second', 'input' => ['id' => 'second'], 'expect' => $expected],
], 'scaling_groups' => [['id' => 'fixed', 'case_ids' => ['first', 'second'], 'max_statement_growth' => 0]]];
$observation = agentEvaluationJson(['schema_version' => 1,
    'response' => [...$expected['response'], 'body' => '{"ok":true}'], 'policy_steps' => [],
    'query_trace' => ['statements' => 0, 'failures' => 0, 'queries' => [], 'truncated' => false],
    'durable_state' => $expected['durable_state'], 'in_transaction' => false]);
$passed = ['exit_code' => 0, 'stdout' => '', 'stderr' => '', 'timed_out' => false,
    'output_limit_exceeded' => false, 'oom_killed' => false, 'container_started' => true,
    'container_destroyed' => true, 'termination_reason' => 'completed'];
$cleanupFailed = [...$passed, 'exit_code' => -1, 'container_destroyed' => false, 'termination_reason' => 'cleanup_failed'];
$unavailable = agentEvaluationControllerEmptyComparisonResults($holdout);
foreach (['application-cleanup', 'observation-cleanup', 'application-failed', 'complete', 'mutated-input'] as $mode) {
    $directory = $argv[2] . '/' . $mode;
    check(mkdir($directory, 0700) && mkdir($directory . '/frozen', 0700) && mkdir($directory . '/evidence', 0700),
        'Synthetic cleanup directories must be created.');
    check(file_put_contents($directory . '/frozen/fixture.txt', "unchanged synthetic input\n", LOCK_EX) !== false,
        'Synthetic input must be created.');
    $application = in_array($mode, ['application-cleanup', 'mutated-input'], true) ? $cleanupFailed : $passed;
    if ($mode === 'application-failed') {
        $application = [...$passed, 'exit_code' => 7, 'termination_reason' => 'process_failed'];
    }
    $resources = ['generation_destroyed' => true, 'calls' => [], 'mutate' => $mode === 'mutated-input',
        'application' => $application, 'observations' => [
            [...($mode === 'observation-cleanup' ? $cleanupFailed : $passed), 'stdout' => $observation],
            [...$passed, 'stdout' => $observation],
        ]];
    $failure = null;
    $result = null;
    try {
        $result = agentEvaluationControllerScoreComparisonCandidate($resources, $directory . '/frozen', $holdout, $directory . '/evidence');
    } catch (RuntimeException $exception) {
        $failure = $exception->getMessage();
    }
    check($failure === ($mode === 'mutated-input' ? 'Comparison frozen candidate changed during scoring.' : null),
        'The final frozen-tree comparison must still reject mutation after cleanup stops observation launch: ' . ($failure ?? 'no rejection'));
    $retained = json_decode(file_get_contents($directory . '/evidence/observation-results.json'), true, 32, JSON_THROW_ON_ERROR);
    check($mode === 'mutated-input' || $result === $retained, 'Returned results must match the retained artifact.');
    $count = in_array($mode, ['application-cleanup', 'mutated-input'], true) ? 0 : ($mode === 'observation-cleanup' ? 1 : 2);
    check($resources['calls'] === array_slice(['application', 'first', 'second'], 0, $count + 1),
        'No observation may start until the preceding application or observation cleanup is verified.');
    $artifacts = ['application-check.json', 'observation-results.json'];
    for ($index = 0; $index < 2; $index++) {
        if ($index >= $count) {
            check($retained['cases'][$index] === $unavailable['cases'][$index], 'Unrun cases must remain explicitly unavailable.');
            continue;
        }
        $artifacts[] = sprintf('observation-%02d.json', $index + 1);
        check($retained['cases'][$index]['observation_valid'] === true && $retained['cases'][$index]['statements'] === 0,
            'Completed observation evidence must remain available even when its cleanup fails.');
        check($retained['cases'][$index]['process_admissible'] === ($mode !== 'observation-cleanup'),
            'Failed cleanup must make its own observation inadmissible.');
    }
    sort($artifacts, SORT_STRING);
    $files = array_values(array_diff(scandir($directory . '/evidence'), ['.', '..']));
    check($files === $artifacts, 'Only the actual application, observation prefix, and final results may be retained.');
    check($retained['application_gate'] === (in_array($mode, ['complete', 'observation-cleanup'], true) ? 'pass' : 'fail'),
        'The application gate must retain its independent outcome.');
    check($retained['scaling'][0]['counts'] === ($count === 0 ? [null, null] : ($count === 1 ? [0, null] : [0, 0]))
        && $retained['scaling'][0]['passed'] === ($count === 2), 'Scaling must preserve unavailable counts.');
    check($retained['automated_status'] === ($mode === 'complete' ? 'pass' : 'fail'), 'Cleanup or application failure must fail the score.');
}
fwrite(STDOUT, "PASS comparison cleanup sequence\n");
PHP;
    $directory = $temporaryRoot . '/comparison-cleanup-sequence';
    if (!mkdir($directory, 0700)
        || file_put_contents($directory . '/worker.php', $program, LOCK_EX) !== strlen($program)) {
        throw new RuntimeException('Unable to prepare the synthetic scoring cleanup worker.');
    }
    $process = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $directory . '/worker.php', $root, $directory],
        $root,
        agentEvaluationControllerMinimalProcessEnvironment(),
        '',
        5,
        16_384,
    );
    agentEvaluationControllerTest($process['exit_code'] === 0 && $process['termination_reason'] === 'completed'
        && $process['stdout'] === "PASS comparison cleanup sequence\n" && $process['stderr'] === ''
        && $process['cleanup']['process_group_absent'],
        'Comparison scoring cleanup sequencing controls must pass: ' . $process['stderr']);
}

function agentEvaluationControllerTestArchiveControls(string $root): void
{
    $archive = agentEvaluationControllerOciCandidateArchive($root . '/skeleton');
    $entries = agentEvaluationControllerOciReadArchive($archive);
    agentEvaluationControllerTest(
        isset($entries['src/HealthRoutes.php'], $entries['src'])
        && $entries['src/HealthRoutes.php']['bytes'] === file_get_contents($root . '/skeleton/src/HealthRoutes.php')
        && $entries['src/HealthRoutes.php']['mode'] === 0644
        && $entries['src']['directory']
        && $entries['src']['mode'] === 0755,
        'The stopped-container archive format must preserve exact bounded source bytes and canonical modes.',
    );
    $end = str_repeat("\0", 1024);
    $file = agentEvaluationControllerOciTarHeader('src/a.php', 0644, 0, '0');
    $controls = [
        [substr_replace($file . $end, 'X', 0, 1), 'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_HEADER_INVALID'],
        [substr($file . $end, 0, -1), 'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_INVALID'],
        [$file . $file . $end, 'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_ENTRY_LIMIT'],
        [$file . agentEvaluationControllerOciTarHeader('SRC/b.php', 0644, 0, '0') . $end,
            'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_CASE_COLLISION'],
        [agentEvaluationControllerOciTarHeader('src', 0644, 0, '0') . $file . $end,
            'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_PARENT_COLLISION'],
        [$file . $end . str_repeat('X', 512), 'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_TRAILER_INVALID'],
        [$file . str_repeat("\0", 512), 'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_NOT_TERMINATED'],
        [agentEvaluationControllerOciTarHeader('src/', 0644, 0, '5') . $end,
            'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_ENTRY_INVALID'],
        [agentEvaluationControllerOciTarHeader('src/a.php', 0644, AGENT_EVALUATION_MAX_ARTIFACT_BYTES + 1, '0') . $end,
            'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_'],
        [agentEvaluationControllerOciTarHeader('vendor/forged.php', 0644, 0, '0') . $end,
            'AGENT_EVALUATION_CONTROLLER_OCI_DEPENDENCY_ESCAPE'],
    ];
    foreach (['1', '2', '3', '4', '6', 'x', 'g'] as $type) {
        $controls[] = [agentEvaluationControllerOciTarHeader('src/a.php', 0644, 0, $type) . $end,
            'AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_ENTRY_INVALID'];
    }
    foreach ($controls as [$invalid, $marker]) {
        agentEvaluationControllerExpectFailureContains(
            static function () use ($invalid): void {
                agentEvaluationControllerOciReadArchive($invalid);
            },
            $marker,
        );
    }
    foreach (['/absolute.php', '../outside.php', 'src/../../outside.php'] as $path) {
        $invalid = agentEvaluationControllerOciTarHeader($path, 0644, 0, '0') . $end;
        agentEvaluationControllerExpectFailureContains(
            static function () use ($invalid): void {
                agentEvaluationControllerOciReadArchive($invalid);
            },
            'OCI exported candidate',
        );
    }
}

function agentEvaluationControllerTestProxyControls(): void
{
    $body = '{"model":"phpthis-fixture","stream":true,"store":false,"input":"Synthetic task.",'
        . '"reasoning":{"effort":"high"},"tools":[],"max_output_tokens":100}';
    $metadataState = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
    $metadataBody = str_replace('"tools":[]', '"tools":[],"client_metadata":{"x-codex-turn-metadata":"synthetic transport hint"}', $body);
    $metadataRequest = agentEvaluationControllerProxyRequest($metadataBody, $metadataState);
    agentEvaluationControllerTest(
        !array_key_exists('client_metadata', $metadataRequest['request'])
        && !str_contains($metadataRequest['count_json'], 'synthetic transport hint'),
        'Codex client metadata must be discarded before quota counting and upstream creation.',
    );
    $count = '{"object":"response.input_tokens","input_tokens":20}';
    $completed = "event: response.completed\ndata: "
        . '{"type":"response.completed","response":{"model":"phpthis-fixture","status":"completed",'
        . '"usage":{"input_tokens":20,"output_tokens":30,"total_tokens":50, '
        . '"input_tokens_details":{"cached_tokens":5},"output_tokens_details":{"reasoning_tokens":7}},"output":[]}}'
        . "\n\n";
    $state = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
    $prepared = agentEvaluationControllerProxyRequest($body, $state);
    $reserved = agentEvaluationControllerProxyJsonObject(
        agentEvaluationControllerProxyReserve($prepared['request'], $count, $state),
    );
    agentEvaluationControllerTest(
        ($reserved['max_output_tokens'] ?? null) === 100
        && ($reserved['store'] ?? null) === false
        && ($reserved['truncation'] ?? null) === 'disabled'
        && ($state['reserved_input'] ?? null) === 20
        && ($state['reserved_output'] ?? null) === 100,
        'The host proxy must reserve counted input and cap output before a create request.',
    );
    $usage = agentEvaluationControllerProxyComplete($completed, $state);
    agentEvaluationControllerTest(
        $usage === ['input_tokens' => 20, 'output_tokens' => 30, 'cached_tokens' => 5, 'reasoning_tokens' => 7]
        && ($state['input_tokens'] ?? null) === 20
        && ($state['output_tokens'] ?? null) === 30
        && ($state['reserved_output'] ?? null) === 0,
        'A complete fixture response must settle only the provider-reported token categories.',
    );
    $second = agentEvaluationControllerProxyRequest($body, $state);
    $secondReserved = agentEvaluationControllerProxyJsonObject(
        agentEvaluationControllerProxyReserve($second['request'], $count, $state),
    );
    agentEvaluationControllerTest(
        ($secondReserved['max_output_tokens'] ?? null) === 50,
        'A second request must share the original run allowance rather than resetting quota.',
    );

    $requestControls = [
        str_replace('"phpthis-fixture"', '"unapproved-model"', $body),
        str_replace('"high"', '"low"', $body),
        str_replace('"stream":true', '"stream":false', $body),
        str_replace('"store":false', '"store":true', $body),
        str_replace('"tools":[]', '"tools":[{"type":"web_search"}]', $body),
        str_replace('"input":"Synthetic task."', '"input":[{"type":"message","role":"user","content":[{"type":"input_image","image_url":"https://example.invalid/image"}]}]', $body),
        str_replace('"tools":[]', '"tools":[],"previous_response_id":"unapproved"', $body),
        str_replace('"tools":[]', '"tools":[],"client_metadata":"invalid"', $body),
        str_replace('"stream":true', '"stream":true,"str\\u0065am":true', $body),
    ];

    foreach ($requestControls as $invalidBody) {
        $invalidState = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
        agentEvaluationControllerExpectFailure(
            static function () use ($invalidBody, &$invalidState): void {
                agentEvaluationControllerProxyRequest($invalidBody, $invalidState);
            },
            'AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_REJECTED',
        );
        agentEvaluationControllerTest(
            ($invalidState['blocked'] ?? null) === true,
            'An unapproved model, hosted action, retained state, or ambiguous request must close the run proxy.',
        );
        $inventory = agentEvaluationControllerLiveExternalActions([], $invalidState);
        agentEvaluationControllerTest(
            $inventory['host_proxy_requests'] === 1 && !$inventory['approved']
            && ($invalidState['request_count'] ?? null) === 0
            && ($invalidState['last_request_sha256'] ?? null) === hash('sha256', $invalidBody)
            && is_string($invalidState['request_rejection_stage'] ?? null),
            'A rejected request must retain its observed attempt and hash separately from authorized reservations.',
        );
    }

    $pendingState = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
    agentEvaluationControllerProxyRequest($body, $pendingState);
    agentEvaluationControllerExpectFailure(
        static function () use ($body, &$pendingState): void {
            agentEvaluationControllerProxyRequest($body, $pendingState);
        },
        'AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_REJECTED',
    );
    $exhaustedState = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 35);
    $exhaustedRequest = agentEvaluationControllerProxyRequest($body, $exhaustedState);
    agentEvaluationControllerExpectFailure(
        static function () use ($exhaustedRequest, $count, &$exhaustedState): void {
            agentEvaluationControllerProxyReserve($exhaustedRequest['request'], $count, $exhaustedState);
        },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESERVATION_REJECTED',
    );
    agentEvaluationControllerTest(
        ($exhaustedState['blocked'] ?? null) === true
        && ($exhaustedState['request_count'] ?? null) === 0,
        'Insufficient quota must fail before any create request is admitted.',
    );

    $responseControls = [
        '',
        str_replace('"model":"phpthis-fixture"', '"model":"unapproved"', $completed),
        str_replace('"input_tokens":20', '"input_tokens":21', $completed),
        str_replace('"output_tokens":30', '"output_tokens":101', $completed),
        str_replace('"total_tokens":50', '"total_tokens":49', $completed),
        str_replace('"reasoning_tokens":7', '"reasoning_tokens":31', $completed),
        str_replace('"usage":', '"missing_usage":', $completed),
        $completed . $completed,
        $completed . "data: {\"type\":\"response.created\"}\n\n",
    ];

    foreach ($responseControls as $invalidResponse) {
        $invalidState = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
        $request = agentEvaluationControllerProxyRequest($body, $invalidState);
        agentEvaluationControllerProxyReserve($request['request'], $count, $invalidState);
        agentEvaluationControllerExpectFailure(
            static function () use ($invalidResponse, &$invalidState): void {
                agentEvaluationControllerProxyComplete($invalidResponse, $invalidState);
            },
            'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
        );
        agentEvaluationControllerTest(
            ($invalidState['blocked'] ?? null) === true,
            'Missing, partial, inconsistent, excessive, or post-terminal usage must close the run proxy.',
        );
    }
    $unknownState = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
    $unknownRequest = agentEvaluationControllerProxyRequest($body, $unknownState);
    agentEvaluationControllerProxyReserve($unknownRequest['request'], $count, $unknownState);
    $unknownUsage = agentEvaluationControllerProxyComplete(
        str_replace(
            ', "input_tokens_details":{"cached_tokens":5},"output_tokens_details":{"reasoning_tokens":7}',
            '',
            $completed,
        ),
        $unknownState,
    );
    agentEvaluationControllerTest(
        $unknownUsage['cached_tokens'] === null && $unknownUsage['reasoning_tokens'] === null,
        'Absent provider token subcategories remain unknown rather than being inferred.',
    );
}

/** @return array<string, mixed> */
function agentEvaluationControllerTestReservedProxyState(): array
{
    $state = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 120);
    $request = agentEvaluationControllerProxyRequest(
        '{"model":"phpthis-fixture","stream":true,"store":false,"input":"Synthetic diagnostics only.","reasoning":{"effort":"high"},"tools":[],"max_output_tokens":100}',
        $state,
    );
    agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state);
    return $state;
}

/** @param array<string, mixed> $response */
function agentEvaluationControllerTestResponseStream(array $response, string $type = 'response.completed'): string
{
    return 'event: ' . $type . "\ndata: " . json_encode(['type' => $type, 'response' => $response], JSON_THROW_ON_ERROR) . "\n\n";
}

function agentEvaluationControllerTestProxySpending(): void
{
    $policy = ['limit_units' => 100_000_000, 'input_cents_per_million' => 250,
        'cached_cents_per_million' => 25, 'output_cents_per_million' => 1500];
    $model = 'gpt-5.4-2026-03-05';
    $legacy = agentEvaluationControllerProxyState($model, 'high', 40_000);
    agentEvaluationControllerTest(!array_key_exists('spending', $legacy)
        && in_array('model_auto_compact_token_limit=40001', agentEvaluationControllerLiveCodexArguments($model, 'high'), true)
        && in_array('model_auto_compact_token_limit=200001', agentEvaluationControllerLiveCodexArguments($model, 'high', $policy), true),
        'Legacy state and argv must stay unchanged; only an explicit approved spending policy selects calibration compaction.');
    foreach ([[40_001, null], [200_001, $policy]] as [$tokens, $spending]) {
        agentEvaluationControllerExpectFailure(
            static function () use ($model, $tokens, $spending): void { agentEvaluationControllerProxyState($model, 'high', $tokens, $spending); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_BUDGET_INVALID',
        );
    }
    foreach ([['other-model', 'high', $policy], [$model, 'low', $policy],
        [$model, 'high', [...$policy, 'limit_units' => 100_000_001]],
        [$model, 'high', [...$policy, 'input_cents_per_million' => 1]],
        [$model, 'high', [...$policy, 'cached_cents_per_million' => 25.0]],
        [$model, 'high', [...$policy, 'output_cents_per_million' => 1499]]] as [$badModel, $effort, $badPolicy]) {
        agentEvaluationControllerExpectFailure(
            static function () use ($badModel, $effort, $badPolicy): void { agentEvaluationControllerProxyState($badModel, $effort, 200_000, $badPolicy); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_SPENDING_POLICY_INVALID',
        );
    }
    foreach ([[0, 'proxy spending policy field limit_units must be positive.'],
        [-1, 'proxy spending policy field limit_units must be positive.'],
        [1.5, 'proxy spending policy field limit_units must be an integer.'],
        ['100000000', 'proxy spending policy field limit_units must be an integer.']] as [$limit, $expectedFailure]) {
        agentEvaluationControllerExpectFailure(
            static function () use ($model, $policy, $limit): void { agentEvaluationControllerProxyState($model, 'high', 200_000, [...$policy, 'limit_units' => $limit]); },
            $expectedFailure,
        );
    }
    foreach ([array_diff_key($policy, ['cached_cents_per_million' => true]), [...$policy, 'extra' => 1]] as $badPolicy) {
        agentEvaluationControllerExpectFailure(
            static function () use ($model, $badPolicy): void { agentEvaluationControllerProxyState($model, 'high', 200_000, $badPolicy); },
            'proxy spending policy must contain exactly: cached_cents_per_million, input_cents_per_million, limit_units, output_cents_per_million.',
        );
    }
    foreach ([[200_000, 100_000_000, PHP_INT_MAX, 66_663, 99_999_500],
        [120, 100_000_000, PHP_INT_MAX, 100, 155_000],
        [200_000, 29_000, 100, 16, 29_000],
        [200_000, 100_000_000, 16, 16, 29_000]] as [$tokens, $limit, $requestedOutput, $expectedOutput, $expectedCost]) {
        $state = agentEvaluationControllerProxyState($model, 'high', $tokens, [...$policy, 'limit_units' => $limit]);
        $request = agentEvaluationControllerProxyRequest(json_encode(['model' => $model, 'stream' => true, 'store' => false,
            'input' => 'Synthetic money reservation.', 'reasoning' => ['effort' => 'high'], 'tools' => [],
            'max_output_tokens' => $requestedOutput], JSON_THROW_ON_ERROR), $state);
        $approved = agentEvaluationControllerProxyJsonObject(agentEvaluationControllerProxyReserve($request['request'],
            '{"object":"response.input_tokens","input_tokens":20}', $state));
        $money = agentEvaluationRequireObject($state, 'spending', 'spending reservation fixture');
        agentEvaluationControllerTest($approved['max_output_tokens'] === $expectedOutput && $state['reserved_input'] === 20
            && $state['reserved_output'] === $expectedOutput && $money['reserved_units'] === $expectedCost
            && $money['settled_units'] === 0 && $state['request_count'] === 1,
            'Reservation must enforce the minimum of requested output, remaining tokens, and integer money capacity before create.');
    }
    $body = json_encode(['model' => $model, 'stream' => true, 'store' => false, 'input' => 'Synthetic money settlement.',
        'reasoning' => ['effort' => 'high'], 'tools' => [], 'max_output_tokens' => 100], JSON_THROW_ON_ERROR);
    $state = agentEvaluationControllerProxyState($model, 'high', 200_000, [...$policy, 'limit_units' => 28_999]);
    $request = agentEvaluationControllerProxyRequest($body, $state);
    $pendingHash = $state['request_sha256'];
    agentEvaluationControllerExpectFailure(
        static function () use ($request, &$state): void { agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESERVATION_REJECTED',
    );
    $money = agentEvaluationRequireObject($state, 'spending', 'spending cap fixture');
    agentEvaluationControllerTest($state['failure_reason'] === 'spending_limit' && $state['blocked'] === true
        && $state['request_count'] === 0 && $state['reserved_input'] === 0 && $state['reserved_output'] === 0
        && $state['request_sha256'] === $pendingHash && $money['reserved_units'] === 0 && $money['settled_units'] === 0,
        'A cap one unit below counted input plus sixteen output tokens must stop before create without an ambiguous reservation.');
    $usage = ['input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 50,
        'input_tokens_details' => ['cached_tokens' => 5], 'output_tokens_details' => ['reasoning_tokens' => 7]];
    foreach ([['completed', $usage, 48_875], ['completed', array_diff_key($usage, ['input_tokens_details' => true]), 50_000],
        ['failed', $usage, 48_875], ['incomplete', $usage, 48_875]] as [$status, $responseUsage, $expectedCost]) {
        $state = agentEvaluationControllerProxyState($model, 'high', 200_000, $policy);
        $request = agentEvaluationControllerProxyRequest($body, $state);
        agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state);
        $stream = agentEvaluationControllerTestResponseStream(['model' => $model, 'status' => $status, 'usage' => $responseUsage], 'response.' . $status);
        if ($status === 'completed') {
            agentEvaluationControllerProxyComplete($stream, $state);
        } else {
            agentEvaluationControllerExpectFailure(
                static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
                'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
            );
        }
        $money = agentEvaluationRequireObject($state, 'spending', 'spending settlement fixture');
        agentEvaluationControllerTest($money['settled_units'] === $expectedCost && $money['reserved_units'] === 0
            && $state['reserved_input'] === 0 && $state['reserved_output'] === 0 && $state['request_sha256'] === null
            && $state['input_tokens'] === 20 && $state['output_tokens'] === 30,
            'Validated terminal usage settles exact cached subsets, conservatively prices absent cache data, and preserves existing failed/incomplete settlement.');
    }
    $valid = agentEvaluationControllerTestResponseStream(['model' => $model, 'status' => 'completed', 'usage' => $usage]);
    foreach (['', "event: error\ndata: {\"type\":\"error\"}\n\n", "data: {\"type\":\"unknown\"}\n\n",
        str_replace('"input_tokens":20', '"input_tokens":21', $valid),
        str_replace('"cached_tokens":5', '"cached_tokens":21', $valid),
        str_replace('"reasoning_tokens":7', '"reasoning_tokens":31', $valid),
        $valid . "data: {\"type\":\"response.created\"}\n\n"] as $stream) {
        $state = agentEvaluationControllerProxyState($model, 'high', 200_000, $policy);
        $request = agentEvaluationControllerProxyRequest($body, $state);
        agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state);
        $pendingHash = $state['request_sha256'];
        agentEvaluationControllerExpectFailure(
            static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
        );
        $money = agentEvaluationRequireObject($state, 'spending', 'ambiguous spending fixture');
        agentEvaluationControllerTest($state['blocked'] === true && $state['reserved_input'] === 20 && $state['reserved_output'] === 100
            && $state['request_sha256'] === $pendingHash && $money['reserved_units'] === 155_000 && $money['settled_units'] === 0
            && agentEvaluationControllerProxyAggregateUsage($state)['input_tokens'] === null,
            'Rejected or ambiguous streams must never release money or token reservations, including after a terminal event followed by invalid data.');
    }
    $state = agentEvaluationControllerProxyState($model, 'high', 200_000, [...$policy, 'limit_units' => 100_000]);
    for ($turn = 0; $turn < 2; $turn++) {
        $request = agentEvaluationControllerProxyRequest($body, $state);
        agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state);
        agentEvaluationControllerProxyComplete($valid, $state);
    }
    $request = agentEvaluationControllerProxyRequest($body, $state);
    agentEvaluationControllerExpectFailure(
        static function () use ($request, &$state): void { agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESERVATION_REJECTED',
    );
    $money = agentEvaluationRequireObject($state, 'spending', 'cumulative spending fixture');
    agentEvaluationControllerTest($money['settled_units'] === 97_750 && $money['reserved_units'] === 0
        && $state['failure_reason'] === 'spending_limit' && $state['request_count'] === 2
        && $state['input_tokens'] === 40 && $state['output_tokens'] === 60,
        'Every turn must share the original dollar allowance; settled input/output totals remain available when the next count cannot reserve money.');
    $state = agentEvaluationControllerProxyState($model, 'high', 200_000, $policy);
    unset($state['spending']);
    agentEvaluationControllerExpectFailure(
        static function () use ($body, &$state): void { agentEvaluationControllerProxyRequest($body, $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_REJECTED',
    );
    agentEvaluationControllerTest($state['blocked'] === true && $state['request_count'] === 0,
        'A high-token state with a removed spending policy must fail closed before counting or creating.');
    foreach ([['policy' => $policy, 'settled_units' => 100_000_001, 'reserved_units' => 0],
        ['policy' => $policy, 'settled_units' => 0, 'reserved_units' => 1]] as $invalidLedger) {
        $state = agentEvaluationControllerProxyState($model, 'high', 200_000, $policy);
        $state['spending'] = $invalidLedger;
        agentEvaluationControllerExpectFailure(
            static function () use ($body, &$state): void { agentEvaluationControllerProxyRequest($body, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_REJECTED',
        );
        agentEvaluationControllerTest($state['blocked'] === true && $state['request_count'] === 0,
            'A malformed or inconsistent money ledger must never authorize another provider create.');
    }
}

function agentEvaluationControllerTestProxyResponseByteBounds(): void
{
    $limit = 4_194_304;
    $usage = ['input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 50,
        'input_tokens_details' => ['cached_tokens' => 5], 'output_tokens_details' => ['reasoning_tokens' => 7]];
    $count = '{"object":"response.input_tokens","input_tokens":20}';
    foreach ([null, agentEvaluationControllerCalibrationSpending()] as $policy) {
        $model = $policy === null ? 'phpthis-fixture' : 'gpt-5.4-2026-03-05';
        $body = json_encode(['model' => $model, 'stream' => true, 'store' => false,
            'input' => 'Offline sequential response-byte control.', 'reasoning' => ['effort' => 'high'],
            'tools' => [], 'max_output_tokens' => 100], JSON_THROW_ON_ERROR);
        $terminal = agentEvaluationControllerTestResponseStream(['model' => $model, 'status' => 'completed', 'usage' => $usage]);
        $fullResponse = ':' . str_repeat('x', $limit - strlen($terminal) - 3) . "\n\n" . $terminal;
        agentEvaluationControllerTest(strlen($fullResponse) === $limit,
            'One valid comment-padded SSE response must exercise the inclusive per-response byte bound.');
        $initial = agentEvaluationControllerProxyState($model, 'high', $policy === null ? 40_000 : 1_000_000, $policy);
        $state = $initial;
        for ($turn = 0; $turn < 2; $turn++) {
            $request = agentEvaluationControllerProxyRequest($body, $state);
            agentEvaluationControllerProxyReserve($request['request'], $count, $state);
            agentEvaluationControllerProxyComplete($fullResponse, $state);
        }
        $money = agentEvaluationControllerProxySpendingLedger($state);
        agentEvaluationControllerTest($state['response_bytes'] === 2 * $limit && $state['request_count'] === 2
            && $state['input_tokens'] === 40 && $state['output_tokens'] === 60
            && $state['cached_tokens'] === 10 && $state['reasoning_tokens'] === 14
            && $state['reserved_input'] === 0 && $state['reserved_output'] === 0 && $state['request_sha256'] === null
            && $state['blocked'] === false && $state['response_rejection_stage'] === null
            && $state['last_response_bytes'] === $limit && $state['last_response_sha256'] === hash('sha256', $fullResponse),
            'Sequential valid responses exceeding four MiB in total must settle both complete usages without an outstanding reservation.');
        agentEvaluationControllerTest($policy === null ? $money === null
            : ($money !== null && $money['settled_units'] === 97_750 && $money['reserved_units'] === 0),
            'Crossing the old cumulative byte cap must preserve legacy accounting and exact dollar-policy settlement.');

        for ($turn = 2; $turn < 128; $turn++) {
            $request = agentEvaluationControllerProxyRequest($body, $state);
            agentEvaluationControllerProxyReserve($request['request'], $count, $state);
            agentEvaluationControllerProxyComplete($terminal, $state);
        }
        $settled = agentEvaluationControllerProxyAggregateUsage($state);
        $settledMoney = agentEvaluationControllerProxySpendingLedger($state);
        $totalResponseBytes = 2 * $limit + 126 * strlen($terminal);
        agentEvaluationControllerTest($state['request_count'] === 128 && $state['observed_request_count'] === 128
            && $state['response_bytes'] === $totalResponseBytes
            && $settled === ['input_tokens' => 2560, 'output_tokens' => 3840, 'cached_tokens' => 640, 'reasoning_tokens' => 896]
            && ($policy === null ? $settledMoney === null
                : ($settledMoney !== null && $settledMoney['settled_units'] === 6_256_000 && $settledMoney['reserved_units'] === 0)),
            'All 128 separately reserved valid responses must complete without allocating the aggregate wire allowance.');
        agentEvaluationControllerExpectFailure(
            static function () use ($body, &$state): void { agentEvaluationControllerProxyRequest($body, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_REQUEST_REJECTED',
        );
        agentEvaluationControllerTest($state['request_count'] === 128 && $state['observed_request_count'] === 129
            && $state['blocked'] === true && $state['reserved_input'] === 0 && $state['reserved_output'] === 0
            && $state['request_sha256'] === null && $state['response_bytes'] === $totalResponseBytes
            && agentEvaluationControllerProxyAggregateUsage($state) === $settled
            && agentEvaluationControllerProxySpendingLedger($state) === $settledMoney,
            'Request 129 must be rejected before reservation without losing the 128 settled usages or charges.');

        $base = $initial;
        $request = agentEvaluationControllerProxyRequest($body, $base);
        agentEvaluationControllerProxyReserve($request['request'], $count, $base);
        $state = $base;
        $oversized = $fullResponse . 'x';
        agentEvaluationControllerExpectFailure(
            static function () use ($oversized, &$state): void { agentEvaluationControllerProxyComplete($oversized, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
        );
        agentEvaluationControllerTest($state['response_rejection_stage'] === 'response_bytes'
            && $state['last_response_bytes'] === null && $state['last_response_sha256'] === null
            && $state['response_bytes'] === 0 && $state['response_observation'] === null
            && $state['reserved_input'] === 20 && $state['reserved_output'] === 100
            && $state['request_sha256'] === $base['request_sha256']
            && agentEvaluationControllerProxySpendingLedger($state) === agentEvaluationControllerProxySpendingLedger($base)
            && agentEvaluationControllerProxyAggregateUsage($state) === ['input_tokens' => null, 'output_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null],
            'A body one byte over four MiB must retain the full token/money reservation and unknown aggregate usage before parsing.');
        unset($oversized);

        foreach ([['request_count', 0], ['request_count', -1], ['request_count', 129], ['request_count', PHP_INT_MAX],
            ['request_count', '1'], ['request_count', 1.0], ['request_count', null],
            ['response_bytes', -1], ['response_bytes', 1], ['response_bytes', PHP_INT_MAX],
            ['response_bytes', '0'], ['response_bytes', 0.0], ['response_bytes', null]] as [$field, $value]) {
            $state = [...$base, $field => $value];
            agentEvaluationControllerExpectFailure(
                static function () use ($terminal, &$state): void { agentEvaluationControllerProxyComplete($terminal, $state); },
                'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
            );
            agentEvaluationControllerTest($state['blocked'] === true && $state['response_rejection_stage'] === 'response_bytes'
                && $state[$field] === $value && $state['reserved_input'] === 20 && $state['reserved_output'] === 100
                && $state['request_sha256'] === $base['request_sha256'] && $state['input_tokens'] === 0 && $state['output_tokens'] === 0
                && agentEvaluationControllerProxySpendingLedger($state) === agentEvaluationControllerProxySpendingLedger($base)
                && agentEvaluationControllerProxyAggregateUsage($state)['input_tokens'] === null,
                'Malformed request/byte counters and impossible prior bytes must fail before arithmetic, parsing or reservation release.');
        }
        $state = [...$base, 'request_count' => 128, 'observed_request_count' => 128, 'response_bytes' => 127 * $limit];
        agentEvaluationControllerProxyComplete($fullResponse, $state);
        agentEvaluationControllerTest($state['response_bytes'] === 536_870_912 && $state['reserved_output'] === 0
            && $state['request_sha256'] === null && $state['blocked'] === false,
            'A bounded counter fixture at the final request must admit the exact derived aggregate limit without allocating 512 MiB.');
        $state = [...$base, 'blocked' => true, 'request_count' => PHP_INT_MAX, 'response_bytes' => PHP_INT_MAX];
        agentEvaluationControllerExpectFailure(
            static function () use ($terminal, &$state): void { agentEvaluationControllerProxyComplete($terminal, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
        );
        agentEvaluationControllerTest($state['response_rejection_stage'] === 'availability'
            && agentEvaluationControllerProxySpendingLedger($state) === agentEvaluationControllerProxySpendingLedger($base),
            'Already unavailable response state must retain availability precedence over malformed byte counters.');
    }
}

function agentEvaluationControllerTestProxyKeepalive(): void
{
    $secret = 'synthetic-keepalive-metadata-never-authoritative';
    $usage = ['input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 50,
        'input_tokens_details' => ['cached_tokens' => 5], 'output_tokens_details' => ['reasoning_tokens' => 7]];
    $keepalive = "event: keepalive\ndata: {\"type\":\"keepalive\"}\n\n";
    $forged = 'event: keepalive' . "\ndata: " . json_encode(['type' => 'keepalive',
        'response' => ['model' => $secret, 'service_tier' => $secret, 'status' => 'completed',
            'usage' => ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0]],
        'output' => [['text' => $secret]], 'error' => ['message' => $secret],
        'sequence_number' => ['unvalidated' => $secret]], JSON_THROW_ON_ERROR) . "\n\n";
    foreach ([null, agentEvaluationControllerCalibrationSpending()] as $policy) {
        $model = $policy === null ? 'phpthis-fixture' : 'gpt-5.4-2026-03-05';
        $base = agentEvaluationControllerProxyState($model, 'high', $policy === null ? 120 : 200_000, $policy);
        $body = json_encode(['model' => $model, 'stream' => true, 'store' => false,
            'input' => 'Offline keepalive control.', 'reasoning' => ['effort' => 'high'], 'tools' => [], 'max_output_tokens' => 100], JSON_THROW_ON_ERROR);
        $request = agentEvaluationControllerProxyRequest($body, $base);
        agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $base);
        $response = ['model' => $model, 'service_tier' => 'default', 'status' => 'completed', 'usage' => $usage];
        $terminal = agentEvaluationControllerTestResponseStream($response);
        $valid = $keepalive . "data: {\"type\":\"response.created\"}\n\n" . $forged
            . "data: {\"type\":\"keepalive\"}\n\n" . $terminal;
        $state = $base;
        $settled = agentEvaluationControllerProxyComplete($valid, $state);
        agentEvaluationControllerTest($settled === ['input_tokens' => 20, 'output_tokens' => 30, 'cached_tokens' => 5, 'reasoning_tokens' => 7]
            && $state['blocked'] === false && $state['reserved_input'] === 0 && $state['reserved_output'] === 0
            && $state['request_sha256'] === null && $state['response_rejection_stage'] === null
            && $state['response_event_observation'] === null && $state['provider_error_observation'] === null
            && $state['response_bytes'] === strlen($valid) && !str_contains(agentEvaluationJson($state), $secret),
            'Exact keepalive events may be interleaved before the validated terminal, without giving any extra payload accounting authority.');
        $money = agentEvaluationControllerProxySpendingLedger($state);
        agentEvaluationControllerTest($policy === null ? $money === null
            : ($money !== null && $money['settled_units'] === 48_875 && $money['reserved_units'] === 0),
            'Ignoring keepalive must preserve both legacy and dollar-reserving canonical settlement.');

        $cases = [
            [$keepalive, 'terminal_identity'],
            [$forged, 'terminal_identity'],
            [agentEvaluationControllerTestResponseStream($response, 'keepalive'), 'terminal_identity'],
            [$keepalive . $forged, 'terminal_identity'],
            ["event: response.created\ndata: {\"type\":\"keepalive\"}\n\n" . $terminal, 'sse_event_identity'],
            ["event: keepalive\ndata: {\"type\":\"response.completed\"}\n\n" . $terminal, 'sse_event_identity'],
            ["event: keepalive.extra\ndata: {\"type\":\"keepalive.extra\"}\n\n" . $terminal, 'sse_event_identity'],
            ["event: Keepalive\ndata: {\"type\":\"Keepalive\"}\n\n" . $terminal, 'sse_event_identity'],
            [$terminal . $keepalive, 'sse_order'],
            [$keepalive . agentEvaluationControllerTestResponseStream([...$response, 'model' => $secret]), 'terminal_identity'],
            [$keepalive . agentEvaluationControllerTestResponseStream([...$response, 'service_tier' => $secret]), 'terminal_identity'],
            [$keepalive . agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'total_tokens' => 0]]), 'usage_reservation'],
            ["event: keepalive\ndata: {\"type\":\"keepalive\",\"type\":\"response.completed\"}\n\n", 'sse_json'],
        ];
        foreach ($cases as [$stream, $stage]) {
            $state = $base;
            agentEvaluationControllerExpectFailure(
                static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
                'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
            );
            agentEvaluationControllerTest($state['blocked'] === true && $state['response_rejection_stage'] === $stage
                && $state['reserved_input'] === 20 && $state['reserved_output'] === 100
                && $state['request_sha256'] === $base['request_sha256'] && $state['input_tokens'] === 0 && $state['output_tokens'] === 0
                && agentEvaluationControllerProxyAggregateUsage($state)['input_tokens'] === null,
                'Keepalive must not relax unknown identity, terminal ordering, model/tier checks or authoritative usage and reservation validation.');
            agentEvaluationControllerTest(agentEvaluationControllerProxySpendingLedger($state) === agentEvaluationControllerProxySpendingLedger($base),
                'An ambiguous or rejected stream containing keepalive must retain its complete money reservation.');
        }
        $state = $base;
        $padded = ':' . str_repeat('x', AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES - strlen($valid) - 3) . "\n\n" . $valid;
        agentEvaluationControllerProxyComplete($padded, $state);
        $request = agentEvaluationControllerProxyRequest($body, $state);
        agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":20}', $state);
        agentEvaluationControllerProxyComplete($valid, $state);
        $money = agentEvaluationControllerProxySpendingLedger($state);
        agentEvaluationControllerTest($state['response_rejection_stage'] === null && $state['request_count'] === 2
            && $state['response_bytes'] === AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES + strlen($valid)
            && $state['reserved_input'] === 0 && $state['reserved_output'] === 0
            && $state['input_tokens'] === 40 && $state['output_tokens'] === 60
            && ($policy === null ? $money === null : ($money !== null && $money['settled_units'] === 97_750 && $money['reserved_units'] === 0))
            && !str_contains(agentEvaluationJson($state), $secret),
            'Keepalive bytes count toward each bounded response while separate validated responses may exceed four MiB cumulatively.');
    }
}

function agentEvaluationControllerTestResponseDiagnostics(): void
{
    $secret = 'synthetic-secret-output-must-not-be-retained';
    $usage = ['input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 50,
        'input_tokens_details' => ['cached_tokens' => 5], 'output_tokens_details' => ['reasoning_tokens' => 7]];
    $response = ['id' => 'resp_' . str_repeat('a', 32), 'model' => 'phpthis-fixture', 'status' => 'completed',
        'service_tier' => 'default', 'usage' => $usage, 'error' => null,
        'output' => [['text' => $secret, 'arguments' => $secret]]];
    $success = agentEvaluationControllerTestResponseStream($response);
    $cases = [
        ['', 'terminal_identity', false],
        ["id: " . $secret . "\n\n", 'sse_frame', false],
        ["data: {\"type\":\"response.created\",\"type\":\"response.completed\"}\n\n", 'sse_json', false],
        ["event: wrong\ndata: {\"type\":\"response.created\"}\n\n", 'sse_event_identity', false],
        ["event: error\ndata: " . json_encode(['type' => 'error', 'message' => $secret, 'code' => $secret], JSON_THROW_ON_ERROR) . "\n\n", 'provider_error_event', false],
        ["data: {\"type\":\"response.completed\",\"response\":[]}\n\n", 'terminal_response', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'model' => $secret]), 'terminal_identity', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'service_tier' => $secret]), 'terminal_identity', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'status' => 'queued']), 'terminal_identity', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => null]), 'usage_object', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'input_tokens' => '20']]), 'usage_input_tokens', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'output_tokens' => -1]]), 'usage_output_tokens', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'total_tokens' => 50.5]]), 'usage_total_tokens', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'input_tokens' => 21, 'total_tokens' => 51]]), 'usage_reservation', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'output_tokens' => 101, 'total_tokens' => 121]]), 'usage_reservation', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'total_tokens' => 49]]), 'usage_reservation', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'input_tokens_details' => ['cached_tokens' => 21]]]), 'usage_cached_tokens', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'usage' => [...$usage, 'output_tokens_details' => ['reasoning_tokens' => 31]]]), 'usage_reasoning_tokens', false],
        [$success . "data: {\"type\":\"response.created\"}\n\n", 'sse_order', false],
        [agentEvaluationControllerTestResponseStream([...$response, 'status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens']], 'response.incomplete'), 'terminal_status', true],
        [agentEvaluationControllerTestResponseStream([...$response, 'status' => 'failed', 'error' => ['message' => $secret, 'code' => $secret]], 'response.failed'), 'terminal_status', true],
    ];
    foreach ($cases as [$stream, $expectedStage, $settled]) {
        $state = agentEvaluationControllerTestReservedProxyState();
        $requestHash = $state['request_sha256'];
        agentEvaluationControllerExpectFailure(
            static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
        );
        agentEvaluationControllerTest(
            $state['blocked'] === true && $state['response_rejection_stage'] === $expectedStage
            && $state['last_response_sha256'] === hash('sha256', $stream) && $state['last_response_bytes'] === strlen($stream)
            && $state['provider_error_event_seen'] === ($expectedStage === 'provider_error_event')
            && $state['reserved_input'] === ($settled ? 0 : 20) && $state['reserved_output'] === ($settled ? 0 : 100)
            && $state['request_sha256'] === ($settled ? null : $requestHash)
            && $state['input_tokens'] === ($settled ? 20 : 0) && $state['output_tokens'] === ($settled ? 30 : 0),
            'Response diagnostics must identify the fixed rejection stage without changing reservation or terminal settlement rules: ' . $expectedStage,
        );
        $json = agentEvaluationJson($state);
        agentEvaluationControllerTest(!str_contains($json, $secret) && strlen($json) < 4096,
            'Diagnostics must never retain raw response text, arguments, model/tier strings, or provider error messages.');
        $aggregate = agentEvaluationControllerProxyAggregateUsage($state);
        agentEvaluationControllerTest($aggregate['input_tokens'] === ($settled ? 20 : null)
            && $aggregate['output_tokens'] === ($settled ? 30 : null),
            'Unvalidated observations must never supply authoritative usage while the reservation is outstanding.');
    }
    $state = agentEvaluationControllerTestReservedProxyState();
    $settledUsage = agentEvaluationControllerProxyComplete($success, $state);
    $observation = agentEvaluationRequireObject($state, 'response_observation', 'successful response diagnostics');
    agentEvaluationControllerTest(
        $settledUsage === ['input_tokens' => 20, 'output_tokens' => 30, 'cached_tokens' => 5, 'reasoning_tokens' => 7]
        && $state['blocked'] === false && $state['response_rejection_stage'] === null
        && $state['provider_error_observation'] === null
        && $state['reserved_input'] === 0 && $state['reserved_output'] === 0 && $state['request_sha256'] === null
        && $observation['authority'] === 'unvalidated-provider-observation'
        && $observation['response_id'] === $response['id'] && $observation['status'] === 'completed'
        && $observation['model_matches'] === true && $observation['service_tier_matches'] === true
        && $observation['error_present'] === false && $observation['usage_is_object'] === true,
        'Successful responses must settle unchanged while retaining only separately labelled structural diagnostics.',
    );
    $wrongCounts = agentEvaluationControllerProxyJsonObject(json_encode([...$response,
        'id' => 'resp_' . $secret, 'status' => $secret, 'model' => $secret, 'service_tier' => $secret,
        'error' => ['message' => $secret], 'usage' => ['input_tokens' => $secret, 'output_tokens' => -1,
            'total_tokens' => 1_000_000_001, 'input_tokens_details' => ['cached_tokens' => '5'],
            'output_tokens_details' => ['reasoning_tokens' => 7.5]]], JSON_THROW_ON_ERROR));
    $observed = agentEvaluationControllerProxyResponseObservation($wrongCounts, 'response.failed', $state);
    agentEvaluationControllerTest($observed['response_id'] === null && $observed['status'] === null
        && !$observed['model_matches'] && !$observed['service_tier_matches'] && $observed['error_present']
        && $observed['usage'] === ['input_tokens' => null, 'output_tokens' => null, 'total_tokens' => null, 'cached_tokens' => null, 'reasoning_tokens' => null]
        && !str_contains(agentEvaluationJson($observed), $secret),
        'Malformed IDs, unknown status strings, and invalid token values must become absent observations, never raw diagnostics.');
    foreach ([0, 1_000_000_000] as $count) {
        agentEvaluationControllerTest(agentEvaluationControllerProxyObservedTokenCount($count) === $count,
            'Observed-only token counts have explicit inclusive integer bounds.');
    }
    foreach ([null, -1, 1.0, '1', 1_000_000_001, [], new stdClass()] as $invalid) {
        agentEvaluationControllerTest(agentEvaluationControllerProxyObservedTokenCount($invalid) === null,
            'Diagnostic count parsing must reject rather than coerce noninteger or excessive values.');
    }
    $oversized = str_repeat('x', AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES + 1);
    $state = agentEvaluationControllerTestReservedProxyState();
    agentEvaluationControllerExpectFailure(
        static function () use ($oversized, &$state): void { agentEvaluationControllerProxyComplete($oversized, $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
    );
    agentEvaluationControllerTest($state['response_rejection_stage'] === 'response_bytes'
        && $state['last_response_sha256'] === null && $state['last_response_bytes'] === null
        && $state['response_observation'] === null && $state['reserved_input'] === 20 && $state['reserved_output'] === 100,
        'An oversized individual body must fail without hashing, retaining, parsing, or settling it.');
}

function agentEvaluationControllerTestProviderErrorDiagnostics(): void
{
    $secret = 'syntheticSecretNeverRetain';
    $cases = [
        [['type' => 'error', 'code' => 'invalid_type', 'param' => 'tools[0].type', 'message' => $secret, 'sequence_number' => 1],
            'top-level', 'invalid_type', 'error', 'tools'],
        [['type' => 'error', 'code' => 'unsupported_parameter', 'param' => 'reasoning.effort', 'message' => $secret],
            'top-level', 'unsupported_parameter', 'error', 'reasoning'],
        [['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'code' => 'invalid_type', 'param' => 'tools[0].' . $secret, 'message' => $secret]],
            'nested-error-object', 'invalid_type', 'invalid_request_error', 'tools'],
        [['type' => 'error', 'error' => ['type' => 'insufficient_quota', 'code' => 'credit_balance_exhausted', 'param' => null, 'message' => $secret]],
            'nested-error-object', 'credit_balance_exhausted', 'insufficient_quota', null],
        [['type' => 'error', 'code' => null, 'param' => null, 'message' => null,
            'error' => ['type' => 'invalid_request_error', 'code' => 'invalid_type', 'param' => 'tools', 'message' => $secret]],
            'top-level', null, 'error', null],
        [['type' => 'error', 'code' => $secret, 'param' => $secret, 'message' => $secret],
            'top-level', null, 'error', null],
        [['type' => 'error', 'error' => ['type' => $secret, 'code' => $secret, 'param' => $secret, 'message' => $secret]],
            'nested-error-object', null, null, null],
        [['type' => 'error', 'code' => null, 'param' => null, 'message' => ''],
            'top-level', null, 'error', null],
        [['type' => 'error'], 'top-level', null, 'error', null],
        [['type' => 'error', 'error' => $secret], 'top-level', null, 'error', null],
        [['type' => 'error', 'code' => true, 'param' => new stdClass(), 'message' => 123],
            'top-level', null, 'error', null],
        [['type' => 'error', 'error' => ['type' => [], 'code' => [$secret], 'param' => false, 'message' => ['secret' => $secret]]],
            'nested-error-object', null, null, null],
        [['type' => 'error', 'code' => str_repeat('x', 8192), 'param' => 'tools.' . str_repeat('x', 1024), 'message' => str_repeat($secret, 1024)],
            'top-level', null, 'error', null],
    ];
    foreach ($cases as [$event, $envelope, $code, $type, $root]) {
        foreach (["event: error\n", ''] as $header) {
            $stream = $header . 'data: ' . json_encode($event, JSON_THROW_ON_ERROR) . "\n\n";
            $state = agentEvaluationControllerTestReservedProxyState();
            $requestHash = $state['request_sha256'];
            agentEvaluationControllerExpectFailure(
                static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
                'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
            );
            $observation = agentEvaluationRequireObject($state, 'provider_error_observation', 'provider error diagnostics');
            agentEvaluationControllerTest(
                $state['response_rejection_stage'] === 'provider_error_event' && $state['provider_error_event_seen'] === true
                && $state['blocked'] === true && $state['reserved_input'] === 20 && $state['reserved_output'] === 100
                && $state['request_sha256'] === $requestHash && $state['input_tokens'] === 0 && $state['output_tokens'] === 0
                && agentEvaluationControllerProxyAggregateUsage($state)['input_tokens'] === null
                && $observation['authority'] === 'unvalidated-provider-error-observation'
                && $observation['envelope_source'] === $envelope && $observation['code'] === $code
                && $observation['type'] === $type && $observation['parameter_root'] === $root,
                'Documented, nested, missing, and malformed error fields must stay rejected with unknown usage and unchanged reservations.',
            );
            $json = agentEvaluationJson($state);
            agentEvaluationControllerTest(strlen($json) < 4096 && !str_contains($json, $secret)
                && !str_contains($json, 'tools[0].type') && !str_contains($json, 'reasoning.effort'),
                'Provider diagnostics must retain only fixed categories and parameter roots, never messages or parameter suffixes.');
        }
    }

    $observed = agentEvaluationControllerProxyErrorObservation(['type' => 'error', 'code' => 'invalid_type',
        'param' => 'tools[0].type', 'message' => $secret]);
    agentEvaluationControllerTest($observed['top_level_fields_present'] && !$observed['nested_error_present']
        && !$observed['nested_error_is_object']
        && $observed['fields']['message'] === ['present' => true, 'kind' => 'string', 'bytes' => strlen($secret),
            'sha256' => hash('sha256', $secret), 'over_limit' => false]
        && $observed['fields']['param']['bytes'] === strlen('tools[0].type')
        && $observed['fields']['param']['sha256'] === hash('sha256', 'tools[0].type'),
        'Withheld field fingerprints must describe exact bounded bytes without retaining those bytes.');
    $nullable = agentEvaluationControllerProxyErrorObservation(['type' => 'error', 'code' => null, 'param' => false, 'error' => new stdClass()]);
    agentEvaluationControllerTest($nullable['envelope_source'] === 'top-level' && $nullable['nested_error_present']
        && $nullable['nested_error_is_object']
        && $nullable['fields']['code'] === ['present' => true, 'kind' => 'null', 'bytes' => null, 'sha256' => null, 'over_limit' => false]
        && $nullable['fields']['param'] === ['present' => true, 'kind' => 'other', 'bytes' => null, 'sha256' => null, 'over_limit' => false]
        && $nullable['fields']['message'] === ['present' => false, 'kind' => 'missing', 'bytes' => null, 'sha256' => null, 'over_limit' => false],
        'Presence diagnostics must distinguish absent, null, and malformed values without merging nested fields.');

    foreach (['tools' => 'tools', 'tools[0].type' => 'tools', 'reasoning.effort' => 'reasoning',
        'input[0].content[1].text' => 'input', 'text.format.schema' => 'text', 'model' => 'model'] as $param => $root) {
        agentEvaluationControllerTest(agentEvaluationControllerProxyErrorObservation(['type' => 'error', 'param' => $param])['parameter_root'] === $root,
            'Only complete recognized root names with legitimate property/index paths may yield a parameter root.');
    }
    foreach (['tools' . $secret, 'tools.', 'tools[', 'tools[-1]', 'tools[01]', 'tools[0]suffix',
        'tools[' . $secret . ']', 'tools..type', 'tools.[0]', 'tools[0]["key"]', 'input.0', $secret,
        'tools.' . str_repeat('x', 1024)] as $param) {
        agentEvaluationControllerTest(agentEvaluationControllerProxyErrorObservation(['type' => 'error', 'param' => $param])['parameter_root'] === null,
            'Unknown, malformed, prefix-collision, and oversized parameter paths must remain unknown.');
    }
    $oversized = str_repeat('x', AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES + 1);
    $over = agentEvaluationControllerProxyErrorObservation(['type' => 'error', 'code' => $oversized, 'param' => $oversized, 'message' => $oversized]);
    foreach (['code', 'param', 'message'] as $name) {
        agentEvaluationControllerTest($over['fields'][$name] === ['present' => true, 'kind' => 'string', 'bytes' => null, 'sha256' => null, 'over_limit' => true],
            'Even direct diagnostic calls must neither hash nor report excessive field lengths outside the response byte bound.');
    }

    $mismatch = "event: response.failed\ndata: {\"type\":\"error\",\"code\":\"invalid_type\",\"param\":\"tools\"}\n\n";
    $state = agentEvaluationControllerTestReservedProxyState();
    agentEvaluationControllerExpectFailure(
        static function () use ($mismatch, &$state): void { agentEvaluationControllerProxyComplete($mismatch, $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
    );
    agentEvaluationControllerTest($state['response_rejection_stage'] === 'sse_event_identity'
        && $state['provider_error_event_seen'] === false && $state['provider_error_observation'] === null,
        'A conflicting SSE event name must remain an identity failure without acquiring a provider-error interpretation.');
    $state = agentEvaluationControllerTestReservedProxyState();
    $state['provider_error_observation'] = ['stale' => $secret];
    $state['provider_error_event_seen'] = true;
    $result = agentEvaluationControllerProxyComplete(agentEvaluationControllerTestResponseStream([
        'model' => 'phpthis-fixture', 'status' => 'completed',
        'usage' => ['input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 50],
    ]), $state);
    agentEvaluationControllerTest($state['provider_error_observation'] === null && $state['provider_error_event_seen'] === false
        && $state['response_rejection_stage'] === null && $state['blocked'] === false
        && $state['reserved_input'] === 0 && $state['reserved_output'] === 0 && $state['request_sha256'] === null
        && $result === ['input_tokens' => 20, 'output_tokens' => 30, 'cached_tokens' => null, 'reasoning_tokens' => null],
        'Successful canonical settlement must clear stale diagnostics and retain its original exact usage behavior.');
}

function agentEvaluationControllerTestEventIdentityDiagnostics(): void
{
    $secret = 'syntheticUnknownIdentityNeverRetain';
    $cases = [
        [null, [], 'missing_type', null, null, 'missing'],
        [null, ['type' => null], 'nonstring_type', null, null, 'null'],
        [null, ['type' => 17], 'nonstring_type', null, null, 'other'],
        [null, ['type' => ['private' => $secret]], 'nonstring_type', null, null, 'other'],
        [null, ['type' => ''], 'empty_type', 'other', null, 'string'],
        [null, ['type' => ' '], 'unsupported_type_family', 'other', null, 'string'],
        [null, ['type' => $secret], 'unsupported_type_family', 'other', null, 'string'],
        [$secret, ['type' => 'response.created'], 'header_type_mismatch', 'response', false, 'string'],
        ['response.created', ['type' => $secret], 'header_type_mismatch', 'other', false, 'string'],
        ['response.created', ['type' => null], 'nonstring_type', null, null, 'null'],
        ['response.created', ['type' => ''], 'header_type_mismatch', 'other', false, 'string'],
        ['response.created', ['type' => 'error'], 'header_type_mismatch', 'error', false, 'string'],
        ['', ['type' => 'response.created'], 'header_type_mismatch', 'response', false, 'string'],
        [$secret, ['type' => $secret], 'unsupported_type_family', 'other', true, 'string'],
        ['', ['type' => ''], 'empty_type', 'other', true, 'string'],
    ];
    foreach ($cases as [$header, $event, $reason, $family, $matches, $kind]) {
        if ($header === null) {
            $expectedHeaderPresent = false;
            $expectedHeaderBytes = null;
            $expectedHeaderHash = null;
            $headerLine = '';
        } else {
            $expectedHeaderPresent = true;
            $expectedHeaderBytes = strlen($header);
            $expectedHeaderHash = hash('sha256', $header);
            $headerLine = 'event: ' . $header . "\n";
        }
        $state = agentEvaluationControllerTestReservedProxyState();
        $requestHash = $state['request_sha256'];
        $stream = ": ignored comment\n\nevent: response.created\n\ndata: {\"type\":\"response.created\"}\n\n"
            . $headerLine
            . 'data: ' . json_encode((object) [...$event, 'private_payload' => $secret], JSON_THROW_ON_ERROR) . "\n\n";
        agentEvaluationControllerExpectFailure(
            static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
            'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
        );
        $observation = agentEvaluationRequireObject($state, 'response_event_observation', 'event identity diagnostics');
        $fields = agentEvaluationRequireObject($observation, 'fields', 'event identity diagnostics');
        $typeField = agentEvaluationRequireObject($fields, 'type', 'event identity fields');
        $headerField = agentEvaluationRequireObject($fields, 'event_name', 'event identity fields');
        $aggregate = agentEvaluationControllerProxyAggregateUsage($state);
        agentEvaluationControllerTest($state['response_rejection_stage'] === 'sse_event_identity'
            && $state['blocked'] === true && $state['reserved_input'] === 20 && $state['reserved_output'] === 100
            && $state['request_sha256'] === $requestHash && $state['input_tokens'] === 0 && $state['output_tokens'] === 0
            && $aggregate['input_tokens'] === null && $aggregate['output_tokens'] === null
            && $state['provider_error_event_seen'] === false && $state['provider_error_observation'] === null
            && $observation['authority'] === 'unvalidated-provider-event-observation'
            && $observation['data_event_ordinal'] === 2 && $observation['identity_reason'] === $reason
            && $observation['type_family'] === $family && $observation['header_matches_type'] === $matches
            && $typeField['kind'] === $kind && $typeField['present'] === array_key_exists('type', $event)
            && $headerField['present'] === $expectedHeaderPresent
            && $headerField['bytes'] === $expectedHeaderBytes
            && $headerField['sha256'] === $expectedHeaderHash,
            'Identity diagnostics must distinguish exact failing conditions and data-event ordinal without settling or accepting anything.');
        $type = $event['type'] ?? null;
        agentEvaluationControllerTest($typeField['bytes'] === (is_string($type) ? strlen($type) : null)
            && $typeField['sha256'] === (is_string($type) ? hash('sha256', $type) : null)
            && !str_contains(agentEvaluationJson($state), $secret) && strlen(agentEvaluationJson($state)) < 4096,
            'Unknown header/type strings and payloads must leave only bounded exact field fingerprints, including empty and whitespace strings.');
    }
    $oversized = str_repeat('x', AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES + 1);
    $observation = agentEvaluationControllerProxyEventObservation(['type' => $oversized], $oversized, 1);
    foreach (['event_name', 'type'] as $name) {
        agentEvaluationControllerTest($observation['fields'][$name] === ['present' => true, 'kind' => 'string',
            'bytes' => null, 'sha256' => null, 'over_limit' => true],
            'Direct identity diagnostics must not hash or report excessive field lengths beyond the response byte bound.');
    }
    foreach ([0, -1, AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_BYTES + 1] as $ordinal) {
        agentEvaluationControllerTest(agentEvaluationControllerProxyEventObservation([], null, $ordinal)['data_event_ordinal'] === null,
            'Diagnostic event ordinals must be positive and bounded independently of caller input.');
    }
    $success = agentEvaluationControllerTestResponseStream(['model' => 'phpthis-fixture', 'status' => 'completed',
        'usage' => ['input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 50]]);
    foreach ([[$success, null], ["event: error\ndata: {\"type\":\"error\"}\n\n", 'provider_error_event'],
        ["data: invalid-json\n\n", 'sse_json'], ['', 'terminal_identity'], [$oversized, 'response_bytes']] as [$stream, $stage]) {
        $state = agentEvaluationControllerTestReservedProxyState();
        $state['response_event_observation'] = ['stale' => $secret];
        if ($stage === null) {
            $usage = agentEvaluationControllerProxyComplete($stream, $state);
            agentEvaluationControllerTest($usage === ['input_tokens' => 20, 'output_tokens' => 30, 'cached_tokens' => null, 'reasoning_tokens' => null]
                && $state['blocked'] === false && $state['reserved_input'] === 0 && $state['reserved_output'] === 0,
                'Successful responses must retain their unchanged exact canonical settlement.');
        } else {
            agentEvaluationControllerExpectFailure(
                static function () use ($stream, &$state): void { agentEvaluationControllerProxyComplete($stream, $state); },
                'AGENT_EVALUATION_CONTROLLER_PROXY_RESPONSE_REJECTED',
            );
            agentEvaluationControllerTest($state['reserved_input'] === 20 && $state['reserved_output'] === 100,
                'Other response failures must preserve their original outstanding reservations.');
        }
        agentEvaluationControllerTest($state['response_event_observation'] === null && $state['response_rejection_stage'] === $stage,
            'Success, provider errors, and other failure stages must clear stale identity observations without retaining new ones.');
    }
}

function agentEvaluationControllerTestProcessBounds(string $root): void
{
    $fixture = $root . '/tools/agent-evaluation-controller/fixtures/fake-codex.php';
    $environment = agentEvaluationControllerMinimalProcessEnvironment();
    $output = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'process-output-limit'],
        $root,
        $environment,
        '',
        2,
        64,
    );
    agentEvaluationControllerTest(
        $output['output_limit_exceeded']
        && $output['termination_reason'] === 'output_limit'
        && strlen($output['stdout']) === 64
        && $output['cleanup']['process_group_absent'],
        'Process output overflow must terminate and reap the fixed process group.',
    );

    $wall = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'process-wall-limit'],
        $root,
        $environment,
        '',
        1,
        1_024,
    );
    agentEvaluationControllerTest(
        $wall['timed_out']
        && $wall['termination_reason'] === 'wall_time_limit'
        && $wall['cleanup']['terminate_sent']
        && $wall['cleanup']['process_group_absent'],
        'Process wall overflow must terminate and reap the fixed process group.',
    );

    $failure = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'process-fail'],
        $root,
        $environment,
        '',
        2,
        1_024,
    );
    agentEvaluationControllerTest(
        $failure['exit_code'] === 42
        && $failure['termination_reason'] === 'process_failed'
        && $failure['stderr'] === "EXPECTED synthetic process failure\n",
        'Process failure must retain one bounded exact result.',
    );
    $invalidParsedOutput = agentEvaluationControllerParseCodexEvents(
        $failure['stdout'],
        40_000,
    );
    agentEvaluationControllerTest(
        agentEvaluationControllerCodexTerminationReason($wall, $invalidParsedOutput)
            === 'wall_time_limit'
        && agentEvaluationControllerCodexTerminationReason($output, $invalidParsedOutput)
            === 'output_limit'
        && agentEvaluationControllerCodexTerminationReason($failure, $invalidParsedOutput)
            === 'runner_failed'
        && !agentEvaluationControllerSyntheticCheckPassed(
            $failure,
            "PASS synthetic public scorer\n",
        ),
        'Generation and scoring failures must retain deterministic bounded classifications.',
    );

    $partial = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'process-partial-jsonl'],
        $root,
        $environment,
        '',
        2,
        1_024,
    );
    $partialParsed = agentEvaluationControllerParseCodexEvents($partial['stdout'], 40_000);
    agentEvaluationControllerTest(
        $partial['exit_code'] === 0
        && !$partialParsed['valid']
        && agentEvaluationControllerCodexTerminationReason($partial, $partialParsed)
            === 'invalid_runner_output',
        'Interrupted JSONL must remain a failed partial generation rather than a completed run.',
    );

    $descendant = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'process-descendant'],
        $root,
        $environment,
        '',
        1,
        1_024,
    );
    agentEvaluationControllerTest(
        $descendant['timed_out']
        && $descendant['cleanup']['process_group_created']
        && $descendant['cleanup']['process_reaped']
        && $descendant['cleanup']['process_group_absent'],
        'A same-group descendant must not survive bounded synthetic termination.',
    );

    $orphanedDescendant = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'process-orphaned-descendant'],
        $root,
        $environment,
        '',
        2,
        1_024,
    );
    agentEvaluationControllerTest(
        $orphanedDescendant['exit_code'] === 0
        && !$orphanedDescendant['timed_out']
        && $orphanedDescendant['termination_reason'] === 'completed'
        && $orphanedDescendant['cleanup']['terminate_sent']
        && $orphanedDescendant['cleanup']['process_reaped']
        && $orphanedDescendant['cleanup']['process_group_absent'],
        'A same-group descendant must be terminated even after its parent exits successfully.',
    );
}

function agentEvaluationControllerTestCliGrammar(string $root): void
{
    $entrypoint = $root . '/tools/agent-evaluation-controller.php';
    $environment = agentEvaluationControllerMinimalProcessEnvironment();
    $validate = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $entrypoint, 'validate'],
        $root,
        $environment,
        '',
        5,
        4_096,
    );
    agentEvaluationControllerTest(
        $validate['exit_code'] === 0
        && $validate['termination_reason'] === 'completed'
        && $validate['stdout']
            === "PASS agent evaluation controller v0.2: synthetic lifecycle installed; live execution fails closed\n"
        && $validate['stderr'] === '',
        'The fixed validate command must retain exact successful CLI behavior.',
    );

    $extra = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $entrypoint, 'validate', 'unexpected'],
        $root,
        $environment,
        '',
        5,
        4_096,
    );
    agentEvaluationControllerTest(
        $extra['exit_code'] === 1
        && $extra['termination_reason'] === 'process_failed'
        && $extra['stdout'] === ''
        && $extra['stderr']
            === "FAIL agent evaluation controller: validate received an unexpected number of arguments.\n",
        'The controller CLI must reject every extra validate argument.',
    );

    $run = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $entrypoint, 'run', '00000000000000000000000000000042'],
        $root,
        $environment,
        '',
        5,
        4_096,
    );
    agentEvaluationControllerTest(
        $run['exit_code'] === 1
        && $run['termination_reason'] === 'process_failed'
        && str_contains($run['stderr'], AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE)
        && $run['stdout'] === '',
        'The only live CLI command must fail closed with its stable boundary marker.',
    );
}

/** @param Closure(): void $callback */
function agentEvaluationControllerExpectFreezeReason(Closure $callback, string $message, string $reason): void
{
    try {
        $callback();
    } catch (Throwable $failure) {
        agentEvaluationControllerTest(
            $failure::class === RuntimeException::class
            && $failure->getMessage() === $message
            && agentEvaluationControllerFreezeFailureReason($failure) === $reason,
            'The actual freeze rejection must preserve its original failure and exact safe reason.',
        );
        return;
    }

    throw new RuntimeException('Expected tagged freeze failure was not reported.');
}

/**
 * @param array<string, mixed> $task
 */
function agentEvaluationControllerTestWorkspaceControls(
    string $root,
    string $dependencies,
    string $temporaryRoot,
    array $task,
): void {
    $hostileMessage = "PRIVATE-FREEZE-SENTINEL /candidate/src/private-value.php\n";
    foreach ([
        new RuntimeException($hostileMessage, 69_999),
        new RuntimeException('candidate_new_path_unapproved'),
        new RuntimeException('Candidate created unapproved path ' . $hostileMessage . '.'),
        new class($hostileMessage, AGENT_EVALUATION_CONTROLLER_FREEZE_NEW_PATH_UNAPPROVED) extends RuntimeException {
        },
        new LogicException($hostileMessage, AGENT_EVALUATION_CONTROLLER_FREEZE_NEW_PATH_UNAPPROVED),
        new Error($hostileMessage, AGENT_EVALUATION_CONTROLLER_FREEZE_NEW_PATH_UNAPPROVED),
    ] as $failure) {
        agentEvaluationControllerTest(
            agentEvaluationControllerFreezeFailureReason($failure) === null,
            'Unknown tags, message-only lookalikes, subclasses, and other throwable classes must remain unclassified.',
        );
    }
    agentEvaluationControllerTest(
        agentEvaluationControllerFreezeFailureReason(
            new RuntimeException($hostileMessage, AGENT_EVALUATION_CONTROLLER_FREEZE_NEW_PATH_UNAPPROVED),
        ) === 'candidate_new_path_unapproved',
        'A recognized tag must return only its fixed reason, independently of hostile exception text.',
    );

    $tamperedSource = $temporaryRoot . '/tampered-source';
    agentEvaluationControllerCopyTree($root . '/skeleton', $tamperedSource, 'tampered source control', true);
    $readme = file_get_contents($tamperedSource . '/README.md');

    if (!is_string($readme) || file_put_contents($tamperedSource . '/README.md', $readme . "\n") === false) {
        throw new RuntimeException('Unable to prepare the tampered source control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($tamperedSource, $dependencies, $temporaryRoot, $task): void {
            agentEvaluationControllerPrepareWorkspace(
                $tamperedSource,
                $dependencies,
                $temporaryRoot . '/source-mismatch-run',
                $task,
            );
        },
        'Prepared source-skeleton fixture digest does not match the selected task revision.',
    );

    $protected = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/protected-run',
        $task,
    );
    $composerPath = $protected['candidate_root'] . '/composer.json';
    $composer = file_get_contents($composerPath);

    if (!is_string($composer) || file_put_contents($composerPath, $composer . "\n") === false) {
        throw new RuntimeException('Unable to prepare the protected-path control.');
    }

    agentEvaluationControllerExpectFreezeReason(
        static function () use ($protected, $task): void {
            agentEvaluationControllerFreezeWorkspace($protected, $task);
        },
        'Candidate changed protected path composer.json.',
        'candidate_protected_path_changed',
    );
    agentEvaluationControllerExpectFailure(
        static function () use ($protected): void {
            agentEvaluationControllerValidateCleanupTarget($protected, $protected['run_root']);
        },
        'Cleanup target is outside the fixed disposable workspace set.',
    );
    agentEvaluationControllerRemoveTree($protected['run_root']);

    $dependencyControl = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/dependency-mismatch-run',
        $task,
    );
    $preparedDependency = $dependencyControl['dependencies_root'] . '/fixture.lock';

    if (
        !chmod($preparedDependency, 0644)
        || file_put_contents($preparedDependency, "tampered\n", FILE_APPEND) === false
        || !chmod($preparedDependency, 0444)
    ) {
        throw new RuntimeException('Unable to prepare the dependency-mismatch control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($dependencyControl, $task): void {
            agentEvaluationControllerFreezeWorkspace($dependencyControl, $task);
        },
        'Prepared dependency fixture.lock changed after preparation.',
    );
    agentEvaluationControllerRemoveTree($dependencyControl['run_root']);

    $unlisted = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/unlisted-run',
        $task,
    );

    $unlistedPath = $unlisted['candidate_root'] . '/src/Unexpected.php';

    if (file_put_contents($unlistedPath, "<?php\n") === false || !chmod($unlistedPath, 0644)) {
        throw new RuntimeException('Unable to prepare the unlisted-path control.');
    }

    agentEvaluationControllerExpectFreezeReason(
        static function () use ($unlisted, $task): void {
            agentEvaluationControllerFreezeWorkspace($unlisted, $task);
        },
        'Candidate created unapproved path src/Unexpected.php.',
        'candidate_new_path_unapproved',
    );
    agentEvaluationControllerRemoveTree($unlisted['run_root']);

    $fileCount = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/changed-file-count-run',
        $task,
    );
    try {
        foreach (['src/HealthRoutes.php', 'tests/run.php'] as $relative) {
            if (file_put_contents($fileCount['candidate_root'] . '/' . $relative, "\n// changed-file count control\n", FILE_APPEND) === false) {
                throw new RuntimeException('Unable to prepare the changed-file-count control.');
            }
        }
        $countBaseline = agentEvaluationControllerDescribeTree($fileCount['baseline_root'], 'file-count baseline', true);
        $countCandidate = agentEvaluationControllerDescribeTree($fileCount['candidate_root'], 'file-count candidate', true);
        $countPolicy = agentEvaluationControllerWorkspacePolicy($task);
        $acceptedChanges = agentEvaluationControllerValidateWorkspacePolicy(
            $fileCount['baseline_root'], $fileCount['candidate_root'], $countBaseline, $countCandidate, $countPolicy,
        );
        agentEvaluationControllerTest(
            $acceptedChanges['changed_files'] === ['src/HealthRoutes.php', 'tests/run.php'],
            'Both ordinary allowed edits must pass before testing a stricter local count bound.',
        );
        $loweredCountPolicy = [...$countPolicy, 'max_changed_files' => 1];
        agentEvaluationControllerExpectFreezeReason(
            static function () use ($fileCount, $countBaseline, $countCandidate, $loweredCountPolicy): void {
                agentEvaluationControllerValidateWorkspacePolicy(
                    $fileCount['baseline_root'], $fileCount['candidate_root'], $countBaseline, $countCandidate, $loweredCountPolicy,
                );
            },
            'Candidate exceeds the maximum changed-file count.',
            'candidate_changed_file_limit',
        );
        agentEvaluationControllerTest(
            agentEvaluationControllerWorkspacePolicy($task) === $countPolicy,
            'The count control must not mutate the admitted task policy.',
        );
    } finally {
        agentEvaluationControllerRemoveTree($fileCount['run_root']);
    }

    $linkControl = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/link-run',
        $task,
    );

    if (!symlink('/private/tmp', $linkControl['candidate_root'] . '/src/PingHandler.php')) {
        throw new RuntimeException('Unable to prepare the symlink control.');
    }

    agentEvaluationControllerExpectFailureContains(
        static function () use ($linkControl, $task): void {
            agentEvaluationControllerFreezeWorkspace($linkControl, $task);
        },
        'contains forbidden symlink src/PingHandler.php',
    );
    agentEvaluationControllerRemoveTree($linkControl['run_root']);

    $hardLinkControl = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/hard-link-run',
        $task,
    );

    if (!link(
        $hardLinkControl['candidate_root'] . '/src/HealthRoutes.php',
        $hardLinkControl['candidate_root'] . '/src/PingHandler.php',
    )) {
        throw new RuntimeException('Unable to prepare the hard-link control.');
    }

    agentEvaluationControllerExpectFailureContains(
        static function () use ($hardLinkControl, $task): void {
            agentEvaluationControllerFreezeWorkspace($hardLinkControl, $task);
        },
        'contains forbidden hard-linked file',
    );
    agentEvaluationControllerRemoveTree($hardLinkControl['run_root']);

    $specialFileControl = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/special-file-run',
        $task,
    );
    $specialPath = $specialFileControl['candidate_root'] . '/src/PingHandler.php';
    if (!posix_mkfifo($specialPath, 0600)) {
        throw new RuntimeException('Unable to prepare the special-file control.');
    }

    agentEvaluationControllerExpectFailureContains(
        static function () use ($specialFileControl, $task): void {
            agentEvaluationControllerFreezeWorkspace($specialFileControl, $task);
        },
        'contains forbidden special file src/PingHandler.php',
    );
    agentEvaluationControllerRemoveTree($specialFileControl['run_root']);

    $modeControl = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/mode-run',
        $task,
    );
    $ping = $modeControl['candidate_root'] . '/src/PingHandler.php';

    if (file_put_contents($ping, "<?php\n\ndeclare(strict_types=1);\n") === false || !chmod($ping, 0755)) {
        throw new RuntimeException('Unable to prepare the executable-mode control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($modeControl, $task): void {
            agentEvaluationControllerFreezeWorkspace($modeControl, $task);
        },
        'Candidate new path src/PingHandler.php must not be executable.',
    );
    agentEvaluationControllerRemoveTree($modeControl['run_root']);

    $lineBound = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/line-bound-run',
        $task,
    );
    $oversizedLines = str_repeat("// bounded line\n", AGENT_EVALUATION_CONTROLLER_MAX_DIFF_LINES + 1);

    $lineBoundPath = $lineBound['candidate_root'] . '/src/PingHandler.php';

    if (file_put_contents($lineBoundPath, $oversizedLines) === false || !chmod($lineBoundPath, 0644)) {
        throw new RuntimeException('Unable to prepare the line-count bound control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($lineBound, $task): void {
            agentEvaluationControllerFreezeWorkspace($lineBound, $task);
        },
        'Candidate text difference exceeds the fixed comparison bound.',
    );
    agentEvaluationControllerRemoveTree($lineBound['run_root']);

    $changeLimit = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/change-limit-run',
        $task,
    );
    $policy = agentEvaluationControllerWorkspacePolicy($task);
    $tooManyAddedLines = str_repeat("// added line\n", $policy['max_added_lines'] + 1);

    $changeLimitPath = $changeLimit['candidate_root'] . '/src/PingHandler.php';

    if (file_put_contents($changeLimitPath, $tooManyAddedLines) === false || !chmod($changeLimitPath, 0644)) {
        throw new RuntimeException('Unable to prepare the added-line-limit control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($changeLimit, $task): void {
            agentEvaluationControllerFreezeWorkspace($changeLimit, $task);
        },
        'Candidate exceeds the maximum added-line count.',
    );
    agentEvaluationControllerRemoveTree($changeLimit['run_root']);

    $mutation = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/mutation-run',
        $task,
    );
    $healthPath = $mutation['candidate_root'] . '/src/HealthRoutes.php';
    $health = file_get_contents($healthPath);

    if (!is_string($health) || file_put_contents($healthPath, $health . "// frozen control\n") === false) {
        throw new RuntimeException('Unable to prepare the post-freeze control.');
    }

    $freeze = agentEvaluationControllerFreezeWorkspace($mutation, $task);

    if (file_put_contents($healthPath, $health . "// mutated after freeze\n") === false) {
        throw new RuntimeException('Unable to mutate the post-freeze control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($mutation, $freeze): void {
            agentEvaluationControllerCreateScoringWorkspace(
                $mutation,
                $mutation['run_root'] . '/scoring',
                $freeze,
            );
        },
        'Candidate mutated after freeze and cannot enter scoring.',
    );
    agentEvaluationControllerRemoveTree($mutation['run_root']);

    $cleanupControl = agentEvaluationControllerPrepareWorkspace(
        $root . '/skeleton',
        $dependencies,
        $temporaryRoot . '/cleanup-failure-run',
        $task,
    );
    $unexpectedCleanupPath = $cleanupControl['run_root'] . '/unexpected.control';

    if (file_put_contents($unexpectedCleanupPath, "unexpected\n") === false) {
        throw new RuntimeException('Unable to prepare the cleanup-failure control.');
    }

    agentEvaluationControllerExpectFailure(
        static function () use ($cleanupControl): void {
            agentEvaluationControllerCleanupWorkspace($cleanupControl);
        },
        'Controller cleanup left an unexpected run-root entry.',
    );

    if (!unlink($unexpectedCleanupPath)) {
        throw new RuntimeException('Unable to remove the cleanup-failure control.');
    }

    agentEvaluationControllerRemoveTree($cleanupControl['run_root']);
}

/** @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets */
function agentEvaluationControllerTestLiveConfiguration(string $root, string $temporaryRoot, array $budgets): void
{
    $dependencies = $temporaryRoot . '/live-configuration-dependencies';
    $lock = $temporaryRoot . '/live-configuration.lock';
    $path = $temporaryRoot . '/live-configuration.json';
    $autoloadBytes = "<?php\n// Inert configuration fixture; never loaded.\n";
    $lockBytes = "{\"packages\":[]}\n";
    if (!mkdir($dependencies, 0700)
        || file_put_contents($dependencies . '/autoload.php', $autoloadBytes, LOCK_EX) !== strlen($autoloadBytes)
        || !chmod($dependencies . '/autoload.php', 0644)
        || file_put_contents($lock, $lockBytes, LOCK_EX) !== strlen($lockBytes)
    ) {
        throw new RuntimeException('Unable to prepare the live configuration control.');
    }
    $profile = agentEvaluationControllerSyntheticProfile($budgets);
    $profile['condition'] = 'repository-only-controller-v0.2-live-fixture';
    $profile['runner'] = ['name' => 'codex-exec', 'version' => '0.153.1'];
    $profile['model'] = [
        'provider' => 'openai', 'id' => 'phpthis-fixture', 'revision' => null,
        'settings' => ['reasoning_effort' => 'high'],
    ];
    $profile['tools'] = [[
        'name' => 'shell', 'version' => null,
        'permissions' => ['workspace-read', 'workspace-write', 'process-execute'],
    ]];
    $isolation = agentEvaluationControllerLiveIsolationProfile($budgets);
    $isolation['uid'] = 65534;
    $profile['isolation'] = $isolation;
    $toolchain = [
        'php_version' => '8.4.19', 'composer_version' => '2.8.12', 'python_version' => '3.11.9',
        'codex_version' => null, 'relay_sha256' => null,
    ];
    $configuration = [
        'profile' => $profile,
        'engine' => [
            'docker_binary' => $temporaryRoot . '/absent-docker',
            'docker_socket' => $temporaryRoot . '/absent-docker.sock',
            'generation_image' => $isolation['image_reference'],
            'scoring_image' => 'registry.invalid/phpthis/scoring@sha256:' . str_repeat('b', 64),
            'generation_toolchain' => [
                ...$toolchain, 'codex_version' => '0.153.1', 'relay_sha256' => str_repeat('c', 64),
            ],
            'scoring_toolchain' => $toolchain,
        ],
        'prepared_dependencies' => $dependencies,
        'prepared_lock' => $lock,
        'prepared_dependencies_sha256' => agentEvaluationControllerDescribeTree($dependencies, 'configuration fixture', true)['sha256'],
        'prepared_lock_sha256' => hash('sha256', $lockBytes),
        'approval' => [
            'reference' => 'synthetic-configuration-test', 'model' => 'phpthis-fixture',
            'runs' => 1, 'spending_ceiling_usd' => '1.00',
        ],
    ];
    $writeConfiguration = static function () use ($path, &$configuration): void {
        $bytes = agentEvaluationJson($configuration);
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Unable to write the live configuration control.');
        }
        clearstatcache(true, $path);
    };
    $writeConfiguration();
    $accepted = agentEvaluationControllerReadLiveConfiguration($path);
    agentEvaluationControllerTest(
        $accepted['prepared_dependencies'] === $dependencies
        && $accepted['prepared_lock_sha256'] === hash('sha256', $lockBytes),
        'An exact live configuration must parse without starting OCI or executing prepared dependencies.',
    );

    foreach (['php_version' => '8.4.18', 'composer_version' => '2.8.11'] as $field => $mismatched) {
        $configuration['engine']['scoring_toolchain'][$field] = $mismatched;
        $writeConfiguration();
        agentEvaluationControllerExpectFailure(
            static function () use ($path): void {
                agentEvaluationControllerReadLiveConfiguration($path);
            },
            'Generation and scoring must use the same exact PHP and Composer versions.',
        );
        $configuration['engine']['scoring_toolchain'][$field] = $toolchain[$field];
    }

    $configuration['profile']['isolation']['uid'] = 65533;
    $writeConfiguration();
    agentEvaluationControllerExpectFailure(
        static function () use ($path): void {
            agentEvaluationControllerReadLiveConfiguration($path);
        },
        'The live generation profile must record its fixed OCI identity 65534.',
    );
    $configuration['profile']['isolation']['uid'] = 65534;
    $writeConfiguration();
    $oversized = fopen($lock, 'wb');
    if ($oversized === false) {
        throw new RuntimeException('Unable to open the oversized lock control.');
    }
    try {
        if (!ftruncate($oversized, AGENT_EVALUATION_MAX_ARTIFACT_BYTES + 1)) {
            throw new RuntimeException('Unable to size the oversized lock control.');
        }
    } finally {
        fclose($oversized);
    }
    clearstatcache(true, $lock);
    agentEvaluationControllerExpectFailure(
        static function () use ($path): void {
            agentEvaluationControllerReadLiveConfiguration($path);
        },
        'live prepared lock exceeds its bounded file size.',
    );
    if (file_put_contents($lock, $lockBytes, LOCK_EX) !== strlen($lockBytes)) {
        throw new RuntimeException('Unable to restore the bounded lock control.');
    }
    clearstatcache(true, $lock);
    $configuration['approval']['spending_ceiling_usd'] = '0.00';
    $writeConfiguration();
    $zeroSpend = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $root . '/tools/agent-evaluation-controller.php', 'run',
            '00000000000000000000000000000042', $path],
        $root,
        agentEvaluationControllerMinimalProcessEnvironment(),
        '',
        5,
        4_096,
    );
    agentEvaluationControllerTest(
        $zeroSpend['exit_code'] === 1 && $zeroSpend['termination_reason'] === 'process_failed'
        && $zeroSpend['stdout'] === ''
        && $zeroSpend['stderr'] === "FAIL agent evaluation controller: A zero-spend integration approval cannot authorize a paid run.\n",
        'The paid CLI must reject zero spending before credential validation, OCI, or evidence creation.',
    );
}

function agentEvaluationControllerTestLiveFailureEvidence(string $temporaryRoot): void
{
    $score = [
        'exit_code' => 0, 'timed_out' => false, 'output_limit_exceeded' => false,
        'oom_killed' => false, 'termination_reason' => 'completed', 'container_destroyed' => true,
    ];
    agentEvaluationControllerTest(
        !agentEvaluationControllerLiveCheckPassed($score)
        && !agentEvaluationControllerLiveCheckPassed([...$score, 'container_started' => false])
        && agentEvaluationControllerLiveCheckPassed([...$score, 'container_started' => true]),
        'A successful and cleaned scorer result must also prove that its container actually started.',
    );
    $score['container_started'] = true;
    foreach ([
        ['timed_out' => true],
        ['output_limit_exceeded' => true],
        ['oom_killed' => true],
        ['oom_killed' => null],
        ['container_started' => false],
        ['container_destroyed' => false],
        ['exit_code' => -1],
        ['exit_code' => 256],
        ['exit_code' => '0'],
        ['termination_reason' => null],
        ['termination_reason' => 'unexpected'],
        ['termination_reason' => 'cleanup_failed'],
    ] as $invalid) {
        $result = [...$score, ...$invalid];
        agentEvaluationControllerTest(
            !agentEvaluationControllerLiveCheckAdmissible($result)
            && !agentEvaluationControllerLiveCheckPassed($result),
            'Observed scorer exhaustion, unknown execution state, and infrastructure failures must make the run inadmissible.',
        );
    }
    $failedCheck = [...$score, 'exit_code' => 7, 'termination_reason' => 'process_failed'];
    agentEvaluationControllerTest(
        agentEvaluationControllerLiveCheckAdmissible($score)
        && agentEvaluationControllerLiveCheckAdmissible($failedCheck)
        && !agentEvaluationControllerLiveCheckPassed($failedCheck),
        'An actual nonzero scorer result within all execution bounds remains admissible while failing its mandatory check.',
    );
    $evidence = $temporaryRoot . '/empty-live-stream-evidence';
    $controlRoot = $temporaryRoot . '/recovery-ledger-control';
    if (!mkdir($evidence, 0700) || !mkdir($controlRoot, 0700)) {
        throw new RuntimeException('Unable to create the live failure evidence controls.');
    }
    foreach (['events.jsonl', 'generation.stderr'] as $name) {
        $path = agentEvaluationControllerWriteArtifact($evidence, $name, '');
        agentEvaluationControllerTest(
            file_get_contents($path) === '' && filesize($path) === 0
            && agentEvaluationFileHash($path, 'empty stream control') === hash('sha256', ''),
            'Empty observed streams must retain their exact zero bytes and SHA-256.',
        );
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($evidence): void {
            agentEvaluationControllerWriteArtifact($evidence, 'proxy.json', '');
        },
        'only observed streams may be empty',
    );
    $manifest = agentEvaluationControllerEvidenceManifest(
        $evidence,
        '00000000000000000000000000000042',
        ['prepare', 'generate', 'cleanup'],
        ['phase' => 'generate', 'class' => 'RuntimeException'],
        null,
        false,
    );
    $artifacts = agentEvaluationRequireObject($manifest, 'artifacts', 'empty stream manifest');
    foreach (['events.jsonl', 'generation.stderr'] as $name) {
        $descriptor = agentEvaluationRequireObject($artifacts, $name, 'empty stream descriptor');
        agentEvaluationControllerTest(
            $descriptor === ['bytes' => 0, 'sha256' => hash('sha256', '')],
            'Failed-run evidence manifests must bind zero-byte observed streams.',
        );
    }

    agentEvaluationControllerTest(
        agentEvaluationControllerReadOciRecoveryLedger($controlRoot) === null,
        'No owned resource ledger must remain distinguishable from an empty owned resource ledger.',
    );
    $ledger = ['owner' => 'phpthis-test-owner', 'run_id' => str_repeat('0', 32), 'containers' => [], 'volumes' => []];
    foreach ([
        ['containers' => [], 'volumes' => []],
        ['containers' => ['generation' => 'phpthis-test-generation'], 'volumes' => ['candidate' => 'phpthis-test-candidate']],
    ] as $resources) {
        $ledger['containers'] = $resources['containers'];
        $ledger['volumes'] = $resources['volumes'];
        $bytes = agentEvaluationJson($ledger);
        if (file_put_contents($controlRoot . '/owned-resources.json', $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Unable to write the recovery ledger control.');
        }
        clearstatcache(true, $controlRoot . '/owned-resources.json');
        agentEvaluationControllerTest(
            agentEvaluationControllerReadOciRecoveryLedger($controlRoot) === $ledger,
            'Recovery parsing must preserve both empty resource maps and the exact remaining resource names.',
        );
    }

    $command = ['id' => 'command_1', 'type' => 'command_execution', 'command' => 'sleep 30'];
    $started = ['type' => 'item.started', 'item' => $command];
    $completed = ['type' => 'item.completed', 'item' => $command];
    $proxy = agentEvaluationControllerProxyState('phpthis-fixture', 'high', 100);
    $expected = [['item_id' => 'command_1', 'sha256' => hash('sha256', 'sleep 30'), 'bytes' => 8]];
    foreach ([[$started], [$started, $completed]] as $events) {
        $inventory = agentEvaluationControllerLiveExternalActions($events, $proxy);
        agentEvaluationControllerTest(
            $inventory['approved'] && $inventory['observed_commands'] === $expected,
            'Known commands must survive interruption before completion and be deduplicated across item phases.',
        );
    }
    $completed['item']['command'] = 'changed command';
    $conflicting = agentEvaluationControllerLiveExternalActions([$started, $completed], $proxy);
    agentEvaluationControllerTest(
        !$conflicting['approved'] && $conflicting['observed_commands'] === $expected,
        'A changed command under one item ID must fail closed while preserving the original observed command.',
    );
}

function agentEvaluationControllerTestLiveUsage(): void
{
    $usage = [
        'input_tokens' => 200, 'cached_input_tokens' => 5, 'cache_write_input_tokens' => 7,
        'output_tokens' => 200, 'reasoning_output_tokens' => 11,
    ];
    $events = [
        ['type' => 'thread.started', 'thread_id' => 'synthetic-live-usage'],
        ['type' => 'turn.started'],
        ['type' => 'turn.completed', 'usage' => $usage],
    ];
    $jsonl = implode("\n", array_map(static fn (array $event): string => json_encode($event, JSON_THROW_ON_ERROR), $events)) . "\n";
    $parsed = agentEvaluationControllerParseCodexEvents($jsonl, 400, true);
    agentEvaluationControllerTest(
        $parsed['valid'] && $parsed['completed'] && $parsed['events'] === $events
        && $parsed['usage'] === ['input_tokens' => 200, 'output_tokens' => 200, 'cached_tokens' => 5, 'reasoning_tokens' => 11],
        'Pinned live usage must retain the distinct raw cache-write category without adding it to cached reads or total input.',
    );
    agentEvaluationControllerTest(
        !agentEvaluationControllerParseCodexEvents($jsonl, 400)['valid'],
        'Live runner usage extensions must preserve the existing strict synthetic event contract.',
    );
    foreach ([
        ['cache_write_input_tokens' => -1],
        ['cache_write_input_tokens' => 201],
        ['cache_write_input_tokens' => '0'],
        ['cached_input_tokens' => 201],
        ['reasoning_output_tokens' => 201],
        ['unexpected_tokens' => 0],
    ] as $invalid) {
        agentEvaluationControllerTest(
            agentEvaluationControllerParseCodexUsage([...$usage, ...$invalid], true) === null,
            'Live usage must reject malformed, excessive, or unknown token categories.',
        );
    }
    agentEvaluationControllerTest(
        agentEvaluationControllerParseCodexUsage(['input_tokens' => 200, 'output_tokens' => 200], true)
            === ['input_tokens' => 200, 'output_tokens' => 200, 'cached_tokens' => null, 'reasoning_tokens' => null],
        'Missing live usage details must remain unknown.',
    );
}

function agentEvaluationControllerTestUnsettledCleanup(string $temporaryRoot): void
{
    $controlRoot = $temporaryRoot . '/pending-creation-control';
    $binary = $controlRoot . '/engine-sentinel';
    $sentinel = "#!/bin/sh\n: > engine-invoked\nexit 99\n";
    if (!mkdir($controlRoot, 0700)
        || file_put_contents($binary, $sentinel, LOCK_EX) !== strlen($sentinel)
        || !chmod($binary, 0700)
    ) {
        throw new RuntimeException('Unable to prepare the unsettled-creation cleanup control.');
    }
    $containers = ['pending-creation-generation' => 'phpthis-test-pending-generation'];
    $volumes = ['dependencies' => 'phpthis-test-dependencies'];
    $resources = [
        'engine' => [
            'binary' => $binary, 'socket' => $controlRoot . '/absent.sock',
            'config_root' => $controlRoot, 'control_root' => $controlRoot,
            'configuration' => ['synthetic' => true],
        ],
        'owner' => 'phpthis-test-owner', 'run_id' => str_repeat('0', 32),
        'containers' => $containers, 'volumes' => $volumes,
        'generation' => null, 'generation_stopped' => false,
        'generation_destroyed' => false, 'frozen' => false,
        'candidate_target' => $controlRoot . '/candidate',
    ];
    agentEvaluationControllerOciWriteLedger($resources);
    $before = agentEvaluationFileHash($controlRoot . '/owned-resources.json', 'pending creation ledger');
    $cleanup = agentEvaluationControllerOciCleanup($resources);
    agentEvaluationControllerTest(
        !$cleanup['verified'] && $cleanup['status'] === 'fail'
        && $cleanup['containers_remaining'] === 1 && $cleanup['volumes_remaining'] === 1
        && $resources['containers'] === $containers && $resources['volumes'] === $volumes
        && !file_exists($controlRoot . '/engine-invoked')
        && agentEvaluationFileHash($controlRoot . '/owned-resources.json', 'pending creation ledger') === $before,
        'An uncertain container creation must retain all dependency volumes and recovery names without invoking the engine.',
    );
}

function agentEvaluationControllerTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param Closure(): void $callback */
function agentEvaluationControllerExpectFailure(Closure $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        agentEvaluationControllerTest(
            $throwable->getMessage() === $message,
            'Unexpected controller failure: ' . $throwable->getMessage(),
        );

        return;
    }

    throw new RuntimeException('Expected controller failure was not reported.');
}

/** @param Closure(): void $callback */
function agentEvaluationControllerExpectFailureContains(Closure $callback, string $marker): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        agentEvaluationControllerTest(
            str_contains($throwable->getMessage(), $marker),
            'Unexpected controller failure: ' . $throwable->getMessage(),
        );

        return;
    }

    throw new RuntimeException('Expected controller failure was not reported.');
}

function agentEvaluationControllerTestRemoveTemporaryRoot(string $temporaryRoot): void
{
    $temporaryBase = realpath(sys_get_temp_dir());

    if ($temporaryBase === false) {
        throw new RuntimeException('Controller self-test temporary base is unavailable.');
    }

    $prefix = $temporaryBase . '/phpthis-agent-evaluation-controller-test-';

    if (!str_starts_with($temporaryRoot, $prefix)) {
        throw new RuntimeException('Controller self-test cleanup target is outside its fixed prefix.');
    }

    if (is_dir($temporaryRoot) || is_link($temporaryRoot)) {
        agentEvaluationControllerRemoveTree($temporaryRoot);
    }
}

function agentEvaluationControllerTestComparisonReport(string $root, string $temporaryRoot): void
{
    $parent = $temporaryRoot . '/report-control';
    $codebase = $parent . '/codebase';
    $campaignId = str_repeat('e', 32);
    $campaignRoot = $parent . '/agent-evaluation-runs/' . $campaignId;
    foreach ([$parent, $codebase, $parent . '/agent-evaluation-runs', $campaignRoot, $campaignRoot . '/attempts', $campaignRoot . '/runs'] as $directory) {
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Unable to prepare the synthetic comparison-report tree.');
        }
    }
    if (!symlink($root . '/tools', $codebase . '/tools')) {
        throw new RuntimeException('Unable to reference the public kit for the synthetic report control.');
    }
    $kit = $root . '/tools/agent-evaluation';
    $configuration = ['campaign_id' => $campaignId, 'protocol_sha256' => AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256,
        'source_revision' => str_repeat('a', 40), 'implementation_sha256' => agentEvaluationControllerComparisonCodeHash($root),
        'model_revision_note' => 'Synthetic report validator only; no provider or human trial.',
        'pricing' => ['input_cents_per_million' => 100, 'cached_cents_per_million' => 10, 'output_cents_per_million' => 400]];
    $configurationBytes = agentEvaluationJson($configuration);
    $inputs = [];
    foreach (agentEvaluationComparisonTasks($kit) as $task) {
        $holdout = ['schema_version' => 1, 'id' => $task['id'] . '.holdout', 'revision' => 1, 'task_id' => $task['id'],
            'cases' => [['id' => 'synthetic.failure-control', 'input' => new stdClass(), 'expect' => [
                'response' => ['status' => 200, 'headers' => new stdClass(), 'body' => new stdClass()],
                'policy_steps' => [], 'query' => ['min_statements' => 0, 'max_statements' => 0, 'failures' => 0, 'max_fingerprint_executions' => 0],
                'durable_state' => new stdClass(), 'in_transaction' => false]]], 'scaling_groups' => []];
        $holdoutBytes = agentEvaluationJson($holdout);
        $holdoutPath = agentEvaluationControllerWriteArtifact($parent, $task['id'] . '.synthetic.json', $holdoutBytes);
        $syntheticTask = [...$task, 'checks' => ['application_check' => 'composer check',
            'holdout' => ['id' => $holdout['id'], 'revision' => 1, 'sha256' => hash('sha256', $holdoutBytes)]]];
        foreach ($task['conditions'] as $condition) {
            $inputs[$task['id'] . ':' . $condition['id']] = ['task' => $syntheticTask, 'condition' => $condition,
                'execution' => ['profile' => ['synthetic_report_control' => true],
                    'prepared_dependencies_sha256' => str_repeat('c', 64), 'prepared_lock_sha256' => str_repeat('d', 64)],
                'holdout' => $holdout, 'holdout_path' => $holdoutPath,
                'installed_context' => ['scope' => 'installed-dependency-documentation', 'files' => [], 'bytes' => 0, 'words' => 0]];
        }
    }
    $schedule = agentEvaluationComparisonSchedule($kit);
    $attempts = [];
    foreach ($schedule as $slot) {
        $attempt = agentEvaluationControllerPlannedComparisonAttempt($configuration, $slot, $inputs[$slot['task_id'] . ':' . $slot['condition']]);
        $attempts[] = $attempt;
        agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', sprintf('%02d.planned.json', $slot['slot']), agentEvaluationJson($attempt));
    }
    $campaign = ['configuration' => $configuration, 'bytes' => $configurationBytes, 'sha256' => hash('sha256', $configurationBytes),
        'schedule' => $schedule, 'inputs' => $inputs, 'attempts' => $attempts];
    agentEvaluationControllerWriteArtifact($campaignRoot, 'configuration.json', $configurationBytes);
    agentEvaluationControllerWriteArtifact($campaignRoot, 'schedule.json', agentEvaluationJson($schedule));
    agentEvaluationControllerRetainComparisonImplementation($root, $campaignRoot, $configuration['implementation_sha256']);
    $unstarted = agentEvaluationControllerComparisonReport($codebase, $campaign);
    $summary = agentEvaluationRequireObject($unstarted, 'summary', 'synthetic report');
    agentEvaluationControllerTest($summary['actual_outcomes_with_scores'] === 0 && $summary['correctness_rates_available'] === false
        && count(agentEvaluationRequireList($summary, 'missing_or_unfinished_slots', 'synthetic report')) === 60,
        'A retained sixty-slot plan must produce missing counts without inventing actual outcomes.');
    $snapshotEntrypoint = $campaignRoot . '/implementation/tools/agent-evaluation.php';
    $snapshotBytes = file_get_contents($snapshotEntrypoint);
    if (!is_string($snapshotBytes)) {
        throw new RuntimeException('Unable to read the retained synthetic implementation control.');
    }
    try {
        if (file_put_contents($snapshotEntrypoint, "\n// synthetic changed snapshot\n", FILE_APPEND) === false) {
            throw new RuntimeException('Unable to modify the disposable implementation snapshot control.');
        }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($codebase, $campaign): void { agentEvaluationControllerComparisonReport($codebase, $campaign); },
            'Comparison reporting requires the exact retained implementation snapshot.',
        );
    } finally {
        if (file_put_contents($snapshotEntrypoint, $snapshotBytes) !== strlen($snapshotBytes)) {
            throw new RuntimeException('Unable to restore the exact retained synthetic implementation.');
        }
    }
    foreach ($attempts as $planned) {
        $slot = agentEvaluationRequireInteger($planned, 'slot', 'synthetic report');
        $prefix = sprintf('%02d', $slot);
        $started = [...$planned, 'status' => 'running', 'phase' => 'prepare'];
        agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', $prefix . '.started.json', agentEvaluationJson($started));
        $failed = [...$started, 'status' => 'failed', 'termination_reason' => 'synthetic_preparation_failed'];
        $failedPath = agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', $prefix . '.json', agentEvaluationJson($failed));
        $input = $inputs[agentEvaluationRequireString($planned, 'task_id', 'synthetic report') . ':'
            . agentEvaluationRequireString($planned, 'condition', 'synthetic report')];
        $score = agentEvaluationControllerComparisonScoreFromEvidence($failed, agentEvaluationFileHash($failedPath, 'synthetic attempt'),
            agentEvaluationRequireObject($input, 'holdout', 'synthetic report'),
            $campaignRoot . '/runs/' . agentEvaluationRequireString($planned, 'run_id', 'synthetic report') . '/evidence');
        $review = agentEvaluationRequireObject($score, 'human_review', 'synthetic report');
        $score['human_review'] = [...$review, 'status' => 'fail', 'reviewer' => 'synthetic-report-validator',
            'semantic_correctness' => false, 'instrumentation_integrity' => true,
            'reason' => 'Synthetic fixture review state only; no accountable human trial occurred.'];
        $score['correct_completion'] = false;
        agentEvaluationControllerWriteArtifact($campaignRoot . '/attempts', $prefix . '.score.json', agentEvaluationJson($score));
    }
    $reported = agentEvaluationControllerComparisonReport($codebase, $campaign);
    $summary = agentEvaluationRequireObject($reported, 'summary', 'synthetic report');
    agentEvaluationControllerTest($summary['actual_outcomes_with_scores'] === 60 && $summary['correctness_rates_available'] === true
        && count(agentEvaluationRequireList($reported, 'attempts', 'synthetic report')) === 60,
        'All sixty retained and reviewed failed outcomes must remain visible and count in the fixed report.');
    foreach (agentEvaluationRequireList($summary, 'groups', 'synthetic report') as $value) {
        $group = agentEvaluationValueObject($value, 'synthetic report group');
        $correct = agentEvaluationRequireObject($group, 'correct_completion', 'synthetic report group');
        $states = agentEvaluationRequireObject($group, 'status_counts', 'synthetic report group');
        agentEvaluationControllerTest($correct['rate'] === 0 && $states['failed'] === 10,
            'A complete group of failed trials must report zero of ten, never disappear from the denominator.');
    }
    $firstFinal = $campaignRoot . '/attempts/01.json';
    $firstScore = $campaignRoot . '/attempts/01.score.json';
    $finalBytes = file_get_contents($firstFinal);
    $scoreBytes = file_get_contents($firstScore);
    $plannedPath = $campaignRoot . '/attempts/60.planned.json';
    $plannedBytes = file_get_contents($plannedPath);
    if (!is_string($finalBytes) || !is_string($scoreBytes) || !is_string($plannedBytes)) {
        throw new RuntimeException('Unable to retain synthetic report mutation controls.');
    }
    try {
        $mutated = agentEvaluationJsonFile($firstFinal);
        $mutated['profile_sha256'] = str_repeat('f', 64);
        if (file_put_contents($firstFinal, agentEvaluationJson($mutated)) === false) {
            throw new RuntimeException('Unable to write the synthetic report identity control.');
        }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($codebase, $campaign): void { agentEvaluationControllerComparisonReport($codebase, $campaign); },
            'Comparison retained attempt changed one frozen planned identity or setting.',
        );
        if (file_put_contents($firstFinal, $finalBytes) !== strlen($finalBytes)) {
            throw new RuntimeException('Unable to restore the synthetic report attempt.');
        }
        $forged = agentEvaluationJsonFile($firstScore);
        $forged['automated_status'] = 'pass';
        if (file_put_contents($firstScore, agentEvaluationJson($forged)) === false) {
            throw new RuntimeException('Unable to write the synthetic report score control.');
        }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($codebase, $campaign): void { agentEvaluationControllerComparisonReport($codebase, $campaign); },
            'Comparison report refuses automated scores that differ from the retained raw evidence.',
        );
        $unbound = agentEvaluationJsonFile($firstFinal);
        $usage = agentEvaluationRequireObject($unbound, 'usage', 'synthetic unbound usage');
        $unknown = agentEvaluationRequireObject($unbound, 'unknown_metrics', 'synthetic unbound usage');
        unset($unknown['input_tokens']);
        $unbound['usage'] = [...$usage, 'input_tokens' => 1];
        $unbound['unknown_metrics'] = $unknown;
        if (!unlink($firstScore) || file_put_contents($firstFinal, agentEvaluationJson($unbound)) === false) {
            throw new RuntimeException('Unable to prepare the synthetic incomplete-measurement control.');
        }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($codebase, $campaign): void { agentEvaluationControllerComparisonReport($codebase, $campaign); },
            'Comparison token measurements must match the retained provider ledger or remain unknown.',
        );
        if (file_put_contents($firstFinal, $finalBytes) !== strlen($finalBytes)
            || file_put_contents($firstScore, $scoreBytes) !== strlen($scoreBytes) || !unlink($plannedPath)
        ) {
            throw new RuntimeException('Unable to prepare the synthetic missing-plan control.');
        }
        agentEvaluationControllerExpectFailureContains(
            static function () use ($codebase, $campaign): void { agentEvaluationControllerComparisonReport($codebase, $campaign); },
            'Comparison reporting cannot omit a planned attempt.',
        );
    } finally {
        if (file_put_contents($firstFinal, $finalBytes) !== strlen($finalBytes)
            || file_put_contents($firstScore, $scoreBytes) !== strlen($scoreBytes)
            || file_put_contents($plannedPath, $plannedBytes) !== strlen($plannedBytes)
        ) {
            throw new RuntimeException('Unable to restore one synthetic comparison report fixture.');
        }
    }
    agentEvaluationControllerTestCalibrationReport($root, $parent, $inputs, 1);
    agentEvaluationControllerTestCalibrationReport($root, $parent, $inputs, 2);
}


/** @param array<string,mixed> $inputs */
function agentEvaluationControllerTestCalibrationReport(string $root, string $parent, array $inputs, int $revision): void
{
    $policy = agentEvaluationControllerCalibrationPolicy($revision);
    agentEvaluationControllerValidateCalibrationPolicy($policy);
    foreach (['claims', 'tokens', 'money', 'price', 'prompt', 'revision', 'input-limit'] as $mode) {
        $invalid = $policy;
        if ($mode === 'revision') { $invalid['kind'] = 'calibration-v' . ($revision === 1 ? 2 : 1); }
        if ($mode === 'input-limit') { $invalid['request_input_tokens'] = 200_001; }
        if ($mode === 'claims') { $invalid['comparative_claims'] = true; }
        if ($mode === 'tokens') { $invalid['budgets'] = [...agentEvaluationControllerCalibrationBudgets($revision), 'model_tokens' => agentEvaluationControllerCalibrationBudgets($revision)['model_tokens'] + 1]; }
        if ($mode === 'money') { $invalid['spending'] = [...agentEvaluationControllerCalibrationSpending(), 'limit_units' => 100_000_001]; }
        if ($mode === 'price') { $invalid['spending'] = [...agentEvaluationControllerCalibrationSpending(), 'input_cents_per_million' => 249]; }
        if ($mode === 'prompt') { $invalid['prompt_sha256'] = str_repeat('a', 64); }
        $accepted = true;
        try { agentEvaluationControllerValidateCalibrationPolicy($invalid); } catch (RuntimeException) { $accepted = false; }
        agentEvaluationControllerTest(!$accepted, 'Calibration must reject changes to its fixed policy: ' . $mode);
    }
    $kit = $root . '/tools/agent-evaluation';
    $task = agentEvaluationComparisonTasks($kit)[0];
    $selected = agentEvaluationControllerAdmitComparisonTask($task, $task['conditions'][0]);
    $budgets = agentEvaluationControllerCalibrationBudgets($revision);
    $profile = ['condition' => $selected['selected_condition'],
        'runner' => ['name' => AGENT_EVALUATION_CONTROLLER_LIVE_RUNNER, 'version' => '0.153.1'],
        'model' => ['provider' => 'openai', 'id' => 'gpt-5.4-2026-03-05', 'revision' => null, 'settings' => ['reasoning_effort' => 'high']],
        'context' => ['bundle_id' => null, 'bundle_sha256' => null],
        'tools' => [['name' => 'shell', 'version' => null, 'permissions' => ['workspace-read', 'workspace-write', 'process-execute']]],
        'budgets' => $budgets, 'isolation' => agentEvaluationControllerLiveIsolationProfile($budgets)];
    agentEvaluationControllerValidateProfile($profile, $selected, false, true, $revision);
    $ordinaryAccepted = true;
    try { agentEvaluationControllerValidateProfile($profile, $selected, false); } catch (RuntimeException) { $ordinaryAccepted = false; }
    $sourceBudgets = agentEvaluationRequireObject($selected, 'budgets', 'synthetic calibration source');
    agentEvaluationControllerTest(!$ordinaryAccepted && $sourceBudgets['model_tokens'] === 40_000,
        'A calibration budget must require an explicit mode and leave the admitted source task budget unchanged.');

    $moneyEvidence = $parent . '/calibration-money-control-v' . $revision;
    if (!mkdir($moneyEvidence, 0700)) { throw new RuntimeException('Unable to prepare calibration monetary evidence.'); }
    $sourcePrompt = file_get_contents($task['directory'] . '/' . $task['prompt']['path']);
    $sourceTask = file_get_contents($task['directory'] . '/task.json');
    if (!is_string($sourcePrompt) || !is_string($sourceTask)) { throw new RuntimeException('Calibration control source is unavailable.'); }
    $workspacePolicy = agentEvaluationControllerWorkspacePolicy($selected);
    foreach (['source-prompt.md' => $sourcePrompt, 'prompt.md' => agentEvaluationControllerGenerationPrompt($sourcePrompt, $workspacePolicy, $revision),
        'workspace-policy.json' => agentEvaluationJson(agentEvaluationControllerWorkspacePolicyEvidence($workspacePolicy)),
        'task.json' => $sourceTask, 'calibration.json' => agentEvaluationJson($policy), 'profile.json' => agentEvaluationJson($profile)] as $name => $data) {
        agentEvaluationControllerWriteArtifact($moneyEvidence, $name, $data);
    }
    $usage = ['input_tokens' => 100, 'output_tokens' => 20, 'cached_tokens' => 40, 'reasoning_tokens' => 5];
    $baseLedger = [...agentEvaluationControllerProxyState('gpt-5.4-2026-03-05', 'high', $budgets['model_tokens'], agentEvaluationControllerCalibrationSpending()),
        ...$usage, 'request_count' => 1, 'observed_request_count' => 1,
        'spending' => ['policy' => agentEvaluationControllerCalibrationSpending(), 'settled_units' => 46_000, 'reserved_units' => 0]];
    $attempt = ['kind' => 'calibration-attempt-v' . $revision, 'status' => 'failed', 'phase' => 'generate', 'termination_reason' => 'process_failed', 'usage' => $usage,
        'elapsed_milliseconds' => null, 'condition' => $profile['condition'], 'profile_sha256' => hash('sha256', agentEvaluationJson($profile)),
        'task_manifest_sha256' => $task['manifest_sha256'], 'calibration_sha256' => hash('sha256', agentEvaluationJson($policy))];
    foreach (['valid', 'wrong-model', 'wrong-effort', 'wrong-budget', 'wrong-price', 'wrong-cap', 'negative', 'overcap', 'wrong-total', 'wrong-reserve'] as $mode) {
        $ledger = $baseLedger;
        $money = ['policy' => agentEvaluationControllerCalibrationSpending(), 'settled_units' => 46_000, 'reserved_units' => 0];
        if ($mode === 'wrong-model') { $ledger['model'] = 'gpt-5.2-codex'; }
        if ($mode === 'wrong-effort') { $ledger['reasoning_effort'] = 'medium'; }
        if ($mode === 'wrong-budget') { $ledger['token_budget'] = 40_000; }
        if ($mode === 'wrong-price') { $money['policy']['input_cents_per_million'] = 249; }
        if ($mode === 'wrong-cap') { $money['policy']['limit_units'] = 99_999_999; }
        if ($mode === 'negative') { $money['settled_units'] = -1; }
        if ($mode === 'overcap') { $money['settled_units'] = 100_000_001; }
        if ($mode === 'wrong-total') { $money['settled_units'] = 46_225; }
        if ($mode === 'wrong-reserve') { $money['reserved_units'] = 1500; }
        $ledger['spending'] = $money;
        $path = $moneyEvidence . '/proxy.json';
        $bytes = agentEvaluationJson(['ledger' => $ledger, 'synthetic_upstream' => false, 'upstream_origin' => 'https://api.openai.com']);
        if (file_put_contents($path, $bytes) !== strlen($bytes)) { throw new RuntimeException('Unable to write calibration money control.'); }
        $attempt['artifacts'] = agentEvaluationControllerComparisonArtifacts($moneyEvidence);
        $accepted = true;
        try { agentEvaluationControllerValidateCalibrationEvidence($attempt, $moneyEvidence); } catch (RuntimeException) { $accepted = false; }
        agentEvaluationControllerTest($accepted === ($mode === 'valid'), 'Calibration report evidence must validate exact monetary totals and policy: ' . $mode);
    }

    $id = str_repeat($revision === 1 ? 'f' : 'd', 32);
    $directory = $parent . '/agent-evaluation-calibrations/' . $id;
    foreach ([$parent . '/agent-evaluation-calibrations', $directory, $directory . '/attempts', $directory . '/runs'] as $path) {
        if (!is_dir($path) && !mkdir($path, 0700)) { throw new RuntimeException('Unable to create the synthetic calibration report tree.'); }
    }
    $configuration = ['campaign_id' => $id, 'protocol_sha256' => AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256,
        'source_revision' => str_repeat('a', 40), 'implementation_sha256' => agentEvaluationControllerComparisonCodeHash($root),
        'model_revision_note' => 'Synthetic calibration report control; no provider call.',
        'pricing' => ['input_cents_per_million' => 250, 'cached_cents_per_million' => 25, 'output_cents_per_million' => 1500],
        'calibration' => $policy];
    $bytes = agentEvaluationJson($configuration);
    $schedule = array_slice(agentEvaluationComparisonSchedule($kit), 0, 6);
    $attempts = [];
    foreach ($schedule as $slot) {
        $input = agentEvaluationValueObject($inputs[$slot['task_id'] . ':' . $slot['condition']], 'synthetic calibration input');
        $planned = agentEvaluationControllerPlannedCalibrationAttempt($configuration, $slot, $input);
        agentEvaluationControllerTest($planned['kind'] === 'calibration-attempt-v' . $revision && $planned['comparative_claims'] === false
            && !isset($planned['protocol_sha256']) && $planned['source_protocol_sha256'] === AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256,
            'Calibration attempts must retain a separate identity from v2 comparison evidence.');
        $attempts[] = $planned;
        agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.planned.json', $slot['slot']), agentEvaluationJson($planned));
    }
    $campaign = ['configuration' => $configuration, 'bytes' => $bytes, 'sha256' => hash('sha256', $bytes),
        'schedule' => $schedule, 'inputs' => $inputs, 'attempts' => $attempts];
    agentEvaluationControllerWriteArtifact($directory, 'configuration.json', $bytes);
    agentEvaluationControllerWriteArtifact($directory, 'schedule.json', agentEvaluationJson($schedule));
    agentEvaluationControllerRetainComparisonImplementation($root, $directory, $configuration['implementation_sha256']);
    $codebase = $parent . '/codebase';
    $unstarted = agentEvaluationControllerCalibrationReport($codebase, $campaign);
    $rows = agentEvaluationRequireList($unstarted, 'rows', 'synthetic calibration report');
    agentEvaluationControllerTest(count($rows) === 6 && $unstarted['correctness_rates_available'] === false,
        'A fresh calibration must report all six unfinished slots without a correctness rate.');
    $firstPlanned = $attempts[0];
    $firstStarted = [...$firstPlanned, 'status' => 'running', 'phase' => 'prepare'];
    $firstStartedPath = $directory . '/attempts/01.started.json';
    $firstRun = $directory . '/runs/' . agentEvaluationRequireString($firstPlanned, 'run_id', 'interrupted calibration control');
    if (!mkdir($firstRun, 0700) || !mkdir($firstRun . '/evidence', 0700)) {
        throw new RuntimeException('Unable to create the interrupted calibration preparation control.');
    }
    agentEvaluationControllerWriteArtifact($firstRun . '/evidence', 'preparation-note.json', '{"stage":"prepare"}');
    agentEvaluationControllerWriteArtifact($directory . '/attempts', '01.started.json', agentEvaluationJson($firstStarted));
    $interrupted = agentEvaluationControllerCalibrationReport($codebase, $campaign);
    $interruptedRows = agentEvaluationRequireList($interrupted, 'rows', 'interrupted calibration report');
    $interruptedFirst = agentEvaluationValueObject($interruptedRows[0], 'interrupted calibration row');
    agentEvaluationControllerTest($interruptedFirst['status'] === 'unfinished' && $interruptedFirst['score'] === null,
        'A valid started attempt may retain partial preparation evidence before any final attempt exists.');
    if (file_put_contents($firstStartedPath, '{"wrong_identity":true}') === false) {
        throw new RuntimeException('Unable to write the malformed calibration start control.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'calibration started identity SHA-256 does not match its recorded hash.',
    );
    if (file_put_contents($firstStartedPath, agentEvaluationJson($firstStarted)) === false) {
        throw new RuntimeException('Unable to restore the calibration start control.');
    }
    agentEvaluationControllerWriteArtifact($directory . '/attempts', '01.score.json', '{"automated_status":"pass"}');
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration score requires its retained final attempt.',
    );
    if (!unlink($directory . '/attempts/01.score.json') || !unlink($firstStartedPath)) {
        throw new RuntimeException('Unable to remove the interrupted calibration ledger controls.');
    }
    agentEvaluationControllerRemoveTree($firstRun);
    foreach ($attempts as $planned) {
        $slot = agentEvaluationRequireInteger($planned, 'slot', 'synthetic calibration attempt');
        $prefix = $directory . '/attempts/' . sprintf('%02d', $slot);
        if ($slot === 1) {
            $started = [...$planned, 'status' => 'running', 'phase' => 'prepare'];
            agentEvaluationControllerWriteArtifact($directory . '/attempts', '01.started.json', agentEvaluationJson($started));
            $final = [...$started, 'status' => 'failed', 'termination_reason' => 'calibration_controller_failed'];
        } else {
            $final = [...$planned, 'status' => 'not_run', 'termination_reason' => 'calibration_aborted'];
        }
        agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.json', $slot), agentEvaluationJson($final));
        $input = agentEvaluationValueObject($inputs[agentEvaluationRequireString($planned, 'task_id', 'synthetic calibration') . ':'
            . agentEvaluationRequireString($planned, 'condition', 'synthetic calibration')], 'synthetic calibration input');
        $score = agentEvaluationControllerCalibrationScore($final, agentEvaluationFileHash($prefix . '.json', 'synthetic calibration'),
            agentEvaluationRequireObject($input, 'holdout', 'synthetic calibration'),
            $directory . '/runs/' . agentEvaluationRequireString($planned, 'run_id', 'synthetic calibration') . '/evidence');
        agentEvaluationControllerWriteArtifact($directory . '/attempts', sprintf('%02d.score.json', $slot), agentEvaluationJson($score));
    }
    $report = agentEvaluationControllerCalibrationReport($codebase, $campaign);
    $rows = agentEvaluationRequireList($report, 'rows', 'synthetic calibration report');
    agentEvaluationControllerTest(count($rows) === 6 && $report['comparative_claims'] === false
        && $report['correctness_rates_available'] === false, 'An aborted calibration must retain every slot without comparative claims.');
    $finalPath = $directory . '/attempts/01.json';
    $scorePath = $directory . '/attempts/01.score.json';
    $original = agentEvaluationJsonFile($finalPath);
    $originalScore = agentEvaluationJsonFile($scorePath);
    foreach (['identity', 'unknown', 'phase', 'false-complete', 'score'] as $mode) {
        $changed = $original;
        $changedScore = $originalScore;
        if ($mode === 'identity') { $changed['calibration_sha256'] = str_repeat('a', 64); }
        if ($mode === 'unknown') { $changed['unknown_metrics'] = new stdClass(); }
        if ($mode === 'phase') { $changed['phase'] = 'planned'; }
        if ($mode === 'false-complete') { $changed['status'] = 'complete'; }
        if ($mode === 'score') { $changedScore['automated_status'] = 'pass'; }
        if (file_put_contents($finalPath, agentEvaluationJson($changed)) === false
            || file_put_contents($scorePath, agentEvaluationJson($changedScore)) === false) {
            throw new RuntimeException('Unable to write synthetic calibration mutation.');
        }
        if ($mode !== 'score') {
            $stateAccepted = true;
            try { agentEvaluationControllerValidateCalibrationAttemptState($changed); } catch (RuntimeException) { $stateAccepted = false; }
            agentEvaluationControllerTest(!$stateAccepted, 'Calibration terminal state validation must reject this mutation before score replay: ' . $mode);
        }
        $accepted = true;
        try { agentEvaluationControllerCalibrationReport($codebase, $campaign); } catch (RuntimeException) { $accepted = false; }
        agentEvaluationControllerTest(!$accepted, 'Calibration reporting must reject forged final evidence: ' . $mode);
    }
    if (file_put_contents($finalPath, agentEvaluationJson($original)) === false || !unlink($scorePath)) {
        throw new RuntimeException('Unable to prepare the interrupted calibration score control.');
    }
    $partial = agentEvaluationControllerCalibrationReport($codebase, $campaign);
    $partialRows = agentEvaluationRequireList($partial, 'rows', 'partial calibration report');
    $first = agentEvaluationValueObject($partialRows[0], 'partial calibration row');
    agentEvaluationControllerTest($first['status'] === 'failed' && $first['score'] === null,
        'An interrupted score write must leave the final failure and unknown exposure reportable without a pass claim.');

    agentEvaluationControllerWriteArtifact($directory . '/attempts', '07.started.json', '{}');
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration attempt ledger contains an unplanned entry.',
    );
    if (!unlink($directory . '/attempts/07.started.json')) { throw new RuntimeException('Unable to remove the extra calibration ledger entry.'); }
    $lastPlanPath = $directory . '/attempts/06.planned.json';
    $lastPlanBytes = agentEvaluationJson($attempts[5]);
    if (!unlink($lastPlanPath)) { throw new RuntimeException('Unable to remove the calibration planned-record control.'); }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'calibration planned identity must resolve to one regular repository file.',
    );
    agentEvaluationControllerWriteArtifact($directory . '/attempts', '06.planned.json', $lastPlanBytes);
    $planAlias = $directory . '/duplicate-plan-control.json';
    if (!link($lastPlanPath, $planAlias)) { throw new RuntimeException('Unable to create the calibration hard-link control.'); }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Retained artifact must have one filesystem identity.',
    );
    if (!unlink($planAlias) || !symlink($directory . '/attempts/02.score.json', $scorePath)) {
        throw new RuntimeException('Unable to prepare the calibration score symlink control.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'retained artifact must not be a symlink.',
    );
    if (!unlink($scorePath) || !chmod($lastPlanPath, 0644)) {
        throw new RuntimeException('Unable to prepare the calibration retained-file mode control.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Retained artifact must use private mode 0600.',
    );
    if (!chmod($lastPlanPath, 0600) || !unlink($firstStartedPath)) {
        throw new RuntimeException('Unable to prepare the calibration missing-start control.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration final execution requires its retained started attempt.',
    );
    agentEvaluationControllerWriteArtifact($directory . '/attempts', '01.started.json', agentEvaluationJson($firstStarted));
    $secondPlanned = $attempts[1];
    $secondRun = $directory . '/runs/' . agentEvaluationRequireString($secondPlanned, 'run_id', 'unrun calibration control');
    if (!mkdir($secondRun, 0700)) { throw new RuntimeException('Unable to prepare the unstarted calibration run control.'); }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'An unstarted calibration slot has an unexpected execution directory.',
    );
    agentEvaluationControllerRemoveTree($secondRun);
    agentEvaluationControllerWriteArtifact($directory . '/attempts', '02.started.json',
        agentEvaluationJson([...$secondPlanned, 'status' => 'running', 'phase' => 'prepare']));
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Unrun calibration must have no generation or artifacts.',
    );
    if (!unlink($directory . '/attempts/02.started.json')) { throw new RuntimeException('Unable to remove the unrun calibration start control.'); }
    $orphanRun = $directory . '/runs/unplanned-run';
    if (!mkdir($orphanRun, 0700)) { throw new RuntimeException('Unable to prepare the unplanned calibration run control.'); }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration evidence contains an execution without its planned started attempt.',
    );
    agentEvaluationControllerRemoveTree($orphanRun);

    if (!mkdir($firstRun, 0700) || !mkdir($firstRun . '/evidence', 0700)) {
        throw new RuntimeException('Unable to prepare the calibration artifact membership controls.');
    }
    $notePath = agentEvaluationControllerWriteArtifact($firstRun . '/evidence', 'preparation-note.json', '{"stage":"prepare"}');
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration report artifact inventory must equal every retained evidence file.',
    );
    $withPrefix = [...$original, 'artifacts' => agentEvaluationControllerComparisonArtifacts($firstRun . '/evidence')];
    if (file_put_contents($finalPath, agentEvaluationJson($withPrefix)) === false) {
        throw new RuntimeException('Unable to retain the exact failed-preparation artifact prefix.');
    }
    $prefixReport = agentEvaluationControllerCalibrationReport($codebase, $campaign);
    $prefixRows = agentEvaluationRequireList($prefixReport, 'rows', 'calibration prefix report');
    $prefixFirst = agentEvaluationValueObject($prefixRows[0], 'calibration prefix row');
    agentEvaluationControllerTest($prefixFirst['status'] === 'failed' && $prefixFirst['spending'] === null && $prefixFirst['score'] === null,
        'A failed preparation may retain its exact artifact prefix without inventing a ledger or score.');
    if (file_put_contents($notePath, '{"stage":"changed"}') === false) {
        throw new RuntimeException('Unable to mutate the same-length calibration artifact control.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration report artifact inventory must equal every retained evidence file.',
    );
    if (!unlink($notePath)) { throw new RuntimeException('Unable to remove the named calibration artifact control.'); }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration report artifact inventory must equal every retained evidence file.',
    );
    agentEvaluationControllerRemoveTree($firstRun);
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'Calibration attempt names evidence absent from its retained run directory.',
    );
    if (file_put_contents($finalPath, agentEvaluationJson($original)) === false
        || !rename($directory . '/runs', $directory . '/runs-control')
        || !symlink($directory . '/runs-control', $directory . '/runs')) {
        throw new RuntimeException('Unable to prepare the calibration run-root symlink control.');
    }
    agentEvaluationControllerExpectFailureContains(
        static function () use ($codebase, $campaign): void { agentEvaluationControllerCalibrationReport($codebase, $campaign); },
        'calibration report run root must be one absolute non-symlink directory.',
    );
    if (!unlink($directory . '/runs') || !rename($directory . '/runs-control', $directory . '/runs')) {
        throw new RuntimeException('Unable to restore the canonical calibration run root.');
    }
}


function agentEvaluationControllerTestProxyCalibrationV2(): void
{
    $model = 'gpt-5.4-2026-03-05';
    $policy = agentEvaluationControllerCalibrationSpending();
    agentEvaluationControllerTest(hash('sha256', agentEvaluationJson(agentEvaluationControllerCalibrationPolicy()))
        === 'a4000e155de81560e329851e0f0f253cc77930fbf9fa291d6ea0bbc3dd05be80',
        'The original v1 policy and its effective prompt identity must remain byte-identical to the frozen calibration.');
    agentEvaluationControllerTest(in_array('model_auto_compact_token_limit=1000001',
        agentEvaluationControllerLiveCodexArguments($model, 'high', $policy, 1_000_000), true),
        'Only the explicitly admitted v2 allowance selects the larger compaction threshold.');
    foreach ([[1_000_000, null], [200_001, $policy], [1_000_001, $policy]] as [$budget, $spending]) {
        $accepted = true;
        try { agentEvaluationControllerLiveCodexArguments($model, 'high', $spending, $budget); } catch (RuntimeException) { $accepted = false; }
        agentEvaluationControllerTest(!$accepted, 'Unsupported or unpriced calibration argv budgets must reject.');
    }
    foreach ([[200_000, 16, 16, 50_024_000], [200_000, PHP_INT_MAX, 33_333, 99_999_500],
        [0, PHP_INT_MAX, 66_666, 99_999_000]] as [$input, $output, $expectedOutput, $expectedUnits]) {
        $state = agentEvaluationControllerProxyState($model, 'high', 1_000_000, $policy);
        $request = agentEvaluationControllerProxyRequest(json_encode(['model' => $model, 'stream' => true, 'store' => false,
            'input' => 'V2 input boundary.', 'reasoning' => ['effort' => 'high'], 'tools' => [], 'max_output_tokens' => $output], JSON_THROW_ON_ERROR), $state);
        $approved = agentEvaluationControllerProxyJsonObject(agentEvaluationControllerProxyReserve($request['request'],
            json_encode(['object' => 'response.input_tokens', 'input_tokens' => $input], JSON_THROW_ON_ERROR), $state));
        $spending = agentEvaluationRequireObject($state, 'spending', 'v2 reservation');
        agentEvaluationControllerTest($approved['max_output_tokens'] === $expectedOutput && $spending['reserved_units'] === $expectedUnits,
            'V2 still reserves full uncached counted input plus only the output affordable under one dollar.');
    }
    $body = json_encode(['model' => $model, 'stream' => true, 'store' => false,
        'input' => 'V2 cumulative boundary.', 'reasoning' => ['effort' => 'high'], 'tools' => [], 'max_output_tokens' => 16], JSON_THROW_ON_ERROR);
    $state = agentEvaluationControllerProxyState($model, 'high', 1_000_000, $policy);
    $request = agentEvaluationControllerProxyRequest($body, $state);
    agentEvaluationControllerExpectFailure(
        static function () use ($request, &$state): void { agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":200001}', $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESERVATION_REJECTED',
    );
    $spending = agentEvaluationRequireObject($state, 'spending', 'v2 context limit');
    agentEvaluationControllerTest($state['failure_reason'] === 'model_input_limit' && $state['request_count'] === 0
        && $state['reserved_input'] === 0 && $state['reserved_output'] === 0 && $spending['reserved_units'] === 0,
        'An input above the fixed short-context ceiling must be refused before a create or money reservation.');
    $state = agentEvaluationControllerProxyState($model, 'high', 1_000_000, $policy);
    for ($turn = 0; $turn < 10; $turn++) {
        $input = $turn === 9 ? 99_840 : 100_000;
        $request = agentEvaluationControllerProxyRequest($body, $state);
        agentEvaluationControllerProxyReserve($request['request'], json_encode(['object' => 'response.input_tokens', 'input_tokens' => $input], JSON_THROW_ON_ERROR), $state);
        agentEvaluationControllerProxyComplete(agentEvaluationControllerTestResponseStream(['model' => $model, 'status' => 'completed',
            'usage' => ['input_tokens' => $input, 'output_tokens' => 16, 'total_tokens' => $input + 16,
                'input_tokens_details' => ['cached_tokens' => $input], 'output_tokens_details' => ['reasoning_tokens' => 0]]]), $state);
    }
    $spending = agentEvaluationRequireObject($state, 'spending', 'v2 cumulative limit');
    agentEvaluationControllerTest($state['input_tokens'] === 999_840 && $state['output_tokens'] === 160
        && $spending['settled_units'] === 25_236_000 && $spending['reserved_units'] === 0,
        'Repeated cached context may pass 200k cumulatively while both dollar settlement and the exact million-token ceiling remain accurate.');
    $request = agentEvaluationControllerProxyRequest($body, $state);
    agentEvaluationControllerExpectFailure(
        static function () use ($request, &$state): void { agentEvaluationControllerProxyReserve($request['request'], '{"object":"response.input_tokens","input_tokens":0}', $state); },
        'AGENT_EVALUATION_CONTROLLER_PROXY_RESERVATION_REJECTED',
    );
    agentEvaluationControllerTest($state['failure_reason'] === 'model_token_limit' && $state['request_count'] === 10,
        'The next create after the exact cumulative ceiling must stop even with money remaining.');
    foreach (['cumulative', 'input', 'output', 'cache', 'reasoning', 'stripped-policy'] as $mode) {
        $invalid = agentEvaluationControllerProxyState($model, 'high', 1_000_000, $policy);
        if ($mode === 'cumulative') { $invalid['input_tokens'] = 1_000_001; }
        if ($mode === 'input') { $invalid['reserved_input'] = 200_001; $invalid['reserved_output'] = 16; }
        if ($mode === 'output') { $invalid['reserved_output'] = 66_667; }
        if ($mode === 'cache') { $invalid['cached_tokens'] = 1; }
        if ($mode === 'reasoning') { $invalid['reasoning_tokens'] = 1; }
        if ($mode === 'stripped-policy') { unset($invalid['spending']); }
        $accepted = true;
        try { agentEvaluationControllerProxyRequest($body, $invalid); } catch (RuntimeException) { $accepted = false; }
        agentEvaluationControllerTest(!$accepted, 'A malformed v2 ledger must never authorize a create: ' . $mode);
    }
}
