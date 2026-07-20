<?php

declare(strict_types=1);

namespace Example\DocumentFiles;

use PHPThis\Routing\Route;

final class DocumentFileRoutes
{
    /** @return list<Route> */
    public static function create(LocalDocumentFiles $documentFiles): array
    {
        return [
            new Route('POST', '/document-files', new UploadDocumentFileHandler($documentFiles)),
            new Route(
                'GET',
                '/document-files/{file_id:token}',
                new DownloadDocumentFileHandler($documentFiles),
            ),
        ];
    }
}
