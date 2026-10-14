<?php

declare(strict_types=1);

namespace PHPThis\Http;

final class UnknownFailureBoundary
{
    public function __construct(private readonly ?ErrorRepresentation $representation = null)
    {
    }

    public function respond(): Response
    {
        return new Response(
            500,
            [
                'Content-Type' => $this->representation->contentType ?? 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
            ],
            $this->representation->body
                ?? "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n",
        );
    }
}
