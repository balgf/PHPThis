<?php

declare(strict_types=1);

use App\Routes;
use PHPThis\Application;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Routing\Router;

require __DIR__ . '/vendor/autoload.php';

$jsonHeaders = [
    'Content-Type' => 'application/json; charset=utf-8',
    'Cache-Control' => 'no-store',
];

return new RequestBoundary(
    new RequestReader(1_024, 'php://input'),
    new Application(new Router(Routes::create())),
    new ErrorResponseRegistry([
        InvalidRequest::class => new Response(
            400,
            $jsonHeaders,
            "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ),
        RequestBodyTooLarge::class => new Response(
            413,
            $jsonHeaders,
            "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
        ),
    ]),
);
