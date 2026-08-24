<?php

declare(strict_types=1);

/**
 * @return array{
 *   publication: non-empty-string,
 *   delivery: non-empty-string,
 *   operations: non-empty-string,
 *   entrypoint: non-empty-string,
 *   composer: non-empty-string
 * }
 */
function installedBackendNeutralJobsVerificationReferences(string $installedFramework): array
{
    $guidePath = $installedFramework . '/docs/jobs/verification.md';
    $guide = file_get_contents($guidePath);

    if (!is_string($guide)) {
        throw new RuntimeException('Unable to read the installed jobs verification reference.');
    }

    $references = [
        'publication' => 'uninitialized',
        'delivery' => 'uninitialized',
        'operations' => 'uninitialized',
        'entrypoint' => 'uninitialized',
        'composer' => 'uninitialized',
    ];

    foreach (
        [
            'publication' => ['## Exact publication module shape', 'php'],
            'delivery' => ['## Exact delivery module shape', 'php'],
            'operations' => ['## Exact operations module shape', 'php'],
            'entrypoint' => ['## Exact ordered entrypoint shape', 'php'],
            'composer' => ['## Exact Composer gate wiring', 'json'],
        ] as $name => [$heading, $language]
    ) {
        if (substr_count($guide, $heading) !== 1) {
            throw new RuntimeException("The installed jobs {$name} reference heading is not unique.");
        }

        $headingOffset = strpos($guide, $heading);

        if ($headingOffset === false) {
            throw new RuntimeException("The installed jobs {$name} reference is missing.");
        }

        $blockMarker = "\n```{$language}\n";
        $blockOffset = strpos($guide, $blockMarker, $headingOffset + strlen($heading));

        if ($blockOffset === false) {
            throw new RuntimeException("The installed jobs {$name} reference block is missing.");
        }

        $sourceOffset = $blockOffset + strlen($blockMarker);
        $sourceEnd = strpos($guide, "\n```", $sourceOffset);

        if ($sourceEnd === false) {
            throw new RuntimeException("The installed jobs {$name} reference is incomplete.");
        }

        $source = substr($guide, $sourceOffset, $sourceEnd - $sourceOffset);

        if ($source === '') {
            throw new RuntimeException("The installed jobs {$name} reference is empty.");
        }

        $references[$name] = $source . "\n";
    }

    return [
        'publication' => $references['publication'],
        'delivery' => $references['delivery'],
        'operations' => $references['operations'],
        'entrypoint' => $references['entrypoint'],
        'composer' => $references['composer'],
    ];
}

/** @return non-empty-string */
function installedBackendNeutralJobsVerificationFixtureModule(
    string $module,
    string $outcome,
): string {
    [$function, $label] = match ($module) {
        'publication' => ['verifyJobsPublication', 'publication'],
        'delivery' => ['verifyJobsDelivery', 'delivery'],
        'operations' => ['verifyJobsOperations', 'operations'],
        default => throw new InvalidArgumentException('Unknown jobs verification fixture module.'),
    };
    $terminal = match ($outcome) {
        'pass' => 'return [];',
        'fail' => "return ['synthetic_{$label}_failure'];",
        'throw' => "throw new RuntimeException('synthetic-secret-{$label}');",
        'exit' => 'exit(0);',
        'completion_bypass' => "\$GLOBALS['jobsVerificationState']->completed = true; exit(0);",
        default => throw new InvalidArgumentException('Unknown jobs verification fixture outcome.'),
    };

    return <<<PHP
<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function {$function}(): array
{
    \$traceBytes = "{$label}\\n";
    \$written = file_put_contents(__DIR__ . '/reachability.log', \$traceBytes, FILE_APPEND);

    if (\$written !== strlen(\$traceBytes)) {
        throw new RuntimeException('Synthetic jobs verification reachability failed.');
    }

    {$terminal}
}
PHP;
}

/**
 * @param array<string, string> $environment
 * @return non-empty-string
 */
