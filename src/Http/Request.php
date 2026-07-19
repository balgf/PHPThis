<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;
use PHPThis\Routing\PathParameters;

final readonly class Request
{
    public PathParameters $pathParameters;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
        public array $headers = [],
        ?PathParameters $pathParameters = null,
    ) {
        $this->pathParameters = $pathParameters ?? PathParameters::none();

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

        foreach ($headers as $name => $value) {
            if (
                $name === ''
                || strtolower($name) !== $name
                || preg_match('/^[!#$%&\'*+\-.^_`|~0-9a-z]+$/D', $name) !== 1
            ) {
                throw new InvalidArgumentException('Request header names must be lowercase HTTP tokens.');
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new InvalidArgumentException('Request header values must be strings without control bytes.');
            }
        }
    }

    public function withPathParameters(PathParameters $pathParameters): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $pathParameters,
        );
    }
}
