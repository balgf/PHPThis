<?php

declare(strict_types=1);

use Example\CreateUserCommand;
use Example\Routes;
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
    $application = new Application(new Router(Routes::create()));
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
