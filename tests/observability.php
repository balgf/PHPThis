<?php

declare(strict_types=1);

use Example\Observability\CorrelationId;
use Example\Observability\ErrorLogRequestSummarySink;
use Example\Observability\QuerySummarySource;
use Example\Observability\RequestSummary;
use Example\Observability\RequestSummarySink;
use Example\Observability\TerminalRequestCoordinator;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

final class ObservabilityUnauthenticated extends RuntimeException
{
}

final class ObservabilityForbidden extends RuntimeException
{
}

final readonly class ObservabilityTestHandler implements RequestHandler
{
    /** @param Closure(Request): Response $behavior */
    public function __construct(private Closure $behavior)
    {
    }

    public function handle(Request $request): Response
    {
        return ($this->behavior)($request);
    }
}

/** @phpstan-import-type RequestSummaryPayload from RequestSummary */
final class CapturingRequestSummarySink implements RequestSummarySink
{
    public int $attempts = 0;

    /** @var list<RequestSummary> */
    public array $summaries = [];

    public function emit(RequestSummary $summary): void
    {
        $this->attempts++;
        $this->summaries[] = $summary;
    }

    /** @return RequestSummaryPayload */
    public function onlyPayload(): array
    {
        return $this->onlySummary()->toArray();
    }

    public function onlySummary(): RequestSummary
    {
        if ($this->attempts !== 1 || count($this->summaries) !== 1) {
            throw new RuntimeException('Expected exactly one request-summary sink invocation.');
        }

        return $this->summaries[0];
    }
}

final class ThrowingRequestSummarySink implements RequestSummarySink
{
    public int $attempts = 0;

    public function emit(RequestSummary $summary): void
    {
        $this->attempts++;
        throw new RuntimeException('SinkPrivateMarker');
    }
}

