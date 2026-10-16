<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;

final class AuthenticationRequired extends \RuntimeException {}
final class UploadDenied extends \RuntimeException {}

interface DocumentUploadPolicy
{
    /** Authenticate and authorize this one action; uncertain verification must throw. */
    public function authorize(Request $request): DocumentOwner;
}

/** Deliberately unusable until an adopting application wires its verified policy. */
final class DenyDocumentUploads implements DocumentUploadPolicy
{
    public function authorize(Request $request): DocumentOwner
    {
        if (!isset($request->headers['authorization'])) { throw new AuthenticationRequired(); }
        throw new UploadDenied();
    }
}

interface DocumentReceiver
{
    public function receive(DocumentInput $input, DocumentOwner $owner): DocumentId;
}

final readonly class LockedDocumentReceiver implements DocumentReceiver
{
    public function __construct(private Documents $documents, private DocumentLock $lock) {}
    public function receive(DocumentInput $input, DocumentOwner $owner): DocumentId
    {
        $this->lock->acquire(true);
        try { return $this->documents->receive($input, $owner); }
        finally { $this->lock->release(); }
    }
}

final readonly class UploadDocument implements RequestHandler
{
    public function __construct(private DocumentUploadPolicy $policy, private DocumentReceiver $receiver) {}
    public function handle(Request $request): Response
    {
        $owner = $this->policy->authorize($request);
        if ($request->multipart === null) { throw new UnsupportedMediaType('Multipart required.'); }
        $input = DocumentInput::fromMultipart($request->multipart);
        $id = $this->receiver->receive($input, $owner);
        return new Response(201, ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
            json_encode(['id' => $id->value], JSON_THROW_ON_ERROR));
    }
}
