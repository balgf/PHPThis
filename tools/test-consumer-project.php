<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composerBinary = composerBinary($root);
$workspace = sys_get_temp_dir() . '/phpthis-consumer-proof-' . bin2hex(random_bytes(12));

if (!mkdir($workspace, 0700)) {
    throw new RuntimeException('Unable to create the isolated consumer-proof directory.');
}

try {
    $environment = processEnvironment([
        'COMPOSER_CACHE_DIR' => $workspace . '/composer-cache',
        'COMPOSER_DISABLE_NETWORK' => '1',
        'COMPOSER_ROOT_VERSION' => 'dev-main',
    ]);
    $archiveDirectory = $workspace . '/archive';

    if (!mkdir($archiveDirectory, 0700)) {
        throw new RuntimeException('Unable to create the package-archive directory.');
    }

    $archiveResult = runProcess(
        composerCommand($composerBinary, [
            'archive',
            '--format=tar',
            '--dir=' . $archiveDirectory,
            '--file=phpthis-framework',
        ]),
        $root,
        $environment,
    );
    requireSuccess($archiveResult, 'Framework archive creation failed.');

    $archivePath = $archiveDirectory . '/phpthis-framework.tar';

    if (!is_file($archivePath)) {
        throw new RuntimeException('Composer did not create the expected framework archive.');
    }

    $expectedArchiveFiles = expectedArchiveFiles($root);
    $archiveFiles = archiveFiles($archivePath);
    verifyExportPolicies($root, $workspace, $expectedArchiveFiles, $environment);
    verifySkeletonPublicationBoundary($root);

    if ($archiveFiles !== $expectedArchiveFiles) {
        throw new RuntimeException(inventoryDifference($expectedArchiveFiles, $archiveFiles));
    }

    proveInstalledGuidanceReferencesResolve(
        $root,
        $workspace,
        $archivePath,
        $composerBinary,
        $environment,
    );

    $project = $workspace . '/application';
    copyDirectory($root . '/skeleton', $project);
    configureIsolatedConsumer($root, $project, $archivePath);

    $installResult = runProcess(
        composerCommand($composerBinary, [
            'install',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ]),
        $project,
        $environment,
    );
    requireSuccess($installResult, 'Isolated consumer dependency installation failed.');

    $validateResult = runProcess(
        composerCommand($composerBinary, ['validate', '--strict', '--no-check-publish']),
        $project,
        $environment,
    );
    requireSuccess($validateResult, 'Isolated consumer Composer validation failed.');

    $installedFramework = $project . '/vendor/phpthis/framework';

    if (!is_dir($installedFramework) || is_link($installedFramework)) {
        throw new RuntimeException('The consumer must install a mirrored framework package, not a symlink.');
    }

    if (
        !is_executable($installedFramework . '/bin/phpthis')
        || !is_executable($project . '/vendor/bin/phpthis')
    ) {
        throw new RuntimeException('The installed PHPThis consumer command is not executable.');
    }

    $installedFiles = directoryFiles($installedFramework);

    if ($installedFiles !== $expectedArchiveFiles) {
        throw new RuntimeException('The installed framework inventory differs from the verified archive.');
    }

    $profileCommand = [$project . '/vendor/bin/phpthis', 'check'];
    proveInstalledReleaseGuidanceDistribution($installedFramework);
    proveInstalledReferenceClarityDistribution($installedFramework);
    proveInstalledDatabaseSetupGuidanceDistribution($project, $installedFramework);
    proveInstalledStartupProbeGuidanceDistribution($project, $installedFramework);
    proveInstalledSessionCleanupAndResponseFramingDistribution($project, $installedFramework);
    proveInstalledBoundedTaskRoutedContextGuidanceDistribution($project, $installedFramework);
    proveInstalledCrudAccessSurfaceGuidanceDistribution($project, $installedFramework);
    proveInstalledIdentifierRepresentationGuidanceDistribution($project, $installedFramework);
    proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution($project, $installedFramework);
    proveInstalledEngineSpecificMigrationInvariantGuidanceDistribution(
        $project,
        $installedFramework,
    );
    proveInstalledMigrationStructureGuidanceDistribution(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );
    $installedWorkbenchGuidanceProof = proveInstalledWorkbenchGuidanceDistribution(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );

    if ($installedWorkbenchGuidanceProof !== 'installed-workbench-guidance-proved') {
        throw new RuntimeException('The installed Workbench guidance proof did not return its success sentinel.');
    }
    proveInstalledUuidAndUlidRouting($project, $environment);
    proveDatabaseContextConnectionConsistency($project, $profileCommand, $environment);
    proveInstalledTypedConfiguration($project, $profileCommand, $environment);
    proveInstalledConfigurationEvidenceReference(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );
    $requestHandlerDecoratorProofPath = proveInstalledRequestHandlerDecorator($project, $environment);

    try {
        $profileResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($profileResult, 'The clean skeleton and request-handler decorator proof failed the installed profile check.');
        requireStdoutContains(
            $profileResult,
            'PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)',
        );
        requireStdoutNotContains($profileResult, 'ADVISORY');
        requireOutputContains($profileResult, 'PASS PHPThis application check');
        requireOutputNotContains($profileResult, $project . '/bootstrap.php');
    } finally {
        if (is_file($requestHandlerDecoratorProofPath) && !unlink($requestHandlerDecoratorProofPath)) {
            throw new RuntimeException('Unable to remove the installed request-handler decorator proof.');
        }
    }

    if (!is_file($project . '/vendor/.phpthis/phpstan/resultCache.php')) {
        throw new RuntimeException('The normal application check did not create its persistent PHPStan cache.');
    }

    $debugResult = runProcess(
        [$project . '/vendor/bin/phpthis', 'check', '--debug'],
        $project,
        $environment,
    );
    requireSuccess($debugResult, 'The explicit diagnostic profile check failed.');
    requireStdoutContains(
        $debugResult,
        'PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)',
    );
    requireStdoutNotContains($debugResult, 'ADVISORY');
    requireOutputContains($debugResult, $project . '/bootstrap.php');

    $completeResult = runProcess(
        composerCommand($composerBinary, ['check']),
        $project,
        $environment,
    );
    requireSuccess($completeResult, 'The clean skeleton failed its complete application check.');
    requireOutputContains($completeResult, 'PASS application behavior and front controller');

    proveDuplicationAdvisoryIsReportOnly(
        $project,
        $composerBinary,
        $profileCommand,
        $environment,
    );
    proveObservabilityContextIsRequired($project, $profileCommand, $environment);
    proveConfigurationContextIsRequired($project, $profileCommand, $environment);
    proveEveryApplicationDirectoryIsChecked($project, $profileCommand, $environment);
    proveValidExtensionlessExecutableIsChecked($project, $profileCommand, $environment);
    proveMagicMethodsAreRejected($project, $profileCommand, $environment);
    proveEvalMethodsAreAllowedAndLanguageConstructIsRejected($project, $profileCommand, $environment);
    proveDependencyDirectoryIsExcluded($project, $profileCommand, $environment);
    proveMixedCoercionIsRejected($project, $profileCommand, $environment);
    proveDirectPdoConstructionIsRejected($project, $profileCommand, $environment);
    proveNativeSessionAccessIsRejected($project, $profileCommand, $environment);
    proveEnvironmentAccessIsRejected($project, $profileCommand, $environment);
    proveDynamicSqlIsRejected($project, $profileCommand, $environment);
    proveConfigurationCannotReplaceProfile($project, $profileCommand, $environment);
    proveBaselinesAndInlineIgnoresAreRejected($project, $profileCommand, $environment);
    proveComposerGateCannotDrift($project, $composerBinary, $profileCommand, $environment);
    proveSymlinkedSourceIsRejected($workspace, $project, $profileCommand, $environment);

    $restoredResult = runProcess($profileCommand, $project, $environment);
    requireSuccess($restoredResult, 'The skeleton did not return to a valid state after negative controls.');

    fwrite(
        STDOUT,
        sprintf(
            "PASS isolated consumer: %d release files, clean install, complete check, and adversarial controls\n",
            count($archiveFiles),
        ),
    );
} finally {
    removeDirectory($workspace);
}

/**
 * @param array<string, list<string>> $artifactMarkers
 * @param non-empty-string $artifactLabel
 */
function requireInstalledArtifactMarkers(array $artifactMarkers, string $artifactLabel): void
{
    foreach ($artifactMarkers as $path => $markers) {
        if (!is_file($path)) {
            throw new RuntimeException("Required installed {$artifactLabel} artifact is not a regular file: {$path}.");
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed {$artifactLabel} artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("Installed {$artifactLabel} artifact {$path} is missing marker: {$marker}");
            }
        }
    }
}

/**
 * @param array<string, list<string>> $artifactMarkers
 * @param non-empty-string $artifactLabel
 */
function forbidInstalledArtifactMarkers(array $artifactMarkers, string $artifactLabel): void
{
    foreach ($artifactMarkers as $path => $markers) {
        if (!is_file($path)) {
            throw new RuntimeException("Required installed {$artifactLabel} artifact is not a regular file: {$path}.");
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed {$artifactLabel} artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (str_contains($contents, $marker)) {
                throw new RuntimeException("Installed {$artifactLabel} artifact {$path} contains forbidden marker: {$marker}");
            }
        }
    }
}

/**
 * @param array<string, string> $environment
 */
function proveInstalledGuidanceReferencesResolve(
    string $root,
    string $workspace,
    string $archivePath,
    string $composerBinary,
    array $environment,
): void {
    $project = $workspace . '/guidance-application';
    copyDirectory($root . '/skeleton', $project);
    configureIsolatedConsumer($root, $project, $archivePath);

    $composerPath = $project . '/composer.json';
    $composer = jsonFile($composerPath);
    $config = $composer['config'] ?? null;

    if (!is_array($config)) {
        throw new RuntimeException('The isolated guidance consumer must define Composer configuration.');
    }

    $config['vendor-dir'] = 'build/dependencies';
    $composer['config'] = $config;
    writeJson($composerPath, $composer);

    $installResult = runProcess(
        composerCommand($composerBinary, [
            'install',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ]),
        $project,
        $environment,
    );
    requireSuccess($installResult, 'Custom-vendor-directory guidance consumer installation failed.');

    $vendorDirectory = configuredComposerVendorDirectory($project);
    $expectedVendorDirectory = $project . '/build/dependencies';

    if ($vendorDirectory !== $expectedVendorDirectory) {
        throw new RuntimeException('The guidance proof did not resolve the configured custom Composer vendor directory.');
    }

    $installedFramework = $vendorDirectory . '/phpthis/framework';

    if (!is_dir($installedFramework) || is_link($installedFramework)) {
        throw new RuntimeException('The guidance proof must use the mirrored framework under the configured vendor directory.');
    }

    $requiredFrameworkGuideOwners = [
        'docs/file-transfers/README.md' => '.ai/file-transfers.md',
        'docs/request-policy.md' => '.ai/request-policy.md',
        'docs/jobs.md' => '.ai/jobs.md',
        'docs/cli.md' => '.ai/cli.md',
        'docs/migrations.md' => '.ai/migrations.md',
    ];
    $skeletonMarkdown = markdownFilesFromInventory($project, directoryFiles($root . '/skeleton'));
    $templateRoot = $installedFramework . '/templates/application';
    $templateMarkdown = markdownFilesFromInventory($templateRoot, directoryFiles($templateRoot));

    requireInstalledGuidanceReferences(
        'generated skeleton',
        $skeletonMarkdown,
        $project,
        $vendorDirectory,
        $requiredFrameworkGuideOwners,
    );
    requireInstalledGuidanceReferences(
        'installed application template',
        $templateMarkdown,
        $templateRoot,
        $vendorDirectory,
        $requiredFrameworkGuideOwners,
    );

    $missingTarget = $installedFramework . '/docs/request-policy.md';
    $parkedTarget = $missingTarget . '.installed-reference-control';

    if (!rename($missingTarget, $parkedTarget)) {
        throw new RuntimeException('Unable to prepare the missing installed-reference negative control.');
    }

    try {
        requireInstalledGuidanceReferenceFailure(
            'generated skeleton',
            $skeletonMarkdown,
            $project,
            $vendorDirectory,
            $requiredFrameworkGuideOwners,
            'does not resolve through the configured Composer vendor directory',
        );
    } finally {
        if (!rename($parkedTarget, $missingTarget)) {
            throw new RuntimeException('Unable to restore the missing installed-reference negative control.');
        }
    }

    $localTarget = $project . '/docs/jobs.md';
    $localControl = $project . '/application-local-installed-reference-control.md';
    writeFile($localTarget, "# Incorrect local framework guide target\n");
    writeFile($localControl, "Read `docs/jobs.md` before changing durable work.\n");

    try {
        requireInstalledGuidanceReferenceFailure(
            'generated skeleton',
            [...$skeletonMarkdown, $localControl],
            $project,
            $vendorDirectory,
            $requiredFrameworkGuideOwners,
            'uses application-local framework guide docs/jobs.md',
        );
    } finally {
        foreach ([$localControl, $localTarget] as $controlPath) {
            if (is_file($controlPath) && !unlink($controlPath)) {
                throw new RuntimeException("Unable to remove installed-reference control {$controlPath}.");
            }
        }
    }

    proveRoutedInstalledGuidanceOwnerFailure(
        'generated skeleton',
        $skeletonMarkdown,
        $project,
        $vendorDirectory,
        $requiredFrameworkGuideOwners,
        'docs/jobs.md',
        '.ai/jobs.md',
    );
    proveRoutedInstalledGuidanceOwnerFailure(
        'installed application template',
        $templateMarkdown,
        $templateRoot,
        $vendorDirectory,
        $requiredFrameworkGuideOwners,
        'docs/jobs.md',
        '.ai/jobs.md',
    );

    fwrite(
        STDOUT,
        "PASS installed guidance references: custom Composer vendor directory and negative controls\n",
    );
}

function configuredComposerVendorDirectory(string $project): string
{
    $composer = jsonFile($project . '/composer.json');
    $config = $composer['config'] ?? null;
    $configured = is_array($config) ? ($config['vendor-dir'] ?? 'vendor') : 'vendor';

    if (!is_string($configured) || $configured === '') {
        throw new RuntimeException('Composer config.vendor-dir must be a non-empty string.');
    }

    if (str_starts_with($configured, '/')) {
        return rtrim($configured, '/');
    }

    return $project . '/' . rtrim($configured, '/');
}

/**
 * @param list<string> $inventory
 * @return list<string>
 */
function markdownFilesFromInventory(string $root, array $inventory): array
{
    $markdownFiles = [];

    foreach ($inventory as $relativePath) {
        if (str_ends_with(strtolower($relativePath), '.md')) {
            $markdownFiles[] = $root . '/' . $relativePath;
        }
    }

    if ($markdownFiles === []) {
        throw new RuntimeException("No Markdown guidance files found under {$root}.");
    }

    return $markdownFiles;
}

/**
 * @param list<string> $markdownFiles
 * @param array<string, string> $requiredFrameworkGuideOwners
 */
function requireInstalledGuidanceReferences(
    string $surface,
    array $markdownFiles,
    string $surfaceRoot,
    string $vendorDirectory,
    array $requiredFrameworkGuideOwners,
): void {
    $installedReferenceCount = 0;
    /** @var array<string, true> $installedReferencesByPath */
    $installedReferencesByPath = [];

    foreach ($markdownFiles as $markdownFile) {
        $contents = file_get_contents($markdownFile);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read {$surface} guidance file {$markdownFile}.");
        }

        foreach (array_keys($requiredFrameworkGuideOwners) as $requiredFrameworkGuide) {
            $localPattern = '~(?<![A-Za-z0-9_./-])'
                . preg_quote($requiredFrameworkGuide, '~')
                . '(?![A-Za-z0-9_./-])~';

            if (preg_match($localPattern, $contents) === 1) {
                throw new RuntimeException(
                    "{$surface} uses application-local framework guide {$requiredFrameworkGuide}; "
                    . 'installed framework guidance must resolve through Composer config.vendor-dir.',
                );
            }
        }

        $installedReferences = installedDependencyReferences($contents, $markdownFile);

        foreach ($installedReferences as $installedReference) {
            $installedReferenceCount++;
            $installedReferencesByPath[$installedReference] = true;
            $dependencyPath = substr($installedReference, strlen('vendor/'));
            $resolvedPath = $dependencyPath === ''
                ? $vendorDirectory
                : $vendorDirectory . '/' . $dependencyPath;

            if (!file_exists($resolvedPath)) {
                throw new RuntimeException(
                    "{$surface} installed reference {$installedReference} does not resolve through "
                    . 'the configured Composer vendor directory.',
                );
            }
        }
    }

    if ($installedReferenceCount === 0) {
        throw new RuntimeException("{$surface} contains no installed dependency references.");
    }

    foreach ($requiredFrameworkGuideOwners as $requiredFrameworkGuide => $routedOwner) {
        $expectedReference = 'vendor/phpthis/framework/' . $requiredFrameworkGuide;

        if (!isset($installedReferencesByPath[$expectedReference])) {
            throw new RuntimeException(
                "{$surface} context is missing required installed framework guide {$requiredFrameworkGuide}.",
            );
        }

        $routedOwnerPath = $surfaceRoot . '/' . $routedOwner;
        $routedOwnerContents = file_get_contents($routedOwnerPath);

        if (!is_string($routedOwnerContents)) {
            throw new RuntimeException(
                "Unable to read {$surface} routed owner {$routedOwner} for {$requiredFrameworkGuide}.",
            );
        }

        if (!in_array(
            $expectedReference,
            installedDependencyReferences($routedOwnerContents, $routedOwnerPath),
            true,
        )) {
            throw new RuntimeException(
                "{$surface} routed owner {$routedOwner} is missing required installed framework guide "
                . "{$requiredFrameworkGuide}.",
            );
        }
    }
}

/** @return list<string> */
function installedDependencyReferences(string $contents, string $sourcePath): array
{
    $matches = [];
    $matchCount = preg_match_all(
        '~`(vendor/[A-Za-z0-9._/-]*)(?:\\s+[^`]*)?`~',
        $contents,
        $matches,
    );

    if ($matchCount === false) {
        throw new RuntimeException("Unable to parse installed dependency references in {$sourcePath}.");
    }

    return $matches[1];
}

/**
 * @param list<string> $markdownFiles
 * @param array<string, string> $requiredFrameworkGuideOwners
 */
function requireInstalledGuidanceReferenceFailure(
    string $surface,
    array $markdownFiles,
    string $surfaceRoot,
    string $vendorDirectory,
    array $requiredFrameworkGuideOwners,
    string $expectedDiagnostic,
): void {
    try {
        requireInstalledGuidanceReferences(
            $surface,
            $markdownFiles,
            $surfaceRoot,
            $vendorDirectory,
            $requiredFrameworkGuideOwners,
        );
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), $expectedDiagnostic)) {
            throw new RuntimeException(
                "Installed-reference control failed with an unexpected diagnostic: {$exception->getMessage()}",
            );
        }

        return;
    }

    throw new RuntimeException('Installed-reference negative control unexpectedly passed.');
}

/**
 * @param list<string> $markdownFiles
 * @param array<string, string> $requiredFrameworkGuideOwners
 */
function proveRoutedInstalledGuidanceOwnerFailure(
    string $surface,
    array $markdownFiles,
    string $surfaceRoot,
    string $vendorDirectory,
    array $requiredFrameworkGuideOwners,
    string $requiredFrameworkGuide,
    string $routedOwner,
): void {
    $routedOwnerPath = $surfaceRoot . '/' . $routedOwner;
    $originalContents = file_get_contents($routedOwnerPath);

    if (!is_string($originalContents)) {
        throw new RuntimeException("Unable to read {$surface} routed-owner negative control {$routedOwner}.");
    }

    $expectedReference = '`vendor/phpthis/framework/' . $requiredFrameworkGuide . '`';
    $replacementCount = 0;
    $controlContents = str_replace(
        $expectedReference,
        'the installed framework guide',
        $originalContents,
        $replacementCount,
    );

    if ($replacementCount !== 1) {
        throw new RuntimeException(
            "{$surface} routed-owner negative control expected one {$requiredFrameworkGuide} reference in {$routedOwner}.",
        );
    }

    writeFile($routedOwnerPath, $controlContents);

    try {
        requireInstalledGuidanceReferenceFailure(
            $surface,
            $markdownFiles,
            $surfaceRoot,
            $vendorDirectory,
            $requiredFrameworkGuideOwners,
            "routed owner {$routedOwner} is missing required installed framework guide {$requiredFrameworkGuide}",
        );
    } finally {
        writeFile($routedOwnerPath, $originalContents);
    }
}

