<?php

declare(strict_types=1);

use App\Routes;
use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
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
    ['Content-Type' => 'application/json; charset=utf-8'],
    $health->headers,
    'GET /health must return the JSON content type.',
);
$expectSame("{\"status\":\"ok\"}\n", $health->body, 'GET /health must return the exact health body.');

$notAllowed = $application->handle(new Request('POST', '/health'));
$expectSame(405, $notAllowed->status, 'POST /health must return 405.');
$expectSame('GET', $notAllowed->headers['Allow'] ?? null, 'POST /health must advertise GET.');

$missing = $application->handle(new Request('GET', '/missing'));
$expectSame(404, $missing->status, 'An unknown route must return 404.');

/** @var RequestBoundary $boundary */
$boundary = require dirname(__DIR__) . '/bootstrap.php';
$runtimeHealth = $boundary->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'], []);
$expectSame(200, $runtimeHealth->status, 'Valid PHP runtime input must reach GET /health.');

$invalid = $boundary->handle([], []);
$expectSame(400, $invalid->status, 'Invalid PHP runtime input must map to 400.');

$oversized = $boundary->handle([
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/health',
    'CONTENT_LENGTH' => '1025',
], []);
$expectSame(413, $oversized->status, 'An oversized declared body must map to 413.');

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
