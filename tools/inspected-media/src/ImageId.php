<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class ImageId
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $value) !== 1) {
            throw new \PHPThis\Http\InvalidRequest('Invalid image identity.');
        }
    }
}
