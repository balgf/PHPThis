<?php

declare(strict_types=1);

namespace Example\Documents;

use Example\Documents\GetDocument\GetDocumentHandler;
use PHPThis\Routing\Route;

final class DocumentRoutes
{
    /** @return list<Route> */
    public static function create(GetDocumentHandler $getDocumentHandler): array
    {
        return [
            new Route(
                'GET',
                '/accounts/{account_id:positive-int}/documents/{document_key:token}',
                $getDocumentHandler,
            ),
        ];
    }
}
