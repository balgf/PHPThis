<?php

declare(strict_types=1);

const AGENT_EVALUATION_TASK_REVISIONS = [
    'change.simple-ping' => [
        'schema_version' => 1,
        'revision' => 29,
        'manifest_sha256' => '2763bdb2ccf21cde598ebf9b926ee98c89092426107e41d3e003a39625158654',
    ],
    'change.protected-endpoint' => [
        'schema_version' => 2,
        'revision' => 7,
        'manifest_sha256' => '0edb91edd860338959edad56d4725ca0b18057d4b6e1b373d9f1e4aab8624649',
    ],
    'repair.transaction-rollback' => [
        'schema_version' => 2,
        'revision' => 7,
        'manifest_sha256' => '70a615f2c43897fdca2e4934d7a29e132aa00a471a38d9df6d245ced52bdc524',
    ],
    'change.filtered-collection' => [
        'schema_version' => 2,
        'revision' => 7,
        'manifest_sha256' => '492beb6546c111cc57706766e3fd44a0da85ba9d5dbd7a298a6eb9df03eb8e32',
    ],
    'explain.file-profile-s3' => [
        'schema_version' => 3,
        'revision' => 15,
        'manifest_sha256' => 'c57f32415f3465a4733d29495ba6321c616f95e12af226cedc489236949b9574',
    ],
];

const AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256 = '4112bfec48681b01cf24edf30a4115da4538a1e662b19c485cfffde69f5ca6ab';
const AGENT_EVALUATION_COMPARISON_TASK_SCHEMA_SHA256 = '7ad4659623022d9884bf5f7a15fd35d6dc3b7cc676b3cc76cab6e3f7ff114ea8';
const AGENT_EVALUATION_COMPARISON_PROTOCOL_SCHEMA_SHA256 = '69e834fb1ce2869586a891831b5f0543415611940430650ecc1438678c2cd874';
const AGENT_EVALUATION_EXPLANATION_TASK_ID = 'explain.file-profile-s3';
const AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION = 'ce369d9f8f29775a6cc3819617ff0ccdadba481e';
const AGENT_EVALUATION_EXPLANATION_SOURCE_TREE = 'ef58eac15f569bcc8b340a0a666eb68082a2094f';
const AGENT_EVALUATION_EXPLANATION_SOURCE_FIXTURE_SHA256 = '9471b5f5a2e83db7b13891a9669def11f4a7876ea47068dee13526e0637380c4';
const AGENT_EVALUATION_EXPLANATION_EFFECTIVE_PROMPT_SHA256 = '100c9e40670cadd4bcb39e0673461ee2cb1b171be1ddbec002e3413c87b9939c';
const AGENT_EVALUATION_EXPLANATION_TASK_SCHEMA_SHA256 = '46667ae3215cb5a55ad17d5062eccb871daa65f29258aad89ca425b674525ce9';
const AGENT_EVALUATION_EXPLANATION_RUN_SCHEMA_SHA256 = '9f6378ffc9abbe91ae2443c2afbc2f6b0179fcdc0c37faf0ab672e1be3f80621';
const AGENT_EVALUATION_EXPLANATION_SCORE_SCHEMA_SHA256 = '4811c0b55524f243539556f98336ca152791a6f616141fc28d74f6b9cba4510a';
const AGENT_EVALUATION_EXPLANATION_ENTRYPOINT_COMMAND = 'cat VISION.md .ai/README.md .ai/rules.md .ai/change-workflow.md .ai/strict-profile.md';
const AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON = 'import pathlib,sys; p,a,b=sys.argv[1:]; a,b=int(a),int(b); '
    . '(1<=a<=b and b-a<20) or sys.exit("Invalid range: use 1-20 lines"); '
    . 'lines=pathlib.Path(p).read_bytes().splitlines(keepends=True); a<=len(lines) or sys.exit("Start exceeds file"); '
    . 'data=b"".join(lines[a-1:b]); data.decode("utf-8"); '
    . 'len(data)<=8192 or sys.exit("Narrow range: exceeds 8192 bytes"); sys.stdout.buffer.write(data)';
const AGENT_EVALUATION_EXPLANATION_BOUNDED_SEARCH_PYTHON = 'import pathlib,re,sys; pattern,*paths=sys.argv[1:]; '
    . '(1<=len(paths)<=4) or sys.exit("Use 1-4 exact paths"); r=re.compile(pattern); '
    . 'hits=[f"{p}:{i}:{line}\n" for p in paths for i,line in '
    . 'enumerate(pathlib.Path(p).read_text(encoding="utf-8").splitlines(),1) if r.search(line)]; '
    . 'data="".join(hits).encode("utf-8"); '
    . '(len(hits)<=40 and len(data)<=4096) or sys.exit("Narrow search: exceeds 40 hits or 4096 bytes"); '
    . 'sys.stdout.buffer.write(data)';
const AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX = 'This is an explanation-only evaluation. Do not modify files. Answer from the pinned workspace.'
    . "\n\n"
    . 'Reading protocol (revision 15): Follow AGENTS.md and all mandatory entrypoints. '
    . 'First read all five mandatory files completely by running this exact command once in one shell invocation:'
    . "\n\n" . AGENT_EVALUATION_EXPLANATION_ENTRYPOINT_COMMAND . "\n\n"
    . 'Do not rediscover these known paths, count their lines, or use the selected-section reader for this initial batch. '
    . 'The section limits below apply only after this batch. '
    . 'Then select the owning guide through the router, locate its headings, and read relevant sections before following exact links to policy, source, and tests. '
    . 'For an unfamiliar guide or linked document, begin with selected windows of at most 20 lines. '
    . 'Continue in adjacent windows only when needed; do not request the whole document or the maximum span by default. '
    . 'Use scoped filename discovery only for unknown paths. Avoid repository-wide content searches and searches spanning multiple concern directories. '
    . 'After entrypoints, each selected read must fit 20 lines and 8,192 bytes. A line bound alone is insufficient. '
    . 'For selected sections use the read-only command below with an exact PATH and inclusive START/END line numbers. '
    . 'Request at most 20 lines; END may extend past EOF and returns the remaining lines, but START must exist. '
    . 'Invalid ranges, encoding, or excess bytes emit no source. '
    . 'Narrow the range on error; do not pipe a whole file through a truncating command. '
    . 'Batch independent reads only when their combined output fits 8,192 bytes; avoid rereading unchanged text, and stop when evidence is sufficient. '
    . 'Do not skip required concerns or invent missing evidence to fit the budget. '
    . 'Inspect concrete execution-path source and its nearest test when the pinned workspace contains an actual implementation. '
    . 'When the owning guide instead names a synthetic framework reference test for an optional consumer-owned profile, inspect that exact test and its direct caller. '
    . 'Do not search unrelated test families for consumer application source that the guide says is outside this workspace; state missing consumer evidence as a condition. '
    . 'Follow the index\'s explicit adoption-review links, but do not reread an already supported concern solely to find another restatement. '
    . 'An index row addressed to adoption or review is necessary linked policy: read one relevant bounded section from each named current page even when a selected-profile page summarizes it. '
    . 'After reading the current guide, necessary linked policy, and the concrete source and nearest test or named reference proof, ask whether any unresolved fact would change the answer. '
    . 'For a generic possible-adoption review, absent consumer-specific records, implementations, and results are limitations to explain, not facts to discover in the framework. '
    . 'Before the final answer, check the draft against the owning guide\'s shared-versus-selected-profile split. '
    . 'State which common ingress and request-policy boundary remains in force, what profile selection changes without implying that the former profile\'s rules changed, and which application-owned source and evidence would make the new profile valid. '
    . 'Include required concerns in the answer rather than relying on having read them; if a necessary fact lacks support, make one targeted read. '
    . 'If not, the next assistant message must be the final answer, not a progress update; do not make another tool call to reconfirm an already supported claim. '
    . 'If a fact would change the answer, make only a targeted read for that fact, return to this checkpoint, and state any remaining limit in the final answer.'
    . "\n\npython3 -I -B -c '" . AGENT_EVALUATION_EXPLANATION_BOUNDED_READ_PYTHON . "' PATH START END"
    . "\n\nFor line-based content searches after the entrypoint batch, use the bounded command below with 1-4 exact known file paths. "
    . 'Search headings first with the pattern `^#{1,6} `, then narrow to specific terms and paths if needed. '
    . 'For content searches, use one exact file and one specific term. '
    . 'Do not combine profile names with regex alternation or search several documents for broad terms. '
    . 'When the heading map identifies a relevant section, read its 20-line window directly instead of searching. '
    . 'If a content search is rejected, use the heading map to read one linked document section; do not repeat a broad search. '
    . 'It rejects more than 40 matches or 4,096 output bytes without emitting partial source; narrow the pattern or path list on rejection. '
    . 'Do not use raw grep or rg for document content searches.'
    . "\n\npython3 -I -B -c '" . AGENT_EVALUATION_EXPLANATION_BOUNDED_SEARCH_PYTHON . "' PATTERN PATH [PATH ...]";
