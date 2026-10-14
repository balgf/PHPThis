<?php

declare(strict_types=1);

namespace BulkWriteRecipe;

use InvalidArgumentException;
use UnexpectedValueException;

final readonly class LedgerEntry
{
    private function __construct(
        public string $entryId,
        public string $quantity,
        public string $amount,
        public ?string $memo,
    ) {
    }

    public static function fromValues(string $entryId, string $quantity, string $amount, ?string $memo): self
    {
        $absoluteQuantity = str_starts_with($quantity, '-') ? substr($quantity, 1) : $quantity;
        $quantityLimit = str_starts_with($quantity, '-') ? '9223372036854775808' : '9223372036854775807';
        if (
            preg_match('/\A[1-9][0-9]{0,18}\z/D', $entryId) !== 1
            || strlen($entryId) === 19 && strcmp($entryId, '9223372036854775807') > 0
            || preg_match('/\A(?:0|-?[1-9][0-9]{0,18})\z/D', $quantity) !== 1
            || strlen($absoluteQuantity) === 19 && strcmp($absoluteQuantity, $quantityLimit) > 0
            || preg_match('/\A-?(?:0|[1-9][0-9]{0,25})\.[0-9]{4}\z/D', $amount) !== 1
            || $amount === '-0.0000'
            || $memo !== null && (strlen($memo) > 128 || str_contains($memo, "\0") || preg_match('//u', $memo) !== 1)
        ) {
            throw new InvalidArgumentException('Ledger entry values are invalid.');
        }
        return new self($entryId, $quantity, $amount, $memo);
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (
            count($row) !== 4 || !isset($row['entry_id'], $row['quantity'], $row['amount'])
            || !array_key_exists('memo', $row) || !is_string($row['entry_id'])
            || !is_string($row['quantity']) || !is_string($row['amount'])
            || $row['memo'] !== null && !is_string($row['memo'])
        ) {
            throw new UnexpectedValueException('Ledger projection has an invalid structure.');
        }
        try {
            return self::fromValues($row['entry_id'], $row['quantity'], $row['amount'], $row['memo']);
        } catch (InvalidArgumentException $failure) {
            throw new UnexpectedValueException('Ledger projection has invalid values.', previous: $failure);
        }
    }

    /** @return array{entry_id: string, quantity: string, amount: string, memo: ?string} */
    public function representation(): array
    {
        return ['entry_id' => $this->entryId, 'quantity' => $this->quantity, 'amount' => $this->amount, 'memo' => $this->memo];
    }
}
