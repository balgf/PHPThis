<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_LIVE_SCORING_UNAVAILABLE = 'AGENT_EVALUATION_CONTROLLER_LIVE_SCORING_UNAVAILABLE';
const AGENT_EVALUATION_CONTROLLER_RESOURCE_SOURCE_BYTES = 32_768;

/**
 * @param array<string, mixed> $resources
 * @param array<string, bool> $checks
 * @param array<string, mixed> $profile
 * @return array{
 *   admissible:bool,
 *   mandatory_checks:array{manifest_valid:bool,workspace_policy:bool,application_check:bool,public_scorer:bool,resource_bounds:bool},
 *   dimensions:array{observable_behavior:int,boundary_behavior:int,resource_bounds:int,application_gate:int,change_locality:int},
 *   weighted_score:int,
 *   automated_status:string,
 *   evidence:array{application_check:array<string,mixed>,public_scorer:array<string,mixed>,resource_inspection:list<string>}
 * }
 */
function agentEvaluationControllerScoreLiveCandidate(
    array &$resources,
    string $candidateDirectory,
    string $publicScorerPath,
    array $checks,
    array $profile,
    string $evidenceRoot,
): array {
    $budgetProfile = agentEvaluationRequireObject($profile, 'budgets', 'controller live profile');
    $budgets = [
        'model_tokens' => agentEvaluationRequirePositiveInteger($budgetProfile, 'model_tokens', 'live budget'),
        'wall_seconds' => agentEvaluationRequirePositiveInteger($budgetProfile, 'wall_seconds', 'live budget'),
        'repair_turns' => agentEvaluationRequireNonNegativeInteger($budgetProfile, 'repair_turns', 'live budget'),
        'command_output_bytes' => agentEvaluationRequirePositiveInteger($budgetProfile, 'command_output_bytes', 'live budget'),
    ];
    [$candidateRoot, $scorerPath] = agentEvaluationControllerValidateScoringRequest(
        $candidateDirectory, $publicScorerPath, $checks, $budgets,
    );
    $isolation = agentEvaluationRequireObject($profile, 'isolation', 'controller live profile');
    $scoringIsolation = [
        ...$isolation,
        'network' => 'none',
        'credential_broker' => 'none',
    ];
    agentEvaluationControllerValidateFutureIsolationProfile($scoringIsolation, $budgets, 'scoring');
    if (($resources['generation_destroyed'] ?? false) !== true) {
        throw new RuntimeException('Live scoring requires verified generation-container destruction after freeze.');
    }
    $before = agentEvaluationControllerDescribeTree($candidateRoot, 'live frozen scoring input', true);
    $scorerHash = agentEvaluationFileHash($scorerPath, 'live public scorer');
    $applicationCheck = agentEvaluationControllerOciRunScore($resources, $candidateRoot, $scorerPath, 'application-check');
    agentEvaluationControllerWriteArtifact($evidenceRoot, 'application-check.json', agentEvaluationJson($applicationCheck));
    $publicScorer = agentEvaluationControllerOciRunScore($resources, $candidateRoot, $scorerPath, 'public-scorer');
    agentEvaluationControllerWriteArtifact($evidenceRoot, 'public-scorer.json', agentEvaluationJson($publicScorer));
    agentEvaluationRequireFileHash($scorerPath, $scorerHash, 'live post-score public scorer');
    $after = agentEvaluationControllerDescribeTree($candidateRoot, 'live post-score frozen input', true);
    if ($before['manifest'] !== $after['manifest']) {
        throw new RuntimeException('Frozen scoring input changed during real scoring.');
    }
    $resourceInspection = agentEvaluationControllerInspectPingResources($candidateRoot);
    agentEvaluationControllerWriteArtifact($evidenceRoot, 'resource-inspection.json', agentEvaluationJson($resourceInspection['evidence']));
    $applicationPassed = agentEvaluationControllerLiveCheckPassed($applicationCheck);
    $publicScorerPassed = agentEvaluationControllerLiveCheckPassed($publicScorer)
        && $publicScorer['stdout'] === "PASS change.simple-ping public smoke\n";
    $scoringAdmissible = agentEvaluationControllerLiveCheckAdmissible($applicationCheck)
        && agentEvaluationControllerLiveCheckAdmissible($publicScorer);
    $admissible = $checks['manifest_valid']
        && $checks['workspace_policy']
        && $checks['frozen_before_scoring']
        && $checks['scorer_integrity']
        && $checks['external_actions_approved']
        && $checks['generation_cleanup']
        && $scoringAdmissible;
    $derived = agentEvaluationControllerDeriveScore([
        'admissible' => $admissible,
        'manifest_valid' => $checks['manifest_valid'],
        'workspace_policy' => $checks['workspace_policy'],
        'application_check' => $applicationPassed,
        'public_scorer' => $publicScorerPassed,
        'resource_bounds' => $resourceInspection['passed'],
    ]);
    return [
        ...$derived,
        'evidence' => [
            'application_check' => $applicationCheck,
            'public_scorer' => $publicScorer,
            'resource_inspection' => $resourceInspection['evidence'],
        ],
    ];
}

