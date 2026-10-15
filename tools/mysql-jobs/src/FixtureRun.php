<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use InvalidArgumentException;

final readonly class FixtureRun
{
    private function __construct(public string $id)
    {
    }

    public static function fromInput(mixed $id): self
    {
        if (!is_string($id) || preg_match('/\A[0-9a-f]{32}\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid isolated fixture identity.');
        }
        return new self($id);
    }
}
