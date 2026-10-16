<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use Aws\Exception\AwsException;
use Aws\CommandInterface;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\DroppingStream;
use GuzzleHttp\Psr7\Utils;

/** Concrete S3 operations for this metadata-bearing document only; no backend interface or registry. */
final class DocumentS3
{
    private int $calls = 0;
    public function __construct(private readonly S3Client $s3, public readonly S3Target $target) {}
    public function calls(): int { return $this->calls; }

    public function create(DocumentId $id, VerifiedUpload $upload): ObjectVersion
    {
        $command = $this->s3->getCommand('PutObject', [
            'Bucket' => $this->target->bucket, 'Key' => $id->key(), 'Body' => $upload->handle(),
            'ContentLength' => $upload->bytes, 'ContentType' => 'application/octet-stream',
            'StorageClass' => 'STANDARD', 'ChecksumAlgorithm' => 'SHA256', 'ChecksumSHA256' => $upload->checksum,
            'IfNoneMatch' => '*', 'ServerSideEncryption' => 'aws:kms', 'SSEKMSKeyId' => $this->target->kms,
            'BucketKeyEnabled' => false, 'ExpectedBucketOwner' => $this->target->account,
            'Metadata' => ['profile' => 'private-documents-v1'],
        ]);
        try { $result = $this->control($command); }
        catch (AwsException $failure) {
            if ($failure->getStatusCode() === 412) { throw new DocumentCollision('Document collision.'); }
            throw new DocumentUnavailable('Document write unresolved.');
        }
        if ($result['ChecksumSHA256'] !== $upload->checksum) { throw new DocumentUnavailable('Document checksum unresolved.'); }
        return ObjectVersion::fromInput($result['VersionId']);
    }

    public function head(DocumentRecord $record, ObjectVersion $version): void
    {
        $result = $this->control($this->s3->getCommand('HeadObject', [
            'Bucket' => $this->target->bucket, 'Key' => $record->id->key(), 'VersionId' => $version->value,
            'ChecksumMode' => 'ENABLED', 'ExpectedBucketOwner' => $this->target->account,
        ]));
        $this->validate($result, $record, $version);
    }

    public function version(DocumentRecord $record): ?ObjectVersion
    {
        $result = $this->control($this->s3->getCommand('ListObjectVersions', [
            'Bucket' => $this->target->bucket, 'Prefix' => $record->id->key(), 'MaxKeys' => 2,
            'ExpectedBucketOwner' => $this->target->account,
        ]));
        $versions = $result['Versions'] ?? [];
        $markers = $result['DeleteMarkers'] ?? [];
        if ($result['IsTruncated'] !== false || $result->hasKey('NextKeyMarker') || $result->hasKey('NextVersionIdMarker')
            || $markers !== [] || !is_array($versions) || !array_is_list($versions) || count($versions) > 1) {
            throw new DocumentUnavailable('Document listing unresolved.');
        }
        if ($versions === []) { return null; }
        $candidate = $versions[0];
        // SDK XML long values are decimal strings; accept only the exact expected size, or its integer form.
        if (!is_array($candidate) || ($candidate['Key'] ?? null) !== $record->id->key()
            || ($candidate['IsLatest'] ?? null) !== true
            || (($candidate['Size'] ?? null) !== $record->bytes && ($candidate['Size'] ?? null) !== (string) $record->bytes)) {
            throw new DocumentUnavailable('Document ownership unresolved.');
        }
        $version = ObjectVersion::fromInput($candidate['VersionId'] ?? null);
        if ($record->version !== null && $record->version->value !== $version->value) { throw new DocumentUnavailable('Document version changed.'); }
        return $version;
    }

    public function delete(DocumentRecord $record): void
    {
        if ($record->version === null) { throw new DocumentUnavailable('Document delete ownership unavailable.'); }
        $result = $this->control($this->s3->getCommand('DeleteObject', [
            'Bucket' => $this->target->bucket, 'Key' => $record->id->key(), 'VersionId' => $record->version->value,
            'ExpectedBucketOwner' => $this->target->account,
        ]));
        if ($result['VersionId'] !== $record->version->value || $result->hasKey('DeleteMarker')) {
            throw new DocumentUnavailable('Document deletion unresolved.');
        }
    }

    private function validate(ResultInterface $result, DocumentRecord $record, ObjectVersion $version): void
    {
        if ($result['VersionId'] !== $version->value || $result['ContentLength'] !== $record->bytes
            || $result['ChecksumSHA256'] !== $record->checksum || $result['ServerSideEncryption'] !== 'aws:kms'
            || $result['SSEKMSKeyId'] !== $this->target->kms || $result['BucketKeyEnabled'] !== false
            || $result['ContentType'] !== 'application/octet-stream' || $result['Metadata'] !== ['profile' => 'private-documents-v1']
            || $result->hasKey('StorageClass') || $result->hasKey('ArchiveStatus') || $result->hasKey('Restore')
            || $result->hasKey('Expiration') || $result->hasKey('ContentEncoding') || $result->hasKey('ContentRange')
            || $result->hasKey('DeleteMarker')) {
            throw new DocumentUnavailable('Document retained facts unavailable.');
        }
    }

    private function countCall(): void
    {
        if ($this->calls >= 4) { throw new DocumentUnavailable('Document operation budget exhausted.'); }
        $this->calls++;
    }

    private function control(CommandInterface $command): ResultInterface
    {
        // Bound XML and error responses too; Guzzle's default sink would spill to disk.
        $stream = @fopen('php://memory', 'w+b');
        if ($stream === false) { throw new DocumentUnavailable('Document response buffer unavailable.'); }
        $sink = new DroppingStream(Utils::streamFor($stream), 16_384);
        try {
            $command['@http'] = ['sink' => $sink];
            $this->countCall();
            return $this->s3->execute($command);
        } finally {
            $sink->close();
            if (is_resource($stream)) { fclose($stream); }
        }
    }
}
