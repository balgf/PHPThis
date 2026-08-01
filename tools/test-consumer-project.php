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
    proveInstalledDatabaseSetupGuidanceDistribution($project, $installedFramework);
    proveInstalledStartupProbeGuidanceDistribution($project, $installedFramework);
    proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution($project, $installedFramework);
    proveInstalledMigrationStructureGuidanceDistribution(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );
    proveInstalledUuidAndUlidRouting($project, $environment);
    proveDatabaseContextConnectionConsistency($project, $profileCommand, $environment);
    proveInstalledTypedConfiguration($project, $profileCommand, $environment);
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

function proveInstalledDatabaseSetupGuidanceDistribution(string $project, string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/AGENTS.md' => [
            '## Early database setup gate',
            'Apply this gate before the full task read order',
            'A current `NOT_APPLICABLE` marker describes present behavior and does not resolve intent for a new adoption request.',
            'After the human resolves the scope, resume the normal read order and load only the selected path.',
        ],
        $project . '/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            'configuration only, connection to an existing server, or project-local server provisioning',
            'deferred migrations or an application-owned migration foundation',
            '> Please setup PostgreSQL as our main DB.',
            'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
        ],
        $project . '/.ai/README.md' => [
            'visible adopted composition or explicit connection-composition deferral',
            'child-process parser or adopted-entrypoint tests',
        ],
        $project . '/.ai/configuration.md' => [
            'Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption.',
            'one separately named factory and final readonly output type for each adopted process profile',
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
            'For PostgreSQL or another engine, first record an engine-specific application decision',
            'when migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests',
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
            'visible adopted composition or explicit connection-composition deferral',
            'child-process parser or adopted-entrypoint tests',
        ],
        $installedFramework . '/templates/application/AGENTS.md' => [
            '## Early database setup gate',
            'Apply this gate before the full task read order',
            'An explicit request proceeds without a redundant scope question.',
        ],
        $installedFramework . '/templates/application/.ai/configuration.md' => [
            'Record only adopted external input contracts.',
            'do not store task scope or task history here',
        ],
        $installedFramework . '/templates/application/.ai/data.md' => [
            '{{ELEVATED_DATABASE_IDENTITY_REFERENCE_OR_NOT_APPLICABLE}}',
            '{{ELEVATED_DATABASE_AUTHORITY_ISOLATION_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
    ];

    foreach ($artifactMarkers as $path => $markers) {
        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed database setup guidance artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("Installed database setup guidance artifact {$path} is missing marker: {$marker}");
            }
        }
    }

    fwrite(STDOUT, "PASS installed database setup guidance distribution\n");
}