/** @return array<string, Closure(): void> */
function observabilityTests(): array
{
    return [
        'correlation IDs are generated with 128 random bits in canonical form' => static function (): void {
            $seen = [];

            for ($index = 0; $index < 32; $index++) {
                $generated = CorrelationId::generate();

                if (
                    preg_match('/\A[a-f0-9]{32}\z/D', $generated->value) !== 1
                    || isset($seen[$generated->value])
                ) {
                    throw new RuntimeException('Expected fresh canonical generated correlation IDs.');
                }

                $seen[$generated->value] = true;
            }
        },
        'terminal coordinator emits one success summary and owns the response request ID' => static function (): void {
            $correlationId = CorrelationId::generate();
            $sink = new CapturingRequestSummarySink();
            $handler = new ObservabilityTestHandler(
                static fn (Request $request): Response => new Response(
                    200,
                    [
                        'Content-Type' => 'text/plain; charset=utf-8',
                        'x-request-id' => 'UntrustedResponseIdMarker',
                        'X-REQUEST-ID' => 'SecondUntrustedResponseIdMarker',
                    ],
                    "ok\n",
                ),
            );
            $coordinator = observabilityCoordinator($handler, $correlationId, $sink);
            $response = $coordinator->handle(
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
                [],
            );
            $payload = $sink->onlyPayload();

            if (
                $response->status !== 200
                || $response->headers !== [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'X-Request-ID' => $correlationId->value,
                ]
                || observabilityRuntimeKeys($payload) !== [
                    'schema_version',
                    'event',
                    'correlation_id',
                    'duration_us',
                    'response_status',
                    'outcome',
                    'unknown_failure_class',
                    'query_count',
                    'query_failures',
                    'query_execute_duration_us',
                    'query_budget_exceeded',
                    'database_sources',
                ]
                || observabilityRuntimeValue($payload, 'schema_version') !== 1
                || observabilityRuntimeValue($payload, 'event') !== 'application.request_summary'
                || $payload['correlation_id'] !== $correlationId->value
                || $payload['response_status'] !== 200
                || $payload['outcome'] !== 'success'
                || $payload['unknown_failure_class'] !== null
                || $payload['query_count'] !== 0
                || $payload['query_failures'] !== 0
                || $payload['query_execute_duration_us'] !== 0
                || $payload['query_budget_exceeded']
                || $payload['database_sources'] !== []
                || $payload['duration_us'] < 0
                || str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'UntrustedResponseIdMarker')
                || str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'SecondUntrustedResponseIdMarker')
            ) {
                throw new RuntimeException('Expected one closed redacted success summary and one owned response ID.');
            }
        },
        'default error-log sink serializes exactly one closed request summary' => static function (): void {
            $logPath = tempnam(sys_get_temp_dir(), 'phpthis-request-summary-');
            $previousErrorLog = ini_get('error_log');

            if (!is_string($logPath) || !is_string($previousErrorLog)) {
                if (is_string($logPath) && is_file($logPath)) {
                    unlink($logPath);
                }

                throw new RuntimeException('Unable to create the request-summary sink fixture.');
            }

            if (ini_set('error_log', $logPath) === false) {
                if (is_file($logPath)) {
                    unlink($logPath);
                }

                throw new RuntimeException('Unable to redirect the request-summary sink fixture.');
            }

            $correlationId = CorrelationId::generate();
            $response = null;
            $log = null;

            try {
                $response = observabilityCoordinator(
                    new ObservabilityTestHandler(
                        static fn (Request $request): Response => new Response(
                            202,
                            ['X-Sink-Marker' => 'SinkHeaderPrivateMarker'],
                            "SinkBodyPrivateMarker\n",
                        ),
                    ),
                    $correlationId,
                    new ErrorLogRequestSummarySink(),
                )->handle([
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/SinkPathPrivateMarker',
                ], []);
                $log = file_get_contents($logPath);
            } finally {
                $restored = ini_set('error_log', $previousErrorLog) !== false;
                $removed = !is_file($logPath) || unlink($logPath);

                if (!$restored || !$removed) {
                    throw new RuntimeException('Unable to restore the request-summary sink fixture.');
                }
            }

            if (!is_string($log)) {
                throw new RuntimeException('Unable to read the request-summary sink fixture.');
            }

            $payload = observabilityDecodeErrorLogPayload($log);
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            if (
                $response->status !== 202
                || observabilityRuntimeKeys($payload) !== [
                    'schema_version',
                    'event',
                    'correlation_id',
                    'duration_us',
                    'response_status',
                    'outcome',
                    'unknown_failure_class',
                    'query_count',
                    'query_failures',
                    'query_execute_duration_us',
                    'query_budget_exceeded',
                    'database_sources',
                ]
                || observabilityRuntimeValue($payload, 'schema_version') !== 1
                || observabilityRuntimeValue($payload, 'event') !== 'application.request_summary'
                || observabilityRuntimeValue($payload, 'correlation_id') !== $correlationId->value
                || observabilityRuntimeValue($payload, 'response_status') !== 202
                || observabilityRuntimeValue($payload, 'outcome') !== 'success'
                || str_contains($encoded, 'SinkHeaderPrivateMarker')
                || str_contains($encoded, 'SinkBodyPrivateMarker')
                || str_contains($encoded, 'SinkPathPrivateMarker')
            ) {
                throw new RuntimeException('Expected one serialized closed request-summary event.');
            }
        },
        'terminal coordinator emits one status-only summary for every mapped or routed failure' => static function (): void {
            $ok = new ObservabilityTestHandler(
                static fn (Request $request): Response => new Response(
                    200,
                    ['Content-Type' => 'text/plain; charset=utf-8'],
                    "ok\n",
                ),
            );
            $unauthenticated = new ObservabilityTestHandler(
                static function (Request $request): Response {
                    throw new ObservabilityUnauthenticated('CredentialPrivateMarker');
                },
            );
            $forbidden = new ObservabilityTestHandler(
                static function (Request $request): Response {
                    throw new ObservabilityForbidden('TenantPrivateMarker');
                },
            );
            $application = new Application(new Router([
                new Route('GET', '/health', $ok),
                new Route('GET', '/unauthenticated', $unauthenticated),
                new Route('GET', '/forbidden', $forbidden),
            ]));
            $registry = new ErrorResponseRegistry([
                InvalidRequest::class => observabilityErrorResponse(400),
                ObservabilityUnauthenticated::class => observabilityErrorResponse(401),
                ObservabilityForbidden::class => observabilityErrorResponse(403),
            ]);
            $cases = [
                'invalid' => [[], 400],
                'unauthenticated' => [[
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/unauthenticated',
                    'HTTP_AUTHORIZATION' => 'Bearer CredentialPrivateMarker',
                ], 401],
                'forbidden' => [[
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/forbidden',
                ], 403],
                'missing' => [[
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/missing/ResourcePrivateMarker',
                ], 404],
                'not_allowed' => [[
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/health',
                ], 405],
            ];

            foreach ($cases as $case => [$server, $status]) {
                $sink = new CapturingRequestSummarySink();
                $correlationId = CorrelationId::generate();
                $coordinator = observabilityCoordinator(
                    $application,
                    $correlationId,
                    $sink,
                    [],
                    $registry,
                );
                $response = $coordinator->handle($server, []);
                $payload = $sink->onlyPayload();
                $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

                if (
                    $response->status !== $status
                    || ($response->headers['X-Request-ID'] ?? null) !== $correlationId->value
                    || $payload['correlation_id'] !== $correlationId->value
                    || $payload['response_status'] !== $status
                    || $payload['outcome'] !== 'known_failure'
                    || $payload['unknown_failure_class'] !== null
                    || str_contains($encoded, 'CredentialPrivateMarker')
                    || str_contains($encoded, 'TenantPrivateMarker')
                    || str_contains($encoded, 'ResourcePrivateMarker')
                ) {
                    throw new RuntimeException(sprintf(
                        'Expected one status-only terminal summary for case %s.',
                        $case,
                    ));
                }
            }
        },
        'terminal coordinator emits one class-only summary for an unknown failure' => static function (): void {
            $sink = new CapturingRequestSummarySink();
            $correlationId = CorrelationId::generate();
            $coordinator = observabilityCoordinator(
                new ObservabilityTestHandler(
                    static function (Request $request): Response {
                        throw new class('UnknownFailurePrivateMarker') extends RuntimeException {
                        };
                    },
                ),
                $correlationId,
                $sink,
            );
            $response = $coordinator->handle(
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/unknown/PathPrivateMarker'],
                [],
            );
            $payload = $sink->onlyPayload();
            $encoded = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );

            if (
                $response->status !== 500
                || ($response->headers['X-Request-ID'] ?? null) !== $correlationId->value
                || $payload['outcome'] !== 'unknown_failure'
                || $payload['unknown_failure_class'] !== RuntimeException::class
                || $payload['response_status'] !== 500
                || $payload['correlation_id'] !== $correlationId->value
                || str_contains($encoded, 'UnknownFailurePrivateMarker')
                || str_contains($encoded, 'PathPrivateMarker')
                || str_contains($encoded, '@anonymous')
                || str_contains($encoded, __FILE__)
            ) {
                throw new RuntimeException('Expected one redacted class-only unknown-failure summary.');
            }
        },
        'terminal coordinator reports repeated exact SQL without retaining SQL or bindings' => static function (): void {
            $budget = new QueryBudget(2);
            $trace = new QueryTrace(1);
            $connection = Connection::connect('sqlite::memory:', $budget, $trace);
            $handler = new ObservabilityTestHandler(
                static function (Request $request) use ($connection): Response {
                    $connection->selectOneRow('SELECT :value AS value', ['value' => 'FirstBindingMarker']);
                    $connection->selectOneRow('SELECT :value AS value', ['value' => 'SecondBindingMarker']);

                    return new Response(200, ['Content-Type' => 'text/plain'], "ok\n");
                },
            );
            $sink = new CapturingRequestSummarySink();
            $correlationId = CorrelationId::generate();
            $coordinator = observabilityCoordinator(
                $handler,
                $correlationId,
                $sink,
                [new QuerySummarySource('primary', $budget, $trace)],
            );
            $response = $coordinator->handle(
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/queries'],
                [],
            );
            $payload = $sink->onlyPayload();
            $source = $payload['database_sources'][0] ?? null;
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            if (
                $response->status !== 200
                || !is_array($source)
                || $payload['query_count'] !== 2
                || $payload['query_failures'] !== 0
                || $payload['query_budget_exceeded']
                || $source['budget_limit'] !== 2
                || $source['budget_used'] !== 2
                || $source['budget_exceeded']
                || $source['query_trace']['repeated_fingerprints'] !== 1
                || $source['query_trace']['maximum_executions_per_fingerprint'] !== 2
                || $source['query_trace']['queries'][0]['executions'] !== 2
                || str_contains($encoded, 'SELECT')
                || str_contains($encoded, 'value')
                || str_contains($encoded, 'FirstBindingMarker')
                || str_contains($encoded, 'SecondBindingMarker')
            ) {
                throw new RuntimeException('Expected deterministic redacted repeated-query evidence.');
            }
        },
        'terminal coordinator aggregates ordered sources failures and bounded trace truncation' => static function (): void {
            $firstBudget = new QueryBudget(2);
            $firstTrace = new QueryTrace(1);
            $firstConnection = Connection::connect('sqlite::memory:', $firstBudget, $firstTrace);
            $secondBudget = new QueryBudget(1);
            $secondTrace = new QueryTrace(1);
            $secondConnection = Connection::connect('sqlite::memory:', $secondBudget, $secondTrace);
            $handler = new ObservabilityTestHandler(
                static function (Request $request) use ($firstConnection, $secondConnection): Response {
                    $firstConnection->selectOneRow(
                        'SELECT :first_value AS first_value',
                        ['first_value' => 'FirstSourcePrivateMarker'],
                    );
                    $firstConnection->selectOneRow(
                        'SELECT :second_value AS second_value',
                        ['second_value' => 'TruncatedSourcePrivateMarker'],
                    );
                    $secondConnection->selectOneRow(
                        'SELECT missing_column FROM missing_table WHERE marker = :marker',
                        ['marker' => 'FailedSourcePrivateMarker'],
                    );

                    return new Response(200, [], "unreachable\n");
                },
            );
            $sink = new CapturingRequestSummarySink();
            $coordinator = observabilityCoordinator(
                $handler,
                CorrelationId::generate(),
                $sink,
                [
                    new QuerySummarySource('first', $firstBudget, $firstTrace),
                    new QuerySummarySource('second', $secondBudget, $secondTrace),
                ],
            );
            $response = $coordinator->handle(
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/multiple-sources'],
                [],
            );
            $payload = $sink->onlyPayload();
            $firstSource = $payload['database_sources'][0] ?? null;
            $secondSource = $payload['database_sources'][1] ?? null;
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            if (
                $response->status !== 500
                || $payload['outcome'] !== 'unknown_failure'
                || $payload['unknown_failure_class'] !== PDOException::class
                || $payload['query_count'] !== 3
                || $payload['query_failures'] !== 1
                || $payload['query_budget_exceeded']
                || !is_array($firstSource)
                || !is_array($secondSource)
                || observabilityRuntimeKeys($firstSource) !== [
                    'name',
                    'budget_limit',
                    'budget_used',
                    'budget_exceeded',
                    'query_trace',
                ]
                || $firstSource['name'] !== 'first'
                || $firstSource['budget_used'] !== 2
                || $firstSource['query_trace']['statements'] !== 2
                || $firstSource['query_trace']['failures'] !== 0
                || $firstSource['query_trace']['tracked_fingerprints'] !== 1
                || !$firstSource['query_trace']['truncated']
                || $firstSource['query_trace']['untracked_statements'] !== 1
                || $secondSource['name'] !== 'second'
                || $secondSource['budget_used'] !== 1
                || $secondSource['query_trace']['statements'] !== 1
                || $secondSource['query_trace']['failures'] !== 1
                || $secondSource['query_trace']['truncated']
                || str_contains($encoded, 'FirstSourcePrivateMarker')
                || str_contains($encoded, 'TruncatedSourcePrivateMarker')
                || str_contains($encoded, 'FailedSourcePrivateMarker')
                || str_contains($encoded, 'missing_table')
                || str_contains($encoded, 'SELECT')
            ) {
                throw new RuntimeException('Expected ordered bounded multi-source failure evidence.');
            }
        },
        'terminal coordinator distinguishes exact budget use from one rejected attempt' => static function (): void {
            $budget = new QueryBudget(1);
            $trace = new QueryTrace(1);
            $connection = Connection::connect('sqlite::memory:', $budget, $trace);
            $handler = new ObservabilityTestHandler(
                static function (Request $request) use ($connection): Response {
                    $connection->selectOneRow('SELECT :value AS value', ['value' => 1]);
                    $connection->selectOneRow('SELECT :value AS value', ['value' => 2]);

                    return new Response(200, ['Content-Type' => 'text/plain'], "unreachable\n");
                },
            );
            $sink = new CapturingRequestSummarySink();
            $correlationId = CorrelationId::generate();
            $coordinator = observabilityCoordinator(
                $handler,
                $correlationId,
                $sink,
                [new QuerySummarySource('primary', $budget, $trace)],
            );
            $response = $coordinator->handle(
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/budget'],
                [],
            );
            $payload = $sink->onlyPayload();
            $source = $payload['database_sources'][0] ?? null;

            if (
                $response->status !== 500
                || $payload['outcome'] !== 'unknown_failure'
                || $payload['unknown_failure_class'] !== PHPThis\Database\QueryBudgetExceeded::class
                || $payload['query_count'] !== 1
                || !$payload['query_budget_exceeded']
                || !is_array($source)
                || $source['budget_limit'] !== 1
                || $source['budget_used'] !== 1
                || !$source['budget_exceeded']
                || $source['query_trace']['statements'] !== 1
                || count($source['query_trace']['queries']) !== 1
            ) {
                throw new RuntimeException('Expected an over-budget attempt to remain visible and untraced.');
            }
        },
        'terminal coordinator keeps success and unknown responses unchanged when the sink throws' => static function (): void {
            $cookie = new ResponseCookie(
                'result',
                'CookiePrivateMarker',
                '/',
                true,
                true,
                CookieSameSite::Lax,
            );
            $successSink = new ThrowingRequestSummarySink();
            $successCorrelationId = CorrelationId::generate();
            $success = observabilityCoordinator(
                new ObservabilityTestHandler(
                    static fn (Request $request): Response => new Response(
                        201,
                        ['Content-Type' => 'text/plain', 'X-Result' => 'kept'],
                        "created\n",
                        [$cookie],
                    ),
                ),
                $successCorrelationId,
                $successSink,
            )->handle(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/create'], []);

            $failureSink = new ThrowingRequestSummarySink();
            $failureCorrelationId = CorrelationId::generate();
            $failure = observabilityCoordinator(
                new ObservabilityTestHandler(
                    static function (Request $request): Response {
                        throw new UnexpectedValueException('FailurePrivateMarker');
                    },
                ),
                $failureCorrelationId,
                $failureSink,
            )->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/failure'], []);

            if (
                $successSink->attempts !== 1
                || $success->status !== 201
                || $success->headers !== [
                    'Content-Type' => 'text/plain',
                    'X-Result' => 'kept',
                    'X-Request-ID' => $successCorrelationId->value,
                ]
                || $success->body !== "created\n"
                || $success->cookies !== [$cookie]
                || $failureSink->attempts !== 1
                || $failure->status !== 500
                || $failure->headers !== [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'no-store',
                    'X-Request-ID' => $failureCorrelationId->value,
                ]
                || $failure->body
                    !== "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n"
            ) {
                throw new RuntimeException('Expected one failed sink attempt to leave each final response intact.');
            }
        },
        'terminal request summary excludes request response database and exception secrets' => static function (): void {
            $bodyPath = __DIR__ . '/../tmp/observability-private-body.txt';
            $body = 'BodyPrivateMarker';

            if (file_put_contents($bodyPath, $body) !== strlen($body)) {
                throw new RuntimeException('Unable to create the observability body fixture.');
            }

            $budget = new QueryBudget(1);
            $trace = new QueryTrace(1);
            $connection = Connection::connect('sqlite::memory:', $budget, $trace);
            $handler = new ObservabilityTestHandler(
                static function (Request $request) use ($connection): Response {
                    $connection->selectOneRow(
                        'SELECT :private_value AS value',
                        ['private_value' => 'SqlBindingPrivateMarker'],
                    );

                    return new Response(
                        200,
                        ['X-Response-Marker' => 'ResponseHeaderPrivateMarker'],
                        "ResponseBodyPrivateMarker\n",
                        [new ResponseCookie(
                            'secret',
                            'ResponseCookiePrivateMarker',
                            '/',
                            true,
                            true,
                            CookieSameSite::Strict,
                        )],
                    );
                },
            );
            $sink = new CapturingRequestSummarySink();
            $correlationId = CorrelationId::generate();

            try {
                $response = observabilityCoordinator(
                    $handler,
                    $correlationId,
                    $sink,
                    [new QuerySummarySource('sensitive_primary', $budget, $trace)],
                    null,
                    $bodyPath,
                )->handle([
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/PathPrivateMarker?query=QueryStringPrivateMarker',
                    'CONTENT_LENGTH' => (string) strlen($body),
                    'HTTP_AUTHORIZATION' => 'Bearer AuthorizationPrivateMarker',
                    'HTTP_COOKIE' => 'session=CookieHeaderPrivateMarker',
                ], ['query' => 'QueryValuePrivateMarker']);
            } finally {
                if (is_file($bodyPath) && !unlink($bodyPath)) {
                    throw new RuntimeException('Unable to remove the observability body fixture.');
                }
            }

            $encoded = json_encode($sink->onlyPayload(), JSON_THROW_ON_ERROR);

            foreach ([
                'PathPrivateMarker',
                'QueryStringPrivateMarker',
                'QueryValuePrivateMarker',
                'BodyPrivateMarker',
                'AuthorizationPrivateMarker',
                'CookieHeaderPrivateMarker',
                'ResponseHeaderPrivateMarker',
                'ResponseBodyPrivateMarker',
                'ResponseCookiePrivateMarker',
                'SqlBindingPrivateMarker',
                'private_value',
                'SELECT',
                'sqlite:',
            ] as $secret) {
                if (str_contains($encoded, $secret)) {
                    throw new RuntimeException('Request summary retained forbidden data: ' . $secret);
                }
            }

            if ($response->status !== 200 || $sink->attempts !== 1) {
                throw new RuntimeException('Expected the redaction request to complete with one summary attempt.');
            }
        },
        'query summary sources are finite uniquely named and connection local' => static function (): void {
            $handler = new ObservabilityTestHandler(
                static fn (Request $request): Response => new Response(200, [], ''),
            );
            $sources = [];

            for ($index = 0; $index < 8; $index++) {
                $sources[] = new QuerySummarySource(
                    'source_' . $index,
                    new QueryBudget(1),
                    new QueryTrace(1),
                );
            }

            observabilityCoordinator(
                $handler,
                CorrelationId::generate(),
                new CapturingRequestSummarySink(),
                $sources,
            );

            $nineSources = $sources;
            $nineSources[] = new QuerySummarySource(
                'source_8',
                new QueryBudget(1),
                new QueryTrace(1),
            );
            assertObservabilityCompositionRejected($handler, $nineSources);

            assertObservabilityCompositionRejected($handler, [
                new QuerySummarySource('duplicate', new QueryBudget(1), new QueryTrace(1)),
                new QuerySummarySource('duplicate', new QueryBudget(1), new QueryTrace(1)),
            ]);

            $sharedBudget = new QueryBudget(1);
            assertObservabilityCompositionRejected($handler, [
                new QuerySummarySource('first', $sharedBudget, new QueryTrace(1)),
                new QuerySummarySource('second', $sharedBudget, new QueryTrace(1)),
            ]);

            $sharedTrace = new QueryTrace(1);
            assertObservabilityCompositionRejected($handler, [
                new QuerySummarySource('first', new QueryBudget(1), $sharedTrace),
                new QuerySummarySource('second', new QueryBudget(1), $sharedTrace),
            ]);

            foreach (['', 'UPPER', 'hyphen-name', str_repeat('a', 33)] as $invalidName) {
                try {
                    new QuerySummarySource($invalidName, new QueryBudget(1), new QueryTrace(1));
                } catch (InvalidArgumentException) {
                    continue;
                }

                throw new RuntimeException('Expected an invalid query-summary source name to be rejected.');
            }
        },
        'sequential terminal requests use fresh IDs budgets and traces' => static function (): void {
            $first = executeObservedSingleQueryRequest();
            $second = executeObservedSingleQueryRequest();

            if (
                $first['request_id'] === $second['request_id']
                || $first['summary']->toArray()['query_count'] !== 1
                || $second['summary']->toArray()['query_count'] !== 1
                || $first['summary']->toArray()['database_sources'][0]['budget_used'] !== 1
                || $second['summary']->toArray()['database_sources'][0]['budget_used'] !== 1
                || $first['summary']->toArray()['database_sources'][0]['query_trace']['statements'] !== 1
                || $second['summary']->toArray()['database_sources'][0]['query_trace']['statements'] !== 1
            ) {
                throw new RuntimeException('Expected fresh request-scoped correlation and database evidence.');
            }
        },
    ];
}

