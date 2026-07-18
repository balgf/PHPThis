<?php

declare(strict_types=1);

namespace App;

use PHPThis\Routing\Route;

final class HealthRoutes
{
    /** @return list<Route> */
    public static function create(HealthHandler $handler): array
    {
        return [new Route('GET', '/health', $handler)];
    }
}
