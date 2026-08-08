<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

$minimumStatusRejected = false;

try {
    new Response(199, [], '');
} catch (InvalidArgumentException) {
    $minimumStatusRejected = true;
}

if (!$minimumStatusRejected) {
    throw new RuntimeException('Expected status 199 to be rejected as non-final.');
}

$maximumStatusResponse = new Response(599, [], '');
$headHandler = new class implements RequestHandler {
    public function handle(Request $request): Response
    {
        return new Response(200, [], '');
    }
};
$getHandler = new class implements RequestHandler {
    public int $calls = 0;

    public function handle(Request $request): Response
    {
        $this->calls++;

        return new Response(200, ['Content-Type' => 'text/plain'], "get representation\n");
    }
};
$explicitHeadResponse = (new Application(new Router([
    new Route('HEAD', '/explicit-head', $headHandler),
])))->handle(new Request('HEAD', '/explicit-head'));
$getOnlyHeadResponse = (new Application(new Router([
    new Route('GET', '/get-only', $getHandler),
])))->handle(new Request('HEAD', '/get-only'));

if (
    $maximumStatusResponse->status !== 599
    || $explicitHeadResponse->status !== 200
    || $explicitHeadResponse->body !== ''
    || $explicitHeadResponse->headers !== []
    || $getOnlyHeadResponse->status !== 405
    || $getOnlyHeadResponse->headers !== [
        'Allow' => 'GET',
        'Cache-Control' => 'no-store',
        'Content-Type' => 'text/plain; charset=utf-8',
    ]
    || $getOnlyHeadResponse->body !== "Method Not Allowed\n"
    || $getHandler->calls !== 0
) {
    throw new RuntimeException('Expected bounded final statuses without an inferred HEAD-to-GET fallback.');
}

echo json_encode([
    'maximum_status' => $maximumStatusResponse->status,
    'minimum_status_rejected' => $minimumStatusRejected,
    'explicit_head_status' => $explicitHeadResponse->status,
    'get_only_head_status' => $getOnlyHeadResponse->status,
    'get_only_head_headers' => $getOnlyHeadResponse->headers,
    'get_only_head_body' => $getOnlyHeadResponse->body,
    'get_only_handler_calls' => $getHandler->calls,
], JSON_THROW_ON_ERROR), "\n";
