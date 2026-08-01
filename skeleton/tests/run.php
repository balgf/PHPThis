<?php

declare(strict_types=1);

use App\Observability\CorrelationId;
use App\Observability\RequestSummary;
use App\Observability\RequestSummarySink;
use App\Observability\TerminalRequestCoordinator;
use App\Routes;
use PHPThis\Application;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$expectSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
};

final class RecordingRequestSummarySink implements RequestSummarySink
{
    public int $attempts = 0;
    public ?RequestSummary $summary = null;

    public function __construct(private readonly bool $throwAfterCapture = false)
    {
    }

    public function emit(RequestSummary $summary): void
    {
        $this->attempts++;
        $this->summary = $summary;

        if ($this->throwAfterCapture) {
            throw new RuntimeException('Test sink failure.');
        }
    }
}

/** @param array<class-string<Throwable>, Response> $errorResponses */
function applicationTestCoordinator(
    RequestHandler $handler,
    RequestSummarySink $sink,
    array $errorResponses,
): TerminalRequestCoordinator {
    return new TerminalRequestCoordinator(
        new RequestBoundary(
            new RequestReader(1_024, 'php://input'),
            $handler,
            new ErrorResponseRegistry($errorResponses),
        ),
        new UnknownFailureBoundary(),
        CorrelationId::generate(),
        $sink,
        [],
    );
}

$application = new Application(new Router(Routes::create()));
$health = $application->handle(new Request('GET', '/health'));

$expectSame(200, $health->status, 'GET /health must return 200.');
$expectSame(
    [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
    ],
    $health->headers,
    'GET /health must return JSON with the explicit no-store policy.',
);
$expectSame("{\"status\":\"ok\"}\n", $health->body, 'GET /health must return the exact health body.');

$notAllowed = $application->handle(new Request('POST', '/health'));
$expectSame(405, $notAllowed->status, 'POST /health must return 405.');
$expectSame('GET', $notAllowed->headers['Allow'] ?? null, 'POST /health must advertise GET.');
$expectSame(
    'no-store',
    $notAllowed->headers['Cache-Control'] ?? null,
    'POST /health must return the explicit no-store policy.',
);

$missing = $application->handle(new Request('GET', '/missing'));
$expectSame(404, $missing->status, 'An unknown route must return 404.');
$expectSame(
    'no-store',
    $missing->headers['Cache-Control'] ?? null,
    'An unknown route must return the explicit no-store policy.',
);

/** @var TerminalRequestCoordinator $healthCoordinator */
$healthCoordinator = require dirname(__DIR__) . '/bootstrap.php';
$runtimeHealth = $healthCoordinator->handle(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
    [],
);
$expectSame(200, $runtimeHealth->status, 'Valid PHP runtime input must reach GET /health.');
$expectSame(
    'no-store',
    $runtimeHealth->headers['Cache-Control'] ?? null,
    'Runtime GET /health must preserve the explicit no-store policy.',
);
$requestId = $runtimeHealth->headers['X-Request-ID'] ?? null;

if (!is_string($requestId) || preg_match('/\A[a-f0-9]{32}\z/D', $requestId) !== 1) {
    throw new RuntimeException('Runtime GET /health must expose one generated correlation ID.');
}

$successSummarySink = new RecordingRequestSummarySink();
$summaryHealth = applicationTestCoordinator($application, $successSummarySink, [])->handle(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
    [],
);
$expectSame(200, $summaryHealth->status, 'Observed health must preserve the selected response.');
$expectSame(1, $successSummarySink->attempts, 'Health must attempt exactly one terminal summary.');
$successSummary = $successSummarySink->summary;

if (!$successSummary instanceof RequestSummary) {
    throw new RuntimeException('Health must capture one terminal summary.');
}

$expectSame('success', $successSummary->outcome, 'Health summary must record success.');
$expectSame(200, $successSummary->responseStatus, 'Health summary must record the selected status.');
$expectSame(null, $successSummary->unknownFailureClass, 'Health summary must not record a failure class.');
$expectSame(0, $successSummary->queryStatements, 'Health summary must record zero statements.');
$expectSame([], $successSummary->querySources, 'Health summary must record zero database sources.');
$expectSame(
    $summaryHealth->headers['X-Request-ID'] ?? null,
    $successSummary->correlationId->value,
    'Health response and summary must share one correlation ID.',
);

/** @var TerminalRequestCoordinator $invalidCoordinator */
$invalidCoordinator = require dirname(__DIR__) . '/bootstrap.php';
$invalid = $invalidCoordinator->handle([], []);
$expectSame(400, $invalid->status, 'Invalid PHP runtime input must map to 400.');
$expectSame(
    'no-store',
    $invalid->headers['Cache-Control'] ?? null,
    'Mapped invalid input must return the explicit no-store policy.',
);
$invalidRequestId = $invalid->headers['X-Request-ID'] ?? null;

if (
    !is_string($invalidRequestId)
    || preg_match('/\A[a-f0-9]{32}\z/D', $invalidRequestId) !== 1
    || $invalidRequestId === $requestId
) {
    throw new RuntimeException('Each terminal coordinator must expose fresh request-scoped state.');
}