/**
 * @param list<QuerySummarySource> $querySources
 */
function observabilityCoordinator(
    RequestHandler $handler,
    CorrelationId $correlationId,
    RequestSummarySink $sink,
    array $querySources = [],
    ?ErrorResponseRegistry $errorResponses = null,
    string $inputUri = 'php://memory',
): TerminalRequestCoordinator {
    return new TerminalRequestCoordinator(
        new RequestBoundary(
            new RequestReader(4_096, $inputUri),
            $handler,
            $errorResponses ?? new ErrorResponseRegistry([]),
        ),
        new UnknownFailureBoundary(),
        $correlationId,
        $sink,
        $querySources,
    );
}

function observabilityErrorResponse(int $status): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        "{\"error\":{\"code\":\"request_failed\",\"message\":\"Request failed.\"}}\n",
    );
}

/** @return array<string, mixed> */
function observabilityDecodeErrorLogPayload(string $log): array
{
    $line = rtrim($log, "\r\n");

    if ($line === '' || str_contains($line, "\r") || str_contains($line, "\n")) {
        throw new RuntimeException('Expected exactly one error-log sink line.');
    }

    $jsonStart = strpos($line, '{');

    if ($jsonStart === false) {
        throw new RuntimeException('Expected one JSON object in the error-log sink line.');
    }

    $decoded = json_decode(
        substr($line, $jsonStart),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    if (!is_array($decoded)) {
        throw new RuntimeException('Expected the error-log sink payload to be an object.');
    }

    $payload = [];

    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            throw new RuntimeException('Expected string keys in the error-log sink payload.');
        }

        $payload[$key] = $value;
    }

    return $payload;
}

