<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $score
 * @param array<string, mixed> $attempt
 */
function agentEvaluationValidateComparisonScoreRecord(array $score, array $attempt, string $attemptHash, string $holdoutBytes): void
{
    $owner = 'comparison score';
    agentEvaluationRequireExactKeys($score, [
        'schema_version', 'campaign_id', 'slot', 'run_id', 'task_id', 'condition',
        'attempt_sha256', 'holdout_sha256', 'admissible', 'application_gate',
        'cases', 'scaling', 'automated_status', 'human_review', 'correct_completion',
    ], $owner);
    if (agentEvaluationRequireInteger($score, 'schema_version', $owner) !== 2) {
        throw new RuntimeException('Comparison score must use its separate version-2 schema.');
    }
    foreach (['campaign_id', 'slot', 'run_id', 'task_id', 'condition', 'holdout_sha256'] as $name) {
        if ($score[$name] !== ($attempt[$name] ?? null)) {
            throw new RuntimeException('Comparison score must bind its exact attempt identity.');
        }
    }
    if (agentEvaluationRequireHash(agentEvaluationRequireString($score, 'attempt_sha256', $owner), $owner) !== $attemptHash) {
        throw new RuntimeException('Comparison score does not bind the retained attempt bytes.');
    }
    if (strlen($holdoutBytes) > AGENT_EVALUATION_MAX_JSON_BYTES
        || hash('sha256', $holdoutBytes) !== ($attempt['holdout_sha256'] ?? null)
    ) {
        throw new RuntimeException('Comparison score validation requires the exact bounded private holdout bytes.');
    }
    $holdout = agentEvaluationValueObject(agentEvaluationJsonValue($holdoutBytes, 'comparison score holdout'), 'comparison score holdout');
    if (($holdout['task_id'] ?? null) !== $score['task_id']) {
        throw new RuntimeException('Comparison score holdout task does not match its attempt.');
    }
    $expectedCases = agentEvaluationRequireList($holdout, 'cases', 'comparison score holdout');
    $expectedGroups = agentEvaluationRequireList($holdout, 'scaling_groups', 'comparison score holdout');
    $admissible = agentEvaluationRequireBoolean($score, 'admissible', $owner);
    $applicationGate = agentEvaluationRequireString($score, 'application_gate', $owner);
    if (!in_array($applicationGate, ['pass', 'fail', 'unavailable'], true)) {
        throw new RuntimeException('Comparison application gate result is invalid.');
    }
    $cases = agentEvaluationRequireList($score, 'cases', $owner);
    $scaling = agentEvaluationRequireList($score, 'scaling', $owner);
    if (count($cases) < 1 || count($cases) > 64 || count($scaling) > 16
        || count($cases) !== count($expectedCases) || count($scaling) !== count($expectedGroups)
    ) {
        throw new RuntimeException('Comparison score exceeds its fixed case or scaling bounds.');
    }
    $allPassed = $admissible && $applicationGate === 'pass';
    $seen = [];
    $caseCounts = [];
    foreach ($cases as $index => $value) {
        $case = agentEvaluationValueObject($value, $owner . ' case');
        agentEvaluationRequireExactKeys($case, [
            'id', 'process_admissible', 'observation_valid', 'response', 'policy_order',
            'durable_state', 'transaction_closed', 'query_bounds', 'statements',
        ], $owner . ' case');
        $id = agentEvaluationRequireString($case, 'id', $owner . ' case');
        $expectedCase = agentEvaluationValueObject($expectedCases[$index], 'comparison expected case');
        if (preg_match('/\A[a-z][a-z0-9.-]{0,95}\z/D', $id) !== 1 || isset($seen[$id]) || $id !== ($expectedCase['id'] ?? null)) {
            throw new RuntimeException('Comparison score case IDs must be unique bounded labels.');
        }
        $seen[$id] = true;
        foreach (['process_admissible', 'observation_valid', 'response', 'policy_order', 'durable_state', 'transaction_closed', 'query_bounds'] as $name) {
            $passed = agentEvaluationRequireBoolean($case, $name, $owner . ' case');
            $allPassed = $allPassed && $passed;
        }
        $statements = $case['statements'];
        if ($statements !== null && (!is_int($statements) || $statements < 0 || $statements > 100_000)) {
            throw new RuntimeException('Comparison statement observation must be a bounded count or null.');
        }
        if ($case['query_bounds'] === true && ($case['observation_valid'] !== true || $statements === null)) {
            throw new RuntimeException('Comparison resource success requires a valid count observation.');
        }
        $expectation = agentEvaluationRequireObject($expectedCase, 'expect', 'comparison expected case');
        $query = agentEvaluationRequireObject($expectation, 'query', 'comparison expected case');
        if ($case['query_bounds'] === true && ($statements < agentEvaluationRequireInteger($query, 'min_statements', 'comparison expected query')
            || $statements > agentEvaluationRequireInteger($query, 'max_statements', 'comparison expected query'))
        ) {
            throw new RuntimeException('Comparison resource success contradicts its observed statement count.');
        }
        $caseCounts[$id] = $statements;
    }
    $seenGroups = [];
    foreach ($scaling as $index => $value) {
        $group = agentEvaluationValueObject($value, $owner . ' scaling');
        agentEvaluationRequireExactKeys($group, ['id', 'case_ids', 'counts', 'passed'], $owner . ' scaling');
        $id = agentEvaluationRequireString($group, 'id', $owner . ' scaling');
        $caseIds = agentEvaluationRequireStringList($group, 'case_ids', $owner . ' scaling');
        $counts = agentEvaluationRequireList($group, 'counts', $owner . ' scaling');
        $expectedGroup = agentEvaluationValueObject($expectedGroups[$index], 'comparison expected scaling group');
        if (preg_match('/\A[a-z][a-z0-9.-]{0,95}\z/D', $id) !== 1 || isset($seenGroups[$id])
            || count($caseIds) < 2 || count($caseIds) > 64 || count($counts) !== count($caseIds)
            || count(array_unique($caseIds)) !== count($caseIds)
            || $id !== ($expectedGroup['id'] ?? null)
            || $caseIds !== agentEvaluationRequireStringList($expectedGroup, 'case_ids', 'comparison expected scaling group')
        ) {
            throw new RuntimeException('Comparison scaling score requires a unique bounded set of case observations.');
        }
        $seenGroups[$id] = true;
        foreach ($caseIds as $caseIndex => $caseId) {
            if (!isset($seen[$caseId]) || $counts[$caseIndex] !== $caseCounts[$caseId]) {
                throw new RuntimeException('Comparison scaling score references an absent case.');
            }
        }
        $known = [];
        foreach ($counts as $count) {
            if ($count !== null && (!is_int($count) || $count < 0 || $count > 100_000)) {
                throw new RuntimeException('Comparison scaling counts must be bounded observations or null.');
            }
            if ($count !== null) {
                $known[] = $count;
            }
        }
        $passed = agentEvaluationRequireBoolean($group, 'passed', $owner . ' scaling');
        $derived = count($known) === count($counts) && max($known) === min($known);
        if ($passed !== $derived) {
            throw new RuntimeException('Comparison scaling status must equal its fixed zero-growth rule.');
        }
        $allPassed = $allPassed && $passed;
    }
    $status = agentEvaluationRequireString($score, 'automated_status', $owner);
    $expectedStatus = $allPassed ? 'pass' : 'fail';
    if ($status !== $expectedStatus) {
        throw new RuntimeException('Comparison automated status must be derived from every mandatory result.');
    }
    if ($admissible && ($attempt['status'] ?? null) !== 'complete') {
        throw new RuntimeException('An incomplete or failed execution cannot claim comparison admissibility.');
    }
    $review = agentEvaluationRequireObject($score, 'human_review', $owner);
    agentEvaluationRequireExactKeys($review, [
        'status', 'reviewer', 'semantic_correctness', 'instrumentation_integrity',
        'review_seconds', 'justified_interventions', 'unnecessary_interventions', 'public_check_repairs', 'reason',
    ], $owner . ' human review');
    $reviewStatus = agentEvaluationRequireString($review, 'status', $owner . ' human review');
    if (!in_array($reviewStatus, ['pending', 'pass', 'fail'], true)) {
        throw new RuntimeException('Comparison human review must remain separate from automated scoring.');
    }
    $reviewer = agentEvaluationRequireNullableString($review, 'reviewer', $owner . ' human review');
    foreach (['semantic_correctness', 'instrumentation_integrity'] as $name) {
        if ($review[$name] !== null && !is_bool($review[$name])) {
            throw new RuntimeException('Comparison review decisions must be Boolean or explicitly unknown.');
        }
    }
    foreach (['review_seconds', 'justified_interventions', 'unnecessary_interventions', 'public_check_repairs'] as $name) {
        $value = $review[$name];
        if ($value !== null && (!is_int($value) || $value < 0 || $value > 86_400)) {
            throw new RuntimeException('Comparison reviewer metrics must be observed bounded counts or null.');
        }
    }
    $reason = agentEvaluationRequireNonEmptyString($review, 'reason', $owner . ' human review');
    if (strlen($reason) > 2_048 || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1
        || ($reviewer !== null && (strlen($reviewer) > 128 || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.@-]*\z/D', $reviewer) !== 1))
    ) {
        throw new RuntimeException('Comparison human review identity and explanation must be bounded.');
    }
    if ($reviewStatus === 'pending') {
        foreach (['reviewer', 'semantic_correctness', 'instrumentation_integrity', 'review_seconds', 'justified_interventions', 'unnecessary_interventions', 'public_check_repairs'] as $name) {
            if ($review[$name] !== null) {
                throw new RuntimeException('A pending human review cannot fabricate reviewer decisions or effort.');
            }
        }
    } elseif ($reviewer === null || !is_bool($review['semantic_correctness']) || !is_bool($review['instrumentation_integrity'])) {
        throw new RuntimeException('A completed human review requires its accountable reviewer and both decisions.');
    } elseif (($reviewStatus === 'pass') !== ($review['semantic_correctness'] && $review['instrumentation_integrity'])) {
        throw new RuntimeException('Human review status must match semantic and instrumentation decisions.');
    }
    $correct = $reviewStatus === 'pending' ? null : ($status === 'pass' && $reviewStatus === 'pass');
    if ($score['correct_completion'] !== $correct) {
        throw new RuntimeException('Correct completion must include the separate human semantic and instrumentation review.');
    }
}

/**
 * @param array<string, mixed> $record
 * @param array{
 *   id: string,
 *   revision: int,
 *   prompt: array{sha256: string},
 *   public_scorer: array{sha256: string}
 * } $task
 * @param array<string, mixed> $runRecord
 */
function agentEvaluationValidateScoreRecord(
    array $record,
    array $task,
    array $runRecord,
    string $runRecordHash,
): void
{
    agentEvaluationRequireExactKeys(
        $record,
        [
            'schema_version',
            'run_id',
            'task_id',
            'task_revision',
            'run_record_sha256',
            'prompt_sha256',
            'scorer_sha256',
            'candidate_patch_sha256',
            'admissible',
            'mandatory_checks',
            'dimensions',
            'weighted_score',
            'automated_status',
            'human_review',
            'notes',
        ],
        'score record',
    );

    if (agentEvaluationRequireInteger($record, 'schema_version', 'score record') !== 1) {
        throw new RuntimeException('Score record must use schema version 1.');
    }

    $runId = agentEvaluationRequireNonEmptyString($record, 'run_id', 'score record');

    if (($runRecord['run_id'] ?? null) !== $runId) {
        throw new RuntimeException('Score record run ID does not match the validated run record.');
    }

    if (agentEvaluationRequireString($record, 'task_id', 'score record') !== $task['id']) {
        throw new RuntimeException('Score record task ID does not match the selected task.');
    }

    if (agentEvaluationRequireInteger($record, 'task_revision', 'score record') !== $task['revision']) {
        throw new RuntimeException('Score record task revision does not match the selected task.');
    }

    if (agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'run_record_sha256', 'score record'),
        'score record run record',
    ) !== $runRecordHash) {
        throw new RuntimeException('Score record run-record hash does not match the validated run record.');
    }

    if (agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'prompt_sha256', 'score record'),
        'score record prompt',
    ) !== $task['prompt']['sha256']) {
        throw new RuntimeException('Score record prompt hash does not match the selected task.');
    }

    if (agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'scorer_sha256', 'score record'),
        'score record scorer',
    ) !== $task['public_scorer']['sha256']) {
        throw new RuntimeException('Score record scorer hash does not match the selected task.');
    }

    $candidatePatchHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($record, 'candidate_patch_sha256', 'score record'),
        'score record candidate patch',
    );

    if (
        !is_string($runRecord['candidate_patch_sha256'] ?? null)
        || $runRecord['candidate_patch_sha256'] !== $candidatePatchHash
    ) {
        throw new RuntimeException('Score record candidate patch hash does not match the validated run record.');
    }
    $admissible = agentEvaluationRequireBoolean($record, 'admissible', 'score record');
    $checks = agentEvaluationRequireObject($record, 'mandatory_checks', 'score record');
    agentEvaluationRequireExactKeys(
        $checks,
        ['manifest_valid', 'workspace_policy', 'application_check', 'public_scorer', 'resource_bounds'],
        'score record mandatory checks',
    );

    $allChecksPass = true;

    foreach ($checks as $name => $passed) {
        if ($name === '' || !is_bool($passed)) {
            throw new RuntimeException('Score record mandatory checks must map names to booleans.');
        }

        if (!$passed) {
            $allChecksPass = false;
        }
    }

    if (
        $admissible
        && (($checks['manifest_valid'] ?? null) !== true || ($checks['workspace_policy'] ?? null) !== true)
    ) {
        throw new RuntimeException('An admissible score requires valid manifests and workspace policy.');
    }

    $dimensions = agentEvaluationRequireObject($record, 'dimensions', 'score record');
    agentEvaluationRequireExactKeys(
        $dimensions,
        [
            'observable_behavior',
            'boundary_behavior',
            'resource_bounds',
            'application_gate',
            'change_locality',
        ],
        'score record dimensions',
    );

    $observableBehavior = agentEvaluationScoreDimension($dimensions, 'observable_behavior');
    $boundaryBehavior = agentEvaluationScoreDimension($dimensions, 'boundary_behavior');
    $resourceBounds = agentEvaluationScoreDimension($dimensions, 'resource_bounds');
    $applicationGate = agentEvaluationScoreDimension($dimensions, 'application_gate');
    $changeLocality = agentEvaluationScoreDimension($dimensions, 'change_locality');

    if (
        ($checks['public_scorer'] ?? null) === true
        && ($observableBehavior !== 100 || $boundaryBehavior !== 100)
    ) {
        throw new RuntimeException('A successful public scorer requires complete observable and boundary dimensions.');
    }

    if (
        ($checks['public_scorer'] ?? null) === false
        && $observableBehavior === 100
        && $boundaryBehavior === 100
    ) {
        throw new RuntimeException('A failed public scorer cannot retain complete observable and boundary dimensions.');
    }

    if (($checks['resource_bounds'] ?? null) === true && $resourceBounds !== 100) {
        throw new RuntimeException('A successful resource-bound check requires the complete resource dimension.');
    }

    if (($checks['resource_bounds'] ?? null) === false && $resourceBounds === 100) {
        throw new RuntimeException('A failed resource-bound check cannot retain the complete resource dimension.');
    }

    if (($checks['application_check'] ?? null) === true && $applicationGate !== 100) {
        throw new RuntimeException('A successful application check requires the complete application-gate dimension.');
    }

    if (($checks['application_check'] ?? null) === false && $applicationGate === 100) {
        throw new RuntimeException('A failed application check cannot retain the complete application-gate dimension.');
    }

    if (($checks['workspace_policy'] ?? null) === true && $changeLocality !== 100) {
        throw new RuntimeException('A successful workspace-policy check requires the complete change-locality dimension.');
    }

    if (($checks['workspace_policy'] ?? null) === false && $changeLocality === 100) {
        throw new RuntimeException('A failed workspace-policy check cannot retain the complete change-locality dimension.');
    }

    $weightedScore = agentEvaluationRequireInteger($record, 'weighted_score', 'score record');

    if ($weightedScore < 0 || $weightedScore > 100) {
        throw new RuntimeException('Weighted score must be an integer from 0 through 100.');
    }

    $expectedWeightedScore = intdiv(
        ($observableBehavior * 40)
        + ($boundaryBehavior * 20)
        + ($resourceBounds * 15)
        + ($applicationGate * 15)
        + ($changeLocality * 10),
        100,
    );

    if ($weightedScore !== $expectedWeightedScore) {
        throw new RuntimeException('Weighted score does not match the fixed evaluation dimensions.');
    }

    $status = agentEvaluationRequireString($record, 'automated_status', 'score record');

    if (!in_array($status, ['pass', 'fail'], true)) {
        throw new RuntimeException('Automated status must be pass or fail.');
    }

    $allCriticalDimensionsPass = $observableBehavior === 100
        && $boundaryBehavior === 100
        && $resourceBounds === 100
        && $applicationGate === 100
        && $changeLocality === 100;
    $expectedStatus = $admissible && $allChecksPass && $allCriticalDimensionsPass && $weightedScore >= 85
        ? 'pass'
        : 'fail';

    if ($status !== $expectedStatus) {
        throw new RuntimeException('Automated status does not match the admissibility, mandatory checks, and critical dimensions.');
    }

    $humanReview = agentEvaluationRequireString($record, 'human_review', 'score record');

    if (!in_array($humanReview, ['pending', 'pass', 'fail'], true)) {
        throw new RuntimeException('Human review must be pending, pass, or fail.');
    }

    agentEvaluationRequireStringList($record, 'notes', 'score record');
}

