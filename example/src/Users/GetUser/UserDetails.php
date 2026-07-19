<?php

declare(strict_types=1);

namespace Example\Users\GetUser;

use UnexpectedValueException;

final readonly class UserDetails
{
    /** @param non-empty-string $name */
    private function __construct(
        public UserId $id,
        public string $name,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (
            count($row) !== 2
            || !array_key_exists('id', $row)
            || !array_key_exists('name', $row)
        ) {
            throw new UnexpectedValueException('User details row must contain exactly id and name.');
        }

        $name = $row['name'];

        if (!is_string($name) || $name === '') {
            throw new UnexpectedValueException('User details name must be a non-empty string.');
        }

        return new self(self::userId($row['id']), $name);
    }

    private static function userId(mixed $value): UserId
    {
        if (is_int($value)) {
            if ($value > 0) {
                return UserId::fromPositiveInteger($value);
            }

            throw new UnexpectedValueException('User details id must be positive.');
        }

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new UnexpectedValueException('User details id has an invalid database representation.');
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($parsed) || $parsed < 1) {
            throw new UnexpectedValueException('User details id is outside the supported integer range.');
        }

        return UserId::fromPositiveInteger($parsed);
    }
}
