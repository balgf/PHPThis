<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MetadataS3Recipe\DocumentInput;
use MetadataS3Recipe\DocumentMetadata;
use MetadataS3Recipe\DocumentUnavailable;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\RequestBodyTooLarge;

$count = 0;
foreach ([
    0 => null, 1 => RequestBodyTooLarge::class, 2 => RequestBodyTooLarge::class,
    3 => InvalidRequest::class, 4 => InvalidRequest::class, 6 => DocumentUnavailable::class,
    7 => DocumentUnavailable::class, 8 => DocumentUnavailable::class, 99 => RuntimeException::class,
] as $error => $expected) {
    $failure = null;
    try {
        $form = MultipartForm::fromPhp(['label' => 'synthetic'], ['document' => [
            'name' => $error === 4 ? '' : 'document.bin', 'type' => $error === 4 ? '' : 'application/octet-stream',
            'tmp_name' => $error === 4 ? '' : '/synthetic-not-a-real-upload', 'error' => $error, 'size' => $error === 4 ? 0 : 1,
        ]], 1024, new MultipartLimits(131072, 65536, 65536, 1024, 1153));
        DocumentInput::fromMultipart($form);
    } catch (Throwable $caught) { $failure = $caught::class; }
    if ($failure !== $expected) { throw new RuntimeException('Upload-error preflight mismatch: ' . $error); }
    $count++;
}
foreach ([
    [], ['label' => null], ['label' => 1], ['label' => []],
    ['label' => 'synthetic', 'note' => null], ['label' => 'synthetic', 'note' => []],
    ['label' => '', 'note' => 1], ['label' => '', 'unknown' => 'synthetic'],
] as $fields) {
    try { DocumentMetadata::fromFields($fields); }
    catch (InvalidRequest) { $count++; continue; }
    throw new RuntimeException('Structural metadata failure must precede value validation.');
}
echo 'PASS metadata S3 values: ' . $count . " cases; every upload error and structural precedence\n";
