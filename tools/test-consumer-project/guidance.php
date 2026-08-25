<?php

declare(strict_types=1);

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

    $commonRequiredFrameworkGuideOwners = [
        'docs/file-transfers/README.md' => '.ai/file-transfers.md',
        'docs/request-policy.md' => '.ai/request-policy.md',
        'docs/stateless-authentication.md' => '.ai/request-policy.md',
        'docs/cli.md' => '.ai/cli.md',
        'docs/coordination.md' => '.ai/operations.md',
        'docs/migrations.md' => '.ai/migrations.md',
    ];
    $skeletonRequiredFrameworkGuideOwners = [
        ...$commonRequiredFrameworkGuideOwners,
        'docs/jobs/README.md' => '.ai/jobs.md',
    ];
    $templateRequiredFrameworkGuideOwners = [
        ...$commonRequiredFrameworkGuideOwners,
        'docs/jobs.md' => '.ai/jobs.md',
        'docs/jobs/verification.md' => '.ai/jobs.md',
    ];
    $skeletonMarkdown = markdownFilesFromInventory($project, directoryFiles($root . '/skeleton'));
    $templateRoot = $installedFramework . '/templates/application';
    $templateMarkdown = markdownFilesFromInventory($templateRoot, directoryFiles($templateRoot));

    requireInstalledGuidanceReferences(
        'generated skeleton',
        $skeletonMarkdown,
        $project,
        $vendorDirectory,
        $skeletonRequiredFrameworkGuideOwners,
    );
    requireInstalledGuidanceReferences(
        'installed application template',
        $templateMarkdown,
        $templateRoot,
        $vendorDirectory,
        $templateRequiredFrameworkGuideOwners,
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
            $skeletonRequiredFrameworkGuideOwners,
            'does not resolve through the configured Composer vendor directory',
        );
    } finally {
        if (!rename($parkedTarget, $missingTarget)) {
            throw new RuntimeException('Unable to restore the missing installed-reference negative control.');
        }
    }

    $localTarget = $project . '/docs/cli.md';
    $localControl = $project . '/application-local-installed-reference-control.md';
    writeFile($localTarget, "# Incorrect local framework guide target\n");
    writeFile($localControl, "Read `docs/cli.md` before changing an application command.\n");

    try {
        requireInstalledGuidanceReferenceFailure(
            'generated skeleton',
            [...$skeletonMarkdown, $localControl],
            $project,
            $vendorDirectory,
            $skeletonRequiredFrameworkGuideOwners,
            'uses application-local framework guide docs/cli.md',
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
        $skeletonRequiredFrameworkGuideOwners,
        'docs/jobs/README.md',
        '.ai/jobs.md',
    );
    proveRoutedInstalledGuidanceOwnerFailure(
        'installed application template',
        $templateMarkdown,
        $templateRoot,
        $vendorDirectory,
        $templateRequiredFrameworkGuideOwners,
        'docs/jobs.md',
        '.ai/jobs.md',
    );

    $segmentEscapeReferences = [
        'parent' => 'vendor/../composer.json',
        'empty' => 'vendor//phpthis/framework/docs/jobs.md',
        'dot' => 'vendor/./phpthis/framework/docs/jobs.md',
    ];

    foreach ($segmentEscapeReferences as $controlName => $installedReference) {
        $escapeControl = $project . "/vendor-{$controlName}-installed-reference-control.md";
        writeFile($escapeControl, "Read `{$installedReference}` before changing dependencies.\n");

        try {
            requireInstalledGuidanceReferenceFailure(
                'generated skeleton',
                [...$skeletonMarkdown, $escapeControl],
                $project,
                $vendorDirectory,
                $skeletonRequiredFrameworkGuideOwners,
                'escapes the configured Composer vendor directory',
            );
        } finally {
            if (is_file($escapeControl) && !unlink($escapeControl)) {
                throw new RuntimeException('Unable to remove a vendor-directory segment escape control.');
            }
        }
    }

    $symlinkControl = $project . '/vendor-symlink-installed-reference-control.md';
    $symlinkReference = $vendorDirectory . '/outside-vendor-reference-control';

    if (file_exists($symlinkReference) || is_link($symlinkReference)) {
        throw new RuntimeException('The vendor-directory symlink escape control already exists.');
    }

    if (!symlink($project . '/composer.json', $symlinkReference)) {
        throw new RuntimeException('Unable to create the vendor-directory symlink escape control.');
    }

    try {
        writeFile(
            $symlinkControl,
            "Read `vendor/outside-vendor-reference-control` before changing dependencies.\n",
        );
        requireInstalledGuidanceReferenceFailure(
            'generated skeleton',
            [...$skeletonMarkdown, $symlinkControl],
            $project,
            $vendorDirectory,
            $skeletonRequiredFrameworkGuideOwners,
            'does not resolve through the configured Composer vendor directory',
        );
    } finally {
        if (is_file($symlinkControl) && !unlink($symlinkControl)) {
            throw new RuntimeException('Unable to remove the vendor-directory symlink Markdown control.');
        }

        if (is_link($symlinkReference) && !unlink($symlinkReference)) {
            throw new RuntimeException('Unable to remove the vendor-directory symlink escape control.');
        }
    }

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
            $dependencySegments = $dependencyPath === '' ? [] : explode('/', $dependencyPath);

            if (
                in_array('', $dependencySegments, true)
                || in_array('.', $dependencySegments, true)
                || in_array('..', $dependencySegments, true)
            ) {
                throw new RuntimeException(
                    "{$surface} installed reference {$installedReference} escapes the configured Composer "
                    . 'vendor directory through an empty or dot path segment.',
                );
            }

            $resolvedPath = $dependencyPath === ''
                ? $vendorDirectory
                : $vendorDirectory . '/' . $dependencyPath;
            $normalizedVendorDirectory = realpath($vendorDirectory);
            $normalizedResolvedPath = realpath($resolvedPath);

            if (
                !is_string($normalizedVendorDirectory)
                || !is_string($normalizedResolvedPath)
                || (
                    $normalizedResolvedPath !== $normalizedVendorDirectory
                    && !str_starts_with($normalizedResolvedPath, $normalizedVendorDirectory . '/')
                )
            ) {
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

    $expectedReferencePath = 'vendor/phpthis/framework/' . $requiredFrameworkGuide;
    $expectedReference = '`' . $expectedReferencePath . '`';
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
        $referenceRemainsElsewhere = false;

        foreach ($markdownFiles as $markdownFile) {
            $markdownContents = file_get_contents($markdownFile);

            if (!is_string($markdownContents)) {
                throw new RuntimeException(
                    "Unable to read {$surface} routed-owner control guidance file {$markdownFile}.",
                );
            }

            if (in_array(
                $expectedReferencePath,
                installedDependencyReferences($markdownContents, $markdownFile),
                true,
            )) {
                $referenceRemainsElsewhere = true;
                break;
            }
        }

        $expectedDiagnostic = $referenceRemainsElsewhere
            ? "routed owner {$routedOwner} is missing required installed framework guide {$requiredFrameworkGuide}"
            : "{$surface} context is missing required installed framework guide {$requiredFrameworkGuide}";
        requireInstalledGuidanceReferenceFailure(
            $surface,
            $markdownFiles,
            $surfaceRoot,
            $vendorDirectory,
            $requiredFrameworkGuideOwners,
            $expectedDiagnostic,
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
        'docs/decisions/015-explicit-native-session-lifecycle.md' => [
            'title' => 'ADR 015: Explicit native session lifecycle',
            'metadata' => "Superseded in part by [ADR 049](049-bounded-response-cookie-profile.md), which retains this decision's explicit typed-cookie and native-session lifecycle while replacing only its cookie validation, duplicate-name, prefix, size, expiration, and lifetime-wording subset.",
            'targets' => ['049-bounded-response-cookie-profile.md'],
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
        'docs/decisions/026-bounded-file-transfers.md' => [
            'title' => 'ADR 026: Bounded explicit file transfers',
            'metadata' => 'Superseded in part by [ADR 053](053-application-owned-amazon-s3-file-transfers.md), which replaces only the remote-object-store and pre-signed-delivery exclusion when an application explicitly selects `AMAZON_S3_ADR053`, and [ADR 060](060-reject-pending-output-before-response-emission.md), which replaces only the headers-only prior-output check before local-file emission.',
            'targets' => [
                '053-application-owned-amazon-s3-file-transfers.md',
                '060-reject-pending-output-before-response-emission.md',
            ],
        ],
        'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md' => [
            'title' => 'ADR 025: Application-owned explicit CLI and scheduler',
            'metadata' => "Superseded in part by [ADR 028](028-application-owned-redis-cache-and-schedule-lease.md), which replaces only the executable example's same-host schedule file lock with one application-owned Redis owner-token lease and extends `schedule:run` success and Redis-failure JSON with a bounded `coordination` list.",
            'targets' => ['028-application-owned-redis-cache-and-schedule-lease.md'],
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
        '| [ADR 015](015-explicit-native-session-lifecycle.md) | Cookie validation, duplicate-name, prefix, size, expiration, and lifetime wording | [ADR 049](049-bounded-response-cookie-profile.md) |',
        '| [ADR 017](017-bounded-trailing-positive-integer-routes.md) | One-trailing-parameter limit, prefix index, and one-value route metadata | [ADR 019](019-bounded-multiple-typed-routes.md) |',
        '| [ADR 019](019-bounded-multiple-typed-routes.md) | Fixed parameter-type set before UUID and ULID | [ADR 032](032-explicit-uuid-and-ulid-route-types.md) |',
        '| [ADR 020](020-application-owned-request-policy.md) | Denial and unknown-failure logging wording | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 021](021-application-owned-typed-input-boundaries.md) | Blanket-`400` authoring default for structured request-body content | [ADR 042](042-application-owned-input-failure-classification.md) |',
        '| [ADR 026](026-bounded-file-transfers.md) | Remote-object-store and pre-signed-delivery exclusion, only when an application explicitly selects `AMAZON_S3_ADR053`; `LOCAL_ADR026` remains unchanged | [ADR 053](053-application-owned-amazon-s3-file-transfers.md) |',
        '| [ADR 026](026-bounded-file-transfers.md) | Headers-only prior-output detection before local-file emission | [ADR 060](060-reject-pending-output-before-response-emission.md) |',
        '| [ADR 025](025-application-owned-explicit-cli-and-scheduler.md) | Executable example\'s same-host schedule file lock and `schedule:run` coordination output | [ADR 028](028-application-owned-redis-cache-and-schedule-lease.md) |',
        '| [ADR 044](044-bounded-task-routed-ai-context.md) | Universal-context ownership and the rejected measurement-report boundary after its recorded reconsideration condition was reached; universal authority, safety, validity, red lines, and the four-file task-specific simple-endpoint metric remain accepted | [ADR 058](058-concern-local-ai-context-routing.md) |',
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
        '| `pgsql` | Official `postgres:17` service | PostgreSQL `17.11` |',
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
            'The tagged Alpha 6 framework source removes the redundant public-prerelease `PathParameters::onePositiveInteger()` convenience factory and occupies 2,595 lines.',
            'Repeated documentation-marker checks use explicit shared repository-module helpers rather than duplicated loops',
            'The decision-navigation and vocabulary guard uses one fixed reviewed map of partial-supersession relationships.',
            'The maintained SQLite negative control supplies an impossible version, requires the exact bounded failure and removal of its pre-DDL fixture, then proves clean recovery through the normal certification run.',
        ],
        $installedFramework . '/docs/getting-started.md' => [
            'Any consumer upgrading from Alpha 5 or an earlier PHPThis revision or package must replace each call with `PathParameters::fromValues([$name => $value], [])`; an unchanged old call fails because the method no longer exists.',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            "Read the application's `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`.",
            'Start with the one current operational guide selected by `.ai/README.md`.',
            "ADR 028 replaces only the executable example's schedule file lock with one application-owned Redis owner-token lease and extends successful and Redis-failure `schedule:run` output with one bounded `coordination` list.",
        ],
        $installedFramework . '/docs/cli.md' => [
            "ADR 028 replaces only the example's same-host schedule file lock with one Redis-specific owner-token lease and extends successful and Redis-failure `schedule:run` output with one bounded `coordination` list.",
        ],
        $installedFramework . '/docs/consumer-profile.md' => [
            'the exact maintained matrix: SQLite `3.45.1`, MySQL `8.4.11`, and PostgreSQL `17.11`',
            'no unlisted engine version inherits certification',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Choose or assess HTTP caching or server-side derived-data caching |',
            'application response headers and, for an adopted server-side cache',
            '| Adopt, change, or review the optional Redis cache and schedule-lease recipe |',
            'the deliberately adopted application recipe',
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
    forbidInstalledArtifactMarkers(
        [$installedFramework . '/docs/knowledge-map.md' => ['.ai/cache.md']],
        'application context routing',
    );

    fwrite(STDOUT, "PASS installed historical and reference clarity distribution\n");
}

function proveInstalledReleaseGuidanceDistribution(string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/RELEASING.md' => [
            'Alpha 7 and `v0.1.0-alpha.7` are the latest immutable framework identity, tag, and source boundary recorded by the repository.',
            'Closed [Issue #53](https://github.com/balgf/PHPThis/issues/53) records the completed and announced coordinated Alpha 7 release',
            '[framework GitHub prerelease](https://github.com/balgf/PHPThis/releases/tag/v0.1.0-alpha.7)',
            '[skeleton GitHub prerelease](https://github.com/balgf/PHPThis-skeleton/releases/tag/v0.1.0-alpha.7)',
            '[final announcement](https://github.com/balgf/PHPThis/issues/53#issuecomment-5323310887)',
            'This current record was reconciled against those external sources on 2026-08-25 (Asia/Manila).',
            'Continuing publication and package availability remain external',
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
            'source preparation; exact-candidate freeze and approval; framework commit and push; framework tag creation and push; framework Packagist update; skeleton commit and push; skeleton tag creation and push; skeleton Packagist update; either GitHub prerelease; and the final announcement.',
            '## Version-neutral release gate',
            'Preparing a proposal or accepted source scope, proving or publishing an approved candidate, and inspecting an older release are different tasks.',
            'candidate-specific announcement',
            'An unexplained collision stops the release and requires a new approved version.',
            'When resuming a recorded partial publication, require every existing tag and artifact to match its recorded commit and distribution evidence exactly',
            'Existing state never authorizes overwrite, tag movement, deletion and recreation, or artifact replacement.',
            'record the framework side as published but the overall release as partial and unproved',
            'preserve and record that exact partial-publication state',
            '### 2. Prove the framework candidate',
            'Do not push it before the local proof in Step 2 passes.',
            'finishes with `git-export-parity=verified` for this exact clean candidate.',
            'Treat `git-export-parity=skipped-dirty` only as a successful development run of the independent Composer archive, isolated installation, installed checker, application behavior, and adversarial controls.',
            'It does not satisfy this candidate gate, approve the candidate, or authorize push, tag, package, release, or any later operation.',
            'Failure to inspect Git status or to create, read, or compare the clean Git archive remains a hard failure rather than a third proof state',
            'fixed terminal output must expose no Git status bytes, source bytes, absolute paths, or untracked filenames.',
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
            'Local Git-export parity state (`verified` required):',
            'Accountable-human authorization records by exact operation:',
            'Partial-publication state or NOT_APPLICABLE:',
            '## Approved Alpha 6 identity and source preparation',
            'Composer version: `0.1.0-alpha.6`',
            'Framework tag: `v0.1.0-alpha.6`',
            'Skeleton tag: `v0.1.0-alpha.6`',
            'Planned release date: `2026-08-09` (Asia/Manila)',
            'Bounded scope: `docs/decisions/047-bounded-alpha-6-release-scope.md`',
            'Release notes: `docs/releases/0.1.0-alpha.6.md`',
            'The source-preparation approval above did not itself authorize any external operation.',
            'Issue #37 subsequently records the complete coordinated Alpha 6 release: the exact framework and skeleton candidates, both tags and packages, clean exact `composer create-project` proof, both GitHub prereleases, and announcement.',
            'Accepted implementation and guidance after `v0.1.0-alpha.6` now include ADRs 048 through 053, Consumer Contract version 13, and the unchanged 2,618-line core under the accepted 2,620-line ceiling.',
            'Those changes are not part of the immutable Alpha 6 framework source.',
            'ADR 054 separately accepts the Alpha 7 identity and source-preparation scope while selecting no exact candidate or external release operation.',
            'This tracked process does not replace the external evidence or establish live host availability.',
            '## Approved Alpha 7 identity and source preparation',
            'The accountable human approved the following release identity, planned date, bounded scope, release notes, and source-preparation state on 2026-08-14 (Asia/Manila):',
            'Composer version: `0.1.0-alpha.7`',
            'Framework tag: `v0.1.0-alpha.7`',
            'Skeleton tag: `v0.1.0-alpha.7`',
            'Planned release date: `2026-08-18` (Asia/Manila)',
            'Bounded scope: `docs/decisions/054-bounded-alpha-7-release-scope.md`',
            'Release notes: `docs/releases/0.1.0-alpha.7.md`',
            'Exact framework candidate commit: `PENDING`',
            'Exact skeleton candidate commit: `PENDING`',
            'This approval accepts a bounded Alpha 7 deferral of unavailable model/context token telemetry, with no lexical-token proxy, and creates no general evaluation precedent.',
            'It excludes the real WebSocket consumer migration and its temporary proposed decision 002',
            'Both exact candidate commits remain `PENDING`.',
            'This source-preparation approval does not authorize exact-candidate approval, repository commit or push, framework or skeleton tag creation or push, package-host write, dedicated-skeleton change, GitHub release, announcement, issue closure, or production-service mutation.',
            'The `PENDING` candidate values and non-authority statements in ADR 054, the tagged Alpha 7 source-preparation notes, and the approved Alpha 7 source-preparation subsection above are preserved acceptance-time history, not current publication state.',
            'Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release, including both GitHub prereleases, the final announcement, and Issue closure.',
            'At this source revision, post-tag `main` includes accepted application-owned operation-coordination and concern-routing guidance, ADRs 055 through 060, Consumer Contract version 17, Strict Profile version 4, PHT008, executable Composer-configuration and application-source checks, request/response runtime corrections, and maintainer proof hardening.',
            'This delta is not limited to release-state documentation.',
            'Those completed histories authorize no production use or later release operation.',
            'For a current candidate, neither partial evidence nor checklist position authorizes the next external write',
        ],
        $installedFramework . '/docs/decisions/047-bounded-alpha-6-release-scope.md' => [
            'Status: accepted',
            'On 2026-08-09 in Asia/Manila, the accountable human approved this bounded Alpha 6 scope, exact release identity, planned date, release notes, candidate-specific announcement draft, and source preparation.',
            'Composer version: `0.1.0-alpha.6`',
            'framework tag: `v0.1.0-alpha.6`',
            'skeleton tag: `v0.1.0-alpha.6`',
            'planned release date: `2026-08-09` (Asia/Manila)',
            'release notes: `docs/releases/0.1.0-alpha.6.md`',
            'Consumer Contract version 10 to version 11 while retaining Strict Profile version 3 and permanent diagnostics `PHT001` through `PHT007`',
            'The exact framework and skeleton candidate commits remain pending',
            'Publication state is external.',
        ],
        $installedFramework . '/docs/releases/0.1.0-alpha.6.md' => [
            'Release identity: `0.1.0-alpha.6`. Publication state is external',
            'Framework tag: `v0.1.0-alpha.6`',
            'Skeleton tag: `v0.1.0-alpha.6`',
            'Planned release date: `2026-08-09` (Asia/Manila)',
            'These notes describe the accepted bounded Alpha 6 source claim.',
            'Every external operation remains subject to the complete release proof and later accountable-human authorization in `RELEASING.md`.',
            'Consumer Contract from version 10 to version 11 while retaining Strict Profile version 3 and permanent diagnostics `PHT001` through `PHT007`',
            'PathParameters::fromValues([$name => $value], [])',
            'This prerelease remains experimental evaluation software. It is not production-ready and makes no backward-compatibility promise across prereleases.',
            'The exact candidate commits, accountable-human candidate and operation authorizations',
        ],
        $installedFramework . '/docs/decisions/054-bounded-alpha-7-release-scope.md' => [
            'Status: accepted',
            'Publication state is external.',
            'On 2026-08-14 in Asia/Manila, the accountable human accepted this bounded Alpha 7 scope, exact release identity and both tag names, planned date, release notes, and source-preparation state.',
            'The same approval accepts a bounded Alpha 7 deferral of unavailable model/context token telemetry',
            'It explicitly excludes the real WebSocket consumer migration and its temporary proposed decision 002 from Alpha 7',
            'This acceptance does not approve either exact candidate commit and does not authorize a commit, push, tag, package-host update, dedicated-skeleton write, GitHub release, announcement, issue closure, production-system mutation, or any other external operation.',
            'Composer version: `0.1.0-alpha.7`',
            'framework tag: `v0.1.0-alpha.7`',
            'skeleton tag: `v0.1.0-alpha.7`',
            'planned release date: `2026-08-18` (Asia/Manila)',
            'framework candidate commit: `PENDING`',
            'skeleton candidate commit: `PENDING`',
            '### Alpha 6 to Alpha 7 migration',
            'The accepted scope is informed by bounded disposable evidence, not by a frozen candidate or public Alpha 7 artifact:',
            'The accountable human accepts that missing field only as a bounded Alpha 7 source-preparation deferral, with `UNAVAILABLE` retained and no lexical-token proxy substituted.',
            'Before source preparation can advance through an exact Alpha 7 candidate and publication, Issue #53 and `RELEASING.md` must record and prove the following at their applicable ordered gates:',
            'This accepted source-preparation scope keeps Alpha 7 experimental.',
        ],
        $installedFramework . '/docs/releases/0.1.0-alpha.7.md' => [
            '# PHPThis 0.1.0-alpha.7',
            'Source-preparation status: accepted on 2026-08-14 (Asia/Manila)',
            'Publication state is external.',
            'The accountable human accepted the following identity, planned date, bounded scope, notes, and source-preparation state on 2026-08-14 (Asia/Manila):',
            'Composer version: `0.1.0-alpha.7`',
            'framework tag: `v0.1.0-alpha.7`',
            'skeleton tag: `v0.1.0-alpha.7`',
            'planned release date: `2026-08-18` (Asia/Manila)',
            'framework candidate commit: `PENDING`',
            'skeleton candidate commit: `PENDING`',
            'Both exact candidate commits remain `PENDING`.',
            'These notes authorize no candidate approval, commit, push, tag, package-host update, dedicated-skeleton write, release, announcement, issue closure, production-system mutation, or other external operation.',
            '## Breaking prerelease change: bounded response cookies',
            'The package inventory grew from Alpha 6\'s 198 files to 216 files at the observed pre-preparation `main`, then to 218 source-preparation paths after this decision and these notes were added.',
            'The accountable human accepted `UNAVAILABLE`, with no lexical-token proxy, as a bounded Alpha 7 source-preparation deferral only.',
            'The accountable human did not accept that decision or the real consumer migration; both remain outside Alpha 7 scope.',
            'Issue #53 must hold any later exact candidate evidence and enumerable accountable-human operation authorizations.',
        ],
        $installedFramework . '/README.md' => [
            'PHPThis is an experimental PHP 8.4 framework foundation for **AI-first authoring with human accountability**.',
            '## Current release state',
            '| Latest framework tag | Alpha 7, [`v0.1.0-alpha.7`](https://github.com/balgf/PHPThis/tree/v0.1.0-alpha.7), Consumer Contract version 13, Strict Profile version 3, and diagnostics `PHT001` through `PHT007` |',
            '| Latest proved application starter | Alpha 7 is the latest matching framework/skeleton pair with complete clean Packagist-only public-distribution evidence in [Issue #53](https://github.com/balgf/PHPThis/issues/53) |',
            '| Coordinated release status | Alpha 7 is the latest completed and announced coordinated release recorded by closed [Issue #53](https://github.com/balgf/PHPThis/issues/53), including both GitHub prereleases, the final announcement, and Issue closure |',
            '| Current post-tag `main` | Unreleased development source containing ADRs 055 through 060, Consumer Contract version 17, Strict Profile version 4, PHT008, and accepted executable guard, checker, runtime, and maintainer-evidence changes',
            'Package availability and current release state are external facts: verify the exact [framework](https://packagist.org/packages/phpthis/framework) and [skeleton](https://packagist.org/packages/phpthis/skeleton) versions before installation.',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.7'",
            'The Alpha 7 framework tag is immutable.',
            'records the completed and announced coordinated release: both exact candidates, required CI, both immutable tags and Packagist versions, clean Packagist-only public-distribution proof, both GitHub prereleases, the final announcement, and Issue closure.',
            'preserve their acceptance-time `PENDING` values and non-authority statements as historical evidence.',
            'The post-tag `main` source is unreleased development work containing ADRs 055 through 060, Consumer Contract version 17, Strict Profile version 4, PHT008, and accepted executable guard, checker, runtime, and maintainer-evidence changes.',
            'The release process owns the detailed dated external-evidence record; live host availability must still be verified.',
            'Create the latest proved public framework/skeleton pair explicitly:',
            'Issue #53 records the exact Alpha 7 skeleton and clean public-install evidence',
            '## Key documentation',
            '[Consumer Contract](docs/consumer-contract.md)',
            '[Knowledge map](docs/knowledge-map.md)',
            '[Alpha 7 release notes](docs/releases/0.1.0-alpha.7.md)',
            '[Security policy](SECURITY.md) and [release process](RELEASING.md)',
        ],
        $installedFramework . '/ROADMAP.md' => [
            'Closed Issue #53 now records the completed coordinated Alpha 7 release',
            'The first three bullets preserve the 2026-08-14 source-preparation checkpoint',
            'Both exact candidate commits remain `PENDING`; no commit, push, tag, package, dedicated-skeleton change, GitHub prerelease, announcement, issue closure, or production mutation is authorized.',
            'Complete for the coordinated Alpha 7 release:',
            'Issue #53 records the framework and skeleton GitHub prereleases, the final candidate-specific announcement, and Issue closure',
            'The [release process](RELEASING.md) owns the detailed current record.',
            'That completed history grants no authority for a later release operation.',
        ],
        $installedFramework . '/SECURITY.md' => [
            'Alpha 7 and `v0.1.0-alpha.7` are the latest immutable framework tag and source boundary.',
            'Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release, including both GitHub prereleases, the final announcement, and Issue closure.',
            'That recorded completion is not a production-support commitment.',
            'Any approved prerelease candidate may be announced only after its complete public-artifact gate in [the release process](RELEASING.md) passes and both GitHub prereleases receive their exact separate authorizations.',
            'A partially published framework or skeleton remains unannounced until both packages, the clean public installation path, and the required GitHub prereleases are proved.',
            'This tracked policy does not prove continuing live publication state',
        ],
        $installedFramework . '/docs/getting-started.md' => [
            '## Start from a proved published skeleton',
            'Use the contract-version-17 Composer scripts',
            'Do not use an unpinned prerelease constraint during partial publication',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.7'",
            'Before selecting a later prerelease, verify its exact skeleton version and clean public-install evidence in the release work item, GitHub, and Packagist.',
            '## Prerelease boundary',
            '`v0.1.0-alpha.5` preserves that historical coordinated framework, skeleton, and public-install boundary.',
            '`v0.1.0-alpha.6` preserves its completed and announced coordinated framework, skeleton, and public-install boundary.',
            'Issue #37 records its exact framework and skeleton candidates, both tags and packages, clean exact `create-project` proof, both GitHub prereleases, and announcement.',
            'Package availability remains an external fact: verify the evidence record, GitHub, and Packagist before selecting a package version.',
            'ADR 054 and `v0.1.0-alpha.7` define the latest immutable framework tag and source boundary, Consumer Contract version 13',
            'Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release',
            'clean Packagist-only public-distribution proof, both GitHub prereleases, the final announcement, and Issue closure.',
            'The `PENDING` values in ADR 054 and the tagged Alpha 7 notes remain acceptance-time history.',
            '[ADR 055](decisions/055-value-free-composer-configuration-scripts.md)',
            '[ADR 058](decisions/058-concern-local-ai-context-routing.md)',
            'The unreleased delta includes application-owned operation-coordination and concern-routing guidance, executable Composer-configuration and application-source checks, request/response runtime corrections, and maintainer proof hardening; it is not limited to release-state documentation.',
            'Prerelease publication follows the complete version-neutral maintainer gate in [the release process](../RELEASING.md).',
            'A framework-only or skeleton-only publication is recorded as partial and is not announced as a complete release.',
            'This tracked guide records the proved Alpha 7 command but does not establish future availability',
            '[ADR 060](decisions/060-reject-pending-output-before-response-emission.md), Consumer Contract version 17, and Strict Profile version 4 with `PHT008`.',
            'Audit every response-emitter path, remove unintended earlier output at its owner, keep intentional buffers empty at emitter entry, and retain no-fallback behavior for `ResponseEmissionFailed(true)`.',
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
            'The independent Git-export subproof returns `verified` only when Git status inspection reports a clean checkout',
            'any tracked-unstaged, staged, or untracked state returns `skipped-dirty`.',
            'Real temporary clean, tracked-unstaged, staged, and untracked repositories prove those states without depending on the maintainer checkout.',
            'the terminal result identifies them as development checks and states `not release evidence`',
            'only `git-export-parity=verified` is local Git-export release evidence.',
            'Both terminal forms are fixed and expose no Git status bytes, source bytes, absolute paths, or untracked filenames.',
            'A local result with `git-export-parity=verified` establishes the source-controlled Composer and Git export policies for that clean checkout.',
            'A `git-export-parity=skipped-dirty` result establishes only the independently completed development checks and is not Git-export release evidence.',
            'It also requires ADR 047 and the Alpha 6 release notes to ship together with the exact approved source-preparation identity, planned date, Contract version 11, Strict Profile version 3, and permanent diagnostics `PHT001` through `PHT007`',
            'ADR 054, the tagged Alpha 7 notes, and the approved Alpha 7 source-preparation subsection retain their acceptance-time `PENDING` and non-authority statements as historical evidence.',
            '`RELEASING.md` is the detailed current mutable release-state owner',
            'Current guidance records closed Issue #53\'s completed and announced coordinated Alpha 7 evidence, including both GitHub prereleases, the final announcement, and Issue closure.',
            'The accepted ADR 059 source-prefix guard pins the 228-file current inventory',
            'the current unreleased ADR 055-through-ADR 060 and Contract 17/Profile 4/PHT008 boundary',
            'the executable guard/checker/runtime and maintainer-evidence character of that delta',
            'Concern-specific capability and evidence contracts remain in their routed guides rather than being repeated in the README.',
            'ordered local-proof-before-push, exact-CI, tag-creation-and-push',
            'rejects explicit versioned present-tense `partial`, `pending`, or `unannounced` claims',
            'This syntactic drift detector does not infer arbitrary semantic freshness.',
            'verifies no continuing live GitHub or Packagist state',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'release guidance');

    $installedMutableReleaseStateClaim = static function (string $contents): bool {
        $plainContents = preg_replace('/\[([^\]\r\n]+)\]\([^\)\r\n]+\)/', '$1', strtolower($contents));

        if (!is_string($plainContents)) {
            throw new RuntimeException('Unable to normalize installed release guidance.');
        }

        $plainContents = str_replace(['*', '_', '`', '~'], '', $plainContents);
        $normalizedContents = preg_replace('/\s+/', ' ', $plainContents);

        if (!is_string($normalizedContents)) {
            throw new RuntimeException('Unable to normalize installed release-guidance whitespace.');
        }

        $releaseSubject = '(?:the\s+)?(?:public\s+)?'
            . '(?:(?:alpha|beta|rc)[ .-]?\d+|v?\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)[.-]\d+)?)'
            . '(?:\s+(?:release|packages?|installation\s+path))?';
        $publicationPredicate = '(?:(?:is|are)\s+(?:(?:not(?:\s+yet)?|now)\s+)?(?:publicly\s+)?(?:available|published|released)|(?:has|have)\s+(?:(?:not(?:\s+yet)?|now)\s+)?been\s+(?:publicly\s+)?(?:published|released))';
        $coordinationPredicate = '(?:(?:is|are|remains?)\s+(?:(?:still|currently)\s+)?(?:an?\s+)?(?:partial|pending|unannounced))';
        $releaseClaimPatterns = [
            '/\b' . $releaseSubject . '\s+' . $publicationPredicate . '\b/',
            '/\b' . $releaseSubject . '\s+' . $coordinationPredicate . '\b/',
        ];

        foreach ($releaseClaimPatterns as $releaseClaimPattern) {
            $releaseClaims = [];

            if (preg_match_all($releaseClaimPattern, $normalizedContents, $releaseClaims, PREG_OFFSET_CAPTURE) <= 0) {
                continue;
            }

            foreach ($releaseClaims[0] as $releaseClaim) {
                $claimPrefix = substr($normalizedContents, 0, $releaseClaim[1]);

                if (preg_match('/(?:\bif|\bwhen|\bonce|\bafter|\bbefore|\bunless)\s*$/', $claimPrefix) !== 1) {
                    return true;
                }
            }
        }

        return false;
    };

    $installedMutableReleaseStateDetectionControls = [
        'Alpha 7 remains partial.' => true,
        "ALPHA 7 REMAINS\nUNANNOUNCED." => true,
        '**Alpha 7** is still pending final coordination.' => true,
        '[v0.1.0-alpha.7](https://example.invalid/release) remains an unannounced partial coordinated release.' => true,
        'Alpha 7 is published.' => true,
        'If Alpha 8 remains partial, record the recovery state.' => false,
        'Alpha 7 remained partial at the source-preparation checkpoint.' => false,
        'Both exact candidate commits remain PENDING.' => false,
        'A partially published framework or skeleton remains unannounced until the complete gate passes.' => false,
    ];

    foreach ($installedMutableReleaseStateDetectionControls as $contents => $expectedClaim) {
        if ($installedMutableReleaseStateClaim($contents) !== $expectedClaim) {
            throw new RuntimeException('The installed normalized mutable release-state detector changed behavior.');
        }
    }

    foreach (
        [
            'README.md',
            'RELEASING.md',
            'ROADMAP.md',
            'SECURITY.md',
            'docs/getting-started.md',
            'docs/guardrails.md',
        ] as $relativePath
    ) {
        $contents = file_get_contents($installedFramework . '/' . $relativePath);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed mutable release-state guidance: {$relativePath}");
        }

        if ($installedMutableReleaseStateClaim($contents)) {
            throw new RuntimeException("Installed mutable release-state claim remains in {$relativePath}.");
        }
    }

    forbidInstalledArtifactMarkers(
        [
            $installedFramework . '/RELEASING.md' => [
                '## Proposed Alpha 7 source preparation',
                'Issue #53 tracks a proposal to prepare the following release identity and source scope for accountable-human review:',
            ],
            $installedFramework . '/docs/decisions/054-bounded-alpha-7-release-scope.md' => [
                'Status: proposed',
                'On 2026-08-13 in Asia/Manila, the accountable human asked maintainers to begin Alpha 7 preparation.',
            ],
            $installedFramework . '/docs/releases/0.1.0-alpha.7.md' => [
                '# Proposed PHPThis 0.1.0-alpha.7',
                'Status: proposed',
                'These draft notes are a source-preparation review artifact.',
                '## Proposed breaking prerelease change: bounded response cookies',
            ],
        ],
        'accepted Alpha 7 stale proposal boundary',
    );

    forbidInstalledArtifactMarkers(
        [
            $installedFramework . '/RELEASING.md' => [
                'post-tag `main` adopts ADR 056, ADR 057, and ADR 059, Consumer Contract version 16',
            ],
            $installedFramework . '/README.md' => [
                'Unreleased development source adopting ADR 056, ADR 057, and ADR 059, Consumer Contract version 16',
                'unreleased development work adopting ADR 056, ADR 057, and ADR 059, Consumer Contract version 16',
            ],
            $installedFramework . '/docs/getting-started.md' => [
                'Use the contract-version-16 Composer scripts',
                '[ADR 059](decisions/059-bounded-application-source-prefix-discovery.md), Consumer Contract version 16, and Strict Profile version 4 with `PHT008`.',
            ],
            $installedFramework . '/docs/guardrails.md' => [
                'the current unreleased ADR 056/ADR 057/ADR 059 and Contract 16/Profile 4/PHT008 source boundary',
            ],
        ],
        'current Contract 17 unreleased-source boundary',
    );

    forbidInstalledArtifactMarkers(
        [
            $installedFramework . '/README.md' => [
                'Alpha 7 remains partial pending both GitHub prereleases and the final announcement',
                'Alpha 6 remains the latest fully completed and announced coordinated release',
                'unreleased development work adopting ADR 056, ADR 057, ADR 059, and ADR 060',
            ],
            $installedFramework . '/RELEASING.md' => [
                'Alpha 7 remains an unannounced partial coordinated release',
                'Alpha 6 remains the latest fully completed and announced coordinated release',
                'post-tag `main` adopts ADR 056, ADR 057, ADR 059, and ADR 060',
                'either GitHub prerelease, the final announcement, Issue #53 closure',
            ],
            $installedFramework . '/ROADMAP.md' => [
                'both GitHub prereleases and the final announcement remain separately gated',
                'Open and separately gated: the framework GitHub prerelease',
            ],
            $installedFramework . '/SECURITY.md' => [
                'Alpha 7 remains an unannounced partial coordinated release',
                'Alpha 6 remains the latest fully completed and announced coordinated release',
            ],
            $installedFramework . '/docs/getting-started.md' => [
                '`v0.1.0-alpha.6` preserves the latest fully completed and announced coordinated framework',
                'Alpha 7 remains an unannounced partial coordinated release',
                'adopts [ADR 056](decisions/056-bounded-request-target-and-path-bytes.md), [ADR 057](decisions/057-distinct-named-sql-placeholder-occurrences.md), [ADR 059]',
            ],
            $installedFramework . '/docs/guardrails.md' => [
                'the unannounced partial coordinated-release status',
                'the current unreleased ADR 056/ADR 057/ADR 059/ADR 060',
                'while keeping both GitHub prereleases, the final announcement, Issue closure',
            ],
        ],
        'completed Alpha 7 release and current post-tag source boundary',
    );

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
        'git-export-parity=verified',
        'git-export-parity=skipped-dirty',
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

function proveInstalledTestRunnerModularizationGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    $guidanceMarkers = [
        'When an application-owned test or validation entrypoint spans unrelated concerns or becomes difficult to review, prefer a small deterministic entrypoint',
        'cohesive concern-owned modules in an explicit order, with narrowly shared support',
        'Preserve deterministic execution and failure behavior, and keep focused evidence directly runnable where the selected tool allows.',
        'Keep that composition explicit; do not introduce runtime discovery or a plugin framework merely to organize the runner.',
        'Modularize only application-owned code; do not copy, replace, or modularize the installed `vendor/bin/phpthis check` entrypoint.',
        'This is advisory organization guidance, not a validity rule: PHPThis sets no line-count threshold, prescribes no directory layout, test library, or module interface, and adds no checker rule.',
        'The application owns whether and how to split the entrypoint; its documented complete project check remains the authoritative gate.',
    ];

    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/testing.md' => $guidanceMarkers,
        $installedFramework . '/docs/consumer-contract.md' => $guidanceMarkers,
        $installedFramework . '/templates/application/.ai/testing.md' => $guidanceMarkers,
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'test-runner modularization guidance');

    fwrite(STDOUT, "PASS installed test-runner modularization guidance distribution\n");
}

function proveInstalledStatelessAuthenticationGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    $guideMarkers = [
        '# Application-owned stateless authentication',
        'PHPThis supplies no credential parser, verifier, issuer, revoker, identity provider, or authentication runtime/API.',
        'This guide changes no core source, runtime dependency, Consumer Contract, Strict Profile, checker rule, or `PHT` diagnostic.',
        'Here, stateless means that each request presents its credential without using PHPThis session or cookie identity.',
        'an application-owned verifier may perform explicit bounded database, trusted-key, or external-provider I/O under the budgets and outage contract below.',
        "\$request->headers['authorization'] ?? null",
        'use TLS with certificate validation for every credential-bearing request',
        'The application authenticator then accepts one Bearer representation under a smaller recorded byte bound and finite grammar.',
        'HTTP authentication-scheme matching is ASCII case-insensitive, while the credential bytes are case-sensitive and opaque.',
        'Do not fall back to a query parameter, request body, cookie, path segment, alternate header, or previously stored identity',
        '`WWW-Authenticate: Bearer` is response semantics for the generic unauthenticated result.',
        'That disclosure-minimizing reference challenge and error policy is not RFC-6750-compatible',
        'Record the exact absent-credential challenge, `invalid_request` status and error mapping',
        '`invalid_token` `401` mapping for definitively invalid credentials',
        '`insufficient_scope` `403` mapping where that application can disclose the classification safely',
        'test Bearers are synthetic only; neither is production credential evidence.',
        'Never guess a format after one verifier fails, accept the same bytes under multiple profiles, or use a fallback verifier.',
        'An ordinary Bearer credential is replayable by any party that possesses it.',
        'Record whether sender constraint is not applicable or is a separately adopted and proved profile.',
        'one fixed code-owned set of acceptable algorithms, selected independently of the received `alg`',
        'one fixed JOSE protection and serialization profile',
        'UTF-8 for the protected header and claims JSON, one finite allowlist of protected-header parameter names',
        'an untrusted `x5c`, embedded key, certificate, thumbprint, or other header never supplies or substitutes verification trust material',
        'rejection of duplicate protected-header and claim member names, or one explicitly recorded canonical duplicate-member behavior of the selected library',
        'the exact trusted issuer and its binding to the verification keys',
        'the exact required audience for this API',
        'the required `exp`, permitted `nbf` and `iat` relationships, maximum accepted lifetime, authoritative injected clock, and finite allowed clock skew',
        'a received `jku`, `x5u`, issuer, or other claim never selects an arbitrary file, database expression, class, command, or outbound URL',
        'Local signature verification does not prove current revocation.',
        'never make the raw value retrievable later, and store only a purpose-built one-way verification value rather than the raw credential',
        'Name the exact maintained verifier construction:',
        'Record what an offline database reader and an application-host compromise can recover.',
        'Require a timing-safe final secret comparison',
        'Every request checks the verifier, active state, expiry, revocation, owner state, tenant relationship, and scopes needed as input to the separate authorizer.',
        'A token-controlled issuer or key URL never selects an outbound destination.',
        'Disable HTTP redirects for key retrieval and introspection by default.',
        'send the exact protocol `POST` to the configured endpoint over TLS with certificate validation',
        'A trusted `active: false` is definitive credential rejection.',
        'provider outage is verifier uncertainty: it fails closed and never produces an authenticated principal, but it is not evidence that the caller\'s credential is invalid.',
        'Derive its lookup key from the credential with one selected maintained one-way keyed primitive and a cache-specific key',
        'This resource-server guide does not define an OAuth authorization server or client flow.',
        'following [RFC 9700](https://www.rfc-editor.org/rfc/rfc9700)',
        'authenticate -> resolve tenant -> authorize -> protected handler',
        'Authorization runs for the current named action on every request.',
        'one named generic `5xx` application failure, distinct from a definitive invalid-credential `401`',
        'Missing, malformed, oversized, expired, not-yet-valid, revoked, definitively inactive, wrong-issuer, wrong-audience, wrong-type, invalid-signature, and otherwise definitively rejected credentials share the application\'s generic `401` Bearer response.',
        'Production acceptance requires evidence for the consuming application\'s selected parser, verifier, credential lifecycle, external dependencies, deployment, and clients.',
    ];

    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change authentication, stateless Bearer/JWT/PAT/external-provider, tenant, or authorization policy | `.ai/request-policy.md` | installed `vendor/phpthis/framework/docs/stateless-authentication.md`, action-specific composition, protected work, lifecycle, and denial tests |',
        ],
        $project . '/.ai/request-policy.md' => [
            '`NOT_APPLICABLE(REQUEST_POLICY)`',
            'read installed `vendor/phpthis/framework/docs/request-policy.md` and `vendor/phpthis/framework/docs/stateless-authentication.md`',
            'one strict TLS-protected `Authorization: Bearer` source with no alternate or fallback source',
            'selected JWT, opaque/PAT/API-token, or external-verification profile',
            'preserve the bare non-RFC-6750-compatible reference challenge',
        ],
        $installedFramework . '/docs/stateless-authentication.md' => $guideMarkers,
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Add, explain, or review stateless Bearer, JWT, opaque/PAT/API-token, external-provider authentication, tenant resolution, or authorization | `docs/stateless-authentication.md`, `docs/request-policy.md`, `docs/security.md`, `docs/errors.md`, `docs/decisions/020-application-owned-request-policy.md` |',
            'verify that PHPThis adds no JWT, PAT, OAuth, identity-provider, or authentication runtime/API',
        ],
        $installedFramework . '/docs/request-policy.md' => [
            '[Application-owned stateless authentication](stateless-authentication.md)',
            '`WWW-Authenticate: Bearer` is response semantics, not token support.',
            'accepts one strict Bearer header over TLS with no alternate credential source',
            'explicitly not an RFC-6750-compatible challenge and error profile',
        ],
        $installedFramework . '/docs/security.md' => [
            'PHPThis supplies no credential parser, verifier, issuer, revoker, identity provider, or authentication runtime/API.',
            'A JWT profile owns RFC 8725\'s fixed algorithm and key binding',
            'that bare challenge and generic error policy are deliberately disclosure-minimizing and non-RFC-6750-compatible',
            'Selected external key retrieval or RFC 7662 introspection owns authenticated TLS I/O, bounds, timeouts, cache-staleness, outage, and fail-closed behavior.',
            '[Application-owned stateless authentication](stateless-authentication.md)',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Change authentication, stateless Bearer/JWT/PAT/external-provider, tenant, or authorization policy | `.ai/request-policy.md` | installed `vendor/phpthis/framework/docs/stateless-authentication.md`, action-specific composition, protected work, lifecycle, and denial tests |',
        ],
        $installedFramework . '/templates/application/.ai/request-policy.md' => [
            'Read installed `vendor/phpthis/framework/docs/request-policy.md` and `vendor/phpthis/framework/docs/stateless-authentication.md` first.',
            '{{AUTHORIZATION_HEADER_BOUNDARY}}',
            '{{CREDENTIAL_PROFILE}}',
            '{{CREDENTIAL_VERIFIER_AND_CONFIGURATION}}',
            '{{CREDENTIAL_LIFECYCLE}}',
            '{{RFC_6750_COMPATIBILITY_POLICY}}',
            '{{POLICY_DEPENDENCY_FAILURE}}',
            '{{FRONTEND_CREDENTIAL_BOUNDARY}}',
            '{{CREDENTIAL_EVIDENCE_OR_LIMIT}}',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'stateless-authentication guidance, source and installed routes, exact package inventory, Composer dependency checks, and runtime-API path and identifier checks preserve application-owned JWT, PAT, OAuth, and external-provider choices',
            'The stateless-authentication guidance guard pins the dedicated installed guide',
            'It adds no core API, runtime or development authentication dependency, Consumer Contract or Strict Profile change, checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'stateless authentication guidance');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    $forbiddenPackageFixtures = [
        'auth0/auth0-php',
        'firebase/php-jwt',
        'laravel/sanctum',
        'league/oauth2-server',
        'paragonie/paseto',
        'vendor/identity-provider',
        'vendor/pat',
    ];
    $allowedPackageFixtures = [
        'ext-session',
        'phpstan/phpstan',
        'phpthis/framework',
        'psr/http-message',
        'roave/security-advisories',
    ];

    foreach ($forbiddenPackageFixtures as $package) {
        if (!installedStatelessAuthenticationPackageIsForbidden($package)) {
            throw new RuntimeException(
                "Installed stateless authentication dependency detector fixture must fail: {$package}.",
            );
        }
    }

    foreach ($allowedPackageFixtures as $package) {
        if (installedStatelessAuthenticationPackageIsForbidden($package)) {
            throw new RuntimeException(
                "Installed stateless authentication dependency detector fixture must remain allowed: {$package}.",
            );
        }
    }

    $forbiddenRuntimeIdentifierFixtures = [
        'AccessToken',
        'ApiTokenVerifier',
        'AuthManager',
        'Auth0Client',
        'AuthenticatedPrincipal',
        'BearerAuthenticator',
        'ClaimsVerifier',
        'IdentityProvider',
        'JwtVerifier',
        'OpaqueTokenStore',
        'PatVerifier',
        'PersonalAccessTokenIssuer',
    ];
    $allowedRuntimeIdentifierFixtures = [
        'QueryTrace',
        'RequestReader',
        'RouteParameterType',
        'SessionLifecycle',
    ];

    foreach ($forbiddenRuntimeIdentifierFixtures as $identifier) {
        if (!installedStatelessAuthenticationRuntimeApiIdentifierIsForbidden($identifier)) {
            throw new RuntimeException(
                "Installed stateless authentication runtime/API detector fixture must fail: {$identifier}.",
            );
        }
    }

    foreach ($allowedRuntimeIdentifierFixtures as $identifier) {
        if (installedStatelessAuthenticationRuntimeApiIdentifierIsForbidden($identifier)) {
            throw new RuntimeException(
                "Installed stateless authentication runtime/API detector fixture must remain allowed: {$identifier}.",
            );
        }
    }

    foreach (
        [
            $installedFramework . '/composer.json' => 'installed framework',
            $project . '/composer.json' => 'installed default skeleton',
        ] as $composerPath => $surface
    ) {
        $composer = jsonFile($composerPath);

        foreach (['require', 'require-dev'] as $dependencySection) {
            $dependencies = $composer[$dependencySection] ?? null;

            if (!is_array($dependencies)) {
                throw new RuntimeException(
                    "{$surface} {$dependencySection} dependencies must remain an explicit Composer map.",
                );
            }

            foreach (array_keys($dependencies) as $dependency) {
                if (
                    is_string($dependency)
                    && installedStatelessAuthenticationPackageIsForbidden($dependency)
                ) {
                    throw new RuntimeException(
                        "Authentication package {$dependency} must remain application-owned and absent from {$surface}:{$dependencySection}.",
                    );
                }
            }
        }
    }

    foreach (
        [
            $installedFramework . '/src' => 'installed framework',
            $project . '/src' => 'installed default skeleton',
        ] as $sourceRoot => $surface
    ) {
        requireInstalledStatelessAuthenticationRuntimeApiBoundary($sourceRoot, $surface);
    }

    fwrite(STDOUT, "PASS installed stateless authentication guidance distribution\n");
}

