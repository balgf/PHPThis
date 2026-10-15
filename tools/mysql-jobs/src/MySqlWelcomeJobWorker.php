<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use Example\Jobs\InvalidUserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobClock;
use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobHandler;
use Example\Jobs\UserWelcomeJobOutcome;

use InvalidArgumentException;
use PHPThis\Database\Connection;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final readonly class MySqlWelcomeJobWorker
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
        $lease = $this->claimOne($leaseToken);
        return $lease === null ? UserWelcomeJobOutcome::Idle : $this->finishClaim($lease);
    }

    public function claimOne(string $leaseToken): ?MySqlWelcomeJobLease
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $leaseToken) !== 1) {
            throw new InvalidArgumentException('Worker lease token must be 128-bit lowercase hexadecimal.');
        }
        return $this->claim($this->currentTime(0), $leaseToken);
    }

    public function finishClaim(MySqlWelcomeJobLease $lease): UserWelcomeJobOutcome
    {
        if ($lease->status === 'dead') {
            return UserWelcomeJobOutcome::DeadLettered;
        }

        $ownedLeaseToken = $lease->leaseToken;

        if (!is_string($ownedLeaseToken)) {
            throw new RuntimeException('Claimed user-welcome job does not carry the requested lease token.');
        }

        $claimNow = $this->currentTime(($lease->leaseExpiresAt ?? 0) - self::LEASE_SECONDS);

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

    private function claim(int $now, string $leaseToken): ?MySqlWelcomeJobLease
    {
        $this->connection->beginTransaction();
        try {
            $candidate = $this->connection->selectOneRow(
                <<<'SQL'
                    SELECT job_id, attempts_started, status
                    FROM application_jobs
                    WHERE next_action_at <= :claim_due_at
                    ORDER BY next_action_at, job_id
                    LIMIT 1 FOR UPDATE SKIP LOCKED
                    SQL,
                ['claim_due_at' => $now],
            );
            if ($candidate === null) {
                $this->connection->commit();
                return null;
            }
            $id = $candidate['job_id'] ?? null;
            $attempts = $candidate['attempts_started'] ?? null;
            $status = $candidate['status'] ?? null;
            if (count($candidate) !== 3 || !is_string($id) || preg_match('/\A[0-9a-f]{32}\z/D', $id) !== 1
                || !is_int($attempts) || $attempts < 0 || $attempts > 3
                || ($status !== 'available' && $status !== 'leased')) {
                throw new UnexpectedValueException('MySQL returned an invalid job candidate.');
            }
            $claimNow = $this->currentTime($now);
            if ($status === 'leased' && $attempts === 3) {
                $changed = $this->connection->executeStatement(
                    <<<'SQL'
                        UPDATE application_jobs SET status = 'dead', lease_token = NULL,
                            lease_expires_at = NULL, dead_at = :dead_at, updated_at = :updated_at,
                            last_failure_code = 'lease_expired_after_final_attempt'
                        WHERE job_id = :job_id AND status = 'leased' AND attempts_started = 3
                          AND lease_expires_at <= :expired_at
                        SQL,
                    ['dead_at' => $claimNow, 'updated_at' => $claimNow, 'job_id' => $id, 'expired_at' => $claimNow],
                );
            } else {
                $changed = $this->connection->executeStatement(
                    <<<'SQL'
                        UPDATE application_jobs SET status = 'leased', attempts_started = attempts_started + 1,
                            lease_token = :token, lease_expires_at = :expires_at, updated_at = :updated_at
                        WHERE job_id = :job_id AND attempts_started < 3
                          AND next_action_at <= :due_at
                        SQL,
                    ['token' => $leaseToken, 'expires_at' => $claimNow + self::LEASE_SECONDS,
                        'updated_at' => $claimNow, 'job_id' => $id, 'due_at' => $claimNow],
                );
            }
            if ($changed !== 1) {
                throw new RuntimeException('MySQL job claim did not affect exactly one row.');
            }
            $row = $this->connection->selectOneRow(
                'SELECT job_id, envelope_json, status, attempts_started, lease_token, lease_expires_at FROM application_jobs WHERE job_id = :job_id',
                ['job_id' => $id],
            );
            $lease = MySqlWelcomeJobLease::fromDatabaseRow($row ?? throw new RuntimeException('Claimed job disappeared.'));
            $this->connection->commit();
            return $lease;
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }

    private function deliver(
        MySqlWelcomeJobLease $lease,
        string $leaseToken,
        UserWelcomeJobEnvelope $job,
        int $claimNow,
    ): UserWelcomeJobOutcome {
        $handlerNow = $this->currentTime($claimNow);

        if ($lease->leaseExpiresAt === null || $lease->leaseExpiresAt <= $handlerNow) {
            throw new WelcomeLeaseLost('User-welcome job lease expired before delivery began.');
        }

        $this->connection->beginTransaction();

        try {
            // Lock the exact delivery before any effect. Re-read the server clock after
            // obtaining the lock: the SELECT may have waited behind another transaction.
            $owned = $this->connection->selectOneRow(
                'SELECT lease_expires_at FROM application_jobs WHERE job_id = :job_id AND status = \'leased\' AND lease_token = :token AND attempts_started = :attempt FOR UPDATE',
                ['job_id' => $lease->jobId, 'token' => $leaseToken, 'attempt' => $lease->attemptsStarted],
            );
            $handlerNow = $this->currentTime($handlerNow);
            $expiresAt = $owned['lease_expires_at'] ?? null;
            if (!is_int($expiresAt) || $expiresAt <= $handlerNow) {
                throw new WelcomeLeaseLost('User-welcome delivery lost its active lease.');
            }
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
                throw new WelcomeLeaseLost('User-welcome completion lost its active lease.');
            }

            $this->connection->commit();

            return UserWelcomeJobOutcome::Completed;
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }

    private function recordHandlerFailure(MySqlWelcomeJobLease $lease, string $leaseToken, int $notBefore): UserWelcomeJobOutcome
    {
        return $this->recordFailure($lease, $leaseToken, $notBefore, false);
    }

    private function deadLetterInvalidEnvelope(MySqlWelcomeJobLease $lease, string $leaseToken, int $notBefore): UserWelcomeJobOutcome
    {
        return $this->recordFailure($lease, $leaseToken, $notBefore, true);
    }

    private function recordFailure(MySqlWelcomeJobLease $lease, string $leaseToken, int $notBefore, bool $poison): UserWelcomeJobOutcome
    {
        $this->connection->beginTransaction();
        try {
            $owned = $this->connection->selectOneRow(
                'SELECT lease_expires_at FROM application_jobs WHERE job_id = :job_id AND status = \'leased\' AND lease_token = :token AND attempts_started = :attempt FOR UPDATE',
                ['job_id' => $lease->jobId, 'token' => $leaseToken, 'attempt' => $lease->attemptsStarted],
            );
            $now = $this->currentTime($notBefore);
            $expiresAt = $owned['lease_expires_at'] ?? null;
            if (!is_int($expiresAt) || $expiresAt <= $now) {
                throw new WelcomeLeaseLost('User-welcome failure transition lost its active lease.');
            }
            $delay = match ($lease->attemptsStarted) {
                1 => 5,
                2 => 30,
                3 => 0,
                default => throw new UnexpectedValueException('Invalid delivery attempt.'),
            };
            $dead = $poison || $lease->attemptsStarted === 3;
            $changed = $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE application_jobs SET status = :status, available_at = :available_at,
                        lease_token = NULL, lease_expires_at = NULL, last_failure_code = :code,
                        updated_at = :updated_at, dead_at = :dead_at
                    WHERE job_id = :job_id AND status = 'leased' AND lease_token = :token
                      AND lease_expires_at > :checked_at AND attempts_started = :attempt
                    SQL,
                ['status' => $dead ? 'dead' : 'available', 'available_at' => $now + $delay,
                    'code' => $poison ? 'invalid_envelope' : 'handler_failure',
                    'updated_at' => $now, 'dead_at' => $dead ? $now : null,
                    'job_id' => $lease->jobId, 'token' => $leaseToken,
                    'checked_at' => $now, 'attempt' => $lease->attemptsStarted],
            );
            if ($changed !== 1) {
                throw new RuntimeException('User-welcome failure transition did not affect exactly one row.');
            }
            $this->connection->commit();
            return $dead ? UserWelcomeJobOutcome::DeadLettered : UserWelcomeJobOutcome::RetryScheduled;
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
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

}