const AGENT_EVALUATION_EXPLANATION_MAX_EVENTS = 4_096;
const AGENT_EVALUATION_EXPLANATION_RELAY_SHA256 = 'eef4017c83216929f74504e0025821b12232190b8d87257cd8bc6186dfbfe123';

/**
 * @return list<array{
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   directory: string
 * }>
 */
function agentEvaluationValidateKit(string $kit): array
{
    $inventory = agentEvaluationJsonValueFile($kit . '/tasks.json');

    if (
        !is_array($inventory)
        || !array_is_list($inventory)
        || $inventory !== array_keys(AGENT_EVALUATION_TASK_REVISIONS)
    ) {
        throw new RuntimeException('The agent-evaluation task inventory must equal the pinned non-empty revision order.');
    }

    $schemaHashes = [
        'task.schema.json' => '55b47a225496cabbaf928c21e5aa0934fe8eb1eeed7dde26b926bb2390cd1327',
        'run.schema.json' => 'f5e9c84592da0064c35cacd54dbdfbe9a05b71ff35483bea841f4fe998234942',
        'score.schema.json' => 'd32d675230d5cfb15c539d91cf6525ba7d993c543436bde4300b89342b51f233',
        'task-v2.schema.json' => AGENT_EVALUATION_COMPARISON_TASK_SCHEMA_SHA256,
        'comparison-protocol-v1.schema.json' => AGENT_EVALUATION_COMPARISON_PROTOCOL_SCHEMA_SHA256,
        'run-v2.schema.json' => '626bf4903096e08e517685214bee11a204640c15e63307d65aecc14fb48db457',
        'score-v2.schema.json' => '7f5d68e3b4056961e1dbcfe85159a0378b67f11cd9c8d597f71bd148b4c79364',
        'task-v3.schema.json' => AGENT_EVALUATION_EXPLANATION_TASK_SCHEMA_SHA256,
        'run-v3.schema.json' => AGENT_EVALUATION_EXPLANATION_RUN_SCHEMA_SHA256,
        'score-v3.schema.json' => AGENT_EVALUATION_EXPLANATION_SCORE_SCHEMA_SHA256,
    ];

    foreach ($schemaHashes as $schema => $hash) {
        agentEvaluationRequireFileHash($kit . '/schema/' . $schema, $hash, "schema/{$schema}");
        $document = agentEvaluationJsonFile($kit . '/schema/' . $schema);
        agentEvaluationRequireString($document, '$schema', "schema/{$schema}");
        agentEvaluationRequireString($document, 'title', "schema/{$schema}");
    }

    agentEvaluationComparisonProtocol($kit);
    $tasks = [];

    foreach (AGENT_EVALUATION_TASK_REVISIONS as $taskId => $pinnedRevision) {
        $version = $pinnedRevision['schema_version'];
        if ($version === 1) {
            $task = agentEvaluationTaskDocument($kit, $taskId);
        } elseif ($version === 2) {
            $task = agentEvaluationComparisonTaskDocument($kit, $taskId);
        } else {
            $task = agentEvaluationExplanationTaskDocument($kit, $taskId);
        }

        if ($task['revision'] !== $pinnedRevision['revision']) {
            throw new RuntimeException("Task {$taskId} revision does not match its pinned identity.");
        }

        if (!hash_equals($pinnedRevision['manifest_sha256'], $task['manifest_sha256'])) {
            throw new RuntimeException("Task {$taskId} manifest SHA-256 does not match its pinned revision.");
        }

        if ($version === 1) {
            $tasks[] = $task;
        }
    }

    return $tasks;
}

/**
 * @return array{
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   directory: string
 * }
 */
function agentEvaluationTask(string $kit, string $taskId): array
{
    $tasks = agentEvaluationValidateKit($kit);

    foreach ($tasks as $task) {
        if ($task['id'] === $taskId) {
            return $task;
        }
    }

    throw new RuntimeException("Unknown agent-evaluation task: {$taskId}.");
}

/**
 * @return array{
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   public_scorer: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{tree: string, fixture_sha256: string},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   directory: string
 * }
 */
function agentEvaluationTaskDocument(string $kit, string $taskId): array
{
    $kitRoot = realpath($kit);
    $directoryCandidate = $kit . '/tasks/' . $taskId;
    $directory = realpath($directoryCandidate);

    if (
        !is_string($kitRoot)
        || !is_string($directory)
        || !is_dir($directory)
        || is_link($directoryCandidate)
        || !str_starts_with($directory, $kitRoot . DIRECTORY_SEPARATOR)
    ) {
        throw new RuntimeException("Task {$taskId} directory must remain inside the evaluation kit.");
    }

    $document = agentEvaluationJsonFile($directory . '/task.json');
    agentEvaluationRequireExactKeys(
        $document,
        [
            'schema_version',
            'id',
            'revision',
            'kind',
            'prompt',
            'rubric',
            'base',
            'workspace_policy',
            'budgets',
            'checks',
            'comparative_claims',
        ],
        "task {$taskId}",
    );

    if (agentEvaluationRequireInteger($document, 'schema_version', "task {$taskId}") !== 1) {
        throw new RuntimeException("Task {$taskId} must use schema version 1.");
    }

    $id = agentEvaluationRequireString($document, 'id', "task {$taskId}");

    if ($id !== $taskId) {
        throw new RuntimeException("Task inventory ID {$taskId} does not match task document ID {$id}.");
    }

    $revision = agentEvaluationRequirePositiveInteger($document, 'revision', "task {$taskId}");
    $kind = agentEvaluationRequireString($document, 'kind', "task {$taskId}");

    if ($kind !== 'implementation') {
        throw new RuntimeException("Task {$taskId} has an unsupported kind: {$kind}.");
    }

    $prompt = agentEvaluationRequireObject($document, 'prompt', "task {$taskId}");
    agentEvaluationRequireExactKeys($prompt, ['path', 'sha256'], "task {$taskId} prompt");
    $promptPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($prompt, 'path', "task {$taskId} prompt"),
        "task {$taskId} prompt",
    );
    $promptHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($prompt, 'sha256', "task {$taskId} prompt"),
        "task {$taskId} prompt",
    );
    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $promptPath, "task {$taskId} prompt"),
        $promptHash,
        "task {$taskId} prompt",
    );

    $rubric = agentEvaluationRequireObject($document, 'rubric', "task {$taskId}");
    agentEvaluationRequireExactKeys($rubric, ['path', 'sha256'], "task {$taskId} rubric");
    $rubricPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($rubric, 'path', "task {$taskId} rubric"),
        "task {$taskId} rubric",
    );
    $rubricHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($rubric, 'sha256', "task {$taskId} rubric"),
        "task {$taskId} rubric",
    );
    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $rubricPath, "task {$taskId} rubric"),
        $rubricHash,
        "task {$taskId} rubric",
    );

    $base = agentEvaluationRequireObject($document, 'base', "task {$taskId}");
    agentEvaluationRequireExactKeys(
        $base,
        ['fixture', 'tree', 'fixture_sha256'],
        "task {$taskId} base",
    );

    if (agentEvaluationRequireString($base, 'fixture', "task {$taskId} base") !== 'source-skeleton') {
        throw new RuntimeException("Task {$taskId} must use the explicit source-skeleton fixture.");
    }

    $baseTree = agentEvaluationRequireString($base, 'tree', "task {$taskId} base");

    if (preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/D', $baseTree) !== 1) {
        throw new RuntimeException("Task {$taskId} base tree must be one lowercase Git object ID.");
    }

    $baseFixtureHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($base, 'fixture_sha256', "task {$taskId} base"),
        "task {$taskId} base fixture",
    );

    agentEvaluationValidateWorkspacePolicy(
        agentEvaluationRequireObject($document, 'workspace_policy', "task {$taskId}"),
        $taskId,
    );
    $budgets = agentEvaluationValidateBudgets(
        agentEvaluationRequireObject($document, 'budgets', "task {$taskId}"),
        $taskId,
    );

    $checks = agentEvaluationRequireObject($document, 'checks', "task {$taskId}");
    agentEvaluationRequireExactKeys($checks, ['application_check', 'public_scorer'], "task {$taskId} checks");

    if (agentEvaluationRequireString($checks, 'application_check', "task {$taskId} checks") !== 'composer check') {
        throw new RuntimeException("Task {$taskId} must retain the complete application check.");
    }

    $scorer = agentEvaluationRequireObject($checks, 'public_scorer', "task {$taskId} checks");
    agentEvaluationRequireExactKeys($scorer, ['path', 'sha256'], "task {$taskId} public scorer");
    $scorerPath = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($scorer, 'path', "task {$taskId} public scorer"),
        "task {$taskId} public scorer",
    );
    $scorerHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($scorer, 'sha256', "task {$taskId} public scorer"),
        "task {$taskId} public scorer",
    );
    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $scorerPath, "task {$taskId} public scorer"),
        $scorerHash,
        "task {$taskId} public scorer",
    );

    $comparativeClaims = agentEvaluationRequireBoolean($document, 'comparative_claims', "task {$taskId}");

    if ($comparativeClaims) {
        throw new RuntimeException("Public smoke task {$taskId} cannot authorize comparative claims.");
    }

    return [
        'id' => $id,
        'revision' => $revision,
        'kind' => $kind,
        'comparative_claims' => $comparativeClaims,
        'prompt' => ['path' => $promptPath, 'sha256' => $promptHash],
        'rubric' => ['path' => $rubricPath, 'sha256' => $rubricHash],
        'public_scorer' => ['path' => $scorerPath, 'sha256' => $scorerHash],
        'manifest_sha256' => agentEvaluationFileHash($directory . '/task.json', "task {$taskId} manifest"),
        'base' => ['tree' => $baseTree, 'fixture_sha256' => $baseFixtureHash],
        'budgets' => $budgets,
        'directory' => $directory,
    ];
}

