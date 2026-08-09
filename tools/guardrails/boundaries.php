<?php

declare(strict_types=1);

/** @return list<string> */
function boundaryGuardrailFailures(string $root): array
{
    $failures = [];

    $sessionContractMarkers = [
        '.ai/README.md' => '`.ai/session.md`',
        'docs/knowledge-map.md' => '`docs/sessions.md`',
        'templates/application/.ai/architecture.md' => '{{SESSION_ADOPTION_AND_KEY_SCHEMA_OR_NOT_APPLICABLE}}',
        'templates/application/.ai/operations.md' => '{{SESSION_NATIVE_FILE_STORAGE_POLICY_OR_NOT_APPLICABLE}}',
        'templates/application/.ai/testing.md' => 'Adopted session transport',
        'skeleton/.ai/README.md' => 'vendor/phpthis/framework/docs/sessions.md',
        'skeleton/.ai/operations.md' => 'ext-session',
        'skeleton/.ai/testing.md' => 'NOT_APPLICABLE(SESSION_EVIDENCE)',
    ];

    foreach ($sessionContractMarkers as $relativePath => $marker) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents) || !str_contains($contents, $marker)) {
            $failures[] = "Session contract route or application-context field is missing from {$relativePath}.";
        }
    }

    $cacheContractMarkers = [
        '.ai/README.md' => '`.ai/cache.md`',
        '.ai/http.md' => '`.ai/cache.md`',
        'docs/knowledge-map.md' => '`docs/caching.md`',
        'templates/application/.ai/architecture.md' => '{{CACHE_ADOPTION_OR_NOT_APPLICABLE}}',
        'templates/application/.ai/operations.md' => '{{CACHE_RUNTIME_ADOPTION_OR_NOT_APPLICABLE}}',
        'templates/application/.ai/testing.md' => 'Adopted cache behavior',
        'skeleton/.ai/README.md' => 'vendor/phpthis/framework/docs/caching.md',
        'skeleton/.ai/architecture.md' => 'NOT_APPLICABLE(CACHE)',
        'skeleton/.ai/testing.md' => 'NOT_APPLICABLE(CACHE_EVIDENCE)',
    ];

    foreach ($cacheContractMarkers as $relativePath => $marker) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents) || !str_contains($contents, $marker)) {
            $failures[] = "Cache contract route or application-context field is missing from {$relativePath}.";
        }
    }

    $cachePolicyArtifactMarkers = [
        '.ai/cache.md' => [
            'The framework currently provides no generic cache API',
            '## HTTP response caching',
            '## Server-side data caching',
        ],
        'docs/caching.md' => [
            'PHPThis has an accepted cache policy but no framework cache mechanism.',
            '`NOT_APPLICABLE(CACHE)`',
            'A warm cache is not evidence that a database path avoids N+1 queries.',
            'stale-refill race',
        ],
        'docs/decisions/016-cache-policy-before-cache-mechanism.md' => [
            'Status: accepted',
            'Framework-owned 404, 405, and unknown-failure 500 responses',
            'no cache client or backend dependency, generic cache API',
            'an explicit stale-refill policy',
        ],
        'templates/application/.ai/architecture.md' => [
            '{{HTTP_CACHE_POLICY_DECISION}}',
            '{{HTTP_CACHE_RESPONSE_POLICY}}',
            '{{CACHEABLE_RESPONSE_FRESHNESS_AND_REVALIDATION_POLICY}}',
        ],
        'templates/application/.ai/operations.md' => [
            '{{HTTP_CACHE_RUNTIME_POLICY}}',
        ],
        'templates/application/.ai/data.md' => [
            '{{CACHE_INVALIDATION_AND_STALE_REFILL_POLICY_OR_NOT_APPLICABLE}}',
        ],
        'templates/application/.ai/testing.md' => [
            'HTTP cache policy evidence',
            'a concurrent miss racing an authoritative write',
        ],
        'skeleton/.ai/README.md' => [
            '| Change HTTP or server-side caching | installed `vendor/phpthis/framework/docs/caching.md` | response path or data, integration, operations, and testing facts |',
        ],
        'skeleton/.ai/testing.md' => [
            'HTTP_CACHE_EVIDENCE(NO_STORE)',
            'a concurrent miss racing an authoritative write',
        ],
        'tools/package-files.txt' => [
            'docs/caching.md',
            'docs/decisions/016-cache-policy-before-cache-mechanism.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $cachePolicyArtifactMarkers, 'cache policy', $failures);

    $routingArtifactMarkers = [
        '.ai/routing.md' => [
            '{name:positive-int}',
            '{name:token}',
            '{name:uuid}',
            '{name:ulid}',
            'at most two',
            'Always use the narrowest type.',
            'RouteMatch',
            'PathParameters',
            'uuid(name): string',
            'ulid(name): string',
            'Route::segments()',
            'must not scan the route list or an index collection',
        ],
        'docs/decisions/017-bounded-trailing-positive-integer-routes.md' => [
            'Status: accepted',
            '[1-9][0-9]*',
            'PHP_INT_MAX',
            'one parameter name',
            'does not claim Update or Delete support',
        ],
        'docs/decisions/019-bounded-multiple-typed-routes.md' => [
            'Status: accepted',
            '[A-Za-z0-9][A-Za-z0-9_-]{0,63}',
            'at most two',
            'Contract version 4',
            '2,300',
            'supersedes ADR 017 only',
            'Superseded in part by [ADR 032]',
        ],
        'docs/decisions/032-explicit-uuid-and-ulid-route-types.md' => [
            'Status: accepted',
            '[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
            '[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}',
            'Consumer Contract version 8',
            'Strict Profile version 2',
            '2,600 physical lines',
            '2,592 core lines',
            'No identifier library, generator, global factory, route builder',
        ],
        'docs/decisions/README.md' => [
            'Accepted records:',
            '`032-explicit-uuid-and-ulid-route-types.md`',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 11',
            'This is the canonical contract for an application built with the installed PHPThis version.',
            'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
            '`positive-int`, `token`, `uuid`, or `ulid`',
            'Always use the narrowest route type.',
            'uuid(name): string',
            'ulid(name): string',
            'never normalized',
        ],
        'src/Routing/RouteParameterType.php' => [
            "case Uuid = 'uuid';",
            "case Ulid = 'ulid';",
            'self::Uuid => self::isUuid($segment)',
            'self::Ulid => self::isUlid($segment)',
            '[1-8][0-9a-f]{3}-[89ab]',
            '[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}',
        ],
        'src/Routing/PathParameters.php' => [
            'public function uuid(string $name): string',
            'public function ulid(string $name): string',
            'Path parameters cannot contain more than two values.',
        ],
        'tests/routing.php' => [
            'router matches canonical lowercase UUID path parameters',
            'router matches canonical lowercase ULID path parameters',
            'invalid UUID and ULID routes stop before handler and database work',
            'literal routes win over canonical UUID and ULID values',
            'Expected all fixed types to remain indexed across 20,000 routes.',
        ],
        'tools/benchmark-routing.php' => [
            "'fixed_parameter_types' => ['positive-int', 'token', 'uuid', 'ulid']",
            "'timed_dynamic_parameter_type' => 'ulid'",
            "'timed_uuid_parameter_type' => 'uuid'",
            "'uuid_hit_nanoseconds' => \$uuidHitNanoseconds",
            "->uuid('document_id')",
            "->ulid('document_id')",
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledUuidAndUlidRouting($project, $environment);',
            'PASS installed UUID and ULID routing',
            '/accounts/{account_id:uuid}',
            '/events/{event_id:ulid}',
        ],
        'example/src/Documents/DocumentRoutes.php' => [
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
        ],
        'example/src/Documents/GetDocument/GetDocumentHandler.php' => [
            "positiveInteger('account_id')",
            "token('document_key')",
            'AccountId::fromPositiveInteger',
            'DocumentKey::fromToken',
            "'Cache-Control' => 'private, no-store'",
        ],
        'example/src/Users/UserRoutes.php' => [
            '/users/{user_id:positive-int}',
        ],
        'example/src/Users/GetUser/GetUserHandler.php' => [
            "positiveInteger('user_id')",
            'UserId::fromPositiveInteger',
            'WHERE users.id = :user_id',
            "'Cache-Control' => 'no-store'",
        ],
        'tools/package-files.txt' => [
            'docs/decisions/017-bounded-trailing-positive-integer-routes.md',
            'docs/decisions/019-bounded-multiple-typed-routes.md',
            'docs/decisions/032-explicit-uuid-and-ulid-route-types.md',
            'src/Routing/PathParameters.php',
            'src/Routing/RouteMatch.php',
            'src/Routing/RouteParameterType.php',
            'src/Routing/RouteSegment.php',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $routingArtifactMarkers, 'typed routing', $failures);

    $requestHandlerDecoratorArtifactMarkers = [
        'docs/decisions/033-application-owned-request-handler-decorators.md' => [
            'Status: accepted',
            'Consumer Contract version 9 accepts one optional application pattern named an **application-owned request-handler decorator**.',
            'receives exactly one downstream `RequestHandler` through its ordinary constructor',
            'Composition occurs only in the handler argument of an explicit `Route`.',
            'passes the exact same `Request` instance to downstream',
            'does not catch, wrap, translate, suppress, retry, or otherwise replace an exception',
            'constructs one explicit immutable replacement and preserves every unchanged status, header, body, `ResponseCookie`, and `LocalFileBody` field',
            'PHPThis adds no core class, runtime dependency, diagnostic, middleware interface, or composition facility',
            'Strict Profile version 2 remains unchanged.',
        ],
        'docs/decisions/README.md' => [
            '`033-application-owned-request-handler-decorators.md`',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 11',
            '## Optional application-owned request-handler decorators',
            'The decorator is composed only as the handler of an explicit `Route`.',
            'zero downstream calls or call its one downstream handler exactly once',
            'passes the exact same immutable `Request` instance downstream',
            'Do not add a generic or framework middleware interface, pipeline, stack, runner, registry, priority list, discovery rule, `$next` callable, request-context bag, request attributes, or framework-owned decorator.',
            'Version 9 adds no core class, framework middleware, runtime dependency, static diagnostic, request attribute, or automatic composition.',
        ],
        'docs/request-handling.md' => [
            '## Application-owned request-handler decorators',
            'receives exactly one downstream `RequestHandler`',
            'The complete outer-to-inner sequence stays visible beside the affected `Route`.',
            'Do not replace the direct nesting with a middleware array, helper, factory, registry, priority, discovery rule, `$next` callable, or container.',
            'It adds no core type or dependency.',
        ],
        'docs/vocabulary.md' => [
            '| application-owned request-handler decorator |',
            'middleware, interceptor, filter, pipeline element, `$next` callable',
        ],
        '.ai/routing.md' => [
            'When one route needs bounded wrapping behavior, its constructed handler may be one application-owned request-handler decorator.',
            'Construct it visibly beside the route',
            'Each decorator invokes its downstream zero or one time with the identical immutable `Request`',
            'Do not add a generic or framework middleware interface, pipeline, iterable registry, priorities, discovery, `$next` abstraction, context bag, hidden binding, or hidden I/O.',
        ],
        '.ai/request-policy.md' => [
            'Do not replace or obscure the action-specific adapter with an application-owned request-handler decorator',
        ],
        'templates/application/.ai/architecture.md' => [
            '{{REQUEST_HANDLER_DECORATOR_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{REQUEST_HANDLER_DECORATOR_ROUTES_AND_ORDER_OR_NOT_APPLICABLE}}',
            '{{REQUEST_HANDLER_DECORATOR_SIDE_EFFECT_AND_FAILURE_POLICY_OR_NOT_APPLICABLE}}',
            'Construct the complete nesting as an unrolled expression beside every affected route.',
        ],
        'skeleton/.ai/architecture.md' => [
            '`NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`',
            '`src/HealthRoutes.php` constructs dependency-free `HealthHandler` inline in the exact route declaration',
            '`src/Routes.php` only includes that existing named route-area list',
            'Never wrap `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter`',
        ],
        'tests/handler-decorator.php' => [
            'final readonly class HandlerDecoratorProofOrderMarkerHandler implements RequestHandler',
            'private RequestHandler $downstream',
            'explicit nested handler decorators preserve request and response identity',
            'maintenance gate short-circuits or delegates exactly once',
            'handler decorator propagates the exact downstream exception',
            'handler decorator propagates its exact own exception before delegation',
            'response decorator preserves immutable buffered and local-file response fields',
            'handler decoration is route-local and removable by direct rewiring',
        ],
        'tests/run.php' => [
            "require __DIR__ . '/handler-decorator.php';",
            'handlerDecoratorTests()',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledRequestHandlerDecorator($project, $environment);',
            'new InstalledHeaderDecorator(',
            'new InstalledRejectingDecorator(',
            'function assertInstalledDecoratorIsolation(InstalledDecoratorTrace $trace): void',
            'assertInstalledDecoratorIsolation($trace);',
            'PASS installed request-handler decorator composition',
            'The clean skeleton and request-handler decorator proof failed the installed profile check.',
            'is_file($requestHandlerDecoratorProofPath)',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/033-application-owned-request-handler-decorators.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $requestHandlerDecoratorArtifactMarkers, 'request-handler decorator', $failures);

    $websocketArtifactMarkers = [
        'docs/decisions/034-application-owned-websocket-integration.md' => [
            'Status: accepted',
            'WebSocket integration remains application-owned.',
            'PHPThis adds no core WebSocket server, client, frame, connection, event-loop, daemon, supervisor, channel, broadcaster, pub/sub, retry, replay, acknowledgement, or delivery API and no runtime dependency.',
            'WebSocket handshakes and frames never become PHPThis HTTP `Request` or `Response` values.',
            'Consumer Contract version 9 and Strict Profile version 2 remain unchanged.',
            '365 application-owned assertions',
            'one reproducible application recipe',
            'the accountable human accepted the completed consumer evidence and its exact local proof limits',
        ],
        'docs/decisions/README.md' => [
            'Accepted records:',
            '`034-application-owned-websocket-integration.md`',
        ],
        'docs/websockets.md' => [
            '# Application-owned WebSocket integration',
            'PHPThis has no native WebSocket runtime or API.',
            'This guide is an accepted evidence profile',
            'A frame is not a PHPThis HTTP `Request`, and an outbound message is not a PHPThis HTTP `Response`.',
            'the exact raw handshake request target, accepted URI form, path-normalization and query behavior',
            'Default to best-effort delivery with no replay across reconnects.',
            '365 application-owned assertions',
            'They are not PHPThis defaults, production recommendations, capacity findings, or evidence for another package version',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 11',
            '## Application-owned WebSocket profile',
            'PHPThis has no WebSocket runtime or core WebSocket API.',
            'Frames never become PHPThis HTTP `Request` or `Response` values',
            'Do not add a framework WebSocket server, client, event loop, connection manager, daemon, supervisor, generic channel, broadcaster, pub/sub, event bus, middleware, context bag, service locator, discovery mechanism, hidden retry, replay, deduplication, acknowledgement, reconnect, or exactly-once behavior.',
            'ADR 034 documents one independent application-owned WebSocket proof without accepting a framework WebSocket runtime, changing application validity, or making its recipe limits universal.',
        ],
        'docs/knowledge-map.md' => [
            '| Propose, add, explain, or review a WebSocket path |',
            'verify that frames never become PHPThis HTTP `Request` or `Response` values and no framework WebSocket runtime exists',
        ],
        'docs/architecture.md' => [
            'ADR 034 keeps WebSockets outside that HTTP graph.',
            'There is no WebSocket namespace or runtime in core.',
            'one measured local recipe, not architecture defaults',
        ],
        'docs/security.md' => [
            '## WebSocket limits',
            'A successful protocol upgrade is not permanent authentication or authorization.',
            'Authenticate explicitly after upgrade even when the handshake also rejects invalid credentials',
            'do not add an unbounded gateway or application queue',
            'one local recipe, not security defaults',
        ],
        'docs/vocabulary.md' => [
            '| application-owned WebSocket integration |',
            '| WebSocket composition root |',
            '| WebSocket command |',
            '| best-effort WebSocket delivery |',
        ],
        'docs/evaluation.md' => [
            'ADR 034 adds an independent consumer proof for one application-owned WebSocket path without adding a framework implementation.',
            '365 application-owned assertions',
            'This establishes that the explicit boundary is viable for that pinned local recipe',
        ],
        'docs/guardrails.md' => [
            'accepted ADR 034, the WebSocket review profile, project-owned AI routes, and package inventory preserve the optional application-owned WebSocket boundary',
            'keeps `.ai/websockets.md` optional under current Contract version 11 as well as its originating Contract version 9',
        ],
        'VISION.md' => [
            'An application that needs WebSockets can keep its pinned mature runtime',
            'without adding a framework real-time runtime or adapting frames into HTTP values',
        ],
        'ROADMAP.md' => [
            'Complete: ADR 034',
            'Accountable-human review accepted the exact local recipe as evidence; no framework runtime, dependency, API, contract version, Strict Profile rule, or core-line increase is added.',
        ],
        '.ai/README.md' => [
            '| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, typed operation, and real socket tests |',
        ],
        '.ai/application-context.md' => [
            'Include `.ai/websockets.md` in the current skeleton and template with `NOT_APPLICABLE(WEBSOCKETS)`',
            'this additional file is not a checker requirement',
        ],
        '.ai/websockets.md' => [
            '# Application-owned WebSocket integration contract',
            'WebSockets are an optional consuming-application capability, not a PHPThis runtime feature.',
            'A frame becomes one operation-specific final readonly command, not an HTTP request.',
            'Do not add framework-owned WebSocket, event-loop, connection-manager, daemon, or supervisor primitives.',
        ],
        '.ai/testing.md' => [
            'An application that adopts WebSockets must test its parser, current authentication and authorization',
            'Real child-process and socket evidence must cover readiness without a blind sleep',
        ],
        'templates/application/.ai/README.md' => [
            '| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, configuration, operation, and socket tests |',
        ],
        'templates/application/.ai/websockets.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            'Keep every WebSocket type and the selected runtime application-owned and manually composed.',
            'Frames never become PHPThis HTTP `Request` or `Response` values',
            'real child-process and socket evidence',
        ],
        'templates/application/.ai/architecture.md' => [
            '## Optional application-owned WebSockets',
            'Keep frames outside PHPThis `Request`, `Response`, `Router`, `RequestBoundary`, `ResponseEmitter`, and terminal request-summary types.',
        ],
        'templates/application/.ai/integrations.md' => [
            '## Optional WebSocket runtime dependency',
            '`NOT_APPLICABLE(WEBSOCKETS)`',
        ],
        'templates/application/.ai/operations.md' => [
            '## WebSocket runtime',
            'forced-stop owner, deployment topology, capacity, scaling, incident policy',
        ],
        'templates/application/.ai/testing.md' => [
            'WebSocket integration and lifecycle tests: `NOT_APPLICABLE(WEBSOCKETS)`',
            'Real child-process and socket tests prove readiness without a blind sleep',
        ],
        'skeleton/.ai/README.md' => [
            '| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, configuration, operation, and socket tests |',
        ],
        'skeleton/.ai/websockets.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            'The existing `GET /health` path remains an independent HTTP path.',
            'Do not add PHPThis WebSocket primitives, HTTP adaptation',
        ],
        'skeleton/.ai/architecture.md' => [
            '## Optional application-owned WebSockets',
            'The existing `GET /health` execution path is HTTP only.',
        ],
        'skeleton/.ai/integrations.md' => [
            '`NOT_APPLICABLE(WEBSOCKETS)`',
            'Keep retries, replay, acknowledgement, delivery, and backend-failure behavior explicit',
        ],
        'skeleton/.ai/operations.md' => [
            '## WebSocket runtime',
            'forced-stop owner, deployment topology, capacity, scaling, incident policy',
        ],
        'skeleton/.ai/testing.md' => [
            'WebSocket integration and lifecycle tests: `NOT_APPLICABLE(WEBSOCKETS)`',
            'Real child-process and socket tests prove readiness without a blind sleep',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/034-application-owned-websocket-integration.md',
            'docs/websockets.md',
            'templates/application/.ai/websockets.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $websocketArtifactMarkers, 'WebSocket boundary', $failures);

    $forbiddenWebSocketRuntimePathPattern = '/(?:websockets?|realtime|event[-_]?loop|daemon|supervisor|broadcast(?:ing)?|pub[-_]?sub|channels?)/i';
    $websocketFrameworkSourceFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($websocketFrameworkSourceFiles as $websocketFrameworkSourceFile) {
        if (!$websocketFrameworkSourceFile instanceof SplFileInfo || !$websocketFrameworkSourceFile->isFile()) {
            continue;
        }

        $relativePath = substr($websocketFrameworkSourceFile->getPathname(), strlen($root) + 1);

        if (preg_match($forbiddenWebSocketRuntimePathPattern, $relativePath) === 1) {
            $failures[] = "WebSocket runtime mechanism must remain outside framework source: {$relativePath}.";
        }
    }

    $websocketPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($websocketPackageInventory)) {
        $websocketPackagePaths = preg_split('/\R/', $websocketPackageInventory);

        if (is_array($websocketPackagePaths)) {
            foreach ($websocketPackagePaths as $websocketPackagePath) {
                if (
                    str_starts_with($websocketPackagePath, 'src/')
                    && preg_match($forbiddenWebSocketRuntimePathPattern, $websocketPackagePath) === 1
                ) {
                    $failures[] = "WebSocket runtime mechanism must remain outside the framework package API: {$websocketPackagePath}.";
                }
            }
        }
    }

    $websocketProofOnlyDependencies = [
        'amphp/amp',
        'amphp/byte-stream',
        'amphp/http',
        'amphp/http-server',
        'amphp/socket',
        'amphp/websocket',
        'amphp/websocket-client',
        'amphp/websocket-server',
        'ext-pcntl',
        'revolt/event-loop',
    ];

    foreach (['composer.json', 'skeleton/composer.json'] as $websocketComposerPath) {
        $contents = file_get_contents($root . '/' . $websocketComposerPath);
        $manifest = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($manifest)) {
            $failures[] = "Cannot decode {$websocketComposerPath} for the WebSocket dependency boundary.";
            continue;
        }

        foreach (['require', 'require-dev'] as $dependencySection) {
            $dependencies = $manifest[$dependencySection] ?? [];

            if (!is_array($dependencies)) {
                continue;
            }

            foreach (array_keys($dependencies) as $dependency) {
                if (
                    is_string($dependency)
                    && in_array(strtolower($dependency), $websocketProofOnlyDependencies, true)
                ) {
                    $failures[] = "Application-owned WebSocket proof dependency {$dependency} must not enter {$websocketComposerPath}:{$dependencySection}.";
                }
            }
        }
    }

    $websocketApplicationChecker = file_get_contents($root . '/verification/ApplicationChecker.php');

    if (
        is_string($websocketApplicationChecker)
        && preg_match('/[\'\"]\\.ai\\/websockets\\.md[\'\"]\s*,/', $websocketApplicationChecker) === 1
    ) {
        $failures[] = 'Contract version 9 must not checker-require the optional application WebSocket context file.';
    }

    $websocketConsumerProjectProof = file_get_contents($root . '/tools/test-consumer-project.php');

    if (
        is_string($websocketConsumerProjectProof)
        && str_contains($websocketConsumerProjectProof, 'proveWebSocketContextIsRequired')
    ) {
        $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/websockets.md is absent.';
    }

    $requestPolicyArtifactMarkers = [
        '.ai/README.md' => [
            '`.ai/request-policy.md`',
        ],
        '.ai/request-policy.md' => [
            'authenticate -> resolve tenant -> authorize -> protected handler',
            'PHPThis provides no credential parser or verifier.',
            'Cache-Control: private, no-store',
        ],
        'docs/knowledge-map.md' => [
            '`docs/request-policy.md`',
        ],
        'docs/request-policy.md' => [
            'PHPThis keeps authentication, tenant resolution, and authorization application-owned.',
            'Missing, malformed, and rejected credentials map to one generic `401`',
            'Ordinary forbidden and cross-tenant decisions map to the same generic `403`.',
            'When a policy reads storage, give it a separately named connection, budget, and trace from protected handler work.',
        ],
        'docs/decisions/020-application-owned-request-policy.md' => [
            'Status: accepted',
            'adds no core runtime contract',
            'Consumer Contract version 4 and Strict Profile version 2 remain unchanged.',
            'No core PHP file, runtime dependency, Consumer Contract version, Strict Profile version, or PHPThis diagnostic changes.',
        ],
        'example/src/Documents/GetDocument/GetDocumentHandler.php' => [
            '$this->authenticate->authenticate($request)',
            '$this->resolveTenant->resolve($principal, $accountId)',
            '$this->authorize->authorize($principal, $tenant, $documentKey)',
            '$this->retrieve->retrieve(',
        ],
        'example/src/Documents/GetDocument/SelectAuthorizedDocument.php' => [
            'documents.account_id = :account_id',
            'documents.account_id = :resolved_tenant_account_id',
            'account_memberships.principal_id = :principal_id',
            'account_memberships.account_id = :membership_tenant_account_id',
        ],
        'example/bootstrap.php' => [
            'ApplicationDatabasePath::fromString(',
            'new ApplicationComposition($databasePath)',
            '->http()',
        ],
        'example/src/ApplicationComposition.php' => [
            'new DenyAllAccountAuthentication()',
            'Unauthenticated::class => new Response(',
            'Forbidden::class => $forbiddenResponse',
            'CrossTenant::class => $forbiddenResponse',
        ],
        'tests/request-policy.php' => [
            'consumer replaces every document policy and passes explicit authority values',
            'permitted document policy keeps protected missing responses private and generic',
            'protected document query fails closed when requested and resolved tenants differ',
            'mapped document denials emit no sensitive log data',
            'unexpected document policy failures use the generic redacted boundary',
        ],
        'templates/application/.ai/request-policy.md' => [
            '{{REQUEST_POLICY_ADAPTER_PATH}}',
            '{{CREDENTIAL_PARSER_EVIDENCE_OR_LIMIT}}',
        ],
        'skeleton/.ai/request-policy.md' => [
            'NOT_APPLICABLE(REQUEST_POLICY)',
            'vendor/phpthis/framework/docs/request-policy.md',
        ],
        'tools/package-files.txt' => [
            'docs/request-policy.md',
            'docs/decisions/020-application-owned-request-policy.md',
            'templates/application/.ai/request-policy.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $requestPolicyArtifactMarkers, 'request-policy', $failures);

    $typedInputBoundaryArtifactMarkers = [
        '.ai/README.md' => [
            '| Parse or change external values | `.ai/types.md` | operation-specific boundary value, failure map, and adversarial tests |',
        ],
        '.ai/application-context.md' => [
            'every adopted inbound operation',
            '`NOT_APPLICABLE(INPUT)`',
            'default generic `400` structural versus exact application-owned generic `422` unacceptable-value split',
            'Query, header, route, and transport representations do not inherit that body-content default.',
        ],
        '.ai/types.md' => [
            'No normalization is implicit.',
            'Native `json_decode` does not expose duplicate object keys and retains the last value',
            'ADR 033 and Consumer Contract v9',
            'For application-owned structured request-body content',
            'do not inherit this request-body default',
        ],
        '.ai/errors.md' => [
            'For application-owned structured request-body content',
            'defaults through its exact application-owned failure to `422`',
            'Query, header, route, and transport representations retain their separately recorded contracts.',
        ],
        '.ai/testing.md' => [
            'For application-owned structured request-body content',
            'property-order variants that both remain `400`',
            'Query, header, route, and transport representations retain their separately recorded contracts.',
        ],
        'docs/type-safety.md' => [
            'external mixed data -> named parser factory -> final readonly value -> native typed code',
            'Invalid input makes zero seam calls when one exists and cannot trigger operation-owned downstream I/O or mutation.',
            'A duplicate-key-aware parser requires a separate decision',
            'canonical authoring default for application-owned structured request-body content',
            'do not inherit this body-content default',
        ],
        'docs/errors.md' => [
            "blanket-`400` default for application-owned structured request-body content",
            'application-owned `UnacceptableCreateUserValues`',
            'Query-string, header, route, and transport representations retain their separately recorded contracts.',
        ],
        'docs/consumer-contract.md' => [
            'For application-owned structured request-body content',
            'Query-string, header, route, and transport representations retain their separately recorded contracts.',
            'ADR 042 changes the application-owned structured request-body authoring default',
        ],
        'docs/getting-started.md' => [
            "each inbound operation's raw representation",
            '`NOT_APPLICABLE(INPUT)`',
            'default generic `400` structural versus exact application-owned generic `422` unacceptable-value split',
            'query, header, route, and transport representations retain separately recorded contracts',
        ],
        'docs/guardrails.md' => [
            'The typed-input guard retains ADR 021',
            "ADR 042's application-owned request-body input-failure classification",
            'mixed-failure property-order evidence',
            'query/header/route/transport non-inheritance',
        ],
        'VISION.md' => [
            'at most one operation-specific typed seam',
        ],
        'docs/decisions/021-application-owned-typed-input-boundaries.md' => [
            'Status: accepted',
            'Each accepting operation owns one named parser factory',
            'This decision adds application-owned example evidence and authoring guidance only.',
            'Consumer Contract version 4 and Strict Profile version 2 remain unchanged.',
        ],
        'docs/decisions/042-application-owned-input-failure-classification.md' => [
            'Status: accepted',
            'For application-owned structured request-body content',
            'The operation-specific parser completes its whole shape and native-type pass before beginning value validation.',
            '`400 invalid_request` means the representation is malformed or its complete payload structure is invalid.',
            '`422 unprocessable_content` means the complete field set, nullability, native types, and nested shapes are correct',
            'Query-string, header, route, PHP runtime transport, and multipart transport failures retain their separately recorded contracts',
            'PHPThis adds no core exception, validator, result object, field-error schema, string-rule language, renderer, hydrator, automatic request binding, or status inference.',
            'Consumer Contract version 10 and Strict Profile version 3 remain unchanged because this decision adds authoring guidance',
        ],
        'docs/decisions/013-optional-crud-reference-profile.md' => [
            'ADR 021 supersedes this record only where the earlier Create tree',
            'List remains handler-local after parsing its concrete `ListUsersPageRequest`',
        ],
        'example/src/Users/CreateUser/CreateUserCommand.php' => [
            'private function __construct(',
            'public static function fromJson(string $json): self',
            'array_key_exists(\'name\', $values)',
            'JSON_THROW_ON_ERROR',
            'FILTER_VALIDATE_EMAIL, 0',
            '!is_string($name) || !is_string($email)',
            'throw new UnacceptableCreateUserValues(',
        ],
        'example/src/Users/CreateUser/UnacceptableCreateUserValues.php' => [
            'final class UnacceptableCreateUserValues extends RuntimeException',
        ],
        'example/src/ApplicationComposition.php' => [
            'UnacceptableCreateUserValues::class => new Response(',
            '"{\\"error\\":{\\"code\\":\\"unprocessable_content\\",\\"message\\":\\"Request content is unacceptable.\\"}}\\n"',
        ],
        'example/src/Users/CreateUser/CreateUserHandler.php' => [
            '$command = CreateUserCommand::fromJson($request->body);',
            '$this->createUser->execute($principal, $tenant, $accountId, $command);',
        ],
        'example/src/Users/CreateUser/CreateUserOperation.php' => [
            'interface CreateUserOperation',
            'AuthenticatedPrincipal $principal,',
            'ResolvedTenant $tenant,',
            'AccountId $accountId,',
            'CreateUserCommand $command,',
        ],
        'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
            'final readonly class TransactionalCreateUser implements CreateUserOperation',
            'four-statement transaction',
            'INSERT INTO account_users (user_id, account_id)',
            'INSERT INTO application_jobs (',
        ],
        'tests/input-projection.php' => [
            'HTTP command parses one exact JSON object',
            'HTTP command exposes native duplicate-key last-value behavior',
            'HTTP command classifies structural and unacceptable input',
            'HTTP handler invokes only its typed create-user operation',
            'HTTP handler rejects invalid commands before use-case invocation',
            'mapped input failures emit no submitted data or log entry',
            'ApplicationComposition::errorResponses()',
            'example request boundary maps client failures before database work',
        ],
        'tests/create-user-support.php' => [
            'function unacceptableCreateUserValueBodies(): array',
            "'integer_name_with_unacceptable_email'",
            "'unacceptable_name_with_unknown_field'",
        ],
        'tests/crud.php' => [
            'account-scoped user creation rejects invalid input before database work',
        ],
        'templates/application/.ai/architecture.md' => [
            '{{INPUT_BOUNDARY_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{INPUT_OPERATION_1_FACTORY_AND_TYPE}}',
            'No normalization is implicit.',
            'complete field set, nullability, native types, and nested shape before applying value rules',
            'maps through an exact application-owned failure to generic `422`',
            'Query, header, route, and transport representations retain their separately recorded contracts.',
        ],
        'templates/application/.ai/testing.md' => [
            '{{INPUT_BOUNDARY_TEST_COMMAND_OR_NOT_APPLICABLE}}',
            'no operation-owned downstream database work',
            'When a separate typed operation seam exists, assert zero calls.',
            'duplicate-key-aware contract requires a separately accepted parser decision',
            'mixed unacceptable-value plus wrong-native-type case in property-order variants that both remain `400`',
            'Query, header, route, and transport representations retain their separately recorded contracts',
        ],
        'templates/application/.ai/change-workflow.md' => [
            'For structured request-body content, record the complete structural phase before value rules',
            'do not apply that body-content default implicitly to query, header, route, or transport representations.',
            'Structured request-body tests must prove mixed structural and value failures remain `400` in property-order variants',
        ],
        'skeleton/.ai/README.md' => [
            '| Change a non-simple route or request input | installed `vendor/phpthis/framework/docs/request-handling.md` | route manifest and only the application guides for concerns actually entered |',
        ],
        'skeleton/.ai/architecture.md' => [
            'NOT_APPLICABLE(INPUT)',
            'operation-specific named parser factory',
            'completes the whole exact-field, nullability, native-type, and nested-shape phase before applying any value',
            'exact application-owned exception registered as generic `422 unprocessable_content`',
            'Query, header, route, and transport representations retain their separately recorded contracts.',
        ],
        'skeleton/.ai/testing.md' => [
            'NOT_APPLICABLE(INPUT_EVIDENCE)',
            'no operation-owned downstream I/O or mutation',
            'zero typed-seam calls when one exists',
            'property-order variants that remain `400`',
            'Query, header, route, and transport representations retain their separately recorded contracts.',
        ],
        'skeleton/.ai/change-workflow.md' => [
            'complete structural phase before value rules',
            'mixed structural and value failures remain `400` in property-order variants',
            'Do not apply that body-content default implicitly to query, header, route, or transport representations.',
        ],
        'example/.ai/README.md' => [
            'ADR 042 for Create request-body classification',
            'only a correctly shaped and typed body with an unacceptable name or email throws application-owned `UnacceptableCreateUserValues`',
            'Query, header, route, and transport representations do not inherit this body-content default.',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/021-application-owned-typed-input-boundaries.md',
            'docs/decisions/042-application-owned-input-failure-classification.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $typedInputBoundaryArtifactMarkers, 'typed-input-boundary', $failures);

    $nativeDateTimeGuidanceArtifactMarkers = [
        '.ai/README.md' => [
            '| Change date, time, timezone, duration, or clock behavior | `.ai/types.md` | `docs/date-time.md`',
        ],
        '.ai/application-context.md' => [
            'Route application date and time work through installed `vendor/phpthis/framework/docs/date-time.md`.',
            'A selected third-party date-time or clock package remains an explicit application-owned dependency',
        ],
        '.ai/types.md' => [
            'Date and time work follows `docs/date-time.md`.',
            'Native `DateTimeImmutable`, `DateTimeZone`, and `DateInterval` are the default application tools',
            'External values require a complete lexical and component grammar',
            'requires a recorded daylight-saving gap and overlap policy.',
            'Behavior that depends on “now” receives an operation-specific injected clock',
        ],
        'docs/date-time.md' => [
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
        'docs/knowledge-map.md' => [
            '| Parse, persist, format, calculate, schedule, or test date and time behavior | `docs/date-time.md`',
        ],
        'docs/type-safety.md' => [
            '[Native date and time](date-time.md)',
            'A date or timestamp has a complete lexical and component grammar',
            'PHP format tokens and generic date guessing are not standards validation.',
        ],
        'templates/application/.ai/README.md' => [
            '| Change date, time, timezone, duration, or clock behavior | installed `vendor/phpthis/framework/docs/date-time.md`',
        ],
        'skeleton/.ai/README.md' => [
            '| Change date, time, timezone, duration, or clock behavior | installed `vendor/phpthis/framework/docs/date-time.md`',
        ],
        'tools/package-files.txt' => [
            'docs/date-time.md',
        ],
        'tools/guardrails/distribution.php' => [
            'Framework runtime dependencies must remain native PHP and extensions:',
            'The default skeleton must require only PHP and phpthis/framework.',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledNativeDateTimeGuidanceDistribution($project, $installedFramework);',
            'function proveInstalledNativeDateTimeGuidanceDistribution(',
            'PASS installed native date and time guidance distribution',
        ],
        'docs/guardrails.md' => [
            'native date/time guidance, installed task routes, exact package inventory, and Composer dependency checks preserve PHP',
            'without adding a framework or default-skeleton runtime date/time package',
            'The native date/time guidance guard pins the dedicated installed guide',
            'It adds no framework clock, date/time type, parser, consumer-checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $nativeDateTimeGuidanceArtifactMarkers,
        'native date and time guidance',
        $failures,
    );

    $finiteDataPathArtifactMarkers = [
        'docs/decisions/022-application-owned-finite-data-paths.md' => [
            'Status: accepted',
            'The protected document-list proof remains entirely application-owned.',
            'Consumer Contract version 4 and Strict Profile version 2 remain unchanged.',
            'eight complete application-owned statements',
            'an explicit empty list means an empty page and zero protected SQL',
            'each category is 1–64 bytes, valid UTF-8, and free of ASCII control bytes and DEL, with no normalization',
            'Cursor traversal is not a snapshot',
            'exercised only as SQLite-specific evidence by the repository\'s current PDO SQLite runtime',
            'not universal authentication, authorization, tenant-isolation, or row-security proof',
            'No ORM, query builder, repository, generic paginator, SQL/binding/placeholder helper, transaction callback, dialect abstraction, generated SQL, or dynamic SQL is accepted by this decision.',
            'No framework core, dependency, Consumer Contract version, Strict Profile version, or diagnostic changes.',
        ],
        'docs/consumer-contract.md' => [
            'ADR 022 records one finite SQLite application data path',
            'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
        ],
        'docs/guardrails.md' => [
            'The finite-data-path guard retains ADR 022',
            'three-driver harness remains PDO transport evidence only',
        ],
        'example/AGENTS.md' => [
            'complete raw engine-specific SQL visible',
            'complete SQL string and its explicit named parameter array together at that call site',
            'Do not add or use an ORM',
            'The document-list SQL is SQLite-specific application evidence.',
        ],
        'example/.ai/README.md' => [
            'evidence-oriented application context, not a traditional framework manual',
            'complete raw SQLite SQL and explicit named parameter arrays',
            'generic paginator',
        ],
        'example/.ai/data.md' => [
            'exactly one, two, or three category placeholders',
            'empty page, zero protected SQL',
            'Each accepted non-empty category is an exact 1–64-byte string',
            'v1:<order>:<sort_rank>:<document_key>',
            'traversal is not a snapshot',
            'MySQL and PostgreSQL are certified only for the base PDO transport harness.',
            'do not prove universal authorization',
        ],
        'example/src/Documents/DocumentRoutes.php' => [
            '/accounts/{account_id:positive-int}/documents',
            'new ListDocumentsHandler(',
        ],
        'example/src/Documents/ListDocuments/AuthorizeListDocuments.php' => [
            'interface AuthorizeListDocuments',
            'public function authorizeList(',
            'AuthenticatedPrincipal $principal',
            'ResolvedTenant $tenant',
        ],
        'example/src/Documents/ListDocuments/ListDocumentsPageRequest.php' => [
            'final readonly class ListDocumentsPageRequest',
            'if ($field !== \'order\' && $field !== \'categories\' && $field !== \'cursor\')',
            'return \'rank_asc\';',
            'count($submitted) > 3',
            "if (\$submitted === [''])",
            '$cursorOrder !== $order',
            '$cursorRank < 0 || $cursorRank > 1_000_000',
        ],
        'example/src/Documents/ListDocuments/DocumentSummary.php' => [
            'final readonly class DocumentSummary',
            'public static function fromDatabaseRow(array $row): self',
            'Document summary row must contain exactly document_key, title, category, and sort_rank.',
            '$parsed < 0 || $parsed > 1_000_000',
        ],
        'example/src/Documents/ListDocuments/ListDocumentsHandler.php' => [
            'private const int PAGE_SIZE = 50;',
            'private const int FETCH_LIMIT = self::PAGE_SIZE + 1;',
            '$pageRequest->categories === []',
            'documents.account_id = :requested_account_id',
            'documents.account_id = :resolved_tenant_account_id',
            'account_memberships.principal_id = :principal_id',
            'account_memberships.account_id = :membership_tenant_account_id',
            ':cursor_is_absent = 1',
            'documents.category IN (:category_1, :category_2, :category_3)',
            'ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC',
            'ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC',
            '\'cursor_primary_sort_rank\' => $cursorRank',
            '\'cursor_tie_sort_rank\' => $cursorRank',
            '\'cursor_document_key\' => $cursorDocumentKey',
            '\'cursor_is_absent\' => $cursorIsAbsent',
            '\'fetch_limit\' => self::FETCH_LIMIT',
            'DocumentSummary::fromDatabaseRow($row)',
            '\'next_cursor\' => $nextCursor',
        ],
        'tests/request-policy.php' => [
            'document list page request accepts only finite orders categories and canonical composite cursors',
            'document list page request rejects adversarial shapes and malformed cursors before SQL',
            'protected document list preserves policy order and rejects denials before SQL',
            'protected document list passes typed authority and rejects invalid query before protected SQL',
            'document list executes eight finite raw SQL branches and empty filters use zero SQL',
            'document list binds SQL-shaped category data and preserves tenant isolation',
            'document list composite cursor covers exact lookahead and stable 125-document traversal',
            'document list page keeps one statement and fingerprint across fixture sizes',
            'document list source uses direct raw SQL without ORM binding or pagination helpers',
        ],
        'templates/application/.ai/data.md' => [
            'finite code-owned fragments are necessary',
            'every bounded list or cursor',
        ],
        'templates/application/.ai/testing.md' => [
            'Every adopted cursor or bounded list proves its recorded omitted and empty-input behavior',
            'not universal authorization, tenant-isolation, or SQL-injection proof',
        ],
        'skeleton/.ai/data.md' => [
            'finite code-owned mapping',
            "cursor's version, stable tie-break and snapshot policy",
        ],
        'skeleton/.ai/testing.md' => [
            'exact zero- versus non-zero-statement bounds',
            'base PDO transport evidence as application-SQL certification',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/022-application-owned-finite-data-paths.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $finiteDataPathArtifactMarkers, 'finite-data-path', $failures);

    $observabilityArtifactMarkers = [
        '.ai/README.md' => [
            '| Change correlation or terminal summaries | `.ai/observability.md` | front-controller coordinator, sink, finite sources, and summary tests |',
        ],
        '.ai/observability.md' => [
            'application.request_summary',
            'at most eight finite code-owned database sources',
            'exactly one sink invocation attempt',
            'Never claim durable delivery',
        ],
        'docs/consumer-contract.md' => [
            'ADR 023 defines the mandatory request-level observability boundary',
            'application.request_summary',
            'at most eight database sources',
            'make exactly one sink invocation attempt',
            'Exactly one sink invocation attempt is not durable delivery.',
        ],
        'docs/knowledge-map.md' => [
            '`docs/observability/README.md`',
            'ADR 023',
        ],
        'docs/logging.md' => [
            '[0-9a-f]{32}',
            '`application.request_summary`',
            'at most eight explicitly registered `database_sources`',
            "anonymous-class runtime name embeds source path and line",
            'make exactly one sink invocation attempt',
            'not durable delivery',
            '`phpthis.request.unhandled`',
        ],
        'docs/observability/README.md' => [
            'ADR 023 is the mandatory request-summary decision',
            '`tests/observability.php`',
        ],
        'docs/observability/correlation-id.md' => [
            '[0-9a-f]{32}',
            'X-Request-ID',
            'TerminalRequestCoordinator::handle',
        ],
        'docs/observability/database-evidence.md' => [
            'at most eight unique names',
            'no two sources share a `QueryBudget` or `QueryTrace`',
            'A rejected over-budget call sets exceeded state',
        ],
        'docs/observability/event-schema.md' => [
            'version-1 `application.request_summary` schema',
            'Known denials gain no denial-specific field',
            'anonymous throwable uses its nearest named parent',
        ],
        'docs/observability/sink-failure.md' => [
            'exactly one synchronous sink invocation attempt',
            'An invocation attempt is not durable delivery.',
        ],
        'docs/observability/testing.md' => [
            '`tests/observability.php`',
            'exactly one sink invocation attempt',
            'They do not prove durable storage',
        ],
        'docs/decisions/023-application-owned-terminal-request-summaries.md' => [
            'Status: accepted',
            'Consumer Contract version 5 carries Strict Profile version 2 forward unchanged.',
            '[0-9a-f]{32}',
            'application.request_summary',
            'at most eight entries',
            'exactly one sink invocation attempt',
            'does not mean durable delivery',
            '`phpthis.request.unhandled`',
            'No ORM, repository, query builder, SQL generator, SQL/binding/placeholder helper, logger facade, global helper, middleware, event pipeline, discovery mechanism, or hidden database instrumentation is accepted by this decision.',
        ],
        'docs/decisions/README.md' => [
            '023-application-owned-terminal-request-summaries.md',
        ],
        'verification/ApplicationChecker.php' => [
            "'.ai/observability.md',",
        ],
        'tools/test-consumer-project.php' => [
            'proveObservabilityContextIsRequired(',
            'Required application context file is missing: .ai/observability.md.',
        ],
        'src/Database/QueryBudget.php' => [
            'private bool $exceeded = false;',
            '$this->exceeded = true;',
            'public function exceeded(): bool',
        ],
        'src/Http/UnknownFailureBoundary.php' => [
            'public function respond(): Response',
        ],
        'example/.ai/observability.md' => [
            '`list_users`, `get_user`, `create_user`, `get_document`, and `list_documents`',
            'one attempt is not durable delivery',
        ],
        'example/bootstrap.php' => [
            'ApplicationDatabasePath::fromString(',
            'new ApplicationComposition($databasePath)',
            '->http()',
        ],
        'example/src/ApplicationComposition.php' => [
            'return new TerminalRequestCoordinator(',
            'CorrelationId::generate()',
            "new QuerySummarySource('list_users'",
            "new QuerySummarySource('get_user'",
            "new QuerySummarySource('create_user'",
            "new QuerySummarySource('get_document'",
            "'list_documents',",
        ],
        'example/public/index.php' => [
            '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
        ],
        'example/src/Observability/CorrelationId.php' => [
            'bin2hex(random_bytes(16))',
        ],
        'example/src/Observability/QuerySummarySource.php' => [
            "'budget_exceeded' => \$this->budget->exceeded(),",
            'sharesObservationStateWith',
        ],
        'example/src/Observability/RequestSummary.php' => [
            "public const string EVENT = 'application.request_summary';",
            "'schema_version' => self::SCHEMA_VERSION,",
            "'document_cache' => \$this->documentCache,",
            "'database_sources' => \$this->querySources,",
            'private static function saturatedAdd',
            'private static function safeFailureClass',
            "str_contains(\$class, '@anonymous')",
        ],
        'example/src/Observability/ErrorLogRequestSummarySink.php' => [
            'JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES',
            'error_log($encoded)',
        ],
        'example/src/Observability/TerminalRequestCoordinator.php' => [
            'private const int MAXIMUM_QUERY_SOURCES = 8;',
            '$this->summarySink->emit($summary);',
            '$headers[\'X-Request-ID\'] = $this->correlationId->value;',
        ],
        'templates/application/.ai/observability.md' => [
            '{{TERMINAL_REQUEST_SUMMARY_COORDINATOR_PATH}}',
            '{{TERMINAL_SUMMARY_DATABASE_SOURCES_OR_EMPTY}}',
            '{{TERMINAL_SUMMARY_TEST_COMMAND}}',
            'One invocation attempt never means durable delivery.',
        ],
        'skeleton/.ai/observability.md' => [
            '`NOT_APPLICABLE(no database)`',
            'delivery is not guaranteed',
        ],
        'skeleton/bootstrap.php' => [
            'return new TerminalRequestCoordinator(',
            'CorrelationId::generate()',
            'new ErrorLogRequestSummarySink()',
        ],
        'skeleton/public/index.php' => [
            '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
        ],
        'skeleton/src/Observability/CorrelationId.php' => [
            'bin2hex(random_bytes(16))',
        ],
        'skeleton/src/Observability/RequestSummary.php' => [
            "public const string EVENT = 'application.request_summary';",
            "'database_sources' => \$this->querySources,",
            'private static function safeFailureClass',
            "str_contains(\$class, '@anonymous')",
        ],
        'skeleton/src/Observability/ErrorLogRequestSummarySink.php' => [
            'JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES',
            'error_log($encoded)',
        ],
        'skeleton/src/Observability/TerminalRequestCoordinator.php' => [
            'private const int MAXIMUM_QUERY_SOURCES = 8;',
            '$this->summarySink->emit($summary);',
            '$headers[\'X-Request-ID\'] = $this->correlationId->value;',
        ],
        'skeleton/tests/run.php' => [
            'Runtime GET /health must expose one generated correlation ID.',
            'Each terminal coordinator must expose fresh request-scoped state.',
        ],
        'tests/run.php' => [
            "require __DIR__ . '/observability.php';",
            "frameworkBehaviorGroupDefinitions('observability', observabilityTests())",
        ],
        'tests/observability.php' => [
            'correlation IDs are generated with 128 random bits in canonical form',
            'terminal coordinator emits one success summary and owns the response request ID',
            'default error-log sink serializes exactly one closed request summary',
            'terminal coordinator emits one status-only summary for every mapped or routed failure',
            'terminal coordinator emits one class-only summary for an unknown failure',
            "str_contains(\$encoded, '@anonymous')",
            'terminal coordinator reports repeated exact SQL without retaining SQL or bindings',
            'terminal coordinator aggregates ordered sources failures and bounded trace truncation',
            'terminal coordinator distinguishes exact budget use from one rejected attempt',
            'terminal coordinator keeps success and unknown responses unchanged when the sink throws',
            'terminal request summary excludes request response database and exception secrets',
            'query summary sources are finite uniquely named and connection local',
            'sequential terminal requests use fresh IDs budgets and traces',
            'terminal summary exposes one bounded document-cache outcome without cache data',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/023-application-owned-terminal-request-summaries.md',
            'docs/observability/README.md',
            'docs/observability/correlation-id.md',
            'docs/observability/database-evidence.md',
            'docs/observability/event-schema.md',
            'docs/observability/sink-failure.md',
            'docs/observability/testing.md',
            'templates/application/.ai/observability.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $observabilityArtifactMarkers, 'observability', $failures);

    return $failures;
}
