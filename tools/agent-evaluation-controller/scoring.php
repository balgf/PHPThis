<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_LIVE_SCORING_UNAVAILABLE = 'AGENT_EVALUATION_CONTROLLER_LIVE_SCORING_UNAVAILABLE';
const AGENT_EVALUATION_CONTROLLER_RESOURCE_SOURCE_BYTES = 32_768;

/**
 * @param array<string, mixed> $resources
 * @param array<string, mixed> $holdout
 * @return array{application_gate:string,cases:list<array<string,mixed>>,scaling:list<array<string,mixed>>,automated_status:string}
 */
function agentEvaluationControllerScoreComparisonCandidate(array &$resources, string $frozenRoot, array $holdout, string $evidenceRoot): array
{
    if (($resources['generation_destroyed'] ?? null) !== true) {
        throw new RuntimeException('Comparison observations require verified generation destruction after freeze.');
    }
    $before = agentEvaluationControllerDescribeTree($frozenRoot, 'comparison frozen input', true);
    $application = agentEvaluationControllerOciRunApplicationCheck($resources, $frozenRoot);
    agentEvaluationControllerWriteArtifact($evidenceRoot, 'application-check.json', agentEvaluationControllerComparisonProcessJson($application));
    $results = agentEvaluationControllerEmptyComparisonResults($holdout);
    $results['application_gate'] = agentEvaluationControllerLiveCheckPassed($application) ? 'pass' : 'fail';
    $process = $application;
    foreach (agentEvaluationRequireList($holdout, 'cases', 'comparison holdout') as $index => $value) {
        if (($process['container_destroyed'] ?? null) !== true) {
            // Retain the completed prefix and explicit unavailable remainder;
            // never start another container while resource ownership is unsettled.
            break;
        }
        $case = agentEvaluationValueObject($value, 'comparison case');
        $input = agentEvaluationRequireObject($case, 'input', 'comparison case');
        $process = agentEvaluationControllerOciRunObservation($resources, $frozenRoot, agentEvaluationJson($input));
        agentEvaluationControllerWriteArtifact($evidenceRoot, sprintf('observation-%02d.json', $index + 1), agentEvaluationControllerComparisonProcessJson($process));
        $results['cases'][$index] = [
            'id' => agentEvaluationRequireString($case, 'id', 'comparison case'),
            'process_admissible' => agentEvaluationControllerLiveCheckPassed($process),
            ...agentEvaluationControllerCompareObservation(
                agentEvaluationRequireString($process, 'stdout', 'comparison process'),
                agentEvaluationRequireObject($case, 'expect', 'comparison case'),
            ),
        ];
    }
    $results['scaling'] = agentEvaluationControllerComparisonScaling($holdout, $results['cases']);
    $passed = $results['application_gate'] === 'pass';
    foreach ($results['cases'] as $case) {
        foreach (['process_admissible', 'observation_valid', 'response', 'policy_order', 'durable_state', 'transaction_closed', 'query_bounds'] as $name) {
            $passed = $passed && $case[$name] === true;
        }
    }
    foreach ($results['scaling'] as $group) {
        $passed = $passed && $group['passed'] === true;
    }
    $results['automated_status'] = $passed ? 'pass' : 'fail';
    agentEvaluationControllerWriteArtifact($evidenceRoot, 'observation-results.json', agentEvaluationJson($results));
    $after = agentEvaluationControllerDescribeTree($frozenRoot, 'post-comparison frozen input', true);
    if ($before['manifest'] !== $after['manifest']) {
        throw new RuntimeException('Comparison frozen candidate changed during scoring.');
    }
    return $results;
}

/**
 * @param array<string, mixed> $holdout
 * @return array{application_gate:string,cases:list<array<string,mixed>>,scaling:list<array<string,mixed>>,automated_status:string}
 */
function agentEvaluationControllerEmptyComparisonResults(array $holdout): array
{
    $cases = [];
    foreach (agentEvaluationRequireList($holdout, 'cases', 'comparison holdout') as $value) {
        $case = agentEvaluationValueObject($value, 'comparison case');
        $cases[] = ['id' => agentEvaluationRequireString($case, 'id', 'comparison case'), 'process_admissible' => false,
            ...agentEvaluationControllerCompareObservation('', agentEvaluationRequireObject($case, 'expect', 'comparison case'))];
    }
    return ['application_gate' => 'unavailable', 'cases' => $cases,
        'scaling' => agentEvaluationControllerComparisonScaling($holdout, $cases), 'automated_status' => 'fail'];
}