/** @param array<string, mixed> $policy */
function agentEvaluationValidateWorkspacePolicy(array $policy, string $taskId): void
{
    agentEvaluationRequireExactKeys(
        $policy,
        [
            'allowed_existing_paths',
            'allowed_new_paths',
            'protected_paths',
            'max_changed_files',
            'max_added_lines',
            'max_deleted_lines',
        ],
        "task {$taskId} workspace policy",
    );
    agentEvaluationRequirePathList($policy, 'allowed_existing_paths', "task {$taskId} workspace policy");
    agentEvaluationRequirePathList($policy, 'allowed_new_paths', "task {$taskId} workspace policy");
    agentEvaluationRequirePathList($policy, 'protected_paths', "task {$taskId} workspace policy");
    $allowedExistingPaths = agentEvaluationRequireStringList(
        $policy,
        'allowed_existing_paths',
        "task {$taskId} workspace policy",
    );
    $protectedPaths = agentEvaluationRequireStringList(
        $policy,
        'protected_paths',
        "task {$taskId} workspace policy",
    );
    $allowedNewPaths = agentEvaluationRequireStringList(
        $policy,
        'allowed_new_paths',
        "task {$taskId} workspace policy",
    );

    foreach ($allowedExistingPaths as $existingPath) {
        foreach ($allowedNewPaths as $newPath) {
            if (
                agentEvaluationPathIsWithin($existingPath, $newPath)
                || agentEvaluationPathIsWithin($newPath, $existingPath)
            ) {
                throw new RuntimeException("Task {$taskId} cannot overlap existing and new permitted paths.");
            }
        }
    }

    foreach (array_merge($allowedExistingPaths, $allowedNewPaths) as $allowedPath) {
        foreach ($protectedPaths as $protectedPath) {
            if (
                agentEvaluationPathIsWithin($allowedPath, $protectedPath)
                || agentEvaluationPathIsWithin($protectedPath, $allowedPath)
            ) {
                throw new RuntimeException("Task {$taskId} cannot overlap permitted and protected paths.");
            }
        }
    }

    agentEvaluationRequirePositiveInteger($policy, 'max_changed_files', "task {$taskId} workspace policy");
    agentEvaluationRequireNonNegativeInteger($policy, 'max_added_lines', "task {$taskId} workspace policy");
    agentEvaluationRequireNonNegativeInteger($policy, 'max_deleted_lines', "task {$taskId} workspace policy");
}

function agentEvaluationPathIsWithin(string $path, string $ancestor): bool
{
    return $path === $ancestor || str_starts_with($path, $ancestor . '/');
}

/**
 * @param array<string, mixed> $budgets
 * @return array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int}
 */
function agentEvaluationValidateBudgets(array $budgets, string $taskId): array
{
    agentEvaluationRequireExactKeys(
        $budgets,
        ['model_tokens', 'wall_seconds', 'repair_turns', 'command_output_bytes'],
        "task {$taskId} budgets",
    );
    return [
        'model_tokens' => agentEvaluationRequirePositiveInteger($budgets, 'model_tokens', "task {$taskId} budgets"),
        'wall_seconds' => agentEvaluationRequirePositiveInteger($budgets, 'wall_seconds', "task {$taskId} budgets"),
        'repair_turns' => agentEvaluationRequireNonNegativeInteger($budgets, 'repair_turns', "task {$taskId} budgets"),
        'command_output_bytes' => agentEvaluationRequirePositiveInteger(
            $budgets,
            'command_output_bytes',
            "task {$taskId} budgets",
        ),
    ];
}

function agentEvaluationExplanationEffectivePrompt(string $sourcePrompt): string
{
    if (
        $sourcePrompt === ''
        || !str_ends_with($sourcePrompt, "\n")
        || str_contains($sourcePrompt, "\0")
    ) {
        throw new RuntimeException('Explanation source prompt must be non-empty newline-terminated text without NUL bytes.');
    }

    $prompt = $sourcePrompt . "\n" . AGENT_EVALUATION_EXPLANATION_PROMPT_SUFFIX . "\n";

    if (strlen($prompt) > AGENT_EVALUATION_MAX_JSON_BYTES) {
        throw new RuntimeException('Explanation effective prompt exceeds its fixed byte bound.');
    }

    return $prompt;
}

/**
 * @return array{
 *   schema_version: int,
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string, effective_sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{fixture: string, revision: string, tree: string, fixture_sha256: string},
 *   workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   execution_profile: array{condition: string, runner: array{name: string, version: string}, model: array{provider: string, id: string, revision: ?string, settings: array<string, mixed>}, context: array{bundle_id: ?string, bundle_sha256: ?string}, tools: list<array{name: string, version: ?string, permissions: list<string>}>, transport_tools: array{kind: string, count: int, sha256: string}},
 *   checks: array{structural: string, semantic_review: string},
 *   directory: string
 * }
 */
function agentEvaluationExplanationTask(string $kit): array
{
    agentEvaluationValidateKit($kit);

    return agentEvaluationExplanationTaskDocument($kit, AGENT_EVALUATION_EXPLANATION_TASK_ID);
}

/**
 * @return array{
 *   schema_version: int,
 *   id: string,
 *   revision: int,
 *   kind: string,
 *   comparative_claims: bool,
 *   prompt: array{path: string, sha256: string, effective_sha256: string},
 *   rubric: array{path: string, sha256: string},
 *   manifest_sha256: string,
 *   base: array{fixture: string, revision: string, tree: string, fixture_sha256: string},
 *   workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int},
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   execution_profile: array{condition: string, runner: array{name: string, version: string}, model: array{provider: string, id: string, revision: ?string, settings: array<string, mixed>}, context: array{bundle_id: ?string, bundle_sha256: ?string}, tools: list<array{name: string, version: ?string, permissions: list<string>}>, transport_tools: array{kind: string, count: int, sha256: string}},
 *   checks: array{structural: string, semantic_review: string},
 *   directory: string
 * }
 */
