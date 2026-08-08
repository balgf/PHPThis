<?php

declare(strict_types=1);

namespace Example\Users\GetUser;

use Example\Users\UserId;
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

        if (!is_string($name) || $name === '' || preg_match('//u', $name) !== 1) {
            throw new UnexpectedValueException(
                'User details name has an invalid database representation.',
            );
        }

        return new self(UserId::fromDatabaseValue($row['id']), $name);
    }
}
