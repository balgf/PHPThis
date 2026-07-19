<?php

declare(strict_types=1);

namespace Example;

use Example\Documents\DocumentRoutes;
use Example\Documents\GetDocument\GetDocumentHandler;
use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\GetUser\GetUserHandler;
use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\UserRoutes;
use PHPThis\Database\Connection;
use PHPThis\Routing\Route;

final class Routes
{
    /** @return list<Route> */
    public static function create(
        Connection $listUsersConnection,
        Connection $getUserConnection,
        Connection $createUserConnection,
    ): array
    {
        $healthHandler = new HealthHandler();
        $getDocumentHandler = new GetDocumentHandler();
        $listUsersHandler = new ListUsersHandler($listUsersConnection);
        $getUserHandler = new GetUserHandler($getUserConnection);
        $createUserHandler = new CreateUserHandler($createUserConnection);

        return [
            ...HealthRoutes::create($healthHandler),
            ...DocumentRoutes::create($getDocumentHandler),
            ...UserRoutes::create($listUsersHandler, $getUserHandler, $createUserHandler),
        ];
    }
}
