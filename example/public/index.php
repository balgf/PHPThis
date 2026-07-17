<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\ResponseEmitter;

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap.php';

$methodValue = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!is_string($methodValue)) {
    throw new UnexpectedValueException('REQUEST_METHOD must be a string.');
}

$requestTargetValue = $_SERVER['REQUEST_URI'] ?? '/';

if (!is_string($requestTargetValue)) {
    throw new UnexpectedValueException('REQUEST_URI must be a string.');
}

$method = strtoupper($methodValue);
$requestTarget = $requestTargetValue;
$parsedPath = parse_url($requestTarget, PHP_URL_PATH);
$path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';
$body = file_get_contents('php://input');

if ($body === false) {
    throw new RuntimeException('Unable to read the request body.');
}

$query = [];

foreach ($_GET as $name => $value) {
    if (!is_string($name)) {
        throw new UnexpectedValueException('Query parameter names must be strings.');
    }

    $query[$name] = $value;
}

$request = new Request(
    method: $method,
    path: $path,
    query: $query,
    body: $body,
);

$response = $application->handle($request);
(new ResponseEmitter())->emit($response);
