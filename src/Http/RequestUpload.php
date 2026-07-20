<?php

declare(strict_types=1);

namespace PHPThis\Http;

final readonly class RequestUpload
{
    public function __construct(
        public string $untrustedClientFilename,
        public string $untrustedClientMediaType,
        public string $temporaryPath,
        public int $reportedSizeBytes,
        public RequestUploadError $error,
    ) {
    }
}
