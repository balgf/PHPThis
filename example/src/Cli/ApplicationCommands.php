<?php

declare(strict_types=1);

namespace Example\Cli;

use Example\Coordination\RedisScheduleRunLease;
use Example\Coordination\RedisScheduleRunLeaseAcquireOutcome;
use Example\Coordination\RedisScheduleRunLeaseReleaseOutcome;
use Example\Coordination\RedisScheduleRunLeaseRenewOutcome;
use Example\Coordination\RedisScheduleRunLeaseTrace;
use Example\Coordination\RedisScheduleRunLeaseUnavailable;
use Example\Jobs\RecordUserWelcomeDelivery;
use Example\Jobs\SqliteUserWelcomeJobWorker;
use Example\Jobs\UserWelcomeJobClock;
use Example\Jobs\UserWelcomeJobOutcome;
use Example\Migrations\ApplicationMigrationFailed;
use Example\Migrations\ApplicationMigrationFailureReason;
use Example\Migrations\ApplicationMigrationOutcome;
use Example\Migrations\LocalMigrationLock;
use Example\Migrations\SqliteApplicationMigrations;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use RuntimeException;
use Throwable;

final readonly class ApplicationCommands
{
    public function __construct(
        private string $databasePath,
        private UserWelcomeJobClock $clock,
        private string $redisHost,
        private int $redisPort,
        private int $redisDatabase,
        private string $scheduleLeaseKey,
    ) {
    }

    public function run(ApplicationCommandName $command): ApplicationCommandExecution
    {
        return match ($command) {
            ApplicationCommandName::DatabaseMigrate => new ApplicationCommandExecution(
                $command,
                $this->runMigrations(),
            ),
            ApplicationCommandName::JobsRunOne => new ApplicationCommandExecution(
                $command,
                $this->runOneJob(),
            ),
            ApplicationCommandName::ScheduleRun => $this->runSchedule($command),
        };
    }

    private function runSchedule(ApplicationCommandName $command): ApplicationCommandExecution
    {
        $databasePath = $this->existingDatabasePath();
        $currentMinute = intdiv($this->clock->now(), 60);

        if ($currentMinute % 5 !== 0) {
            return new ApplicationCommandExecution(
                $command,
                ApplicationCommandOutcome::NotDue,
                [],
            );
        }

        $trace = new RedisScheduleRunLeaseTrace();
        $scheduleLease = RedisScheduleRunLease::connect(
            $this->redisHost,
            $this->redisPort,
            $this->redisDatabase,
            $this->scheduleLeaseKey,
            $trace,
        );

        if ($scheduleLease->acquire() === RedisScheduleRunLeaseAcquireOutcome::Contended) {
            return new ApplicationCommandExecution(
                $command,
                ApplicationCommandOutcome::OverlapSkipped,
                $trace->snapshot(),
            );
        }

        try {
            if ($scheduleLease->renew() === RedisScheduleRunLeaseRenewOutcome::Lost) {
                throw new RedisScheduleRunLeaseUnavailable($trace->snapshot());
            }

            $outcome = $this->runOneJob($databasePath);
        } catch (Throwable $failure) {
            $releaseFailed = false;

            try {
                $releaseFailed = $scheduleLease->release()
                    === RedisScheduleRunLeaseReleaseOutcome::Lost;
            } catch (Throwable) {
                $releaseFailed = true;
            }

            if ($failure instanceof RedisScheduleRunLeaseUnavailable || $releaseFailed) {
                throw new RedisScheduleRunLeaseUnavailable($trace->snapshot(), $failure);
            }

            throw $failure;
        }

        if ($scheduleLease->release() === RedisScheduleRunLeaseReleaseOutcome::Lost) {
            throw new RedisScheduleRunLeaseUnavailable($trace->snapshot());
        }

        return new ApplicationCommandExecution($command, $outcome, $trace->snapshot());
    }

    private function runOneJob(?string $databasePath = null): ApplicationCommandOutcome
    {
        $databasePath ??= $this->existingDatabasePath();
        $connection = Connection::connect(
            'sqlite:' . $databasePath,
            new QueryBudget(3),
            new QueryTrace(3),
            options: [\PDO::ATTR_TIMEOUT => 5],
        );
        $worker = new SqliteUserWelcomeJobWorker(
            $connection,
            new RecordUserWelcomeDelivery(),
            $this->clock,
        );
        $outcome = $worker->runOne(bin2hex(random_bytes(16)));

        return match ($outcome) {
            UserWelcomeJobOutcome::Idle => ApplicationCommandOutcome::Idle,
            UserWelcomeJobOutcome::Completed => ApplicationCommandOutcome::Completed,
            UserWelcomeJobOutcome::RetryScheduled => ApplicationCommandOutcome::RetryScheduled,
            UserWelcomeJobOutcome::DeadLettered => ApplicationCommandOutcome::DeadLettered,
        };
    }

    private function runMigrations(): ApplicationCommandOutcome
    {
        $databasePath = $this->migrationDatabasePath();
        $outcome = (new SqliteApplicationMigrations(
            $databasePath,
            new LocalMigrationLock($databasePath . '.migration.lock'),
        ))->run();

        return match ($outcome) {
            ApplicationMigrationOutcome::Applied => ApplicationCommandOutcome::Applied,
            ApplicationMigrationOutcome::UpToDate => ApplicationCommandOutcome::UpToDate,
        };
    }

    private function existingDatabasePath(): string
    {
        if (!is_file($this->databasePath)) {
            throw new RuntimeException('The application database is unavailable.');
        }

        $resolvedPath = realpath($this->databasePath);

        if (!is_string($resolvedPath)) {
            throw new RuntimeException('The application database path cannot be resolved.');
        }

        return $resolvedPath;
    }

    private function migrationDatabasePath(): string
    {
        $resolvedDirectory = realpath(dirname($this->databasePath));

        if (!is_string($resolvedDirectory) || !is_dir($resolvedDirectory)) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::LedgerUnavailable,
            );
        }

        $databasePath = $resolvedDirectory . DIRECTORY_SEPARATOR . basename($this->databasePath);

        if (is_link($databasePath) || (file_exists($databasePath) && !is_file($databasePath))) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::LedgerUnavailable,
            );
        }

        if (!is_file($databasePath)) {
            return $databasePath;
        }

        $resolvedPath = realpath($databasePath);

        if (!is_string($resolvedPath)) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::LedgerUnavailable,
            );
        }

        return $resolvedPath;
    }
}