function agentEvaluationExplanationTaskDocument(string $kit, string $taskId): array
{
    $pin = AGENT_EVALUATION_TASK_REVISIONS[$taskId] ?? null;
    $kitRoot = realpath($kit);

    if (
        $taskId !== AGENT_EVALUATION_EXPLANATION_TASK_ID
        || !is_array($pin)
        || $pin['schema_version'] !== 3
        || !is_string($kitRoot)
    ) {
        throw new RuntimeException('Unknown explanation evaluation task.');
    }

    $candidate = $kitRoot . '/tasks/' . $taskId;
    $directory = realpath($candidate);

    if (
        !is_string($directory)
        || $directory !== $candidate
        || !is_dir($directory)
        || is_link($directory)
    ) {
        throw new RuntimeException('Explanation task must remain in its canonical inventory directory.');
    }

    $document = agentEvaluationJsonFile($directory . '/task.json');
    agentEvaluationRequireExactKeys(
        $document,
        [
            'schema_version',
            'id',
            'revision',
            'kind',
            'prompt',
            'rubric',
            'base',
            'workspace_policy',
            'budgets',
            'execution_profile',
            'checks',
            'comparative_claims',
        ],
        'explanation task',
    );

    $revision = agentEvaluationRequirePositiveInteger($document, 'revision', 'explanation task');

    if (
        ($document['schema_version'] ?? null) !== 3
        || ($document['id'] ?? null) !== AGENT_EVALUATION_EXPLANATION_TASK_ID
        || $revision !== $pin['revision']
        || ($document['kind'] ?? null) !== 'explanation'
    ) {
        throw new RuntimeException('Explanation task identity must match its explicit pinned version and kind.');
    }

    $promptDescriptor = agentEvaluationRequireObject($document, 'prompt', 'explanation task');
    agentEvaluationRequireExactKeys(
        $promptDescriptor,
        ['path', 'sha256', 'effective_sha256'],
        'explanation prompt',
    );
    $prompt = agentEvaluationExplanationArtifact(
        [
            'path' => agentEvaluationRequireString($promptDescriptor, 'path', 'explanation prompt'),
            'sha256' => agentEvaluationRequireString($promptDescriptor, 'sha256', 'explanation prompt'),
        ],
        $directory,
        'prompt.md',
        'explanation prompt',
    );
    $sourcePrompt = file_get_contents($directory . '/' . $prompt['path']);

    if (
        $sourcePrompt !== "Review whether a consumer may switch its adopted local file profile to Amazon S3.\n"
    ) {
        throw new RuntimeException('Explanation task prompt must equal the frozen routing-review seed.');
    }

    $effectivePrompt = agentEvaluationExplanationEffectivePrompt($sourcePrompt);
    $effectivePromptHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($promptDescriptor, 'effective_sha256', 'explanation prompt'),
        'explanation effective prompt',
    );

    if (
        $effectivePromptHash !== AGENT_EVALUATION_EXPLANATION_EFFECTIVE_PROMPT_SHA256
        || !hash_equals($effectivePromptHash, hash('sha256', $effectivePrompt))
    ) {
        throw new RuntimeException('Explanation effective prompt hash does not match the exact source and suffix bytes.');
    }

    $prompt['effective_sha256'] = $effectivePromptHash;
    $rubric = agentEvaluationExplanationArtifact(
        agentEvaluationRequireObject($document, 'rubric', 'explanation task'),
        $directory,
        'rubric.md',
        'explanation rubric',
    );
    $base = agentEvaluationRequireObject($document, 'base', 'explanation task');
    agentEvaluationRequireExactKeys(
        $base,
        ['fixture', 'revision', 'tree', 'fixture_sha256'],
        'explanation task base',
    );
    $expectedBase = [
        'fixture' => 'tracked-maintainer-source',
        'revision' => AGENT_EVALUATION_EXPLANATION_SOURCE_REVISION,
        'tree' => AGENT_EVALUATION_EXPLANATION_SOURCE_TREE,
        'fixture_sha256' => AGENT_EVALUATION_EXPLANATION_SOURCE_FIXTURE_SHA256,
    ];

    foreach ($expectedBase as $name => $value) {
        if (($base[$name] ?? null) !== $value) {
            throw new RuntimeException('Explanation task must bind the exact tracked maintainer source revision.');
        }
    }

    $workspacePolicy = agentEvaluationValidateExplanationWorkspacePolicy(
        agentEvaluationRequireObject($document, 'workspace_policy', 'explanation task'),
        $taskId,
    );
    $budgets = agentEvaluationValidateBudgets(
        agentEvaluationRequireObject($document, 'budgets', 'explanation task'),
        $taskId,
    );
    $expectedBudgets = [
        'model_tokens' => 1_000_000,
        'wall_seconds' => 1_200,
        'repair_turns' => 0,
        'command_output_bytes' => 4_194_304,
    ];

    if ($budgets !== $expectedBudgets) {
        throw new RuntimeException('Explanation task budgets must equal the fixed bounded protocol.');
    }

    $executionProfile = agentEvaluationNormalizeExplanationExecutionProfile(
        agentEvaluationRequireObject($document, 'execution_profile', 'explanation task'),
        'explanation task execution profile',
    );
    $expectedExecutionProfile = agentEvaluationExplanationLiveExecutionProfile();

    if ($executionProfile !== $expectedExecutionProfile) {
        throw new RuntimeException('Explanation task must bind its exact live execution profile.');
    }

    $checks = agentEvaluationRequireObject($document, 'checks', 'explanation task');
    agentEvaluationRequireExactKeys($checks, ['structural', 'semantic_review'], 'explanation task checks');

    if (
        ($checks['structural'] ?? null) !== 'artifact-bound-v1'
        || ($checks['semantic_review'] ?? null) !== 'human-v1'
    ) {
        throw new RuntimeException('Explanation checks must keep structural collection separate from human semantic review.');
    }

    if (agentEvaluationRequireBoolean($document, 'comparative_claims', 'explanation task')) {
        throw new RuntimeException('The explanation task cannot authorize comparative claims.');
    }

    return [
        'schema_version' => 3,
        'id' => AGENT_EVALUATION_EXPLANATION_TASK_ID,
        'revision' => $revision,
        'kind' => 'explanation',
        'comparative_claims' => false,
        'prompt' => $prompt,
        'rubric' => $rubric,
        'manifest_sha256' => agentEvaluationFileHash($directory . '/task.json', 'explanation task'),
        'base' => $expectedBase,
        'workspace_policy' => $workspacePolicy,
        'budgets' => $budgets,
        'execution_profile' => $expectedExecutionProfile,
        'checks' => ['structural' => 'artifact-bound-v1', 'semantic_review' => 'human-v1'],
        'directory' => $directory,
    ];
}

/**
 * @param array<string, mixed> $descriptor
 * @return array{path: string, sha256: string}
 */
function agentEvaluationExplanationArtifact(
    array $descriptor,
    string $directory,
    string $expectedPath,
    string $owner,
): array {
    agentEvaluationRequireExactKeys($descriptor, ['path', 'sha256'], $owner);
    $path = agentEvaluationRequireRelativePath(
        agentEvaluationRequireString($descriptor, 'path', $owner),
        $owner,
    );
    $hash = agentEvaluationRequireHash(
        agentEvaluationRequireString($descriptor, 'sha256', $owner),
        $owner,
    );

    if ($path !== $expectedPath) {
        throw new RuntimeException("{$owner} must use its fixed task-local path.");
    }

    agentEvaluationRequireFileHash(
        agentEvaluationContainedArtifactPath($directory, $path, $owner),
        $hash,
        $owner,
    );

    return ['path' => $path, 'sha256' => $hash];
}

/**
 * @param array<string, mixed> $policy
 * @return array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}
 */
