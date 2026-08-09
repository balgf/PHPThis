<?php

declare(strict_types=1);

use PHPThis\Verification\SyntaxProfile;

/**
 * @return list<string>
 */
function distributionGuardrailFailures(
    string $root,
    int &$markdownCount,
    int &$phpCount,
    int &$coreLines,
): array {
    $phpFiles = [];
    $markdownFiles = [];
    $failures = [];

    $nativeSessionFunctions = [
        'session_abort',
        'session_cache_expire',
        'session_cache_limiter',
        'session_commit',
        'session_create_id',
        'session_decode',
        'session_destroy',
        'session_encode',
        'session_gc',
        'session_get_cookie_params',
        'session_id',
        'session_module_name',
        'session_name',
        'session_regenerate_id',
        'session_register_shutdown',
        'session_reset',
        'session_save_path',
        'session_set_cookie_params',
        'session_set_save_handler',
        'session_start',
        'session_status',
        'session_unset',
        'session_write_close',
    ];

    $packageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($packageInventory) && preg_match('/^example\//m', $packageInventory) === 1) {
        $failures[] = 'The application-owned example must remain excluded from the framework release inventory.';
    }

    $composerPath = $root . '/composer.json';
    $composerContents = file_get_contents($composerPath);

    if (!is_string($composerContents)) {
        $failures[] = 'Cannot read composer.json.';
    } else {
        $composer = json_decode($composerContents, true);
        $runtimeRequirements = is_array($composer) ? ($composer['require'] ?? null) : null;
        $developmentRequirements = is_array($composer) ? ($composer['require-dev'] ?? null) : null;
        $scripts = is_array($composer) ? ($composer['scripts'] ?? null) : null;
        $check = is_array($scripts) ? ($scripts['check'] ?? null) : null;
        $archive = is_array($composer) ? ($composer['archive'] ?? null) : null;
        $archiveExclusions = is_array($archive) ? ($archive['exclude'] ?? null) : null;

        if (!is_array($runtimeRequirements)) {
            $failures[] = 'Framework runtime requirements must remain an explicit Composer map.';
        } else {
            foreach (array_keys($runtimeRequirements) as $runtimePackage) {
                if (
                    !is_string($runtimePackage)
                    || (
                        $runtimePackage !== 'php'
                        && !str_starts_with($runtimePackage, 'ext-')
                    )
                ) {
                    $failures[] = "Framework runtime dependencies must remain native PHP and extensions: {$runtimePackage}.";
                }
            }
        }

        if (
            !is_array($developmentRequirements)
            || ($developmentRequirements['phpunit/phpunit'] ?? null) !== '^13.0'
        ) {
            $failures[] = 'PHPUnit 13 must remain an exact framework-maintainer require-dev dependency.';
        }

        foreach (is_array($runtimeRequirements) ? array_keys($runtimeRequirements) : [] as $runtimePackage) {
            if (
                is_string($runtimePackage)
                && (
                    str_starts_with($runtimePackage, 'phpunit/')
                    || str_starts_with($runtimePackage, 'pestphp/')
                )
            ) {
                $failures[] = "Test runner must not become a framework runtime dependency: {$runtimePackage}.";
            }
        }

        foreach (is_array($developmentRequirements) ? array_keys($developmentRequirements) : [] as $developmentPackage) {
            if (is_string($developmentPackage) && str_starts_with($developmentPackage, 'pestphp/')) {
                $failures[] = "Pest is outside the framework-maintainer runner decision: {$developmentPackage}.";
            }
        }

        if (
            !is_array($scripts)
            || ($scripts['test'] ?? null) !== 'php vendor/bin/phpunit --configuration=phpunit.xml.dist'
        ) {
            $failures[] = 'composer test must run the canonical PHPUnit framework-maintainer suite.';
        }

        if (
            !is_array($scripts)
            || ($scripts['test:coverage'] ?? null)
                !== 'php vendor/bin/phpunit --configuration=phpunit.xml.dist --coverage-text --coverage-clover=.phpunit.cache/coverage.xml'
        ) {
            $failures[] = 'composer test:coverage must produce report-only text and Clover coverage.';
        }

        $expectedCheckStages = [
            '@guard',
            '@analyse',
            '@test:profile',
            '@test:duplication',
            '@test:consumer',
            '@test:database-drivers',
            '@test:query-scaling',
            '@test',
        ];

        if ($check !== $expectedCheckStages) {
            $failures[] = 'composer check must preserve every canonical stage in its reviewed order.';
        }

        if (
            !is_array($archiveExclusions)
            || !in_array('/phpunit.xml.dist', $archiveExclusions, true)
            || !in_array('/.phpunit.cache', $archiveExclusions, true)
            || !in_array('/tests', $archiveExclusions, true)
        ) {
            $failures[] = 'Framework-maintainer tests, PHPUnit configuration, and reports must remain outside Composer package archives.';
        }

        if (!is_array($scripts) || ($scripts['test:database-drivers'] ?? null) !== 'php tools/test-database-drivers.php') {
            $failures[] = 'composer.json must define the canonical database-driver certification script.';
        }

        if (!is_array($check) || !in_array('@test:database-drivers', $check, true)) {
            $failures[] = 'composer check must include database-driver certification.';
        }
    }

    $skeletonComposerContents = file_get_contents($root . '/skeleton/composer.json');
    $skeletonComposer = is_string($skeletonComposerContents)
        ? json_decode($skeletonComposerContents, true)
        : null;
    $skeletonRuntimeRequirements = is_array($skeletonComposer) ? ($skeletonComposer['require'] ?? null) : null;
    $skeletonDevelopmentRequirements = is_array($skeletonComposer) ? ($skeletonComposer['require-dev'] ?? null) : null;
    $skeletonScripts = is_array($skeletonComposer) ? ($skeletonComposer['scripts'] ?? null) : null;

    if (!is_array($skeletonRuntimeRequirements)) {
        $failures[] = 'The default skeleton runtime requirements must remain an explicit Composer map.';
    } else {
        $skeletonRuntimePackages = array_keys($skeletonRuntimeRequirements);
        sort($skeletonRuntimePackages, SORT_STRING);

        if ($skeletonRuntimePackages !== ['php', 'phpthis/framework']) {
            $failures[] = 'The default skeleton must require only PHP and phpthis/framework.';
        }
    }

    foreach (
        [
            is_array($skeletonRuntimeRequirements) ? $skeletonRuntimeRequirements : [],
            is_array($skeletonDevelopmentRequirements) ? $skeletonDevelopmentRequirements : [],
        ] as $skeletonRequirements
    ) {
        foreach (array_keys($skeletonRequirements) as $skeletonPackage) {
            if (
                is_string($skeletonPackage)
                && (
                    str_starts_with($skeletonPackage, 'phpunit/')
                    || str_starts_with($skeletonPackage, 'pestphp/')
                )
            ) {
                $failures[] = "The skeleton must keep its application-owned test-runner choice: {$skeletonPackage}.";
            }
        }
    }

    if (!is_array($skeletonScripts) || ($skeletonScripts['test'] ?? null) !== 'php tests/run.php') {
        $failures[] = 'The skeleton must retain its application-owned example test command.';
    }

    $gitAttributes = file_get_contents($root . '/.gitattributes');

    if (
        !is_string($gitAttributes)
        || preg_match('/^\/phpunit\.xml\.dist export-ignore$/m', $gitAttributes) !== 1
        || preg_match('/^\/\.phpunit\.cache export-ignore$/m', $gitAttributes) !== 1
        || preg_match('/^\/tests export-ignore$/m', $gitAttributes) !== 1
    ) {
        $failures[] = 'Framework-maintainer tests, PHPUnit configuration, and reports must remain outside Git exports.';
    }

    $phpunitConfig = file_get_contents($root . '/phpunit.xml.dist');

    if (!is_string($phpunitConfig)) {
        $failures[] = 'Cannot read phpunit.xml.dist.';
    } else {
        foreach (
            [
                'https://schema.phpunit.de/13.2/phpunit.xsd',
                'cacheDirectory=".phpunit.cache"',
                'failOnRisky="true"',
                'failOnWarning="true"',
                '<file>tests/FrameworkBehaviorTest.php</file>',
                '<directory>src</directory>',
                '<directory includeInCodeCoverage="false">example/src</directory>',
                '<coverage includeUncoveredFiles="true"/>',
                '<junit outputFile=".phpunit.cache/junit.xml"/>',
            ] as $phpunitConfigMarker
        ) {
            if (!str_contains($phpunitConfig, $phpunitConfigMarker)) {
                $failures[] = "The framework-maintainer PHPUnit configuration is missing: {$phpunitConfigMarker}.";
            }
        }

        if (
            str_contains($phpunitConfig, '<directory>tests</directory>')
            || substr_count($phpunitConfig, '<file>tests/FrameworkBehaviorTest.php</file>') !== 1
        ) {
            $failures[] = 'PHPUnit must discover only the explicit framework behavior bridge.';
        }
    }

    $maintainerTestPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (
        is_string($maintainerTestPackageInventory)
        && preg_match(
            '/^(?:phpunit\.xml\.dist|tests\/)/m',
            $maintainerTestPackageInventory,
        ) === 1
    ) {
        $failures[] = 'Framework-maintainer test artifacts must remain outside the runtime package inventory.';
    }

    $behaviorInventory = file_get_contents($root . '/tests/behavior-names.txt');

    if (!is_string($behaviorInventory)) {
        $failures[] = 'Cannot read the framework behavior-name inventory.';
    } elseif (
        $behaviorInventory === ''
        || !str_ends_with($behaviorInventory, "\n")
        || str_contains($behaviorInventory, "\r")
    ) {
        $failures[] = 'The framework behavior-name inventory must use non-empty LF-terminated lines.';
    } else {
        $behaviorNames = explode("\n", substr($behaviorInventory, 0, -1));

        if (count($behaviorNames) !== 181 || count(array_unique($behaviorNames)) !== 181) {
            $failures[] = 'The framework suite must preserve exactly 181 unique named framework behaviors.';
        }

        if (
            hash('sha256', $behaviorInventory)
            !== '85878e382942708ea8bf43a063b7c0fede99bf42bf4e39524fa10b655f632d8c'
        ) {
            $failures[] = 'The ordered framework behavior-name inventory changed without an explicit parity decision.';
        }
    }

    $maintainerTestArtifactMarkers = [
        '.ai/README.md' => [
            '| Change maintainer tests or evidence organization | `.ai/testing.md` | applicable concern-owned test file, behavior names, and complete gate |',
        ],
        '.ai/testing.md' => [
            'PHPUnit 13 as a maintainer-only development runner',
            'exactly 181 named behaviors',
            '`tests/run.php` is the explicit ordered loader',
            '`tests/composition.php`, `tests/http-boundary.php`, `tests/routing.php`, `tests/input-projection.php`, `tests/crud.php`, and `tests/database-boundary.php`',
            '`tests/request-reader-support.php`, `tests/process-support.php`, and `tests/create-user-support.php`',
            '`tests/behavior-names.txt` locks the complete behavior order',
            "composer test -- --group routing",
            'migrated query-trace comparison slice',
            'Applications continue to own their test library, runner, organization',
        ],
        'tests/run.php' => [
            "require dirname(__DIR__) . '/autoload.php';",
            "require __DIR__ . '/request-reader-support.php';",
            "require __DIR__ . '/process-support.php';",
            "require __DIR__ . '/create-user-support.php';",
            "require __DIR__ . '/composition.php';",
            "require __DIR__ . '/http-boundary.php';",
            "require __DIR__ . '/routing.php';",
            "require __DIR__ . '/input-projection.php';",
            "require __DIR__ . '/crud.php';",
            "require __DIR__ . '/database-boundary.php';",
            'function frameworkBehaviorDefinitions(): Generator',
            "frameworkBehaviorGroupDefinitions('request-policy', requestPolicyTests())",
            "frameworkBehaviorGroupDefinitions('composition', compositionBehaviorTests())",
            "frameworkBehaviorGroupDefinitions('http-boundary', httpBoundaryBehaviorTests())",
            "frameworkBehaviorGroupDefinitions('routing', routingBehaviorTests())",
            "frameworkBehaviorGroupDefinitions('input-projection', inputProjectionBehaviorTests())",
            "frameworkBehaviorGroupDefinitions('crud', crudBehaviorTests())",
            "frameworkBehaviorGroupDefinitions('database-boundary', databaseBoundaryBehaviorTests())",
            'function frameworkBehaviorGroupDefinitions(string $group, iterable $tests): Generator',
            'function frameworkBehaviorRegistry(): array',
            'function frameworkBehaviorTests(): array',
            'function frameworkBehaviorGroups(): array',
            'function frameworkBehaviorNamesForGroup(string $group): array',
            'function frameworkBehaviorInventory(): array',
            'array_key_exists($name, $registered)',
            '85878e382942708ea8bf43a063b7c0fede99bf42bf4e39524fa10b655f632d8c',
        ],
        'tests/composition.php' => [
            'function compositionBehaviorTests(): Generator',
        ],
        'tests/http-boundary.php' => [
            'function httpBoundaryBehaviorTests(): Generator',
        ],
        'tests/routing.php' => [
            'function routingBehaviorTests(): Generator',
        ],
        'tests/input-projection.php' => [
            'function inputProjectionBehaviorTests(): Generator',
            'ApplicationComposition::errorResponses()',
        ],
        'tests/crud.php' => [
            'function crudBehaviorTests(): Generator',
            'function runListUsersPageScenario(string $databasePath, ?string $afterUserId): array',
            'function runCreateUserScenario(string $name, int $preexistingUsers): array',
        ],
        'tests/database-boundary.php' => [
            'function databaseBoundaryBehaviorTests(): Generator',
            'Assert::assertSame(',
        ],
        'tests/request-reader-support.php' => [
            'function requestReaderForBody(string $body, int $maximumBodyBytes): RequestReader',
        ],
        'tests/process-support.php' => [
            'function runIsolatedPhpTest(string $path, array $arguments = []): array',
        ],
        'tests/create-user-support.php' => [
            'final readonly class RunTestAllowCreateUserPolicy implements',
            'function createUserTestHandler(CreateUserOperation $operation): CreateUserHandler',
            'function invalidCreateUserCases(): array',
            'function structurallyInvalidCreateUserBodies(): array',
            'function unacceptableCreateUserValueBodies(): array',
            'function createUserSecretProbe(): string',
            'function exactCreateUserBody(int $bytes): string',
            'function createUserDatabaseFixture(string $name, int $userCount, bool $seedEvents): string',
        ],
        'tests/FrameworkBehaviorTest.php' => [
            "#[Group('request-policy')]",
            "#[Group('routing')]",
            "#[Group('database-boundary')]",
            "#[Group('parity')]",
            "#[TestDox('\$_dataName')]",
            'frameworkBehaviorTests()[$name]',
            '$this->addToAssertionCount(1);',
            'yield $name => [$name];',
            'testReviewedInventoryAndGroupOrderMatchTheRegistry',
            'Expected focused groups to flatten to the exact reviewed behavior inventory.',
        ],
        'tests/request-policy.php' => [
            'function requestPolicyTests(): Generator',
        ],
        'tests/observability.php' => [
            'function observabilityTests(): Generator',
        ],
        'tests/jobs.php' => [
            'function jobTests(): Generator',
        ],
        'tests/cli.php' => [
            'function cliTests(): Generator',
        ],
        'tests/migrations.php' => [
            'function migrationTests(): Generator',
        ],
        'tests/document-files.php' => [
            'function documentFileTests(): Generator',
        ],
        'tests/cache.php' => [
            'function cacheTests(): Generator',
        ],
        'tests/redis-coordination.php' => [
            'function redisCoordinationTests(): Generator',
        ],
        'tests/consumer-profile.php' => [
            'function consumerProfileTests(): Generator',
        ],
        'tests/handler-decorator.php' => [
            'function handlerDecoratorTests(): Generator',
        ],
        'ROADMAP.md' => [
            'adopt PHPUnit 13 only for the framework-maintainer suite',
            'exact-name and coherent-group selection',
            'application-owned consumer test choices',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $maintainerTestArtifactMarkers, 'framework-maintainer test', $failures);

    $phpunitBehaviorBridge = file_get_contents($root . '/tests/FrameworkBehaviorTest.php');
    /** @var array<non-empty-string, array{test_method: non-empty-string, provider: non-empty-string}> $expectedPhpunitGroupBridges */
    $expectedPhpunitGroupBridges = [
        'request-policy' => [
            'test_method' => 'testRequestPolicyBehavior',
            'provider' => 'requestPolicyProvider',
        ],
        'observability' => [
            'test_method' => 'testObservabilityBehavior',
            'provider' => 'observabilityProvider',
        ],
        'jobs' => [
            'test_method' => 'testJobBehavior',
            'provider' => 'jobProvider',
        ],
        'cli' => [
            'test_method' => 'testCliBehavior',
            'provider' => 'cliProvider',
        ],
        'migrations' => [
            'test_method' => 'testMigrationBehavior',
            'provider' => 'migrationProvider',
        ],
        'document-files' => [
            'test_method' => 'testDocumentFileBehavior',
            'provider' => 'documentFileProvider',
        ],
        'cache' => [
            'test_method' => 'testCacheBehavior',
            'provider' => 'cacheProvider',
        ],
        'redis-coordination' => [
            'test_method' => 'testRedisCoordinationBehavior',
            'provider' => 'redisCoordinationProvider',
        ],
        'consumer-profile' => [
            'test_method' => 'testConsumerProfileBehavior',
            'provider' => 'consumerProfileProvider',
        ],
        'handler-decorator' => [
            'test_method' => 'testHandlerDecoratorBehavior',
            'provider' => 'handlerDecoratorProvider',
        ],
        'composition' => [
            'test_method' => 'testCompositionBehavior',
            'provider' => 'compositionProvider',
        ],
        'http-boundary' => [
            'test_method' => 'testHttpBoundaryBehavior',
            'provider' => 'httpBoundaryProvider',
        ],
        'routing' => [
            'test_method' => 'testRoutingBehavior',
            'provider' => 'routingProvider',
        ],
        'input-projection' => [
            'test_method' => 'testInputProjectionBehavior',
            'provider' => 'inputProjectionProvider',
        ],
        'crud' => [
            'test_method' => 'testCrudBehavior',
            'provider' => 'crudProvider',
        ],
        'database-boundary' => [
            'test_method' => 'testDatabaseBoundaryBehavior',
            'provider' => 'databaseBoundaryProvider',
        ],
    ];

    if (!is_string($phpunitBehaviorBridge)) {
        $failures[] = 'Cannot read the PHPUnit framework behavior bridge.';
    } else {
        foreach ($expectedPhpunitGroupBridges as $group => $bridge) {
            $testMarker = sprintf(
                "#[Group('%s')]\n"
                . "    #[DataProvider('%s')]\n"
                . "    #[TestDox('\$_dataName')]\n"
                . "    public function %s(string \$name): void\n"
                . "    {\n"
                . "        \$this->runBehavior(\$name);\n"
                . '    }',
                $group,
                $bridge['provider'],
                $bridge['test_method'],
            );
            $providerMarker = sprintf(
                "public static function %s(): Generator\n"
                . "    {\n"
                . "        yield from self::groupedBehaviorProvider('%s');\n"
                . '    }',
                $bridge['provider'],
                $group,
            );

            if (
                !str_contains($phpunitBehaviorBridge, $testMarker)
                || !str_contains($phpunitBehaviorBridge, $providerMarker)
            ) {
                $failures[] = "PHPUnit must retain the complete test/provider bridge for group {$group}.";
            }
        }

        if (
            substr_count($phpunitBehaviorBridge, "#[Group('") !== 17
            || substr_count($phpunitBehaviorBridge, "#[DataProvider('") !== 16
            || substr_count($phpunitBehaviorBridge, 'yield from self::groupedBehaviorProvider(') !== 16
        ) {
            $failures[] = 'PHPUnit must expose exactly 16 behavior groups and one parity group.';
        }
    }

    $frameworkBehaviorRegistry = file_get_contents($root . '/tests/run.php');

    if (
        is_string($frameworkBehaviorRegistry)
        && str_contains($frameworkBehaviorRegistry, 'fwrite(STDOUT, "PASS {$name}')
    ) {
        $failures[] = 'The removed custom framework test execution loop must not return.';
    }

    if (is_string($frameworkBehaviorRegistry)) {
        /** @var list<array{depth: int, statement: non-empty-string}> $expectedFrameworkTestIncludes */
        $expectedFrameworkTestIncludes = [
            ['depth' => 0, 'statement' => "requiredirname(__DIR__).'/autoload.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/request-reader-support.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/process-support.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/create-user-support.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/request-policy.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/observability.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/jobs.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/cli.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/migrations.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/document-files.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/cache.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/redis-coordination.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/consumer-profile.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/handler-decorator.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/composition.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/http-boundary.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/routing.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/input-projection.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/crud.php';"],
            ['depth' => 0, 'statement' => "require__DIR__.'/database-boundary.php';"],
        ];
        /** @var list<array{depth: int, yielded: bool, call: non-empty-string}> $expectedFrameworkBehaviorCalls */
        $expectedFrameworkBehaviorCalls = [
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('request-policy',requestPolicyTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('observability',observabilityTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('jobs',jobTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('cli',cliTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('migrations',migrationTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('document-files',documentFileTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('cache',cacheTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('redis-coordination',redisCoordinationTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('consumer-profile',consumerProfileTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('handler-decorator',handlerDecoratorTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('composition',compositionBehaviorTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('http-boundary',httpBoundaryBehaviorTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('routing',routingBehaviorTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('input-projection',inputProjectionBehaviorTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('crud',crudBehaviorTests())",
            ],
            [
                'depth' => 1,
                'yielded' => true,
                'call' => "frameworkBehaviorGroupDefinitions('database-boundary',databaseBoundaryBehaviorTests())",
            ],
        ];
        $frameworkBehaviorTokens = token_get_all($frameworkBehaviorRegistry);
        /** @var list<int> $frameworkBehaviorDefinitionIndexes */
        $frameworkBehaviorDefinitionIndexes = [];
        $frameworkBehaviorDeclareIndex = null;
        $frameworkBehaviorIncludeCount = 0;
        $frameworkBehaviorGroupIdentifierCount = 0;

        foreach ($frameworkBehaviorTokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_DECLARE && $frameworkBehaviorDeclareIndex === null) {
                $frameworkBehaviorDeclareIndex = $index;
            }

            if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                $frameworkBehaviorIncludeCount++;
            }

            if (
                in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE], true)
                && str_ends_with(strtolower(ltrim($token[1], '\\')), 'frameworkbehaviorgroupdefinitions')
            ) {
                $identifier = ltrim($token[1], '\\');
                $namespaceSeparator = strrpos($identifier, '\\');
                $identifier = $namespaceSeparator === false
                    ? $identifier
                    : substr($identifier, $namespaceSeparator + 1);

                if (strtolower($identifier) === 'frameworkbehaviorgroupdefinitions') {
                    $frameworkBehaviorGroupIdentifierCount++;
                }
            }

            if ($token[0] !== T_FUNCTION) {
                continue;
            }

            $nameIndex = routingNextSignificantTokenIndex($frameworkBehaviorTokens, $index + 1);

            if (
                $nameIndex !== null
                && routingTokenText($frameworkBehaviorTokens[$nameIndex]) === 'frameworkBehaviorDefinitions'
            ) {
                $frameworkBehaviorDefinitionIndexes[] = $index;
            }
        }

        $expectedFrameworkTestPreamble = 'declare(strict_types=1);';

        foreach ($expectedFrameworkTestIncludes as $expectedFrameworkTestInclude) {
            $expectedFrameworkTestPreamble .= $expectedFrameworkTestInclude['statement'];
        }

        $actualFrameworkTestPreamble = '';
        $frameworkBehaviorDefinitionIndex = count($frameworkBehaviorDefinitionIndexes) === 1
            ? $frameworkBehaviorDefinitionIndexes[0]
            : null;

        if ($frameworkBehaviorDeclareIndex !== null && $frameworkBehaviorDefinitionIndex !== null) {
            for (
                $index = $frameworkBehaviorDeclareIndex;
                $index < $frameworkBehaviorDefinitionIndex;
                $index++
            ) {
                $token = $frameworkBehaviorTokens[$index];

                if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $actualFrameworkTestPreamble .= routingTokenText($token);
            }
        }

        if (
            $actualFrameworkTestPreamble !== $expectedFrameworkTestPreamble
            || $frameworkBehaviorIncludeCount !== count($expectedFrameworkTestIncludes)
        ) {
            $failures[] = 'The framework test loader include manifest must remain explicit, literal, top-level, and ordered.';
        }

        $expectedFrameworkBehaviorBody = '';

        foreach ($expectedFrameworkBehaviorCalls as $expectedFrameworkBehaviorCall) {
            $expectedFrameworkBehaviorBody .= 'yield from' . $expectedFrameworkBehaviorCall['call'] . ';';
        }

        $actualFrameworkBehaviorBody = '';

        if ($frameworkBehaviorDefinitionIndex !== null) {
            $bodyOpenIndex = null;

            for (
                $index = $frameworkBehaviorDefinitionIndex + 1, $count = count($frameworkBehaviorTokens);
                $index < $count;
                $index++
            ) {
                if (routingTokenText($frameworkBehaviorTokens[$index]) === '{') {
                    $bodyOpenIndex = $index;
                    break;
                }
            }

            if ($bodyOpenIndex !== null) {
                $bodyDepth = 1;

                for (
                    $index = $bodyOpenIndex + 1, $count = count($frameworkBehaviorTokens);
                    $index < $count;
                    $index++
                ) {
                    $token = $frameworkBehaviorTokens[$index];
                    $tokenText = routingTokenText($token);

                    if ($tokenText === '{') {
                        $bodyDepth++;
                    } elseif ($tokenText === '}') {
                        $bodyDepth--;

                        if ($bodyDepth === 0) {
                            break;
                        }
                    }

                    if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }

                    $actualFrameworkBehaviorBody .= $tokenText;
                }
            }
        }

        if (
            $actualFrameworkBehaviorBody !== $expectedFrameworkBehaviorBody
            || $frameworkBehaviorGroupIdentifierCount !== count($expectedFrameworkBehaviorCalls) + 1
        ) {
            $failures[] = 'The framework behavior group manifest must remain explicit, yielded, and ordered.';
        }

        foreach (
            [
                'glob(',
                'scandir(',
                'opendir(',
                'readdir(',
                'DirectoryIterator',
                'FilesystemIterator',
            ] as $testDiscoveryMarker
        ) {
            if (str_contains($frameworkBehaviorRegistry, $testDiscoveryMarker)) {
                $failures[] = "The framework test loader must not discover files dynamically: {$testDiscoveryMarker}.";
            }
        }
    }

    $ciPath = $root . '/.github/workflows/ci.yml';
    $ciContents = file_get_contents($ciPath);

    if (!is_string($ciContents)) {
        $failures[] = 'Cannot read .github/workflows/ci.yml.';
    } elseif (
        !str_contains($ciContents, 'PHPTHIS_DATABASE_TEST_DRIVERS: sqlite,mysql,pgsql')
        || !str_contains($ciContents, 'image: mysql:8.4')
        || !str_contains($ciContents, 'image: postgres:17')
        || !str_contains($ciContents, 'run: composer test:database-drivers')
        || !str_contains($ciContents, "PHPTHIS_MYSQL_DSN: 'mysql:")
        || !str_contains($ciContents, "PHPTHIS_PGSQL_DSN: 'pgsql:")
    ) {
        $failures[] = 'CI must preserve SQLite, MySQL, and PostgreSQL PDO transport certification.';
    }

    if (
        is_string($ciContents)
        && (
            substr_count($ciContents, 'coverage: pcov') !== 1
            || !str_contains($ciContents, 'run: composer test:coverage')
            || !str_contains($ciContents, 'uses: actions/upload-artifact@v4')
            || !str_contains($ciContents, '.phpunit.cache/junit.xml')
            || !str_contains($ciContents, '.phpunit.cache/coverage.xml')
            || !str_contains($ciContents, 'if-no-files-found: warn')
        )
    ) {
        $failures[] = 'CI must retain report-only PHPUnit coverage and machine-readable test artifacts.';
    }

    $consumerProfileArtifactMarkers = [
        '.ai/README.md' => [
            '| Review the consumer capability profile | `.ai/consumer-profile.md` | checked-in application proof and affected current guides |',
        ],
        '.ai/consumer-profile.md' => [
            'framework behavior lives only in `src/` and the Consumer Contract',
            'commit-visible job publication',
            'Do not add an ORM, repository, binding helper',
        ],
        'docs/consumer-profile.md' => [
            '`POST /accounts/{account_id:positive-int}/users`',
            'four complete raw SQL statements',
            'The checked-in HTTP composition remains deny-all.',
            'Framework and skeleton Composer metadata use `~8.4.0`',
            'Typed routing | ADR 019 and `tests/routing.php`',
            'Typed external input | ADR 021 and the Create tests in `tests/input-projection.php`',
        ],
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => [
            'Status: accepted',
            '| #2 | bounded multiple typed routes, ADR 019 | `core` |',
            '| #3 | request policy, ADR 020 | `application pattern` |',
            '| #4 | typed input boundaries, ADR 021 | `application pattern` |',
            '| #5 | finite data paths, ADR 022 | `application pattern` |',
            '| #6 | terminal request summaries, ADR 023 | `application pattern` |',
            '| #7 | SQLite durable jobs, ADR 024 | `application pattern` |',
            '| #8 | explicit CLI and scheduler, ADR 025 | `application pattern` |',
            '| #9 | bounded file transfers, ADR 026 | `core` |',
            '| #10 | explicit SQLite migrations, ADR 027 | `application pattern` |',
            '| #11 | Redis cache and schedule lease, ADR 028 | `application pattern` |',
            '`src/Routing/`; routing and application tests in `tests/routing.php`',
            '`example/src/Users/CreateUser/`; `tests/input-projection.php` and `tests/consumer-profile.php`',
            'No capability has an overall `defer` exit.',
            'The supported PHP runtime is exactly the PHP 8.4.x Composer range `~8.4.0`.',
        ],
        'docs/decisions/README.md' => [
            '`029-alpha-2-consumer-profile-rollup.md`',
        ],
        'docs/evaluation.md' => [
            'The Alpha 2 rollup is recorded in `docs/consumer-profile.md` and ADR 029.',
        ],
        'docs/knowledge-map.md' => [
            'Assess the Alpha 2 consumer profile or a capability exit',
        ],
        'example/src/Users/UserRoutes.php' => [
            'new Route(\'POST\', \'/accounts/{account_id:positive-int}/users\', $createUserHandler)',
        ],
        'example/src/Users/CreateUser/CreateUserHandler.php' => [
            '$this->authenticate->authenticate($request)',
            '$this->resolveTenant->resolve($principal, $accountId)',
            '$this->authorize->authorizeCreate($principal, $tenant)',
            '$command = CreateUserCommand::fromJson($request->body);',
            '$this->createUser->execute($principal, $tenant, $accountId, $command);',
        ],
        'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
            'four-statement transaction',
            'INSERT INTO users (name, email)',
            'INSERT INTO account_users (user_id, account_id)',
            'INSERT INTO user_events (user_id, event_type)',
            'INSERT INTO application_jobs (',
            '$this->connection->commit();',
        ],
        'tests/consumer-profile.php' => [
            'consumer profile composes policy typed input transaction job and correlation',
            'consumer profile denials and invalid input stop before protected SQL',
            'consumer profile job and budget failures roll back every scoped write',
            'consumer profile SQL rejects mismatched tenant and missing actor membership',
            'new QuerySummarySource(\'create_user\', $budget, $queryTrace)',
        ],
        'tests/run.php' => [
            "require __DIR__ . '/consumer-profile.php';",
            "frameworkBehaviorGroupDefinitions('consumer-profile', consumerProfileTests())",
        ],
        'composer.json' => [
            '"php": "~8.4.0"',
        ],
        'skeleton/composer.json' => [
            '"php": "~8.4.0"',
        ],
        '.github/workflows/ci.yml' => [
            "php: ['8.4']",
            'php-version: ${{ matrix.php }}',
        ],
        'tools/package-files.txt' => [
            'docs/consumer-profile.md',
            'docs/decisions/029-alpha-2-consumer-profile-rollup.md',
        ],
        'ROADMAP.md' => [
            'ADR 029 records every Alpha 2 capability exit',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $consumerProfileArtifactMarkers, 'consumer-profile', $failures);

    foreach (['composer.json', 'skeleton/composer.json'] as $phpManifestPath) {
        $manifestContents = file_get_contents($root . '/' . $phpManifestPath);
        $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;
        $requirements = is_array($manifest) ? ($manifest['require'] ?? null) : null;

        if (!is_array($requirements) || ($requirements['php'] ?? null) !== '~8.4.0') {
            $failures[] = "{$phpManifestPath} must support exactly PHP 8.4.x through ~8.4.0.";
        }

        foreach (['require', 'require-dev'] as $dependencySection) {
            $dependencies = is_array($manifest) ? ($manifest[$dependencySection] ?? null) : null;

            foreach (is_array($dependencies) ? array_keys($dependencies) : [] as $dependencyName) {
                if (is_string($dependencyName) && str_contains(strtolower($dependencyName), 'dotenv')) {
                    $failures[] = "{$phpManifestPath} must not add a framework or skeleton dotenv dependency.";
                }
            }
        }
    }

    if (is_string($packageInventory)) {
        $packagePaths = preg_split('/\R/', $packageInventory);

        foreach (is_array($packagePaths) ? $packagePaths : [] as $packagePath) {
            if (frameworkMechanismPathIsForbidden($packagePath)) {
                $failures[] = "Permanent framework boundary forbids packaged runtime mechanism path: {$packagePath}.";
            }
        }
    }

    $fileTransferArtifactMarkers = [
        '.ai/README.md' => [
            '| Change uploads or local-file responses | `.ai/file-transfers.md` | boundary, storage operation, emitter path, and transfer tests |',
        ],
        '.ai/file-transfers.md' => [
            'A `null` multipart limit disables multipart input.',
            'Do not add a generic storage interface, facade, disk registry, binding helper',
            'Do not claim rejection of duplicate raw scalar parts',
            'After headers, do not attempt a replacement response',
            'Do not introduce an ORM',
        ],
        'docs/consumer-contract.md' => [
            '## Optional bounded file transfers',
            'Raw `$_FILES` never enters a handler.',
            'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
        ],
        'docs/decisions/026-bounded-file-transfers.md' => [
            'Status: accepted',
            'Duplicate raw parts using the same scalar name collapse to one normalized entry',
            'The accepted implementation occupies 2,495 physical core lines',
            'PHPThis adds no ORM behavior, automatic or domain binding',
        ],
        'docs/file-transfers/README.md' => [
            'This knowledge set routes an AI through PHPThis\'s one accepted file-transfer path.',
            'The installed example uses a 2 MiB multipart transport ceiling and a separate 1 MiB document limit.',
        ],
        'example/.ai/file-transfers.md' => [
            '`POST /document-files`',
            '`GET /document-files/{file_id:token}`',
            'application.response_emission_failed',
        ],
        'skeleton/.ai/file-transfers.md' => [
            '`NOT_APPLICABLE(FILE_TRANSFER)`',
            'multipart input remains disabled',
        ],
        'templates/application/.ai/file-transfers.md' => [
            '{{FILE_TRANSFER_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{FILE_TRANSFER_EVIDENCE_OR_NOT_APPLICABLE}}',
        ],
        'src/Http/RequestReader.php' => [
            'private ?int $maximumMultipartBytes;',
            'array $parsedFields = [],',
            'array $files = [],',
            'RequestUploadError::tryFrom',
        ],
        'src/Http/ResponseEmitter.php' => [
            'private const int FILE_CHUNK_BYTES = 8_192;',
            'if (headers_sent())',
            'throw new ResponseEmissionFailed(false);',
            'throw new ResponseEmissionFailed(true);',
        ],
        'example/src/DocumentFiles/LocalDocumentFiles.php' => [
            'move_uploaded_file($upload->temporaryPath, $destination)',
            'requirePrivateDirectory($this->directory)',
            "DIRECTORY_SEPARATOR . 'content'",
        ],
        'example/src/DocumentFiles/DownloadDocumentFileHandler.php' => [
            "'Accept-Ranges' => 'none'",
            "'Content-Disposition' => 'attachment; filename=\"document.bin\"'",
        ],
        'example/public/index.php' => [
            '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
            "error_log('application.response_emission_failed')",
            'if (!$failure->responseStarted)',
        ],
        'skeleton/public/index.php' => [
            '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
            "error_log('application.response_emission_failed')",
            'if (!$failure->responseStarted)',
        ],
        'tests/document-files.php' => [
            'real multipart upload and download remain bounded and metadata-blind',
            'large local file emission stays below a fixed memory delta',
            "'scalar-duplicate'",
            "'display_errors=1'",
        ],
        'tests/upload-request-boundary.php' => [
            'Expected multipart input to require an explicit configured cap.',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/026-bounded-file-transfers.md',
            'docs/file-transfers/README.md',
            'src/Http/RequestUpload.php',
            'src/Http/LocalFileBody.php',
            'templates/application/.ai/file-transfers.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $fileTransferArtifactMarkers, 'file-transfer', $failures);

    $consumerContractPath = $root . '/docs/consumer-contract.md';

    if (is_file($consumerContractPath)) {
        $consumerContract = file_get_contents($consumerContractPath);

        if (!is_string($consumerContract)) {
            $failures[] = 'Cannot read docs/consumer-contract.md.';
        } else {
            if (preg_match('/^Contract version: 11$/m', $consumerContract) !== 1) {
                $failures[] = 'docs/consumer-contract.md must declare contract version 11.';
            }

            if (!str_contains($consumerContract, '## AI authoring and human accountability')) {
                $failures[] = 'docs/consumer-contract.md must define the AI authoring and human accountability contract.';
            }

            if (!str_contains($consumerContract, 'docs/knowledge-map.md')) {
                $failures[] = 'docs/consumer-contract.md must route framework questions through docs/knowledge-map.md.';
            }

            if (!str_contains($consumerContract, '`PHT006`')) {
                $failures[] = 'docs/consumer-contract.md must preserve finite SQL enforcement through PHT006.';
            }
        }
    }

    $securityGuidePath = $root . '/docs/security.md';

    if (is_file($securityGuidePath)) {
        $securityGuide = file_get_contents($securityGuidePath);

        if (!is_string($securityGuide)) {
            $failures[] = 'Cannot read docs/security.md.';
        } elseif (
            !str_contains($securityGuide, 'Separate SQL data from SQL structure.')
            || !str_contains($securityGuide, '## Database authority')
            || !str_contains($securityGuide, '## Proof limits')
        ) {
            $failures[] = 'docs/security.md must preserve SQL separation, database authority, and proof limits.';
        }
    }

    $applicationDataTemplatePath = $root . '/templates/application/.ai/data.md';
    $applicationTestingTemplatePath = $root . '/templates/application/.ai/testing.md';

    if (is_file($applicationDataTemplatePath) && is_file($applicationTestingTemplatePath)) {
        $applicationDataTemplate = file_get_contents($applicationDataTemplatePath);
        $applicationTestingTemplate = file_get_contents($applicationTestingTemplatePath);

        if (!is_string($applicationDataTemplate) || !is_string($applicationTestingTemplate)) {
            $failures[] = 'Cannot read the application SQL-safety context templates.';
        } elseif (
            !str_contains($applicationDataTemplate, '## SQL structure and bounded-input policy')
            || !str_contains($applicationDataTemplate, '## Runtime and migration authority')
            || !str_contains($applicationTestingTemplate, 'before the query budget or trace changes')
        ) {
            $failures[] = 'Application context templates must preserve SQL structure, authority, and adversarial evidence.';
        }
    }

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

    $crudGuidanceMarkers = [
        'docs/crud.md' => [
            'The CRUD reference profile is optional application structure. The PHPThis consumer contract and Strict Profile remain mandatory.',
            '## Multiple access surfaces',
            ...$crudAccessSurfaceContractMarkers,
            'The table selects no directory hierarchy.',
            'An application may record one coherent resource-first, surface-first, or capability-first organization in `.ai/architecture.md`.',
            'A directory, namespace, route prefix, or route-list name is an authoring and review aid, never an authorization mechanism.',
            $crudAccessSurfaceEvidenceMarker,
            'do not split genuinely identical behavior merely because two routes carry different audience labels',
            'PHPThis never discovers or validates a feature from its directory name.',
        ],
        '.ai/crud.md' => [
            '## Multiple access surfaces',
            ...$crudAccessSurfaceContractMarkers,
            'The application may record another layout without adding a second way to perform the same task inside that application.',
            'Treat every directory, namespace, route prefix, and route-list label as authoring organization only.',
            $crudAccessSurfaceEvidenceMarker,
            'Do not add directory checker enforcement.',
        ],
        'skeleton/.ai/architecture.md' => [
            'Before exposing one resource through a second access surface, record the selected surface-grouping rule and permitted sharing here.',
            ...$crudAccessSurfaceContractMarkers,
            'An alternate layout cannot weaken the installed consumer contract or Strict Profile.',
            'A directory, namespace, route prefix, or route-list label never establishes authority',
            'Do not impose a forced surface directory hierarchy.',
        ],
        'templates/application/.ai/architecture.md' => [
            '{{CRUD_MULTI_SURFACE_ORGANIZATION_AND_SHARING_POLICY_OR_NOT_APPLICABLE}}',
            'When one resource is exposed through multiple access surfaces, record the selected grouping rule and permitted sharing above.',
            ...$crudAccessSurfaceContractMarkers,
            'An alternate directory and naming policy cannot weaken the installed consumer contract or Strict Profile',
            'Do not impose a forced surface directory hierarchy.',
        ],
        'skeleton/.ai/testing.md' => [
            $crudAccessSurfaceEvidenceMarker,
            'Do not add runtime or checker assertions for optional CRUD directory and naming choices.',
        ],
        'templates/application/.ai/testing.md' => [
            $crudAccessSurfaceEvidenceMarker,
            'Directory and naming choices in the optional CRUD profile are application context, not runtime or checker assertions.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $crudGuidanceMarkers,
        'optional multi-surface CRUD guidance',
        $failures,
    );

    $uuidIdentifierPolicyGuidanceMarkers = [
        'docs/request-handling.md' => [
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
        'skeleton/.ai/architecture.md' => [
            'one narrowly named application-owned representation primitive for shared validation and canonical scalar representation',
            'generation remains a separate explicitly versioned policy',
            'operations still require the concrete domain identifier, never the shared primitive',
        ],
        'templates/application/.ai/architecture.md' => [
            'one narrowly named application-owned representation primitive for shared validation and canonical scalar representation',
            'generation remains a separate explicitly versioned policy',
            'operations still require the concrete domain identifier, never the shared primitive',
        ],
        'skeleton/.ai/data.md' => [
            '`NOT_APPLICABLE(UUID_POLICY)`',
            'The reference acceptance policy is canonical lowercase RFC-variant versions 1 through 8.',
            'Version 7 is recommended for newly generated database row identifiers when embedded approximate creation-time disclosure is accepted',
            'generation owner and exact application source path, selected package and version, database facility and engine version, or external owner',
            'accepted metadata-bearing UUID exposure and handling',
            'failure and no-fallback policy',
            'Choosing version 4 does not prevent metadata disclosure',
            'PHPThis selects no generator, package, database facility, schema rule, or persistence representation.',
        ],
        'templates/application/.ai/data.md' => [
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
        ],
        'skeleton/.ai/testing.md' => [
            'When multiple concrete identifiers compose one recorded application-owned representation primitive',
            'operation signatures continue to require the concrete identifier',
            'versions 1 through 8 and RFC variant nibbles `8`, `9`, `a`, and `b`',
            'Test generation separately from acceptance',
            'prove the exact recorded generator source contract',
            'Version and variant bits alone are insufficient.',
            'finite generated samples do not prove uniqueness or total creation order',
        ],
        'templates/application/.ai/testing.md' => [
            'When multiple concrete identifiers compose one recorded application-owned representation primitive',
            'operation signatures continue to require the concrete identifier',
            'versions 1 through 8 and RFC variant nibbles `8`, `9`, `a`, and `b`',
            'Test generation separately from acceptance',
            'prove the exact recorded generator source contract',
            'Version and variant bits alone are insufficient.',
            'finite generated samples do not prove uniqueness or total creation order',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $uuidIdentifierPolicyGuidanceMarkers,
        'application-owned UUID policy guidance',
        $failures,
    );

    foreach (['templates/application/.ai/README.md', 'skeleton/.ai/README.md'] as $applicationContextIndex) {
        $applicationContextIndexContents = file_get_contents($root . '/' . $applicationContextIndex);

        if (!is_string($applicationContextIndexContents)) {
            $failures[] = "Cannot read {$applicationContextIndex}.";
        } elseif (!str_contains($applicationContextIndexContents, 'vendor/phpthis/framework/docs/crud.md')) {
            $failures[] = "{$applicationContextIndex} must route CRUD work through the installed framework guide.";
        }
    }

    $visionPath = $root . '/VISION.md';

    if (is_file($visionPath)) {
        $vision = file_get_contents($visionPath);

        if (!is_string($vision)) {
            $failures[] = 'Cannot read VISION.md.';
        } elseif (!str_contains($vision, 'AI-first authoring with human accountability')) {
            $failures[] = 'VISION.md must preserve AI-first authoring with human accountability as the north star.';
        }
    }

    $strictProfilePath = $root . '/docs/strict-profile.md';

    if (is_file($strictProfilePath)) {
        $strictProfile = file_get_contents($strictProfilePath);

        if (!is_string($strictProfile)) {
            $failures[] = 'Cannot read docs/strict-profile.md.';
        } elseif (preg_match('/^Profile version: 3$/m', $strictProfile) !== 1) {
            $failures[] = 'docs/strict-profile.md must declare profile version 3.';
        }
    }

    $applicationAgentInstructionsPath = $root . '/templates/application/AGENTS.md';

    if (is_file($applicationAgentInstructionsPath)) {
        $applicationAgentInstructions = file_get_contents($applicationAgentInstructionsPath);

        if (!is_string($applicationAgentInstructions)) {
            $failures[] = 'Cannot read templates/application/AGENTS.md.';
        } else {
            if (!str_contains(
                $applicationAgentInstructions,
                'vendor/phpthis/framework/docs/consumer-contract.md',
            )) {
                $failures[] = 'Application AGENTS.md must point to the installed PHPThis consumer contract.';
            }

            if (!str_contains(
                $applicationAgentInstructions,
                'vendor/phpthis/framework/docs/knowledge-map.md',
            )) {
                $failures[] = 'Application AGENTS.md must point to the installed PHPThis knowledge map.';
            }

            if (!str_contains($applicationAgentInstructions, 'primary code author and knowledge interface')) {
                $failures[] = 'Application AGENTS.md must define the AI authoring role.';
            }

            if (!str_contains($applicationAgentInstructions, 'only an accountable human may accept it')) {
                $failures[] = 'Application AGENTS.md must preserve human acceptance of consequential decisions.';
            }

            if (!str_contains($applicationAgentInstructions, 'Consumer Contract v11 and Strict Profile v3 are the minimum accepted rules')) {
                $failures[] = 'Application AGENTS.md must identify Consumer Contract v11 and Strict Profile v3 as the minimum accepted rules.';
            }
        }
    }

    $skeletonAgentInstructionsPath = $root . '/skeleton/AGENTS.md';

    if (is_file($skeletonAgentInstructionsPath)) {
        $skeletonAgentInstructions = file_get_contents($skeletonAgentInstructionsPath);

        if (!is_string($skeletonAgentInstructions)) {
            $failures[] = 'Cannot read skeleton/AGENTS.md.';
        } elseif (
            !str_contains($skeletonAgentInstructions, 'vendor/phpthis/framework/docs/knowledge-map.md')
            || !str_contains($skeletonAgentInstructions, 'primary code author and knowledge interface')
            || !str_contains($skeletonAgentInstructions, 'only an accountable human may accept it')
            || !str_contains($skeletonAgentInstructions, 'Consumer Contract v11 and Strict Profile v3 are the minimum accepted rules')
        ) {
            $failures[] = 'Skeleton AGENTS.md must preserve current Contract v11 authority, the installed knowledge route, AI authoring role, and human decision boundary.';
        }
    }

    $scaffoldParityMarkers = [
        'templates/application/AGENTS.md' => [
            'If Composer uses a non-default vendor directory, replace the leading `vendor/` segment in every installed path.',
            'Never substitute a framework-maintainer checkout for installed application authority.',
        ],
        'skeleton/AGENTS.md' => [
            'If Composer uses a non-default vendor directory, replace the leading `vendor/` segment in every installed path.',
            'Never substitute a framework-maintainer checkout for installed application authority.',
        ],
        'templates/application/.ai/operations.md' => [
            'Required extensions: `ext-pdo` and `ext-session`',
            'A database adoption additionally records its actual `ext-pdo_*` driver.',
        ],
        'skeleton/.ai/operations.md' => [
            'Required extensions: `ext-pdo` and `ext-session`',
        ],
        'templates/application/.ai/architecture.md' => [
            'construction and dependency ownership only, not temporal request flow',
            'Record the temporal request flow separately from the dependency diagram:',
            'The coordinator invokes the boundary and sink, then returns the selected response.',
            'The front controller owns the separate emission step',
        ],
        'skeleton/.ai/architecture.md' => [
            'This diagram records construction and dependency ownership, not request-time control flow.',
            'Request-time control flow is separate from dependency ownership:',
            'The coordinator invokes the request boundary and sink, then returns the selected response.',
            'The front controller separately gives that returned response to `ResponseEmitter`',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $scaffoldParityMarkers, 'scaffold-parity', $failures);

    $falseTerminalFlowChains = [
        'public/index.php -> bootstrap.php -> TerminalRequestCoordinator -> RequestBoundary -> Routes -> HealthRoutes -> HealthHandler -> Response -> RequestSummarySink -> ResponseEmitter',
        'front controller -> application terminal coordinator -> RequestBoundary -> selected Response -> one sink attempt -> ResponseEmitter',
    ];

    foreach (['templates/application/.ai/architecture.md', 'skeleton/.ai/architecture.md'] as $architectureContext) {
        $contents = file_get_contents($root . '/' . $architectureContext);

        if (!is_string($contents)) {
            continue;
        }

        foreach ($falseTerminalFlowChains as $falseTerminalFlowChain) {
            if (str_contains($contents, $falseTerminalFlowChain)) {
                $failures[] = "{$architectureContext} retains the false linear terminal-flow chain.";
            }
        }
    }

    $skeletonCi = file_get_contents($root . '/skeleton/.github/workflows/ci.yml');

    if (
        !is_string($skeletonCi)
        || substr_count($skeletonCi, '- run: composer check') !== 1
        || str_contains($skeletonCi, '- run: vendor/bin/phpthis check')
        || str_contains($skeletonCi, '- run: composer test')
    ) {
        $failures[] = 'Skeleton CI must invoke the canonical composer check exactly once without duplicating its component stages.';
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = substr($path, strlen($root) + 1);

        if (str_starts_with($relativePath, 'vendor/') || str_starts_with($relativePath, 'tmp/')) {
            continue;
        }

        $normalizedBasename = strtolower($file->getBasename());

        if (
            $relativePath !== 'phpstan.neon'
            && (
                preg_match('/\Aphpstan[a-z0-9._-]*\.neon(?:\.dist)?\z/', $normalizedBasename) === 1
                || preg_match('/\Aphpstan[a-z0-9._-]*baseline[a-z0-9._-]*\.php\z/', $normalizedBasename) === 1
            )
        ) {
            $failures[] = "PHT004 alternate PHPStan configuration is forbidden: {$relativePath}.";
        }

        if ($file->getExtension() === 'php' || $relativePath === 'bin/phpthis') {
            $phpFiles[$relativePath] = $path;
        }

        if ($file->getExtension() === 'md') {
            $markdownFiles[$relativePath] = $path;
        }
    }

    foreach ($phpFiles as $relativePath => $path) {
        if (frameworkMechanismPathIsForbidden($relativePath)) {
            $failures[] = "Permanent framework boundary forbids core runtime mechanism path: {$relativePath}.";
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            $failures[] = "Cannot read {$relativePath}.";
            continue;
        }

        $strictTypesPattern = $relativePath === 'bin/phpthis'
            ? '/^#!\/usr\/bin\/env php\R<\\?php\\s+declare\\(strict_types=1\\);/'
            : '/^<\\?php\\s+declare\\(strict_types=1\\);/';

        if (preg_match($strictTypesPattern, $contents) !== 1) {
            $failures[] = "{$relativePath} must declare strict types immediately after <?php.";
        }

        if (preg_match('/\\$\\$[A-Za-z_{]/', $contents) === 1) {
            $failures[] = "{$relativePath} uses a variable variable.";
        }

        foreach (SyntaxProfile::failures($contents, $relativePath) as $profileFailure) {
            $failures[] = $profileFailure;
        }

        if ($relativePath === 'src/Routing/Router.php') {
            foreach (routingLookupFailures($contents, $relativePath) as $routingFailure) {
                $failures[] = $routingFailure;
            }
        }

        $tokens = token_get_all($contents);
        $functionImportPending = false;
        $insideFunctionImport = false;

        foreach ($tokens as $index => $token) {
            $tokenId = is_array($token) ? $token[0] : null;
            $tokenText = is_array($token) ? $token[1] : $token;
            $isSignificant = !is_array($token)
                || !in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);

            if ($functionImportPending && $isSignificant) {
                $insideFunctionImport = $tokenId === T_FUNCTION;
                $functionImportPending = false;
            }

            if ($tokenId === T_USE) {
                $functionImportPending = true;
            } elseif ($insideFunctionImport && $tokenText === ';') {
                $insideFunctionImport = false;
            }

            if ($tokenId === T_VARIABLE) {
                $isCanonicalSessionState = $relativePath === 'src/Session/SessionLifecycle.php'
                    && $tokenText === '$_SESSION';
                $isFrontControllerInput = in_array(
                    $relativePath,
                    ['example/public/index.php', 'skeleton/public/index.php'],
                    true,
                ) && $tokenText !== '$_SESSION';

                if (
                    !$isCanonicalSessionState
                    && !$isFrontControllerInput
                    && in_array(
                        $tokenText,
                        ['$_SERVER', '$_GET', '$_POST', '$_COOKIE', '$_FILES', '$_SESSION', '$_ENV', '$_REQUEST'],
                        true,
                    )
                ) {
                    $boundary = $tokenText === '$_SESSION'
                        ? 'the canonical session boundary'
                        : 'the front controller';
                    $failures[] = sprintf(
                        '%s:%d reads a PHP superglobal outside %s.',
                        $relativePath,
                        $token[2],
                        $boundary,
                    );
                }
            }

            $nativeSessionFunction = strtolower(ltrim($tokenText, '\\'));

            if (
                $relativePath !== 'src/Session/SessionLifecycle.php'
                && in_array($tokenId, [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                && in_array($nativeSessionFunction, $nativeSessionFunctions, true)
            ) {
                $nextSignificantToken = null;

                for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
                    $candidate = $tokens[$next];

                    if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }

                    $nextSignificantToken = $candidate;
                    break;
                }

                $previousSignificantToken = null;

                for ($previous = $index - 1; $previous >= 0; $previous--) {
                    $candidate = $tokens[$previous];

                    if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }

                    $previousSignificantToken = $candidate;
                    break;
                }

                $previousTokenId = is_array($previousSignificantToken) ? $previousSignificantToken[0] : null;

                if (
                    ($nextSignificantToken === '(' || $insideFunctionImport)
                    && !in_array(
                        $previousTokenId,
                        [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON],
                        true,
                    )
                ) {
                    $failures[] = sprintf(
                        '%s:%d %s native session function %s outside the canonical session boundary.',
                        $relativePath,
                        $token[2],
                        $insideFunctionImport ? 'imports' : 'calls',
                        $nativeSessionFunction,
                    );
                }
            }

            if (
                !in_array($relativePath, ['tools/guardrails/distribution.php', 'verification/ApplicationChecker.php'], true)
                && $tokenId === T_CONSTANT_ENCAPSED_STRING
                && strlen($tokenText) >= 2
            ) {
                $literalFunction = strtolower(ltrim(stripcslashes(substr($tokenText, 1, -1)), '\\'));

                if (in_array($literalFunction, $nativeSessionFunctions, true)) {
                    $failures[] = sprintf(
                        '%s:%d references native session function %s indirectly outside the canonical session boundary.',
                        $relativePath,
                        $token[2],
                        $literalFunction,
                    );
                }
            }

            if (
                in_array($tokenId, [T_COMMENT, T_DOC_COMMENT], true)
                && preg_match('/@phpstan-ignore[A-Za-z0-9_-]*/i', $tokenText) === 1
            ) {
                $failures[] = sprintf(
                    'PHT004 %s:%d PHPStan comment suppressions are forbidden.',
                    $relativePath,
                    $token[2],
                );
            }

        }
    }

    if (count($markdownFiles) <= count($phpFiles)) {
        $failures[] = sprintf(
            'Markdown files (%d) must outnumber PHP files (%d).',
            count($markdownFiles),
            count($phpFiles),
        );
    }

    $coreLines = 0;

    foreach ($phpFiles as $relativePath => $path) {
        if (!str_starts_with($relativePath, 'src/')) {
            continue;
        }

        $lines = file($path);
        $coreLines += is_array($lines) ? count($lines) : 0;
    }

    if ($coreLines > 2_600) {
        $failures[] = "Core source has {$coreLines} physical lines; the accepted UUID/ULID routing limit is 2600.";
    }

    $markdownCount = count($markdownFiles);
    $phpCount = count($phpFiles);

    return $failures;
}