function installedStatelessAuthenticationPackageIsForbidden(string $package): bool
{
    $normalized = strtolower($package);

    return preg_match(
        '~(?:^|[/._-])(?:access[-_]?token|api[-_]?token|auth[0-9]*|authn|authz|authentication|authorization|bearer|credential|identity[-_]?provider|jose|jwe|jwk|jws|jwt|oauth2?|oidc|openid|opaque[-_]?token|paseto|pat|passport|personal[-_]?access[-_]?token|sanctum)(?:$|[/._-])~i',
        $package,
    ) === 1
        || str_starts_with($normalized, 'symfony/security-');
}

function requireInstalledStatelessAuthenticationRuntimeApiBoundary(
    string $sourceRoot,
    string $surface,
): void {
    if (!is_dir($sourceRoot) || is_link($sourceRoot)) {
        throw new RuntimeException("The stateless authentication {$surface} source boundary is unavailable.");
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($sourceRoot) + 1);

        foreach (explode('/', $relativePath) as $segment) {
            $name = str_ends_with(strtolower($segment), '.php')
                ? substr($segment, 0, -4)
                : $segment;

            if (installedStatelessAuthenticationRuntimeApiIdentifierIsForbidden($name)) {
                throw new RuntimeException(
                    "Authentication runtime/API path must remain outside {$surface} source: {$relativePath}.",
                );
            }
        }

        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (!is_string($contents)) {
            throw new RuntimeException(
                "Cannot read {$surface} source for the stateless authentication API boundary: {$relativePath}.",
            );
        }

        foreach (token_get_all($contents) as $token) {
            if (
                !is_array($token)
                || !in_array(
                    $token[0],
                    [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE],
                    true,
                )
            ) {
                continue;
            }

            $identifiers = preg_split('/\\\\/', $token[1]);

            foreach (is_array($identifiers) ? $identifiers : [] as $identifier) {
                if (installedStatelessAuthenticationRuntimeApiIdentifierIsForbidden($identifier)) {
                    throw new RuntimeException(
                        "Authentication runtime/API identifier {$identifier} must remain outside {$surface} source: {$relativePath}.",
                    );
                }
            }
        }
    }
}

