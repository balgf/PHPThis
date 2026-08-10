<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_MAX_TREE_FILES = 20_000;
const AGENT_EVALUATION_CONTROLLER_MAX_TREE_DIRECTORIES = 4_000;
const AGENT_EVALUATION_CONTROLLER_MAX_DIFF_CELLS = 250_000;
const AGENT_EVALUATION_CONTROLLER_MAX_DIFF_LINES = 4_096;

/**
 * @param array<string, mixed> $task
 * @return array{
 *   run_root: string,
 *   candidate_root: string,
 *   baseline_root: string,
 *   dependencies_root: string,
 *   evidence_root: string,
 *   dependency_manifest_path: string,
 *   dependency_manifest_sha256: string,
 *   base_fixture_sha256: string,
 *   baseline_manifest: string,
 *   baseline_sha256: string
 * }
 */
function agentEvaluationControllerPrepareWorkspace(
    string $sourceSkeleton,
    string $preparedDependencies,
    string $runRoot,
    array $task,
): array {
    agentEvaluationControllerRequireFixedTask($task);
    $sourceRoot = agentEvaluationControllerExistingRoot($sourceSkeleton, 'source-skeleton fixture');
    $dependencySource = agentEvaluationControllerExistingRoot(
        $preparedDependencies,
        'prepared-dependencies source',
    );
    $target = agentEvaluationControllerFreshAbsoluteTarget($runRoot, 'controller run root');

    if (
        agentEvaluationControllerPathsOverlap($target, $sourceRoot)
        || agentEvaluationControllerPathsOverlap($target, $dependencySource)
    ) {
        throw new RuntimeException('Controller run root must be separate from source and dependency inputs.');
    }

    $sourceTree = agentEvaluationControllerDescribeTree($sourceRoot, 'source-skeleton fixture', true);
    $base = $task['base'] ?? null;
    $expectedFixtureHash = is_array($base) ? ($base['fixture_sha256'] ?? null) : null;

    if (!is_string($expectedFixtureHash) || !hash_equals($expectedFixtureHash, $sourceTree['sha256'])) {
        throw new RuntimeException('Prepared source-skeleton fixture digest does not match the selected task revision.');
    }

    $dependencyTree = agentEvaluationControllerDescribeTree(
        $dependencySource,
        'prepared-dependencies source',
        true,
    );

    if ($dependencyTree['files'] === []) {
        throw new RuntimeException('Prepared dependencies must contain at least one regular file.');
    }

    if (!mkdir($target, 0700) || !chmod($target, 0700)) {
        throw new RuntimeException('Unable to create the private controller run root.');
    }

    $candidateRoot = $target . '/candidate';
    $baselineRoot = $target . '/baseline';
    $dependenciesRoot = $target . '/dependencies';
    $evidenceRoot = $target . '/evidence';

    try {
        agentEvaluationControllerCopyTree($sourceRoot, $candidateRoot, 'candidate source copy', true);
        agentEvaluationControllerCopyTree($sourceRoot, $baselineRoot, 'baseline source copy', true);
        agentEvaluationControllerCopyTree(
            $dependencySource,
            $dependenciesRoot,
            'prepared-dependencies copy',
            true,
        );

        if (!mkdir($evidenceRoot, 0700) || !chmod($evidenceRoot, 0700)) {
            throw new RuntimeException('Unable to create the private retained-evidence root.');
        }

        agentEvaluationControllerMakeTreeReadOnly($baselineRoot, false);
        agentEvaluationControllerMakeTreeReadOnly($dependenciesRoot, true);
        $dependencyManifestPath = $evidenceRoot . '/prepared-dependencies.manifest';

        if (
            file_put_contents($dependencyManifestPath, $dependencyTree['manifest'], LOCK_EX) === false
            || !chmod($dependencyManifestPath, 0600)
        ) {
            throw new RuntimeException('Unable to retain the prepared-dependencies manifest.');
        }

        agentEvaluationValidateDependencyManifest($dependencyManifestPath);
        agentEvaluationControllerValidateReadOnlyDependencies(
            $dependenciesRoot,
            $dependencyTree['manifest'],
        );
        $baselineTree = agentEvaluationControllerDescribeTree($baselineRoot, 'prepared baseline', true);
        $candidateTree = agentEvaluationControllerDescribeTree($candidateRoot, 'prepared candidate', true);

        if (
            !hash_equals($sourceTree['sha256'], $baselineTree['sha256'])
            || !hash_equals($sourceTree['sha256'], $candidateTree['sha256'])
        ) {
            throw new RuntimeException('Prepared baseline and candidate must equal the pinned source fixture.');
        }

        return [
            'run_root' => $target,
            'candidate_root' => $candidateRoot,
            'baseline_root' => $baselineRoot,
            'dependencies_root' => $dependenciesRoot,
            'evidence_root' => $evidenceRoot,
            'dependency_manifest_path' => $dependencyManifestPath,
            'dependency_manifest_sha256' => hash('sha256', $dependencyTree['manifest']),
            'base_fixture_sha256' => $sourceTree['sha256'],
            'baseline_manifest' => $baselineTree['manifest'],
            'baseline_sha256' => $baselineTree['sha256'],
        ];
    } catch (Throwable $throwable) {
        try {
            agentEvaluationControllerRemoveTree($target);
        } catch (Throwable $cleanupThrowable) {
            throw new RuntimeException(
                'Controller preparation failed and partial-workspace cleanup also failed: primary='
                . $throwable::class
                . ' cleanup='
                . $cleanupThrowable::class,
                0,
                $throwable,
            );
        }

        throw $throwable;
    }
}

