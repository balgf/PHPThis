<?php

declare(strict_types=1);

use Example\Users\ListUsers\UserSummary;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;
use PHPUnit\Framework\Assert;

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function databaseBoundaryBehaviorTests(): Generator
{
    yield 'connection marks only its password argument as sensitive' => static function (): void {
    $parameters = (new ReflectionMethod(Connection::class, 'connect'))->getParameters();
    $sensitiveParameters = [];

    foreach ($parameters as $parameter) {
        if ($parameter->getAttributes(SensitiveParameter::class) !== []) {
            $sensitiveParameters[] = $parameter->getName();
        }
    }

    if ($sensitiveParameters !== ['password']) {
        throw new RuntimeException('Expected only the connection password argument to be sensitive.');
    }
};

    yield 'connection binds named values and enforces its budget' => static function (): void {
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

    if (
        $row !== ['id' => 7, 'name' => 'Ada']
        || $budget->used() !== 3
        || $budget->exceeded()
    ) {
        throw new RuntimeException('Expected exact-limit success without an exceeded budget state.');
    }

    $user = UserSummary::fromDatabaseRow($row);

    if ($user->id !== 7 || $user->name !== 'Ada') {
        throw new RuntimeException('Expected the raw PDO row to be parsed immediately.');
    }

    $overrunBudget = new QueryBudget(1);
    $overrunTrace = new QueryTrace(1);
    $overrunConnection = Connection::connect(
        'sqlite::memory:',
        $overrunBudget,
        $overrunTrace,
    );
    $overrunConnection->selectOneRow('SELECT :value AS value', ['value' => 1]);
    $budgetWasExceeded = false;

    try {
        $overrunConnection->selectOneRow('SELECT :value AS value', ['value' => 2]);
    } catch (QueryBudgetExceeded) {
        $budgetWasExceeded = true;
    }

    if (
        !$budgetWasExceeded
        || !$overrunBudget->exceeded()
        || $overrunBudget->used() !== 1
        || $overrunTrace->snapshot()['statements'] !== 1
    ) {
        throw new RuntimeException('Expected an over-budget statement to be rejected without being traced.');
    }
};

    yield 'connection keeps SQL-looking bound text outside statement structure' => static function (): void {
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

    yield 'connection accepts portable parameter names and rejects invalid or duplicate names before database work' => static function (): void {
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

    yield 'query trace detects repetition without exposing SQL or parameters' => static function (): void {
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
    $repeatedFingerprint = $summary['queries'][2]['fingerprint'] ?? null;

    Assert::assertSame(
        [
            'schema_version' => 1,
            'event' => 'database.query_summary',
            'statements' => 4,
            'repeated_fingerprints' => 1,
            'maximum_executions_per_fingerprint' => 2,
            'retained_queries' => 3,
            'repeated_query_executions' => 2,
            'repeated_fingerprint_is_sha256' => true,
            'repeated_fingerprint_bytes' => 71,
            'slowest_duration_is_non_negative' => true,
            'total_duration_covers_slowest' => true,
            'contains_sql' => false,
            'contains_parameter_name' => false,
            'contains_first_value' => false,
            'contains_second_value' => false,
        ],
        [
            'schema_version' => $summary['schema_version'],
            'event' => $summary['event'],
            'statements' => $summary['statements'],
            'repeated_fingerprints' => $summary['repeated_fingerprints'],
            'maximum_executions_per_fingerprint' => $summary['maximum_executions_per_fingerprint'],
            'retained_queries' => count($summary['queries']),
            'repeated_query_executions' => $summary['queries'][2]['executions'] ?? null,
            'repeated_fingerprint_is_sha256' => is_string($repeatedFingerprint)
                && str_starts_with($repeatedFingerprint, 'sha256:'),
            'repeated_fingerprint_bytes' => is_string($repeatedFingerprint)
                ? strlen($repeatedFingerprint)
                : null,
            'slowest_duration_is_non_negative' => $summary['slowest_execute_duration_us'] >= 0,
            'total_duration_covers_slowest' => $summary['total_execute_duration_us']
                >= $summary['slowest_execute_duration_us'],
            'contains_sql' => str_contains($json, 'SELECT'),
            'contains_parameter_name' => str_contains($json, 'first_name'),
            'contains_first_value' => str_contains($json, 'Ada'),
            'contains_second_value' => str_contains($json, 'Grace'),
        ],
        'Expected a redacted structured repetition summary.',
    );
};

    yield 'query trace records database failures before rethrowing them' => static function (): void {
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

    Assert::assertSame(
        [
            'database_exception_rethrown' => true,
            'statements' => 1,
            'failures' => 1,
            'retained_query_failures' => 1,
            'contains_table_name' => false,
            'contains_database_message' => false,
        ],
        [
            'database_exception_rethrown' => $databaseFailed,
            'statements' => $summary['statements'],
            'failures' => $summary['failures'],
            'retained_query_failures' => $summary['queries'][0]['failures'] ?? null,
            'contains_table_name' => str_contains($json, 'missing_users'),
            'contains_database_message' => str_contains($json, 'no such table'),
        ],
        'Expected the failed statement to be traced and rethrown.',
    );
};

    yield 'query trace requires a positive fingerprint bound' => static function (): void {
    $caught = null;

    try {
        new QueryTrace(0);
    } catch (Throwable $failure) {
        $caught = $failure;
    }

    Assert::assertInstanceOf(
        InvalidArgumentException::class,
        $caught,
        'Expected a non-positive query trace bound to fail.',
    );
};

    yield 'query trace bounds retained fingerprint details' => static function (): void {
    $trace = new QueryTrace(1);
    $connection = Connection::connect('sqlite::memory:', new QueryBudget(3), $trace);

    $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $connection->executeStatement(
        'INSERT INTO users (id, name) VALUES (:id, :name)',
        ['id' => 7, 'name' => 'Ada'],
    );
    $connection->selectOneRow('SELECT id, name FROM users WHERE id = :id', ['id' => 7]);
    $summary = $trace->snapshot();

    Assert::assertSame(
        [
            'tracked_fingerprints' => 1,
            'truncated' => true,
            'untracked_statements' => 2,
        ],
        [
            'tracked_fingerprints' => $summary['tracked_fingerprints'],
            'truncated' => $summary['truncated'],
            'untracked_statements' => $summary['untracked_statements'],
        ],
        'Expected fingerprint detail retention to remain bounded.',
    );
};
}
