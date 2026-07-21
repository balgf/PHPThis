<?php

declare(strict_types=1);

namespace Example\Accounts;

use InvalidArgumentException;

final readonly class AccountId
{
    private function __construct(public int $value)
    {
    }

    public static function fromPositiveInteger(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Account id must be a positive integer.');
        }

        return new self($value);
    }
}
