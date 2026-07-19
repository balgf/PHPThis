<?php

declare(strict_types=1);

namespace Example;

use Example\Documents\DocumentRoutes;
use Example\Documents\GetDocument\AuthenticateGetDocumentRequest;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\ResolveGetDocumentTenant;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
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
        Connection $getDocumentConnection,
        AuthenticateGetDocumentRequest $authenticateGetDocument,
        ResolveGetDocumentTenant $resolveGetDocumentTenant,
        AuthorizeGetDocument $authorizeGetDocument,
    ): array {
        $healthHandler = new HealthHandler();
        $retrieveDocument = new SelectAuthorizedDocument($getDocumentConnection);
        $listUsersHandler = new ListUsersHandler($listUsersConnection);
        $getUserHandler = new GetUserHandler($getUserConnection);
        $createUserHandler = new CreateUserHandler($createUserConnection);

        return [
            ...HealthRoutes::create($healthHandler),
            ...DocumentRoutes::create(
                $authenticateGetDocument,
                $resolveGetDocumentTenant,
                $authorizeGetDocument,
                $retrieveDocument,
            ),
            ...UserRoutes::create($listUsersHandler, $getUserHandler, $createUserHandler),
        ];
    }
}
