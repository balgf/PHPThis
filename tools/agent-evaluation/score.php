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
 * @param array<string, mixed> $score
 * @param array<string, mixed> $task
 * @param array<string, mixed> $runRecord
 */
function agentEvaluationValidateExplanationScoreRecord(
    array $score,
    array $task,
    array $runRecord,
    string $runRecordHash,
): void {
    $owner = 'explanation score record';
    agentEvaluationRequireExactKeys(
        $score,
        [
            'schema_version',
            'run_id',
            'task_id',
            'task_revision',
            'run_record_sha256',
            'prompt_sha256',
            'effective_prompt_sha256',
            'rubric_sha256',
            'response_sha256',
            'candidate_patch_sha256',
            'structural_evidence_path',
            'structural_evidence_sha256',
            'admissible',
            'structural_checks',
            'automated_status',
            'human_review',
            'correct_completion',
        ],
        $owner,
    );

    if (
        ($task['schema_version'] ?? null) !== 3
        || ($task['id'] ?? null) !== AGENT_EVALUATION_EXPLANATION_TASK_ID
        || ($runRecord['schema_version'] ?? null) !== 3
    ) {
        throw new RuntimeException('Explanation score validation requires the explicit schema-v3 task and run.');
    }

    if (agentEvaluationRequireInteger($score, 'schema_version', $owner) !== 3) {
        throw new RuntimeException('Explanation score record must use schema version 3.');
    }

    if (
        agentEvaluationRequireString($score, 'run_id', $owner) !== ($runRecord['run_id'] ?? null)
        || agentEvaluationRequireString($score, 'task_id', $owner) !== $task['id']
        || agentEvaluationRequireInteger($score, 'task_revision', $owner) !== ($task['revision'] ?? null)
    ) {
        throw new RuntimeException('Explanation score identity does not match its task and run records.');
    }

    $prompt = agentEvaluationRequireObject($task, 'prompt', 'explanation task');
    $rubric = agentEvaluationRequireObject($task, 'rubric', 'explanation task');
    $bindings = [
        'run_record_sha256' => $runRecordHash,
        'prompt_sha256' => agentEvaluationRequireString($prompt, 'sha256', 'explanation task prompt'),
        'effective_prompt_sha256' => agentEvaluationRequireString(
            $prompt,
            'effective_sha256',
            'explanation task prompt',
        ),
        'rubric_sha256' => agentEvaluationRequireString($rubric, 'sha256', 'explanation task rubric'),
        'response_sha256' => $runRecord['response_sha256'] ?? null,
        'candidate_patch_sha256' => $runRecord['candidate_patch_sha256'] ?? null,
    ];

    foreach ($bindings as $name => $expected) {
        $actual = agentEvaluationRequireHash(
            agentEvaluationRequireString($score, $name, $owner),
            "{$owner} {$name}",
        );

        if (!is_string($expected) || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Explanation score artifacts do not match the validated task and run records.');
        }
    }

    if (
        ($runRecord['effective_prompt_sha256'] ?? null) !== $bindings['effective_prompt_sha256']
    ) {
        throw new RuntimeException('Explanation score effective prompt does not match the validated run record.');
    }

    if (($bindings['candidate_patch_sha256'] ?? null) !== hash('sha256', '')) {
        throw new RuntimeException('Explanation score must bind the empty candidate patch.');
    }

    if (
        agentEvaluationRequireRelativePath(
            agentEvaluationRequireString($score, 'structural_evidence_path', $owner),
            $owner . ' structural evidence path',
        ) !== 'structural-evidence.json'
    ) {
        throw new RuntimeException('Explanation score must use its fixed structural-evidence path.');
    }
    agentEvaluationRequireHash(
        agentEvaluationRequireString($score, 'structural_evidence_sha256', $owner),
        $owner . ' structural evidence',
    );

    $checks = agentEvaluationRequireObject($score, 'structural_checks', $owner);
    agentEvaluationRequireExactKeys(
        $checks,
        [
            'task_identity',
            'response_integrity',
            'workspace_unchanged',
            'resource_bounds',
            'external_actions_approved',
            'cleanup',
        ],
        $owner . ' structural checks',
    );
    $structuralPass = true;

    foreach ($checks as $name => $value) {
        if (!is_bool($value)) {
            throw new RuntimeException("Explanation structural check {$name} must be Boolean.");
        }

        $structuralPass = $structuralPass && $value;
    }

    foreach (
        ['task_identity', 'response_integrity', 'workspace_unchanged', 'resource_bounds', 'external_actions_approved']
        as $name
    ) {
        if ($checks[$name] !== true) {
            throw new RuntimeException('A completed explanation run requires every non-cleanup structural check to pass.');
        }
    }

    $admissible = agentEvaluationRequireBoolean($score, 'admissible', $owner);

    if ($admissible !== $structuralPass) {
        throw new RuntimeException('Explanation admissibility must equal every structural automated check.');
    }

    $automatedStatus = agentEvaluationRequireString($score, 'automated_status', $owner);
    $expectedAutomatedStatus = $structuralPass ? 'pass' : 'fail';

    if ($automatedStatus !== $expectedAutomatedStatus) {
        throw new RuntimeException('Explanation automated status must derive only from structural checks.');
    }

    $review = agentEvaluationRequireObject($score, 'human_review', $owner);
    agentEvaluationRequireExactKeys(
        $review,
        ['status', 'reviewer', 'dimensions', 'reason'],
        $owner . ' human review',
    );
    $reviewStatus = agentEvaluationRequireString($review, 'status', $owner . ' human review');

    if (!in_array($reviewStatus, ['pending', 'pass', 'fail'], true)) {
        throw new RuntimeException('Explanation human review status must be pending, pass, or fail.');
    }

    $reviewer = agentEvaluationRequireNullableString($review, 'reviewer', $owner . ' human review');

    if (
        $reviewer !== null
        && (
            strlen($reviewer) > 128
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.@-]*\z/D', $reviewer) !== 1
        )
    ) {
        throw new RuntimeException('Explanation reviewer identity must be one bounded label.');
    }

    $reason = agentEvaluationRequireNonEmptyString($review, 'reason', $owner . ' human review');

    if (strlen($reason) > 2_048 || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1) {
        throw new RuntimeException('Explanation human review reason must be bounded single-line evidence.');
    }

    $dimensions = agentEvaluationRequireObject($review, 'dimensions', $owner . ' human review');
    $dimensionNames = [
        'route_selection',
        'necessary_concern_coverage',
        'unsupported_claims',
        'answer_correctness',
        'repairs',
        'clarification',
    ];
    agentEvaluationRequireExactKeys($dimensions, $dimensionNames, $owner . ' human review dimensions');
    $statuses = [];

    foreach ($dimensionNames as $name) {
        $dimension = agentEvaluationValueObject(
            $dimensions[$name] ?? null,
            "{$owner} human review dimension {$name}",
        );
        agentEvaluationRequireExactKeys(
            $dimension,
            ['status', 'evidence'],
            "{$owner} human review dimension {$name}",
        );
        $status = agentEvaluationRequireString(
            $dimension,
            'status',
            "{$owner} human review dimension {$name}",
        );

        if (!in_array($status, ['pass', 'fail', 'unknown'], true)) {
            throw new RuntimeException('Explanation review dimensions must be pass, fail, or unknown.');
        }

        $evidence = agentEvaluationRequireNonEmptyString(
            $dimension,
            'evidence',
            "{$owner} human review dimension {$name}",
        );

        if (strlen($evidence) > 2_048 || preg_match('/[\x00-\x1F\x7F]/', $evidence) === 1) {
            throw new RuntimeException('Explanation review dimension evidence must be bounded single-line text.');
        }

        $statuses[$name] = $status;
    }

    $correctCompletion = $score['correct_completion'] ?? null;

    if ($correctCompletion !== null && !is_bool($correctCompletion)) {
        throw new RuntimeException('Explanation correct completion must be Boolean or null.');
    }

    $executionKind = agentEvaluationRequireString($runRecord, 'execution_kind', 'explanation run record');

    if (
        $executionKind === 'synthetic-control'
        && ($reviewStatus !== 'pending' || $correctCompletion !== null)
    ) {
        throw new RuntimeException('Synthetic explanation controls cannot claim semantic review or correct completion.');
    }

    if ($reviewStatus === 'pending') {
        if ($reviewer !== null || array_unique(array_values($statuses)) !== ['unknown']) {
            throw new RuntimeException('A pending explanation review requires a null reviewer and unknown dimensions.');
        }

        if ($correctCompletion !== null) {
            throw new RuntimeException('A pending explanation review requires unknown correct completion.');
        }

        return;
    }

    if ($reviewer === null) {
        throw new RuntimeException('A completed explanation review requires an accountable reviewer.');
    }

    $criticalPass = true;

    foreach (
        ['route_selection', 'necessary_concern_coverage', 'unsupported_claims', 'answer_correctness']
        as $name
    ) {
        $criticalPass = $criticalPass && $statuses[$name] === 'pass';
    }

    $hasFailure = in_array('fail', $statuses, true);
    $expectedReviewStatus = $criticalPass && !$hasFailure ? 'pass' : 'fail';

    if ($reviewStatus !== $expectedReviewStatus) {
        throw new RuntimeException('Explanation human review status must match its six semantic dimensions.');
    }

    $expectedCorrectCompletion = $structuralPass && $reviewStatus === 'pass';

    if ($correctCompletion !== $expectedCorrectCompletion) {
        throw new RuntimeException('Explanation correct completion must combine structural and human review status.');
    }
}

