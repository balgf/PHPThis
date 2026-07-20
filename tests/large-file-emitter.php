<?php

declare(strict_types=1);

use PHPThis\Http\LocalFileBody;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseEmitter;

require dirname(__DIR__) . '/autoload.php';

$path = $argv[1] ?? null;
$bytesValue = $argv[2] ?? null;
$bytes = is_string($bytesValue) ? filter_var($bytesValue, FILTER_VALIDATE_INT) : false;

if (!is_string($path) || !is_int($bytes) || $bytes < 0) {
    throw new RuntimeException('Large-file emitter arguments are invalid.');
}

$baselineBytes = memory_get_usage(false);
(new ResponseEmitter())->emit(new Response(
    200,
    ['Content-Length' => (string) $bytes, 'Content-Type' => 'application/octet-stream'],
    '',
    [],
    new LocalFileBody($path, $bytes),
));
$additionalPeakBytes = max(0, memory_get_peak_usage(false) - $baselineBytes);
fwrite(STDERR, (string) $additionalPeakBytes . "\n");
