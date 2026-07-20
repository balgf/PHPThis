<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use Example\Jobs\UserWelcomeJobEnvelope;
use PHPThis\Database\Connection;
use RuntimeException;

/**
 * Narrow SQL owner for the create-user operation's three-statement transaction.
 */
final readonly class TransactionalCreateUser implements CreateUserOperation
{
    public function __construct(private Connection $connection)
    {
    }

    public function execute(CreateUserCommand $command): void
    {
        $job = UserWelcomeJobEnvelope::forEmail($command->email);
        $publishedAt = time();
        $this->connection->beginTransaction();

        try {
            $insertedUsers = $this->connection->executeStatement(
                'INSERT INTO users (name, email) VALUES (:name, :email)',
                ['name' => $command->name, 'email' => $command->email],
            );

            if ($insertedUsers !== 1) {
                throw new RuntimeException('Create user must insert exactly one user row.');
            }

            $insertedEvents = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO user_events (user_id, event_type)
                    SELECT users.id, :event_type
                    FROM users
                    WHERE users.email = :email
                    SQL,
                ['event_type' => 'user.created', 'email' => $command->email],
            );

            if ($insertedEvents !== 1) {
                throw new RuntimeException('Create user must insert exactly one event row.');
            }

            $insertedJobs = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO application_jobs (
                        job_id,
                        envelope_json,
                        status,
                        available_at,
                        attempts_started,
                        max_attempts,
                        lease_token,
                        lease_expires_at,
                        last_failure_code,
                        created_at,
                        updated_at,
                        completed_at,
                        dead_at
                    )
                    VALUES (
                        :job_id,
                        :envelope_json,
                        'available',
                        :available_at,
                        0,
                        3,
                        NULL,
                        NULL,
                        NULL,
                        :created_at,
                        :updated_at,
                        NULL,
                        NULL
                    )
                    SQL,
                [
                    'job_id' => $job->jobId,
                    'envelope_json' => $job->toJson(),
                    'available_at' => $publishedAt,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ],
            );

            if ($insertedJobs !== 1) {
                throw new RuntimeException('Create user must publish exactly one welcome job.');
            }

            $this->connection->commit();
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }
}
