<?php

declare(strict_types=1);

namespace PHPThis\Routing;

final readonly class RouteMatch
{
    public function __construct(
        public Route $route,
        public PathParameters $pathParameters,
    ) {
    }
}