/**
 * @param array<string, mixed> $workspace
 * @param array<string, mixed> $task
 * @return array{
 *   candidate_manifest: string,
 *   candidate_sha256: string,
 *   patch: string,
 *   patch_sha256: string,
 *   changed_files: list<string>,
 *   added_lines: int,
 *   deleted_lines: int
 * }
 */
function agentEvaluationControllerFreezeWorkspace(array $workspace, array $task): array
{
    $workspace = agentEvaluationControllerValidateWorkspaceShape($workspace);
    agentEvaluationControllerRequireFixedTask($task);
    $baselineRoot = $workspace['baseline_root'];
    $candidateRoot = $workspace['candidate_root'];
    $dependenciesRoot = $workspace['dependencies_root'];
    $dependencyManifestPath = $workspace['dependency_manifest_path'];
    $baselineTree = agentEvaluationControllerDescribeTree($baselineRoot, 'frozen baseline', true);

    if (
        !hash_equals($workspace['baseline_sha256'], $baselineTree['sha256'])
        || !hash_equals($workspace['baseline_manifest'], $baselineTree['manifest'])
    ) {
        throw new RuntimeException('Prepared baseline mutated before candidate freeze.');
    }

    $dependencyManifest = file_get_contents($dependencyManifestPath);

    if (
        !is_string($dependencyManifest)
        || !hash_equals($workspace['dependency_manifest_sha256'], hash('sha256', $dependencyManifest))
    ) {
        throw new RuntimeException('Prepared-dependencies evidence mutated before candidate freeze.');
    }

    agentEvaluationValidateDependencyManifest($dependencyManifestPath);
    agentEvaluationControllerValidateReadOnlyDependencies($dependenciesRoot, $dependencyManifest);
    $candidateTree = agentEvaluationControllerDescribeTree($candidateRoot, 'candidate workspace', true);
    $policy = agentEvaluationControllerWorkspacePolicy($task);
    $change = agentEvaluationControllerValidateWorkspacePolicy(
        $baselineRoot,
        $candidateRoot,
        $baselineTree,
        $candidateTree,
        $policy,
    );
    $patch = agentEvaluationControllerPatch(
        $baselineRoot,
        $candidateRoot,
        $baselineTree['files'],
        $candidateTree['files'],
        $change['changed_files'],
    );
    $candidateManifest = agentEvaluationControllerFrozenTreeManifest($candidateTree);

    return [
        'candidate_manifest' => $candidateManifest,
        'candidate_sha256' => hash('sha256', $candidateManifest),
        'patch' => $patch,
        'patch_sha256' => hash('sha256', $patch),
        'changed_files' => $change['changed_files'],
        'added_lines' => $change['added_lines'],
        'deleted_lines' => $change['deleted_lines'],
    ];
}

/**
 * @param array<string, mixed> $workspace
 * @param array<string, mixed> $freeze
 * @return array{scoring_root: string, candidate_root: string, candidate_sha256: string}
 */
function agentEvaluationControllerCreateScoringWorkspace(
    array $workspace,
    string $scoringRoot,
    array $freeze,
): array {
    $workspace = agentEvaluationControllerValidateWorkspaceShape($workspace);
    agentEvaluationRequireExactKeys(
        $freeze,
        [
            'candidate_manifest',
            'candidate_sha256',
            'patch',
            'patch_sha256',
            'changed_files',
            'added_lines',
            'deleted_lines',
        ],
        'controller freeze record',
    );
    $expectedRoot = $workspace['run_root'] . '/scoring';

    if ($scoringRoot !== $expectedRoot) {
        throw new RuntimeException('Scoring workspace must use the fixed disposable scoring child.');
    }

    agentEvaluationControllerFreshAbsoluteTarget($scoringRoot, 'scoring workspace root');
    $current = agentEvaluationControllerDescribeTree(
        $workspace['candidate_root'],
        'post-freeze candidate source',
        true,
    );
    agentEvaluationControllerValidateWritableCandidateModes(
        $workspace['candidate_root'],
        $current['files'],
        $current['directories'],
    );
    $expectedHash = $freeze['candidate_sha256'] ?? null;
    $expectedManifest = $freeze['candidate_manifest'] ?? null;

    if (
        !is_string($expectedHash)
        || !is_string($expectedManifest)
        || !hash_equals($expectedHash, hash('sha256', agentEvaluationControllerFrozenTreeManifest($current)))
        || !hash_equals($expectedManifest, agentEvaluationControllerFrozenTreeManifest($current))
    ) {
        throw new RuntimeException('Candidate mutated after freeze and cannot enter scoring.');
    }

    if (!mkdir($scoringRoot, 0700) || !chmod($scoringRoot, 0700)) {
        throw new RuntimeException('Unable to create the private scoring workspace.');
    }

    $scoringCandidate = $scoringRoot . '/candidate';

    try {
        agentEvaluationControllerCopyTree(
            $workspace['candidate_root'],
            $scoringCandidate,
            'frozen scoring candidate copy',
            true,
        );
        $copy = agentEvaluationControllerDescribeTree($scoringCandidate, 'scoring candidate copy', true);
        $copyManifest = agentEvaluationControllerFrozenTreeManifest($copy);

        if (
            !hash_equals($expectedHash, hash('sha256', $copyManifest))
            || !hash_equals($expectedManifest, $copyManifest)
        ) {
            throw new RuntimeException('Scoring candidate copy does not equal the frozen candidate.');
        }

        agentEvaluationControllerMakeTreeReadOnly($scoringCandidate, true);
        agentEvaluationControllerValidateReadOnlyScoringCandidate(
            $scoringCandidate,
            $expectedManifest,
            $expectedHash,
        );

        return [
            'scoring_root' => $scoringRoot,
            'candidate_root' => $scoringCandidate,
            'candidate_sha256' => $expectedHash,
        ];
    } catch (Throwable $throwable) {
        agentEvaluationControllerRemoveTree($scoringRoot);
        throw $throwable;
    }
}

