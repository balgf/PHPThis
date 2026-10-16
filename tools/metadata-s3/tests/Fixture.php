<?php

declare(strict_types=1);

namespace MetadataS3Recipe\Tests;

use Aws\CommandInterface;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use MetadataS3Recipe\DocumentClock;
use MetadataS3Recipe\DocumentId;
use MetadataS3Recipe\DocumentLock;
use MetadataS3Recipe\DocumentRecord;
use MetadataS3Recipe\DocumentOwner;
use MetadataS3Recipe\DocumentRoot;
use MetadataS3Recipe\Documents;
use MetadataS3Recipe\DocumentS3;
use MetadataS3Recipe\S3Target;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use Psr\Http\Message\StreamInterface;

function requireProof(bool $condition, string $name): void { if (!$condition) { throw new \RuntimeException('Metadata S3 proof: ' . $name); } }

final class FixtureClock implements DocumentClock
{
    public int $value;
    public function __construct() { $this->value = time(); }
    public function now(): int { return $this->value; }
}

/** Synthetic SDK operation results. Never connected to a service. */
final class SyntheticS3
{
    /** @var list<string> */
    public array $calls = [];
    public string $bytes = "sku,quantity\n";
    public string $key = '';
    public string $version = 'synthetic-version';
    public string $fault = '';
    public bool $present = false;
    public bool $deleteTakesEffect = true;
    public ?StreamInterface $lastBody = null;
    /** @var (\Closure(): void)|null */
    public ?\Closure $afterPut = null;
    /** @var (\Closure(): void)|null */
    public ?\Closure $beforeDelete = null;
    public function __construct(public readonly S3Target $target) {}

    public function client(): S3Client
    {
        return new S3Client(['version' => '2006-03-01', 'region' => $this->target->region,
            'credentials' => new Credentials('synthetic-key', 'synthetic-secret'), 'retries' => 0,
            'use_aws_shared_config_files' => false, 'ignore_configured_endpoint_urls' => true, 'handler' => $this->handle(...)]);
    }

    public function handle(CommandInterface $command): PromiseInterface
    {
        $name = $command->getName(); $this->calls[] = $name;
        requireProof($command['ExpectedBucketOwner'] === $this->target->account && $command['Bucket'] === $this->target->bucket, 'fixed ownership');
        if ($name === 'PutObject') {
            requireProof($command['IfNoneMatch'] === '*' && $command['BucketKeyEnabled'] === false
                && $command['StorageClass'] === 'STANDARD' && $command['SSEKMSKeyId'] === $this->target->kms, 'conditional encrypted create');
            if (in_array($this->fault, ['put412', 'put409', 'put403', 'put500'], true)) {
                $status = match ($this->fault) { 'put412' => 412, 'put409' => 409, 'put403' => 403, default => 500 };
                return Create::rejectionFor(new AwsException('deliberate secret exception', $command, ['response' => new Response($status)]));
            }
            $body = $command['Body']; $key = $command['Key'];
            if (!is_resource($body) || !is_string($key)) { throw new \LogicException('Synthetic Put shape.'); }
            $bytes = stream_get_contents($body);
            if (!is_string($bytes)) { throw new \LogicException('Synthetic Put bytes.'); }
            $this->bytes = $bytes; $this->key = $key; $this->present = true;
            requireProof($command['ContentLength'] === strlen($this->bytes) && $command['ChecksumSHA256'] === base64_encode(hash('sha256', $this->bytes, true)), 'put byte integrity');
            if ($this->afterPut !== null) { ($this->afterPut)(); }
            if ($this->fault === 'lost_put') { return Create::rejectionFor(new \RuntimeException('lost response')); }
            return Create::promiseFor(new Result(['VersionId' => $this->fault === 'null_version' ? 'null' : $this->version,
                'ChecksumSHA256' => base64_encode(hash('sha256', $this->bytes, true))]));
        }
        if ($name === 'HeadObject' || $name === 'GetObject') {
            requireProof($command['VersionId'] === $this->version && $command['ChecksumMode'] === 'ENABLED'
                && $command['Key'] === $this->key && !isset($command['ServerSideEncryption']), 'versioned retrieval');
            if ($this->fault === 'head_denied') { return Create::rejectionFor(new \RuntimeException('denied')); }
            $result = $this->metadata();
            if ($name === 'GetObject') {
                $this->lastBody = Utils::streamFor($this->fault === 'corrupt' ? str_repeat('x', strlen($this->bytes)) : $this->bytes);
                $result['Body'] = $this->lastBody;
            }
            return Create::promiseFor($result);
        }
        if ($name === 'ListObjectVersions') {
            requireProof($command['Prefix'] === $this->key && $command['MaxKeys'] === 2, 'exact bounded listing');
            $version = ['Key' => $this->fault === 'sibling' ? $this->key . '-other' : $this->key,
                'VersionId' => $this->fault === 'other_version' ? 'other' : $this->version, 'IsLatest' => true, 'Size' => strlen($this->bytes)];
            $versions = $this->present ? [$version] : [];
            if ($this->fault === 'multiple') { $versions[] = $version; }
            return Create::promiseFor(new Result(['IsTruncated' => $this->fault === 'truncated', 'Versions' => $versions,
                'DeleteMarkers' => $this->fault === 'marker' ? [$version] : []]));
        }
        if ($name === 'DeleteObject') {
            requireProof($command['VersionId'] === $this->version && $command['Key'] === $this->key, 'exact version deletion');
            if ($this->beforeDelete !== null) { ($this->beforeDelete)(); }
            if ($this->deleteTakesEffect) { $this->present = false; }
            if ($this->fault === 'lost_delete') { return Create::rejectionFor(new \RuntimeException('lost deletion')); }
            return Create::promiseFor(new Result(['VersionId' => $this->version]));
        }
        throw new \LogicException('Unexpected synthetic command.');
    }

