<?php

declare(strict_types=1);

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticateAccountRequest;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\ResolveAccountTenant;
use Example\Accounts\ResolvedTenant;
use Example\Users\CreateUser\AuthorizeCreateUser;
use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\CreateUser\CreateUserOperation;
use Example\Users\CreateUser\UnacceptableCreateUserValues;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;

final readonly class RunTestAllowCreateUserPolicy implements
    AuthenticateAccountRequest,
    ResolveAccountTenant,
    AuthorizeCreateUser
{
    public function authenticate(Request $request): AuthenticatedPrincipal
    {
        return AuthenticatedPrincipal::fromPositiveInteger(7);
    }

    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant {
        return ResolvedTenant::forAccount($accountId);
    }

    public function authorizeCreate(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void {
    }
}


function createUserTestHandler(CreateUserOperation $operation): CreateUserHandler
{
    $policy = new RunTestAllowCreateUserPolicy();

    return new CreateUserHandler($policy, $policy, $policy, $operation);
}


function createUserSecretProbe(): string
{
    return 'submitted-secret-issue-4';
}


function exactCreateUserBody(int $bytes): string
{
    $prefix = '{"name":"';
    $suffix = '","email":"a@example.com"}';
    $nameBytes = $bytes - strlen($prefix) - strlen($suffix);

    if ($nameBytes < 1) {
        throw new InvalidArgumentException('Exact create-user body requires room for a non-empty name.');
    }

    return $prefix . str_repeat('a', $nameBytes) . $suffix;
}


/** @return array<string, array{body: string, failure: class-string<Throwable>, status: 400|413|422}> */
function invalidCreateUserCases(): array
{
    $cases = [];

    foreach (structurallyInvalidCreateUserBodies() as $case => $body) {
        $cases[$case] = [
            'body' => $body,
            'failure' => InvalidRequest::class,
            'status' => 400,
        ];
    }

    foreach (unacceptableCreateUserValueBodies() as $case => $body) {
        $cases[$case] = [
            'body' => $body,
            'failure' => UnacceptableCreateUserValues::class,
            'status' => 422,
        ];
    }

    $cases['exact_endpoint_overflow'] = [
        'body' => exactCreateUserBody(2_049),
        'failure' => RequestBodyTooLarge::class,
        'status' => 413,
    ];

    return $cases;
}


/** @return array<string, string> */
function structurallyInvalidCreateUserBodies(): array
{
    $tooDeep = str_repeat('{"value":', 17) . 'null' . str_repeat('}', 17);

    return [
        'empty' => '',
        'unfinished_object' => '{',
        'multiple_documents' => '{}{}',
        'top_level_string' => '"text"',
        'top_level_integer' => '7',
        'top_level_boolean' => 'true',
        'top_level_null' => 'null',
        'top_level_list' => '[]',
        'malformed_utf8_document' => "\xB1\x31",
        'excessive_depth' => $tooDeep,
        'missing_name' => '{"email":"ada@example.com"}',
        'missing_email' => '{"name":"Ada"}',
        'null_name' => '{"name":null,"email":"ada@example.com"}',
        'null_email' => '{"name":"Ada","email":null}',
        'unknown_field' => '{"name":"Ada","email":"ada@example.com","is_admin":true}',
        'unknown_secret_field' => '{"name":"Ada","email":"ada@example.com","api_token":"'
            . createUserSecretProbe()
            . '"}',
        'case_mismatched_name' => '{"Name":"Ada","email":"ada@example.com"}',
        'integer_name' => '{"name":7,"email":"ada@example.com"}',
        'float_name' => '{"name":7.5,"email":"ada@example.com"}',
        'boolean_name' => '{"name":true,"email":"ada@example.com"}',
        'list_name' => '{"name":[],"email":"ada@example.com"}',
        'object_name' => '{"name":{},"email":"ada@example.com"}',
        'nested_name' => '{"name":{"value":["Ada"]},"email":"ada@example.com"}',
        'integer_email' => '{"name":"Ada","email":7}',
        'float_email' => '{"name":"Ada","email":7.5}',
        'boolean_email' => '{"name":"Ada","email":false}',
        'list_email' => '{"name":"Ada","email":[]}',
        'object_email' => '{"name":"Ada","email":{}}',
        'nested_email' => '{"name":"Ada","email":{"value":["ada@example.com"]}}',
        'unacceptable_name_with_integer_email' => '{"name":"","email":7}',
        'integer_email_before_unacceptable_name' => '{"email":7,"name":""}',
        'integer_name_with_unacceptable_email' => '{"name":7,"email":"not-an-email"}',
        'unacceptable_email_before_integer_name' => '{"email":"not-an-email","name":7}',
        'unacceptable_name_with_unknown_field' => '{"name":"","email":"ada@example.com","unexpected":"value"}',
        'unknown_field_before_unacceptable_name' => '{"unexpected":"value","email":"ada@example.com","name":""}',
        'malformed_utf8_in_name' => "{\"name\":\"\xB1\",\"email\":\"ada@example.com\"}",
        'lone_surrogate_in_name' => '{"name":"\uD800","email":"ada@example.com"}',
    ];
}


/** @return array<string, string> */
function unacceptableCreateUserValueBodies(): array
{
    return [
        'empty_name' => '{"name":"","email":"ada@example.com"}',
        'blank_name' => '{"name":"   ","email":"ada@example.com"}',
        'padded_name' => '{"name":" Ada","email":"ada@example.com"}',
        'empty_email' => '{"name":"Ada","email":""}',
        'invalid_email' => '{"name":"Ada","email":"not-an-email"}',
        'unicode_local_email' => '{"name":"Ada","email":"jos\u00e9@example.com"}',
        'double_dot_email' => '{"name":"Ada","email":"ada@example..com"}',
        'local_domain_email' => '{"name":"Ada","email":"ada@localhost"}',
        'trailing_dot_email' => '{"name":"Ada","email":"ada@example.com."}',
        'padded_email' => '{"name":"Ada","email":" ada@example.com"}',
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
        new QueryBudget(12),
        new QueryTrace(12),
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
            CREATE TABLE application_jobs (
                job_id TEXT PRIMARY KEY
                    CHECK (
                        length(job_id) = 32
                        AND job_id NOT GLOB '*[^0-9a-f]*'
                    ),
                envelope_json TEXT NOT NULL
                    CHECK (length(CAST(envelope_json AS BLOB)) BETWEEN 2 AND 2048),
                status TEXT NOT NULL
                    CHECK (status IN ('available', 'leased', 'succeeded', 'dead')),
                available_at INTEGER NOT NULL CHECK (available_at >= 0),
                attempts_started INTEGER NOT NULL DEFAULT 0
                    CHECK (attempts_started >= 0),
                max_attempts INTEGER NOT NULL CHECK (max_attempts = 3),
                lease_token TEXT
                    CHECK (
                        lease_token IS NULL
                        OR (
                            length(lease_token) = 32
                            AND lease_token NOT GLOB '*[^0-9a-f]*'
                        )
                    ),
                lease_expires_at INTEGER
                    CHECK (lease_expires_at IS NULL OR lease_expires_at >= 0),
                last_failure_code TEXT
                    CHECK (
                        last_failure_code IS NULL
                        OR last_failure_code IN (
                            'handler_failure',
                            'invalid_envelope',
                            'lease_expired',
                            'lease_expired_after_final_attempt'
                        )
                    ),
                created_at INTEGER NOT NULL CHECK (created_at >= 0),
                updated_at INTEGER NOT NULL CHECK (updated_at >= 0),
                completed_at INTEGER CHECK (completed_at IS NULL OR completed_at >= 0),
                dead_at INTEGER CHECK (dead_at IS NULL OR dead_at >= 0),
                CHECK (attempts_started <= max_attempts),
                CHECK (
                    (
                        status = 'available'
                        AND attempts_started < max_attempts
                        AND lease_token IS NULL
                        AND lease_expires_at IS NULL
                        AND completed_at IS NULL
                        AND dead_at IS NULL
                    )
                    OR (
                        status = 'leased'
                        AND attempts_started BETWEEN 1 AND max_attempts
                        AND lease_token IS NOT NULL
                        AND lease_expires_at IS NOT NULL
                        AND completed_at IS NULL
                        AND dead_at IS NULL
                    )
                    OR (
                        status = 'succeeded'
                        AND attempts_started BETWEEN 1 AND max_attempts
                        AND lease_token IS NULL
                        AND lease_expires_at IS NULL
                        AND completed_at IS NOT NULL
                        AND dead_at IS NULL
                    )
                    OR (
                        status = 'dead'
                        AND attempts_started BETWEEN 1 AND max_attempts
                        AND lease_token IS NULL
                        AND lease_expires_at IS NULL
                        AND completed_at IS NULL
                        AND dead_at IS NOT NULL
                        AND last_failure_code IS NOT NULL
                    )
                )
            ) STRICT
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE INDEX application_jobs_available_due_idx
            ON application_jobs (available_at, created_at, job_id)
            WHERE status = 'available'
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE INDEX application_jobs_expired_lease_idx
            ON application_jobs (lease_expires_at, created_at, job_id)
            WHERE status = 'leased'
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE welcome_deliveries (
                idempotency_key TEXT PRIMARY KEY
                    CHECK (
                        length(idempotency_key) = 64
                        AND idempotency_key NOT GLOB '*[^0-9a-f]*'
                    ),
                job_id TEXT NOT NULL
                    CHECK (
                        length(job_id) = 32
                        AND job_id NOT GLOB '*[^0-9a-f]*'
                    ),
                recipient_email TEXT NOT NULL
                    CHECK (length(CAST(recipient_email AS BLOB)) BETWEEN 3 AND 254),
                created_at INTEGER NOT NULL CHECK (created_at >= 0)
            ) STRICT
        SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE account_memberships (
                principal_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                PRIMARY KEY (principal_id, account_id)
            )
            SQL,
    );
    $connection->executeStatement(
        'INSERT INTO account_memberships (principal_id, account_id) VALUES (:principal_id, :account_id)',
        ['principal_id' => 7, 'account_id' => 42],
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE account_users (
                user_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, account_id),
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
            SQL,
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