function agentEvaluationValidateExplanationWorkspacePolicy(array $policy, string $taskId): array
{
    $owner = "task {$taskId} explanation workspace policy";
    agentEvaluationRequireExactKeys(
        $policy,
        [
            'allowed_existing_paths',
            'allowed_new_paths',
            'protected_paths',
            'max_changed_files',
            'max_added_lines',
            'max_deleted_lines',
        ],
        $owner,
    );
    $normalized = [
        'allowed_existing_paths' => agentEvaluationRequireStringList($policy, 'allowed_existing_paths', $owner),
        'allowed_new_paths' => agentEvaluationRequireStringList($policy, 'allowed_new_paths', $owner),
        'protected_paths' => agentEvaluationRequireStringList($policy, 'protected_paths', $owner),
        'max_changed_files' => agentEvaluationRequireNonNegativeInteger($policy, 'max_changed_files', $owner),
        'max_added_lines' => agentEvaluationRequireNonNegativeInteger($policy, 'max_added_lines', $owner),
        'max_deleted_lines' => agentEvaluationRequireNonNegativeInteger($policy, 'max_deleted_lines', $owner),
    ];
    $expected = [
        'allowed_existing_paths' => [],
        'allowed_new_paths' => [],
        'protected_paths' => [],
        'max_changed_files' => 0,
        'max_added_lines' => 0,
        'max_deleted_lines' => 0,
    ];

    if ($normalized !== $expected) {
        throw new RuntimeException('Explanation workspace policy must prohibit every candidate mutation.');
    }

    return $normalized;
}

/**
 * @return array{condition: string, runner: array{name: string, version: string}, model: array{provider: string, id: string, revision: ?string, settings: array<string, mixed>}, context: array{bundle_id: ?string, bundle_sha256: ?string}, tools: list<array{name: string, version: ?string, permissions: list<string>}>, transport_tools: array{kind: string, count: int, sha256: string}}
 */
function agentEvaluationExplanationLiveExecutionProfile(): array
{
    return [
        'condition' => 'repository-only',
        'runner' => ['name' => 'codex-exec', 'version' => '0.153.1'],
        'model' => [
            'provider' => 'openai',
            'id' => 'gpt-5.4-2026-03-05',
            'revision' => null,
            'settings' => ['reasoning_effort' => 'high'],
        ],
        'context' => ['bundle_id' => null, 'bundle_sha256' => null],
        'tools' => [[
            'name' => 'shell',
            'version' => null,
            'permissions' => ['workspace-read', 'process-execute'],
        ]],
        'transport_tools' => [
            'kind' => 'codex-responses-local-tools-v1',
            'count' => 4,
            'sha256' => '3392681cd5b82960557ffe2ce5b0ba1e223cc0f97a226432a7a43353475d6aed',
        ],
    ];
}

/**
 * @param array<string, mixed> $profile
 * @return array{condition: string, runner: array{name: string, version: string}, model: array{provider: string, id: string, revision: ?string, settings: array<string, mixed>}, context: array{bundle_id: ?string, bundle_sha256: ?string}, tools: list<array{name: string, version: ?string, permissions: list<string>}>, transport_tools: ?array{kind: string, count: int, sha256: string}}
 */
function agentEvaluationNormalizeExplanationExecutionProfile(array $profile, string $owner): array
{
    agentEvaluationRequireExactKeys($profile, ['condition', 'runner', 'model', 'context', 'tools', 'transport_tools'], $owner);
    $runner = agentEvaluationRequireObject($profile, 'runner', $owner);
    agentEvaluationRequireExactKeys($runner, ['name', 'version'], $owner . ' runner');
    $model = agentEvaluationRequireObject($profile, 'model', $owner);
    agentEvaluationValidateModel($model);
    $settings = agentEvaluationValueObject($model['settings'] ?? null, $owner . ' model settings');
    $context = agentEvaluationRequireObject($profile, 'context', $owner);
    agentEvaluationValidateContext($context);
    $toolValues = agentEvaluationRequireList($profile, 'tools', $owner);
    agentEvaluationValidateTools($toolValues);
    $tools = [];

    foreach ($toolValues as $index => $value) {
        $tool = agentEvaluationValueObject($value, "{$owner} tool {$index}");
        $tools[] = [
            'name' => agentEvaluationRequireNonEmptyString($tool, 'name', "{$owner} tool {$index}"),
            'version' => agentEvaluationRequireNullableString($tool, 'version', "{$owner} tool {$index}"),
            'permissions' => agentEvaluationRequireStringList($tool, 'permissions', "{$owner} tool {$index}"),
        ];
    }

    return [
        'condition' => agentEvaluationRequireNonEmptyString($profile, 'condition', $owner),
        'runner' => [
            'name' => agentEvaluationRequireNonEmptyString($runner, 'name', $owner . ' runner'),
            'version' => agentEvaluationRequireNonEmptyString($runner, 'version', $owner . ' runner'),
        ],
        'model' => [
            'provider' => agentEvaluationRequireNonEmptyString($model, 'provider', $owner . ' model'),
            'id' => agentEvaluationRequireNonEmptyString($model, 'id', $owner . ' model'),
            'revision' => agentEvaluationRequireNullableString($model, 'revision', $owner . ' model'),
            'settings' => $settings,
        ],
        'context' => [
            'bundle_id' => agentEvaluationRequireNullableString($context, 'bundle_id', $owner . ' context'),
            'bundle_sha256' => agentEvaluationRequireNullableString(
                $context,
                'bundle_sha256',
                $owner . ' context',
            ),
        ],
        'tools' => $tools,
        'transport_tools' => agentEvaluationNormalizeExplanationTransportTools(
            $profile['transport_tools'],
            $owner . ' transport tools',
        ),
    ];
}

/** @return ?array{kind: string, count: int, sha256: string} */
function agentEvaluationNormalizeExplanationTransportTools(mixed $value, string $owner): ?array
{
    if ($value === null) {
        return null;
    }

    $transport = agentEvaluationValueObject($value, $owner);
    agentEvaluationRequireExactKeys($transport, ['kind', 'count', 'sha256'], $owner);

    return [
        'kind' => agentEvaluationRequireNonEmptyString($transport, 'kind', $owner),
        'count' => agentEvaluationRequirePositiveInteger($transport, 'count', $owner),
        'sha256' => agentEvaluationRequireHash(
            agentEvaluationRequireString($transport, 'sha256', $owner),
            $owner,
        ),
    ];
}

/**
 * @return array{schema_version: int, id: string, revision: int, task_schema_version: int,
 *   conditions: list<string>, trials_per_task_condition: int,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   schedule: array{kind: string}, attempt_policy: string, holdout_policy: string,
 *   reporting: array{primary_metric: string, denominator: string, group_by: list<string>, unknown_metrics: string, human_review: string},
 *   sha256: string}
 */
function agentEvaluationComparisonProtocol(string $kit): array
{
    agentEvaluationRequireFileHash($kit . '/schema/comparison-protocol-v1.schema.json', AGENT_EVALUATION_COMPARISON_PROTOCOL_SCHEMA_SHA256, 'comparison protocol schema');
    agentEvaluationRequireFileHash($kit . '/comparison-v1.json', AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256, 'comparison protocol');
    return [...agentEvaluationValidateComparisonProtocolDocument(agentEvaluationJsonFile($kit . '/comparison-v1.json')),
        'sha256' => AGENT_EVALUATION_COMPARISON_PROTOCOL_SHA256];
}

/**
 * @param array<string, mixed> $document
 * @return array{schema_version: int, id: string, revision: int, task_schema_version: int,
 *   conditions: list<string>, trials_per_task_condition: int,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   schedule: array{kind: string}, attempt_policy: string, holdout_policy: string,
 *   reporting: array{primary_metric: string, denominator: string, group_by: list<string>, unknown_metrics: string, human_review: string}}
 */
