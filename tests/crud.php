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
use Example\Users\CreateUser\TransactionalCreateUser;
use Example\Users\CreateUser\UnacceptableCreateUserValues;
use Example\Users\GetUser\GetUserHandler;
use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\ListUsers\UserActivitySummary;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Routing\PathParameters;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function crudBehaviorTests(): Generator
{
    yield 'user routes execute bounded reads and one transactional write end to end' => static function (): void {
    $databasePath = createUserDatabaseFixture('user-routes', 0, false);
    $readBudget = new QueryBudget(1);
    $readTrace = new QueryTrace(1);
    $getBudget = new QueryBudget(1);
    $getTrace = new QueryTrace(1);
    $writeBudget = new QueryBudget(4);
    $writeTrace = new QueryTrace(4);
    $dsn = 'sqlite:' . $databasePath;
    $createPolicy = new RunTestAllowCreateUserPolicy();
    $application = new Application(new Router(Routes::create(
        Connection::connect($dsn, $readBudget, $readTrace),
        Connection::connect($dsn, $getBudget, $getTrace),
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

    $created = $application->handle(new Request(
        'POST',
        '/accounts/42/users',
        body: '{"name":"Ada Lovelace","email":"ada@example.com"}',
        headers: ['content-type' => 'application/json'],
    ));
    $got = $application->handle(new Request('GET', '/users/1'));
    $listed = $application->handle(new Request('GET', '/users'));

    if (
        $created->status !== 201
        || $created->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]
        || $created->body !== "{\"user\":{\"account_id\":42,\"name\":\"Ada Lovelace\",\"email\":\"ada@example.com\"}}\n"
        || $got->status !== 200
        || $got->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $got->body !== "{\"user\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n"
        || $listed->status !== 200
        || $listed->headers !== $got->headers
        || $listed->body !== "{\"users\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"next_after_user_id\":null}\n"
        || $writeBudget->used() !== 4
        || $readBudget->used() !== 1
        || $getBudget->used() !== 1
        || $writeTrace->snapshot()['statements'] !== 4
        || $readTrace->snapshot()['statements'] !== 1
        || $getTrace->snapshot()['statements'] !== 1
    ) {
        throw new RuntimeException('Expected explicit user routes with bounded reads and writes.');
    }
};

    yield 'user list page keeps one query across dataset sizes' => static function (): void {
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

    yield 'user list continuation handles exact and lookahead page boundaries' => static function (): void {
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

    yield 'user list continuation traverses large data without gaps or duplicates' => static function (): void {
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

    yield 'user item endpoint keeps one query across dataset sizes' => static function (): void {
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

    yield 'user item route separates missing records from malformed identifiers' => static function (): void {
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

    yield 'account-scoped user creation publishes one job with four writes across dataset sizes' => static function (): void {
    $empty = runCreateUserScenario('write-empty', 0);
    $large = runCreateUserScenario('write-large', 500);

    if (
        $empty !== $large
        || $empty['status'] !== 201
        || $empty['body'] !== "{\"user\":{\"account_id\":42,\"name\":\"New User\",\"email\":\"new@example.com\"}}\n"
        || $empty['used'] !== 4
        || $empty['statements'] !== 4
        || $empty['repeated_fingerprints'] !== 0
        || $empty['maximum_executions'] !== 1
        || $empty['created_users'] !== 1
        || $empty['created_account_users'] !== 1
        || $empty['created_events'] !== 1
        || $empty['published_jobs'] !== 1
    ) {
        throw new RuntimeException('Expected transactional creation to publish one job at constant cost.');
    }
};

    yield 'account-scoped user creation keeps principal and user identities separate' => static function (): void {
    $databasePath = createUserDatabaseFixture('write-principal-user-separation', 0, false);
    $budget = new QueryBudget(32);
    $trace = new QueryTrace(4);
    $operation = new TransactionalCreateUser(
        Connection::connect('sqlite:' . $databasePath, $budget, $trace),
    );
    $accountId = AccountId::fromPositiveInteger(42);

    foreach (range(1, 8) as $number) {
        $operation->execute(
            AuthenticatedPrincipal::fromPositiveInteger(7),
            ResolvedTenant::forAccount($accountId),
            $accountId,
            CreateUserCommand::fromJson(json_encode(
                [
                    'name' => 'Created User ' . $number,
                    'email' => 'created' . $number . '@example.com',
                ],
                JSON_THROW_ON_ERROR,
            )),
        );
    }

    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    )->selectOneRow(
        <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM users) AS user_count,
                (SELECT COUNT(*) FROM account_users) AS account_user_count,
                (SELECT COUNT(*) FROM account_memberships) AS actor_membership_count,
                (SELECT COUNT(*) FROM user_events) AS event_count,
                (SELECT COUNT(*) FROM application_jobs) AS job_count
            SQL,
    );
    $summary = $trace->snapshot();

    if (
        $verification !== [
            'user_count' => 8,
            'account_user_count' => 8,
            'actor_membership_count' => 1,
            'event_count' => 8,
            'job_count' => 8,
        ]
        || $budget->used() !== 32
        || $summary['statements'] !== 32
        || $summary['tracked_fingerprints'] !== 4
        || $summary['repeated_fingerprints'] !== 4
        || $summary['maximum_executions_per_fingerprint'] !== 8
        || $summary['failures'] !== 0
    ) {
        throw new RuntimeException('Principal membership must never collide with created user identity.');
    }
};

    yield 'account-scoped user creation rolls back when its budget rejects account relation' => static function (): void {
    $databasePath = createUserDatabaseFixture('write-rollback', 0, false);
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $handler = createUserTestHandler(new TransactionalCreateUser($connection));
    $budgetFailed = false;

    try {
        $handler->handle(new Request(
            'POST',
            '/accounts/42/users',
            body: '{"name":"Ada","email":"ada@example.com"}',
            headers: ['content-type' => 'application/json'],
            pathParameters: PathParameters::fromValues(['account_id' => 42], []),
        ));
    } catch (QueryBudgetExceeded) {
        $budgetFailed = true;
    }

    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(3),
        new QueryTrace(3),
    );
    $userCount = $verification->selectOneRow('SELECT COUNT(users.id) AS row_count FROM users');
    $eventCount = $verification->selectOneRow('SELECT COUNT(user_events.id) AS row_count FROM user_events');
    $accountUserCount = $verification->selectOneRow(
        'SELECT COUNT(account_users.user_id) AS row_count FROM account_users',
    );

    if (
        !$budgetFailed
        || $connection->inTransaction()
        || $budget->used() !== 1
        || $trace->snapshot()['statements'] !== 1
        || ($userCount['row_count'] ?? null) !== 0
        || ($eventCount['row_count'] ?? null) !== 0
        || ($accountUserCount['row_count'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected the first write to roll back after the second exceeds its budget.');
    }
};

    yield 'account-scoped user creation rolls back when the event statement fails' => static function (): void {
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

    $budget = new QueryBudget(3);
    $trace = new QueryTrace(3);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $handler = createUserTestHandler(new TransactionalCreateUser($connection));
    $statementFailed = false;

    try {
        $handler->handle(new Request(
            'POST',
            '/accounts/42/users',
            body: '{"name":"Ada","email":"ada@example.com"}',
            headers: ['content-type' => 'application/json'],
            pathParameters: PathParameters::fromValues(['account_id' => 42], []),
        ));
    } catch (PDOException) {
        $statementFailed = true;
    }

    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(3),
        new QueryTrace(3),
    );
    $userCount = $verification->selectOneRow('SELECT COUNT(users.id) AS row_count FROM users');
    $eventCount = $verification->selectOneRow('SELECT COUNT(user_events.id) AS row_count FROM user_events');
    $accountUserCount = $verification->selectOneRow(
        'SELECT COUNT(account_users.user_id) AS row_count FROM account_users',
    );
    $summary = $trace->snapshot();

    if (
        !$statementFailed
        || $connection->inTransaction()
        || $budget->used() !== 3
        || $summary['statements'] !== 3
        || $summary['failures'] !== 1
        || ($userCount['row_count'] ?? null) !== 0
        || ($eventCount['row_count'] ?? null) !== 0
        || ($accountUserCount['row_count'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected an executed event failure to roll back the user insert.');
    }
};

    yield 'account-scoped user creation rejects invalid input before database work' => static function (): void {
    $databasePath = createUserDatabaseFixture('write-invalid', 0, false);
    $budget = new QueryBudget(2);
    $trace = new QueryTrace(2);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $handler = createUserTestHandler(new TransactionalCreateUser($connection));

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
            'Expected create-user input case "%s" to fail before database work.',
            $case,
        ));
    }

    if (
        $connection->inTransaction()
        || $budget->used() !== 0
        || $trace->snapshot()['statements'] !== 0
    ) {
        throw new RuntimeException('Expected invalid input to fail before opening a transaction or querying.');
    }
};

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
        $ids[] = $user->id->value;
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
 * @return array{status: int, body: string, used: int, statements: int, repeated_fingerprints: int, maximum_executions: int, created_users: int, created_account_users: int, created_events: int, published_jobs: int}
 */
function runCreateUserScenario(string $name, int $preexistingUsers): array
{
    $databasePath = createUserDatabaseFixture($name, $preexistingUsers, $preexistingUsers > 0);
    $budget = new QueryBudget(4);
    $trace = new QueryTrace(4);
    $handler = createUserTestHandler(
        new TransactionalCreateUser(
            Connection::connect('sqlite:' . $databasePath, $budget, $trace),
        ),
    );
    $response = $handler->handle(new Request(
        'POST',
        '/accounts/42/users',
        body: '{"name":"New User","email":"new@example.com"}',
        headers: ['content-type' => 'application/json'],
        pathParameters: PathParameters::fromValues(['account_id' => 42], []),
    ));
    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(4),
        new QueryTrace(4),
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
    $accountUserCount = $verification->selectOneRow(
        <<<'SQL'
            SELECT COUNT(account_users.user_id) AS row_count
            FROM account_users
            INNER JOIN users ON users.id = account_users.user_id
            WHERE users.email = :email
              AND account_users.account_id = :account_id
            SQL,
        ['email' => 'new@example.com', 'account_id' => 42],
    );
    $jobCount = $verification->selectOneRow(
        <<<'SQL'
            SELECT COUNT(application_jobs.job_id) AS row_count
            FROM application_jobs
            WHERE application_jobs.status = :status
            SQL,
        ['status' => 'available'],
    );
    $createdUsers = $userCount['row_count'] ?? null;
    $createdAccountUsers = $accountUserCount['row_count'] ?? null;
    $createdEvents = $eventCount['row_count'] ?? null;
    $publishedJobs = $jobCount['row_count'] ?? null;

    if (
        !is_int($createdUsers)
        || !is_int($createdAccountUsers)
        || !is_int($createdEvents)
        || !is_int($publishedJobs)
    ) {
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
        'created_account_users' => $createdAccountUsers,
        'created_events' => $createdEvents,
        'published_jobs' => $publishedJobs,
    ];
}
