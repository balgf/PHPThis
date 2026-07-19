<?php

declare(strict_types=1);

namespace PHPThis\Http;

use Throwable;

final class UnknownFailureBoundary
{
    public function logAndRespond(Throwable $failure): Response
    {
        error_log('phpthis.request.unhandled exception=' . $failure::class);

        return new Response(
            500,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
            "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n",
        );
    }
}