function agentEvaluationValidateComparisonProtocolDocument(array $document): array
{
    $expected = [
        'schema_version' => 1, 'id' => 'bounded-comparison-v1', 'revision' => 1, 'task_schema_version' => 2,
        'conditions' => ['phpthis', 'plain-php'], 'trials_per_task_condition' => 10,
        'budgets' => ['model_tokens' => 40_000, 'wall_seconds' => 1_200, 'repair_turns' => 0, 'command_output_bytes' => 4_194_304],
        'schedule' => ['kind' => 'balanced-rotating-v1'],
        'attempt_policy' => 'retain-every-start-no-replacement-v1', 'holdout_policy' => 'external-post-freeze-v1',
        'reporting' => ['primary_metric' => 'correct_completion_rate', 'denominator' => 'planned-trials',
            'group_by' => ['task_id', 'condition'], 'unknown_metrics' => 'null-with-reason', 'human_review' => 'required-for-claims'],
    ];
    agentEvaluationRequireExactKeys($document, array_keys($expected), 'comparison protocol');
    foreach (['schema_version', 'id', 'revision', 'task_schema_version', 'conditions', 'trials_per_task_condition', 'attempt_policy', 'holdout_policy'] as $field) {
        if ($document[$field] !== $expected[$field]) {
            throw new RuntimeException('Comparison protocol fields do not match the fixed reviewed version.');
        }
    }
    foreach (['budgets', 'schedule', 'reporting'] as $field) {
        $members = agentEvaluationRequireObject($document, $field, 'comparison protocol');
        agentEvaluationRequireExactKeys($members, array_keys($expected[$field]), 'comparison protocol ' . $field);
        foreach ($expected[$field] as $key => $value) {
            if ($members[$key] !== $value) {
                throw new RuntimeException('Comparison protocol fields do not match the fixed reviewed version.');
            }
        }
    }
    return $expected;
}

/**
 * Describe exact candidate paths and modes; an explicitly supplied shared bundle mounts at docs/phpstan.
 * @return array{manifest: string, sha256: string, bytes: int,
 *   files: array<string, array{source_path: string, sha256: string, mode: string, bytes: int}>}
 */
function agentEvaluationComparisonFixture(string $directory, ?string $sharedReferenceDirectory = null): array
{
    $files = [];
    $identities = [];
    $directories = [];
    $bytes = 0;
    $entries = 0;
    $sources = ['' => $directory];
    if ($sharedReferenceDirectory !== null) {
        $sources['docs/phpstan'] = $sharedReferenceDirectory;
    }
    foreach ($sources as $prefix => $sourceDirectory) {
        $root = realpath($sourceDirectory);
        if (!is_string($root) || $root !== $sourceDirectory || !is_dir($root) || is_link($root)) {
            throw new RuntimeException('Comparison fixture must be one canonical real directory.');
        }
        if ($prefix !== '') {
            foreach ([...array_keys($identities), ...array_keys($directories)] as $identity) {
                if (agentEvaluationPathIsWithin($identity, $prefix)) {
                    throw new RuntimeException('Comparison local fixture must not occupy the shared reference destination.');
                }
            }
            foreach (['docs', 'docs/phpstan'] as $parent) {
                if (isset($directories[$parent]) && $directories[$parent] !== $parent) {
                    throw new RuntimeException('Comparison fixture directory identities collide.');
                }
                if (!isset($directories[$parent])) {
                    $directories[$parent] = $parent;
                    $entries++;
                }
            }
        }
        if ($entries > 8_192) {
            throw new RuntimeException('Comparison fixture contains an unsupported entry or exceeds its entry bound.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            $entries++;
            if (!$entry instanceof SplFileInfo || $entry->isLink() || $entries > 8_192) {
                throw new RuntimeException('Comparison fixture contains an unsupported entry or exceeds its entry bound.');
            }
            $source = $entry->getPathname();
            $sourceRelative = substr($source, strlen($root) + 1);
            if (agentEvaluationPathIsWithin($sourceRelative, '.git') || agentEvaluationPathIsWithin($sourceRelative, 'vendor')) {
                throw new RuntimeException('Comparison fixture path exceeds its bound or overlaps an isolated input.');
            }
            $relative = $prefix === '' ? $sourceRelative : $prefix . '/' . $sourceRelative;
            agentEvaluationRequireRelativePath($relative, 'comparison source fixture path');
            if (strlen($relative) > 263 || preg_match('/[\x00-\x20\x7F]/', $relative) === 1
                || agentEvaluationPathIsWithin($relative, '.git') || agentEvaluationPathIsWithin($relative, 'vendor')) {
                throw new RuntimeException('Comparison fixture path exceeds its bound or overlaps an isolated input.');
            }
            if ($entry->isDir()) {
                $identity = strtolower($relative);
                if (isset($directories[$identity]) && $directories[$identity] !== $relative) {
                    throw new RuntimeException('Comparison fixture directory identities collide.');
                }
                $directories[$identity] = $relative;
                continue;
            }
            if (!$entry->isFile()) {
                throw new RuntimeException('Comparison fixture contains a forbidden special file.');
            }
            $metadata = lstat($source);
            if (!is_array($metadata) || $metadata['nlink'] !== 1 || !in_array($metadata['mode'] & 07777, [0644, 0755], true)) {
                throw new RuntimeException('Comparison fixture file must have one regular reviewed identity and mode.');
            }
            if (str_ends_with($relative, '.fixture')) {
                $relative = substr($relative, 0, -8);
            }
            agentEvaluationRequireRelativePath($relative, 'comparison materialized fixture path');
            if (strlen($relative) > 255 || agentEvaluationPathIsWithin($relative, '.git') || agentEvaluationPathIsWithin($relative, 'vendor')) {
                throw new RuntimeException('Comparison fixture path exceeds its bound or overlaps an isolated input.');
            }
            $identity = strtolower($relative);
            if (isset($identities[$identity]) || count($files) >= 4_096) {
                throw new RuntimeException('Comparison fixture materialization collides or exceeds its file bound.');
            }
            agentEvaluationRequireBoundedFile($source, AGENT_EVALUATION_MAX_JSON_BYTES, 'comparison fixture file');
            $size = filesize($source);
            if (!is_int($size)) {
                throw new RuntimeException('Unable to size one comparison fixture file.');
            }
            $bytes += $size;
            if ($bytes > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
                throw new RuntimeException('Comparison fixture exceeds its aggregate byte bound.');
            }
            $identities[$identity] = true;
            $files[$relative] = ['source_path' => $source, 'sha256' => agentEvaluationFileHash($source, 'comparison fixture file'),
                'mode' => ($metadata['mode'] & 0111) !== 0 ? '100755' : '100644', 'bytes' => $size];
        }
    }

    if ($files === []) {
        throw new RuntimeException('Comparison fixture must contain regular files.');
    }
    ksort($files, SORT_STRING);
    $lines = [];
    foreach ($files as $relative => $file) {
        if (isset($directories[strtolower($relative)])) {
            throw new RuntimeException('Comparison fixture materialization has a file-directory collision.');
        }
        $parent = dirname($relative);
        while ($parent !== '.') {
            if (isset($identities[strtolower($parent)])) {
                throw new RuntimeException('Comparison fixture materialization has a file-directory collision.');
            }
            $parent = dirname($parent);
        }
        $lines[] = $file['mode'] . ' ' . $file['sha256'] . ' ' . $relative;
    }
    sort($lines, SORT_STRING);
    $manifest = implode("\n", $lines) . "\n";
    return ['manifest' => $manifest, 'sha256' => hash('sha256', $manifest), 'bytes' => $bytes, 'files' => $files];
}

/**
 * @param array<string, mixed> $descriptor
 * @return array{path: string, sha256: string}
 */
function agentEvaluationComparisonArtifact(array $descriptor, string $directory, string $expectedPath): array
{
    agentEvaluationRequireExactKeys($descriptor, ['path', 'sha256'], 'comparison artifact');
    $path = agentEvaluationRequireString($descriptor, 'path', 'comparison artifact');
    if ($path !== $expectedPath) {
        throw new RuntimeException('Comparison artifact path must match its fixed task location.');
    }
    $hash = agentEvaluationRequireHash(agentEvaluationRequireString($descriptor, 'sha256', 'comparison artifact'), 'comparison artifact');
    $source = agentEvaluationContainedArtifactPath($directory, $path, 'comparison artifact');
    agentEvaluationRequireBoundedFile($source, AGENT_EVALUATION_MAX_JSON_BYTES, 'comparison artifact');
    $size = filesize($source);
    if (!is_int($size) || $size < 1) {
        throw new RuntimeException('Comparison artifacts must contain bounded nonempty bytes.');
    }
    agentEvaluationRequireFileHash($source, $hash, 'comparison artifact');
    return ['path' => $path, 'sha256' => $hash];
}

/**
 * @param array<string, mixed> $policy
 * @param array<string, array{source_path: string, sha256: string, mode: string, bytes: int}> $files
 * @return array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}
 */
function agentEvaluationComparisonWorkspacePolicy(array $policy, array $files): array
{
    agentEvaluationRequireExactKeys($policy, ['allowed_existing_paths', 'allowed_new_paths', 'protected_paths', 'max_changed_files', 'max_added_lines', 'max_deleted_lines'], 'comparison workspace policy');
    $lists = [];
    foreach (['allowed_existing_paths', 'allowed_new_paths', 'protected_paths'] as $key) {
        $values = agentEvaluationRequireStringList($policy, $key, 'comparison workspace policy');
        if (count($values) > 128 || count(array_unique($values, SORT_STRING)) !== count($values) || ($key === 'protected_paths' && $values === [])) {
            throw new RuntimeException('Comparison workspace paths must be unique and bounded.');
        }
        foreach ($values as $path) {
            agentEvaluationRequireRelativePath($path, 'comparison workspace path');
            if (strlen($path) > 255 || preg_match('/[\x00-\x20\x7F]/', $path) === 1
                || ($key === 'allowed_existing_paths' && !isset($files[$path])) || ($key === 'allowed_new_paths' && isset($files[$path]))) {
                throw new RuntimeException('Comparison workspace policy must match its materialized fixture.');
            }
        }
        $lists[$key] = $values;
    }
    if ($lists['allowed_existing_paths'] === [] && $lists['allowed_new_paths'] === []) {
        throw new RuntimeException('Comparison workspace policy must permit a bounded change.');
    }
    foreach ($lists['allowed_existing_paths'] as $existing) {
        foreach ($lists['allowed_new_paths'] as $new) {
            if (agentEvaluationPathIsWithin($existing, $new) || agentEvaluationPathIsWithin($new, $existing)) {
                throw new RuntimeException('Comparison permitted paths must not overlap.');
            }
        }
    }
    foreach (array_merge($lists['allowed_existing_paths'], $lists['allowed_new_paths']) as $allowed) {
        foreach ($lists['protected_paths'] as $protected) {
            if (agentEvaluationPathIsWithin($allowed, $protected) || agentEvaluationPathIsWithin($protected, $allowed)) {
                throw new RuntimeException('Comparison permitted paths must not overlap protected paths.');
            }
        }
    }
    foreach (['.git', 'composer.json', 'composer.lock', 'vendor', 'evaluation/observe.php'] as $required) {
        $covered = false;
        foreach ($lists['protected_paths'] as $protected) {
            $covered = $covered || agentEvaluationPathIsWithin($required, $protected);
        }
        if (!$covered) {
            throw new RuntimeException('Comparison policy must protect the fixed gate, dependencies, and observation driver.');
        }
    }
    $changed = agentEvaluationRequirePositiveInteger($policy, 'max_changed_files', 'comparison workspace policy');
    $added = agentEvaluationRequireNonNegativeInteger($policy, 'max_added_lines', 'comparison workspace policy');
    $deleted = agentEvaluationRequireNonNegativeInteger($policy, 'max_deleted_lines', 'comparison workspace policy');
    if ($changed > 128 || $added > 4_096 || $deleted > 4_096) {
        throw new RuntimeException('Comparison workspace change limits exceed their fixed maxima.');
    }
    return ['allowed_existing_paths' => $lists['allowed_existing_paths'], 'allowed_new_paths' => $lists['allowed_new_paths'],
        'protected_paths' => $lists['protected_paths'], 'max_changed_files' => $changed, 'max_added_lines' => $added, 'max_deleted_lines' => $deleted];
}

/**
 * @param array<string, array{source_path: string, sha256: string, mode: string, bytes: int}> $files
 * @return array{scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int}
 */
function agentEvaluationComparisonSourceContext(string $path, array $files): array
{
    $document = agentEvaluationJsonFile($path);
    agentEvaluationRequireExactKeys($document, ['schema_version', 'files'], 'comparison source context');
    $entries = agentEvaluationRequireList($document, 'files', 'comparison source context');
    if (($document['schema_version'] ?? null) !== 1 || $entries === [] || count($entries) > 4_096) {
        throw new RuntimeException('Comparison source context must use the fixed bounded manifest version.');
    }
    $normalized = [];
    $seen = [];
    $bytes = 0;
    $words = 0;
    foreach ($entries as $entry) {
        $descriptor = agentEvaluationValueObject($entry, 'comparison context file');
        agentEvaluationRequireExactKeys($descriptor, ['path', 'sha256'], 'comparison context file');
        $relative = agentEvaluationRequireRelativePath(agentEvaluationRequireString($descriptor, 'path', 'comparison context file'), 'comparison context file');
        $hash = agentEvaluationRequireHash(agentEvaluationRequireString($descriptor, 'sha256', 'comparison context file'), 'comparison context file');
        if (!isset($files[$relative]) || isset($seen[$relative]) || $files[$relative]['sha256'] !== $hash) {
            throw new RuntimeException('Comparison source context must name unique exact materialized fixture files.');
        }
        $source = file_get_contents($files[$relative]['source_path']);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read one comparison source context file.');
        }
        $bytes += strlen($source);
        $trimmed = trim($source, " \t\r\n\f\v");
        $tokens = $trimmed === '' ? [] : preg_split('/[ \t\r\n\f\v]+/', $trimmed);
        if ($tokens === false) {
            throw new RuntimeException('Unable to measure comparison source context.');
        }
        $words += count($tokens);
        $seen[$relative] = true;
        $normalized[] = ['path' => $relative, 'sha256' => $hash];
    }
    $paths = array_keys($seen);
    $ordered = $paths;
    sort($ordered, SORT_STRING);
    if ($paths !== $ordered) {
        throw new RuntimeException('Comparison source context files must be byte-sorted.');
    }
    return ['scope' => 'source-only', 'files' => $normalized, 'bytes' => $bytes, 'words' => $words];
}

