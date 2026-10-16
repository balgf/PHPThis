<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestUploadError;
use PHPThis\Http\UnsupportedMediaType;

final readonly class PendingImageUpload
{
    private function __construct(public string $temporaryPath, public int $bytes) {}

    public static function fromRequest(Request $request, CatalogImageFormat $format = CatalogImageFormat::Png): self
    {
        $field = $format === CatalogImageFormat::Mp4 ? 'clip' : 'image';
        if ($request->multipart === null) { throw new UnsupportedMediaType('Multipart image required.'); }
        if ($request->multipart->fields !== [] || array_keys($request->uploads) !== [$field]) {
            throw new InvalidRequest('One image and no fields required.');
        }
        $upload = $request->uploads[$field];
        match ($upload->error) {
            RequestUploadError::Success => null,
            RequestUploadError::IniSize, RequestUploadError::FormSize => throw new RequestBodyTooLarge('Image too large.'),
            RequestUploadError::Partial, RequestUploadError::NoFile => throw new InvalidRequest('Incomplete image.'),
            RequestUploadError::NoTemporaryDirectory, RequestUploadError::CannotWrite,
            RequestUploadError::Extension => throw new CatalogUnavailable('Upload unavailable.'),
        };
        if ($upload->reportedSizeBytes < 1) { throw new InvalidRequest('Empty image.'); }
        if ($upload->reportedSizeBytes > 1_048_576) { throw new RequestBodyTooLarge('Image too large.'); }
        if ($upload->temporaryPath === '' || !@is_uploaded_file($upload->temporaryPath)
            || @filesize($upload->temporaryPath) !== $upload->reportedSizeBytes) {
            throw new CatalogUnavailable('Upload provenance unavailable.');
        }
        return new self($upload->temporaryPath, $upload->reportedSizeBytes);
    }
}