/**
 * @param array<string, mixed> $holdout
 * @param list<array<string, mixed>> $cases
 * @return list<array<string, mixed>>
 */
function agentEvaluationControllerComparisonScaling(array $holdout, array $cases): array
{
    $counts = [];
    foreach ($cases as $case) {
        $count = $case['statements'] ?? null;
        if ($count !== null && !is_int($count)) {
            throw new RuntimeException('Comparison scaling requires observed integer counts or null.');
        }
        $counts[agentEvaluationRequireString($case, 'id', 'comparison case')] = $count;
    }
    $results = [];
    foreach (agentEvaluationRequireList($holdout, 'scaling_groups', 'comparison holdout') as $value) {
        $group = agentEvaluationValueObject($value, 'comparison scaling group');
        $ids = agentEvaluationRequireStringList($group, 'case_ids', 'comparison scaling group');
        $observed = [];
        $known = [];
        foreach ($ids as $id) {
            $count = $counts[$id] ?? null;
            $observed[] = $count;
            if ($count !== null) {
                $known[] = $count;
            }
        }
        $results[] = ['id' => agentEvaluationRequireString($group, 'id', 'comparison scaling group'),
            'case_ids' => $ids, 'counts' => $observed,
            'passed' => $known !== [] && count($known) === count($observed) && max($known) === min($known)];
    }
    return $results;
}

/**
 * @param array<string, mixed> $task
 * @return array<string, mixed>
 */
function agentEvaluationControllerReadComparisonHoldout(string $path, array $task): array
{
    $checks = agentEvaluationRequireObject($task, 'checks', 'comparison task');
    $identity = agentEvaluationRequireObject($checks, 'holdout', 'comparison task');
    agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_JSON_BYTES, 'private comparison holdout');
    $bytes = file_get_contents($path, false, null, 0, AGENT_EVALUATION_MAX_JSON_BYTES + 1);
    if (!is_string($bytes) || strlen($bytes) > AGENT_EVALUATION_MAX_JSON_BYTES
        || !hash_equals(agentEvaluationRequireString($identity, 'sha256', 'comparison holdout'), hash('sha256', $bytes))
    ) {
        throw new RuntimeException('Private comparison holdout bytes do not match their bounded frozen identity.');
    }
    $holdout = agentEvaluationValueObject(agentEvaluationJsonValue($bytes, 'private comparison holdout'), 'private comparison holdout');
    agentEvaluationRequireExactKeys($holdout, ['schema_version', 'id', 'revision', 'task_id', 'cases', 'scaling_groups'], 'comparison holdout');
    if ($holdout['schema_version'] !== 1 || $holdout['id'] !== $identity['id']
        || $holdout['revision'] !== $identity['revision'] || $holdout['task_id'] !== $task['id']
    ) {
        throw new RuntimeException('Private comparison holdout identity does not match its predeclared task.');
    }
    $cases = agentEvaluationRequireList($holdout, 'cases', 'comparison holdout');
    if (count($cases) < 1 || count($cases) > 64) {
        throw new RuntimeException('Private comparison holdout requires between one and 64 cases.');
    }
    $seen = [];
    foreach ($cases as $value) {
        $case = agentEvaluationValueObject($value, 'private holdout case');
        agentEvaluationRequireExactKeys($case, ['id', 'input', 'expect'], 'private holdout case');
        $id = agentEvaluationRequireString($case, 'id', 'private holdout case');
        if (preg_match('/\A[a-z][a-z0-9.-]{0,95}\z/D', $id) !== 1 || isset($seen[$id])) {
            throw new RuntimeException('Private holdout case IDs must be unique bounded labels.');
        }
        $seen[$id] = true;
        agentEvaluationRequireObject($case, 'input', 'private holdout case');
        $expected = agentEvaluationRequireObject($case, 'expect', 'private holdout case');
        agentEvaluationRequireExactKeys($expected, ['response', 'policy_steps', 'query', 'durable_state', 'in_transaction'], 'private holdout expectation');
        $response = agentEvaluationRequireObject($expected, 'response', 'private holdout expectation');
        agentEvaluationRequireExactKeys($response, ['status', 'headers', 'body'], 'private holdout response');
        $status = agentEvaluationRequireInteger($response, 'status', 'private holdout response');
        if ($status < 100 || $status > 599) {
            throw new RuntimeException('Private holdout status must be an HTTP status.');
        }
        agentEvaluationRequireObject($response, 'headers', 'private holdout response');
        agentEvaluationRequireObject($response, 'body', 'private holdout response');
        agentEvaluationRequireStringList($expected, 'policy_steps', 'private holdout expectation');
        agentEvaluationRequireObject($expected, 'durable_state', 'private holdout expectation');
        if (agentEvaluationRequireBoolean($expected, 'in_transaction', 'private holdout expectation')) {
            throw new RuntimeException('Private holdout must require a closed transaction.');
        }
        $query = agentEvaluationRequireObject($expected, 'query', 'private holdout expectation');
        agentEvaluationRequireExactKeys($query, ['min_statements', 'max_statements', 'failures', 'max_fingerprint_executions'], 'private query expectation');
        foreach (['min_statements', 'max_statements', 'failures', 'max_fingerprint_executions'] as $name) {
            if (agentEvaluationRequireNonNegativeInteger($query, $name, 'private query expectation') > 64) {
                throw new RuntimeException('Private query expectation exceeds its finite bound.');
            }
        }
        if ($query['min_statements'] > $query['max_statements']) {
            throw new RuntimeException('Private query expectation minimum exceeds its maximum.');
        }
    }
    $groups = agentEvaluationRequireList($holdout, 'scaling_groups', 'comparison holdout');
    if (count($groups) > 16) {
        throw new RuntimeException('Private holdout has too many scaling groups.');
    }
    $groupIds = [];
    foreach ($groups as $value) {
        $group = agentEvaluationValueObject($value, 'private scaling group');
        agentEvaluationRequireExactKeys($group, ['id', 'case_ids', 'max_statement_growth'], 'private scaling group');
        $id = agentEvaluationRequireString($group, 'id', 'private scaling group');
        $ids = agentEvaluationRequireStringList($group, 'case_ids', 'private scaling group');
        if (preg_match('/\A[a-z][a-z0-9.-]{0,95}\z/D', $id) !== 1 || isset($groupIds[$id])
            || count($ids) < 2 || count($ids) > 64 || count(array_unique($ids)) !== count($ids)
            || agentEvaluationRequireInteger($group, 'max_statement_growth', 'private scaling group') !== 0
        ) {
            throw new RuntimeException('Private scaling group must compare distinct cases with zero statement growth.');
        }
        $groupIds[$id] = true;
        foreach ($ids as $caseId) {
            if (!isset($seen[$caseId])) {
                throw new RuntimeException('Private scaling group references an unknown case.');
            }
        }
    }
    return $holdout;
}

