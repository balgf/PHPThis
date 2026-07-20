<?php

declare(strict_types=1);

use Example\Jobs\SqliteUserWelcomeJobWorker;
use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobClock;
use Example\Jobs\UserWelcomeJobHandler;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__) . '/autoload.php';

/** @var list<string> $arguments */
$arguments = $argv;

if (count($arguments) !== 4) {
    throw new InvalidArgumentException('Crash worker requires database path, time, and lease token.');
}

$databasePath = $arguments[1];
$submittedNow = filter_var($arguments[2], FILTER_VALIDATE_INT);
$leaseToken = $arguments[3];

if (
    !str_starts_with($databasePath, DIRECTORY_SEPARATOR)
    || !is_file($databasePath)
    || !is_int($submittedNow)
    || $submittedNow < 0
    || preg_match('/\A[0-9a-f]{32}\z/D', $leaseToken) !== 1
) {
    throw new InvalidArgumentException('Crash worker arguments are invalid.');
}

$connection = Connection::connect(
    'sqlite:' . $databasePath,
    new QueryBudget(3),
    new QueryTrace(3),
    options: [PDO::ATTR_TIMEOUT => 5],
);
$handler = new class implements UserWelcomeJobHandler {
    public function handle(
        Connection $connection,
        UserWelcomeJobEnvelope $job,
        int $now,
    ): void {
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
        sleep(60);
    }
};
$clock = new class($submittedNow) implements UserWelcomeJobClock {
    public function __construct(private readonly int $currentTime)
    {
    }

    public function now(): int
    {
        return $this->currentTime;
    }
};
$worker = new SqliteUserWelcomeJobWorker($connection, $handler, $clock);
$worker->runOne($leaseToken);
