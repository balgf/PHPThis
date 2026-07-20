<?php

declare(strict_types=1);

namespace Example;

use Example\DocumentFiles\DocumentFileRoutes;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Documents\AuthenticateDocumentRequest;
use Example\Documents\ResolveDocumentTenant;
use Example\Documents\DocumentRoutes;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\Documents\ListDocuments\AuthorizeListDocuments;
use Example\Users\CreateUser\CreateUserHandler;
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
        Connection $getDocumentConnection,
        Connection $listDocumentsConnection,
        AuthenticateDocumentRequest $authenticateDocument,
        ResolveDocumentTenant $resolveDocumentTenant,
        AuthorizeGetDocument $authorizeGetDocument,
        AuthorizeListDocuments $authorizeListDocuments,
        LocalDocumentFiles $documentFiles,
    ): array {
        $healthHandler = new HealthHandler();
        $retrieveDocument = new SelectAuthorizedDocument($getDocumentConnection);
        $listUsersHandler = new ListUsersHandler($listUsersConnection);
        $getUserHandler = new GetUserHandler($getUserConnection);
        $createUserHandler = new CreateUserHandler(new TransactionalCreateUser($createUserConnection));

        return [
            ...HealthRoutes::create($healthHandler),
            ...DocumentRoutes::create(
                $authenticateDocument,
                $resolveDocumentTenant,
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