    public function metadata(): ResultInterface
    {
        $result = new Result(['VersionId' => $this->version, 'ContentLength' => strlen($this->bytes),
            'ChecksumSHA256' => base64_encode(hash('sha256', $this->bytes, true)), 'ServerSideEncryption' => 'aws:kms',
            'SSEKMSKeyId' => $this->target->kms, 'BucketKeyEnabled' => false, 'ContentType' => 'application/octet-stream',
            'Metadata' => ['profile' => 'private-documents-v1']]);
        if ($this->fault === 'storage_class') { $result['StorageClass'] = 'STANDARD'; }
        if ($this->fault === 'wrong_kms') { $result['SSEKMSKeyId'] = 'wrong'; }
        if ($this->fault === 'wrong_metadata') { $result['Metadata'] = ['profile' => 'wrong']; }
        return $result;
    }
}

final readonly class Fixture
{
    public DocumentRoot $root;
    public Connection $database;
    public DocumentLock $lock;
    public FixtureClock $clock;
    public SyntheticS3 $remote;
    public DocumentOwner $owner;

    public function __construct()
    {
        $path = dirname(__DIR__, 3) . '/tmp/metadata-s3-proof-' . bin2hex(random_bytes(8));
        mkdir($path, 0700); touch($path . '/documents.sqlite'); chmod($path . '/documents.sqlite', 0600);
        touch($path . '/documents.lock'); chmod($path . '/documents.lock', 0600);
        $this->root = DocumentRoot::fromInput($path);
        $this->database = Connection::connect('sqlite:' . $path . '/documents.sqlite', new QueryBudget(100), new QueryTrace(32));
        $this->database->executeStatement("CREATE TABLE documents (
    document_id TEXT PRIMARY KEY NOT NULL CHECK (length(document_id) = 32),
    owner_id INTEGER NOT NULL CHECK (owner_id > 0),
    label TEXT CHECK (label IS NULL OR length(CAST(label AS BLOB)) BETWEEN 1 AND 120),
    note TEXT CHECK (note IS NULL OR length(CAST(note AS BLOB)) <= 1024),
    size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 65536),
    sha256 TEXT NOT NULL CHECK (length(sha256) = 64),
    state TEXT NOT NULL CHECK (state IN ('pending','stored','accepted','failed','reconciliation_required','deletion_pending','deleted')),
    version_id TEXT CHECK (version_id IS NULL OR length(CAST(version_id AS BLOB)) BETWEEN 1 AND 1024),
    delete_attempts INTEGER NOT NULL DEFAULT 0 CHECK (delete_attempts BETWEEN 0 AND 2),
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400),
    target TEXT NOT NULL CHECK (length(target) = 64),
    reason TEXT NOT NULL DEFAULT 'reserved',
    CHECK (CASE WHEN state IN ('failed','deleted') THEN label IS NULL AND note IS NULL ELSE label IS NOT NULL END)
) STRICT;");
        $this->database->executeStatement("CREATE TABLE document_packets(
    packet_id TEXT PRIMARY KEY NOT NULL CHECK (length(packet_id) = 32),
    owner_id INTEGER NOT NULL CHECK (owner_id > 0),
    request_id TEXT NOT NULL CHECK (length(request_id) = 32),
    request_fingerprint TEXT NOT NULL CHECK (length(request_fingerprint) = 64),
    label TEXT CHECK (label IS NULL OR length(CAST(label AS BLOB)) BETWEEN 1 AND 120),
    note TEXT CHECK (note IS NULL OR length(CAST(note AS BLOB)) <= 1024),
    primary_bytes INTEGER NOT NULL CHECK (primary_bytes BETWEEN 1 AND 65536),
    primary_sha256 TEXT NOT NULL CHECK (length(primary_sha256) = 64),
    primary_nonce TEXT NOT NULL CHECK (length(primary_nonce) = 32),
    primary_state TEXT NOT NULL DEFAULT 'ready' CHECK (primary_state IN ('ready','write_started','stored','verified','collision','deletion_pending','deleted')),
    primary_version TEXT CHECK (primary_version IS NULL OR length(CAST(primary_version AS BLOB)) BETWEEN 1 AND 1024),
    primary_delete_attempts INTEGER NOT NULL DEFAULT 0 CHECK (primary_delete_attempts BETWEEN 0 AND 2),
    supporting_bytes INTEGER NOT NULL CHECK (supporting_bytes BETWEEN 1 AND 65536),
    supporting_sha256 TEXT NOT NULL CHECK (length(supporting_sha256) = 64),
    supporting_nonce TEXT NOT NULL CHECK (length(supporting_nonce) = 32),
    supporting_state TEXT NOT NULL DEFAULT 'ready' CHECK (supporting_state IN ('ready','write_started','stored','verified','collision','deletion_pending','deleted')),
    supporting_version TEXT CHECK (supporting_version IS NULL OR length(CAST(supporting_version AS BLOB)) BETWEEN 1 AND 1024),
    supporting_delete_attempts INTEGER NOT NULL DEFAULT 0 CHECK (supporting_delete_attempts BETWEEN 0 AND 2),
    state TEXT NOT NULL CHECK (state IN ('pending','reconciliation_required','accepted','aborting','deletion_pending','failed','deleted')),
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400),
    target TEXT NOT NULL CHECK (length(target) = 64),
    UNIQUE (owner_id, request_id),
    CHECK (state <> 'accepted' OR (primary_state = 'verified' AND supporting_state = 'verified'
        AND primary_version IS NOT NULL AND supporting_version IS NOT NULL)),
    CHECK (state NOT IN ('failed','deleted') OR (primary_state IN ('ready','collision','deleted') AND supporting_state IN ('ready','collision','deleted'))),
    CHECK (CASE WHEN state IN ('failed','deleted') THEN label IS NULL AND note IS NULL ELSE label IS NOT NULL END)
) STRICT; ");
        $this->lock = new DocumentLock($this->root); $this->lock->acquire(true);
        $this->clock = new FixtureClock(); $this->owner = new DocumentOwner(1);
        $this->remote = new SyntheticS3(S3Target::fromInputs('fixture-bucket', 'us-east-1', '000000000000', 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001'));
    }
    public function operation(?QueryBudget $budget = null): Documents
    {
        return new Documents(Connection::connect('sqlite:' . $this->root->path . '/documents.sqlite', $budget ?? new QueryBudget(16), new QueryTrace(16)),
            $this->lock, new DocumentS3($this->remote->client(), $this->remote->target), $this->clock);
    }
    public function seed(string $state = 'pending'): DocumentId
    {
        $id = new DocumentId(str_repeat('a', 32));
        $this->remote->bytes = 'opaque private-sentinel'; $this->remote->key = $id->key(); $this->remote->present = true;
        $this->database->executeStatement(
            'INSERT INTO documents (document_id, owner_id, label, note, size_bytes, sha256, state, version_id, created_at, expires_at, target)
             VALUES (:id, :owner, :label, :note, :bytes, :digest, :state, :version, :created, :expires, :target)',
            ['id' => $id->value, 'owner' => $this->owner->id, 'label' => $state === 'failed' ? null : 'private-sentinel label',
                'note' => $state === 'failed' ? null : 'private-sentinel note', 'bytes' => strlen($this->remote->bytes),
                'digest' => hash('sha256', $this->remote->bytes), 'state' => $state,
                'version' => in_array($state, ['accepted', 'stored', 'deletion_pending'], true) ? $this->remote->version : null,
                'created' => $this->clock->value, 'expires' => $this->clock->value + 86400, 'target' => $this->remote->target->fingerprint()],
        );
        return $id;
    }
    public function record(): DocumentRecord
    {
        $row = $this->database->selectOneRow('SELECT document_id, owner_id, label, note, size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target FROM documents ORDER BY created_at LIMIT 1');
        if ($row === null) { throw new \LogicException('Synthetic record missing.'); }
        return DocumentRecord::fromDatabaseRow($row);
    }
    public function cleanup(): void
    {
        $this->lock->release();
        unlink($this->root->path . '/documents.sqlite'); unlink($this->root->path . '/documents.lock'); rmdir($this->root->path);
    }
}
