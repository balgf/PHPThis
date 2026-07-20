<?php

declare(strict_types=1);

namespace Example\Jobs;

use InvalidArgumentException;
use PHPThis\Database\Connection;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final readonly class SqliteUserWelcomeJobWorker
{
    private const int LEASE_SECONDS = 30;

    public function __construct(
        private Connection $connection,
        private UserWelcomeJobHandler $handler,
        private UserWelcomeJobClock $clock,
    ) {
    }

    public function runOne(string $leaseToken): UserWelcomeJobOutcome
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $leaseToken) !== 1) {
            throw new InvalidArgumentException('Worker lease token must be 128-bit lowercase hexadecimal.');
        }

        $claimNow = $this->currentTime(0);
        $lease = $this->claim($claimNow, $leaseToken);

        if ($lease === null) {
            return UserWelcomeJobOutcome::Idle;
        }

        if ($lease->status === 'dead') {
        return UserWelcomeJobOutcome::DeadLettered;
        }

        $ownedLeaseToken = $lease->leaseToken;

        if (!is_string($ownedLeaseToken) || !hash_equals($leaseToken, $ownedLeaseToken)) {
            throw new RuntimeException('Claimed user-welcome job does not carry the requested lease token.');
        }

        try {
            $job = UserWelcomeJobEnvelope::fromStored(
                $lease->jobId,
                $lease->envelopeJson,
            );
        } catch (InvalidUserWelcomeJobEnvelope) {
            return $this->deadLetterInvalidEnvelope(
                $lease,
                $ownedLeaseToken,
                $this->currentTime($claimNow),
            );
        }

        return match ($job->version) {
            UserWelcomeJobEnvelope::VERSION => match ($job->type) {
                UserWelcomeJobEnvelope::TYPE => $this->deliver(
                    $lease,
                    $ownedLeaseToken,
                    $job,
                    $claimNow,
                ),
                default => $this->deadLetterInvalidEnvelope(
                    $lease,
                    $ownedLeaseToken,
                    $this->currentTime($claimNow),
                ),
            },
            default => $this->deadLetterInvalidEnvelope(
                $lease,
                $ownedLeaseToken,
                $this->currentTime($claimNow),
            ),
        };
    }

    private function claim(int $now, string $leaseToken): ?SqliteUserWelcomeJobLease
    {
        $this->connection->beginTransaction();

        try {
            $row = $this->connection->selectOneRow(
                <<<'SQL'
                    WITH next_job AS (
                        SELECT job_id
                        FROM application_jobs
                        WHERE (
                            status = 'available'
                            AND available_at <= :available_due_at
                            AND attempts_started < max_attempts
                        ) OR (
                            status = 'leased'
                            AND lease_expires_at <= :expired_due_at
                        )
                        ORDER BY
                            CASE
                                WHEN status = 'leased'
                                    AND attempts_started >= max_attempts
                                    THEN 0
                                ELSE 1
                            END ASC,
                            CASE
                                WHEN status = 'available' THEN available_at
                                ELSE lease_expires_at
                            END ASC,
                            created_at ASC,
                            job_id ASC
                        LIMIT 1
                    )
                    UPDATE application_jobs
                    SET
                        status = CASE
                            WHEN status = 'leased'
                                AND attempts_started >= max_attempts
                                THEN 'dead'
                            ELSE 'leased'
                        END,
                        attempts_started = CASE
                            WHEN status = 'leased'
                                AND attempts_started >= max_attempts
                                THEN attempts_started
                            ELSE attempts_started + 1
                        END,
                        lease_token = CASE
                            WHEN status = 'leased'
                                AND attempts_started >= max_attempts
                                THEN NULL
                            ELSE :claimed_lease_token
                        END,
                        lease_expires_at = CASE
                            WHEN status = 'leased'
                                AND attempts_started >= max_attempts
                                THEN NULL
                            ELSE :claimed_lease_expires_at
                        END,
                        last_failure_code = CASE
                            WHEN status = 'leased'
                                AND attempts_started >= max_attempts
                                THEN 'lease_expired_after_final_attempt'
                            WHEN status = 'leased' THEN 'lease_expired'
                            ELSE last_failure_code
                        END,
                        updated_at = :claim_updated_at,
                        dead_at = CASE
                            WHEN status = 'leased'
                                AND attempts_started >= max_attempts
                                THEN :expired_dead_at
                            ELSE NULL
                        END
                    WHERE job_id = (SELECT job_id FROM next_job)
                    RETURNING
                        job_id,
                        envelope_json,
                        status,
                        attempts_started,
                        lease_token,
                        lease_expires_at
                    SQL,
                [
                    'available_due_at' => $now,
                    'expired_due_at' => $now,
                    'claimed_lease_token' => $leaseToken,
                    'claimed_lease_expires_at' => $now + self::LEASE_SECONDS,
                    'claim_updated_at' => $now,
                    'expired_dead_at' => $now,
                ],
            );
            $this->connection->commit();
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }

        return $row === null
            ? null
            : SqliteUserWelcomeJobLease::fromDatabaseRow($row);
    }

    private function deliver(
        SqliteUserWelcomeJobLease $lease,
        string $leaseToken,
        UserWelcomeJobEnvelope $job,
        int $claimNow,
    ): UserWelcomeJobOutcome {
        $handlerNow = $this->currentTime($claimNow);

        if ($lease->leaseExpiresAt === null || $lease->leaseExpiresAt <= $handlerNow) {
            throw new RuntimeException('User-welcome job lease expired before delivery began.');
        }

        $this->connection->beginTransaction();

        try {
            try {
                $this->handler->handle($this->connection, $job, $handlerNow);
            } catch (Throwable) {
                $this->connection->rollBack();

                return $this->recordHandlerFailure(
                    $lease,
                    $leaseToken,
                    $this->currentTime($handlerNow),
                );
            }

            $completionNow = $this->currentTime($handlerNow);

            $completed = $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE application_jobs
                    SET
                        status = 'succeeded',
                        lease_token = NULL,
                        lease_expires_at = NULL,
                        last_failure_code = NULL,
                        updated_at = :completion_updated_at,
                        completed_at = :completion_completed_at
                    WHERE job_id = :completion_job_id
                      AND status = 'leased'
                      AND lease_token = :completion_lease_token
                      AND lease_expires_at > :completion_checked_at
                      AND attempts_started = :completion_attempts_started
                    SQL,
                [
                    'completion_updated_at' => $completionNow,
                    'completion_completed_at' => $completionNow,
                    'completion_job_id' => $lease->jobId,
                    'completion_lease_token' => $leaseToken,
                    'completion_checked_at' => $completionNow,
                    'completion_attempts_started' => $lease->attemptsStarted,
                ],
            );

            if ($completed !== 1) {
                throw new RuntimeException('User-welcome completion lost its active lease.');
            }

            $this->connection->commit();

            return UserWelcomeJobOutcome::Completed;
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }

    private function recordHandlerFailure(
        SqliteUserWelcomeJobLease $lease,
        string $leaseToken,
        int $now,
    ): UserWelcomeJobOutcome {
        return match ($lease->attemptsStarted) {
            1 => $this->scheduleRetry($lease, $leaseToken, $now, 5),
            2 => $this->scheduleRetry($lease, $leaseToken, $now, 30),
            3 => $this->deadLetterHandlerFailure($lease, $leaseToken, $now),
            default => throw new UnexpectedValueException('Claimed job has an unsupported attempt number.'),
        };
    }

    private function scheduleRetry(
        SqliteUserWelcomeJobLease $lease,
        string $leaseToken,
        int $now,
        int $delaySeconds,
    ): UserWelcomeJobOutcome {
        $scheduled = $this->connection->executeStatement(
            <<<'SQL'
                UPDATE application_jobs
                SET
                    status = 'available',
                    available_at = :retry_available_at,
                    lease_token = NULL,
                    lease_expires_at = NULL,
                    last_failure_code = 'handler_failure',
                    updated_at = :retry_updated_at
                WHERE job_id = :retry_job_id
                  AND status = 'leased'
                  AND lease_token = :retry_lease_token
                  AND lease_expires_at > :retry_checked_at
                  AND attempts_started = :retry_attempts_started
                SQL,
            [
                'retry_available_at' => $now + $delaySeconds,
                'retry_updated_at' => $now,
                'retry_job_id' => $lease->jobId,
                'retry_lease_token' => $leaseToken,
                'retry_checked_at' => $now,
                'retry_attempts_started' => $lease->attemptsStarted,
            ],
        );

        if ($scheduled !== 1) {
            throw new RuntimeException('User-welcome retry lost its active lease.');
        }

        return UserWelcomeJobOutcome::RetryScheduled;
    }

    private function deadLetterHandlerFailure(
        SqliteUserWelcomeJobLease $lease,
        string $leaseToken,
        int $now,
    ): UserWelcomeJobOutcome {
        $deadLettered = $this->connection->executeStatement(
            <<<'SQL'
                UPDATE application_jobs
                SET
                    status = 'dead',
                    lease_token = NULL,
                    lease_expires_at = NULL,
                    last_failure_code = 'handler_failure',
                    updated_at = :handler_dead_updated_at,
                    dead_at = :handler_dead_at
                WHERE job_id = :handler_dead_job_id
                  AND status = 'leased'
                  AND lease_token = :handler_dead_lease_token
                  AND lease_expires_at > :handler_dead_checked_at
                  AND attempts_started = :handler_dead_attempts_started
                SQL,
            [
                'handler_dead_updated_at' => $now,
                'handler_dead_at' => $now,
                'handler_dead_job_id' => $lease->jobId,
                'handler_dead_lease_token' => $leaseToken,
                'handler_dead_checked_at' => $now,
                'handler_dead_attempts_started' => $lease->attemptsStarted,
            ],
        );

        if ($deadLettered !== 1) {
            throw new RuntimeException('User-welcome dead letter lost its active lease.');
        }

            return UserWelcomeJobOutcome::DeadLettered;
    }

    private function currentTime(int $notBefore): int
    {
        $now = $this->clock->now();

        if (
            $now < $notBefore
            || $now < 0
            || $now > PHP_INT_MAX - self::LEASE_SECONDS
        ) {
            throw new UnexpectedValueException('Worker clock cannot produce a valid monotonic lease deadline.');
        }

        return $now;
    }

    private function deadLetterInvalidEnvelope(
        SqliteUserWelcomeJobLease $lease,
        string $leaseToken,
        int $now,
    ): UserWelcomeJobOutcome {
        $deadLettered = $this->connection->executeStatement(
            <<<'SQL'
                UPDATE application_jobs
                SET
                    status = 'dead',
                    lease_token = NULL,
                    lease_expires_at = NULL,
                    last_failure_code = 'invalid_envelope',
                    updated_at = :poison_dead_updated_at,
                    dead_at = :poison_dead_at
                WHERE job_id = :poison_dead_job_id
                  AND status = 'leased'
                  AND lease_token = :poison_dead_lease_token
                  AND lease_expires_at > :poison_dead_checked_at
                  AND attempts_started = :poison_dead_attempts_started
                SQL,
            [
                'poison_dead_updated_at' => $now,
                'poison_dead_at' => $now,
                'poison_dead_job_id' => $lease->jobId,
                'poison_dead_lease_token' => $leaseToken,
                'poison_dead_checked_at' => $now,
                'poison_dead_attempts_started' => $lease->attemptsStarted,
            ],
        );

        if ($deadLettered !== 1) {
            throw new RuntimeException('Poison user-welcome job lost its active lease.');
        }

        return UserWelcomeJobOutcome::DeadLettered;
    }
}
