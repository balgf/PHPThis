<?php

declare(strict_types=1);

namespace Example\DocumentFiles;

use PHPThis\Http\InvalidRequest;

final readonly class DocumentFileId
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public static function fromToken(string $value): self
    {
        if (preg_match('/^[0-9a-f]{32}$/D', $value) !== 1) {
            throw new InvalidRequest('Document file identifier is invalid.');
        }

        return new self($value);
    }
}
