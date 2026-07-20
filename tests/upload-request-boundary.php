<?php

declare(strict_types=1);

use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\RequestUploadError;
use PHPThis\Http\Response;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Routing\PathParameters;

require dirname(__DIR__) . '/autoload.php';

$reader = new RequestReader(8, '/input-must-not-be-read-for-multipart', 1_024);
$server = uploadServer('128');
$files = ['document' => uploadFile('../../client.php', 'folder/../../client.php')];
$request = $reader->read($server, [], [], $files);
$upload = $request->uploads['document'] ?? null;

if (
    $request->body !== ''
    || $upload === null
    || $upload->untrustedClientFilename !== '../../client.php'
    || $upload->untrustedClientMediaType !== 'text/html'
    || $upload->temporaryPath !== '/tmp/phpthis-upload'
    || $upload->reportedSizeBytes !== 12
    || $upload->error !== RequestUploadError::Success
) {
    throw new RuntimeException('Expected one flat upload to become explicit untrusted transport metadata.');
}

$handler = new class implements RequestHandler {
    public bool $receivedUpload = false;

    public function handle(Request $request): Response
    {
        $this->receivedUpload = isset($request->uploads['document']);
        return new Response(204, [], '');
    }
};
$response = (new RequestBoundary(
    $reader,
    $handler,
    new ErrorResponseRegistry([]),
))->handle($server, [], [], $files);

if ($response->status !== 204 || !$handler->receivedUpload) {
    throw new RuntimeException('Expected RequestBoundary to pass explicit multipart runtime input once.');
}

$routed = $request->withPathParameters(PathParameters::onePositiveInteger('file_id', 7));

if (
    ($routed->uploads['document'] ?? null) !== $upload
    || $routed->pathParameters->positiveInteger('file_id') !== 7
) {
    throw new RuntimeException('Expected route matching to preserve normalized uploads.');
}

$noFile = $reader->read($server, [], [], [
    'document' => [
        'name' => '',
        'full_path' => '',
        'type' => '',
        'tmp_name' => '',
        'error' => 4,
        'size' => 0,
    ],
]);

if (($noFile->uploads['document'] ?? null)?->error !== RequestUploadError::NoFile) {
    throw new RuntimeException('Expected PHP upload failures to remain explicit typed values.');
}

foreach (RequestUploadError::cases() as $uploadError) {
    $errorFiles = $uploadError === RequestUploadError::NoFile
        ? ['document' => [
            'name' => '', 'full_path' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0,
        ]]
        : ['document' => uploadFile('client.txt', error: $uploadError->value)];
    $typedError = $reader->read($server, [], [], $errorFiles)->uploads['document']->error ?? null;

    if ($typedError !== $uploadError) {
        throw new RuntimeException('Expected every recognized PHP upload error to remain typed.');
    }
}

$ordinaryInput = tempnam(sys_get_temp_dir(), 'phpthis-request-');

if (!is_string($ordinaryInput)) {
    throw new RuntimeException('Unable to create the ordinary-body fixture.');
}

try {
    $writtenBytes = file_put_contents($ordinaryInput, 'body', LOCK_EX);

    if ($writtenBytes !== 4) {
        throw new RuntimeException('Unable to write the ordinary-body fixture.');
    }

    $ordinary = (new RequestReader(4, $ordinaryInput, 1_024))->read([
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/ordinary',
        'CONTENT_LENGTH' => '4',
        'CONTENT_TYPE' => 'application/json',
    ], [], ['ignored_outside_multipart' => 'runtime-parsed-value']);

    if ($ordinary->body !== 'body' || $ordinary->uploads !== []) {
        throw new RuntimeException('Expected ordinary request-body behavior to remain unchanged.');
    }
} finally {
    if (is_file($ordinaryInput) && !unlink($ordinaryInput)) {
        throw new RuntimeException('Unable to remove the ordinary-body fixture.');
    }
}

