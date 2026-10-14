<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class ErrorRepresentation
{
    public function __construct(
        public string $contentType,
        public string $body,
    ) {
        if (
            trim($contentType) === ''
            || strlen($contentType) > 256
            || preg_match('/[\x00-\x1F\x7F]/', $contentType) === 1
            || strlen($body) > 65_536
        ) {
            throw new InvalidArgumentException('Error representation is invalid or oversized.');
        }
    }
}