function proveInstalledReferenceClarityDistribution(string $installedFramework): void
{
    /** @var array<string, array{title: non-empty-string, metadata: non-empty-string, targets: non-empty-list<non-empty-string>}> $decisionHeaders */
    $decisionHeaders = [
        'docs/decisions/005-bounded-query-tracing.md' => [
            'title' => 'ADR 005: Bounded query tracing',
            'metadata' => "Superseded in part by [ADR 008](008-explicit-request-boundary.md), which replaces only this decision's temporary Phase 0 core-source ceiling.",
            'targets' => ['008-explicit-request-boundary.md'],
        ],
        'docs/decisions/008-explicit-request-boundary.md' => [
            'title' => 'ADR 008: Explicit request boundary and exact error responses',
            'metadata' => 'Superseded in part by [ADR 023](023-application-owned-terminal-request-summaries.md), which replaces only the separate unknown-failure log, and [ADR 026](026-bounded-file-transfers.md), which resolves only the upload and response-streaming reconsideration item.',
            'targets' => [
                '023-application-owned-terminal-request-summaries.md',
                '026-bounded-file-transfers.md',
            ],
        ],
        'docs/decisions/012-pdo-transport-application-owned-dialects.md' => [
            'title' => 'ADR 012: PDO transport with application-owned SQL dialects',
            'metadata' => 'Superseded in part by [ADR 023](023-application-owned-terminal-request-summaries.md), which replaces only the option to share one request-wide query budget across terminal-summary database sources.',
            'targets' => ['023-application-owned-terminal-request-summaries.md'],
        ],
        'docs/decisions/013-optional-crud-reference-profile.md' => [
            'title' => 'ADR 013: Optional CRUD reference profile',
            'metadata' => "Superseded in part by [ADR 021](021-application-owned-typed-input-boundaries.md), which replaces only the earlier Create tree and handler-owned transaction description.\n\nCurrent executable-example placement is refined by [ADR 046](046-canonical-executable-example-boundaries.md), which moves the shared `UserId` invariant to the feature level without changing this optional profile.",
            'targets' => [
                '021-application-owned-typed-input-boundaries.md',
                '046-canonical-executable-example-boundaries.md',
            ],
        ],
        'docs/decisions/017-bounded-trailing-positive-integer-routes.md' => [
            'title' => 'ADR 017: Bounded trailing positive-integer routes',
            'metadata' => "Superseded in part by [ADR 019](019-bounded-multiple-typed-routes.md), which retains this decision's positive-integer and explicit-routing constraints while replacing its one-trailing-parameter limit, prefix index, and one-value metadata.",
            'targets' => ['019-bounded-multiple-typed-routes.md'],
        ],
        'docs/decisions/019-bounded-multiple-typed-routes.md' => [
            'title' => 'ADR 019: Bounded multiple typed routes',
            'metadata' => "Superseded in part by [ADR 032](032-explicit-uuid-and-ulid-route-types.md), which retains this decision's parameter count, state index, opaque-token, conflict, and immutable-delivery constraints while extending the fixed parameter-type set with canonical UUID and ULID values.",
            'targets' => ['032-explicit-uuid-and-ulid-route-types.md'],
        ],
        'docs/decisions/020-application-owned-request-policy.md' => [
            'title' => 'ADR 020: Application-owned request policy composition',
            'metadata' => 'Superseded in part by [ADR 023](023-application-owned-terminal-request-summaries.md), which replaces only the denial and unknown-failure logging wording with one application-owned terminal summary attempt.',
            'targets' => ['023-application-owned-terminal-request-summaries.md'],
        ],
        'docs/decisions/021-application-owned-typed-input-boundaries.md' => [
            'title' => 'ADR 021: Application-owned typed input boundaries',
            'metadata' => 'Superseded in part by [ADR 042](042-application-owned-input-failure-classification.md), which replaces only the blanket-`400` authoring default for application-owned structured request-body content.',
            'targets' => ['042-application-owned-input-failure-classification.md'],
        ],
    ];

    foreach ($decisionHeaders as $relativePath => $expected) {
        $path = $installedFramework . '/' . $relativePath;

        if (!is_file($path)) {
            throw new RuntimeException("Installed partially superseded decision is not a regular file: {$path}.");
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed partially superseded decision {$path}.");
        }

        $expectedPrefix = "# {$expected['title']}\n\nStatus: accepted\n\n{$expected['metadata']}\n\n## Context\n";

        if (!str_starts_with($contents, $expectedPrefix)) {
            throw new RuntimeException(
                "Installed partially superseded decision {$path} does not expose its exact successor metadata after accepted status.",
            );
        }

        foreach ($expected['targets'] as $targetPath) {
            $target = $installedFramework . '/docs/decisions/' . $targetPath;

            if (!is_file($target)) {
                throw new RuntimeException(
                    "Installed partially superseded decision {$path} has no regular-file relationship target {$target}.",
                );
            }
        }
    }

    $expectedIndexRows = [
        '| Accepted record | Scope superseded in part | Direct successor |',
        '| --- | --- | --- |',
        '| [ADR 005](005-bounded-query-tracing.md) | Temporary Phase 0 core-source ceiling | [ADR 008](008-explicit-request-boundary.md) |',
        '| [ADR 008](008-explicit-request-boundary.md) | Separate unknown-failure log | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 008](008-explicit-request-boundary.md) | Upload and response-streaming reconsideration item | [ADR 026](026-bounded-file-transfers.md) |',
        '| [ADR 012](012-pdo-transport-application-owned-dialects.md) | Shared request-wide query-budget option for terminal-summary database sources | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 013](013-optional-crud-reference-profile.md) | Earlier Create tree and handler-owned transaction description | [ADR 021](021-application-owned-typed-input-boundaries.md) |',
        '| [ADR 017](017-bounded-trailing-positive-integer-routes.md) | One-trailing-parameter limit, prefix index, and one-value route metadata | [ADR 019](019-bounded-multiple-typed-routes.md) |',
        '| [ADR 019](019-bounded-multiple-typed-routes.md) | Fixed parameter-type set before UUID and ULID | [ADR 032](032-explicit-uuid-and-ulid-route-types.md) |',
        '| [ADR 020](020-application-owned-request-policy.md) | Denial and unknown-failure logging wording | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 021](021-application-owned-typed-input-boundaries.md) | Blanket-`400` authoring default for structured request-body content | [ADR 042](042-application-owned-input-failure-classification.md) |',
    ];
    $indexPath = $installedFramework . '/docs/decisions/README.md';

    if (!is_file($indexPath)) {
        throw new RuntimeException('The installed decision successor index is not a regular file.');
    }

    $indexContents = file_get_contents($indexPath);

    if (!is_string($indexContents)) {
        throw new RuntimeException('Unable to read the installed decision successor index.');
    }

    $indexLines = preg_split('/\R/', $indexContents);

    if (!is_array($indexLines)) {
        throw new RuntimeException('Unable to parse the installed decision successor index.');
    }

    $heading = '## Current and successor relationships';
    $headingIndex = null;
    $headingCount = 0;

    foreach ($indexLines as $index => $line) {
        if ($line === $heading) {
            $headingIndex = $index;
            $headingCount++;
        }
    }

    if ($headingIndex === null || $headingCount !== 1) {
        throw new RuntimeException('The installed decision index must contain one successor relationship section.');
    }

    $expectedIntroduction = 'A partially superseded record remains accepted outside the exact scope named below. Follow the direct successor for that scope; use current operational guides for ordinary implementation rather than rewriting historical decision bodies.';

    if (($indexLines[$headingIndex + 2] ?? null) !== $expectedIntroduction) {
        throw new RuntimeException('The installed decision index changed its bounded successor explanation.');
    }

    $actualIndexRows = [];
    $tableEndIndex = null;

    for ($index = $headingIndex + 1, $count = count($indexLines); $index < $count; $index++) {
        if (!str_starts_with($indexLines[$index], '|')) {
            if ($actualIndexRows !== []) {
                $tableEndIndex = $index;
                break;
            }

            continue;
        }

        $actualIndexRows[] = $indexLines[$index];
    }

    if ($actualIndexRows !== $expectedIndexRows) {
        throw new RuntimeException('The installed decision successor relationship table changed.');
    }

    $expectedRefinement = "ADR 013's current executable-example identifier placement is additionally refined by [ADR 046](046-canonical-executable-example-boundaries.md); the canonical current tree remains in [Optional CRUD reference profile](../crud.md#reference-placement). This refinement does not additionally supersede ADR 013's optional structure decision.";

    if ($tableEndIndex === null || ($indexLines[$tableEndIndex + 1] ?? null) !== $expectedRefinement) {
        throw new RuntimeException('The installed decision index changed ADR 013\'s current-tree refinement pointer.');
    }

    $vocabularyPath = $installedFramework . '/docs/vocabulary.md';

    if (!is_file($vocabularyPath)) {
        throw new RuntimeException('The installed canonical vocabulary is not a regular file.');
    }

    $vocabularyContents = file_get_contents($vocabularyPath);

    if (!is_string($vocabularyContents)) {
        throw new RuntimeException('Unable to read the installed canonical vocabulary.');
    }

    $expectedVocabularyRows = [
        'typed operation seam' => '| typed operation seam | optional application-owned, narrowly typed interface, at most one in a request path, separating completed inbound transport or HTTP adaptation from one independently meaningful business or transaction responsibility while outbound response adaptation remains in the handler; omitted when behavior remains coherent in the handler | service layer, repository, command bus, use-case interface required for every handler |',
        'application-owned representation primitive' => '| application-owned representation primitive | optional narrowly named application value used through composition by distinct concrete domain identifiers that deliberately share one complete validation invariant and canonical scalar representation; operations continue to require concrete identifiers and generation stays separate | framework identifier, generic domain ID, base class, trait, generator, binding or persistence abstraction |',
        'UUID policy' => '| UUID policy | application-owned recorded separation of accepted canonical UUID versions from generation version and owner, metadata disclosure, ordering and clock behavior, failure, narrower domain rules, persistence, and evidence | route grammar, framework UUID generator, package selection, database default, persistence abstraction |',
    ];
    /** @var array<string, list<string>> $installedVocabularyRows */
    $installedVocabularyRows = [];
    $vocabularyLines = preg_split('/\R/', $vocabularyContents);

    if (!is_array($vocabularyLines)) {
        throw new RuntimeException('Unable to parse the installed canonical vocabulary.');
    }

    foreach ($vocabularyLines as $line) {
        if (!str_starts_with($line, '| ')) {
            continue;
        }

        $columns = explode(' | ', trim($line, '| '));

        if (count($columns) === 3 && array_key_exists($columns[0], $expectedVocabularyRows)) {
            $installedVocabularyRows[$columns[0]][] = $line;
        }
    }

    foreach ($expectedVocabularyRows as $term => $expectedRow) {
        if (($installedVocabularyRows[$term] ?? []) !== [$expectedRow]) {
            throw new RuntimeException("Installed canonical vocabulary term {$term} changed or is repeated.");
        }
    }

    $databasePath = $installedFramework . '/docs/database.md';

    if (!is_file($databasePath)) {
        throw new RuntimeException('The installed database certification guide is not a regular file.');
    }

    $databaseContents = file_get_contents($databasePath);

    if (!is_string($databaseContents)) {
        throw new RuntimeException('Unable to read the installed database certification guide.');
    }

    $databaseLines = preg_split('/\R/', $databaseContents);

    if (!is_array($databaseLines)) {
        throw new RuntimeException('Unable to parse the installed database certification guide.');
    }

    $databaseHeading = '### PDO transport certification matrix';
    $databaseHeadingIndex = null;
    $databaseHeadingCount = 0;

    foreach ($databaseLines as $index => $line) {
        if ($line === $databaseHeading) {
            $databaseHeadingIndex = $index;
            $databaseHeadingCount++;
        }
    }

    if ($databaseHeadingIndex === null || $databaseHeadingCount !== 1) {
        throw new RuntimeException('The installed database guide must contain one certification matrix.');
    }

    $expectedDatabaseRows = [
        '| PDO driver | CI provision | Required exact engine or server version |',
        '| --- | --- | --- |',
        '| `sqlite` | PHP 8.4 `pdo_sqlite` on the `ubuntu-24.04` runner | SQLite `3.45.1` |',
        '| `mysql` | Official `mysql:8.4` service | MySQL `8.4.11` |',
        '| `pgsql` | Official `postgres:17` service | PostgreSQL `17.10` |',
    ];
    $installedDatabaseRows = [];

    for ($index = $databaseHeadingIndex + 1, $count = count($databaseLines); $index < $count; $index++) {
        if (!str_starts_with($databaseLines[$index], '|')) {
            if ($installedDatabaseRows !== []) {
                break;
            }

            continue;
        }

        $installedDatabaseRows[] = $databaseLines[$index];
    }

    if ($installedDatabaseRows !== $expectedDatabaseRows) {
        throw new RuntimeException('The installed database certification matrix changed.');
    }

    $databaseLimitation = 'No unlisted patch, minor, major, distribution build, extension build, service topology, or managed offering inherits certification from a listed row.';

    if (substr_count($databaseContents, $databaseLimitation) !== 1) {
        throw new RuntimeException('The installed database certification matrix changed its unlisted-version limitation.');
    }

    $databaseMismatchEvidence = 'Its maintained SQLite negative control first supplies an impossible expected version, requires the exact bounded mismatch diagnostic and removal of the pre-DDL fixture, then proves clean recovery through the normal certification run.';

    if (substr_count($databaseContents, $databaseMismatchEvidence) !== 1) {
        throw new RuntimeException('The installed database certification matrix changed its mismatch evidence boundary.');
    }

    $requiredMarkers = [
        $installedFramework . '/docs/guardrails.md' => [
            'Current unreleased source removes the redundant public-prerelease `PathParameters::onePositiveInteger()` convenience factory and occupies 2,595 lines.',
            'Repeated documentation-marker checks use file-local helpers rather than duplicated loops.',
            'The decision-navigation and vocabulary guard uses one fixed reviewed map of partial-supersession relationships.',
            'The maintained SQLite negative control supplies an impossible version, requires the exact bounded failure and removal of its pre-DDL fixture, then proves clean recovery through the normal certification run.',
        ],
        $installedFramework . '/docs/getting-started.md' => [
            'Any consumer upgrading from Alpha 5 or an earlier PHPThis revision or package must replace each call with `PathParameters::fromValues([$name => $value], [])`; an unchanged old call fails because the method no longer exists.',
        ],
        $installedFramework . '/src/Routing/PathParameters.php' => [
            'public static function fromValues(',
        ],
    ];
    $forbiddenMarkers = [
        $installedFramework . '/src/Routing/PathParameters.php' => [
            'onePositiveInteger',
        ],
    ];

    requireInstalledArtifactMarkers($requiredMarkers, 'historical and reference clarity');
    forbidInstalledArtifactMarkers($forbiddenMarkers, 'routing compatibility');

    fwrite(STDOUT, "PASS installed historical and reference clarity distribution\n");
}

function proveInstalledReleaseGuidanceDistribution(string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/RELEASING.md' => [
            '## Immutable release history',
            'Historical release authority means the exact bytes reachable from the approved tag.',
            'A later `main` file at the same path may contain a clarification, but it is current documentation rather than evidence of the tagged release.',
            '## Reusable release state model',
            '**Latest recorded release:**',
            '**Unreleased `main`:**',
            '**Proposed next candidate:**',
            '**Approved candidate:**',
            'only an explicit accountable-human record may approve the exact version, framework and skeleton tags, framework candidate commit, planned release date, bounded scope, release notes, candidate-specific announcement text, and each authorized next operation.',
            'The skeleton candidate commit may remain explicitly `PENDING`',
            'Keep the planned release date distinct from the observed timestamp of every external publication operation.',
            'Authorization is enumerable, not implied by reaching a checklist step.',
            'candidate preparation; framework commit and push; framework tag creation and push; framework Packagist update; skeleton commit and push; skeleton tag creation and push; skeleton Packagist update; either GitHub prerelease; and the final announcement.',
            '## Version-neutral release gate',
            'Preparing a proposal, proving or publishing an approved candidate, and inspecting an older release are different tasks.',
            'candidate-specific announcement',
            'An unexplained collision stops the release and requires a new approved version.',
            'When resuming a recorded partial publication, require every existing tag and artifact to match its recorded commit and distribution evidence exactly',
            'Existing state never authorizes overwrite, tag movement, deletion and recreation, or artifact replacement.',
            'record the framework side as published but the overall release as partial and unproved',
            'preserve and record that exact partial-publication state',
            '### 2. Prove the framework candidate',
            'Do not push it before the local proof in Step 2 passes.',
            'After the complete local gate passes, confirm the authorization record permits pushing the exact framework candidate commit',
            'GitHub CI passes both the PHP 8.4 validity job and the SQLite/MySQL/PostgreSQL PDO transport job for that exact pushed candidate commit.',
            '### 3. Publish the framework prerelease',
            'push that exact tag to the approved remote',
            '### 4. Publish the skeleton prerelease',
            'push the exact skeleton candidate commit without modification',
            'Confirm skeleton CI passes for that exact pushed candidate commit',
            'push that exact tag to the approved remote without moving or reusing an existing tag',
            '### 5. Prove the public distribution path',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton phpthis-release-proof 'APPROVED_SKELETON_VERSION'",
            '### 6. Announce or stop',
            'publish both approved GitHub prereleases for the already-pushed proven tags',
            'Exact framework candidate commit:',
            'Exact-candidate approval record:',
            'Exact skeleton candidate commit:',
            'Planned release date:',
            'Observed external operation timestamps and results:',
            'Accountable-human authorization records by exact operation:',
            'Partial-publication state or NOT_APPLICABLE:',
        ],
        $installedFramework . '/README.md' => [
            'That work is unreleased:',
            'does not yet have an approved next-release identity',
            'separates this unreleased state from a proposed candidate, an explicitly approved candidate, and immutable release history',
        ],
        $installedFramework . '/SECURITY.md' => [
            'Any approved prerelease candidate may be announced only after its complete public-artifact gate in `RELEASING.md` passes.',
            'A partially published framework or skeleton remains unannounced until both packages and the clean public installation path are proved.',
            'This tracked policy does not record current publication state',
        ],
        $installedFramework . '/docs/getting-started.md' => [
            '## Prerelease boundary',
            'Current `main` may contain later unreleased work;',
            'nor an approved next candidate.',
            'Prerelease publication follows the complete version-neutral maintainer gate in `RELEASING.md`.',
            'A framework-only or skeleton-only publication is recorded as partial and is not announced as a complete release.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            'Assess or prepare a proposed PHPThis release',
            'Prove or publish an approved PHPThis candidate',
            'Inspect an installed or historical PHPThis release',
            'exact framework and skeleton candidate commits recorded at their respective freeze points',
            'planned release date separate from observed publication timestamps',
            'distinct exact-candidate approval and separately enumerable preparation, commit/push, tag creation/push, package, GitHub-prerelease, and announcement authorization',
            'clean-tree local proof before push and exact pushed-commit CI',
            'exact-version clean public installation evidence',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'A separate installed distribution proof checks the version-neutral release guidance',
            'ordered local-proof-before-push, exact-CI, tag-creation-and-push',
            'discovers every current `docs/releases/*.md` note and rejects unqualified positive or negative live-publication claims',
            'performs no network request, tag operation, package-host write, release creation, or announcement',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'release guidance');

    $releaseGuidance = file_get_contents($installedFramework . '/RELEASING.md');

    if (!is_string($releaseGuidance)) {
        throw new RuntimeException('Unable to read installed release guidance for ordered proof.');
    }

    $orderedReleaseMarkers = [
        '### 1. Freeze the release candidate',
        'Do not push it before the local proof in Step 2 passes.',
        '### 2. Prove the framework candidate',
        'composer validate --strict',
        'composer check',
        'After the complete local gate passes, confirm the authorization record permits pushing the exact framework candidate commit',
        'GitHub CI passes both the PHP 8.4 validity job and the SQLite/MySQL/PostgreSQL PDO transport job for that exact pushed candidate commit.',
        '### 3. Publish the framework prerelease',
        'Create the approved framework prerelease tag from the proven commit',
        'push that exact tag to the approved remote',
        'Submit or refresh `phpthis/framework` on Packagist',
        '### 4. Publish the skeleton prerelease',
        'Export the contents of `skeleton/` as the root of its dedicated repository',
        'Run `composer validate --strict` and `composer check` from the skeleton root.',
        'push the exact skeleton candidate commit without modification',
        'Confirm skeleton CI passes for that exact pushed candidate commit',
        'Tag the proven skeleton commit and push that exact tag to the approved remote',
        'Submit or refresh `phpthis/skeleton` on Packagist',
        '### 5. Prove the public distribution path',
        "composer create-project --stability=alpha --prefer-dist phpthis/skeleton phpthis-release-proof 'APPROVED_SKELETON_VERSION'",
        'composer check',
        '### 6. Announce or stop',
        'publish both approved GitHub prereleases for the already-pushed proven tags',
        'Publish only the approved candidate-specific announcement',
    ];
    $previousPosition = -1;

    foreach ($orderedReleaseMarkers as $marker) {
        $position = strpos($releaseGuidance, $marker, $previousPosition + 1);

        if ($position === false) {
            throw new RuntimeException("Installed release guidance is missing or misorders marker: {$marker}");
        }

        $previousPosition = $position;
    }

    fwrite(STDOUT, "PASS installed version-neutral release guidance distribution\n");
}

function proveInstalledDatabaseSetupGuidanceDistribution(string $project, string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
            'An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.',
        ],
        $project . '/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            'configuration only, connection to an existing server, or project-local server provisioning',
            'deferred migrations or an application-owned migration foundation',
            '> Please setup PostgreSQL as our main DB.',
            'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
        ],
        $project . '/.ai/README.md' => [
            '| Select or set up a database engine | `.ai/change-workflow.md` | prompt and current configuration/data facts before any external action |',
        ],
        $project . '/.ai/configuration.md' => [
            'Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption.',
            'one separately named factory, final readonly output type, and process identity for each adopted process profile',
        ],
        $project . '/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'Ask all unresolved choices in one concise message',
            'Do not perform external database I/O, provision or mutate a server',
        ],
        $installedFramework . '/docs/configuration.md' => [
            '## Scope database setup before implementation',
            '> Please setup PostgreSQL as our main DB.',
            'should I only add PostgreSQL configuration, connect this project to an existing PostgreSQL server, or provision a project-local PostgreSQL server?',
            'Configuration-only scope records infrastructure injection and connection evidence as deferred and does not create dead wiring.',
            'For PostgreSQL or another engine, first record the exact accepted initial baseline',
            'When migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests',
            'Provisioning and production evidence is required only for an explicitly selected scope.',
        ],
        $installedFramework . '/docs/evaluation.md' => [
            '## Database setup scope-gate evaluation',
            'A starter not-applicable marker does not answer that adoption question.',
            'no connection attempt or other external database I/O',
            'they do not prove that a particular model follows them or meets a duration target',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Select or set up a database engine |',
            'load and prove only the selected slice',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            "It also verifies that the local skeleton and installed framework distribute ADR 037's database setup guidance.",
            'This distribution proof does not establish that an AI asks the scope question, avoids external database I/O, or meets a duration target',
        ],
        $installedFramework . '/templates/application/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            '> Please setup PostgreSQL as our main DB.',
            'An explicit request such as “Provision a project-local PostgreSQL server, configure it, and do not add migrations” proceeds without this scope question.',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Select or set up a database engine | `.ai/change-workflow.md` | prompt and current configuration/data facts before any external action |',
        ],
        $installedFramework . '/templates/application/AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
            'An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.',
        ],
        $installedFramework . '/templates/application/.ai/configuration.md' => [
            'Record only adopted external input contracts.',
            'do not store task scope or task history here',
        ],
        $installedFramework . '/templates/application/.ai/data.md' => [
            '{{ELEVATED_PROFILE_1_HISTORY_OR_ADMIN_NAME_OR_NOT_APPLICABLE}}',
            '{{ELEVATED_PROFILE_1_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'database setup guidance');

    fwrite(STDOUT, "PASS installed database setup guidance distribution\n");
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledWorkbenchGuidanceDistribution(
    string $project,
    string $installedFramework,
    array $profileCommand,
    array $environment,
): string
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change the development Workbench | `.ai/workbench.md` | approved package, checked bootstrap, explicit workspace, and retained tests |',
        ],
        $project . '/.ai/workbench.md' => [
            '`NOT_APPLICABLE(WORKBENCH)`',
            'the dedicated development operating-system identity, inherited environment, independently loaded child CLI configuration',
            'the absence of a Workbench execution timeout or CPU, memory, resource, and operating-system termination isolation',
            'the existing adopted business producer transaction and the application-recorded finite one-delivery console command',
            'Production artifacts install with `--no-dev`',
        ],
        $installedFramework . '/docs/workbench.md' => [
            '# PHPThis Workbench',
            'returns exactly one concrete application-owned object',
            'Composer\\\\Config::disableProcessTimeout',
            'fresh `PHP_BINARY` child',
            'Workbench supplies no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
            'existing adopted business operation',
            'recorded finite tested one-delivery operational command',
            'Workbench supplies no `dispatch()`',
            'Workbench output is exploratory evidence, not application validity evidence.',
        ],
        $installedFramework . '/docs/decisions/041-optional-development-workbench.md' => [
            'Status: accepted',
            'optional separate `phpthis/workbench` development package',
            'The generated child program is not a security boundary.',
            'Workbench provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation',
            'This decision adds no framework-core PHP, runtime dependency, command, checker rule, `PHT` diagnostic',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            '## Optional development Workbench',
            'Existing applications need not add `.ai/workbench.md`',
            'This changes neither the carried-forward Workbench contract nor Strict Profile version 3',
        ],
        $installedFramework . '/docs/security.md' => [
            '## Workbench limits',
            'Workbench is not a sandbox, dry run, redactor, authorization layer, output bound, environment verifier, or production-safety control.',
            'Workbench also provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
        ],
        $installedFramework . '/docs/jobs.md' => [
            'existing adopted business operation',
            'recorded finite tested one-delivery console command',
        ],
        $installedFramework . '/templates/application/.ai/workbench.md' => [
            '{{WORKBENCH_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_EXCLUDED_AUTHORITY_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_RESOURCE_LIMITS_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_SIDE_EFFECT_POLICY_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_JOB_PATH_OR_NOT_APPLICABLE}}',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'Workbench guidance');

    $consumerComposer = file_get_contents($project . '/composer.json');

    if (!is_string($consumerComposer)) {
        throw new RuntimeException('Unable to read the installed skeleton Composer manifest for Workbench proof.');
    }

    if (
        str_contains($consumerComposer, '"phpthis/workbench"')
        || is_file($project . '/vendor/bin/phpthis-workbench')
    ) {
        throw new RuntimeException(
            'The skeleton adopted phpthis/workbench without explicit application approval and verified Composer-source availability.',
        );
    }

    $workbenchContext = $project . '/.ai/workbench.md';
    $optionalContextProof = $project . '/.ai/workbench.md.optional-context-proof';

    if (!is_file($workbenchContext) || file_exists($optionalContextProof)) {
        throw new RuntimeException('Unable to prepare the optional Workbench context compatibility proof.');
    }

    if (!rename($workbenchContext, $optionalContextProof)) {
        throw new RuntimeException('Unable to remove the optional Workbench context for compatibility proof.');
    }

    try {
        $withoutWorkbenchContext = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $withoutWorkbenchContext,
            'The installed checker rejected a consumer only because .ai/workbench.md was absent.',
        );
        requireOutputContains($withoutWorkbenchContext, 'PASS PHPThis application check');
    } finally {
        if (!rename($optionalContextProof, $workbenchContext)) {
            throw new RuntimeException('Unable to restore the optional Workbench context after compatibility proof.');
        }
    }

    fwrite(STDOUT, "PASS installed Workbench guidance distribution\n");

    return 'installed-workbench-guidance-proved';
}

