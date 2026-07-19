<?php

declare(strict_types=1);

namespace App;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final class HealthHandler implements RequestHandler
{
    public function handle(Request $request): Response
    {
        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            body: json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR) . "\n",
        );
    }
}
