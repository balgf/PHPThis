<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestUpload;
use PHPThis\Http\RequestUploadError;

final class PacketConflict extends \RuntimeException {}
final class PacketGone extends \RuntimeException {}

final readonly class PacketInput
{
    private function __construct(
        public DocumentMetadata $metadata, public string $requestId,
        public RequestUpload $primary, public RequestUpload $supporting,
    ) {}

    public static function fromMultipart(MultipartForm $form): self
    {
        if (count($form->uploads) !== 2 || !isset($form->uploads['primary'], $form->uploads['supporting'])
            || !array_key_exists('label', $form->fields) || !array_key_exists('request_id', $form->fields)
            || array_diff(array_keys($form->fields), ['label', 'note', 'request_id']) !== []) {
            throw new InvalidRequest('Invalid packet shape.');
        }
        self::requireUpload($form->uploads['primary']);
        self::requireUpload($form->uploads['supporting']);
        $metadata = DocumentMetadata::fromFields(array_key_exists('note', $form->fields)
            ? ['label' => $form->fields['label'], 'note' => $form->fields['note']] : ['label' => $form->fields['label']]);
        if (preg_match('/\A[0-9a-f]{32}\z/', $form->fields['request_id']) !== 1
            || $form->uploads['primary']->reportedSizeBytes < 1 || $form->uploads['primary']->reportedSizeBytes > 65536
            || $form->uploads['supporting']->reportedSizeBytes < 1 || $form->uploads['supporting']->reportedSizeBytes > 65536) {
            throw new InvalidMetadata('Invalid packet values.');
        }
        return new self($metadata, $form->fields['request_id'], $form->uploads['primary'], $form->uploads['supporting']);
    }

    public function fingerprint(VerifiedUpload $primary, VerifiedUpload $supporting): string
    {
        return hash('sha256', json_encode(['version' => 1, 'label' => $this->metadata->label, 'note' => $this->metadata->note,
            'primary' => ['bytes' => $primary->bytes, 'sha256' => $primary->sha256],
            'supporting' => ['bytes' => $supporting->bytes, 'sha256' => $supporting->sha256]], JSON_THROW_ON_ERROR));
    }

    private static function requireUpload(RequestUpload $upload): void
    {
        match ($upload->error) {
            RequestUploadError::Success => null,
            RequestUploadError::IniSize, RequestUploadError::FormSize => throw new RequestBodyTooLarge('Packet too large.'),
            RequestUploadError::Partial, RequestUploadError::NoFile => throw new InvalidRequest('Packet incomplete.'),
            RequestUploadError::NoTemporaryDirectory, RequestUploadError::CannotWrite,
            RequestUploadError::Extension => throw new DocumentUnavailable('Packet temporary storage unavailable.'),
        };
    }
}
