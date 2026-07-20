<?php

declare(strict_types=1);

namespace Example\Jobs;

use UnexpectedValueException;

final readonly class SqliteUserWelcomeJobLease
{
    private function __construct(
        public string $jobId,
        public string $envelopeJson,
        public string $status,
        public int $attemptsStarted,
        public ?string $leaseToken,
        public ?int $leaseExpiresAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (
            count($row) !== 6
            || !array_key_exists('job_id', $row)
            || !array_key_exists('envelope_json', $row)
            || !array_key_exists('status', $row)
            || !array_key_exists('attempts_started', $row)
            || !array_key_exists('lease_token', $row)
            || !array_key_exists('lease_expires_at', $row)
        ) {
            throw self::invalid();
        }

        $jobId = $row['job_id'];
        $envelopeJson = $row['envelope_json'];
        $status = $row['status'];
        $attemptsStarted = $row['attempts_started'];
        $leaseToken = $row['lease_token'];
        $leaseExpiresAt = $row['lease_expires_at'];

        if (
            !is_string($jobId)
            || preg_match('/\A[0-9a-f]{32}\z/D', $jobId) !== 1
            || !is_string($envelopeJson)
            || strlen($envelopeJson) < 2
            || strlen($envelopeJson) > 2_048
            || !is_string($status)
            || ($status !== 'leased' && $status !== 'dead')
            || !is_int($attemptsStarted)
            || $attemptsStarted < 1
            || $attemptsStarted > 3
        ) {
            throw self::invalid();
        }

        if (
            $status === 'leased'
            && (
                !is_string($leaseToken)
                || preg_match('/\A[0-9a-f]{32}\z/D', $leaseToken) !== 1
                || !is_int($leaseExpiresAt)
                || $leaseExpiresAt < 0
            )
        ) {
            throw self::invalid();
        }

        if ($status === 'dead' && ($leaseToken !== null || $leaseExpiresAt !== null)) {
            throw self::invalid();
        }

        return new self(
            $jobId,
            $envelopeJson,
            $status,
            $attemptsStarted,
            $leaseToken,
            $leaseExpiresAt,
        );
    }

    private static function invalid(): UnexpectedValueException
    {
        return new UnexpectedValueException('SQLite returned an invalid claimed job row.');
    }
}