function proveInstalledBackendNeutralJobsVerificationReference(
    string $project,
    string $installedFramework,
    string $composerBinary,
    array $environment,
): string {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/docs/decisions/052-backend-neutral-application-owned-durable-jobs.md' => [
            'Status: accepted',
            'Accountable-human approval: 2026-08-13 (Asia/Manila). This approval accepts the decision only; it does not authorize a commit, push, release, or issue closure.',
            'after reported success, the intent/message survives producer or request termination and remains recoverable or deliverable until acknowledgement, terminal outcome, or explicit finite cancel/expiry',
            'maximum handler duration relative to any ownership window, exact extension, renewal, heartbeat or session-liveness owner/cadence/bounds and failure/expiry/actual stale-owner behavior',
            'rejection or fencing only where supported and otherwise bounded overlapping stale work plus duplicate-safe recovery, or explicit non-applicability only when no such mechanism applies',
            'Redis Pub/Sub, process memory and other fire-and-forget paths do not qualify.',
            'This decision adds no checker rule.',
            "the starter's non-adopting behavior remain unchanged.",
        ],
        $installedFramework . '/docs/jobs.md' => [
            'Status: current optional guidance under accepted [ADR 052](decisions/052-backend-neutral-application-owned-durable-jobs.md).',
            'after the application reports publication success, the intent or message survives producer/request termination and remains recoverable or deliverable until acknowledgement, a terminal outcome, or an explicit finite cancellation or expiry',
            'it is not permission to call Redis Pub/Sub, process memory, after-response work or another fire-and-forget path durable.',
            'Where delivery ownership or session liveness expires, record maximum handler duration relative to that window',
            'The accepted [SQLite profile](jobs/sqlite.md) remains the first and only checked profile under its existing ADR 024 evidence;',
            'The stricter exact service/client version and real-service bar above applies to every profile added under ADR 052.',
            'This accepted optional guidance left Consumer Contract version 12 unchanged; current Consumer Contract version 15 carries version 14 and this guidance forward under Strict Profile version 4 and diagnostics `PHT001` through `PHT008`.',
        ],
        $installedFramework . '/docs/jobs/README.md' => [
            'PHPThis currently accepts the optional [backend-neutral contract](../jobs.md) and [verification structure](verification.md) under ADR 052.',
            'Its first and only checked backend-specific profile remains [application-owned SQLite durable jobs](sqlite.md) under ADR 024.',
        ],
        $installedFramework . '/docs/jobs/verification.md' => [
            'Status: current optional guidance under accepted ADR 052. The [checked SQLite profile](sqlite.md) and ADR 024 remain the first and only checked backend-specific evidence.',
            'This structure organizes application evidence. It is not a backend validator, PHPThis checker extension, queue adapter, transport simulation, or proof of production topology.',
            'When no business database commit, outbox or relay applies, the application records its initiating-state semantics, including explicit non-applicability, and the exact direct-publication and recovery mechanism',
            'the isolated real-service gate asserts the selected service or API identity and version, client version, and every safely observable or pinned version-controlled durability, persistence and topology feature',
            'unsupported or drifted versions and features fail closed, unobservable managed-service internals are not claimed, and production topology remains separate deployment evidence;',
            'where an ownership window or session-liveness requirement applies, maximum handler duration is bounded relative to that window',
            'rejection or fencing is required only when supported, otherwise bounded overlapping stale work and duplicate-safe recovery are proved',
            'when a semantic effect depends on ordering, the exact ordering, partition or sequence key and concurrency scope are exercised',
            'an order-independent effect proves its recorded non-applicability;',
            "require dirname(__DIR__) . '/vendor/autoload.php';",
            'first requires one literal application-owned Composer autoload path, then owns the literal ordered module path',
            "return ['jobs_publication_not_implemented'];",
            "return ['jobs_delivery_not_implemented'];",
            "return ['jobs_operations_not_implemented'];",
            'The completion guard converts a module\'s premature `exit(0)`',
            '`NOT_APPLICABLE(JOBS)` and `REFERENCE_ONLY(JOBS_VERIFICATION_STRUCTURE)`',
            'That synthetic pass does not adopt jobs and proves no backend, publication, durability, delivery, idempotency, security or production semantics.',
        ],
        $installedFramework . '/docs/jobs/sqlite.md' => [
            'one accepted durable-job recipe and no framework queue mechanism',
            'one finite complete `UPDATE ... RETURNING` statement',
            'This proves one durable database effect for duplicate deliveries in the exercised SQLite schema.',
        ],
        $installedFramework . '/templates/application/.ai/jobs.md' => [
            '{{JOBS_BACKEND_CLIENT_VERSION_OWNER_OR_NOT_APPLICABLE}}',
            '{{JOBS_DELIVERY_SEMANTICS_OR_NOT_APPLICABLE}}',
            '{{JOBS_VERIFY_BOOTSTRAP_OR_NOT_APPLICABLE}}',
            '{{JOBS_VERIFY_SCRIPT_OR_NOT_APPLICABLE}}',
            '{{JOBS_COMPLETE_GATE_WIRING_OR_NOT_APPLICABLE}}',
            '{{JOBS_REAL_SERVICE_EVIDENCE_OR_NOT_APPLICABLE}}',
            'Status: accepted ADR 052 supplies the optional backend-neutral fields below.',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Accepted ADR 052 optional durable-job Composer evidence script `jobs:verify`: `{{JOBS_VERIFY_SCRIPT_OR_NOT_APPLICABLE}}`',
            'Accepted ADR 052 optional literal `jobs:verify` -> `test` -> complete `check` wiring: `{{JOBS_COMPLETE_GATE_WIRING_OR_NOT_APPLICABLE}}`',
        ],
        $project . '/.ai/jobs.md' => [
            '`NOT_APPLICABLE(JOBS)`',
            'Accepted ADR 052 supplies the optional backend-neutral contract and verification structure.',
            'delivery ordering/concurrency and ownership-window/liveness, effect, retry/terminal, privileged-operation, lifecycle, operations, redaction and real-service evidence decisions.',
            'The canonical application evidence script is `jobs:verify`',
        ],
        $project . '/.ai/testing.md' => [
            'accepted ADR 052 supplies optional backend-neutral requirements, while ADR 024 remains the current first and only checked backend-specific SQLite profile.',
        ],
    ];
    requireInstalledArtifactMarkers($artifactMarkers, 'backend-neutral jobs verification reference');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    foreach (directoryFiles($installedFramework) as $installedFile) {
        if (str_starts_with($installedFile, 'src/Jobs/') || str_starts_with($installedFile, 'src/Queue/')) {
            throw new RuntimeException('The installed framework unexpectedly contains a jobs runtime API.');
        }
    }

    $references = installedBackendNeutralJobsVerificationReferences($installedFramework);
    $expectedReferenceHashes = [
        'publication' => '9366a93aaab5aab3f70ba85c43f5f047e776dbf4b10743f1c826408ea3c2a619',
        'delivery' => '394bf56f72a77cf62ae63ebdcede2f8606182b5e14896feb64286ab2d3db7cb1',
        'operations' => 'aeedbe85e62c851e202de5cd72b6176b08264720773b06346883c2b8c832b890',
        'entrypoint' => '792e604be11c4bacabf763b43afdaf27d0f5ba386891936634c4960b17a4fc69',
        'composer' => '2f977d4257326d50a609a193e918aa858354c8aaeda3d0eb1c2ccae703cf32b5',
    ];

    foreach ($expectedReferenceHashes as $name => $expectedReferenceHash) {
        if (!hash_equals($expectedReferenceHash, hash('sha256', $references[$name]))) {
            throw new RuntimeException("The installed jobs {$name} reference changed.");
        }
    }

    foreach (
        [
            'set_error_handler(' => 1,
            'restore_error_handler();' => 1,
            "throw new ErrorException('Jobs verification runtime warning.', 0, \$severity);" => 1,
            "require dirname(__DIR__) . '/vendor/autoload.php';" => 1,
            "require __DIR__ . '/verify-jobs/publication.php';" => 1,
            "require __DIR__ . '/verify-jobs/delivery.php';" => 1,
            "require __DIR__ . '/verify-jobs/operations.php';" => 1,
            'function runJobsVerificationModules(): array' => 1,
            '$failures = runJobsVerificationModules();' => 1,
            '$jobsVerificationState = new class {' => 1,
            '(static function (): void {' => 1,
        ] as $marker => $expectedCount
    ) {
        if (substr_count($references['entrypoint'], $marker) !== $expectedCount) {
            throw new RuntimeException('The installed jobs entrypoint warning boundary changed.');
        }
    }

    $previousEntrypointMarkerOffset = -1;

    foreach (
        [
            "require dirname(__DIR__) . '/vendor/autoload.php';",
            "require __DIR__ . '/verify-jobs/publication.php';",
            "require __DIR__ . '/verify-jobs/delivery.php';",
            "require __DIR__ . '/verify-jobs/operations.php';",
            '$publicationFailures = verifyJobsPublication();',
            '$deliveryFailures = verifyJobsDelivery();',
            '$operationsFailures = verifyJobsOperations();',
        ] as $orderedEntrypointMarker
    ) {
        $entrypointMarkerOffset = strpos($references['entrypoint'], $orderedEntrypointMarker);

        if (
            $entrypointMarkerOffset === false
            || $entrypointMarkerOffset <= $previousEntrypointMarkerOffset
        ) {
            throw new RuntimeException('The installed jobs entrypoint literal bootstrap or module order changed.');
        }

        $previousEntrypointMarkerOffset = $entrypointMarkerOffset;
    }

    foreach (['@require', 'error_reporting(', "ini_set('display_errors'", 'ini_set("display_errors"'] as $marker) {
        if (str_contains($references['entrypoint'], $marker)) {
            throw new RuntimeException('The installed jobs entrypoint must not broadly suppress diagnostics.');
        }
    }

    $composerReference = json_decode($references['composer'], true, 32, JSON_THROW_ON_ERROR);
    $expectedScripts = [
        'profile' => 'phpthis check',
        'jobs:verify' => 'php tools/verify-jobs.php',
        'test:application' => 'php tests/run.php',
        'test' => ['@jobs:verify', '@test:application'],
        'check' => ['@profile', '@test'],
    ];

    if (
        !is_array($composerReference)
        || array_keys($composerReference) !== ['scripts']
        || ($composerReference['scripts'] ?? null) !== $expectedScripts
    ) {
        throw new RuntimeException('The installed jobs Composer reference changed.');
    }

    $jobsContextPath = $project . '/.ai/jobs.md';
    $composerPath = $project . '/composer.json';
    $toolsPath = $project . '/tools';
    $modulePath = $toolsPath . '/verify-jobs';
    $entrypointPath = $toolsPath . '/verify-jobs.php';
    $publicationPath = $modulePath . '/publication.php';
    $deliveryPath = $modulePath . '/delivery.php';
    $operationsPath = $modulePath . '/operations.php';
    $tracePath = $modulePath . '/reachability.log';
    $originalJobsContext = file_get_contents($jobsContextPath);
    $originalComposer = file_get_contents($composerPath);

    if (
        !is_string($originalJobsContext)
        || !is_string($originalComposer)
        || is_link($jobsContextPath)
        || is_link($composerPath)
        || file_exists($toolsPath)
        || is_link($toolsPath)
    ) {
        throw new RuntimeException('The installed jobs verification proof requires an untouched regular starter.');
    }

    $referenceContext = <<<'MD'
