<?php

declare(strict_types=1);

namespace Example;

use PHPThis\Routing\Route;

final class Routes
{
    /** @return list<Route> */
    public static function create(): array
    {
        $healthHandler = new HealthHandler();

        return [
            ...HealthRoutes::create($healthHandler),
        ];
    }
}