/** @param array<string, mixed> $dimensions */
function agentEvaluationScoreDimension(array $dimensions, string $name): int
{
    $value = agentEvaluationRequireInteger($dimensions, $name, 'score record dimensions');

    if ($value < 0 || $value > 100) {
        throw new RuntimeException("Score dimension {$name} must be an integer from 0 through 100.");
    }

    return $value;
}

/**
 * Aggregate already verified attempts. The fixed planned schedule remains the
 * denominator even when observations are missing or a run ends unsuccessfully.
 * @param list<array{slot:int,round:int,task_id:string,condition:string}> $schedule
 * @param list<array{slot:int,attempt:array<string,mixed>,score:array<string,mixed>|null,started:bool,final_retained:bool,metrics:array<string,int|null>}> $observations
 * @param array<string,mixed> $pricing
 * @return array<string,mixed>
 */
function agentEvaluationAggregateComparisonResults(array $schedule, array $observations, array $pricing): array
{
    if (count($schedule) !== 60 || count($observations) > 60) {
        throw new RuntimeException('Comparison aggregation requires the fixed sixty-slot schedule.');
    }
    $slots = [];
    $groups = [];
    foreach ($schedule as $index => $slot) {
        $pin = AGENT_EVALUATION_TASK_REVISIONS[$slot['task_id']] ?? null;
        if ($slot['slot'] !== $index + 1 || $slot['round'] !== intdiv($index, 6)
            || !is_array($pin) || $pin['schema_version'] !== 2 || !in_array($slot['condition'], ['phpthis', 'plain-php'], true)
        ) {
            throw new RuntimeException('Comparison aggregation schedule has an invalid fixed slot.');
        }
        $slots[$slot['slot']] = $slot;
        $groups[$slot['task_id'] . ':' . $slot['condition']][] = $slot['slot'];
    }
    if (count($groups) !== 6) {
        throw new RuntimeException('Comparison aggregation requires all six planned groups.');
    }
    foreach ($groups as $groupSlots) {
        if (count($groupSlots) !== 10) {
            throw new RuntimeException('Comparison group denominators must remain ten planned trials.');
        }
    }
    $bySlot = [];
    foreach ($observations as $observation) {
        $slot = $slots[$observation['slot']] ?? null;
        $attempt = $observation['attempt'];
        if ($slot === null || isset($bySlot[$observation['slot']])
            || ($attempt['slot'] ?? null) !== $slot['slot'] || ($attempt['task_id'] ?? null) !== $slot['task_id']
            || ($attempt['condition'] ?? null) !== $slot['condition']
        ) {
            throw new RuntimeException('Comparison aggregation cannot duplicate or replace a planned observation.');
        }
        $bySlot[$slot['slot']] = $observation;
    }
    $missing = [];
    $pending = [];
    $actual = 0;
    foreach ($slots as $number => $slot) {
        $observation = $bySlot[$number] ?? null;
        if ($observation === null || !$observation['final_retained'] || !$observation['started']
            || !in_array($observation['attempt']['status'] ?? null, ['complete', 'failed'], true)
            || $observation['score'] === null
        ) {
            $missing[] = $number;
            continue;
        }
        $actual++;
        $review = agentEvaluationRequireObject($observation['score'], 'human_review', 'comparison aggregation');
        if (($review['status'] ?? null) === 'pending' || !is_bool($observation['score']['correct_completion'] ?? null)) {
            $pending[] = $number;
        }
    }
    $complete = $actual === 60;
    $ratesAvailable = $complete && $pending === [];
    $rateReason = !$complete ? 'The campaign lacks sixty retained actual outcomes and scores.'
        : ($pending !== [] ? 'Accountable human semantic and instrumentation reviews remain pending.' : null);
    $reports = [];
    foreach ($groups as $key => $groupSlots) {
        $first = $slots[$groupSlots[0]];
        $rows = [];
        foreach ($groupSlots as $number) {
            if (isset($bySlot[$number])) {
                $rows[] = $bySlot[$number];
            }
        }
        $reports[$key] = ['task_id' => $first['task_id'], 'condition' => $first['condition'],
            ...agentEvaluationComparisonGroupSummary($rows, $ratesAvailable, $rateReason, $pricing)];
    }
    $differences = [];
    foreach (AGENT_EVALUATION_TASK_REVISIONS as $taskId => $pin) {
        if ($pin['schema_version'] !== 2) {
            continue;
        }
        $phpthis = agentEvaluationRequireObject($reports[$taskId . ':phpthis'], 'correct_completion', 'comparison group');
        $plain = agentEvaluationRequireObject($reports[$taskId . ':plain-php'], 'correct_completion', 'comparison group');
        $differences[] = ['task_id' => $taskId,
            'phpthis_minus_plain_php' => $ratesAvailable
                ? (agentEvaluationRequireInteger($phpthis, 'count', 'comparison group') - agentEvaluationRequireInteger($plain, 'count', 'comparison group')) / 10 : null,
            'reason' => $rateReason];
    }
    $totals = agentEvaluationComparisonGroupSummary(array_values($bySlot), false,
        'Pooled framework correctness rates are outside the fixed protocol.', $pricing);
    $totals['planned_denominator'] = 60;
    return ['planned_slots' => 60, 'actual_outcomes_with_scores' => $actual, 'complete' => $complete,
        'correctness_rates_available' => $ratesAvailable, 'rate_unavailable_reason' => $rateReason,
        'missing_or_unfinished_slots' => $missing, 'pending_review_slots' => $pending,
        'groups' => array_values($reports), 'task_differences' => $differences,
        'uncertainty_method' => 'Wilson score interval, z=1.959963984540054, 95 percent; fixed ten-trial groups only.',
        'usage_scope' => 'Retained started attempts only; unknown provider categories remain unavailable.',
        'totals' => $totals];
}

