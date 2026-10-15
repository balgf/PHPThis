<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobHandler;
use PHPThis\Database\Connection;
use RuntimeException;

final readonly class RecordMySqlWelcomeDelivery implements UserWelcomeJobHandler
{
    public function handle(Connection $connection, UserWelcomeJobEnvelope $job, int $now): void
    {
        // Execute only the previously committed system-owned obligation. The
        // worker credential and an internally well-formed envelope are insufficient.
        $intent = $connection->selectOneRow(
            'SELECT idempotency_key FROM welcome_requests WHERE idempotency_key = :key AND recipient_email = :email',
            ['key' => $job->idempotencyKey, 'email' => $job->email],
        );
        if ($intent === null) {
            throw new RuntimeException('No matching committed welcome obligation exists.');
        }
        $changed = $connection->executeStatement(
            <<<'SQL'
                INSERT INTO welcome_deliveries (idempotency_key, recipient_email, created_at)
                VALUES (:key, :email, :created_at) AS incoming
                ON DUPLICATE KEY UPDATE idempotency_key = incoming.idempotency_key
                SQL,
            ['key' => $job->idempotencyKey, 'email' => $job->email, 'created_at' => $now],
        );
        if ($changed !== 0 && $changed !== 1) {
            throw new RuntimeException('Welcome delivery affected an unexpected row count.');
        }
        $receipt = $connection->selectOneRow(
            'SELECT idempotency_key FROM welcome_deliveries WHERE idempotency_key = :key AND recipient_email = :email',
            ['key' => $job->idempotencyKey, 'email' => $job->email],
        );
        if ($receipt === null) {
            throw new RuntimeException('Welcome receipt does not match its committed obligation.');
        }
    }
}
