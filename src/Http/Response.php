<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Response status must be between 100 and 599.');
        }

        foreach ($headers as $name => $value) {
            if ($name === '' || preg_match('/^[A-Za-z0-9-]+$/D', $name) !== 1) {
                throw new InvalidArgumentException('Response contains an invalid header name.');
            }

            if (str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new InvalidArgumentException('Response header values cannot contain newlines.');
            }
        }
    }
}
