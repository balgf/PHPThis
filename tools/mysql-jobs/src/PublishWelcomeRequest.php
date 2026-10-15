<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use Example\Jobs\UserWelcomeJobEnvelope;
use InvalidArgumentException;
use PHPThis\Database\Connection;
use RuntimeException;

final readonly class PublishWelcomeRequest
{
    public function __construct(private Connection $connection, private MySqlWelcomeClock $clock)
    {
    }

    public function publish(UserWelcomeJobEnvelope $job, int $delaySeconds = 0): void
    {
        if ($delaySeconds < 0 || $delaySeconds > 86_400) {
            throw new InvalidArgumentException('Welcome delay must be between zero and one day.');
        }
        $now = $this->clock->now();
        $this->connection->beginTransaction();
        try {
            $requested = $this->connection->executeStatement(
                'INSERT INTO welcome_requests (idempotency_key, recipient_email, job_id, requested_at) VALUES (:key, :email, :job_id, :requested_at)',
                ['key' => $job->idempotencyKey, 'email' => $job->email, 'job_id' => $job->jobId, 'requested_at' => $now],
            );
            $published = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO application_jobs (job_id, envelope_json, status, attempts_started, available_at, created_at, updated_at)
                    VALUES (:job_id, :envelope, 'available', 0, :available_at, :created_at, :updated_at)
                    SQL,
                ['job_id' => $job->jobId, 'envelope' => $job->toJson(), 'available_at' => $now + $delaySeconds,
                    'created_at' => $now, 'updated_at' => $now],
            );
            if ($requested !== 1 || $published !== 1) {
                throw new RuntimeException('Welcome publication did not create its exact pair.');
            }
            $this->connection->commit();
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }
}
