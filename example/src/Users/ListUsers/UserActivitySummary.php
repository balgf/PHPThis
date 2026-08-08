<?php

declare(strict_types=1);

namespace Example\Users\ListUsers;

use Example\Users\UserId;
use UnexpectedValueException;

final readonly class UserActivitySummary
{
    /** @param non-empty-string $name */
    private function __construct(
        public UserId $id,
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

        if (!is_string($name) || $name === '' || preg_match('//u', $name) !== 1) {
            throw new UnexpectedValueException(
                'User activity name has an invalid database representation.',
            );
        }

        return new self(
            UserId::fromDatabaseValue($row['id']),
            $name,
            self::nonNegativeInteger($row['event_count'], 'event_count'),
        );
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
