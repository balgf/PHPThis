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

    $releaseClaimPattern = '/\b' . $releaseSubject . '\s+' . $publicationPredicate . '\b/';
    $releaseClaims = [];

    if (preg_match_all($releaseClaimPattern, $normalizedContents, $releaseClaims, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($releaseClaims[0] as $releaseClaim) {
            $claimOffset = $releaseClaim[1];
            $claimPrefix = substr($normalizedContents, 0, $claimOffset);

            if (preg_match('/(?:\bif|\bwhen|\bonce|\bafter|\bbefore|\bunless)\s*$/', $claimPrefix) === 1) {
                continue;
            }

            return 'normalized release publication claim';
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
        'README.md' => [
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
            'A report-only context-size or repeated-rule advisory was considered and rejected',
            'Do not add a context report script, `ApplicationChecker` rule, `PHT` diagnostic, or consumer-size validity gate',
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
            'Contract version: 11',
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
            'Consumer Contract version 11 carries Strict Profile version 3 forward unchanged.',
            "ADR 045's response/session runtime behavior remain contract behavior; they are not part of PHT007.",
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
            'proveInstalledSessionCleanupAndResponseFramingDistribution($project, $installedFramework);',
            'function proveInstalledSessionCleanupAndResponseFramingDistribution(',
            'PASS installed session cleanup and response framing distribution',
        ],
        'docs/guardrails.md' => [
            'ADR 045 used the remaining seven-line margin for its bounded session-cleanup failure and response-framing correction. Current unreleased source removes the redundant public-prerelease `PathParameters::onePositiveInteger()` convenience factory and occupies 2,595 lines. The five freed lines remain unallocated;',
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
        '.ai/application-context.md' => [
            'ADR 046 then consolidates four application-owned executable-example boundaries without changing that contract, profile, or framework runtime.',
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
            "['user' => ['id' => \$user->id->value, 'name' => \$user->name]]",
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
            '| Prepare or publish a release | `RELEASING.md` | approved scope, exact candidate commits, CI, packages, and public-install proof |',
        ],
        '.ai/application-context.md' => [
            'Both are unreleased work on `main`, create no next candidate identity or publication authority',
            'Release preparation, approved-candidate proof or publication, and exact-tag historical inspection follow their distinct routes in `RELEASING.md`.',
        ],
        '.ai/testing.md' => [
            'The Git export comparison requires a clean worktree',
            'Prerelease publication separately verifies the actual version-pinned Packagist-preferred dist because a local archive cannot prove hosting-provider output.',
        ],
        '.github/workflows/ci.yml' => [
            'name: PHP ${{ matrix.php }} validity',
            'name: PDO transport (SQLite 3.45.1, MySQL 8.4.11, PostgreSQL 17.10)',
            'run: composer check',
        ],
        'README.md' => [
            'That work is unreleased:',
            'does not yet have an approved next-release identity',
            'separates this unreleased state from a proposed candidate, an explicitly approved candidate, and immutable release history',
        ],
        'RELEASING.md' => [
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
            'Preparing a proposal, proving or publishing an approved candidate, and inspecting an older release are different tasks.',
            '## Version-neutral release gate',
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
        'ROADMAP.md' => [
            'A future release requires its own bounded scope, fresh identity, exact candidate evidence, and accountable-human authorization through `RELEASING.md`.',
        ],
        'SECURITY.md' => [
            'Any approved prerelease candidate may be announced only after its complete public-artifact gate in `RELEASING.md` passes.',
            'A partially published framework or skeleton remains unannounced until both packages and the clean public installation path are proved.',
            'This tracked policy does not record current publication state',
        ],
        'composer.json' => [
            '"test:consumer": "php tools/test-consumer-project.php"',
            '"@test:consumer"',
        ],
        'docs/getting-started.md' => [
            '## Prerelease boundary',
            'Current `main` may contain later unreleased work;',
            'nor an approved next candidate.',
            'Prerelease publication follows the complete version-neutral maintainer gate in `RELEASING.md`.',
            'A framework-only or skeleton-only publication is recorded as partial and is not announced as a complete release.',
        ],
        'docs/guardrails.md' => [
            'the reusable release gate keeps immutable Alpha 1 through Alpha 5 identity records separate from the version-neutral',
            'A separate installed distribution proof checks the version-neutral release guidance',
            'ordered local-proof-before-push, exact-CI, tag-creation-and-push',
            'discovers every current `docs/releases/*.md` note and rejects unqualified positive or negative live-publication claims',
            'performs no network request, tag operation, package-host write, release creation, or announcement',
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
            'docs/getting-started.md',
            'docs/guardrails.md',
            'docs/knowledge-map.md',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledReleaseGuidanceDistribution($installedFramework);',
            'function proveInstalledReleaseGuidanceDistribution(string $installedFramework): void',
            '$orderedReleaseMarkers = [',
            'Installed release guidance is missing or misorders marker:',
            "composer create-project --stability=alpha --prefer-dist phpthis/skeleton phpthis-release-proof 'APPROVED_SKELETON_VERSION'",
            'PASS installed version-neutral release guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $versionNeutralReleaseContractMarkers, 'version-neutral release', $failures);

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

    $currentConsumerContractVersionMarkers = [
        'docs/consumer-contract.md' => 'Contract version: 11',
        'docs/getting-started.md' => 'contract-version-11 Composer scripts',
        'skeleton/.ai/README.md' => 'Consumer Contract v11 and Strict Profile v3 remain mandatory.',
        'skeleton/.ai/rules.md' => 'These rules supplement installed PHPThis Consumer Contract v11 and Strict Profile v3',
        'templates/application/.ai/README.md' => 'Consumer Contract v11 and Strict Profile v3 remain mandatory.',
        'templates/application/.ai/rules.md' => 'These rules supplement installed PHPThis Consumer Contract v11 and Strict Profile v3',
    ];

    foreach ($currentConsumerContractVersionMarkers as $relativePath => $marker) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents) || !str_contains($contents, $marker)) {
            $failures[] = "The current Consumer Contract version marker is missing from {$relativePath}.";
        }
    }

    $configurationArtifactMarkers = [
        'docs/decisions/036-one-typed-application-configuration-boundary.md' => [
            'Status: accepted',
            'Consumer Contract version 10 and Strict Profile version 3 add permanent structural rule `PHT007`.',
            'No application or deployment configuration runtime or class enters framework `src/`, and no runtime dependency is added.',
            'Adopted migration or administrative configuration never falls back to runtime configuration.',
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
            'function runConfigurationProcess(',
            'function requireConfigurationOutputExcludes(',
            "'' => 'APP_RUNTIME_MODE='",
            "fifth `proc_open` environment argument",
            'It treats an empty-string array key as a raw environment entry',
            'This is pinned PHP 8.4 implementation behavior rather than a general environment-array convention',
            'absence of deliberate parent-configuration inheritance',
            'Do not grow this configuration example into a general process runner, worker, or supervisor.',
        ],
        'docs/consumer-contract.md' => [
            '## Application configuration',
            'PHT007',
            'For each adopted process profile, keep its runtime, worker, migration, or administrative input names, factories, and output types separate.',
            'non-secret configuration reference',
            'A configuration-free application records `NOT_APPLICABLE(CONFIGURATION)`',
        ],
        'docs/decisions/README.md' => [
            "`035-bounded-alpha-4-release-scope.md`\n- `036-one-typed-application-configuration-boundary.md`",
        ],
        'docs/strict-profile.md' => [
            'Profile version: 3',
            '`PHT007`',
            'one application-owned PHP file',
        ],
        'templates/application/.ai/configuration.md' => [
            '{{CONFIGURATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}',
            '{{CONFIGURATION_PROFILE_CREDENTIAL_SEPARATION_OR_NOT_APPLICABLE}}',
            '{{CONFIGURATION_REDACTION_EVIDENCE_OR_NOT_APPLICABLE}}',
        ],
        'skeleton/.ai/configuration.md' => [
            '`NOT_APPLICABLE(CONFIGURATION)`',
            'The health-only skeleton reads no process environment',
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
        ],
        'verification/ApplicationChecker.php' => [
            "'.ai/configuration.md'",
            'EnvironmentAccessProfile::inspect(',
            'EnvironmentAccessProfile::boundaryFailures($environmentReads)',
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
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledTypedConfiguration($project, $profileCommand, $environment);',
            'proveInstalledConfigurationEvidenceReference(',
            'proveConfigurationContextIsRequired($project, $profileCommand, $environment);',
            'proveEnvironmentAccessIsRejected($project, $profileCommand, $environment);',
            "selectOneRow('SELECT 1 AS configured')",
            'requireExactProcessResult(',
            'requireExactFailureLines(',
            'PASS installed runtime typed configuration delivery',
            'PASS installed migration typed configuration delivery',
            'The installed configuration evidence reference is missing.',
            'PASS child-process configuration evidence',
            'PASS installed empty configuration delivery',
            'environmentWithEmptyValue(',
            'final class ReferenceEmptyRuntimeMode extends InvalidArgumentException',
            'catch (ReferenceEmptyRuntimeMode)',
            'The installed missing runtime mode was misclassified as empty.',
        ],
        'docs/guardrails.md' => [
            'extracts the exact application-owned child-process reference from installed `docs/configuration.md`',
            "PHP 8.4's empty-string-key raw `NAME=` environment-entry form",
            'invokes the matching factory and proves that this raw form reaches its exact empty-value validation branch',
            'a paired run with the same variable omitted proves that the missing-value branch remains distinct',
            'an explicit synthetic application environment through the fifth `proc_open` argument instead of null inheritance',
            'explicit binary pipe descriptors and working directory',
            'the application test runner or CI job owns its hard outer timeout',
            'does not claim that the host, executable, or PHP runtime adds no required environment entries',
            'does not prove application-specific validation, deployment safety, or redaction outside the captured streams',
        ],
        'tools/package-files.txt' => [
            'docs/configuration.md',
            'docs/decisions/036-one-typed-application-configuration-boundary.md',
            'templates/application/.ai/configuration.md',
            'verification/EnvironmentAccessProfile.php',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $configurationArtifactMarkers, 'configuration-boundary', $failures);

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
            'new PDO($dsn, $username, $password, $defaults + $options),',
        ],
        'docs/configuration.md' => [
            '### Eager composition and probe semantics',
            '`Connection::connect()` constructs native `PDO` immediately rather than returning a deferred handle.',
            'Depending on the selected driver and DSN, construction may perform database, filesystem, or network I/O and may fail during composition.',
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
        '.ai/database.md' => [
            'Each adopted runtime, migration, or administrative factory uses a distinct name and never falls back.',
            'configuration-only scope records connection composition as deferred.',
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
            'Configuration, connectivity, target existence, migration success, PHT006, tenant predicates, and adversarial bindings are not universal authority',
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
            'Structural namespace/control model: SQLite\'s default `main` attachment namespace exists only inside each in-memory proof connection;',
            'no live authority probe runs.',
            'PASS installed database-context connection consistency',
            'PASS installed database authority lifecycle guidance distribution',
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
        'docs/decisions/018-bounded-alpha-1-release-scope.md',
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md',
        'docs/decisions/031-bounded-alpha-3-release-scope.md',
        'docs/decisions/035-bounded-alpha-4-release-scope.md',
        'docs/decisions/040-bounded-alpha-5-release-scope.md',
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
        'Alpha 5 was published on an observed historical date.' => false,
        'If Alpha 6 is published, record the observed timestamp.' => false,
        'When v0.2.0-alpha.1 is available, prove the installed artifact.' => false,
        'Unless Alpha 6 is not published, stop.' => false,
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
        'RELEASING.md' => 'Publication state is external',
        'ROADMAP.md' => 'Its live GitHub, Packagist, installation, and announcement state remains external',
        'SECURITY.md' => 'This tracked policy does not record current publication state',
        'docs/getting-started.md' => 'Package availability is an external fact',
        'docs/releases/0.1.0-alpha.1.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.2.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.3.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.4.md' => 'Publication state is external',
        'docs/releases/0.1.0-alpha.5.md' => 'Publication state is external',
        'docs/decisions/018-bounded-alpha-1-release-scope.md' => 'This decision does not record mutable publication state',
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => 'not mutable tag, package, GitHub release, or installation availability',
        'docs/decisions/031-bounded-alpha-3-release-scope.md' => 'Publication state is external',
        'docs/decisions/035-bounded-alpha-4-release-scope.md' => 'Publication state is external',
        'docs/decisions/040-bounded-alpha-5-release-scope.md' => 'Publication state is external',
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
            'composer create-project --stability=alpha phpthis/skeleton my-app',
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
            '## Start from the published skeleton',
            'composer create-project --stability=alpha phpthis/skeleton my-app',
            'Consumers do not clone or copy the PHPThis framework repository.',
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

    return $failures;
}
