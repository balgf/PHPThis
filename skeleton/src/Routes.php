<?php

declare(strict_types=1);

namespace App;

use PHPThis\Routing\Route;

final class Routes
{
    /** @return list<Route> */
    public static function create(): array
    {
        return [...HealthRoutes::create(new HealthHandler())];
    }
}
