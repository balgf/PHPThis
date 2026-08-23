<?php

declare(strict_types=1);

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

use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
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
$encodedPath = '/raw/%00/%20/%7F/%2F/%3F/%23';
$encodedRoute = new Route('GET', $encodedPath, $handler);
$router = new Router([
    new Route('GET', '/accounts/{account_id:uuid}', $handler),
    new Route('POST', '/events/{event_id:ulid}', $handler),
    $encodedRoute,
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
$encodedRequest = (new RequestReader(1, 'php://memory'))->read(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $encodedPath . '?value=%0A?tail'],
    ['value' => "\n"],
);
$encodedMatch = $router->match($encodedRequest);

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

$reader = new RequestReader(1, 'php://memory');

foreach ([...range(0x00, 0x20), 0x7F] as $byte) {
    foreach ([
        '/safe' . chr($byte) . 'PrivateMarker',
        '/safe?value=' . chr($byte) . 'PrivateMarker',
    ] as $requestTarget) {
        try {
            $reader->read(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $requestTarget], []);
        } catch (InvalidRequest $failure) {
            if (
                $failure->getMessage()
                    !== 'REQUEST_URI has an invalid or oversized request-target representation.'
                || str_contains($failure->getMessage(), 'PrivateMarker')
            ) {
                throw new RuntimeException('Installed RequestReader returned an unsafe diagnostic.');
            }

            continue;
        }

        throw new RuntimeException('Installed RequestReader accepted a prohibited raw target byte.');
    }

    $requestRejected = false;

    try {
        new Request('GET', '/safe' . chr($byte) . 'PrivateMarker');
    } catch (InvalidArgumentException $failure) {
        if (
            $failure->getMessage()
                !== 'Request path must be absolute and contain no query, fragment, raw space, control, or DEL byte.'
            || str_contains($failure->getMessage(), 'PrivateMarker')
        ) {
            throw new RuntimeException('Installed Request returned an unsafe path diagnostic.');
        }

        $requestRejected = true;
    }

    if (!$requestRejected) {
        throw new RuntimeException('Installed Request accepted a prohibited raw path byte.');
    }

    foreach ([
        '/literal' . chr($byte) . 'PrivateMarker',
        '/accounts/{account_id:uuid}/documents' . chr($byte) . 'PrivateMarker',
    ] as $routePath) {
        try {
            new Route('GET', $routePath, $handler);
        } catch (InvalidArgumentException $failure) {
            if (
                $failure->getMessage()
                    !== 'Route path must be absolute and contain no query, fragment, raw space, control, or DEL byte.'
                || str_contains($failure->getMessage(), 'PrivateMarker')
            ) {
                throw new RuntimeException('Installed Route returned an unsafe path diagnostic.');
            }

            continue;
        }

        throw new RuntimeException('Installed Route accepted a prohibited raw path byte.');
    }
}

if (
    $ulidMatch?->pathParameters->ulid('event_id') !== $ulid
    || $router->match(new Request('POST', '/events/' . strtoupper($ulid))) !== null
    || $router->allowedMethodsForPath('/events/' . $ulid) !== ['POST']
    || $encodedRequest->path !== $encodedPath
    || $encodedRequest->query !== ['value' => "\n"]
    || $encodedMatch?->route !== $encodedRoute
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