/**
 * @return list<array{schema_version: int, id: string, revision: int, kind: string,
 *   protocol: array{id: string, revision: int, sha256: string},
 *   prompt: array{path: string, sha256: string}, rubric: array{path: string, sha256: string},
 *   conditions: list<array{id: string, base: array{path: string, fixture_sha256: string, directory: string, reference_directory: string},
 *     context_manifest: array{path: string, sha256: string, scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int},
 *     workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}}>,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   checks: array{application_check: string, holdout: array{id: string, revision: int, sha256: string}},
 *   manifest_sha256: string, directory: string}>
 */
function agentEvaluationComparisonTasks(string $kit): array
{
    agentEvaluationValidateKit($kit);
    $tasks = [];
    foreach (AGENT_EVALUATION_TASK_REVISIONS as $id => $pin) {
        if ($pin['schema_version'] === 2) {
            $tasks[] = agentEvaluationComparisonTaskDocument($kit, $id);
        }
    }
    return $tasks;
}

/**
 * @return array{schema_version: int, id: string, revision: int, kind: string,
 *   protocol: array{id: string, revision: int, sha256: string},
 *   prompt: array{path: string, sha256: string}, rubric: array{path: string, sha256: string},
 *   conditions: list<array{id: string, base: array{path: string, fixture_sha256: string, directory: string, reference_directory: string},
 *     context_manifest: array{path: string, sha256: string, scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int},
 *     workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}}>,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   checks: array{application_check: string, holdout: array{id: string, revision: int, sha256: string}},
 *   manifest_sha256: string, directory: string}
 */
function agentEvaluationComparisonTask(string $kit, string $taskId): array
{
    foreach (agentEvaluationComparisonTasks($kit) as $task) {
        if ($task['id'] === $taskId) {
            return $task;
        }
    }
    throw new RuntimeException('Unknown bounded comparison task.');
}

