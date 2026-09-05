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

        if ($command === 'preflight') {
            agentEvaluationControllerRequireArgumentCount($arguments, 3, 'preflight <configuration.json>');
            $configuration = agentEvaluationControllerReadLiveConfiguration($arguments[2]);
            $controlRoot = agentEvaluationControllerCreatePreflightRoot();
            $interruptState = null;
            try {
                $interruptState = agentEvaluationControllerInstallInterruptHandlers();
                $engine = agentEvaluationControllerOciPreflight(
                    agentEvaluationRequireObject($configuration, 'engine', 'controller live configuration'),
                    $controlRoot,
                );
                fwrite(STDOUT, agentEvaluationJson(['status' => 'pass', 'engine' => $engine['identity']]));
            } finally {
                try {
                    $ledger = agentEvaluationControllerReadOciRecoveryLedger($controlRoot);
                    if ($ledger !== null && ($ledger['containers'] !== [] || $ledger['volumes'] !== [])) {
                        fwrite(STDERR, 'OCI cleanup requires review; recovery ledger retained at ' . $controlRoot . "/owned-resources.json\n");
                    } else {
                        agentEvaluationControllerRemoveTree($controlRoot);
                    }
                } finally {
                    if ($interruptState !== null) {
                        agentEvaluationControllerRestoreInterruptHandlers($interruptState);
                    }
                }
            }
            return 0;
        }

        if ($command === 'run') {
            if (count($arguments) !== 3 && count($arguments) !== 4) {
                throw new RuntimeException('run <run-id> <configuration.json> received an unexpected number of arguments.');
            }
            $runId = $arguments[2];

            $task = agentEvaluationTask($kit, AGENT_EVALUATION_CONTROLLER_TASK_ID);
            agentEvaluationControllerValidateRequest(
                ['run_id' => $runId, 'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID],
                $task,
            );
            if (!isset($arguments[3])) {
                throw new RuntimeException(
                    AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE
                    . ': an approved explicit live configuration and all OCI controls are required.',
                );
            }
            $configuration = agentEvaluationControllerReadLiveConfiguration($arguments[3]);
            $approval = agentEvaluationRequireObject($configuration, 'approval', 'controller smoke approval');
            if ($approval['spending_ceiling_usd'] === '0.00') {
                throw new RuntimeException('A zero-spend integration approval cannot authorize a paid run.');
            }
            $credential = \getenv('OPENAI_API_KEY');
            if (!is_string($credential) || $credential === '' || strlen($credential) > 4_096 || preg_match('/[\x00-\x20\x7F]/', $credential) === 1) {
                throw new RuntimeException('Live execution requires the host-only OPENAI_API_KEY.');
            }
            $runsRoot = dirname($root) . '/agent-evaluation-runs';
            if (!file_exists($runsRoot) && !mkdir($runsRoot, 0700)) {
                throw new RuntimeException('Unable to prepare the fixed evaluation evidence parent.');
            }
            agentEvaluationControllerExistingRoot($runsRoot, 'evaluation evidence parent');
            $result = agentEvaluationControllerExecuteLive(
                $root,
                $runsRoot . '/' . $runId,
                ['run_id' => $runId, 'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID],
                $configuration,
                $credential,
            );
            fwrite(STDOUT, agentEvaluationJson($result));
            return $result['automated_status'] === 'pass' ? 0 : 1;
        }

        if ($command === 'help') {
            if (count($arguments) !== 1 && count($arguments) !== 2) {
                throw new RuntimeException('help received an unexpected number of arguments.');
            }

            fwrite(
                STDOUT,
                "Usage:\n"
                . "  php tools/agent-evaluation-controller.php validate\n"
                . "  php tools/agent-evaluation-controller.php preflight <configuration.json>\n"
                . "  php tools/agent-evaluation-controller.php run <32-lowercase-hex-run-id> <configuration.json>\n\n"
                . "Live execution is opt-in and fails closed unless every ADR 048 OCI and proxy control passes.\n",
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

    return agentEvaluationControllerExecuteControlled(
        $repositoryRoot, $preparedDependencies, $runRoot, $request, $profile, $testFailureMode, null, '',
    );
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $configuration
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,weighted_score:int,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerExecuteLive(
    string $repositoryRoot,
    string $runRoot,
    array $request,
    array $configuration,
    #[SensitiveParameter] string $credential,
): array {
    return agentEvaluationControllerExecuteControlled(
        $repositoryRoot,
        agentEvaluationRequireString($configuration, 'prepared_dependencies', 'controller live configuration'),
        $runRoot,
        $request,
        agentEvaluationRequireObject($configuration, 'profile', 'controller live configuration'),
        null,
        $configuration,
        $credential,
    );
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $profile
 * @param array<string, mixed>|null $execution
 * @return array{run_id:string,evidence_root:string,run_record_path:string,score_record_path:string,evidence_manifest_path:string,automated_status:string,weighted_score:int,cleanup:array{status:string,removed:list<string>}}
 */
function agentEvaluationControllerExecuteControlled(
    string $repositoryRoot,
    string $preparedDependencies,
    string $runRoot,
    array $request,
    array $profile,
    ?string $testFailureMode,
    ?array $execution,
    #[SensitiveParameter] string $credential,
): array {
    $synthetic = $execution === null;
    if ($synthetic && !agentEvaluationControllerTestingEnabled()) {
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
    $validatedProfile = agentEvaluationControllerValidateProfile($profile, $task, $synthetic);
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
    $oci = null;
    $controlRoot = null;
    /** @var list<string> $observedPhases */
    $observedPhases = [];
    $phase = 'prepare';
    $primaryFailure = null;
    $cleanupFailure = null;
    /** @var array{status: string, removed: list<string>} $cleanup */
    $cleanup = ['status' => 'not_started', 'removed' => []];
    $success = null;
    $interruptState = $synthetic ? null : agentEvaluationControllerInstallInterruptHandlers();

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

        if ($execution !== null) {
            $controlRoot = agentEvaluationControllerCreatePreflightRoot();
            $engine = agentEvaluationControllerOciPreflight(
                agentEvaluationRequireObject($execution, 'engine', 'controller live configuration'),
                $controlRoot,
            );
            agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'oci-preflight.json', agentEvaluationJson($engine['identity']));
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'], 'approval.json',
                agentEvaluationJson(agentEvaluationRequireObject($execution, 'approval', 'controller live configuration')),
            );
            $lockPath = agentEvaluationRequireString($execution, 'prepared_lock', 'controller live configuration');
            agentEvaluationRequireBoundedFile($lockPath, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, 'live prepared lock');
            $lockBytes = file_get_contents($lockPath);
            $lockHash = agentEvaluationRequireString($execution, 'prepared_lock_sha256', 'controller live configuration');
            $dependencyHash = agentEvaluationRequireString($execution, 'prepared_dependencies_sha256', 'controller live configuration');
            if (!is_string($lockBytes) || !hash_equals($lockHash, hash('sha256', $lockBytes)) || !hash_equals($dependencyHash, $workspace['dependency_manifest_sha256'])) {
                throw new RuntimeException('Live prepared inputs changed before generation.');
            }
            agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'dependencies.lock', $lockBytes);
            $oci = agentEvaluationControllerOciPrepare(
                $engine, $validatedRequest['run_id'], $workspace['candidate_root'], $workspace['dependencies_root'],
            );
        }

        $phase = 'generate';
        agentEvaluationControllerEnterPhase($observedPhases, 'generate');
        agentEvaluationControllerInjectSyntheticFailure($workspace, $testFailureMode);
        $startedAt = agentEvaluationControllerUtcNow();
        $generation = $oci === null ? agentEvaluationControllerRunCodex(
            $workspace['candidate_root'],
            $prompt,
            agentEvaluationRequireString($validatedProfile['model'], 'id', 'controller model profile'),
            'high',
            $task['budgets'],
            $validatedProfile['isolation'],
            true,
        ) : agentEvaluationControllerRunLiveCodex($oci, $prompt, $validatedProfile, $credential);
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
        if (!$synthetic) {
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'], 'proxy.json',
                agentEvaluationJson(agentEvaluationRequireObject($generation, 'proxy_evidence', 'live generation evidence')),
            );
            agentEvaluationControllerWriteArtifact(
                $workspace['evidence_root'], 'external-actions.json',
                agentEvaluationJson(agentEvaluationRequireObject($generation, 'external_actions', 'live generation evidence')),
            );
        }

        if ($generation['termination_reason'] !== 'completed') {
            throw new RuntimeException('Generation did not complete within every fixed bound.');
        }

        $externalActionsApproved = $synthetic ? agentEvaluationControllerSyntheticExternalActionsApproved(
            $generation['events'],
        ) : ($generation['external_actions_approved'] ?? false) === true;

        if (!$externalActionsApproved) {
            throw new RuntimeException('Generation reported an unapproved action.');
        }
        if ($synthetic) {
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
        }

        $phase = 'freeze';
        agentEvaluationControllerEnterPhase($observedPhases, 'freeze');
        if ($oci !== null) {
            agentEvaluationControllerOciStopGeneration($oci);
            agentEvaluationControllerOciExportCandidate($oci, $workspace['candidate_root']);
        }
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
        $ociGenerationCleanup = $oci === null ? null : agentEvaluationControllerOciDestroyGeneration($oci);
        $generationRemoved = agentEvaluationControllerRemoveGenerationWorkspace($workspace);
        $generationCleanup = count($generationRemoved) === 3
            && ($ociGenerationCleanup === null || $ociGenerationCleanup['status'] === 'pass');
        agentEvaluationControllerWriteArtifact(
            $workspace['evidence_root'],
            'generation-cleanup.json',
            agentEvaluationJson([
                'status' => $generationCleanup ? 'pass' : 'fail',
                ...($ociGenerationCleanup === null ? [] : ['oci' => $ociGenerationCleanup]),
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
        $scoringChecks = [
            'manifest_valid' => true,
            'workspace_policy' => true,
            'frozen_before_scoring' => true,
            'scorer_integrity' => true,
            'external_actions_approved' => $externalActionsApproved,
            'generation_cleanup' => $generationCleanup,
        ];
        $score = $oci === null ? agentEvaluationControllerScoreFrozenCandidate(
            $scoringWorkspace['candidate_root'],
            $scorerPath,
            $scoringChecks,
            $task['budgets'],
            $validatedProfile['isolation'],
            true,
        ) : agentEvaluationControllerScoreLiveCandidate(
            $oci, $scoringWorkspace['candidate_root'], $scorerPath, $scoringChecks, $validatedProfile,
            $workspace['evidence_root'],
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

        if ($synthetic) {
            agentEvaluationControllerRetainScoringEvidence($workspace['evidence_root'], $score['evidence']);
        }

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
            $synthetic,
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
        if (preg_match('/\AAGENT_EVALUATION_CONTROLLER_[A-Z0-9_]+\z/D', $throwable->getMessage()) === 1) {
            $primaryFailure['code'] = $throwable->getMessage();
        }
    } finally {
        if ($oci !== null) {
            try {
                $ociCleanup = agentEvaluationControllerOciCleanup($oci);
                if (is_array($workspace)) {
                    agentEvaluationControllerWriteArtifact($workspace['evidence_root'], 'oci-cleanup.json', agentEvaluationJson($ociCleanup));
                }
                if ($ociCleanup['status'] !== 'pass') {
                    throw new RuntimeException('OCI resource cleanup was not verified.');
                }
            } catch (Throwable $throwable) {
                $cleanupFailure = ['class' => $throwable::class];
            }
        }
        if ($controlRoot !== null) {
            try {
                $ledger = agentEvaluationControllerReadOciRecoveryLedger($controlRoot);
                if ($ledger !== null && is_array($workspace)) {
                    agentEvaluationControllerWriteArtifact(
                        $workspace['evidence_root'], 'owned-resources.json', agentEvaluationJson($ledger),
                    );
                    if ($ledger['containers'] !== [] || $ledger['volumes'] !== []) {
                        $cleanupFailure = ['class' => RuntimeException::class];
                    }
                }
                agentEvaluationControllerRemoveTree($controlRoot);
            } catch (Throwable $throwable) {
                $cleanupFailure = ['class' => $throwable::class];
            }
        }
        if (is_array($workspace)) {
            try {
                agentEvaluationControllerEnterCleanupPhase($observedPhases);
                $cleanup = [
                    'status' => $cleanupFailure === null ? 'pass' : 'fail',
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
                        $synthetic,
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

    if ($interruptState !== null) {
        agentEvaluationControllerRestoreInterruptHandlers($interruptState);
    }

    if ($primaryFailure !== null || $cleanupFailure !== null) {
        throw new RuntimeException(agentEvaluationControllerFailureMessage($primaryFailure, $cleanupFailure));
    }
    if ($success === null || $workspace === null) {
        throw new RuntimeException('Controller completion requires retained results and a prepared workspace.');
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

/** @return array{owner: string, run_id: string, containers: array<string, string>, volumes: array<string, string>}|null */
function agentEvaluationControllerReadOciRecoveryLedger(string $controlRoot): ?array
{
    $path = $controlRoot . '/owned-resources.json';
    if (!file_exists($path) && !is_link($path)) {
        return null;
    }
    agentEvaluationRequireBoundedFile($path, 32_768, 'OCI recovery ledger');
    $ledger = agentEvaluationJsonFile($path);
    agentEvaluationRequireExactKeys($ledger, ['owner', 'run_id', 'containers', 'volumes'], 'OCI recovery ledger');
    $owner = agentEvaluationRequireNonEmptyString($ledger, 'owner', 'OCI recovery ledger');
    $runId = agentEvaluationRequireNonEmptyString($ledger, 'run_id', 'OCI recovery ledger');
    $resources = [];
    foreach (['containers', 'volumes'] as $kind) {
        $resources[$kind] = [];
        $members = $ledger[$kind] === [] ? [] : agentEvaluationRequireObject($ledger, $kind, 'OCI recovery ledger');
        foreach ($members as $role => $name) {
            if (!is_string($name) || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}\z/D', $name) !== 1) {
                throw new RuntimeException('OCI recovery ledger contains an invalid resource name.');
            }
            $resources[$kind][$role] = $name;
        }
    }
    return ['owner' => $owner, 'run_id' => $runId, 'containers' => $resources['containers'], 'volumes' => $resources['volumes']];
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

function agentEvaluationControllerCreatePreflightRoot(): string
{
    $base = realpath(sys_get_temp_dir());
    if (!is_string($base)) {
        throw new RuntimeException('Controller temporary base is unavailable.');
    }
    $target = $base . '/phpthis-oci-control-' . bin2hex(random_bytes(16));
    if (!mkdir($target, 0700) || !chmod($target, 0700)) {
        throw new RuntimeException('Unable to create the private OCI control directory.');
    }
    return $target;
}

function agentEvaluationControllerWriteArtifact(string $evidenceRoot, string $name, string $bytes): string
{
    agentEvaluationRequireRelativePath($name, 'controller evidence filename');

    $emptyStream = $bytes === '' && in_array($name, ['events.jsonl', 'generation.stderr'], true);
    if (str_contains($name, '/') || ($bytes === '' && !$emptyStream) || strlen($bytes) > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
        throw new RuntimeException('Controller evidence artifact must be bounded flat content; only observed streams may be empty.');
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
    bool $synthetic = true,
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
            $synthetic
                ? 'Deterministic fake-controller smoke only; no model, real candidate gate, OCI, or comparison.'
                : 'Public OCI/Codex smoke with actual application gate and public scorer; inspect proxy evidence for provider or deterministic fixture identity. No comparative claim.',
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
    bool $synthetic = true,
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
        'synthetic' => $synthetic,
        'comparative_claims' => false,
        'expected_phase_order' => AGENT_EVALUATION_CONTROLLER_PHASES,
        'observed_phases' => $observedPhases,
        'primary_failure' => $primaryFailure,
        'cleanup_failure' => $cleanupFailure,
        'artifacts' => $artifacts,
    ];
}
