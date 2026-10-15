<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

interface CatalogClock
{
    public function now(): int;
}

final class SystemCatalogClock implements CatalogClock
{
    public function now(): int { return time(); }
}