/**
 * @return array{schema_version: int, id: string, revision: int, kind: string,
 *   protocol: array{id: string, revision: int, sha256: string},
 *   prompt: array{path: string, sha256: string}, rubric: array{path: string, sha256: string},
 *   conditions: list<array{id: string, base: array{path: string, fixture_sha256: string, directory: string, reference_directory: string},
 *     context_manifest: array{path: string, sha256: string, scope: string, files: list<array{path: string, sha256: string}>, bytes: int, words: int},
 *     workspace_policy: array{allowed_existing_paths: list<string>, allowed_new_paths: list<string>, protected_paths: list<string>, max_changed_files: int, max_added_lines: int, max_deleted_lines: int}}>,
 *   budgets: array{model_tokens: int, wall_seconds: int, repair_turns: int, command_output_bytes: int},
 *   checks: array{application_check: string, holdout: array{id: string, revision: int, sha256: string}},
 *   manifest_sha256: string, directory: string}
 */
function agentEvaluationComparisonTaskDocument(string $kit, string $taskId): array
{
    $pin = AGENT_EVALUATION_TASK_REVISIONS[$taskId] ?? null;
    $kitRoot = realpath($kit);
    if (!is_array($pin) || $pin['schema_version'] !== 2 || !is_string($kitRoot)) {
        throw new RuntimeException('Unknown bounded comparison task.');
    }
    $candidate = $kitRoot . '/tasks/' . $taskId;
    $directory = realpath($candidate);
    if (!is_string($directory) || $directory !== $candidate || !is_dir($directory) || is_link($directory)) {
        throw new RuntimeException('Comparison task must remain in its canonical inventory directory.');
    }
    $document = agentEvaluationJsonFile($directory . '/task.json');
    agentEvaluationRequireExactKeys($document, ['schema_version', 'id', 'revision', 'kind', 'protocol', 'prompt', 'rubric', 'conditions', 'budgets', 'checks'], 'comparison task');
    $revision = agentEvaluationRequirePositiveInteger($document, 'revision', 'comparison task');
    $kind = agentEvaluationRequireString($document, 'kind', 'comparison task');
    if (($document['schema_version'] ?? null) !== 2 || ($document['id'] ?? null) !== $taskId
        || $revision !== $pin['revision'] || $kind !== (str_starts_with($taskId, 'repair.') ? 'repair' : 'implementation')) {
        throw new RuntimeException('Comparison task identity must match its explicit pinned version and kind.');
    }
    $protocol = agentEvaluationComparisonProtocol($kitRoot);
    $binding = agentEvaluationRequireObject($document, 'protocol', 'comparison task');
    agentEvaluationRequireExactKeys($binding, ['id', 'revision', 'sha256'], 'comparison task protocol');
    foreach (['id', 'revision', 'sha256'] as $field) {
        if ($binding[$field] !== $protocol[$field]) {
            throw new RuntimeException('Comparison task must bind the exact reviewed protocol.');
        }
    }
    $budgets = agentEvaluationValidateBudgets(agentEvaluationRequireObject($document, 'budgets', 'comparison task'), $taskId);
    if ($budgets !== $protocol['budgets']) {
        throw new RuntimeException('Comparison task budgets must equal the fixed matched protocol.');
    }
    $prompt = agentEvaluationComparisonArtifact(agentEvaluationRequireObject($document, 'prompt', 'comparison task'), $directory, 'prompt.md');
    $rubric = agentEvaluationComparisonArtifact(agentEvaluationRequireObject($document, 'rubric', 'comparison task'), $directory, 'rubric.md');
    $conditionValues = agentEvaluationRequireList($document, 'conditions', 'comparison task');
    if (count($conditionValues) !== 2) {
        throw new RuntimeException('Comparison task must define exactly the two matched conditions.');
    }
    $conditions = [];
    foreach ($conditionValues as $index => $value) {
        $condition = agentEvaluationValueObject($value, 'comparison condition');
        agentEvaluationRequireExactKeys($condition, ['id', 'base', 'context_manifest', 'workspace_policy'], 'comparison condition');
        $id = agentEvaluationRequireString($condition, 'id', 'comparison condition');
        if ($id !== $protocol['conditions'][$index]) {
            throw new RuntimeException('Comparison conditions must retain the fixed protocol order.');
        }
        $base = agentEvaluationRequireObject($condition, 'base', 'comparison condition');
        agentEvaluationRequireExactKeys($base, ['path', 'fixture_sha256'], 'comparison base');
        $path = agentEvaluationRequireString($base, 'path', 'comparison base');
        $hash = agentEvaluationRequireHash(agentEvaluationRequireString($base, 'fixture_sha256', 'comparison base'), 'comparison base');
        if ($path !== 'fixtures/' . $id) {
            throw new RuntimeException('Comparison base must use its fixed condition fixture directory.');
        }
        $referenceDirectory = $kitRoot . '/references/phpstan';
        $fixture = agentEvaluationComparisonFixture($directory . '/' . $path, $referenceDirectory);
        if ($fixture['sha256'] !== $hash) {
            throw new RuntimeException('Comparison base hash must match the exact materialized fixture.');
        }
        foreach (['composer.json', 'evaluation/observe.php'] as $required) {
            if (!isset($fixture['files'][$required])) {
                throw new RuntimeException('Comparison fixture is missing its fixed gate or observation driver.');
            }
        }
        $context = agentEvaluationComparisonArtifact(agentEvaluationRequireObject($condition, 'context_manifest', 'comparison condition'), $directory, 'context/' . $id . '.json');
        $sourceContext = agentEvaluationComparisonSourceContext($directory . '/' . $context['path'], $fixture['files']);
        $conditions[] = ['id' => $id, 'base' => ['path' => $path, 'fixture_sha256' => $hash, 'directory' => $directory . '/' . $path, 'reference_directory' => $referenceDirectory],
            'context_manifest' => [...$context, ...$sourceContext],
            'workspace_policy' => agentEvaluationComparisonWorkspacePolicy(agentEvaluationRequireObject($condition, 'workspace_policy', 'comparison condition'), $fixture['files'])];
    }
    $checks = agentEvaluationRequireObject($document, 'checks', 'comparison task');
    agentEvaluationRequireExactKeys($checks, ['application_check', 'holdout'], 'comparison checks');
    $holdout = agentEvaluationRequireObject($checks, 'holdout', 'comparison checks');
    agentEvaluationRequireExactKeys($holdout, ['id', 'revision', 'sha256'], 'comparison holdout');
    $holdoutId = agentEvaluationRequireString($holdout, 'id', 'comparison holdout');
    $holdoutRevision = agentEvaluationRequirePositiveInteger($holdout, 'revision', 'comparison holdout');
    $holdoutHash = agentEvaluationRequireHash(agentEvaluationRequireString($holdout, 'sha256', 'comparison holdout'), 'comparison holdout');
    if (($checks['application_check'] ?? null) !== 'composer check' || $holdoutId !== $taskId . '.holdout' || $holdoutRevision !== 1) {
        throw new RuntimeException('Comparison checks must name the fixed gate and external holdout identity.');
    }
    return ['schema_version' => 2, 'id' => $taskId, 'revision' => $revision, 'kind' => $kind,
        'protocol' => ['id' => $protocol['id'], 'revision' => $protocol['revision'], 'sha256' => $protocol['sha256']],
        'prompt' => $prompt, 'rubric' => $rubric, 'conditions' => $conditions, 'budgets' => $budgets,
        'checks' => ['application_check' => 'composer check', 'holdout' => ['id' => $holdoutId, 'revision' => $holdoutRevision, 'sha256' => $holdoutHash]],
        'manifest_sha256' => agentEvaluationFileHash($directory . '/task.json', 'comparison task'), 'directory' => $directory];
}

/** @return list<array{slot: int, round: int, task_id: string, condition: string}> */
function agentEvaluationComparisonSchedule(string $kit): array
{
    $tasks = agentEvaluationComparisonTasks($kit);
    $schedule = [];
    for ($round = 0; $round < 10; $round++) {
        for ($position = 0; $position < 3; $position++) {
            $index = ($round + $position) % 3;
            $conditions = ($round + $index) % 2 === 0 ? ['phpthis', 'plain-php'] : ['plain-php', 'phpthis'];
            foreach ($conditions as $condition) {
                $schedule[] = ['slot' => count($schedule) + 1, 'round' => $round, 'task_id' => $tasks[$index]['id'], 'condition' => $condition];
            }
        }
    }
    return $schedule;
}