/**
 * @param list<array{slot:int,attempt:array<string,mixed>,score:array<string,mixed>|null,started:bool,final_retained:bool,metrics:array<string,int|null>}> $rows
 * @param array<string,mixed> $pricing
 * @return array<string,mixed>
 */
function agentEvaluationComparisonGroupSummary(array $rows, bool $ratesAvailable, ?string $rateReason, array $pricing): array
{
    $states = ['planned' => 0, 'running' => 0, 'complete' => 0, 'failed' => 0, 'not_run' => 0];
    $caseFlags = ['process_admissible', 'observation_valid', 'response', 'policy_order', 'durable_state', 'transaction_closed', 'query_bounds'];
    $components = [];
    foreach ($caseFlags as $name) {
        $components[$name] = ['pass' => 0, 'fail' => 0];
    }
    $gate = ['pass' => 0, 'fail' => 0, 'unavailable' => 0];
    $scaling = ['pass' => 0, 'fail' => 0];
    $correct = 0;
    $reviewed = 0;
    $automated = 0;
    $admissible = 0;
    $scores = 0;
    $started = 0;
    $finals = 0;
    $failures = [];
    $measurements = [];
    $metricNames = ['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens', 'generation_elapsed_milliseconds',
        'scoring_elapsed_milliseconds', 'changed_files', 'added_lines', 'deleted_lines',
        'review_seconds', 'justified_interventions', 'unnecessary_interventions', 'public_check_repairs'];
    foreach ($metricNames as $name) {
        $measurements[$name] = [];
    }
    $charges = [];
    foreach ($rows as $row) {
        $attempt = $row['attempt'];
        $status = agentEvaluationRequireString($attempt, 'status', 'comparison aggregation');
        if (!isset($states[$status])) {
            throw new RuntimeException('Comparison aggregation received an unknown lifecycle state.');
        }
        $states[$status]++;
        $finals += $row['final_retained'] ? 1 : 0;
        $score = $row['score'];
        $review = $score === null ? null : agentEvaluationRequireObject($score, 'human_review', 'comparison aggregation');
        if ($score !== null) {
            $scores++;
            $correct += ($score['correct_completion'] ?? null) === true ? 1 : 0;
            $reviewed += is_bool($score['correct_completion'] ?? null) ? 1 : 0;
            $automated += ($score['automated_status'] ?? null) === 'pass' ? 1 : 0;
            $admissible += ($score['admissible'] ?? null) === true ? 1 : 0;
            $application = agentEvaluationRequireString($score, 'application_gate', 'comparison aggregation');
            if (!isset($gate[$application])) {
                throw new RuntimeException('Comparison aggregation received an unknown application result.');
            }
            $gate[$application]++;
            foreach (agentEvaluationRequireList($score, 'cases', 'comparison aggregation') as $caseValue) {
                $case = agentEvaluationValueObject($caseValue, 'comparison aggregation case');
                foreach ($caseFlags as $name) {
                    $components[$name][agentEvaluationRequireBoolean($case, $name, 'comparison aggregation case') ? 'pass' : 'fail']++;
                }
            }
            foreach (agentEvaluationRequireList($score, 'scaling', 'comparison aggregation') as $groupValue) {
                $group = agentEvaluationValueObject($groupValue, 'comparison aggregation scaling');
                $scaling[agentEvaluationRequireBoolean($group, 'passed', 'comparison aggregation scaling') ? 'pass' : 'fail']++;
            }
        }
        if ($status === 'failed' || $status === 'not_run') {
            $failures[] = ['slot' => $row['slot'], 'status' => $status,
                'phase' => agentEvaluationRequireString($attempt, 'phase', 'comparison aggregation'),
                'termination_reason' => agentEvaluationRequireNullableString($attempt, 'termination_reason', 'comparison aggregation')];
        }
        if (!$row['started']) {
            continue;
        }
        $started++;
        $usage = agentEvaluationRequireObject($attempt, 'usage', 'comparison aggregation');
        foreach ($metricNames as $name) {
            $value = match ($name) {
                'input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens' => $usage[$name] ?? null,
                'generation_elapsed_milliseconds' => $attempt['elapsed_milliseconds'] ?? null,
                'review_seconds', 'justified_interventions', 'unnecessary_interventions', 'public_check_repairs' => $review[$name] ?? null,
                default => $row['metrics'][$name] ?? null,
            };
            if ($value !== null && (!is_int($value) || $value < 0)) {
                throw new RuntimeException('Comparison aggregation metrics must be observed nonnegative counts or null.');
            }
            $measurements[$name][] = $value;
        }
        $charges[] = agentEvaluationComparisonEstimatedCharge($usage, $pricing);
    }
    $metrics = [];
    foreach ($measurements as $name => $values) {
        $metrics[$name] = agentEvaluationComparisonObservedSum($values);
    }
    return ['planned_denominator' => 10, 'retained_rows' => count($rows), 'started_attempts' => $started,
        'retained_finals' => $finals, 'status_counts' => $states, 'scores' => $scores,
        'correct_completion' => ['count' => $correct, 'reviewed_outcomes' => $reviewed,
            'rate' => $ratesAvailable ? $correct / 10 : null,
            'wilson_95' => $ratesAvailable ? agentEvaluationComparisonWilsonInterval($correct, 10) : null,
            'reason' => $rateReason],
        'automated_passes' => $automated, 'admissible_attempts' => $admissible,
        'application_gate' => $gate, 'case_component_counts' => $components, 'scaling_group_counts' => $scaling,
        'failures' => $failures, 'metrics' => $metrics,
        'estimated_api_charge_usd' => agentEvaluationComparisonObservedChargeSum($charges),
        'charge_method' => '((input-cached)*input_cents_per_million + cached*cached_cents_per_million + output*output_cents_per_million) / 100000000; reasoning is included in output, never added again.'];
}

