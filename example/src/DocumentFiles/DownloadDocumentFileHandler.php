<?php

declare(strict_types=1);

namespace Example\DocumentFiles;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final readonly class DownloadDocumentFileHandler implements RequestHandler
{
    public function __construct(private LocalDocumentFiles $documentFiles)
    {
    }

    public function handle(Request $request): Response
    {
        $id = DocumentFileId::fromToken($request->pathParameters->token('file_id'));
        $fileBody = $this->documentFiles->read($id);

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="document.bin"',
                'Content-Length' => (string) $fileBody->bytes,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Accept-Ranges' => 'none',
            ],
            body: '',
            fileBody: $fileBody,
        );
    }
}
