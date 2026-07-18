<?php

declare(strict_types=1);

namespace Example;

use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\UserRoutes;
use PHPThis\Database\Connection;
use PHPThis\Routing\Route;

final class Routes
{
    /** @return list<Route> */
    public static function create(
        Connection $listUsersConnection,
        Connection $createUserConnection,
    ): array
    {
        $healthHandler = new HealthHandler();
        $listUsersHandler = new ListUsersHandler($listUsersConnection);
        $createUserHandler = new CreateUserHandler($createUserConnection);

        return [
            ...HealthRoutes::create($healthHandler),
            ...UserRoutes::create($listUsersHandler, $createUserHandler),
        ];
    }
}