function agentEvaluationControllerValidateReadOnlyScoringCandidate(
    string $candidateRoot,
    string $expectedManifest,
    string $expectedHash,
): void {
    agentEvaluationRequireHash($expectedHash, 'frozen scoring candidate');

    if (
        strlen($expectedManifest) > AGENT_EVALUATION_MAX_ARTIFACT_BYTES
        || !hash_equals($expectedHash, hash('sha256', $expectedManifest))
    ) {
        throw new RuntimeException('Frozen scoring-candidate manifest does not match its recorded hash.');
    }

    $tree = agentEvaluationControllerDescribeTree(
        $candidateRoot,
        'read-only scoring candidate',
        true,
    );
    $actualManifest = agentEvaluationControllerFrozenTreeManifest($tree);

    if (
        !hash_equals($expectedHash, hash('sha256', $actualManifest))
        || !hash_equals($expectedManifest, $actualManifest)
    ) {
        throw new RuntimeException('Read-only scoring candidate does not equal the frozen candidate.');
    }

    $rootMetadata = lstat($candidateRoot);

    if (!is_array($rootMetadata) || ($rootMetadata['mode'] & 07777) !== 0555) {
        throw new RuntimeException('Scoring candidate root is not read-only.');
    }

    foreach ($tree['directories'] as $path => $mode) {
        if ($mode !== '0555') {
            throw new RuntimeException("Scoring candidate directory {$path} is not read-only.");
        }
    }

    foreach ($tree['files'] as $path => $file) {
        $metadata = lstat($candidateRoot . '/' . $path);
        $expectedMode = $file['mode'] === '100755' ? 0555 : 0444;

        if (!is_array($metadata) || ($metadata['mode'] & 07777) !== $expectedMode) {
            throw new RuntimeException("Scoring candidate file {$path} is not read-only.");
        }
    }
}

/**
 * @param array{
 *   manifest: string,
 *   sha256: string,
 *   files: array<string, array{mode: string, sha256: string, bytes: int}>,
 *   directories: array<string, string>,
 *   bytes: int
 * } $tree
 */
function agentEvaluationControllerFrozenTreeManifest(array $tree): string
{
    $lines = [];

    foreach ($tree['directories'] as $path => $mode) {
        $lines[] = "040000 directory {$path}";
    }

    foreach ($tree['files'] as $path => $file) {
        $lines[] = "{$file['mode']} {$file['sha256']} {$path}";
    }

    sort($lines, SORT_STRING);

    return implode("\n", $lines) . ($lines === [] ? '' : "\n");
}

/**
 * @param array{
 *   allowed_existing_paths: list<string>,
 *   allowed_new_paths: list<string>,
 *   protected_paths: list<string>,
 *   max_changed_files: int,
 *   max_added_lines: int,
 *   max_deleted_lines: int
 * } $policy
 * @param array{
 *   manifest: string,
 *   sha256: string,
 *   files: array<string, array{mode: string, sha256: string, bytes: int}>,
 *   directories: array<string, string>,
 *   bytes: int
 * } $baselineTree
 * @param array{
 *   manifest: string,
 *   sha256: string,
 *   files: array<string, array{mode: string, sha256: string, bytes: int}>,
 *   directories: array<string, string>,
 *   bytes: int
 * } $candidateTree
 * @return array{changed_files: list<string>, added_lines: int, deleted_lines: int}
 */
