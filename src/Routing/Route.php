<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use PHPThis\Http\RequestHandler;

final readonly class Route
{
    private ?string $literalPrefix;

    private ?string $parameterName;

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

        $literalPrefix = null;
        $parameterName = null;

        if (str_contains($path, '{') || str_contains($path, '}')) {
            $lastSlash = strrpos($path, '/');

            if ($lastSlash === false) {
                throw new InvalidArgumentException('Parameterized route must use one trailing full path segment.');
            }

            $prefix = substr($path, 0, $lastSlash + 1);
            $segment = substr($path, $lastSlash + 1);
            $matches = [];

            if (
                str_contains($prefix, '{')
                || str_contains($prefix, '}')
                || preg_match('/^\{([a-z][a-z0-9_]*):positive-int\}$/D', $segment, $matches) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Route supports only one trailing {name:positive-int} parameter.',
                );
            }

            $literalPrefix = $prefix;
            $parameterName = $matches[1];
        }

        $this->literalPrefix = $literalPrefix;
        $this->parameterName = $parameterName;
    }

    public function literalPrefix(): ?string
    {
        return $this->literalPrefix;
    }

    public function parameterName(): ?string
    {
        return $this->parameterName;
    }
}
