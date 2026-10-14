<?php

declare(strict_types=1);

namespace BulkWriteRecipe;

use LogicException;
use PHPThis\Database\Connection;
use UnexpectedValueException;

final readonly class PostgresLedgerImport
{
    public const string INSERT_SQL = <<<'SQL'
        INSERT INTO phpthis_recipe_entries (entry_id, quantity, amount, memo)
        SELECT input.entry_id, input.quantity, input.amount, input.memo
        FROM jsonb_to_recordset(CAST(:entries_json AS jsonb))
            AS input(entry_id bigint, quantity bigint, amount numeric(30,4), memo text)
        SQL;

    public const string SELECT_SQL = <<<'SQL'
        SELECT CAST(persisted.entry_id AS text) AS entry_id,
               CAST(persisted.quantity AS text) AS quantity,
               CAST(persisted.amount AS text) AS amount, persisted.memo AS memo
        FROM jsonb_to_recordset(CAST(:requested_entries AS jsonb)) AS requested(entry_id bigint)
        CROSS JOIN LATERAL (
            SELECT entries.entry_id, entries.quantity, entries.amount, entries.memo
            FROM phpthis_recipe_entries AS entries
            WHERE entries.entry_id = requested.entry_id
            LIMIT 1
        ) AS persisted
        ORDER BY persisted.entry_id ASC
        LIMIT :row_limit
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    /** @return list<LedgerEntry> */
    public function import(LedgerBatch $batch): array
    {
        if ($batch->rowCount === 0) {
            return [];
        }
        if ($this->connection->inTransaction()) {
            throw new LogicException('Ledger import requires its own transaction.');
        }
        $this->connection->beginTransaction();
        try {
            $affected = $this->connection->executeStatement(self::INSERT_SQL, ['entries_json' => $batch->encodedRows]);
            if ($affected !== $batch->rowCount) {
                throw new UnexpectedValueException('Ledger insert affected an unexpected number of rows.');
            }
            $rows = $this->connection->selectAllRows(self::SELECT_SQL, ['requested_entries' => $batch->encodedRows, 'row_limit' => $batch->rowCount]);
            if (count($rows) !== $batch->rowCount) {
                throw new UnexpectedValueException('Ledger projection returned an unexpected number of rows.');
            }
            $entries = [];
            foreach ($rows as $row) {
                $entries[] = LedgerEntry::fromDatabaseRow($row);
            }
            $this->connection->commit();
            return $entries;
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }
}
