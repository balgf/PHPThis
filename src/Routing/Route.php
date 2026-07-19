<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use PHPThis\Http\RequestHandler;

final readonly class Route
{
    /** @var list<RouteSegment> */
    private array $segments;

    private int $parameterCount;

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

        $segments = [];
        $parameterNames = [];
        $parameterCount = 0;

        foreach (explode('/', $path) as $segment) {
            if (!str_contains($segment, '{') && !str_contains($segment, '}')) {
                $segments[] = RouteSegment::literal($segment);
                continue;
            }

            $matches = [];

            if (
                preg_match(
                    '/^\{([a-z][a-z0-9_]*):(positive-int|token)\}$/D',
                    $segment,
                    $matches,
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Route parameters must occupy a full segment and use positive-int or token.',
                );
            }

            if (isset($parameterNames[$matches[1]])) {
                throw new InvalidArgumentException(
                    "Route path parameter {$matches[1]} must be unique.",
                );
            }

            $parameterCount++;

            if ($parameterCount > 2) {
                throw new InvalidArgumentException('Route supports at most two path parameters.');
            }

            $type = RouteParameterType::from($matches[2]);
            $segments[] = RouteSegment::parameter($matches[1], $type);
            $parameterNames[$matches[1]] = true;
        }

        $this->segments = $segments;
        $this->parameterCount = $parameterCount;
    }

    /** @return list<RouteSegment> */
    public function segments(): array
    {
        return $this->segments;
    }

    public function parameterCount(): int
    {
        return $this->parameterCount;
    }
}
