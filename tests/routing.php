<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\PathParameters;
use PHPThis\Routing\Route;
use PHPThis\Routing\RouteParameterType;
use PHPThis\Routing\Router;

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function routingBehaviorTests(): Generator
{
    yield 'router rejects duplicate method and path pairs' => static function (): void {
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

    yield 'route accepts at most two full-segment typed parameter declarations' => static function (): void {
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
    $percentRoute = new Route('GET', '/raw/%00/%20/%7F/%2F/%3F/%23', $handler);
    $percentMatch = (new Router([$percentRoute]))->match(
        new Request('GET', '/raw/%00/%20/%7F/%2F/%3F/%23'),
    );
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
        || $percentMatch?->route !== $percentRoute
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

    foreach ([...range(0x00, 0x20), 0x7F] as $byte) {
        foreach ([
            '/literal' . chr($byte) . 'PrivateMarker',
            '/accounts/{account_id:positive-int}/documents' . chr($byte) . 'PrivateMarker',
        ] as $path) {
            try {
                new Route('GET', $path, $handler);
            } catch (InvalidArgumentException $failure) {
                if (
                    $failure->getMessage()
                        !== 'Route path must be absolute and contain no query, fragment, raw space, control, or DEL byte.'
                    || str_contains($failure->getMessage(), 'PrivateMarker')
                ) {
                    throw new RuntimeException('Expected one fixed redacted route-path diagnostic.');
                }

                continue;
            }

            throw new RuntimeException('Expected every prohibited route-path byte to be rejected.');
        }
    }
};

    yield 'router matches bounded canonical positive integer path parameters' => static function (): void {
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

    yield 'router matches two ordered parameters and bounded opaque tokens' => static function (): void {
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

    yield 'router matches canonical lowercase UUID path parameters' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $route = new Route('GET', '/accounts/{account_id:uuid}', $handler);
    $router = new Router([$route]);
    $validValues = [
        '123e4567-e89b-12d3-8456-426614174000',
        '123e4567-e89b-22d3-9456-426614174000',
        '123e4567-e89b-32d3-a456-426614174000',
        '123e4567-e89b-42d3-b456-426614174000',
        '123e4567-e89b-52d3-8456-426614174000',
        '123e4567-e89b-62d3-8456-426614174000',
        '01890f5a-4c96-7a2b-8c3d-123456789abc',
        '123e4567-e89b-82d3-8456-426614174000',
    ];

    foreach ($validValues as $value) {
        $match = $router->match(new Request('GET', '/accounts/' . $value));

        if (
            $match === null
            || $match->route !== $route
            || $match->pathParameters->uuid('account_id') !== $value
        ) {
            throw new RuntimeException("Expected canonical lowercase UUID matching: {$value}");
        }
    }

    $invalidValues = [
        '00000000-0000-0000-0000-000000000000',
        'ffffffff-ffff-ffff-ffff-ffffffffffff',
        '123e4567-e89b-02d3-8456-426614174000',
        '123e4567-e89b-92d3-8456-426614174000',
        '123e4567-e89b-42d3-7456-426614174000',
        '123e4567-e89b-42d3-c456-426614174000',
        '123E4567-E89B-42D3-8456-426614174000',
        '123e4567e89b42d38456426614174000',
        '{123e4567-e89b-42d3-8456-426614174000}',
        'urn:uuid:123e4567-e89b-42d3-8456-426614174000',
        '123e4567-e89b-42d3-8456-42661417400g',
        '123e4567-e89b-42d3-8456-4266141740000',
        '123e4567-e89b-42d3-8456-42661417400',
        '%31' . '23e4567-e89b-42d3-8456-426614174000',
    ];

    foreach ($invalidValues as $value) {
        if ($router->match(new Request('GET', '/accounts/' . $value)) !== null) {
            throw new RuntimeException("Expected UUID route value to be rejected: {$value}");
        }
    }
};

    yield 'router matches canonical lowercase ULID path parameters' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $route = new Route('GET', '/events/{event_id:ulid}', $handler);
    $router = new Router([$route]);
    $validValues = [
        '00000000000000000000000000',
        '01arz3ndektsv4rrffq69g5fav',
        '7zzzzzzzzzzzzzzzzzzzzzzzzz',
    ];

    foreach ($validValues as $value) {
        $match = $router->match(new Request('GET', '/events/' . $value));

        if (
            $match === null
            || $match->route !== $route
            || $match->pathParameters->ulid('event_id') !== $value
        ) {
            throw new RuntimeException("Expected canonical lowercase ULID matching: {$value}");
        }
    }

    $invalidValues = [
        '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        '8zzzzzzzzzzzzzzzzzzzzzzzzz',
        'z1arz3ndektsv4rrffq69g5fav',
        '01arz3ndektsv4rrffq69g5fai',
        '01arz3ndektsv4rrffq69g5fal',
        '01arz3ndektsv4rrffq69g5fao',
        '01arz3ndektsv4rrffq69g5fau',
        '01arz3ndektsv4rrffq69g5fa',
        '01arz3ndektsv4rrffq69g5fav0',
        '01arz3ndektsv4rrffq69g5fa-',
        '%30' . '1arz3ndektsv4rrffq69g5fav',
    ];

    foreach ($invalidValues as $value) {
        if ($router->match(new Request('GET', '/events/' . $value)) !== null) {
            throw new RuntimeException("Expected ULID route value to be rejected: {$value}");
        }
    }
};

    yield 'invalid UUID and ULID routes stop before handler and database work' => static function (): void {
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite::memory:', $budget, $trace);
    $handler = new class($connection) implements RequestHandler {
        public int $calls = 0;

        public function __construct(private readonly Connection $connection)
        {
        }

        public function handle(Request $request): Response
        {
            $this->calls++;
            $this->connection->selectOneRow('SELECT 1 AS reached');

            return new Response(204, [], '');
        }
    };
    $application = new Application(new Router([
        new Route('GET', '/accounts/{account_id:uuid}', $handler),
        new Route('DELETE', '/accounts/{account_id:uuid}', $handler),
        new Route('POST', '/events/{event_id:ulid}', $handler),
        new Route('PUT', '/events/{event_id:ulid}', $handler),
    ]));
    $validUuid = '01890f5a-4c96-7a2b-8c3d-123456789abc';
    $validUlid = '01arz3ndektsv4rrffq69g5fav';
    $uuidNotAllowed = $application->handle(new Request('PATCH', '/accounts/' . $validUuid));
    $ulidNotAllowed = $application->handle(new Request('PATCH', '/events/' . $validUlid));
    $invalidUuid = $application->handle(new Request('GET', '/accounts/' . strtoupper($validUuid)));
    $invalidUlid = $application->handle(new Request('GET', '/events/' . strtoupper($validUlid)));

    if (
        $uuidNotAllowed->status !== 405
        || $uuidNotAllowed->headers['Allow'] !== 'GET, DELETE'
        || $ulidNotAllowed->status !== 405
        || $ulidNotAllowed->headers['Allow'] !== 'POST, PUT'
        || $invalidUuid->status !== 404
        || $invalidUlid->status !== 404
        || $handler->calls !== 0
        || $budget->used() !== 0
        || $trace->snapshot()['statements'] !== 0
    ) {
        throw new RuntimeException('Expected UUID and ULID rejection before handler and database work.');
    }
};

    yield 'path parameters reject invalid construction unknown names and wrong types' => static function (): void {
    foreach ([['Invalid', 1], ['user_id', 0]] as [$name, $value]) {
        try {
            PathParameters::fromValues([$name => $value], []);
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
        static fn(): PathParameters => PathParameters::fromValues(
            ['first_id' => 1],
            [],
            ['second_id' => '01890f5a-4c96-7a2b-8c3d-123456789abc'],
            ['third_id' => '01arz3ndektsv4rrffq69g5fav'],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            [],
            ['identifier' => 'Identifier'],
            ['identifier' => '01890f5a-4c96-7a2b-8c3d-123456789abc'],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            [],
            [],
            ['account_id' => '01890F5A-4C96-7A2B-8C3D-123456789ABC'],
        ),
        static fn(): PathParameters => PathParameters::fromValues(
            [],
            [],
            [],
            ['event_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
        ),
        static fn(): PathParameters => PathParameters::fromValues([], [], ['account_id' => 1]),
        static fn(): PathParameters => PathParameters::fromValues([], [], [], ['event_id' => 1]),
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
        static fn(): string => $match->pathParameters->uuid('account_id'),
        static fn(): string => $match->pathParameters->ulid('document_key'),
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

    yield 'literal route wins over a matching mixed typed route' => static function (): void {
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

    yield 'literal routes win over canonical UUID and ULID values' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $cases = [
        [
            '/accounts/{account_id:uuid}',
            '/accounts/01890f5a-4c96-7a2b-8c3d-123456789abc',
        ],
        [
            '/events/{event_id:ulid}',
            '/events/01arz3ndektsv4rrffq69g5fav',
        ],
    ];

    foreach ($cases as [$parameterizedPath, $literalPath]) {
        $dynamic = new Route('GET', $parameterizedPath, $handler);
        $literal = new Route('GET', $literalPath, $handler);
        $match = (new Router([$dynamic, $literal]))->match(new Request('GET', $literalPath));

        if ($match === null || $match->route !== $literal) {
            throw new RuntimeException('Expected the exact identifier literal route to win.');
        }
    }
};

    yield 'route rejects repeated typed parameter names' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };

    foreach (['positive-int', 'token', 'uuid', 'ulid'] as $secondType) {
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

    yield 'router rejects overlapping typed declarations and inconsistent metadata' => static function (): void {
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
            new Route('GET', '/items/{item_id:token}', $handler),
            new Route('GET', '/items/{item_id:uuid}', $handler),
        ],
        [
            new Route('GET', '/items/{item_id:token}', $handler),
            new Route('POST', '/items/{item_id:ulid}', $handler),
        ],
        [
            new Route('GET', '/items/{item_id:positive-int}', $handler),
            new Route('GET', '/items/{item_id:uuid}', $handler),
        ],
        [
            new Route('GET', '/items/{item_id:positive-int}', $handler),
            new Route('GET', '/items/{item_id:ulid}', $handler),
        ],
        [
            new Route('GET', '/items/{item_id:uuid}', $handler),
            new Route('GET', '/items/{item_id:ulid}', $handler),
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

    yield 'application passes immutable mixed path parameters to the handler' => static function (): void {
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

    yield 'application preserves mixed route 405 order and rejects invalid values before handling' => static function (): void {
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

    yield 'allowed methods merge literal and parameterized registrations in order' => static function (): void {
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

    yield 'router dispatches from a large explicit route table' => static function (): void {
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

    yield 'router indexes mixed paths in a large branching route table' => static function (): void {
    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $routes = [];
    $targetRoutes = [];
    $uuid = '01890f5a-4c96-7a2b-8c3d-123456789abc';
    $ulid = '01arz3ndektsv4rrffq69g5fav';

    for ($index = 0; $index < 10_000; $index++) {
        $parameter = match (true) {
            $index < 3_333 => '{document_key:token}',
            $index < 6_666 => '{document_id:uuid}',
            default => '{document_id:ulid}',
        };
        $routes[] = new Route(
            'GET',
            '/accounts/account-'
                . $index
                . '/documents/'
                . $parameter,
            $handler,
        );
        $targetRoute = new Route(
            'GET',
            '/accounts/{account_id:positive-int}/document-groups/'
                . $index
                . '/documents/'
                . $parameter,
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
        new Request('GET', '/accounts/5001/document-groups/5000/documents/' . $uuid),
    );
    $last = $router->match(
        new Request('GET', '/accounts/10000/document-groups/9999/documents/' . $ulid),
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
        || $middle->pathParameters->uuid('document_id') !== $uuid
        || $last?->route !== $targetRoutes[9_999]
        || $last->pathParameters->positiveInteger('account_id') !== 10_000
        || $last->pathParameters->ulid('document_id') !== $ulid
        || $missing !== null
        || $router->allowedMethodsForPath(
            '/accounts/10000/document-groups/9999/documents/' . $ulid,
        ) !== ['GET']
    ) {
        throw new RuntimeException('Expected all fixed types to remain indexed across 20,000 routes.');
    }
};

    yield 'router preserves allowed method registration order' => static function (): void {
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

}