/** @param array<string, mixed> $result */
function agentEvaluationControllerLiveCheckPassed(array $result): bool
{
    return agentEvaluationControllerLiveCheckAdmissible($result)
        && ($result['exit_code'] ?? null) === 0
        && ($result['termination_reason'] ?? null) === 'completed';
}

/** @param array<string, mixed> $result */
function agentEvaluationControllerLiveCheckAdmissible(array $result): bool
{
    $exitCode = $result['exit_code'] ?? null;
    return is_int($exitCode) && $exitCode >= 0 && $exitCode <= 255
        && ($result['container_started'] ?? null) === true
        && ($result['timed_out'] ?? null) === false
        && ($result['output_limit_exceeded'] ?? null) === false
        && ($result['oom_killed'] ?? null) === false
        && ($result['container_destroyed'] ?? null) === true
        && in_array($result['termination_reason'] ?? null, ['completed', 'process_failed'], true);
}

/**
 * @param array{
 *   manifest_valid: bool,
 *   workspace_policy: bool,
 *   frozen_before_scoring: bool,
 *   scorer_integrity: bool,
 *   external_actions_approved: bool,
 *   generation_cleanup: bool
 * } $checks
 * @param array{
 *   model_tokens: int,
 *   wall_seconds: int,
 *   repair_turns: int,
 *   command_output_bytes: int
 * } $budgets
 * @param array<string, mixed> $isolation
 * @return array{
 *   admissible: bool,
 *   mandatory_checks: array{
 *     manifest_valid: bool,
 *     workspace_policy: bool,
 *     application_check: bool,
 *     public_scorer: bool,
 *     resource_bounds: bool
 *   },
 *   dimensions: array{
 *     observable_behavior: int,
 *     boundary_behavior: int,
 *     resource_bounds: int,
 *     application_gate: int,
 *     change_locality: int
 *   },
 *   weighted_score: int,
 *   automated_status: string,
 *   evidence: array{
 *     application_check: array{
 *       exit_code: int,
 *       stdout: string,
 *       stderr: string,
 *       elapsed_milliseconds: int,
 *       timed_out: bool,
 *       output_limit_exceeded: bool,
 *       termination_reason: string,
 *       cleanup: array{
 *         process_group_created: bool,
 *         terminate_sent: bool,
 *         kill_sent: bool,
 *         process_reaped: bool,
 *         process_group_absent: bool
 *       }
 *     },
 *     public_scorer: array{
 *       exit_code: int,
 *       stdout: string,
 *       stderr: string,
 *       elapsed_milliseconds: int,
 *       timed_out: bool,
 *       output_limit_exceeded: bool,
 *       termination_reason: string,
 *       cleanup: array{
 *         process_group_created: bool,
 *         terminate_sent: bool,
 *         kill_sent: bool,
 *         process_reaped: bool,
 *         process_group_absent: bool
 *       }
 *     },
 *     resource_inspection: list<string>
 *   }
 * }
 */