function agentEvaluationControllerValidateWorkspacePolicy(
    string $baselineRoot,
    string $candidateRoot,
    array $baselineTree,
    array $candidateTree,
    array $policy,
): array {
    $baselineFiles = $baselineTree['files'];
    $candidateFiles = $candidateTree['files'];
    agentEvaluationControllerValidateWritableCandidateModes(
        $candidateRoot,
        $candidateFiles,
        $candidateTree['directories'],
    );

    if (array_keys($baselineTree['directories']) !== array_keys($candidateTree['directories'])) {
        throw new RuntimeException('Candidate directory set or mode changed outside the fixed workspace policy.');
    }

    $paths = array_values(array_unique(array_merge(array_keys($baselineFiles), array_keys($candidateFiles))));
    sort($paths, SORT_STRING);
    $changed = [];
    $addedLines = 0;
    $deletedLines = 0;

    foreach ($paths as $path) {
        $before = $baselineFiles[$path] ?? null;
        $after = $candidateFiles[$path] ?? null;

        if ($before === $after) {
            continue;
        }

        foreach ($policy['protected_paths'] as $protectedPath) {
            if (agentEvaluationPathIsWithin($path, $protectedPath)) {
                throw new RuntimeException("Candidate changed protected path {$path}.");
            }
        }

        if ($before === null) {
            if (!in_array($path, $policy['allowed_new_paths'], true)) {
                throw new RuntimeException("Candidate created unapproved path {$path}.");
            }

            if (($after['mode'] ?? null) !== '100644') {
                throw new RuntimeException("Candidate new path {$path} must not be executable.");
            }
        } elseif (!in_array($path, $policy['allowed_existing_paths'], true)) {
            throw new RuntimeException("Candidate changed unapproved existing path {$path}.");
        }

        if ($before !== null && $after !== null && $before['mode'] !== $after['mode']) {
            throw new RuntimeException("Candidate changed the executable mode of {$path}.");
        }

        $difference = agentEvaluationControllerLineDifference(
            $before === null ? '' : agentEvaluationControllerTextFile($baselineRoot . '/' . $path, $path),
            $after === null ? '' : agentEvaluationControllerTextFile($candidateRoot . '/' . $path, $path),
        );
        $addedLines += $difference['added'];
        $deletedLines += $difference['deleted'];
        $changed[] = $path;
    }

    if (count($changed) > $policy['max_changed_files']) {
        throw new RuntimeException('Candidate exceeds the maximum changed-file count.');
    }

    if ($addedLines > $policy['max_added_lines']) {
        throw new RuntimeException('Candidate exceeds the maximum added-line count.');
    }

    if ($deletedLines > $policy['max_deleted_lines']) {
        throw new RuntimeException('Candidate exceeds the maximum deleted-line count.');
    }

    return ['changed_files' => $changed, 'added_lines' => $addedLines, 'deleted_lines' => $deletedLines];
}

/**
 * @param array<string, array{mode: string, sha256: string, bytes: int}> $files
 * @param array<string, string> $directories
 */
function agentEvaluationControllerValidateWritableCandidateModes(
    string $candidateRoot,
    array $files,
    array $directories,
): void {
    $rootMetadata = lstat($candidateRoot);

    if (!is_array($rootMetadata) || ($rootMetadata['mode'] & 07777) !== 0700) {
        throw new RuntimeException('Candidate workspace root changed its prepared private mode.');
    }

    foreach ($directories as $path => $mode) {
        if ($mode !== '0755') {
            throw new RuntimeException("Candidate directory {$path} changed its prepared mode.");
        }
    }

    foreach ($files as $path => $file) {
        $metadata = lstat($candidateRoot . '/' . $path);
        $expectedMode = $file['mode'] === '100755' ? 0755 : 0644;

        if (!is_array($metadata) || ($metadata['mode'] & 07777) !== $expectedMode) {
            throw new RuntimeException("Candidate file {$path} changed its prepared mode.");
        }
    }
}

function agentEvaluationControllerValidateRetainedArtifact(
    string $evidenceRoot,
    string $relativePath,
    int $maximumBytes,
): string {
    $root = agentEvaluationControllerExistingRoot($evidenceRoot, 'retained-evidence root');
    $relative = agentEvaluationRequireRelativePath($relativePath, 'retained artifact path');

    if (str_contains($relative, '/')) {
        throw new RuntimeException('Retained artifacts must use the fixed flat evidence directory.');
    }

    if ($maximumBytes < 1 || $maximumBytes > AGENT_EVALUATION_MAX_ARTIFACT_BYTES) {
        throw new RuntimeException('Retained artifact byte bound is outside the reviewed maximum.');
    }

    $path = agentEvaluationContainedArtifactPath($root, $relative, 'retained artifact');
    agentEvaluationRequireBoundedFile($path, $maximumBytes, 'retained artifact');
    $metadata = lstat($path);

    if (!is_array($metadata) || $metadata['nlink'] !== 1) {
        throw new RuntimeException('Retained artifact must have one filesystem identity.');
    }

    if (($metadata['mode'] & 07777) !== 0600) {
        throw new RuntimeException('Retained artifact must use private mode 0600.');
    }

    return $path;
}

/** @param array<string, mixed> $workspace */
function agentEvaluationControllerValidateCleanupTarget(array $workspace, string $target): string
{
    $workspace = agentEvaluationControllerValidateWorkspaceShape($workspace);
    $allowed = [
        $workspace['candidate_root'],
        $workspace['baseline_root'],
        $workspace['dependencies_root'],
        $workspace['run_root'] . '/scoring',
    ];

    if (!in_array($target, $allowed, true) || $target === $workspace['evidence_root']) {
        throw new RuntimeException('Cleanup target is outside the fixed disposable workspace set.');
    }

    $path = realpath($target);

    if (!is_string($path) || $path !== $target || is_link($target) || !is_dir($target)) {
        throw new RuntimeException('Cleanup target must be one real fixed disposable directory.');
    }

    return $path;
}

