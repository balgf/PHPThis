<?php

declare(strict_types=1);

namespace BulkWriteRecipe;

use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class LedgerBatch
{
    private function __construct(public string $encodedRows, public int $rowCount)
    {
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > 65_536) {
            throw new InvalidArgumentException('Ledger batch exceeds its input byte limit.');
        }
        try {
            $decoded = json_decode($json, false, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $failure) {
            throw new InvalidArgumentException('Ledger batch JSON is invalid.', previous: $failure);
        }
        if (!$decoded instanceof stdClass) {
            throw new InvalidArgumentException('Ledger batch must be an object.');
        }
        $outer = get_object_vars($decoded);
        if (array_keys($outer) !== ['entries'] || !is_array($outer['entries']) || !array_is_list($outer['entries']) || count($outer['entries']) > 64) {
            throw new InvalidArgumentException('Ledger batch structure is invalid.');
        }
        $typedRows = [];
        // Complete every row's structure before interpreting any values.
        foreach ($outer['entries'] as $row) {
            if (!$row instanceof stdClass) {
                throw new InvalidArgumentException('Ledger entry structure is invalid.');
            }
            $fields = get_object_vars($row);
            if (
                count($fields) !== 4 || !isset($fields['entry_id'], $fields['quantity'], $fields['amount'])
                || !array_key_exists('memo', $fields) || !is_string($fields['entry_id'])
                || !is_string($fields['quantity']) || !is_string($fields['amount'])
                || $fields['memo'] !== null && !is_string($fields['memo'])
            ) {
                throw new InvalidArgumentException('Ledger entry structure is invalid.');
            }
            $typedRows[] = $fields;
        }
        $rows = [];
        $seen = [];
        foreach ($typedRows as $fields) {
            $entry = LedgerEntry::fromValues($fields['entry_id'], $fields['quantity'], $fields['amount'], $fields['memo']);
            $key = 'entry-' . $entry->entryId;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Ledger batch contains a duplicate identifier.');
            }
            $seen[$key] = true;
            $rows[] = $entry->representation();
        }
        $encoded = json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($encoded) > 32_768) {
            throw new InvalidArgumentException('Ledger batch exceeds its encoded byte limit.');
        }
        return new self($encoded, count($rows));
    }
}
