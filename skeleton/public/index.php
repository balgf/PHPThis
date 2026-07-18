<?php

declare(strict_types=1);

use PHPThis\Http\RequestBoundary;
use PHPThis\Http\ResponseEmitter;
use PHPThis\Http\UnknownFailureBoundary;

try {
    /** @var RequestBoundary $boundary */
    $boundary = require dirname(__DIR__) . '/bootstrap.php';
    $response = $boundary->handle($_SERVER, $_GET);
} catch (Throwable $failure) {
    $response = (new UnknownFailureBoundary())->logAndRespond($failure);
}

(new ResponseEmitter())->emit($response);