function proveInstalledStartupProbeGuidanceDistribution(string $project, string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |',
        ],
        $project . '/.ai/operations.md' => [
            '`GET /health` is the starter liveness route; no readiness route exists.',
            'It does not establish external-service-independent liveness because the deployment-configured `error_log` destination and its latency are unverified.',
            'covering success, mapped failure, unknown failure, captured summaries, throwing-sink isolation, and the real front controller.',
            '`Connection::connect()` constructs PDO eagerly and may fail during composition',
            'Do not preserve a liveness claim through a hidden bypass or second HTTP execution path.',
        ],
        $project . '/.ai/observability.md' => [
            'calls deployment-configured `error_log` synchronously before the coordinator returns',
            'throwing-sink response isolation',
        ],
        $project . '/.ai/testing.md' => [
            'This proves the current HTTP composition and response path, not external-service-independent liveness',
            'the coordinator invokes deployment-configured `error_log` synchronously and no destination or latency bound is recorded.',
            'do not treat connection construction as database-authority or complete-readiness evidence.',
        ],
        $installedFramework . '/docs/configuration.md' => [
            '### Eager composition and probe semantics',
            '`Connection::connect()` constructs native `PDO` immediately rather than returning a deferred handle.',
            'Depending on the selected driver and DSN, construction may perform database, filesystem, or network I/O and may fail during composition.',
            'Successful connection construction is also not evidence of schema compatibility, migration completion, capacity, per-operation database authority, or complete application readiness.',
            'Failure isolation that preserves a selected response does not by itself bound a synchronous sink\'s latency or make that probe external-service-independent.',
            'Do not disguise a dependency bypass as the ordinary application bootstrap or add a second hidden HTTP execution path.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            'Define, change, or review startup, liveness, dependency health, or readiness semantics',
            'verify that no framework probe API, lazy connection, hidden bypass, or second HTTP execution path was introduced',
        ],
        $installedFramework . '/docs/vocabulary.md' => [
            '| external-service-independent liveness |',
            '| readiness | application-owned operational claim that its recorded conditions for receiving traffic are satisfied |',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'A separate installed distribution proof checks the eager-composition and probe-semantics clarification',
            'the current starter does not claim external-service independence while its deployment-configured `error_log` destination and latency remain unverified',
            'does not connect to a service, prove that a deployment classified a probe correctly, establish dependency availability or traffic readiness',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            '{{HEALTH_AND_READINESS_PATHS}}',
            '`Connection::connect()` constructs PDO eagerly and, depending on the selected driver and DSN, may perform I/O or fail during composition.',
            'must not be described as external-service-independent liveness.',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Every adopted health, readiness, or non-HTTP probe proves the exact claim recorded in `.ai/operations.md`',
            'A caught sink failure proves response isolation, not a latency bound or independence from that sink\'s destination.',
            'Connection construction alone is not exact-statement database-authority or complete-readiness evidence.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'startup and probe guidance');

    fwrite(STDOUT, "PASS installed startup and probe guidance distribution\n");
}

function proveInstalledSessionCleanupAndResponseFramingDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/testing.md' => [
            'Cleanup evidence proves exact primary identity after success; redacted retention after cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            '`HTTP_RESPONSE_FRAMING`',
            'A `HEAD` route is explicit and returns an empty body without inferred representation length.',
        ],
        $installedFramework . '/docs/decisions/045-bounded-session-cleanup-and-response-framing.md' => [
            '# ADR 045: Bounded session cleanup and response framing',
            'Status: accepted',
            'When cleanup also fails, it throws the narrow redacted `SessionCleanupFailed` failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'Cleanup follows prerequisite order; it does not retry or attempt an unsafe dependent action after its prerequisite fails.',
            '`Response` accepts final response statuses from `200` through `599`',
            '`HEAD` remains application-owned and explicit.',
            'Strict Profile version 3 remains unchanged',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'Contract version: 11',
            'A final `Response` uses a status from `200` through `599`, never `Transfer-Encoding`',
            'a second cleanup failure becomes the narrow redacted `SessionCleanupFailed` retaining both failures',
            'Contract version 11 carries contract version 10 forward and retains Strict Profile version 3.',
        ],
        $installedFramework . '/docs/request-handling.md' => [
            'An ordinary final response has a status from `200` through `599`, no `Transfer-Encoding`',
            'A `204`, `205`, or `304` has no ordinary body and no `Content-Length`.',
            '`ResponseEmitter` receives only a `Response`',
        ],
        $installedFramework . '/docs/sessions.md' => [
            '## Cleanup failure precedence',
            'Failed invalidation cleanup likewise clears live pending-cookie ownership before it escapes.',
            'Cleanup follows prerequisite order and does not retry or attempt an unsafe dependent action after its prerequisite fails.',
            'If cleanup also fails, `SessionCleanupFailed` retains the original and cleanup failures',
            'PHPThis does not log, retry, suppress, or turn either failure into a response inside session code.',
        ],
        $installedFramework . '/src/Http/Response.php' => [
            '$status < 200 || $status > 599',
            "isset(\$normalizedHeaderNames['transfer-encoding'])",
            'in_array($status, [204, 205, 304], true)',
            '$contentLength !== (string) strlen($body)',
            '$contentLength !== (string) $fileBody->bytes',
        ],
        $installedFramework . '/src/Http/ResponseEmitter.php' => [
            'public function emit(Response $response): void',
            'echo $response->body;',
            'private function emitFile(Response $response, LocalFileBody $body): void',
        ],
        $installedFramework . '/src/Session/SessionCleanupFailed.php' => [
            'final class SessionCleanupFailed extends \\RuntimeException',
            'public readonly \\Throwable $primaryFailure',
            'public readonly \\Throwable $cleanupFailure',
            "parent::__construct('Session cleanup failed after a primary failure.');",
        ],
        $installedFramework . '/src/Session/SessionLifecycle.php' => [
            'private function failAfterCleanup(Throwable $primaryFailure, ?string $firstUnissuedId, ?string $secondUnissuedId = null, bool $abortActive = true): never',
            'throw new SessionCleanupFailed($primaryFailure, $cleanupFailure);',
            'if (!$this->cleanupFailed)',
            "} catch (Throwable \$failure) {\n            if (\$this->cleanupFailed) {\n                throw \$failure;\n            }\n            \$this->failAfterCleanup(\$failure, \$createdId);",
            '$this->cleanupFailed && session_status() !== PHP_SESSION_NONE',
            '$this->failAfterCleanup($failure, $newId, null, false);',
            "if (!session_start(\$options)) {\n            \$this->failAfterCleanup(new RuntimeException('Unable to start native session storage.'), null);\n        }",
            "new RuntimeException('Unable to invalidate native session state.')",
            "\$unissuedId = \$this->unissuedId;\n        \$this->unissuedId = \$this->pendingCookie = null;\n\n        \$this->start(\$incomingId, false);",
            '$this->unissuedId = $this->pendingCookie = null;',
            "if (session_status() === PHP_SESSION_NONE && session_id('') === false) {\n            \$this->cleanupFailed = true;\n            throw new RuntimeException('Unable to clear native session request state.');",
            "if (session_status() === PHP_SESSION_ACTIVE && !session_abort()) {\n            \$this->cleanupFailed = true;\n            throw new RuntimeException('Unable to abort native session state.');",
            'if ($abortActive)',
            '$previousCleanupFailed = $this->cleanupFailed;',
            '$this->cleanupFailed = true;',
            '$this->resetRequestState();',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Cleanup evidence proves exact primary identity after success; redacted retention after cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            'Every response test asserts the final status, body, and headers selected by the route.',
            'a `HEAD` route remains explicit with an empty body and no inferred representation length.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'session-cleanup and response-framing');

    $installedEmitter = file_get_contents($installedFramework . '/src/Http/ResponseEmitter.php');

    if (is_string($installedEmitter) && str_contains($installedEmitter, 'Request $request')) {
        throw new RuntimeException('The installed ResponseEmitter gained request knowledge.');
    }

    fwrite(STDOUT, "PASS installed session cleanup and response framing distribution\n");
}

function proveInstalledBoundedTaskRoutedContextGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    $simpleEndpointDefinition = 'A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision.';
    $simpleEndpointLocality = 'After universal entrypoints, a simple-endpoint change has exactly four task-specific files: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test.';
    $ordinaryImplementationRoute = 'Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.';
    $installedOrdinaryRoute = 'An ordinary route change starts with installed `vendor/phpthis/framework/docs/request-handling.md`; read a decision record only when reviewing or changing the decision it records.';
    $slimUniversalEntrypoint = 'Concern-specific rules live in the current guide routed by `.ai/README.md`; do not copy them into this universal entrypoint.';
    $finalClassContract = 'Every named class is final. Express extension points with interfaces, never non-final classes.';
    $databaseLoopContract = 'Never execute a database call inside `for`, `foreach`, `while`, `do`, or recursive traversal.';
    $privateConstructorScope = 'An operation-specific request, command, or projection parsed from external `mixed` uses a private constructor. This requirement does not set identifier constructor visibility; an application-owned identifier follows its recorded coherent convention.';

    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/AGENTS.md' => [
            $slimUniversalEntrypoint,
            '## Early database setup gate',
            'Start with the one current operational guide selected by `.ai/README.md`.',
            '## Project gate',
        ],
        $project . '/.ai/README.md' => [
            $installedOrdinaryRoute,
            'Use the exact simple-endpoint definition and four-file locality metric in the already-read installed `vendor/phpthis/framework/docs/knowledge-map.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.',
            '| Add or change a qualifying simple endpoint | installed `vendor/phpthis/framework/docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |',
        ],
        $project . '/.ai/rules.md' => [
            $finalClassContract,
            $databaseLoopContract,
            $privateConstructorScope,
        ],
        $project . '/.ai/architecture.md' => [
            'A qualifying dependency-free simple endpoint may be constructed inline only in an existing named route-area manifest so the root `Routes::create()` remains unchanged; every handler with a constructor dependency stays visibly constructed in the root and passed into its route area.',
        ],
        $project . '/src/Routes.php' => [
            'return [...HealthRoutes::create()];',
        ],
        $project . '/src/HealthRoutes.php' => [
            'public static function create(): array',
            "return [new Route('GET', '/health', new HealthHandler())];",
        ],
        $project . '/src/HealthHandler.php' => [
            'final class HealthHandler implements RequestHandler',
        ],
        $installedFramework . '/VISION.md' => [
            $simpleEndpointDefinition,
            $simpleEndpointLocality,
            $ordinaryImplementationRoute,
        ],
        $installedFramework . '/docs/decisions/044-bounded-task-routed-ai-context.md' => [
            '# ADR 044: Bounded task-routed AI context',
            $simpleEndpointDefinition,
            $simpleEndpointLocality,
            $ordinaryImplementationRoute,
            'Consumer Contract version 10 and Strict Profile version 3 remain unchanged.',
            'A report-only context-size or repeated-rule advisory was considered and is not adopted.',
            'Human review remains responsible for whether task routes stay compact and unambiguous.',
            'No context report script, `ApplicationChecker` rule, `PHT` diagnostic, or consumer-size validity gate is added.',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'Ordinary implementation starts with the current operational guide selected by those routers.',
            'Read a decision record only when reviewing or changing the decision it records; historical rationale is not ordinary implementation context.',
            'ADR 044 defines bounded task-routed AI context',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            $simpleEndpointDefinition,
            $simpleEndpointLocality,
            '| Add a simple application endpoint | `docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged, and this is the complete four-file task-specific set after universal entrypoints |',
        ],
        $installedFramework . '/docs/strict-profile.md' => [
            'Every named class in checked PHP is `final`; abstract classes also fail.',
            '`for`, `foreach`, `while`, or `do` header or body',
            'Mark the class final or expose an interface as the explicit extension point.',
        ],
        $installedFramework . '/docs/type-safety.md' => [
            'A parser-owned request, command, page-request, or projection value uses a private constructor',
            'This is not a universal constructor rule for application identifiers or other domain values',
            'Parser-owned request, command, page-request, and projection factories use private constructors',
        ],
        $installedFramework . '/docs/crud.md' => [
            'this is the single canonical current tree',
            'contains no speculative Update or Delete scaffold',
            'AuthorizeCreateUser.php',
            'UnacceptableCreateUserValues.php',
            'UserSummary.php',
            '/users/{user_id:positive-int}',
        ],
        $installedFramework . '/docs/database.md' => [
            '/accounts/{account_id:positive-int}/documents',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            "The bounded task-routed context guard pins ADR 044's exact simple-endpoint definition and four-file locality metric",
            'The installed proof checks the copied local skeleton plus packaged public guidance and application template, including the starter',
            'The guard adds no context report script, `ApplicationChecker` rule, `PHT` diagnostic, or consumer-size validity gate.',
        ],
        $installedFramework . '/templates/application/AGENTS.md' => [
            $slimUniversalEntrypoint,
            '## Early database setup gate',
            'Start with the one current operational guide selected by `.ai/README.md`.',
            '## Project gate',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            $installedOrdinaryRoute,
            'Use the exact simple-endpoint definition and four-file locality metric in the already-read installed `vendor/phpthis/framework/docs/knowledge-map.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.',
            '| Add or change a qualifying simple endpoint | installed `vendor/phpthis/framework/docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |',
        ],
        $installedFramework . '/templates/application/.ai/rules.md' => [
            $finalClassContract,
            $databaseLoopContract,
            $privateConstructorScope,
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'bounded task-routed context');

    /** @var array<string, list<string>> $forbiddenMarkers */
    $forbiddenMarkers = [
        $project . '/AGENTS.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            '`NOT_APPLICABLE(WORKBENCH)`',
            '`NOT_APPLICABLE(CLI)`',
            'each history\'s exact initial baseline',
        ],
        $project . '/.ai/rules.md' => [
            'Keep `NOT_APPLICABLE(WEBSOCKETS)`',
            'Keep `NOT_APPLICABLE(CLI)`',
            'Keep `NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`',
        ],
        $project . '/src/Routes.php' => [
            'HealthRoutes::create(new HealthHandler())',
        ],
        $project . '/src/HealthHandler.php' => [
            'function __construct',
        ],
        $installedFramework . '/docs/crud.md' => [
            'UpdateUser/',
            'DeleteUser/',
        ],
        $installedFramework . '/templates/application/AGENTS.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            '`NOT_APPLICABLE(WORKBENCH)`',
            'each history\'s exact initial baseline',
        ],
        $installedFramework . '/templates/application/.ai/rules.md' => [
            'Keep `NOT_APPLICABLE(WEBSOCKETS)`',
            'Keep every adopted operational command behind the sole application console',
            'Keep every adopted application-owned request-handler decorator',
        ],
        $installedFramework . '/verification/ApplicationChecker.php' => [
            'context-size',
            'repeated-rule',
            'context report',
        ],
    ];

    foreach ($forbiddenMarkers as $path => $markers) {
        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed bounded-context boundary artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (str_contains(strtolower($contents), strtolower($marker))) {
                throw new RuntimeException(
                    "Installed bounded-context boundary artifact {$path} retains forbidden marker: {$marker}",
                );
            }
        }
    }

    fwrite(STDOUT, "PASS installed bounded task-routed context guidance distribution\n");
}

function proveInstalledCrudAccessSurfaceGuidanceDistribution(
    string $project,
    string $installedFramework,
): void
{
    $crudAccessSurfaceContractMarkers = [
        'Give every surface its own named route-area list with explicit route entries.',
        'Separate its action-specific policy composition when authentication, named authorization action, tenant resolution, or policy budget or trace differs.',
        'Separate its HTTP handler and boundary types when accepted input, tenant, resource or data scope, SQL, projection or disclosure, failure behavior, HTTP cache policy, handler query budget or trace, side effects, or audit effects differ.',
        'Keep its SQL owner separate when data scope or SQL differs.',
        'Do not share an existing independently meaningful typed business or transaction operation, including any typed operation seam, when its typed input, data scope or SQL, transaction or concurrency policy, result contract, side effects, or audit effects differ.',
        'A route or method difference alone does not require duplicating an otherwise identical handler or operation',
        'Narrowly typed authentication, tenant-resolution, or denial implementations may be shared when their contracts are identical, while every protected named action retains its own action-specific authorization contract.',
        'Share one existing independently meaningful typed business or transaction operation, including any typed operation seam, only when its complete responsibility remains identical and each surface reaches it only after its own applicable validation and, when protected, current authorization.',
        'Do not put role, audience, mode, or permission branching inside a shared handler or business operation to select SQL, behavior, side effects, or disclosure.',
        "Do not add a superset projection filtered for another surface or SQL broader than the receiving surface's recorded contract.",
    ];

    $crudAccessSurfaceEvidenceMarker = 'For a resource exposed through multiple access surfaces, prove that each named route-area list selects its intended handler and its applicable policy path or recorded not-applicable policy; when protected, denial performs no protected work; and no surface executes SQL or side effects or emits fields outside its recorded operation contract and, when applicable, named authorization action and tenant or resource scope.';

    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/architecture.md' => [
            'Before exposing one resource through a second access surface, record the selected surface-grouping rule and permitted sharing here.',
            ...$crudAccessSurfaceContractMarkers,
            'An alternate layout cannot weaken the installed consumer contract or Strict Profile.',
            'A directory, namespace, route prefix, or route-list label never establishes authority',
            'Do not impose a forced surface directory hierarchy.',
        ],
        $installedFramework . '/docs/crud.md' => [
            '## Multiple access surfaces',
            ...$crudAccessSurfaceContractMarkers,
            'The table selects no directory hierarchy.',
            'An application may record one coherent resource-first, surface-first, or capability-first organization in `.ai/architecture.md`.',
            'A directory, namespace, route prefix, or route-list name is an authoring and review aid, never an authorization mechanism.',
            $crudAccessSurfaceEvidenceMarker,
            'do not split genuinely identical behavior merely because two routes carry different audience labels',
            'PHPThis never discovers or validates a feature from its directory name.',
        ],
        $installedFramework . '/templates/application/.ai/architecture.md' => [
            '{{CRUD_MULTI_SURFACE_ORGANIZATION_AND_SHARING_POLICY_OR_NOT_APPLICABLE}}',
            'When one resource is exposed through multiple access surfaces, record the selected grouping rule and permitted sharing above.',
            ...$crudAccessSurfaceContractMarkers,
            'An alternate directory and naming policy cannot weaken the installed consumer contract or Strict Profile',
            'Do not impose a forced surface directory hierarchy.',
        ],
        $project . '/.ai/testing.md' => [
            $crudAccessSurfaceEvidenceMarker,
            'Do not add runtime or checker assertions for optional CRUD directory and naming choices.',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            $crudAccessSurfaceEvidenceMarker,
            'Directory and naming choices in the optional CRUD profile are application context, not runtime or checker assertions.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'CRUD access-surface guidance');

    fwrite(STDOUT, "PASS installed CRUD access-surface guidance distribution\n");
}

function proveInstalledIdentifierRepresentationGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    $architectureMarkers = [
        'one narrowly named application-owned representation primitive for shared validation and canonical scalar representation',
        'generation remains a separate explicitly versioned policy',
        'operations still require the concrete domain identifier, never the shared primitive',
    ];
    $testingMarkers = [
        'When multiple concrete identifiers compose one recorded application-owned representation primitive',
        'operation signatures continue to require the concrete identifier',
        'versions 1 through 8 and RFC variant nibbles `8`, `9`, `a`, and `b`',
        'Test generation separately from acceptance',
        'prove the exact recorded generator source contract',
        'Version and variant bits alone are insufficient.',
        'finite generated samples do not prove uniqueness or total creation order',
    ];
    $skeletonDataMarkers = [
        '`NOT_APPLICABLE(UUID_POLICY)`',
        'The reference acceptance policy is canonical lowercase RFC-variant versions 1 through 8.',
        'Version 7 is recommended for newly generated database row identifiers when embedded approximate creation-time disclosure is accepted',
        'generation owner and exact application source path, selected package and version, database facility and engine version, or external owner',
        'accepted metadata-bearing UUID exposure and handling',
        'failure and no-fallback policy',
        'Choosing version 4 does not prevent metadata disclosure',
        'PHPThis selects no generator, package, database facility, schema rule, or persistence representation.',
    ];
    $templateDataMarkers = [
        '`UUID_POLICY(ADOPTED)`',
        '{{UUID_POLICY_1_SCOPE_AND_CONCRETE_IDENTIFIERS}}',
        '{{UUID_POLICY_1_ACCEPTED_CANONICAL_VERSIONS}}',
        '{{UUID_POLICY_1_GENERATED_VERSION_AND_PURPOSE_OR_NOT_APPLICABLE}}',
        '{{UUID_POLICY_1_GENERATION_OWNER_AND_EXACT_APPLICATION_SOURCE_PACKAGE_DATABASE_OR_EXTERNAL_SOURCE_OR_NOT_APPLICABLE}}',
        '{{UUID_POLICY_1_GENERATED_VALUE_METADATA_AND_TIME_DISCLOSURE_DECISION}}',
        '{{UUID_POLICY_1_ACCEPTED_METADATA_BEARING_UUID_EXPOSURE_AND_HANDLING}}',
        '{{UUID_POLICY_1_SAME_TIMESTAMP_ORDERING_SCOPE_AND_CLOCK_REGRESSION_BEHAVIOR_OR_NOT_APPLICABLE}}',
        '{{UUID_POLICY_1_FAILURE_AND_NO_FALLBACK_POLICY_OR_NOT_APPLICABLE}}',
        '{{UUID_POLICY_1_NARROWER_DOMAIN_RULES_OR_NONE}}',
        '{{UUID_POLICY_1_PERSISTENCE_REPRESENTATION_AND_ORDERING_ASSUMPTIONS}}',
        '{{UUID_POLICY_1_EVIDENCE_SOURCE}}',
        'Keep accepted versions separate from the version generated for new values.',
        'PHPThis recommends version 7 for newly generated database row identifiers when embedded approximate creation-time disclosure is accepted',
        'That choice does not prevent metadata disclosure if accepted or persisted time-bearing UUID versions such as 1, 6, or 7 are exposed.',
        'Record the generation owner as an application source path, selected package and version, database facility and engine version, or explicit external owner.',
        'PHPThis selects no generator, package, database facility, schema rule, or persistence representation.',
    ];

    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/architecture.md' => $architectureMarkers,
        $project . '/.ai/data.md' => $skeletonDataMarkers,
        $project . '/.ai/testing.md' => $testingMarkers,
        $installedFramework . '/docs/request-handling.md' => [
            'one narrowly named application-owned representation primitive',
            'That primitive may own only the shared validation and canonical scalar representation',
            'Generation remains a separate, explicitly versioned application policy',
            'application operations continue to require that concrete type rather than the shared primitive',
            'Treat accepted UUID versions and newly generated UUID versions as separate decisions.',
            'PHPThis recommends UUID version 7 when disclosing its embedded approximate creation time is acceptable.',
            'not an adopted application fact',
            'Accepted metadata-bearing UUID exposure and handling',
            'application source path, selected package and version, database facility and engine version, or explicit external owner',
            'PHPThis supplies no UUID value object, generator, package choice, database function, schema rule, binding, or persistence abstraction.',
        ],
        $installedFramework . '/templates/application/.ai/architecture.md' => $architectureMarkers,
        $installedFramework . '/templates/application/.ai/data.md' => $templateDataMarkers,
        $installedFramework . '/templates/application/.ai/testing.md' => $testingMarkers,
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'identifier representation guidance');

    fwrite(STDOUT, "PASS installed identifier representation guidance distribution\n");
}

function proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/data.md' => [
            "\n`NOT_APPLICABLE(DATABASE)`\n",
            'database/catalog/schema/attachment namespace selection and qualification as supported',
            'namespace and object control or ownership model or explicit N/A',
            'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'one accountable non-HTTP owner and authoritative implementation reference for every adopted authority activation and deactivation',
            'Configuration, connectivity, target existence, and migration completion do not activate runtime authority.',
        ],
        $project . '/.ai/migrations.md' => [
            'accepted engine-specific database definition or provisioning, supported namespace/control model, data-definition, authority, coordination, recovery, and integration decision',
            'selected authority-transition implementation source and complete non-HTTP implementation path',
            'the history\'s engine-specific compatibility, authority-verification, failure-stop, and handoff constraints',
            'application-wide release sequence recorded only in `.ai/operations.md`',
        ],
        $project . '/.ai/operations.md' => [
            'authority-transition owner or activation stage',
            'Record here, keyed by stable history name or explicit intersecting-history set, the deployment runner',
            'application-owned sequence through authority verification, rollout, traffic enablement, later deactivation',
            'No universal deployment order is inferred',
        ],
        $project . '/.ai/testing.md' => [
            'Execute every intended statement under the runtime identity before traffic',
            'selected prohibited namespace, data-definition, identity or role, authority-administration, migration-ledger, database or global, and unrelated-target capabilities',
            'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'each adopted authority activation and deactivation has one visible non-HTTP owner and path, record `GRANT` and `REVOKE` only where supported',
            'elevated configuration remains unavailable to HTTP',
            'Configuration, connectivity, target existence, migration success, PHT006, tenant predicates, and adversarial bindings are not universal authority',
        ],
        $installedFramework . '/docs/decisions/038-application-owned-database-authority-lifecycle.md' => [
            'Status: accepted',
            'Database and object definition source; database/catalog/schema/attachment namespace selection and qualification as supported; namespace and object control-or-ownership; and active authority are separate application facts.',
            'Withholding all runtime object access is valid before a named application operation exists.',
            'Each adopted authority activation or deactivation has one explicit application-owned path.',
            'The installed application checker adds one deliberately narrow context-consistency check',
            'No framework runtime type or dependency is added.',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'treat zero runtime object access as valid before a named application operation exists',
            'record how effective authority resolves under the selected engine, using only applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other engine-specific sources',
            '`GRANT` or `REVOKE` migration SQL when supported and selected',
            'record the application-owned ordering among migration, authority activation, exact-engine authority verification, application rollout, and traffic enablement',
            'Configuration parsing, successful connectivity, `SELECT 1`, object existence, and migration success do not prove usable runtime authority.',
        ],
        $installedFramework . '/docs/database.md' => [
            '### Authority activation lifecycle',
            'Configuration and source presence do not activate database authority.',
            'Database and object definition source; database/catalog/schema/attachment namespace selection and qualification as supported; namespace and object control-or-ownership; and active authority are separate facts.',
            'Record only applicable sources, such as direct, role or inherited, public or default, database or global, ownership-chain, IAM, or filesystem and process authority.',
            'Each adopted authority activation or deactivation has one explicit application-owned owner and path.',
            '`GRANT` or `REVOKE` SQL may be visible and checksum-covered inside a migration when the selected engine supports and uses it',
        ],
        $installedFramework . '/docs/security.md' => [
            'Withholding runtime object access is valid until a named operation exists.',
            'Account for effective authority using only the engine\'s applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other sources.',
            'Every authority activation and deactivation has one recorded application-owned owner and non-HTTP path.',
            '`GRANT` or `REVOKE` SQL is supported, selected, and part of a migration',
            'PHPThis neither requires nor discourages an engine-default or application-specific database, catalog, schema, attachment namespace, or equivalent.',
        ],
        $installedFramework . '/docs/migrations.md' => [
            '## Authority transition and release handoff',
            'Migration success proves the migration path only.',
            'Before dependent code receives traffic, positive evidence executes its exact runtime statements under the runtime identity',
            'PHPThis does not prescribe migration-first or code-first rollout.',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            "A separate installed distribution proof checks that ADR 038's application-owned authority lifecycle remains present",
            'This marker proof is a source-distribution check only: it performs no live authority probe, validates no engine privilege or control model',
        ],
        $installedFramework . '/templates/application/.ai/data.md' => [
            '{{CONNECTION_1_DATABASE_DEFINITION_OR_PROVISIONING_SOURCE}}',
            '{{CONNECTION_1_NAMESPACE_SELECTION_AND_QUALIFICATION_POLICY}}',
            '{{CONNECTION_1_NAMESPACE_AND_OBJECT_CONTROL_OR_OWNERSHIP_MODEL_OR_NOT_APPLICABLE}}',
            '{{DATABASE_AUTHORITY_1_CONNECTION_AND_OPERATION}}',
            '{{DATABASE_AUTHORITY_1_EFFECTIVE_AUTHORITY_RESOLUTION_SOURCE}}',
            'capability isolation where supported or exact effective overlap and residual risk',
            'otherwise record the exact effective-authority overlap and residual risk, including SQLite file-level limits',
            '{{ELEVATED_PROFILE_1_AUTHORITY_TRANSITION_OWNER_AND_IMPLEMENTATION_REFERENCE_OR_NOT_APPLICABLE}}',
            'Record `GRANT` and `REVOKE` in the referenced transition implementation only where the exact engine supports and the application selects them.',
        ],
        $installedFramework . '/templates/application/.ai/migrations.md' => [
            '{{MIGRATION_ENGINE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONFIGURATION_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_AUTHORITY_TRANSITION_IMPLEMENTATION_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_RELEASE_CONSTRAINTS_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            '{{DATABASE_AUTHORITY_AND_RELEASE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
            '{{DATABASE_AUTHORITY_TRANSITION_RUNBOOK_AND_EVIDENCE_MAPPING_OR_NOT_APPLICABLE}}',
            '{{DATABASE_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}',
            '{{DATABASE_COMPATIBILITY_DEACTIVATION_AND_REMOVAL_POLICY_OR_NOT_APPLICABLE}}',
            '{{DATABASE_PRE_TRAFFIC_AUTHORITY_GATE_EVIDENCE_AND_OWNER_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'executes every intended statement for each named operation under the runtime identity before traffic',
            'selected prohibited namespace, data-definition, identity or role, authority-administration, migration-ledger, database or global, and unrelated-target capabilities',
            'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'Configuration, connectivity, target existence, and migration success are not authority evidence.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'database authority lifecycle');

    fwrite(STDOUT, "PASS installed database authority lifecycle guidance distribution\n");
}

function proveInstalledEngineSpecificMigrationInvariantGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change database migrations | `.ai/migrations.md` | configuration, authority, manifest, ledger, operations, and exact-engine tests |',
        ],
        $project . '/.ai/migrations.md' => [
            'each separately tracked history\'s exact initial baseline',
            'required position, identifier, and checksum',
            'finite exact-engine accepted metadata and explicitly permitted supporting objects',
            'every accepted present object, data assumption, ledger row, and checksum',
            'exact-baseline and accepted-ledger-prefix validation, every pending checksum-covered',
            'application-wide release sequence recorded only in `.ai/operations.md`',
            'rejection of missing, incompatible, or additional unrecorded ledger-related objects before history parsing or pending work',
            'shared exclusion across concurrently reachable topologies for one history or pairwise authority gating',
            'owner fencing or confirmed termination',
            'next owner to reacquire coordination and re-detect exact state before mutating',
            'disjoint managed objects, data, authority transitions, and coordination domains between separately tracked histories',
            'cross-history isolation or shared-boundary partial-deployment behavior',
            'exact creation, acquisition, use, and release permissions or authority',
            'finite stable output, redaction, exact-engine, and no-HTTP-startup tests',
            'ADR 027 remains the accepted SQLite reference proof.',
            'Those mechanics and names are not another engine, topology, or application\'s defaults.',
        ],
        $project . '/.ai/operations.md' => [
            'Record exact configuration and process identity only in `.ai/configuration.md`',
            'effective authority facts and accountable transition ownership only in `.ai/data.md`',
            'This file records only stable-history-keyed operational owners, mappings, runbooks, and evidence references',
            'it does not restate migration, configuration, identity, or authority policy',
        ],
        $project . '/.ai/testing.md' => [
            'exact initial baseline',
            'every concurrently reachable topology pair',
            'migration-effect/ledger consistency at every failure boundary',
            'validate the accepted ledger prefix; prove every pending checksum-covered statement',
            'Multiple histories prove disjoint managed objects, data, authority transitions, and coordination domains before they are called independent',
            "When ADR 027's SQLite reference shape is adopted",
            'Do not generalize that SQLite transaction, file-lock, rollback, output, or filesystem-authority evidence to another engine or host topology.',
            'Migration evidence separately proves exact creation, acquisition, use, and release permissions or authority',
        ],
        $project . '/.ai/configuration.md' => [
            'one separately named factory, final readonly output type, and process identity for each adopted process profile',
            'each migration history records its own exact input names and never inherits, combines, or falls back',
        ],
        $project . '/.ai/data.md' => [
            'each future history\'s source and namespace; exact initial baseline',
            'stable coordination namespace, collision, creation/acquisition/use/release permissions, reachable-topology exclusion, and lost-owner behavior',
            '`.ai/configuration.md` owns exact no-fallback configuration and process identity, this file owns effective authority facts and accountable transition ownership, and `.ai/operations.md` alone owns the application-wide release and cross-history recovery execution sequence',
        ],
        $project . '/.ai/cli.md' => [
            'When a scheduled pass is adopted, additionally record',
            'every adopted migration history has its own separately scoped references',
            'exact process identity, process-specific configuration factory, and final readonly type recorded in `.ai/configuration.md`',
            'A migration-only console records writer coordination or serialization in `.ai/migrations.md` under ADR 043.',
        ],
        $installedFramework . '/docs/decisions/043-engine-specific-application-migration-invariants.md' => [
            '# ADR 043: Engine-specific application migration invariants',
            '### Universal application-owned invariants',
            'These invariants require ledger consistency, not one universal transaction shape.',
            'These invariants also require explicit concurrency decisions, not one universal lock.',
            'record the exact effective-authority overlap between migration and runtime',
            'including SQLite file-level authority limits',
            'finite accepted catalog or metadata surface and the rejection policy for unrecorded or incompatible',
            'additional fields are finite, non-executable, validated, and never select migration work, define order, or authorize behavior',
            'checksum-covered exact statement sequences plus every code-owned binding value or finite binding-derivation policy',
            'All writer topologies that can reach one history must participate in one shared exclusion domain or use explicit authority gating',
            'An expiring or losable mechanism is valid only when a successor cannot begin a mutation while an earlier owner\'s statement may still be executing',
            'Before implementing any adoption, the accountable human approves an application decision',
            '`.ai/configuration.md` owns exact configuration and process identity, `.ai/data.md` owns effective database-authority facts and accountable transition ownership, `.ai/migrations.md` owns the per-history migration constraints and transition implementation, and `.ai/operations.md` alone owns the application-wide sequence and operational runbooks.',
            'exact-engine evidence that permits shared or production use',
            '### SQLite reference proof',
            'Consumer Contract version 10 and Strict Profile version 3 remain unchanged.',
            'No framework migration API, schema builder, DSL, discovery rule, generic ledger or lock type, transaction callback, permission abstraction, automatic rollback, runtime SQL loading, HTTP-startup behavior, core change, contract-version change, or Strict Profile change is introduced.',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'ADR 043 defines universal application-owned migration invariants',
            'engine-specific ledger-consistency boundary',
            'complete exact-engine evidence in `.ai/migrations.md`',
            'finite exact-engine ledger metadata surface',
            'every code-owned binding name/type/literal value or complete finite binding-derivation policy',
            'All topologies that can reach one history share an exclusion domain or use explicit authority gating',
            'a successor cannot mutate until in-flight prior-owner work is fenced',
            'ADR 027 remains the one executable SQLite reference proof.',
            'not universal migration requirements',
            'PHPThis supplies no universal lock.',
            'Exact configuration and process identity remain authoritative in `.ai/configuration.md`',
        ],
        $installedFramework . '/docs/migrations.md' => [
            '[universal application-owned migration invariants](decisions/043-engine-specific-application-migration-invariants.md)',
            '## Engine-specific ledger-consistency path',
            'Ledger consistency is universal; one transaction shape is not.',
            'Concurrency coverage is universal; one lock is not.',
            'the exact recorded initial baseline, including any externally pre-provisioned objects',
            'finite exact-engine definition-verification surface',
            'which unrecorded or incompatible columns, types, nullability, defaults, keys, constraints, indexes, triggers, rules, policies, ownership, and authority it rejects',
            'Additional fields are finite, non-executable, validated, and never select migration work, define order, or authorize behavior.',
            'every code-owned binding name, type, and literal value',
            'checksum the complete finite derivation policy and its input contract instead of the runtime result',
            'same- and cross-topology concurrent migration writers',
            'first pending migration may run',
            'explicitly accepted ledger prefix that the migration identity validates rather than re-executes',
            '`.ai/operations.md` alone owns the application-wide release sequence',
            'exact-engine evidence',
            '### SQLite reference transaction',
            'Those are SQLite reference requirements, not substitutes for another engine\'s exact coordination and partial-failure evidence.',
            'exact creation, acquisition, use, and release permissions or authority',
        ],
        $installedFramework . '/docs/cli.md' => [
            'each command\'s configuration-profile and authority references',
            '`.ai/configuration.md` owns exact process identity and configuration, `.ai/data.md` owns effective database-authority facts and accountable transition ownership, and `.ai/migrations.md` owns each history\'s transition implementation and handoff constraints',
            'A console with no scheduled pass records those schedule-only facts as not applicable.',
            'a migration-only console does not need a scheduler overlap lock or cadence policy.',
            'the ADR 027 SQLite proof additionally requires its empty-database case',
        ],
        $installedFramework . '/docs/cli/testing.md' => [
            'When a scheduled pass is adopted, use its explicit deterministic clock',
            'When a scheduled pass adopts the ADR 028 lease',
            'prove the exact recorded initial baseline and manifest order',
            'statement and code-owned binding or finite binding-policy drift',
            'same- and cross-topology exclusion or authority gating',
            'For the ADR 027 SQLite proof, additionally prove its empty-database case',
        ],
        $installedFramework . '/docs/getting-started.md' => [
            'one accepted engine-specific migration policy following ADR 043',
            'engine-specific ledger-consistency boundary and every non-atomic state',
            "ADR 027's per-migration transaction, rollback, and same-host `flock` are required only when adopting its SQLite reference boundary",
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            'ADR 043, ADR 027 for the SQLite reference proof',
            '`.ai/configuration.md` for exact no-fallback process configuration and identity',
            '`.ai/data.md` for effective database-authority facts, accountable transition ownership',
            '`.ai/operations.md` for the application-wide release order and operational runbooks',
            'scope transaction, rollback, and lock claims to their proved engine and topology',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Change database migrations | `.ai/migrations.md` | configuration, authority, manifest, ledger, operations, and exact-engine tests |',
        ],
        $installedFramework . '/templates/application/.ai/migrations.md' => [
            '{{MIGRATION_CONSOLE_EXECUTABLE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_HISTORY_STABLE_NAME_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_HISTORY_COMMAND_OR_NOT_APPLICABLE}}',
            '## Separately tracked history: `{{MIGRATION_HISTORY_STABLE_NAME_OR_NOT_APPLICABLE}}`',
            'copy this complete section once for every separately tracked history and replace every placeholder inside each copy',
            'Use one stable application-owned history name consistently',
            'Do not combine several histories in one field.',
            '## Shared migration rules',
            '{{MIGRATION_INITIAL_BASELINE_OR_NOT_APPLICABLE}}',
            'every accepted present object, data assumption, ledger row, and checksum',
            '{{MIGRATION_RELEASE_CONSTRAINTS_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_ATOMICITY_AND_LEDGER_CONSISTENCY_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_COORDINATION_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONFIGURATION_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_AUTHORITY_TRANSITION_IMPLEMENTATION_OR_NOT_APPLICABLE}}',
            'exact creation, acquisition, use, and release permissions or authority',
            '{{MIGRATION_CROSS_TOPOLOGY_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_COORDINATION_COVERAGE_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CROSS_HISTORY_POLICY_OR_NOT_APPLICABLE}}',
            'proved disjoint managed objects, data, authority transitions, and coordination domains',
            'Ledger requiring position, identifier, and checksum',
            'every code-owned binding name/type/literal value or finite binding-derivation policy',
            'any selected extra metadata, including a timestamp, has an explicit source, representation, and bound, is parsed and validated as non-executable data, and cannot select work, define order, or grant authority',
            'finite exact-engine accepted metadata and explicitly permitted supporting objects',
            'rejection of missing, incompatible, and additional unrecorded ledger-related objects',
            'next-owner reacquisition and exact-state redetection before mutation',
            'partial-failure detection, forward-correction, backup, restore, and recovery policy',
            'ADR 027 remains the accepted SQLite reference proof.',
            'Those mechanics and names are conditional SQLite/example policy, not portable defaults.',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            '{{CLI_NON_MIGRATION_DEPLOYMENT_RUNNER_AND_INCIDENT_MAPPING_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_DEPLOYMENT_RUNNER_MAPPING_OR_NOT_APPLICABLE}}',
            'Exact initial baseline per stable history name: `.ai/migrations.md`; do not duplicate it here.',
            '{{MIGRATION_COORDINATION_RUNBOOK_AND_EVIDENCE_MAPPING_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_MAINTENANCE_CAPACITY_TERMINATION_AND_INCIDENT_MAPPING_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_RECOVERY_AND_CROSS_HISTORY_RUNBOOK_MAPPING_OR_NOT_APPLICABLE}}',
            'The bullets above record only stable-history-keyed operational owners, mappings, runbooks, and evidence references; they do not restate those policies.',
            'exact process identity and configuration remain authoritative in `.ai/configuration.md`, and effective authority facts plus accountable transition ownership remain authoritative in `.ai/data.md`',
            'the underlying per-history and shared-mechanism policy remains in `.ai/migrations.md`',
            'This guide owns the application-specific release sequence and operational runbooks',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'exact recorded initial baseline',
            'every concurrently reachable topology pair',
            'migration-effect/ledger consistency at every failure boundary',
            'validates the accepted ledger prefix; proves every pending checksum-covered statement',
            'Multiple histories prove disjoint managed objects, data, authority transitions, and coordination domains before they are called independent',
            "When ADR 027's SQLite reference shape is adopted",
            'Do not generalize that SQLite transaction, file-lock, rollback, output, or filesystem-authority evidence to another engine or host topology.',
            'exact creation, acquisition, use, and release permissions or authority',
        ],
        $installedFramework . '/templates/application/.ai/configuration.md' => [
            '{{ELEVATED_CONFIGURATION_FACTORIES_TYPES_IDENTITIES_AND_HISTORY_OWNERSHIP_OR_NOT_APPLICABLE}}',
            'Runtime, each migration history, and administrative profile, input-name, and credential separation with no inheritance, combined credentials, or fallback',
        ],
        $installedFramework . '/templates/application/.ai/data.md' => [
            '{{ELEVATED_PROFILE_1_HISTORY_OR_ADMIN_NAME_OR_NOT_APPLICABLE}}',
            'Record one separate row per adopted migration history',
            '{{ELEVATED_PROFILE_1_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}}',
            'capability isolation where supported or exact effective overlap and residual risk',
            'otherwise record the exact effective-authority overlap and residual risk, including SQLite file-level limits',
            '{{ELEVATED_PROFILE_1_AUTHORITY_TRANSITION_OWNER_AND_IMPLEMENTATION_REFERENCE_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/cli.md' => [
            '{{CLI_CONSOLE_EXECUTABLE_OR_NOT_APPLICABLE}}',
            '{{CLI_COMMAND_PROFILE_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            'Complete the clock, cadence, overlap, and supervisor fields only when a scheduled pass is adopted',
            'A migration-only console records writer coordination or serialization in `.ai/migrations.md` under ADR 043',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'engine-specific migration-invariant');

    fwrite(STDOUT, "PASS installed engine-specific migration-invariant guidance distribution\n");
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledMigrationStructureGuidanceDistribution(
    string $project,
    string $installedFramework,
    array $profileCommand,
    array $environment,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/migrations.md' => [
            'No migration directory, code, or dependency is included',
            'PHPThis recommends `src/Database/Migrations/`',
            '`App\\Database\\Migrations` namespace',
            'Record the actual adopted directory and namespace in this file.',
            'neither PHPThis nor the consumer checker enforces the recommendation or discovers migration files',
            'multiple named database connections later adopt separately tracked migration histories',
            'do not pre-create or prescribe connection subdivisions',
        ],
        $installedFramework . '/docs/decisions/039-recommended-database-migration-structure.md' => [
            'Status: accepted',
            'Migrations are specialized application-owned database evolution.',
            'A consumer may instead record any coherent application-owned path and namespace.',
            'does not reject an alternative, enforce this directory through the checker or Strict Profile',
            'The database-free skeleton does not create an empty migration directory.',
            'multiple named database connections own separately tracked migration histories',
            'histories are called independent only after their managed objects, data, authority transitions, and coordination domains are proved disjoint',
            'does not create speculative connection directories for a single-database application',
            'does not establish a generic database layer',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'ADR 039 recommends `src/Database/Migrations/`',
            'A coherent consumer-selected alternative remains valid',
            'does not enforce migration placement through the checker or Strict Profile',
            'no empty migration directory',
            'explicit connection-owned subdivision for each adopted history',
            'Do not combine their credentials or invent connection subdivisions for a single-database application or for a connection that has no separately adopted migration history.',
        ],
        $installedFramework . '/docs/database.md' => [
            'Migrations are specialized application-owned database evolution.',
            'ADR 039 recommends `src/Database/Migrations/`',
            'records its actual source path and namespace in `.ai/migrations.md`',
            'any coherent alternative remains valid',
            'does not enforce placement, discover work from a directory, silently relocate established source',
            'multiple named database connections adopt separately tracked migration histories',
            'creates no speculative connection directories for a single-database application',
        ],
        $installedFramework . '/docs/migrations.md' => [
            '## Recommended application structure',
            'record the actual path and namespace in `.ai/migrations.md`',
            'A consumer may choose any coherent alternative.',
            'does not enforce a path through the checker or Strict Profile',
            'A database-free skeleton creates no empty migration directory.',
            'PHPThis recommends no subdivision spelling',
            'connection without its own migration history',
            'does not recommend a generic `Database/Queries` directory, repository, query-object layer, or alternate SQL execution boundary',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            "ADR 039's migration-structure recommendation",
            'The proof then records `src/Infrastructure/ChangeHistory/` and `App\\Infrastructure\\ChangeHistory` in the isolated consumer',
            'proves Composer can autoload it, and requires the installed canonical checker to pass',
            'The fixture performs no database I/O or migration execution',
        ],
        $installedFramework . '/templates/application/.ai/migrations.md' => [
            '{{MIGRATION_SOURCE_DIRECTORY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_APPLICATION_NAMESPACE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONNECTION_OWNERSHIP_OR_NOT_APPLICABLE}}',
            'PHPThis recommends `src/Database/Migrations/`',
            'A coherent consumer-selected alternative is authoritative',
            'neither PHPThis nor the consumer checker enforces the recommendation or discovers migration files',
            'Do not prescribe or create subdivisions for a single-database application or a connection without a separately tracked migration history.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'migration-structure guidance');

    if (is_dir($project . '/src/Database/Migrations') || is_dir($project . '/src/Migrations')) {
        throw new RuntimeException('The database-free installed skeleton unexpectedly contains a migration directory.');
    }

    $migrationContextPath = $project . '/.ai/migrations.md';
    $originalMigrationContext = file_get_contents($migrationContextPath);

    if (!is_string($originalMigrationContext)) {
        throw new RuntimeException('Unable to read the installed skeleton migration context.');
    }

    $alternativeDirectory = $project . '/src/Infrastructure/ChangeHistory';
    $alternativeSourcePath = $alternativeDirectory . '/ApplicationMigrations.php';

    writeFile(
        $migrationContextPath,
        <<<'MD'
# Application migration contract

- Adoption: synthetic alternative-layout checker proof
- Actual adopted migration source directory: `src/Infrastructure/ChangeHistory/`
- Matching application namespace: `App\Infrastructure\ChangeHistory`
- Final concrete coordinator: `App\Infrastructure\ChangeHistory\ApplicationMigrations`
- Placement authority: this application-selected path and namespace are explicit and no filesystem discovery is used.
- Proof boundary: this fixture performs no database I/O, schema mutation, or migration execution.
MD,
    );
    writeFile(
        $alternativeSourcePath,
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Infrastructure\ChangeHistory;

final class ApplicationMigrations
{
    public static function sourceDirectory(): string
    {
        return 'src/Infrastructure/ChangeHistory';
    }
}
PHP,
    );

    try {
        $autoloadResult = runProcess(
            [
                PHP_BINARY,
                '-r',
                sprintf(
                    'require %s; exit(class_exists(%s) ? 0 : 1);',
                    var_export($project . '/vendor/autoload.php', true),
                    var_export('App\\Infrastructure\\ChangeHistory\\ApplicationMigrations', true),
                ),
            ],
            $project,
            $environment,
        );
        requireSuccess(
            $autoloadResult,
            'The consumer-selected migration path and namespace are not Composer-autoload coherent.',
        );

        $alternativeResult = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $alternativeResult,
            'The installed checker rejected a coherent consumer-selected migration structure.',
        );
        requireOutputContains($alternativeResult, 'PASS PHPThis application check');
    } finally {
        writeFile($migrationContextPath, $originalMigrationContext);

        if (is_file($alternativeSourcePath) && !unlink($alternativeSourcePath)) {
            throw new RuntimeException('Unable to remove the alternative migration-structure proof.');
        }

        if (is_dir($alternativeDirectory) && !rmdir($alternativeDirectory)) {
            throw new RuntimeException('Unable to remove the alternative migration-structure directory.');
        }

        $alternativeInfrastructureDirectory = dirname($alternativeDirectory);

        if (
            is_dir($alternativeInfrastructureDirectory)
            && !rmdir($alternativeInfrastructureDirectory)
        ) {
            throw new RuntimeException('Unable to remove the alternative migration parent directory.');
        }
    }

    fwrite(STDOUT, "PASS installed migration alternative structure\n");
    fwrite(STDOUT, "PASS installed migration structure guidance distribution\n");
}