/** @return list<string> */
function agentEvaluationExplanationStructuralEvidencePaths(string $executionKind): array
{
    $paths = [
        'candidate.manifest',
        'candidate.patch',
        'cleanup.json',
        'external-actions.json',
        'freeze.json',
        'generation-cleanup.json',
        'generation-process.json',
        'profile.json',
        'prompt.md',
        'run.json',
        'task.json',
        'tracked-source.manifest',
    ];

    if ($executionKind === 'live-model') {
        $paths = [
            ...$paths,
            'approval.json',
            'dependencies.installed.json',
            'dependencies.lock',
            'oci-cleanup.json',
            'oci-preflight.json',
            'proxy.json',
        ];
        sort($paths, SORT_STRING);

        return $paths;
    }

    if ($executionKind !== 'synthetic-control') {
        throw new RuntimeException('Explanation structural evidence requires a known execution kind.');
    }

    return $paths;
}

/**
 * @return array{schema_version: int, execution_kind: string, artifacts: array<string, string>}
 */
function agentEvaluationExplanationStructuralEvidenceDocument(
    string $executionKind,
    string $artifactRoot,
): array {
    $root = realpath($artifactRoot);

    if (!is_string($root) || !is_dir($root)) {
        throw new RuntimeException('Explanation structural evidence root is unavailable.');
    }

    $artifacts = [];
    $files = [];

    foreach (agentEvaluationExplanationStructuralEvidencePaths($executionKind) as $relativePath) {
        $path = agentEvaluationContainedArtifactPath(
            $root,
            $relativePath,
            "explanation structural evidence artifact {$relativePath}",
        );
        agentEvaluationRequireBoundedFile(
            $path,
            AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
            "explanation structural evidence artifact {$relativePath}",
        );
        $artifacts[$relativePath] = agentEvaluationFileHash(
            $path,
            "explanation structural evidence artifact {$relativePath}",
        );
        $files[] = $path;
    }

    agentEvaluationRequireDistinctFileIdentities($files);

    return [
        'schema_version' => 1,
        'execution_kind' => $executionKind,
        'artifacts' => $artifacts,
    ];
}

/**
 * @param array<string, mixed> $isolation
 * @param array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int} $budgets
 * @return array<string, mixed>
 */
function agentEvaluationNormalizeExplanationIsolationEvidence(
    array $isolation,
    array $budgets,
    string $executionKind,
): array {
    $owner = 'explanation retained isolation profile';
    agentEvaluationRequireExactKeys(
        $isolation,
        [
            'launcher',
            'image_reference',
            'image_digest',
            'credential_broker',
            'network',
            'root_read_only',
            'capabilities_dropped',
            'no_new_privileges',
            'candidate_git_absent',
            'dependencies_read_only',
            'uid',
            'cpu_millis',
            'memory_bytes',
            'disk_bytes',
            'processes',
            'wall_seconds',
            'model_tokens',
            'output_bytes',
            'descendant_cleanup',
        ],
        $owner,
    );
    $live = $executionKind === 'live-model';
    $imageReference = agentEvaluationRequireNullableString($isolation, 'image_reference', $owner);
    $imageDigest = agentEvaluationRequireNullableString($isolation, 'image_digest', $owner);
    $expectedStrings = $live ? [
        'launcher' => 'docker-oci',
        'credential_broker' => 'responses-api-run-proxy',
        'network' => 'proxy-only',
        'descendant_cleanup' => 'container-destroy',
    ] : [
        'launcher' => 'synthetic-test',
        'credential_broker' => 'none',
        'network' => 'none',
        'descendant_cleanup' => 'in-process-fixture',
    ];

    foreach ($expectedStrings as $name => $expected) {
        if (agentEvaluationRequireString($isolation, $name, $owner) !== $expected) {
            throw new RuntimeException('Explanation retained isolation profile does not match its fixed execution kind.');
        }
    }

    if ($live) {
        if (
            !is_string($imageReference)
            || !is_string($imageDigest)
            || preg_match('/\Asha256:[a-f0-9]{64}\z/D', $imageDigest) !== 1
            || preg_match('/\A[a-z0-9][a-z0-9._:\/-]*@sha256:[a-f0-9]{64}\z/D', $imageReference) !== 1
            || !str_ends_with($imageReference, '@' . $imageDigest)
        ) {
            throw new RuntimeException('Explanation retained isolation profile requires one digest-pinned generation image.');
        }
    } elseif ($imageReference !== null || $imageDigest !== null) {
        throw new RuntimeException('Synthetic explanation isolation cannot claim an OCI image identity.');
    }

    foreach (
        ['root_read_only', 'capabilities_dropped', 'no_new_privileges', 'candidate_git_absent', 'dependencies_read_only']
        as $name
    ) {
        if (!agentEvaluationRequireBoolean($isolation, $name, $owner)) {
            throw new RuntimeException('Explanation retained isolation profile must keep every hardening control enabled.');
        }
    }

    $expectedIntegers = [
        'uid' => 65_534,
        'cpu_millis' => 1_000,
        'memory_bytes' => 1_073_741_824,
        'disk_bytes' => 1_073_741_824,
        'processes' => 64,
        'wall_seconds' => $budgets['wall_seconds'],
        'model_tokens' => $budgets['model_tokens'],
        'output_bytes' => $budgets['command_output_bytes'],
    ];

    foreach ($expectedIntegers as $name => $expected) {
        if (agentEvaluationRequirePositiveInteger($isolation, $name, $owner) !== $expected) {
            throw new RuntimeException('Explanation retained isolation limits do not match the fixed run profile.');
        }
    }

    return [
        ...$expectedStrings,
        'image_reference' => $imageReference,
        'image_digest' => $imageDigest,
        'root_read_only' => true,
        'capabilities_dropped' => true,
        'no_new_privileges' => true,
        'candidate_git_absent' => true,
        'dependencies_read_only' => true,
        ...$expectedIntegers,
    ];
}

/**
 * @param array<string, mixed> $preflight
 * @param array<string, mixed> $isolation
 * @param array<string, mixed> $runner
 */
