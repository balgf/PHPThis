<?php

declare(strict_types=1);

namespace MetadataS3Recipe\Tests;

use Aws\CommandInterface;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use MetadataS3Recipe\DocumentOwner;
use MetadataS3Recipe\PacketId;
use MetadataS3Recipe\PacketObject;
use MetadataS3Recipe\PacketRecord;
use MetadataS3Recipe\Packets;
use MetadataS3Recipe\PacketS3;
use MetadataS3Recipe\S3Target;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

/** A synthetic provider with independent, inspectable object state; never uses a network. */
final class PacketPeer
{
    /** @var array<string, array{bytes: string, version: string, metadata: array<string, string>}> */
    public array $objects = [];
    /** @var list<string> */
    public array $calls = [];
    public string $fault = '';
    /** @var (\Closure(CommandInterface): void)|null */
    public ?\Closure $before = null;
    /** @var (\Closure(): void)|null */
    public ?\Closure $save = null;
    public function __construct(public readonly S3Target $target) {}

    public function client(): S3Client
    {
        return new S3Client(['version' => '2006-03-01', 'region' => $this->target->region,
            'credentials' => new Credentials('synthetic-key', 'synthetic-secret'), 'retries' => 0,
            'use_aws_shared_config_files' => false, 'ignore_configured_endpoint_urls' => true, 'handler' => $this->handle(...)]);
    }

    public function retain(PacketId $id, PacketObject $object, string $bytes): void
    {
        $this->objects[$id->key($object->role)] = ['bytes' => $bytes, 'version' => 'version-' . $object->role->value, 'metadata' => $object->metadata($id)];
    }

