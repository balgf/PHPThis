<?php

declare(strict_types=1);

namespace Example\DocumentFiles;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;

final readonly class UploadDocumentFileHandler implements RequestHandler
{
    public function __construct(private LocalDocumentFiles $documentFiles)
    {
    }

    public function handle(Request $request): Response
    {
        $contentType = $request->headers['content-type'] ?? null;

        if (!is_string($contentType)) {
            throw new UnsupportedMediaType('Document upload requires multipart/form-data.');
        }

        $parameterPosition = strpos($contentType, ';');
        $mediaType = $parameterPosition === false
            ? $contentType
            : substr($contentType, 0, $parameterPosition);

        if (strtolower(trim($mediaType)) !== 'multipart/form-data') {
            throw new UnsupportedMediaType('Document upload requires multipart/form-data.');
        }

        $pendingUpload = PendingDocumentUpload::fromUploads($request->uploads);
        $id = $this->documentFiles->store($pendingUpload);
        $body = json_encode(['file_id' => $id->value], JSON_THROW_ON_ERROR);

        return new Response(
            status: 201,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
                'Location' => '/document-files/' . $id->value,
            ],
            body: $body . "\n",
        );
    }
}
