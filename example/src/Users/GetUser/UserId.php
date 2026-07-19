<?php

declare(strict_types=1);

namespace Example\Users\GetUser;

use InvalidArgumentException;

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
}