    public function handle(CommandInterface $command): PromiseInterface
    {
        $name = $command->getName(); $key = $name === 'ListObjectVersions' ? $command['Prefix'] : $command['Key'];
        if (!is_string($key) || preg_match('~\Aprivate-documents/v1/packets/([0-9a-f]{32})/(primary|supporting)\z~', $key, $matches) !== 1) {
            throw new \LogicException('Packet peer key shape.');
        }
        $role = $matches[2];
        $this->calls[] = $name . ':' . $role;
        requireProof($command['Bucket'] === $this->target->bucket && $command['ExpectedBucketOwner'] === $this->target->account, 'packet target authority');
        if ($this->before !== null) { ($this->before)($command); }
        $fault = str_starts_with($this->fault, $role . ':') ? substr($this->fault, strlen($role) + 1) : '';
        if ($name === 'PutObject') {
            requireProof($command['IfNoneMatch'] === '*' && $command['StorageClass'] === 'STANDARD' && $command['BucketKeyEnabled'] === false
                && $command['ServerSideEncryption'] === 'aws:kms' && $command['SSEKMSKeyId'] === $this->target->kms
                && $command['ContentType'] === 'application/octet-stream', 'packet conditional encryption');
            $metadata = $command['Metadata']; $body = $command['Body'];
            if (!is_resource($body) || !is_array($metadata) || count($metadata) !== 4
                || ($metadata['profile'] ?? null) !== 'private-document-packet-v1' || ($metadata['operation'] ?? null) !== $matches[1]
                || ($metadata['role'] ?? null) !== $role || !is_string($metadata['ownership'] ?? null)
                || preg_match('/\A[0-9a-f]{32}\z/', $metadata['ownership']) !== 1) { throw new \LogicException('Packet peer Put shape.'); }
            $bytes = stream_get_contents($body);
            if (!is_string($bytes) || $command['ContentLength'] !== strlen($bytes)
                || $command['ChecksumSHA256'] !== base64_encode(hash('sha256', $bytes, true))) { throw new \LogicException('Packet peer bytes.'); }
            if (isset($this->objects[$key]) || $fault === 'put412') {
                // A deliberately foreign object, even though its bytes happen to match.
                if (!isset($this->objects[$key])) {
                    $this->objects[$key] = ['bytes' => $bytes, 'version' => 'foreign-version',
                        'metadata' => ['profile' => $metadata['profile'], 'operation' => $metadata['operation'], 'role' => $role, 'ownership' => str_repeat('f', 32)]];
                }
                $this->persist();
                return Create::rejectionFor(new AwsException('synthetic-secret collision', $command, ['response' => new Response(412)]));
            }
            if (in_array($fault, ['put400', 'put403', 'put409', 'put500', 'put_timeout'], true)) {
                $status = match ($fault) { 'put400' => 400, 'put403' => 403, 'put409' => 409, default => 500 };
                return Create::rejectionFor(new AwsException('synthetic-secret write', $command, ['response' => new Response($status)]));
            }
            $version = 'version-' . $role;
            $this->objects[$key] = ['bytes' => $bytes, 'version' => $version, 'metadata' => [
                'profile' => $metadata['profile'], 'operation' => $metadata['operation'], 'role' => $role, 'ownership' => $metadata['ownership']]];
            $this->persist();
            if ($fault === 'lost_put') { return Create::rejectionFor(new \RuntimeException('synthetic-secret lost Put')); }
            return Create::promiseFor(new Result(['VersionId' => $fault === 'null_version' ? 'null' : $version,
                'ChecksumSHA256' => $fault === 'checksum' ? 'wrong' : base64_encode(hash('sha256', $bytes, true))]));
        }
        if ($name === 'ListObjectVersions') {
            requireProof($command['MaxKeys'] === 2, 'packet finite exact listing');
            if ($fault === 'list_denied') { return Create::rejectionFor(new \RuntimeException('synthetic-secret list')); }
            $object = $this->objects[$key] ?? null;
            $versions = $object === null ? [] : [['Key' => $fault === 'sibling' ? $key . '-foreign' : $key,
                'VersionId' => $fault === 'other_version' ? 'foreign-version' : $object['version'],
                'IsLatest' => $fault !== 'not_latest', 'Size' => (string) strlen($object['bytes'])]];
            if ($fault === 'multiple' && $versions !== []) { $versions[] = $versions[0]; }
            $result = ['Versions' => $versions, 'DeleteMarkers' => $fault === 'marker' ? [['Key' => $key]] : [], 'IsTruncated' => $fault === 'truncated'];
            if ($fault === 'continuation') { $result['NextKeyMarker'] = $key; }
            return Create::promiseFor(new Result($result));
        }
        $object = $this->objects[$key] ?? null;
        if ($name === 'HeadObject') {
            if ($object === null || $fault === 'head_denied') { return Create::rejectionFor(new \RuntimeException('synthetic-secret head')); }
            requireProof($command['VersionId'] === $object['version'] && $command['ChecksumMode'] === 'ENABLED'
                && !isset($command['ServerSideEncryption']), 'packet exact-version Head');
            $metadata = array_reverse($object['metadata'], true); // Header map order is not semantic.
            if ($fault === 'wrong_nonce') { $metadata['ownership'] = str_repeat('e', 32); }
            if ($fault === 'wrong_role') { $metadata['role'] = $role === 'primary' ? 'supporting' : 'primary'; }
            if ($fault === 'wrong_packet') { $metadata['operation'] = str_repeat('e', 32); }
            if ($fault === 'extra_metadata') { $metadata['label'] = 'synthetic-secret'; }
            $result = new Result(['VersionId' => $object['version'], 'ContentLength' => strlen($object['bytes']),
                'ChecksumSHA256' => base64_encode(hash('sha256', $object['bytes'], true)), 'ServerSideEncryption' => 'aws:kms',
                'SSEKMSKeyId' => $fault === 'wrong_kms' ? 'wrong' : $this->target->kms, 'BucketKeyEnabled' => false,
                'ContentType' => 'application/octet-stream', 'Metadata' => $metadata]);
            if ($fault === 'storage_class') { $result['StorageClass'] = 'STANDARD'; }
            return Create::promiseFor($result);
        }
        if ($name === 'DeleteObject') {
            requireProof($object !== null && $command['VersionId'] === $object['version'], 'packet owned exact-version deletion');
            if ($fault !== 'retained_delete') { unset($this->objects[$key]); }
            $this->persist();
            if ($fault === 'lost_delete') { return Create::rejectionFor(new \RuntimeException('synthetic-secret lost Delete')); }
            return Create::promiseFor(new Result(['VersionId' => $command['VersionId']]));
        }
        throw new \LogicException('Unexpected packet peer command.');
    }

    public function persist(): void { if ($this->save !== null) { ($this->save)(); } }
    public function retainedVersion(string $key): ?string { return $this->objects[$key]['version'] ?? null; }

