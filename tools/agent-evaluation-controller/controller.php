<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_PHASES = [
    'prepare',
    'generate',
    'freeze',
    'score',
    'validate',
    'retain',
    'cleanup',
];
const AGENT_EVALUATION_CONTROLLER_EVIDENCE_VERSION = 1;

/** @param list<string> $arguments */
function agentEvaluationControllerMain(array $arguments): int
{
    $root = dirname(__DIR__, 2);
    $kit = $root . '/tools/agent-evaluation';
    $command = $arguments[1] ?? 'help';

    try {
        if ($command === 'validate') {
            agentEvaluationControllerRequireArgumentCount($arguments, 2, 'validate');
            $task = agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID);
            agentEvaluationControllerRequireFixedTask($task);
            fwrite(
                STDOUT,
                "PASS agent evaluation controller v0.2: synthetic lifecycle installed; live execution fails closed\n",
            );

            return 0;
        }

        if ($command === 'run') {
            agentEvaluationControllerRequireArgumentCount($arguments, 3, 'run <run-id>');
            $runId = $arguments[2] ?? null;

            if (!is_string($runId)) {
                throw new RuntimeException('run requires one run ID.');
            }

            $task = agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID);
            agentEvaluationControllerValidateRequest(
                ['run_id' => $runId, 'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID],
                $task,
            );
            throw new RuntimeException(
                AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE
                . ': ADR 048 requires a separately implemented and proved OCI image and credential proxy.',
            );
        }

        if ($command === 'help') {
            if (count($arguments) !== 1 && count($arguments) !== 2) {
                throw new RuntimeException('help received an unexpected number of arguments.');
            }

            fwrite(
                STDOUT,
                "Usage:\n"
                . "  php tools/agent-evaluation-controller.php validate\n"
                . "  php tools/agent-evaluation-controller.php run <32-lowercase-hex-run-id>\n\n"
                . "The run command fails closed until ADR 048's real OCI and credential-proxy boundary exists.\n",
            );

            return 0;
        }

        throw new RuntimeException("Unknown agent-evaluation-controller command: {$command}.");
    } catch (Throwable $throwable) {
        fwrite(STDERR, "FAIL agent evaluation controller: {$throwable->getMessage()}\n");

        return 1;
    }
}

/**
 * @param list<string> $arguments
 */
