<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function httpBoundaryBehaviorTests(): Generator
{
    yield 'application dispatches an exact route' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(200, ['Content-Type' => 'text/plain'], 'ok');
        }
    };

    $application = new Application(new Router([new Route('GET', '/health', $handler)]));
    $response = $application->handle(new Request('GET', '/health'));

    if ($response->status !== 200 || $response->body !== 'ok') {
        throw new RuntimeException('Expected the route handler response.');
    }
};

    yield 'application distinguishes 404 and 405' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };

    $application = new Application(new Router([new Route('GET', '/health', $handler)]));
    $notAllowed = $application->handle(new Request('POST', '/health'));
    $notFound = $application->handle(new Request('GET', '/missing'));

    if (
        $notAllowed->status !== 405
        || $notAllowed->headers['Allow'] !== 'GET'
        || $notAllowed->headers['Cache-Control'] !== 'no-store'
    ) {
        throw new RuntimeException('Expected 405 with an Allow header.');
    }

    if ($notFound->status !== 404 || $notFound->headers['Cache-Control'] !== 'no-store') {
        throw new RuntimeException('Expected 404 for an unknown path.');
    }
};

    yield 'request reader normalizes one bounded PHP runtime request' => static function (): void {
    $body = '{"name":"Ada"}';
    $reader = requestReaderForBody($body, strlen($body));
    $request = $reader->read(
        [
            'REQUEST_METHOD' => 'post',
            'REQUEST_URI' => '/users?active=1',
            'CONTENT_TYPE' => 'application/json; charset=utf-8',
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
            'CONTENT_LENGTH' => (string) strlen($body),
            'HTTP_CONTENT_LENGTH' => (string) strlen($body),
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUEST_SOURCE' => 'test-suite',
            'SERVER_PROTOCOL' => ['ignored', 'because it is not a header'],
        ],
        ['page' => '1', 'filter' => ['active' => '1']],
    );
    $retainedPath = "/a//../raw\\\x80/%00/%20/%7F/%2F/%3F/%23";
    $retainedRequest = requestReaderForBody('', 8)->read(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $retainedPath . '?value=%0A?tail'],
        ['value' => "\n"],
    );
    $maximumTarget = '/' . str_repeat('a', 8_191);
    $maximumTargetRequest = requestReaderForBody('', 8)->read(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $maximumTarget],
        [],
    );

    if (
        $request->method !== 'POST'
        || $request->path !== '/users'
        || $request->body !== $body
        || $request->query !== ['page' => '1', 'filter' => ['active' => '1']]
        || $request->headers !== [
            'content-type' => 'application/json; charset=utf-8',
            'content-length' => (string) strlen($body),
            'accept' => 'application/json',
            'x-request-source' => 'test-suite',
        ]
        || $retainedRequest->path !== $retainedPath
        || $retainedRequest->query !== ['value' => "\n"]
        || $maximumTargetRequest->path !== $maximumTarget
    ) {
        throw new RuntimeException('Expected one normalized immutable request from PHP runtime values.');
    }
};

    yield 'request reader rejects malformed runtime metadata' => static function (): void {
    $tooManyQueryParameters = [];

    for ($index = 0; $index < 65; $index++) {
        $tooManyQueryParameters['parameter_' . $index] = 'value';
    }

    $tooManyHeaders = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ];

    for ($index = 0; $index < 65; $index++) {
        $tooManyHeaders['HTTP_X_TEST_' . $index] = 'value';
    }

    $cases = [
        [[], []],
        [['REQUEST_METHOD' => [], 'REQUEST_URI' => '/'], []],
        [['REQUEST_METHOD' => 'GET ', 'REQUEST_URI' => '/'], []],
        [['REQUEST_METHOD' => 'GET'], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => []], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'relative'], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items#fragment'], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/' . str_repeat('a', 8_192)], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], [0 => 'value']],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], $tooManyQueryParameters],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_ACCEPT' => []], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_' => 'value'], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_X_TEST' => "ok\nbad"], []],
        [[
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_X_TEST' => str_repeat('a', 8_193),
        ], []],
        [[
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'text/plain',
        ], []],
        [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'CONTENT_LENGTH' => '01'], []],
        [$tooManyHeaders, []],
    ];
    $reader = requestReaderForBody('', 8);

    foreach ($cases as [$server, $query]) {
        try {
            $reader->read($server, $query);
        } catch (InvalidRequest) {
            continue;
        }

        throw new RuntimeException('Expected malformed PHP runtime metadata to be rejected.');
    }

    $invalidPathBytes = [...range(0x00, 0x20), 0x7F];

    foreach ($invalidPathBytes as $byte) {
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
                    throw new RuntimeException('Expected one fixed redacted request-target diagnostic.');
                }

                continue;
            }

            throw new RuntimeException('Expected every prohibited raw request-target byte to be rejected.');
        }

        try {
            new Request('GET', '/safe' . chr($byte) . 'PrivateMarker');
        } catch (InvalidArgumentException $failure) {
            if (
                $failure->getMessage()
                    !== 'Request path must be absolute and contain no query, fragment, raw space, control, or DEL byte.'
                || str_contains($failure->getMessage(), 'PrivateMarker')
            ) {
                throw new RuntimeException('Expected one fixed redacted request-path diagnostic.');
            }

            continue;
        }

        throw new RuntimeException('Expected every prohibited direct request-path byte to be rejected.');
    }
};

    yield 'request reader enforces declared and actual body bounds' => static function (): void {
    foreach ([0, -1, PHP_INT_MAX] as $invalidLimit) {
        try {
            new RequestReader($invalidLimit, 'php://input');
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected an unsafe body limit to be rejected at composition time.');
    }

    $emptyInputUriRejected = false;

    try {
        new RequestReader(1, '');
    } catch (InvalidArgumentException) {
        $emptyInputUriRejected = true;
    }

    if (!$emptyInputUriRejected) {
        throw new RuntimeException('Expected an empty input URI to be rejected at composition time.');
    }

    $exactBody = '1234';
    $exactRequest = requestReaderForBody($exactBody, 4)->read(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'CONTENT_LENGTH' => '4',
        ],
        [],
    );

    if ($exactRequest->body !== $exactBody) {
        throw new RuntimeException('Expected a body exactly at the configured limit.');
    }

    $oversizedReaders = [
        requestReaderForBody('12345', 4),
        new RequestReader(4, __DIR__ . '/../tmp/request-bodies/not-read.body'),
        new RequestReader(4, __DIR__ . '/../tmp/request-bodies/not-read.body'),
    ];
    $servers = [
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_LENGTH' => '5'],
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'CONTENT_LENGTH' => (string) PHP_INT_MAX . '0',
        ],
    ];

    foreach ($oversizedReaders as $index => $reader) {
        try {
            $reader->read($servers[$index], []);
        } catch (RequestBodyTooLarge) {
            continue;
        }

        throw new RuntimeException('Expected declared and actual oversized bodies to be rejected.');
    }

    try {
        requestReaderForBody('1234', 8)->read(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_LENGTH' => '3'],
            [],
        );
    } catch (InvalidRequest) {
        return;
    }

    throw new RuntimeException('Expected a mismatched declared body length to be rejected.');
};

    yield 'request boundary maps exact known failures and rethrows unknown failures' => static function (): void {
    $knownResponse = new Response(
        400,
        ['Content-Type' => 'application/json; charset=utf-8'],
        "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
    );
    $registry = new ErrorResponseRegistry([InvalidRequest::class => $knownResponse]);
    $handler = new class implements RequestHandler {
        public bool $called = false;

        public function handle(Request $request): Response
        {
            $this->called = true;
            return new Response(204, [], '');
        }
    };
    $knownBoundary = new RequestBoundary(requestReaderForBody('', 8), $handler, $registry);
    $mappedResponses = [
        $knownBoundary->handle(['REQUEST_METHOD' => [], 'REQUEST_URI' => '/'], []),
        $knownBoundary->handle(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/safe\nPrivateMarker"],
            [],
        ),
        $knownBoundary->handle(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/safe?value=\x7FPrivateMarker"],
            [],
        ),
    ];

    if (
        $mappedResponses !== [$knownResponse, $knownResponse, $knownResponse]
        || $handler->called
        || $registry->responseFor(new UnexpectedValueException('internal projection failure')) !== null
        || $registry->responseFor(new QueryBudgetExceeded('internal query limit')) !== null
        || str_contains($knownResponse->body, 'PrivateMarker')
    ) {
        throw new RuntimeException('Expected exact known-error mapping without broad exception matches.');
    }

    $unknownFailure = new RuntimeException('internal failure');
    $failingHandler = new class ($unknownFailure) implements RequestHandler {
        public function __construct(private RuntimeException $failure)
        {
        }

        public function handle(Request $request): Response
        {
            throw $this->failure;
        }
    };
    $unknownBoundary = new RequestBoundary(requestReaderForBody('', 8), $failingHandler, $registry);

    try {
        $unknownBoundary->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], []);
    } catch (RuntimeException $failure) {
        if ($failure === $unknownFailure) {
            return;
        }
    }

    throw new RuntimeException('Expected an unregistered failure to escape unchanged.');
};

    yield 'response cookies are explicit validated values' => static function (): void {
    $live = new ResponseCookie(
        '__Host-PHPThisSession',
        str_repeat('a', 32),
        '/',
        true,
        true,
        CookieSameSite::Lax,
    );
    $expired = new ResponseCookie(
        '__Host-PHPThisSession',
        '',
        '/',
        true,
        true,
        CookieSameSite::Lax,
        1,
        0,
    );
    $maximumNameValue = new ResponseCookie(
        'n',
        str_repeat('v', 4_095),
        '/',
        false,
        false,
        CookieSameSite::Lax,
    );
    $maximumName = new ResponseCookie(
        str_repeat('n', 4_096),
        '',
        '/',
        false,
        false,
        CookieSameSite::Lax,
    );
    $maximumPath = new ResponseCookie(
        'path',
        'value',
        '/' . str_repeat('p', 1_023),
        false,
        false,
        CookieSameSite::Strict,
    );
    $maximumExpirationTimestamp = PHP_INT_SIZE >= 8 ? (int) '253402300799' : PHP_INT_MAX;
    $maximumExpiration = new ResponseCookie(
        'expiration',
        'value',
        '/',
        false,
        false,
        CookieSameSite::Lax,
        $maximumExpirationTimestamp,
    );
    $maximumAge = new ResponseCookie(
        'maximum-age',
        'value',
        '/',
        false,
        false,
        CookieSameSite::Lax,
        maximumAgeSeconds: 34_560_000,
    );
    $casePreservedPrefix = new ResponseCookie(
        '__hOsT-HtTp-case',
        'value',
        '/',
        true,
        true,
        CookieSameSite::Lax,
    );

    if (
        $live->headerValue() !== '__Host-PHPThisSession=' . str_repeat('a', 32)
            . '; Path=/; Secure; HttpOnly; SameSite=Lax'
        || $expired->headerValue() !== '__Host-PHPThisSession=; Path=/'
            . '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; Secure; HttpOnly; SameSite=Lax'
        || strlen($maximumNameValue->name) + strlen($maximumNameValue->value) !== 4_096
        || strlen($maximumName->name) !== 4_096
        || strlen($maximumPath->path) !== 1_024
        || !str_contains(
            $maximumExpiration->headerValue(),
            'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $maximumExpirationTimestamp),
        )
        || strlen(gmdate('Y', $maximumExpirationTimestamp)) !== 4
        || !str_contains($maximumAge->headerValue(), 'Max-Age=34560000')
        || !str_starts_with($casePreservedPrefix->headerValue(), '__hOsT-HtTp-case=value;')
    ) {
        throw new RuntimeException('Expected exact cookie bounds and deterministic secure serialization.');
    }

    $invalidCookies = [
        static fn(): ResponseCookie => new ResponseCookie('bad name', 'value', '/', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie(str_repeat('n', 4_097), '', '/', false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', "bad;value", '/', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', 'relative', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/bad;attribute', false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/bad path', false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', "/bad\x1F", false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', "/bad\x7F", false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('n', str_repeat('v', 4_096), '/', false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/' . str_repeat('p', 1_024), false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', "/\x80", false, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, false, CookieSameSite::Lax, 0),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, false, CookieSameSite::Lax, maximumAgeSeconds: -1),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, false, CookieSameSite::Lax, maximumAgeSeconds: 34_560_001),
        static fn(): ResponseCookie => new ResponseCookie('__hOsT-name', 'value', '/', false, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__hOsT-name', 'value', '/nested', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__sEcUrE-name', 'value', '/', false, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__hTtP-name', 'value', '/', true, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__HtTp-name', 'value', '/', false, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__hOsT-HtTp-name', 'value', '/nested', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__HoSt-HtTp-name', 'value', '/', true, false, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, true, CookieSameSite::None),
    ];

    if (PHP_INT_SIZE >= 8) {
        $invalidCookies[] = static fn(): ResponseCookie => new ResponseCookie(
            'name',
            'value',
            '/',
            false,
            false,
            CookieSameSite::Lax,
            (int) '253402300800',
        );
    }

    foreach ($invalidCookies as $invalidCookie) {
        try {
            $invalidCookie();
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected an invalid response cookie to be rejected.');
    }

    $maximumCookies = [];
    for ($index = 0; $index < 50; $index++) {
        $maximumCookies[] = new ResponseCookie('cookie-' . $index, 'value', '/', false, false, CookieSameSite::Lax);
    }
    $maximumCookieResponse = new Response(200, [], '', $maximumCookies);
    $caseSensitiveNames = new Response(200, [], '', [
        new ResponseCookie('Case', 'one', '/', false, false, CookieSameSite::Lax),
        new ResponseCookie('case', 'two', '/', false, false, CookieSameSite::Lax),
    ]);
    $firstAggregateCookie = new ResponseCookie('first', str_repeat('v', 4_091), '/', false, false, CookieSameSite::Lax);
    $secondAggregateCookie = new ResponseCookie('second', str_repeat('v', 4_044), '/', false, false, CookieSameSite::Lax);
    $maximumAggregateResponse = new Response(200, [], '', [$firstAggregateCookie, $secondAggregateCookie]);

    if (
        count($maximumCookieResponse->cookies) !== 50
        || count($caseSensitiveNames->cookies) !== 2
        || strlen($firstAggregateCookie->headerValue()) + strlen($secondAggregateCookie->headerValue()) !== 8_192
        || count($maximumAggregateResponse->cookies) !== 2
    ) {
        throw new RuntimeException('Expected exact response-cookie collection bounds and case-sensitive names.');
    }

    $invalidResponses = [
        static fn(): Response => new Response(200, [], '', [
            new ResponseCookie('duplicate', 'one', '/', false, false, CookieSameSite::Lax),
            new ResponseCookie('duplicate', 'two', '/nested', false, false, CookieSameSite::Lax),
        ]),
        static fn(): Response => new Response(200, [], '', [
            new ResponseCookie('first', str_repeat('v', 4_091), '/', false, false, CookieSameSite::Lax),
            new ResponseCookie('second', str_repeat('v', 4_045), '/', false, false, CookieSameSite::Lax),
        ]),
        static fn(): Response => new Response(200, [], '', [
            ...array_map(
                static fn(int $index): ResponseCookie => new ResponseCookie(
                    'overflow-' . $index,
                    'value',
                    '/',
                    false,
                    false,
                    CookieSameSite::Lax,
                ),
                range(0, 50),
            ),
        ]),
        static fn(): Response => new Response(200, ['Set-Cookie' => 'manual=value'], ''),
    ];

    foreach ($invalidResponses as $invalidResponse) {
        try {
            $invalidResponse();
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected an invalid response cookie collection to be rejected.');
    }
};

    yield 'ordinary response framing enforces final statuses and explicit HEAD routing' => static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/response-framing.php');

    if ($result['exit_code'] !== 0 || $result['stderr'] !== '') {
        throw new RuntimeException('Response-framing subprocess failed: ' . $result['stderr']);
    }

    $decoded = json_decode($result['stdout'], true, 32, JSON_THROW_ON_ERROR);

    if (
        !is_array($decoded)
        || $decoded !== [
            'maximum_status' => 599,
            'minimum_status_rejected' => true,
            'explicit_head_status' => 200,
            'get_only_head_status' => 405,
            'get_only_head_headers' => [
                'Allow' => 'GET',
                'Cache-Control' => 'no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
            'get_only_head_body' => "Method Not Allowed\n",
            'get_only_handler_calls' => 0,
        ]
    ) {
        throw new RuntimeException('Expected bounded final statuses without an inferred HEAD-to-GET fallback.');
    }
};

    yield 'response emitter rejects pending output and preserves repeated Set-Cookie fields' => static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/response-emitter.php');

    if ($result['exit_code'] !== 0 || $result['stderr'] !== '') {
        throw new RuntimeException('Response emitter subprocess failed: ' . $result['stderr']);
    }

    $decoded = json_decode($result['stdout'], true, 32, JSON_THROW_ON_ERROR);

    if (
        !is_array($decoded)
        || ($decoded['status'] ?? null) !== 201
        || ($decoded['body'] ?? null) !== 'created'
        || ($decoded['headers'] ?? null) !== [
            ['line' => 'Content-Type: text/plain', 'replace' => true],
            ['line' => 'Set-Cookie: first=one; Path=/; Secure; HttpOnly; SameSite=Lax', 'replace' => false],
            ['line' => 'Set-Cookie: second=two; Path=/; Secure; HttpOnly; SameSite=Strict', 'replace' => false],
        ]
    ) {
        throw new RuntimeException('Expected ordinary replacement headers and repeated cookie fields.');
    }
};

    yield 'request boundary normalizes one bounded multipart upload' => static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/upload-request-boundary.php');

    if (
        $result['exit_code'] !== 0
        || $result['stdout'] !== "upload request boundary: ok\n"
        || $result['stderr'] !== ''
    ) {
        throw new RuntimeException('Multipart request-boundary subprocess failed.');
    }
};

    yield 'session lifecycle is lazy strict scoped and fixation resistant' => static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/session-lifecycle.php');

    if (
        $result['exit_code'] !== 0
        || $result['stdout'] !== "PASS isolated native session lifecycle\n"
    ) {
        throw new RuntimeException('Native session lifecycle proof failed: ' . $result['stderr'] . $result['stdout']);
    }
};

    yield 'session cleanup preserves primary failures and resets deterministically' => static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/fixtures/session-cleanup-failures.php.fixture');

    if (
        $result['exit_code'] !== 0
        || $result['stdout'] !== "PASS isolated session cleanup failure precedence\n"
        || $result['stderr'] !== ''
    ) {
        throw new RuntimeException('Session cleanup failure proof failed: ' . $result['stderr'] . $result['stdout']);
    }
};

    yield 'unknown failure boundary returns one generic response without logging' => static function (): void {
    $response = (new UnknownFailureBoundary())->respond();

    if (
        $response->status !== 500
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]
        || $response->body !== "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n"
    ) {
        throw new RuntimeException('Expected the pure unknown-failure boundary to return one generic 500 response.');
    }
};

}
