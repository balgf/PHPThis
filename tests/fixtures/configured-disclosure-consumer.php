<?php

declare(strict_types=1);

namespace TestConfiguredDisclosureConsumer;

use Example\Http\DevelopmentDiagnosticFailure;
use Example\Http\DevelopmentFailureResponse;
use Example\Observability\ErrorLogOuterFailureSink;
use InvalidArgumentException;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseEmissionFailed;
use PHPThis\Http\ResponseEmitter;
use PHPThis\Http\UnknownFailureBoundary;
use Throwable;

final readonly class DisclosureSelection
{
    private function __construct(public bool $developmentDetails)
    {
    }

    public static function fromProcess(): self
    {
        $profile = \getenv('PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE');
        $mode = \getenv('PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE');

        if (!is_string($profile) || !is_string($mode) || $profile === '' || $mode === '') {
            throw new InvalidArgumentException('Required disclosure configuration is unavailable.');
        }

        if (!in_array($profile, ['local', 'development', 'test', 'staging', 'production'], true)) {
            throw new InvalidArgumentException('The runtime profile is invalid.');
        }

        if (!in_array($mode, ['GENERIC', 'DEVELOPMENT_DETAILS'], true)) {
            throw new InvalidArgumentException('The disclosure mode is invalid.');
        }

        if (
            $mode === 'DEVELOPMENT_DETAILS'
            && !in_array($profile, ['local', 'development', 'test'], true)
        ) {
            throw new InvalidArgumentException('The runtime profile and disclosure mode contradict.');
        }

        return new self($mode === 'DEVELOPMENT_DETAILS');
    }
}

require dirname(__DIR__) . '/autoload.php';

$genericFailureResponse = (new UnknownFailureBoundary())->respond();
$developmentDetails = false;

try {
    $selection = DisclosureSelection::fromProcess();
    $developmentDetails = $selection->developmentDetails;

    throw new DevelopmentDiagnosticFailure();
} catch (Throwable $failure) {
    $response = $genericFailureResponse;

    try {
        (new ErrorLogOuterFailureSink())->emit($failure);
    } catch (Throwable) {
    }

    if ($developmentDetails) {
        try {
            $detailedResponse = (new DevelopmentFailureResponse())->respond($failure);
            $response = $detailedResponse;
        } catch (Throwable) {
        }
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