function agentEvaluationControllerRequireArgumentCount(array $arguments, int $expected, string $usage): void
{
    if (count($arguments) !== $expected) {
        throw new RuntimeException("{$usage} received an unexpected number of arguments.");
    }
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $profile
 * @return array{
 *   run_id: string,
 *   evidence_root: string,
 *   run_record_path: string,
 *   score_record_path: string,
 *   evidence_manifest_path: string,
 *   automated_status: string,
 *   weighted_score: int,
 *   cleanup: array{status: string, removed: list<string>}
 * }
 */
function agentEvaluationControllerExecuteSynthetic(
    string $repositoryRoot,
    string $preparedDependencies,
    string $runRoot,
    array $request,
    array $profile,
    ?string $testFailureMode = null,
): array {
    if (!agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_SYNTHETIC_EXECUTION_TEST_ONLY');
    }

    if (!in_array($testFailureMode, [null, 'generate', 'generate-and-cleanup'], true)) {
        throw new RuntimeException('Synthetic controller failure mode is not one fixed test control.');
    }

    $root = agentEvaluationControllerExistingRoot($repositoryRoot, 'controller repository root');
    $freshRunRoot = agentEvaluationControllerFreshAbsoluteTarget($runRoot, 'controller run root');

    if (agentEvaluationControllerPathsOverlap($freshRunRoot, $root)) {
        throw new RuntimeException(
            'Controller run root must be separate from the maintainer repository.',
        );
    }

    $kit = $root . '/tools/agent-evaluation';
    $task = agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID);
    $validatedRequest = agentEvaluationControllerValidateRequest($request, $task);
    $validatedProfile = agentEvaluationControllerValidateProfile($profile, $task, true);
    $promptPath = $task['directory'] . '/' . $task['prompt']['path'];
    $taskManifestPath = $task['directory'] . '/task.json';
    $rubricPath = $task['directory'] . '/' . $task['rubric']['path'];
    $scorerPath = $task['directory'] . '/' . $task['public_scorer']['path'];
    $prompt = file_get_contents($promptPath);
    $taskManifest = file_get_contents($taskManifestPath);
    $rubric = file_get_contents($rubricPath);

    if (!is_string($prompt) || !is_string($taskManifest) || !is_string($rubric)) {
        throw new RuntimeException('Controller task, prompt, or rubric is unreadable.');
    }

    agentEvaluationRequireFileHash($promptPath, $task['prompt']['sha256'], 'controller prompt');
    agentEvaluationRequireFileHash($rubricPath, $task['rubric']['sha256'], 'controller rubric');
    agentEvaluationRequireFileHash($scorerPath, $task['public_scorer']['sha256'], 'controller public scorer');

    $workspace = null;
    /** @var list<string> $observedPhases */
    $observedPhases = [];
    $phase = 'prepare';
    $primaryFailure = null;
    $cleanupFailure = null;
    /** @var array{status: string, removed: list<string>} $cleanup */
    $cleanup = ['status' => 'not_started', 'removed' => []];
    $success = null;

    try {
        agentEvaluationControllerEnterPhase($observedPhases, 'prepare');
        $workspace = agentEvaluationControllerPrepareWorkspace(
            $root . '/skeleton',
            $preparedDependencies,
            $runRoot,
            $task,
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'source-skeleton.manifest',
            $workspace['baseline_manifest'],
        );
        agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'prompt.md', $prompt);
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'task.json',
            $taskManifest,
        );
        agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'rubric.md', $rubric);
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'profile.json',
            agentEvaluationJson($validatedProfile),
        );

        $phase = 'generate';
        agentEvaluationControllerEnterPhase($observedPhases, 'generate');
        agentEvaluationControllerInjectSyntheticFailure($workspace, $testFailureMode);
        $startedAt = agentEvaluationControllerUtcNow();
        $generation = agentEvaluationControllerRunCodex(
            $workspace['candidate_root'],
            $prompt,
            agentEvaluationRequireString($validatedProfile['model'], 'id', 'controller model profile'),
            'high',
            $task['budgets'],
            $validatedProfile['isolation'],
            true,
        );
        $finishedAt = agentEvaluationControllerUtcNow();
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'events.jsonl',
            $generation['events_jsonl'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation.stderr',
            $generation['process']['stderr'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation-process.json',
            agentEvaluationJson([
                ...$generation['process'],
                'stdout' => 'retained separately as events.jsonl',
                'stderr' => 'retained separately as generation.stderr',
            ]),
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'response.txt',
            $generation['response'] . "\n",
        );

        if ($generation['termination_reason'] !== 'completed') {
            throw new RuntimeException('Synthetic generation did not complete within every fixed bound.');
        }

        $externalActionsApproved = agentEvaluationControllerSyntheticExternalActionsApproved(
            $generation['events'],
        );

        if (!$externalActionsApproved) {
            throw new RuntimeException('Synthetic generation reported an unapproved action.');
        }
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'external-actions.json',
            agentEvaluationJson([
                'approved' => true,
                'network_attempts' => 0,
                'process_tool_calls' => 0,
                'changed_paths' => ['src/HealthRoutes.php', 'src/PingHandler.php', 'tests/run.php'],
            ]),
        );

        $phase = 'freeze';
        agentEvaluationControllerEnterPhase($observedPhases, 'freeze');
        $freeze = agentEvaluationControllerFreezeWorkspace($workspace, $task);
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'candidate.patch',
            $freeze['patch'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'candidate.manifest',
            $freeze['candidate_manifest'],
        );
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'freeze.json',
            agentEvaluationJson([
                'candidate_sha256' => $freeze['candidate_sha256'],
                'patch_sha256' => $freeze['patch_sha256'],
                'changed_files' => $freeze['changed_files'],
                'added_lines' => $freeze['added_lines'],
                'deleted_lines' => $freeze['deleted_lines'],
            ]),
        );
        $scoringWorkspace = agentEvaluationControllerCreateScoringWorkspace(
            $workspace,
            $workspace['run_root'] . '/scoring',
            $freeze,
        );
        $generationRemoved = agentEvaluationControllerRemoveGenerationWorkspace($workspace);
        $generationCleanup = count($generationRemoved) === 3;
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation-cleanup.json',
            agentEvaluationJson([
                'status' => $generationCleanup ? 'pass' : 'fail',
                'removed' => array_map(
                    static fn (string $path): string => basename($path),
                    $generationRemoved,
                ),
            ]),
        );

        $phase = 'score';
        agentEvaluationControllerEnterPhase($observedPhases, 'score');
        agentEvaluationRequireFileHash(
            $scorerPath,
            $task['public_scorer']['sha256'],
            'pre-score public scorer',
        );
        $score = agentEvaluationControllerScoreFrozenCandidate(
            $scoringWorkspace['candidate_root'],
            $scorerPath,
            [
                'manifest_valid' => true,
                'workspace_policy' => true,
                'frozen_before_scoring' => true,
                'scorer_integrity' => true,
                'external_actions_approved' => $externalActionsApproved,
                'generation_cleanup' => $generationCleanup,
            ],
            $task['budgets'],
            $validatedProfile['isolation'],
            true,
        );
        agentEvaluationRequireFileHash(
            $scorerPath,
            $task['public_scorer']['sha256'],
            'post-score public scorer',
        );
        agentEvaluationControllerValidateReadOnlyScoringCandidate(
            $scoringWorkspace['candidate_root'],
            $freeze['candidate_manifest'],
            $freeze['candidate_sha256'],
        );

        agentEvaluationControllerRetainScoringEvidence($workspace['evidence_root'], $score['evidence']);

        $phase = 'validate';
        agentEvaluationControllerEnterPhase($observedPhases, 'validate');
        $runRecord = agentEvaluationControllerRunRecord(
            $validatedRequest,
            $validatedProfile,
            $task,
            $workspace,
            $generation,
            $startedAt,
            $finishedAt,
        );
        $runRecordPath = agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'run.json',
            agentEvaluationJson($runRecord),
        );
        agentEvaluationValidateRunRecord($runRecord, $task);
        agentEvaluationValidateRunArtifacts($runRecord, $workspace['evidence_root']);
        $runRecordHash = agentEvaluationFileHash($runRecordPath, 'controller run record');
        $scoreRecord = agentEvaluationControllerScoreRecord(
            $validatedRequest,
            $task,
            $runRecord,
            $runRecordHash,
            $score,
        );
        $scoreRecordPath = agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'score.json',
            agentEvaluationJson($scoreRecord),
        );
        agentEvaluationValidateScoreRecord($scoreRecord, $task, $runRecord, $runRecordHash);
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'validation.json',
            agentEvaluationJson([
                'v1_run_record' => 'pass',
                'v1_score_record' => 'pass',
            ]),
        );

        $phase = 'retain';
        agentEvaluationControllerEnterPhase($observedPhases, 'retain');
        $success = [
            'run_id' => $validatedRequest['run_id'],
            'run_record_path' => $runRecordPath,
            'score_record_path' => $scoreRecordPath,
            'automated_status' => agentEvaluationRequireString(
                $scoreRecord,
                'automated_status',
                'controller score record',
            ),
            'weighted_score' => agentEvaluationRequireInteger(
                $scoreRecord,
                'weighted_score',
                'controller score record',
            ),
        ];
    } catch (Throwable $throwable) {
        $primaryFailure = [
            'phase' => $phase,
            'class' => $throwable::class,
        ];
    } finally {
        if (is_array($workspace)) {
            try {
                agentEvaluationControllerEnterCleanupPhase($observedPhases);
                $cleanup = [
                    'status' => 'pass',
                    'removed' => agentEvaluationControllerCleanupWorkspace($workspace),
                ];
            } catch (Throwable $throwable) {
                $cleanup = ['status' => 'fail', 'removed' => []];
                $cleanupFailure = ['class' => $throwable::class];
            }

            if (is_dir($workspace['evidence_root']) && !is_link($workspace['evidence_root'])) {
                try {
                    agentEvaluationControllerWriteArtifact(
                        $workspace['evidence_root'],
                        'cleanup.json',
                        agentEvaluationJson([
                            'status' => $cleanup['status'],
                            'removed' => array_map(
                                static fn (string $path): string => basename($path),
                                $cleanup['removed'],
                            ),
                            'primary_failure' => $primaryFailure,
                            'cleanup_failure' => $cleanupFailure,
                        ]),
                    );
                    $manifest = agentEvaluationControllerEvidenceManifest(
                        $workspace['evidence_root'],
                        $validatedRequest['run_id'],
                        $observedPhases,
                        $primaryFailure,
                        $cleanupFailure,
                    );
                    agentEvaluationControllerWriteArtifact(
                        $workspace['evidence_root'],
                        'evidence-manifest.json',
                        agentEvaluationJson($manifest),
                    );
                } catch (Throwable $throwable) {
                    $cleanupFailure ??= ['class' => $throwable::class];
                    $cleanup['status'] = 'fail';
                }
            }
        }
    }

    if ($primaryFailure !== null || $cleanupFailure !== null) {
        throw new RuntimeException(agentEvaluationControllerFailureMessage($primaryFailure, $cleanupFailure));
    }

    return [
        'run_id' => $success['run_id'],
        'evidence_root' => $workspace['evidence_root'],
        'run_record_path' => $success['run_record_path'],
        'score_record_path' => $success['score_record_path'],
        'evidence_manifest_path' => $workspace['evidence_root'] . '/evidence-manifest.json',
        'automated_status' => $success['automated_status'],
        'weighted_score' => $success['weighted_score'],
        'cleanup' => $cleanup,
    ];
}