function agentEvaluationValidateExplanationOciPreflightEvidence(
    array $preflight,
    array $isolation,
    array $runner,
): void {
    $owner = 'explanation OCI preflight evidence';
    agentEvaluationRequireExactKeys(
        $preflight,
        ['engine_version', 'cgroup_version', 'images', 'network', 'toolchains'],
        $owner,
    );

    if (
        agentEvaluationRequireNonEmptyString($preflight, 'engine_version', $owner) === ''
        || agentEvaluationRequireString($preflight, 'cgroup_version', $owner) !== '2'
        || agentEvaluationRequireString($preflight, 'network', $owner) !== 'none-with-fixed-broker-pipe'
    ) {
        throw new RuntimeException('Explanation OCI preflight does not prove the fixed engine boundary.');
    }

    $images = agentEvaluationRequireObject($preflight, 'images', $owner);
    agentEvaluationRequireExactKeys($images, ['generation', 'scoring'], $owner . ' images');
    $normalizedImages = [];

    foreach (['generation', 'scoring'] as $role) {
        $image = agentEvaluationRequireObject($images, $role, $owner . ' images');
        agentEvaluationRequireExactKeys(
            $image,
            ['image_reference', 'image_id', 'architecture'],
            "{$owner} {$role} image",
        );
        $reference = agentEvaluationRequireString($image, 'image_reference', "{$owner} {$role} image");
        $id = agentEvaluationRequireString($image, 'image_id', "{$owner} {$role} image");
        $architecture = agentEvaluationRequireString($image, 'architecture', "{$owner} {$role} image");

        if (
            preg_match('/\A[a-z0-9][a-z0-9._:\/-]*@sha256:[a-f0-9]{64}\z/D', $reference) !== 1
            || preg_match('/\Asha256:[a-f0-9]{64}\z/D', $id) !== 1
            || !in_array($architecture, ['arm64', 'amd64'], true)
        ) {
            throw new RuntimeException('Explanation OCI preflight contains an invalid image identity.');
        }

        $normalizedImages[$role] = ['image_reference' => $reference, 'image_id' => $id];
    }

    $isolationImageReference = agentEvaluationRequireString(
        $isolation,
        'image_reference',
        'explanation retained isolation profile',
    );
    $isolationImageDigest = agentEvaluationRequireString(
        $isolation,
        'image_digest',
        'explanation retained isolation profile',
    );

    if (
        $normalizedImages['generation']['image_reference'] !== $isolationImageReference
        || !str_ends_with(
            $normalizedImages['generation']['image_reference'],
            '@' . $isolationImageDigest,
        )
        || $normalizedImages['generation']['image_reference'] === $normalizedImages['scoring']['image_reference']
    ) {
        throw new RuntimeException('Explanation OCI preflight generation image does not match the retained isolation profile.');
    }

    $toolchains = agentEvaluationRequireObject($preflight, 'toolchains', $owner);
    agentEvaluationRequireExactKeys($toolchains, ['generation', 'scoring'], $owner . ' toolchains');
    $observedToolchains = [];

    foreach (['generation', 'scoring'] as $role) {
        $toolchain = agentEvaluationRequireObject($toolchains, $role, $owner . ' toolchains');
        agentEvaluationRequireExactKeys(
            $toolchain,
            ['php_version', 'composer_version', 'python_version', 'codex_version', 'relay_sha256', 'database'],
            "{$owner} {$role} toolchain",
        );

        $versions = [];

        foreach (['php_version', 'composer_version', 'python_version'] as $name) {
            $version = agentEvaluationRequireString($toolchain, $name, "{$owner} {$role} toolchain");
            if (preg_match(
                '/\A[0-9]+\.[0-9]+\.[0-9]+\z/D',
                $version,
            ) !== 1) {
                throw new RuntimeException('Explanation OCI preflight contains an invalid toolchain version.');
            }

            $versions[$name] = $version;
        }

        if (!str_starts_with($versions['php_version'], '8.4.')) {
            throw new RuntimeException('Explanation OCI preflight requires the supported PHP 8.4 toolchain.');
        }

        $database = agentEvaluationRequireObject($toolchain, 'database', "{$owner} {$role} toolchain");
        agentEvaluationRequireExactKeys(
            $database,
            ['pdo_drivers', 'pdo_sqlite_version', 'sqlite_json1', 'sqlite_version'],
            "{$owner} {$role} database",
        );
        $drivers = agentEvaluationRequireStringList($database, 'pdo_drivers', "{$owner} {$role} database");

        foreach ($drivers as $driver) {
            if (preg_match('/\A[a-z][a-z0-9_]{0,31}\z/D', $driver) !== 1) {
                throw new RuntimeException('Explanation OCI preflight contains an invalid database identity.');
            }
        }

        if (count(array_unique($drivers, SORT_STRING)) !== count($drivers)) {
            throw new RuntimeException('Explanation OCI preflight contains an invalid database identity.');
        }

        if (!is_bool($database['sqlite_json1'] ?? null)) {
            throw new RuntimeException('Explanation OCI preflight contains an invalid database identity.');
        }

        $sqliteAvailable = in_array('sqlite', $drivers, true);

        foreach (['pdo_sqlite_version', 'sqlite_version'] as $name) {
            $version = $database[$name] ?? null;

            if (
                ($sqliteAvailable && (!is_string($version) || preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $version) !== 1))
                || (!$sqliteAvailable && $version !== null)
            ) {
                throw new RuntimeException('Explanation OCI preflight contains an invalid database identity.');
            }
        }

        if (!$sqliteAvailable && $database['sqlite_json1'] !== false) {
            throw new RuntimeException('Explanation OCI preflight contains an invalid database identity.');
        }

        if ($role === 'generation') {
            if (
                $toolchain['codex_version'] !== ($runner['version'] ?? null)
                || $toolchain['codex_version'] !== '0.153.1'
                || $toolchain['relay_sha256'] !== AGENT_EVALUATION_EXPLANATION_RELAY_SHA256
            ) {
                throw new RuntimeException('Explanation OCI preflight does not match the pinned generation client and relay.');
            }
        } elseif ($toolchain['codex_version'] !== null || $toolchain['relay_sha256'] !== null) {
            throw new RuntimeException('Explanation scoring image must omit the generation client and relay.');
        }

        $observedToolchains[$role] = $versions;
    }

    if (
        $observedToolchains['generation']['php_version'] !== $observedToolchains['scoring']['php_version']
        || $observedToolchains['generation']['composer_version'] !== $observedToolchains['scoring']['composer_version']
    ) {
        throw new RuntimeException('Explanation generation and scoring toolchains must share PHP and Composer identities.');
    }
}

/**
 * @param array<string, mixed> $score
 * @param array<string, mixed> $runRecord
 */