function proveInstalledStartupProbeGuidanceDistribution(string $project, string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            'Change runtime, logging, liveness, or readiness behavior',
            'exact probe claim, inherited dependencies, bounds, failure behavior, local or deployment operations owner, and evidence',
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
            'Change runtime, logging, deployment, liveness, or readiness behavior',
            'exact probe claim, inherited dependencies, bounds, failure behavior, local or deployment operations owner, evidence',
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

    foreach ($artifactMarkers as $path => $markers) {
        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed startup and probe guidance artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("Installed startup and probe guidance artifact {$path} is missing marker: {$marker}");
            }
        }
    }

    fwrite(STDOUT, "PASS installed startup and probe guidance distribution\n");
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
            'one non-HTTP owner and path for every adopted authority activation and deactivation, `GRANT` and `REVOKE` only where supported',
            'Configuration, connectivity, target existence, and migration completion do not activate runtime authority.',
        ],
        $project . '/.ai/migrations.md' => [
            'accepted engine-specific database definition or provisioning, supported namespace/control model, data-definition, authority, locking, recovery, and integration decision',
            'one owner and complete non-HTTP path for each authority activation and deactivation, with `GRANT` and `REVOKE` only where supported',
            'runtime-authority activation handoff, exact-engine positive and negative verification',
            'application rollout and traffic-enablement order',
        ],
        $project . '/.ai/operations.md' => [
            'authority-transition owner or activation stage',
            'application-owned order and compatibility among migration, authority activation, exact-engine verification, application rollout, traffic enablement, later authority deactivation',
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
        $installedFramework . '/templates/application/AGENTS.md' => [
            'database, catalog, schema, or attachment namespace selection and qualification as supported by the chosen engine',
            'namespace and object control or ownership model, with explicit not-applicable facts where the engine has no such model',
            'each named operation\'s exact statements, targets, required capabilities, and prohibited capabilities',
            'effective authority resolution source, using only applicable mechanisms such as direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'Give each adopted authority activation or deactivation one non-HTTP owner and path; record `GRANT` or `REVOKE` only where supported.',
            'activate and verify it before dependent code receives traffic',
        ],
        $installedFramework . '/templates/application/.ai/data.md' => [
            '{{CONNECTION_1_DATABASE_DEFINITION_OR_PROVISIONING_SOURCE}}',
            '{{CONNECTION_1_NAMESPACE_SELECTION_AND_QUALIFICATION_POLICY}}',
            '{{CONNECTION_1_NAMESPACE_AND_OBJECT_CONTROL_OR_OWNERSHIP_MODEL_OR_NOT_APPLICABLE}}',
            '{{DATABASE_AUTHORITY_1_CONNECTION_AND_OPERATION}}',
            '{{DATABASE_AUTHORITY_1_EFFECTIVE_AUTHORITY_RESOLUTION_SOURCE}}',
            '{{DATABASE_AUTHORITY_ACTIVATION_AND_DEACTIVATION_PATH_OR_NOT_APPLICABLE}}',
            'Authority activation and deactivation owner, complete non-HTTP path, and transition source; `GRANT` and `REVOKE` only where supported',
        ],
        $installedFramework . '/templates/application/.ai/migrations.md' => [
            '{{MIGRATION_ENGINE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_REQUIRED_AND_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_AUTHORITY_TRANSITION_PATH_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_RUNTIME_AUTHORITY_HANDOFF_AND_EVIDENCE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            '{{DATABASE_AUTHORITY_AND_RELEASE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
            '{{DATABASE_AUTHORITY_TRANSITION_OPERATIONS_OR_NOT_APPLICABLE}}',
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

    foreach ($artifactMarkers as $path => $markers) {
        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed database authority lifecycle artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("Installed database authority lifecycle artifact {$path} is missing marker: {$marker}");
            }
        }
    }

    fwrite(STDOUT, "PASS installed database authority lifecycle guidance distribution\n");
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
        $project . '/AGENTS.md' => [
            'the health-only starter has no database, migration path, or migration directory',
            'PHPThis recommends `src/Database/Migrations/` and `App\\Database\\Migrations`',
            'A coherent consumer-selected alternative is authoritative',
            'must not be relocated by AI without explicit human approval',
        ],
        $project . '/.ai/migrations.md' => [
            'No migration directory, code, or dependency is included',
            'PHPThis recommends `src/Database/Migrations/`',
            '`App\\Database\\Migrations` namespace',
            'Record the actual adopted directory and namespace in this file.',
            'neither PHPThis nor the consumer checker enforces the recommendation or discovers migration files',
            'multiple named database connections later adopt independent migration histories',
            'do not pre-create or prescribe connection subdivisions',
        ],
        $installedFramework . '/docs/decisions/039-recommended-database-migration-structure.md' => [
            'Status: accepted',
            'Migrations are specialized application-owned database evolution.',
            'A consumer may instead record any coherent application-owned path and namespace.',
            'does not reject an alternative, enforce this directory through the checker or Strict Profile',
            'The database-free skeleton does not create an empty migration directory.',
            'multiple named database connections genuinely own independent migration histories',
            'does not create speculative connection directories for a single-database application',
            'does not establish a generic database layer',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'ADR 039 recommends `src/Database/Migrations/`',
            'A coherent consumer-selected alternative remains valid',
            'does not enforce migration placement through the checker or Strict Profile',
            'no empty migration directory',
            'explicit connection-owned subdivision for each adopted history',
            'Do not invent connection subdivisions for a single-database application',
        ],
        $installedFramework . '/docs/database.md' => [
            'Migrations are specialized application-owned database evolution.',
            'ADR 039 recommends `src/Database/Migrations/`',
            'records its actual source path and namespace in `.ai/migrations.md`',
            'any coherent alternative remains valid',
            'does not enforce placement, discover work from a directory, silently relocate established source',
            'multiple named database connections independently adopt migration histories',
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
        $installedFramework . '/templates/application/AGENTS.md' => [
            'PHPThis recommends `src/Database/Migrations/`',
            '`.ai/migrations.md` must record the actual adopted source directory and namespace.',
            'A coherent consumer-selected alternative is authoritative',
            'must not be relocated by AI without explicit human approval',
        ],
        $installedFramework . '/templates/application/.ai/migrations.md' => [
            '{{MIGRATION_SOURCE_DIRECTORY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_APPLICATION_NAMESPACE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONNECTION_OWNERSHIP_OR_NOT_APPLICABLE}}',
            'PHPThis recommends `src/Database/Migrations/`',
            'A coherent consumer-selected alternative is authoritative',
            'neither PHPThis nor the consumer checker enforces the recommendation or discovers migration files',
            'connection without an independently adopted migration history',
        ],
    ];

    foreach ($artifactMarkers as $path => $markers) {
        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed migration-structure guidance artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("Installed migration-structure guidance artifact {$path} is missing marker: {$marker}");
            }
        }
    }

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
$uuid = '01890f5a-4c96-7a2b-8c3d-123456789abc';
$ulid = '01arz3ndektsv4rrffq69g5fav';
$uuidMatch = $router->match(new Request('GET', '/accounts/' . $uuid));
$ulidMatch = $router->match(new Request('POST', '/events/' . $ulid));

if (
    $uuidMatch?->pathParameters->uuid('account_id') !== $uuid
    || $ulidMatch?->pathParameters->ulid('event_id') !== $ulid
    || $router->match(new Request('GET', '/accounts/' . strtoupper($uuid))) !== null
    || $router->match(new Request('POST', '/events/' . strtoupper($ulid))) !== null
    || $router->allowedMethodsForPath('/accounts/' . $uuid) !== ['GET']
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
            'empty runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => '',
            ],
            'malformed runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'malformed runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'INVALID RUNTIME USER',
            ],
            'oversized runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty runtime password' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => '',
            ],
            'oversized runtime password' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('p', 65),
            ],
        ];
        $migrationInvalidCases = [
            'empty migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => '',
            ],
            'malformed migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'malformed migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'INVALID MIGRATION USER',
            ],
            'oversized migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty migration password' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => '',
            ],
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
