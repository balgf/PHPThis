<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class CatalogSettings
{
    private function __construct(public string $definition, public string $sourceFingerprint, public int $lastStarted) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (array_keys($row) !== ['definition', 'source_fingerprint', 'last_started'] || !is_string($row['definition'])
            || !is_string($row['source_fingerprint']) || !is_int($row['last_started'])) {
            throw new CatalogUnavailable('Catalog settings unavailable.');
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', $row['definition']) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $row['source_fingerprint']) !== 1 || $row['last_started'] < 0) {
            throw new CatalogUnavailable('Catalog settings unavailable.');
        }
        return new self($row['definition'], $row['source_fingerprint'], $row['last_started']);
    }
}
