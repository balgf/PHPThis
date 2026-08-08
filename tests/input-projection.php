<?php

declare(strict_types=1);

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\DenyAllAccountAuthorization;
use Example\Accounts\ResolvedTenant;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Routes;
use Example\Users\CreateUser\CreateUserCommand;
use Example\Users\CreateUser\CreateUserOperation;
use Example\Users\CreateUser\UnacceptableCreateUserValues;
use Example\Users\GetUser\UserDetails;
use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\ListUsers\ListUsersPageRequest;
use Example\Users\ListUsers\UserActivitySummary;
use Example\Users\ListUsers\UserSummary;
use Example\Users\UserId;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Routing\PathParameters;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function inputProjectionBehaviorTests(): Generator
{
    yield 'item projection converts exact database rows into concrete identifiers' => static function (): void {
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

    yield 'item projection rejects coercive and structurally invalid rows' => static function (): void {
    $invalidRows = [
        ['id' => 0, 'name' => 'Ada'],
        ['id' => '01', 'name' => 'Ada'],
        ['id' => (string) PHP_INT_MAX . '0', 'name' => 'Ada'],
        ['id' => 7, 'name' => ''],
        ['id' => 7, 'name' => "Invalid \xC3\x28"],
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

    yield 'database projection parses documented integer representations' => static function (): void {
    $nativeInteger = UserSummary::fromDatabaseRow(['name' => 'Ada', 'id' => 7]);
    $canonicalString = UserSummary::fromDatabaseRow(['id' => '8', 'name' => 'Grace']);

    if (
        $nativeInteger->id->value !== 7
        || $nativeInteger->name !== 'Ada'
        || $canonicalString->id->value !== 8
        || $canonicalString->name !== 'Grace'
    ) {
        throw new RuntimeException('Expected strict database rows to become typed projections.');
    }
};

    yield 'database projection rejects coercive identifiers' => static function (): void {
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

    yield 'database projection rejects missing unknown and invalid fields' => static function (): void {
    $invalidRows = [
        ['id' => 7],
        ['id' => 7, 'name' => 'Ada', 'is_admin' => true],
        ['id' => 7, 'name' => ''],
        ['id' => 7, 'name' => "Invalid \xC3\x28"],
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

    yield 'user activity projection parses exact aggregate rows' => static function (): void {
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
        $nativeValues->id->value !== 7
        || $nativeValues->eventCount !== 2
        || $canonicalStrings->id->value !== 8
        || $canonicalStrings->eventCount !== 0
    ) {
        throw new RuntimeException('Expected aggregate rows to become typed user activity summaries.');
    }
};

    yield 'user activity projection rejects malformed aggregate rows' => static function (): void {
    $invalidRows = [
        ['id' => 7, 'name' => 'Ada'],
        ['id' => 7, 'name' => 'Ada', 'event_count' => 1, 'unknown' => true],
        ['id' => 0, 'name' => 'Ada', 'event_count' => 1],
        ['id' => '01', 'name' => 'Ada', 'event_count' => 1],
        ['id' => 7, 'name' => '', 'event_count' => 1],
        ['id' => 7, 'name' => "Invalid \xC3\x28", 'event_count' => 1],
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

    yield 'list users page request parses only one canonical continuation' => static function (): void {
    $firstPage = ListUsersPageRequest::fromQuery([]);
    $continuedPage = ListUsersPageRequest::fromQuery(['after_user_id' => '1']);
    $maximumPage = ListUsersPageRequest::fromQuery([
        'after_user_id' => (string) PHP_INT_MAX,
    ]);

    if (
        $firstPage->afterUserId !== null
        || $continuedPage->afterUserId?->value !== 1
        || $maximumPage->afterUserId?->value !== PHP_INT_MAX
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

    yield 'list users rejects invalid continuation before database work' => static function (): void {
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
                'Cache-Control' => 'private, no-store',
            ]
            || $response->body !== "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n"
        ) {
            throw new RuntimeException('Expected invalid list continuation to map to the conservative 400 response.');
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

    yield 'list users accepts one canonical runtime continuation' => static function (): void {
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

    yield 'HTTP command parses one exact JSON object' => static function (): void {
    $command = CreateUserCommand::fromJson(
        '{"email":"ada@example.com","name":"Ada Lovelace"}',
    );
    $unicodeCommand = CreateUserCommand::fromJson(
        '{"name":"Jos\u00e9","email":"jose@example.com"}',
    );
    $preservedUnicodeSpaceCommand = CreateUserCommand::fromJson(
        '{"name":"\u00a0Ada\u00a0","email":"unicode-space@example.com"}',
    );
    $preservedEmailCommand = CreateUserCommand::fromJson(
        '{"name":"Ada","email":"Ada+Tag@Example.COM"}',
    );
    $exactLimitBody = exactCreateUserBody(2_048);
    $exactLimitCommand = CreateUserCommand::fromJson($exactLimitBody);

    if (
        $command->name !== 'Ada Lovelace'
        || $command->email !== 'ada@example.com'
        || $unicodeCommand->name !== 'José'
        || $preservedUnicodeSpaceCommand->name !== "\u{00a0}Ada\u{00a0}"
        || $preservedEmailCommand->email !== 'Ada+Tag@Example.COM'
        || strlen($exactLimitBody) !== 2_048
        || strlen($exactLimitCommand->name) !== 2_013
        || $exactLimitCommand->email !== 'a@example.com'
    ) {
        throw new RuntimeException('Expected strict JSON to become a typed command.');
    }
};

    yield 'HTTP command exposes native duplicate-key last-value behavior' => static function (): void {
    $command = CreateUserCommand::fromJson(
        '{"name":"First","email":"first@example.com","name":"Final","email":"final@example.com"}',
    );

    if ($command->name !== 'Final' || $command->email !== 'final@example.com') {
        throw new RuntimeException('Expected the documented json_decode duplicate-key limitation.');
    }
};

    yield 'HTTP command classifies structural and unacceptable input' => static function (): void {
    foreach (invalidCreateUserCases() as $case => $input) {
        try {
            CreateUserCommand::fromJson($input['body']);
        } catch (InvalidRequest | RequestBodyTooLarge | UnacceptableCreateUserValues $failure) {
            if ($failure::class !== $input['failure']) {
                throw new RuntimeException(sprintf(
                    'Expected create-user input case "%s" to fail as %s, received %s.',
                    $case,
                    $input['failure'],
                    $failure::class,
                ));
            }

            continue;
        }

        throw new RuntimeException(sprintf('Expected create-user input case "%s" to be rejected.', $case));
    }
};

    yield 'HTTP handler invokes only its typed create-user operation' => static function (): void {
    $operation = new class implements CreateUserOperation {
        public int $calls = 0;

        public ?CreateUserCommand $received = null;

        public function execute(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
            AccountId $accountId,
            CreateUserCommand $command,
        ): void {
            ++$this->calls;
            $this->received = $command;
        }
    };
    $handler = createUserTestHandler($operation);
    $response = $handler->handle(new Request(
        'POST',
        '/accounts/42/users',
        body: '{"name":"Ada Lovelace","email":"ada@example.com"}',
        headers: ['content-type' => 'application/json'],
        pathParameters: PathParameters::fromValues(['account_id' => 42], []),
    ));

    if (
        $response->status !== 201
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]
        || $response->body !== "{\"user\":{\"account_id\":42,\"name\":\"Ada Lovelace\",\"email\":\"ada@example.com\"}}\n"
        || $operation->calls !== 1
        || !$operation->received instanceof CreateUserCommand
        || $operation->received->name !== 'Ada Lovelace'
        || $operation->received->email !== 'ada@example.com'
    ) {
        throw new RuntimeException('Expected the handler to pass one typed command to its operation.');
    }
};

    yield 'HTTP request boundary accepts the exact endpoint byte limit' => static function (): void {
    $operation = new class implements CreateUserOperation {
        public int $calls = 0;

        public ?CreateUserCommand $received = null;

        public function execute(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
            AccountId $accountId,
            CreateUserCommand $command,
        ): void {
            ++$this->calls;
            $this->received = $command;
        }
    };
    $body = exactCreateUserBody(2_048);
    $application = new Application(new Router([
        new Route(
            'POST',
            '/accounts/{account_id:positive-int}/users',
            createUserTestHandler($operation),
        ),
    ]));
    $response = (new RequestBoundary(
        requestReaderForBody($body, 8_192),
        $application,
        exampleErrorResponseRegistry(),
    ))->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/accounts/42/users',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) strlen($body),
        ],
        [],
    );

    if (
        strlen($body) !== 2_048
        || $response->status !== 201
        || $operation->calls !== 1
        || !$operation->received instanceof CreateUserCommand
        || strlen($operation->received->name) !== 2_013
        || $operation->received->email !== 'a@example.com'
    ) {
        throw new RuntimeException('Expected the exact endpoint byte limit to reach the typed operation.');
    }
};

    yield 'HTTP handler rejects invalid commands before use-case invocation' => static function (): void {
    $operation = new class implements CreateUserOperation {
        public int $calls = 0;

        public function execute(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
            AccountId $accountId,
            CreateUserCommand $command,
        ): void {
            ++$this->calls;
        }
    };
    $handler = createUserTestHandler($operation);

    foreach (invalidCreateUserCases() as $case => $input) {
        try {
            $handler->handle(new Request(
                'POST',
                '/accounts/42/users',
                body: $input['body'],
                headers: ['content-type' => 'application/json'],
                pathParameters: PathParameters::fromValues(['account_id' => 42], []),
            ));
        } catch (InvalidRequest | RequestBodyTooLarge | UnacceptableCreateUserValues $failure) {
            if ($failure::class !== $input['failure']) {
                throw new RuntimeException(sprintf(
                    'Expected create-user input case "%s" to fail as %s, received %s.',
                    $case,
                    $input['failure'],
                    $failure::class,
                ));
            }

            continue;
        }

        throw new RuntimeException(sprintf(
            'Expected create-user input case "%s" to fail before use-case invocation.',
            $case,
        ));
    }

    if ($operation->calls !== 0) {
        throw new RuntimeException('Expected invalid create-user input to make zero use-case calls.');
    }
};

    yield 'example request boundary maps client failures before database work' => static function (): void {
    $databasePath = createUserDatabaseFixture('request-client-failures', 0, false);
    $readBudget = new QueryBudget(1);
    $getBudget = new QueryBudget(1);
    $writeBudget = new QueryBudget(4);
    $writeTrace = new QueryTrace(4);
    $dsn = 'sqlite:' . $databasePath;
    $createPolicy = new RunTestAllowCreateUserPolicy();
    $application = new Application(new Router(Routes::create(
        Connection::connect($dsn, $readBudget, new QueryTrace(1)),
        Connection::connect($dsn, $getBudget, new QueryTrace(1)),
        Connection::connect($dsn, $writeBudget, $writeTrace),
        new SelectAuthorizedDocument(
            Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1)),
        ),
        Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1)),
        $createPolicy,
        $createPolicy,
        $createPolicy,
        new DenyAllAccountAuthorization(),
        new DenyAllAccountAuthorization(),
        new LocalDocumentFiles(__DIR__ . '/../tmp/application-tests/document-files'),
    )));
    $registry = exampleErrorResponseRegistry();
    $expectedHeaders = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'private, no-store',
    ];
    $expectedBodies = [
        400 => "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        413 => "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
        422 => "{\"error\":{\"code\":\"unprocessable_content\",\"message\":\"Request content is unacceptable.\"}}\n",
    ];

    foreach (invalidCreateUserCases() as $case => $input) {
        $invalidResponse = (new RequestBoundary(
            requestReaderForBody($input['body'], 8_192),
            $application,
            $registry,
        ))->handle(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/accounts/42/users',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => (string) strlen($input['body']),
            ],
            [],
        );

        if (
            $invalidResponse->status !== $input['status']
            || $invalidResponse->headers !== $expectedHeaders
            || $invalidResponse->body !== $expectedBodies[$input['status']]
            || str_contains($invalidResponse->body, createUserSecretProbe())
            || str_contains(implode("\n", $invalidResponse->headers), createUserSecretProbe())
        ) {
            throw new RuntimeException(sprintf(
                'Expected create-user input case "%s" to receive its exact generic redacted response.',
                $case,
            ));
        }
    }

    $validBody = '{"name":"Ada","email":"ada@example.com"}';
    $unsupportedResponse = (new RequestBoundary(
        requestReaderForBody($validBody, 8_192),
        $application,
        $registry,
    ))->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/accounts/42/users',
            'CONTENT_LENGTH' => (string) strlen($validBody),
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
            'REQUEST_URI' => '/accounts/42/users',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '8193',
        ],
        [],
    );

    if (
        $unsupportedResponse->status !== 415
        || $unsupportedResponse->headers !== $expectedHeaders
        || $unsupportedResponse->body !== "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type is unsupported.\"}}\n"
        || $outerTooLargeResponse->status !== 413
        || $outerTooLargeResponse->headers !== $expectedHeaders
        || $outerTooLargeResponse->body !== $expectedBodies[413]
        || $readBudget->used() !== 0
        || $getBudget->used() !== 0
        || $writeBudget->used() !== 0
        || $writeTrace->snapshot()['statements'] !== 0
    ) {
        throw new RuntimeException('Expected explicit public client failures before database work.');
    }
};

    yield 'mapped input failures emit no submitted data or log entry' => static function (): void {
    $logPath = __DIR__ . '/../tmp/mapped-input-failure.log';

    if (file_put_contents($logPath, '') !== 0) {
        throw new RuntimeException('Unable to reset the mapped-input test log.');
    }

    $operation = new class implements CreateUserOperation {
        public int $calls = 0;

        public function execute(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
            AccountId $accountId,
            CreateUserCommand $command,
        ): void {
            ++$this->calls;
        }
    };
    $secret = createUserSecretProbe();
    $cases = [
        'invalid_structure' => [
            'body' => '{"name":"Ada","email":"ada@example.com","api_token":"' . $secret . '"}',
            'status' => 400,
            'response_body' => "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ],
        'unacceptable_values' => [
            'body' => '{"name":"Ada","email":"' . $secret . '"}',
            'status' => 422,
            'response_body' => "{\"error\":{\"code\":\"unprocessable_content\",\"message\":\"Request content is unacceptable.\"}}\n",
        ],
    ];
    $application = new Application(new Router([
        new Route(
            'POST',
            '/accounts/{account_id:positive-int}/users',
            createUserTestHandler($operation),
        ),
    ]));
    $previousErrorLog = ini_get('error_log');

    if (ini_set('error_log', $logPath) === false) {
        throw new RuntimeException('Unable to redirect the mapped-input test log.');
    }

    $responses = [];

    try {
        foreach ($cases as $case => $input) {
            $responses[$case] = (new RequestBoundary(
                requestReaderForBody($input['body'], 8_192),
                $application,
                exampleErrorResponseRegistry(),
            ))->handle(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/accounts/42/users',
                    'CONTENT_TYPE' => 'application/json',
                    'CONTENT_LENGTH' => (string) strlen($input['body']),
                ],
                [],
            );
        }
    } finally {
        if (is_string($previousErrorLog)) {
            ini_set('error_log', $previousErrorLog);
        }
    }

    $log = file_get_contents($logPath);

    if (
        !is_string($log)
        || $log !== ''
        || $operation->calls !== 0
    ) {
        throw new RuntimeException('Expected mapped input failures to perform no operation work or logging.');
    }

    $expectedHeaders = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'private, no-store',
    ];

    foreach ($cases as $case => $input) {
        $response = $responses[$case];

        if (
            $response->status !== $input['status']
            || $response->headers !== $expectedHeaders
            || $response->body !== $input['response_body']
            || str_contains($response->body, $secret)
            || str_contains(implode("\n", $response->headers), $secret)
        ) {
            throw new RuntimeException(sprintf(
                'Expected mapped input case "%s" to emit one generic redacted response.',
                $case,
            ));
        }
    }
};

}


function exampleErrorResponseRegistry(): ErrorResponseRegistry
{
    $headers = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'private, no-store',
    ];

    return new ErrorResponseRegistry([
        InvalidRequest::class => new Response(
            400,
            $headers,
            "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ),
        UnacceptableCreateUserValues::class => new Response(
            422,
            $headers,
            "{\"error\":{\"code\":\"unprocessable_content\",\"message\":\"Request content is unacceptable.\"}}\n",
        ),
        RequestBodyTooLarge::class => new Response(
            413,
            $headers,
            "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
        ),
        UnsupportedMediaType::class => new Response(
            415,
            $headers,
            "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type is unsupported.\"}}\n",
        ),
    ]);
}
