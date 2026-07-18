<?php

declare(strict_types=1);

namespace Example\Users\ListUsers;

use UnexpectedValueException;

final readonly class UserActivitySummary
{
    /** @param non-empty-string $name */
    private function __construct(
        public int $id,
        public string $name,
        public int $eventCount,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (
            count($row) !== 3
            || !array_key_exists('id', $row)
            || !array_key_exists('name', $row)
            || !array_key_exists('event_count', $row)
        ) {
            throw new UnexpectedValueException(
                'User activity row must contain exactly id, name, and event_count.',
            );
        }

        $name = $row['name'];

        if (!is_string($name) || $name === '') {
            throw new UnexpectedValueException('User activity name must be a non-empty string.');
        }

        return new self(
            self::positiveInteger($row['id'], 'id'),
            $name,
            self::nonNegativeInteger($row['event_count'], 'event_count'),
        );
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        $parsed = self::nonNegativeInteger($value, $field);

        if ($parsed < 1) {
            throw new UnexpectedValueException("User activity {$field} must be positive.");
        }

        return $parsed;
    }

    private static function nonNegativeInteger(mixed $value, string $field): int
    {
        if (is_int($value)) {
            if ($value >= 0) {
                return $value;
            }

            throw new UnexpectedValueException("User activity {$field} must be non-negative.");
        }

        if (!is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw new UnexpectedValueException("User activity {$field} has an invalid database representation.");
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($parsed) || $parsed < 0) {
            throw new UnexpectedValueException("User activity {$field} is outside the supported integer range.");
        }

        return $parsed;
    }
}
