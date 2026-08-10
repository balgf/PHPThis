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
        'source-skeleton.manifest',
        'task.json',
        'validation.json',
    ];
    $manifestArtifacts = agentEvaluationValueObject(
        $evidenceManifest['artifacts'] ?? null,
        'controller evidence artifacts',
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

/**
 * @param array<string, mixed> $task
 */
function agentEvaluationControllerTestWorkspaceControls(
    string $root,
    string $dependencies,
    string $temporaryRoot,
    array $task,
): void {
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

    agentEvaluationControllerExpectFailure(
        static function () use ($protected, $task): void {
            agentEvaluationControllerFreezeWorkspace($protected, $task);
        },
        'Candidate changed protected path composer.json.',
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

    agentEvaluationControllerExpectFailure(
        static function () use ($unlisted, $task): void {
            agentEvaluationControllerFreezeWorkspace($unlisted, $task);
        },
        'Candidate created unapproved path src/Unexpected.php.',
    );
    agentEvaluationControllerRemoveTree($unlisted['run_root']);

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
