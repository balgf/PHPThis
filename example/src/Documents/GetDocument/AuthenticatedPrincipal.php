<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use InvalidArgumentException;

final readonly class AuthenticatedPrincipal
{
    private function __construct(public int $id)
    {
    }

    public static function fromPositiveInteger(int $id): self
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Authenticated principal id must be positive.');
        }

        return new self($id);
    }
}