function agentEvaluationValidateExplanationScoreArtifacts(
    array $score,
    array $runRecord,
    string $artifactRoot,
): void {
    $root = realpath($artifactRoot);

    if (!is_string($root) || !is_dir($root)) {
        throw new RuntimeException('Explanation score artifact root is unavailable.');
    }

    $descriptor = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($score, 'structural_evidence_path', 'explanation score record'),
        'explanation structural evidence path',
    );

    if ($descriptor !== 'structural-evidence.json') {
        throw new RuntimeException('Explanation score must use its fixed structural-evidence path.');
    }

    $descriptorPath = agentEvaluationContainedArtifactPath(
        $root,
        $descriptor,
        'explanation structural evidence',
    );
    agentEvaluationRequireBoundedFile(
        $descriptorPath,
        AGENT_EVALUATION_MAX_JSON_BYTES,
        'explanation structural evidence',
    );
    agentEvaluationRequireFileHash(
        $descriptorPath,
        agentEvaluationRequireString($score, 'structural_evidence_sha256', 'explanation score record'),
        'explanation structural evidence',
    );
    $document = agentEvaluationJsonFile($descriptorPath);
    agentEvaluationRequireExactKeys(
        $document,
        ['schema_version', 'execution_kind', 'artifacts'],
        'explanation structural evidence',
    );

    if (agentEvaluationRequireInteger($document, 'schema_version', 'explanation structural evidence') !== 1) {
        throw new RuntimeException('Explanation structural evidence must use schema version 1.');
    }

    $executionKind = agentEvaluationRequireString($runRecord, 'execution_kind', 'explanation run record');

    if (
        agentEvaluationRequireString($document, 'execution_kind', 'explanation structural evidence')
        !== $executionKind
    ) {
        throw new RuntimeException('Explanation structural evidence execution kind does not match the run.');
    }

    $expectedPaths = agentEvaluationExplanationStructuralEvidencePaths($executionKind);
    $artifacts = agentEvaluationRequireObject($document, 'artifacts', 'explanation structural evidence');
    agentEvaluationRequireExactKeys($artifacts, $expectedPaths, 'explanation structural evidence artifacts');
    $expectedBindings = [
        'candidate.patch' => agentEvaluationRequireString(
            $runRecord,
            'candidate_patch_sha256',
            'explanation run record',
        ),
        'prompt.md' => agentEvaluationRequireString(
            $runRecord,
            'effective_prompt_sha256',
            'explanation run record',
        ),
        'run.json' => agentEvaluationRequireString($score, 'run_record_sha256', 'explanation score record'),
        'task.json' => agentEvaluationRequireString(
            $runRecord,
            'task_manifest_sha256',
            'explanation run record',
        ),
        'tracked-source.manifest' => agentEvaluationRequireString(
            $runRecord,
            'base_fixture_sha256',
            'explanation run record',
        ),
    ];
    if ($executionKind === 'live-model') {
        $expectedBindings['dependencies.lock'] = agentEvaluationRequireString(
            $runRecord,
            'prepared_lock_sha256',
            'explanation run record',
        );
        $expectedBindings['dependencies.installed.json'] = agentEvaluationRequireString(
            $runRecord,
            'prepared_installed_metadata_sha256',
            'explanation run record',
        );
    }
    $paths = [$descriptorPath];

    foreach ($expectedPaths as $relativePath) {
        $hash = agentEvaluationRequireHash(
            agentEvaluationRequireString($artifacts, $relativePath, 'explanation structural evidence artifacts'),
            "explanation structural evidence artifact {$relativePath}",
        );
        $expectedHash = $expectedBindings[$relativePath] ?? null;

        if (is_string($expectedHash) && !hash_equals($expectedHash, $hash)) {
            throw new RuntimeException("Explanation structural evidence artifact {$relativePath} is not bound to its record.");
        }

        $path = agentEvaluationContainedArtifactPath(
            $root,
            $relativePath,
            "explanation structural evidence artifact {$relativePath}",
        );
        agentEvaluationRequireBoundedFile(
            $path,
            AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
            "explanation structural evidence artifact {$relativePath}",
        );
        agentEvaluationRequireFileHash(
            $path,
            $hash,
            "explanation structural evidence artifact {$relativePath}",
        );
        $paths[] = $path;
    }

    agentEvaluationRequireDistinctFileIdentities($paths);

    if (filesize($root . '/candidate.patch') !== 0) {
        throw new RuntimeException('Explanation structural evidence must retain the empty candidate patch.');
    }

    agentEvaluationValidateExplanationRunArtifacts($runRecord, $root);
    $eventEvidence = agentEvaluationExplanationEventEvidence(
        agentEvaluationContainedArtifactPath(
            $root,
            agentEvaluationRequireString($runRecord, 'events_path', 'explanation run record'),
            'explanation events artifact',
        ),
        agentEvaluationContainedArtifactPath(
            $root,
            agentEvaluationRequireString($runRecord, 'response_path', 'explanation run record'),
            'explanation response artifact',
        ),
    );
    $profile = agentEvaluationJsonFile($root . '/profile.json');
    agentEvaluationRequireExactKeys(
        $profile,
        ['condition', 'runner', 'model', 'context', 'tools', 'transport_tools', 'budgets', 'isolation'],
        'explanation retained profile',
    );
    if (!array_key_exists('transport_tools', $profile)) {
        throw new RuntimeException('Explanation retained profile must state its transport-tools identity.');
    }
    $observedProfile = agentEvaluationNormalizeExplanationExecutionProfile(
        [
            'condition' => agentEvaluationRequireString($profile, 'condition', 'explanation retained profile'),
            'runner' => agentEvaluationRequireObject($profile, 'runner', 'explanation retained profile'),
            'model' => agentEvaluationRequireObject($profile, 'model', 'explanation retained profile'),
            'context' => agentEvaluationRequireObject($profile, 'context', 'explanation retained profile'),
            'tools' => agentEvaluationRequireList($profile, 'tools', 'explanation retained profile'),
            'transport_tools' => $profile['transport_tools'],
        ],
        'explanation retained profile',
    );
    $recordedProfile = agentEvaluationNormalizeExplanationExecutionProfile(
        [
            'condition' => agentEvaluationRequireString($runRecord, 'condition', 'explanation run record'),
            'runner' => agentEvaluationRequireObject($runRecord, 'runner', 'explanation run record'),
            'model' => agentEvaluationRequireObject($runRecord, 'model', 'explanation run record'),
            'context' => agentEvaluationRequireObject($runRecord, 'context', 'explanation run record'),
            'tools' => agentEvaluationRequireList($runRecord, 'tools', 'explanation run record'),
            'transport_tools' => $runRecord['transport_tools'] ?? null,
        ],
        'explanation recorded profile',
    );
    $observedBudgets = agentEvaluationValidateBudgets(
        agentEvaluationRequireObject($profile, 'budgets', 'explanation retained profile'),
        AGENT_EVALUATION_EXPLANATION_TASK_ID,
    );
    $recordedBudgets = agentEvaluationValidateBudgets(
        agentEvaluationRequireObject($runRecord, 'budgets', 'explanation run record'),
        AGENT_EVALUATION_EXPLANATION_TASK_ID,
    );
    $observedIsolation = agentEvaluationNormalizeExplanationIsolationEvidence(
        agentEvaluationRequireObject($profile, 'isolation', 'explanation retained profile'),
        $observedBudgets,
        $executionKind,
    );

    if (
        $observedProfile !== $recordedProfile
        || $observedBudgets !== $recordedBudgets
    ) {
        throw new RuntimeException('Explanation retained profile does not match the validated run record.');
    }

    $process = agentEvaluationJsonFile($root . '/generation-process.json');
    agentEvaluationRequireExactKeys(
        $process,
        $executionKind === 'live-model' ? [
            'exit_code',
            'stdout',
            'stderr',
            'termination_reason',
            'elapsed_milliseconds',
            'timed_out',
            'output_limit_exceeded',
            'cleanup',
            'resource_observation',
            'synthetic_upstream',
            'failure_code',
            'upstream_failure',
        ] : [
            'exit_code',
            'stdout',
            'stderr',
            'termination_reason',
            'elapsed_milliseconds',
            'timed_out',
            'output_limit_exceeded',
            'cleanup',
        ],
        'explanation generation process evidence',
    );

    if (
        agentEvaluationRequireNonNegativeInteger($process, 'exit_code', 'explanation generation process evidence') !== 0
        || agentEvaluationRequireString($process, 'termination_reason', 'explanation generation process evidence')
            !== 'completed'
        || agentEvaluationRequireBoolean($process, 'timed_out', 'explanation generation process evidence')
        || agentEvaluationRequireBoolean($process, 'output_limit_exceeded', 'explanation generation process evidence')
    ) {
        throw new RuntimeException('Explanation generation process evidence does not describe bounded completion.');
    }
    $elapsedMilliseconds = agentEvaluationRequireNonNegativeInteger(
        $process,
        'elapsed_milliseconds',
        'explanation generation process evidence',
    );
    $budgets = agentEvaluationRequireObject($runRecord, 'budgets', 'explanation run record');
    $wallSeconds = agentEvaluationRequirePositiveInteger($budgets, 'wall_seconds', 'explanation run budgets');

    if ($elapsedMilliseconds > $wallSeconds * 1_000) {
        throw new RuntimeException('Explanation generation process exceeds its recorded wall-time budget.');
    }

    if (
        agentEvaluationRequireString($process, 'stdout', 'explanation generation process evidence')
            !== 'retained separately as events.jsonl'
        || agentEvaluationRequireString($process, 'stderr', 'explanation generation process evidence')
            !== 'retained separately as generation.stderr'
    ) {
        throw new RuntimeException('Explanation generation process must bind its separately retained output evidence.');
    }

    if ($executionKind === 'live-model') {
        $processCleanup = agentEvaluationRequireObject(
            $process,
            'cleanup',
            'explanation live generation process evidence',
        );
        agentEvaluationRequireExactKeys(
            $processCleanup,
            ['container_stopped', 'oom_killed', 'pid'],
            'explanation live generation process cleanup',
        );
        $resourceObservation = agentEvaluationRequireObject(
            $process,
            'resource_observation',
            'explanation live resource observation',
        );
        agentEvaluationRequireExactKeys(
            $resourceObservation,
            ['memory_events', 'pids_events', 'disk_free_bytes'],
            'explanation live resource observation',
        );
        $memoryEvents = agentEvaluationRequireObject(
            $resourceObservation,
            'memory_events',
            'explanation live memory observation',
        );
        $pidEvents = agentEvaluationRequireObject(
            $resourceObservation,
            'pids_events',
            'explanation live process observation',
        );
        $diskFree = agentEvaluationRequireObject(
            $resourceObservation,
            'disk_free_bytes',
            'explanation live disk observation',
        );
        agentEvaluationRequireExactKeys($memoryEvents, ['oom', 'oom_kill'], 'explanation live memory observation');
        agentEvaluationRequireExactKeys($pidEvents, ['max'], 'explanation live process observation');
        agentEvaluationRequireExactKeys(
            $diskFree,
            ['candidate', 'tmp', 'workspace_tmp', 'cache', 'shm'],
            'explanation live disk observation',
        );
        $resourcePass = agentEvaluationRequireBoolean(
            $processCleanup,
            'container_stopped',
            'explanation live generation process cleanup',
        )
            && !agentEvaluationRequireBoolean(
                $processCleanup,
                'oom_killed',
                'explanation live generation process cleanup',
            )
            && agentEvaluationRequireNonNegativeInteger(
                $processCleanup,
                'pid',
                'explanation live generation process cleanup',
            ) === 0
            && agentEvaluationRequireNonNegativeInteger(
                $memoryEvents,
                'oom',
                'explanation live memory observation',
            ) === 0
            && agentEvaluationRequireNonNegativeInteger(
                $memoryEvents,
                'oom_kill',
                'explanation live memory observation',
            ) === 0
            && agentEvaluationRequireNonNegativeInteger(
                $pidEvents,
                'max',
                'explanation live process observation',
            ) === 0;

        foreach (['candidate', 'tmp', 'workspace_tmp', 'cache', 'shm'] as $mount) {
            $resourcePass = $resourcePass && agentEvaluationRequirePositiveInteger(
                $diskFree,
                $mount,
                'explanation live disk observation',
            ) > 0;
        }

        if (!$resourcePass || agentEvaluationRequireBoolean(
            $process,
            'synthetic_upstream',
            'explanation generation process evidence',
        )) {
            throw new RuntimeException('Explanation live resource evidence does not prove bounded provider execution.');
        }

        if ($process['failure_code'] !== null || $process['upstream_failure'] !== null) {
            throw new RuntimeException('Completed explanation generation cannot retain a process failure.');
        }
    } else {
        $processCleanup = agentEvaluationRequireObject(
            $process,
            'cleanup',
            'synthetic explanation generation process evidence',
        );
        agentEvaluationRequireExactKeys(
            $processCleanup,
            ['process_group_created', 'terminate_sent', 'kill_sent', 'process_reaped', 'process_group_absent'],
            'synthetic explanation process cleanup',
        );

        if (
            !agentEvaluationRequireBoolean(
                $processCleanup,
                'process_group_created',
                'synthetic explanation process cleanup',
            )
            || agentEvaluationRequireBoolean(
                $processCleanup,
                'terminate_sent',
                'synthetic explanation process cleanup',
            )
            || agentEvaluationRequireBoolean(
                $processCleanup,
                'kill_sent',
                'synthetic explanation process cleanup',
            )
            || !agentEvaluationRequireBoolean(
                $processCleanup,
                'process_reaped',
                'synthetic explanation process cleanup',
            )
            || !agentEvaluationRequireBoolean(
                $processCleanup,
                'process_group_absent',
                'synthetic explanation process cleanup',
            )
        ) {
            throw new RuntimeException('Synthetic explanation process cleanup evidence is incomplete.');
        }
    }

    $freeze = agentEvaluationJsonFile($root . '/freeze.json');
    agentEvaluationRequireExactKeys(
        $freeze,
        ['candidate_sha256', 'patch_sha256', 'changed_files', 'added_lines', 'deleted_lines'],
        'explanation freeze evidence',
    );
    $candidateHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($freeze, 'candidate_sha256', 'explanation freeze evidence'),
        'explanation frozen candidate',
    );
    $patchHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($freeze, 'patch_sha256', 'explanation freeze evidence'),
        'explanation frozen patch',
    );
    $changedFiles = agentEvaluationRequireStringList($freeze, 'changed_files', 'explanation freeze evidence');
    $candidateManifestBytes = file_get_contents($root . '/candidate.manifest');
    $trackedSourceBytes = file_get_contents($root . '/tracked-source.manifest');
    $baseFixtureHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($runRecord, 'base_fixture_sha256', 'explanation run record'),
        'explanation run base fixture',
    );

    if (
        !is_string($candidateManifestBytes)
        || !is_string($trackedSourceBytes)
        || $candidateManifestBytes !== $trackedSourceBytes
        || !hash_equals($baseFixtureHash, $candidateHash)
        || $changedFiles !== []
        || agentEvaluationRequireNonNegativeInteger($freeze, 'added_lines', 'explanation freeze evidence') !== 0
        || agentEvaluationRequireNonNegativeInteger($freeze, 'deleted_lines', 'explanation freeze evidence') !== 0
        || !hash_equals(hash('sha256', ''), $patchHash)
        || !hash_equals(
            agentEvaluationFileHash($root . '/candidate.manifest', 'explanation candidate manifest'),
            $candidateHash,
        )
    ) {
        throw new RuntimeException('Explanation freeze evidence does not prove an unchanged workspace.');
    }

    if (
        $executionKind === 'live-model'
        && !hash_equals(
            agentEvaluationExplanationTrackedComposerLockSha256($trackedSourceBytes),
            agentEvaluationRequireString(
                $runRecord,
                'prepared_lock_sha256',
                'explanation run record',
            ),
        )
    ) {
        throw new RuntimeException(
            'Explanation retained prepared lock does not match the tracked source composer.lock.',
        );
    }

    $actions = agentEvaluationJsonFile($root . '/external-actions.json');

    if (
        !agentEvaluationRequireBoolean($actions, 'approved', 'explanation external-action evidence')
        || agentEvaluationRequireNonNegativeInteger(
            $actions,
            'file_change_events',
            'explanation external-action evidence',
        ) !== 0
    ) {
        throw new RuntimeException('Explanation external-action evidence does not prove approved read-only execution.');
    }

    if ($executionKind === 'synthetic-control') {
        agentEvaluationRequireExactKeys(
            $actions,
            ['approved', 'network_attempts', 'process_tool_calls', 'file_change_events', 'changed_paths'],
            'synthetic explanation external-action evidence',
        );

        if (
            agentEvaluationRequireNonNegativeInteger(
                $actions,
                'network_attempts',
                'synthetic explanation external-action evidence',
            ) !== 0
            || agentEvaluationRequireNonNegativeInteger(
                $actions,
                'process_tool_calls',
                'synthetic explanation external-action evidence',
            ) !== count($eventEvidence['commands'])
            || agentEvaluationRequireNonNegativeInteger(
                $actions,
                'file_change_events',
                'synthetic explanation external-action evidence',
            ) !== $eventEvidence['file_change_events']
            || agentEvaluationRequireStringList(
                $actions,
                'changed_paths',
                'synthetic explanation external-action evidence',
            ) !== []
        ) {
            throw new RuntimeException('Synthetic explanation evidence contains an external action.');
        }
    } else {
        agentEvaluationRequireExactKeys(
            $actions,
            [
                'approved',
                'network',
                'socket_attempt_telemetry',
                'host_proxy_requests',
                'proxy_blocked',
                'observed_commands',
                'file_change_events',
            ],
            'live explanation external-action evidence',
        );
        $commands = agentEvaluationRequireList(
            $actions,
            'observed_commands',
            'live explanation external-action evidence',
        );
        $normalizedCommands = [];

        foreach ($commands as $command) {
            $command = agentEvaluationValueObject($command, 'live explanation observed command');
            agentEvaluationRequireExactKeys(
                $command,
                ['item_id', 'sha256', 'bytes'],
                'live explanation observed command',
            );
            agentEvaluationRequireNonEmptyString($command, 'item_id', 'live explanation observed command');
            agentEvaluationRequireHash(
                agentEvaluationRequireString($command, 'sha256', 'live explanation observed command'),
                'live explanation observed command',
            );
            agentEvaluationRequireNonNegativeInteger($command, 'bytes', 'live explanation observed command');
            $normalizedCommands[] = [
                'item_id' => agentEvaluationRequireString(
                    $command,
                    'item_id',
                    'live explanation observed command',
                ),
                'sha256' => agentEvaluationRequireString(
                    $command,
                    'sha256',
                    'live explanation observed command',
                ),
                'bytes' => agentEvaluationRequireNonNegativeInteger(
                    $command,
                    'bytes',
                    'live explanation observed command',
                ),
            ];
        }

        if (
            agentEvaluationRequireString($actions, 'network', 'live explanation external-action evidence') !== 'none'
            || $actions['socket_attempt_telemetry'] !== null
            || agentEvaluationRequirePositiveInteger(
                $actions,
                'host_proxy_requests',
                'live explanation external-action evidence',
            ) < 1
            || agentEvaluationRequireBoolean(
                $actions,
                'proxy_blocked',
                'live explanation external-action evidence',
            )
            || agentEvaluationRequireNonNegativeInteger(
                $actions,
                'file_change_events',
                'live explanation external-action evidence',
            ) !== $eventEvidence['file_change_events']
            || $normalizedCommands !== $eventEvidence['commands']
        ) {
            throw new RuntimeException('Live explanation evidence contains an unapproved external action.');
        }

        $approval = agentEvaluationJsonFile($root . '/approval.json');
        agentEvaluationRequireExactKeys(
            $approval,
            ['reference', 'model', 'runs', 'spending_ceiling_usd', 'run_id'],
            'explanation approval evidence',
        );
        $reference = agentEvaluationRequireNonEmptyString(
            $approval,
            'reference',
            'explanation approval evidence',
        );

        if (
            strlen($reference) > 128
            || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1
            || str_contains(strtolower($reference), 'pending')
            || str_contains(strtolower($reference), 'placeholder')
            || agentEvaluationRequireString($approval, 'model', 'explanation approval evidence')
                !== agentEvaluationRequireString(
                    agentEvaluationRequireObject($runRecord, 'model', 'explanation run record'),
                    'id',
                    'explanation run model',
                )
            || agentEvaluationRequireInteger($approval, 'runs', 'explanation approval evidence') !== 1
            || agentEvaluationRequireString($approval, 'run_id', 'explanation approval evidence')
                !== agentEvaluationRequireString($runRecord, 'run_id', 'explanation run record')
            || agentEvaluationRequireString(
                $approval,
                'spending_ceiling_usd',
                'explanation approval evidence',
            ) !== '0.60'
        ) {
            throw new RuntimeException('Explanation approval evidence does not bind the exact reviewed live run and ceiling.');
        }

        $proxy = agentEvaluationJsonFile($root . '/proxy.json');
        agentEvaluationRequireExactKeys(
            $proxy,
            [
                'candidate_operation',
                'upstream_operations',
                'upstream_origin',
                'synthetic_upstream',
                'transport',
                'provider_reported_usage',
                'runner_reported_usage',
                'ledger',
            ],
            'explanation proxy evidence',
        );
        $ledger = agentEvaluationRequireObject($proxy, 'ledger', 'explanation proxy evidence');
        $spending = agentEvaluationRequireObject($ledger, 'spending', 'explanation proxy ledger');
        agentEvaluationRequireExactKeys(
            $spending,
            ['policy', 'settled_units', 'reserved_units'],
            'explanation proxy spending ledger',
        );
        $policy = agentEvaluationRequireObject($spending, 'policy', 'explanation proxy spending ledger');
        $expectedPolicy = [
            'limit_units' => 60_000_000,
            'input_cents_per_million' => 250,
            'cached_cents_per_million' => 25,
            'output_cents_per_million' => 1_500,
        ];
        agentEvaluationRequireExactKeys($policy, array_keys($expectedPolicy), 'explanation proxy spending policy');

        foreach ($expectedPolicy as $name => $expectedValue) {
            if (agentEvaluationRequireInteger($policy, $name, 'explanation proxy spending policy') !== $expectedValue) {
                throw new RuntimeException('Explanation proxy evidence does not bind the exact approved spending policy.');
            }
        }

        $transportTools = agentEvaluationNormalizeExplanationTransportTools(
            $ledger['transport_tools'] ?? null,
            'explanation proxy transport tools',
        );
        $recordedTransportTools = agentEvaluationNormalizeExplanationTransportTools(
            $runRecord['transport_tools'] ?? null,
            'explanation run transport tools',
        );
        $providerUsage = agentEvaluationRequireObject(
            $proxy,
            'provider_reported_usage',
            'explanation proxy evidence',
        );
        $runnerUsage = agentEvaluationRequireObject(
            $proxy,
            'runner_reported_usage',
            'explanation proxy evidence',
        );
        $recordedUsage = agentEvaluationRequireObject($runRecord, 'usage', 'explanation run record');
        $settledUnits = agentEvaluationRequireNonNegativeInteger(
            $spending,
            'settled_units',
            'explanation proxy spending ledger',
        );
        $inputTokens = agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'input_tokens',
            'explanation run usage',
        );
        $outputTokens = agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'output_tokens',
            'explanation run usage',
        );
        $cachedTokens = agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'cached_tokens',
            'explanation run usage',
        );
        $reasoningTokens = agentEvaluationRequireNonNegativeInteger(
            $recordedUsage,
            'reasoning_tokens',
            'explanation run usage',
        );
        $recordedModel = agentEvaluationRequireObject($runRecord, 'model', 'explanation run record');
        $recordedSettings = agentEvaluationRequireObject(
            $recordedModel,
            'settings',
            'explanation run model',
        );
        foreach (
            [
                'model',
                'reasoning_effort',
                'token_budget',
                'input_tokens',
                'output_tokens',
                'cached_tokens',
                'reasoning_tokens',
                'reserved_input',
                'reserved_output',
                'request_sha256',
                'failure_reason',
            ] as $requiredLedgerField
        ) {
            if (!array_key_exists($requiredLedgerField, $ledger)) {
                throw new RuntimeException(
                    'Explanation proxy ledger does not match the completed run usage and zero-reservation state.',
                );
            }
        }
        $ledgerUsage = [
            'input_tokens' => agentEvaluationRequireNonNegativeInteger(
                $ledger,
                'input_tokens',
                'explanation proxy ledger',
            ),
            'output_tokens' => agentEvaluationRequireNonNegativeInteger(
                $ledger,
                'output_tokens',
                'explanation proxy ledger',
            ),
            'cached_tokens' => agentEvaluationRequireNonNegativeInteger(
                $ledger,
                'cached_tokens',
                'explanation proxy ledger',
            ),
            'reasoning_tokens' => agentEvaluationRequireNonNegativeInteger(
                $ledger,
                'reasoning_tokens',
                'explanation proxy ledger',
            ),
        ];
        $expectedUsage = [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'reasoning_tokens' => $reasoningTokens,
        ];

        if (
            agentEvaluationRequirePositiveInteger(
                $ledger,
                'token_budget',
                'explanation proxy ledger',
            ) !== agentEvaluationRequirePositiveInteger(
                $budgets,
                'model_tokens',
                'explanation run budgets',
            )
            || $ledgerUsage !== $expectedUsage
            || agentEvaluationRequireNonNegativeInteger(
                $ledger,
                'reserved_input',
                'explanation proxy ledger',
            ) !== 0
            || agentEvaluationRequireNonNegativeInteger(
                $ledger,
                'reserved_output',
                'explanation proxy ledger',
            ) !== 0
            || $ledger['request_sha256'] !== null
            || $ledger['failure_reason'] !== null
            || agentEvaluationRequireString($ledger, 'model', 'explanation proxy ledger')
                !== agentEvaluationRequireString($recordedModel, 'id', 'explanation run model')
            || agentEvaluationRequireString(
                $ledger,
                'reasoning_effort',
                'explanation proxy ledger',
            ) !== agentEvaluationRequireString(
                $recordedSettings,
                'reasoning_effort',
                'explanation run model settings',
            )
        ) {
            throw new RuntimeException(
                'Explanation proxy ledger does not match the completed run usage and zero-reservation state.',
            );
        }

        $expectedSettledUnits = ($inputTokens - $cachedTokens) * $expectedPolicy['input_cents_per_million']
            + $cachedTokens * $expectedPolicy['cached_cents_per_million']
            + $outputTokens * $expectedPolicy['output_cents_per_million'];

        if (
            agentEvaluationRequireString($proxy, 'candidate_operation', 'explanation proxy evidence')
                !== 'POST /v1/responses'
            || agentEvaluationRequireStringList($proxy, 'upstream_operations', 'explanation proxy evidence')
                !== ['POST /v1/responses/input_tokens', 'POST /v1/responses']
            || agentEvaluationRequireString($proxy, 'upstream_origin', 'explanation proxy evidence')
                !== 'https://api.openai.com'
            || agentEvaluationRequireBoolean($proxy, 'synthetic_upstream', 'explanation proxy evidence')
            || agentEvaluationRequireString($proxy, 'transport', 'explanation proxy evidence')
                !== 'container-loopback-to-stdio'
            || $transportTools === null
            || $transportTools !== $recordedTransportTools
            || $providerUsage !== $recordedUsage
            || $runnerUsage !== $recordedUsage
            || agentEvaluationRequireNonNegativeInteger(
                $spending,
                'reserved_units',
                'explanation proxy spending ledger',
            ) !== 0
            || $settledUnits !== $expectedSettledUnits
            || $settledUnits > $expectedPolicy['limit_units']
            || ($ledger['blocked'] ?? null) !== false
            || agentEvaluationRequirePositiveInteger($ledger, 'request_count', 'explanation proxy ledger') < 1
            || agentEvaluationRequirePositiveInteger(
                $ledger,
                'observed_request_count',
                'explanation proxy ledger',
            ) !== agentEvaluationRequirePositiveInteger(
                $actions,
                'host_proxy_requests',
                'live explanation external-action evidence',
            )
        ) {
            throw new RuntimeException('Explanation proxy evidence does not match the validated live run.');
        }

        agentEvaluationValidateExplanationOciPreflightEvidence(
            agentEvaluationJsonFile($root . '/oci-preflight.json'),
            $observedIsolation,
            $observedProfile['runner'],
        );
    }

    $generationCleanup = agentEvaluationJsonFile($root . '/generation-cleanup.json');
    agentEvaluationRequireExactKeys(
        $generationCleanup,
        $executionKind === 'live-model' ? ['status', 'oci', 'removed'] : ['status', 'removed'],
        'explanation generation-cleanup evidence',
    );
    $generationRemoved = agentEvaluationRequireStringList(
        $generationCleanup,
        'removed',
        'explanation generation-cleanup evidence',
    );
    $generationCleanupStatus = agentEvaluationRequireString(
        $generationCleanup,
        'status',
        'explanation generation-cleanup evidence',
    );
    $cleanup = agentEvaluationJsonFile($root . '/cleanup.json');
    agentEvaluationRequireExactKeys(
        $cleanup,
        ['status', 'removed', 'primary_failure', 'cleanup_failure'],
        'explanation final-cleanup evidence',
    );
    $finalRemoved = agentEvaluationRequireStringList(
        $cleanup,
        'removed',
        'explanation final-cleanup evidence',
    );
    $cleanupStatus = agentEvaluationRequireString($cleanup, 'status', 'explanation final-cleanup evidence');

    if (
        $generationCleanupStatus !== 'pass'
        || $generationRemoved !== ['candidate', 'baseline', 'dependencies']
    ) {
        throw new RuntimeException('Explanation generation cleanup does not match the completed run lifecycle.');
    }

    if (!in_array($cleanupStatus, ['pass', 'fail'], true) || $finalRemoved !== []) {
        throw new RuntimeException('Explanation final cleanup evidence is incoherent.');
    }

    $primaryFailure = agentEvaluationValidateExplanationFailureEvidence(
        $cleanup['primary_failure'],
        'explanation final cleanup primary failure',
        false,
    );
    $cleanupFailure = agentEvaluationValidateExplanationFailureEvidence(
        $cleanup['cleanup_failure'],
        'explanation final cleanup failure',
        true,
    );

    if (
        ($cleanupStatus === 'pass' && ($primaryFailure !== null || $cleanupFailure !== null))
        || ($cleanupStatus === 'fail' && $cleanupFailure === null)
    ) {
        throw new RuntimeException('Explanation final cleanup evidence is incoherent.');
    }

    $scoreChecks = agentEvaluationRequireObject($score, 'structural_checks', 'explanation score record');
    $scoreCleanup = agentEvaluationRequireBoolean($scoreChecks, 'cleanup', 'explanation score structural checks');

    if ($scoreCleanup !== ($cleanupStatus === 'pass')) {
        throw new RuntimeException('Explanation cleanup score does not match final cleanup evidence.');
    }

    if ($executionKind === 'live-model') {
        $generationOci = agentEvaluationRequireObject(
            $generationCleanup,
            'oci',
            'explanation generation-cleanup evidence',
        );
        agentEvaluationRequireExactKeys(
            $generationOci,
            ['status', 'generation_destroyed'],
            'explanation generation OCI cleanup evidence',
        );
        $ociCleanup = agentEvaluationJsonFile($root . '/oci-cleanup.json');
        agentEvaluationRequireExactKeys(
            $ociCleanup,
            ['verified', 'status', 'containers_remaining', 'volumes_remaining'],
            'explanation OCI cleanup evidence',
        );
        $generationOciStatus = agentEvaluationRequireString(
            $generationOci,
            'status',
            'explanation generation OCI cleanup evidence',
        );
        $generationDestroyed = agentEvaluationRequireBoolean(
            $generationOci,
            'generation_destroyed',
            'explanation generation OCI cleanup evidence',
        );
        $ociStatus = agentEvaluationRequireString(
            $ociCleanup,
            'status',
            'explanation OCI cleanup evidence',
        );
        $ociVerified = agentEvaluationRequireBoolean(
            $ociCleanup,
            'verified',
            'explanation OCI cleanup evidence',
        );
        $containersRemaining = agentEvaluationRequireNonNegativeInteger(
            $ociCleanup,
            'containers_remaining',
            'explanation OCI cleanup evidence',
        );
        $volumesRemaining = agentEvaluationRequireNonNegativeInteger(
            $ociCleanup,
            'volumes_remaining',
            'explanation OCI cleanup evidence',
        );
        $resourcesAbsent = $containersRemaining === 0 && $volumesRemaining === 0;

        if (
            $generationOciStatus !== 'pass'
            || !$generationDestroyed
            || !in_array($ociStatus, ['pass', 'fail'], true)
            || $ociVerified !== $resourcesAbsent
            || ($ociStatus === 'pass') !== $resourcesAbsent
            || ($cleanupStatus === 'pass' && !$resourcesAbsent)
        ) {
            throw new RuntimeException('Explanation OCI cleanup evidence is incoherent.');
        }
    }
}

