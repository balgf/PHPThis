<?php

declare(strict_types=1);

namespace Example\Users;

use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\GetUser\GetUserHandler;
use Example\Users\ListUsers\ListUsersHandler;
use PHPThis\Routing\Route;

final class UserRoutes
{
    /** @return list<Route> */
    public static function create(
        ListUsersHandler $listUsersHandler,
        GetUserHandler $getUserHandler,
        CreateUserHandler $createUserHandler,
    ): array {
        return [
            new Route('GET', '/users', $listUsersHandler),
            new Route('GET', '/users/{user_id:positive-int}', $getUserHandler),
            new Route('POST', '/accounts/{account_id:positive-int}/users', $createUserHandler),
        ];
    }
}
