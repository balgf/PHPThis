<?php

declare(strict_types=1);

namespace Example\Jobs;

use PHPThis\Database\Connection;
use RuntimeException;

final readonly class RecordUserWelcomeDelivery implements UserWelcomeJobHandler
{
    public function handle(
        Connection $connection,
        UserWelcomeJobEnvelope $job,
        int $now,
    ): void {
        $recorded = $connection->executeStatement(
            <<<'SQL'
                INSERT INTO welcome_deliveries (
                    idempotency_key,
                    job_id,
                    recipient_email,
                    created_at
                )
                VALUES (
                    :delivery_idempotency_key,
                    :delivery_job_id,
                    :delivery_recipient_email,
                    :delivery_created_at
                )
                ON CONFLICT (idempotency_key) DO NOTHING
                SQL,
            [
                'delivery_idempotency_key' => $job->idempotencyKey,
                'delivery_job_id' => $job->jobId,
                'delivery_recipient_email' => $job->email,
                'delivery_created_at' => $now,
            ],
        );

        if ($recorded !== 0 && $recorded !== 1) {
            throw new RuntimeException('A user-welcome delivery must affect at most one row.');
        }
    }
}