/** @param array<string, string> $environment */
function proveInstalledUuidAndUlidRouting(string $project, array $environment): void
{
    $proofPath = $project . '/installed-routing-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require __DIR__ . '/vendor/autoload.php';

$handler = new class implements RequestHandler {
    public function handle(Request $request): Response
    {
        return new Response(204, [], '');
    }
};
$router = new Router([
    new Route('GET', '/accounts/{account_id:uuid}', $handler),
    new Route('POST', '/events/{event_id:ulid}', $handler),
]);
$validUuids = [
    '123e4567-e89b-12d3-8456-426614174000',
    '123e4567-e89b-22d3-9456-426614174000',
    '123e4567-e89b-32d3-a456-426614174000',
    '123e4567-e89b-42d3-b456-426614174000',
    '123e4567-e89b-52d3-8456-426614174000',
    '123e4567-e89b-62d3-8456-426614174000',
    '01890f5a-4c96-7a2b-8c3d-123456789abc',
    '123e4567-e89b-82d3-8456-426614174000',
];
$invalidUuids = [
    '00000000-0000-0000-0000-000000000000',
    'ffffffff-ffff-ffff-ffff-ffffffffffff',
    '123e4567-e89b-02d3-8456-426614174000',
    '123e4567-e89b-92d3-8456-426614174000',
    '123e4567-e89b-42d3-7456-426614174000',
    '123e4567-e89b-42d3-c456-426614174000',
    '123E4567-E89B-42D3-8456-426614174000',
    '123e4567e89b42d38456426614174000',
    '{123e4567-e89b-42d3-8456-426614174000}',
    'urn:uuid:123e4567-e89b-42d3-8456-426614174000',
    '%31' . '23e4567-e89b-42d3-8456-426614174000',
];
$ulid = '01arz3ndektsv4rrffq69g5fav';
$ulidMatch = $router->match(new Request('POST', '/events/' . $ulid));

foreach ($validUuids as $uuid) {
    $uuidMatch = $router->match(new Request('GET', '/accounts/' . $uuid));

    if (
        $uuidMatch?->pathParameters->uuid('account_id') !== $uuid
        || $router->allowedMethodsForPath('/accounts/' . $uuid) !== ['GET']
    ) {
        throw new RuntimeException('Installed UUID routing did not accept every canonical version and RFC variant.');
    }
}

foreach ($invalidUuids as $uuid) {
    if (
        $router->match(new Request('GET', '/accounts/' . $uuid)) !== null
        || $router->allowedMethodsForPath('/accounts/' . $uuid) !== []
    ) {
        throw new RuntimeException('Installed UUID routing accepted an invalid or alternate representation.');
    }
}

if (
    $ulidMatch?->pathParameters->ulid('event_id') !== $ulid
    || $router->match(new Request('POST', '/events/' . strtoupper($ulid))) !== null
    || $router->allowedMethodsForPath('/events/' . $ulid) !== ['POST']
) {
    throw new RuntimeException('Installed UUID and ULID routing did not preserve the canonical contract.');
}

fwrite(STDOUT, "PASS installed UUID and ULID routing\n");
PHP,
    );

    try {
        $result = runProcess([PHP_BINARY, $proofPath], $project, $environment);
        requireSuccess($result, 'The installed framework failed UUID and ULID routing proof.');
        requireOutputContains($result, 'PASS installed UUID and ULID routing');
    } finally {
        if (is_file($proofPath) && !unlink($proofPath)) {
            throw new RuntimeException('Unable to remove the installed routing proof.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDatabaseContextConnectionConsistency(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $contextPath = $project . '/.ai/data.md';
    $originalContext = file_get_contents($contextPath);

    if (!is_string($originalContext)) {
        throw new RuntimeException('Unable to read the consumer database context control.');
    }

    /** @var array<string, string> $connectionSources */
    $connectionSources = [
        $project . '/DatabaseContextOrdinaryControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final class DatabaseContextOrdinaryControl
{
    public static function connect(): Connection
    {
        return Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1));
    }
}
PHP,
        $project . '/DatabaseContextAliasControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection as DatabaseConnectionAlias;
use PHPThis\Database\QueryBudget as DatabaseQueryBudgetAlias;
use PHPThis\Database\QueryTrace as DatabaseQueryTraceAlias;

final class DatabaseContextAliasControl
{
    public static function connect(): DatabaseConnectionAlias
    {
        return DatabaseConnectionAlias::connect(
            'sqlite::memory:',
            new DatabaseQueryBudgetAlias(1),
            new DatabaseQueryTraceAlias(1),
        );
    }
}
PHP,
        $project . '/DatabaseContextGroupedControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\{
    Connection as GroupedDatabaseConnection,
    QueryBudget as GroupedDatabaseQueryBudget,
    QueryTrace as GroupedDatabaseQueryTrace,
};

final class DatabaseContextGroupedControl
{
    public static function connect(): GroupedDatabaseConnection
    {
        return GroupedDatabaseConnection::connect(
            'sqlite::memory:',
            new GroupedDatabaseQueryBudget(1),
            new GroupedDatabaseQueryTrace(1),
        );
    }
}
PHP,
        $project . '/DatabaseContextNamespaceAliasControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database as DB;

final class DatabaseContextNamespaceAliasControl
{
    public static function connect(): DB\Connection
    {
        return DB\Connection::connect(
            'sqlite::memory:',
            new DB\QueryBudget(1),
            new DB\QueryTrace(1),
        );
    }
}
PHP,
        $project . '/DatabaseContextNamespaceImportControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database;

final class DatabaseContextNamespaceImportControl
{
    public static function connect(): Database\Connection
    {
        return Database\Connection::connect(
            'sqlite::memory:',
            new Database\QueryBudget(1),
            new Database\QueryTrace(1),
        );
    }
}
PHP,
        $project . '/DatabaseContextCurrentNamespaceControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace PHPThis;

final class DatabaseContextCurrentNamespaceControl
{
    public static function connect(): Database\Connection
    {
        return Database\Connection::connect(
            'sqlite::memory:',
            new Database\QueryBudget(1),
            new Database\QueryTrace(1),
        );
    }
}
PHP,
        $project . '/DatabaseContextFullyQualifiedControl.php' => <<<'PHP'
<?php

declare(strict_types=1);

final class DatabaseContextFullyQualifiedControl
{
    public static function connect(): \PHPThis\Database\Connection
    {
        return \PHPThis\Database\Connection::connect(
            'sqlite::memory:',
            new \PHPThis\Database\QueryBudget(1),
            new \PHPThis\Database\QueryTrace(1),
        );
    }
}
PHP,
    ];
    $documentationPath = $project . '/DatabaseContextDocumentationControl.php';
    $diagnostic = 'Application data context declares no database while application-owned PHP calls PHPThis\\Database\\Connection::connect; replace the not-applicable declaration with the explicit database contract.';
    $notApplicableContext = <<<'MD'
# Application data contract

`NOT_APPLICABLE(DATABASE)`

The installed structural control currently declares no database.
MD;
    $legacyNotApplicableLine = '`NOT_APPLICABLE`: the starter has no database, persisted resource, or CRUD-shaped behavior. It therefore has no SQL, structural selectors, bounded data lists, database identities or privileges, migrations, CRUD resource identifiers or item/collection routes, pagination, create identity or conflicts, `PUT`/`PATCH` or concurrency policy, missing-resource semantics, deletion or retention policy, resource authorization, or audit events.';
    $ordinaryPath = array_key_first($connectionSources);

    if (!is_string($ordinaryPath)) {
        throw new RuntimeException('The database context controls are empty.');
    }

    try {
        writeFile($contextPath, $notApplicableContext);

        foreach ($connectionSources as $sourcePath => $source) {
            writeFile($sourcePath, $source);
            $result = runProcess($profileCommand, $project, $environment);

            if ($sourcePath === $ordinaryPath) {
                requireExactFailureLines(
                    $result,
                    ['FAIL ' . $diagnostic],
                    'The isolated database-context diagnostic changed.',
                );
            } else {
                requireFailure(
                    $result,
                    basename($sourcePath) . ' passed while the application data context declared no database.',
                );
                requireOutputContains($result, $diagnostic);
            }

            if (!unlink($sourcePath)) {
                throw new RuntimeException("Unable to remove database context control {$sourcePath}.");
            }
        }

        writeFile($ordinaryPath, $connectionSources[$ordinaryPath]);
        writeFile(
            $contextPath,
            "# Application data contract\r\n\r\n`NOT_APPLICABLE(DATABASE)`\r\n\r\nThe installed structural control currently declares no database.\r\n",
        );
        $crlfResult = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $crlfResult,
            'CRLF database context bypassed the not-applicable Connection::connect check.',
        );
        requireOutputContains($crlfResult, $diagnostic);

        writeFile(
            $contextPath,
            "# Application data contract\n\n{$legacyNotApplicableLine}\n",
        );
        $legacyMarkerResult = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $legacyMarkerResult,
            'The legacy starter no-database declaration bypassed the Connection::connect check.',
        );
        requireOutputContains($legacyMarkerResult, $diagnostic);

        /** @var array<string, string> $nonDeclarationContexts */
        $nonDeclarationContexts = [
            'an unmatched leading backtick' => "# Application data contract\n\n`NOT_APPLICABLE(DATABASE)\n",
            'an unmatched trailing backtick' => "# Application data contract\n\nNOT_APPLICABLE(DATABASE)`\n",
            'legacy text quoted inside adopted prose' => installedSyntheticDatabaseContext()
                . "\nThe replaced starter declaration was quoted as: {$legacyNotApplicableLine}\n",
        ];

        foreach ($nonDeclarationContexts as $label => $nonDeclarationContext) {
            writeFile($contextPath, $nonDeclarationContext);
            $nonDeclarationResult = runProcess($profileCommand, $project, $environment);
            requireSuccess(
                $nonDeclarationResult,
                "Database context with {$label} was mistaken for a no-database declaration.",
            );
        }

        if (!unlink($ordinaryPath)) {
            throw new RuntimeException('Unable to remove the CRLF database context control.');
        }

        writeFile(
            $documentationPath,
            <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;

// Documentation only: \PHPThis\Database\Connection::connect(...)
final class DatabaseContextDocumentationControl
{
    private const CONNECTION_TYPE = Connection::class;

    private const EXAMPLE = 'PHPThis\\Database\\Connection::connect';

    public static function example(): string
    {
        return self::CONNECTION_TYPE . ':' . self::EXAMPLE;
    }
}
PHP,
        );
        $documentationResult = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $documentationResult,
            'A comment or string mentioning Connection::connect was mistaken for executable database use.',
        );

        if (!unlink($documentationPath)) {
            throw new RuntimeException('Unable to remove the database context documentation control.');
        }

        foreach ($connectionSources as $sourcePath => $source) {
            writeFile($sourcePath, $source);
        }

        writeFile($contextPath, installedSyntheticDatabaseContext());
        $adoptedContextResult = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $adoptedContextResult,
            'Canonical Connection::connect forms failed with an adopted synthetic SQLite data context.',
        );

        fwrite(STDOUT, "PASS installed database-context connection consistency\n");
    } finally {
        writeFile($contextPath, $originalContext);

        foreach ([...array_keys($connectionSources), $documentationPath] as $sourcePath) {
            if (is_file($sourcePath) && !unlink($sourcePath)) {
                throw new RuntimeException("Unable to remove database context control {$sourcePath}.");
            }
        }
    }
}