function agentEvaluationControllerScoreFrozenCandidate(
    string $candidateDirectory,
    string $publicScorerPath,
    array $checks,
    array $budgets,
    array $isolation,
    bool $fakeForTests = false,
): array {
    [$candidateRoot, $scorerPath] = agentEvaluationControllerValidateScoringRequest(
        $candidateDirectory,
        $publicScorerPath,
        $checks,
        $budgets,
    );

    if (!$fakeForTests) {
        agentEvaluationControllerValidateFutureIsolationProfile($isolation, $budgets, 'scoring');
        throw new RuntimeException(
            AGENT_EVALUATION_CONTROLLER_LIVE_SCORING_UNAVAILABLE
            . ': v0.2 records separate post-freeze scoring but does not execute candidate code.',
        );
    }

    if (!agentEvaluationControllerTestingEnabled()) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_FAKE_SCORING_TEST_ONLY');
    }

    agentEvaluationControllerValidateIsolationProfile($isolation, $budgets, true);

    $fixture = __DIR__ . '/fixtures/fake-codex.php';

    if (!is_file($fixture) || is_link($fixture)) {
        throw new RuntimeException('AGENT_EVALUATION_CONTROLLER_FAKE_SCORER_MISSING');
    }

    $scoringWallSeconds = min(60, $budgets['wall_seconds']);
    $environment = agentEvaluationControllerMinimalProcessEnvironment();
    $applicationCheck = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'score-application-check'],
        $candidateRoot,
        $environment,
        '',
        $scoringWallSeconds,
        $budgets['command_output_bytes'],
    );
    $publicScorer = agentEvaluationControllerRunProcess(
        [PHP_BINARY, $fixture, 'score-public-scorer'],
        $candidateRoot,
        $environment,
        '',
        $scoringWallSeconds,
        $budgets['command_output_bytes'],
    );
    $resourceInspection = agentEvaluationControllerInspectPingResources($candidateRoot);
    $applicationPassed = agentEvaluationControllerSyntheticCheckPassed(
        $applicationCheck,
        "PASS synthetic composer check\n",
    );
    $publicScorerPassed = agentEvaluationControllerSyntheticCheckPassed(
        $publicScorer,
        "PASS synthetic public scorer\n",
    );
    $scoringCleanup = agentEvaluationControllerProcessCleanupPassed($applicationCheck['cleanup'])
        && agentEvaluationControllerProcessCleanupPassed($publicScorer['cleanup']);
    $admissible = $checks['manifest_valid']
        && $checks['workspace_policy']
        && $checks['frozen_before_scoring']
        && $checks['scorer_integrity']
        && $checks['external_actions_approved']
        && $checks['generation_cleanup']
        && $scoringCleanup;
    $derived = agentEvaluationControllerDeriveScore([
        'admissible' => $admissible,
        'manifest_valid' => $checks['manifest_valid'],
        'workspace_policy' => $checks['workspace_policy'],
        'application_check' => $applicationPassed,
        'public_scorer' => $publicScorerPassed,
        'resource_bounds' => $resourceInspection['passed'],
    ]);

    return [
        ...$derived,
        'evidence' => [
            'application_check' => $applicationCheck,
            'public_scorer' => $publicScorer,
            'resource_inspection' => $resourceInspection['evidence'],
        ],
    ];
}

/**
 * @param array<string, mixed> $checks
 * @return array{
 *   admissible: bool,
 *   mandatory_checks: array{
 *     manifest_valid: bool,
 *     workspace_policy: bool,
 *     application_check: bool,
 *     public_scorer: bool,
 *     resource_bounds: bool
 *   },
 *   dimensions: array{
 *     observable_behavior: int,
 *     boundary_behavior: int,
 *     resource_bounds: int,
 *     application_gate: int,
 *     change_locality: int
 *   },
 *   weighted_score: int,
 *   automated_status: string
 * }
 */
