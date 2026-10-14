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
