<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__) . '/autoload.php';

$root = dirname(__DIR__);
$tmpDirectory = $root . '/tmp';

if (!is_dir($tmpDirectory) && !mkdir($tmpDirectory, 0777, true) && !is_dir($tmpDirectory)) {
    throw new RuntimeException('Unable to create the example database directory.');
}

$databasePath = $tmpDirectory . '/example.sqlite';
$connection = Connection::connect(
    'sqlite:' . $databasePath,
    new QueryBudget(9),
    new QueryTrace(9),
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
            PRIMARY KEY (account_id, document_key)
        )
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
        INSERT OR IGNORE INTO documents (account_id, document_key, title)
        VALUES (:account_id, :document_key, :title)
        SQL,
    [
        'account_id' => 42,
        'document_key' => 'Doc_9-z',
        'title' => 'Example document',
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

fwrite(STDOUT, "Example database ready at tmp/example.sqlite\n");