function agentEvaluationExplanationTrackedComposerLockSha256(string $manifest): string
{
    $matched = preg_match_all(
        '/(?:\A|\n)100644 ([a-f0-9]{64}) composer\.lock\n/D',
        $manifest,
        $matches,
    );

    if ($matched !== 1) {
        throw new RuntimeException(
            'Explanation tracked source manifest must bind one regular composer.lock.',
        );
    }

    return $matches[1][0];
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

/** @return array<string, mixed>|null */
function agentEvaluationValidateExplanationFailureEvidence(
    mixed $value,
    string $owner,
    bool $cleanupFailure,
): ?array {
    if ($value === null) {
        return null;
    }

    $failure = agentEvaluationValueObject($value, $owner);

    if ($cleanupFailure) {
        agentEvaluationRequireExactKeys($failure, ['class'], $owner);
    } else {
        $keys = array_keys($failure);
        $allowed = ['phase', 'class', 'code', 'reason_code'];

        if (
            !isset($failure['phase'], $failure['class'])
            || array_diff($keys, $allowed) !== []
        ) {
            throw new RuntimeException("{$owner} has an invalid bounded shape.");
        }

        $phase = agentEvaluationRequireString($failure, 'phase', $owner);
        if (!in_array(
            $phase,
            ['prepare', 'generate', 'freeze', 'score', 'validate', 'retain', 'cleanup'],
            true,
        )) {
            throw new RuntimeException("{$owner} has an invalid bounded shape.");
        }

        if (array_key_exists('code', $failure)) {
            $code = agentEvaluationRequireString($failure, 'code', $owner);
            if (preg_match('/\AAGENT_EVALUATION_CONTROLLER_[A-Z0-9_]+\z/D', $code) !== 1) {
                throw new RuntimeException("{$owner} has an invalid bounded shape.");
            }
        }

        if (array_key_exists('reason_code', $failure)) {
            $reasonCode = agentEvaluationRequireString($failure, 'reason_code', $owner);
            if (preg_match('/\A[a-z][a-z0-9_]{0,95}\z/D', $reasonCode) !== 1) {
                throw new RuntimeException("{$owner} has an invalid bounded shape.");
            }
        }
    }

    $class = agentEvaluationRequireNonEmptyString($failure, 'class', $owner);
    if (
        strlen($class) > 256
        || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $class) !== 1
    ) {
        throw new RuntimeException("{$owner} has an invalid bounded shape.");
    }

    return $failure;
}

