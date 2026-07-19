<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use LogicException;
use PHPThis\Http\Request;

final readonly class Router
{
    /** @var array<string, array<string, Route>> */
    private array $literalRoutesByMethodAndPath;

    /** @var array<string, list<string>> */
    private array $literalMethodsByPath;

    /** @var array<string, array<string, Route>> */
    private array $parameterizedRoutesByMethodAndPrefix;

    /** @var array<string, list<string>> */
    private array $parameterizedMethodsByPrefix;

    /**
     * @param list<Route> $routes
     */
    public function __construct(array $routes)
    {
        $literalRoutes = [];
        $literalMethodEntries = [];
        $parameterizedRoutes = [];
        $parameterizedMethodEntries = [];
        $parameterNamesByPrefix = [];

        foreach ($routes as $order => $route) {
            $route = $this->routeFromValue($route);
            $prefix = $route->literalPrefix();
            $parameterName = $route->parameterName();

            if ($prefix === null && $parameterName === null) {
                if (isset($literalRoutes[$route->method][$route->path])) {
                    throw new InvalidArgumentException(
                        "Duplicate route: {$route->method} {$route->path}.",
                    );
                }

                $literalRoutes[$route->method][$route->path] = $route;
                $literalMethodEntries[$route->path][] = [
                    'method' => $route->method,
                    'order' => $order,
                ];
                continue;
            }

            if ($prefix === null || $parameterName === null) {
                throw new LogicException('Route parameter metadata is incomplete.');
            }

            if (
                isset($parameterNamesByPrefix[$prefix])
                && $parameterNamesByPrefix[$prefix] !== $parameterName
            ) {
                throw new InvalidArgumentException(
                    "Parameterized routes with prefix {$prefix} must use one parameter name.",
                );
            }

            if (isset($parameterizedRoutes[$route->method][$prefix])) {
                throw new InvalidArgumentException(
                    "Ambiguous parameterized route: {$route->method} {$route->path}.",
                );
            }

            $parameterNamesByPrefix[$prefix] = $parameterName;
            $parameterizedRoutes[$route->method][$prefix] = $route;
            $parameterizedMethodEntries[$prefix][] = [
                'method' => $route->method,
                'order' => $order,
            ];
        }

        $this->literalRoutesByMethodAndPath = $literalRoutes;
        $this->parameterizedRoutesByMethodAndPrefix = $parameterizedRoutes;
        $this->parameterizedMethodsByPrefix = $this->methodLists($parameterizedMethodEntries);
        $this->literalMethodsByPath = $this->literalMethodLists(
            $literalMethodEntries,
            $parameterizedMethodEntries,
        );
    }

    private function routeFromValue(mixed $route): Route
    {
        if (!$route instanceof Route) {
            throw new InvalidArgumentException('Router accepts only Route objects.');
        }

        return $route;
    }

    public function match(Request $request): ?RouteMatch
    {
        $literalRoute = $this->literalRoutesByMethodAndPath[$request->method][$request->path] ?? null;

        if ($literalRoute !== null) {
            return new RouteMatch($literalRoute, PathParameters::none());
        }

        $value = $this->trailingPositiveInteger($request->path);

        if ($value === null) {
            return null;
        }

        $prefix = $this->trailingSegmentPrefix($request->path);
        $route = $this->parameterizedRoutesByMethodAndPrefix[$request->method][$prefix] ?? null;

        if ($route === null) {
            return null;
        }

        $parameterName = $route->parameterName();

        if ($parameterName === null) {
            throw new LogicException('Parameterized route has no parameter name.');
        }

        return new RouteMatch(
            $route,
            PathParameters::onePositiveInteger($parameterName, $value),
        );
    }

    /** @return list<string> */
    public function allowedMethodsForPath(string $path): array
    {
        $literalMethods = $this->literalMethodsByPath[$path] ?? null;

        if ($literalMethods !== null) {
            return $literalMethods;
        }

        if ($this->trailingPositiveInteger($path) === null) {
            return [];
        }

        return $this->parameterizedMethodsByPrefix[$this->trailingSegmentPrefix($path)] ?? [];
    }

    /**
     * @param array<string, list<array{method: string, order: int}>> $entriesByKey
     * @return array<string, list<string>>
     */
    private function methodLists(array $entriesByKey): array
    {
        $methodsByKey = [];

        foreach ($entriesByKey as $key => $entries) {
            $methodsByKey[$key] = $this->orderedUniqueMethods($entries);
        }

        return $methodsByKey;
    }

    /**
     * @param array<string, list<array{method: string, order: int}>> $literalEntries
     * @param array<string, list<array{method: string, order: int}>> $parameterizedEntries
     * @return array<string, list<string>>
     */
    private function literalMethodLists(array $literalEntries, array $parameterizedEntries): array
    {
        $methodsByPath = [];

        foreach ($literalEntries as $path => $entries) {
            if ($this->trailingPositiveInteger($path) !== null) {
                $prefix = $this->trailingSegmentPrefix($path);
                $entries = array_merge($entries, $parameterizedEntries[$prefix] ?? []);
            }

            $methodsByPath[$path] = $this->orderedUniqueMethods($entries);
        }

        return $methodsByPath;
    }

    /**
     * @param list<array{method: string, order: int}> $entries
     * @return list<string>
     */
    private function orderedUniqueMethods(array $entries): array
    {
        usort(
            $entries,
            static fn(array $left, array $right): int => $left['order'] <=> $right['order'],
        );

        $methods = [];

        foreach ($entries as $entry) {
            if (!in_array($entry['method'], $methods, true)) {
                $methods[] = $entry['method'];
            }
        }

        return $methods;
    }

    private function trailingSegmentPrefix(string $path): string
    {
        $lastSlash = strrpos($path, '/');

        if ($lastSlash === false) {
            throw new LogicException('Request path has no slash.');
        }

        return substr($path, 0, $lastSlash + 1);
    }

    private function trailingPositiveInteger(string $path): ?int
    {
        $lastSlash = strrpos($path, '/');

        if ($lastSlash === false) {
            return null;
        }

        $segment = substr($path, $lastSlash + 1);

        if (preg_match('/^[1-9][0-9]*$/D', $segment) !== 1) {
            return null;
        }

        $maximum = (string) PHP_INT_MAX;
        $length = strlen($segment);
        $maximumLength = strlen($maximum);

        if (
            $length > $maximumLength
            || ($length === $maximumLength && strcmp($segment, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $segment;
    }
}