$invalidCases = [
    [InvalidRequest::class, uploadServer(null), [], []],
    [InvalidRequest::class, uploadServer('01'), [], []],
    [InvalidRequest::class, uploadServer('128', 'multipart/form-data'), [], []],
    [InvalidRequest::class, uploadServer('128', 'multipart/form-data; boundary=""'), [], []],
    [InvalidRequest::class, uploadServer('128', 'multipart/form-data; boundary=a; boundary=b'), [], []],
    [InvalidRequest::class, uploadServer('128', 'multipart/form-data; boundary=bad!value'), [], []],
    [InvalidRequest::class, uploadServer('128', 'multipart/form-data; boundary=' . str_repeat('a', 71)), [], []],
    [InvalidRequest::class, array_replace(uploadServer('128'), ['REQUEST_METHOD' => 'GET']), [], []],
    [InvalidRequest::class, uploadServer('128', transferEncoding: 'chunked'), [], []],
    [RequestBodyTooLarge::class, uploadServer('1025'), [], []],
    [InvalidRequest::class, $server, ['caption' => 'not accepted'], []],
    [InvalidRequest::class, $server, [], [
        'first' => uploadFile('first.txt'),
        'second' => uploadFile('second.txt'),
    ]],
    [InvalidRequest::class, $server, [], ['document' => [
        'name' => ['first.txt', 'second.txt'],
        'type' => ['text/plain', 'text/plain'],
        'tmp_name' => ['/tmp/first', '/tmp/second'],
        'error' => [0, 0],
        'size' => [1, 1],
    ]]],
    [InvalidRequest::class, $server, [], ['document' => uploadFile("client\n.txt")]],
    [InvalidRequest::class, $server, [], ['document' => array_replace(uploadFile('client.txt'), ['extra' => 'value'])]],
    [InvalidRequest::class, $server, [], ['document' => array_diff_key(uploadFile('client.txt'), ['type' => true])]],
    [InvalidRequest::class, $server, [], ['document' => array_replace(uploadFile('client.txt'), ['size' => '12'])]],
    [InvalidRequest::class, $server, [], ['document' => array_replace(uploadFile('client.txt'), ['full_path' => null])]],
    [InvalidRequest::class, $server, [], ['document' => array_replace(
        uploadFile('client.txt'),
        ['tmp_name' => '/' . str_repeat('t', 4_096)],
    )]],
    [RuntimeException::class, $server, [], ['document' => uploadFile('client.txt', error: 5)]],
    [InvalidRequest::class, $server, [], ['document' => uploadFile('', error: 0)]],
    [InvalidRequest::class, $server, [], ['document' => uploadFile('client.txt', error: 4, size: 0)]],
    [InvalidRequest::class, $server, [], ['document' => [
        'name' => '', 'full_path' => '', 'type' => 'text/plain', 'tmp_name' => '', 'error' => 4, 'size' => 0,
    ]]],
    [InvalidRequest::class, $server, [], ['document' => uploadFile('client.txt', size: 129)]],
    [RequestBodyTooLarge::class, uploadServer(str_repeat('9', 100)), [], []],
    [InvalidRequest::class, [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/files',
        'CONTENT_LENGTH' => '0',
        'CONTENT_TYPE' => 'application/json',
    ], [], $files],
];

foreach ($invalidCases as [$failureClass, $invalidServer, $fields, $invalidFiles]) {
    try {
        $reader->read($invalidServer, [], $fields, $invalidFiles);
    } catch (Throwable $failure) {
        if ($failure::class === $failureClass) {
            continue;
        }

        throw new RuntimeException('Unexpected multipart rejection type.', previous: $failure);
    }

    throw new RuntimeException('Expected malformed or unbounded multipart input to be rejected.');
}

try {
    (new RequestReader(8, 'php://memory'))->read($server, [], [], $files);
    throw new RuntimeException('Expected multipart input to require an explicit configured cap.');
} catch (UnsupportedMediaType) {
}

try {
    new Request('POST', '/files', uploads: ['first' => $upload, 'second' => $upload]);
    throw new RuntimeException('Expected Request itself to retain the one-upload invariant.');
} catch (InvalidArgumentException) {
}

echo "upload request boundary: ok\n";

/** @return array<string, string> */
function uploadServer(
    ?string $contentLength,
    string $contentType = 'multipart/form-data; boundary=phpthis-boundary',
    ?string $transferEncoding = null,
): array {
    $server = [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/files',
        'CONTENT_TYPE' => $contentType,
    ];

    if ($contentLength !== null) {
        $server['CONTENT_LENGTH'] = $contentLength;
    }

    if ($transferEncoding !== null) {
        $server['HTTP_TRANSFER_ENCODING'] = $transferEncoding;
    }

    return $server;
}

/** @return array<string, mixed> */
function uploadFile(
    string $name,
    ?string $fullPath = null,
    int $error = 0,
    int $size = 12,
): array {
    $file = [
        'name' => $name,
        'type' => 'text/html',
        'tmp_name' => '/tmp/phpthis-upload',
        'error' => $error,
        'size' => $size,
    ];

    if ($fullPath !== null) {
        $file['full_path'] = $fullPath;
    }

    return $file;
}
