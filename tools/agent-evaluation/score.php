<?php

declare(strict_types=1);

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
