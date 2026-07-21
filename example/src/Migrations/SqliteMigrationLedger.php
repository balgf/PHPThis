<?php

declare(strict_types=1);

namespace Example\Migrations;

use PHPThis\Database\Connection;
use Throwable;

final readonly class SqliteMigrationLedger
{
    private const string CREATE_LEDGER_SQL = <<<'SQL'
        CREATE TABLE application_migrations (
            position INTEGER PRIMARY KEY
                CHECK (position BETWEEN 1 AND 512),
            migration_id TEXT NOT NULL UNIQUE
                CHECK (length(CAST(migration_id AS BLOB)) BETWEEN 1 AND 96),
            checksum_sha256 TEXT NOT NULL
                CHECK (
                    length(checksum_sha256) = 64
                    AND checksum_sha256 NOT GLOB '*[^0-9a-f]*'
                ),
            applied_at_epoch INTEGER NOT NULL
                CHECK (applied_at_epoch >= 0)
        ) STRICT
        SQL;

    private const string SELECT_LEDGER_OBJECTS_SQL = <<<'SQL'
        SELECT
            sqlite_master.type,
            sqlite_master.name,
            sqlite_master.sql
        FROM sqlite_master
        WHERE sqlite_master.tbl_name = 'application_migrations'
        ORDER BY sqlite_master.type ASC, sqlite_master.name ASC
        LIMIT 3
        SQL;

    private const string SELECT_HISTORY_SQL = <<<'SQL'
        SELECT
            application_migrations.position,
            application_migrations.migration_id,
            application_migrations.checksum_sha256
        FROM application_migrations
        ORDER BY application_migrations.position ASC
        LIMIT 513
        SQL;

    private const string INSERT_APPLIED_SQL = <<<'SQL'
        INSERT INTO application_migrations (
            position,
            migration_id,
            checksum_sha256,
            applied_at_epoch
        ) VALUES (
            :position,
            :migration_id,
            :checksum_sha256,
            unixepoch()
        )
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<array{position: int, identifier: non-empty-string, checksum: non-empty-string}> $manifest
     */
    public function loadHistory(array $manifest): MigrationHistory
    {
        if ($manifest === [] || count($manifest) > 512) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::HistoryInvalid,
            );
        }

        try {
            $storedObjects = $this->connection->selectAllRows(self::SELECT_LEDGER_OBJECTS_SQL);

            if ($storedObjects === []) {
                $this->connection->executeStatement(self::CREATE_LEDGER_SQL);
            } elseif ($storedObjects !== self::expectedLedgerObjects()) {
                throw new ApplicationMigrationFailed(
                    ApplicationMigrationFailureReason::HistoryInvalid,
                );
            }

            $rows = $this->connection->selectAllRows(self::SELECT_HISTORY_SQL);
        } catch (Throwable $exception) {
            if ($exception instanceof ApplicationMigrationFailed) {
                throw $exception;
            }

            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::LedgerUnavailable,
                previous: $exception,
            );
        }

        return MigrationHistory::fromDatabaseRows($rows, $manifest);
    }

    /**
     * @return list<array{type: string, name: string, sql: ?string}>
     */
    private static function expectedLedgerObjects(): array
    {
        return [
            [
                'type' => 'index',
                'name' => 'sqlite_autoindex_application_migrations_1',
                'sql' => null,
            ],
            [
                'type' => 'table',
                'name' => 'application_migrations',
                'sql' => self::CREATE_LEDGER_SQL,
            ],
        ];
    }

    public function record(int $position, string $identifier, string $checksum): void
    {
        $this->connection->executeStatement(
            self::INSERT_APPLIED_SQL,
            [
                'position' => $position,
                'migration_id' => $identifier,
                'checksum_sha256' => $checksum,
            ],
        );
    }
}
