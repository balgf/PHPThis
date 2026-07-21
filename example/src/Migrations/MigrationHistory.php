<?php

declare(strict_types=1);

namespace Example\Migrations;

final readonly class MigrationHistory
{
    private function __construct(private int $appliedCount)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array{position: int, identifier: non-empty-string, checksum: non-empty-string}> $manifest
     */
    public static function fromDatabaseRows(array $rows, array $manifest): self
    {
        if (count($rows) > count($manifest) || count($rows) > 512) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::HistoryInvalid,
            );
        }

        foreach ($rows as $index => $row) {
            $expected = $manifest[$index] ?? null;

            if (
                !is_array($expected)
                || array_keys($row) !== ['position', 'migration_id', 'checksum_sha256']
                || !is_int($row['position'])
                || !is_string($row['migration_id'])
                || !is_string($row['checksum_sha256'])
                || $row['position'] !== $expected['position']
                || $row['migration_id'] !== $expected['identifier']
            ) {
                throw new ApplicationMigrationFailed(
                    ApplicationMigrationFailureReason::HistoryInvalid,
                );
            }

            if (!hash_equals($expected['checksum'], $row['checksum_sha256'])) {
                throw new ApplicationMigrationFailed(
                    ApplicationMigrationFailureReason::ChecksumDrift,
                    $expected['identifier'],
                );
            }
        }

        return new self(count($rows));
    }

    public function contains(int $position): bool
    {
        return $position <= $this->appliedCount;
    }
}
