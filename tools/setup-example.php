<?php

declare(strict_types=1);

use Example\ApplicationComposition;
use Example\ApplicationDatabasePath;
use Example\Cli\ApplicationCommandName;
use Example\InvalidApplicationDatabasePath;
use Example\Jobs\SystemUserWelcomeJobClock;
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
    $databasePath = $arguments[1];
}

try {
    $applicationDatabasePath = ApplicationDatabasePath::fromString($databasePath);
} catch (InvalidApplicationDatabasePath $exception) {
    throw new InvalidArgumentException(
        'The optional example database path must be a safe non-empty absolute file path.',
        previous: $exception,
    );
}

$databaseDirectory = dirname($applicationDatabasePath->value);

if (
    !is_dir($databaseDirectory)
    && !mkdir($databaseDirectory, 0777, true)
    && !is_dir($databaseDirectory)
) {
    throw new RuntimeException('Unable to create the example database directory.');
}

(new ApplicationComposition($applicationDatabasePath))
    ->commands(new SystemUserWelcomeJobClock())
    ->run(ApplicationCommandName::DatabaseMigrate);

$connection = Connection::connect(
    'sqlite:' . $applicationDatabasePath->value,
    new QueryBudget(4),
    new QueryTrace(4),
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

$displayPath = $usesExplicitDatabasePath ? $applicationDatabasePath->value : 'tmp/example.sqlite';
fwrite(STDOUT, "Example database ready at {$displayPath}\n");
