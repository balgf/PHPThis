<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class Request
{
    /** @param array<string, mixed> $query */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
    ) {
        if (preg_match('/^[A-Z]+$/D', $method) !== 1) {
            throw new InvalidArgumentException('Request method must contain uppercase letters only.');
        }

        if (
            $path === ''
            || $path[0] !== '/'
            || str_contains($path, '?')
            || str_contains($path, '#')
        ) {
            throw new InvalidArgumentException('Request path must be an absolute path without query or fragment.');
        }
    }
}