/** @return array{lower:float,upper:float} */
function agentEvaluationComparisonWilsonInterval(int $successes, int $trials): array
{
    if ($trials < 1 || $successes < 0 || $successes > $trials) {
        throw new RuntimeException('Comparison uncertainty requires bounded observed successes and trials.');
    }
    $z = 1.959963984540054;
    $p = $successes / $trials;
    $denominator = 1 + $z * $z / $trials;
    $center = ($p + $z * $z / (2 * $trials)) / $denominator;
    $radius = $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $trials)) / $trials) / $denominator;
    return ['lower' => $successes === 0 ? 0.0 : max(0.0, $center - $radius),
        'upper' => $successes === $trials ? 1.0 : min(1.0, $center + $radius)];
}

/**
 * @param list<int|null> $values
 * @return array{total:int|null,observed_subtotal:int,observed_attempts:int,unknown_started_attempts:int,reason:string|null}
 */
function agentEvaluationComparisonObservedSum(array $values): array
{
    $sum = 0;
    $known = 0;
    foreach ($values as $value) {
        if ($value !== null) {
            $sum += $value;
            $known++;
        }
    }
    $unknown = count($values) - $known;
    return ['total' => $values !== [] && $unknown === 0 ? $sum : null,
        'observed_subtotal' => $sum, 'observed_attempts' => $known, 'unknown_started_attempts' => $unknown,
        'reason' => $values === [] ? 'No retained started attempts.' : ($unknown > 0 ? 'One or more started attempts lack this observation; no value was imputed.' : null)];
}