/**
 * @param array<string, mixed> $workspace
 * @return list<string>
 */
function agentEvaluationControllerCleanupWorkspace(array $workspace): array
{
    $workspace = agentEvaluationControllerValidateWorkspaceShape($workspace);
    $removed = [];

    foreach (
        [
            $workspace['candidate_root'],
            $workspace['baseline_root'],
            $workspace['dependencies_root'],
            $workspace['run_root'] . '/scoring',
        ] as $target
    ) {
        if (!file_exists($target) && !is_link($target)) {
            continue;
        }

        $validated = agentEvaluationControllerValidateCleanupTarget($workspace, $target);
        agentEvaluationControllerRemoveTree($validated);
        $removed[] = $validated;
    }

    agentEvaluationControllerExistingRoot($workspace['evidence_root'], 'retained-evidence root');
    $children = scandir($workspace['run_root']);

    if ($children === false || array_values(array_diff($children, ['.', '..', 'evidence'])) !== []) {
        throw new RuntimeException('Controller cleanup left an unexpected run-root entry.');
    }

    return $removed;
}

/**
 * @return array{
 *   manifest: string,
 *   sha256: string,
 *   files: array<string, array{mode: string, sha256: string, bytes: int}>,
 *   directories: array<string, string>,
 *   bytes: int
 * }
 */
function agentEvaluationControllerDescribeTree(string $directory, string $owner, bool $allowExecutables): array
{
    $root = agentEvaluationControllerExistingRoot($directory, $owner);
    $files = [];
    $directories = [];
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            throw new RuntimeException("{$owner} contains an unreadable filesystem entry.");
        }

        $path = $entry->getPathname();
        $relative = substr($path, strlen($root) + 1);
        agentEvaluationControllerValidateTreePath($relative, $owner);

        if ($entry->isLink()) {
            throw new RuntimeException("{$owner} contains forbidden symlink {$relative}.");
        }

        $metadata = lstat($path);

        if (!is_array($metadata)) {
            throw new RuntimeException("{$owner} entry metadata is unavailable for {$relative}.");
        }

        $mode = $metadata['mode'] & 07777;

        if ($entry->isDir()) {
            if (!in_array($mode, [0500, 0555, 0700, 0755], true)) {
                throw new RuntimeException("{$owner} directory {$relative} has an unsafe mode.");
            }

            $directories[$relative] = sprintf('%04o', $mode);

            if (count($directories) > AGENT_EVALUATION_CONTROLLER_MAX_TREE_DIRECTORIES) {
                throw new RuntimeException("{$owner} exceeds the fixed directory-count bound.");
            }

            continue;
        }

        if (!$entry->isFile()) {
            throw new RuntimeException("{$owner} contains forbidden special file {$relative}.");
        }

        if ($metadata['nlink'] !== 1) {
            throw new RuntimeException("{$owner} contains forbidden hard-linked file {$relative}.");
        }

        if (count($files) >= AGENT_EVALUATION_CONTROLLER_MAX_TREE_FILES) {
            throw new RuntimeException("{$owner} exceeds the fixed file-count bound.");
        }

        if (!in_array($mode, [0444, 0555, 0600, 0644, 0700, 0755], true)) {
            throw new RuntimeException("{$owner} file {$relative} has an unsafe mode.");
        }

        $executable = ($mode & 0111) !== 0;

        if ($executable && !$allowExecutables) {
            throw new RuntimeException("{$owner} contains unexpected executable file {$relative}.");
        }

        $size = filesize($path);
        $hash = hash_file('sha256', $path);

        if (!is_int($size) || !is_string($hash)) {
            throw new RuntimeException("Unable to describe {$owner} file {$relative}.");
        }

        $bytes += $size;

        if ($bytes > AGENT_EVALUATION_CONTROLLER_DISK_BYTES) {
            throw new RuntimeException("{$owner} exceeds the fixed disk-byte bound.");
        }

        $files[$relative] = [
            'mode' => $executable ? '100755' : '100644',
            'sha256' => $hash,
            'bytes' => $size,
        ];
    }

    ksort($files, SORT_STRING);
    ksort($directories, SORT_STRING);
    $lines = [];

    foreach ($files as $path => $file) {
        $lines[] = "{$file['mode']} {$file['sha256']} {$path}";
    }

    sort($lines, SORT_STRING);
    $manifest = implode("\n", $lines) . ($lines === [] ? '' : "\n");

    return [
        'manifest' => $manifest,
        'sha256' => hash('sha256', $manifest),
        'files' => $files,
        'directories' => $directories,
        'bytes' => $bytes,
    ];
}

