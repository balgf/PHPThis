<?php

declare(strict_types=1);

/** @param list<string> $markers */
function mutableReleaseStateClaim(string $contents, array $markers): ?string
{
    $plainContents = preg_replace('/\[([^\]\r\n]+)\]\([^\)\r\n]+\)/', '$1', strtolower($contents));

    if (!is_string($plainContents)) {
        return null;
    }

    $plainContents = str_replace(['*', '_', '`', '~'], '', $plainContents);
    $normalizedContents = preg_replace('/\s+/', ' ', $plainContents);

    if (!is_string($normalizedContents)) {
        return null;
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
            $claimOffset = $releaseClaim[1];
            $claimPrefix = substr($normalizedContents, 0, $claimOffset);

            if (preg_match('/(?:\bif|\bwhen|\bonce|\bafter|\bbefore|\bunless)\s*$/', $claimPrefix) === 1) {
                continue;
            }

            return 'normalized release-state claim';
        }
    }

    foreach ($markers as $marker) {
        $normalizedMarker = preg_replace('/\s+/', ' ', strtolower($marker));

        if (is_string($normalizedMarker) && str_contains($normalizedContents, $normalizedMarker)) {
            return $marker;
        }
    }

    return null;
}

/** @return list<string> */
function contextGuardrailFailures(string $root): array
{
    $failures = [];

    $simpleEndpointDefinition = 'A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision.';
    $simpleEndpointLocality = 'After universal entrypoints, a simple-endpoint change has exactly four task-specific files: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test.';
    $ordinaryImplementationRoute = 'Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.';
    $frameworkOrdinaryRoute = 'An ordinary route change starts with `.ai/routing.md`; read a decision record only when reviewing or changing the decision it records.';
    $installedOrdinaryRoute = 'An ordinary route change starts with installed `vendor/phpthis/framework/docs/request-handling.md`; read a decision record only when reviewing or changing the decision it records.';
    $slimUniversalEntrypoint = 'Concern-specific rules live in the current guide routed by `.ai/README.md`; do not copy them into this universal entrypoint.';
    $finalClassContract = 'Every named class is final. Express extension points with interfaces, never non-final classes.';
    $databaseLoopContract = 'Never execute a database call inside `for`, `foreach`, `while`, `do`, or recursive traversal.';
    $privateConstructorScope = 'An operation-specific request, command, or projection parsed from external `mixed` uses a private constructor. This requirement does not set identifier constructor visibility; an application-owned identifier follows its recorded coherent convention.';

    $boundedTaskRoutedContextArtifactMarkers = [
        'docs/decisions/044-bounded-task-routed-ai-context.md' => [
            'Status: accepted',
            $simpleEndpointDefinition,
            $simpleEndpointLocality,
            $ordinaryImplementationRoute,
            'Consumer Contract version 10 and Strict Profile version 3 remain unchanged.',
            'A report-only context-size or repeated-rule advisory was considered and is not adopted.',
            'Human review remains responsible for whether task routes stay compact and unambiguous.',
            'No context report script, `ApplicationChecker` rule, `PHT` diagnostic, or consumer-size validity gate is added.',
            'No runtime API, dependency, automatic discovery, generated policy, consumer validity diagnostic',
        ],
        'docs/decisions/README.md' => [
            '`044-bounded-task-routed-ai-context.md`',
        ],
        'docs/knowledge-map.md' => [
            $simpleEndpointDefinition,
            $simpleEndpointLocality,
            '| Add a simple application endpoint | `docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged, and this is the complete four-file task-specific set after universal entrypoints |',
            'Read an ADR only when reviewing or changing the decision it records',
        ],
        'docs/consumer-contract.md' => [
            'Ordinary implementation starts with the current operational guide selected by those routers.',
            'Read a decision record only when reviewing or changing the decision it records; historical rationale is not ordinary implementation context.',
            'ADR 044 defines bounded task-routed AI context',
        ],
        'docs/getting-started.md' => [
            'begin ordinary implementation with one current operational guide',
            'current guide, existing named route-area manifest, dependency-free handler, and nearest behavior test',
        ],
        'VISION.md' => [
            $simpleEndpointDefinition,
            $simpleEndpointLocality,
            $ordinaryImplementationRoute,
        ],
        'AGENTS.md' => [
            $slimUniversalEntrypoint,
            '## Early database setup gate',
            'Start with the one current operational guide selected by `.ai/README.md`.',
            'final named classes, interface extension points',
            $databaseLoopContract,
            '## Project gate',
        ],
        '.ai/README.md' => [
            $frameworkOrdinaryRoute,
            'Use the exact simple-endpoint definition and four-file locality metric in the already-read `VISION.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.',
            '| Add or change a qualifying simple endpoint | `.ai/routing.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |',
        ],
        '.ai/rules.md' => [
            $finalClassContract,
            $databaseLoopContract,
            $privateConstructorScope,
        ],
        '.ai/strict-profile.md' => [
            'every named repository class is `final`. Use an interface for an extension point',
            'inside the header or body of any `for`, `foreach`, `while`, or `do` loop',
        ],
        '.ai/types.md' => [
            'Every parser-owned operation-specific request, command, page-request, or projection factory must:',
            'Use a private constructor so invalid instances cannot be created.',
            'This is not a universal constructor rule for application identifiers or other domain values',
        ],
        'docs/type-safety.md' => [
            'A parser-owned request, command, page-request, or projection value uses a private constructor',
            'This is not a universal constructor rule for application identifiers or other domain values',
            'Parser-owned request, command, page-request, and projection factories use private constructors',
        ],
        '.ai/crud.md' => [
            'single canonical current reference tree',
            'Update and Delete remain prose-only decisions',
            'do not scaffold absent operations from this guide',
        ],
        'docs/crud.md' => [
            'this is the single canonical current tree',
            'contains no speculative Update or Delete scaffold',
            'AuthorizeCreateUser.php',
            'UnacceptableCreateUserValues.php',
            'UserSummary.php',
            '/users/{user_id:positive-int}',
        ],
        'ROADMAP.md' => [
            '/users/{user_id:positive-int}',
        ],
        'docs/database.md' => [
            '/accounts/{account_id:positive-int}/documents',
        ],
        'docs/evaluation.md' => [
            '/users/{user_id:positive-int}',
        ],
        'example/.ai/file-transfers.md' => [
            'human-readable response-template shorthand',
            'GET /document-files/{file_id:token}',
        ],
        'templates/application/AGENTS.md' => [
            $slimUniversalEntrypoint,
            '## Early database setup gate',
            'Start with the one current operational guide selected by `.ai/README.md`.',
            '## Project gate',
        ],
        'templates/application/.ai/README.md' => [
            $installedOrdinaryRoute,
            'Use the exact simple-endpoint definition and four-file locality metric in the already-read installed `vendor/phpthis/framework/docs/knowledge-map.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.',
            '| Add or change a qualifying simple endpoint | installed `vendor/phpthis/framework/docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |',
        ],
        'templates/application/.ai/rules.md' => [
            $finalClassContract,
            $databaseLoopContract,
            $privateConstructorScope,
        ],
        'skeleton/AGENTS.md' => [
            $slimUniversalEntrypoint,
            '## Early database setup gate',
            'Start with the one current operational guide selected by `.ai/README.md`.',
            '## Project gate',
        ],
        'skeleton/.ai/README.md' => [
            $installedOrdinaryRoute,
            'Use the exact simple-endpoint definition and four-file locality metric in the already-read installed `vendor/phpthis/framework/docs/knowledge-map.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.',
            '| Add or change a qualifying simple endpoint | installed `vendor/phpthis/framework/docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |',
        ],
        'skeleton/.ai/rules.md' => [
            $finalClassContract,
            $databaseLoopContract,
            $privateConstructorScope,
        ],
        'skeleton/.ai/architecture.md' => [
            'A qualifying dependency-free simple endpoint may be constructed inline only in an existing named route-area manifest so the root `Routes::create()` remains unchanged; every handler with a constructor dependency stays visibly constructed in the root and passed into its route area.',
        ],
        'skeleton/src/Routes.php' => [
            'return [...HealthRoutes::create()];',
        ],
        'skeleton/src/HealthRoutes.php' => [
            'public static function create(): array',
            "return [new Route('GET', '/health', new HealthHandler())];",
        ],
        'skeleton/src/HealthHandler.php' => [
            'final class HealthHandler implements RequestHandler',
        ],
        '.ai/testing.md' => [
            'ADR 058 explicitly revisits ADR 044 after growth in the fixed universal set and umbrella context',
            'Words, bytes, or tokens do not determine program validity.',
            'Do not add a context report script, repeated-rule advisory, `ApplicationChecker` rule, `PHT` diagnostic, consumer-size threshold or validity gate, automatic context discovery, or generated policy.',
        ],
        'docs/guardrails.md' => [
            "The bounded task-routed context guard pins ADR 044's exact simple-endpoint definition and four-file locality metric",
            'The installed proof checks the copied local skeleton plus packaged public guidance and application template, including the starter',
            'The guard adds no context report script, `ApplicationChecker` rule, `PHT` diagnostic, or consumer-size validity gate.',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/044-bounded-task-routed-ai-context.md',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledBoundedTaskRoutedContextGuidanceDistribution($project, $installedFramework);',
        ],
        'tools/test-consumer-project/data.php' => [
            'function proveInstalledBoundedTaskRoutedContextGuidanceDistribution(',
            'PASS installed bounded task-routed context guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $boundedTaskRoutedContextArtifactMarkers,
        'bounded task-routed context',
        $failures,
    );

    $concernLocalContextArtifactMarkers = [
        'docs/decisions/058-concern-local-ai-context-routing.md' => [
            '# ADR 058: Concern-local AI context routing',
            'Status: accepted',
            'On 2026-08-24 in Asia/Manila, the accountable human approved Issue #59',
            'Unique requirements are transferred to the concern owner before they are removed from the universal contract',
            '`docs/consumer-contract-upgrades.md` owns the complete Contract version 1 through version 15 migration and historical narrative.',
            'The four task-specific files are never described as total context.',
            'No maximum, score, trend threshold, warning, Composer failure, application-checker rule, generated report, automatic discovery, `PHT` diagnostic, or consumer validity effect follows from these numbers.',
            '[bounded AI-context routing review](../ai-context-routing-review.md)',
            'Contract version 15 and Strict Profile version 4 remain unchanged',
        ],
        'docs/decisions/README.md' => [
            '`058-concern-local-ai-context-routing.md`',
            '| [ADR 044](044-bounded-task-routed-ai-context.md)',
            '[ADR 058](058-concern-local-ai-context-routing.md)',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 18',
            'Load [the contract upgrade and history companion](consumer-contract-upgrades.md) only when upgrading an application across contract versions, reviewing contract evolution, or changing that history.',
            '## Universal safety and unsupported claims',
            '## Mandatory application context',
            '## Normative concern routing',
            '| Configuration, secrets, database-setup scope, startup and probes | `docs/configuration.md` |',
            '| Database migrations | `docs/migrations.md` |',
            '| Durable jobs | `docs/jobs/README.md` |',
            '| File transfers, including local storage and Amazon S3 | `docs/file-transfers/README.md` |',
            '| WebSockets | `docs/websockets.md` |',
            '| Contract upgrade or historical review | `docs/consumer-contract-upgrades.md` |',
            'Report universal context cost separately from that four-file task-specific metric; no size result permits skipping authority, safety, or evidence.',
            'Contract version 18 and Strict Profile version 4 remain current with permanent diagnostics `PHT001` through `PHT008`.',
        ],
        'docs/consumer-contract-upgrades.md' => [
            '# PHPThis consumer contract upgrades',
            'Load it only when upgrading an application across contract versions, reviewing contract evolution, or changing the decision history.',
            '## Contract evolution',
            '### Contract version 18',
            '### Contract version 17',
            '### Contract version 16',
            '### Contract version 15',
            '### Contract version 14',
            '### Contract version 13',
            '### Contract version 12',
            'Contract version 1 replaced consumer-owned PHPStan configuration with the installed checker and added the runnable skeleton.',
        ],
        'docs/knowledge-map.md' => [
            'Within each row, begin with the first current operational guide.',
            'Contract upgrades and historical review additionally load `docs/consumer-contract-upgrades.md`.',
            'Measure or report universal context separately; its cost is never hidden inside or used to weaken that four-file routing claim.',
            '| Add a simple application endpoint | `docs/request-handling.md` |',
            '| Add, explain, or review configuration or secrets | `docs/configuration.md` |',
            '| Adopt or review backend-neutral durable jobs | `docs/jobs/README.md` |',
            '| Adopt, secure, store, or return a file, including Amazon S3 | `docs/file-transfers/README.md` |',
            '| Propose, add, or review a WebSocket path | `docs/websockets.md` |',
            '| Upgrade across Consumer Contract versions or review contract history | `docs/consumer-contract-upgrades.md` |',
            '## Answer protocol',
            'Do not invent missing product requirements, schema meaning, authorization policy, production limits, or external-service behavior.',
        ],
        '.ai/README.md' => [
            'A concern-specific skeleton or template change starts at its concern row.',
            'Report fixed universal-entrypoint word and byte cost separately; neither measure affects validity or permits skipping universal safety.',
            '| Change email guidance or application email context | `.ai/email.md` |',
            '| Change configuration guidance, application configuration context, or value-free Composer aliases | `.ai/configuration.md` |',
            '| Change local environment launcher guidance or its checked reference | `.ai/configuration.md` |',
            '| Change startup, liveness, dependency health, or readiness semantics | `.ai/operations.md` |',
            '| Change application-owned atomic-lock, mutex, mutual-exclusion, lease, critical-section, or coordination guidance | `.ai/operations.md` |',
            '| Change durable deferred work | `.ai/jobs.md` |',
            '| Change database migrations | `.ai/migrations.md` |',
            '| Change uploads or file responses, or adopt/review Amazon S3 | `.ai/file-transfers.md` |',
            '| Change application-owned WebSockets | `.ai/websockets.md` |',
            '| Change the current Consumer Contract, knowledge router, context ownership, shared template/skeleton authority, or package context inventory | `.ai/application-context.md` |',
            '| Prepare or publish a release | `RELEASING.md` |',
        ],
        '.ai/application-context.md' => [
            '# Application-context distribution contract',
            'Use this guide only for cross-artifact application-context ownership and distribution',
            '## Ownership and authority',
            '## Distribution surfaces',
            '## Concern routing',
            'Do not duplicate its normative policy here.',
            '## Verification',
            'Context-size measurements are advisory evidence only',
            'never make words, bytes, or tokens a validity threshold, checker rule, `PHT` diagnostic, or substitute for route-clarity and unsupported-claim review.',
        ],
        '.ai/configuration.md' => [
            '# Application configuration contract',
            '## Value-free Composer aliases',
            '## Optional local environment launcher',
            'Do not add framework configuration runtime',
            'Do not add a framework or skeleton launcher',
        ],
        '.ai/operations.md' => [
            '# Application operations contract',
            '## Standalone operation coordination',
            '## Startup and probes',
            '## Optional local launcher operation',
            '`RELEASING.md` is the sole route for those tasks.',
        ],
        '.ai/email.md' => [
            '# Application-owned email contract',
            'PHPThis provides no framework mailer, renderer, notification system, queue, worker, webhook receiver, provider, or email runtime dependency.',
            'Require the application\'s `.ai/integrations.md` to record exactly `NOT_APPLICABLE(EMAIL)` or one adopted policy.',
        ],
        'VISION.md' => [
            'Any report of this metric states the universal read cost separately',
            'The four files are the task-specific authoring set, not the total context read.',
            'another concern\'s guide, policy, source, or evidence is never skipped',
        ],
        'skeleton/.ai/README.md' => [
            'Read installed `vendor/phpthis/framework/docs/consumer-contract-upgrades.md#contract-version-18` when upgrading to the current accepted contract.',
            'Read the complete installed upgrade companion only when reviewing its history.',
            'Four files is the task-specific authoring set, not total context, and never permits skipped safety or evidence.',
            '| Upgrade the installed Consumer Contract to current version 18 | installed `vendor/phpthis/framework/docs/consumer-contract-upgrades.md#contract-version-18` |',
            '| Review Consumer Contract history | installed `vendor/phpthis/framework/docs/consumer-contract-upgrades.md` |',
        ],
        'templates/application/.ai/README.md' => [
            'Read installed `vendor/phpthis/framework/docs/consumer-contract-upgrades.md#contract-version-18` when upgrading to the current accepted contract.',
            'Read the complete installed upgrade companion only when reviewing its history.',
            'Four files is the task-specific authoring set, not total context, and never permits skipped safety or evidence.',
            '| Upgrade the installed Consumer Contract to current version 18 | installed `vendor/phpthis/framework/docs/consumer-contract-upgrades.md#contract-version-18` |',
            '| Review Consumer Contract history | installed `vendor/phpthis/framework/docs/consumer-contract-upgrades.md` |',
        ],
        'docs/ai-context-routing-review.md' => [
            '# Bounded AI-context routing review',
            '| 1 | Application: “Add a dependency-free `GET /ping` literal endpoint beside the existing health route.” |',
            '| 10 | Maintainer: “Add configuration for an S3-backed durable job and document its operational probe.” |',
            'Findings: 0 unsupported claims across 10 fixed routes.',
            'It does not measure answer quality, token use, compliance probability, or comparative model performance.',
        ],
        'docs/evaluation.md' => [
            'ADR 058\'s [bounded AI-context routing review](ai-context-routing-review.md)',
            'not a model run, context-strategy comparison, token result, or proof that an arbitrary agent follows the selected route.',
        ],
        'docs/guardrails.md' => [
            'ADR 058 reaches ADR 044\'s reconsideration condition and adds a separate concern-local routing guard.',
            'Only explicit path-and-marker pairs reviewed as obsolete under ADR 058 may retire legacy expectations',
            'An unlisted current or future marker must still fail.',
            'The installed consumer proof independently rereads the new contract, upgrade companion, knowledge map, ADR, route review, `VISION.md`, and both application routers from the mirrored package.',
        ],
        'tools/package-files.txt' => [
            'docs/ai-context-routing-review.md',
            'docs/consumer-contract-upgrades.md',
            'docs/decisions/058-concern-local-ai-context-routing.md',
        ],
        'tools/guardrails/repository.php' => [
            'function guardrailLegacyUmbrellaMarkerRequirementIsRetired(',
            'string $legacyMarker,',
            '$retiredMarkers = [',
            'return in_array($legacyMarker, $retiredMarkers[$legacyRelativePath] ?? [], true);',
            'Unique requirements are transferred to the concern owner before they are removed from the universal contract',
        ],
        'tools/test-consumer-project/support.php' => [
            'function installedLegacyUmbrellaMarkerRequirementIsRetired(string $path, string $marker): bool',
            "'docs/consumer-contract.md'",
            "'docs/knowledge-map.md'",
            '$retiredMarkers = [',
            'return in_array($marker, $retiredMarkers[$legacyRelativePath], true);',
            'Unique requirements are transferred to the concern owner before they are removed from the universal contract',
        ],
        'tools/test-consumer-project/data.php' => [
            "\$installedFramework . '/docs/consumer-contract-upgrades.md'",
            "\$installedFramework . '/docs/decisions/058-concern-local-ai-context-routing.md'",
            "\$installedFramework . '/docs/ai-context-routing-review.md'",
            'PASS installed bounded task-routed context guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $concernLocalContextArtifactMarkers,
        'concern-local context routing',
        $failures,
    );

    if (!guardrailLegacyUmbrellaMarkerRequirementIsRetired(
        $root,
        'docs/consumer-contract.md',
        '## Application configuration',
    )) {
        $failures[] = 'The exact ADR 058 legacy-marker retirement control is not active.';
    }

    if (guardrailLegacyUmbrellaMarkerRequirementIsRetired(
        $root,
        'docs/consumer-contract.md',
        '## Universal safety and unsupported claims',
    )) {
        $failures[] = 'The ADR 058 legacy-marker retirement control accepts an unlisted current marker.';
    }

    $concernLocalContextForbiddenMarkers = [
        'docs/consumer-contract.md' => [
            '## Contract evolution',
            '## Application configuration',
            '## Application-owned WebSocket profile',
            '## Optional bounded file transfers',
            '## Optional application-owned durable jobs',
            '## Optional application-owned database migrations',
        ],
        '.ai/application-context.md' => [
            'ADR 054 and `v0.1.0-alpha.7` define the latest immutable framework tag',
            '`NOT_APPLICABLE(JOBS)`',
            '`NOT_APPLICABLE(MIGRATIONS)`',
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            '`LOCAL_ADR026`',
            '`AMAZON_S3_ADR053`',
            'Keep transactional email composition and delivery application-owned.',
            'Keep every application Composer alias value-free',
        ],
    ];

    forbidGuardrailArtifactMarkers(
        $root,
        $concernLocalContextForbiddenMarkers,
        'concern-local context routing',
        $failures,
    );

    $boundedTaskRoutedContextForbiddenMarkers = [
        'AGENTS.md' => [
            'Keep optional WebSockets application-owned and separate from PHPThis HTTP:',
            'Keep optional Workbench use development-only and explicit:',
            'Keep migrations application-owned and engine/version-specific under ADR 043',
        ],
        '.ai/rules.md' => [
            'Keep optional WebSockets application-owned:',
            'Keep optional Workbench use development-only and explicit:',
            'Keep ADR 027 schema migration explicit and application-owned:',
        ],
        'templates/application/AGENTS.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            '`NOT_APPLICABLE(WORKBENCH)`',
            'each history\'s exact initial baseline',
        ],
        'templates/application/.ai/rules.md' => [
            'Keep `NOT_APPLICABLE(WEBSOCKETS)`',
            'Keep every adopted operational command behind the sole application console',
            'Keep every adopted application-owned request-handler decorator',
        ],
        'skeleton/AGENTS.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            '`NOT_APPLICABLE(WORKBENCH)`',
            '`NOT_APPLICABLE(CLI)`',
            'each history\'s exact initial baseline',
        ],
        'skeleton/.ai/rules.md' => [
            'Keep `NOT_APPLICABLE(WEBSOCKETS)`',
            'Keep `NOT_APPLICABLE(CLI)`',
            'Keep `NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`',
        ],
        'skeleton/src/Routes.php' => [
            'HealthRoutes::create(new HealthHandler())',
        ],
        'skeleton/src/HealthHandler.php' => [
            'function __construct',
        ],
        '.ai/crud.md' => [
            'UpdateUser/',
            'DeleteUser/',
        ],
        'docs/crud.md' => [
            'UpdateUser/',
            'DeleteUser/',
        ],
    ];

    forbidGuardrailArtifactMarkers(
        $root,
        $boundedTaskRoutedContextForbiddenMarkers,
        'bounded task-routed context',
        $failures,
    );

    $sessionCleanupAndResponseFramingArtifactMarkers = [
        'docs/decisions/045-bounded-session-cleanup-and-response-framing.md' => [
            'Status: accepted',
            'On 2026-08-08 in Asia/Manila, the accountable human approved this bounded correction',
            'When an operation has an original failure and cleanup succeeds, PHPThis rethrows that exact original `Throwable` instance.',
            'When cleanup also fails, it throws the narrow redacted `SessionCleanupFailed` failure, retaining both the original failure and cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'Cleanup follows prerequisite order; it does not retry or attempt an unsafe dependent action after its prerequisite fails.',
            'PHPThis neither logs, retries, suppresses, nor converts these failures into a response inside the lifecycle.',
            '`Response` accepts final response statuses from `200` through `599`; it rejects final informational `1xx` statuses.',
            'It rejects every `Transfer-Encoding` ordinary header.',
            'A `204`, `205`, or `304` response has an empty ordinary body and no `Content-Length`.',
            '`HEAD` remains application-owned and explicit.',
            'The existing `LocalFileBody` contract remains stronger',
            'Consumer Contract version 11 carries version 10 forward',
            'Strict Profile version 3 remains unchanged',
        ],
        'docs/decisions/README.md' => [
            '`045-bounded-session-cleanup-and-response-framing.md`',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 18',
            'A final `Response` uses a status from `200` through `599`, never `Transfer-Encoding`, and one explicit ordinary body.',
            '`HEAD` remains an explicit application route with its own empty response body and no inferred representation length under this safe subset.',
            'a second cleanup failure becomes the narrow redacted `SessionCleanupFailed` retaining both failures',
            'Contract version 11 carries contract version 10 forward and retains Strict Profile version 3.',
        ],
        '.ai/http.md' => [
            'Construct only final `200` through `599` responses.',
            'A `204`, `205`, or `304` has an empty ordinary body and no `Content-Length`.',
            'Keep `HEAD` explicit and application-owned',
            'Do not fall back to `GET`, suppress a `GET` body, give the emitter a request, or infer a representation length.',
        ],
        '.ai/session.md' => [
            'A failed `update()` or `regenerateAndUpdate()` makes one bounded cleanup attempt while preserving the begun request and any earlier pending cookie or unissued state that remains coherent.',
            'Failed `invalidate()` cleanup likewise clears live pending-cookie ownership before it escapes.',
            'unsafe dependent cleanup is not attempted after its prerequisite fails.',
            '`finish()` and `abort()` are terminal and reset local request state even when cleanup fails',
            'If cleanup also fails, the narrow redacted `SessionCleanupFailed` retains both failures and is excluded from registered response mapping',
            'do not log, retry, suppress, or map it inside session code.',
        ],
        '.ai/strict-profile.md' => [
            'Consumer Contract version 18 carries version 17 and Strict Profile version 4 forward with permanent diagnostics `PHT001` through `PHT008`.',
            "ADR 045's response/session runtime behavior, ADR 049's response-cookie boundary, ADR 053's application-owned optional S3 profile, ADR 059's bounded fail-closed application source discovery, ADR 060's pending-output emission preflight, and ADR 061's outer HTTP failure boundary, disclosure modes, safe-message allowlist, and real-SAPI evidence remain contract behavior; they are not part of PHT008 or a new Strict Profile rule.",
        ],
        '.ai/testing.md' => [
            'Inject native cleanup faults only through the isolated framework test boundary',
            'preservation of begun-request and earlier pending state after failed update or regeneration cleanup',
            'invalidation commit-failure precedence with no stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            'normal finalization of a registered original response when operation cleanup succeeds.',
            'Response tests cover final-status bounds, rejection of `Transfer-Encoding`, ordinary omitted or exact canonical `Content-Length`',
            '`HEAD` remains a separately declared application route with an empty body and no inferred representation length; the emitter does not receive request knowledge.',
        ],
        'docs/request-handling.md' => [
            'An ordinary final response has a status from `200` through `599`, no `Transfer-Encoding`, and one explicit string body.',
            'A `204`, `205`, or `304` has no ordinary body and no `Content-Length`.',
            '`ResponseEmitter` receives only a `Response`: PHPThis does not use a `GET` fallback, silently suppress output, or add request knowledge to emission.',
        ],
        'docs/sessions.md' => [
            '## Cleanup failure precedence',
            'Failed invalidation cleanup likewise clears live pending-cookie ownership before it escapes.',
            'Cleanup follows prerequisite order and does not retry or attempt an unsafe dependent action after its prerequisite fails.',
            'If cleanup also fails, `SessionCleanupFailed` retains the original and cleanup failures',
            'PHPThis does not log, retry, suppress, or turn either failure into a response inside session code.',
        ],
        'docs/errors.md' => [
            '`SessionCleanupFailed` is the narrow framework failure raised only when a session operation already failed and bounded native cleanup also failed.',
            'It is not a retry instruction, log event, or client response, and `RequestBoundary` deliberately excludes it from `ErrorResponseRegistry` mapping.',
        ],
        'docs/security.md' => [
            'Keep native session cleanup bounded.',
            'Construct final responses with explicit safe framing: no `Transfer-Encoding`',
            'Keep `HEAD` explicit; do not rely on a hidden `GET` fallback or emitter body suppression.',
        ],
        'skeleton/.ai/testing.md' => [
            'Cleanup evidence proves exact primary identity after success; redacted retention after cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            '`HTTP_RESPONSE_FRAMING`',
            'A `HEAD` route is explicit and returns an empty body without inferred representation length.',
        ],
        'templates/application/.ai/testing.md' => [
            'Cleanup evidence proves exact primary identity after success; redacted retention after cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            'Every response test asserts the final status, body, and headers selected by the route.',
            'a `HEAD` route remains explicit with an empty body and no inferred representation length.',
        ],
        'src/Http/Response.php' => [
            '$status < 200 || $status > 599',
            "isset(\$normalizedHeaderNames['transfer-encoding'])",
            'in_array($status, [204, 205, 304], true)',
            '$contentLength !== (string) strlen($body)',
            '$contentLength !== (string) $fileBody->bytes',
        ],
        'src/Http/ResponseEmitter.php' => [
            'public function emit(Response $response): void',
            'echo $response->body;',
            'private function emitFile(Response $response, LocalFileBody $body): void',
        ],
        'src/Http/RequestBoundary.php' => [
            'use PHPThis\\Session\\SessionCleanupFailed;',
            '$failure instanceof SessionCleanupFailed',
            'throw new SessionCleanupFailed($failure, $cleanupFailure);',
        ],
        'src/Session/SessionCleanupFailed.php' => [
            'final class SessionCleanupFailed extends \\RuntimeException',
            'public readonly \\Throwable $primaryFailure',
            'public readonly \\Throwable $cleanupFailure',
            "parent::__construct('Session cleanup failed after a primary failure.');",
        ],
        'src/Session/SessionLifecycle.php' => [
            'private function failAfterCleanup(Throwable $primaryFailure, ?string $firstUnissuedId, ?string $secondUnissuedId = null, bool $abortActive = true): never',
            'throw new SessionCleanupFailed($primaryFailure, $cleanupFailure);',
            'if (!$this->cleanupFailed)',
            "} catch (Throwable \$failure) {\n            if (\$this->cleanupFailed) {\n                throw \$failure;\n            }\n            \$this->failAfterCleanup(\$failure, \$createdId);",
            '$this->resetRequestState($this->cleanupFailed);',
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
        'tests/fixtures/session-cleanup-failures.php.fixture' => [
            'public static function failNextIdClear(): void',
            'function session_abort(): bool',
            'function session_destroy(): bool',
            'function session_id(?string $id = null): string|false',
            "\$id === '' && NativeSessionFaults::idClearShouldFail()",
            'function session_start(array $options = []): bool',
            'function session_write_close(): bool',
            'Cleanup aggregation must preserve the exact primary failure and one cleanup failure.',
            'Request abort must reset an operation cleanup failure without retrying native abort.',
            'NativeSessionFaults::abortCalls() === $updateClearAbortCalls',
            'NativeSessionFaults::destroyCalls() === $updateClearDestroyCalls',
            'A latched update clear failure must escape once without RequestBoundary cleanup retry.',
            'A lone explicit-abort cleanup failure must escape directly once and reset.',
            'Operation cleanup must preserve prior same-request state selected for known-response finalization.',
            'A mapped superseded-ID cleanup failure must not emit a stale pending cookie.',
            'A failed superseded-ID destroy must be followed only by destruction of the new identifier.',
            'A failed superseded-ID destroy must not leak the new pending identifier.',
            'A superseded-ID start failure must be attempted once before only the distinct new ID is destroyed.',
            'A failed abort must not be retried or followed by dependent identifier destruction.',
            'Invalidation must retain its write-close failure before one abort failure without dependent cleanup.',
            '$mappedInvalidationStartResponse->cookies === []',
            'count($invalidationStartAttempts) === 1',
            'NativeSessionFaults::abortCalls() === $invalidationStartAbortCalls',
            'NativeSessionFaults::destroyCalls() === $invalidationStartDestroyCalls',
            'An early mapped invalidation start failure must relinquish the live cookie without cleanup retry.',
            'count(NativeSessionFaults::startAttemptsSinceFault()) === 1',
            'NativeSessionFaults::abortCalls() === $combinedStartClearAbortCalls',
            'NativeSessionFaults::destroyCalls() === $combinedStartClearDestroyCalls',
            'A failed start must remain primary when its single identifier-clear cleanup also fails.',
            '$mappedInvalidationClearResponse->cookies === []',
            'NativeSessionFaults::idClearCallsSinceFault() === 1',
            'NativeSessionFaults::abortCalls() === $invalidationClearAbortCalls',
            'NativeSessionFaults::destroyCalls() === $invalidationClearDestroyCalls',
            'A mapped invalidation identifier-clear failure must emit no stale cookie or cleanup retry.',
            'NativeSessionFaults::abortCalls() === $unmappedInvalidationClearAbortCalls',
            'NativeSessionFaults::destroyCalls() === $unmappedInvalidationClearDestroyCalls',
            'An unmapped invalidation clear failure must escape once without RequestBoundary cleanup retry.',
            'NativeSessionFaults::abortCalls() === $mismatchedInvalidationAbortCalls + 1',
            'NativeSessionFaults::destroyCalls() === $mismatchedInvalidationDestroyCalls',
            'Mismatched invalidation must attempt a no-release abort once without RequestBoundary retry.',
            'NativeSessionFaults::abortCalls() === $rejectedRegenerationAbortCalls + 1',
            'NativeSessionFaults::destroyCalls() === $rejectedRegenerationDestroyCalls',
            'Rejected regeneration must attempt a no-release abort once without RequestBoundary retry.',
            'A lone mapped destruction failure must emit no stale cookie or second destroy attempt.',
            'Every cleanup path must reset local lifecycle state for the next request.',
            'The cleanup aggregate text must not expose either retained failure or session storage details.',
            'A cleanup aggregate must retain the generic redacted unknown-failure path.',
            'A mapped handler failure must select its known response before finalization becomes primary.',
            'PASS isolated session cleanup failure precedence',
        ],
        'tests/http-boundary.php' => [
            "yield 'session cleanup preserves primary failures and resets deterministically'",
            "runIsolatedPhpTest(__DIR__ . '/fixtures/session-cleanup-failures.php.fixture')",
            'PASS isolated session cleanup failure precedence',
            "yield 'ordinary response framing enforces final statuses and explicit HEAD routing'",
            "runIsolatedPhpTest(__DIR__ . '/response-framing.php')",
            "\$result['exit_code'] !== 0 || \$result['stderr'] !== ''",
            "'get_only_head_status' => 405",
            "'get_only_handler_calls' => 0",
        ],
        'tests/response-framing.php' => [
            "new Response(199, [], '')",
            "new Response(599, [], '')",
            "new Route('HEAD', '/explicit-head', \$headHandler)",
            "new Request('HEAD', '/explicit-head')",
            "new Route('GET', '/get-only', \$getHandler)",
            "new Request('HEAD', '/get-only')",
            "'get_only_handler_calls' => \$getHandler->calls",
            'Expected bounded final statuses without an inferred HEAD-to-GET fallback.',
        ],
        'tests/response-emitter.php' => [
            "new Response(103, [], '')",
            "new Response(600, [], '')",
            "new Response(200, ['Transfer-Encoding' => 'identity'], '')",
            "new Response(200, ['Content-Length' => '07'], 'created')",
            "new Response(204, ['Content-Length' => '0'], '')",
            "new Route('HEAD', '/explicit-head', \$headHandler)",
            "new Request('HEAD', '/explicit-head')",
            '$explicitHeadResponse->body !== \'\'',
            'Expected supported ordinary response framing to remain valid.',
            'Expected the complete local file to be emitted in bounded chunks.',
        ],
        'tests/behavior-names.txt' => [
            'ordinary response framing enforces final statuses and explicit HEAD routing',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/045-bounded-session-cleanup-and-response-framing.md',
            'src/Session/SessionCleanupFailed.php',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledSessionCleanupAndResponseFramingDistribution(',
        ],
        'tools/test-consumer-project/http.php' => [
            'function proveInstalledSessionCleanupAndResponseFramingDistribution(',
            'PASS installed session cleanup and response framing distribution',
        ],
        'docs/guardrails.md' => [
            'ADR 045 used the remaining seven-line margin for its bounded session-cleanup failure and response-framing correction. The tagged Alpha 6 framework source removes the redundant public-prerelease `PathParameters::onePositiveInteger()` convenience factory and occupies 2,595 lines. Accepted post-tag documentation, guardrail, and maintainer-only evaluation-tooling changes before Issue #43 add no core',
            'The ADR 045 guard pins the bounded session-cleanup failure precedence and ordinary-response framing contract.',
            'A dedicated selectable behavior also proves rejection at `199`, acceptance at `599`, explicit application-owned `HEAD`, and an exact `405` with zero GET-handler calls when only GET is declared; its subprocess must keep stderr empty.',
            'superseded-identifier restart and distinct-new-identifier cleanup',
            'start failure retained as primary when identifier clearing also fails',
            'clear and abort failure latches that prevent request-boundary cleanup re-entry',
            'distinct regenerated-identifier cleanup without a repeated active-session abort',
            'one-attempt update-clear, unmapped-invalidation-clear, mismatched-invalidation-abort, and rejected-regeneration-abort evidence',
            "local pending and unissued ownership cleared before invalidation's first fallible native operation",
            'zero-cookie, no-retry mapped early-start and post-commit identifier-clear failures',
            'invalidation write-close failure precedence, stale-cookie exclusion, and no dependent cleanup after a failed prerequisite',
            'It does not reproduce those runtime semantics as a consumer checker rule or add a `PHT` diagnostic.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $sessionCleanupAndResponseFramingArtifactMarkers,
        'session-cleanup and response-framing',
        $failures,
    );

    $pendingOutputResponseEmissionArtifactMarkers = [
        'docs/decisions/060-reject-pending-output-before-response-emission.md' => [
            '# ADR 060: Reject pending output before response emission',
            'Status: accepted',
            'On 2026-08-25 in Asia/Manila, the accountable human directed implementation of Issue #62',
            'At the start of every `ResponseEmitter::emit()` call',
            '`ob_get_status(true)` reports a non-zero `buffer_used` value for any active PHP-managed output-buffer level.',
            'No active buffer, one empty active buffer, and nested active buffers whose every level is empty remain valid infrastructure.',
            'The emitter only inspects the entry state. It does not clean, flush, close, reorder, rewrite, copy, or incorporate application-owned buffers or prior bytes.',
            'Rejection occurs before file access, response status, ordinary headers, separate `Set-Cookie` fields, ordinary body output, or local-file bytes.',
            'Consumer Contract version 17 carries version 16 and Strict Profile version 4 forward with permanent diagnostics `PHT001` through `PHT008`.',
            'The core remains 2,618 physical lines under the accepted 2,620-line ceiling',
        ],
        'docs/decisions/README.md' => [
            '`060-reject-pending-output-before-response-emission.md`',
            '| [ADR 026](026-bounded-file-transfers.md) | Headers-only prior-output detection before local-file emission | [ADR 060](060-reject-pending-output-before-response-emission.md) |',
            'Accepted [ADR 060](060-reject-pending-output-before-response-emission.md) coordinates Consumer Contract version 17 while retaining Strict Profile version 4',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 18',
            'begin every ordinary or local-file response emission with headers unsent and no pending bytes in any active PHP-managed output-buffer level',
            'empty active buffers remain valid, and application code fixes early output at its owner rather than cleaning or incorporating it',
            'Contract version 18 and Strict Profile version 4 remain current with permanent diagnostics `PHT001` through `PHT008`.',
        ],
        'docs/consumer-contract-upgrades.md' => [
            '### Contract version 17',
            'both ordinary and local-file `ResponseEmitter::emit()` calls now fail as `ResponseEmissionFailed(true)` at entry',
            'a non-zero `buffer_used` value at any active output-buffer level',
            'Remove unintended early output at its owner.',
            'Keep intentional capture or infrastructure buffers empty at emitter entry',
            'ADR 060 introduces Contract version 17 because pending PHP-managed output now rejects an emission call that version 16 allowed to proceed.',
        ],
        '.ai/http.md' => [
            'At emitter entry, reject ordinary and local-file responses as `ResponseEmissionFailed(true)`',
            'Allow empty active and nested buffers.',
            'fail before status, headers, cookies, body, or file access.',
            'Only `ResponseEmissionFailed(false)` may receive one generic fallback',
            'ADR 060 adds the pending-output preflight; ADR 061 adds the generic-first outer HTTP failure boundary and optional controlled detail response.',
            'Current Consumer Contract v18 carries version 17 and Strict Profile v4 forward.',
        ],
        '.ai/file-transfers.md' => [
            'Before file access, `ResponseEmitter` applies the common response-output guard',
            'all-empty active and nested buffers remain valid.',
            'It inspects without cleaning, flushing, rewriting, or incorporating prior bytes.',
        ],
        '.ai/testing.md' => [
            'Use real PHP output buffers to allow empty active and nested levels',
            'lower-level bytes hidden below an empty top level as `ResponseEmissionFailed(true)`',
            'prove zero status, header, cookie, body, or file-access work.',
            'Ordinary emission evidence uses real empty and non-empty active buffers and proves pending bytes remain untouched',
        ],
        '.ai/strict-profile.md' => [
            'Consumer Contract version 18 carries version 17 and Strict Profile version 4 forward with permanent diagnostics `PHT001` through `PHT008`.',
            "ADR 045's response/session runtime behavior, ADR 049's response-cookie boundary, ADR 053's application-owned optional S3 profile, ADR 059's bounded fail-closed application source discovery, ADR 060's pending-output emission preflight, and ADR 061's outer HTTP failure boundary, disclosure modes, safe-message allowlist, and real-SAPI evidence remain contract behavior; they are not part of PHT008 or a new Strict Profile rule.",
        ],
        'docs/request-handling.md' => [
            'At emitter entry, both ordinary and local-file responses fail as `ResponseEmissionFailed(true)`',
            'An active buffer, or nested stack of buffers, remains valid when every level is empty.',
            'rejects before status, headers, cookies, body, or local-file access.',
            'Only `ResponseEmissionFailed(false)` may receive one generic fallback in the front controller',
        ],
        'docs/file-transfers/emission.md' => [
            'reject when headers were already sent or any active PHP-managed output-buffer level reports pending bytes;',
            'No active buffer, one empty active buffer, and a nested stack whose every level is empty are valid.',
            'Pending bytes at any level fail as `ResponseEmissionFailed(true)` before file access, status, headers, cookies, or body output.',
            'A pre-header open, type, or length failure raises `ResponseEmissionFailed(false)`.',
        ],
        'docs/file-transfers/testing.md' => [
            'Exercise real PHP output buffers: allow empty active and nested buffers',
            'below an empty top level as `ResponseEmissionFailed(true)`',
            'prove rejection precedes status, headers, cookies, body, and file access.',
            'does not establish custom output-handler private state, later output',
        ],
        'docs/security.md' => [
            'Before ordinary or local-file emission, ensure headers are unsent and every active PHP-managed output-buffer level is empty',
            'fix earlier output at its owner rather than cleaning, discarding, or incorporating it.',
            'The entry snapshot does not expose custom output-handler private state, later output',
        ],
        'docs/evaluation.md' => [
            'ADR 026 adds a bounded file-transfer proof, and ADR 060 extends its preflight evidence.',
            'use real PHP buffers to reject pending top-level and nested lower-level bytes while allowing empty active buffers',
            'prove zero file-open or response-output work',
        ],
        'docs/guardrails.md' => [
            'The accepted ADR 060 response-emission guard pins Consumer Contract version 17',
            'real top-level and nested PHP-buffer evidence, empty-buffer success, `ResponseEmissionFailed(true)`',
            'rejection before status, headers, cookies, body, or file access.',
            'Acceptance authorizes no commit, push, issue closure, tag, package, release, or announcement.',
        ],
        'ROADMAP.md' => [
            'Complete: ADR 060 and Consumer Contract version 17 retain Strict Profile version 4 and `PHT001` through `PHT008`',
            'All-empty active buffers remain valid; prior bytes and buffer lifecycle remain application-owned',
        ],
        'example/.ai/file-transfers.md' => [
            'The emitter first rejects already-sent headers or pending bytes in any active PHP-managed output-buffer level as `ResponseEmissionFailed(true)`',
            'allowing all-empty active buffers and leaving application-owned buffers and prior bytes untouched.',
        ],
        'src/Http/ResponseEmitter.php' => [
            "if (headers_sent() || array_sum(array_column(\\ob_get_status(true), 'buffer_used')) > 0)",
            'throw new ResponseEmissionFailed(true);',
            'if ($response->fileBody !== null)',
        ],
        'tests/response-emitter.php' => [
            'public static bool $sideEffectsForbidden = false;',
            "echo 'prefix';",
            '$bufferedOrdinaryFailure->responseStarted',
            '$bufferedOrdinaryOutput !== \'prefix\'',
            '$bufferedFileTopOutput !== \'\'',
            '$bufferedFileLowerOutput !== \'prefix\'',
            'Response emission performed forbidden local-file access.',
            'Expected lower buffered bytes to reject local-file emission before file access.',
            '$fileOuterOutput !== \'\'',
        ],
        'tests/http-boundary.php' => [
            "yield 'response emitter rejects pending output and preserves repeated Set-Cookie fields'",
            "runIsolatedPhpTest(__DIR__ . '/response-emitter.php')",
        ],
        'tests/behavior-names.txt' => [
            'response emitter rejects pending output and preserves repeated Set-Cookie fields',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/060-reject-pending-output-before-response-emission.md',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledSessionCleanupAndResponseFramingDistribution(',
            '$environment,',
        ],
        'tools/test-consumer-project/http.php' => [
            'function proveInstalledSessionCleanupAndResponseFramingDistribution(',
            '$installedFramework . \'/docs/decisions/060-reject-pending-output-before-response-emission.md\'',
            '$installedFramework . \'/src/Http/ResponseEmitter.php\'',
            "'buffer_used'",
            "throw new RuntimeException('Installed ordinary emitter did not reject pending bytes intact.');",
            "throw new RuntimeException('Installed local-file emitter did not reject nested pending bytes intact.');",
            "throw new RuntimeException('Installed emitter rejected empty nested buffering infrastructure.');",
            'PASS installed response-emission preflight',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $pendingOutputResponseEmissionArtifactMarkers,
        'pending-output response emission',
        $failures,
    );

    $canonicalExecutableExampleBoundaryMarkers = [
        'docs/decisions/046-canonical-executable-example-boundaries.md' => [
            'Status: accepted',
            'On 2026-08-09 in Asia/Manila, the accountable human approved Issue #34 and this application-example consolidation.',
            '### One exact-class error-response owner',
            '### One semantic user identifier',
            "This narrowly refines ADR 013's historical executable-example tree by moving `UserId.php` from `GetUser/` to the feature-level `Users/` directory.",
            '### Cadence before dependency work',
            '### Authoritative title and cache-admission boundaries',
            'Consumer Contract version 11, Strict Profile version 3, PHPThis core at 2,600 lines, runtime dependencies, consumer checker validity, and the skeleton/template application API remain unchanged.',
        ],
        'docs/decisions/README.md' => [
            '`046-canonical-executable-example-boundaries.md`',
        ],
        'docs/decisions/013-optional-crud-reference-profile.md' => [
            'ADR 046 later refines only the current executable example\'s identifier placement:',
            'Historical release inspection uses the exact tagged copy of this decision.',
        ],
        'docs/consumer-contract.md' => [
            'ADR 046 consolidates four executable-example application boundaries without changing framework runtime, accepted PHP syntax, consumer checker validity, or the contract or Strict Profile version.',
        ],
        '.ai/crud.md' => [
            'One application-owned `Users\\UserId` carries the same positive `users.id` invariant through Get and List projections and the accepted List continuation while every operation-specific projection remains separate.',
        ],
        '.ai/cli.md' => [
            'The current example evaluates its injected clock and cadence before database-path inspection, Redis, PDO, token generation, SQL, or job work.',
            'A non-due pass returns `not_due` with empty coordination and is not a dependency-readiness result;',
        ],
        'docs/type-safety.md' => [
            '`ListUsersPageRequest::fromQuery` turns its optional canonical decimal `after_user_id` string into `?UserId`',
        ],
        'docs/database.md' => [
            'A projection also validates any representation required by its next explicit sink.',
            "Field byte or character limits remain separate schema or operation decisions and must not be inferred from an optional cache's admission policy.",
        ],
        'docs/redis/cache-value.md' => [
            'These are limits for this cache representation only, not an authoritative database-title bound; a valid authoritative title that exceeds cache admission remains usable without being stored here.',
        ],
        'example/src/ApplicationComposition.php' => [
            'public static function errorResponses(): ErrorResponseRegistry',
            'InvalidRequest::class => new Response(',
            '"{\\"error\\":{\\"code\\":\\"invalid_request\\",\\"message\\":\\"Request is invalid.\\"}}\\n"',
            '$errorResponses = self::errorResponses();',
        ],
        '.ai/errors.md' => [
            'The executable example and its boundary evidence obtain that map from `ApplicationComposition::errorResponses()`; do not reproduce the registry in a test helper.',
        ],
        'example/src/Documents/ListDocuments/ListDocumentsHandler.php' => [
            '$this->authorize->authorizeList($principal, $tenant);',
            '$pageRequest = ListDocumentsPageRequest::fromQuery($request->query);',
        ],
        'example/src/Users/UserId.php' => [
            'final readonly class UserId',
            'public static function fromPositiveInteger(int $value): self',
            'public static function fromDatabaseValue(mixed $value): self',
            "preg_match('/^[1-9][0-9]*$/D', \$value)",
        ],
        'example/src/Users/GetUser/UserDetails.php' => [
            'public UserId $id,',
            "UserId::fromDatabaseValue(\$row['id'])",
            "preg_match('//u', \$name) !== 1",
        ],
        'example/src/Users/ListUsers/UserSummary.php' => [
            'public UserId $id,',
            "UserId::fromDatabaseValue(\$row['id'])",
            "preg_match('//u', \$name) !== 1",
        ],
        'example/src/Users/ListUsers/UserActivitySummary.php' => [
            'public UserId $id,',
            "UserId::fromDatabaseValue(\$row['id'])",
            "preg_match('//u', \$name) !== 1",
        ],
        'example/src/Users/ListUsers/ListUsersPageRequest.php' => [
            'private function __construct(public ?UserId $afterUserId)',
            'return new self(UserId::fromPositiveInteger($afterUserId));',
        ],
        'example/src/Users/GetUser/GetUserHandler.php' => [
            'UserId::fromPositiveInteger(',
            "['user_id' => \$userId->value]",
            "['data' => ['id' => \$user->id->value, 'name' => \$user->name]]",
        ],
        'example/src/Users/ListUsers/ListUsersHandler.php' => [
            ': $pageRequest->afterUserId->value,',
            "'id' => \$user->id->value,",
            '$nextAfterUserId = (string) $lastUserId->value;',
        ],
        'example/src/Documents/GetDocument/DocumentDetails.php' => [
            "!is_string(\$title) || \$title === '' || preg_match('//u', \$title) !== 1",
            'Document details title has an invalid database representation.',
        ],
        'example/src/Documents/ListDocuments/DocumentSummary.php' => [
            "!is_string(\$title) || \$title === '' || preg_match('//u', \$title) !== 1",
            'Document summary title has an invalid database representation.',
        ],
        'tests/request-policy.php' => [
            'final class RequestPolicySummarySink implements RequestSummarySink',
            'document list invalid input uses the central exact-class response mapping',
            '$response !== $expected',
            'document projections reject invalid stored UTF-8 before JSON responses',
            'ApplicationComposition::errorResponses()',
            "[new QuerySummarySource('get_document', \$getBudget, \$getQueryTrace)]",
            "[new QuerySummarySource('list_documents', \$listBudget, \$listQueryTrace)]",
            "\$getSummary['outcome'] !== 'unknown_failure'",
            "\$getSummary['unknown_failure_class'] !== UnexpectedValueException::class",
            'Invalid stored UTF-8 must reach the terminal generic response and redacted summary path.',
        ],
        'tests/input-projection.php' => [
            '[\'id\' => 7, \'name\' => "Invalid \\xC3\\x28"]',
            '[\'id\' => 7, \'name\' => "Invalid \\xC3\\x28", \'event_count\' => 1]',
        ],
        'tests/cli.php' => [
            "new TestUserWelcomeJobClock(299)",
            "new TestUserWelcomeJobClock(300)",
            'The explicit clock must gate database and Redis work and select complete UTC five-minute slots without catch-up.',
        ],
        'tests/cache.php' => [
            'Redis document cache keeps a 513-byte authoritative title usable while rejecting cache admission',
            "strlen(\$title) !== 513",
            "preg_match('//u', \$title) !== 1",
            "cacheTrace('miss', 'payload_rejected')",
        ],
        'tests/behavior-names.txt' => [
            'document list invalid input uses the central exact-class response mapping',
            'document projections reject invalid stored UTF-8 before JSON responses',
            'Redis document cache keeps a 513-byte authoritative title usable while rejecting cache admission',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/046-canonical-executable-example-boundaries.md',
        ],
        'ROADMAP.md' => [
            'ADR 046 consolidates the executable example around one exact-class response owner, one application-owned semantic user identifier, cadence-first scheduled preflight, and valid-UTF-8 authoritative document projections',
        ],
        'docs/guardrails.md' => [
            "The ADR 046 guard pins the executable example's canonical application boundaries without turning them into framework runtime or consumer-validity rules.",
            'It retains the composition-root exact-class registry through `ApplicationComposition::errorResponses()` with no handler-local or test-local duplicate;',
            'invalid stored UTF-8 traverses the real request boundary and terminal coordinator to the generic `500`, redacted unknown-failure summary, and one-query policy path using that production-owned registry.',
            'No generic identifier, validator, response renderer, scheduler, cache helper, repository, ORM, checker rule, or `PHT` diagnostic is added.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $canonicalExecutableExampleBoundaryMarkers,
        'canonical executable-example boundary',
        $failures,
    );

    $listDocumentsHandler = file_get_contents(
        $root . '/example/src/Documents/ListDocuments/ListDocumentsHandler.php',
    );

    if (is_string($listDocumentsHandler) && (
        str_contains($listDocumentsHandler, 'catch (InvalidRequest')
        || str_contains($listDocumentsHandler, 'use PHPThis\\Http\\InvalidRequest;')
        || str_contains($listDocumentsHandler, '"invalid_request"')
    )) {
        $failures[] = 'ListDocumentsHandler must delegate InvalidRequest response selection to the composition-root registry.';
    }

    $applicationComposition = file_get_contents($root . '/example/src/ApplicationComposition.php');
    $requestPolicyEvidence = file_get_contents($root . '/tests/request-policy.php');
    $inputProjectionEvidence = file_get_contents($root . '/tests/input-projection.php');

    if (
        !is_string($applicationComposition)
        || str_contains($applicationComposition, 'UnexpectedValueException::class')
    ) {
        $failures[] = 'The production error registry must leave invalid stored representations on the unknown-failure path.';
    }

    if (
        !is_string($requestPolicyEvidence)
        || str_contains($requestPolicyEvidence, 'function requestPolicyErrorRegistry(')
        || str_contains($requestPolicyEvidence, 'new ErrorResponseRegistry(')
        || !is_string($inputProjectionEvidence)
        || str_contains($inputProjectionEvidence, 'function exampleErrorResponseRegistry(')
        || str_contains($inputProjectionEvidence, 'new ErrorResponseRegistry(')
    ) {
        $failures[] = 'Example boundary evidence must use the production-owned composition registry without a test-local duplicate.';
    }

    if (
        !is_string($requestPolicyEvidence)
        || str_contains($requestPolicyEvidence, 'new CapturingRequestSummarySink(')
    ) {
        $failures[] = 'Request-policy evidence must own its summary sink without depending on a later concern file.';
    }

    if (is_file($root . '/example/src/Users/GetUser/UserId.php')) {
        $failures[] = 'The semantic user identifier must remain feature-owned rather than nested under GetUser.';
    }

    foreach (
        [
            'example/src/Documents/GetDocument/DocumentDetails.php',
            'example/src/Documents/ListDocuments/DocumentSummary.php',
        ] as $authoritativeTitleProjection
    ) {
        $contents = file_get_contents($root . '/' . $authoritativeTitleProjection);

        if (is_string($contents) && (
            str_contains($contents, 'strlen($title) > 512')
            || str_contains($contents, 'strlen($title) <= 512')
            || str_contains($contents, 'MAX_TITLE_BYTES')
        )) {
            $failures[] = "Authoritative title projection {$authoritativeTitleProjection} must not inherit the cache-only 512-byte bound.";
        }
    }

    $applicationCommands = file_get_contents($root . '/example/src/Cli/ApplicationCommands.php');

    if (is_string($applicationCommands)) {
        $clockPosition = strpos($applicationCommands, 'intdiv($this->clock->now(), 60)');
        $cadencePosition = strpos($applicationCommands, 'if ($currentMinute % 5 !== 0)');
        $databasePathPosition = strpos($applicationCommands, '$databasePath = $this->existingDatabasePath();');
        $redisPosition = strpos($applicationCommands, 'RedisScheduleRunLease::connect(');

        if (
            $clockPosition === false
            || $cadencePosition === false
            || $databasePathPosition === false
            || $redisPosition === false
            || $clockPosition >= $cadencePosition
            || $cadencePosition >= $databasePathPosition
            || $databasePathPosition >= $redisPosition
        ) {
            $failures[] = 'The example schedule must evaluate clock and cadence before database-path and Redis work.';
        }
    }

    $responseEmitter = file_get_contents($root . '/src/Http/ResponseEmitter.php');

    if (is_string($responseEmitter) && str_contains($responseEmitter, 'Request $request')) {
        $failures[] = 'ResponseEmitter must remain request-unaware; HEAD behavior is explicit application-owned routing.';
    }

    foreach (['verification/ApplicationChecker.php', 'verification/SyntaxProfile.php'] as $consumerValidityPath) {
        $contents = file_get_contents($root . '/' . $consumerValidityPath);

        if (is_string($contents) && (
            str_contains($contents, 'SessionCleanupFailed')
            || str_contains($contents, 'Final response status must')
            || str_contains($contents, 'Response framing is invalid')
        )) {
            $failures[] = "ADR 045 must not become a consumer checker rule: {$consumerValidityPath}.";
        }
    }

    foreach (canonicalCrudTreeFailures($root) as $canonicalCrudTreeFailure) {
        $failures[] = $canonicalCrudTreeFailure;
    }

    $contextValidityForbiddenMarkers = [
        'verification/ApplicationChecker.php' => ['context-size', 'repeated-rule', 'context report'],
        'verification/SyntaxProfile.php' => ['context-size', 'repeated-rule', 'context report'],
        'composer.json' => ['"test:context', '"context:report'],
    ];

    foreach ($contextValidityForbiddenMarkers as $relativePath => $markers) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            $failures[] = "Cannot read consumer-validity boundary artifact {$relativePath}.";
            continue;
        }

        foreach ($markers as $marker) {
            if (str_contains(strtolower($contents), strtolower($marker))) {
                $failures[] = "Consumer-validity boundary artifact {$relativePath} adds forbidden context validity: {$marker}";
            }
        }
    }

    foreach (['tools/context-report.php', 'tools/report-context.php', 'tools/test-context-size.php'] as $forbiddenContextReport) {
        if (is_file($root . '/' . $forbiddenContextReport)) {
            $failures[] = "A context report script must not become a consumer validity mechanism: {$forbiddenContextReport}.";
        }
    }

    $versionNeutralReleaseContractMarkers = [
        '.ai/README.md' => [
            '| Prepare or publish a release | `RELEASING.md` | proposal or approved scope as applicable, explicit authority, exact candidate commits only after approval, CI, packages, and public-install proof |',
        ],
        '.ai/application-context.md' => [
            'Keep `RELEASING.md` as the detailed current mutable release-state owner.',
            'Consumer-facing summaries may project only their audience-specific boundary and must link back instead of copying the complete evidence.',
            'Reconcile that owner, every summary, the guard markers, and installed-consumer proof together; preserve tagged and explicitly historical source-preparation text unchanged.',
            'Record a source and verification date for volatile scale or operational claims.',
        ],
        '.ai/testing.md' => [
            'The local package-archive proof always compares the complete Composer archive inventory with the explicit release allowlist.',
            'Its independent Git-export subproof returns exactly `verified` only after Git status inspection reports a clean worktree',
            'any tracked-unstaged, staged, or untracked state returns exactly `skipped-dirty`.',
            'A `skipped-dirty` development run continues the Composer archive, isolated installation, installed checker, application behavior, and adversarial controls',
            'Status inspection failure or clean Git-archive creation, reading, or comparison failure remains a hard failure.',
            'fixed state and terminal outputs expose no Git status bytes, source bytes, absolute paths, or untracked filenames.',
            'Only a terminal `git-export-parity=verified` result is local Git-export release evidence',
            'Prerelease publication separately verifies the actual version-pinned Packagist-preferred dist because a local archive cannot prove hosting-provider output.',
        ],
        '.github/workflows/ci.yml' => [
            'name: PHP ${{ matrix.php }} validity',
            'name: PDO transport (SQLite 3.45.1, MySQL 8.4.11, PostgreSQL 17.11)',
            'run: composer check',
        ],
        'README.md' => [
            'PHPThis is an experimental PHP 8.4 framework foundation for **AI-first authoring with human accountability**.',
            '## Current release state',
            '| Latest framework tag | Alpha 7, [`v0.1.0-alpha.7`](https://github.com/balgf/PHPThis/tree/v0.1.0-alpha.7), Consumer Contract version 13, Strict Profile version 3, and diagnostics `PHT001` through `PHT007` |',
            '| Latest proved application starter | Alpha 7 is the latest matching framework/skeleton pair with complete clean Packagist-only public-distribution evidence in [Issue #53](https://github.com/balgf/PHPThis/issues/53) |',
            '| Coordinated release status | Alpha 7 is the latest completed and announced coordinated release recorded by closed [Issue #53](https://github.com/balgf/PHPThis/issues/53), including both GitHub prereleases, the final announcement, and Issue closure |',
            '| Current post-tag `main` | Unreleased development source containing accepted ADRs 055 through 061, Consumer Contract version 18, Strict Profile version 4, PHT008',
            'Package availability and current release state are external facts',
            'The Alpha 7 framework tag is immutable.',
            'records the completed and announced coordinated release: both exact candidates, required CI, both immutable tags and Packagist versions, clean Packagist-only public-distribution proof, both GitHub prereleases, the final announcement, and Issue closure.',
            'preserve their acceptance-time `PENDING` values and non-authority statements as historical evidence.',
            'The post-tag `main` source is unreleased development work containing accepted ADRs 055 through 061, Consumer Contract version 18, Strict Profile version 4, PHT008',
            'runtime-boundary, and maintainer-evidence changes.',
            'The release process owns the detailed dated external-evidence record; live host availability must still be verified.',
            'Create the latest proved public framework/skeleton pair explicitly:',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.7'",
            'Issue #53 records the exact Alpha 7 skeleton and clean public-install evidence',
            '## Key documentation',
            '[Consumer Contract](docs/consumer-contract.md)',
            '[Knowledge map](docs/knowledge-map.md)',
            '[Alpha 7 release notes](docs/releases/0.1.0-alpha.7.md)',
            '[Security policy](SECURITY.md) and [release process](RELEASING.md)',
        ],
        'RELEASING.md' => [
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
            'The `PENDING` candidate values and non-authority statements in ADR 054, the tagged Alpha 7 source-preparation notes, and the approved Alpha 7 source-preparation subsection above are preserved acceptance-time history, not current publication state.',
            '## Reusable release state model',
            '**Latest recorded release:**',
            '**Unreleased `main`:**',
            '**Proposed next candidate:**',
            '**Approved candidate:**',
            'only an explicit accountable-human record may approve the exact version, framework and skeleton tags, framework candidate commit, planned release date, bounded scope, release notes, candidate-specific announcement text, and each authorized next operation.',
            'The skeleton candidate commit may remain explicitly `PENDING`',
            'Keep the planned release date distinct from the observed timestamp of every external publication operation.',
            'Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release, including both GitHub prereleases, the final announcement, and Issue closure.',
            'At this source revision, post-tag `main` includes accepted application-owned operation-coordination and concern-routing guidance, ADRs 055 through 061, Consumer Contract version 18, Strict Profile version 4, and PHT008.',
            'outer HTTP failure-boundary, and maintainer-evidence changes are accepted.',
            'This delta is not limited to release-state documentation.',
            'Those completed histories authorize no production use or later release operation.',
            'For a current candidate, neither partial evidence nor checklist position authorizes the next external write',
            'Authorization is enumerable, not implied by reaching a checklist step.',
            'source preparation; exact-candidate freeze and approval; framework commit and push; framework tag creation and push; framework Packagist update; skeleton commit and push; skeleton tag creation and push; skeleton Packagist update; either GitHub prerelease; and the final announcement.',
            'Preparing a proposal or accepted source scope, proving or publishing an approved candidate, and inspecting an older release are different tasks.',
            '## Version-neutral release gate',
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
        ],
        'ROADMAP.md' => [
            'Issue #37 records the exact framework and skeleton candidates, both tags and packages, clean exact `create-project` proof, both GitHub prereleases, and announcement.',
            'That completed Alpha 6 history grants no authority for a later release operation.',
            'At the completed Alpha 6 boundary, Alpha 6 and `v0.1.0-alpha.6` were the latest immutable framework tag and source boundary',
            'At the Alpha 7 source-preparation checkpoint, accepted post-Alpha-6 source included ADRs 048 through 054, Consumer Contract version 13',
            'Closed Issue #53 now records the completed coordinated Alpha 7 release',
            'both GitHub prereleases, the final announcement, and Issue closure.',
            'Complete for Alpha 7 source-preparation evidence:',
            'Model/context token telemetry was unavailable, and the accountable human accepted `UNAVAILABLE` with no lexical-token proxy as a bounded Alpha 7 deferral only.',
            'The real WebSocket migration and temporary proposed decision 002 remain outside Alpha 7 and unapproved',
            '## Phase 7: Alpha 7 release',
            'The first three bullets preserve the 2026-08-14 source-preparation checkpoint',
            'Complete for source-preparation authority: on 2026-08-14 (Asia/Manila), the accountable human accepted ADR 054, `0.1.0-alpha.7`, both `v0.1.0-alpha.7` tag names, the planned `2026-08-18` date, the release notes, and the bounded source-preparation state.',
            'Both exact candidate commits remain `PENDING`; no commit, push, tag, package, dedicated-skeleton change, GitHub prerelease, announcement, issue closure, or production mutation is authorized.',
            'Complete for the exact framework candidate, tag, required CI, automatic Packagist indexing, and preferred-distribution proof',
            'Complete for the exact dedicated-skeleton export, lock, candidate, required CI, tag, and automatic Packagist indexing',
            'Complete for the clean Packagist-only public-distribution path, exact generated and installed inventories, complete application gate, and loopback `GET /health`.',
            'Complete for the coordinated Alpha 7 release:',
            'Issue #53 records the framework and skeleton GitHub prereleases, the final candidate-specific announcement, and Issue closure',
            'The [release process](RELEASING.md) owns the detailed current record.',
            'That completed history grants no authority for a later release operation.',
        ],
        'SECURITY.md' => [
            'Alpha 7 and `v0.1.0-alpha.7` are the latest immutable framework tag and source boundary.',
            'Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release, including both GitHub prereleases, the final announcement, and Issue closure.',
            'That recorded completion is not a production-support commitment.',
            'Any approved prerelease candidate may be announced only after its complete public-artifact gate in [the release process](RELEASING.md) passes and both GitHub prereleases receive their exact separate authorizations.',
            'A partially published framework or skeleton remains unannounced until both packages, the clean public installation path, and the required GitHub prereleases are proved.',
            'This tracked policy does not prove continuing live publication state',
        ],
        'composer.json' => [
            '"test:consumer": "php tools/test-consumer-project.php"',
            '"@test:consumer"',
        ],
        'docs/getting-started.md' => [
            '## Prerelease boundary',
            '`v0.1.0-alpha.5` preserves that historical coordinated framework, skeleton, and public-install boundary.',
            '`v0.1.0-alpha.6` preserves its completed and announced coordinated framework, skeleton, and public-install boundary.',
            'Issue #37 records its exact framework and skeleton candidates, both tags and packages, clean exact `create-project` proof, both GitHub prereleases, and announcement.',
            'Package availability remains an external fact',
            'ADR 054 and `v0.1.0-alpha.7` define the latest immutable framework tag and source boundary, Consumer Contract version 13',
            'Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release',
            'clean Packagist-only public-distribution proof, both GitHub prereleases, the final announcement, and Issue closure.',
            'The `PENDING` values in ADR 054 and the tagged Alpha 7 notes remain acceptance-time history.',
            '[ADR 055](decisions/055-value-free-composer-configuration-scripts.md)',
            '[ADR 058](decisions/058-concern-local-ai-context-routing.md)',
            'The unreleased delta includes application-owned operation-coordination and concern-routing guidance, executable Composer-configuration and application-source checks, request/response runtime corrections, and maintainer proof hardening; it is not limited to release-state documentation.',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.7'",
            'Prerelease publication follows the complete version-neutral maintainer gate in [the release process](../RELEASING.md).',
            'A framework-only or skeleton-only publication is recorded as partial and is not announced as a complete release.',
            'This tracked guide records the proved Alpha 7 command but does not establish future availability',
        ],
        'docs/guardrails.md' => [
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
            'the root README is a concise consumer projection',
            'The root README proof also pins the exact Alpha 7 framework/starter command',
            'accepted ADRs 055 through 061, current Contract 18/Profile 4/PHT008',
            'the executable guard/checker/runtime-boundary and maintainer-evidence character of the accepted delta',
            'Concern-specific capability and evidence contracts remain in their routed guides rather than being repeated in the README.',
            'ordered local-proof-before-push, exact-CI, tag-creation-and-push',
            'rejects explicit versioned present-tense `partial`, `pending`, or `unannounced` claims',
            'This syntactic drift detector does not infer arbitrary semantic freshness.',
            'verifies no continuing live GitHub or Packagist state',
        ],
        'docs/knowledge-map.md' => [
            'Assess or prepare a proposed PHPThis release',
            'Prove or publish an approved PHPThis candidate',
            'Inspect an installed or historical PHPThis release',
            'exact framework and skeleton candidate commits recorded at their respective freeze points',
            'planned release date separate from observed publication timestamps',
            'distinct exact-candidate approval and separately enumerable preparation, commit/push, tag creation/push, package, GitHub-prerelease, and announcement authorization',
            'clean-tree local proof before push and exact pushed-commit CI',
            'exact-version clean public installation evidence',
        ],
        'tools/package-files.txt' => [
            'README.md',
            'RELEASING.md',
            'SECURITY.md',
            'docs/decisions/047-bounded-alpha-6-release-scope.md',
            'docs/decisions/054-bounded-alpha-7-release-scope.md',
            'docs/getting-started.md',
            'docs/guardrails.md',
            'docs/knowledge-map.md',
            'docs/releases/0.1.0-alpha.6.md',
            'docs/releases/0.1.0-alpha.7.md',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledReleaseGuidanceDistribution($installedFramework);',
        ],
        'tools/test-consumer-project/guidance.php' => [
            'function proveInstalledReleaseGuidanceDistribution(string $installedFramework): void',
            '$orderedReleaseMarkers = [',
            'Installed release guidance is missing or misorders marker:',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton phpthis-release-proof 'APPROVED_SKELETON_VERSION'",
            'PASS installed version-neutral release guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $versionNeutralReleaseContractMarkers, 'version-neutral release', $failures);

    $staleCompletedAlpha7ReleaseMarkers = [
        'README.md' => [
            'Alpha 7 remains partial pending both GitHub prereleases and the final announcement',
            'Alpha 6 remains the latest fully completed and announced coordinated release',
            'unreleased development work adopting ADR 056, ADR 057, ADR 059, and ADR 060',
        ],
        'RELEASING.md' => [
            'Alpha 7 remains an unannounced partial coordinated release',
            'Alpha 6 remains the latest fully completed and announced coordinated release',
            'post-tag `main` adopts ADR 056, ADR 057, ADR 059, and ADR 060',
            'either GitHub prerelease, the final announcement, Issue #53 closure',
        ],
        'ROADMAP.md' => [
            'both GitHub prereleases and the final announcement remain separately gated',
            'Open and separately gated: the framework GitHub prerelease',
        ],
        'SECURITY.md' => [
            'Alpha 7 remains an unannounced partial coordinated release',
            'Alpha 6 remains the latest fully completed and announced coordinated release',
        ],
        'docs/getting-started.md' => [
            '`v0.1.0-alpha.6` preserves the latest fully completed and announced coordinated framework',
            'Alpha 7 remains an unannounced partial coordinated release',
            'adopts [ADR 056](decisions/056-bounded-request-target-and-path-bytes.md), [ADR 057](decisions/057-distinct-named-sql-placeholder-occurrences.md), [ADR 059]',
        ],
        'docs/guardrails.md' => [
            'the unannounced partial coordinated-release status',
            'the current unreleased ADR 056/ADR 057/ADR 059/ADR 060',
            'while keeping both GitHub prereleases, the final announcement, Issue closure',
        ],
    ];

    forbidGuardrailArtifactMarkers(
        $root,
        $staleCompletedAlpha7ReleaseMarkers,
        'completed Alpha 7 release and current post-tag source boundary',
        $failures,
    );

    $historicalAlpha1IdentityArtifactMarkers = [
        'RELEASING.md' => [
            '## Approved Alpha 1 identity',
            'Composer version: `0.1.0-alpha.1`',
            'Framework tag: `v0.1.0-alpha.1`',
            'Skeleton tag: `v0.1.0-alpha.1`',
            'The exact candidate commit, release date, and accountable-human publication authorization belong in the external release evidence',
            'That approval did not itself authorize creation of either tag, either package-host entry, either GitHub release, or the announcement.',
            'Alpha 1 remained subject to the complete gate recorded by its tagged source',
        ],
        'docs/releases/0.1.0-alpha.1.md' => [
            'Release identity: `0.1.0-alpha.1`. Publication state is external',
            'external release evidence recorded with the release work item using the checklist in `RELEASING.md`',
            'They are intentionally not embedded in these tracked notes because changing them would produce a different candidate commit.',
            'The public `composer create-project --stability=alpha phpthis/skeleton` path is supported only when both packages are indexed',
            'It is not production-ready and makes no backward-compatibility promise across prereleases.',
        ],
        'docs/decisions/018-bounded-alpha-1-release-scope.md' => [
            'When this decision was accepted',
            'This decision does not record mutable publication state',
        ],
        'tools/package-files.txt' => [
            'docs/releases/0.1.0-alpha.1.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $historicalAlpha1IdentityArtifactMarkers, 'historical Alpha 1 identity', $failures);

    $historicalAlpha2IdentityArtifactMarkers = [
        'RELEASING.md' => [
            '## Approved Alpha 2 identity',
            'Composer version: `0.1.0-alpha.2`',
            'Framework tag: `v0.1.0-alpha.2`',
            'Skeleton tag: `v0.1.0-alpha.2`',
            'The accountable human approved the following release identity and gated publication sequence on 2026-07-21',
            'This approves the exact version and tag names and authorizes the following operations only after their preceding gates pass',
            'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
        ],
        'docs/releases/0.1.0-alpha.2.md' => [
            'Release identity: `0.1.0-alpha.2`. Publication state is external',
            'Identity and gated publication authorization do not announce either tag, either package, or the public installation path.',
            'It is not production-ready and makes no backward-compatibility promise across prereleases.',
            'external release evidence recorded through `RELEASING.md`',
            'composer create-project --stability=alpha phpthis/skeleton',
        ],
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => [
            'Status: accepted',
            'Framework core and release inventory must continue rejecting runtime namespaces or files that present an ORM',
        ],
        'tools/package-files.txt' => [
            'docs/releases/0.1.0-alpha.2.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $historicalAlpha2IdentityArtifactMarkers, 'historical Alpha 2 identity', $failures);

    $historicalAlpha3IdentityArtifactMarkers = [
        'RELEASING.md' => [
            '## Approved Alpha 3 identity',
            'Composer version: `0.1.0-alpha.3`',
            'Framework tag: `v0.1.0-alpha.3`',
            'Skeleton tag: `v0.1.0-alpha.3`',
            'The accountable human approved the following release identity and gated publication sequence on 2026-07-21',
            'prove the clean public installation path; create both GitHub prereleases; and announce Alpha 3.',
            'The exact candidate commits, release date, artifact references, and gate evidence belong in the external release evidence',
            'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
        ],
        'docs/releases/0.1.0-alpha.3.md' => [
            'Release identity: `0.1.0-alpha.3`. Publication state is external',
            'Identity and gated publication authorization do not announce either tag, either package, or the public installation path.',
            'It is not production-ready and makes no backward-compatibility promise across prereleases.',
            'external release evidence recorded through `RELEASING.md`',
            'composer create-project --stability=alpha phpthis/skeleton',
        ],
        'docs/decisions/031-bounded-alpha-3-release-scope.md' => [
            'Status: accepted',
            'Alpha 3 is accepted as a tooling-only release',
            'Publication state is external.',
            'Consumer Contract version 7, Strict Profile version 2, diagnostics `PHT001` through `PHT006`',
        ],
        'docs/decisions/README.md' => [
            '`031-bounded-alpha-3-release-scope.md`',
        ],
        'tools/package-files.txt' => [
            'docs/releases/0.1.0-alpha.3.md',
            'docs/decisions/031-bounded-alpha-3-release-scope.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $historicalAlpha3IdentityArtifactMarkers, 'historical Alpha 3 identity', $failures);

    $historicalAlpha4IdentityArtifactMarkers = [
        'RELEASING.md' => [
            '## Approved Alpha 4 identity',
            'Composer version: `0.1.0-alpha.4`',
            'Framework tag: `v0.1.0-alpha.4`',
            'Skeleton tag: `v0.1.0-alpha.4`',
            'The accountable human approved the following release identity and gated publication sequence on 2026-07-23',
            'prove the clean public installation path; create both GitHub prereleases; and announce Alpha 4.',
            'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
        ],
        'docs/releases/0.1.0-alpha.4.md' => [
            'Release identity: `0.1.0-alpha.4`. Publication state is external',
            'Identity and gated publication authorization do not announce either tag, either package, or the public installation path.',
            'It is not production-ready and makes no backward-compatibility promise across prereleases.',
            'Consumer Contract version 7 to version 9',
            'At the Alpha 4 tag, `docs/consumer-contract.md` defined Consumer Contract version 9',
            'composer create-project --stability=alpha phpthis/skeleton',
        ],
        'docs/decisions/035-bounded-alpha-4-release-scope.md' => [
            'Status: accepted',
            'Alpha 4 is accepted as the bounded rollup of the changes after Alpha 3',
            'Composer version: `0.1.0-alpha.4`',
            'framework tag: `v0.1.0-alpha.4`',
            'skeleton tag: `v0.1.0-alpha.4`',
            'Strict Profile version 2 and permanent diagnostics `PHT001` through `PHT006`',
            'Alpha 4 does not add or permit an ORM',
        ],
        'docs/decisions/README.md' => [
            '`035-bounded-alpha-4-release-scope.md`',
        ],
        'tools/package-files.txt' => [
            'docs/releases/0.1.0-alpha.4.md',
            'docs/decisions/033-application-owned-request-handler-decorators.md',
            'docs/decisions/035-bounded-alpha-4-release-scope.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $historicalAlpha4IdentityArtifactMarkers, 'historical Alpha 4 identity', $failures);

    $historicalAlpha5IdentityArtifactMarkers = [
        'RELEASING.md' => [
            '## Approved Alpha 5 identity',
            'Composer version: `0.1.0-alpha.5`',
            'Framework tag: `v0.1.0-alpha.5`',
            'Skeleton tag: `v0.1.0-alpha.5`',
            'The accountable human approved preparation of the following bounded release scope and exact identity on 2026-08-01',
            'This approval authorizes source preparation and local verification only.',
            'Those external operations require later explicit accountable-human authorization after the candidate evidence is reviewed.',
            'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
        ],
        'docs/releases/0.1.0-alpha.5.md' => [
            'Release identity: `0.1.0-alpha.5`. Publication state is external',
            'Identity and candidate-preparation approval do not announce or authorize either tag, either package, the dedicated-skeleton update, a GitHub release, or the public installation path.',
            'It is not production-ready and makes no backward-compatibility promise across prereleases.',
            'Alpha 4 consumers move from Consumer Contract version 9 to version 10 and Strict Profile version 2 to version 3',
            'At the Alpha 4 tag, `docs/consumer-contract.md` defined Consumer Contract version 9 and Strict Profile version 2.',
            'composer create-project --stability=alpha phpthis/skeleton',
        ],
        'docs/decisions/040-bounded-alpha-5-release-scope.md' => [
            'Status: accepted',
            'Alpha 5 is accepted as the bounded rollup of exactly these changes after Alpha 4',
            'Composer version: `0.1.0-alpha.5`',
            'framework tag: `v0.1.0-alpha.5`',
            'skeleton tag: `v0.1.0-alpha.5`',
            'Strict Profile version 2 to version 3 with permanent diagnostic `PHT007`',
            'Alpha 5 does not add or permit an ORM',
            'Publication state is external.',
        ],
        'docs/decisions/README.md' => [
            '`040-bounded-alpha-5-release-scope.md`',
        ],
        'tools/package-files.txt' => [
            'docs/releases/0.1.0-alpha.5.md',
            'docs/decisions/036-one-typed-application-configuration-boundary.md',
            'docs/decisions/040-bounded-alpha-5-release-scope.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $historicalAlpha5IdentityArtifactMarkers, 'historical Alpha 5 identity', $failures);

    $historicalAlpha6PreparationArtifactMarkers = [
        'RELEASING.md' => [
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
        ],
        'docs/decisions/047-bounded-alpha-6-release-scope.md' => [
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
        'docs/releases/0.1.0-alpha.6.md' => [
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
        'docs/decisions/README.md' => [
            '`047-bounded-alpha-6-release-scope.md`',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/047-bounded-alpha-6-release-scope.md',
            'docs/releases/0.1.0-alpha.6.md',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $historicalAlpha6PreparationArtifactMarkers,
        'historical Alpha 6 source preparation',
        $failures,
    );

    $approvedAlpha7PreparationArtifactMarkers = [
        'RELEASING.md' => [
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
        ],
        'docs/decisions/054-bounded-alpha-7-release-scope.md' => [
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
        'docs/releases/0.1.0-alpha.7.md' => [
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
        'docs/decisions/README.md' => [
            'Proposed records:',
            '- None.',
            'Accepted records:',
            '`054-bounded-alpha-7-release-scope.md`',
            '`059-bounded-application-source-prefix-discovery.md`',
            '`060-reject-pending-output-before-response-emission.md`',
            '`061-fail-closed-outer-http-failure-disclosure-profiles.md`',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/054-bounded-alpha-7-release-scope.md',
            'docs/releases/0.1.0-alpha.7.md',
        ],
        'tools/test-consumer-project/guidance.php' => [
            'function proveInstalledReleaseGuidanceDistribution(string $installedFramework): void',
            "\$installedFramework . '/docs/decisions/054-bounded-alpha-7-release-scope.md'",
            "\$installedFramework . '/docs/releases/0.1.0-alpha.7.md'",
            '# PHPThis 0.1.0-alpha.7',
            'Source-preparation status: accepted on 2026-08-14 (Asia/Manila)',
            'Exact framework candidate commit: `PENDING`',
            'Exact skeleton candidate commit: `PENDING`',
            'PASS installed version-neutral release guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $approvedAlpha7PreparationArtifactMarkers,
        'approved Alpha 7 source preparation',
        $failures,
    );

    forbidGuardrailArtifactMarkers(
        $root,
        [
            'RELEASING.md' => [
                '## Proposed Alpha 7 source preparation',
                'Issue #53 tracks a proposal to prepare the following release identity and source scope for accountable-human review:',
            ],
            'docs/decisions/054-bounded-alpha-7-release-scope.md' => [
                'Status: proposed',
                'On 2026-08-13 in Asia/Manila, the accountable human asked maintainers to begin Alpha 7 preparation.',
            ],
            'docs/releases/0.1.0-alpha.7.md' => [
                '# Proposed PHPThis 0.1.0-alpha.7',
                'Status: proposed',
                'These draft notes are a source-preparation review artifact.',
                '## Proposed breaking prerelease change: bounded response cookies',
            ],
        ],
        'accepted Alpha 7 stale proposal boundary',
        $failures,
    );

    $currentConsumerContractVersionMarkers = [
        'docs/consumer-contract.md' => 'Contract version: 18',
        'docs/getting-started.md' => 'contract-version-18 Composer scripts',
        'skeleton/.ai/README.md' => 'Consumer Contract v18 and Strict Profile v4 remain mandatory.',
        'skeleton/.ai/rules.md' => 'These rules supplement installed PHPThis Consumer Contract v18 and Strict Profile v4',
        'templates/application/.ai/README.md' => 'Consumer Contract v18 and Strict Profile v4 remain mandatory.',
        'templates/application/.ai/rules.md' => 'These rules supplement installed PHPThis Consumer Contract v18 and Strict Profile v4',
    ];

    foreach ($currentConsumerContractVersionMarkers as $relativePath => $marker) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents) || !str_contains($contents, $marker)) {
            $failures[] = "The current Consumer Contract version marker is missing from {$relativePath}.";
        }
    }

    $distinctNamedSqlPlaceholderArtifactMarkers = [
        'docs/decisions/057-distinct-named-sql-placeholder-occurrences.md' => [
            '# ADR 057: Distinct named SQL placeholder occurrences',
            'Status: accepted',
            'the accountable human approved a separate check-time rule',
            'Strict Profile version 4 carries `PHT001` through `PHT007` forward unchanged and adds permanent diagnostic `PHT008`.',
            'This is a fail-closed common lexical subset, not a dialect parser.',
            'every backtick and `#` spelling',
            'MySQL executable or optimizer-hint block comments',
            'the rest of that SQL alternative is scanned without further lexical exclusions',
            'this decision does not claim per-call runtime rejection.',
        ],
        'docs/decisions/README.md' => [
            '`057-distinct-named-sql-placeholder-occurrences.md`',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 18',
            '### Contract version 15',
            '`PHT008` rejects a repeated name and requires each occurrence to have its own explicit binding',
            'Version 15 adds no runtime SQL parser, binding-array comparison, positional-placeholder support, SQL rewrite, placeholder helper, query builder, dialect abstraction',
        ],
        'docs/strict-profile.md' => [
            'Profile version: 4',
            '| `PHT008` |',
            'Non-ignorable PHPStan rule `phpthis.pht008`',
            'Ambiguous SQLite bracket text',
            'PostgreSQL dollar-quote-looking text',
        ],
        '.ai/strict-profile.md' => [
            'Strict Profile v4 carries `PHT001` through `PHT007` forward and adds:',
            '`PHT008`:',
            'PHT006 retains finite SQL-shape ownership; PHT008 alone owns distinct named-placeholder occurrences',
        ],
        '.ai/static-analysis.md' => [
            'Enforce `PHT008` after PHT006 at the same direct `Connection` call sites',
            'Scan ambiguous SQLite brackets',
            'MySQL executable or optimizer-hint block comments',
            'Do not compare SQL with bindings',
        ],
        '.ai/database.md' => [
            'ADR 057 before changing distinct-placeholder enforcement',
            'PHT008 requires one distinct exact case-sensitive named parameter',
        ],
        '.ai/testing.md' => [
            'exact 53-proof-call order',
            '`PHT008` fixtures must reject a repeated exact case-sensitive named placeholder',
            'one such opener disables lexical exclusions for the rest of that alternative',
            'proveRepeatedSqlPlaceholderIsRejected(...)',
        ],
        'docs/database.md' => [
            'PHT008 then checks every PHT006-finite SQL alternative independently.',
            '`Connection` performs no corresponding SQL-text parse or runtime PHT008 rejection.',
        ],
        'docs/security.md' => [
            'Keep every exact case-sensitive named placeholder occurrence distinct within each PHT006-finite SQL alternative',
            'PHT008 does not validate SQL syntax, choose a dialect',
        ],
        'docs/static-analysis.md' => [
            '`PHT008` owns non-ignorable detection',
            'It scans ambiguous SQLite bracket text',
        ],
        'templates/application/AGENTS.md' => [
            'Consumer Contract v18 and Strict Profile v4 are the minimum accepted rules.',
        ],
        'templates/application/.ai/data.md' => [
            'Every data value is bound with a distinct exact case-sensitive named placeholder and separate binding for each occurrence',
        ],
        'templates/application/.ai/testing.md' => [
            'PHT006, PHT008, explicit tenant predicates',
            'PHT008 is static and does not imply runtime SQL-text rejection.',
        ],
        'skeleton/AGENTS.md' => [
            'Consumer Contract v18 and Strict Profile v4 are the minimum accepted rules.',
        ],
        'skeleton/.ai/data.md' => [
            'Every SQL data value must use a distinct exact case-sensitive named placeholder and binding for each occurrence',
        ],
        'skeleton/.ai/testing.md' => [
            'distinct names and bindings for every placeholder occurrence including repeated logical values',
            'PHT008 is static and does not imply runtime SQL-text rejection',
        ],
        'verification/phpstan/DistinctNamedSqlPlaceholderRule.php' => [
            'final class DistinctNamedSqlPlaceholderRule implements Rule',
            'ConnectionSqlRuleSupport::isFiniteNonBlankConstantString',
            'ConnectionSqlRuleSupport::hasRepeatedNamedPlaceholder',
            'ConnectionSqlRuleSupport::repeatedNamedPlaceholderError()',
        ],
        'verification/phpstan/ConnectionSqlRuleSupport.php' => [
            'public static function hasRepeatedNamedPlaceholder(string $sql): bool',
            '$lexicalExclusionsAllowed = false;',
            'private static function isPostgreSqlEscapeString',
            'private static function isPortableDashLineComment',
            'private static function afterLineComment',
            "->identifier('phpthis.pht008')",
        ],
        'verification/phpstan/extension.php' => [
            "require_once __DIR__ . '/DistinctNamedSqlPlaceholderRule.php';",
            'PHPThis\\Verification\\PHPStan\\DistinctNamedSqlPlaceholderRule::class',
        ],
        'tools/test-strict-profile.php' => [
            "'phpthis.pht008'",
            'SELECT ARRAY[:same::integer, :same::integer]',
            'SELECT (1 # :same::integer), :same::integer',
            'SELECT COALESCE($tag$, 0) + :same + :same',
            'SELECT 1 /*! + :same + :same */ AS total',
            'SELECT 1 /*+ :same :same */ AS total',
            'SELECT 1 ` :same ` :same',
            'SELECT 1--:same AS first_value, :same AS second_value',
            'SELECT [:same], :same',
            "SELECT ` ':same :same' `",
            'SELECT $tag$ /* :same :same */ $tag$',
            "SELECT #':same :same'",
            "SELECT 1--':same :same'",
            "SELECT [':same :same']",
            'PASS strict profile: PHT001 through PHT008',
        ],
        'tools/test-consumer-project.php' => [
            'proveRepeatedSqlPlaceholderIsRejected($project, $profileCommand, $environment);',
        ],
        'tools/test-consumer-project/profile-controls.php' => [
            'function proveRepeatedSqlPlaceholderIsRejected(',
            'Expected repeated Connection SQL placeholder to emit exactly one PHT008 finding.',
            '$githubAnnotationCount = preg_match_all(',
            'Installed PHT008 direct-call diagnostic changed.',
            'Distinct Connection SQL placeholder occurrences failed PHT008.',
        ],
        'tests/database-boundary.php' => [
            'SELECT :first_value AS first_value, :second_value AS second_value',
            'optional leading-colon normalization, and one counted and traced statement',
        ],
        'tools/test-database-drivers.php' => [
            ':selected_id AND id = :same_selected_id',
            'distinct named bindings, optional leading-colon normalization',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/057-distinct-named-sql-placeholder-occurrences.md',
            'verification/phpstan/DistinctNamedSqlPlaceholderRule.php',
        ],
        'ROADMAP.md' => [
            'Complete: ADR 057, Consumer Contract version 15, Strict Profile version 4, and PHT008',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $distinctNamedSqlPlaceholderArtifactMarkers,
        'distinct named SQL placeholder',
        $failures,
    );

    forbidGuardrailArtifactMarkers(
        $root,
        [
            'src/Database/Connection.php' => [
                'PHT008',
                'hasRepeatedNamedPlaceholder',
                'DistinctNamedSqlPlaceholder',
            ],
        ],
        'runtime SQL placeholder parser',
        $failures,
    );

    $configurationArtifactMarkers = [
        'docs/decisions/036-one-typed-application-configuration-boundary.md' => [
            'Status: accepted',
            'Consumer Contract version 10 and Strict Profile version 3 add permanent structural rule `PHT007`.',
            'No application or deployment configuration runtime or class enters framework `src/`, and no runtime dependency is added.',
            'Adopted migration or administrative configuration never falls back to runtime configuration.',
        ],
        'docs/decisions/055-value-free-composer-configuration-scripts.md' => [
            '# ADR 055: Value-free Composer configuration scripts',
            'Status: accepted',
            'the accountable human directed PHPThis to prevent consumers from assigning application configuration in Composer scripts',
            'Consumer Contract version 13, Strict Profile version 3, and permanent diagnostics `PHT001` through `PHT007` remain unchanged',
            'It never repeats the script name, command, or right-hand value.',
        ],
        'docs/configuration.md' => [
            '# Application-owned configuration',
            'every read in the Composer project must occur in one PHP file',
            "\\getenv('APP_RUNTIME_DATABASE_DSN')",
            'private static function required(#[\\SensitiveParameter] string|false $value, int $maximumBytes): string',
            '->handle($_SERVER, $_GET, $_POST, $_FILES)',
            'HTTP calls only `forHttp()`.',
            'In the illustrated single-history case, that history\'s command calls only `forMigrations()`',
            'PHPThis does not load it',
            '#[\\SensitiveParameter]',
            '### Copyable child-process configuration evidence',
            'function runConfigurationCommand(',
            'stream_select(',
            'CONFIGURATION_PROCESS_WALL_LIMIT',
            'CONFIGURATION_PROCESS_OUTPUT_LIMIT',
            'function stopConfigurationProcess(',
            'function requireConfigurationOutputExcludes(',
            "|| !function_exists('posix_get_last_error')",
            "'' => 'APP_RUNTIME_MODE='",
            "fifth `proc_open` environment argument",
            'It treats an empty-string array key as a raw environment entry',
            'This is pinned PHP 8.4 implementation behavior rather than a general environment-array convention',
            'absence of deliberate parent-configuration inheritance',
            'This is application-owned test support for one fixed direct PHP child, not a general process runner, worker, or supervisor.',
            'The referenced configuration entrypoint must not spawn descendants.',
            'An adopted real command that can create descendants needs a separately reviewed platform-specific process-group, Job Object, container, or supervisor boundary',
            'Root Composer scripts are orchestration, not configuration delivery.',
            'reports only the input name, never a script name, command, or assigned value',
        ],
        'docs/consumer-contract.md' => [
            '## Application configuration',
            'PHT007',
            'For each adopted process profile, keep its runtime, worker, migration, or administrative input names, factories, and output types separate.',
            'non-secret configuration reference',
            'A configuration-free application records `NOT_APPLICABLE(CONFIGURATION)`',
            'keep root Composer script command text free of assignments or direct mutations',
            'The cross-platform lexical check is case-insensitive and conservatively rejects the adopted `KEY=` spelling',
        ],
        'docs/decisions/README.md' => [
            "`035-bounded-alpha-4-release-scope.md`\n- `036-one-typed-application-configuration-boundary.md`",
        ],
        'docs/strict-profile.md' => [
            'Profile version: 4',
            '`PHT007`',
            'one application-owned PHP file',
        ],
        'templates/application/.ai/configuration.md' => [
            '{{CONFIGURATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}',
            '{{CONFIGURATION_PROFILE_CREDENTIAL_SEPARATION_OR_NOT_APPLICABLE}}',
            '{{CONFIGURATION_REDACTION_EVIDENCE_OR_NOT_APPLICABLE}}',
            'their command text remains value-free for every adopted input name',
        ],
        'skeleton/.ai/configuration.md' => [
            '`NOT_APPLICABLE(CONFIGURATION)`',
            'The health-only skeleton reads no process environment',
            'Composer aliases may invoke a recorded entrypoint but remain value-free',
        ],
        'example/.ai/configuration.md' => [
            '# Example application configuration context',
            'not the standalone skeleton consumer checked by `ApplicationChecker`',
            '`NOT_APPLICABLE(PROCESS_ENVIRONMENT)`',
            'HTTP reaches only `http()`',
            'does not prove production operating-system identities or database grants',
        ],
        'verification/EnvironmentAccessProfile.php' => [
            'final class EnvironmentAccessProfile',
            'public static function inspect(string $contents, string $relativePath): array',
            'public static function boundaryFailures(array $readsByFile): array',
            'private static function isLiteralCallableReference(',
            'private static function isConstantLookupArgument(array $tokens, int $index): bool',
            'private static function isCanonicalServerTransportHandoff(',
            'PHT007',
            'keys: list<string>',
            'private static function validGetenvKey(',
        ],
        'verification/ApplicationChecker.php' => [
            "'.ai/configuration.md'",
            'EnvironmentAccessProfile::inspect(',
            'EnvironmentAccessProfile::boundaryFailures($environmentReads)',
            'composerScriptConfigurationFailures($composer, $environmentKeys)',
            'composerCommandContainsEnvironmentMutationText(',
            'keep Composer command text value-free and supply configuration at the outer process boundary',
        ],
        'bin/phpthis' => [
            "require_once dirname(__DIR__) . '/verification/EnvironmentAccessProfile.php';",
        ],
        'src/Database/Connection.php' => [
            '#[\\SensitiveParameter]',
        ],
        'tests/database-boundary.php' => [
            'connection marks only its password argument as sensitive',
        ],
        'tools/test-strict-profile.php' => [
            "'PHT007'",
            'PHT007 invalid-access fixture diagnostics changed.',
            "'keys' => ['APP_RUNTIME_DATABASE_DSN', 'APP_RUNTIME_DATABASE_USERNAME']",
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledTypedConfiguration($project, $profileCommand, $environment);',
            'proveInstalledConfigurationEvidenceReference(',
            'proveConfigurationContextIsRequired($project, $profileCommand, $environment);',
            'proveEnvironmentAccessIsRejected($project, $profileCommand, $environment);',
        ],
        'tools/test-consumer-project/configuration.php' => [
            "selectOneRow('SELECT 1 AS configured')",
            'PASS installed runtime typed configuration delivery',
            'PASS installed migration typed configuration delivery',
            'The installed configuration evidence reference is missing.',
            'PASS child-process configuration evidence',
            'fresh direct-child processes with an explicit synthetic application environment and no null inheritance',
            'concurrently drains separated stdout and stderr under a 5,000-millisecond deadline and 65,536-byte per-stream ordinary bound',
            'proves stderr pressure before stdout, fixed redacted timeout and output-limit failures, direct-child termination and reaping, and PID-file cleanup',
            'The entrypoint is forbidden from spawning descendants; an outer job timeout remains defense in depth.',
            'PASS installed empty configuration delivery',
            'final class ReferenceEmptyRuntimeMode extends InvalidArgumentException',
            'catch (ReferenceEmptyRuntimeMode)',
            'The installed missing runtime mode was misclassified as empty.',
            "'disable_functions=posix_get_last_error'",
            'The installed public configuration evidence reference required partial POSIX PID observation.',
            'function proveComposerScriptsCannotAssignApplicationConfiguration(',
            'PHPTHIS_COMPOSER_CONFIGURATION_SECRET_SENTINEL',
            'Value-free Composer entrypoints or an unrelated tooling assignment unexpectedly failed.',
        ],
        'tools/test-consumer-project/support.php' => [
            'function requireExactProcessResult(',
            'function requireExactFailureLines(',
            'function environmentWithEmptyValue(',
        ],
        'tools/test-consumer-project/profile-controls.php' => [
            'function proveConfigurationContextIsRequired(',
            'function proveEnvironmentAccessIsRejected(',
        ],
        'docs/guardrails.md' => [
            'extracts the exact application-owned child-process reference from installed `docs/configuration.md`',
            "PHP 8.4's empty-string-key raw `NAME=` environment-entry form",
            'invokes the matching factory and proves that this raw form reaches its exact empty-value validation branch',
            'a paired run with the same variable omitted proves that the missing-value branch remains distinct',
            'an explicit synthetic application environment through the fifth `proc_open` argument instead of null inheritance',
            'explicit binary pipe descriptors and working directory',
            'concurrently drains separated nonblocking streams under a 5,000-millisecond deadline and 65,536-byte ordinary per-stream caps',
            'Fixed redacted timeout and output-limit failures terminate, verify the direct child stopped before reap, and remove PID evidence in `finally`.',
            'The fixed configuration entrypoint may not spawn descendants',
            'An outer job timeout remains defense in depth.',
            'does not claim that the host, executable, or PHP runtime adds no required environment entries',
            'does not prove application-specific validation, deployment safety, or redaction outside the captured streams',
            'ADR 055 adds a second ordinary consistency check without changing PHT007',
            'reports only the input name without a script name, command, or value',
        ],
        '.ai/application-context.md' => [
            'Keep every application Composer alias value-free for adopted configuration.',
            'inherited ambient authority, referenced scripts, plugins, dynamic or escaped names, profile completeness, and pre-check lifecycle execution remain explicit review limits',
        ],
        '.ai/static-analysis.md' => [
            "Keep ADR 055's Composer/configuration consistency check ordinary and separate from PHT007.",
            'reports only the input name',
        ],
        '.ai/testing.md' => [
            "ADR 055's ordinary Composer/configuration consistency check also has no `PHT` identifier.",
            'Every mutation restores the consumer manifest in `finally`.',
        ],
        'tools/package-files.txt' => [
            'docs/configuration.md',
            'docs/decisions/036-one-typed-application-configuration-boundary.md',
            'docs/decisions/055-value-free-composer-configuration-scripts.md',
            'templates/application/.ai/configuration.md',
            'verification/EnvironmentAccessProfile.php',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $configurationArtifactMarkers, 'configuration-boundary', $failures);

    $localEnvironmentLauncherArtifactMarkers = [
        'docs/decisions/050-application-owned-local-environment-launcher.md' => [
            '# ADR 050: Application-owned local environment launcher',
            'Status: accepted',
            'The checked reference is invoked explicitly through PHP CLI as `php ./bin/application jobs:run-one` or `php ./bin/application database:migrate`.',
            'The array-form `proc_open` call supplies exactly the selected application triplet as its explicit child environment.',
            'Without that application-specific factory and evidence, the checked block remains transport proof rather than adopted application configuration.',
            'The health-only skeleton retains `NOT_APPLICABLE(CONFIGURATION)`, `NOT_APPLICABLE(CLI)`, and `NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)` and ships no launcher file.',
        ],
        'docs/decisions/README.md' => [
            '- `050-application-owned-local-environment-launcher.md`',
            'It is authoritative for the checked application-owned launcher pattern',
        ],
        'docs/configuration.md' => [
            '[application-owned local environment launcher reference](configuration/local-environment-launcher.md)',
            'it is not a dotenv API, configuration cache, `config:clear` facility, or production secret-delivery mechanism.',
        ],
        'docs/configuration/local-environment-launcher.md' => [
            '# Application-owned local environment launcher',
            '## Copyable `bin/application` reference',
            '## Copyable `src/Configuration/ApplicationEnvironment.php` reference',
            '## Private child handoff',
            '!isset($argv)',
            "fwrite(STDERR, \"{\\\"error\\\":\\\"invalid_arguments\\\"}\\n\");",
            "fwrite(STDERR, \"{\\\"error\\\":\\\"unknown_command\\\"}\\n\");",
            "fwrite(STDERR, \"{\\\"error\\\":\\\"local_environment_failed\\\"}\\n\");",
            'final readonly class WorkerLauncherTransport',
            'final readonly class MigrationLauncherTransport',
            'public static function workerForLocalLauncher(string $root): WorkerLauncherTransport',
            'public static function migrationsForLocalLauncher(string $root): MigrationLauncherTransport',
            '$contents = @stream_get_contents($stream, 65_537);',
            'strlen($contents) > 65_536',
            'count($lines) > 256',
            'strlen($line) > 4_225',
            'strlen($value) > 4_096',
            "preg_match('/\\A[A-Z][A-Z0-9_]{0,127}\\z/D', \$name)",
            "['bypass_shell' => true]",
            'Every selected inherited value and every represented local value receives the same transport validation',
            'They are not final application configuration types and the private child does not call them.',
        ],
        'docs/cli.md' => [
            '## Optional local environment launcher',
            'php ./bin/application jobs:run-one',
            'php ./bin/application database:migrate',
            'Follow [Application-owned local environment launcher](configuration/local-environment-launcher.md) for the checked PHP reference.',
        ],
        'docs/cli/README.md' => [
            '[Application-owned local environment launcher](../configuration/local-environment-launcher.md)',
            'The checked launcher is an ordinary application-owned PHP file invoked through explicit PHP CLI, not a framework command.',
        ],
        'docs/cli/arguments-output.md' => [
            '`php ./bin/application <command>`',
            '`{"error":"local_environment_failed"}\\n`',
        ],
        'docs/cli/composition.md' => [
            'array-form shell-free `proc_open` with an explicit environment containing only the selected application profile',
        ],
        'docs/cli/testing.md' => [
            'execute the real PHP file separately in fresh subprocesses',
            'Clean every file and sentinel in `finally`.',
        ],
        'docs/knowledge-map.md' => [
            '| Adopt, change, or review a local development environment launcher | `docs/configuration/local-environment-launcher.md`',
            "verify that no framework loader, automatic bootstrap, dotenv dependency, configuration cache, `config:clear` command, launcher-specific Contract/Profile/PHT/checker change beyond ADR 055's separate command-text rule",
        ],
        '.ai/README.md' => [
            '| Change local environment launcher guidance or its checked reference | `.ai/application-context.md` |',
            'accepted ADR 050, checked application-owned reference',
        ],
        '.ai/application-context.md' => [
            'Preserve accepted ADR 050 and its optional application-owned boundary.',
            "Do not add a framework or skeleton launcher, automatic PHP loading, dotenv dependency, configuration cache, `config:clear`, any launcher-specific Contract/Profile/PHT/checker change beyond ADR 055's separate command-text consistency rule",
        ],
        '.ai/cli.md' => [
            'an optional application-owned PHP launcher invoked as `php ./bin/application <command>`',
            'array-form shell-free `proc_open` with inherited `STDIN`, `STDOUT`, and `STDERR` descriptor resources',
        ],
        '.ai/testing.md' => [
            'exactly `php ./bin/application jobs:run-one` and `php ./bin/application database:migrate`',
            'This installed proof certifies launcher transport only',
        ],
        'templates/application/.ai/README.md' => [
            '| Adopt or change a local development environment launcher | installed `vendor/phpthis/framework/docs/configuration/local-environment-launcher.md`',
        ],
        'templates/application/.ai/configuration.md' => [
            '{{LOCAL_ENVIRONMENT_LAUNCHER_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{LOCAL_ENVIRONMENT_LAUNCHER_FILE_CONTRACT_OR_NOT_APPLICABLE}}',
            '{{LOCAL_ENVIRONMENT_LAUNCHER_PROFILE_PRECEDENCE_OR_NOT_APPLICABLE}}',
            '{{LOCAL_ENVIRONMENT_LAUNCHER_CHILD_ENVIRONMENT_OR_NOT_APPLICABLE}}',
            '{{LOCAL_ENVIRONMENT_LAUNCHER_FAILURE_RELOAD_AND_REDACTION_OR_NOT_APPLICABLE}}',
        ],
        'templates/application/.ai/cli.md' => [
            '{{CLI_LOCAL_ENVIRONMENT_LAUNCHER_FORWARDING_OR_NOT_APPLICABLE}}',
        ],
        'templates/application/.ai/operations.md' => [
            '{{LOCAL_ENVIRONMENT_LAUNCHER_OPERATIONS_OR_NOT_APPLICABLE}}',
            '{{PRODUCTION_CONFIGURATION_DELIVERY_OR_NOT_APPLICABLE}}',
        ],
        'templates/application/.ai/testing.md' => [
            '{{LOCAL_ENVIRONMENT_LAUNCHER_TEST_COMMAND_OR_NOT_APPLICABLE}}',
            'no wholesale environment inheritance, no launcher-side stream interception, and no secret argument',
        ],
        'skeleton/.ai/README.md' => [
            '| Adopt or change a local development environment launcher | installed `vendor/phpthis/framework/docs/configuration/local-environment-launcher.md`',
        ],
        'skeleton/.ai/configuration.md' => [
            '`NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`',
            'It therefore has no configuration reader, typed configuration value, local environment launcher, selected process profile, local configuration file, or launcher PHP file.',
        ],
        'skeleton/.ai/cli.md' => [
            '`NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`: the starter has no local launcher command',
        ],
        'skeleton/.ai/operations.md' => [
            'Local environment launcher: `NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`',
        ],
        'skeleton/.ai/testing.md' => [
            '`NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER_EVIDENCE)`',
            'no command, `exit`, substitution, backtick, expansion, redirection, or `PATH` side effect; and cleanup in `finally`.',
        ],
        'tools/test-consumer-project.php' => [
            "require_once __DIR__ . '/test-consumer-project/local-environment-launcher.php';",
            '$installedLocalEnvironmentLauncherProof = proveInstalledLocalEnvironmentLauncherReference(',
            "!== 'installed-local-environment-launcher-reference-proved'",
            'The installed local environment launcher proof did not complete.',
        ],
        'tools/test-consumer-project/local-environment-launcher.php' => [
            'function installedLocalEnvironmentLauncherReferences(string $installedFramework): array',
            'function proveInstalledLocalEnvironmentLauncherReference(',
            "'launcher' => '1696b1bb2694539588bad9a540bd4121a4a106bb3810803e742283e9f98e020a'",
            "'environment' => '18d2dce2559fe9d276a7430c438b409848a5949174e0c3c48cf70d388a4a3fb1'",
            "shell_exec('true')",
            "\$childEnvironment += ['PATH' => '/usr/bin']",
            'The local launcher exact-source mutation control failed.',
            "[PHP_BINARY, '-d', 'register_argc_argv=0', \$launcherPath]",
            '$environmentSourceWithoutExpectedReads = $references[\'environment\'];',
            'The local environment boundary literal-read inventory changed.',
            'The local environment boundary gained alternate process access.',
            '$expectedChildEnvironmentBlocks = [',
            "'[0 => STDIN, 1 => STDOUT, 2 => STDERR]'",
            'The local launcher selected-environment handoff changed.',
            "substr_count(\$references['launcher'], '\$childEnvironment = [') !== 2",
            'The local launcher child environment is not exact.',
            'The relative in-project launcher or selected-only child environment changed.',
            'An invalid inherited worker profile fell back to a valid local profile.',
            'The partial inherited {$profile} profile was merged with the local file.',
            'The partial local {$profile} profile was accepted.',
            "'duplicate key' =>",
            "'invalid complete non-selected profile' =>",
            "'final unterminated carriage return' =>",
            'Opaque local environment metacharacters changed.',
            'Executable local environment syntax ran.',
            'The 65,536-byte local-file boundary was rejected.',
            "'65,537-byte file' =>",
            'Fresh-process local environment reload changed.',
            'PASS installed local environment launcher propagated exit',
            'removeDirectory($launcherProject);',
            'The local environment launcher proof did not clean its workspace.',
            "return 'installed-local-environment-launcher-reference-proved';",
        ],
        'docs/guardrails.md' => [
            'accepted ADR 050, the packaged application-owned PHP launcher reference',
            'The local-environment-launcher guard separately pins accepted ADR 050',
            'Exact block hashes plus representative extra-shell-call and child-environment-mutation controls retain the complete executable reference source.',
            'One unconditional entrypoint completion check, terminal module sentinel, early-return mutation control, and post-`finally` workspace-absence assertion retain proof reachability and cleanup.',
            'The copied transport classes and `*ForLocalLauncher()` factories prove only atomic local source selection, transport bounds, and exact child delivery.',
        ],
        'tools/package-files.txt' => [
            'docs/configuration/local-environment-launcher.md',
            'docs/decisions/050-application-owned-local-environment-launcher.md',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $localEnvironmentLauncherArtifactMarkers,
        'local environment launcher',
        $failures,
    );

    forbidGuardrailArtifactMarkers(
        $root,
        [
            'docs/consumer-contract.md' => [
                'LOCAL_ENVIRONMENT_LAUNCHER',
            ],
            'docs/strict-profile.md' => [
                'LOCAL_ENVIRONMENT_LAUNCHER',
                'local-environment-launcher.md',
            ],
            'verification/ApplicationChecker.php' => [
                'LOCAL_ENVIRONMENT_LAUNCHER',
                'LocalEnvironmentLauncher',
                'local-environment-launcher',
            ],
            'tools/test-consumer-project/configuration.php' => [
                'proveInstalledLocalEnvironmentLauncherReference',
                'LocalEnvironmentLauncher',
            ],
        ],
        'local environment launcher boundary',
        $failures,
    );

    $applicationOwnedOperationCoordinationArtifactMarkers = [
        '.ai/README.md' => [
            '| Change application-owned atomic-lock, mutex, mutual-exclusion, lease, critical-section, or coordination guidance | `.ai/application-context.md` |',
            '`docs/coordination.md`, knowledge and application task routes, template and skeleton operations records',
        ],
        '.ai/application-context.md' => [
            '`docs/coordination.md`',
            'Use the existing `.ai/operations.md` `OPERATION_COORDINATION` section as the sole record for a standalone named operation; do not add `.ai/coordination.md`.',
            'Keep scheduler policy in `.ai/cli.md`, migration-writer policy in `.ai/migrations.md`, durable-job ownership in `.ai/jobs.md`',
            'Add no framework lock, mutex, lease, fencing-token, coordination helper, facade, driver, registry, discovery, runtime dependency, checker rule, Contract/Profile change, or `PHT` diagnostic.',
        ],
        '.ai/testing.md' => [
            'proveInstalledApplicationOwnedOperationCoordinationGuidanceDistribution(...)` after the frontend-integration proof',
            'exact 53-proof-call order',
        ],
        'docs/coordination.md' => [
            '# Application-owned operation coordination',
            'Use this guide when a task names an atomic lock, mutex, mutual exclusion, lease, critical section, application coordination',
            'Do not add `.ai/coordination.md`',
            '**Critical section** names the exact interval',
            '**Mutual exclusion or mutex** means cooperating contenders',
            '**Lease** is expiring ownership.',
            '**Fencing** requires a monotonically ordered token',
            '**Idempotency or duplicate safety** bounds the effect of repeated work.',
            '**Cross-system atomicity** requires one proved transaction or protocol',
            '`NOT_APPLICABLE(OPERATION_COORDINATION)`',
            'maximum admitted work duration or an explicit `UNPROVED` duration limitation',
            'references to `.ai/testing.md` for real evidence',
            '## Bounded Redis schedule-lease reference',
            'one nonblocking `SET key token NX PX 30000`',
            'no proved numeric wall-clock maximum, so duration is `UNPROVED`',
            'Copy the reasoning fields, never the mechanism by default.',
            'This guidance adds no framework class, interface, trait, helper, facade, service, driver, registry, discovery, configuration, runtime dependency',
        ],
        'docs/consumer-contract.md' => [
            '## Optional application-owned operation coordination',
            'An application with no standalone operation-specific coordination records `NOT_APPLICABLE(OPERATION_COORDINATION)` in `.ai/operations.md`.',
            'Fencing requires an ordered token that every protected downstream effect validates',
            'ADR 028 remains one bounded Redis schedule-lease example, not a portable mechanism.',
            'This operation-coordination guidance adds no accepted PHP syntax, checker rule, Contract or Strict Profile version, diagnostic, runtime API, or dependency.',
        ],
        'docs/knowledge-map.md' => [
            '| Adopt, change, explain, or review an atomic lock, mutex, mutual exclusion, lease, critical section, or application coordination boundary | `docs/coordination.md`;',
            'verify that no framework helper, portable distributed-lock claim, or duplicate context owner was introduced',
        ],
        'docs/redis-coordination.md' => [
            'start with [Application-owned operation coordination](coordination.md).',
            'ADR 028 remains a bounded `schedule:run` reference only',
        ],
        'templates/application/.ai/README.md' => [
            '| Adopt or change an atomic lock, mutex, mutual exclusion, lease, critical section, or application coordination boundary | installed `vendor/phpthis/framework/docs/coordination.md` |',
        ],
        'templates/application/.ai/operations.md' => [
            '## Operation-specific coordination',
            '{{OPERATION_COORDINATION_RECORDS_OR_NOT_APPLICABLE}}',
            'references to the real concurrency, contention, expiry or cleanup, stale-owner, process-termination, outage, recovery and topology evidence owned by `.ai/testing.md`',
            'do not duplicate them or infer a portable lock abstraction.',
        ],
        'templates/application/.ai/testing.md' => [
            '{{OPERATION_COORDINATION_TEST_COMMAND_OR_NOT_APPLICABLE}}',
            'Every standalone operation-coordination adoption proves the exact record in `.ai/operations.md`',
        ],
        'skeleton/.ai/README.md' => [
            '| Adopt or change an atomic lock, mutex, mutual exclusion, lease, critical section, or application coordination boundary | installed `vendor/phpthis/framework/docs/coordination.md` |',
        ],
        'skeleton/.ai/operations.md' => [
            '## Operation-specific coordination',
            '`NOT_APPLICABLE(OPERATION_COORDINATION)`',
            'commands and results remain in `.ai/testing.md`',
            'Do not add a framework helper or `.ai/coordination.md`.',
        ],
        'skeleton/.ai/testing.md' => [
            'Standalone operation-coordination evidence: `NOT_APPLICABLE(OPERATION_COORDINATION_EVIDENCE)`',
            '`NOT_APPLICABLE(OPERATION_COORDINATION_EVIDENCE)`',
            'a mutex, lease, fencing token, idempotency key, and cross-system transaction remain distinct claims.',
        ],
        'docs/guardrails.md' => [
            'A separate installed distribution proof checks application-owned operation-coordination guidance',
            'It does not exercise a consumer backend, prove atomicity, timing, stale-owner rejection, failover, incident response, or production behavior',
        ],
        'tools/package-files.txt' => [
            'docs/coordination.md',
        ],
        'tools/guardrails/distribution.php' => [
            'count($packagePaths) !== 229',
            'current post-Alpha-7 release inventory must contain exactly 229 reviewed files',
            'ADR 058 concern-local context routing',
            'accepted ADR 059 bounded source-prefix discovery',
            'accepted ADR 060 pending-output response-emission preflight',
            'accepted ADR 061 outer HTTP failure disclosure',
            'immutable Alpha 7 remains the historical 218-file artifact',
        ],
        'tools/guardrails/repository.php' => [
            "'docs/coordination.md',",
            "'src/AtomicLock.php',",
            "'src/Support/DistributedLockInterface.php',",
            'Framework source contains a forbidden generic mechanism path:',
            'Default-skeleton source contains a forbidden generic mechanism path:',
            'Reviewed package inventory contains a forbidden generic mechanism path:',
            "'proveInstalledApplicationOwnedOperationCoordinationGuidanceDistribution',",
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledApplicationOwnedOperationCoordinationGuidanceDistribution(',
        ],
        'tools/test-consumer-project/guidance.php' => [
            "'docs/coordination.md' => '.ai/operations.md',",
            'function proveInstalledApplicationOwnedOperationCoordinationGuidanceDistribution(',
            'Installed coordination runtime-path forbidden fixture was accepted:',
            'Installed coordination guidance contains a forbidden {$runtimeOwner} runtime mechanism path:',
            'PASS installed application-owned operation coordination guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $applicationOwnedOperationCoordinationArtifactMarkers,
        'application-owned operation coordination guidance',
        $failures,
    );

    foreach (
        [
            '.ai/coordination.md',
            'templates/application/.ai/coordination.md',
            'skeleton/.ai/coordination.md',
        ] as $duplicateCoordinationContextPath
    ) {
        if (
            file_exists($root . '/' . $duplicateCoordinationContextPath)
            || is_link($root . '/' . $duplicateCoordinationContextPath)
        ) {
            $failures[] = "Application-owned operation coordination must use existing .ai/operations.md rather than {$duplicateCoordinationContextPath}.";
        }
    }

    $startupProbeSemanticsArtifactMarkers = [
        '.ai/README.md' => [
            '| Change startup, liveness, dependency health, or readiness semantics | `.ai/application-context.md` | bootstrap, front controller, exact probe claim, and behavior tests; add `.ai/database.md` only when a database dependency is entered |',
        ],
        '.ai/application-context.md' => [
            'That sink\'s destination may itself involve network or remote-filesystem I/O.',
            'Until its destination and latency are verified, describe the starter only as the current liveness route and HTTP composition proof, not as external-service-independent liveness.',
            'Do not add a framework probe API, lazy connection, hidden bypass, second HTTP execution path, universal readiness definition, or checker diagnostic for operational semantics.',
        ],
        'src/Database/Connection.php' => [
            'PDO::connect($dsn, $username, $password, $defaults + $options),',
        ],
        'docs/configuration.md' => [
            '### Eager composition and probe semantics',
            '`Connection::connect()` calls the native `PDO::connect()` factory immediately rather than returning a deferred handle.',
            'Depending on the selected driver and DSN, connection creation may perform database, filesystem, or network I/O and may fail during composition.',
            'Successful connection construction is also not evidence of schema compatibility, migration completion, capacity, per-operation database authority, or complete application readiness.',
            'Failure isolation that preserves a selected response does not by itself bound a synchronous sink\'s latency or make that probe external-service-independent.',
            'Do not disguise a dependency bypass as the ordinary application bootstrap or add a second hidden HTTP execution path.',
        ],
        'docs/knowledge-map.md' => [
            'Define, change, or review startup, liveness, dependency health, or readiness semantics',
            'verify that no framework probe API, lazy connection, hidden bypass, or second HTTP execution path was introduced',
        ],
        'docs/vocabulary.md' => [
            '| external-service-independent liveness |',
            '| readiness | application-owned operational claim that its recorded conditions for receiving traffic are satisfied |',
        ],
        'docs/guardrails.md' => [
            'A separate installed distribution proof checks the eager-composition and probe-semantics clarification',
            'the current starter does not claim external-service independence while its deployment-configured `error_log` destination and latency remain unverified',
            'does not connect to a service, prove that a deployment classified a probe correctly, establish dependency availability or traffic readiness',
        ],
        'templates/application/.ai/README.md' => [
            '| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |',
        ],
        'templates/application/.ai/operations.md' => [
            '{{HEALTH_AND_READINESS_PATHS}}',
            '`Connection::connect()` constructs PDO eagerly and, depending on the selected driver and DSN, may perform I/O or fail during composition.',
            'must not be described as external-service-independent liveness.',
        ],
        'templates/application/.ai/testing.md' => [
            'Every adopted health, readiness, or non-HTTP probe proves the exact claim recorded in `.ai/operations.md`',
            'A caught sink failure proves response isolation, not a latency bound or independence from that sink\'s destination.',
            'Connection construction alone is not exact-statement database-authority or complete-readiness evidence.',
        ],
        'skeleton/.ai/README.md' => [
            '| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |',
        ],
        'skeleton/.ai/operations.md' => [
            '`GET /health` is the starter liveness route; no readiness route exists.',
            'It does not establish external-service-independent liveness because the deployment-configured `error_log` destination and its latency are unverified.',
            'covering success, mapped failure, unknown failure, captured summaries, throwing-sink isolation, and the real front controller.',
            '`Connection::connect()` constructs PDO eagerly and may fail during composition',
            'Do not preserve a liveness claim through a hidden bypass or second HTTP execution path.',
        ],
        'skeleton/.ai/observability.md' => [
            'calls deployment-configured `error_log` synchronously before the coordinator returns',
            'throwing-sink response isolation',
        ],
        'skeleton/.ai/testing.md' => [
            'This proves the current HTTP composition and response path, not external-service-independent liveness',
            'the coordinator invokes deployment-configured `error_log` synchronously and no destination or latency bound is recorded.',
            'do not treat connection construction as database-authority or complete-readiness evidence.',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledStartupProbeGuidanceDistribution($project, $installedFramework);',
        ],
        'tools/test-consumer-project/application.php' => [
            'function proveInstalledStartupProbeGuidanceDistribution(string $project, string $installedFramework): void',
            'PASS installed startup and probe guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $startupProbeSemanticsArtifactMarkers, 'startup and probe semantics', $failures);

    $databaseSetupScopeArtifactMarkers = [
        'AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
        ],
        'docs/decisions/037-database-setup-scope-gate.md' => [
            'Status: accepted',
            '> Please setup PostgreSQL as our main DB.',
            'database scope: add configuration structure only, connect to an existing server, or provision a project-local server',
            'schema scope: defer migrations or add an application-owned migration foundation',
            'This is an AI-authoring workflow clarification.',
        ],
        'docs/decisions/README.md' => [
            '`037-database-setup-scope-gate.md`',
        ],
        'docs/consumer-contract.md' => [
            'For an ambiguous database setup request, inspect the prompt and existing project state first.',
            'Ask all unresolved choices in one concise message',
            'Do not perform external database I/O, provision or mutate a server',
            'ADR 037 adds the early database setup scope gate as an AI-authoring workflow clarification',
        ],
        'docs/configuration.md' => [
            '## Scope database setup before implementation',
            '> Please setup PostgreSQL as our main DB.',
            'should I only add PostgreSQL configuration, connect this project to an existing PostgreSQL server, or provision a project-local PostgreSQL server?',
            'Record the non-secret input contract and add its typed parser or factory with parsing, failure, redaction, and child-process evidence.',
            'Configuration-only scope records infrastructure injection and connection evidence as deferred and does not create dead wiring.',
            'For PostgreSQL or another engine, first record the exact accepted initial baseline',
            'When migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests',
            'Provisioning and production evidence is required only for an explicitly selected scope.',
        ],
        'docs/evaluation.md' => [
            '## Database setup scope-gate evaluation',
            'A starter not-applicable marker does not answer that adoption question.',
            'no connection attempt or other external database I/O',
            'they do not prove that a particular model follows them or meets a duration target',
        ],
        'docs/knowledge-map.md' => [
            '| Select or set up a database engine |',
            'load and prove only the selected slice',
            'when a connection is adopted, record supported database/catalog/schema/attachment namespace selection and qualification',
        ],
        'docs/guardrails.md' => [
            "accepted ADR 037, its early application scope gate, configuration-only typed-boundary meaning, external-I/O prohibition before approval, conditional process profiles, package inventory, and installed-consumer guidance-distribution evidence remain present",
            'the guard also rejects the reviewed unconditional composition, elevated-profile, and template-placeholder wording',
            "It also verifies that the local skeleton and installed framework distribute ADR 037's database setup guidance.",
            'This distribution proof does not establish that an AI asks the scope question, avoids external database I/O, or meets a duration target',
        ],
        '.ai/application-context.md' => [
            'Keep the ADR 037 database setup scope gate in both application `AGENTS.md` entrypoints and change workflows.',
            'It records injection sites when process or infrastructure composition is selected, or explicitly deferred connection composition for configuration-only scope.',
        ],
        '.ai/configuration.md' => [
            'Give runtime, migration, administrative, worker, and other adopted identities distinct input names and final types without inheritance, combined credentials, or fallback.',
            'configuration-only scope records connection composition as explicitly deferred.',
        ],
        'templates/application/AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
            'An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.',
        ],
        'skeleton/AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
            'An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.',
        ],
        'templates/application/.ai/README.md' => [
            '| Select or set up a database engine |',
            'prompt and current configuration/data facts before any external action',
        ],
        'skeleton/.ai/README.md' => [
            '| Select or set up a database engine |',
            'prompt and current configuration/data facts before any external action',
        ],
        'templates/application/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            '> Please setup PostgreSQL as our main DB.',
            'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
        ],
        'skeleton/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            '> Please setup PostgreSQL as our main DB.',
            'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
        ],
        'templates/application/.ai/configuration.md' => [
            'Record only adopted external input contracts.',
            'do not store task scope or task history here',
            'Composition injection sites, or deferred connection composition for configuration-only scope',
        ],
        'skeleton/.ai/configuration.md' => [
            'Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption.',
            'one separately named factory, final readonly output type, and process identity for each adopted process profile',
            'child-process parser or adopted-entrypoint evidence',
        ],
        'templates/application/.ai/data.md' => [
            'Record one separate row per adopted migration history and one separate administrative row only when that path is adopted',
            '{{ELEVATED_PROFILE_1_IDENTITY_AND_CONFIGURATION_REFERENCE_OR_NOT_APPLICABLE}}',
            '{{ELEVATED_PROFILE_1_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}}',
            'capability isolation where supported or exact effective overlap and residual risk',
            'otherwise record the exact effective-authority overlap and residual risk, including SQLite file-level limits',
        ],
        'skeleton/.ai/data.md' => [
            'one separately named no-fallback identity/configuration profile per adopted migration history plus a separate administrative profile when adopted',
        ],
        'templates/application/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
        'skeleton/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledDatabaseSetupGuidanceDistribution($project, $installedFramework);',
        ],
        'tools/test-consumer-project/application.php' => [
            'function proveInstalledDatabaseSetupGuidanceDistribution(',
            'PASS installed database setup guidance distribution',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/037-database-setup-scope-gate.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $databaseSetupScopeArtifactMarkers, 'database setup scope', $failures);

    $databaseSetupScopeForbiddenArtifactMarkers = [
        '.ai/database.md' => [
            'Runtime, migration, and administrative factories use distinct names and never fall back.',
            'Inject only the runtime type into visible HTTP `Connection::connect` construction;',
        ],
        'templates/application/.ai/README.md' => [
            'authority separation, explicit composition, rotation/restart',
        ],
        'skeleton/.ai/README.md' => [
            'authority separation, explicit composition, rotation/restart',
        ],
        'templates/application/.ai/data.md' => [
            '{{CONNECTION_1_MIGRATION_IDENTITY_REFERENCE}}',
            '{{CONNECTION_1_AUTHORITY_ISOLATION_MECHANISM}}',
        ],
    ];

    forbidGuardrailArtifactMarkers($root, $databaseSetupScopeForbiddenArtifactMarkers, 'database setup scope', $failures);

    $databaseAuthorityLifecycleArtifactMarkers = [
        'docs/decisions/038-application-owned-database-authority-lifecycle.md' => [
            'Status: accepted',
            'Configuration and source presence do not activate database authority.',
            'Withholding all runtime object access is valid before a named application operation exists.',
            'The installed application checker adds one deliberately narrow context-consistency check',
            'No framework runtime type or dependency is added.',
        ],
        'docs/decisions/README.md' => [
            '`038-application-owned-database-authority-lifecycle.md`',
        ],
        'docs/consumer-contract.md' => [
            'treat zero runtime object access as valid before a named application operation exists',
            'record how effective authority resolves under the selected engine, using only applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other engine-specific sources',
            'record the application-owned ordering among migration, authority activation, exact-engine authority verification, application rollout, and traffic enablement',
            'Configuration parsing, successful connectivity, `SELECT 1`, object existence, and migration success do not prove usable runtime authority.',
            'adds one ordinary context-consistency failure without a `PHT` diagnostic',
        ],
        'docs/database.md' => [
            '### Authority activation lifecycle',
            'Configuration and source presence do not activate database authority.',
            'Database and object definition source; database/catalog/schema/attachment namespace selection and qualification as supported; namespace and object control-or-ownership; and active authority are separate facts.',
            'Record only applicable sources, such as direct, role or inherited, public or default, database or global, ownership-chain, IAM, or filesystem and process authority.',
            'Each adopted authority activation or deactivation has one explicit application-owned owner and path.',
            '`GRANT` or `REVOKE` SQL may be visible and checksum-covered inside a migration when the selected engine supports and uses it',
            'PHPThis chooses no universal migration-first, code-first, rolling, or maintenance-window sequence.',
        ],
        'docs/security.md' => [
            'Withholding runtime object access is valid until a named operation exists.',
            'Account for effective authority using only the engine\'s applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other sources.',
            'Every authority activation and deactivation has one recorded application-owned owner and non-HTTP path.',
            '`GRANT` or `REVOKE` SQL is supported, selected, and part of a migration',
            'PHPThis neither requires nor discourages an engine-default or application-specific database, catalog, schema, attachment namespace, or equivalent.',
        ],
        'docs/migrations.md' => [
            '## Authority transition and release handoff',
            'Migration success proves the migration path only.',
            'Before dependent code receives traffic, positive evidence executes its exact runtime statements under the runtime identity',
            'PHPThis does not prescribe migration-first or code-first rollout.',
            'No proof establishes production coordination duration or loss behavior, availability, free-space behavior, crash recovery, backup restore, live effective authority, release ordering',
        ],
        'docs/knowledge-map.md' => [
            '| Connect to, read, write, or assess SQL safety or database authority |',
            'supported database/catalog/schema/attachment namespace selection and qualification, namespace and object control-or-ownership model, per-operation runtime authority, activation and deactivation ownership, exact-engine positive and negative evidence',
        ],
        'docs/guardrails.md' => [
            'application-owned canonical `PHPThis\Database\Connection::connect` calls cannot coexist with a standalone `NOT_APPLICABLE(DATABASE)` declaration',
            "A separate installed distribution proof checks that ADR 038's application-owned authority lifecycle remains present",
            'This marker proof is a source-distribution check only: it performs no live authority probe, validates no engine privilege or control model',
        ],
        '.ai/application-context.md' => [
            "Keep ADR 038's application-owned database authority lifecycle in the consumer contract, both application contexts, and compact task routing.",
            'Configuration, connectivity, object existence, and migration completion do not activate authority.',
            'Do not prescribe a namespace, identity topology, default privilege, universal deployment order, permission helper, runtime introspection, or automatic hook.',
        ],
        'templates/application/.ai/data.md' => [
            'Its first non-heading declaration is the canonical standalone marker:',
            "\n`NOT_APPLICABLE(DATABASE)`\n",
            '{{CONNECTION_1_DATABASE_DEFINITION_OR_PROVISIONING_SOURCE}}',
            '{{CONNECTION_1_NAMESPACE_SELECTION_AND_QUALIFICATION_POLICY}}',
            '{{CONNECTION_1_NAMESPACE_AND_OBJECT_CONTROL_OR_OWNERSHIP_MODEL_OR_NOT_APPLICABLE}}',
            '{{DATABASE_AUTHORITY_1_CONNECTION_AND_OPERATION}}',
            '{{DATABASE_AUTHORITY_1_EFFECTIVE_AUTHORITY_RESOLUTION_SOURCE}}',
            '{{ELEVATED_PROFILE_1_AUTHORITY_TRANSITION_OWNER_AND_IMPLEMENTATION_REFERENCE_OR_NOT_APPLICABLE}}',
            'Activation/deactivation accountable owner and authoritative implementation reference',
            'Activate and verify authority against the exact engine and version before dependent code receives traffic.',
        ],
        'templates/application/.ai/migrations.md' => [
            '{{MIGRATION_ENGINE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONFIGURATION_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_AUTHORITY_TRANSITION_IMPLEMENTATION_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_RELEASE_CONSTRAINTS_OR_NOT_APPLICABLE}}',
            'Migration success alone does not prove runtime authority is active.',
        ],
        'templates/application/.ai/operations.md' => [
            '{{DATABASE_AUTHORITY_AND_RELEASE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
            '{{DATABASE_AUTHORITY_TRANSITION_RUNBOOK_AND_EVIDENCE_MAPPING_OR_NOT_APPLICABLE}}',
            '{{DATABASE_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}',
            '{{DATABASE_COMPATIBILITY_DEACTIVATION_AND_REMOVAL_POLICY_OR_NOT_APPLICABLE}}',
            '{{DATABASE_PRE_TRAFFIC_AUTHORITY_GATE_EVIDENCE_AND_OWNER_OR_NOT_APPLICABLE}}',
            'there is no universal order beyond activating and verifying required authority before dependent traffic',
        ],
        'templates/application/.ai/testing.md' => [
            'executes every intended statement for each named operation under the runtime identity before traffic',
            'selected prohibited namespace, data-definition, identity or role, authority-administration, migration-ledger, database or global, and unrelated-target capabilities',
            'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'Configuration, connectivity, target existence, and migration success are not authority evidence.',
        ],
        'skeleton/.ai/data.md' => [
            "\n`NOT_APPLICABLE(DATABASE)`\n",
            'database/catalog/schema/attachment namespace selection and qualification as supported',
            'namespace and object control or ownership model or explicit N/A',
            'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'one accountable non-HTTP owner and authoritative implementation reference for every adopted authority activation and deactivation',
            'Configuration, connectivity, target existence, and migration completion do not activate runtime authority.',
        ],
        'skeleton/.ai/migrations.md' => [
            'selected authority-transition implementation source and complete non-HTTP implementation path',
            'the history\'s engine-specific compatibility, authority-verification, failure-stop, and handoff constraints',
            'application-wide release sequence recorded only in `.ai/operations.md`',
            'Migration success alone does not prove runtime authority is active.',
        ],
        'skeleton/.ai/operations.md' => [
            'authority-transition owner or activation stage',
            'Record here, keyed by stable history name or explicit intersecting-history set, the deployment runner',
            'application-owned sequence through authority verification, rollout, traffic enablement, later deactivation',
            'No universal deployment order is inferred',
        ],
        'skeleton/.ai/testing.md' => [
            'Execute every intended statement under the runtime identity before traffic',
            'elevated configuration remains unavailable to HTTP',
            'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
            'each adopted authority activation and deactivation has one visible non-HTTP owner and path, record `GRANT` and `REVOKE` only where supported',
            'Configuration, connectivity, target existence, migration success, PHT006, PHT008, tenant predicates, and adversarial bindings are not universal authority',
        ],
        'verification/ApplicationChecker.php' => [
            'private function databaseContextConnectionFailures(',
            'private function hasCanonicalConnectionCall(',
            'private function importAliases(',
            'private function resolvedClassName(',
            'Application data context declares no database while application-owned PHP calls PHPThis\\\\Database\\\\Connection::connect;',
            'T_NAME_FULLY_QUALIFIED',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution($project, $installedFramework);',
            'proveDatabaseContextConnectionConsistency($project, $profileCommand, $environment);',
        ],
        'tools/test-consumer-project/data.php' => [
            'function proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution(',
            'function proveDatabaseContextConnectionConsistency(',
            'DatabaseContextOrdinaryControl',
            'DatabaseContextAliasControl',
            'DatabaseContextGroupedControl',
            'DatabaseContextNamespaceAliasControl',
            'DatabaseContextNamespaceImportControl',
            'DatabaseContextCurrentNamespaceControl',
            'DatabaseContextFullyQualifiedControl',
            'The isolated database-context diagnostic changed.',
            'CRLF database context bypassed the not-applicable Connection::connect check.',
            'The legacy starter no-database declaration bypassed the Connection::connect check.',
            'It therefore has no SQL, structural selectors, bounded data lists',
            'an unmatched leading backtick',
            'an unmatched trailing backtick',
            'legacy text quoted inside adopted prose',
            'A comment or string mentioning Connection::connect was mistaken for executable database use.',
            'private const CONNECTION_TYPE = Connection::class;',
            'installedSyntheticDatabaseContext()',
            'PASS installed database-context connection consistency',
            'PASS installed database authority lifecycle guidance distribution',
        ],
        'tools/test-consumer-project/support.php' => [
            'function installedSyntheticDatabaseContext(): string',
            'Structural namespace/control model: SQLite\'s default `main` attachment namespace exists only inside each in-memory proof connection;',
            'no live authority probe runs.',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/038-application-owned-database-authority-lifecycle.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $databaseAuthorityLifecycleArtifactMarkers, 'database authority lifecycle', $failures);

    $databaseAuthorityLifecycleForbiddenTemplateMarkers = [
        'templates/application/.ai/data.md' => [
            '| Connection name | Engine and supported version | PDO driver | Required Composer extension | Non-secret configuration reference | Schema authority |',
            '{{CONNECTION_1_SCHEMA_SOURCE}}',
            '{{CONNECTION_2_SCHEMA_SOURCE_OR_NOT_APPLICABLE}}',
        ],
    ];

    forbidGuardrailArtifactMarkers(
        $root,
        $databaseAuthorityLifecycleForbiddenTemplateMarkers,
        'database authority lifecycle template',
        $failures,
    );

    $mutableReleaseStateForbiddenMarkers = [
        'Status: unpublished; project state remains pre-alpha',
        'PHPThis is still pre-alpha.',
        'Until tagged packages are published',
        'It remains pre-alpha because neither',
        'Until every mandatory release gate passes, the public project status remains pre-alpha.',
        'The public artifact and skeleton path are still unproved.',
        'no alpha has been published',
        'path is intentionally unavailable until',
        'Alpha 6 remains the latest fully completed and announced coordinated release',
        'v0.1.0-alpha.6 preserves the latest fully completed and announced coordinated framework, skeleton, and public-install release',
    ];

    $requiredReleaseRoutingAuthorityFiles = [
        '.ai/README.md',
        '.ai/application-context.md',
        'docs/guardrails.md',
        'docs/knowledge-map.md',
    ];

    $mutableReleaseStateAuthorityFiles = [
        ...$requiredReleaseRoutingAuthorityFiles,
        '.ai/testing.md',
        'AGENTS.md',
        'CONTRIBUTING.md',
        'README.md',
        'RELEASING.md',
        'ROADMAP.md',
        'SECURITY.md',
        'VISION.md',
        'docs/consumer-contract.md',
        'docs/getting-started.md',
        'docs/strict-profile.md',
        'docs/decisions/README.md',
        'docs/releases/0.1.0-alpha.1.md',
        'docs/releases/0.1.0-alpha.2.md',
        'docs/releases/0.1.0-alpha.3.md',
        'docs/releases/0.1.0-alpha.4.md',
        'docs/releases/0.1.0-alpha.5.md',
        'docs/releases/0.1.0-alpha.6.md',
        'docs/releases/0.1.0-alpha.7.md',
        'docs/decisions/018-bounded-alpha-1-release-scope.md',
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md',
        'docs/decisions/031-bounded-alpha-3-release-scope.md',
        'docs/decisions/035-bounded-alpha-4-release-scope.md',
        'docs/decisions/040-bounded-alpha-5-release-scope.md',
        'docs/decisions/047-bounded-alpha-6-release-scope.md',
        'docs/decisions/054-bounded-alpha-7-release-scope.md',
        'skeleton/README.md',
    ];

    $releaseNotePaths = glob($root . '/docs/releases/*.md');

    if ($releaseNotePaths === false) {
        $failures[] = 'Cannot enumerate release notes for mutable publication-state checks.';
    } else {
        sort($releaseNotePaths, SORT_STRING);

        foreach ($releaseNotePaths as $releaseNotePath) {
            $relativeReleaseNotePath = substr($releaseNotePath, strlen($root) + 1);

            if (!in_array($relativeReleaseNotePath, $mutableReleaseStateAuthorityFiles, true)) {
                $mutableReleaseStateAuthorityFiles[] = $relativeReleaseNotePath;
            }
        }
    }

    $mutableReleaseStateDetectionControls = [
        'ALPHA 5 IS PUBLISHED.' => true,
        "Alpha 5 is\npublicly available." => true,
        'Alpha 5 has been released.' => true,
        '**Alpha 5** is now publicly available.' => true,
        'v0.1.0-alpha.5 is available.' => true,
        '[Alpha 5](https://example.invalid/release) has now been published.' => true,
        'Alpha 6 is published.' => true,
        'Beta 2 packages are now available.' => true,
        'RC 3 has been publicly released.' => true,
        'v0.2.0-alpha.1 is available.' => true,
        'v0.2.0-alpha-1 is available.' => true,
        '0.2.0-beta.2 has now been published.' => true,
        '0.2.0-rc-3 has now been released.' => true,
        'v1.0.0 is released.' => true,
        'Alpha 6 is not published.' => true,
        'v0.2.0-beta.2 has not yet been released.' => true,
        'Alpha 7 remains partial.' => true,
        "ALPHA 7 REMAINS\nUNANNOUNCED." => true,
        '**Alpha 7** is still pending final coordination.' => true,
        '[v0.1.0-alpha.7](https://example.invalid/release) remains an unannounced partial coordinated release.' => true,
        'Alpha 5 was published on an observed historical date.' => false,
        'If Alpha 6 is published, record the observed timestamp.' => false,
        'When v0.2.0-alpha.1 is available, prove the installed artifact.' => false,
        'Unless Alpha 6 is not published, stop.' => false,
        'If Alpha 8 remains partial, record the recovery state.' => false,
        'Alpha 7 remained partial at the source-preparation checkpoint.' => false,
        'Both exact candidate commits remain PENDING.' => false,
        'A partially published framework or skeleton remains unannounced until the complete gate passes.' => false,
        'Alpha 7 is experimental prerelease software.' => false,
        'Alpha 5 publication state is external.' => false,
        'Alpha 5 is the latest immutable release identity and tag known to this checkout.' => false,
        'Alpha 5 is the latest immutable release identity and tag known to the repository source record.' => false,
        'Unreleased main establishes no external publication state.' => false,
        'Verify GitHub and Packagist state separately.' => false,
        'Publication state is external.' => false,
    ];

    foreach ($mutableReleaseStateDetectionControls as $contents => $expectedClaim) {
        $hasClaim = mutableReleaseStateClaim($contents, $mutableReleaseStateForbiddenMarkers) !== null;

        if ($hasClaim !== $expectedClaim) {
            $failures[] = 'The normalized mutable release-state detector changed behavior.';
        }
    }

    $externalReleaseStateMarkers = [
        'README.md' => 'Package availability and current release state are external facts',
        'RELEASING.md' => 'Continuing publication and package availability remain external',
        'ROADMAP.md' => 'Live GitHub and Packagist state remains external',
        'SECURITY.md' => 'This tracked policy does not prove continuing live publication state',
        'docs/getting-started.md' => 'Package availability remains an external fact',
        'docs/releases/0.1.0-alpha.1.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.2.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.3.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.4.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.5.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.6.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.7.md' => 'Publication state is external',
        'docs/decisions/018-bounded-alpha-1-release-scope.md' => 'This decision does not record mutable publication state',
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => 'not mutable tag, package, GitHub release, or installation availability',
        'docs/decisions/031-bounded-alpha-3-release-scope.md' => 'Publication state is external',
        'docs/decisions/035-bounded-alpha-4-release-scope.md' => 'Publication state is external',
        'docs/decisions/040-bounded-alpha-5-release-scope.md' => 'Publication state is external',
        'docs/decisions/047-bounded-alpha-6-release-scope.md' => 'Publication state is external',
        'docs/decisions/054-bounded-alpha-7-release-scope.md' => 'Publication state is external',
        'skeleton/README.md' => 'Package availability is an external fact',
    ];

    foreach ($externalReleaseStateMarkers as $relativePath => $marker) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents) || !str_contains($contents, $marker)) {
            $failures[] = "The external release-state disclaimer is missing from {$relativePath}.";
        }
    }

    $consumerInstallationOrder = [
        'README.md' => [
            '## Start a PHPThis application',
            'Consumers install PHPThis through Composer.',
            'Do not clone or copy the PHPThis framework repository to start an application.',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.7'",
            '`phpthis/skeleton` becomes the application root and Composer installs `phpthis/framework`',
            '## Develop or evaluate PHPThis itself',
            'It is not the consumer application installation path.',
            'git clone https://github.com/balgf/PHPThis.git',
        ],
        'skeleton/README.md' => [
            '## Create a new application',
            'Consumers do not clone or copy the PHPThis framework repository.',
            'composer create-project --stability=alpha phpthis/skeleton my-app',
            'installs `phpthis/framework` under `vendor/phpthis/framework`',
            '## Install and check an existing application checkout',
            '## Framework-maintainer source evaluation',
            'This section is not a consumer installation path.',
            'phpthis/framework: dev-main',
        ],
        'docs/getting-started.md' => [
            '## Start from a proved published skeleton',
            'Do not use an unpinned prerelease constraint during partial publication',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.7'",
            'Consumers do not clone or copy the PHPThis framework repository.',
            'Before selecting a later prerelease, verify its exact skeleton version and clean public-install evidence in the release work item, GitHub, and Packagist.',
            '## Framework source evaluation only',
            'It is not the normal consumer installation path.',
            'git clone https://github.com/balgf/PHPThis.git phpthis-source',
        ],
        'RELEASING.md' => [
            'Export the contents of `skeleton/` as the root of its dedicated repository',
            'Remove the framework-maintainer source-evaluation section from the exported skeleton README',
            'Remove the pre-alpha VCS `repositories` override from the exported `composer.json`',
        ],
        'composer.json' => [
            'start applications with phpthis/skeleton',
        ],
        'skeleton/composer.json' => [
            'starting a checked PHPThis application with phpthis/framework',
        ],
    ];

    foreach ($consumerInstallationOrder as $relativePath => $orderedMarkers) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            $failures[] = "Cannot read consumer installation artifact {$relativePath}.";
            continue;
        }

        $previousPosition = -1;

        foreach ($orderedMarkers as $marker) {
            $position = strpos($contents, $marker);

            if ($position === false || $position <= $previousPosition) {
                $failures[] = "The Composer-first consumer installation contract is missing or out of order in {$relativePath}.";
                break;
            }

            $previousPosition = $position;
        }
    }

    foreach ($mutableReleaseStateAuthorityFiles as $relativePath) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            $failures[] = "Cannot read release-state authority {$relativePath}.";
            continue;
        }

        $releaseStateClaim = mutableReleaseStateClaim($contents, $mutableReleaseStateForbiddenMarkers);

        if ($releaseStateClaim !== null) {
            $failures[] = "Mutable release-state claim remains in {$relativePath}: {$releaseStateClaim}";
        }
    }

    $outerHttpFailureContextMarkers = [
        'docs/knowledge-map.md' => [
            '| Configure or review outer HTTP failure disclosure | `docs/errors.md#outer-http-failures` |',
            '`docs/configuration.md#http-failure-disclosure-selection`',
            '`docs/request-handling.md#outer-http-failure-boundary`',
            'application `.ai/operations.md` owns effective web-SAPI and isolation evidence',
            'Native PHP display remains off in every profile.',
        ],
        'docs/consumer-contract-upgrades.md' => [
            '### Contract version 18',
            'Through [ADR 061](decisions/061-fail-closed-outer-http-failure-disclosure-profiles.md)',
            'Either retain deliberate code-owned `GENERIC` with no selection inputs, or define both exact application-owned disclosure and runtime-profile inputs',
            'No request value or inferred fact may select details.',
            'ADR 061 introduces Contract version 18',
        ],
        'docs/decisions/README.md' => [
            "Proposed records:\n\n- None.",
            '`061-fail-closed-outer-http-failure-disclosure-profiles.md`',
            'Accepted [ADR 061](061-fail-closed-outer-http-failure-disclosure-profiles.md) coordinates Consumer Contract version 18',
        ],
        'skeleton/.ai/README.md' => [
            'consumer-contract-upgrades.md#contract-version-18',
            '| Change outer HTTP failure disclosure or web-SAPI error display | installed `vendor/phpthis/framework/docs/errors.md#outer-http-failures`, then `.ai/configuration.md` |',
            'code-owned `GENERIC`, effective SAPI settings, and real-SAPI evidence',
        ],
        'templates/application/.ai/README.md' => [
            'consumer-contract-upgrades.md#contract-version-18',
            '| Change outer HTTP failure disclosure or web-SAPI error display | installed `vendor/phpthis/framework/docs/errors.md#outer-http-failures`, then `.ai/configuration.md` |',
            'exact generic/detail selection, effective SAPI settings, bounded disclosure, and real-SAPI evidence',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/061-fail-closed-outer-http-failure-disclosure-profiles.md',
            'docs/errors.md',
            'docs/configuration.md',
            'docs/request-handling.md',
            'docs/security.md',
            'templates/application/.ai/architecture.md',
            'templates/application/.ai/configuration.md',
            'templates/application/.ai/operations.md',
            'templates/application/.ai/testing.md',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $outerHttpFailureContextMarkers,
        'outer HTTP failure context routing',
        $failures,
    );

    return $failures;
}