function installedStatelessAuthenticationRuntimeApiIdentifierIsForbidden(string $identifier): bool
{
    if (
        preg_match('/claims(?:parser|validator|verifier)/i', $identifier) === 1
        || preg_match('/(?:\A|[a-z0-9])(?:Auth|PAT|Pat)(?:[A-Z]|\z)/', $identifier) === 1
    ) {
        return true;
    }

    if (
        preg_match(
            '/auth(?:enticate|enticated|entication|enticator|orize|orization|orizer)/i',
            $identifier,
        ) === 1
    ) {
        return true;
    }

    return preg_match(
        '/(?:accesstoken|apitoken|auth[0-9]+|auth(?:enticate|authenticated|authentication|authenticator|authorize|authorization|authorizer)|bearer|credential(?:introspector|parser|refresher|repository|revoker|service|store|validator|verifier|issuer)?|identityprovider|jose|jwe|jwk|jws|jwt|oauth|oidc|openid|opaquetoken|paseto|personalaccesstoken|token(?:introspector|parser|refresher|repository|revoker|service|store|validator|verifier|issuer))/i',
        $identifier,
    ) === 1;
}

function proveInstalledNativeDateTimeGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change date, time, timezone, duration, or clock behavior | installed `vendor/phpthis/framework/docs/date-time.md`',
        ],
        $installedFramework . '/docs/date-time.md' => [
            '# Native date and time',
            "PHPThis recommends PHP's native date and time API.",
            'The framework and default skeleton do not require Carbon or another date-time package as a runtime dependency.',
            'An application may deliberately adopt a third-party package when a concrete requirement justifies it',
            '## Name the temporal concept first',
            'An **instant** is one point on the timeline.',
            'A **calendar date** such as `2026-08-10`',
            'A **local date-time** contains civil clock fields',
            'An **elapsed duration** is a measured amount of time.',
            'A **calendar interval** such as one month or one day',
            'An operation records which concept it owns before selecting a PHP type, database column, JSON representation, or arithmetic rule.',
            'PHP has no native calendar-date or unresolved-local-date-time value type.',
            'Pass an explicit `DateTimeZone` whenever timezone affects parsing, conversion, display, or calendar arithmetic.',
            'Use `hrtime(true)` only for elapsed measurement inside one running system.',
            'must not be persisted, serialized, compared across processes, or used for scheduling.',
            'The effective ceiling may be a recorded total request bound; add a separate field byte bound only when the operation needs one.',
            "complete every field's shape and native-type phase before applying any timestamp value rule",
            'This example assumes the native-type phase has already established that `$value` is a string.',
            'apply an operation-owned complete lexical grammar and component ranges before parsing one fixed format',
            'PHP format tokens are parsers, not standards validators:',
            "'2026-08-10T12:00:00+24:00'",
            'str_contains($value, "\0")',
            '!checkdate($month, $day, $year)',
            '|| $offsetHour > 14',
            '|| ($offsetHour === 14 && $offsetMinute !== 0)',
            "(\$parts['sign'] === '-' && \$offsetHour === 0 && \$offsetMinute === 0)",
            "DateTimeImmutable::createFromFormat('!' . \$format, \$value)",
            '$errors = DateTimeImmutable::getLastErrors();',
            "(\$errors !== false && (\$errors['warning_count'] !== 0 || \$errors['error_count'] !== 0))",
            '$parsed->format($format) !== $value',
            '`InvalidTimestamp` is an illustrative operation-owned value failure, not a PHPThis type.',
            'query, header, route, and transport inputs retain their own contracts;',
            'a database projection uses its recorded persisted-state failure',
            'Call it immediately after `createFromFormat()` because it describes the most recent parse.',
            'requires a recorded daylight-saving transition policy.',
            'A skipped local time in a forward gap and a repeated local time in a backward overlap',
            'A forward gap has no matching instant in that zone:',
            'A supplied offset cannot make the skipped local fields valid in the named zone.',
            'validate it against an actual candidate for the named zone.',
            'inject one narrowly named application clock into that operation.',
            'For every persisted or transmitted temporal value, record:',
            '- the temporal concept and authoritative clock;',
            '- exact format or integer unit, precision, accepted range, and canonical spelling;',
            '- timezone, offset, or named-zone retention policy;',
            '- database engine representation and projection parser;',
            '- JSON or other sink format and normalization policy; and',
            '- compatibility and migration behavior when the representation changes.',
            'Calendar arithmetic requires boundary evidence.',
            'Cover every applicable leap day, month end, daylight-saving gap and overlap, offset change, minimum and maximum accepted value, fractional precision, and serialization round trip.',
            'prefer `CarbonImmutable` over mutable `Carbon\\Carbon`.',
            'global `setTestNow()` state',
            'PHPThis adds no date-time facade, generic parser, normalization helper, clock API, persistence mapping, checker rule, or `PHT` diagnostic.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Parse, persist, format, calculate, schedule, or test date and time behavior | `docs/date-time.md`',
        ],
        $installedFramework . '/docs/type-safety.md' => [
            '[Native date and time](date-time.md)',
            'A date or timestamp has a complete lexical and component grammar',
            'PHP format tokens and generic date guessing are not standards validation.',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Change date, time, timezone, duration, or clock behavior | installed `vendor/phpthis/framework/docs/date-time.md`',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'native date and time guidance');

    $installedComposer = jsonFile($installedFramework . '/composer.json');
    $installedRuntimeRequirements = $installedComposer['require'] ?? null;

    if (!is_array($installedRuntimeRequirements)) {
        throw new RuntimeException('Installed framework runtime requirements must be an explicit Composer map.');
    }

    foreach (array_keys($installedRuntimeRequirements) as $runtimePackage) {
        if (
            !is_string($runtimePackage)
            || (
                $runtimePackage !== 'php'
                && !str_starts_with($runtimePackage, 'ext-')
            )
        ) {
            throw new RuntimeException(
                'Installed framework runtime dependencies must remain native PHP and extensions.',
            );
        }
    }

    $consumerComposer = jsonFile($project . '/composer.json');
    $consumerRuntimeRequirements = $consumerComposer['require'] ?? null;

    if (!is_array($consumerRuntimeRequirements)) {
        throw new RuntimeException('Installed skeleton runtime requirements must be an explicit Composer map.');
    }

    $consumerRuntimePackages = array_keys($consumerRuntimeRequirements);

    foreach ($consumerRuntimePackages as $consumerRuntimePackage) {
        if (!is_string($consumerRuntimePackage)) {
            throw new RuntimeException('Installed skeleton runtime requirement names must be strings.');
        }
    }

    sort($consumerRuntimePackages, SORT_STRING);

    if ($consumerRuntimePackages !== ['php', 'phpthis/framework']) {
        throw new RuntimeException(
            'Installed default skeleton must require only PHP and phpthis/framework.',
        );
    }

    fwrite(STDOUT, "PASS installed native date and time guidance distribution\n");
}

function proveInstalledFrontendIntegrationGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Build or change frontend integration or application-owned HTML rendering | installed `vendor/phpthis/framework/docs/frontend-integration.md` | `.ai/architecture.md`, `.ai/testing.md`, and exact HTTP paths; add other concern guides only when entered |',
        ],
        $installedFramework . '/docs/frontend-integration.md' => [
            '# Frontend integration',
            'A browser or other frontend may use React, Vue, Svelte, plain JavaScript, a native mobile stack, another client stack, or no client framework at all;',
            "The frontend consumes the application's explicit HTTP and any adopted WebSocket contracts",
            'PHPThis recommends a separately owned frontend and API, exposed through one same-origin reverse proxy when the product permits it.',
            'Never let a single-page-application fallback convert an unknown `/api/...` path into `index.html`;',
            '## Record one handoff per operation',
            'method, literal or typed path, query fields, request headers, request media type, body shape, and every byte, depth, collection, and scalar bound',
            'credential location, browser credential and cookie mode, authentication, tenant resolution, and authorization position and outcomes, session-cookie behavior where adopted, and CSRF token transport and rotation where required;',
            'every success status, response media type, exact field set and native JSON types, absent-versus-`null` behavior, enum vocabulary, identifier representation, date and time representation, and compatibility policy;',
            'every expected HTTP failure status, media type, stable public code or non-JSON body, retry policy, disclosure policy, and whether rejected work has no operation-owned side effect;',
            '## Keep frontend failures distinct',
            '**Transport failure:** no usable HTTP response reached the frontend application.',
            '**HTTP failure:** a response exists with a status, headers, media type, and body.',
            '**Decode or contract failure:** the response claims an accepted operation result',
            'Do not call a JSON decoder before checking the response status and media type.',
            'framework-owned route misses and method rejections are `text/plain`.',
            '## Treat cross-origin access as a complete policy',
            'Record CORS as not applicable when the browser and API share one origin;',
            'every exact allowed origin and its normalization source, with no reflection of arbitrary request data;',
            'a credentialed response never uses `*` as its allowed origin;',
            '`Access-Control-Expose-Headers: X-Request-ID` on the actual response when browser code must read the correlation value;',
            'Record the exact successful `2xx` preflight status.',
            'Put `Access-Control-Allow-Origin` on the preflight and every actual response that the browser is allowed to expose.',
            'A PHPThis `204` preflight has an empty body and no `Content-Length`.',
            'The ordinary HTTP `Allow` header on a `405` reports route methods; it is not CORS permission.',
            'PHPThis provides no CORS middleware, automatic preflight, origin policy, or response post-processor.',
            'A route-local request-handler decorator cannot establish complete CORS behavior because it cannot wrap routing-owned 404 or 405 responses, exact failure mapping, the unknown-failure boundary, the terminal coordinator, or response emission.',
            'bootstrap, composition, fatal, and emission-fallback failures outside the ordinary PHPThis coordinator.',
            'it explicitly classifies them as opaque browser transport or infrastructure failures with no readable status, body, or request ID.',
            'Raw duplicate `Origin` or preflight-request-header handling belongs to the first server or proxy boundary that can observe the raw field multiplicity.',
            'PHPThis receives application request headers after SAPI normalization',
            '## Keep static assets frontend-owned',
            'PHPThis supplies no package manager, bundler, development server, asset discovery, manifest reader, fingerprint helper, or static-file route.',
            'If measured product evidence requires one PHPThis operation to serve application-owned assets, record that exception separately.',
            'That bounded operation does not establish a generic asset server, directory walk, fallback, manifest discovery, or framework capability.',
            '## Optional application-owned HTML rendering',
            'An application may return an explicit `text/html; charset=utf-8` string in an ordinary `Response`.',
            'Pass one final readonly operation-specific view model rather than `mixed`, an associative context bag, or service objects.',
            'Templates perform no database or network I/O, filesystem discovery, service lookup, environment or session access, mutable global-state access, or dynamic code execution;',
            'Record the response media type, character encoding, renderer failure mapping, output-size and execution bounds, template compilation and cache ownership, development-versus-production behavior, content-security policy, form CSRF, response cache, localization, accessibility, and browser evidence where applicable.',
            'Before adding a template package, record an application decision explaining why explicit string construction no longer suffices',
            'Select a mature, maintained package, pin the exact package and version, keep automatic escaping enabled for the selected context',
            '## Defer machine-readable API description',
            'Machine-readable API description remains a separate future decision. This guide does not decide whether an application or PHPThis would own such an artifact.',
            'PHPThis currently supplies no OpenAPI document, JSON Schema catalogue, runtime reflection, route scanner, client generator, or schema-to-handler binding',
            'the normative-versus-derived source and drift-check direction, the selected OpenAPI version or other format, the supported JSON Schema subset, unsupported semantics and explicit extensions',
            'whether enforcement is advisory or changes consumer validity. Request-time specification validation or specification serving is not implied.',
            'Route metadata or a machine-readable description alone cannot prove validation and request-policy order, source-specific failure classification, authorization, redaction, cache behavior, side-effect exclusion, or resource bounds.',
            '## Frontend-owned evidence',
            '`composer check` verifies the PHPThis application boundary; it does not verify frontend source or browser behavior.',
            'keep backend behavior evidence plus frontend-owned finite fixtures or contract tests',
            'When cross-origin access is adopted, prove it at an exact local or otherwise non-production browser boundary.',
            'Cover preflight and the actual response, permitted and denied origins, credentialed or uncredentialed behavior as selected, mapped and unknown failures, routing-owned `404` and `405`, exposed `X-Request-ID`, and exact cache and `Vary` headers.',
            'This guide adds no framework runtime, Composer dependency, HTTP type, route behavior, CORS behavior, HTML renderer, templating engine, static-file server, OpenAPI or JSON Schema generator, client generator, checker rule, Consumer Contract change, or Strict Profile change.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Design, implement, or review frontend integration, a frontend/API handoff, browser CORS, static assets, or application-owned HTML rendering |',
            '`docs/frontend-integration.md`; add only the concern guides it routes to',
            'verify that no framework frontend runtime, CORS middleware, renderer, templating or asset engine, machine-readable API generator, or client generator was implied',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Build or change frontend integration or application-owned HTML rendering | installed `vendor/phpthis/framework/docs/frontend-integration.md` | `.ai/architecture.md`, `.ai/testing.md`, and exact HTTP paths; add other concern guides only when entered |',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'frontend integration guidance, installed task routes, exact package inventory, and Composer dependency checks keep browser clients and cross-origin policy application-owned',
            'without adding an SDK, generator, frontend scaffold, OpenAPI runtime, CORS middleware, or framework/default-skeleton runtime dependency',
            'The frontend integration guidance guard pins the dedicated installed guide',
            'It adds no JavaScript or TypeScript SDK, generator, frontend scaffold, OpenAPI artifact or runtime, CORS middleware, automatic preflight, framework source, consumer-checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'frontend integration guidance');

    $installedComposer = jsonFile($installedFramework . '/composer.json');
    $installedRuntimeRequirements = $installedComposer['require'] ?? null;

    if (!is_array($installedRuntimeRequirements)) {
        throw new RuntimeException('Installed framework runtime requirements must be an explicit Composer map.');
    }

    foreach (array_keys($installedRuntimeRequirements) as $runtimePackage) {
        if (
            !is_string($runtimePackage)
            || (
                $runtimePackage !== 'php'
                && !str_starts_with($runtimePackage, 'ext-')
            )
        ) {
            throw new RuntimeException(
                'Installed framework runtime dependencies must remain native PHP and extensions.',
            );
        }
    }

    $consumerComposer = jsonFile($project . '/composer.json');
    $consumerRuntimeRequirements = $consumerComposer['require'] ?? null;

    if (!is_array($consumerRuntimeRequirements)) {
        throw new RuntimeException('Installed skeleton runtime requirements must be an explicit Composer map.');
    }

    $consumerRuntimePackages = array_keys($consumerRuntimeRequirements);

    foreach ($consumerRuntimePackages as $consumerRuntimePackage) {
        if (!is_string($consumerRuntimePackage)) {
            throw new RuntimeException('Installed skeleton runtime requirement names must be strings.');
        }
    }

    sort($consumerRuntimePackages, SORT_STRING);

    if ($consumerRuntimePackages !== ['php', 'phpthis/framework']) {
        throw new RuntimeException(
            'Installed default skeleton must require only PHP and phpthis/framework.',
        );
    }

    fwrite(STDOUT, "PASS installed frontend integration guidance distribution\n");
}

