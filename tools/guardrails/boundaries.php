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

    $boundedResponseCookieProfileArtifactMarkers = [
        'docs/decisions/049-bounded-response-cookie-profile.md' => [
            '# ADR 049: Bounded response-cookie profile',
            'Status: accepted',
            'On 2026-08-11 in Asia/Manila, the accountable human approved this bounded correction',
            '`strlen($name) + strlen($value)` is at most 4,096 bytes',
            '`Path` is an absolute 1-to-1,024-byte ASCII producer value',
            'One `Response` contains at most 50 cookies',
            'The `__Secure-`, `__Host-`, `__Http-`, and `__Host-Http-` prefix comparisons are ASCII case-insensitive',
            'The accepted core ceiling is 2,620 physical lines',
        ],
        'docs/consumer-contract.md' => [
            'Contract version: 12',
            '### Contract version 12',
            'Contract version 12 carries Contract version 11 forward and retains Strict Profile version 3',
            'one response contains at most 50 cookies, has no repeated case-sensitive cookie name regardless of path',
            '`Domain`, `Partitioned`/CHIPS, and `Priority` remain unsupported.',
        ],
        '.ai/README.md' => [
            '| Change request, response, or generic response-cookie behavior |',
        ],
        '.ai/http.md' => [
            'the name and cookie-safe ASCII value together occupy at most 4,096 bytes',
            '`Path` is an absolute 1-to-1,024-byte string',
            '`maximumAgeSeconds` is either absent or 0 through 34,560,000',
            'Apply reserved-name requirements case-insensitively without changing the emitted case-sensitive cookie name',
            '`Domain`, `Partitioned`/CHIPS, and `Priority` are unsupported',
            'A `Response` accepts at most 50 cookies',
            '8,192 aggregate bytes across the exact `ResponseCookie::headerValue()` strings',
        ],
        '.ai/session.md' => [
            'A live session cookie uses the configured exact name and 32-character lowercase-hex identifier',
            'Absence of both lifetime attributes on the live cookie is not a reliable browser-close deadline',
            'Production authentication and session cookies normally use `Secure`',
            '`HttpOnly` prevents ordinary script access to the cookie bytes, but it does not stop script-initiated authenticated requests',
        ],
        '.ai/testing.md' => [
            'case-insensitive `__Secure-`, `__Host-`, `__Http-`, and `__Host-Http-` constraints without name rewriting',
            'source behavior and isolated installed-consumer positive and negative controls for every public invariant',
            "assert the live cookie's exact configured name",
            "Assert the deletion cookie's same identity and scope",
        ],
        '.ai/application-context.md' => [
            'the exact cookie name and prefix, canonical casing, host-only scope',
            'Prefer a canonical `__Host-` name for production authentication sessions when compatible',
        ],
        'docs/request-handling.md' => [
            'path scoping controls delivery and is not an authorization or security boundary',
            'Prefix requirements are checked case-insensitively without changing the emitted case-sensitive cookie name',
            'One response accepts at most 50 cookies and at most 8,192 bytes summed across their exact `headerValue()` strings',
        ],
        'docs/sessions.md' => [
            'A live session cookie contains the configured exact name and the certified 32-character lowercase-hex identifier',
            'The deletion cookie keeps the exact configured name',
            'Prefix requirements are applied case-insensitively without rewriting the configured name',
            'browser session restoration can retain the cookie',
            'the browser still attaches the cookie to eligible script-initiated requests',
        ],
        'docs/security.md' => [
            'Production authentication/session cookies normally use `Secure`',
            'Treat `HttpOnly` as protection from ordinary script access to cookie bytes, not from script-initiated authenticated requests',
        ],
        'docs/guardrails.md' => [
            'The bounded response-cookie profile guard pins the exact accepted name/value, path, expiration, maximum-age, count, aggregate-byte, duplicate-name, and case-insensitive prefix constraints',
            'These controls enforce the accepted 2,620-line ceiling but do not prove browser behavior',
        ],
        'docs/knowledge-map.md' => [
            '| Construct, emit, or review a generic response cookie |',
        ],
        'templates/application/.ai/operations.md' => [
            'Exact cookie name and prefix, canonical casing, host-only scope',
            'limit an insecure cookie to an explicitly isolated development profile',
        ],
        'templates/application/.ai/testing.md' => [
            "Assert the live cookie's exact configured name and identifier",
            "Assert the deletion cookie's same name and scope",
            'does not treat `Path`, `Secure`, `HttpOnly`, SameSite, or a prefix as authorization, CSRF, XSS, or transport proof',
        ],
        'skeleton/.ai/testing.md' => [
            "Assert the live cookie's exact configured name and identifier",
            "Assert the deletion cookie's same name and scope",
            'does not treat `Path`, `Secure`, `HttpOnly`, SameSite, or a prefix as authorization, CSRF, XSS, or transport proof',
        ],
        'src/Http/ResponseCookie.php' => [
            'MAXIMUM_NAME_VALUE_BYTES = 4_096',
            'MAXIMUM_PATH_BYTES = 1_024',
            "strlen(gmdate('Y', \$expiresAt)) !== 4",
            'MAXIMUM_AGE_SECONDS = 34_560_000',
            "str_starts_with(\$lowercaseName, '__http-')",
            "str_starts_with(\$lowercaseName, '__host-http-')",
        ],
        'src/Http/Response.php' => [
            'MAXIMUM_COOKIES = 50',
            'MAXIMUM_COOKIE_HEADER_BYTES = 8_192',
            'isset($cookieNames[$cookie->name])',
            '$cookieHeaderBytes += strlen($cookie->headerValue())',
        ],
        'src/Session/SessionConfiguration.php' => [
            '$lowercaseCookieName = strtolower($cookieName);',
            "str_starts_with(\$lowercaseCookieName, '__http-')",
        ],
        'tests/http-boundary.php' => [
            "yield 'response cookies are explicit validated values'",
            "str_repeat('n', 4_096)",
            "'/bad;attribute'",
            'strlen($firstAggregateCookie->headerValue()) + strlen($secondAggregateCookie->headerValue()) !== 8_192',
            "new ResponseCookie('__hOsT-name', 'value', '/nested'",
            "new ResponseCookie('__hOsT-HtTp-name', 'value', '/nested'",
            "new ResponseCookie('duplicate', 'two', '/nested'",
        ],
        'tests/session-lifecycle.php' => [
            "['__hOsT-insecure', '__sEcUrE-insecure', '__hTtP-insecure', '__hOsT-HtTp-insecure']",
            "Expected one live session cookie.",
            'sessionCookieSecurityAttributes($configuration)',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/049-bounded-response-cookie-profile.md',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledBoundedResponseCookieProfileDistribution(',
            "PHP_INT_SIZE >= 8 ? (int) '253402300799' : PHP_INT_MAX",
            "new ResponseCookie('name', 'value', '/bad;attribute'",
            "new ResponseCookie('__hOsT-name', 'value', '/nested'",
            "new Response(200, ['set-cookie' => 'manual=value']",
            "'__sEcUrE-insecure'",
            "['Set-Cookie: first-emitted=one; Path=/; HttpOnly; SameSite=Lax', false]",
            'PASS installed bounded response-cookie runtime',
            'PASS installed bounded response-cookie profile distribution',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $boundedResponseCookieProfileArtifactMarkers,
        'bounded response-cookie profile',
        $failures,
    );

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
            'Contract version: 12',
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
            'Contract version: 12',
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
            'Contract version: 12',
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
            'keeps `.ai/websockets.md` optional under current Contract version 12 as well as its originating Contract version 9',
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
            'PHPThis supplies no credential parser, verifier, issuer, revoker, identity provider, or authentication runtime/API.',
            'Cache-Control: private, no-store',
        ],
        'docs/knowledge-map.md' => [
            '`docs/request-policy.md`',
        ],
        'docs/request-policy.md' => [
            'PHPThis keeps authentication, tenant resolution, and authorization application-owned.',
            'Missing, malformed, and rejected credentials map to one generic `401`',
            'Ordinary forbidden and cross-tenant decisions map to the same generic `403`.',
            'When a policy reads storage, a trusted-key endpoint, or an external verifier, give it a separately named dependency, budget, trace, timeout, response bound, cache-staleness rule, and outage proof distinct from protected handler work.',
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
            '{{AUTHORIZATION_HEADER_BOUNDARY}}',
            '{{CREDENTIAL_PROFILE}}',
            '{{CREDENTIAL_VERIFIER_AND_CONFIGURATION}}',
            '{{CREDENTIAL_LIFECYCLE}}',
            '{{RFC_6750_COMPATIBILITY_POLICY}}',
            '{{CREDENTIAL_EVIDENCE_OR_LIMIT}}',
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

    foreach (statelessAuthenticationGuidanceFailures($root) as $failure) {
        $failures[] = $failure;
    }

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

    $frontendIntegrationGuidanceArtifactMarkers = [
        '.ai/README.md' => [
            '| Change frontend integration or application-owned HTML rendering | `.ai/http.md` | `docs/frontend-integration.md`, exact HTTP paths, and behavior evidence; add other concern guides only when entered |',
        ],
        '.ai/application-context.md' => [
            'Keep frontend delivery and optional HTML rendering application-owned.',
            "Prefer a separately built frontend that consumes the application's explicit HTTP API through a same-origin deployment",
            'a cross-origin topology records its exact origins, methods, headers, credentials, preflight, CSRF consequences, and proxy or TLS owner at the outer boundary.',
            'PHPThis adds no frontend stack, CORS or automatic `OPTIONS` mechanism, templating API, or OpenAPI runtime.',
            'Route application work through installed `vendor/phpthis/framework/docs/frontend-integration.md`;',
        ],
        '.ai/http.md' => [
            'For frontend integration, a frontend/API handoff, browser CORS, static assets, or application-owned HTML rendering, follow `docs/frontend-integration.md`.',
            'Treat cross-origin browser access as one complete application or deployment policy covering preflight, success, mapped failure, routing-owned `404` and `405`, unknown failure, credentials, cache variation, and exposed response fields including `X-Request-ID`.',
            'do not add generic CORS middleware or claim partial headers establish complete support.',
            'Add no framework renderer, templating engine, static-file server, OpenAPI or JSON Schema generator, or client generator.',
        ],
        'docs/frontend-integration.md' => [
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
        'docs/knowledge-map.md' => [
            '| Design, implement, or review frontend integration, a frontend/API handoff, browser CORS, static assets, or application-owned HTML rendering |',
            '`docs/frontend-integration.md`; add only the concern guides it routes to',
            'verify that no framework frontend runtime, CORS middleware, renderer, templating or asset engine, machine-readable API generator, or client generator was implied',
        ],
        'skeleton/.ai/README.md' => [
            '| Build or change frontend integration or application-owned HTML rendering | installed `vendor/phpthis/framework/docs/frontend-integration.md` | `.ai/architecture.md`, `.ai/testing.md`, and exact HTTP paths; add other concern guides only when entered |',
        ],
        'templates/application/.ai/README.md' => [
            '| Build or change frontend integration or application-owned HTML rendering | installed `vendor/phpthis/framework/docs/frontend-integration.md` | `.ai/architecture.md`, `.ai/testing.md`, and exact HTTP paths; add other concern guides only when entered |',
        ],
        'tools/package-files.txt' => [
            'docs/frontend-integration.md',
        ],
        'tools/guardrails/repository.php' => [
            "'docs/frontend-integration.md',",
        ],
        'tools/guardrails/distribution.php' => [
            'Framework runtime dependencies must remain native PHP and extensions:',
            'The default skeleton must require only PHP and phpthis/framework.',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledFrontendIntegrationGuidanceDistribution($project, $installedFramework);',
            'function proveInstalledFrontendIntegrationGuidanceDistribution(',
            'PASS installed frontend integration guidance distribution',
        ],
        'docs/guardrails.md' => [
            'frontend integration guidance, installed task routes, exact package inventory, and Composer dependency checks keep browser clients and cross-origin policy application-owned',
            'without adding an SDK, generator, frontend scaffold, OpenAPI runtime, CORS middleware, or framework/default-skeleton runtime dependency',
            'The frontend integration guidance guard pins the dedicated installed guide',
            'It adds no JavaScript or TypeScript SDK, generator, frontend scaffold, OpenAPI artifact or runtime, CORS middleware, automatic preflight, framework source, consumer-checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $frontendIntegrationGuidanceArtifactMarkers,
        'frontend integration guidance',
        $failures,
    );

    $structuredJsonSuccessEnvelopeArtifactMarkers = [
        '.ai/README.md' => [
            '| Define or change a structured JSON resource success representation, including nested child data | `.ai/http.md` |',
            'prove one fixed bounded operation-owned I/O plan plus I/O-free mapping and encoding',
            'preserve the advisory application-owned boundary without a generic wrapper, relationship loader, serializer, paginator, or generator',
        ],
        '.ai/http.md' => [
            "For a new application's successful structured JSON resource representations",
            'one resource uses a top-level `data` object',
            'a collection uses a top-level `data` array including `[]`',
            'optional operation-owned pagination or other non-resource information uses a top-level `meta` object',
            'Keep every field and continuation semantic operation-specific, keep errors and non-resource representations outside this convention',
            'do not add a framework or application-wide response wrapper, serializer, resource class, paginator, middleware, helper, discovery mechanism, or generator.',
            'Nested child objects and collections may appear inside `data` only as explicit operation-owned fields.',
            'The complete I/O plan remains fixed and bounded independently of parent-page cardinality;',
            '`PHT003` rejects direct lexical `Connection` calls inside loops, but it cannot prove that an indirectly called mapper, cache client, or integration is I/O-free',
            'For an ordinary nested to-one relationship, prefer one explicit bounded join with concrete projections and explicit parent and child predicates.',
        ],
        '.ai/database.md' => [
            'For a response that embeds child data, the handler or its one justified concrete operation owns the complete data-access plan.',
            'Per-resource mapping, serialization, callbacks, and recursive traversal perform no database, cache, or external-service I/O.',
            '`PHT003` catches a direct lexical database call inside a loop; it does not inspect an indirectly called mapper or prove the absence of cache or integration work',
            'For an ordinary to-one relationship such as `workspace.creator`, prefer one explicit bounded join',
            'A finite batch plan may instead use a fixed number of reviewed statements when one join is inappropriate',
            'Parent pagination remains authoritative.',
        ],
        '.ai/testing.md' => [
            'A nested-resource response proof covers empty, one-parent, and maximum-parent pages;',
            'Snapshot each relevant counter immediately after data loading and again after mapping and JSON encoding to prove those phases add no I/O.',
            'Frontend-style decoder fixtures reject missing, unknown, wrongly typed, out-of-bound, and malformed nested shapes while accepting semantically identical JSON object members in another order.',
            'Retain or add one intentionally invalid `.php.fixture` negative control that performs one child query per parent.',
            'This negative control does not let `PHT003` claim detection of indirect database, cache, or integration I/O.',
        ],
        'docs/frontend-integration.md' => [
            '## Recommended structured JSON resource success envelope',
            'Put one resource in a top-level `data` object.',
            'Put a resource collection in a top-level `data` array, including `[]` when the collection is empty.',
            'Put optional pagination or other operation-owned non-resource information in a top-level `meta` object.',
            'Pagination continuation names, grammar, ordering, bounds, filter compatibility, invalidation, snapshot behavior, and end-of-list representation remain distinct operation contracts.',
            '`next_after_user_id` and `next_cursor` may both live under `meta` without becoming interchangeable.',
            'A missing resource normally follows the operation\'s recorded `404` failure contract instead of returning a successful `{"data":null}`.',
            'Errors remain separate from this success envelope and retain their explicit status, media type, and stable public error shape.',
            'Bodyless `204`, `205`, and `304` responses, explicit `HEAD`, downloads, HTML, plain text, health responses, and other non-resource representations are not automatically wrapped.',
            'Every adopting operation records and proves its exact `Content-Type`, status, field set, native JSON types, null-versus-absence behavior, scalar and collection bounds, identifier representation, temporal representation, collection ordering, and compatibility policy.',
            'Frontend fixtures and decoders reject incompatible media types, malformed JSON, unknown or missing fields where the operation forbids them, wrong native types, out-of-bound values, and incompatible envelope changes as decode or contract failures.',
            'A top-level `data` member alone is not JSON:API.',
            'Existing published resource-named or bare responses remain valid application contracts; moving one to `data` is a breaking API change',
            'This recommendation adds no runtime wrapper, serializer, resource class, paginator, middleware, helper, reflection, discovery, OpenAPI or JSON Schema artifact, SDK, or client generator.',
            '## Embed nested resources without N+1 I/O',
            'Name the relationship for its meaning in that operation: `creator` communicates more than an ambiguous `user`.',
            'The complete I/O plan remains fixed and bounded independently of parent-page cardinality.',
            'Perform all database, cache, and external-service operations before resource mapping and JSON encoding;',
            '`PHT003` catches direct lexical database calls inside loops, but it does not prove that an indirectly called mapper, cache client, or integration performs no I/O.',
            'Parent pagination remains the controlling contract.',
            'The frontend decoder owns the same exactness.',
            'Adding a nested relationship, removing it, changing its optionality, or changing its fields can break an exact field-set decoder',
            'Nested representations add no PHPThis relationship loader, ORM, lazy loading, resource class, serializer, generic batcher, expansion syntax, or JSON:API relationship support.',
        ],
        'docs/request-handling.md' => [
            '## Recommended structured JSON resource success envelope',
            'An empty collection is `{"data":[]}`, not `null`.',
            'Continuation names such as `next_after_user_id` and `next_cursor` deliberately remain distinct',
            'their grammar, ordering, bounds, filter compatibility, invalidation, snapshot behavior, null-versus-absence policy, and end-of-list semantics do not become a framework pagination contract',
            'Errors keep their separate explicit status, exact media type, and stable public error representation.',
            'HTTP status remains authoritative; do not add a body-level success flag or duplicated status field.',
            'The convention does not wrap bodyless `204`, `205`, or `304` responses, explicit `HEAD`, downloads, HTML, plain text, health responses, or other non-resource representations.',
            'proves the exact field set, native JSON types, null-versus-absence behavior, scalar and collection bounds, identifier representation, temporal representation, collection ordering, and compatibility policy.',
            'encodes its concrete application-owned array with `JSON_THROW_ON_ERROR`',
            'changing one to `data` is a breaking API change requiring an explicit migration or versioning decision.',
            'PHPThis adds no runtime wrapper, serializer, resource class, paginator, middleware, facade, helper, reflection, discovery, OpenAPI or JSON Schema artifact, SDK, or client generator.',
            '### Nested child data',
            'Prefer a semantic relationship name such as `creator` instead of an ambiguous `user`.',
            'Mapping, serialization, callbacks, and recursive traversal perform no database, cache, or external-service I/O.',
            'Never query one child per parent.',
            '`PHT003` rejects direct lexical database calls inside loops but cannot prove that an indirect mapper, cache client, or integration is I/O-free',
            'Join fan-out must not alter the parent limit, stable order, continuation, or duplicate-parent behavior.',
            'PHPThis adds no relationship mechanism, loader, serializer, generic batcher, or expansion API.',
        ],
        'docs/database.md' => [
            '## N+1-safe nested resource plans',
            'The number of database statements, cache operations, and external calls remains fixed as the bounded parent page grows.',
            'For an ordinary to-one relationship such as `workspace.creator`, prefer one explicit bounded join',
            'A finite batch plan may instead use a fixed number of reviewed statements when one join is inappropriate',
            'Never execute one child query per parent, hide repeated I/O behind a mapper, or introduce a repository, relationship loader, generic batcher, or generated placeholder list.',
            '`PHT003` catches direct lexical calls to `selectAllRows`, `selectOneRow`, or `executeStatement` inside a loop.',
            'The existing isolated N+1 negative control in `tools/test-query-scaling.php` demonstrates both query growth and budget containment',
            'This guidance adds no PHPThis runtime relationship mechanism, ORM, lazy loading, resource serializer, paginator, or new Strict Profile diagnostic.',
        ],
        'docs/knowledge-map.md' => [
            '| Define, change, or review a structured JSON resource success representation, including nested child objects or collections |',
            'exact application response construction and `Content-Type`',
            'fixed bounded query/cache/external-call counts independent of parent-page cardinality',
            'preserve the advisory application-owned boundary',
        ],
        'docs/performance.md' => [
            'a separate checked application-owned `workspace.creator` proof without changing any published example endpoint',
            'A separate fixture-local denial gate proves zero database statements before this work.',
            'Hidden, cross-tenant, and fixed-principal-denied creators exercise the explicit `null` policy',
            'The accepted nested read executes one statement for empty, one-parent, and 50-parent fixtures.',
            'It retains statement counts after loading, after mapping, and after encoding; all three phases remain `[1, 1, 1]`, while denial remains `[0, 0, 0]`.',
            'performs a creator query inside the parent loop, grows from 2 statements for one parent to 51 for 50',
            'Its phase counts grow from `[1, 2, 2]` to `[1, 51, 51]`;',
            'Each negative source uses a `.php.fixture` suffix and is never accepted application code.',
        ],
        'example/.ai/data.md' => [
            '## Checked nested workspace creator fixture',
            'This is an isolated scaling fixture, not a route and not a change to the published user or document response contracts.',
            'All identifiers are canonical lowercase UUID strings with a supported version nibble and RFC variant nibble.',
            'Hidden, cross-tenant, and principal-denied fixture parents remain absent;',
            'A separate fixture-local deny input stops before connection construction and proves zero database statements.',
            'It retains statement counts after loading, after mapping, and after encoding; every accepted shape is `[1, 1, 1]`.',
            'Its retained denial counts are `[0, 0, 0]`.',
            'grows from phase counts `[1, 2, 2]` for one parent to `[1, 51, 51]` for 50',
            'queries one creator inside the parent loop.',
            'It has no request authentication or composed request-policy path',
        ],
        'skeleton/.ai/README.md' => [
            '| Define or change a structured JSON resource success representation, including nested child data | installed `vendor/phpthis/framework/docs/frontend-integration.md`, then installed `vendor/phpthis/framework/docs/request-handling.md`;',
            'fixed bounded query/cache/external-call counts independent of parent-page cardinality',
            'add no generic wrapper, relationship loader, serializer, paginator, or generator',
        ],
        'templates/application/.ai/README.md' => [
            '| Define or change a structured JSON resource success representation, including nested child data | installed `vendor/phpthis/framework/docs/frontend-integration.md`, then installed `vendor/phpthis/framework/docs/request-handling.md`;',
            'fixed bounded query/cache/external-call counts independent of parent-page cardinality',
            'add no generic wrapper, relationship loader, serializer, paginator, or generator',
        ],
        'example/src/Users/GetUser/GetUserHandler.php' => [
            "['data' => ['id' => \$user->id->value, 'name' => \$user->name]]",
            "'Content-Type' => 'application/json; charset=utf-8'",
            'status: 404',
            '{\"error\":{\"code\":\"user_not_found\",\"message\":\"User was not found.\"}}',
        ],
        'example/src/Users/ListUsers/ListUsersHandler.php' => [
            "'data' => \$users",
            "'meta' => ['next_after_user_id' => \$nextAfterUserId]",
            "'Content-Type' => 'application/json; charset=utf-8'",
        ],
        'example/src/Documents/ListDocuments/ListDocumentsHandler.php' => [
            "'data' => []",
            "'meta' => ['next_cursor' => null]",
            "'data' => \$documents",
            "'meta' => ['next_cursor' => \$nextCursor]",
            "'Content-Type' => 'application/json; charset=utf-8'",
        ],
        'example/src/Users/CreateUser/CreateUserHandler.php' => [
            "'data' => [",
            "'account_id' => \$accountId->value",
            "'name' => \$command->name",
            "'email' => \$command->email",
            "'Content-Type' => 'application/json; charset=utf-8'",
        ],
        'example/src/Documents/GetDocument/GetDocumentHandler.php' => [
            "'data' => [",
            "'account_id' => \$accountId->value",
            "'key' => \$documentKey->value",
            "'title' => \$document->title",
            "'Content-Type' => 'application/json; charset=utf-8'",
        ],
        'example/src/DocumentFiles/UploadDocumentFileHandler.php' => [
            "json_encode(['data' => ['file_id' => \$id->value]], JSON_THROW_ON_ERROR)",
            'status: 201',
            "'Content-Type' => 'application/json; charset=utf-8'",
        ],
        'example/.ai/file-transfers.md' => [
            '`201` JSON with a top-level `data` object containing one generated `file_id`',
        ],
        'tests/crud.php' => [
            '{\"data\":{\"account_id\":42,\"name\":\"Ada Lovelace\",\"email\":\"ada@example.com\"}}\n',
            '{\"data\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n',
            '{\"data\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"meta\":{\"next_after_user_id\":null}}\n',
            '{\"data\":[],\"meta\":{\"next_after_user_id\":null}}\n',
            "'meta' => ['next_after_user_id' => '50']",
            '$lookahead[\'body\'] !== $expectedLookaheadBody',
            'count($decoded) !== 2',
            "!array_key_exists('data', \$decoded)",
            "!array_key_exists('meta', \$decoded)",
            "yield 'user item route separates missing records from malformed identifiers'",
            '$missing->status !== 404',
            '{\"error\":{\"code\":\"user_not_found\",\"message\":\"User was not found.\"}}\n',
            '$missingBudget->used() !== 1',
        ],
        'tests/request-policy.php' => [
            '{\"data\":{\"account_id\":42,\"key\":\"Doc_9-z\",\"title\":\"Example document\"}}\n',
            '{\"data\":[],\"meta\":{\"next_cursor\":null}}\n',
            '{\"data\":[{\"document_key\":\"Doc_051\",\"title\":\"Document 51\",\"category\":\"alpha\",\"sort_rank\":2}],\"meta\":{\"next_cursor\":null}}\n',
            "'meta' => ['next_cursor' => 'v1:rank_asc:2:Doc_050']",
            '$lookahead[\'body\'] !== $expectedLookaheadBody',
            'count($decoded) !== 2',
            "!array_key_exists('data', \$decoded)",
            "!array_key_exists('meta', \$decoded)",
        ],
        'tests/document-files.php' => [
            '$uploadData = is_array($decoded) && array_keys($decoded) === [\'data\']',
            '$upload[\'body\'] !== \'{"data":{"file_id":"\' . $storedId . "\\"}}\\n"',
            'content-type: application/json; charset=utf-8',
        ],
        'tests/consumer-profile.php' => [
            '{\"data\":{\"account_id\":42,\"name\":\"Profile Name Marker\",\"email\":\"profile-secret@example.com\"}}\n',
        ],
        'tests/fixtures/workspaces-with-creators.accepted.php' => [
            "if (\$authorizationValue === 'deny') {",
            'workspaces.tenant_id = :workspace_tenant_id',
            'workspaces.authorized_principal_id = :workspace_authorized_principal_id',
            'creators.tenant_id = :creator_tenant_id',
            'creators.authorized_principal_id = :creator_authorized_principal_id',
            'LIMIT :parent_limit',
            'foreach ($rows as $row) {',
            '$workspaces[] = acceptedNestedWorkspaceItem($row);',
            '$afterLoadStatements = $trace->snapshot()[\'statements\'];',
            '$afterMappingStatements = $trace->snapshot()[\'statements\'];',
            '$afterEncodingStatements = $trace->snapshot()[\'statements\'];',
            "'phase_statements' => [",
            '$body = json_encode([\'data\' => $workspaces], JSON_THROW_ON_ERROR) . "\\n";',
            '[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}',
            "preg_match('/^https:\/\/[!-~]+\$/D', \$value)",
        ],
        'tests/fixtures/workspaces-with-creators.n-plus-one.php.fixture' => [
            'workspaces.authorized_principal_id = :workspace_authorized_principal_id',
            'foreach ($rows as $row) {',
            '$creatorRow = $connection->selectOneRow(',
            'users.authorized_principal_id = :creator_authorized_principal_id',
            '$workspaces[] = rejectedNestedWorkspaceItem($row, $creatorRow);',
            '$afterLoadStatements = $trace->snapshot()[\'statements\'];',
            '$afterMappingStatements = $trace->snapshot()[\'statements\'];',
            '$afterEncodingStatements = $trace->snapshot()[\'statements\'];',
            "'phase_statements' => [",
            '$body = json_encode([\'data\' => $workspaces], JSON_THROW_ON_ERROR) . "\\n";',
        ],
        'tools/test-query-scaling.php' => [
            "'tests/fixtures/workspaces-with-creators.accepted.php'",
            "'tests/fixtures/workspaces-with-creators.n-plus-one.php.fixture'",
            'PHT003 tests/fixtures/workspaces-with-creators.n-plus-one.php.fixture:61 calls a database method inside a loop.',
            'The accepted nested workspace fixture must pass the Strict Profile.',
            'Nested workspace mapping and JSON encoding must perform zero database calls.',
            'Nested workspace authorization denial must perform zero database work.',
            'A creator denied by the fixed principal predicate must remain an explicit null child.',
            'A present nested creator id must equal created_by_user_id.',
            'Accepted JOIN and nested N+1 control outputs differ.',
            'Each accepted nested page must execute one statement.',
            'Accepted nested mapping or encoding added a database statement.',
            'One-parent N+1 phase counts changed.',
            'Maximum N+1 phase counts changed.',
            'Budgeted N+1 phase counts changed.',
            'Nested N+1 budget rejection must occur before statement 4 enters the trace.',
            'workspace.creator accepted 1/page and rejected 2 -> 51; budget stopped statement 4; mapping/JSON 0 database calls',
        ],
        'tools/package-files.txt' => [
            'docs/frontend-integration.md',
            'docs/knowledge-map.md',
            'docs/request-handling.md',
            'templates/application/.ai/README.md',
        ],
        'tools/test-consumer-project.php' => [
            '$installedStructuredJsonProofCompletion =',
            'proveInstalledStructuredJsonSuccessEnvelopeDistribution(',
            'function proveInstalledStructuredJsonSuccessEnvelopeDistribution(',
            "!== 'installed-structured-json-and-nested-resource-proof-complete'",
            "return 'installed-structured-json-and-nested-resource-proof-complete';",
            'Installed structured JSON and nested-resource proof did not complete.',
            'decodeGetUserSuccess(Response $response)',
            'decodeListUsersSuccess(Response $response)',
            'decodeListDocumentsSuccess(Response $response)',
            'decodeListWorkspacesSuccess(Response $response)',
            'function isCanonicalUuidString(mixed $value): bool',
            '[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}',
            'json_decode($response->body, false, 8, JSON_THROW_ON_ERROR)',
            '!$decoded instanceof stdClass',
            '!is_array($decoded->data)',
            'count($decoded->data) > 50',
            'strlen($nextAfterUserId) > strlen((string) PHP_INT_MAX)',
            "strlen('v1:rank_desc:1000000:') + 64",
            "preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', \$document->document_key)",
            'strlen($document->category) > 64',
            "preg_match('/[\\x00-\\x1F\\x7F]/', \$document->category)",
            '$document->sort_rank > 1_000_000',
            'Expected a successful response before decoding.',
            'Expected the exact structured JSON response media type.',
            '$getUser = new Response(',
            '{\"data\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n',
            '$listUsersContinuation = new Response(',
            '{\"data\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"meta\":{\"next_after_user_id\":\"50\"}}\n',
            '$listUsersEnd = new Response(',
            '{\"data\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"meta\":{\"next_after_user_id\":null}}\n',
            '$listDocumentsEmpty = new Response(',
            '{\"data\":[],\"meta\":{\"next_cursor\":null}}\n',
            '$listDocumentsContinuation = new Response(',
            '{\"data\":[{\"document_key\":\"Doc_001\",\"title\":\"Plan\",\"category\":\"active\",\"sort_rank\":1}],\"meta\":{\"next_cursor\":\"v1:rank_asc:1:Doc_001\"}}\n',
            '$listDocumentsEnd = new Response(',
            '{\"data\":[{\"document_key\":\"Doc_001\",\"title\":\"Plan\",\"category\":\"active\",\"sort_rank\":1}],\"meta\":{\"next_cursor\":null}}\n',
            '$reorderedGetUser = new Response(',
            '{\"data\":{\"name\":\"Ada Lovelace\",\"id\":1}}\n',
            '$reorderedListDocuments = new Response(',
            '{\"meta\":{\"next_cursor\":null},\"data\":[]}\n',
            '$opaqueListUsersContinuation = new Response(',
            '{\"data\":[],\"meta\":{\"next_after_user_id\":\"01\"}}\n',
            '$opaqueListDocumentsContinuation = new Response(',
            '{\"data\":[],\"meta\":{\"next_cursor\":\"50\"}}\n',
            '$workspaceWithCreator = new Response(',
            '$workspaceWithoutVisibleCreator = new Response(',
            '$workspaceWithAvatar = new Response(',
            '$reorderedWorkspaceWithCreator = new Response(',
            "'created_by_user_id' => \$creatorId",
            "\$creator->id !== \$workspace->created_by_user_id",
            '$invalidWorkspaceBodies = [',
            "'id' => 'cd7fcaf6-7b5c-0b25-a2f6-01ecad54f86b'",
            "'id' => 'cd7fcaf6-7b5c-4b25-72f6-01ecad54f86b'",
            "'email' => 'admin@example.com'",
            "'display_name' => str_repeat('n', 161)",
            "'avatar_url' => 'http://example.com/a.png'",
            "'avatar_url' => 'https://example.com/' . str_repeat('a', 2_030)",
            '$missingUser = new Response(',
            '$missingUser->status !== 404',
            '{\"error\":{\"code\":\"user_not_found\",\"message\":\"User was not found.\"}}\n',
            'str_contains($missingUser->body, \'"data":null\')',
            '{\"data\":\n',
            '{\"user\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n',
            "['Content-Type' => 'application/json']",
            '{\"data\":[],\"meta\":{\"next_cursor\":\"v1:rank_asc:1:Doc_001\"}}\n',
            '{\"data\":[],\"meta\":{\"next_after_user_id\":\"50\"}}\n',
            '{\"data\":[],\"meta\":{\"next_after_user_id\":\"\"}}\n',
            '{\"data\":[],\"meta\":{\"next_cursor\":\"\"}}\n',
            '{\"data\":{},\"meta\":{\"next_after_user_id\":null}}\n',
            '{\"data\":{},\"meta\":{\"next_cursor\":null}}\n',
            '{\"data\":[],\"meta\":{\"next_after_user_id\":50}}\n',
            '{\"data\":[],\"meta\":{\"next_cursor\":50}}\n',
            '$tooManyUsersBody = json_encode(',
            '$overlongUserContinuationBody = json_encode(',
            '$tooManyDocumentsBody = json_encode(',
            '$overlongDocumentCursorBody = json_encode(',
            '$invalidDocumentScalarsBody = json_encode(',
            "'document_key' => str_repeat('k', 65)",
            "'category' => str_repeat('c', 65)",
            "'sort_rank' => 1_000_001",
            'Envelope|Wrapper|Serializer|Resource|ResourceCollection|Paginator|Pagination',
            'Response|Json|Resource|Success|Pagination',
            'SchemaGenerator|ClientGenerator|SdkGenerator',
            'Response|Json|Resource|Success|Pagination)\/(?:Builder|Factory',
            'RelationshipLoader|RelationLoader|LazyRelationship|LazyRelation|EagerRelationship|EagerRelation|BatchLoader|DataLoader',
            '$installedSourceRoots = [',
            '$project . \'/src\'',
            'PASS installed structured JSON success-envelope and nested-resource runtime',
            'PASS installed structured JSON success-envelope and nested-resource guidance distribution',
        ],
        'tools/guardrails/boundaries.php' => [
            '$structuredJsonForbiddenRuntimePathFixtures = [',
            "'src/Http/ResponseEnvelope.php'",
            "'src/Http/ResponseWrapper.php'",
            "'src/Http/JsonEnvelope.php'",
            "'src/Http/JsonResponse.php'",
            "'src/Http/JsonSerializer.php'",
            "'src/Http/UserResource.php'",
            "'src/Http/ResourceCollection.php'",
            "'src/Http/ResourceTransformer.php'",
            "'src/Http/Paginator.php'",
            "'src/Http/PaginationMiddleware.php'",
            "'src/Http/ResponseMiddleware.php'",
            "'src/Http/ResponseBuilder.php'",
            "'src/Http/ResponseFactory.php'",
            "'src/Http/ResponseHelper.php'",
            "'src/Http/JsonResponder.php'",
            "'src/Http/ResponseFormatter.php'",
            "'src/Http/JsonDiscovery.php'",
            "'src/Http/Response/Builder.php'",
            "'src/Http/Response/Factory.php'",
            "'src/Http/Response/Helper.php'",
            "'src/Http/Response/Middleware.php'",
            "'src/Http/Json/Responder.php'",
            "'src/Http/Resource/Transformer.php'",
            "'src/Http/Json/Discovery.php'",
            "'src/Database/WorkspaceCreatorRelationshipLoader.php'",
            "'src/Database/RelationLoader.php'",
            "'src/Database/LazyRelationship.php'",
            "'src/Database/CreatorBatchLoader.php'",
            "'src/Database/Relationships/Loader.php'",
            "'src/Http/SchemaGenerator.php'",
            "'src/Http/ClientGenerator.php'",
            '$structuredJsonAllowedRuntimePathFixtures = [',
            "'src/Http/Request.php'",
            "'src/Http/Response.php'",
            "'src/Database/Connection.php'",
            "'src/Database/QueryBudget.php'",
            '$root . \'/skeleton/src\'',
            '$structuredJsonSourceRoots = [',
        ],
        'docs/guardrails.md' => [
            'structured JSON success-envelope guidance, task routes, exact checked example shapes, isolated installed response and decoder controls, and dependency checks preserve the advisory application-owned `data` and optional `meta` convention',
            'without adding a framework wrapper, serializer, resource class, paginator, middleware, helper, discovery mechanism, generator, runtime dependency, checker rule, or `PHT` diagnostic',
            'The structured JSON success-envelope guard pins the two installed public guides',
            'The isolated installed proof constructs explicit application-owned `Response` values',
            'Framework, default-skeleton, installed-framework, copied-project, and package-inventory path checks',
            'This deterministic name/path evidence does not prove absence of differently named hidden behavior',
            'It adds no framework response mechanism or consumer-checker rule and does not make the example envelope a consumer-validity rule.',
            'N+1-safe nested-resource guidance, a focused checked `workspace.creator` fixture, query-scaling and `PHT003` controls, installed nested decoder evidence',
            'The N+1-safe nested-resource extension pins the maintainer, public, knowledge-map, skeleton, and application-template routes',
            'a deny-before-connection control uses zero statements;',
            'Retained phase snapshots stay `[1, 1, 1]` after load, mapping, and encoding for every accepted page and `[0, 0, 0]` for denial',
            'exposes `[1, 2, 2]` and `[1, 51, 51]` phase counts',
            'The intentionally invalid `.php.fixture` performs one creator query inside the parent loop',
            'The isolated installed proof accepts present and null creators plus reordered object members',
            'Those deterministic source/name checks do not prove the absence of indirect or differently named database, cache, or integration I/O;',
            'This adds no framework relationship mechanism, response mechanism, consumer-checker rule, Contract or Strict Profile change, or new `PHT` diagnostic.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $structuredJsonSuccessEnvelopeArtifactMarkers,
        'structured JSON success-envelope guidance and proof',
        $failures,
    );

    $installedStructuredJsonProofSource = file_get_contents(
        $root . '/tools/test-consumer-project.php',
    );
    $installedStructuredJsonProofWiring = <<<'PHP'
    $installedStructuredJsonProofCompletion =
        proveInstalledStructuredJsonSuccessEnvelopeDistribution(
            $project,
            $installedFramework,
            $environment,
        );

    if (
        $installedStructuredJsonProofCompletion
            !== 'installed-structured-json-and-nested-resource-proof-complete'
    ) {
        throw new RuntimeException('Installed structured JSON and nested-resource proof did not complete.');
    }
PHP;
    $installedStructuredJsonProofWiringIsCanonical = static function (
        string $source,
    ) use ($installedStructuredJsonProofWiring): bool {
        if (
            substr_count($source, $installedStructuredJsonProofWiring) !== 1
            || substr_count($source, '$installedStructuredJsonProofCompletion') !== 2
            || substr_count(
                $source,
                "'installed-structured-json-and-nested-resource-proof-complete'",
            ) !== 2
            || substr_count(
                $source,
                "    return 'installed-structured-json-and-nested-resource-proof-complete';",
            ) !== 1
        ) {
            return false;
        }

        $wiringOffset = strpos($source, $installedStructuredJsonProofWiring);

        if ($wiringOffset === false) {
            return false;
        }

        $prefixTokens = token_get_all(substr($source, 0, $wiringOffset));
        $braceDepth = 0;
        $functionDeclarationSeen = false;
        $previousSignificantToken = null;

        foreach ($prefixTokens as $prefixToken) {
            if (is_array($prefixToken)) {
                if ($prefixToken[0] === T_FUNCTION) {
                    $functionDeclarationSeen = true;
                }

                if (in_array($prefixToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $previousSignificantToken = $prefixToken;
                continue;
            }

            if ($prefixToken === '{') {
                $braceDepth++;
            } elseif ($prefixToken === '}') {
                $braceDepth--;
            }

            $previousSignificantToken = $prefixToken;
        }

        if (
            $braceDepth !== 1
            || $functionDeclarationSeen
            || !in_array($previousSignificantToken, ['{', ';'], true)
        ) {
            return false;
        }

        $proofCallCount = 0;
        $tokens = token_get_all($source);

        foreach ($tokens as $tokenIndex => $token) {
            if (
                !is_array($token)
                || $token[0] !== T_STRING
                || $token[1] !== 'proveInstalledStructuredJsonSuccessEnvelopeDistribution'
            ) {
                continue;
            }

            $previousToken = null;

            for ($previousTokenIndex = $tokenIndex - 1; $previousTokenIndex >= 0; $previousTokenIndex--) {
                $candidateToken = $tokens[$previousTokenIndex];

                if (
                    is_array($candidateToken)
                    && in_array($candidateToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    continue;
                }

                $previousToken = $candidateToken;
                break;
            }

            if (is_array($previousToken) && $previousToken[0] === T_FUNCTION) {
                continue;
            }

            $nextToken = null;

            for ($nextTokenIndex = $tokenIndex + 1; $nextTokenIndex < count($tokens); $nextTokenIndex++) {
                $candidateToken = $tokens[$nextTokenIndex];

                if (
                    is_array($candidateToken)
                    && in_array($candidateToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    continue;
                }

                $nextToken = $candidateToken;
                break;
            }

            if ($nextToken === '(') {
                $proofCallCount++;
            }
        }

        return $proofCallCount === 1;
    };

    if (!is_string($installedStructuredJsonProofSource)) {
        $failures[] = 'Cannot read the installed structured JSON and nested-resource proof harness.';
    } else {
        $mutatedStructuredJsonProofSources = [
            str_replace(
                $installedStructuredJsonProofWiring,
                '',
                $installedStructuredJsonProofSource,
            ),
            str_replace(
                $installedStructuredJsonProofWiring,
                "    if (false) {\n{$installedStructuredJsonProofWiring}\n    }",
                $installedStructuredJsonProofSource,
            ),
            str_replace(
                $installedStructuredJsonProofWiring,
                "    if (false)\n{$installedStructuredJsonProofWiring}",
                $installedStructuredJsonProofSource,
            ),
        ];
        $mutationWasAccepted = false;

        foreach ($mutatedStructuredJsonProofSources as $mutatedStructuredJsonProofSource) {
            if ($installedStructuredJsonProofWiringIsCanonical($mutatedStructuredJsonProofSource)) {
                $mutationWasAccepted = true;
                break;
            }
        }

        if (
            !$installedStructuredJsonProofWiringIsCanonical($installedStructuredJsonProofSource)
            || $mutationWasAccepted
        ) {
            $failures[] = 'The installed structured JSON and nested-resource runtime proof must complete exactly once in the unconditional top-level consumer proof before function declarations.';
        }
    }

    forbidGuardrailArtifactMarkers(
        $root,
        [
            'tools/test-consumer-project.php' => [
                'decodeListDocumentsSuccess(Response $response, string $expectedOrder)',
                'filter_var($nextAfterUserId, FILTER_VALIDATE_INT)',
                'ListDocuments expected order is incompatible.',
                "preg_match(\n            '/^v1:(rank_asc|rank_desc):",
            ],
        ],
        'opaque installed structured JSON continuation proof',
        $failures,
    );

    $forbiddenStructuredJsonRuntimePathPattern = '/(?:\A|\/)(?:[A-Za-z0-9]*(?:Envelope|Wrapper|Serializer|Resource|ResourceCollection|Paginator|Pagination)|[A-Za-z0-9]*JsonResponse|(?:Response|Json|Resource|Success|Pagination)[A-Za-z0-9]*(?:Builder|Factory|Responder|Formatter|Transformer|Middleware|Facade|Helper|Reflection|Discovery)|(?:Response|Json|Resource|Success|Pagination)\/(?:Builder|Factory|Responder|Formatter|Transformer|Middleware|Facade|Helper|Reflection|Discovery)|[A-Za-z0-9]*(?:RelationshipLoader|RelationLoader|LazyRelationship|LazyRelation|EagerRelationship|EagerRelation|BatchLoader|DataLoader)|(?:Relationships?|Relations?)\/(?:Loader|Resolver|Mapper|Batcher)|[A-Za-z0-9]*(?:SchemaGenerator|ClientGenerator|SdkGenerator)|(?:OpenApi|JsonSchema)[A-Za-z0-9]*)(?:\.php|\/)/i';
    $structuredJsonForbiddenRuntimePathFixtures = [
        'src/Http/ResponseEnvelope.php',
        'src/Http/ResponseWrapper.php',
        'src/Http/JsonEnvelope.php',
        'src/Http/JsonResponse.php',
        'src/Http/JsonSerializer.php',
        'src/Http/UserResource.php',
        'src/Http/ResourceCollection.php',
        'src/Http/ResourceTransformer.php',
        'src/Http/Paginator.php',
        'src/Http/Pagination/Page.php',
        'src/Http/PaginationMiddleware.php',
        'src/Http/ResponseMiddleware.php',
        'src/Http/ResponseBuilder.php',
        'src/Http/ResponseFactory.php',
        'src/Http/ResponseHelper.php',
        'src/Http/JsonResponder.php',
        'src/Http/ResponseFormatter.php',
        'src/Http/JsonDiscovery.php',
        'src/Http/Response/Builder.php',
        'src/Http/Response/Factory.php',
        'src/Http/Response/Helper.php',
        'src/Http/Response/Middleware.php',
        'src/Http/Json/Responder.php',
        'src/Http/Resource/Transformer.php',
        'src/Http/Json/Discovery.php',
        'src/Database/WorkspaceCreatorRelationshipLoader.php',
        'src/Database/RelationLoader.php',
        'src/Database/LazyRelationship.php',
        'src/Database/CreatorBatchLoader.php',
        'src/Database/Relationships/Loader.php',
        'src/Http/SchemaGenerator.php',
        'src/Http/ClientGenerator.php',
    ];
    $structuredJsonAllowedRuntimePathFixtures = [
        'src/Http/Request.php',
        'src/Http/Response.php',
        'src/Database/Connection.php',
        'src/Database/QueryBudget.php',
    ];

    foreach ($structuredJsonForbiddenRuntimePathFixtures as $structuredJsonForbiddenRuntimePathFixture) {
        if (preg_match($forbiddenStructuredJsonRuntimePathPattern, $structuredJsonForbiddenRuntimePathFixture) !== 1) {
            $failures[] = "Structured JSON runtime-path detector missed forbidden fixture: {$structuredJsonForbiddenRuntimePathFixture}.";
        }
    }

    foreach ($structuredJsonAllowedRuntimePathFixtures as $structuredJsonAllowedRuntimePathFixture) {
        if (preg_match($forbiddenStructuredJsonRuntimePathPattern, $structuredJsonAllowedRuntimePathFixture) === 1) {
            $failures[] = "Structured JSON runtime-path detector rejected allowed fixture: {$structuredJsonAllowedRuntimePathFixture}.";
        }
    }

    $structuredJsonSourceRoots = [
        $root . '/src' => 'framework source',
        $root . '/skeleton/src' => 'default-skeleton source',
    ];

    foreach ($structuredJsonSourceRoots as $structuredJsonSourceRoot => $structuredJsonSourceOwner) {
        $structuredJsonSourceFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($structuredJsonSourceRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($structuredJsonSourceFiles as $structuredJsonSourceFile) {
            if (!$structuredJsonSourceFile instanceof SplFileInfo || !$structuredJsonSourceFile->isFile()) {
                continue;
            }

            $relativePath = substr($structuredJsonSourceFile->getPathname(), strlen($root) + 1);

            if (preg_match($forbiddenStructuredJsonRuntimePathPattern, $relativePath) === 1) {
                $failures[] = "Structured JSON response runtime mechanism must remain outside {$structuredJsonSourceOwner}: {$relativePath}.";
            }
        }
    }

    $structuredJsonPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($structuredJsonPackageInventory)) {
        $structuredJsonPackagePaths = preg_split('/\R/', $structuredJsonPackageInventory);

        if (is_array($structuredJsonPackagePaths)) {
            foreach ($structuredJsonPackagePaths as $structuredJsonPackagePath) {
                if (
                    str_starts_with($structuredJsonPackagePath, 'src/')
                    && preg_match(
                        $forbiddenStructuredJsonRuntimePathPattern,
                        $structuredJsonPackagePath,
                    ) === 1
                ) {
                    $failures[] = "Structured JSON response runtime mechanism must remain outside the framework package API: {$structuredJsonPackagePath}.";
                }
            }
        }
    }

    $transactionalEmailGuidanceArtifactMarkers = [
        '.ai/README.md' => [
            '| Change email guidance or application email context | `.ai/application-context.md` | `docs/email.md`, task routes, integration context, package inventory, focused guardrails, and installed-consumer evidence |',
        ],
        '.ai/application-context.md' => [
            '`docs/email.md`',
            'Keep transactional email composition and delivery application-owned.',
            'Require `.ai/integrations.md` to record `NOT_APPLICABLE(EMAIL)`',
            'Route adoption through installed `vendor/phpthis/framework/docs/email.md`',
            'do not add another always-read context file or template a framework mailer, renderer, notification system, queue, worker, webhook receiver, provider, or runtime dependency.',
        ],
        'docs/email.md' => [
            '# Application-owned transactional email',
            'PHPThis provides no mail transport or email-rendering runtime.',
            'The checked `user.welcome` example is not an email implementation.',
            '`RecordUserWelcomeDelivery` records one idempotent database effect',
            'Email HTML is a distinct MIME output sink.',
            'replace `NOT_APPLICABLE(EMAIL)` in application `.ai/integrations.md` with a reviewed adoption record.',
            'Separate deterministic message composition from transport and provider I/O.',
            'one operation-specific final readonly message or view model containing already validated values.',
            'Select a template only through a finite code-owned choice',
            'Template execution performs no database or network I/O, filesystem discovery, service lookup, session or environment access, mutable-global access, or dynamic code execution.',
            'Every message has an intentionally authored UTF-8 `text/plain` body.',
            'authoritative plain text must not be produced by naively stripping HTML.',
            'the renderer\'s finite public or operational failure mapping without template values or exception details;',
            'template input cardinality, execution timeout or other effective execution bound, and maximum rendered bytes;',
            'A template package is optional.',
            '## Encode at each output context',
            'every deliberate raw-output boundary is finite, code-owned, named, reviewed, and tested with adversarial values.',
            'Build absolute reviewed HTTPS links from typed canonical application configuration.',
            'Never derive their origin, scheme, authority, or base path from an untrusted `Host`, `Forwarded`, or `X-Forwarded-*` request header.',
            'Do not use native `mail()` or hand-build MIME boundaries, header folding, address encoding, or transfer encoding.',
            'Keep these identities distinct:',
            'Reject CR, LF, NUL, and the selected package\'s other invalid control characters in every value that can reach a header.',
            'Never accept a user-selected header name, raw header line, MIME part type, transfer encoding, or boundary.',
            'Record and enforce limits for recipient count, header count and bytes, each text and HTML body, attachment count and bytes, inline-image count and bytes, and total encoded message bytes.',
            '## One explicit transport boundary',
            'the exact package and version, provider and API or SMTP contract version, and supported feature subset;',
            'separate finite connect, operation, and total timeouts;',
            'dependency and contract update cadence plus redacted behavior for every known and unknown failure.',
            'Provider acceptance means only that the selected provider accepted a request under its contract.',
            'Permit synchronous sending only after the application explicitly accepts and tests its latency, timeout, provider-outage, client-disconnect, process-termination, duplicate-submission, and public-failure consequences',
            'An external call does not belong inside the business database transaction.',
            'When delivery must survive request or process termination, prefer a durable deferred intent',
            'The current `jobs:run-one` example remains a one-delivery application console operation;',
            'Preserve at-least-once semantics.',
            'one bounded business-event idempotency key distinct from recipient-controlled input;',
            'provider idempotency support and key scope, retention window, collision policy, and unsupported cases;',
            'durable internal request identity and any provider request, message, and receipt identifiers;',
            'ambiguous-timeout behavior when the provider may have accepted a request but the application received no conclusive response;',
            'finite attempt count and code-owned backoff, with retryable and terminal classifications;',
            'redacted dead-letter inspection and retention;',
            'authoritative reconciliation inputs, cadence, timeout, and unavailable-provider behavior;',
            'compensation policy when the external effect cannot be reversed;',
            'the identity authorized to perform an operator replay and the checks that preserve idempotency and audit evidence.',
            'Treat bounce, complaint, suppression, unsubscribe, and delivery-status webhooks as separate external integrations.',
            'SPF, DKIM, DMARC, sender and domain verification, consent, unsubscribe and legal policy',
            'Keep credentials, recipient data, message bodies, rendered HTML and text, link or action tokens, provider responses, exception details, and webhook payloads out of default logs and durable diagnostic codes.',
            'Composition tests cover address and header injection, finite template selection, every output-context encoder, every deliberate raw boundary, intentional text and HTML semantic parity, absolute HTTPS links and token encoding, supported locales, recipient and byte limits, attachments and inline images when adopted, renderer failures, and deterministic semantic composition.',
            'Inspect composed messages semantically through the selected mail or MIME package.',
            'Transport tests cover success, provider rejection, authentication failure, rate limiting, retryable and terminal failures, ambiguous timeout, provider-idempotent retry, redaction, reconciliation, and dead-letter behavior without contacting production.',
            'Prove that synchronous failure follows its recorded public contract and that deferred failure follows its recorded at-least-once lifecycle.',
            'Use only a local fake, captured transport, or explicitly approved provider sandbox for integration evidence.',
            '## Unsupported framework boundary',
            'Adopting email changes no Consumer Contract or Strict Profile requirement.',
        ],
        'docs/knowledge-map.md' => [
            '| Compose, deliver, or review transactional email | `docs/email.md`;',
            'verify that the welcome-delivery example remains a database-effect proof and that no framework mailer, renderer, queue, worker, or webhook receiver was implied',
        ],
        'skeleton/.ai/README.md' => [
            '| Compose or deliver transactional email | installed `vendor/phpthis/framework/docs/email.md` | `.ai/integrations.md` and the operation-specific composer and transport; add configuration, jobs, operations, and testing context only when entered |',
        ],
        'skeleton/.ai/integrations.md' => [
            '`NOT_APPLICABLE`: the starter application contacts no external services and performs no external side effects.',
            '`NOT_APPLICABLE(EMAIL)`',
            'Before adoption, read installed `vendor/phpthis/framework/docs/email.md`',
            'Do not add a framework mailer, renderer, notification system, queue, worker, or webhook receiver.',
        ],
        'templates/application/.ai/README.md' => [
            '| Compose or deliver transactional email | installed `vendor/phpthis/framework/docs/email.md` | `.ai/integrations.md` and the operation-specific composer and transport; add configuration, jobs, operations, and testing context only when entered |',
        ],
        'templates/application/.ai/integrations.md' => [
            '## Transactional email boundary',
            'Adoption or `NOT_APPLICABLE(EMAIL)`',
            '{{EMAIL_COMPOSITION_BOUNDARY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_PACKAGE_CONTRACT_OR_NOT_APPLICABLE}}',
            '{{EMAIL_RENDERING_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_OUTPUT_AND_ADDRESS_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_RESOURCE_BOUNDS_OR_NOT_APPLICABLE}}',
            '{{EMAIL_TRANSPORT_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_DELIVERY_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_WEBHOOK_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_OPERATIONS_AND_EVIDENCE_OR_NOT_APPLICABLE}}',
            'PHPThis provides no mail transport or email-rendering runtime;',
            'Do not use native `mail()`, hand-build MIME, or add a framework mailer, renderer, notification system, queue, worker, webhook receiver, provider, or hidden retry.',
        ],
        'tools/package-files.txt' => [
            'docs/email.md',
        ],
        'tools/guardrails/repository.php' => [
            "'docs/email.md',",
        ],
        'tools/guardrails/distribution.php' => [
            'Framework runtime dependencies must remain native PHP and extensions:',
            'The default skeleton must require only PHP and phpthis/framework.',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledTransactionalEmailGuidanceDistribution($project, $installedFramework);',
            'function proveInstalledTransactionalEmailGuidanceDistribution(',
            'PASS installed application-owned transactional email guidance distribution',
        ],
        'docs/guardrails.md' => [
            'transactional-email guidance, installed task and integration routes, exact package inventory, and Composer dependency checks keep composition, MIME, rendering, delivery, provider operations, and evidence application-owned',
            'without adding a framework mailer, renderer, notification system, queue, worker, webhook receiver, or runtime dependency',
            'The transactional-email guidance guard pins the dedicated installed guide',
            'It adds no framework mailer, renderer, notification system, provider adapter, queue, worker, webhook receiver, runtime dependency, consumer-checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $transactionalEmailGuidanceArtifactMarkers,
        'application-owned transactional email guidance',
        $failures,
    );

    $forbiddenTransactionalEmailRuntimePathPattern = '/(?:email|mail(?:er)?|mime|smtp|notifications?)/i';
    $transactionalEmailFrameworkSourceFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($transactionalEmailFrameworkSourceFiles as $transactionalEmailFrameworkSourceFile) {
        if (
            !$transactionalEmailFrameworkSourceFile instanceof SplFileInfo
            || !$transactionalEmailFrameworkSourceFile->isFile()
        ) {
            continue;
        }

        $relativePath = substr($transactionalEmailFrameworkSourceFile->getPathname(), strlen($root) + 1);

        if (preg_match($forbiddenTransactionalEmailRuntimePathPattern, $relativePath) === 1) {
            $failures[] = "Transactional-email runtime mechanism must remain outside framework source: {$relativePath}.";
        }
    }

    $transactionalEmailPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($transactionalEmailPackageInventory)) {
        $transactionalEmailPackagePaths = preg_split('/\R/', $transactionalEmailPackageInventory);

        if (is_array($transactionalEmailPackagePaths)) {
            foreach ($transactionalEmailPackagePaths as $transactionalEmailPackagePath) {
                if (
                    str_starts_with($transactionalEmailPackagePath, 'src/')
                    && preg_match(
                        $forbiddenTransactionalEmailRuntimePathPattern,
                        $transactionalEmailPackagePath,
                    ) === 1
                ) {
                    $failures[] = "Transactional-email runtime mechanism must remain outside the framework package API: {$transactionalEmailPackagePath}.";
                }
            }
        }
    }

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

/** @return list<string> */
function statelessAuthenticationGuidanceFailures(string $root): array
{
    $failures = [];
    $artifactMarkers = [
        '.ai/README.md' => [
            '| Change authentication, stateless Bearer/JWT/PAT/external-IdP policy, tenant resolution, or authorization | `.ai/request-policy.md` | `docs/stateless-authentication.md`, action-specific policy path, protected work, and denial tests |',
        ],
        '.ai/application-context.md' => [
            'route adoption through installed `vendor/phpthis/framework/docs/stateless-authentication.md` plus the application\'s `.ai/request-policy.md`.',
            'Require one strict Bearer header over TLS with no query, body, cookie, path, alternate-header, or fallback source',
        ],
        '.ai/request-policy.md' => [
            'For Bearer, JWT, opaque/PAT/API-token, or external-identity-provider adoption, also follow `docs/stateless-authentication.md`',
            'Treat PHPThis as Bearer-ready only at the bounded lowercase `authorization` header seam.',
            'Accept one strict Bearer credential over TLS with no query, body, cookie, path, alternate-header, or fallback credential source.',
            'Test Bearers are synthetic only and are never production credential evidence.',
            'explicitly non-RFC-6750-compatible',
        ],
        'docs/stateless-authentication.md' => [
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
        ],
        'docs/knowledge-map.md' => [
            '| Add, explain, or review stateless Bearer, JWT, opaque/PAT/API-token, external-provider authentication, tenant resolution, or authorization | `docs/stateless-authentication.md`, `docs/request-policy.md`, `docs/security.md`, `docs/errors.md`, `docs/decisions/020-application-owned-request-policy.md` |',
            'verify that PHPThis adds no JWT, PAT, OAuth, identity-provider, or authentication runtime/API',
        ],
        'docs/request-policy.md' => [
            '[Application-owned stateless authentication](stateless-authentication.md)',
            '`WWW-Authenticate: Bearer` is response semantics, not token support.',
            'accepts one strict Bearer header over TLS with no alternate credential source',
            'explicitly not an RFC-6750-compatible challenge and error profile',
        ],
        'docs/security.md' => [
            'PHPThis supplies no credential parser, verifier, issuer, revoker, identity provider, or authentication runtime/API.',
            'A JWT profile owns RFC 8725\'s fixed algorithm and key binding',
            'that bare challenge and generic error policy are deliberately disclosure-minimizing and non-RFC-6750-compatible',
            'Selected external key retrieval or RFC 7662 introspection owns authenticated TLS I/O, bounds, timeouts, cache-staleness, outage, and fail-closed behavior.',
            '[Application-owned stateless authentication](stateless-authentication.md)',
        ],
        'skeleton/.ai/README.md' => [
            '| Change authentication, stateless Bearer/JWT/PAT/external-provider, tenant, or authorization policy | `.ai/request-policy.md` | installed `vendor/phpthis/framework/docs/stateless-authentication.md`, action-specific composition, protected work, lifecycle, and denial tests |',
        ],
        'skeleton/.ai/request-policy.md' => [
            '`NOT_APPLICABLE(REQUEST_POLICY)`',
            'read installed `vendor/phpthis/framework/docs/request-policy.md` and `vendor/phpthis/framework/docs/stateless-authentication.md`',
            'one strict TLS-protected `Authorization: Bearer` source with no alternate or fallback source',
            'selected JWT, opaque/PAT/API-token, or external-verification profile',
            'preserve the bare non-RFC-6750-compatible reference challenge',
        ],
        'templates/application/.ai/README.md' => [
            '| Change authentication, stateless Bearer/JWT/PAT/external-provider, tenant, or authorization policy | `.ai/request-policy.md` | installed `vendor/phpthis/framework/docs/stateless-authentication.md`, action-specific composition, protected work, lifecycle, and denial tests |',
        ],
        'templates/application/.ai/request-policy.md' => [
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
        'tools/package-files.txt' => [
            'docs/stateless-authentication.md',
        ],
        'tools/guardrails/distribution.php' => [
            'Framework runtime dependencies must remain native PHP and extensions:',
            'The default skeleton must require only PHP and phpthis/framework.',
        ],
        'tools/guardrails/boundaries.php' => [
            'Stateless authentication dependency detector fixture must fail:',
            'roave/security-advisories',
            'src/Identity/Auth0Client.php',
            'src/Security/ClaimsVerifier.php',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledStatelessAuthenticationGuidanceDistribution($project, $installedFramework);',
            'function proveInstalledStatelessAuthenticationGuidanceDistribution(',
            'Installed stateless authentication dependency detector fixture must fail:',
            'roave/security-advisories',
            'ClaimsVerifier',
            'PASS installed stateless authentication guidance distribution',
        ],
        'docs/guardrails.md' => [
            'stateless-authentication guidance, source and installed routes, exact package inventory, Composer dependency checks, and runtime-API path and identifier checks preserve application-owned JWT, PAT, OAuth, and external-provider choices',
            'The stateless-authentication guidance guard pins the dedicated installed guide',
            'It adds no core API, runtime or development authentication dependency, Consumer Contract or Strict Profile change, checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $artifactMarkers,
        'stateless authentication guidance',
        $failures,
    );

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
        if (!statelessAuthenticationPackageIsForbidden($package)) {
            $failures[] = "Stateless authentication dependency detector fixture must fail: {$package}.";
        }
    }

    foreach ($allowedPackageFixtures as $package) {
        if (statelessAuthenticationPackageIsForbidden($package)) {
            $failures[] = "Stateless authentication dependency detector fixture must remain allowed: {$package}.";
        }
    }

    $forbiddenRuntimePathFixtures = [
        'src/Auth/BearerAuthenticator.php',
        'src/Auth/AuthManager.php',
        'src/Identity/Auth0Client.php',
        'src/Identity/IdentityProvider.php',
        'src/Security/AccessToken.php',
        'src/Security/ApiTokenVerifier.php',
        'src/Security/ClaimsVerifier.php',
        'src/Security/JwtVerifier.php',
        'src/Security/OpaqueTokenStore.php',
        'src/Security/PatVerifier.php',
        'src/Security/PersonalAccessTokenIssuer.php',
    ];
    $allowedRuntimePathFixtures = [
        'src/Database/QueryTrace.php',
        'src/Http/RequestReader.php',
        'src/Routing/RouteParameterType.php',
        'src/Session/SessionLifecycle.php',
    ];

    foreach ($forbiddenRuntimePathFixtures as $relativePath) {
        if (!statelessAuthenticationRuntimeApiPathIsForbidden($relativePath)) {
            $failures[] = "Stateless authentication runtime/API detector fixture must fail: {$relativePath}.";
        }
    }

    foreach ($allowedRuntimePathFixtures as $relativePath) {
        if (statelessAuthenticationRuntimeApiPathIsForbidden($relativePath)) {
            $failures[] = "Stateless authentication runtime/API detector fixture must remain allowed: {$relativePath}.";
        }
    }

    foreach (['composer.json', 'skeleton/composer.json'] as $relativeComposerPath) {
        $composerContents = file_get_contents($root . '/' . $relativeComposerPath);
        $composer = is_string($composerContents) ? json_decode($composerContents, true) : null;

        if (!is_array($composer)) {
            $failures[] = "Cannot decode {$relativeComposerPath} for the stateless authentication dependency boundary.";
            continue;
        }

        foreach (['require', 'require-dev'] as $dependencySection) {
            $dependencies = $composer[$dependencySection] ?? null;

            if (!is_array($dependencies)) {
                $failures[] = "{$relativeComposerPath}:{$dependencySection} must remain an explicit Composer map.";
                continue;
            }

            foreach (array_keys($dependencies) as $dependency) {
                if (
                    is_string($dependency)
                    && statelessAuthenticationPackageIsForbidden($dependency)
                ) {
                    $failures[] = "Authentication package {$dependency} must remain application-owned and absent from {$relativeComposerPath}:{$dependencySection}.";
                }
            }
        }
    }

    foreach (
        [
            'src' => 'framework',
            'skeleton/src' => 'default skeleton',
        ] as $relativeSourceRoot => $surface
    ) {
        foreach (statelessAuthenticationRuntimeApiFailures($root, $relativeSourceRoot, $surface) as $failure) {
            $failures[] = $failure;
        }
    }

    return $failures;
}

function statelessAuthenticationPackageIsForbidden(string $package): bool
{
    $normalized = strtolower($package);

    return preg_match(
        '~(?:^|[/._-])(?:access[-_]?token|api[-_]?token|auth[0-9]*|authn|authz|authentication|authorization|bearer|credential|identity[-_]?provider|jose|jwe|jwk|jws|jwt|oauth2?|oidc|openid|opaque[-_]?token|paseto|pat|passport|personal[-_]?access[-_]?token|sanctum)(?:$|[/._-])~i',
        $package,
    ) === 1
        || str_starts_with($normalized, 'symfony/security-');
}

/** @return list<string> */
function statelessAuthenticationRuntimeApiFailures(
    string $root,
    string $relativeSourceRoot,
    string $surface,
): array {
    $sourceRoot = $root . '/' . $relativeSourceRoot;

    if (!is_dir($sourceRoot) || is_link($sourceRoot)) {
        return ["The stateless authentication {$surface} source boundary is unavailable: {$relativeSourceRoot}."];
    }

    $failures = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($root) + 1);

        if (statelessAuthenticationRuntimeApiPathIsForbidden($relativePath)) {
            $failures[] = "Authentication runtime/API path must remain outside {$surface} source: {$relativePath}.";
        }

        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (!is_string($contents)) {
            $failures[] = "Cannot read {$surface} source for the stateless authentication API boundary: {$relativePath}.";
            continue;
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
                if (statelessAuthenticationRuntimeApiIdentifierIsForbidden($identifier)) {
                    $failures[] = "Authentication runtime/API identifier {$identifier} must remain outside {$surface} source: {$relativePath}.";
                    continue 3;
                }
            }
        }
    }

    return $failures;
}

function statelessAuthenticationRuntimeApiPathIsForbidden(string $relativePath): bool
{
    foreach (explode('/', $relativePath) as $segment) {
        $name = str_ends_with(strtolower($segment), '.php')
            ? substr($segment, 0, -4)
            : $segment;

        if (statelessAuthenticationRuntimeApiIdentifierIsForbidden($name)) {
            return true;
        }
    }

    return false;
}

function statelessAuthenticationRuntimeApiIdentifierIsForbidden(string $identifier): bool
{
    if (
        preg_match('/claims(?:parser|validator|verifier)/i', $identifier) === 1
        || preg_match('/(?:\A|[a-z0-9])(?:Auth|PAT|Pat)(?:[A-Z]|\z)/', $identifier) === 1
    ) {
        return true;
    }

    return preg_match(
        '/(?:accesstoken|apitoken|auth[0-9]+|auth(?:enticate|enticated|entication|enticator|orize|orization|orizer)|bearer|credential(?:introspector|parser|refresher|repository|revoker|service|store|validator|verifier|issuer)?|identityprovider|jose|jwe|jwk|jws|jwt|oauth|oidc|openid|opaquetoken|paseto|personalaccesstoken|token(?:introspector|parser|refresher|repository|revoker|service|store|validator|verifier|issuer))/i',
        $identifier,
    ) === 1;
}
