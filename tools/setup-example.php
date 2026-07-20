<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__) . '/autoload.php';

$root = dirname(__DIR__);
$defaultDatabasePath = $root . '/tmp/example.sqlite';
/** @var list<string> $arguments */
$arguments = $argv;

if (count($arguments) > 2) {
    throw new InvalidArgumentException('Usage: php tools/setup-example.php [absolute-database-path]');
}

$databasePath = $defaultDatabasePath;
$usesExplicitDatabasePath = array_key_exists(1, $arguments);

if ($usesExplicitDatabasePath) {
    $submittedDatabasePath = $arguments[1];
    $isAbsoluteDatabasePath = DIRECTORY_SEPARATOR === '\\'
        ? preg_match('/\A[A-Za-z]:[\\\\\/]/D', $submittedDatabasePath) === 1
        : str_starts_with($submittedDatabasePath, '/');

    if (
        $submittedDatabasePath === ''
        || strlen($submittedDatabasePath) > 4_096
        || !$isAbsoluteDatabasePath
        || str_ends_with($submittedDatabasePath, '/')
        || str_ends_with($submittedDatabasePath, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $submittedDatabasePath) === 1
    ) {
        throw new InvalidArgumentException(
            'The optional example database path must be a safe non-empty absolute file path.',
        );
    }

    $databasePath = $submittedDatabasePath;
}

$databaseDirectory = dirname($databasePath);

if (
    !is_dir($databaseDirectory)
    && !mkdir($databaseDirectory, 0777, true)
    && !is_dir($databaseDirectory)
) {
    throw new RuntimeException('Unable to create the example database directory.');
}

$connection = Connection::connect(
    'sqlite:' . $databasePath,
    new QueryBudget(17),
    new QueryTrace(17),
);

$connection->executeStatement(
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE
        )
        SQL,
);
$connection->executeStatement(
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS user_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            event_type TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
        SQL,
);
$connection->executeStatement(
    'CREATE INDEX IF NOT EXISTS user_events_user_id_idx ON user_events (user_id)',
);
$connection->executeStatement(
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS application_jobs (
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
        CREATE INDEX IF NOT EXISTS application_jobs_available_due_idx
        ON application_jobs (available_at, created_at, job_id)
        WHERE status = 'available'
        SQL,
);
$connection->executeStatement(
    <<<'SQL'
        CREATE INDEX IF NOT EXISTS application_jobs_expired_lease_idx
        ON application_jobs (lease_expires_at, created_at, job_id)
        WHERE status = 'leased'
        SQL,
);
$connection->executeStatement(
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS welcome_deliveries (
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
        INSERT OR IGNORE INTO users (name, email)
        VALUES
            (:first_name, :first_email),
            (:second_name, :second_email)
        SQL,
    [
        'first_name' => 'Ada Lovelace',
        'first_email' => 'ada@example.com',
        'second_name' => 'Grace Hopper',
        'second_email' => 'grace@example.com',
    ],
);
$connection->executeStatement(
    <<<'SQL'
        INSERT INTO user_events (user_id, event_type)
        SELECT users.id, :insert_event_type
        FROM users
        WHERE users.email = :email
          AND NOT EXISTS (
              SELECT 1
              FROM user_events
              WHERE user_events.user_id = users.id
                AND user_events.event_type = :existing_event_type
          )
        SQL,
    [
        'insert_event_type' => 'user.seeded',
        'email' => 'ada@example.com',
        'existing_event_type' => 'user.seeded',
    ],
);
$connection->executeStatement(
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS documents (
            account_id INTEGER NOT NULL,
            document_key TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'general',
            sort_rank INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (account_id, document_key)
        )
        SQL,
);
$documentColumns = $connection->selectAllRows('PRAGMA table_info(documents)');
$hasDocumentCategory = false;
$hasDocumentSortRank = false;

foreach ($documentColumns as $documentColumn) {
    $columnName = $documentColumn['name'] ?? null;

    if ($columnName === 'category') {
        $hasDocumentCategory = true;
    }

    if ($columnName === 'sort_rank') {
        $hasDocumentSortRank = true;
    }
}

if (!$hasDocumentCategory) {
    $connection->executeStatement(
        "ALTER TABLE documents ADD COLUMN category TEXT NOT NULL DEFAULT 'general'",
    );
}

if (!$hasDocumentSortRank) {
    $connection->executeStatement(
        'ALTER TABLE documents ADD COLUMN sort_rank INTEGER NOT NULL DEFAULT 0',
    );
}
$connection->executeStatement(
    <<<'SQL'
        CREATE INDEX IF NOT EXISTS documents_account_rank_key_idx
        ON documents (account_id, sort_rank, document_key COLLATE BINARY)
        SQL,
);
$connection->executeStatement(
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS account_memberships (
            principal_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            PRIMARY KEY (principal_id, account_id)
        )
        SQL,
);
$connection->executeStatement(
    <<<'SQL'
        INSERT INTO documents (account_id, document_key, title, category, sort_rank)
        VALUES (
            :insert_account_id,
            :insert_document_key,
            :insert_title,
            :insert_category,
            :insert_sort_rank
        )
        ON CONFLICT (account_id, document_key) DO UPDATE SET
            title = :update_title,
            category = :update_category,
            sort_rank = :update_sort_rank
        SQL,
    [
        'insert_account_id' => 42,
        'insert_document_key' => 'Doc_9-z',
        'insert_title' => 'Example document',
        'insert_category' => 'general',
        'insert_sort_rank' => 10,
        'update_title' => 'Example document',
        'update_category' => 'general',
        'update_sort_rank' => 10,
    ],
);
$connection->executeStatement(
    <<<'SQL'
        INSERT OR IGNORE INTO account_memberships (principal_id, account_id)
        VALUES (:principal_id, :account_id)
        SQL,
    [
        'principal_id' => 7,
        'account_id' => 42,
    ],
);

$displayPath = $usesExplicitDatabasePath ? $databasePath : 'tmp/example.sqlite';
fwrite(STDOUT, "Example database ready at {$displayPath}\n");