/**
 * Expected observations remain in the host. Raw candidate output is never a
 * verdict and an empty successful process cannot satisfy a holdout case.
 *
 * @param array<string, mixed> $expected
 * @return array{observation_valid:bool,response:bool,policy_order:bool,durable_state:bool,transaction_closed:bool,query_bounds:bool,statements:int|null}
 */
function agentEvaluationControllerCompareObservation(string $raw, array $expected): array
{
    $result = ['observation_valid' => false, 'response' => false, 'policy_order' => false,
        'durable_state' => false, 'transaction_closed' => false, 'query_bounds' => false, 'statements' => null];
    try {
        $observed = agentEvaluationValueObject(agentEvaluationJsonValue($raw, 'candidate observation'), 'candidate observation');
        agentEvaluationRequireExactKeys($observed, ['schema_version', 'response', 'policy_steps', 'query_trace', 'durable_state', 'in_transaction'], 'candidate observation');
        if (agentEvaluationRequireInteger($observed, 'schema_version', 'candidate observation') !== 1) {
            throw new RuntimeException('Candidate observation must use its fixed version-1 format.');
        }
        $response = agentEvaluationRequireObject($observed, 'response', 'candidate observation');
        agentEvaluationRequireExactKeys($response, ['status', 'headers', 'body'], 'candidate observation response');
        $expectedResponse = agentEvaluationRequireObject($expected, 'response', 'private response expectation');
        $headers = agentEvaluationRequireObject($response, 'headers', 'candidate response');
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $key = strtolower($name);
            if (isset($normalizedHeaders[$key]) || !is_string($value)) {
                throw new RuntimeException('Candidate response headers must have unique string values.');
            }
            $normalizedHeaders[$key] = $value;
        }
        $headersMatch = true;
        foreach (agentEvaluationRequireObject($expectedResponse, 'headers', 'private response expectation') as $name => $value) {
            $headersMatch = $headersMatch && ($normalizedHeaders[strtolower($name)] ?? null) === $value;
        }
        $body = agentEvaluationJsonValue(agentEvaluationRequireString($response, 'body', 'candidate response'), 'candidate response body');
        $result['response'] = agentEvaluationRequireInteger($response, 'status', 'candidate response') === $expectedResponse['status']
            && $headersMatch && agentEvaluationControllerCanonicalObservation($body) === agentEvaluationControllerCanonicalObservation($expectedResponse['body']);
        $result['policy_order'] = agentEvaluationRequireStringList($observed, 'policy_steps', 'candidate observation')
            === agentEvaluationRequireStringList($expected, 'policy_steps', 'private expectation');
        $result['durable_state'] = agentEvaluationControllerCanonicalObservation(agentEvaluationRequireObject($observed, 'durable_state', 'candidate observation'))
            === agentEvaluationControllerCanonicalObservation(agentEvaluationRequireObject($expected, 'durable_state', 'private expectation'));
        $result['transaction_closed'] = !agentEvaluationRequireBoolean($observed, 'in_transaction', 'candidate observation');
        $trace = agentEvaluationRequireObject($observed, 'query_trace', 'candidate observation');
        agentEvaluationRequireExactKeys($trace, ['statements', 'failures', 'queries', 'truncated'], 'candidate query trace');
        $count = agentEvaluationRequireNonNegativeInteger($trace, 'statements', 'candidate query trace');
        $failures = agentEvaluationRequireNonNegativeInteger($trace, 'failures', 'candidate query trace');
        $queries = agentEvaluationRequireList($trace, 'queries', 'candidate query trace');
        if ($count > 100_000 || $failures > $count || count($queries) > 64) {
            throw new RuntimeException('Candidate query trace exceeds its bounded observation shape.');
        }
        $seen = [];
        $sum = 0;
        $sumFailures = 0;
        $maximum = 0;
        foreach ($queries as $value) {
            $query = agentEvaluationValueObject($value, 'candidate query entry');
            agentEvaluationRequireExactKeys($query, ['fingerprint', 'executions', 'failures'], 'candidate query entry');
            $fingerprint = agentEvaluationRequireString($query, 'fingerprint', 'candidate query entry');
            $executions = agentEvaluationRequirePositiveInteger($query, 'executions', 'candidate query entry');
            $queryFailures = agentEvaluationRequireNonNegativeInteger($query, 'failures', 'candidate query entry');
            if (preg_match('/\Asha256:[a-f0-9]{64}\z/D', $fingerprint) !== 1 || isset($seen[$fingerprint])
                || $executions > 100_000 || $queryFailures > $executions
            ) {
                throw new RuntimeException('Candidate query entries must be unique and internally consistent.');
            }
            $seen[$fingerprint] = true;
            $sum += $executions;
            $sumFailures += $queryFailures;
            $maximum = max($maximum, $executions);
        }
        $queryExpectation = agentEvaluationRequireObject($expected, 'query', 'private query expectation');
        $result['statements'] = $count;
        $result['query_bounds'] = !agentEvaluationRequireBoolean($trace, 'truncated', 'candidate query trace')
            && $sum === $count && $sumFailures === $failures
            && $count >= agentEvaluationRequireInteger($queryExpectation, 'min_statements', 'private query expectation')
            && $count <= agentEvaluationRequireInteger($queryExpectation, 'max_statements', 'private query expectation')
            && $failures === agentEvaluationRequireInteger($queryExpectation, 'failures', 'private query expectation')
            && $maximum <= agentEvaluationRequireInteger($queryExpectation, 'max_fingerprint_executions', 'private query expectation');
        $result['observation_valid'] = true;
        return $result;
    } catch (JsonException|RuntimeException) {
        return $result;
    }
}

function agentEvaluationControllerCanonicalObservation(mixed $value): mixed
{
    if ($value instanceof stdClass) {
        $properties = get_object_vars($value);
        ksort($properties, SORT_STRING);
        return ['object', array_map(agentEvaluationControllerCanonicalObservation(...), $properties)];
    }
    if (is_array($value)) {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
            return ['object', array_map(agentEvaluationControllerCanonicalObservation(...), $value)];
        }
        return ['array', array_map(agentEvaluationControllerCanonicalObservation(...), $value)];
    }
    return ['scalar', $value];
}

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

/** @param array<string,mixed> $process */
function agentEvaluationControllerComparisonProcessJson(array $process): string
{
    // Base64 preserves arbitrary bounded candidate bytes without UTF-8 failures
    // or sixfold JSON escaping growth for adversarial control-byte streams.
    return agentEvaluationJson([...$process,
        'stdout' => base64_encode(agentEvaluationRequireString($process, 'stdout', 'comparison process')),
        'stderr' => base64_encode(agentEvaluationRequireString($process, 'stderr', 'comparison process')),
        'stream_encoding' => 'base64']);
}
