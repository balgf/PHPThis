<?php

declare(strict_types=1);

use Example\Observability\ErrorLogOuterFailureSink;
use Example\Observability\TerminalRequestCoordinator;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseEmissionFailed;
use PHPThis\Http\ResponseEmitter;
use PHPThis\Http\UnknownFailureBoundary;

require dirname(__DIR__, 2) . '/autoload.php';

$genericFailureResponse = (new UnknownFailureBoundary())->respond();

try {
    /** @var TerminalRequestCoordinator $coordinator */
    $coordinator = require dirname(__DIR__) . '/bootstrap.php';
    $response = $coordinator->handle($_SERVER, $_GET, $_POST, $_FILES);
} catch (Throwable $failure) {
    $response = $genericFailureResponse;

    try {
        (new ErrorLogOuterFailureSink())->emit($failure);
    } catch (Throwable) {
    }
}

$emitter = new ResponseEmitter();

try {
    $emitter->emit($response);
} catch (ResponseEmissionFailed $failure) {
    error_log('application.response_emission_failed');

    if (!$failure->responseStarted) {
        $emitter->emit(new Response(
            500,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n",
        ));
    }
}