/**
 * @param array<string, mixed> $workspace
 */
function agentEvaluationControllerInjectSyntheticFailure(array $workspace, ?string $mode): void
{
    if ($mode === null) {
        return;
    }

    if ($mode === 'generate-and-cleanup') {
        $runRoot = agentEvaluationRequireString($workspace, 'run_root', 'controller workspace');
        $unexpected = $runRoot . '/unexpected-cleanup.control';

        if (file_put_contents($unexpected, "fixed cleanup failure\n", LOCK_EX) === false) {
            throw new RuntimeException('Unable to prepare the fixed cleanup-failure control.');
        }
    }

    throw new RuntimeException('Fixed synthetic generation failure.');
}

/**
 * @param array{phase: string, class: string}|null $primaryFailure
 * @param array{class: string}|null $cleanupFailure
 */
function agentEvaluationControllerFailureMessage(?array $primaryFailure, ?array $cleanupFailure): string
{
    $primary = $primaryFailure === null
        ? 'none'
        : $primaryFailure['phase'] . ':' . $primaryFailure['class'];
    $cleanup = $cleanupFailure === null ? 'none' : $cleanupFailure['class'];

    return "AGENT_EVALUATION_CONTROLLER_RUN_FAILED primary={$primary} cleanup={$cleanup}";
}

