<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use UnexpectedValueException;

final readonly class DocumentDetails
{
    /** @param non-empty-string $title */
    private function __construct(public string $title)
    {
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (count($row) !== 1 || !array_key_exists('title', $row)) {
            throw new UnexpectedValueException('Document details row must contain exactly title.');
        }

        $title = $row['title'];

        if (!is_string($title) || $title === '') {
            throw new UnexpectedValueException('Document title must be a non-empty string.');
        }

        return new self($title);
    }
}
