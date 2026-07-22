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

    /** @var array<int, array<string, int>> */
    private array $literalTransitionsByState;

    /**
     * @var array<int, array{type: RouteParameterType, name: string, next: int}>
     */
    private array $typedTransitionsByState;

    /** @var array<int, array<string, Route>> */
    private array $terminalRoutesByStateAndMethod;

    /** @var array<int, list<string>> */
    private array $terminalMethodsByState;

    /**
     * @param list<Route> $routes
     */
    public function __construct(array $routes)
    {
        $literalRoutes = [];
        $literalMethodEntries = [];
        $literalTransitions = [];
        $typedTransitions = [];
        $terminalRoutes = [];
        $terminalMethodEntries = [];
        $nextState = 1;

        foreach ($routes as $order => $route) {
            $route = $this->routeFromValue($route);

            if ($route->parameterCount() === 0) {
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

            $state = 0;

            foreach ($route->segments() as $segment) {
                if ($segment->literal !== null) {
                    $typedTransition = $typedTransitions[$state] ?? null;

                    if (
                        $typedTransition !== null
                        && $typedTransition['type']->accepts($segment->literal)
                    ) {
                        throw new InvalidArgumentException(
                            "Ambiguous parameterized route transition: {$route->path}.",
                        );
                    }

                    $followingState = $literalTransitions[$state][$segment->literal] ?? null;

                    if ($followingState === null) {
                        $followingState = $nextState++;
                        $literalTransitions[$state][$segment->literal] = $followingState;
                    }

                    $state = $followingState;
                    continue;
                }

                $name = $segment->parameterName;
                $type = $segment->parameterType;

                if ($name === null || $type === null) {
                    throw new LogicException('Route parameter metadata is incomplete.');
                }

                $typedTransition = $typedTransitions[$state] ?? null;

                if ($typedTransition === null) {
                    foreach ($literalTransitions[$state] ?? [] as $literal => $_followingState) {
                        if ($type->accepts($literal)) {
                            throw new InvalidArgumentException(
                                "Ambiguous parameterized route transition: {$route->path}.",
                            );
                        }
                    }

                    $typedTransition = [
                        'type' => $type,
                        'name' => $name,
                        'next' => $nextState++,
                    ];
                    $typedTransitions[$state] = $typedTransition;
                } elseif (
                    $typedTransition['type'] !== $type
                    || $typedTransition['name'] !== $name
                ) {
                    throw new InvalidArgumentException(
                        "Conflicting parameterized route metadata: {$route->path}.",
                    );
                }

                $state = $typedTransition['next'];
            }

            if (isset($terminalRoutes[$state][$route->method])) {
                throw new InvalidArgumentException(
                    "Ambiguous parameterized route: {$route->method} {$route->path}.",
                );
            }

            $terminalRoutes[$state][$route->method] = $route;
            $terminalMethodEntries[$state][] = [
                'method' => $route->method,
                'order' => $order,
            ];
        }

        $this->literalRoutesByMethodAndPath = $literalRoutes;
        $this->literalTransitionsByState = $literalTransitions;
        $this->typedTransitionsByState = $typedTransitions;
        $this->terminalRoutesByStateAndMethod = $terminalRoutes;
        $this->terminalMethodsByState = $this->methodLists($terminalMethodEntries);
        $this->literalMethodsByPath = $this->literalMethodLists(
            $literalMethodEntries,
            $terminalMethodEntries,
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

        $pathMatch = $this->typedPathMatch($request->path);

        if ($pathMatch === null) {
            return null;
        }

        $route = $this->terminalRoutesByStateAndMethod[$pathMatch['state']][$request->method]
            ?? null;

        if ($route === null) {
            return null;
        }

        return new RouteMatch(
            $route,
            PathParameters::fromValues(
                $pathMatch['positiveIntegers'],
                $pathMatch['tokens'],
                $pathMatch['uuids'],
                $pathMatch['ulids'],
            ),
        );
    }

    /** @return list<string> */
    public function allowedMethodsForPath(string $path): array
    {
        $literalMethods = $this->literalMethodsByPath[$path] ?? null;

        if ($literalMethods !== null) {
            return $literalMethods;
        }

        $pathMatch = $this->typedPathMatch($path);

        if ($pathMatch === null) {
            return [];
        }

        return $this->terminalMethodsByState[$pathMatch['state']] ?? [];
    }

    /**
     * @return array{
     *     state: int,
     *     positiveIntegers: array<string, int>,
     *     tokens: array<string, string>,
     *     uuids: array<string, string>,
     *     ulids: array<string, string>
     * }|null
     */
    private function typedPathMatch(string $path): ?array
    {
        $state = 0;
        $positiveIntegers = [];
        $tokens = [];
        $uuids = [];
        $ulids = [];
        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            $literalState = $this->literalTransitionsByState[$state][$segment] ?? null;

            if ($literalState !== null) {
                $state = $literalState;
                continue;
            }

            $typedTransition = $this->typedTransitionsByState[$state] ?? null;

            if ($typedTransition === null) {
                return null;
            }

            if ($typedTransition['type'] === RouteParameterType::PositiveInteger) {
                $value = RouteParameterType::positiveInteger($segment);

                if ($value === null) {
                    return null;
                }

                $positiveIntegers[$typedTransition['name']] = $value;
            } elseif (!$typedTransition['type']->accepts($segment)) {
                return null;
            } elseif ($typedTransition['type'] === RouteParameterType::Token) {
                $tokens[$typedTransition['name']] = $segment;
            } elseif ($typedTransition['type'] === RouteParameterType::Uuid) {
                $uuids[$typedTransition['name']] = $segment;
            } elseif ($typedTransition['type'] === RouteParameterType::Ulid) {
                $ulids[$typedTransition['name']] = $segment;
            } else {
                throw new LogicException('Route parameter type is unsupported.');
            }

            $state = $typedTransition['next'];
        }

        return [
            'state' => $state,
            'positiveIntegers' => $positiveIntegers,
            'tokens' => $tokens,
            'uuids' => $uuids,
            'ulids' => $ulids,
        ];
    }

    /**
     * @param array<int, list<array{method: string, order: int}>> $entriesByState
     * @return array<int, list<string>>
     */
    private function methodLists(array $entriesByState): array
    {
        $methodsByState = [];

        foreach ($entriesByState as $state => $entries) {
            $methodsByState[$state] = $this->orderedUniqueMethods($entries);
        }

        return $methodsByState;
    }

    /**
     * @param array<string, list<array{method: string, order: int}>> $literalEntries
     * @param array<int, list<array{method: string, order: int}>> $terminalEntries
     * @return array<string, list<string>>
     */
    private function literalMethodLists(array $literalEntries, array $terminalEntries): array
    {
        $methodsByPath = [];

        foreach ($literalEntries as $path => $entries) {
            $pathMatch = $this->typedPathMatch($path);

            if ($pathMatch !== null) {
                $entries = array_merge(
                    $entries,
                    $terminalEntries[$pathMatch['state']] ?? [],
                );
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
}
