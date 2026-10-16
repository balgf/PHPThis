<?php

declare(strict_types=1);

use App\Observability\TerminalRequestCoordinator;
use App\Observability\ErrorLogOuterFailureSink;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseEmissionFailed;
use PHPThis\Http\ResponseEmitter;
use PHPThis\Http\UnknownFailureBoundary;

require dirname(__DIR__, 3) . '/autoload.php';

$genericFailureResponse = (new UnknownFailureBoundary())->respond();

try {
    /** @var TerminalRequestCoordinator $coordinator */
    $coordinator = require dirname(__DIR__) . '/http.php';
    $response = $coordinator->handle($_SERVER, $_GET);
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
                'Content-Type' => $genericFailureResponse->headers['Content-Type'],
                'Cache-Control' => 'no-store',
            ],
            $genericFailureResponse->body,
        ));
    }
}
