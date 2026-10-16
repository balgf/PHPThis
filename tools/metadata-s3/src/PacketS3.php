<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\DroppingStream;
use GuzzleHttp\Psr7\Utils;

/** Exact two-role packet operations; no backend selection or implicit retry. */
final class PacketS3
{
    private int $calls = 0;
    public function __construct(private readonly S3Client $client, public readonly S3Target $target, private readonly int $maximumCalls = 4)
    {
        if (!in_array($maximumCalls, [4, 8], true)) { throw new \InvalidArgumentException('Invalid packet call budget.'); }
    }
    public function calls(): int { return $this->calls; }

    public function create(PacketId $id, PacketObject $object, VerifiedUpload $upload): ObjectVersion
    {
        if ($object->bytes !== $upload->bytes || !hash_equals($object->sha256, $upload->sha256)) {
            throw new DocumentUnavailable('Packet upload changed.');
        }
        try {
            $result = $this->control($this->client->getCommand('PutObject', [
                'Bucket' => $this->target->bucket, 'Key' => $id->key($object->role), 'Body' => $upload->handle(),
                'ContentLength' => $object->bytes, 'ContentType' => 'application/octet-stream', 'StorageClass' => 'STANDARD',
                'ChecksumAlgorithm' => 'SHA256', 'ChecksumSHA256' => $object->checksum, 'IfNoneMatch' => '*',
                'ServerSideEncryption' => 'aws:kms', 'SSEKMSKeyId' => $this->target->kms, 'BucketKeyEnabled' => false,
                'ExpectedBucketOwner' => $this->target->account, 'Metadata' => $object->metadata($id),
            ]));
        } catch (AwsException $failure) {
            if ($failure->getStatusCode() === 412) { throw new DocumentCollision('Packet role collision.'); }
            throw new DocumentUnavailable('Packet write unresolved.');
        }
        if ($result['ChecksumSHA256'] !== $object->checksum) { throw new DocumentUnavailable('Packet checksum unresolved.'); }
        return ObjectVersion::fromInput($result['VersionId']);
    }

    public function head(PacketId $id, PacketObject $object, ObjectVersion $version): void
    {
        $result = $this->control($this->client->getCommand('HeadObject', [
            'Bucket' => $this->target->bucket, 'Key' => $id->key($object->role), 'VersionId' => $version->value,
            'ChecksumMode' => 'ENABLED', 'ExpectedBucketOwner' => $this->target->account,
        ]));
        $metadata = $result['Metadata'];
        if (!is_array($metadata) || count($metadata) !== 4 || ($metadata['profile'] ?? null) !== 'private-document-packet-v1'
            || ($metadata['operation'] ?? null) !== $id->value || ($metadata['role'] ?? null) !== $object->role->value
            || ($metadata['ownership'] ?? null) !== $object->nonce || $result['VersionId'] !== $version->value
            || $result['ContentLength'] !== $object->bytes || $result['ChecksumSHA256'] !== $object->checksum
            || $result['ServerSideEncryption'] !== 'aws:kms' || $result['SSEKMSKeyId'] !== $this->target->kms
            || $result['BucketKeyEnabled'] !== false || $result['ContentType'] !== 'application/octet-stream'
            || $result->hasKey('StorageClass') || $result->hasKey('ArchiveStatus') || $result->hasKey('Restore')
            || $result->hasKey('Expiration') || $result->hasKey('ContentEncoding') || $result->hasKey('ContentRange')
            || $result->hasKey('DeleteMarker')) { throw new DocumentUnavailable('Packet retained ownership unavailable.'); }
    }

    public function version(PacketId $id, PacketObject $object): ?ObjectVersion
    {
        $result = $this->control($this->client->getCommand('ListObjectVersions', [
            'Bucket' => $this->target->bucket, 'Prefix' => $id->key($object->role), 'MaxKeys' => 2,
            'ExpectedBucketOwner' => $this->target->account,
        ]));
        $versions = $result['Versions'] ?? [];
        if ($result['IsTruncated'] !== false || $result->hasKey('NextKeyMarker') || $result->hasKey('NextVersionIdMarker')
            || ($result['DeleteMarkers'] ?? []) !== [] || !is_array($versions) || !array_is_list($versions) || count($versions) > 1) {
            throw new DocumentUnavailable('Packet listing unresolved.');
        }
        if ($versions === []) { return null; }
        $candidate = $versions[0];
        if (!is_array($candidate) || ($candidate['Key'] ?? null) !== $id->key($object->role)
            || ($candidate['IsLatest'] ?? null) !== true
            || (($candidate['Size'] ?? null) !== $object->bytes && ($candidate['Size'] ?? null) !== (string) $object->bytes)) {
            throw new DocumentUnavailable('Packet version ownership unavailable.');
        }
        $version = ObjectVersion::fromInput($candidate['VersionId'] ?? null);
        if ($object->version !== null && $object->version->value !== $version->value) { throw new DocumentUnavailable('Packet version changed.'); }
        return $version;
    }

    public function delete(PacketId $id, PacketObject $object): void
    {
        if ($object->version === null) { throw new DocumentUnavailable('Packet deletion version unavailable.'); }
        $result = $this->control($this->client->getCommand('DeleteObject', [
            'Bucket' => $this->target->bucket, 'Key' => $id->key($object->role), 'VersionId' => $object->version->value,
            'ExpectedBucketOwner' => $this->target->account,
        ]));
        if ($result['VersionId'] !== $object->version->value || ($result->hasKey('DeleteMarker') && $result['DeleteMarker'] !== false)) {
            throw new DocumentUnavailable('Packet deletion unresolved.');
        }
    }

    private function control(CommandInterface $command): ResultInterface
    {
        if ($this->calls >= $this->maximumCalls) { throw new DocumentUnavailable('Packet operation budget exhausted.'); }
        $stream = @fopen('php://memory', 'w+b');
        if ($stream === false) { throw new DocumentUnavailable('Packet response buffer unavailable.'); }
        $sink = new DroppingStream(Utils::streamFor($stream), 16384);
        try {
            $command['@http'] = ['sink' => $sink];
            $this->calls++;
            return $this->client->execute($command);
        } finally {
            $sink->close();
            if (is_resource($stream)) { fclose($stream); }
        }
    }
}
