<?php

declare(strict_types=1);

namespace Example\Users;

use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\ListUsers\ListUsersHandler;
use PHPThis\Routing\Route;

final class UserRoutes
{
    /** @return list<Route> */
    public static function create(
        ListUsersHandler $listUsersHandler,
        CreateUserHandler $createUserHandler,
    ): array {
        return [
            new Route('GET', '/users', $listUsersHandler),
            new Route('POST', '/users', $createUserHandler),
        ];
    }
}