/** @param list<string> $observed */
function agentEvaluationControllerEnterPhase(array &$observed, string $phase): void
{
    $expected = AGENT_EVALUATION_CONTROLLER_PHASES[count($observed)] ?? null;

    if ($phase !== $expected) {
        throw new RuntimeException('Controller phase order changed before ' . $phase . '.');
    }

    $observed[] = $phase;
}

/** @param list<string> $observed */
function agentEvaluationControllerEnterCleanupPhase(array &$observed): void
{
    if (in_array('cleanup', $observed, true)) {
        throw new RuntimeException('Controller cleanup phase was entered more than once.');
    }

    $observed[] = 'cleanup';
}

function agentEvaluationControllerUtcNow(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
}

function agentEvaluationControllerWriteArtifact(string $evidenceRoot, string $name, string $bytes): string
{
    agentEvaluationRequireRelativePath($name, 'controller evidence filename');

    if (str_contains($name, '/') || $bytes === '' || strlen($bytes) > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
        throw new RuntimeException('Controller evidence artifact must be non-empty bounded flat content.');
    }

    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'controller evidence root');
    $path = $root . '/' . $name;

    if (file_exists($path) || is_link($path)) {
        throw new RuntimeException("Controller evidence artifact already exists: {$name}.");
    }

    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($path, 0600)) {
        throw new RuntimeException("Unable to retain controller evidence artifact {$name}.");
    }

    return agentEvaluationControllerValidateRetainedArtifact(
        $root,
        $name,
        AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
    );
}

