<?php

declare(strict_types=1);

use Example\Documents\GetDocument\DocumentDetailsCacheTrace;
use Example\Observability\CorrelationId;
use Example\Observability\TerminalRequestCoordinator;
use PHPThis\Application;
use PHPThis\Http\ErrorRepresentation;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

/** @return Generator<string, Closure(): void, mixed, void> */
function errorRepresentationBehaviorTests(): Generator
{
    yield 'error representations preserve defaults and independently customize routing failures' => static function (): void {
        $success = new Response(200, ['Content-Type' => 'text/plain'], 'ok');
        $handler = new ObservabilityTestHandler(static fn (Request $request): Response => $success);
        $router = new Router([
            new Route('GET', '/items/{id:positive-int}', $handler),
            new Route('DELETE', '/items/{id:positive-int}', $handler),
        ]);
        $notFound = new ErrorRepresentation('application/json', '{"code":"missing"}');
        $notAllowed = new ErrorRepresentation('text/html; charset=utf-8', '<p>Method unavailable</p>');
        foreach ([[null, null], [$notFound, null], [null, $notAllowed], [$notFound, $notAllowed]] as [$missing, $method]) {
            $application = new Application($router, notFound: $missing, methodNotAllowed: $method);
            $response404 = $application->handle(new Request('GET', '/items/0'));
            $response405 = $application->handle(new Request('POST', '/items/1'));
            if (
                $response404->status !== 404
                || $response404->body !== ($missing->body ?? "Not Found\n")
                || $response404->headers !== [
                    'Cache-Control' => 'no-store',
                    'Content-Type' => $missing->contentType ?? 'text/plain; charset=utf-8',
                ]
                || $response405->status !== 405
                || $response405->body !== ($method->body ?? "Method Not Allowed\n")
                || $response405->headers !== [
                    'Allow' => 'GET, DELETE',
                    'Cache-Control' => 'no-store',
                    'Content-Type' => $method->contentType ?? 'text/plain; charset=utf-8',
                ]
                || $response404->cookies !== [] || $response405->cookies !== []
                || $response404->fileBody !== null || $response405->fileBody !== null
                || $application->handle(new Request('GET', '/items/1')) !== $success
            ) {
                throw new RuntimeException('Error customization changed routing, control headers or defaults.');
            }
        }
        $explicitNull = (new UnknownFailureBoundary(null))->respond();
        $default = (new UnknownFailureBoundary())->respond();
        if ($explicitNull->status !== $default->status || $explicitNull->headers !== $default->headers || $explicitNull->body !== $default->body) {
            throw new RuntimeException('Explicit null must retain the built-in unknown response.');
        }
    };

    yield 'error representations enforce byte bounds and reject control header injection' => static function (): void {
        $maximumType = 'text/plain; profile=' . str_repeat('a', 236);
        $maximumBody = str_repeat('é', 32_768);
        $maximum = new ErrorRepresentation($maximumType, $maximumBody);
        $response = (new UnknownFailureBoundary($maximum))->respond();
        $empty = (new UnknownFailureBoundary(new ErrorRepresentation('text/plain', '')))->respond();
        if (
            strlen($maximumType) !== 256 || strlen($maximumBody) !== 65_536
            || $response->body !== $maximumBody
            || $response->headers !== ['Content-Type' => $maximumType, 'Cache-Control' => 'private, no-store']
            || $empty->body !== '' || $empty->status !== 500
        ) {
            throw new RuntimeException('Valid representation bounds must preserve exact bytes.');
        }
        $invalid = [
            ['', 'public'], ['   ', 'public'], [$maximumType . 'a', 'public'],
            ['text/plain', $maximumBody . 'a'],
        ];
        foreach (['Allow: POST', 'Cache-Control: public', 'Content-Length: 0', 'X-Request-ID: forged',
            'Set-Cookie: secret=value', 'Transfer-Encoding: chunked'] as $header) {
            $invalid[] = ["text/plain\r\n" . $header . '-private-sentinel', 'public'];
        }
        foreach (["\0", "\t", "\n", "\r", "\x1f", "\x7f"] as $control) {
            $invalid[] = ['text/' . $control . 'private-sentinel', 'public'];
        }
        foreach ($invalid as [$type, $body]) {
            try {
                new ErrorRepresentation($type, $body);
            } catch (InvalidArgumentException $failure) {
                if ($failure->getMessage() !== 'Error representation is invalid or oversized.') {
                    throw new RuntimeException('Invalid error configuration must have a fixed redacted failure.');
                }
                continue;
            }
            throw new RuntimeException('An invalid error representation was accepted.');
        }
    };

    yield 'configured errors retain correlation known mappings and unknown failure redaction' => static function (): void {
        $known = new Response(422, ['Content-Type' => 'application/json'], '{"code":"invalid_value"}');
        foreach ([null, new InvalidRequest('known-private-sentinel'), new RuntimeException('SQLSTATE secret-private-sentinel')] as $failure) {
            $handler = new ObservabilityTestHandler(static function (Request $request) use ($failure): Response {
                if ($failure !== null) {
                    throw $failure;
                }
                return new Response(204, [], '');
            });
            $application = new Application(
                new Router([new Route('POST', '/items', $handler)]),
                notFound: new ErrorRepresentation('application/json', '{"code":"missing"}'),
                methodNotAllowed: new ErrorRepresentation('application/json', '{"code":"method"}'),
            );
            $handledStatus = $failure === null ? 204 : ($failure instanceof InvalidRequest ? 422 : 500);
            $handledBody = $failure === null ? '' : ($failure instanceof InvalidRequest ? $known->body : '{"code":"service_error"}');
            $handledHeaders = $failure === null ? [] : ($failure instanceof InvalidRequest ? $known->headers : [
                'Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store',
            ]);
            $handledClass = $failure !== null && !$failure instanceof InvalidRequest ? RuntimeException::class : null;
            foreach ([
                ['GET', '/missing', 404, '{"code":"missing"}', ['Cache-Control' => 'no-store', 'Content-Type' => 'application/json'], null],
                ['GET', '/items', 405, '{"code":"method"}', ['Allow' => 'POST', 'Cache-Control' => 'no-store', 'Content-Type' => 'application/json'], null],
                ['POST', '/items', $handledStatus, $handledBody, $handledHeaders, $handledClass],
            ] as [$method, $path, $status, $body, $headers, $unknownClass]) {
                $id = CorrelationId::generate();
                $sink = new CapturingRequestSummarySink();
                $coordinator = new TerminalRequestCoordinator(
                    new RequestBoundary(requestReaderForBody('', 8), $application, new ErrorResponseRegistry([InvalidRequest::class => $known])),
                    new UnknownFailureBoundary(new ErrorRepresentation('application/json', '{"code":"service_error"}')),
                    $id, $sink, new DocumentDetailsCacheTrace(), [],
                );
                $response = $coordinator->handle([
                    'REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'HTTP_X_REQUEST_ID' => 'forged-private-sentinel',
                ], []);
                $payload = $sink->onlyPayload();
                $headers['X-Request-ID'] = $id->value;
                if (
                    $response->status !== $status || $response->body !== $body
                    || $response->headers !== $headers
                    || $payload['correlation_id'] !== $id->value || $payload['response_status'] !== $status
                    || $payload['unknown_failure_class'] !== $unknownClass
                    || str_contains(json_encode($payload, JSON_THROW_ON_ERROR) . $response->body, 'private-sentinel')
                ) {
                    throw new RuntimeException('Configured errors lost safe correlation, mapping or redaction.');
                }
            }
        }
    };

    yield 'fixed early representations preserve fallback disclosure and emission boundaries' => static function (): void {
        proveFixedEarlyErrorRepresentations();
    };

    yield 'real front controller emits configured errors and retains the built-in early fallback' => static function (): void {
        $source = file_get_contents(__DIR__ . '/fixtures/configured-error-bootstrap.php.fixture');
        if (!is_string($source)) {
            throw new RuntimeException('Cannot read configured error fixture.');
        }
        $temporary = dirname(__DIR__) . '/tmp/application-tests/configured-errors-' . bin2hex(random_bytes(8));
        $genericBody = "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n";
        $process = null;
        try {
            /** @var list<array{string, int, string, bool}> $cases */
            $cases = [
                ['missing', 404, '{"code":"missing"}', false],
                ['method', 405, '{"code":"method"}', false],
                ['unknown', 500, '{"code":"service_error"}', false],
                ['known', 400, '{"code":"known"}', false],
                ['invalid', 500, $genericBody, true],
                ['composition', 500, $genericBody, true],
            ];
            foreach ($cases as [$scenario, $status, $body, $early]) {
                $root = createExampleFrontControllerProcessTree(
                    $temporary . '/' . $scenario,
                    str_replace("\$scenario = 'unknown';", "\$scenario = '" . $scenario . "';", $source),
                );
                $logPath = $root . '/server.log';
                if (file_put_contents($logPath, '') !== 0 || !chmod($logPath, 0600)) {
                    throw new RuntimeException('Cannot create private configured-error server log.');
                }
                [$process, $port] = startExampleOuterHttpFailureServer($root, $logPath);
                $response = requestExampleOuterHttpFailure($port);
                proc_terminate($process);
                proc_close($process);
                $process = null;
                $headers = array_change_key_case($response['headers'], CASE_LOWER);
                $log = file_get_contents($logPath);
                if (
                    $response['status'] !== $status || $response['body'] !== $body
                    || ($headers['content-type'] ?? null) !== ($early ? 'application/json; charset=utf-8' : 'application/json')
                    || ($headers['cache-control'] ?? null) !== ($status === 500 ? 'private, no-store' : 'no-store')
                    || isset($headers['x-request-id']) === $early
                    || (!$early && preg_match('/\A[a-f0-9]{32}\z/D', $headers['x-request-id']) !== 1)
                    || ($headers['allow'] ?? null) !== ($status === 405 ? 'GET' : null)
                    || !is_string($log)
                    || str_contains($log . $response['body'], 'private-sentinel')
                    || substr_count($log, 'application.http_outer_failure') !== ($early ? 1 : 0)
                    || substr_count($log, 'application.request_summary') !== ($early ? 0 : 1)
                ) {
                    throw new RuntimeException('Real configured error or safe fallback failed: ' . $scenario);
                }
            }
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            removeOuterHttpFailureDirectory($temporary);
        }
    };
}

