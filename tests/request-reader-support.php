<?php

declare(strict_types=1);

use PHPThis\Http\RequestReader;

function requestReaderForBody(string $body, int $maximumBodyBytes): RequestReader
{
    $directory = __DIR__ . '/../tmp/request-bodies';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the request-body fixture directory.');
    }

    $path = $directory . '/' . hash('sha256', $body) . '.body';
    $writtenBytes = file_put_contents($path, $body, LOCK_EX);

    if (!is_int($writtenBytes) || $writtenBytes !== strlen($body)) {
        throw new RuntimeException('Unable to write the complete request-body fixture.');
    }

    return new RequestReader($maximumBodyBytes, $path);
}
