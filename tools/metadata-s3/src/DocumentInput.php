<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestUpload;
use PHPThis\Http\RequestUploadError;

final class InvalidMetadata extends \RuntimeException {}
final class CapacityUnavailable extends \RuntimeException {}

final readonly class DocumentMetadata
{
    private function __construct(public string $label, public ?string $note) {}

    /** @param array<string, mixed> $fields */
    public static function fromFields(array $fields): self
    {
        if (!array_key_exists('label', $fields) || array_diff(array_keys($fields), ['label', 'note']) !== []
            || !is_string($fields['label']) || (array_key_exists('note', $fields) && !is_string($fields['note']))) {
            throw new InvalidRequest('Invalid document metadata shape.');
        }
        $label = $fields['label'];
        $note = $fields['note'] ?? null;
        if (strlen($label) < 1 || strlen($label) > 120 || preg_match('//u', $label) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $label) === 1
            || ($note !== null && (strlen($note) > 1024 || preg_match('//u', $note) !== 1
                || preg_match('/[\x00-\x1f\x7f]/', $note) === 1))) {
            throw new InvalidMetadata('Invalid document metadata value.');
        }
        return new self($label, $note);
    }
}

final readonly class DocumentInput
{
    private function __construct(public DocumentMetadata $metadata, public RequestUpload $upload) {}

    public static function fromMultipart(MultipartForm $form): self
    {
        // Validate the entire observable set before any metadata value rule.
        if (array_keys($form->uploads) !== ['document'] || !array_key_exists('label', $form->fields)
            || array_diff(array_keys($form->fields), ['label', 'note']) !== []) {
            throw new InvalidRequest('Invalid document operation shape.');
        }
        $upload = $form->uploads['document'];
        match ($upload->error) {
            RequestUploadError::Success => null,
            RequestUploadError::IniSize, RequestUploadError::FormSize => throw new RequestBodyTooLarge('Document too large.'),
            RequestUploadError::Partial, RequestUploadError::NoFile => throw new InvalidRequest('Document upload incomplete.'),
            RequestUploadError::NoTemporaryDirectory, RequestUploadError::CannotWrite,
            RequestUploadError::Extension => throw new DocumentUnavailable('Document temporary storage unavailable.'),
        };
        $metadata = DocumentMetadata::fromFields($form->fields);
        if ($upload->reportedSizeBytes < 1 || $upload->reportedSizeBytes > 65536) {
            throw new InvalidMetadata('Invalid document size.');
        }
        return new self($metadata, $upload);
    }
}

final readonly class DocumentOwner
{
    public function __construct(public int $id)
    {
        if ($id < 1) { throw new \InvalidArgumentException('Invalid document owner.'); }
    }
}
