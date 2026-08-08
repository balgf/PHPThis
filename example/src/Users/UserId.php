<?php

declare(strict_types=1);

namespace Example\Users;

use InvalidArgumentException;
use UnexpectedValueException;

final readonly class UserId
{
    private function __construct(public int $value)
    {
    }

    public static function fromPositiveInteger(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('User id must be a positive integer.');
        }

        return new self($value);
    }

    public static function fromDatabaseValue(mixed $value): self
    {
        if (is_int($value)) {
            if ($value > 0) {
                return new self($value);
            }

            throw new UnexpectedValueException('User id must be positive.');
        }

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new UnexpectedValueException('User id has an invalid database representation.');
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($parsed) || $parsed < 1) {
            throw new UnexpectedValueException('User id is outside the supported integer range.');
        }

        return new self($parsed);
    }
}
