<?php

declare(strict_types=1);

use Example\Routes;
use Example\Users\CreateUser\CreateUserCommand;
use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\GetUser\GetUserHandler;
use Example\Users\GetUser\UserDetails;
use Example\Users\GetUser\UserId;
use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\ListUsers\ListUsersPageRequest;
use Example\Users\ListUsers\UserActivitySummary;
use Example\Users\ListUsers\UserSummary;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\PathParameters;
use PHPThis\Routing\Route;
use PHPThis\Routing\RouteParameterType;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

$tests = [];

$tests['example composes explicit route modules'] = static function (): void {
    $application = new Application(new Router(Routes::create(
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(2), new QueryTrace(2)),
    )));
    $response = $application->handle(new Request('GET', '/health'));

    if (
        $response->status !== 200
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $response->body !== "{\"status\":\"ok\"}\n"
    ) {
        throw new RuntimeException('Expected the composed example health route.');
    }
};

$tests['example converts both nested route values into concrete identifiers'] = static function (): void {
    $application = new Application(new Router(Routes::create(
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(2), new QueryTrace(2)),
    )));
    $response = $application->handle(
        new Request('GET', '/accounts/42/documents/Doc_9-z'),
    );

    if (
        $response->status !== 200
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $response->body
            !== "{\"document\":{\"account_id\":42,\"key\":\"Doc_9-z\"}}\n"
    ) {
        throw new RuntimeException('Expected concrete identifiers from both typed route values.');
    }
};

