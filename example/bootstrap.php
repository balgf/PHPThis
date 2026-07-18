<?php

declare(strict_types=1);

use Example\Routes;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

$databasePath = dirname(__DIR__) . '/tmp/example.sqlite';

if (!is_file($databasePath)) {
    throw new RuntimeException('Example database is missing. Run composer example:setup first.');
}

$dsn = 'sqlite:' . $databasePath;
$listUsersConnection = Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1));
$createUserConnection = Connection::connect($dsn, new QueryBudget(2), new QueryTrace(2));
$application = new Application(new Router(Routes::create($listUsersConnection, $createUserConnection)));
$jsonHeaders = ['Content-Type' => 'application/json; charset=utf-8'];
$errorResponses = new ErrorResponseRegistry([
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
    UnsupportedMediaType::class => new Response(
        415,
        $jsonHeaders,
        "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type must be application/json.\"}}\n",
    ),
]);

return new RequestBoundary(
    new RequestReader(8_192, 'php://input'),
    $application,
    $errorResponses,
);
