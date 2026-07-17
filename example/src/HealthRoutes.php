<?php

declare(strict_types=1);

namespace Example;

use PHPThis\Routing\Route;

final class HealthRoutes
{
    /** @return list<Route> */
    public static function create(HealthHandler $healthHandler): array
    {
        return [
            new Route('GET', '/health', $healthHandler),
        ];
    }
}
