<?php

declare(strict_types=1);

namespace BulkWriteRecipe;

use LogicException;
use PHPThis\Database\Connection;
use UnexpectedValueException;

final readonly class MySqlLedgerImport
{
    public const string INSERT_SQL = <<<'SQL'
        INSERT INTO phpthis_recipe_entries (entry_id, quantity, amount, memo)
        SELECT input.entry_id, input.quantity, input.amount, input.memo
        FROM JSON_TABLE(:entries_json, '$[*]' COLUMNS (
            entry_id BIGINT PATH '$.entry_id' ERROR ON EMPTY ERROR ON ERROR,
            quantity BIGINT PATH '$.quantity' ERROR ON EMPTY ERROR ON ERROR,
            amount DECIMAL(30,4) PATH '$.amount' ERROR ON EMPTY ERROR ON ERROR,
            memo VARCHAR(128) CHARACTER SET utf8mb4 PATH '$.memo' ERROR ON EMPTY ERROR ON ERROR
        )) AS input
        SQL;

    public const string SELECT_SQL = <<<'SQL'
        SELECT CAST(persisted.entry_id AS CHAR) AS entry_id,
               CAST(persisted.quantity AS CHAR) AS quantity,
               CAST(persisted.amount AS CHAR) AS amount, persisted.memo AS memo
        FROM JSON_TABLE(:requested_entries, '$[*]' COLUMNS (
            entry_id BIGINT PATH '$.entry_id' ERROR ON EMPTY ERROR ON ERROR
        )) AS requested
        JOIN phpthis_recipe_entries AS persisted ON persisted.entry_id = requested.entry_id
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