/** @return list<string> */
function agentEvaluationExplanationOuterEvidencePaths(string $executionKind): array
{
    $paths = [
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
        'response.txt',
        'rubric.md',
        'run.json',
        'score.json',
        'source-prompt.md',
        'structural-evidence.json',
        'task.json',
        'tracked-source.manifest',
        'validation.json',
        'workspace-policy.json',
    ];

    if ($executionKind === 'live-model') {
        $paths = [
            ...$paths,
            'approval.json',
            'dependencies.lock',
            'dependencies.installed.json',
            'oci-cleanup.json',
            'oci-preflight.json',
            'proxy.json',
        ];
    } elseif ($executionKind !== 'synthetic-control') {
        throw new RuntimeException('Explanation outer evidence requires a known execution kind.');
    }

    sort($paths, SORT_STRING);

    return $paths;
}

/** @param array<string, mixed> $score
 * @return array<string, mixed>
 */
function agentEvaluationExplanationPendingScore(array $score): array
{
    $unknown = static fn (): array => [
        'status' => 'unknown',
        'evidence' => 'Pending accountable review of the retained response and events.',
    ];
    $score['human_review'] = [
        'status' => 'pending',
        'reviewer' => null,
        'dimensions' => [
            'route_selection' => $unknown(),
            'necessary_concern_coverage' => $unknown(),
            'unsupported_claims' => $unknown(),
            'answer_correctness' => $unknown(),
            'repairs' => $unknown(),
            'clarification' => $unknown(),
        ],
        'reason' => 'Semantic correctness requires a separate accountable human review.',
    ];
    $score['correct_completion'] = null;

    return $score;
}