/**
 * @param array<string,mixed> $usage
 * @param array<string,mixed> $pricing
 */
function agentEvaluationComparisonEstimatedCharge(array $usage, array $pricing): ?float
{
    $input = $usage['input_tokens'] ?? null;
    $output = $usage['output_tokens'] ?? null;
    $cached = $usage['cached_tokens'] ?? null;
    if (!is_int($input) || !is_int($output) || !is_int($cached)) {
        return null;
    }
    if ($input < 0 || $output < 0 || $cached < 0 || $cached > $input) {
        throw new RuntimeException('Comparison charge requires coherent observed provider token totals.');
    }
    $ordinaryPrice = agentEvaluationRequireNonNegativeInteger($pricing, 'input_cents_per_million', 'comparison charge');
    $cachedPrice = agentEvaluationRequireNonNegativeInteger($pricing, 'cached_cents_per_million', 'comparison charge');
    $outputPrice = agentEvaluationRequireNonNegativeInteger($pricing, 'output_cents_per_million', 'comparison charge');
    return (($input - $cached) * $ordinaryPrice + $cached * $cachedPrice + $output * $outputPrice) / 100_000_000;
}

/**
 * @param list<float|null> $charges
 * @return array{total:float|null,observed_subtotal:float,observed_attempts:int,unknown_started_attempts:int,reason:string|null}
 */
function agentEvaluationComparisonObservedChargeSum(array $charges): array
{
    $sum = 0.0;
    $known = 0;
    foreach ($charges as $charge) {
        if ($charge !== null) {
            $sum += $charge;
            $known++;
        }
    }
    $unknown = count($charges) - $known;
    return ['total' => $charges !== [] && $unknown === 0 ? $sum : null, 'observed_subtotal' => $sum,
        'observed_attempts' => $known, 'unknown_started_attempts' => $unknown,
        'reason' => $charges === [] ? 'No retained started attempts.' : ($unknown > 0
            ? 'At least one attempt lacks input, output, or cached input usage; dated token prices cannot establish its charge.' : null)];
}
