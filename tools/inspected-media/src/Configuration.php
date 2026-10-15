<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class HttpConfiguration
{
    private function __construct(public CatalogRoot $root) {}
    public static function fromInput(mixed $root): self { return new self(CatalogRoot::fromInput($root)); }
}

final readonly class WorkerConfiguration
{
    private function __construct(public CatalogRoot $root, public InspectionDefinition $definition) {}

    public static function fromInputs(mixed $root, mixed $image): self
    {
        $parsed = CatalogRoot::fromInput($root);
        return new self($parsed, InspectionDefinition::fromImageId($image, dirname(__DIR__)));
    }
}
