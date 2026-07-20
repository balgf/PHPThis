<?php

declare(strict_types=1);

namespace Example\DocumentFiles;

use PHPThis\Http\InvalidRequest;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestUpload;
use PHPThis\Http\RequestUploadError;

final readonly class PendingDocumentUpload
{
    private const int MAXIMUM_BYTES = 1_048_576;

    private function __construct(
        public string $temporaryPath,
        public int $sizeBytes,
    ) {
    }

    /** @param array<string, RequestUpload> $uploads */
    public static function fromUploads(array $uploads): self
    {
        if (array_keys($uploads) !== ['document']) {
            throw new InvalidRequest('Exactly one document upload is required.');
        }

        $upload = $uploads['document'];

        match ($upload->error) {
            RequestUploadError::Success => null,
            RequestUploadError::IniSize,
            RequestUploadError::FormSize => throw new RequestBodyTooLarge(
                'Document upload exceeds its byte limit.',
            ),
            RequestUploadError::Partial,
            RequestUploadError::NoFile => throw new InvalidRequest(
                'Document upload is incomplete or missing.',
            ),
            RequestUploadError::NoTemporaryDirectory,
            RequestUploadError::CannotWrite,
            RequestUploadError::Extension => throw new DocumentFileUnavailable(
                'PHP could not retain the uploaded document.',
            ),
        };

        if ($upload->reportedSizeBytes > self::MAXIMUM_BYTES) {
            throw new RequestBodyTooLarge('Document upload exceeds its byte limit.');
        }

        if ($upload->temporaryPath === '' || !@is_uploaded_file($upload->temporaryPath)) {
            throw new DocumentFileUnavailable('Uploaded document provenance could not be verified.');
        }

        $actualBytes = @filesize($upload->temporaryPath);

        if (!is_int($actualBytes) || $actualBytes !== $upload->reportedSizeBytes) {
            throw new DocumentFileUnavailable('Uploaded document size could not be verified.');
        }

        return new self($upload->temporaryPath, $actualBytes);
    }
}
