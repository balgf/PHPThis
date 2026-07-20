<?php

declare(strict_types=1);

namespace PHPThis\Http;

final class UnknownFailureBoundary
{
    public function respond(): Response
    {
        return new Response(
            500,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
            "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n",
        );
    }
}
