<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

enum InspectionOutcome: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ScannerError = 'scanner_error';
}

interface PngInspector
{
    public function inspect(string $bytes, string $token): InspectionOutcome;
    public function discard(string $token): bool;
}