function agentEvaluationControllerValidateReadOnlyDependencies(string $directory, string $manifest): void
{
    $root = agentEvaluationControllerExistingRoot($directory, 'prepared-dependencies root');
    $expected = agentEvaluationControllerManifestFiles($manifest);
    $tree = agentEvaluationControllerDescribeTree($root, 'prepared-dependencies root', true);

    if (array_keys($tree['files']) !== array_keys($expected)) {
        throw new RuntimeException('Prepared-dependencies paths changed after preparation.');
    }

    foreach ($expected as $path => $file) {
        $actual = $tree['files'][$path] ?? null;

        if (!is_array($actual) || !hash_equals($file['sha256'], $actual['sha256'])) {
            throw new RuntimeException("Prepared dependency {$path} changed after preparation.");
        }

        $metadata = lstat($root . '/' . $path);
        $expectedMode = $file['mode'] === '100755' ? 0555 : 0444;

        if (!is_array($metadata) || ($metadata['mode'] & 07777) !== $expectedMode) {
            throw new RuntimeException("Prepared dependency {$path} is not read-only.");
        }
    }

    foreach ($tree['directories'] as $path => $mode) {
        if ($mode !== '0555') {
            throw new RuntimeException("Prepared-dependencies directory {$path} is not read-only.");
        }
    }

    $rootMetadata = lstat($root);

    if (!is_array($rootMetadata) || ($rootMetadata['mode'] & 07777) !== 0555) {
        throw new RuntimeException('Prepared-dependencies root is not read-only.');
    }
}

/**
 * @return array<string, array{mode: string, sha256: string}>
 */
function agentEvaluationControllerManifestFiles(string $manifest): array
{
    if ($manifest === '' || !str_ends_with($manifest, "\n")) {
        throw new RuntimeException('Prepared-dependencies manifest must be non-empty canonical text.');
    }

    $files = [];

    foreach (explode("\n", substr($manifest, 0, -1)) as $line) {
        if (preg_match('/\A(100644|100755) ([a-f0-9]{64}) (.+)\z/D', $line, $matches) !== 1) {
            throw new RuntimeException('Prepared-dependencies manifest has an invalid line.');
        }

        $path = agentEvaluationRequireRelativePath($matches[3], 'prepared-dependencies manifest path');
        $files[$path] = ['mode' => $matches[1], 'sha256' => $matches[2]];
    }

    ksort($files, SORT_STRING);

    return $files;
}

function agentEvaluationControllerCopyTree(
    string $source,
    string $destination,
    string $owner,
    bool $allowExecutables,
): void {
    $sourceRoot = agentEvaluationControllerExistingRoot($source, "{$owner} source");
    agentEvaluationControllerFreshAbsoluteTarget($destination, "{$owner} destination");
    agentEvaluationControllerDescribeTree($sourceRoot, "{$owner} source", $allowExecutables);

    if (!mkdir($destination, 0700) || !chmod($destination, 0700)) {
        throw new RuntimeException("Unable to create {$owner} destination.");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || $entry->isLink()) {
            throw new RuntimeException("{$owner} encountered an invalid source entry.");
        }

        $relative = substr($entry->getPathname(), strlen($sourceRoot) + 1);
        agentEvaluationControllerValidateTreePath($relative, $owner);
        $target = $destination . '/' . $relative;

        if ($entry->isDir()) {
            if (!mkdir($target, 0755) || !chmod($target, 0755)) {
                throw new RuntimeException("Unable to create {$owner} directory {$relative}.");
            }

            continue;
        }

        if (!$entry->isFile() || !copy($entry->getPathname(), $target)) {
            throw new RuntimeException("Unable to copy {$owner} file {$relative}.");
        }

        $mode = $entry->isExecutable() ? 0755 : 0644;

        if (!chmod($target, $mode)) {
            throw new RuntimeException("Unable to set {$owner} file mode for {$relative}.");
        }
    }
}

function agentEvaluationControllerMakeTreeReadOnly(string $directory, bool $retainExecutables): void
{
    $root = agentEvaluationControllerExistingRoot($directory, 'read-only tree');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || $entry->isLink()) {
            throw new RuntimeException('Read-only tree contains an invalid entry.');
        }

        if ($entry->isDir()) {
            if (!chmod($entry->getPathname(), 0555)) {
                throw new RuntimeException('Unable to make one prepared directory read-only.');
            }

            continue;
        }

        if (!$entry->isFile()) {
            throw new RuntimeException('Read-only tree contains a special file.');
        }

        $mode = $retainExecutables && $entry->isExecutable() ? 0555 : 0444;

        if (!chmod($entry->getPathname(), $mode)) {
            throw new RuntimeException('Unable to make one prepared file read-only.');
        }
    }

    if (!chmod($root, 0555)) {
        throw new RuntimeException('Unable to make the prepared root read-only.');
    }
}

/**
 * @param array<string, array{mode: string, sha256: string, bytes: int}> $baselineFiles
 * @param array<string, array{mode: string, sha256: string, bytes: int}> $candidateFiles
 * @param list<string> $changedFiles
 */