/** @param list<array<string, mixed>> $events */
function agentEvaluationControllerSyntheticExternalActionsApproved(array $events): bool
{
    $allowedPaths = ['src/HealthRoutes.php', 'src/PingHandler.php', 'tests/run.php'];
    $fileChanges = 0;
    $messages = 0;

    foreach ($events as $event) {
        $eventType = $event['type'] ?? null;

        if (in_array($eventType, ['thread.started', 'turn.started', 'turn.completed'], true)) {
            continue;
        }

        if ($eventType !== 'item.completed') {
            return false;
        }

        $item = $event['item'] ?? null;

        if (!is_array($item) || array_is_list($item)) {
            return false;
        }

        $type = $item['type'] ?? null;

        if ($type === 'agent_message') {
            $messages++;
            continue;
        }

        if ($type !== 'file_change') {
            return false;
        }

        $paths = $item['paths'] ?? null;

        if (!is_array($paths) || !array_is_list($paths) || $paths !== $allowedPaths) {
            return false;
        }

        $fileChanges++;
    }

    return $fileChanges === 1 && $messages === 1;
}

/**
 * @param array<string, mixed> $workspace
 * @return list<string>
 */
function agentEvaluationControllerRemoveGenerationWorkspace(array $workspace): array
{
    $removed = [];

    foreach (['candidate_root', 'baseline_root', 'dependencies_root'] as $field) {
        $target = $workspace[$field] ?? null;

        if (!is_string($target)) {
            throw new RuntimeException('Controller generation-cleanup target is unavailable.');
        }

        $validated = agentEvaluationControllerValidateCleanupTarget($workspace, $target);
        agentEvaluationControllerRemoveTree($validated);
        $removed[] = $validated;
    }

    return $removed;
}

/**
 * @param array<string, mixed> $evidence
 */
