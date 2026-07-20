<?php

declare(strict_types=1);

use Example\Jobs\RecordUserWelcomeDelivery;
use Example\Jobs\SqliteUserWelcomeJobWorker;
use Example\Jobs\SystemUserWelcomeJobClock;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__, 2) . '/autoload.php';

try {
    /** @var list<string> $arguments */
    $arguments = $argv;

    if (count($arguments) > 2) {
        throw new InvalidArgumentException('The worker accepts at most one database path.');
    }

    $databasePath = dirname(__DIR__, 2) . '/tmp/example.sqlite';

    if (array_key_exists(1, $arguments)) {
        $submittedPath = $arguments[1];
        $isAbsolutePath = DIRECTORY_SEPARATOR === '\\'
            ? preg_match('/\A[A-Za-z]:[\\\\\/]/D', $submittedPath) === 1
            : str_starts_with($submittedPath, '/');

        if (
            $submittedPath === ''
            || strlen($submittedPath) > 4_096
            || !$isAbsolutePath
            || str_ends_with($submittedPath, '/')
            || str_ends_with($submittedPath, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $submittedPath) === 1
        ) {
            throw new InvalidArgumentException('The worker database path is invalid.');
        }

        $databasePath = $submittedPath;
    }

    if (!is_file($databasePath)) {
        throw new RuntimeException('The worker database is unavailable.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(3),
        new QueryTrace(3),
        options: [\PDO::ATTR_TIMEOUT => 5],
    );
    $worker = new SqliteUserWelcomeJobWorker(
        $connection,
        new RecordUserWelcomeDelivery(),
        new SystemUserWelcomeJobClock(),
    );
    $outcome = $worker->runOne(bin2hex(random_bytes(16)));

    echo json_encode(['outcome' => $outcome->value], JSON_THROW_ON_ERROR) . "\n";
    exit(0);
} catch (Throwable) {
    echo "{\"outcome\":\"unexpected_failure\"}\n";
    exit(1);
}