/** @var TerminalRequestCoordinator $oversizedCoordinator */
$oversizedCoordinator = require dirname(__DIR__) . '/bootstrap.php';
$oversized = $oversizedCoordinator->handle([
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/health',
    'CONTENT_LENGTH' => '1025',
], []);
$expectSame(413, $oversized->status, 'An oversized declared body must map to 413.');
$expectSame(
    'no-store',
    $oversized->headers['Cache-Control'] ?? null,
    'Mapped oversized input must return the explicit no-store policy.',
);

$mappedSummarySink = new RecordingRequestSummarySink();
$mappedSummaryResponse = applicationTestCoordinator(
    $application,
    $mappedSummarySink,
    [
        InvalidRequest::class => new Response(
            400,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ),
    ],
)->handle([], []);
$expectSame(400, $mappedSummaryResponse->status, 'Mapped failure must preserve the selected response.');
$expectSame(1, $mappedSummarySink->attempts, 'Mapped failure must attempt exactly one terminal summary.');
$mappedSummary = $mappedSummarySink->summary;

if (!$mappedSummary instanceof RequestSummary) {
    throw new RuntimeException('Mapped failure must capture one terminal summary.');
}

$expectSame('known_failure', $mappedSummary->outcome, 'Mapped failure summary must record known failure.');
$expectSame(400, $mappedSummary->responseStatus, 'Mapped failure summary must record the selected status.');
$expectSame(null, $mappedSummary->unknownFailureClass, 'Mapped failure must not expose a class.');
$expectSame(
    $mappedSummaryResponse->headers['X-Request-ID'] ?? null,
    $mappedSummary->correlationId->value,
    'Mapped response and summary must share one correlation ID.',
);

$unknownSummarySink = new RecordingRequestSummarySink(true);
$unknown = applicationTestCoordinator(
    new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            throw new RuntimeException('Sensitive test request failure.');
        }
    },
    $unknownSummarySink,
    [],
)->handle(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
    [],
);

$expectSame(500, $unknown->status, 'An unknown failure must return 500.');
$expectSame(
    'private, no-store',
    $unknown->headers['Cache-Control'] ?? null,
    'An unknown failure must return the explicit private no-store policy.',
);
$expectSame(
    "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n",
    $unknown->body,
    'A throwing summary sink must not alter the selected unknown-failure response.',
);
$expectSame(1, $unknownSummarySink->attempts, 'Unknown failure must attempt exactly one terminal summary.');
$unknownSummary = $unknownSummarySink->summary;

if (!$unknownSummary instanceof RequestSummary) {
    throw new RuntimeException('Unknown failure must capture one terminal summary before the sink throws.');
}

$expectSame('unknown_failure', $unknownSummary->outcome, 'Unknown summary must record unknown failure.');
$expectSame(500, $unknownSummary->responseStatus, 'Unknown summary must record the selected status.');
$expectSame(
    RuntimeException::class,
    $unknownSummary->unknownFailureClass,
    'Unknown summary must record only the concrete failure class.',
);
$expectSame(
    $unknown->headers['X-Request-ID'] ?? null,
    $unknownSummary->correlationId->value,
    'Unknown response and summary must share one correlation ID.',
);

if (str_contains(json_encode($unknownSummary->toArray(), JSON_THROW_ON_ERROR), 'Sensitive test request failure.')) {
    throw new RuntimeException('Unknown summary must not expose the failure message.');
}

$frontControllerProgram = <<<'PHP'
$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'];
$_GET = [];
ob_start();
require $argv[1];
$body = ob_get_clean();
if (http_response_code() !== 200 || $body !== "{\"status\":\"ok\"}\n") {
    fwrite(STDERR, 'Front controller returned an unexpected response.');
    exit(1);
}
fwrite(STDOUT, $body);
PHP;

$process = proc_open(
    [PHP_BINARY, '-r', $frontControllerProgram, dirname(__DIR__) . '/public/index.php'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    dirname(__DIR__),
);

if (!is_resource($process)) {
    throw new RuntimeException('Unable to execute the real front controller.');
}

fclose($pipes[0]);
$frontControllerOutput = stream_get_contents($pipes[1]);
$frontControllerError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$frontControllerExitCode = proc_close($process);

if (!is_string($frontControllerOutput) || !is_string($frontControllerError)) {
    throw new RuntimeException('Unable to read the front-controller result.');
}

$expectSame(0, $frontControllerExitCode, 'The real front controller must exit successfully: ' . $frontControllerError);
$expectSame("{\"status\":\"ok\"}\n", $frontControllerOutput, 'The real front controller must emit the health body.');

$frontControllerSource = file_get_contents(dirname(__DIR__) . '/public/index.php');

if (
    !is_string($frontControllerSource)
    || substr_count($frontControllerSource, "error_log('application.response_emission_failed');") !== 1
    || !str_contains($frontControllerSource, 'catch (ResponseEmissionFailed $failure)')
    || !str_contains($frontControllerSource, 'if (!$failure->responseStarted)')
    || !str_contains($frontControllerSource, "'Cache-Control' => 'no-store'")
) {
    throw new RuntimeException(
        'The front controller must retain one redacted, response-start-aware emission fallback.',
    );
}

fwrite(STDOUT, "PASS application behavior and front controller\n");