function agentEvaluationControllerRetainScoringEvidence(string $evidenceRoot, array $evidence): void
{
    $application = $evidence['application_check'] ?? null;
    $scorer = $evidence['public_scorer'] ?? null;
    $resource = $evidence['resource_inspection'] ?? null;

    if (!is_array($application) || !is_array($scorer) || !is_array($resource)) {
        throw new RuntimeException('Controller scoring evidence has an invalid shape.');
    }

    agentEvaluationControllerWriteArtifact(
        $evidenceRoot,
        'application-check.json',
        agentEvaluationJson($application),
    );
    agentEvaluationControllerWriteArtifact(
        $evidenceRoot,
        'public-scorer.json',
        agentEvaluationJson($scorer),
    );
    agentEvaluationControllerWriteArtifact(
        $evidenceRoot,
        'resource-inspection.json',
        agentEvaluationJson($resource),
    );
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $profile
 * @param array<string, mixed> $task
 * @param array<string, mixed> $workspace
 * @param array<string, mixed> $generation
 * @return array<string, mixed>
 */
function agentEvaluationControllerRunRecord(
    array $request,
    array $profile,
    array $task,
    array $workspace,
    array $generation,
    string $startedAt,
    string $finishedAt,
): array {
    $rubric = agentEvaluationValueObject($task['rubric'] ?? null, 'controller task rubric');
    $base = agentEvaluationValueObject($task['base'] ?? null, 'controller task base');
    $evidenceRoot = agentEvaluationRequireString($workspace, 'evidence_root', 'controller workspace');

    return [
        'schema_version' => 1,
        'run_id' => $request['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'task_manifest_sha256' => $task['manifest_sha256'],
        'rubric_sha256' => agentEvaluationRequireString($rubric, 'sha256', 'controller task rubric'),
        'base_revision' => agentEvaluationRequireString($base, 'tree', 'controller task base'),
        'base_fixture_sha256' => $workspace['base_fixture_sha256'],
        'prepared_dependencies_manifest_path' => 'prepared-dependencies.manifest',
        'prepared_dependencies_manifest_sha256' => $workspace['dependency_manifest_sha256'],
        'condition' => $profile['condition'],
        'model' => $profile['model'],
        'context' => $profile['context'],
        'tools' => $profile['tools'],
        'budgets' => $profile['budgets'],
        'usage' => $generation['usage'],
        'timing' => ['started_at' => $startedAt, 'finished_at' => $finishedAt],
        'repair_turns' => 0,
        'termination_reason' => $generation['termination_reason'],
        'events_path' => 'events.jsonl',
        'events_sha256' => agentEvaluationFileHash(
            $evidenceRoot . '/events.jsonl',
            'controller events',
        ),
        'candidate_patch_path' => 'candidate.patch',
        'candidate_patch_sha256' => agentEvaluationFileHash(
            $evidenceRoot . '/candidate.patch',
            'controller candidate patch',
        ),
    ];
}

/**
 * @param array{run_id: string, task_id: string} $request
 * @param array<string, mixed> $task
 * @param array<string, mixed> $runRecord
 * @param array<string, mixed> $score
 * @return array<string, mixed>
 */
function agentEvaluationControllerScoreRecord(
    array $request,
    array $task,
    array $runRecord,
    string $runRecordHash,
    array $score,
): array {
    $prompt = agentEvaluationValueObject($task['prompt'] ?? null, 'controller task prompt');
    $publicScorer = agentEvaluationValueObject(
        $task['public_scorer'] ?? null,
        'controller task public scorer',
    );

    return [
        'schema_version' => 1,
        'run_id' => $request['run_id'],
        'task_id' => $task['id'],
        'task_revision' => $task['revision'],
        'run_record_sha256' => $runRecordHash,
        'prompt_sha256' => agentEvaluationRequireString($prompt, 'sha256', 'controller task prompt'),
        'scorer_sha256' => agentEvaluationRequireString(
            $publicScorer,
            'sha256',
            'controller task public scorer',
        ),
        'candidate_patch_sha256' => $runRecord['candidate_patch_sha256'],
        'admissible' => $score['admissible'],
        'mandatory_checks' => $score['mandatory_checks'],
        'dimensions' => $score['dimensions'],
        'weighted_score' => $score['weighted_score'],
        'automated_status' => $score['automated_status'],
        'human_review' => 'pending',
        'notes' => [
            'Deterministic fake-controller smoke only; no model, real candidate gate, OCI, or comparison.',
        ],
    ];
}

/**
 * @param list<string> $observedPhases
 * @param array<string, string>|null $primaryFailure
 * @param array<string, string>|null $cleanupFailure
 * @return array<string, mixed>
 */
function agentEvaluationControllerEvidenceManifest(
    string $evidenceRoot,
    string $runId,
    array $observedPhases,
    ?array $primaryFailure,
    ?array $cleanupFailure,
): array {
    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'controller evidence root');
    $entries = scandir($root);

    if ($entries === false) {
        throw new RuntimeException('Unable to enumerate retained controller evidence.');
    }

    $artifacts = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'evidence-manifest.json') {
            continue;
        }

        $path = agentEvaluationControllerValidateRetainedArtifact(
            $root,
            $entry,
            AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
        );
        $bytes = filesize($path);

        if (!is_int($bytes)) {
            throw new RuntimeException("Unable to size retained controller artifact {$entry}.");
        }

        $artifacts[$entry] = [
            'bytes' => $bytes,
            'sha256' => agentEvaluationFileHash($path, "controller evidence {$entry}"),
        ];
    }

    ksort($artifacts, SORT_STRING);

    if ($primaryFailure === null && $cleanupFailure === null && $observedPhases !== AGENT_EVALUATION_CONTROLLER_PHASES) {
        throw new RuntimeException('Successful controller evidence must retain the complete exact phase order.');
    }

    return [
        'schema_version' => AGENT_EVALUATION_CONTROLLER_EVIDENCE_VERSION,
        'controller_version' => AGENT_EVALUATION_CONTROLLER_VERSION,
        'run_id' => $runId,
        'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID,
        'task_revision' => AGENT_EVALUATION_CONTROLLER_TASK_REVISION,
        'synthetic' => true,
        'comparative_claims' => false,
        'expected_phase_order' => AGENT_EVALUATION_CONTROLLER_PHASES,
        'observed_phases' => $observedPhases,
        'primary_failure' => $primaryFailure,
        'cleanup_failure' => $cleanupFailure,
        'artifacts' => $artifacts,
    ];
}
