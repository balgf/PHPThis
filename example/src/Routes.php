<?php

declare(strict_types=1);

namespace Example;

use Example\DocumentFiles\DocumentFileRoutes;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Accounts\AuthenticateAccountRequest;
use Example\Accounts\ResolveAccountTenant;
use Example\Documents\DocumentRoutes;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use Example\Documents\ListDocuments\AuthorizeListDocuments;
use Example\Users\CreateUser\CreateUserHandler;
use Example\Users\CreateUser\AuthorizeCreateUser;
use Example\Users\CreateUser\TransactionalCreateUser;
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
        RetrieveAuthorizedDocument $retrieveDocument,
        Connection $listDocumentsConnection,
        AuthenticateAccountRequest $authenticateAccount,
        ResolveAccountTenant $resolveAccountTenant,
        AuthorizeCreateUser $authorizeCreateUser,
        AuthorizeGetDocument $authorizeGetDocument,
        AuthorizeListDocuments $authorizeListDocuments,
        LocalDocumentFiles $documentFiles,
    ): array {
        $healthHandler = new HealthHandler();
        $listUsersHandler = new ListUsersHandler($listUsersConnection);
        $getUserHandler = new GetUserHandler($getUserConnection);
        $createUserHandler = new CreateUserHandler(
            $authenticateAccount,
            $resolveAccountTenant,
            $authorizeCreateUser,
            new TransactionalCreateUser($createUserConnection),
        );

        return [
            ...HealthRoutes::create($healthHandler),
            ...DocumentRoutes::create(
                $authenticateAccount,
                $resolveAccountTenant,
                $authorizeGetDocument,
                $retrieveDocument,
                $authorizeListDocuments,
                $listDocumentsConnection,
            ),
            ...UserRoutes::create($listUsersHandler, $getUserHandler, $createUserHandler),
            ...DocumentFileRoutes::create($documentFiles),
        ];
    }
}
