<?php

declare(strict_types=1);

namespace Example\Cli;

use Example\Jobs\RecordUserWelcomeDelivery;
use Example\Jobs\SqliteUserWelcomeJobWorker;
use Example\Jobs\UserWelcomeJobClock;
use Example\Jobs\UserWelcomeJobOutcome;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final readonly class ApplicationCommands
{
    public function __construct(
        private string $databasePath,
        private UserWelcomeJobClock $clock,
        private LocalScheduleLock $scheduleLock,
    ) {
    }

    public function run(ApplicationCommandName $command): ApplicationCommandExecution
    {
        return match ($command) {
            ApplicationCommandName::JobsRunOne => new ApplicationCommandExecution(
                $command,
                $this->runOneJob(),
            ),
            ApplicationCommandName::ScheduleRun => new ApplicationCommandExecution(
                $command,
                $this->runSchedule(),
            ),
        };
    }

    private function runSchedule(): ApplicationCommandOutcome
    {
        $currentMinute = intdiv($this->clock->now(), 60);

        if ($currentMinute % 5 !== 0) {
            return ApplicationCommandOutcome::NotDue;
        }

        if (!$this->scheduleLock->acquire()) {
            return ApplicationCommandOutcome::OverlapSkipped;
        }

        try {
            return $this->runOneJob();
        } finally {
            $this->scheduleLock->release();
        }
    }

    private function runOneJob(): ApplicationCommandOutcome
    {
        $connection = Connection::connect(
            'sqlite:' . $this->databasePath,
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
}
