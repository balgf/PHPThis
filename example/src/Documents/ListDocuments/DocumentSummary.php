<?php

declare(strict_types=1);

namespace Example\Documents\ListDocuments;

use Example\Documents\DocumentKey;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class DocumentSummary
{
    /**
     * @param non-empty-string $title
     * @param non-empty-string $category
     */
    private function __construct(
        public DocumentKey $documentKey,
        public string $title,
        public string $category,
        public int $sortRank,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (
            count($row) !== 4
            || !array_key_exists('document_key', $row)
            || !array_key_exists('title', $row)
            || !array_key_exists('category', $row)
            || !array_key_exists('sort_rank', $row)
        ) {
            throw new UnexpectedValueException(
                'Document summary row must contain exactly document_key, title, category, and sort_rank.',
            );
        }

        $documentKey = $row['document_key'];
        $title = $row['title'];
        $category = $row['category'];

        if (!is_string($documentKey)) {
            throw new UnexpectedValueException('Document summary key must be a string.');
        }

        try {
            $typedDocumentKey = DocumentKey::fromToken($documentKey);
        } catch (InvalidArgumentException) {
            throw new UnexpectedValueException('Document summary key has an invalid database representation.');
        }

        if (!is_string($title) || $title === '') {
            throw new UnexpectedValueException('Document summary title must be a non-empty string.');
        }

        if (
            !is_string($category)
            || $category === ''
            || strlen($category) > 64
            || preg_match('//u', $category) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $category) === 1
        ) {
            throw new UnexpectedValueException('Document summary category has an invalid database representation.');
        }

        return new self(
            $typedDocumentKey,
            $title,
            $category,
            self::sortRank($row['sort_rank']),
        );
    }

    private static function sortRank(mixed $value): int
    {
        if (is_int($value)) {
            if ($value >= 0 && $value <= 1_000_000) {
                return $value;
            }

            throw new UnexpectedValueException('Document summary sort rank is outside the supported range.');
        }

        if (!is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw new UnexpectedValueException('Document summary sort rank has an invalid database representation.');
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($parsed) || $parsed < 0 || $parsed > 1_000_000) {
            throw new UnexpectedValueException('Document summary sort rank is outside the supported range.');
        }

        return $parsed;
    }
}