function agentEvaluationControllerDeriveScore(array $checks): array
{
    $expectedKeys = [
        'admissible',
        'manifest_valid',
        'workspace_policy',
        'application_check',
        'public_scorer',
        'resource_bounds',
    ];
    $actualKeys = array_keys($checks);
    sort($expectedKeys, SORT_STRING);
    sort($actualKeys, SORT_STRING);

    if ($actualKeys !== $expectedKeys) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_SCORE_CHECK_FIELDS_INVALID');
    }

    $admissible = agentEvaluationControllerScoreBoolean($checks, 'admissible');
    $mandatoryChecks = [
        'manifest_valid' => agentEvaluationControllerScoreBoolean($checks, 'manifest_valid'),
        'workspace_policy' => agentEvaluationControllerScoreBoolean($checks, 'workspace_policy'),
        'application_check' => agentEvaluationControllerScoreBoolean($checks, 'application_check'),
        'public_scorer' => agentEvaluationControllerScoreBoolean($checks, 'public_scorer'),
        'resource_bounds' => agentEvaluationControllerScoreBoolean($checks, 'resource_bounds'),
    ];

    $dimensions = [
        'observable_behavior' => $mandatoryChecks['public_scorer'] ? 100 : 0,
        'boundary_behavior' => $mandatoryChecks['public_scorer'] ? 100 : 0,
        'resource_bounds' => $mandatoryChecks['resource_bounds'] ? 100 : 0,
        'application_gate' => $mandatoryChecks['application_check'] ? 100 : 0,
        'change_locality' => $mandatoryChecks['workspace_policy'] ? 100 : 0,
    ];
    $weightedScore = intdiv(
        (40 * $dimensions['observable_behavior'])
        + (20 * $dimensions['boundary_behavior'])
        + (15 * $dimensions['resource_bounds'])
        + (15 * $dimensions['application_gate'])
        + (10 * $dimensions['change_locality']),
        100,
    );
    $allMandatoryChecksPass = !in_array(false, $mandatoryChecks, true);
    $allDimensionsPass = !in_array(0, $dimensions, true);

    return [
        'admissible' => $admissible,
        'mandatory_checks' => $mandatoryChecks,
        'dimensions' => $dimensions,
        'weighted_score' => $weightedScore,
        'automated_status' => $admissible
            && $allMandatoryChecksPass
            && $allDimensionsPass
            && $weightedScore >= 85
                ? 'pass'
                : 'fail',
    ];
}

/** @param array<string, mixed> $checks */
function agentEvaluationControllerScoreBoolean(array $checks, string $name): bool
{
    $value = $checks[$name] ?? null;

    if (!is_bool($value)) {
        throw new InvalidArgumentException("AGENT_EVALUATION_CONTROLLER_SCORE_CHECK_INVALID: {$name}");
    }

    return $value;
}

/**
 * @param array<string, mixed> $checks
 * @param array<string, mixed> $budgets
 * @return array{string, string}
 */
function agentEvaluationControllerValidateScoringRequest(
    string $candidateDirectory,
    string $publicScorerPath,
    array $checks,
    array $budgets,
): array {
    $candidateRoot = realpath($candidateDirectory);
    $scorerPath = realpath($publicScorerPath);

    if (
        !is_string($candidateRoot)
        || !is_dir($candidateRoot)
        || is_link($candidateDirectory)
        || file_exists($candidateRoot . '/.git')
        || is_link($candidateRoot . '/.git')
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_SCORING_CANDIDATE_INVALID');
    }

    $candidatePrefix = $candidateRoot . DIRECTORY_SEPARATOR;

    if (
        !is_string($scorerPath)
        || !is_file($scorerPath)
        || is_link($publicScorerPath)
        || str_starts_with($scorerPath, $candidatePrefix)
    ) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_SCORER_BOUNDARY_INVALID');
    }

    $expectedCheckKeys = [
        'manifest_valid',
        'workspace_policy',
        'frozen_before_scoring',
        'scorer_integrity',
        'external_actions_approved',
        'generation_cleanup',
    ];
    $actualCheckKeys = array_keys($checks);
    sort($expectedCheckKeys, SORT_STRING);
    sort($actualCheckKeys, SORT_STRING);

    if ($actualCheckKeys !== $expectedCheckKeys) {
        throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_SCORING_CHECK_FIELDS_INVALID');
    }

    foreach ($checks as $value) {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('AGENT_EVALUATION_CONTROLLER_SCORING_CHECK_INVALID');
        }
    }

    agentEvaluationControllerValidateBudgets($budgets);

    return [$candidateRoot, $scorerPath];
}

/**
 * @param array{
 *   exit_code: int,
 *   stdout: string,
 *   stderr: string,
 *   timed_out: bool,
 *   output_limit_exceeded: bool,
 *   termination_reason: string,
 *   cleanup: array{
 *     process_group_created: bool,
 *     process_reaped: bool,
 *     process_group_absent: bool
 *   }
 * } $process
 */
function agentEvaluationControllerSyntheticCheckPassed(array $process, string $expectedOutput): bool
{
    return $process['exit_code'] === 0
        && !$process['timed_out']
        && !$process['output_limit_exceeded']
        && $process['termination_reason'] === 'completed'
        && agentEvaluationControllerProcessCleanupPassed($process['cleanup'])
        && $process['stdout'] === $expectedOutput
        && $process['stderr'] === '';
}

