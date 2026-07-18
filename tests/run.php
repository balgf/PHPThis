<?php

declare(strict_types=1);

use Example\CreateUserCommand;
use Example\CreateUserHandler;
use Example\ListUsersHandler;
use Example\Routes;
use Example\UserActivitySummary;
use Example\UserSummary;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

$tests = [];

$tests['example composes explicit route modules'] = static function (): void {
    $application = new Application(new Router(Routes::create(
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(2), new QueryTrace(2)),
    )));
    $response = $application->handle(new Request('GET', '/health'));

    if ($response->status !== 200 || $response->body !== "{\"status\":\"ok\"}\n") {
        throw new RuntimeException('Expected the composed example health route.');
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

    if ($notAllowed->status !== 405 || $notAllowed->headers['Allow'] !== 'GET') {
        throw new RuntimeException('Expected 405 with an Allow header.');
    }

    if ($notFound->status !== 404) {
        throw new RuntimeException('Expected 404 for an unknown path.');
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
        $firstRoute !== $routes[0]
        || $middleRoute !== $routes[5_000]
        || $lastRoute !== $routes[9_999]
        || $missingRoute !== null
        || $allowedMethods !== ['GET']
    ) {
        throw new RuntimeException('Expected exact lookup across 10,000 routes.');
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
        } catch (JsonException | UnexpectedValueException) {
            continue;
        }

        throw new RuntimeException('Expected malformed or coercive JSON input to be rejected.');
    }
};

$tests['user routes execute one read and one transactional write end to end'] = static function (): void {
    $databasePath = createUserDatabaseFixture('user-routes', 0, false);
    $readBudget = new QueryBudget(1);
    $readTrace = new QueryTrace(1);
    $writeBudget = new QueryBudget(2);
    $writeTrace = new QueryTrace(2);
    $dsn = 'sqlite:' . $databasePath;
    $application = new Application(new Router(Routes::create(
        Connection::connect($dsn, $readBudget, $readTrace),
        Connection::connect($dsn, $writeBudget, $writeTrace),
    )));

    $created = $application->handle(new Request(
        'POST',
        '/users',
        body: '{"name":"Ada Lovelace","email":"ada@example.com"}',
    ));
    $listed = $application->handle(new Request('GET', '/users'));

    if (
        $created->status !== 201
        || $created->body !== "{\"user\":{\"name\":\"Ada Lovelace\",\"email\":\"ada@example.com\"}}\n"
        || $listed->status !== 200
        || $listed->body !== "{\"users\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}]}\n"
        || $writeBudget->used() !== 2
        || $readBudget->used() !== 1
        || $writeTrace->snapshot()['statements'] !== 2
        || $readTrace->snapshot()['statements'] !== 1
    ) {
        throw new RuntimeException('Expected the explicit user routes to read and write the sample schema.');
    }
};

$tests['user read endpoint keeps one query across dataset sizes'] = static function (): void {
    $smallPath = createUserDatabaseFixture('read-small', 2, true);
    $largePath = createUserDatabaseFixture('read-large', 500, true);
    $smallBudget = new QueryBudget(1);
    $smallTrace = new QueryTrace(1);
    $largeBudget = new QueryBudget(1);
    $largeTrace = new QueryTrace(1);
    $smallHandler = new ListUsersHandler(
        Connection::connect('sqlite:' . $smallPath, $smallBudget, $smallTrace),
    );
    $largeHandler = new ListUsersHandler(
        Connection::connect('sqlite:' . $largePath, $largeBudget, $largeTrace),
    );

    $smallResponse = $smallHandler->handle(new Request('GET', '/users'));
    $largeResponse = $largeHandler->handle(new Request('GET', '/users'));
    $smallSummary = $smallTrace->snapshot();
    $largeSummary = $largeTrace->snapshot();

    if (
        $smallResponse->body !== "{\"users\":[{\"id\":1,\"name\":\"User 1\",\"event_count\":2},{\"id\":2,\"name\":\"User 2\",\"event_count\":2}]}\n"
        || substr_count($largeResponse->body, '"event_count":2') !== 50
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
        throw new RuntimeException('Expected the bounded aggregate read to remain one query at scale.');
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
        $handler->handle(new Request('POST', '/users', body: '{"name":"Ada"}'));
    } catch (UnexpectedValueException) {
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
