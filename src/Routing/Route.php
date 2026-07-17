<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use PHPThis\Http\RequestHandler;

final readonly class Route
{
    public function __construct(
        public string $method,
        public string $path,
        public RequestHandler $handler,
    ) {
        if (preg_match('/^[A-Z]+$/D', $method) !== 1) {
            throw new InvalidArgumentException('Route method must contain uppercase letters only.');
        }

        if (
            $path === ''
            || $path[0] !== '/'
            || str_contains($path, '?')
            || str_contains($path, '#')
        ) {
            throw new InvalidArgumentException('Route path must be an absolute path without query or fragment.');
        }
    }
}