/**
 * @param array<string, mixed> $ledger
 * @return array<string, string>
 */
function agentEvaluationExplanationRecoveryResources(array $ledger, string $kind, string $owner): array
{
    $value = $ledger[$kind] ?? null;
    $resources = $value instanceof stdClass
        ? get_object_vars($value)
        : $value;

    if (!is_array($resources) || ($resources !== [] && array_is_list($resources))) {
        throw new RuntimeException('Explanation OCI recovery ledger contains an invalid resource map.');
    }

    $validated = [];

    foreach ($resources as $role => $name) {
        if (
            !is_string($role)
            || !is_string($name)
            || preg_match('/\A[a-z][a-z0-9-]{0,95}\z/D', $role) !== 1
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}\z/D', $name) !== 1
        ) {
            throw new RuntimeException('Explanation OCI recovery ledger contains an invalid resource map.');
        }

        $resourceRole = str_starts_with($role, 'pending-creation-')
            ? substr($role, strlen('pending-creation-'))
            : $role;

        if ($resourceRole === '' || $name !== $owner . '-' . $resourceRole) {
            throw new RuntimeException('Explanation OCI recovery ledger contains an invalid resource map.');
        }

        $validated[$role] = $name;
    }

    return $validated;
}

/**
 * Validate the controller's final outer inventory after structural score replay.
 *
 * The evidence manifest intentionally predates accountable human review. A reviewed
 * live score may replace score.json, but the manifest must continue to bind the
 * exact canonical pending score derived from the already validated automated fields.
 *
 * @param array<string, mixed> $score
 * @param array<string, mixed> $runRecord
 */
