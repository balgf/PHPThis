<?php

declare(strict_types=1);

use Example\DocumentFiles\DocumentFileId;
use Example\DocumentFiles\DocumentFileNotFound;
use Example\DocumentFiles\DocumentFileUnavailable;
use Example\DocumentFiles\DownloadDocumentFileHandler;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\DocumentFiles\PendingDocumentUpload;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestUpload;
use PHPThis\Http\RequestUploadError;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Routing\PathParameters;

/** @return array<string, Closure(): void> */
function documentFileTests(): array
{
    return [
        'document upload rejects every non-success outcome before storage' => static function (): void {
            $directory = dirname(__DIR__) . '/tmp/document-file-tests/rejected.files';
            removeDocumentFileTestDirectory($directory);

            $invalidErrors = [
                [RequestUploadError::Partial, InvalidRequest::class],
                [RequestUploadError::NoFile, InvalidRequest::class],
                [RequestUploadError::IniSize, RequestBodyTooLarge::class],
                [RequestUploadError::FormSize, RequestBodyTooLarge::class],
                [RequestUploadError::NoTemporaryDirectory, DocumentFileUnavailable::class],
                [RequestUploadError::CannotWrite, DocumentFileUnavailable::class],
                [RequestUploadError::Extension, DocumentFileUnavailable::class],
            ];

            foreach ($invalidErrors as [$error, $expectedFailure]) {
                $upload = $error === RequestUploadError::NoFile
                    ? new RequestUpload('', '', '', 0, $error)
                    : new RequestUpload(
                        '../../private.php',
                        'text/html',
                        '/private/php-upload',
                        1,
                        $error,
                    );

                try {
                    PendingDocumentUpload::fromUploads(['document' => $upload]);
                } catch (Throwable $failure) {
                    if ($failure::class !== $expectedFailure || is_dir($directory)) {
                        throw new RuntimeException('Upload error mapping or rejection ownership changed.');
                    }

                    continue;
                }

                throw new RuntimeException('Expected every unsuccessful PHP upload outcome to fail.');
            }

            $missingAndMultiple = [
                [],
                [
                    'document' => new RequestUpload('', '', '', 0, RequestUploadError::NoFile),
                    'other' => new RequestUpload('', '', '', 0, RequestUploadError::NoFile),
                ],
            ];

            foreach ($missingAndMultiple as $uploads) {
                try {
                    PendingDocumentUpload::fromUploads($uploads);
                } catch (InvalidRequest) {
                    continue;
                }

                throw new RuntimeException('Expected missing and multiple uploads to fail before storage.');
            }

            try {
                PendingDocumentUpload::fromUploads([
                    'document' => new RequestUpload(
                        'oversized.bin',
                        'application/octet-stream',
                        '/not-inspected',
                        1_048_577,
                        RequestUploadError::Success,
                    ),
                ]);
            } catch (RequestBodyTooLarge) {
                if (!is_dir($directory)) {
                    return;
                }
            }

            throw new RuntimeException('Expected the application byte limit before provenance or storage work.');
        },
        'document upload keeps media and provenance failures explicit' => static function (): void {
            $directory = dirname(__DIR__) . '/tmp/document-file-tests/explicit-failures.files';
            removeDocumentFileTestDirectory($directory);
            $files = new LocalDocumentFiles($directory);
            $handler = new Example\DocumentFiles\UploadDocumentFileHandler($files);

            $mediaTypeRejected = false;

            try {
                $handler->handle(new Request('POST', '/document-files'));
            } catch (UnsupportedMediaType) {
                $mediaTypeRejected = true;
            }

            if (!$mediaTypeRejected || is_dir($directory)) {
                throw new RuntimeException('Expected media rejection before upload parsing or storage.');
            }

            try {
                PendingDocumentUpload::fromUploads([
                    'document' => new RequestUpload(
                        "..\\unsafe\r\nname.php",
                        'application/x-httpd-php',
                        '/not-a-php-upload',
                        0,
                        RequestUploadError::Success,
                    ),
                ]);
            } catch (DocumentFileUnavailable $failure) {
                $message = $failure->getMessage();

                if (
                    is_dir($directory)
                    || str_contains($message, 'unsafe')
                    || str_contains($message, 'php-upload')
                    || str_contains($message, 'x-httpd')
                ) {
                    throw new RuntimeException('Untrusted upload metadata escaped a provenance failure.');
                }

                return;
            }

            throw new RuntimeException('Expected a constructed non-upload path to fail provenance checks.');
        },
        'document storage rejects symlinked and overly permissive roots' => static function (): void {
            $base = dirname(__DIR__) . '/tmp/document-file-tests/unsafe-roots';
            $realRoot = $base . '/real';
            $linkedRoot = $base . '/linked';
            removeDocumentFileTestDirectory($base);

            if (!mkdir($realRoot, 0700, true) || !symlink($realRoot, $linkedRoot)) {
                throw new RuntimeException('Unable to create unsafe document-root fixtures.');
            }

            $id = DocumentFileId::fromToken(str_repeat('c', 32));

            try {
                try {
                    (new LocalDocumentFiles($linkedRoot))->read($id);
                    throw new RuntimeException('Expected a symlinked storage root to fail closed.');
                } catch (DocumentFileUnavailable) {
                }

                if (!chmod($realRoot, 0755)) {
                    throw new RuntimeException('Unable to create an overly permissive storage root.');
                }

                try {
                    (new LocalDocumentFiles($realRoot))->read($id);
                    throw new RuntimeException('Expected an overly permissive storage root to fail closed.');
                } catch (DocumentFileUnavailable) {
                }
            } finally {
                if (is_link($linkedRoot) && !unlink($linkedRoot)) {
                    throw new RuntimeException('Unable to remove the linked storage-root fixture.');
                }
                if (is_dir($realRoot) && !chmod($realRoot, 0700)) {
                    throw new RuntimeException('Unable to restore the storage-root fixture mode.');
                }
                removeDocumentFileTestDirectory($base);
            }
        },
        'document download exposes one fixed local-file response contract' => static function (): void {
            $root = dirname(__DIR__) . '/tmp/document-file-tests/download.files';
            removeDocumentFileTestDirectory($root);
            $idValue = str_repeat('a', 32);
            $documentDirectory = $root . '/' . $idValue;

            if (!mkdir($documentDirectory, 0700, true)) {
                throw new RuntimeException('Unable to create the document download fixture.');
            }

            $path = $documentDirectory . '/content';
            $bytes = "stored-document\0bytes";

            if (file_put_contents($path, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Unable to write the document download fixture.');
            }
            if (!chmod($path, 0600)) {
                throw new RuntimeException('Unable to restrict the document download fixture.');
            }

            try {
                $handler = new DownloadDocumentFileHandler(new LocalDocumentFiles($root));
                $response = $handler->handle(new Request(
                    'GET',
                    '/document-files/' . $idValue,
                    pathParameters: PathParameters::fromValues([], ['file_id' => $idValue]),
                ));

                if (
                    $response->status !== 200
                    || $response->body !== ''
                    || $response->fileBody === null
                    || $response->fileBody->path !== $path
                    || $response->fileBody->bytes !== strlen($bytes)
                    || $response->headers !== [
                        'Content-Type' => 'application/octet-stream',
                        'Content-Disposition' => 'attachment; filename="document.bin"',
                        'Content-Length' => (string) strlen($bytes),
                        'Cache-Control' => 'private, no-store',
                        'X-Content-Type-Options' => 'nosniff',
                        'Accept-Ranges' => 'none',
                    ]
                ) {
                    throw new RuntimeException('Expected the fixed explicit local-file response contract.');
                }

                if (!chmod($path, 0644)) {
                    throw new RuntimeException('Unable to loosen the document download fixture mode.');
                }

                try {
                    (new LocalDocumentFiles($root))->read(DocumentFileId::fromToken($idValue));
                    throw new RuntimeException('Expected a changed retained-file mode to fail closed.');
                } catch (DocumentFileUnavailable) {
                } finally {
                    if (!chmod($path, 0600)) {
                        throw new RuntimeException('Unable to restore the document download fixture mode.');
                    }
                }

                try {
                    (new LocalDocumentFiles($root))->read(DocumentFileId::fromToken(str_repeat('b', 32)));
                } catch (DocumentFileNotFound) {
                    return;
                }

                throw new RuntimeException('Expected a missing document file to retain its named failure.');
            } finally {
                removeDocumentFileTestDirectory($root);
            }
        },
        'real multipart upload and download remain bounded and metadata-blind' => static function (): void {
            proveRealDocumentFileTransfer();
        },
        'large local file emission stays below a fixed memory delta' => static function (): void {
            proveBoundedDocumentFileEmission();
        },
    ];
}

function proveBoundedDocumentFileEmission(): void
{
    $directory = dirname(__DIR__) . '/tmp/document-file-tests/bounded-emission';
    removeDocumentFileTestDirectory($directory);

    if (!mkdir($directory, 0700, true)) {
        throw new RuntimeException('Unable to create the bounded-emission test directory.');
    }

    $source = $directory . '/source.bin';
    $output = $directory . '/output.bin';
    $handle = fopen($source, 'wb');
    $fileBytes = 16_777_216;

    if (!is_resource($handle)) {
        throw new RuntimeException('Unable to create the large source file.');
    }

    try {
        $chunk = str_repeat('0123456789abcdef', 512);

        for ($written = 0; $written < $fileBytes; $written += strlen($chunk)) {
            if (fwrite($handle, $chunk) !== strlen($chunk)) {
                throw new RuntimeException('Unable to write the large source file.');
            }
        }
    } finally {
        fclose($handle);
    }

    try {
        $process = proc_open(
            [
                PHP_BINARY,
                '-d',
                'memory_limit=12M',
                __DIR__ . '/large-file-emitter.php',
                $source,
                (string) $fileBytes,
            ],
            [0 => ['pipe', 'r'], 1 => ['file', $output, 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
            null,
            ['bypass_shell' => true],
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the bounded-emission subprocess.');
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $additionalPeakBytes = is_string($stderr)
            ? filter_var(trim($stderr), FILTER_VALIDATE_INT)
            : false;
        $sourceHash = hash_file('sha256', $source);
        $outputHash = hash_file('sha256', $output);

        if (
            $exitCode !== 0
            || !is_int($additionalPeakBytes)
            || $additionalPeakBytes > 1_048_576
            || filesize($output) !== $fileBytes
            || !is_string($sourceHash)
            || $sourceHash !== $outputHash
        ) {
            throw new RuntimeException('Large-file emission exceeded its fixed memory or byte contract.');
        }
    } finally {
        removeDocumentFileTestDirectory($directory);
    }
}

function proveRealDocumentFileTransfer(): void
{
    $root = dirname(__DIR__);
    $temporary = $root . '/tmp/document-file-tests/real-sapi';
    removeDocumentFileTestDirectory($temporary);

    if (!mkdir($temporary . '/upload-tmp', 0700, true)) {
        throw new RuntimeException('Unable to create the real multipart test directory.');
    }

    $databasePath = $root . '/tmp/example.sqlite';
    $databaseExisted = is_file($databasePath);
    $storageRoot = $databasePath . '.files';
    $storageExisted = is_dir($storageRoot);

    if (!$databaseExisted && file_put_contents($databasePath, '') !== 0) {
        throw new RuntimeException('Unable to create the temporary example database.');
    }

    $maximumFile = $temporary . '/maximum.bin';
    $oversizedFile = $temporary . '/oversized.bin';
    $smallFile = $temporary . '/small.bin';
    $secondSmallFile = $temporary . '/second-small.bin';
    $emptyFile = $temporary . '/empty.bin';
    $payload = str_repeat('P', 1_048_576);

    if (
        file_put_contents($maximumFile, $payload) !== 1_048_576
        || file_put_contents($oversizedFile, $payload . 'X') !== 1_048_577
        || file_put_contents($smallFile, 'S') !== 1
        || file_put_contents($secondSmallFile, 'T') !== 1
        || file_put_contents($emptyFile, '') !== 0
    ) {
        throw new RuntimeException('Unable to write multipart boundary fixtures.');
    }

    $server = null;
    $storedId = null;
    $emptyStoredId = null;
    $scalarDuplicateStoredId = null;
    $storageBackup = $temporary . '/displaced-storage';
    $storageDisplaced = false;

    try {
        [$server, $port] = startDocumentFileServer($temporary, $root);
        $upload = runDocumentFileCurl(
            $temporary,
            'upload',
            [
                '--form',
                'document=@' . $maximumFile
                    . ';filename=../../unsafe.php;type=application/x-httpd-php',
            ],
            'http://127.0.0.1:' . $port . '/document-files',
        );

        if ($upload['status'] !== 201) {
            throw new RuntimeException('Expected the exact 1 MiB multipart upload to succeed.');
        }

        $decoded = json_decode($upload['body'], true, 8, JSON_THROW_ON_ERROR);
        $storedId = is_array($decoded) ? ($decoded['file_id'] ?? null) : null;

        if (!is_string($storedId) || preg_match('/^[0-9a-f]{32}$/D', $storedId) !== 1) {
            throw new RuntimeException('Expected a server-generated opaque document file identifier.');
        }

        $storedPath = $storageRoot . '/' . $storedId . '/content';
        $storedDirectory = dirname($storedPath);
        $storedHash = hash_file('sha256', $storedPath);
        $expectedHash = hash_file('sha256', $maximumFile);
        $rootPermissions = fileperms($storageRoot);
        $directoryPermissions = fileperms($storedDirectory);
        $filePermissions = fileperms($storedPath);

        if (
            !is_string($storedHash)
            || !is_string($expectedHash)
            || $storedHash !== $expectedHash
            || !is_int($rootPermissions)
            || ($rootPermissions & 0777) !== 0700
            || !is_int($directoryPermissions)
            || ($directoryPermissions & 0777) !== 0700
            || !is_int($filePermissions)
            || ($filePermissions & 0777) !== 0600
            || is_file($storageRoot . '/unsafe.php')
            || str_contains($upload['body'], 'unsafe')
            || str_contains($upload['headers'], 'unsafe')
        ) {
            throw new RuntimeException('Client upload metadata influenced storage or the public response.');
        }

        $emptyUpload = runDocumentFileCurl(
            $temporary,
            'empty-upload',
            ['--form', 'document=@' . $emptyFile . ';filename=empty.php;type=text/html'],
            'http://127.0.0.1:' . $port . '/document-files',
        );
        $emptyDecoded = json_decode($emptyUpload['body'], true, 8, JSON_THROW_ON_ERROR);
        $emptyStoredId = is_array($emptyDecoded) ? ($emptyDecoded['file_id'] ?? null) : null;

        if (
            $emptyUpload['status'] !== 201
            || !is_string($emptyStoredId)
            || preg_match('/^[0-9a-f]{32}$/D', $emptyStoredId) !== 1
            || filesize($storageRoot . '/' . $emptyStoredId . '/content') !== 0
        ) {
            throw new RuntimeException('Expected an empty successful upload to remain an application decision.');
        }

        $scalarDuplicate = runDocumentFileCurl(
            $temporary,
            'scalar-duplicate',
            [
                '--form',
                'document=@' . $smallFile . ';filename=first.bin',
                '--form',
                'document=@' . $secondSmallFile . ';filename=second.bin',
            ],
            'http://127.0.0.1:' . $port . '/document-files',
        );
        $scalarDuplicateDecoded = json_decode($scalarDuplicate['body'], true, 8, JSON_THROW_ON_ERROR);
        $scalarDuplicateStoredId = is_array($scalarDuplicateDecoded)
            ? ($scalarDuplicateDecoded['file_id'] ?? null)
            : null;
        $normalizedScalarBytes = is_string($scalarDuplicateStoredId)
            ? file_get_contents($storageRoot . '/' . $scalarDuplicateStoredId . '/content')
            : false;

        if (
            $scalarDuplicate['status'] !== 201
            || !is_string($scalarDuplicateStoredId)
            || preg_match('/^[0-9a-f]{32}$/D', $scalarDuplicateStoredId) !== 1
            || !in_array($normalizedScalarBytes, ['S', 'T'], true)
        ) {
            throw new RuntimeException('Expected PHP to expose duplicate scalar parts as one normalized upload.');
        }

        $download = runDocumentFileCurl(
            $temporary,
            'download',
            [],
            'http://127.0.0.1:' . $port . '/document-files/' . $storedId,
        );
        $range = runDocumentFileCurl(
            $temporary,
            'range',
            ['--header', 'Range: bytes=0-1'],
            'http://127.0.0.1:' . $port . '/document-files/' . $storedId,
        );
        $downloadHash = hash_file('sha256', $download['body_path']);
        $rangeHash = hash_file('sha256', $range['body_path']);
        $normalizedHeaders = strtolower($download['headers']);

        if (
            $download['status'] !== 200
            || $range['status'] !== 200
            || $downloadHash !== $expectedHash
            || $rangeHash !== $expectedHash
            || !str_contains($normalizedHeaders, "content-length: 1048576\r\n")
            || !str_contains($normalizedHeaders, "accept-ranges: none\r\n")
            || !str_contains($normalizedHeaders, "x-content-type-options: nosniff\r\n")
            || !str_contains(
                $normalizedHeaders,
                "content-disposition: attachment; filename=\"document.bin\"\r\n",
            )
        ) {
            throw new RuntimeException('Expected exact full-response bytes and explicit range deferral.');
        }

        $oversized = runDocumentFileCurl(
            $temporary,
            'oversized',
            ['--form', 'document=@' . $oversizedFile . ';filename=oversized.bin'],
            'http://127.0.0.1:' . $port . '/document-files',
        );
        $multiple = runDocumentFileCurl(
            $temporary,
            'multiple',
            [
                '--form',
                'document[]=@' . $smallFile . ';filename=first.bin',
                '--form',
                'document[]=@' . $smallFile . ';filename=second.bin',
            ],
            'http://127.0.0.1:' . $port . '/document-files',
        );
        $missing = runDocumentFileCurl(
            $temporary,
            'missing',
            ['--form', 'document='],
            'http://127.0.0.1:' . $port . '/document-files',
        );
        $wrongMediaType = runDocumentFileCurl(
            $temporary,
            'wrong-media-type',
            ['--header', 'Content-Type: application/json', '--data-binary', '{}'],
            'http://127.0.0.1:' . $port . '/document-files',
        );
        $notFound = runDocumentFileCurl(
            $temporary,
            'not-found',
            [],
            'http://127.0.0.1:' . $port . '/document-files/' . str_repeat('f', 32),
        );

        if (!rename($storageRoot, $storageBackup)) {
            throw new RuntimeException('Unable to displace the document storage root.');
        }
        $storageDisplaced = true;

        if (file_put_contents($storageRoot, 'not-a-directory') !== 15) {
            throw new RuntimeException('Unable to create the unavailable storage-root fixture.');
        }

        $unavailable = runDocumentFileCurl(
            $temporary,
            'unavailable-storage',
            ['--form', 'document=@' . $smallFile . ';filename=private-path.php'],
            'http://127.0.0.1:' . $port . '/document-files',
        );

        if (!unlink($storageRoot) || !rename($storageBackup, $storageRoot)) {
            throw new RuntimeException('Unable to restore the document storage root.');
        }
        $storageDisplaced = false;

        if (
            $oversized['status'] !== 413
            || $multiple['status'] !== 400
            || $missing['status'] !== 400
            || $wrongMediaType['status'] !== 415
            || $notFound['status'] !== 404
            || $unavailable['status'] !== 500
            || str_contains($unavailable['body'], $storageRoot)
            || str_contains($unavailable['body'], 'private-path.php')
        ) {
            throw new RuntimeException('Expected the complete bounded file-transfer failure map.');
        }

        $serverLog = file_get_contents($temporary . '/server.log');

        if (
            !is_string($serverLog)
            || str_contains($serverLog, 'unsafe.php')
            || str_contains($serverLog, 'private-path.php')
            || str_contains($serverLog, $storageRoot)
        ) {
            throw new RuntimeException('Untrusted metadata and storage paths must not enter terminal output.');
        }
    } finally {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
        }

        if ($storageDisplaced) {
            if (is_file($storageRoot)) {
                unlink($storageRoot);
            }
            if (is_dir($storageBackup)) {
                rename($storageBackup, $storageRoot);
            }
        }

        if (is_string($storedId)) {
            $storedDirectory = $storageRoot . '/' . $storedId;
            $storedPath = $storedDirectory . '/content';

            if (is_file($storedPath)) {
                unlink($storedPath);
            }

            if (is_dir($storedDirectory)) {
                rmdir($storedDirectory);
            }
        }

        if (is_string($emptyStoredId)) {
            $emptyStoredDirectory = $storageRoot . '/' . $emptyStoredId;
            $emptyStoredPath = $emptyStoredDirectory . '/content';

            if (is_file($emptyStoredPath)) {
                unlink($emptyStoredPath);
            }

            if (is_dir($emptyStoredDirectory)) {
                rmdir($emptyStoredDirectory);
            }
        }

        if (is_string($scalarDuplicateStoredId)) {
            $scalarDuplicateDirectory = $storageRoot . '/' . $scalarDuplicateStoredId;
            $scalarDuplicatePath = $scalarDuplicateDirectory . '/content';

            if (is_file($scalarDuplicatePath)) {
                unlink($scalarDuplicatePath);
            }

            if (is_dir($scalarDuplicateDirectory)) {
                rmdir($scalarDuplicateDirectory);
            }
        }

        if (!$storageExisted && is_dir($storageRoot)) {
            rmdir($storageRoot);
        }

        if (!$databaseExisted && is_file($databasePath)) {
            unlink($databasePath);
        }

        removeDocumentFileTestDirectory($temporary);
    }
}

/** @return array{0: resource, 1: int} */
function startDocumentFileServer(string $temporary, string $root): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to reserve a local HTTP test port.');
    }

    $socketName = stream_socket_get_name($socket, false);
    fclose($socket);
    $separator = is_string($socketName) ? strrpos($socketName, ':') : false;
    $portValue = $separator === false ? null : substr($socketName, $separator + 1);
    $port = is_string($portValue) ? filter_var($portValue, FILTER_VALIDATE_INT) : false;

    if (!is_int($port) || $port < 1 || $port > 65_535) {
        throw new RuntimeException('Unable to resolve the local HTTP test port.');
    }

    $process = proc_open(
        [
            PHP_BINARY,
            '-d',
            'display_errors=1',
            '-d',
            'file_uploads=1',
            '-d',
            'upload_max_filesize=2M',
            '-d',
            'post_max_size=3M',
            '-d',
            'max_file_uploads=2',
            '-d',
            'upload_tmp_dir=' . $temporary . '/upload-tmp',
            '-S',
            '127.0.0.1:' . $port,
            '-t',
            $root . '/example/public',
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['file', $temporary . '/server.log', 'a'],
            2 => ['file', $temporary . '/server.log', 'a'],
        ],
        $pipes,
        $root,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the local HTTP test server.');
    }

    fclose($pipes[0]);
    $deadline = hrtime(true) + 5_000_000_000;

    do {
        $probe = @fsockopen('127.0.0.1', $port, $probeError, $probeMessage, 0.05);

        if (is_resource($probe)) {
            fclose($probe);
            return [$process, $port];
        }

        usleep(10_000);
    } while (hrtime(true) < $deadline);

    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('The local HTTP test server did not become ready.');
}

/**
 * @param list<string> $arguments
 * @return array{status: int, body: string, body_path: string, headers: string}
 */
function runDocumentFileCurl(
    string $temporary,
    string $name,
    array $arguments,
    string $url,
): array {
    $bodyPath = $temporary . '/' . $name . '.body';
    $headerPath = $temporary . '/' . $name . '.headers';
    $process = proc_open(
        [
            'curl',
            '--silent',
            '--show-error',
            '--max-time',
            '15',
            '--output',
            $bodyPath,
            '--dump-header',
            $headerPath,
            '--write-out',
            '%{http_code}',
            ...$arguments,
            $url,
        ],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to execute the local HTTP client.');
    }

    fclose($pipes[0]);
    $statusOutput = stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $body = file_get_contents($bodyPath);
    $headers = file_get_contents($headerPath);

    if (
        $exitCode !== 0
        || !is_string($statusOutput)
        || !is_string($errorOutput)
        || $errorOutput !== ''
        || !is_string($body)
        || !is_string($headers)
    ) {
        throw new RuntimeException('The local HTTP client failed.');
    }

    $status = filter_var(trim($statusOutput), FILTER_VALIDATE_INT);

    if (!is_int($status)) {
        throw new RuntimeException('The local HTTP client returned an invalid status.');
    }

    return ['status' => $status, 'body' => $body, 'body_path' => $bodyPath, 'headers' => $headers];
}

function removeDocumentFileTestDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $entries = scandir($directory);

    if (!is_array($entries)) {
        throw new RuntimeException('Unable to inspect a document file test directory.');
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($path)) {
            removeDocumentFileTestDirectory($path);
        } elseif (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove a document file test artifact.');
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove a document file test directory.');
    }
}
