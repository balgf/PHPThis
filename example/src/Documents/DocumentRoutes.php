<?php

declare(strict_types=1);

namespace Example\Documents;

use Example\Documents\GetDocument\AuthenticateGetDocumentRequest;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\GetDocumentHandler;
use Example\Documents\GetDocument\ResolveGetDocumentTenant;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use PHPThis\Routing\Route;

final class DocumentRoutes
{
    /** @return list<Route> */
    public static function create(
        AuthenticateGetDocumentRequest $authenticate,
        ResolveGetDocumentTenant $resolveTenant,
        AuthorizeGetDocument $authorize,
        RetrieveAuthorizedDocument $retrieve,
    ): array {
        return [
            new Route(
                'GET',
                '/accounts/{account_id:positive-int}/documents/{document_key:token}',
                new GetDocumentHandler(
                    $authenticate,
                    $resolveTenant,
                    $authorize,
                    $retrieve,
                ),
            ),
        ];
    }
}
