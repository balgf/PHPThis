<?php

declare(strict_types=1);

namespace Example\Documents;

use Example\Accounts\AuthenticateAccountRequest;
use Example\Accounts\ResolveAccountTenant;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\GetDocumentHandler;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use Example\Documents\ListDocuments\AuthorizeListDocuments;
use Example\Documents\ListDocuments\ListDocumentsHandler;
use PHPThis\Database\Connection;
use PHPThis\Routing\Route;

final class DocumentRoutes
{
    /** @return list<Route> */
    public static function create(
        AuthenticateAccountRequest $authenticate,
        ResolveAccountTenant $resolveTenant,
        AuthorizeGetDocument $authorizeGet,
        RetrieveAuthorizedDocument $retrieve,
        AuthorizeListDocuments $authorizeList,
        Connection $listConnection,
    ): array {
        return [
            new Route(
                'GET',
                '/accounts/{account_id:positive-int}/documents',
                new ListDocumentsHandler(
                    $authenticate,
                    $resolveTenant,
                    $authorizeList,
                    $listConnection,
                ),
            ),
            new Route(
                'GET',
                '/accounts/{account_id:positive-int}/documents/{document_key:token}',
                new GetDocumentHandler(
                    $authenticate,
                    $resolveTenant,
                    $authorizeGet,
                    $retrieve,
                ),
            ),
        ];
    }
}
