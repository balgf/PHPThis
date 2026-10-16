<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

use Aws\Exception\AwsException;
use Aws\CommandInterface;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\DroppingStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/** Concrete S3 operations for the CSV export and fixed test tone; no backend interface or registry. */
final class InventoryS3
{
    private int $calls = 0;
    public function __construct(private readonly S3Client $s3, public readonly S3Target $target) {}
    public function calls(): int { return $this->calls; }

    public function create(ExportId $id, ExportBytes $bytes): ObjectVersion
    {
        $source = @fopen('php://memory', 'w+b');
        if ($source === false) { throw new BeforePut('Export staging unavailable.'); }
        try {
            if (fwrite($source, $bytes->value) !== strlen($bytes->value) || !rewind($source)) {
                throw new BeforePut('Export staging incomplete.');
            }
            $command = $this->s3->getCommand('PutObject', [
                'Bucket' => $this->target->bucket, 'Key' => $id->key(), 'Body' => $source,
                'ContentLength' => strlen($bytes->value), 'ContentType' => 'application/octet-stream',
                'StorageClass' => 'STANDARD', 'ChecksumAlgorithm' => 'SHA256', 'ChecksumSHA256' => $bytes->checksum,
                'IfNoneMatch' => '*', 'ServerSideEncryption' => 'aws:kms', 'SSEKMSKeyId' => $this->target->kms,
                'BucketKeyEnabled' => false, 'ExpectedBucketOwner' => $this->target->account,
                'Metadata' => ['profile' => 'private-documents-v1'],
            ]);
            try { $result = $this->control($command); }
            catch (AwsException $failure) {
                if ($failure->getStatusCode() === 412) { throw new ExportCollision('Export collision.'); }
                throw new ExportUnavailable('Export write unresolved.');
            }
            if ($result['ChecksumSHA256'] !== $bytes->checksum) { throw new ExportUnavailable('Export checksum unresolved.'); }
            return ObjectVersion::fromInput($result['VersionId']);
        } finally { if (is_resource($source)) { fclose($source); } }
    }

    public function head(ExportRecord $record, ObjectVersion $version): void
    {
        $result = $this->control($this->s3->getCommand('HeadObject', [
            'Bucket' => $this->target->bucket, 'Key' => $record->id->key(), 'VersionId' => $version->value,
            'ChecksumMode' => 'ENABLED', 'ExpectedBucketOwner' => $this->target->account,
        ]));
        $this->validate($result, $record, $version);
    }

    public function read(ExportRecord $record): ExportBytes
    {
        $version = $record->version;
        if ($version === null) { throw new ExportUnavailable('Export version unavailable.'); }
        $stream = @fopen('php://memory', 'w+b');
        if ($stream === false) { throw new ExportUnavailable('Export read buffer unavailable.'); }
        $sink = new DroppingStream(Utils::streamFor($stream), $record->bytes);
        $body = null;
        try {
            $this->countCall();
            $result = $this->s3->execute($this->s3->getCommand('GetObject', [
                'Bucket' => $this->target->bucket, 'Key' => $record->id->key(), 'VersionId' => $version->value,
                'ChecksumMode' => 'ENABLED', 'ExpectedBucketOwner' => $this->target->account, '@http' => ['sink' => $sink],
            ]));
            $candidate = $result['Body'];
            if (!$candidate instanceof StreamInterface) { throw new ExportUnavailable('Export read unavailable.'); }
            $body = $candidate;
            $this->validate($result, $record, $version);
            // This independently bounds consumption even if a test handler bypasses the real cURL sink.
            $bytes = $body->read($record->bytes + 1);
            if (strlen($bytes) !== $record->bytes || !$body->eof() || !hash_equals($record->sha256, hash('sha256', $bytes))) {
                throw new ExportUnavailable('Export integrity unavailable.');
            }
            return ExportBytes::fromBytes($bytes);
        } finally {
            if ($body !== null) { $body->close(); }
            $sink->close();
            if (is_resource($stream)) { fclose($stream); }
        }
    }

    public function version(ExportRecord $record): ?ObjectVersion
    {
        $result = $this->control($this->s3->getCommand('ListObjectVersions', [
            'Bucket' => $this->target->bucket, 'Prefix' => $record->id->key(), 'MaxKeys' => 2,
            'ExpectedBucketOwner' => $this->target->account,
        ]));
        $versions = $result['Versions'] ?? [];
        $markers = $result['DeleteMarkers'] ?? [];
        if ($result['IsTruncated'] !== false || $result->hasKey('NextKeyMarker') || $result->hasKey('NextVersionIdMarker')
            || $markers !== [] || !is_array($versions) || !array_is_list($versions) || count($versions) > 1) {
            throw new ExportUnavailable('Export listing unresolved.');
        }
        if ($versions === []) { return null; }
        $candidate = $versions[0];
        // SDK XML long values are decimal strings; accept only the exact expected size, or its integer form.
        if (!is_array($candidate) || ($candidate['Key'] ?? null) !== $record->id->key()
            || ($candidate['IsLatest'] ?? null) !== true
            || (($candidate['Size'] ?? null) !== $record->bytes && ($candidate['Size'] ?? null) !== (string) $record->bytes)) {
            throw new ExportUnavailable('Export ownership unresolved.');
        }
        $version = ObjectVersion::fromInput($candidate['VersionId'] ?? null);
        if ($record->version !== null && $record->version->value !== $version->value) { throw new ExportUnavailable('Export version changed.'); }
        return $version;
    }

    public function delete(ExportRecord $record): void
    {
        if ($record->version === null) { throw new ExportUnavailable('Export delete ownership unavailable.'); }
        $result = $this->control($this->s3->getCommand('DeleteObject', [
            'Bucket' => $this->target->bucket, 'Key' => $record->id->key(), 'VersionId' => $record->version->value,
            'ExpectedBucketOwner' => $this->target->account,
        ]));
        if ($result['VersionId'] !== $record->version->value || $result->hasKey('DeleteMarker')) {
            throw new ExportUnavailable('Export deletion unresolved.');
        }
    }

    private function validate(ResultInterface $result, ExportRecord $record, ObjectVersion $version): void
    {
        if ($result['VersionId'] !== $version->value || $result['ContentLength'] !== $record->bytes
            || $result['ChecksumSHA256'] !== $record->checksum || $result['ServerSideEncryption'] !== 'aws:kms'
            || $result['SSEKMSKeyId'] !== $this->target->kms || $result['BucketKeyEnabled'] !== false
            || $result['ContentType'] !== 'application/octet-stream' || $result['Metadata'] !== ['profile' => 'private-documents-v1']
            || $result->hasKey('StorageClass') || $result->hasKey('ArchiveStatus') || $result->hasKey('Restore')
            || $result->hasKey('Expiration') || $result->hasKey('ContentEncoding') || $result->hasKey('ContentRange')
            || $result->hasKey('DeleteMarker')) {
            throw new ExportUnavailable('Export retained facts unavailable.');
        }
    }

    private function countCall(): void
    {
        if ($this->calls >= 4) { throw new ExportUnavailable('Export operation budget exhausted.'); }
        $this->calls++;
    }

    private function control(CommandInterface $command): ResultInterface
    {
        // Bound XML and error responses too; Guzzle's default sink would spill to disk.
        $stream = @fopen('php://memory', 'w+b');
        if ($stream === false) { throw new ExportUnavailable('Export response buffer unavailable.'); }
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
