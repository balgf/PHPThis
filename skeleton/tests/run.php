<?php

declare(strict_types=1);

use App\Routes;
use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$expectSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
};

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

/** @var RequestBoundary $boundary */
$boundary = require dirname(__DIR__) . '/bootstrap.php';
$runtimeHealth = $boundary->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'], []);
$expectSame(200, $runtimeHealth->status, 'Valid PHP runtime input must reach GET /health.');
$expectSame(
    'no-store',
    $runtimeHealth->headers['Cache-Control'] ?? null,
    'Runtime GET /health must preserve the explicit no-store policy.',
);

$invalid = $boundary->handle([], []);
$expectSame(400, $invalid->status, 'Invalid PHP runtime input must map to 400.');
$expectSame(
    'no-store',
    $invalid->headers['Cache-Control'] ?? null,
    'Mapped invalid input must return the explicit no-store policy.',
);

$oversized = $boundary->handle([
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

$unknownLog = tempnam(sys_get_temp_dir(), 'phpthis-unknown-');
$previousErrorLog = ini_get('error_log');

if (!is_string($unknownLog) || !is_string($previousErrorLog) || ini_set('error_log', $unknownLog) === false) {
    throw new RuntimeException('Unable to isolate the expected unknown-failure log.');
}

try {
    $unknown = (new UnknownFailureBoundary())->logAndRespond(new RuntimeException('private test failure'));
} finally {
    ini_set('error_log', $previousErrorLog);

    if (is_file($unknownLog) && !unlink($unknownLog)) {
        throw new RuntimeException('Unable to remove the expected unknown-failure log.');
    }
}

$expectSame(500, $unknown->status, 'An unknown failure must return 500.');
$expectSame(
    'no-store',
    $unknown->headers['Cache-Control'] ?? null,
    'An unknown failure must return the explicit no-store policy.',
);

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

fwrite(STDOUT, "PASS application behavior and front controller\n");