    /** Only synthetic test data is stored in this private fixture. */
    public function export(): string
    {
        $rows = [];
        foreach ($this->objects as $key => $object) {
            $rows[$key] = ['bytes' => base64_encode($object['bytes']), 'version' => $object['version'], 'metadata' => $object['metadata']];
        }
        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    public function restore(string $json): void
    {
        if (strlen($json) > 4_194_304) { throw new \LogicException('Oversized synthetic packet store.'); }
        $rows = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || count($rows) > 32) { throw new \LogicException('Invalid synthetic packet store.'); }
        foreach ($rows as $key => $row) {
            if (!is_string($key) || !is_array($row) || !is_string($row['bytes'] ?? null) || !is_string($row['version'] ?? null)
                || !is_array($row['metadata'] ?? null)) { throw new \LogicException('Invalid synthetic packet object.'); }
            $bytes = base64_decode($row['bytes'], true); $metadata = [];
            if ($bytes === false) { throw new \LogicException('Invalid synthetic packet bytes.'); }
            foreach ($row['metadata'] as $name => $value) {
                if (!is_string($name) || !is_string($value)) { throw new \LogicException('Invalid synthetic metadata.'); }
                $metadata[$name] = $value;
            }
            $this->objects[$key] = ['bytes' => $bytes, 'version' => $row['version'], 'metadata' => $metadata];
        }
    }
}

final readonly class PacketFixture
{
    public Fixture $base;
    public PacketPeer $peer;
    public function __construct()
    {
        $this->base = new Fixture();
        $this->peer = new PacketPeer($this->base->remote->target);
    }
    public function operation(int $calls = 4, bool $foreignKeys = false): Packets
    {
        $database = Connection::connect('sqlite:' . $this->base->root->path . '/documents.sqlite', new QueryBudget(32), new QueryTrace(32));
        if ($foreignKeys) { $database->executeStatement('PRAGMA foreign_keys = ON'); }
        return new Packets($database,
            $this->base->lock, new PacketS3($this->peer->client(), $this->peer->target, $calls), $this->base->clock);
    }
    public function seed(string $state = 'pending', string $primary = 'write_started', string $supporting = 'write_started'): PacketId
    {
        $id = new PacketId(str_repeat('a', 32));
        $this->base->database->executeStatement(
            'INSERT INTO document_packets (packet_id, owner_id, request_id, request_fingerprint, label, note,
                primary_bytes, primary_sha256, primary_nonce, primary_state, primary_version,
                supporting_bytes, supporting_sha256, supporting_nonce, supporting_state, supporting_version,
                state, created_at, expires_at, target)
             VALUES (:id, :owner, :retry, :fingerprint, :label, :note, :pbytes, :pdigest, :pnonce, :pstate, :pversion,
                :sbytes, :sdigest, :snonce, :sstate, :sversion, :state, :created, :expires, :target)',
            ['id' => $id->value, 'owner' => 1, 'retry' => str_repeat('b', 32), 'fingerprint' => str_repeat('c', 64),
                'label' => 'private-sentinel label', 'note' => 'private-sentinel note', 'pbytes' => 7, 'pdigest' => hash('sha256', 'primary'),
                'pnonce' => str_repeat('d', 32), 'pstate' => $primary,
                'pversion' => in_array($primary, ['stored', 'verified'], true) ? 'version-primary' : null,
                'sbytes' => 10, 'sdigest' => hash('sha256', 'supporting'), 'snonce' => str_repeat('e', 32), 'sstate' => $supporting,
                'sversion' => in_array($supporting, ['stored', 'verified'], true) ? 'version-supporting' : null,
                'state' => $state, 'created' => $this->base->clock->value, 'expires' => $this->base->clock->value + 86400,
                'target' => $this->peer->target->fingerprint()],
        );
        $record = $this->record();
        if (!in_array($primary, ['ready', 'collision'], true)) { $this->peer->retain($id, $record->primary, 'primary'); }
        if (!in_array($supporting, ['ready', 'collision'], true)) { $this->peer->retain($id, $record->supporting, 'supporting'); }
        return $id;
    }
    public function record(): PacketRecord
    {
        $row = $this->base->database->selectOneRow('SELECT packet_id, owner_id, request_id, request_fingerprint, label, note, state,
            created_at, expires_at, target, primary_bytes, primary_sha256, primary_nonce, primary_state, primary_version, primary_delete_attempts,
            supporting_bytes, supporting_sha256, supporting_nonce, supporting_state, supporting_version, supporting_delete_attempts
            FROM document_packets WHERE owner_id = :owner AND packet_id = :id', ['owner' => 1, 'id' => str_repeat('a', 32)]);
        if ($row === null) { throw new \LogicException('Packet fixture missing.'); }
        return PacketRecord::fromDatabaseRow($row);
    }
    public function cleanup(): void { $this->base->cleanup(); }
}