function agentEvaluationControllerPatch(
    string $baselineRoot,
    string $candidateRoot,
    array $baselineFiles,
    array $candidateFiles,
    array $changedFiles,
): string {
    $patch = "PHPTHIS-CANDIDATE-PATCH-V1\n";

    foreach ($changedFiles as $path) {
        $before = $baselineFiles[$path] ?? null;
        $after = $candidateFiles[$path] ?? null;
        $oldText = $before === null ? '' : agentEvaluationControllerTextFile($baselineRoot . '/' . $path, $path);
        $newText = $after === null ? '' : agentEvaluationControllerTextFile($candidateRoot . '/' . $path, $path);
        $operations = agentEvaluationControllerLineOperations($oldText, $newText);
        $patch .= "path {$path}\n";
        $patch .= 'old-mode ' . ($before['mode'] ?? 'absent') . "\n";
        $patch .= 'new-mode ' . ($after['mode'] ?? 'absent') . "\n";
        $patch .= 'old-sha256 ' . ($before['sha256'] ?? str_repeat('0', 64)) . "\n";
        $patch .= 'new-sha256 ' . ($after['sha256'] ?? str_repeat('0', 64)) . "\n";

        foreach ($operations as $operation) {
            $patch .= $operation['kind'] . $operation['line'] . "\n";
        }
    }

    return $patch;
}

/** @return array{added: int, deleted: int} */
function agentEvaluationControllerLineDifference(string $before, string $after): array
{
    $added = 0;
    $deleted = 0;

    foreach (agentEvaluationControllerLineOperations($before, $after) as $operation) {
        if ($operation['kind'] === '+') {
            $added++;
        } elseif ($operation['kind'] === '-') {
            $deleted++;
        }
    }

    return ['added' => $added, 'deleted' => $deleted];
}

/** @return list<array{kind: string, line: string}> */
function agentEvaluationControllerLineOperations(string $before, string $after): array
{
    $oldLines = $before === '' ? [] : explode("\n", substr($before, 0, -1));
    $newLines = $after === '' ? [] : explode("\n", substr($after, 0, -1));
    $oldCount = count($oldLines);
    $newCount = count($newLines);

    if (
        $oldCount > AGENT_EVALUATION_CONTROLLER_MAX_DIFF_LINES
        || $newCount > AGENT_EVALUATION_CONTROLLER_MAX_DIFF_LINES
        || ($oldCount + 1) > intdiv(AGENT_EVALUATION_CONTROLLER_MAX_DIFF_CELLS, $newCount + 1)
    ) {
        throw new RuntimeException('Candidate text difference exceeds the fixed comparison bound.');
    }

    $lengths = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

    for ($old = $oldCount - 1; $old >= 0; $old--) {
        for ($new = $newCount - 1; $new >= 0; $new--) {
            $lengths[$old][$new] = $oldLines[$old] === $newLines[$new]
                ? $lengths[$old + 1][$new + 1] + 1
                : max($lengths[$old + 1][$new], $lengths[$old][$new + 1]);
        }
    }

    $operations = [];
    $old = 0;
    $new = 0;

    while ($old < $oldCount || $new < $newCount) {
        if ($old < $oldCount && $new < $newCount && $oldLines[$old] === $newLines[$new]) {
            $operations[] = ['kind' => ' ', 'line' => $oldLines[$old]];
            $old++;
            $new++;
            continue;
        }

        if ($new < $newCount && ($old === $oldCount || $lengths[$old][$new + 1] >= $lengths[$old + 1][$new])) {
            $operations[] = ['kind' => '+', 'line' => $newLines[$new]];
            $new++;
            continue;
        }

        $operations[] = ['kind' => '-', 'line' => $oldLines[$old]];
        $old++;
    }

    return $operations;
}

function agentEvaluationControllerTextFile(string $path, string $relativePath): string
{
    agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_ARTIFACT_BYTES, "candidate text {$relativePath}");
    $source = file_get_contents($path);

    if (
        !is_string($source)
        || ($source !== '' && !str_ends_with($source, "\n"))
        || str_contains($source, "\r")
        || str_contains($source, "\0")
        || preg_match('//u', $source) !== 1
    ) {
        throw new RuntimeException("Candidate path {$relativePath} must remain canonical UTF-8 LF text.");
    }

    return $source;
}

function agentEvaluationControllerExistingRoot(string $directory, string $owner): string
{
    if ($directory === '' || !str_starts_with($directory, '/') || is_link($directory)) {
        throw new RuntimeException("{$owner} must be one absolute non-symlink directory.");
    }

    $root = realpath($directory);

    if (!is_string($root) || $root !== $directory || !is_dir($root)) {
        throw new RuntimeException("{$owner} must use its canonical absolute directory path.");
    }

    return $root;
}

function agentEvaluationControllerFreshAbsoluteTarget(string $target, string $owner): string
{
    if (
        $target === ''
        || !str_starts_with($target, '/')
        || str_contains($target, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $target) === 1
        || preg_match('~(?:\A|/)\.\.?(/|\z)~D', $target) === 1
        || preg_match('~//~', $target) === 1
        || file_exists($target)
        || is_link($target)
    ) {
        throw new RuntimeException("{$owner} must be one fresh normalized absolute path.");
    }

    $parent = realpath(dirname($target));

    if (!is_string($parent) || !is_dir($parent) || is_link(dirname($target))) {
        throw new RuntimeException("{$owner} parent must be one real existing directory.");
    }

    if ($parent . '/' . basename($target) !== $target) {
        throw new RuntimeException("{$owner} must use its canonical parent path.");
    }

    return $target;
}

