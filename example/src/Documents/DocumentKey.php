<?php

declare(strict_types=1);

namespace Example\Documents;

use InvalidArgumentException;

final readonly class DocumentKey
{
    private function __construct(public string $value)
    {
    }

    public static function fromToken(string $value): self
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Document key must be a canonical route token.');
        }

        return new self($value);
    }
}
