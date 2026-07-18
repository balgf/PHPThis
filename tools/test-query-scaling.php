<?php

declare(strict_types=1);

use Example\ListUsersHandler;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\Request;

require dirname(__DIR__) . '/autoload.php';
require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

use PHPThis\Verification\SyntaxProfile;

$root = dirname(__DIR__);
$fixtureRelativePath = 'tests/fixtures/list-users.n-plus-one.php.fixture';
$fixturePath = $root . '/' . $fixtureRelativePath;
$fixtureSource = file_get_contents($fixturePath);

if (!is_string($fixtureSource)) {
    throw new RuntimeException('Unable to read the N+1 negative-control fixture.');
}

$profileFailures = SyntaxProfile::failures($fixtureSource, $fixtureRelativePath);
$expectedProfileFailures = [
    'PHT003 tests/fixtures/list-users.n-plus-one.php.fixture:45 calls a database method inside a loop.',
];

requireScalingProof(
    $profileFailures === $expectedProfileFailures,
    'The N+1 negative control must be rejected by exactly one stable PHT003 diagnostic.',
);

$smallDatabase = createScalingDatabase($root, 'small', 2);
$largeDatabase = createScalingDatabase($root, 'large', 50);
$smallAccepted = runAcceptedRead($smallDatabase);
$largeAccepted = runAcceptedRead($largeDatabase);
$smallRejected = runRejectedRead($root, $fixturePath, $smallDatabase, 10);
$largeRejected = runRejectedRead($root, $fixturePath, $largeDatabase, 60);
$limitedRejected = runRejectedRead($root, $fixturePath, $largeDatabase, 3);

requireScalingProof($smallAccepted['body'] === $smallRejected['body'], 'Small accepted and N+1 outputs differ.');
requireScalingProof($largeAccepted['body'] === $largeRejected['body'], 'Large accepted and N+1 outputs differ.');
requireScalingProof($smallAccepted['statements'] === 1, 'Accepted small read must execute one statement.');
requireScalingProof($largeAccepted['statements'] === 1, 'Accepted large read must execute one statement.');
requireScalingProof($smallAccepted['maximum_executions'] === 1, 'Accepted small read repeated a statement.');
requireScalingProof($largeAccepted['maximum_executions'] === 1, 'Accepted large read repeated a statement.');
requireScalingProof(!$smallAccepted['truncated'], 'Accepted small trace was truncated.');
requireScalingProof(!$largeAccepted['truncated'], 'Accepted large trace was truncated.');
requireScalingProof($smallRejected['statements'] === 3, 'Small N+1 control must execute three statements.');
requireScalingProof($largeRejected['statements'] === 51, 'Large N+1 control must execute 51 statements.');
requireScalingProof($smallRejected['maximum_executions'] === 2, 'Small N+1 child query count changed.');
requireScalingProof($largeRejected['maximum_executions'] === 50, 'Large N+1 child query count changed.');
requireScalingProof(!$smallRejected['budget_exceeded'], 'Small N+1 control unexpectedly exceeded its budget.');
requireScalingProof(!$largeRejected['budget_exceeded'], 'Large N+1 control unexpectedly exceeded its budget.');
requireScalingProof($limitedRejected['budget_exceeded'], 'The bounded N+1 control did not exceed its budget.');
requireScalingProof($limitedRejected['statements'] === 3, 'Budget rejection must not enter the query trace.');
requireScalingProof($limitedRejected['maximum_executions'] === 2, 'Limited N+1 trace shape changed.');

fwrite(
    STDOUT,
    "PASS query scaling: accepted 1 -> 1; rejected N+1 3 -> 51; budget stopped statement 4\n",
);

/** @return array{body: string, statements: int, maximum_executions: int, truncated: bool} */
function runAcceptedRead(string $databasePath): array
{
    $trace = new QueryTrace(1);
    $handler = new ListUsersHandler(
        Connection::connect('sqlite:' . $databasePath, new QueryBudget(1), $trace),
    );
    $response = $handler->handle(new Request('GET', '/users'));
    $summary = $trace->snapshot();

    return [
        'body' => $response->body,
        'statements' => $summary['statements'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
        'truncated' => $summary['truncated'],
    ];
}

/** @return array{body: string, budget_exceeded: bool, statements: int, maximum_executions: int} */
function runRejectedRead(
    string $root,
    string $fixturePath,
    string $databasePath,
    int $budget,
): array {
    $process = proc_open(
        [PHP_BINARY, $fixturePath, $root, $databasePath, (string) $budget],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the N+1 negative-control fixture.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read the N+1 negative-control output.');
    }

    if ($exitCode !== 0) {
        throw new RuntimeException("N+1 negative control failed.\n{$stderr}\n{$stdout}");
    }

    $decoded = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException('N+1 negative control did not return a JSON object.');
    }

    $body = $decoded['body'] ?? null;
    $budgetExceeded = $decoded['budget_exceeded'] ?? null;
    $trace = $decoded['trace'] ?? null;

    if (!is_string($body) || !is_bool($budgetExceeded) || !is_array($trace)) {
        throw new RuntimeException('N+1 negative control returned an invalid result shape.');
    }

    $statements = $trace['statements'] ?? null;
    $maximumExecutions = $trace['maximum_executions_per_fingerprint'] ?? null;

    if (!is_int($statements) || !is_int($maximumExecutions)) {
        throw new RuntimeException('N+1 negative control returned an invalid trace shape.');
    }

    return [
        'body' => $body,
        'budget_exceeded' => $budgetExceeded,
        'statements' => $statements,
        'maximum_executions' => $maximumExecutions,
    ];
}

function createScalingDatabase(string $root, string $name, int $userCount): string
{
    if ($userCount < 1 || $userCount > 50) {
        throw new InvalidArgumentException('Scaling fixture count must be between 1 and 50.');
    }

    $directory = $root . '/tmp/query-scaling';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the query-scaling fixture directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset a query-scaling database.');
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

    return $databasePath;
}

function requireScalingProof(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
