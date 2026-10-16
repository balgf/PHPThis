<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;

interface PacketUploadPolicy { public function authorize(Request $request): DocumentOwner; }
final class DenyPacketUploads implements PacketUploadPolicy
{
    public function authorize(Request $request): DocumentOwner
    {
        if (!isset($request->headers['authorization'])) { throw new AuthenticationRequired(); }
        throw new UploadDenied();
    }
}

interface PacketReceiver { public function receive(PacketInput $input, DocumentOwner $owner): PacketReceipt; }
final readonly class LockedPacketReceiver implements PacketReceiver
{
    public function __construct(private Packets $packets, private DocumentLock $lock) {}
    public function receive(PacketInput $input, DocumentOwner $owner): PacketReceipt
    {
        $this->lock->acquire(true);
        try { return $this->packets->receive($input, $owner); }
        finally { $this->lock->release(); }
    }
}

final readonly class UploadPacket implements RequestHandler
{
    public function __construct(private PacketUploadPolicy $policy, private PacketReceiver $receiver) {}
    public function handle(Request $request): Response
    {
        $owner = $this->policy->authorize($request);
        if ($request->multipart === null) { throw new UnsupportedMediaType('Multipart required.'); }
        $input = PacketInput::fromMultipart($request->multipart);
        $receipt = $this->receiver->receive($input, $owner);
        return new Response($receipt->status, ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
            json_encode(['id' => $receipt->id->value, 'status' => $receipt->outcome], JSON_THROW_ON_ERROR));
    }
}
