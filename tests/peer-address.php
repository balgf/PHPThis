<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\PeerAddress;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Routing\PathParameters;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;
use PHPThis\Session\SessionConfiguration;
use PHPThis\Session\SessionLifecycle;

/** @return Generator<string, Closure(): void, mixed, void> */
function peerAddressBehaviorTests(): Generator
{
    yield 'peer addresses retain valid IPv4 and IPv6 bytes without trusting forwarding headers' => static function (): void {
        $reader = requestReaderForBody('', 8);
        $server = [
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health',
            'HTTP_FORWARDED' => 'for=203.0.113.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.2',
            'HTTP_X_REAL_IP' => '203.0.113.3', 'HTTP_REMOTE_ADDR' => '203.0.113.4',
            'REMOTE_HOST' => ['not read'], 'REMOTE_PORT' => ['not read'],
        ];
        foreach ([
            '192.0.2.1', '0.0.0.0', '255.255.255.255', '127.0.0.1', '10.0.0.1',
            '::', '::1', '2001:DB8:0:0:0:0:0:1', '2001:db8::1', 'fe80::1',
            '::ffff:192.0.2.1', 'ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.255',
        ] as $address) {
            $value = PeerAddress::fromString($address);
            $request = $reader->read($server + ['REMOTE_ADDR' => $address], []);
            if ($value->address !== $address || $request->peerAddress === null || $request->peerAddress->address !== $address) {
                throw new RuntimeException('Valid peer address bytes were changed or lost.');
            }
        }
        $missing = $reader->read($server, []);
        if (
            $missing->peerAddress !== null || (new Request('GET', '/'))->peerAddress !== null
            || $missing->headers['x-forwarded-for'] !== '203.0.113.2'
        ) {
            throw new RuntimeException('Unavailable peer metadata must remain null without a header fallback.');
        }
    };

    yield 'malformed peer metadata fails redacted before body session and handler work' => static function (): void {
        $invalidStrings = [
            '', '192.168.001.1', '256.1.1.1', '127.1', '2130706433', '0x7f000001',
            '[::1]', 'fe80::1%eth0', '192.0.2.1:80', '192.0.2.1/24', '2001:db8::/32',
            'localhost', ' 192.0.2.1', "192.0.2.1\n", "::1\0", '::1 ',
            '192.0.2.1,192.0.2.2', '2001::db8::1', str_repeat('a', 46), 'private-sentinel',
        ];
        foreach ($invalidStrings as $address) {
            try {
                PeerAddress::fromString($address);
            } catch (InvalidArgumentException $failure) {
                if ($failure->getMessage() !== 'Peer address must be a bare IPv4 or IPv6 address of at most 45 bytes.') {
                    throw new RuntimeException('Peer factory failure must be fixed and redacted.');
                }
                continue;
            }
            throw new RuntimeException('Invalid direct peer address was accepted.');
        }
        $reader = new RequestReader(8, __DIR__ . '/../tmp/issue-103-must-not-read.body');
        $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/health'];
        foreach ([...$invalidStrings, null, false, 123, 1.5, ['192.0.2.1'], new stdClass()] as $address) {
            try {
                // Invalid query/header/upload metadata and a nonexistent body must not be reached.
                $reader->read($server + ['REMOTE_ADDR' => $address, 'HTTP_ACCEPT' => []], [0 => 'invalid'], ['bad'], ['bad']);
            } catch (InvalidRequest $failure) {
                if ($failure->getMessage() !== 'REMOTE_ADDR must be a bare IPv4 or IPv6 address of at most 45 bytes.') {
                    throw new RuntimeException('Peer rejection must precede other input parsing and redact its value.');
                }
                continue;
            }
            throw new RuntimeException('Malformed runtime peer metadata was accepted.');
        }
        $handler = new PeerRequiredTestHandler(new ObservabilityTestHandler(static function (Request $request): Response {
            throw new RuntimeException('Malformed peer reached the handler.');
        }));
        $sessions = new SessionLifecycle(new SessionConfiguration('peerTest', 'peerTest', false, CookieSameSite::Lax, sys_get_temp_dir()));
        // An already begun lifecycle makes any attempted begin fail; finish below also detects premature finalization.
        $sessions->begin(new Request('GET', '/'));
        $known = new Response(400, ['Cache-Control' => 'no-store'], 'invalid_request');
        try {
            $boundary = new RequestBoundary($reader, $handler, new ErrorResponseRegistry([InvalidRequest::class => $known]), $sessions);
            if ($boundary->handle($server + ['REMOTE_ADDR' => 'private-sentinel'], []) !== $known || $sessions->finish($known) !== $known) {
                throw new RuntimeException('Malformed peer touched the session or changed the known failure.');
            }
        } finally {
            $sessions->abort();
        }
    };

    yield 'routing and multipart request copies preserve the same peer value and request fields' => static function (): void {
        $reader = new RequestReader(8, 'php://memory', 1_024);
        foreach (['/health', '/items/7'] as $path) {
            $request = $reader->read([
                'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '2001:DB8::1',
                'CONTENT_TYPE' => 'multipart/form-data; boundary=peer-test', 'CONTENT_LENGTH' => '128',
            ], ['page' => '1'], [], ['document' => [
                'name' => 'untrusted.txt', 'type' => 'text/plain', 'tmp_name' => '/synthetic-upload',
                'error' => UPLOAD_ERR_OK, 'size' => 1,
            ]]);
            $handler = new ObservabilityTestHandler(static function (Request $routed) use ($request, $path): Response {
                if (
                    $routed === $request || $routed->peerAddress !== $request->peerAddress
                    || $routed->peerAddress === null || $routed->peerAddress->address !== '2001:DB8::1'
                    || $routed->uploads !== $request->uploads || $routed->headers !== $request->headers
                    || $routed->body !== $request->body || $routed->query !== $request->query
                    || $routed->path !== $path || $routed->method !== 'POST'
                    || ($path === '/items/7' && $routed->pathParameters->positiveInteger('id') !== 7)
                ) {
                    throw new RuntimeException('Routing discarded or changed peer or other request metadata.');
                }
                return new Response(204, [], '');
            });
            $application = new Application(new Router([
                new Route('POST', '/health', $handler), new Route('POST', '/items/{id:positive-int}', $handler),
            ]));
            if ($application->handle($request)->status !== 204) {
                throw new RuntimeException('Peer-bearing multipart request was not dispatched.');
            }
        }
        $peer = PeerAddress::fromString('192.0.2.1');
        $direct = new Request('POST', '/', ['q' => 'v'], 'body', ['x-test' => 'v'], peerAddress: $peer);
        $copy = $direct->withPathParameters(PathParameters::none());
        if ($copy->peerAddress !== $peer || $copy->body !== 'body' || $copy->query !== $direct->query || $copy->headers !== $direct->headers) {
            throw new RuntimeException('Direct request copying lost peer or content.');
        }
    };

    yield 'an application requiring peer metadata rejects unavailable input before protected work' => static function (): void {
        $success = new Response(204, [], '');
        $present = new Request('GET', '/', peerAddress: PeerAddress::fromString('192.0.2.1'));
        $protected = new PeerProtectedTestHandler(static function (Request $request) use ($present, $success): Response {
            if ($request !== $present) {
                throw new RuntimeException('The peer gate must delegate the exact request.');
            }
            return $success;
        });
        $handler = new PeerRequiredTestHandler($protected);
        $denied = $handler->handle(new Request('GET', '/', headers: ['x-forwarded-for' => '192.0.2.1']));
        if ($denied->status !== 403 || $denied->body !== '{"code":"forbidden"}' || $denied->headers !== ['Cache-Control' => 'private, no-store']) {
            throw new RuntimeException('An unavailable peer must not enter protected work.');
        }
        $protected->expectCalls(0);
        if ($handler->handle($present) !== $success) {
            throw new RuntimeException('A present peer must enter the selected application handler once.');
        }
        $protected->expectCalls(1);
    };

    yield 'the real front controller retains the server peer despite spoofed forwarding headers' => static function (): void {
        $source = file_get_contents(__DIR__ . '/fixtures/peer-bootstrap.php.fixture');
        if (!is_string($source)) {
            throw new RuntimeException('Cannot read peer bootstrap fixture.');
        }
        $temporary = dirname(__DIR__) . '/tmp/application-tests/peer-' . bin2hex(random_bytes(8));
        $process = null;
        try {
            $root = createExampleFrontControllerProcessTree($temporary, $source);
            $logPath = $root . '/server.log';
            if (file_put_contents($logPath, '') !== 0 || !chmod($logPath, 0600)) {
                throw new RuntimeException('Cannot create private peer server log.');
            }
            [$process, $port] = startExampleOuterHttpFailureServer($root, $logPath);
            $response = requestExampleOuterHttpFailure($port, [
                'Forwarded' => 'for=203.0.113.1', 'X-Forwarded-For' => '203.0.113.2',
                'X-Real-IP' => '203.0.113.3', 'Remote-Addr' => '203.0.113.4',
            ]);
            $headers = array_change_key_case($response['headers'], CASE_LOWER);
            if (
                $response['status'] !== 200 || $response['body'] !== '{"peer":"127.0.0.1"}'
                || ($headers['cache-control'] ?? null) !== 'private, no-store'
                || !isset($headers['x-request-id']) || preg_match('/\A[a-f0-9]{32}\z/D', $headers['x-request-id']) !== 1
            ) {
                throw new RuntimeException('Canonical front controller did not retain the server-reported peer.');
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

final class PeerProtectedTestHandler implements RequestHandler
{
    private int $calls = 0;

    /** @param Closure(Request): Response $behavior */
    public function __construct(private readonly Closure $behavior)
    {
    }

    public function handle(Request $request): Response
    {
        $this->calls++;
        return ($this->behavior)($request);
    }

    public function expectCalls(int $expected): void
    {
        if ($this->calls !== $expected) {
            throw new RuntimeException('Protected peer operation call count differs.');
        }
    }
}

// Checked application-owned availability gate; it does not authenticate or allowlist an address.
final readonly class PeerRequiredTestHandler implements RequestHandler
{
    public function __construct(private RequestHandler $next)
    {
    }

    public function handle(Request $request): Response
    {
        if ($request->peerAddress === null) {
            return new Response(403, ['Cache-Control' => 'private, no-store'], '{"code":"forbidden"}');
        }
        return $this->next->handle($request);
    }
}