function agentEvaluationControllerValidateTreePath(string $relativePath, string $owner): void
{
    agentEvaluationRequireRelativePath($relativePath, "{$owner} path");

    if (strlen($relativePath) > 4_096) {
        throw new RuntimeException("{$owner} contains an overlong path.");
    }

    $segments = explode('/', $relativePath);

    if (in_array('.git', $segments, true) || in_array('.gitmodules', $segments, true)) {
        throw new RuntimeException("{$owner} contains forbidden Git or submodule metadata.");
    }
}

function agentEvaluationControllerPathsOverlap(string $first, string $second): bool
{
    return $first === $second
        || str_starts_with($first, $second . '/')
        || str_starts_with($second, $first . '/');
}

/**
 * @param array<string, mixed> $workspace
 * @return array{
 *   run_root: string,
 *   candidate_root: string,
 *   baseline_root: string,
 *   dependencies_root: string,
 *   evidence_root: string,
 *   dependency_manifest_path: string,
 *   dependency_manifest_sha256: string,
 *   base_fixture_sha256: string,
 *   baseline_manifest: string,
 *   baseline_sha256: string
 * }
 */
function agentEvaluationControllerValidateWorkspaceShape(array $workspace): array
{
    agentEvaluationRequireExactKeys(
        $workspace,
        [
            'run_root',
            'candidate_root',
            'baseline_root',
            'dependencies_root',
            'evidence_root',
            'dependency_manifest_path',
            'dependency_manifest_sha256',
            'base_fixture_sha256',
            'baseline_manifest',
            'baseline_sha256',
        ],
        'controller workspace',
    );

    $runRoot = agentEvaluationRequireString($workspace, 'run_root', 'controller workspace');
    $candidateRoot = agentEvaluationRequireString($workspace, 'candidate_root', 'controller workspace');
    $baselineRoot = agentEvaluationRequireString($workspace, 'baseline_root', 'controller workspace');
    $dependenciesRoot = agentEvaluationRequireString($workspace, 'dependencies_root', 'controller workspace');
    $evidenceRoot = agentEvaluationRequireString($workspace, 'evidence_root', 'controller workspace');
    $dependencyManifestPath = agentEvaluationRequireString(
        $workspace,
        'dependency_manifest_path',
        'controller workspace',
    );
    $dependencyManifestHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($workspace, 'dependency_manifest_sha256', 'controller workspace'),
        'controller dependency manifest',
    );
    $baseFixtureHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($workspace, 'base_fixture_sha256', 'controller workspace'),
        'controller base fixture',
    );
    $baselineManifest = agentEvaluationRequireString($workspace, 'baseline_manifest', 'controller workspace');
    $baselineHash = agentEvaluationRequireHash(
        agentEvaluationRequireString($workspace, 'baseline_sha256', 'controller workspace'),
        'controller baseline',
    );

    if (
        $candidateRoot !== $runRoot . '/candidate'
        || $baselineRoot !== $runRoot . '/baseline'
        || $dependenciesRoot !== $runRoot . '/dependencies'
        || $evidenceRoot !== $runRoot . '/evidence'
        || $dependencyManifestPath !== $evidenceRoot . '/prepared-dependencies.manifest'
    ) {
        throw new RuntimeException('Controller workspace paths do not match the fixed child layout.');
    }

    return [
        'run_root' => $runRoot,
        'candidate_root' => $candidateRoot,
        'baseline_root' => $baselineRoot,
        'dependencies_root' => $dependenciesRoot,
        'evidence_root' => $evidenceRoot,
        'dependency_manifest_path' => $dependencyManifestPath,
        'dependency_manifest_sha256' => $dependencyManifestHash,
        'base_fixture_sha256' => $baseFixtureHash,
        'baseline_manifest' => $baselineManifest,
        'baseline_sha256' => $baselineHash,
    ];
}

function agentEvaluationControllerRemoveTree(string $directory): void
{
    if (is_link($directory)) {
        if (!unlink($directory)) {
            throw new RuntimeException('Unable to remove a disposable workspace symlink.');
        }

        return;
    }

    if (!is_dir($directory)) {
        throw new RuntimeException('Disposable cleanup target is not a directory.');
    }

    if (!chmod($directory, 0700)) {
        throw new RuntimeException('Unable to make the disposable cleanup root removable.');
    }

    $directories = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($directories as $entry) {
        if (
            !$entry instanceof SplFileInfo
            || $entry->isLink()
            || !$entry->isDir()
        ) {
            continue;
        }

        if (!chmod($entry->getPathname(), 0700)) {
            throw new RuntimeException('Unable to make one disposable directory removable.');
        }
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            throw new RuntimeException('Disposable cleanup encountered an unreadable entry.');
        }

        $path = $entry->getPathname();

        if ($entry->isLink()) {
            if (!unlink($path)) {
                throw new RuntimeException('Unable to remove a disposable workspace symlink.');
            }

            continue;
        }

        if ($entry->isDir()) {
            if (!chmod($path, 0700) || !rmdir($path)) {
                throw new RuntimeException('Unable to remove a disposable workspace directory.');
            }

            continue;
        }

        if (!unlink($path)) {
            throw new RuntimeException('Unable to remove a disposable workspace entry.');
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove the disposable workspace root.');
    }
}