$tests['application dispatches an exact route'] = static function (): void {
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

$tests['application distinguishes 404 and 405'] = static function (): void {
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

$tests['request reader normalizes one bounded PHP runtime request'] = static function (): void {
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
    ) {
        throw new RuntimeException('Expected one normalized immutable request from PHP runtime values.');
    }
};

$tests['request reader rejects malformed runtime metadata'] = static function (): void {
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
};

$tests['request reader enforces declared and actual body bounds'] = static function (): void {
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

$tests['request boundary maps exact known failures and rethrows unknown failures'] = static function (): void {
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
    $mapped = $knownBoundary->handle(['REQUEST_METHOD' => [], 'REQUEST_URI' => '/'], []);

    if (
        $mapped !== $knownResponse
        || $handler->called
        || $registry->responseFor(new UnexpectedValueException('internal projection failure')) !== null
        || $registry->responseFor(new QueryBudgetExceeded('internal query limit')) !== null
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

$tests['response cookies are explicit validated values'] = static function (): void {
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

    if (
        $live->headerValue() !== '__Host-PHPThisSession=' . str_repeat('a', 32)
            . '; Path=/; Secure; HttpOnly; SameSite=Lax'
        || $expired->headerValue() !== '__Host-PHPThisSession=; Path=/'
            . '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; Secure; HttpOnly; SameSite=Lax'
    ) {
        throw new RuntimeException('Expected deterministic secure cookie serialization.');
    }

    $invalidCookies = [
        static fn(): ResponseCookie => new ResponseCookie('bad name', 'value', '/', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', "bad;value", '/', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', 'relative', true, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('__Host-name', 'value', '/', false, true, CookieSameSite::Lax),
        static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, true, CookieSameSite::None),
    ];

    foreach ($invalidCookies as $invalidCookie) {
        try {
            $invalidCookie();
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected an invalid response cookie to be rejected.');
    }

    try {
        new Response(200, [], '', [$live, $expired]);
    } catch (InvalidArgumentException) {
        try {
            new Response(200, ['Set-Cookie' => 'manual=value'], '');
        } catch (InvalidArgumentException) {
            return;
        }
    }

    throw new RuntimeException('Expected duplicate or manually encoded response cookies to be rejected.');
};

$tests['response emitter preserves repeated Set-Cookie fields'] = static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/response-emitter.php');

    if ($result['exit_code'] !== 0) {
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

$tests['session lifecycle is lazy strict scoped and fixation resistant'] = static function (): void {
    $result = runIsolatedPhpTest(__DIR__ . '/session-lifecycle.php');

    if (
        $result['exit_code'] !== 0
        || $result['stdout'] !== "PASS isolated native session lifecycle\n"
    ) {
        throw new RuntimeException('Native session lifecycle proof failed: ' . $result['stderr'] . $result['stdout']);
    }
};

$tests['unknown failure boundary logs once and returns one generic response'] = static function (): void {
    $logPath = __DIR__ . '/../tmp/unknown-failure.log';

    if (is_file($logPath) && !unlink($logPath)) {
        throw new RuntimeException('Unable to reset the unknown-failure test log.');
    }

    $previousErrorLog = ini_get('error_log');

    if (ini_set('error_log', $logPath) === false) {
        throw new RuntimeException('Unable to redirect the unknown-failure test log.');
    }

    try {
        $response = (new UnknownFailureBoundary())->logAndRespond(
            new RuntimeException('private failure message'),
        );
    } finally {
        if (is_string($previousErrorLog)) {
            ini_set('error_log', $previousErrorLog);
        }
    }

    $log = file_get_contents($logPath);

    if (
        !is_string($log)
        || substr_count($log, 'phpthis.request.unhandled exception=RuntimeException') !== 1
        || str_contains($log, 'private failure message')
        || $response->status !== 500
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $response->body !== "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n"
    ) {
        throw new RuntimeException('Expected one redacted unknown-failure log and generic 500 response.');
    }
};

$tests['router rejects duplicate method and path pairs'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };

    try {
        new Router([
            new Route('GET', '/health', $handler),
            new Route('GET', '/health', $handler),
        ]);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Expected duplicate routes to fail at startup.');
};

$tests['route accepts at most two full-segment typed parameter declarations'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $itemRoute = new Route('GET', '/users/{user_id:positive-int}', $handler);
    $nestedRoute = new Route(
        'GET',
        '/accounts/{account_id:positive-int}/documents/{document_key:token}',
        $handler,
    );
    $retainedRoute = new Route('GET', '/users//{user_id:positive-int}', $handler);
    $retainedMatch = (new Router([$retainedRoute]))->match(new Request('GET', '/users//7'));
    $segments = $nestedRoute->segments();

    if (
        $itemRoute->path !== '/users/{user_id:positive-int}'
        || $nestedRoute->path
            !== '/accounts/{account_id:positive-int}/documents/{document_key:token}'
        || count($segments) !== 5
        || $segments[1]->literal !== 'accounts'
        || $segments[2]->parameterName !== 'account_id'
        || $segments[2]->parameterType !== RouteParameterType::PositiveInteger
        || $segments[3]->literal !== 'documents'
        || $segments[4]->parameterName !== 'document_key'
        || $segments[4]->parameterType !== RouteParameterType::Token
        || $retainedMatch?->route !== $retainedRoute
        || $retainedMatch->pathParameters->positiveInteger('user_id') !== 7
    ) {
        throw new RuntimeException('Expected explicit typed route declarations to remain inspectable.');
    }

    $invalidPaths = [
        '/users/{id}',
        '/users/{Id:positive-int}',
        '/accounts/{accountId:positive-int}',
        '/users/{1id:positive-int}',
        '/users/{id:integer}',
        '/users/{id:positive-int}suffix',
        '/users/prefix{id:positive-int}',
        '/{first:positive-int}/{second:token}/{third:token}',
    ];

    foreach ($invalidPaths as $path) {
        try {
            new Route('GET', $path, $handler);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException("Expected invalid typed route declaration to fail: {$path}");
    }
};

$tests['router matches bounded canonical positive integer path parameters'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $route = new Route('GET', '/users/{user_id:positive-int}', $handler);
    $router = new Router([$route]);
    $one = $router->match(new Request('GET', '/users/1'));
    $maximum = $router->match(new Request('GET', '/users/' . PHP_INT_MAX));

    if (
        $one === null
        || $one->route !== $route
        || $one->pathParameters->positiveInteger('user_id') !== 1
        || $maximum === null
        || $maximum->pathParameters->positiveInteger('user_id') !== PHP_INT_MAX
    ) {
        throw new RuntimeException('Expected canonical bounded positive-integer matching.');
    }

    $invalidSegments = [
        '',
        '0',
        '-1',
        '+1',
        '01',
        ' 1',
        '1 ',
        '1e2',
        (string) PHP_INT_MAX . '0',
        str_repeat('9', strlen((string) PHP_INT_MAX)),
        '%31',
        '1%32',
        '1%2Fdetails',
        '1.0',
        '１２',
    ];

    foreach ($invalidSegments as $segment) {
        if ($router->match(new Request('GET', '/users/' . $segment)) !== null) {
            throw new RuntimeException("Expected route parameter to be rejected: {$segment}");
        }
    }

    if ($router->match(new Request('GET', '/users/1/details')) !== null) {
        throw new RuntimeException('Expected an extra path segment to miss the item route.');
    }
};

$tests['router matches two ordered parameters and bounded opaque tokens'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $route = new Route(
        'GET',
        '/accounts/{account_id:positive-int}/documents/{document_key:token}',
        $handler,
    );
    $router = new Router([$route]);
    $validValues = [
        'A',
        'AbC_9-z',
        '001',
        'A' . str_repeat('_', 63),
    ];

    foreach ($validValues as $value) {
        $match = $router->match(new Request('GET', '/accounts/42/documents/' . $value));

        if (
            $match === null
            || $match->route !== $route
            || $match->pathParameters->positiveInteger('account_id') !== 42
            || $match->pathParameters->token('document_key') !== $value
        ) {
            throw new RuntimeException("Expected exact bounded token matching: {$value}");
        }
    }

    $invalidValues = [
        '',
        '_leading_underscore',
        '-leading-hyphen',
        'A' . str_repeat('_', 64),
        'contains.dot',
        'contains~tilde',
        'contains:colon',
        'contains space',
        "contains\tcontrol",
        'unicode-é',
        '%41',
        'abc%2Fdef',
        'abc/def',
    ];

    foreach ($invalidValues as $value) {
        if ($router->match(new Request('GET', '/accounts/42/documents/' . $value)) !== null) {
            throw new RuntimeException("Expected opaque token to be rejected: {$value}");
        }
    }
};

$tests['path parameters reject invalid construction unknown names and wrong types'] = static function (): void {
    foreach ([['Invalid', 1], ['user_id', 0]] as [$name, $value]) {
        try {
            PathParameters::onePositiveInteger($name, $value);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected invalid path parameter construction to fail.');
    }

    $invalidCollections = [
        static fn(): PathParameters => PathParameters::fromValues(
            ['first_id' => 1, 'second_id' => 2],
            ['third_key' => 'Third'],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            ['identifier' => 1],
            ['identifier' => 'Identifier'],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            [],
            ['document_key' => '_invalid'],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            ['user_id' => '1'],
            [],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            ['user_id' => true],
            [],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            ['user_id' => 1.0],
            [],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            [1 => 1],
            [],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            [],
            ['document_key' => 1],
        ),
    ];

    foreach ($invalidCollections as $invalidCollection) {
        try {
            $invalidCollection();
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected an invalid path parameter collection to fail.');
    }

    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $match = (new Router([
        new Route(
            'GET',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
    ]))->match(new Request('GET', '/accounts/7/documents/Doc_9'));

    if ($match === null) {
        throw new RuntimeException('Expected typed path parameters for accessor failure tests.');
    }

    $invalidAccessors = [
        static fn(): int => $match->pathParameters->positiveInteger('other_id'),
        static fn(): int => $match->pathParameters->positiveInteger('document_key'),
        static fn(): string => $match->pathParameters->token('other_key'),
        static fn(): string => $match->pathParameters->token('account_id'),
    ];

    foreach ($invalidAccessors as $invalidAccessor) {
        try {
            $invalidAccessor();
        } catch (OutOfBoundsException) {
            continue;
        }

        throw new RuntimeException('Expected an unknown or wrongly typed path parameter access to fail.');
    }
};

$tests['literal route wins over a matching mixed typed route'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $dynamic = new Route(
        'GET',
        '/accounts/{account_id:positive-int}/documents/{document_key:token}',
        $handler,
    );
    $literal = new Route('GET', '/accounts/7/documents/latest', $handler);
    $match = (new Router([$dynamic, $literal]))->match(
        new Request('GET', '/accounts/7/documents/latest'),
    );

    if ($match === null || $match->route !== $literal) {
        throw new RuntimeException('Expected the exact literal route to win.');
    }

    try {
        $match->pathParameters->positiveInteger('account_id');
    } catch (OutOfBoundsException) {
        return;
    }

    throw new RuntimeException('Expected a literal route match to carry no path parameters.');
};

$tests['route rejects repeated typed parameter names'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };

    foreach (['positive-int', 'token'] as $secondType) {
        try {
            new Route(
                'GET',
                '/accounts/{identifier:positive-int}/documents/{identifier:' . $secondType . '}',
                $handler,
            );
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected repeated typed parameter names to fail at startup.');
    }
};

$tests['router rejects overlapping typed declarations and inconsistent metadata'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $invalidRouteLists = [
        [
            new Route('GET', '/users/{user_id:positive-int}', $handler),
            new Route('GET', '/users/{user_id:positive-int}', $handler),
        ],
        [
            new Route('GET', '/users/{user_id:positive-int}', $handler),
            new Route('GET', '/users/{id:positive-int}', $handler),
        ],
        [
            new Route('GET', '/users/{user_id:positive-int}', $handler),
            new Route('POST', '/users/{id:positive-int}', $handler),
        ],
        [
            new Route('GET', '/items/{item_id:positive-int}', $handler),
            new Route('GET', '/items/{item_key:token}', $handler),
        ],
        [
            new Route('GET', '/items/{item_id:positive-int}', $handler),
            new Route('POST', '/items/{item_id:token}', $handler),
        ],
        [
            new Route('GET', '/accounts/{account_key:token}/documents/latest', $handler),
            new Route('GET', '/accounts/current/documents/{document_key:token}', $handler),
        ],
        [
            new Route(
                'GET',
                '/accounts/{account_id:positive-int}/documents/{document_key:token}',
                $handler,
            ),
            new Route(
                'GET',
                '/accounts/{id:positive-int}/documents/{key:token}',
                $handler,
            ),
        ],
    ];

    foreach ($invalidRouteLists as $routes) {
        try {
            new Router($routes);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('Expected overlapping typed routes to fail at startup.');
    }

    new Router([
        new Route(
            'GET',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
        new Route(
            'POST',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
    ]);
};

$tests['application passes immutable mixed path parameters to the handler'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            if (
                $request->method !== 'GET'
                || $request->path !== '/accounts/41/documents/Doc_9-z'
                || $request->query !== ['view' => 'summary']
                || $request->headers !== ['accept' => 'application/json']
            ) {
                throw new RuntimeException('Expected the routed request copy to preserve request input.');
            }

            return new Response(
                200,
                ['Content-Type' => 'text/plain'],
                $request->pathParameters->positiveInteger('account_id')
                    . ':'
                    . $request->pathParameters->token('document_key'),
            );
        }
    };
    $application = new Application(new Router([
        new Route(
            'GET',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
    ]));
    $request = new Request(
        'GET',
        '/accounts/41/documents/Doc_9-z',
        ['view' => 'summary'],
        '',
        ['accept' => 'application/json'],
    );
    $response = $application->handle($request);

    if ($response->body !== '41:Doc_9-z') {
        throw new RuntimeException('Expected the handler to receive both typed path parameters.');
    }

    try {
        $request->pathParameters->positiveInteger('account_id');
    } catch (OutOfBoundsException) {
        try {
            $request->pathParameters->token('document_key');
        } catch (OutOfBoundsException) {
            return;
        }
    }

    throw new RuntimeException('Expected Application to preserve the original immutable request.');
};

$tests['application preserves mixed route 405 order and rejects invalid values before handling'] = static function (): void {
    $handler = new class implements RequestHandler {
        public int $calls = 0;

        public function handle(Request $request): Response
        {
            $this->calls++;

            return new Response(204, [], '');
        }
    };
    $application = new Application(new Router([
        new Route(
            'POST',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
        new Route(
            'GET',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
        new Route(
            'DELETE',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
    ]));
    $notAllowed = $application->handle(
        new Request('PATCH', '/accounts/9/documents/Doc_9'),
    );
    $invalidInteger = $application->handle(
        new Request('PATCH', '/accounts/09/documents/Doc_9'),
    );
    $overflowingInteger = $application->handle(
        new Request('PATCH', '/accounts/' . PHP_INT_MAX . '0/documents/Doc_9'),
    );
    $encodedToken = $application->handle(
        new Request('PATCH', '/accounts/9/documents/%41'),
    );
    $oversizedToken = $application->handle(
        new Request('PATCH', '/accounts/9/documents/A' . str_repeat('_', 64)),
    );

    if (
        $notAllowed->status !== 405
        || $notAllowed->headers['Allow'] !== 'POST, GET, DELETE'
        || $notAllowed->headers['Cache-Control'] !== 'no-store'
        || $invalidInteger->status !== 404
        || $invalidInteger->headers['Cache-Control'] !== 'no-store'
        || $overflowingInteger->status !== 404
        || $overflowingInteger->headers['Cache-Control'] !== 'no-store'
        || $encodedToken->status !== 404
        || $encodedToken->headers['Cache-Control'] !== 'no-store'
        || $oversizedToken->status !== 404
        || $oversizedToken->headers['Cache-Control'] !== 'no-store'
        || $handler->calls !== 0
    ) {
        throw new RuntimeException('Expected indexed method discovery and pre-handler typed rejection.');
    }
};

$tests['allowed methods merge literal and parameterized registrations in order'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $router = new Router([
        new Route(
            'POST',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
        new Route('GET', '/accounts/7/documents/latest', $handler),
        new Route(
            'DELETE',
            '/accounts/{account_id:positive-int}/documents/{document_key:token}',
            $handler,
        ),
        new Route('POST', '/accounts/7/documents/latest', $handler),
    ]);

    if (
        $router->allowedMethodsForPath('/accounts/7/documents/latest')
            !== ['POST', 'GET', 'DELETE']
    ) {
        throw new RuntimeException('Expected ordered unique methods from literal and parameter routes.');
    }
};

$tests['router dispatches from a large explicit route table'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $routes = [];

    for ($index = 0; $index < 10_000; $index++) {
        $routes[] = new Route('GET', '/routes/' . $index, $handler);
    }

    $router = new Router($routes);
    $firstRoute = $router->match(new Request('GET', '/routes/0'));
    $middleRoute = $router->match(new Request('GET', '/routes/5000'));
    $lastRoute = $router->match(new Request('GET', '/routes/9999'));
    $missingRoute = $router->match(new Request('GET', '/routes/missing'));
    $allowedMethods = $router->allowedMethodsForPath('/routes/9999');

    if (
        $firstRoute?->route !== $routes[0]
        || $middleRoute?->route !== $routes[5_000]
        || $lastRoute?->route !== $routes[9_999]
        || $missingRoute !== null
        || $allowedMethods !== ['GET']
    ) {
        throw new RuntimeException('Expected exact lookup across 10,000 routes.');
    }
};

$tests['router indexes mixed paths in a large branching route table'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $routes = [];
    $targetRoutes = [];

    for ($index = 0; $index < 10_000; $index++) {
        $routes[] = new Route(
            'GET',
            '/accounts/account-'
                . $index
                . '/documents/{document_key:token}',
            $handler,
        );
        $targetRoute = new Route(
            'GET',
            '/accounts/{account_id:positive-int}/document-groups/'
                . $index
                . '/documents/{document_key:token}',
            $handler,
        );
        $routes[] = $targetRoute;
        $targetRoutes[] = $targetRoute;
    }

    $router = new Router($routes);
    $first = $router->match(
        new Request('GET', '/accounts/1/document-groups/0/documents/Doc_0'),
    );
    $middle = $router->match(
        new Request('GET', '/accounts/5001/document-groups/5000/documents/Doc_5000'),
    );
    $last = $router->match(
        new Request('GET', '/accounts/10000/document-groups/9999/documents/Doc_9999'),
    );
    $missing = $router->match(
        new Request('GET', '/accounts/1/document-groups/missing/documents/Doc_0'),
    );

    if (
        $first?->route !== $targetRoutes[0]
        || $first->pathParameters->positiveInteger('account_id') !== 1
        || $first->pathParameters->token('document_key') !== 'Doc_0'
        || $middle?->route !== $targetRoutes[5_000]
        || $middle->pathParameters->positiveInteger('account_id') !== 5_001
        || $middle->pathParameters->token('document_key') !== 'Doc_5000'
        || $last?->route !== $targetRoutes[9_999]
        || $last->pathParameters->positiveInteger('account_id') !== 10_000
        || $last->pathParameters->token('document_key') !== 'Doc_9999'
        || $missing !== null
        || $router->allowedMethodsForPath(
            '/accounts/10000/document-groups/9999/documents/Doc_9999',
        ) !== ['GET']
    ) {
        throw new RuntimeException('Expected indexed mixed matching across 20,000 routes.');
    }
};

$tests['router preserves allowed method registration order'] = static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $router = new Router([
        new Route('POST', '/items', $handler),
        new Route('GET', '/items', $handler),
        new Route('DELETE', '/items', $handler),
    ]);

    if ($router->allowedMethodsForPath('/items') !== ['POST', 'GET', 'DELETE']) {
        throw new RuntimeException('Expected allowed methods in explicit registration order.');
    }
};

$tests['item projection converts exact database rows into concrete identifiers'] = static function (): void {
    $nativeInteger = UserDetails::fromDatabaseRow(['name' => 'Ada', 'id' => 7]);
    $canonicalString = UserDetails::fromDatabaseRow(['id' => '8', 'name' => 'Grace']);

    if (
        $nativeInteger->id->value !== 7
        || $nativeInteger->name !== 'Ada'
        || $canonicalString->id->value !== 8
        || $canonicalString->name !== 'Grace'
    ) {
        throw new RuntimeException('Expected strict item rows to use concrete user identifiers.');
    }

    try {
        UserId::fromPositiveInteger(0);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Expected the concrete user identifier to reject zero.');
};

$tests['item projection rejects coercive and structurally invalid rows'] = static function (): void {
    $invalidRows = [
        ['id' => 0, 'name' => 'Ada'],
        ['id' => '01', 'name' => 'Ada'],
        ['id' => (string) PHP_INT_MAX . '0', 'name' => 'Ada'],
        ['id' => 7, 'name' => ''],
        ['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ['id' => 7],
    ];

    foreach ($invalidRows as $row) {
        try {
            UserDetails::fromDatabaseRow($row);
        } catch (UnexpectedValueException) {
            continue;
        }

        throw new RuntimeException('Expected an invalid item projection row to be rejected.');
    }
};

$tests['database projection parses documented integer representations'] = static function (): void {
    $nativeInteger = UserSummary::fromDatabaseRow(['name' => 'Ada', 'id' => 7]);
    $canonicalString = UserSummary::fromDatabaseRow(['id' => '8', 'name' => 'Grace']);

    if (
        $nativeInteger->id !== 7
        || $nativeInteger->name !== 'Ada'
        || $canonicalString->id !== 8
        || $canonicalString->name !== 'Grace'
    ) {
        throw new RuntimeException('Expected strict database rows to become typed projections.');
    }
};

$tests['database projection rejects coercive identifiers'] = static function (): void {
    $invalidIdentifiers = [
        '',
        ' ',
        '12abc',
        '1e3',
        '7.0',
        '7e0',
        '7x',
        '+7',
        ' 7',
        '01',
        '0',
        '-1',
        0,
        -1,
        (string) PHP_INT_MAX . '0',
        null,
        true,
        12.0,
        [],
        new stdClass(),
    ];

    foreach ($invalidIdentifiers as $identifier) {
        try {
            UserSummary::fromDatabaseRow(['id' => $identifier, 'name' => 'Ada']);
        } catch (UnexpectedValueException) {
            continue;
        }

        throw new RuntimeException(
            sprintf('Expected identifier of type %s to be rejected.', get_debug_type($identifier)),
        );
    }
};

$tests['database projection rejects missing unknown and invalid fields'] = static function (): void {
    $invalidRows = [
        ['id' => 7],
        ['id' => 7, 'name' => 'Ada', 'is_admin' => true],
        ['id' => 7, 'name' => ''],
        ['id' => 7, 'name' => null],
        ['id' => 7, 'name' => true],
        ['id' => 7, 'name' => []],
        ['id' => 7, 'name' => new stdClass()],
    ];

    foreach ($invalidRows as $row) {
        try {
            UserSummary::fromDatabaseRow($row);
        } catch (UnexpectedValueException) {
            continue;
        }

        throw new RuntimeException('Expected an invalid database row to be rejected.');
    }
};

$tests['user activity projection parses exact aggregate rows'] = static function (): void {
    $nativeValues = UserActivitySummary::fromDatabaseRow([
        'id' => 7,
        'name' => 'Ada',
        'event_count' => 2,
    ]);
    $canonicalStrings = UserActivitySummary::fromDatabaseRow([
        'id' => '8',
        'name' => 'Grace',
        'event_count' => '0',
    ]);

    if (
        $nativeValues->id !== 7
        || $nativeValues->eventCount !== 2
        || $canonicalStrings->id !== 8
        || $canonicalStrings->eventCount !== 0
    ) {
        throw new RuntimeException('Expected aggregate rows to become typed user activity summaries.');
    }
};

$tests['user activity projection rejects malformed aggregate rows'] = static function (): void {
    $invalidRows = [
        ['id' => 7, 'name' => 'Ada'],
        ['id' => 7, 'name' => 'Ada', 'event_count' => 1, 'unknown' => true],
        ['id' => 0, 'name' => 'Ada', 'event_count' => 1],
        ['id' => '01', 'name' => 'Ada', 'event_count' => 1],
        ['id' => 7, 'name' => '', 'event_count' => 1],
        ['id' => 7, 'name' => 'Ada', 'event_count' => -1],
        ['id' => 7, 'name' => 'Ada', 'event_count' => '-1'],
        ['id' => 7, 'name' => 'Ada', 'event_count' => '01'],
        ['id' => 7, 'name' => 'Ada', 'event_count' => 1.0],
        ['id' => 7, 'name' => 'Ada', 'event_count' => null],
    ];

    foreach ($invalidRows as $row) {
        try {
            UserActivitySummary::fromDatabaseRow($row);
        } catch (UnexpectedValueException) {
            continue;
        }

        throw new RuntimeException('Expected a malformed aggregate row to be rejected.');
    }
};

$tests['list users page request parses only one canonical continuation'] = static function (): void {
    $firstPage = ListUsersPageRequest::fromQuery([]);
    $continuedPage = ListUsersPageRequest::fromQuery(['after_user_id' => '1']);
    $maximumPage = ListUsersPageRequest::fromQuery([
        'after_user_id' => (string) PHP_INT_MAX,
    ]);

    if (
        $firstPage->afterUserId !== null
        || $continuedPage->afterUserId !== 1
        || $maximumPage->afterUserId !== PHP_INT_MAX
    ) {
        throw new RuntimeException('Expected canonical list continuation input to become typed page requests.');
    }

    $invalidQueries = [
        ['after_user_id' => ''],
        ['after_user_id' => '0'],
        ['after_user_id' => '01'],
        ['after_user_id' => '+1'],
        ['after_user_id' => '-1'],
        ['after_user_id' => '1.0'],
        ['after_user_id' => '1e0'],
        ['after_user_id' => ' 1'],
        ['after_user_id' => '１'],
        ['after_user_id' => (string) PHP_INT_MAX . '0'],
        ['after_user_id' => 1],
        ['after_user_id' => 1.0],
        ['after_user_id' => true],
        ['after_user_id' => null],
        ['after_user_id' => []],
        ['after_user_id' => new stdClass()],
        ['cursor' => '1'],
        ['after_user_id' => '1', 'limit' => '50'],
    ];

    foreach ($invalidQueries as $query) {
        try {
            ListUsersPageRequest::fromQuery($query);
        } catch (InvalidRequest) {
            continue;
        }

        throw new RuntimeException('Expected malformed or unknown list continuation input to be rejected.');
    }
};

$tests['list users rejects invalid continuation before database work'] = static function (): void {
    $databasePath = createUserDatabaseFixture('list-invalid-continuation', 2, true);
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $application = new Application(new Router([
        new Route(
            'GET',
            '/users',
            new ListUsersHandler(
                Connection::connect('sqlite:' . $databasePath, $budget, $trace),
            ),
        ),
    ]));
    $boundary = new RequestBoundary(
        requestReaderForBody('', 8_192),
        $application,
        exampleErrorResponseRegistry(),
    );
    $invalidQueries = [
        ['after_user_id' => '01'],
        ['after_user_id' => (string) PHP_INT_MAX . '0'],
        ['after_user_id' => ['1']],
        ['unknown' => '1'],
        ['after_user_id' => '1', 'limit' => '50'],
    ];

    foreach ($invalidQueries as $query) {
        $response = $boundary->handle(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users?after_user_id=invalid'],
            $query,
        );

        if (
            $response->status !== 400
            || $response->headers !== [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]
            || $response->body !== "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n"
        ) {
            throw new RuntimeException('Expected invalid list continuation to map to the public 400 response.');
        }
    }

    $summary = $trace->snapshot();

    if (
        $budget->used() !== 0
        || $summary['statements'] !== 0
        || $summary['failures'] !== 0
        || $summary['tracked_fingerprints'] !== 0
        || $summary['queries'] !== []
    ) {
        throw new RuntimeException('Expected invalid list continuation to perform zero database work.');
    }
};

$tests['list users accepts one canonical runtime continuation'] = static function (): void {
    $databasePath = createUserDatabaseFixture('list-valid-continuation', 2, true);
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $application = new Application(new Router([
        new Route(
            'GET',
            '/users',
            new ListUsersHandler(
                Connection::connect('sqlite:' . $databasePath, $budget, $trace),
            ),
        ),
    ]));
    $boundary = new RequestBoundary(
        requestReaderForBody('', 8_192),
        $application,
        exampleErrorResponseRegistry(),
    );
    $continued = $boundary->handle(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users?after_user_id=1'],
        ['after_user_id' => '1'],
    );

    if (
        $continued->status !== 200
        || $continued->body !== "{\"users\":[{\"id\":2,\"name\":\"User 2\",\"event_count\":2}],\"next_after_user_id\":null}\n"
        || $budget->used() !== 1
        || $trace->snapshot()['statements'] !== 1
    ) {
        throw new RuntimeException('Expected one valid runtime continuation to reach the bounded list query.');
    }
};

$tests['HTTP command parses one exact JSON object'] = static function (): void {
    $command = CreateUserCommand::fromJson(
        '{"email":"ada@example.com","name":"Ada Lovelace"}',
    );
    $unicodeCommand = CreateUserCommand::fromJson(
        '{"name":"Jos\u00e9","email":"jose@example.com"}',
    );

    if (
        $command->name !== 'Ada Lovelace'
        || $command->email !== 'ada@example.com'
        || $unicodeCommand->name !== 'José'
    ) {
        throw new RuntimeException('Expected strict JSON to become a typed command.');
    }
};

$tests['HTTP command rejects malformed coercive and unknown input'] = static function (): void {
    $tooDeep = str_repeat('{"value":', 17) . 'null' . str_repeat('}', 17);
    $invalidBodies = [
        '',
        '{',
        '{}{}',
        '"text"',
        '7',
        'true',
        'null',
        '[]',
        "\xB1\x31",
        $tooDeep,
        str_repeat('x', 2_049),
        '{"name":"Ada"}',
        '{"name":"Ada","email":"ada@example.com","is_admin":true}',
        '{"Name":"Ada","email":"ada@example.com"}',
        '{"name":"","email":"ada@example.com"}',
        '{"name":"   ","email":"ada@example.com"}',
        '{"name":null,"email":"ada@example.com"}',
        '{"name":7,"email":"ada@example.com"}',
        '{"name":true,"email":"ada@example.com"}',
        '{"name":[],"email":"ada@example.com"}',
        '{"name":{},"email":"ada@example.com"}',
        '{"name":" Ada","email":"ada@example.com"}',
        '{"name":"Ada","email":null}',
        '{"name":"Ada","email":false}',
        '{"name":"Ada","email":[]}',
        '{"name":"Ada","email":{}}',
        '{"name":"Ada","email":"not-an-email"}',
        '{"name":"Ada","email":" ada@example.com"}',
    ];

    foreach ($invalidBodies as $body) {
        try {
            CreateUserCommand::fromJson($body);
        } catch (InvalidRequest | RequestBodyTooLarge) {
            continue;
        }

        throw new RuntimeException('Expected malformed or coercive JSON input to be rejected.');
    }
};

$tests['example request boundary maps client failures before database work'] = static function (): void {
    $databasePath = createUserDatabaseFixture('request-client-failures', 0, false);
    $readBudget = new QueryBudget(1);
    $getBudget = new QueryBudget(1);
    $writeBudget = new QueryBudget(2);
    $writeTrace = new QueryTrace(2);
    $dsn = 'sqlite:' . $databasePath;
    $application = new Application(new Router(Routes::create(
        Connection::connect($dsn, $readBudget, new QueryTrace(1)),
        Connection::connect($dsn, $getBudget, new QueryTrace(1)),
        Connection::connect($dsn, $writeBudget, $writeTrace),
    )));
    $registry = exampleErrorResponseRegistry();
    $invalidBody = '{"name":"Ada"}';
    $invalidResponse = (new RequestBoundary(
        requestReaderForBody($invalidBody, 8_192),
        $application,
        $registry,
    ))->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) strlen($invalidBody),
        ],
        [],
    );
    $validBody = '{"name":"Ada","email":"ada@example.com"}';
    $unsupportedResponse = (new RequestBoundary(
        requestReaderForBody($validBody, 8_192),
        $application,
        $registry,
    ))->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users',
            'CONTENT_LENGTH' => (string) strlen($validBody),
        ],
        [],
    );
    $endpointOversizeBody = str_repeat('x', 2_049);
    $tooLargeResponse = (new RequestBoundary(
        requestReaderForBody($endpointOversizeBody, 8_192),
        $application,
        $registry,
    ))->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) strlen($endpointOversizeBody),
        ],
        [],
    );
    $outerTooLargeResponse = (new RequestBoundary(
        new RequestReader(8_192, __DIR__ . '/../tmp/request-bodies/not-read.body'),
        $application,
        $registry,
    ))->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '8193',
        ],
        [],
    );

    if (
        $invalidResponse->status !== 400
        || $invalidResponse->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $invalidResponse->body !== "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n"
        || str_contains($invalidResponse->body, 'exactly name and email')
        || $unsupportedResponse->status !== 415
        || $unsupportedResponse->headers !== $invalidResponse->headers
        || $unsupportedResponse->body !== "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type must be application/json.\"}}\n"
        || $tooLargeResponse->status !== 413
        || $tooLargeResponse->headers !== $invalidResponse->headers
        || $tooLargeResponse->body !== "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n"
        || $outerTooLargeResponse->status !== 413
        || $outerTooLargeResponse->headers !== $invalidResponse->headers
        || $outerTooLargeResponse->body !== $tooLargeResponse->body
        || $readBudget->used() !== 0
        || $getBudget->used() !== 0
        || $writeBudget->used() !== 0
        || $writeTrace->snapshot()['statements'] !== 0
    ) {
        throw new RuntimeException('Expected explicit public client failures before database work.');
    }
};

$tests['user routes execute bounded reads and one transactional write end to end'] = static function (): void {
    $databasePath = createUserDatabaseFixture('user-routes', 0, false);
    $readBudget = new QueryBudget(1);
    $readTrace = new QueryTrace(1);
    $getBudget = new QueryBudget(1);
    $getTrace = new QueryTrace(1);
    $writeBudget = new QueryBudget(2);
    $writeTrace = new QueryTrace(2);
    $dsn = 'sqlite:' . $databasePath;
    $application = new Application(new Router(Routes::create(
        Connection::connect($dsn, $readBudget, $readTrace),
        Connection::connect($dsn, $getBudget, $getTrace),
        Connection::connect($dsn, $writeBudget, $writeTrace),
    )));

    $created = $application->handle(new Request(
        'POST',
        '/users',
        body: '{"name":"Ada Lovelace","email":"ada@example.com"}',
        headers: ['content-type' => 'application/json'],
    ));
    $got = $application->handle(new Request('GET', '/users/1'));
    $listed = $application->handle(new Request('GET', '/users'));

    if (
        $created->status !== 201
        || $created->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $created->body !== "{\"user\":{\"name\":\"Ada Lovelace\",\"email\":\"ada@example.com\"}}\n"
        || $got->status !== 200
        || $got->headers !== $created->headers
        || $got->body !== "{\"user\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n"
        || $listed->status !== 200
        || $listed->headers !== $created->headers
        || $listed->body !== "{\"users\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"next_after_user_id\":null}\n"
        || $writeBudget->used() !== 2
        || $readBudget->used() !== 1
        || $getBudget->used() !== 1
        || $writeTrace->snapshot()['statements'] !== 2
        || $readTrace->snapshot()['statements'] !== 1
        || $getTrace->snapshot()['statements'] !== 1
    ) {
        throw new RuntimeException('Expected explicit user routes with bounded reads and writes.');
    }
};

$tests['user list page keeps one query across dataset sizes'] = static function (): void {
    $smallPath = createUserDatabaseFixture('read-small', 2, true);
    $largePath = createUserDatabaseFixture('read-large', 500, true);
    $small = runListUsersPageScenario($smallPath, null);
    $large = runListUsersPageScenario($largePath, null);

    if (
        $small['ids'] !== [1, 2]
        || $small['event_counts'] !== [2, 2]
        || $small['next_after_user_id'] !== null
        || $large['ids'] !== range(1, 50)
        || $large['next_after_user_id'] !== '50'
        || $small['used'] !== 1
        || $large['used'] !== 1
        || $small['statements'] !== 1
        || $large['statements'] !== 1
        || $small['repeated_fingerprints'] !== 0
        || $large['repeated_fingerprints'] !== 0
        || $small['maximum_executions'] !== 1
        || $large['maximum_executions'] !== 1
        || $small['truncated']
        || $large['truncated']
    ) {
        throw new RuntimeException('Expected each bounded list page to remain one query at scale.');
    }
};

$tests['user list continuation handles exact and lookahead page boundaries'] = static function (): void {
    $fullPath = createUserDatabaseFixture('list-exact-page', 50, true);
    $lookaheadPath = createUserDatabaseFixture('list-lookahead-page', 51, false);
    $full = runListUsersPageScenario($fullPath, null);
    $lookahead = runListUsersPageScenario($lookaheadPath, null);
    $deletedRows = Connection::connect(
        'sqlite:' . $lookaheadPath,
        new QueryBudget(1),
        new QueryTrace(1),
    )->executeStatement(
        'DELETE FROM users WHERE users.id = :user_id',
        ['user_id' => 50],
    );
    $continued = runListUsersPageScenario($lookaheadPath, '50');

    if (
        $full['ids'] !== range(1, 50)
        || $full['next_after_user_id'] !== null
        || $lookahead['ids'] !== range(1, 50)
        || $lookahead['next_after_user_id'] !== '50'
        || $deletedRows !== 1
        || $continued['ids'] !== [51]
        || $continued['next_after_user_id'] !== null
    ) {
        throw new RuntimeException('Expected lookahead continuation to survive deletion without skipping row 51.');
    }
};

$tests['user list continuation traverses large data without gaps or duplicates'] = static function (): void {
    $databasePath = createUserDatabaseFixture('list-continuation', 125, true);
    $first = runListUsersPageScenario($databasePath, null);
    $second = runListUsersPageScenario($databasePath, '50');
    $third = runListUsersPageScenario($databasePath, '100');
    $beyond = runListUsersPageScenario($databasePath, '125');
    $ids = [...$first['ids'], ...$second['ids'], ...$third['ids']];
    $eventCounts = [
        ...$first['event_counts'],
        ...$second['event_counts'],
        ...$third['event_counts'],
    ];

    foreach ([$first, $second, $third, $beyond] as $page) {
        if (
            $page['used'] !== 1
            || $page['statements'] !== 1
            || $page['failures'] !== 0
            || $page['tracked_fingerprints'] !== 1
            || $page['repeated_fingerprints'] !== 0
            || $page['maximum_executions'] !== 1
            || $page['truncated']
            || $page['untracked_statements'] !== 0
        ) {
            throw new RuntimeException('Expected every continuation request to execute one bounded statement.');
        }
    }

    if (
        count($first['ids']) !== 50
        || count($second['ids']) !== 50
        || count($third['ids']) !== 25
        || $first['next_after_user_id'] !== '50'
        || $second['next_after_user_id'] !== '100'
        || $third['next_after_user_id'] !== null
        || $ids !== range(1, 125)
        || count(array_unique($ids)) !== 125
        || array_unique($eventCounts) !== [2]
        || $beyond['ids'] !== []
        || $beyond['next_after_user_id'] !== null
    ) {
        throw new RuntimeException('Expected stable keyset continuation with no gaps or duplicates.');
    }
};

$tests['user item endpoint keeps one query across dataset sizes'] = static function (): void {
    $smallPath = createUserDatabaseFixture('item-read-small', 2, false);
    $largePath = createUserDatabaseFixture('item-read-large', 500, false);
    $smallBudget = new QueryBudget(1);
    $smallTrace = new QueryTrace(1);
    $largeBudget = new QueryBudget(1);
    $largeTrace = new QueryTrace(1);
    $smallApplication = new Application(new Router([
        new Route(
            'GET',
            '/users/{user_id:positive-int}',
            new GetUserHandler(
                Connection::connect('sqlite:' . $smallPath, $smallBudget, $smallTrace),
            ),
        ),
    ]));
    $largeApplication = new Application(new Router([
        new Route(
            'GET',
            '/users/{user_id:positive-int}',
            new GetUserHandler(
                Connection::connect('sqlite:' . $largePath, $largeBudget, $largeTrace),
            ),
        ),
    ]));

    $smallResponse = $smallApplication->handle(new Request('GET', '/users/2'));
    $largeResponse = $largeApplication->handle(new Request('GET', '/users/500'));
    $smallSummary = $smallTrace->snapshot();
    $largeSummary = $largeTrace->snapshot();

    if (
        $smallResponse->status !== 200
        || $smallResponse->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $smallResponse->body !== "{\"user\":{\"id\":2,\"name\":\"User 2\"}}\n"
        || $largeResponse->status !== 200
        || $largeResponse->headers !== $smallResponse->headers
        || $largeResponse->body !== "{\"user\":{\"id\":500,\"name\":\"User 500\"}}\n"
        || $smallBudget->used() !== 1
        || $largeBudget->used() !== 1
        || $smallSummary['statements'] !== 1
        || $largeSummary['statements'] !== 1
        || $smallSummary['repeated_fingerprints'] !== 0
        || $largeSummary['repeated_fingerprints'] !== 0
        || $smallSummary['maximum_executions_per_fingerprint'] !== 1
        || $largeSummary['maximum_executions_per_fingerprint'] !== 1
        || $smallSummary['truncated']
        || $largeSummary['truncated']
    ) {
        throw new RuntimeException('Expected the typed item read to remain one query at scale.');
    }
};

$tests['user item route separates missing records from malformed identifiers'] = static function (): void {
    $databasePath = createUserDatabaseFixture('item-read-failures', 2, false);
    $missingBudget = new QueryBudget(1);
    $missingTrace = new QueryTrace(1);
    $missingApplication = new Application(new Router([
        new Route(
            'GET',
            '/users/{user_id:positive-int}',
            new GetUserHandler(
                Connection::connect(
                    'sqlite:' . $databasePath,
                    $missingBudget,
                    $missingTrace,
                ),
            ),
        ),
    ]));
    $missing = $missingApplication->handle(new Request('GET', '/users/99'));

    if (
        $missing->status !== 404
        || $missing->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $missing->body !== "{\"error\":{\"code\":\"user_not_found\",\"message\":\"User was not found.\"}}\n"
        || $missingBudget->used() !== 1
        || $missingTrace->snapshot()['statements'] !== 1
    ) {
        throw new RuntimeException('Expected a valid absent identifier to perform one bounded item query.');
    }

    $malformedBudget = new QueryBudget(1);
    $malformedTrace = new QueryTrace(1);
    $malformedApplication = new Application(new Router([
        new Route(
            'GET',
            '/users/{user_id:positive-int}',
            new GetUserHandler(
                Connection::connect(
                    'sqlite:' . $databasePath,
                    $malformedBudget,
                    $malformedTrace,
                ),
            ),
        ),
    ]));
    $malformedPaths = [
        '/users/0',
        '/users/01',
        '/users/-1',
        '/users/%31',
        '/users/1%2Fdetails',
        '/users/' . PHP_INT_MAX . '0',
        '/users/' . str_repeat('9', strlen((string) PHP_INT_MAX)),
        '/users/1/details',
    ];

    foreach ($malformedPaths as $path) {
        $response = $malformedApplication->handle(new Request('GET', $path));

        if (
            $response->status !== 404
            || $response->headers['Cache-Control'] !== 'no-store'
            || $response->body !== "Not Found\n"
        ) {
            throw new RuntimeException("Expected malformed item identifier to miss routing: {$path}");
        }
    }

    if ($malformedBudget->used() !== 0 || $malformedTrace->snapshot()['statements'] !== 0) {
        throw new RuntimeException('Expected malformed item identifiers to perform no database work.');
    }
};

$tests['transactional user creation keeps two queries across dataset sizes'] = static function (): void {
    $empty = runCreateUserScenario('write-empty', 0);
    $large = runCreateUserScenario('write-large', 500);

    if (
        $empty !== $large
        || $empty['status'] !== 201
        || $empty['body'] !== "{\"user\":{\"name\":\"New User\",\"email\":\"new@example.com\"}}\n"
        || $empty['used'] !== 2
        || $empty['statements'] !== 2
        || $empty['repeated_fingerprints'] !== 0
        || $empty['maximum_executions'] !== 1
        || $empty['created_users'] !== 1
        || $empty['created_events'] !== 1
    ) {
        throw new RuntimeException('Expected transactional creation to keep two writes at scale.');
    }
};

$tests['transactional user creation rolls back when its budget rejects the event write'] = static function (): void {
    $databasePath = createUserDatabaseFixture('write-rollback', 0, false);
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $handler = new CreateUserHandler($connection);
    $budgetFailed = false;

    try {
        $handler->handle(new Request(
            'POST',
            '/users',
            body: '{"name":"Ada","email":"ada@example.com"}',
            headers: ['content-type' => 'application/json'],
        ));
    } catch (QueryBudgetExceeded) {
        $budgetFailed = true;
    }

    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(2),
        new QueryTrace(2),
    );
    $userCount = $verification->selectOneRow('SELECT COUNT(users.id) AS row_count FROM users');
    $eventCount = $verification->selectOneRow('SELECT COUNT(user_events.id) AS row_count FROM user_events');

    if (
        !$budgetFailed
        || $connection->inTransaction()
        || $budget->used() !== 1
        || $trace->snapshot()['statements'] !== 1
        || ($userCount['row_count'] ?? null) !== 0
        || ($eventCount['row_count'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected the first write to roll back after the second exceeds its budget.');
    }
};

$tests['transactional user creation rolls back when the event statement fails'] = static function (): void {
    $databasePath = createUserDatabaseFixture('write-statement-failure', 0, false);
    $schemaConnection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $schemaConnection->executeStatement(
        <<<'SQL'
            CREATE TRIGGER reject_user_created
            BEFORE INSERT ON user_events
            WHEN NEW.event_type = 'user.created'
            BEGIN
                SELECT RAISE(ABORT, 'user.created rejected');
            END
            SQL,
    );

    $budget = new QueryBudget(2);
    $trace = new QueryTrace(2);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $handler = new CreateUserHandler($connection);
    $statementFailed = false;

    try {
        $handler->handle(new Request(
            'POST',
            '/users',
            body: '{"name":"Ada","email":"ada@example.com"}',
            headers: ['content-type' => 'application/json'],
        ));
    } catch (PDOException) {
        $statementFailed = true;
    }

    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(2),
        new QueryTrace(2),
    );
    $userCount = $verification->selectOneRow('SELECT COUNT(users.id) AS row_count FROM users');
    $eventCount = $verification->selectOneRow('SELECT COUNT(user_events.id) AS row_count FROM user_events');
    $summary = $trace->snapshot();

    if (
        !$statementFailed
        || $connection->inTransaction()
        || $budget->used() !== 2
        || $summary['statements'] !== 2
        || $summary['failures'] !== 1
        || ($userCount['row_count'] ?? null) !== 0
        || ($eventCount['row_count'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected an executed event failure to roll back the user insert.');
    }
};

$tests['transactional user creation rejects invalid input before database work'] = static function (): void {
    $databasePath = createUserDatabaseFixture('write-invalid', 0, false);
    $budget = new QueryBudget(2);
    $trace = new QueryTrace(2);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $handler = new CreateUserHandler($connection);
    $inputFailed = false;

    try {
        $handler->handle(new Request(
            'POST',
            '/users',
            body: '{"name":"Ada"}',
            headers: ['content-type' => 'application/json'],
        ));
    } catch (InvalidRequest) {
        $inputFailed = true;
    }

    if (
        !$inputFailed
        || $connection->inTransaction()
        || $budget->used() !== 0
        || $trace->snapshot()['statements'] !== 0
    ) {
        throw new RuntimeException('Expected invalid input to fail before opening a transaction or querying.');
    }
};

$tests['connection binds named values and enforces its budget'] = static function (): void {
    $budget = new QueryBudget(3);
    $trace = new QueryTrace(3);
    $connection = Connection::connect('sqlite::memory:', $budget, $trace);

    $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $connection->executeStatement(
        'INSERT INTO users (id, name) VALUES (:id, :name)',
        ['id' => 7, 'name' => 'Ada'],
    );
    $row = $connection->selectOneRow(
        'SELECT id, name FROM users WHERE id = :id',
        ['id' => 7],
    );

    if ($row !== ['id' => 7, 'name' => 'Ada'] || $budget->used() !== 3) {
        throw new RuntimeException('Expected a typed row and three recorded statements.');
    }

    $user = UserSummary::fromDatabaseRow($row);

    if ($user->id !== 7 || $user->name !== 'Ada') {
        throw new RuntimeException('Expected the raw PDO row to be parsed immediately.');
    }

    $budgetWasExceeded = false;

    try {
        $connection->selectOneRow('SELECT id, name FROM users WHERE id = :id', ['id' => 7]);
    } catch (QueryBudgetExceeded) {
        $budgetWasExceeded = true;
    }

    if (!$budgetWasExceeded || $trace->snapshot()['statements'] !== 3) {
        throw new RuntimeException('Expected the fourth statement to exceed the budget without being traced.');
    }
};

$tests['connection keeps SQL-looking bound text outside statement structure'] = static function (): void {
    $budget = new QueryBudget(5);
    $trace = new QueryTrace(5);
    $connection = Connection::connect('sqlite::memory:', $budget, $trace);
    $payload = "Robert'); DELETE FROM users; -- 雪";

    $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $connection->executeStatement(
        'INSERT INTO users (id, name) VALUES (:id, :name)',
        ['id' => 1, 'name' => 'ordinary'],
    );
    $connection->executeStatement(
        'INSERT INTO users (id, name) VALUES (:id, :name)',
        ['id' => 2, 'name' => $payload],
    );
    $payloadRow = $connection->selectOneRow(
        'SELECT id, name FROM users WHERE name = :name',
        ['name' => $payload],
    );
    $countRow = $connection->selectOneRow('SELECT COUNT(id) AS row_count FROM users');
    $summary = $trace->snapshot();
    $traceJson = json_encode($summary, JSON_THROW_ON_ERROR);

    if (
        $payloadRow !== ['id' => 2, 'name' => $payload]
        || ($countRow['row_count'] ?? null) !== 2
        || $budget->used() !== 5
        || $summary['statements'] !== 5
        || $summary['failures'] !== 0
        || $summary['repeated_fingerprints'] !== 1
        || $summary['maximum_executions_per_fingerprint'] !== 2
        || str_contains($traceJson, $payload)
    ) {
        throw new RuntimeException('Expected SQL-looking text to remain bound data and stay out of query traces.');
    }
};

$tests['connection accepts portable parameter names and rejects invalid or duplicate names before database work'] = static function (): void {
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite::memory:', $budget, $trace);
    $requireInvalidName = static function (Connection $checkedConnection, string $invalidName): void {
        try {
            $checkedConnection->selectOneRow('SELECT :value AS value', [$invalidName => 7]);
        } catch (InvalidArgumentException) {
            return;
        }

        throw new RuntimeException("Expected nonportable SQL parameter name to be rejected: {$invalidName}.");
    };

    $requireInvalidName($connection, '');
    $requireInvalidName($connection, ':');
    $requireInvalidName($connection, '1value');
    $requireInvalidName($connection, 'user-id');
    $requireInvalidName($connection, 'user id');

    try {
        $connection->selectOneRow(
            'SELECT :value AS value',
            ['value' => 1, ':value' => 2],
        );
        throw new RuntimeException('Expected normalized duplicate SQL parameter names to be rejected.');
    } catch (InvalidArgumentException) {
    }

    if ($budget->used() !== 0 || $trace->snapshot()['statements'] !== 0) {
        throw new RuntimeException('Invalid and duplicate parameter names must fail before database work is counted or traced.');
    }

    $row = $connection->selectOneRow('SELECT :value AS value', [':value' => 7]);
    $value = $row['value'] ?? null;

    if ($value !== 7 && $value !== '7') {
        throw new RuntimeException('Expected an optional leading colon and portable parameter identifier.');
    }
};

$tests['query trace detects repetition without exposing SQL or parameters'] = static function (): void {
    $budget = new QueryBudget(4);
    $trace = new QueryTrace(4);
    $connection = Connection::connect('sqlite::memory:', $budget, $trace);

    $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $connection->executeStatement(
        'INSERT INTO users (id, name) VALUES (:first_id, :first_name), (:second_id, :second_name)',
        ['first_id' => 7, 'first_name' => 'Ada', 'second_id' => 8, 'second_name' => 'Grace'],
    );
    $connection->selectOneRow('SELECT id, name FROM users WHERE id = :id', ['id' => 7]);
    $connection->selectOneRow('SELECT id, name FROM users WHERE id = :id', ['id' => 8]);
    $summary = $trace->snapshot();
    $json = json_encode($summary, JSON_THROW_ON_ERROR);

    if (
        $summary['schema_version'] !== 1
        || $summary['event'] !== 'database.query_summary'
        || $summary['statements'] !== 4
        || $summary['repeated_fingerprints'] !== 1
        || $summary['maximum_executions_per_fingerprint'] !== 2
        || count($summary['queries']) !== 3
        || $summary['queries'][2]['executions'] !== 2
        || !str_starts_with($summary['queries'][2]['fingerprint'], 'sha256:')
        || strlen($summary['queries'][2]['fingerprint']) !== 71
        || $summary['slowest_execute_duration_us'] < 0
        || $summary['total_execute_duration_us'] < $summary['slowest_execute_duration_us']
        || str_contains($json, 'SELECT')
        || str_contains($json, 'first_name')
        || str_contains($json, 'Ada')
        || str_contains($json, 'Grace')
    ) {
        throw new RuntimeException('Expected a redacted structured repetition summary.');
    }
};

$tests['query trace records database failures before rethrowing them'] = static function (): void {
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite::memory:', new QueryBudget(1), $trace);
    $databaseFailed = false;

    try {
        $connection->executeStatement('INSERT INTO missing_users (id) VALUES (:id)', ['id' => 7]);
    } catch (PDOException) {
        $databaseFailed = true;
    }

    $summary = $trace->snapshot();
    $json = json_encode($summary, JSON_THROW_ON_ERROR);

    if (
        !$databaseFailed
        || $summary['statements'] !== 1
        || $summary['failures'] !== 1
        || $summary['queries'][0]['failures'] !== 1
        || str_contains($json, 'missing_users')
        || str_contains($json, 'no such table')
    ) {
        throw new RuntimeException('Expected the failed statement to be traced and rethrown.');
    }
};

$tests['query trace requires a positive fingerprint bound'] = static function (): void {
    try {
        new QueryTrace(0);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Expected a non-positive query trace bound to fail.');
};

$tests['query trace bounds retained fingerprint details'] = static function (): void {
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite::memory:', new QueryBudget(3), $trace);

    $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $connection->executeStatement(
        'INSERT INTO users (id, name) VALUES (:id, :name)',
        ['id' => 7, 'name' => 'Ada'],
    );
    $connection->selectOneRow('SELECT id, name FROM users WHERE id = :id', ['id' => 7]);
    $summary = $trace->snapshot();

    if (
        $summary['tracked_fingerprints'] !== 1
        || !$summary['truncated']
        || $summary['untracked_statements'] !== 2
    ) {
        throw new RuntimeException('Expected fingerprint detail retention to remain bounded.');
    }
};

function requestReaderForBody(string $body, int $maximumBodyBytes): RequestReader
{
    $directory = __DIR__ . '/../tmp/request-bodies';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the request-body fixture directory.');
    }

    $path = $directory . '/' . hash('sha256', $body) . '.body';
    $writtenBytes = file_put_contents($path, $body, LOCK_EX);

    if (!is_int($writtenBytes) || $writtenBytes !== strlen($body)) {
        throw new RuntimeException('Unable to write the complete request-body fixture.');
    }

    return new RequestReader($maximumBodyBytes, $path);
}

function exampleErrorResponseRegistry(): ErrorResponseRegistry
{
    $headers = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
    ];

    return new ErrorResponseRegistry([
        InvalidRequest::class => new Response(
            400,
            $headers,
            "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ),
        RequestBodyTooLarge::class => new Response(
            413,
            $headers,
            "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
        ),
        UnsupportedMediaType::class => new Response(
            415,
            $headers,
            "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type must be application/json.\"}}\n",
        ),
    ]);
}

/**
 * @return array{
 *     ids: list<int>,
 *     event_counts: list<int>,
 *     next_after_user_id: string|null,
 *     used: int,
 *     statements: int,
 *     failures: int,
 *     tracked_fingerprints: int,
 *     repeated_fingerprints: int,
 *     maximum_executions: int,
 *     truncated: bool,
 *     untracked_statements: int
 * }
 */
function runListUsersPageScenario(string $databasePath, ?string $afterUserId): array
{
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $handler = new ListUsersHandler(
        Connection::connect('sqlite:' . $databasePath, $budget, $trace),
    );
    $query = $afterUserId === null ? [] : ['after_user_id' => $afterUserId];
    $response = $handler->handle(new Request('GET', '/users', $query));
    $decoded = json_decode($response->body, true, 64, JSON_THROW_ON_ERROR);

    if (
        $response->status !== 200
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || !is_array($decoded)
        || count($decoded) !== 2
        || !array_key_exists('users', $decoded)
        || !array_key_exists('next_after_user_id', $decoded)
    ) {
        throw new RuntimeException('List users returned an invalid page response.');
    }

    $userValues = $decoded['users'];
    $nextAfterUserId = $decoded['next_after_user_id'];

    if (!is_array($userValues) || !array_is_list($userValues)) {
        throw new RuntimeException('List users returned an invalid users collection.');
    }

    if (
        $nextAfterUserId !== null
        && (
            !is_string($nextAfterUserId)
            || preg_match('/^[1-9][0-9]*$/D', $nextAfterUserId) !== 1
        )
    ) {
        throw new RuntimeException('List users returned an invalid continuation representation.');
    }

    $ids = [];
    $eventCounts = [];

    foreach ($userValues as $userValue) {
        if (!is_array($userValue)) {
            throw new RuntimeException('List users returned a non-object user representation.');
        }

        $row = [];

        foreach ($userValue as $name => $value) {
            if (!is_string($name)) {
                throw new RuntimeException('List users returned a non-string user field name.');
            }

            $row[$name] = $value;
        }

        $user = UserActivitySummary::fromDatabaseRow($row);
        $ids[] = $user->id;
        $eventCounts[] = $user->eventCount;
    }

    $summary = $trace->snapshot();

    return [
        'ids' => $ids,
        'event_counts' => $eventCounts,
        'next_after_user_id' => $nextAfterUserId,
        'used' => $budget->used(),
        'statements' => $summary['statements'],
        'failures' => $summary['failures'],
        'tracked_fingerprints' => $summary['tracked_fingerprints'],
        'repeated_fingerprints' => $summary['repeated_fingerprints'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
        'truncated' => $summary['truncated'],
        'untracked_statements' => $summary['untracked_statements'],
    ];
}

/**
 * @return array{status: int, body: string, used: int, statements: int, repeated_fingerprints: int, maximum_executions: int, created_users: int, created_events: int}
 */
function runCreateUserScenario(string $name, int $preexistingUsers): array
{
    $databasePath = createUserDatabaseFixture($name, $preexistingUsers, $preexistingUsers > 0);
    $budget = new QueryBudget(2);
    $trace = new QueryTrace(2);
    $handler = new CreateUserHandler(
        Connection::connect('sqlite:' . $databasePath, $budget, $trace),
    );
    $response = $handler->handle(new Request(
        'POST',
        '/users',
        body: '{"name":"New User","email":"new@example.com"}',
        headers: ['content-type' => 'application/json'],
    ));
    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(2),
        new QueryTrace(2),
    );
    $userCount = $verification->selectOneRow(
        'SELECT COUNT(users.id) AS row_count FROM users WHERE users.email = :email',
        ['email' => 'new@example.com'],
    );
    $eventCount = $verification->selectOneRow(
        <<<'SQL'
            SELECT COUNT(user_events.id) AS row_count
            FROM user_events
            INNER JOIN users ON users.id = user_events.user_id
            WHERE users.email = :email
              AND user_events.event_type = :event_type
            SQL,
        ['email' => 'new@example.com', 'event_type' => 'user.created'],
    );
    $createdUsers = $userCount['row_count'] ?? null;
    $createdEvents = $eventCount['row_count'] ?? null;

    if (!is_int($createdUsers) || !is_int($createdEvents)) {
        throw new RuntimeException('Expected SQLite count results to be integers.');
    }

    $summary = $trace->snapshot();
    return [
        'status' => $response->status,
        'body' => $response->body,
        'used' => $budget->used(),
        'statements' => $summary['statements'],
        'repeated_fingerprints' => $summary['repeated_fingerprints'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
        'created_users' => $createdUsers,
        'created_events' => $createdEvents,
    ];
}

function createUserDatabaseFixture(string $name, int $userCount, bool $seedEvents): string
{
    if ($userCount < 0 || $userCount > 500) {
        throw new InvalidArgumentException('User fixture count must be between 0 and 500.');
    }

    $directory = __DIR__ . '/../tmp/application-tests';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the application test database directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset an application test database.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(5),
        new QueryTrace(5),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE user_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
            SQL,
    );
    $connection->executeStatement(
        'CREATE INDEX user_events_user_id_idx ON user_events (user_id)',
    );

    if ($userCount === 0) {
        return $databasePath;
    }

    $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                UNION ALL
                SELECT value + 1
                FROM sequence
                WHERE value < :user_count
            )
            INSERT INTO users (name, email)
            SELECT
                'User ' || sequence.value,
                'user' || sequence.value || '@example.com'
            FROM sequence
            SQL,
        ['user_count' => $userCount],
    );

    if ($seedEvents) {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO user_events (user_id, event_type)
                SELECT users.id, :first_event_type
                FROM users
                UNION ALL
                SELECT users.id, :second_event_type
                FROM users
                SQL,
            ['first_event_type' => 'seed.first', 'second_event_type' => 'seed.second'],
        );
    }

    return $databasePath;
}

/** @return array{exit_code: int, stdout: string, stderr: string} */
function runIsolatedPhpTest(string $path): array
{
    $process = proc_open(
        [PHP_BINARY, $path],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start isolated PHP test process.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read isolated PHP test output.');
    }

    return [
        'exit_code' => $exitCode >= 0 ? $exitCode : 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

$failures = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }

}

fwrite(STDOUT, sprintf("%d tests, %d failures\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
