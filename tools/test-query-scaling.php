<?php

declare(strict_types=1);

use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\ListUsers\UserActivitySummary;
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
$continuationDatabase = createScalingDatabase($root, 'continuation', 125);
$firstPage = runAcceptedRead($continuationDatabase);
$secondPage = runAcceptedRead($continuationDatabase, '50');
$thirdPage = runAcceptedRead($continuationDatabase, '100');
$firstPageData = acceptedPageData($firstPage['body']);
$secondPageData = acceptedPageData($secondPage['body']);
$thirdPageData = acceptedPageData($thirdPage['body']);
$continuedIds = [
    ...$firstPageData['ids'],
    ...$secondPageData['ids'],
    ...$thirdPageData['ids'],
];
$continuedEventCounts = [
    ...$firstPageData['event_counts'],
    ...$secondPageData['event_counts'],
    ...$thirdPageData['event_counts'],
];

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
requireScalingProof($firstPageData['ids'] === range(1, 50), 'First continuation page changed.');
requireScalingProof($secondPageData['ids'] === range(51, 100), 'Second continuation page changed.');
requireScalingProof($thirdPageData['ids'] === range(101, 125), 'Final continuation page changed.');
requireScalingProof($firstPageData['next_after_user_id'] === '50', 'First continuation cursor changed.');
requireScalingProof($secondPageData['next_after_user_id'] === '100', 'Second continuation cursor changed.');
requireScalingProof($thirdPageData['next_after_user_id'] === null, 'Final continuation cursor must be null.');
requireScalingProof($continuedIds === range(1, 125), 'Continuation introduced a gap or ordering error.');
requireScalingProof(count(array_unique($continuedIds)) === 125, 'Continuation returned a duplicate user.');
requireScalingProof(array_unique($continuedEventCounts) === [2], 'Continuation aggregate output changed.');

foreach ([$firstPage, $secondPage, $thirdPage] as $page) {
    requireScalingProof($page['statements'] === 1, 'Each accepted page must execute one statement.');
    requireScalingProof($page['maximum_executions'] === 1, 'An accepted page repeated a statement.');
    requireScalingProof(!$page['truncated'], 'An accepted page trace was truncated.');
}

fwrite(
    STDOUT,
    "PASS query scaling: accepted pages 1 each across 125 users; rejected N+1 3 -> 51; budget stopped statement 4\n",
);

/** @return array{body: string, statements: int, maximum_executions: int, truncated: bool} */
function runAcceptedRead(string $databasePath, ?string $afterUserId = null): array
{
    $trace = new QueryTrace(1);
    $handler = new ListUsersHandler(
        Connection::connect('sqlite:' . $databasePath, new QueryBudget(1), $trace),
    );
    $query = $afterUserId === null ? [] : ['after_user_id' => $afterUserId];
    $response = $handler->handle(new Request('GET', '/users', $query));
    $summary = $trace->snapshot();

    return [
        'body' => $response->body,
        'statements' => $summary['statements'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
        'truncated' => $summary['truncated'],
    ];
}

/** @return array{ids: list<int>, event_counts: list<int>, next_after_user_id: string|null} */
function acceptedPageData(string $body): array
{
    $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);

    if (
        !is_array($decoded)
        || count($decoded) !== 2
        || !array_key_exists('users', $decoded)
        || !array_key_exists('next_after_user_id', $decoded)
    ) {
        throw new RuntimeException('Accepted page returned an invalid response shape.');
    }

    $userValues = $decoded['users'];
    $nextAfterUserId = $decoded['next_after_user_id'];

    if (!is_array($userValues) || !array_is_list($userValues)) {
        throw new RuntimeException('Accepted page returned an invalid users collection.');
    }

    if ($nextAfterUserId !== null && !is_string($nextAfterUserId)) {
        throw new RuntimeException('Accepted page returned an invalid continuation value.');
    }

    $ids = [];
    $eventCounts = [];

    foreach ($userValues as $userValue) {
        if (!is_array($userValue)) {
            throw new RuntimeException('Accepted page returned a non-object user value.');
        }

        $row = [];

        foreach ($userValue as $name => $value) {
            if (!is_string($name)) {
                throw new RuntimeException('Accepted page returned a non-string user field name.');
            }

            $row[$name] = $value;
        }

        $user = UserActivitySummary::fromDatabaseRow($row);
        $ids[] = $user->id;
        $eventCounts[] = $user->eventCount;
    }

    return [
        'ids' => $ids,
        'event_counts' => $eventCounts,
        'next_after_user_id' => $nextAfterUserId,
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
    if ($userCount < 1 || $userCount > 125) {
        throw new InvalidArgumentException('Scaling fixture count must be between 1 and 125.');
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