function installedSyntheticDatabaseContext(): string
{
    return <<<'MD'
# Installed synthetic SQLite data contract

- Connection and engine: proof-only in-memory SQLite through `pdo_sqlite`; no persistent or shared database is contacted.
- Schema definition source: no persistent schema or migration is adopted; the executable proof statement is the code-owned constant `SELECT 1 AS configured`.
- Structural namespace/control model: SQLite's default `main` attachment namespace exists only inside each in-memory proof connection; this is structural context, not live namespace ownership or authority evidence.
- Runtime operation and capability: the synthetic configuration proof may connect and execute only its named constant `SELECT 1 AS configured` statement.
- Elevated path: the separately composed synthetic migration-profile connection proves typed configuration delivery only; it performs no DDL, identity-management, authority-management, or administrative action and never falls back to runtime configuration.
- Authority evidence: installed static checking and isolated synthetic execution prove only the recorded code and process separation. They do not inspect or prove any engine's effective-authority resolution, activation or deactivation, production identity isolation, or deployment order; no live authority probe runs.
MD;
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledTypedConfiguration(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $boundaryPath = $project . '/installed-configuration-boundary.php';
    $runtimePath = $project . '/installed-runtime-entrypoint.php';
    $migrationPath = $project . '/installed-migration-entrypoint.php';
    $contextPath = $project . '/.ai/configuration.md';
    $dataContextPath = $project . '/.ai/data.md';
    $originalContext = file_get_contents($contextPath);
    $originalDataContext = file_get_contents($dataContextPath);

    if (!is_string($originalContext) || !is_string($originalDataContext)) {
        throw new RuntimeException('Unable to read the installed configuration and data context proof.');
    }

    writeFile(
        $boundaryPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final readonly class InstalledRuntimeDatabaseConfiguration
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final readonly class InstalledMigrationDatabaseConfiguration
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final class InstalledApplicationEnvironment
{
    public static function forHttp(): InstalledRuntimeDatabaseConfiguration
    {
        return new InstalledRuntimeDatabaseConfiguration(
            self::dsn(\getenv('PHPTHIS_PROOF_RUNTIME_DATABASE_DSN')),
            self::username(\getenv('PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME')),
            self::password(\getenv('PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD')),
        );
    }

    public static function forMigrations(): InstalledMigrationDatabaseConfiguration
    {
        return new InstalledMigrationDatabaseConfiguration(
            self::dsn(\getenv('PHPTHIS_PROOF_MIGRATION_DATABASE_DSN')),
            self::username(\getenv('PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME')),
            self::password(\getenv('PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD')),
        );
    }

    private static function dsn(string|false $value): string
    {
        if (
            $value === false
            || $value === ''
            || strlen($value) > 128
            || !str_starts_with($value, 'sqlite:')
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function username(string|false $value): string
    {
        if (
            $value === false
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function password(#[\SensitiveParameter] string|false $value): string
    {
        if ($value === false || $value === '' || strlen($value) > 64) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }
}

final class InstalledConnectionRecordingSeam
{
    private static int $calls = 0;

    public static function recordAndDelegateToInstalledConnection(
        string $dsn,
        QueryBudget $queryBudget,
        QueryTrace $queryTrace,
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $expectedDsn,
        string $expectedUsername,
        #[\SensitiveParameter]
        string $expectedPassword,
    ): Connection {
        self::$calls++;

        if (
            $dsn !== $expectedDsn
            || $username !== $expectedUsername
            || $password !== $expectedPassword
        ) {
            throw new RuntimeException('Installed configuration delivery changed.');
        }

        return Connection::connect(
            $dsn,
            $queryBudget,
            $queryTrace,
            $username,
            $password,
        );
    }

    public static function calls(): int
    {
        return self::$calls;
    }
}

final class InstalledConfigurationContractProof
{
    public static function assertSensitiveParametersAndReadonlyTypes(): void
    {
        if (
            !(new ReflectionClass(InstalledRuntimeDatabaseConfiguration::class))->isReadOnly()
            || !(new ReflectionClass(InstalledMigrationDatabaseConfiguration::class))->isReadOnly()
        ) {
            throw new RuntimeException('Installed application configuration must be readonly.');
        }

        $expected = [
            InstalledRuntimeDatabaseConfiguration::class . '::__construct' => ['password'],
            InstalledMigrationDatabaseConfiguration::class . '::__construct' => ['password'],
            InstalledApplicationEnvironment::class . '::password' => ['value'],
            InstalledConnectionRecordingSeam::class . '::recordAndDelegateToInstalledConnection' => [
                'password',
                'expectedPassword',
            ],
            Connection::class . '::connect' => ['password'],
        ];

        foreach ($expected as $method => $expectedNames) {
            [$class, $methodName] = explode('::', $method, 2);
            $actualNames = [];

            foreach ((new ReflectionMethod($class, $methodName))->getParameters() as $parameter) {
                if ($parameter->getAttributes(SensitiveParameter::class) !== []) {
                    $actualNames[] = $parameter->getName();
                }
            }

            if ($actualNames !== $expectedNames) {
                throw new RuntimeException('Installed sensitive-parameter contract changed.');
            }
        }
    }
}
PHP,
    );
    writeFile(
        $runtimePath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/installed-configuration-boundary.php';

$recordDelivery = ($argv[1] ?? '') === 'record';

try {
    $configuration = InstalledApplicationEnvironment::forHttp();
    InstalledConfigurationContractProof::assertSensitiveParametersAndReadonlyTypes();

    if ($recordDelivery) {
        if (!isset($argv[2], $argv[3], $argv[4])) {
            throw new RuntimeException('Installed configuration recording evidence is incomplete.');
        }

        $connection = InstalledConnectionRecordingSeam::recordAndDelegateToInstalledConnection(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
            $argv[2],
            $argv[3],
            $argv[4],
        );

        if (InstalledConnectionRecordingSeam::calls() !== 1) {
            throw new RuntimeException('Installed runtime configuration recording count changed.');
        }
    } else {
        $connection = Connection::connect(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
        );
    }

    if ($connection->selectOneRow('SELECT 1 AS configured') !== ['configured' => 1]) {
        throw new RuntimeException('Installed runtime configuration did not reach the visible connection boundary.');
    }

    fwrite(
        STDOUT,
        $recordDelivery
            ? "PASS installed runtime typed configuration delivery\n"
            : "PASS installed runtime typed configuration\n",
    );
} catch (InvalidArgumentException) {
    if (InstalledConnectionRecordingSeam::calls() !== 0) {
        fwrite(STDERR, "INFRASTRUCTURE_BOUNDARY_REACHED\n");
        exit(3);
    }

    fwrite(STDERR, "CONFIGURATION_INVALID\n");
    exit(2);
}
PHP,
    );
    writeFile(
        $migrationPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/installed-configuration-boundary.php';

$recordDelivery = ($argv[1] ?? '') === 'record';

try {
    $configuration = InstalledApplicationEnvironment::forMigrations();
    InstalledConfigurationContractProof::assertSensitiveParametersAndReadonlyTypes();

    if ($recordDelivery) {
        if (!isset($argv[2], $argv[3], $argv[4])) {
            throw new RuntimeException('Installed configuration recording evidence is incomplete.');
        }

        $connection = InstalledConnectionRecordingSeam::recordAndDelegateToInstalledConnection(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
            $argv[2],
            $argv[3],
            $argv[4],
        );

        if (InstalledConnectionRecordingSeam::calls() !== 1) {
            throw new RuntimeException('Installed migration configuration recording count changed.');
        }
    } else {
        $connection = Connection::connect(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
        );
    }

    if ($connection->selectOneRow('SELECT 1 AS configured') !== ['configured' => 1]) {
        throw new RuntimeException('Installed migration configuration did not reach the visible connection boundary.');
    }

    fwrite(
        STDOUT,
        $recordDelivery
            ? "PASS installed migration typed configuration delivery\n"
            : "PASS installed migration typed configuration\n",
    );
} catch (InvalidArgumentException) {
    if (InstalledConnectionRecordingSeam::calls() !== 0) {
        fwrite(STDERR, "INFRASTRUCTURE_BOUNDARY_REACHED\n");
        exit(3);
    }

    fwrite(STDERR, "CONFIGURATION_INVALID\n");
    exit(2);
}
PHP,
    );
    writeFile(
        $contextPath,
        <<<'MD'
# Application configuration context

- Boundary: `installed-configuration-boundary.php` is the only process-environment reader; `installed-runtime-entrypoint.php` and `installed-migration-entrypoint.php` are separate executable composition roots.
- Runtime input `PHPTHIS_PROOF_RUNTIME_DATABASE_DSN`: required with no default or fallback; non-empty, at most 128 bytes, and begins exactly with `sqlite:`.
- Runtime input `PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME`: required with no default or fallback; 1 to 64 lowercase ASCII bytes matching `[a-z][a-z0-9-]{0,63}`.
- Runtime input `PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD`: required with no default or fallback; opaque and 1 to 64 bytes.
- Migration input `PHPTHIS_PROOF_MIGRATION_DATABASE_DSN`: required with no default or fallback; non-empty, at most 128 bytes, and begins exactly with `sqlite:`.
- Migration input `PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME`: required with no default or fallback; 1 to 64 lowercase ASCII bytes matching `[a-z][a-z0-9-]{0,63}`.
- Migration input `PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD`: required with no default or fallback; opaque and 1 to 64 bytes.
- Factories and types: `InstalledApplicationEnvironment::forHttp()` returns `InstalledRuntimeDatabaseConfiguration`; `InstalledApplicationEnvironment::forMigrations()` returns `InstalledMigrationDatabaseConfiguration`; both values are final readonly objects.
- Injection: each entrypoint visibly passes its concrete process-specific DSN, username, and password to the installed `Connection::connect`; proof-only recording mode records the same exact arguments before delegating to that installed connection.
- Authority: the HTTP/runtime entrypoint reads only runtime inputs and never falls back to migration authority; the migration entrypoint reads only migration inputs and never falls back to runtime authority.
- Failure: after source and autoload loading, every missing, empty, malformed, or oversized input fails before the proof-only recording seam, installed connection, or query with exact exit `2`, empty stdout, and `CONFIGURATION_INVALID` on stderr.
- Rotation and reload: deployment supplies fresh values to each newly started process; this proof records no in-process reload or hidden refresh behavior.
- Redaction: passwords and raw password validation are sensitive parameters; exact process output contains no input names or values, DSNs, usernames, passwords, exception text, or traces.
- Evidence: child-process tests execute both real entrypoint files, exact delivery through the installed connection, accepted bounds, every validation branch, poisoned opposite-authority inputs, per-field no-fallback controls, zero infrastructure calls on rejection, sensitivity reflection, exact redacted bytes, a real query, and the installed public checker.
MD,
    );
    writeFile($dataContextPath, installedSyntheticDatabaseContext());

    $configurationNames = [
        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD',
    ];
    $cleanEnvironment = environmentWithout($environment, $configurationNames);
    $runtimeDatabaseValues = [
        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite::memory:',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'runtime-user',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => 'runtime-synthetic-password',
    ];
    $migrationDatabaseValues = [
        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite::memory:',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'migration-user',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => 'migration-synthetic-password',
    ];
    $runtimeDeliveryValues = [
        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite:file:runtime-recording?mode=memory&cache=private',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'runtime-recorder',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => 'runtime-recording-password',
    ];
    $migrationDeliveryValues = [
        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite:file:migration-recording?mode=memory&cache=private',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'migration-recorder',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => 'migration-recording-password',
    ];
    $runtimeRecordingCommand = [
        PHP_BINARY,
        $runtimePath,
        'record',
        $runtimeDeliveryValues['PHPTHIS_PROOF_RUNTIME_DATABASE_DSN'],
        $runtimeDeliveryValues['PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME'],
        $runtimeDeliveryValues['PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD'],
    ];
    $migrationRecordingCommand = [
        PHP_BINARY,
        $migrationPath,
        'record',
        $migrationDeliveryValues['PHPTHIS_PROOF_MIGRATION_DATABASE_DSN'],
        $migrationDeliveryValues['PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME'],
        $migrationDeliveryValues['PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD'],
    ];
    $maximumDsn = 'sqlite:file:' . str_repeat('d', 90) . '?mode=memory&cache=private';

    try {
        $runtimeResult = runProcess(
            [PHP_BINARY, $runtimePath],
            $project,
            [...$cleanEnvironment, ...$runtimeDatabaseValues],
        );
        requireExactProcessResult(
            $runtimeResult,
            0,
            "PASS installed runtime typed configuration\n",
            '',
            'Runtime typed configuration failed without migration credentials.',
        );

        $migrationResult = runProcess(
            [PHP_BINARY, $migrationPath],
            $project,
            [...$cleanEnvironment, ...$migrationDatabaseValues],
        );
        requireExactProcessResult(
            $migrationResult,
            0,
            "PASS installed migration typed configuration\n",
            '',
            'Migration typed configuration failed without runtime credentials.',
        );

        foreach (
            [
                'runtime minimum credential bounds' => [
                    $runtimePath,
                    [
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite::memory:',
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'a',
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => 'p',
                    ],
                    "PASS installed runtime typed configuration\n",
                ],
                'runtime maximum credential bounds' => [
                    $runtimePath,
                    [
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => $maximumDsn,
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => str_repeat('u', 64),
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('p', 64),
                    ],
                    "PASS installed runtime typed configuration\n",
                ],
                'migration minimum credential bounds' => [
                    $migrationPath,
                    [
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite::memory:',
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'a',
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => 'p',
                    ],
                    "PASS installed migration typed configuration\n",
                ],
                'migration maximum credential bounds' => [
                    $migrationPath,
                    [
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => $maximumDsn,
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => str_repeat('u', 64),
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => str_repeat('p', 64),
                    ],
                    "PASS installed migration typed configuration\n",
                ],
            ] as $label => [$entrypoint, $values, $expectedStdout]
        ) {
            $boundaryResult = runProcess(
                [PHP_BINARY, $entrypoint],
                $project,
                [...$cleanEnvironment, ...$values],
            );
            requireExactProcessResult(
                $boundaryResult,
                0,
                $expectedStdout,
                '',
                "Installed configuration rejected {$label}.",
            );
        }

        $runtimeDeliveryResult = runProcess(
            $runtimeRecordingCommand,
            $project,
            [...$cleanEnvironment, ...$runtimeDeliveryValues],
        );
        requireExactProcessResult(
            $runtimeDeliveryResult,
            0,
            "PASS installed runtime typed configuration delivery\n",
            '',
            'Runtime configuration did not deliver the exact DSN, username, and password.',
        );

        $migrationDeliveryResult = runProcess(
            $migrationRecordingCommand,
            $project,
            [...$cleanEnvironment, ...$migrationDeliveryValues],
        );
        requireExactProcessResult(
            $migrationDeliveryResult,
            0,
            "PASS installed migration typed configuration delivery\n",
            '',
            'Migration configuration did not deliver the exact DSN, username, and password.',
        );

        $runtimeWithPoisonedMigrationResult = runProcess(
            [PHP_BINARY, $runtimePath],
            $project,
            [
                ...$cleanEnvironment,
                ...$runtimeDatabaseValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'not-a-migration-dsn',
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'INVALID MIGRATION USER',
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => str_repeat('migration-secret-', 8),
            ],
        );
        requireExactProcessResult(
            $runtimeWithPoisonedMigrationResult,
            0,
            "PASS installed runtime typed configuration\n",
            '',
            'Runtime entrypoint read or validated migration credentials.',
        );

        $migrationWithPoisonedRuntimeResult = runProcess(
            [PHP_BINARY, $migrationPath],
            $project,
            [
                ...$cleanEnvironment,
                ...$migrationDatabaseValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'not-a-runtime-dsn',
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'INVALID RUNTIME USER',
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('runtime-secret-', 8),
            ],
        );
        requireExactProcessResult(
            $migrationWithPoisonedRuntimeResult,
            0,
            "PASS installed migration typed configuration\n",
            '',
            'Migration entrypoint read or validated runtime credentials.',
        );

        foreach (array_keys($runtimeDeliveryValues) as $omittedName) {
            $runtimeWithoutOneCredential = [
                ...$cleanEnvironment,
                ...$runtimeDeliveryValues,
                ...$migrationDeliveryValues,
            ];
            unset($runtimeWithoutOneCredential[$omittedName]);
            $runtimeNoFallbackResult = runProcess(
                $runtimeRecordingCommand,
                $project,
                $runtimeWithoutOneCredential,
            );
            requireExactProcessResult(
                $runtimeNoFallbackResult,
                2,
                '',
                "CONFIGURATION_INVALID\n",
                "Runtime configuration unexpectedly fell back for {$omittedName}.",
            );
        }

        foreach (array_keys($migrationDeliveryValues) as $omittedName) {
            $migrationWithoutOneCredential = [
                ...$cleanEnvironment,
                ...$runtimeDeliveryValues,
                ...$migrationDeliveryValues,
            ];
            unset($migrationWithoutOneCredential[$omittedName]);
            $migrationNoFallbackResult = runProcess(
                $migrationRecordingCommand,
                $project,
                $migrationWithoutOneCredential,
            );
            requireExactProcessResult(
                $migrationNoFallbackResult,
                2,
                '',
                "CONFIGURATION_INVALID\n",
                "Migration configuration unexpectedly fell back for {$omittedName}.",
            );
        }

        $runtimeInvalidCases = [
            'empty runtime DSN' => environmentWithEmptyValue(
                $runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
            ),
            'malformed runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'empty runtime username' => environmentWithEmptyValue(
                $runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
            ),
            'malformed runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'INVALID RUNTIME USER',
            ],
            'oversized runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty runtime password' => environmentWithEmptyValue(
                $runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
            ),
            'oversized runtime password' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('p', 65),
            ],
        ];
        $migrationInvalidCases = [
            'empty migration DSN' => environmentWithEmptyValue(
                $migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN',
            ),
            'malformed migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'empty migration username' => environmentWithEmptyValue(
                $migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME',
            ),
            'malformed migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'INVALID MIGRATION USER',
            ],
            'oversized migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty migration password' => environmentWithEmptyValue(
                $migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD',
            ),
            'oversized migration password' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => str_repeat('p', 65),
            ],
        ];

        foreach (
            [
                'runtime' => [$runtimeRecordingCommand, $runtimeInvalidCases],
                'migration' => [$migrationRecordingCommand, $migrationInvalidCases],
            ] as $process => [$recordingCommand, $invalidCases]
        ) {
            foreach ($invalidCases as $label => $invalidValues) {
                $invalidResult = runProcess(
                    $recordingCommand,
                    $project,
                    [...$cleanEnvironment, ...$invalidValues],
                );
                requireExactProcessResult(
                    $invalidResult,
                    2,
                    '',
                    "CONFIGURATION_INVALID\n",
                    "{$process} {$label} did not fail before infrastructure with exact redacted output.",
                );
            }
        }

        $profileResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($profileResult, 'Canonical one-file configuration failed the installed profile.');
    } finally {
        writeFile($contextPath, $originalContext);
        writeFile($dataContextPath, $originalDataContext);

        foreach ([$boundaryPath, $runtimePath, $migrationPath] as $proofPath) {
            if (is_file($proofPath) && !unlink($proofPath)) {
                throw new RuntimeException("Unable to remove installed configuration proof {$proofPath}.");
            }
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledConfigurationEvidenceReference(
    string $project,
    string $installedFramework,
    array $profileCommand,
    array $environment,
): void {
    $guidePath = $installedFramework . '/docs/configuration.md';
    $guide = file_get_contents($guidePath);

    if (!is_string($guide)) {
        throw new RuntimeException('Unable to read the installed configuration evidence guide.');
    }

    $headingMarker = '### Copyable child-process configuration evidence';
    $markerOffset = strpos($guide, $headingMarker);

    if ($markerOffset === false) {
        throw new RuntimeException('The installed configuration evidence reference is missing.');
    }

    $blockMarker = "\n```php\n";
    $blockOffset = strpos($guide, $blockMarker, $markerOffset + strlen($headingMarker));

    if ($blockOffset === false) {
        throw new RuntimeException('The installed configuration evidence PHP block is missing.');
    }

    $sourceOffset = $blockOffset + strlen($blockMarker);
    $sourceEnd = strpos($guide, "\n```", $sourceOffset);

    if ($sourceEnd === false) {
        throw new RuntimeException('The installed configuration evidence reference is incomplete.');
    }

    $referenceSource = substr($guide, $sourceOffset, $sourceEnd - $sourceOffset);

    if ($referenceSource === '') {
        throw new RuntimeException('The installed configuration evidence reference is empty.');
    }

    $referencePath = $project . '/tests/configuration-child-process-reference.php';
    $fixtureDirectory = $project . '/tests/fixtures';
    $entrypointPath = $fixtureDirectory . '/runtime-configuration-entrypoint.php';
    $emptyEntrypointPath = $fixtureDirectory . '/empty-configuration-entrypoint.php';
    $boundaryPath = $project . '/configuration-reference-boundary.php';
    $contextPath = $project . '/.ai/configuration.md';
    $originalContext = file_get_contents($contextPath);

    if (!is_string($originalContext)) {
        throw new RuntimeException('Unable to read the installed configuration context.');
    }

    $createdFixtureDirectory = false;

    try {
        if (!is_dir($fixtureDirectory)) {
            if (!mkdir($fixtureDirectory, 0700)) {
                throw new RuntimeException('Unable to create the installed configuration evidence fixture directory.');
            }

            $createdFixtureDirectory = true;
        }

        writeFile(
            $boundaryPath,
            <<<'PHP'
<?php

declare(strict_types=1);

final class ReferenceEmptyRuntimeMode extends InvalidArgumentException
{
}

final readonly class ReferenceRuntimeConfiguration
{
    public function __construct(
        public string $mode,
        public string $endpoint,
        #[\SensitiveParameter]
        public string $credential,
    ) {
    }
}

final class ReferenceApplicationEnvironment
{
    public static function forHttp(): ReferenceRuntimeConfiguration
    {
        return new ReferenceRuntimeConfiguration(
            self::mode(\getenv('APP_RUNTIME_MODE')),
            self::endpoint(\getenv('APP_RUNTIME_ENDPOINT')),
            self::credential(\getenv('APP_RUNTIME_CREDENTIAL')),
        );
    }

    private static function mode(string|false $value): string
    {
        if ($value === '') {
            throw new ReferenceEmptyRuntimeMode('Required application configuration is invalid.');
        }

        if ($value !== 'synthetic') {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function endpoint(string|false $value): string
    {
        if (
            $value === false
            || $value === ''
            || strlen($value) > 128
            || !str_starts_with($value, 'https://')
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function credential(#[\SensitiveParameter] string|false $value): string
    {
        if ($value === false || $value === '' || strlen($value) > 64) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }
}
PHP,
        );
        writeFile(
            $entrypointPath,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/configuration-reference-boundary.php';

try {
    ReferenceApplicationEnvironment::forHttp();
    fwrite(STDOUT, "CONFIGURATION_OK\n");
} catch (InvalidArgumentException) {
    fwrite(STDERR, "CONFIGURATION_INVALID\n");
    exit(2);
}
PHP,
        );
        writeFile(
            $emptyEntrypointPath,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/configuration-reference-boundary.php';

try {
    ReferenceApplicationEnvironment::forHttp();
} catch (ReferenceEmptyRuntimeMode) {
    fwrite(STDOUT, "PASS installed empty configuration delivery\n");
    exit(0);
} catch (InvalidArgumentException) {
    fwrite(STDERR, "EMPTY_CONFIGURATION_NOT_DELIVERED\n");
    exit(2);
}

fwrite(STDERR, "EMPTY_CONFIGURATION_NOT_DELIVERED\n");
exit(2);
PHP,
        );
        writeFile($referencePath, $referenceSource . "\n");
        writeFile(
            $contextPath,
            <<<'MD'
# Installed configuration evidence reference context

- Boundary: `configuration-reference-boundary.php` is the only process-environment reader.
- Inputs: `APP_RUNTIME_MODE`, `APP_RUNTIME_ENDPOINT`, and `APP_RUNTIME_CREDENTIAL` are required without defaults or fallback; values are never recorded here.
- Factory and type: `ReferenceApplicationEnvironment::forHttp()` returns the final readonly `ReferenceRuntimeConfiguration` before application-controlled I/O.
- Authority: this proof adopts one runtime parser only; worker, migration, and administrative profiles are not applicable.
- Injection: configuration-only scope is selected, so infrastructure composition is deferred.
- Failure: missing, empty, malformed, and oversized inputs produce exact exit `2`, empty stdout, and `CONFIGURATION_INVALID` on stderr before infrastructure or business I/O.
- Rotation: every evidence invocation is a fresh process; no hidden reload behavior is adopted.
- Redaction: the public reference asserts exact stream bytes and explicit absence of one supplied synthetic sentinel.
- Evidence: the exact PHP block extracted from installed `docs/configuration.md` passes the installed maximum-level profile and executes the intentionally short-lived, tiny-fixed-output parser fixture in fresh child processes with an explicit synthetic application environment and no null inheritance; a focused probe separately invokes the matching factory and proves that the raw `NAME=` form reaches its exact empty-value validation branch, while a paired run with the mode omitted proves that missing remains distinct; a hard timeout remains caller- or CI-owned and is not established by this harness.
MD,
        );

        $cleanEnvironment = environmentWithout(
            $environment,
            ['APP_RUNTIME_MODE', 'APP_RUNTIME_ENDPOINT', 'APP_RUNTIME_CREDENTIAL'],
        );
        $profileResult = runProcess($profileCommand, $project, $cleanEnvironment);
        requireSuccess(
            $profileResult,
            'The installed public configuration evidence reference failed the maximum-level profile.',
        );

        $emptyDeliveryResult = runProcess(
            [PHP_BINARY, $emptyEntrypointPath],
            $project,
            [
                '' => 'APP_RUNTIME_MODE=',
                'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
                'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
            ],
        );
        requireExactProcessResult(
            $emptyDeliveryResult,
            0,
            "PASS installed empty configuration delivery\n",
            '',
            'The installed empty configuration environment entry was not delivered as empty.',
        );

        $missingDeliveryResult = runProcess(
            [PHP_BINARY, $emptyEntrypointPath],
            $project,
            [
                'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
                'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
            ],
        );
        requireExactProcessResult(
            $missingDeliveryResult,
            2,
            '',
            "EMPTY_CONFIGURATION_NOT_DELIVERED\n",
            'The installed missing runtime mode was misclassified as empty.',
        );

        $referenceResult = runProcess(
            [PHP_BINARY, $referencePath],
            $project,
            $cleanEnvironment,
        );
        requireExactProcessResult(
            $referenceResult,
            0,
            "PASS child-process configuration evidence\n",
            '',
            'The installed public configuration evidence reference changed behavior.',
        );
        requireOutputNotContains(
            $referenceResult,
            'synthetic-rejected-value-must-not-appear',
        );
    } finally {
        writeFile($contextPath, $originalContext);

        foreach ([$referencePath, $entrypointPath, $emptyEntrypointPath, $boundaryPath] as $proofPath) {
            if (is_file($proofPath) && !unlink($proofPath)) {
                throw new RuntimeException("Unable to remove installed configuration evidence proof {$proofPath}.");
            }
        }

        if ($createdFixtureDirectory && is_dir($fixtureDirectory) && !rmdir($fixtureDirectory)) {
            throw new RuntimeException('Unable to remove the installed configuration evidence fixture directory.');
        }
    }
}

/** @param array<string, string> $environment */
function proveInstalledRequestHandlerDecorator(string $project, array $environment): string
{
    $proofPath = $project . '/installed-handler-decorator-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require __DIR__ . '/vendor/autoload.php';

final class InstalledDecoratorTrace
{
    /** @var list<string> */
    private array $steps = [];

    private int $downstreamCalls = 0;

    private ?int $decoratorRequestId = null;

    private ?int $downstreamRequestId = null;

    public function recordBefore(Request $request): void
    {
        $this->steps[] = 'before';
        $this->decoratorRequestId = spl_object_id($request);
    }

    public function recordAfter(): void
    {
        $this->steps[] = 'after';
    }

    public function recordHandler(Request $request): void
    {
        $this->steps[] = 'handler';
        $this->downstreamCalls++;
        $this->downstreamRequestId = spl_object_id($request);
    }

    /** @return list<string> */
    public function steps(): array
    {
        return $this->steps;
    }

    public function downstreamCalls(): int
    {
        return $this->downstreamCalls;
    }

    public function decoratorRequestId(): ?int
    {
        return $this->decoratorRequestId;
    }

    public function downstreamRequestId(): ?int
    {
        return $this->downstreamRequestId;
    }
}

final readonly class InstalledHeaderDecorator implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private InstalledDecoratorTrace $trace,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordBefore($request);
        $response = $this->downstream->handle($request);
        $this->trace->recordAfter();

        return new Response(
            $response->status,
            [...$response->headers, 'X-Decorator-Proof' => 'present'],
            $response->body,
            $response->cookies,
            $response->fileBody,
        );
    }
}

final readonly class InstalledRejectingDecorator implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private bool $reject,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->reject) {
            return new Response(429, ['Cache-Control' => 'no-store'], "Rejected\n");
        }

        return $this->downstream->handle($request);
    }
}

final readonly class InstalledDecoratedHandler implements RequestHandler
{
    public function __construct(private InstalledDecoratorTrace $trace)
    {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordHandler($request);

        return new Response(200, ['Cache-Control' => 'no-store'], "Decorated\n");
    }
}

function assertInstalledDecoratedResponse(
    Response $response,
    InstalledDecoratorTrace $trace,
): void {
    if (
        $response->status !== 200
        || $response->headers !== [
            'Cache-Control' => 'no-store',
            'X-Decorator-Proof' => 'present',
        ]
        || $response->body !== "Decorated\n"
        || $trace->steps() !== ['before', 'handler', 'after']
        || $trace->downstreamCalls() !== 1
        || $trace->decoratorRequestId() === null
        || $trace->decoratorRequestId() !== $trace->downstreamRequestId()
    ) {
        throw new RuntimeException('Installed route decorator did not preserve explicit composition.');
    }
}

function assertInstalledDecoratorRejection(
    Response $response,
    InstalledDecoratorTrace $trace,
): void {
    if ($response->status !== 429 || $trace->downstreamCalls() !== 1) {
        throw new RuntimeException('Installed rejecting decorator entered downstream work.');
    }
}

function assertInstalledDecoratorIsolation(InstalledDecoratorTrace $trace): void
{
    if (
        $trace->downstreamCalls() !== 1
        || $trace->steps() !== ['before', 'handler', 'after']
    ) {
        throw new RuntimeException('Route miss or method rejection entered decorated work.');
    }
}

$trace = new InstalledDecoratorTrace();
$application = new Application(new Router([
    new Route(
        'GET',
        '/decorated',
        new InstalledHeaderDecorator(
            new InstalledDecoratedHandler($trace),
            $trace,
        ),
    ),
    new Route(
        'GET',
        '/rejected',
        new InstalledRejectingDecorator(
            new InstalledDecoratedHandler($trace),
            true,
        ),
    ),
    new Route('GET', '/plain', new InstalledDecoratedHandler($trace)),
]));
$request = new Request('GET', '/decorated');
$response = $application->handle($request);
assertInstalledDecoratedResponse($response, $trace);

$rejectedResponse = $application->handle(new Request('GET', '/rejected'));
assertInstalledDecoratorRejection($rejectedResponse, $trace);

$application->handle(new Request('POST', '/decorated'));
$application->handle(new Request('GET', '/missing'));
assertInstalledDecoratorIsolation($trace);

fwrite(STDOUT, "PASS installed request-handler decorator composition\n");
PHP,
    );

    $result = runProcess([PHP_BINARY, $proofPath], $project, $environment);
    requireSuccess($result, 'The installed framework failed request-handler decorator proof.');
    requireOutputContains($result, 'PASS installed request-handler decorator composition');

    return $proofPath;
}

/**
 * @param array<string, string> $overrides
 * @return array<string, string>
 */
function processEnvironment(array $overrides): array
{
    $environment = getenv();

    foreach ($overrides as $name => $value) {
        $environment[$name] = $value;
    }

    return $environment;
}

/**
 * @param array<string, string> $environment
 * @param list<string> $names
 * @return array<string, string>
 */
function environmentWithout(array $environment, array $names): array
{
    foreach ($names as $name) {
        unset($environment[$name]);
    }

    return $environment;
}

/**
 * @param array<string, string> $environment
 * @return array<string, string>
 */
function environmentWithEmptyValue(array $environment, string $name): array
{
    unset($environment[$name]);
    $environment[''] = $name . '=';

    return $environment;
}

function composerBinary(string $root): string
{
    $configured = getenv('COMPOSER_BINARY');

    if (is_string($configured) && $configured !== '') {
        $resolved = realpath($configured);

        if (is_string($resolved) && is_file($resolved)) {
            return $resolved;
        }

        return $configured;
    }

    $localPhar = $root . '/composer.phar';

    if (is_file($localPhar)) {
        return $localPhar;
    }

    throw new RuntimeException('COMPOSER_BINARY is unavailable; run this proof through Composer.');
}

/**
 * @param list<string> $arguments
 * @return list<string>
 */
function composerCommand(string $binary, array $arguments): array
{
    $command = str_ends_with(strtolower($binary), '.phar') ? [PHP_BINARY, $binary] : [$binary];

    return [...$command, ...$arguments];
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runProcess(array $command, string $workingDirectory, array $environment): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory,
        $environment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start process: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read process output.');
    }

    return [
        'exit_code' => $exitCode >= 0 ? $exitCode : 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireExactProcessResult(
    array $result,
    int $exitCode,
    string $stdout,
    string $stderr,
    string $message,
): void {
    if (
        $result['exit_code'] !== $exitCode
        || $result['stdout'] !== $stdout
        || $result['stderr'] !== $stderr
    ) {
        throw new RuntimeException($message);
    }
}

/**
 * @param array{exit_code: int, stdout: string, stderr: string} $result
 * @param list<string> $expected
 */
function requireExactFailureLines(
    array $result,
    array $expected,
    string $message,
): void {
    requireExactProcessResult(
        $result,
        1,
        '',
        implode("\n", $expected) . "\n",
        $message,
    );
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireSuccess(array $result, string $message): void
{
    if ($result['exit_code'] !== 0) {
        throw new RuntimeException($message . "\n" . $result['stderr'] . $result['stdout']);
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireFailure(array $result, string $message): void
{
    if ($result['exit_code'] === 0) {
        throw new RuntimeException($message . "\n" . $result['stdout']);
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireOutputContains(array $result, string $expected): void
{
    if (!str_contains($result['stdout'] . $result['stderr'], $expected)) {
        throw new RuntimeException("Expected process output to contain: {$expected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireOutputNotContains(array $result, string $unexpected): void
{
    if (str_contains($result['stdout'] . $result['stderr'], $unexpected)) {
        throw new RuntimeException("Expected process output not to contain: {$unexpected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireStdoutContains(array $result, string $expected): void
{
    if (!str_contains($result['stdout'], $expected)) {
        throw new RuntimeException("Expected process stdout to contain: {$expected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireStdoutNotContains(array $result, string $unexpected): void
{
    if (str_contains($result['stdout'], $unexpected)) {
        throw new RuntimeException("Expected process stdout not to contain: {$unexpected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function advisoryOutput(array $result): string
{
    $lines = preg_split('/\R/', $result['stdout']);

    if (!is_array($lines)) {
        throw new RuntimeException('Unable to split checker advisory output.');
    }

    return implode(
        "\n",
        array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, 'ADVISORY'),
        )),
    );
}

/** @return list<string> */
function expectedArchiveFiles(string $root): array
{
    $manifestPath = $root . '/tools/package-files.txt';
    $files = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($files) || $files === []) {
        throw new RuntimeException('The framework package inventory manifest is empty or unreadable.');
    }

    foreach ($files as $file) {
        if ($file === '' || str_starts_with($file, '/') || !is_file($root . '/' . $file)) {
            throw new RuntimeException("Invalid framework package inventory entry: {$file}");
        }
    }

    sort($files, SORT_STRING);

    if (count($files) !== count(array_unique($files))) {
        throw new RuntimeException('The framework package inventory contains a duplicate path.');
    }

    return $files;
}

/**
 * @param list<string> $expectedFiles
 * @param array<string, string> $environment
 */
function verifyExportPolicies(
    string $root,
    string $workspace,
    array $expectedFiles,
    array $environment,
): void {
    $composer = jsonFile($root . '/composer.json');
    $archive = $composer['archive'] ?? null;
    $composerExclusions = is_array($archive) ? ($archive['exclude'] ?? null) : null;

    if (!is_array($composerExclusions) || !array_is_list($composerExclusions)) {
        throw new RuntimeException('composer.json must define a list of archive exclusions.');
    }

    foreach ($composerExclusions as $exclusion) {
        if (!is_string($exclusion)) {
            throw new RuntimeException('Composer archive exclusions must be strings.');
        }
    }

    $attributeLines = file($root . '/.gitattributes', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($attributeLines)) {
        throw new RuntimeException('Unable to read .gitattributes export policy.');
    }

    $attributeExclusions = [];

    foreach ($attributeLines as $line) {
        $matches = [];

        if (preg_match('/\A(\/\S+) export-ignore\z/', $line, $matches) !== 1) {
            throw new RuntimeException("Unexpected .gitattributes release-policy line: {$line}");
        }

        $attributeExclusions[] = $matches[1];
    }

    sort($composerExclusions, SORT_STRING);
    sort($attributeExclusions, SORT_STRING);

    if ($composerExclusions !== $attributeExclusions) {
        throw new RuntimeException('Composer and Git export exclusions must remain identical.');
    }

    $status = runProcess(
        ['git', 'status', '--porcelain', '--untracked-files=all'],
        $root,
        $environment,
    );
    requireSuccess($status, 'Unable to determine whether the Git export can be verified.');

    if (trim($status['stdout']) !== '') {
        return;
    }

    $gitArchivePath = $workspace . '/git-export.tar';
    $gitArchive = runProcess(
        [
            'git',
            'archive',
            '--format=tar',
            '--worktree-attributes',
            '--output=' . $gitArchivePath,
            'HEAD',
        ],
        $root,
        $environment,
    );
    requireSuccess($gitArchive, 'Git release-archive creation failed.');

    $gitFiles = archiveFiles($gitArchivePath);

    if ($gitFiles !== $expectedFiles) {
        throw new RuntimeException(inventoryDifference($expectedFiles, $gitFiles));
    }
}

/** @return list<string> */
function archiveFiles(string $archivePath): array
{
    $resolvedArchivePath = realpath($archivePath);

    if (!is_string($resolvedArchivePath)) {
        throw new RuntimeException('Unable to resolve the package archive.');
    }

    $archive = new PharData($resolvedArchivePath);
    $prefix = 'phar://' . $resolvedArchivePath . '/';
    $files = [];
    $iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();

        if (!str_starts_with($path, $prefix)) {
            throw new RuntimeException('Unable to resolve a package-archive entry.');
        }

        $files[] = substr($path, strlen($prefix));
    }

    sort($files, SORT_STRING);

    return $files;
}

/** @return list<string> */
function directoryFiles(string $root, string $prefix = ''): array
{
    if (!is_dir($root)) {
        throw new RuntimeException("Required directory is missing: {$root}");
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($root) + 1);
        $files[] = $prefix . str_replace('\\', '/', $relativePath);
    }

    sort($files, SORT_STRING);

    return $files;
}

/**
 * @param list<string> $expected
 * @param list<string> $actual
 */
function inventoryDifference(array $expected, array $actual): string
{
    $missing = array_values(array_diff($expected, $actual));
    $unexpected = array_values(array_diff($actual, $expected));

    return sprintf(
        "Framework archive inventory changed.\nMissing: %s\nUnexpected: %s",
        $missing === [] ? 'none' : implode(', ', $missing),
        $unexpected === [] ? 'none' : implode(', ', $unexpected),
    );
}

function configureIsolatedConsumer(string $root, string $project, string $archivePath): void
{
    $composerPath = $project . '/composer.json';
    $composer = jsonFile($composerPath);
    $rootComposer = jsonFile($root . '/composer.json');
    $phpstanVersion = lockedVersion($root, 'phpstan/phpstan');
    $strictRulesVersion = lockedVersion($root, 'phpstan/phpstan-strict-rules');
    $frameworkVersion = is_file($root . '/skeleton/composer.lock')
        ? lockedVersion($root . '/skeleton', 'phpthis/framework')
        : 'dev-main';
    $projectLock = $project . '/composer.lock';

    if (is_file($projectLock) && !unlink($projectLock)) {
        throw new RuntimeException('Unable to remove the copied skeleton lock for the local archive proof.');
    }

    $composer['repositories'] = [
        [
            'type' => 'package',
            'package' => [
                'name' => 'phpthis/framework',
                'version' => $frameworkVersion,
                'type' => 'library',
                'dist' => ['type' => 'tar', 'url' => 'file://' . $archivePath],
                'require' => $rootComposer['require'],
                'autoload' => $rootComposer['autoload'],
                'bin' => $rootComposer['bin'],
            ],
        ],
        pathRepository($root . '/vendor/phpstan/phpstan', 'phpstan/phpstan', $phpstanVersion),
        pathRepository(
            $root . '/vendor/phpstan/phpstan-strict-rules',
            'phpstan/phpstan-strict-rules',
            $strictRulesVersion,
        ),
        ['packagist.org' => false],
    ];

    writeJson($composerPath, $composer);
}

function verifySkeletonPublicationBoundary(string $root): void
{
    $composer = jsonFile($root . '/skeleton/composer.json');
    $require = $composer['require'] ?? null;
    $frameworkConstraint = is_array($require) ? ($require['phpthis/framework'] ?? null) : null;

    if (!is_string($frameworkConstraint) || $frameworkConstraint === '') {
        throw new RuntimeException('The skeleton must declare its framework constraint.');
    }

    if ($frameworkConstraint === 'dev-main') {
        $expectedBootstrapRepository = [[
            'type' => 'vcs',
            'url' => 'https://github.com/balgf/PHPThis.git',
        ]];

        if (($composer['repositories'] ?? null) !== $expectedBootstrapRepository) {
            throw new RuntimeException('The pre-alpha skeleton must use only the documented framework VCS bootstrap.');
        }

        return;
    }

    if (array_key_exists('repositories', $composer)) {
        throw new RuntimeException('A tagged skeleton must remove the pre-alpha framework VCS repository override.');
    }

    if (!is_file($root . '/skeleton/composer.lock')) {
        throw new RuntimeException('A tagged skeleton must commit its Composer lockfile.');
    }
}

/** @return array<array-key, mixed> */
function jsonFile(string $path): array
{
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        throw new RuntimeException("Unable to read JSON file {$path}.");
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException("JSON file {$path} must contain an object.");
    }

    return $decoded;
}

/** @param array<array-key, mixed> $contents */
function writeJson(string $path, array $contents): void
{
    $encoded = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (file_put_contents($path, $encoded . "\n", LOCK_EX) === false) {
        throw new RuntimeException("Unable to write JSON file {$path}.");
    }
}

/** @return array<string, mixed> */
function pathRepository(string $path, string $package, string $version): array
{
    return [
        'type' => 'path',
        'url' => $path,
        'options' => [
            'symlink' => false,
            'versions' => [$package => $version],
        ],
    ];
}

function lockedVersion(string $root, string $package): string
{
    $lock = jsonFile($root . '/composer.lock');

    foreach (['packages', 'packages-dev'] as $section) {
        $packages = $lock[$section] ?? null;

        if (!is_array($packages)) {
            continue;
        }

        foreach ($packages as $candidate) {
            if (
                is_array($candidate)
                && ($candidate['name'] ?? null) === $package
                && is_string($candidate['version'] ?? null)
            ) {
                return $candidate['version'];
            }
        }
    }

    throw new RuntimeException("Locked package is missing: {$package}");
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDuplicationAdvisoryIsReportOnly(
    string $project,
    string $composerBinary,
    array $profileCommand,
    array $environment,
): void {
    $firstPath = $project . '/.hidden/duplication/FirstDuplicationProof.php';
    $secondPath = $project . '/unconventional/duplication/SecondDuplicationProof.php';
    $frameworkPath = $project . '/vendor/phpthis/framework/duplication-negative-control.php';
    $dependencyPath = $project . '/vendor/dependency-negative-control/DuplicationProof.php';
    $vcsPath = $project . '/.git/duplication-negative-control.php';
    $largeAdvisoryPath = $project . '/unconventional/duplication/LargeAdvisory.php';
    $structuralFailurePath = $project . '/unconventional/duplication/StructuralFailure.php';
    $phpStanFailurePath = $project . '/unconventional/duplication/PhpStanFailure.php';
    $plain = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DuplicationProof;

final class FirstDuplicationProof
{
    public function calculate(): int
    {
        $total = 0;
        $canary = 'DUPLICATION_PRIVATE_CANARY_7b4f';
        $total += 101;
        $total += 102;
        $total += 103;
        $total += 104;
        $total += 105;
        $total += 106;
        $total += 107;
        $total += 108;
        $total += 109;
        $total += 110;
        $total += 111;
        $total += 112;

        return $total + strlen($canary);
    }
}
PHP;
    $decorated = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DuplicationProof;

final class SecondDuplicationProof
{
    public function calculate(): int
    {
        /* Formatting and comments are deliberately different. */
        $total=0;
        $canary =
            'DUPLICATION_PRIVATE_CANARY_7b4f';
        $total /* one */ += 101;
        $total += 102;
        $total += 103;
        $total += 104;
        $total += 105;
        $total += 106;
        $total += 107;
        $total += 108;
        $total += 109;
        $total += 110;
        $total += 111;
        $total += 112;

        return $total +
            strlen($canary);
    }
}
PHP;

    writeFile($firstPath, $plain . "\n");
    writeFile($secondPath, $decorated . "\n");
    writeFile($frameworkPath, $plain . "\n");
    writeFile($dependencyPath, $decorated . "\n");
    writeFile($vcsPath, $plain . "\n");

    try {
        $normal = runProcess($profileCommand, $project, $environment);
        requireSuccess($normal, 'A possible duplication advisory invalidated the consumer.');
        requireStdoutContains(
            $normal,
            'ADVISORY possible application duplication: 1 group (minimum 48 normalized tokens)',
        );
        requireStdoutContains($normal, 'application validity is unaffected');
        requireStdoutContains($normal, 'PASS PHPThis application check');
        $normalAdvisories = advisoryOutput($normal);

        if (
            $normalAdvisories
                !== 'ADVISORY possible application duplication: 1 group (minimum 48 normalized tokens); run `phpthis check --debug` for details; application validity is unaffected'
        ) {
            throw new RuntimeException('The installed normal duplication advisory was not exactly one concise line.');
        }

        foreach (
            [
                '.hidden/duplication/FirstDuplicationProof.php',
                'unconventional/duplication/SecondDuplicationProof.php',
                $project,
                'DUPLICATION_PRIVATE_CANARY_7b4f',
            ] as $privateNormalValue
        ) {
            requireOutputNotContains($normal, $privateNormalValue);
        }

        $debug = runProcess(
            [$project . '/vendor/bin/phpthis', 'check', '--debug'],
            $project,
            $environment,
        );
        requireSuccess($debug, 'The duplication diagnostic mode failed.');
        $advisories = advisoryOutput($debug);

        if (substr_count($advisories, 'ADVISORY duplication group ') !== 1) {
            throw new RuntimeException('The installed checker did not consolidate the copied block into one group.');
        }

        if (substr_count($advisories, 'ADVISORY duplication location 1.') !== 2) {
            throw new RuntimeException('The installed checker did not report exactly two application-owned locations.');
        }

        if (
            preg_match(
                '/^ADVISORY duplication group 1: [0-9]+ normalized tokens across 2 locations$/m',
                $advisories,
            ) !== 1
        ) {
            throw new RuntimeException('Duplication debug output omitted its bounded token and location counts.');
        }

        foreach (
            [
                '/^ADVISORY duplication location 1\.1: "\.hidden\/duplication\/FirstDuplicationProof\.php":[0-9]+(?:-[0-9]+)?$/m',
                '/^ADVISORY duplication location 1\.2: "unconventional\/duplication\/SecondDuplicationProof\.php":[0-9]+(?:-[0-9]+)?$/m',
            ] as $locationPattern
        ) {
            if (preg_match($locationPattern, $advisories) !== 1) {
                throw new RuntimeException('Duplication debug output omitted a bounded application-relative line range.');
            }
        }

        if (str_contains($advisories, $project)) {
            throw new RuntimeException('Duplication debug output disclosed the temporary project absolute path.');
        }

        foreach (
            [
                '".hidden/duplication/FirstDuplicationProof.php"',
                '"unconventional/duplication/SecondDuplicationProof.php"',
            ] as $relativeLocation
        ) {
            if (!str_contains($advisories, $relativeLocation)) {
                throw new RuntimeException("Duplication debug output omitted {$relativeLocation}.");
            }
        }

        foreach (
            [
                'vendor/phpthis/framework/duplication-negative-control.php',
                'vendor/dependency-negative-control/DuplicationProof.php',
                '.git/duplication-negative-control.php',
                'DUPLICATION_PRIVATE_CANARY_7b4f',
            ] as $excludedValue
        ) {
            if (str_contains($advisories, $excludedValue)) {
                throw new RuntimeException("Duplication advisory output disclosed excluded content: {$excludedValue}");
            }
        }

        $complete = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireSuccess($complete, 'A possible duplication advisory stopped the canonical consumer gate.');
        requireStdoutContains($complete, 'ADVISORY possible application duplication: 1 group');
        requireStdoutContains($complete, 'PASS application behavior and front controller');

        $largeAdvisory = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\DuplicationProof;\n\n/*"
            . str_repeat('bounded-advisory-padding-', 1_500)
            . "*/\nfinal class LargeAdvisory {}\n";
        writeFile($largeAdvisoryPath, $largeAdvisory);
        $incomplete = runProcess($profileCommand, $project, $environment);
        requireSuccess($incomplete, 'A bounded incomplete duplication scan invalidated the consumer.');
        requireStdoutContains($incomplete, 'found within an incomplete bounded scan');
        requireStdoutContains($incomplete, 'application validity is unaffected');
        requireStdoutContains($incomplete, 'PASS PHPThis application check');

        $largeStaticFailure = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\DuplicationProof;\n\nfinal class LargeAdvisory\n{\n    public function value(): int\n    {\n        return 'invalid';\n    }\n}\n\n/*"
            . str_repeat('bounded-advisory-padding-', 1_500)
            . "*/\n";
        writeFile($largeAdvisoryPath, $largeStaticFailure);
        $incompleteStaticFailure = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $incompleteStaticFailure,
            'A scanner-skipped oversized application file was also skipped by PHPStan.',
        );
        requireStdoutContains($incompleteStaticFailure, 'found within an incomplete bounded scan');
        requireOutputContains($incompleteStaticFailure, 'return.type');
        unlink($largeAdvisoryPath);

        writeFile($structuralFailurePath, "<?php\n\nclass StructuralFailure {}\n");
        $structuralFailure = runProcess($profileCommand, $project, $environment);
        requireFailure($structuralFailure, 'A duplication advisory masked a structural failure.');
        requireOutputContains($structuralFailure, 'PHT002 unconventional/duplication/StructuralFailure.php:3');
        requireOutputNotContains($structuralFailure, 'ADVISORY possible application duplication');
        unlink($structuralFailurePath);

        $phpStanFailure = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DuplicationProof;

final class PhpStanFailure
{
    public function value(): int
    {
        return 'invalid';
    }
}
PHP;
        writeFile($phpStanFailurePath, $phpStanFailure . "\n");
        $staticFailure = runProcess($profileCommand, $project, $environment);
        requireFailure($staticFailure, 'A duplication advisory masked a PHPStan failure.');
        requireStdoutContains($staticFailure, 'ADVISORY possible application duplication: 1 group');
        requireOutputContains($staticFailure, 'return.type');
        unlink($phpStanFailurePath);
    } finally {
        foreach (
            [
                $firstPath,
                $secondPath,
                $frameworkPath,
                $dependencyPath,
                $vcsPath,
                $largeAdvisoryPath,
                $structuralFailurePath,
                $phpStanFailurePath,
            ] as $path
        ) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (
            [
                $project . '/.hidden',
                $project . '/unconventional',
                $project . '/vendor/dependency-negative-control',
                $project . '/.git',
            ] as $directory
        ) {
            removeDirectory($directory);
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveObservabilityContextIsRequired(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/.ai/observability.md';
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read the consumer observability context control.');
    }

    if (!unlink($path)) {
        throw new RuntimeException('Unable to remove the consumer observability context control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A consumer without observability context unexpectedly passed.');
        requireOutputContains(
            $result,
            'Required application context file is missing: .ai/observability.md.',
        );
    } finally {
        writeFile($path, $contents);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveConfigurationContextIsRequired(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/.ai/configuration.md';
    $sourcePath = $project . '/ConfigurationContextControl.php';
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read the consumer configuration context control.');
    }

    if (!unlink($path)) {
        throw new RuntimeException('Unable to remove the consumer configuration context control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A consumer without configuration context unexpectedly passed.');
        requireOutputContains(
            $result,
            'Required application context file is missing: .ai/configuration.md.',
        );
    } finally {
        writeFile($path, $contents);
    }

    writeFile(
        $sourcePath,
        <<<'PHP'
<?php

declare(strict_types=1);

final readonly class ConfigurationContextValue
{
    public function __construct(public string $value)
    {
    }
}

final class ConfigurationContextControl
{
    public static function fromEnvironment(): ConfigurationContextValue
    {
        $value = \getenv('PHPTHIS_CONFIGURATION_CONTEXT_CONTROL');

        if (
            $value === false
            || preg_match('/\A[a-z][a-z0-9-]{0,15}\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return new ConfigurationContextValue($value);
    }
}

final readonly class ConfigurationContextConsumer
{
    public function __construct(private ConfigurationContextValue $configuration)
    {
    }

    public function configuredValue(): string
    {
        return $this->configuration->value;
    }
}

final class ConfigurationContextComposition
{
    public static function create(): ConfigurationContextConsumer
    {
        return new ConfigurationContextConsumer(
            ConfigurationContextControl::fromEnvironment(),
        );
    }
}
PHP,
    );

    try {
        $notApplicableResult = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $notApplicableResult,
            'Configuration environment access passed while the application context remained not applicable.',
        );
        requireOutputContains(
            $notApplicableResult,
            'Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
        );

        writeFile(
            $path,
            "# Application configuration context\r\n\r\n`NOT_APPLICABLE(CONFIGURATION)`\r\n",
        );
        $crlfNotApplicableResult = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $crlfNotApplicableResult,
            'CRLF configuration context bypassed the not-applicable environment-read check.',
        );
        requireOutputContains(
            $crlfNotApplicableResult,
            'Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
        );

        writeFile(
            $path,
            <<<'MD'
# Application configuration context

- Boundary: `ConfigurationContextControl.php` is the sole process-environment reader.
- Input `PHPTHIS_CONFIGURATION_CONTEXT_CONTROL`: required with no default or fallback; 1 to 16 lowercase ASCII bytes matching `[a-z][a-z0-9-]{0,15}`.
- Factory and type: `ConfigurationContextControl::fromEnvironment()` validates once and returns the final readonly `ConfigurationContextValue`.
- Injection: `ConfigurationContextComposition::create()` visibly calls the environment factory and supplies its concrete value to `ConfigurationContextConsumer::__construct`; the consumer does not receive an environment name or unvalidated scalar.
- Authority: this ordinary application-process input has no migration, administration, or cross-process credential fallback.
- Failure: missing or invalid input raises `InvalidArgumentException` before application-controlled I/O; this correlation fixture performs no I/O.
- Rotation and reload: a fresh process samples the deployment value once; no in-process reload or hidden refresh is claimed.
- Redaction: submitted values are absent from checker diagnostics and this fixture emits no configuration output.
- Evidence: the fixture contains the exact `ConfigurationContextComposition::create()` constructor-injection path, and the installed public checker correlates this complete context with the one canonical environment read while rejecting absent or `NOT_APPLICABLE(CONFIGURATION)` context, including CRLF form.
MD,
        );
        $completedContextResult = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $completedContextResult,
            'A completed configuration context failed the installed public checker.',
        );
    } finally {
        writeFile($path, $contents);

        if (is_file($sourcePath) && !unlink($sourcePath)) {
            throw new RuntimeException('Unable to remove the configuration context control.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveEveryApplicationDirectoryIsChecked(string $project, array $profileCommand, array $environment): void
{
    $paths = [
        'OpenRoot.php',
        'config/OpenConfig.php',
        'bin/OpenBin.php',
        'migrations/OpenMigration.php',
        '.hidden/OpenHidden.php',
        'tmp/OpenTemporary.php',
    ];
    $source = "<?php\n\ndeclare(strict_types=1);\n\nclass OpenClass {}\n";

    foreach ($paths as $relativePath) {
        writeFile($project . '/' . $relativePath, $source);
    }

    $extensionlessPath = 'bin/OpenConsole';
    writeFile($project . '/' . $extensionlessPath, "#!/usr/bin/env php\n" . $source);
    $unsupportedExtensionPath = 'config/OpenInclude.inc';
    writeFile(
        $project . '/' . $unsupportedExtensionPath,
        "<?php\n\ndeclare(strict_types=1);\n\nfinal class IncludeClass {}\n",
    );

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT002 files outside conventional roots unexpectedly passed.');

        foreach ($paths as $relativePath) {
            requireOutputContains($result, "PHT002 {$relativePath}:5");
        }

        requireOutputContains($result, "PHT002 {$extensionlessPath}:6");
        requireOutputContains(
            $result,
            "{$unsupportedExtensionPath} contains PHP source but must use the .php extension",
        );
    } finally {
        foreach ($paths as $relativePath) {
            unlink($project . '/' . $relativePath);
        }

        unlink($project . '/' . $extensionlessPath);
        unlink($project . '/' . $unsupportedExtensionPath);

        foreach (['config', 'bin', 'migrations', '.hidden', 'tmp'] as $directory) {
            rmdir($project . '/' . $directory);
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveValidExtensionlessExecutableIsChecked(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/bin/HealthCommand';
    $source = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace App;

final class HealthCommand
{
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireSuccess($result, 'A valid extensionless PHP executable was rejected.');
        requireOutputContains($result, 'PASS application guardrails: 13 PHP files');
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveMagicMethodsAreRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/src/MagicMethods.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class MagicMethods
{
    public function /* comment */ __isset(string $name): bool
    {
        return $name !== '';
    }

    public function &__get(string $name): mixed
    {
        $value = $name;

        return $value;
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Obscured magic methods unexpectedly passed.');
        requireOutputContains($result, 'defines forbidden magic method __isset');
        requireOutputContains($result, 'defines forbidden magic method __get');
    } finally {
        unlink($path);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveEvalMethodsAreAllowedAndLanguageConstructIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $methodPath = $project . '/src/EvalMethodControl.php';
    $methodSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class InstanceEvalMethodControl
{
    public function /* declaration comment */ EvAl(string $value): string
    {
        return $value;
    }
}

final class StaticEvalMethodControl
{
    public static function EVAL(string $value): string
    {
        return $value;
    }
}

/** @return array{string, ?string, string} */
function evalMethodControl(?InstanceEvalMethodControl $optional): array
{
    $instance = new InstanceEvalMethodControl();

    return [
        $instance -> /* instance comment */ EvAl('instance'),
        $optional ?-> /* nullsafe comment */ EvAl('nullsafe'),
        StaticEvalMethodControl :: /* static comment */ EVAL('static'),
    ];
}
PHP;
    $constructPath = $project . '/src/EvalLanguageConstructControl.php';
    $constructSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class EvalLanguageConstructControl
{
    public function run(string $source): mixed
    {
        return EVAL /* construct comment */ ($source);
    }
}
PHP;
    writeFile($methodPath, $methodSource . "\n");

    try {
        $methodResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($methodResult, 'Legal method declarations or calls named eval unexpectedly failed.');
        requireOutputContains($methodResult, 'PASS PHPThis application check');

        writeFile($constructPath, $constructSource . "\n");
        $constructResult = runProcess($profileCommand, $project, $environment);
        requireFailure($constructResult, 'The eval language construct unexpectedly passed.');
        requireOutputContains(
            $constructResult,
            'src/EvalLanguageConstructControl.php:11 uses eval.',
        );
    } finally {
        if (is_file($constructPath)) {
            unlink($constructPath);
        }

        if (is_file($methodPath)) {
            unlink($methodPath);
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDependencyDirectoryIsExcluded(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/vendor/dependency-negative-control/OpenDependencyClass.php';
    writeFile($path, "<?php\n\nclass OpenDependencyClass {}\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireSuccess($result, 'Dependency-owned PHP was incorrectly treated as application source.');
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveMixedCoercionIsRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/unconventional/MixedCoercion.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class MixedCoercion
{
    public function convert(mixed $value): int
    {
        return (int) $value;
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT001 mixed coercion unexpectedly passed.');
        requireOutputContains($result, 'phpthis.pht001');
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDirectPdoConstructionIsRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/src/DirectPdo.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDO as Driver;

final class DirectPdo
{
    public function direct(): PDO
    {
        return new PDO('sqlite::memory:');
    }

    public function aliased(): Driver
    {
        return new Driver('sqlite::memory:');
    }

    public function fullyQualified(): \PDO
    {
        return new \PDO('sqlite::memory:');
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT005 direct PDO construction unexpectedly passed.');

        if (substr_count($result['stdout'] . $result['stderr'], 'phpthis.pht005') !== 3) {
            throw new RuntimeException('Expected literal, aliased, and fully qualified PDO to emit PHT005.');
        }
    } finally {
        unlink($path);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveNativeSessionAccessIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/src/DirectSession.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use function session_destroy as destroy_session;

final class DirectSession
{
    public function start(): void
    {
        session_start();
        destroy_session();
        call_user_func('session_write_close');
        $_SESSION['identity_id'] = 1;
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Direct native session access unexpectedly passed.');
        requireOutputContains($result, 'calls native session function session_start');
        requireOutputContains($result, 'imports native session function session_destroy');
        requireOutputContains($result, 'references native session function session_write_close indirectly');
        requireOutputContains($result, 'reads a PHP superglobal outside PHPThis\\Session\\SessionLifecycle');
    } finally {
        unlink($path);
    }

    $frontControllerPath = $project . '/public/index.php';
    $originalFrontController = file_get_contents($frontControllerPath);

    if (!is_string($originalFrontController)) {
        throw new RuntimeException('Unable to read the consumer front controller session control.');
    }

    $frontControllerSource = <<<'PHP'
<?php

declare(strict_types=1);

session_start();
$_SESSION['identity_id'] = 1;
PHP;
    writeFile($frontControllerPath, $frontControllerSource . "\n");

    try {
        $frontControllerResult = runProcess($profileCommand, $project, $environment);
        requireFailure($frontControllerResult, 'Native session access in public/index.php unexpectedly passed.');
        requireOutputContains($frontControllerResult, 'calls native session function session_start');
        requireOutputContains(
            $frontControllerResult,
            'public/index.php:6 reads a PHP superglobal outside PHPThis\\Session\\SessionLifecycle',
        );
    } finally {
        if (file_put_contents($frontControllerPath, $originalFrontController, LOCK_EX) !== strlen($originalFrontController)) {
            throw new RuntimeException('Unable to restore the consumer front controller session control.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveEnvironmentAccessIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $firstPath = $project . '/src/EnvironmentOne.php';
    $secondPath = $project . '/src/EnvironmentTwo.php';
    $boundarySource = static fn (string $class, string $key): string => sprintf(
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class %s
{
    public static function read(): string|false
    {
        return \getenv('%s');
    }
}
PHP,
        $class,
        $key,
    );
    writeFile($firstPath, $boundarySource('EnvironmentOne', 'APP_FIRST_VALUE') . "\n");
    writeFile($secondPath, $boundarySource('EnvironmentTwo', 'APP_SECOND_VALUE') . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT007 process-environment reads in two files unexpectedly passed.');
        requireExactFailureLines(
            $result,
            [
                'FAIL PHT007 src/EnvironmentOne.php:11 reads process environment in more than one application-owned PHP file; centralize every \getenv call in one configuration boundary.',
                'FAIL PHT007 src/EnvironmentTwo.php:11 reads process environment in more than one application-owned PHP file; centralize every \getenv call in one configuration boundary.',
                'FAIL Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
            ],
            'Installed PHT007 scattered-boundary diagnostics changed.',
        );
    } finally {
        unlink($firstPath);
        unlink($secondPath);
    }

    $invalidPath = $project . '/src/InvalidEnvironmentAccess.php';
    $invalidSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use function getenv as importedGetenv;
use function putenv;

$key = 'APP_KEY';
getenv('APP_KEY');
\GeTeNv('APP_KEY');
App\getenv('APP_KEY');
\getenv();
\getenv($key);
\getenv('APP_KEY', true);
\getenv(name: 'APP_KEY');
\getenv(...['APP_KEY']);
$fromEnvironment = $_ENV['APP_KEY'];
$fromServer = $_SERVER['APP_KEY'];
$filtered = filter_input(INPUT_ENV, 'APP_KEY');
\putenv('APP_KEY=value');
\apache_getenv('APP_KEY');
\apache_setenv('APP_KEY', 'value');
$reader = "get\x65nv";
$reader('APP_KEY');
$filteredIndirect = filter_input(constant("INPUT_\x45NV"), 'APP_KEY');
$directLiteral = ('getenv')('APP_KEY');
$mapped = array_map('getenv', ['APP_KEY']);
$namedMapped = array_map(callback: 'getenv', arrays: ['APP_KEY']);
$reduced = array_reduce([], 'getenv');
register_shutdown_function('putenv', 'APP_KEY=value');
$called = call_user_func(('apache_getenv'), 'APP_KEY');
$closure = \Closure::fromCallable('getenv');
$namedInput = filter_input(constant(name: 'INPUT_ENV'), 'APP_KEY');
$parenthesizedInput = filter_input(constant(('INPUT_ENV')), 'APP_KEY');
$harmless = 'getenv';
PHP;
    writeFile($invalidPath, $invalidSource . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT007 alternate environment access unexpectedly passed.');
        requireExactFailureLines(
            $result,
            [
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:7 imports environment function getenv; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:8 imports environment function putenv; use direct \getenv calls only.',
                "FAIL PHT007 src/InvalidEnvironmentAccess.php:11 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
                "FAIL PHT007 src/InvalidEnvironmentAccess.php:12 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
                "FAIL PHT007 src/InvalidEnvironmentAccess.php:13 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:14 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:15 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:16 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:17 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:18 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:19 reads $_ENV; read exact keys with \getenv in the single application configuration boundary.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:20 indexes $_SERVER; pass the HTTP transport array unchanged or read configuration with \getenv in the single configuration boundary.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:21 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:22 calls environment function putenv; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:23 calls environment function apache_getenv; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:24 calls environment function apache_setenv; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:25 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:27 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:28 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:29 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:30 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:31 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:32 references environment function putenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:33 references environment function apache_getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:34 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:35 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:36 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
                'FAIL src/InvalidEnvironmentAccess.php:20 reads a PHP superglobal outside public/index.php.',
                'FAIL Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
            ],
            'Installed PHT007 alternate-access diagnostics changed.',
        );
    } finally {
        unlink($invalidPath);
    }

    $frontControllerPath = $project . '/public/index.php';
    $frontController = file_get_contents($frontControllerPath);

    if (!is_string($frontController)) {
        throw new RuntimeException('Unable to read the installed front-controller environment control.');
    }

    writeFile(
        $frontControllerPath,
        <<<'PHP'
<?php

declare(strict_types=1);

$server = $_SERVER;
Configuration::fromServer($_SERVER);
$configurationReader->handle($_SERVER, $_GET, $_POST, $_FILES);
PHP,
    );

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Bare front-controller $_SERVER aliases unexpectedly passed PHT007.');
        requireExactFailureLines(
            $result,
            [
                'FAIL PHT007 public/index.php:5 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
                'FAIL PHT007 public/index.php:6 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
                'FAIL PHT007 public/index.php:7 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
            ],
            'Installed PHT007 bare-server diagnostics changed.',
        );
    } finally {
        writeFile($frontControllerPath, $frontController);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDynamicSqlIsRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/src/DynamicSql.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PHPThis\Database\Connection;

final class DynamicSql
{
    public function run(Connection $connection, string $sql): void
    {
        $connection->selectAllRows($sql);
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT006 dynamic Connection SQL unexpectedly passed.');

        if (substr_count($result['stdout'] . $result['stderr'], 'phpthis.pht006') !== 1) {
            throw new RuntimeException('Expected dynamic Connection SQL to emit exactly one PHT006 finding.');
        }
    } finally {
        unlink($path);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveConfigurationCannotReplaceProfile(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/phpstan.neon';
    writeFile($path, "parameters:\n    level: 0\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A consumer PHPStan configuration unexpectedly replaced the installed profile.');
        requireOutputContains($result, 'PHT004');
    } finally {
        unlink($path);
    }

    $target = $project . '/alternate-analysis.neon';
    writeFile($target, "parameters:\n    level: 0\n");

    if (!symlink($target, $path)) {
        throw new RuntimeException('Unable to create the PHPStan configuration symlink control.');
    }

    try {
        $symlinkResult = runProcess($profileCommand, $project, $environment);
        requireFailure($symlinkResult, 'A symlinked consumer PHPStan configuration unexpectedly passed.');
        requireOutputContains($symlinkResult, 'PHT004 phpstan.neon is forbidden');
    } finally {
        unlink($path);
        unlink($target);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveBaselinesAndInlineIgnoresAreRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    foreach (
        ['phpstan.project.neon', 'phpstanLocal.neon', 'phpstan-baseline.neon.dist', 'phpstanbaseline.php']
        as $basename
    ) {
        $configuration = $project . '/' . $basename;
        writeFile($configuration, "parameters:\n    ignoreErrors: []\n");

        try {
            $configurationResult = runProcess($profileCommand, $project, $environment);
            requireFailure($configurationResult, "PHPStan artifact {$basename} unexpectedly passed.");
            requireOutputContains($configurationResult, "PHT004 {$basename} is forbidden");
        } finally {
            unlink($configuration);
        }
    }

    $ignoredPath = $project . '/src/IgnoredFinding.php';
    $ignoredSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

/** @phpstan-ignore class.name */
final class IgnoredFinding
{
    /** @phpstan-ignore-next-line */
    public function value(): int
    {
        // @phpstan-ignore-line
        return 1;
    }
}
PHP;
    writeFile($ignoredPath, $ignoredSource . "\n");

    try {
        $ignoreResult = runProcess($profileCommand, $project, $environment);
        requireFailure($ignoreResult, 'Inline PHPStan suppressions unexpectedly passed.');

        foreach ([7, 10, 13] as $line) {
            requireOutputContains($ignoreResult, "PHT004 src/IgnoredFinding.php:{$line}");
        }

        if (substr_count($ignoreResult['stdout'] . $ignoreResult['stderr'], 'PHT004') !== 3) {
            throw new RuntimeException('Expected every inline PHPStan suppression form to produce PHT004.');
        }
    } finally {
        unlink($ignoredPath);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveComposerGateCannotDrift(
    string $project,
    string $composerBinary,
    array $profileCommand,
    array $environment,
): void
{
    $composerPath = $project . '/composer.json';
    $original = file_get_contents($composerPath);

    if (!is_string($original)) {
        throw new RuntimeException('Unable to read the consumer Composer gate.');
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The consumer Composer scripts are missing.');
    }

    $scripts['profile'] = 'php -r "exit(0);"';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A weakened Composer profile command unexpectedly passed.');
        requireOutputContains($result, 'scripts.profile must be exactly `phpthis check`');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer Composer gate.');
        }
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['test'] = '';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $testResult = runProcess($profileCommand, $project, $environment);
        requireFailure($testResult, 'A missing application behavior-test command unexpectedly passed.');
        requireOutputContains($testResult, "scripts.test must execute the application's automated behavior tests");
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer behavior-test command.');
        }
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['check'] = ['@profile'];
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $checkResult = runProcess($profileCommand, $project, $environment);
        requireFailure($checkResult, 'A complete gate without the application behavior-test stage unexpectedly passed.');
        requireOutputContains($checkResult, 'scripts.check must be exactly [`@profile`, `@test`]');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the complete consumer gate.');
        }
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['test'] = 'php -r "fwrite(STDERR, \'PHPTHIS_BEHAVIOR_STAGE_FAILED\'); exit(23);"';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $behaviorFailureResult = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireFailure($behaviorFailureResult, 'A failing application behavior-test stage did not fail the complete gate.');
        requireOutputContains($behaviorFailureResult, 'PASS PHPThis application check');
        requireOutputContains($behaviorFailureResult, 'PHPTHIS_BEHAVIOR_STAGE_FAILED');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer behavior-test stage.');
        }
    }

    $checksDirectory = $project . '/checks';
    $originalRunner = $project . '/tests/run.php';
    $movedRunner = $checksDirectory . '/behavior.php';

    if (!mkdir($checksDirectory, 0700)) {
        throw new RuntimeException('Unable to create the alternate behavior-test directory.');
    }

    if (!rename($originalRunner, $movedRunner)) {
        throw new RuntimeException('Unable to move the behavior-test runner for the path-neutrality control.');
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['test'] = 'php checks/behavior.php';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $alternatePathResult = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireSuccess($alternatePathResult, 'An application-owned behavior-test path unexpectedly failed.');
        requireOutputContains($alternatePathResult, 'PASS application behavior and front controller');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer test path.');
        }

        if (!rename($movedRunner, $originalRunner)) {
            throw new RuntimeException('Unable to restore the original behavior-test runner.');
        }

        if (!rmdir($checksDirectory)) {
            throw new RuntimeException('Unable to remove the alternate behavior-test directory.');
        }
    }

    $composer = jsonFile($composerPath);
    $requireDev = $composer['require-dev'] ?? null;

    if (!is_array($requireDev)) {
        throw new RuntimeException('The consumer analysis dependencies are missing.');
    }

    $requireDev['phpstan/phpstan'] = '*';
    $composer['require-dev'] = $requireDev;
    writeJson($composerPath, $composer);

    try {
        $dependencyResult = runProcess($profileCommand, $project, $environment);
        requireFailure($dependencyResult, 'A floating PHPStan constraint unexpectedly passed.');
        requireOutputContains($dependencyResult, 'must require-dev phpstan/phpstan at `^2.1`');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer analysis dependencies.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveSymlinkedSourceIsRejected(
    string $workspace,
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $outside = $workspace . '/outside-source';

    if (!mkdir($outside, 0700)) {
        throw new RuntimeException('Unable to create the symlink negative-control target.');
    }

    writeFile($outside . '/External.php', "<?php\n\ndeclare(strict_types=1);\n");
    $link = $project . '/linked-source';

    if (!symlink($outside, $link)) {
        throw new RuntimeException('Unable to create the symlink negative control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A symlinked source directory unexpectedly passed.');
        requireOutputContains($result, 'linked-source is a symlink directory');
    } finally {
        unlink($link);
        removeDirectory($outside);
    }

    $outsideExecutable = $workspace . '/outside-command';
    writeFile(
        $outsideExecutable,
        "#!/usr/bin/env php\n<?php\n\ndeclare(strict_types=1);\n\nnamespace External;\n\nfinal class Command {}\n",
    );
    $binDirectory = $project . '/bin';

    if (!mkdir($binDirectory, 0700)) {
        throw new RuntimeException('Unable to create the executable symlink negative-control directory.');
    }

    $executableLink = $binDirectory . '/linked-command';

    if (!symlink($outsideExecutable, $executableLink)) {
        throw new RuntimeException('Unable to create the executable symlink negative control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A symlinked extensionless PHP executable unexpectedly passed.');
        requireOutputContains($result, 'bin/linked-command is a symlink file');
    } finally {
        unlink($executableLink);
        rmdir($binDirectory);
        unlink($outsideExecutable);
    }
}

function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory {$directory}.");
    }

    if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
        throw new RuntimeException("Unable to write file {$path}.");
    }
}

function copyDirectory(string $source, string $destination): void
{
    if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create directory {$destination}.");
    }

    $entries = scandir($source);

    if (!is_array($entries)) {
        throw new RuntimeException("Unable to read directory {$source}.");
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $sourcePath = $source . '/' . $entry;
        $destinationPath = $destination . '/' . $entry;

        if (is_dir($sourcePath) && !is_link($sourcePath)) {
            copyDirectory($sourcePath, $destinationPath);
            continue;
        }

        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Unable to copy {$sourcePath}.");
        }
    }
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory) || is_link($directory)) {
        if (is_link($directory)) {
            unlink($directory);
        }

        return;
    }

    $entries = scandir($directory);

    if (!is_array($entries)) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . '/' . $entry;

        if (is_dir($path) && !is_link($path)) {
            removeDirectory($path);
            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}