/**
 * @param array{
 *   process_group_created: bool,
 *   process_reaped: bool,
 *   process_group_absent: bool
 * } $cleanup
 */
function agentEvaluationControllerProcessCleanupPassed(array $cleanup): bool
{
    return $cleanup['process_group_created']
        && $cleanup['process_reaped']
        && $cleanup['process_group_absent'];
}

/** @return array{passed: bool, evidence: list<string>} */
function agentEvaluationControllerInspectPingResources(string $candidateRoot): array
{
    $path = $candidateRoot . '/src/PingHandler.php';
    $resolved = realpath($path);
    $prefix = $candidateRoot . DIRECTORY_SEPARATOR;
    $evidence = [];

    if (
        !is_string($resolved)
        || !str_starts_with($resolved, $prefix)
        || !is_file($resolved)
        || is_link($path)
    ) {
        return [
            'passed' => false,
            'evidence' => ['FAIL ping_handler_regular_file'],
        ];
    }

    $bytes = filesize($resolved);

    if (!is_int($bytes) || $bytes > AGENT_EVALUATION_CONTROLLER_RESOURCE_SOURCE_BYTES) {
        return [
            'passed' => false,
            'evidence' => ['FAIL ping_handler_source_bound'],
        ];
    }

    $source = file_get_contents($resolved);

    if (!is_string($source)) {
        return [
            'passed' => false,
            'evidence' => ['FAIL ping_handler_read'],
        ];
    }

    $evidence[] = 'PASS ping_handler_regular_file';
    $structurePasses = str_contains($source, 'final class PingHandler implements RequestHandler')
        && substr_count($source, 'function handle(') === 1
        && !str_contains($source, '__construct')
        && substr_count($source, 'new Response(') === 1;
    $evidence[] = ($structurePasses ? 'PASS' : 'FAIL') . ' ping_handler_dependency_free';
    try {
        $tokens = token_get_all($source, TOKEN_PARSE);
    } catch (ParseError) {
        return [
            'passed' => false,
            'evidence' => [...$evidence, 'FAIL ping_handler_parse'],
        ];
    }
    $forbiddenTokenIds = [
        T_EVAL,
        T_EXIT,
        T_GLOBAL,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_YIELD,
        T_YIELD_FROM,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];
    $forbiddenNames = [
        'cache',
        'connection',
        'copy',
        'curl_exec',
        'curl_init',
        'exec',
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fsockopen',
        'fwrite',
        'getenv',
        'mkdir',
        'passthru',
        'pcntl_exec',
        'pdo',
        'popen',
        'proc_open',
        'putenv',
        'redis',
        'rename',
        'session',
        'shell_exec',
        'sleep',
        'stream_socket_client',
        'system',
        'unlink',
        'usleep',
    ];
    $prohibitedIo = false;
    $newCount = 0;
    $functionCount = 0;

    foreach ($tokens as $token) {
        if (is_string($token)) {
            if ($token === '`') {
                $prohibitedIo = true;
            }

            continue;
        }

        [$tokenId, $tokenText] = $token;

        if (in_array($tokenId, $forbiddenTokenIds, true)) {
            $prohibitedIo = true;
        }

        if ($tokenId === T_STRING && in_array(strtolower($tokenText), $forbiddenNames, true)) {
            $prohibitedIo = true;
        }

        if ($tokenId === T_NEW) {
            $newCount++;
        }

        if ($tokenId === T_FUNCTION) {
            $functionCount++;
        }
    }

    $ioPasses = !$prohibitedIo && $newCount === 1 && $functionCount === 1;
    $evidence[] = ($ioPasses ? 'PASS' : 'FAIL') . ' ping_handler_no_prohibited_io';
    $responsePasses = str_contains($source, 'status: 200')
        && str_contains($source, "'Content-Type' => 'application/json; charset=utf-8'")
        && str_contains($source, "'Cache-Control' => 'no-store'")
        && str_contains($source, 'body: "{\\"status\\":\\"pong\\"}\\n"');
    $evidence[] = ($responsePasses ? 'PASS' : 'FAIL') . ' ping_handler_fixed_response';

    return [
        'passed' => $structurePasses && $ioPasses && $responsePasses,
        'evidence' => $evidence,
    ];
}
