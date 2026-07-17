<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use PHPThis\Http\Request;

final readonly class Router
{
    /** @var array<string, array<string, Route>> */
    private array $routesByMethodAndPath;

    /** @var array<string, list<string>> */
    private array $methodsByPath;

    /**
     * @param list<Route> $routes
     */
    public function __construct(array $routes)
    {
        $routesByMethodAndPath = [];
        $methodsByPath = [];

        foreach ($routes as $route) {
            $route = $this->routeFromValue($route);

            if (isset($routesByMethodAndPath[$route->method][$route->path])) {
                throw new InvalidArgumentException(
                    "Duplicate route: {$route->method} {$route->path}.",
                );
            }

            $routesByMethodAndPath[$route->method][$route->path] = $route;
            $methodsByPath[$route->path][] = $route->method;
        }

        $this->routesByMethodAndPath = $routesByMethodAndPath;
        $this->methodsByPath = $methodsByPath;
    }

    private function routeFromValue(mixed $route): Route
    {
        if (!$route instanceof Route) {
            throw new InvalidArgumentException('Router accepts only Route objects.');
        }

        return $route;
    }

    public function match(Request $request): ?Route
    {
        return $this->routesByMethodAndPath[$request->method][$request->path] ?? null;
    }

    /** @return list<string> */
    public function allowedMethodsForPath(string $path): array
    {
        return $this->methodsByPath[$path] ?? [];
    }
}
