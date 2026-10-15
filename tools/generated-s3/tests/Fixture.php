<?php

declare(strict_types=1);

namespace GeneratedS3Recipe\Tests;

use Aws\CommandInterface;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use GeneratedS3Recipe\ExportClock;
use GeneratedS3Recipe\ExportId;
use GeneratedS3Recipe\ExportLock;
use GeneratedS3Recipe\ExportRecord;
use GeneratedS3Recipe\ExportRoot;
use GeneratedS3Recipe\InventoryExports;
use GeneratedS3Recipe\InventoryS3;
use GeneratedS3Recipe\S3Target;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use Psr\Http\Message\StreamInterface;

function requireProof(bool $condition, string $name): void { if (!$condition) { throw new \RuntimeException('Generated S3 proof: ' . $name); } }

final class FixtureClock implements ExportClock
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
    public ExportRoot $root;
    public Connection $database;
    public ExportLock $lock;
    public FixtureClock $clock;
    public SyntheticS3 $remote;

    public function __construct()
    {
        $path = dirname(__DIR__, 3) . '/tmp/generated-s3-proof-' . bin2hex(random_bytes(8));
        mkdir($path, 0700); touch($path . '/inventory.sqlite'); chmod($path . '/inventory.sqlite', 0600);
        touch($path . '/exports.lock'); chmod($path . '/exports.lock', 0600);
        $this->root = ExportRoot::fromInput($path);
        $this->database = Connection::connect('sqlite:' . $path . '/inventory.sqlite', new QueryBudget(100), new QueryTrace(32));
        $this->database->executeStatement('CREATE TABLE inventory (sku TEXT PRIMARY KEY NOT NULL, quantity INTEGER NOT NULL CHECK (quantity BETWEEN 0 AND 2147483647)) STRICT');
        $this->database->executeStatement("CREATE TABLE exports (
            export_id TEXT PRIMARY KEY NOT NULL, size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 65536), sha256 TEXT NOT NULL,
            state TEXT NOT NULL CHECK (state IN ('pending','stored','accepted','failed','reconciliation_required','deletion_pending','deleted')),
            version_id TEXT, delete_attempts INTEGER NOT NULL CHECK (delete_attempts BETWEEN 0 AND 2),
            created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), target TEXT NOT NULL, reason TEXT NOT NULL) STRICT");
        $this->lock = new ExportLock($this->root); $this->lock->acquire(true);
        $this->clock = new FixtureClock();
        $this->remote = new SyntheticS3(S3Target::fromInputs('fixture-bucket', 'us-east-1', '000000000000', 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001'));
    }
    public function operation(?QueryBudget $budget = null): InventoryExports
    {
        return new InventoryExports(Connection::connect('sqlite:' . $this->root->path . '/inventory.sqlite', $budget ?? new QueryBudget(16), new QueryTrace(16)),
            $this->lock, new InventoryS3($this->remote->client(), $this->remote->target), $this->clock);
    }
    public function record(): ExportRecord
    {
        $row = $this->database->selectOneRow('SELECT export_id, size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target FROM exports ORDER BY created_at LIMIT 1');
        if ($row === null) { throw new \LogicException('Synthetic record missing.'); }
        return ExportRecord::fromDatabaseRow($row);
    }
    public function cleanup(): void
    {
        $this->lock->release();
        unlink($this->root->path . '/inventory.sqlite'); unlink($this->root->path . '/exports.lock'); rmdir($this->root->path);
    }
}