/** @param array<string, mixed> $payload */
function observabilityRuntimeValue(array $payload, string $key): mixed
{
    return $payload[$key] ?? null;
}

/**
 * @param array<string, mixed> $payload
 * @return list<string>
 */
function observabilityRuntimeKeys(array $payload): array
{
    return array_keys($payload);
}

/** @param list<QuerySummarySource> $sources */
function assertObservabilityCompositionRejected(
    RequestHandler $handler,
    array $sources,
): void {
    try {
        observabilityCoordinator(
            $handler,
            CorrelationId::generate(),
            new CapturingRequestSummarySink(),
            $sources,
        );
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Expected invalid observability composition to be rejected.');
}

/**
 * @return array{
 *     request_id: string,
 *     summary: RequestSummary
 * }
 */
function executeObservedSingleQueryRequest(): array
{
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite::memory:', $budget, $trace);
    $sink = new CapturingRequestSummarySink();
    $handler = new ObservabilityTestHandler(
        static function (Request $request) use ($connection): Response {
            $connection->selectOneRow('SELECT :value AS value', ['value' => 1]);

            return new Response(200, [], '');
        },
    );
    $correlationId = CorrelationId::generate();
    $response = observabilityCoordinator(
        $handler,
        $correlationId,
        $sink,
        [new QuerySummarySource('primary', $budget, $trace)],
    )->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/single-query'], []);
    $requestId = $response->headers['X-Request-ID'] ?? null;

    if (!is_string($requestId)) {
        throw new RuntimeException('Expected a generated request ID response header.');
    }

    return [
        'request_id' => $requestId,
        'summary' => $sink->onlySummary(),
    ];
}