# Installed synthetic jobs verification structure reference

NOT_APPLICABLE(JOBS)
REFERENCE_ONLY(JOBS_VERIFICATION_STRUCTURE)

- This transient non-adopter installs only the exact copyable verifier structure from installed `vendor/phpthis/framework/docs/jobs/verification.md`.
- The entrypoint uses exactly one literal application-owned Composer-autoload path before the three literal modules; it performs no bootstrap discovery or fallback.
- Canonical structure command: `composer jobs:verify`; literal gate chain: `jobs:verify` -> `test` -> complete `check`.
- The unadapted documented modules fail closed. Synthetic replacements prove only literal publication, delivery, operations order; complete reachability; fixed ordinary pass/fail bytes; failure and exception propagation; premature `exit(0)` rejection; and Composer gate wiring.
- No backend, client, service, broker, database publication, outbox, delivery, acknowledgement, redelivery, retry, idempotency, durability, TLS, authorization, failover, capacity, retention, recovery, deployment, or production semantics are adopted or proved.
- The existing accepted SQLite example and ADR 024 remain the sole real checked backend profile.
- No PHPThis checker, PHT diagnostic, runtime dependency, queue abstraction, dispatcher, worker, transport adapter, discovery, or backend rule is added.
MD;

    if (
        str_contains($referenceContext, 'ADOPTED(JOBS')
        || substr_count($referenceContext, 'NOT_APPLICABLE(JOBS)') !== 1
        || substr_count($referenceContext, 'REFERENCE_ONLY(JOBS_VERIFICATION_STRUCTURE)') !== 1
    ) {
        throw new RuntimeException('The installed jobs structure-only context classification changed.');
    }
    $projectComposer = json_decode($originalComposer, true, 32, JSON_THROW_ON_ERROR);
    $projectScripts = is_array($projectComposer) ? ($projectComposer['scripts'] ?? null) : null;

    if (
        !is_array($projectComposer)
        || !is_array($projectScripts)
        || ($projectScripts['test'] ?? null) !== 'php tests/run.php'
    ) {
        throw new RuntimeException('The installed starter test script changed before the jobs structure proof.');
    }

    $projectComposer['scripts'] = $expectedScripts;
    $cleanupFailure = null;

    try {
        writeFile($jobsContextPath, $referenceContext . "\n");
        writeJson($composerPath, $projectComposer);

        if (!mkdir($toolsPath, 0700) || !mkdir($modulePath, 0700)) {
            throw new RuntimeException('Unable to create the installed jobs verification proof paths.');
        }

        writeFile($entrypointPath, $references['entrypoint']);
        writeFile($publicationPath, $references['publication']);
        writeFile($deliveryPath, $references['delivery']);
        writeFile($operationsPath, $references['operations']);

        foreach ([$entrypointPath, $publicationPath, $deliveryPath, $operationsPath] as $phpPath) {
            $lintResult = runProcess([PHP_BINARY, '-l', $phpPath], $project, $environment);
            requireSuccess($lintResult, 'One installed jobs verification reference did not pass PHP syntax checking.');
        }

        $profileResult = runProcess([$project . '/vendor/bin/phpthis', 'check'], $project, $environment);
        requireSuccess($profileResult, 'The installed jobs verification reference failed the maximum consumer profile.');
        requireOutputContains($profileResult, 'PASS PHPThis application check');

        requireExactProcessResult(
            runProcess([PHP_BINARY, $entrypointPath], $project, $environment),
            1,
            '',
            "JOBS VERIFY FAIL\n",
            'The unadapted installed jobs verification reference did not fail closed.',
        );

        if (!unlink($deliveryPath) || file_exists($deliveryPath) || is_link($deliveryPath)) {
            throw new RuntimeException('Unable to remove one installed jobs verification module for the missing-module control.');
        }

        try {
            $missingModuleResult = runProcess([PHP_BINARY, $entrypointPath], $project, $environment);
            requireExactProcessResult(
                $missingModuleResult,
                1,
                '',
                "JOBS VERIFY FAIL\n",
                'A missing installed jobs verification module was not redacted exactly.',
            );
            requireOutputNotContains($missingModuleResult, $deliveryPath);

            if (file_exists($deliveryPath)) {
                throw new RuntimeException('The missing-module control unexpectedly recreated its module.');
            }
        } finally {
            writeFile($deliveryPath, $references['delivery']);
        }

        $writeFixtureModules = static function (
            string $publicationOutcome,
            string $deliveryOutcome,
            string $operationsOutcome,
        ) use ($publicationPath, $deliveryPath, $operationsPath, $tracePath): void {
            if ((is_file($tracePath) || is_link($tracePath)) && !unlink($tracePath)) {
                throw new RuntimeException('Unable to reset installed jobs verification reachability.');
            }

            writeFile(
                $publicationPath,
                installedBackendNeutralJobsVerificationFixtureModule('publication', $publicationOutcome),
            );
            writeFile(
                $deliveryPath,
                installedBackendNeutralJobsVerificationFixtureModule('delivery', $deliveryOutcome),
            );
            writeFile(
                $operationsPath,
                installedBackendNeutralJobsVerificationFixtureModule('operations', $operationsOutcome),
            );
        };
        $requireTrace = static function (string $expected) use ($tracePath): void {
            $trace = file_get_contents($tracePath);

            if (!is_string($trace) || $trace !== $expected) {
                throw new RuntimeException('Installed jobs verification module reachability or order changed.');
            }
        };

        $writeFixtureModules('pass', 'pass', 'pass');
        requireExactProcessResult(
            runProcess([PHP_BINARY, $entrypointPath], $project, $environment),
            0,
            "JOBS VERIFY PASS\n",
            '',
            'The installed jobs verification structure did not select exact success.',
        );
        $requireTrace("publication\ndelivery\noperations\n");

        $writeFixtureModules('pass', 'pass', 'pass');
        $namedCommandResult = runProcess(
            composerCommand($composerBinary, ['jobs:verify']),
            $project,
            $environment,
        );
        requireSuccess($namedCommandResult, 'The exact installed Composer jobs:verify command failed.');
        requireOutputContains($namedCommandResult, "JOBS VERIFY PASS\n");
        requireOutputNotContains($namedCommandResult, "JOBS VERIFY FAIL\n");
        $requireTrace("publication\ndelivery\noperations\n");

        $writeFixtureModules('pass', 'pass', 'pass');
        $completeResult = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireSuccess($completeResult, 'The installed jobs verification structure failed its complete gate.');
        requireOutputContains($completeResult, "JOBS VERIFY PASS\n");
        requireOutputContains($completeResult, 'PASS application behavior and front controller');

        foreach (['publication', 'delivery', 'operations'] as $failingModule) {
            $writeFixtureModules(
                $failingModule === 'publication' ? 'fail' : 'pass',
                $failingModule === 'delivery' ? 'fail' : 'pass',
                $failingModule === 'operations' ? 'fail' : 'pass',
            );
            requireExactProcessResult(
                runProcess([PHP_BINARY, $entrypointPath], $project, $environment),
                1,
                '',
                "JOBS VERIFY FAIL\n",
                "The installed jobs {$failingModule} failure did not propagate exactly.",
            );
            $requireTrace("publication\ndelivery\noperations\n");
        }

        $writeFixtureModules('pass', 'fail', 'pass');
        $failedCompleteResult = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireFailure($failedCompleteResult, 'A jobs verification failure did not fail the complete gate.');
        requireOutputContains($failedCompleteResult, "JOBS VERIFY FAIL\n");
        requireOutputNotContains($failedCompleteResult, 'PASS application behavior and front controller');

        $writeFixtureModules('pass', 'throw', 'pass');
        $throwResult = runProcess([PHP_BINARY, $entrypointPath], $project, $environment);
        requireExactProcessResult(
            $throwResult,
            1,
            '',
            "JOBS VERIFY FAIL\n",
            'An installed jobs verification exception was not redacted exactly.',
        );
        requireOutputNotContains($throwResult, 'synthetic-secret-delivery');
        $requireTrace("publication\ndelivery\n");

        $writeFixtureModules('pass', 'exit', 'pass');
        requireExactProcessResult(
            runProcess([PHP_BINARY, $entrypointPath], $project, $environment),
            1,
            '',
            "JOBS VERIFY FAIL\n",
            'An installed jobs verification premature exit was accepted.',
        );
        $requireTrace("publication\ndelivery\n");

        $writeFixtureModules('pass', 'completion_bypass', 'pass');
        $completionBypassResult = runProcess([PHP_BINARY, $entrypointPath], $project, $environment);
        requireExactProcessResult(
            $completionBypassResult,
            1,
            '',
            "JOBS VERIFY FAIL\n",
            'An installed module reached the outer jobs verification completion state.',
        );
        requireOutputNotContains($completionBypassResult, 'jobsVerificationState');
        $requireTrace("publication\ndelivery\n");
    } finally {
        try {
            if (is_dir($toolsPath)) {
                removeDirectory($toolsPath);
            }

            if (file_exists($toolsPath)) {
                throw new RuntimeException('Installed jobs verification proof cleanup left a tools path.');
            }
        } catch (Throwable $failure) {
            $cleanupFailure = $failure;
        } finally {
            writeFile($jobsContextPath, $originalJobsContext);
            writeFile($composerPath, $originalComposer);

            if (
                file_get_contents($jobsContextPath) !== $originalJobsContext
                || file_get_contents($composerPath) !== $originalComposer
            ) {
                throw new RuntimeException('Installed jobs verification proof did not restore starter files exactly.');
            }
        }

        if ($cleanupFailure instanceof Throwable) {
            throw $cleanupFailure;
        }
    }

    fwrite(STDOUT, "PASS installed backend-neutral jobs verification structure\n");

    return 'installed-backend-neutral-jobs-verification-reference-proved';
}
