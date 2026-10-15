<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class CatalogPrincipal
{
    public function __construct(public int $id)
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Invalid principal.');
        }
    }
}