function agentEvaluationValidateExplanationOuterEvidence(
    array $score,
    array $runRecord,
    string $artifactRoot,
): void {
    $root = realpath($artifactRoot);

    if (!is_string($root) || !is_dir($root)) {
        throw new RuntimeException('Explanation outer evidence root is unavailable.');
    }

    $executionKind = agentEvaluationRequireString($runRecord, 'execution_kind', 'explanation run record');
    $validationPath = agentEvaluationContainedArtifactPath(
        $root,
        'validation.json',
        'explanation validation evidence',
    );
    $manifestPath = agentEvaluationContainedArtifactPath(
        $root,
        'evidence-manifest.json',
        'explanation evidence manifest',
    );
    agentEvaluationRequireBoundedFile(
        $validationPath,
        AGENT_EVALUATION_MAX_JSON_BYTES,
        'explanation validation evidence',
    );
    agentEvaluationRequireBoundedFile(
        $manifestPath,
        AGENT_EVALUATION_MAX_JSON_BYTES,
        'explanation evidence manifest',
    );
    $validation = agentEvaluationJsonFile($validationPath);
    agentEvaluationRequireExactKeys(
        $validation,
        ['v3_run_record', 'v3_score_record', 'structural_status', 'semantic_review'],
        'explanation validation evidence',
    );

    if ($validation !== [
        'v3_run_record' => 'pass',
        'v3_score_record' => 'pass',
        'structural_status' => agentEvaluationRequireString(
            $score,
            'automated_status',
            'explanation score record',
        ),
        'semantic_review' => 'pending',
    ]) {
        throw new RuntimeException('Explanation validation evidence does not match the replayed score.');
    }

    $fixedScore = agentEvaluationJsonFile($root . '/score.json');
    if (agentEvaluationJson($fixedScore) !== agentEvaluationJson($score)) {
        throw new RuntimeException('Explanation score input must be the retained score.json record.');
    }

    $pendingScore = agentEvaluationExplanationPendingScore($score);
    $review = agentEvaluationRequireObject($score, 'human_review', 'explanation score record');
    $reviewStatus = agentEvaluationRequireString($review, 'status', 'explanation score human review');

    if ($reviewStatus === 'pending' && agentEvaluationJson($score) !== agentEvaluationJson($pendingScore)) {
        throw new RuntimeException('Pending explanation outer evidence must retain the canonical controller score.');
    }

    $pendingScoreBytes = agentEvaluationJson($pendingScore);
    $manifest = agentEvaluationJsonFile($manifestPath);
    agentEvaluationRequireExactKeys(
        $manifest,
        [
            'schema_version',
            'controller_version',
            'run_id',
            'task_id',
            'task_revision',
            'synthetic',
            'comparative_claims',
            'explanation_execution',
            'condition',
            'expected_phase_order',
            'observed_phases',
            'primary_failure',
            'cleanup_failure',
            'artifacts',
        ],
        'explanation evidence manifest',
    );
    $phases = ['prepare', 'generate', 'freeze', 'score', 'validate', 'retain', 'cleanup'];
    $cleanup = agentEvaluationJsonFile($root . '/cleanup.json');

    if (
        agentEvaluationRequireInteger($manifest, 'schema_version', 'explanation evidence manifest') !== 1
        || agentEvaluationRequireInteger($manifest, 'controller_version', 'explanation evidence manifest') !== 2
        || agentEvaluationRequireString($manifest, 'run_id', 'explanation evidence manifest')
            !== agentEvaluationRequireString($runRecord, 'run_id', 'explanation run record')
        || agentEvaluationRequireString($manifest, 'task_id', 'explanation evidence manifest')
            !== agentEvaluationRequireString($runRecord, 'task_id', 'explanation run record')
        || agentEvaluationRequireInteger($manifest, 'task_revision', 'explanation evidence manifest')
            !== agentEvaluationRequireInteger($runRecord, 'task_revision', 'explanation run record')
        || agentEvaluationRequireBoolean($manifest, 'synthetic', 'explanation evidence manifest')
            !== ($executionKind === 'synthetic-control')
        || agentEvaluationRequireBoolean($manifest, 'comparative_claims', 'explanation evidence manifest')
        || !agentEvaluationRequireBoolean($manifest, 'explanation_execution', 'explanation evidence manifest')
        || agentEvaluationRequireString($manifest, 'condition', 'explanation evidence manifest')
            !== 'repository-only'
        || ($manifest['expected_phase_order'] ?? null) !== $phases
        || ($manifest['observed_phases'] ?? null) !== $phases
        || agentEvaluationValidateExplanationFailureEvidence($manifest['primary_failure'], 'explanation manifest primary failure', false)
            !== agentEvaluationValidateExplanationFailureEvidence($cleanup['primary_failure'], 'explanation cleanup primary failure', false)
        || agentEvaluationValidateExplanationFailureEvidence($manifest['cleanup_failure'], 'explanation manifest cleanup failure', true)
            !== agentEvaluationValidateExplanationFailureEvidence($cleanup['cleanup_failure'], 'explanation cleanup failure', true)
    ) {
        throw new RuntimeException('Explanation evidence manifest does not match its final lifecycle.');
    }

    $entries = scandir($root);
    if ($entries === false) {
        throw new RuntimeException('Unable to enumerate explanation outer evidence.');
    }
    $actualNames = [];
    $paths = [$manifestPath];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'evidence-manifest.json') {
            continue;
        }

        if (str_contains($entry, '/')) {
            throw new RuntimeException('Explanation outer evidence must use flat artifact names.');
        }

        $path = agentEvaluationContainedArtifactPath($root, $entry, "explanation outer artifact {$entry}");
        agentEvaluationRequireBoundedFile(
            $path,
            AGENT_EVALUATION_MAX_ARTIFACT_BYTES,
            "explanation outer artifact {$entry}",
        );
        $actualNames[] = $entry;
        $paths[] = $path;
    }

    sort($actualNames, SORT_STRING);
    $expectedNames = agentEvaluationExplanationOuterEvidencePaths($executionKind);
    if ($executionKind === 'live-model' && in_array('owned-resources.json', $actualNames, true)) {
        $expectedNames[] = 'owned-resources.json';
        sort($expectedNames, SORT_STRING);
    }

    if ($actualNames !== $expectedNames) {
        throw new RuntimeException(
            'Explanation outer evidence must contain exactly its fixed final artifact inventory.',
        );
    }

    agentEvaluationRequireDistinctFileIdentities($paths);

    if (in_array('owned-resources.json', $expectedNames, true)) {
        $ledger = agentEvaluationJsonFile($root . '/owned-resources.json');
        agentEvaluationRequireExactKeys(
            $ledger,
            ['owner', 'run_id', 'containers', 'volumes'],
            'explanation OCI recovery ledger',
        );
        $owner = agentEvaluationRequireString($ledger, 'owner', 'explanation OCI recovery ledger');
        $ledgerRunId = agentEvaluationRequireString($ledger, 'run_id', 'explanation OCI recovery ledger');
        $containers = agentEvaluationExplanationRecoveryResources($ledger, 'containers', $owner);
        $volumes = agentEvaluationExplanationRecoveryResources($ledger, 'volumes', $owner);
        $ociCleanup = agentEvaluationJsonFile($root . '/oci-cleanup.json');
        $cleanupFailed = ($cleanup['status'] ?? null) === 'fail'
            && ($cleanup['cleanup_failure'] ?? null) !== null;

        if (
            preg_match('/\Aphpthis-eval-[a-f0-9]{32}\z/D', $owner) !== 1
            || $ledgerRunId !== agentEvaluationRequireString($runRecord, 'run_id', 'explanation run record')
            || count($containers) !== agentEvaluationRequireNonNegativeInteger(
                $ociCleanup,
                'containers_remaining',
                'explanation OCI cleanup evidence',
            )
            || count($volumes) !== agentEvaluationRequireNonNegativeInteger(
                $ociCleanup,
                'volumes_remaining',
                'explanation OCI cleanup evidence',
            )
            || (($containers !== [] || $volumes !== []) && !$cleanupFailed)
        ) {
            throw new RuntimeException(
                'Explanation OCI recovery ledger does not match the run and final cleanup evidence.',
            );
        }
    }

    $artifacts = agentEvaluationRequireObject($manifest, 'artifacts', 'explanation evidence manifest');
    agentEvaluationRequireExactKeys($artifacts, $expectedNames, 'explanation evidence manifest artifacts');

    foreach ($expectedNames as $name) {
        $artifact = agentEvaluationValueObject(
            $artifacts[$name] ?? null,
            "explanation evidence manifest artifact {$name}",
        );
        agentEvaluationRequireExactKeys(
            $artifact,
            ['bytes', 'sha256'],
            "explanation evidence manifest artifact {$name}",
        );
        $recordedBytes = agentEvaluationRequireNonNegativeInteger(
            $artifact,
            'bytes',
            "explanation evidence manifest artifact {$name}",
        );
        $recordedHash = agentEvaluationRequireHash(
            agentEvaluationRequireString(
                $artifact,
                'sha256',
                "explanation evidence manifest artifact {$name}",
            ),
            "explanation evidence manifest artifact {$name}",
        );
        $expectedBytes = $name === 'score.json'
            ? strlen($pendingScoreBytes)
            : filesize($root . '/' . $name);
        $expectedHash = $name === 'score.json'
            ? hash('sha256', $pendingScoreBytes)
            : agentEvaluationFileHash($root . '/' . $name, "explanation outer artifact {$name}");

        if (!is_int($expectedBytes) || $recordedBytes !== $expectedBytes || !hash_equals($expectedHash, $recordedHash)) {
            throw new RuntimeException("Explanation evidence manifest artifact {$name} does not match final evidence.");
        }
    }

    $runBindings = [
        'source-prompt.md' => 'prompt_sha256',
        'rubric.md' => 'rubric_sha256',
        'prepared-dependencies.manifest' => 'prepared_dependencies_manifest_sha256',
    ];
    if ($executionKind === 'live-model') {
        $runBindings['dependencies.installed.json'] = 'prepared_installed_metadata_sha256';
    }

    foreach ($runBindings as $artifactName => $recordField) {
        $artifact = agentEvaluationValueObject(
            $artifacts[$artifactName] ?? null,
            "explanation evidence manifest artifact {$artifactName}",
        );
        if (
            agentEvaluationRequireString(
                $artifact,
                'sha256',
                "explanation evidence manifest artifact {$artifactName}",
            ) !== agentEvaluationRequireString($runRecord, $recordField, 'explanation run record')
        ) {
            throw new RuntimeException(
                "Explanation evidence manifest artifact {$artifactName} is not bound to the run record.",
            );
        }
    }
}