function proveInstalledApplicationOwnedOperationCoordinationGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Adopt or change an atomic lock, mutex, mutual exclusion, lease, critical section, or application coordination boundary | installed `vendor/phpthis/framework/docs/coordination.md` |',
            'record exact mechanism, topology, loss/fencing/idempotency limits, operations, and evidence rather than a framework helper',
        ],
        $project . '/.ai/operations.md' => [
            '## Operation-specific coordination',
            '`NOT_APPLICABLE(OPERATION_COORDINATION)`',
            'This marker does not replace the separate `NOT_APPLICABLE` records for CLI scheduling, migrations, durable jobs, server-side cache, sessions, or file transfers.',
            'commands and results remain in `.ai/testing.md`',
            'Do not add a framework helper or `.ai/coordination.md`.',
        ],
        $project . '/.ai/testing.md' => [
            'Standalone operation-coordination evidence: `NOT_APPLICABLE(OPERATION_COORDINATION_EVIDENCE)`',
            '`NOT_APPLICABLE(OPERATION_COORDINATION_EVIDENCE)`',
            'a mutex, lease, fencing token, idempotency key, and cross-system transaction remain distinct claims.',
        ],
        $installedFramework . '/docs/coordination.md' => [
            '# Application-owned operation coordination',
            'PHPThis provides no atomic-lock helper, mutex, lease, fencing-token service, distributed-lock abstraction, coordination facade, driver, or discovery mechanism.',
            '**Critical section** names the exact interval',
            '**Mutual exclusion or mutex** means cooperating contenders',
            '**Lease** is expiring ownership.',
            '**Fencing** requires a monotonically ordered token',
            '**Idempotency or duplicate safety** bounds the effect of repeated work.',
            '**Cross-system atomicity** requires one proved transaction or protocol',
            '## Record one finite operation',
            '`NOT_APPLICABLE(OPERATION_COORDINATION)`',
            'owner-checked release when the selected mechanism supports them',
            'maximum admitted work duration or an explicit `UNPROVED` duration limitation',
            '## Bounded Redis schedule-lease reference',
            'one nonblocking `SET key token NX PX 30000`',
            'no proved numeric wall-clock maximum, so duration is `UNPROVED`',
            'Copy the reasoning fields, never the mechanism by default.',
            'This guidance adds no framework class, interface, trait, helper, facade, service, driver, registry, discovery, configuration, runtime dependency',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            '## Optional application-owned operation coordination',
            'An application with no standalone operation-specific coordination records `NOT_APPLICABLE(OPERATION_COORDINATION)` in `.ai/operations.md`.',
            'Fencing requires an ordered token that every protected downstream effect validates',
            'ADR 028 remains one bounded Redis schedule-lease example, not a portable mechanism.',
            'This operation-coordination guidance adds no accepted PHP syntax, checker rule, Contract or Strict Profile version, diagnostic, runtime API, or dependency.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Adopt, change, explain, or review an atomic lock, mutex, mutual exclusion, lease, critical section, or application coordination boundary | `docs/coordination.md`;',
            'verify that no framework helper, portable distributed-lock claim, or duplicate context owner was introduced',
        ],
        $installedFramework . '/docs/redis-coordination.md' => [
            'start with [Application-owned operation coordination](coordination.md).',
            'ADR 028 remains a bounded `schedule:run` reference only',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Adopt or change an atomic lock, mutex, mutual exclusion, lease, critical section, or application coordination boundary | installed `vendor/phpthis/framework/docs/coordination.md` |',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            '## Operation-specific coordination',
            '{{OPERATION_COORDINATION_RECORDS_OR_NOT_APPLICABLE}}',
            'references to the real concurrency, contention, expiry or cleanup, stale-owner, process-termination, outage, recovery and topology evidence owned by `.ai/testing.md`',
            'Keep a scheduled pass\'s mechanism in `.ai/cli.md`, a migration writer\'s mechanism in `.ai/migrations.md`, durable-job ownership in `.ai/jobs.md`',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            '{{OPERATION_COORDINATION_TEST_COMMAND_OR_NOT_APPLICABLE}}',
            'Every standalone operation-coordination adoption proves the exact record in `.ai/operations.md`',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'A separate installed distribution proof checks application-owned operation-coordination guidance',
            'It does not exercise a consumer backend, prove atomicity, timing, stale-owner rejection, failover, incident response, or production behavior',
        ],
    ];

    requireInstalledArtifactMarkers(
        $artifactMarkers,
        'application-owned operation coordination guidance',
    );

    $coordinationRuntimePathPattern = '~(?:\A|/)(?:locks?|leases?|mutex(?:es)?|coordination|fencing[-_]?tokens?)(?:/|\.php\z)|(?:\A|/)[A-Za-z0-9_-]*(?:AtomicLock|DistributedLock|Lock|Lease|Mutex|FencingToken|Coordination)(?:(?:Manager|Service|Facade|Driver|Registry|Helper)(?:Interface|Provider|Factory)?|Interface|Provider|Factory)?\.php\z~';
    $forbiddenCoordinationRuntimePathFixtures = [
        'AtomicLock.php',
        'Support/RedisLease.php',
        'Support/CoordinationHelper.php',
        'Support/FencingTokenService.php',
    ];
    $allowedCoordinationRuntimePathFixtures = [
        'Application.php',
        'Http/Request.php',
        'Support/Clock.php',
        'Observability/RequestCoordinator.php',
    ];

    foreach ($forbiddenCoordinationRuntimePathFixtures as $forbiddenCoordinationRuntimePathFixture) {
        if (preg_match($coordinationRuntimePathPattern, $forbiddenCoordinationRuntimePathFixture) !== 1) {
            throw new RuntimeException(
                "Installed coordination runtime-path forbidden fixture was accepted: {$forbiddenCoordinationRuntimePathFixture}",
            );
        }
    }

    foreach ($allowedCoordinationRuntimePathFixtures as $allowedCoordinationRuntimePathFixture) {
        if (preg_match($coordinationRuntimePathPattern, $allowedCoordinationRuntimePathFixture) === 1) {
            throw new RuntimeException(
                "Installed coordination runtime-path allowed fixture was rejected: {$allowedCoordinationRuntimePathFixture}",
            );
        }
    }

    foreach (
        [
            'installed framework' => $installedFramework . '/src',
            'default skeleton' => $project . '/src',
        ] as $runtimeOwner => $runtimeSourceRoot
    ) {
        if (!is_dir($runtimeSourceRoot) || is_link($runtimeSourceRoot)) {
            throw new RuntimeException("Installed coordination proof cannot inspect {$runtimeOwner} source.");
        }

        $runtimeSourceIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($runtimeSourceRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($runtimeSourceIterator as $runtimeSourceEntry) {
            if (!$runtimeSourceEntry instanceof SplFileInfo || !$runtimeSourceEntry->isFile()) {
                continue;
            }

            $runtimeSourcePath = substr(
                $runtimeSourceEntry->getPathname(),
                strlen($runtimeSourceRoot) + 1,
            );

            if (preg_match($coordinationRuntimePathPattern, $runtimeSourcePath) === 1) {
                throw new RuntimeException(
                    "Installed coordination guidance contains a forbidden {$runtimeOwner} runtime mechanism path: {$runtimeSourcePath}",
                );
            }
        }
    }

    foreach (
        [
            $project . '/.ai/coordination.md',
            $installedFramework . '/templates/application/.ai/coordination.md',
            $installedFramework . '/src/AtomicLock.php',
            $installedFramework . '/src/DistributedLock.php',
            $installedFramework . '/src/Lock.php',
            $installedFramework . '/src/Lease.php',
            $installedFramework . '/src/Mutex.php',
            $installedFramework . '/src/FencingToken.php',
            $installedFramework . '/src/Coordination',
            $installedFramework . '/src/Lock',
            $installedFramework . '/src/Lease',
        ] as $forbiddenCoordinationPath
    ) {
        if (file_exists($forbiddenCoordinationPath) || is_link($forbiddenCoordinationPath)) {
            throw new RuntimeException(
                "Application-owned coordination guidance added a duplicate context owner or framework runtime path: {$forbiddenCoordinationPath}",
            );
        }
    }

    $installedComposer = jsonFile($installedFramework . '/composer.json');
    $installedRuntimeRequirements = $installedComposer['require'] ?? null;

    if (!is_array($installedRuntimeRequirements)) {
        throw new RuntimeException('Installed framework runtime requirements must be an explicit Composer map.');
    }

    $installedRuntimePackages = array_keys($installedRuntimeRequirements);

    foreach ($installedRuntimePackages as $installedRuntimePackage) {
        if (!is_string($installedRuntimePackage)) {
            throw new RuntimeException('Installed framework runtime requirement names must be strings.');
        }
    }

    sort($installedRuntimePackages, SORT_STRING);

    if ($installedRuntimePackages !== ['ext-pdo', 'ext-session', 'php']) {
        throw new RuntimeException(
            'Application-owned coordination guidance must not change framework runtime packages.',
        );
    }

    $consumerComposer = jsonFile($project . '/composer.json');
    $consumerRuntimeRequirements = $consumerComposer['require'] ?? null;

    if (!is_array($consumerRuntimeRequirements)) {
        throw new RuntimeException('Installed skeleton runtime requirements must be an explicit Composer map.');
    }

    $consumerRuntimePackages = array_keys($consumerRuntimeRequirements);

    foreach ($consumerRuntimePackages as $consumerRuntimePackage) {
        if (!is_string($consumerRuntimePackage)) {
            throw new RuntimeException('Installed skeleton runtime requirement names must be strings.');
        }
    }

    sort($consumerRuntimePackages, SORT_STRING);

    if ($consumerRuntimePackages !== ['php', 'phpthis/framework']) {
        throw new RuntimeException(
            'Application-owned coordination guidance must not add a default-skeleton runtime package.',
        );
    }

    fwrite(
        STDOUT,
        "PASS installed application-owned operation coordination guidance distribution\n",
    );
}