function configureEarlyErrorRepresentation(string $source, string $selection): string
{
    $start = strpos($source, "try {\n");
    if ($start === false) {
        throw new RuntimeException('Expected the established outer boundary.');
    }
    return substr_replace($source, "try {\n" . $selection . "\n", $start, 6);
}

function assertEarlyErrorRepresentation(bool $condition, string $name): void
{
    if (!$condition) {
        throw new RuntimeException('Early error representation failed: ' . $name);
    }
}

function proveFixedEarlyErrorRepresentations(): void
{
    $repository = dirname(__DIR__);
    $selection = <<<'PHP'
        $genericFailureResponse = (new UnknownFailureBoundary(
            new \PHPThis\Http\ErrorRepresentation(
                'application/json; charset=utf-8',
                '{"code":"service_unavailable"}',
            ),
        ))->respond();
    PHP;
    $original = file_get_contents($repository . '/example/public/index.php');
    if (!is_string($original)) {
        throw new RuntimeException('Unable to read the existing front controller.');
    }
    $genericBody = (new PHPThis\Http\UnknownFailureBoundary())->respond()->body;
    $customBody = '{"code":"service_unavailable"}';
    $prelude = <<<'PHP'
    <?php
    declare(strict_types=1);
    error_log('preflight.bootstrap_entered');
    PHP;
    $fileResponse = <<<'PHP'
    return new class {
        public function handle(array $server, array $query): \PHPThis\Http\Response
        {
            return new \PHPThis\Http\Response(200, ['Content-Length' => '4'], '', [],
                new \PHPThis\Http\LocalFileBody(__DIR__ . '/absent.bin', 4));
        }
    };
    PHP;
    $normalResponse = <<<'PHP'
    return new class {
        public function handle(array $server, array $query): \PHPThis\Http\Response
        {
            return new \PHPThis\Http\Response(200, ['Content-Type' => 'text/plain'], 'ok');
        }
    };
    PHP;
    $throwingInvocation = <<<'PHP'
    return new class {
        public function handle(array $server, array $query): \PHPThis\Http\Response
        {
            throw new \RuntimeException('private-sentinel-invocation');
        }
    };
    PHP;
    $composition = <<<'PHP'
    final class FailedComposition
    {
        public function __construct()
        {
            throw new \RuntimeException('private-sentinel-composition');
        }
    }
    new FailedComposition();
    PHP;
    $loadFailure = <<<'PHP'
    <?php
    declare(strict_types=1);
    spl_autoload_register(static function (string $class): void {
        if ($class === 'PHPThis\\Http\\ErrorRepresentation') {
            throw new ParseError('private-sentinel-representation-load');
        }
    });
    PHP;
    $eventFailure = <<<'PHP'
    <?php
    declare(strict_types=1);
    spl_autoload_register(static function (string $class): void {
        if ($class === 'Example\\Observability\\ErrorLogOuterFailureSink') {
            error_log('preflight.outer_sink_attempt');
            throw new RuntimeException('private-sentinel-sink-load');
        }
    });
    PHP;

    // name, selection, bootstrap, status, body, cache, bootstrap attempts, outer events, emission events, prepend
    $cases = [
        ['configuration', $selection, $prelude . "\nthrow new InvalidArgumentException('private-sentinel-configuration');", 500, $customBody, 'private, no-store', 1, 1, 0, null],
        ['bootstrap', $selection, $prelude . "\nthrow new RuntimeException('private-sentinel-bootstrap');", 500, $customBody, 'private, no-store', 1, 1, 0, null],
        ['composition', $selection, $prelude . "\n" . $composition, 500, $customBody, 'private, no-store', 1, 1, 0, null],
        ['bootstrap-parse-error', $selection, '<?php this is deliberately invalid syntax', 500, $customBody, 'private, no-store', 0, 1, 0, null],
        ['invocation', $selection, $prelude . "\n" . $throwingInvocation, 500, $customBody, 'private, no-store', 1, 1, 0, null],
        ['invalid-content-type', str_replace('application/json; charset=utf-8', ' ', $selection), $prelude . "\n" . $normalResponse, 500, $genericBody, 'private, no-store', 0, 1, 0, null],
        ['oversized-body', str_replace("'{\"code\":\"service_unavailable\"}'", var_export(str_repeat('a', 65_537), true), $selection), $prelude . "\n" . $normalResponse, 500, $genericBody, 'private, no-store', 0, 1, 0, null],
        ['representation-load-failure', $selection, $prelude . "\n" . $normalResponse, 500, $genericBody, 'private, no-store', 0, 1, 0, $loadFailure],
        ['throwing-outer-sink', $selection, $prelude . "\nthrow new RuntimeException('private-sentinel-bootstrap');", 500, $customBody, 'private, no-store', 1, 0, 0, $eventFailure],
        ['pre-start-emission', $selection, $prelude . "\n" . $fileResponse, 500, $customBody, 'no-store', 1, 0, 1, null],
        ['text-type-early', str_replace('application/json', 'text/plain', $selection), $prelude . "\nthrow new RuntimeException('private-sentinel-bootstrap');", 500, $customBody, 'private, no-store', 1, 1, 0, null],
        ['text-type-emission', str_replace('application/json', 'text/plain', $selection), $prelude . "\n" . $fileResponse, 500, $customBody, 'no-store', 1, 0, 1, null],
        ['default-early-failure', '', $prelude . "\nthrow new RuntimeException('private-sentinel-bootstrap');", 500, $genericBody, 'private, no-store', 1, 1, 0, null],
        ['default-emission-failure', '', $prelude . "\n" . $fileResponse, 500, $genericBody, 'no-store', 1, 0, 1, null],
        ['normal-response', $selection, $prelude . "\n" . $normalResponse, 200, 'ok', null, 1, 0, 0, null],
        ['pending-output', $selection, $prelude . "\nob_start(); echo 'already-owned-output'; throw new RuntimeException('private-sentinel-buffer');", 200, 'already-owned-output', null, 1, 1, 1, null],
        ['started-output', $selection, $prelude . "\necho 'already-owned-output'; flush(); throw new RuntimeException('private-sentinel-output');", 200, 'already-owned-output', null, 1, 1, 1, null],
    ];

    $coordinatorFixture = file_get_contents($repository . '/tests/fixtures/configured-error-bootstrap.php.fixture');
    if (!is_string($coordinatorFixture)) {
        throw new RuntimeException('Unable to read the configured coordinator fixture.');
    }
    foreach ([['unknown', 500, '{"code":"service_error"}'], ['known', 400, '{"code":"known"}'], ['missing', 404, '{"code":"missing"}'], ['method', 405, '{"code":"method"}']] as [$scenario, $status, $body]) {
        $bootstrap = str_replace("\$scenario = 'unknown';", "error_log('preflight.bootstrap_entered');\n\$scenario = '" . $scenario . "';", $coordinatorFixture);
        $cases[] = ['coordinator-' . $scenario, $selection, $bootstrap, $status, $body, $status === 500 ? 'private, no-store' : 'no-store', 1, 0, 0, null];
    }

    $temporary = $repository . '/tmp/application-tests/early-errors-' . bin2hex(random_bytes(6));
    $process = null;
    try {
        foreach ($cases as [$name, $chosen, $bootstrap, $status, $body, $cache, $bootstrapCount, $outerCount, $emissionCount, $prepend]) {
            $serverRoot = createExampleFrontControllerProcessTree($temporary . '/' . $name, $bootstrap);
            $front = configureEarlyErrorRepresentation($original, $chosen);
            file_put_contents($serverRoot . '/example/public/index.php', $front);
            $logPath = $serverRoot . '/server.log';
            file_put_contents($logPath, '');
            chmod($logPath, 0600);
            $prependPath = null;
            if ($prepend !== null) {
                $prependPath = $serverRoot . '/prepend.php';
                file_put_contents($prependPath, $prepend);
            }
            [$process, $port] = startExampleOuterHttpFailureServer($serverRoot, $logPath, null, null, $prependPath, ['output_buffering=0']);
            $response = requestExampleOuterHttpFailure($port, ['X-Request-ID' => 'private-sentinel-request']);
            proc_terminate($process);
            proc_close($process);
            $process = null;
            $log = file_get_contents($logPath);
            if (!is_string($log)) {
                throw new RuntimeException('Unable to read the early error test log.');
            }
            $headers = array_change_key_case($response['headers'], CASE_LOWER);
            $coordinated = str_starts_with($name, 'coordinator-');
            assertEarlyErrorRepresentation($response['status'] === $status && $response['body'] === $body, $name . ' status/body');
            assertEarlyErrorRepresentation(($headers['cache-control'] ?? null) === $cache, $name . ' cache policy');
            if ($coordinated) {
                assertEarlyErrorRepresentation(($headers['content-type'] ?? null) === 'application/json', $name . ' content type');
                assertEarlyErrorRepresentation(preg_match('/\A[a-f0-9]{32}\z/D', $headers['x-request-id'] ?? '') === 1, $name . ' correlation preserved');
            } else {
                if ($status === 500) {
                    $type = str_starts_with($name, 'text-type-') ? 'text/plain; charset=utf-8' : 'application/json; charset=utf-8';
                    assertEarlyErrorRepresentation(($headers['content-type'] ?? null) === $type, $name . ' content type');
                }
                assertEarlyErrorRepresentation(!isset($headers['x-request-id']), $name . ' no invented correlation');
            }
            assertEarlyErrorRepresentation(substr_count($log, 'preflight.bootstrap_entered') === $bootstrapCount, $name . ' startup count');
            assertEarlyErrorRepresentation(substr_count($log, 'application.http_outer_failure') === $outerCount, $name . ' outer event count');
            assertEarlyErrorRepresentation(substr_count($log, 'application.response_emission_failed') === $emissionCount, $name . ' emission event count');
            assertEarlyErrorRepresentation(substr_count($log, 'application.request_summary') === ($coordinated ? 1 : 0), $name . ' terminal summary count');
            if ($name === 'coordinator-method') {
                assertEarlyErrorRepresentation(($headers['allow'] ?? null) === 'GET', $name . ' Allow preserved');
            }
            assertEarlyErrorRepresentation(!str_contains(json_encode($response, JSON_THROW_ON_ERROR) . $log, 'private-sentinel'), $name . ' redaction');
            if ($name === 'throwing-outer-sink') {
                assertEarlyErrorRepresentation(substr_count($log, 'preflight.outer_sink_attempt') === 1, $name . ' single attempt');
            }
        }
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        removeOuterHttpFailureDirectory($temporary);
    }

    foreach ([
        ['eligible-details', false, false, 'development', 'DEVELOPMENT_DETAILS', true],
        ['renderer-autoload-failure', false, true, 'development', 'DEVELOPMENT_DETAILS', false],
        ['outer-sink-failure-details', true, false, 'development', 'DEVELOPMENT_DETAILS', true],
        ['invalid-disclosure', false, false, 'production', 'DEVELOPMENT_DETAILS', false],
        ['missing-disclosure', false, false, null, null, false],
    ] as [$name, $throwingSink, $throwingRenderer, $profile, $mode, $details]) {
        [$temporary, $serverRoot] = createConfiguredDisclosureServerTree('issue-137-' . $name, $throwingSink, $throwingRenderer);
        try {
            $path = $serverRoot . '/public/index.php';
            $source = file_get_contents($path);
            if (!is_string($source)) {
                throw new RuntimeException('Unable to read the disclosure fixture.');
            }
            file_put_contents($path, configureEarlyErrorRepresentation($source, $selection));
            $inputs = [];
            if ($profile !== null && $mode !== null) {
                $inputs['PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE'] = $profile;
                $inputs['PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE'] = $mode;
            }
            $result = runConfiguredDisclosureCase($temporary, $serverRoot, $name, $inputs);
            $response = $result['response'];
            $headers = array_change_key_case($response['headers'], CASE_LOWER);
            assertEarlyErrorRepresentation($response['status'] === 500, $name . ' status');
            assertEarlyErrorRepresentation($details ? str_starts_with($response['body'], "PHPThis development failure\n") : $response['body'] === $customBody, $name . ' selected response');
            assertEarlyErrorRepresentation(($headers['content-type'] ?? null) === ($details ? 'text/plain; charset=utf-8' : 'application/json; charset=utf-8'), $name . ' type');
            assertEarlyErrorRepresentation(($headers['cache-control'] ?? null) === 'private, no-store' && !isset($headers['x-request-id']), $name . ' headers');
            assertEarlyErrorRepresentation(!str_contains($result['log'], 'application.request_summary'), $name . ' summary');
        } finally {
            removeOuterHttpFailureDirectory($temporary);
        }
    }

}
