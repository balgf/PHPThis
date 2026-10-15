<?php

declare(strict_types=1);

use MySqlWelcomeRecipe\MySqlWelcomeClock;
use MySqlWelcomeRecipe\MySqlWelcomeJobWorker;
use MySqlWelcomeRecipe\MySqlWelcomeSession;
use MySqlWelcomeRecipe\RecordMySqlWelcomeDelivery;
use MySqlWelcomeRecipe\ReferenceEnvironment;
use MySqlWelcomeRecipe\WorkerConfiguration;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

try {
    if (($argv[1] ?? null) !== 'jobs:run-one') {
        fwrite(STDERR, "{\"error\":\"unknown_command\"}\n");
        exit(2);
    }
    if ($argc !== 2) {
        fwrite(STDERR, "{\"error\":\"invalid_arguments\"}\n");
        exit(2);
    }
    require __DIR__ . '/bootstrap.php';
    $configuration = ReferenceEnvironment::forWorker();
    $connection = Connection::connect($configuration->dsn, new QueryBudget(32), new QueryTrace(32), WorkerConfiguration::USERNAME, $configuration->password, [PDO::ATTR_TIMEOUT => 2]);
    MySqlWelcomeSession::configure($connection);
    $outcome = (new MySqlWelcomeJobWorker($connection, new RecordMySqlWelcomeDelivery(), new MySqlWelcomeClock($connection)))->runOne(bin2hex(random_bytes(16)));
    fwrite(STDOUT, json_encode(['command' => 'jobs:run-one', 'outcome' => $outcome->value], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable) {
    fwrite(STDERR, "{\"error\":\"command_failed\"}\n");
    exit(1);
}
