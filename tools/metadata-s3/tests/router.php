<?php

declare(strict_types=1);

use MetadataS3Recipe\AuthenticationRequired;
use MetadataS3Recipe\DocumentId;
use MetadataS3Recipe\DocumentInput;
use MetadataS3Recipe\DocumentLock;
use MetadataS3Recipe\DocumentOwner;
use MetadataS3Recipe\DocumentReceiver;
use MetadataS3Recipe\DocumentRoot;
use MetadataS3Recipe\Documents;
use MetadataS3Recipe\DocumentS3;
use MetadataS3Recipe\DocumentUploadPolicy;
use MetadataS3Recipe\LockedDocumentReceiver;
use MetadataS3Recipe\S3Target;
use MetadataS3Recipe\SystemDocumentClock;
use MetadataS3Recipe\Tests\SyntheticS3;
use MetadataS3Recipe\UploadDenied;
use App\Observability\RequestSummary;
use App\Observability\RequestSummarySink;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\Request;
use PHPThis\Http\ResponseEmitter;
use function MetadataS3Recipe\documentCoordinator;

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/Fixture.php';
require __DIR__ . '/PacketFixture.php';
require __DIR__ . '/packet-loopback.php';

// Synthetic policy/fault inputs are private test files, never a deployed credential verifier.
final readonly class SyntheticUploadPolicy implements DocumentUploadPolicy, MetadataS3Recipe\PacketUploadPolicy
{
    public function authorize(Request $request): DocumentOwner
    {
        $header = $request->headers['authorization'] ?? null;
        if ($header === null) { throw new AuthenticationRequired(); }
        if ($header === 'Bearer synthetic-outage') { throw new RuntimeException('Synthetic secret verifier failure.'); }
        if (!in_array($header, ['Bearer synthetic-owner-one', 'Bearer synthetic-owner-two'], true)) { throw new UploadDenied(); }
        return new DocumentOwner($header === 'Bearer synthetic-owner-one' ? 1 : 2);
    }
}

final readonly class PacketFaultReceiver implements MetadataS3Recipe\PacketReceiver
{
    public function __construct(private MetadataS3Recipe\PacketReceiver $receiver, private string $fault) {}
    public function receive(MetadataS3Recipe\PacketInput $input, DocumentOwner $owner): MetadataS3Recipe\PacketReceipt
    {
        if (in_array($this->fault, ['primary:bad_path', 'supporting:bad_path', 'primary:bad_size', 'supporting:bad_size'], true)) {
            $files = [];
            foreach (['primary' => $input->primary, 'supporting' => $input->supporting] as $role => $upload) {
                $files[$role] = ['name' => $upload->untrustedClientFilename, 'type' => $upload->untrustedClientMediaType,
                    'tmp_name' => $this->fault === $role . ':bad_path' ? __FILE__ : $upload->temporaryPath,
                    'size' => $upload->reportedSizeBytes + ($this->fault === $role . ':bad_size' ? 1 : 0), 'error' => $upload->error->value];
            }
            $fields = ['label' => $input->metadata->label, 'request_id' => $input->requestId];
            if ($input->metadata->note !== null) { $fields['note'] = $input->metadata->note; }
            $input = MetadataS3Recipe\PacketInput::fromMultipart(MultipartForm::fromPhp($fields, $files, 196608,
                new MultipartLimits(196608, 65536, 131072, 1024, 1195)));
        }
        return $this->receiver->receive($input, $owner);
    }
}

final readonly class FaultReceiver implements DocumentReceiver
{
    public function __construct(private DocumentReceiver $receiver, private string $fault) {}
    public function receive(DocumentInput $input, DocumentOwner $owner): DocumentId
    {
        if (in_array($this->fault, ['bad_path', 'bad_size'], true)) {
            $upload = $input->upload;
            $fields = $input->metadata->note === null ? ['label' => $input->metadata->label]
                : ['label' => $input->metadata->label, 'note' => $input->metadata->note];
            $input = DocumentInput::fromMultipart(MultipartForm::fromPhp($fields, ['document' => [
                'name' => $upload->untrustedClientFilename, 'type' => $upload->untrustedClientMediaType,
                'tmp_name' => $this->fault === 'bad_path' ? __FILE__ : $upload->temporaryPath,
                'size' => $upload->reportedSizeBytes + ($this->fault === 'bad_size' ? 1 : 0), 'error' => $upload->error->value,
            ]], 131072, new MultipartLimits(131072, 65536, 65536, 1024, 1153)));
        }
        return $this->receiver->receive($input, $owner);
    }
}

final class CapturingSummarySink implements RequestSummarySink
{
    public int $attempts = 0;
    public string $json = '';
    public function __construct(private readonly bool $fail) {}
    public function emit(RequestSummary $summary): void
    {
        $this->attempts++;
        $this->json = json_encode($summary->toArray(), JSON_THROW_ON_ERROR);
        if ($this->fail) { throw new RuntimeException('Synthetic sink secret'); }
    }
}

$root = getcwd();
if (!is_string($root)) { throw new RuntimeException('Test directory unavailable.'); }
$rawPlan = file_get_contents($root . '/plan.json');
if (!is_string($rawPlan)) { throw new RuntimeException('Test plan unavailable.'); }
$plan = json_decode($rawPlan, true, 4, JSON_THROW_ON_ERROR);
if (!is_array($plan) || !is_string($plan['fault'] ?? null)) { throw new RuntimeException('Invalid test plan.'); }
$remote = new SyntheticS3(S3Target::fromInputs('fixture-bucket', 'us-east-1', '000000000000',
    'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001'));
$remote->fault = $plan['fault'];
$trace = new QueryTrace(16);
$database = Connection::connect('sqlite:' . $root . '/documents.sqlite', ($budget = new QueryBudget(16)), $trace);
$pendingSeen = false;
$client = new Aws\S3\S3Client([
    'version' => '2006-03-01', 'region' => $remote->target->region,
    'credentials' => new Aws\Credentials\Credentials('synthetic-key', 'synthetic-secret'),
    'retries' => 0, 'use_aws_shared_config_files' => false, 'ignore_configured_endpoint_urls' => true,
    'handler' => static function (Aws\CommandInterface $command) use ($remote, $database, &$pendingSeen): GuzzleHttp\Promise\PromiseInterface {
        if ($command->getName() === 'PutObject') {
            $key = $command['Key']; $body = $command['Body'];
            if ($command['Metadata'] !== ['profile' => 'private-documents-v1']
                || $command['ContentType'] !== 'application/octet-stream' || !is_resource($body) || ftell($body) !== 0
                || !is_string($key) || preg_match('#\Aprivate-documents/v1/([0-9a-f]{32})/content\z#', $key, $match) !== 1) {
                throw new LogicException('Synthetic request shape changed.');
            }
            $streamMetadata = stream_get_meta_data($body);
            if (!is_string($streamMetadata['uri'] ?? null) || !is_uploaded_file($streamMetadata['uri'])) {
                throw new LogicException('Synthetic SDK did not receive the verified upload handle.');
            }
            $pending = $database->selectOneRow('SELECT state, size_bytes, sha256 FROM documents WHERE document_id = :id', ['id' => $match[1]]);
            if ($pending === null || !is_string($pending['sha256'])) { throw new LogicException('Synthetic ownership missing.'); }
            $rawDigest = hex2bin($pending['sha256']);
            if ($rawDigest === false || $pending['state'] !== 'pending' || $pending['size_bytes'] !== $command['ContentLength']
                || base64_encode($rawDigest) !== $command['ChecksumSHA256']) {
                throw new LogicException('Synthetic write lacks durable exact ownership.');
            }
            $pendingSeen = true;
        }
        return $remote->handle($command);
    },
]);

$lock = new DocumentLock(DocumentRoot::fromInput($root));
$documents = new Documents($database, $lock, new DocumentS3($client, $remote->target), new SystemDocumentClock());
$receiver = new FaultReceiver(new LockedDocumentReceiver($documents, $lock), $plan['fault']);
$sink = new CapturingSummarySink($plan['fault'] === 'sink_failure');
$packetBudget = new QueryBudget(32); $packetTrace = new QueryTrace(32);
$packetPeer = new MetadataS3Recipe\Tests\PacketPeer($remote->target);
$packetPeer->fault = $plan['fault'];
if (is_file($root . '/packet-remote.json')) {
    $serialized = file_get_contents($root . '/packet-remote.json');
    if (!is_string($serialized)) { throw new LogicException('Packet fixture unavailable.'); }
    $packetPeer->restore($serialized);
}
$packetPeer->save = static function () use ($packetPeer, $root): void {
    file_put_contents($root . '/packet-remote.json', $packetPeer->export()); chmod($root . '/packet-remote.json', 0600);
};
$observations = Connection::connect('sqlite:' . $root . '/documents.sqlite', new QueryBudget(32), new QueryTrace(32));
$packetSeen = false;
$packetPeer->before = static function (Aws\CommandInterface $command) use ($observations, &$packetSeen, $plan): void {
    $key = $command['Key'];
    if (!is_string($key) || preg_match('~\Aprivate-documents/v1/packets/([0-9a-f]{32})/(primary|supporting)\z~', $key, $matches) !== 1) {
        throw new LogicException('Packet test key unavailable.');
    }
    if ($command->getName() === 'PutObject') {
        $row = $observations->selectOneRow('SELECT state, primary_state, supporting_state, primary_nonce, supporting_nonce,
            primary_bytes, supporting_bytes, primary_sha256, supporting_sha256 FROM document_packets WHERE packet_id = :id', ['id' => $matches[1]]);
        $body = $command['Body']; $metadata = $command['Metadata'];
        if ($row === null || !is_resource($body) || !is_array($metadata)) { throw new LogicException('Packet pending facts unavailable.'); }
        $stream = stream_get_meta_data($body); $role = $matches[2];
        if (!is_string($stream['uri'] ?? null) || !is_uploaded_file($stream['uri']) || ftell($body) !== 0
            || $row['state'] !== 'pending' || $row[$role . '_state'] !== 'write_started'
            || ($metadata['ownership'] ?? null) !== $row[$role . '_nonce'] || $row[$role . '_bytes'] !== $command['ContentLength']) {
            throw new LogicException('Packet lacks durable role ownership and native provenance.');
        }
        $packetSeen = true;
    }
    if ($plan['fault'] === 'expire_after_supporting_head' && $command->getName() === 'HeadObject' && $matches[2] === 'supporting') {
        $observations->executeStatement('UPDATE document_packets SET created_at = :created, expires_at = :expires WHERE packet_id = :id',
            ['created' => time() - 86400, 'expires' => time(), 'id' => $matches[1]]);
    }
};
$packetClient = $packetPeer->client();
if (array_key_exists('loopback_port', $plan)) {
    if (!is_int($plan['loopback_port'])) { throw new LogicException('Invalid loopback plan.'); }
    $packetClient = packetLoopbackClient($plan['loopback_port']);
}
$packets = new MetadataS3Recipe\Packets(Connection::connect('sqlite:' . $root . '/documents.sqlite', $packetBudget, $packetTrace),
    $lock, new MetadataS3Recipe\PacketS3($packetClient, $remote->target), new SystemDocumentClock());
$response = documentCoordinator(new SyntheticUploadPolicy(), $receiver, $budget, $trace, $sink,
    new SyntheticUploadPolicy(), new PacketFaultReceiver(new MetadataS3Recipe\LockedPacketReceiver($packets, $lock), $plan['fault']),
    $packetBudget, $packetTrace)->handle($_SERVER, $_GET);
$receipt = ['calls' => $remote->calls, 'key' => $remote->key, 'sha256' => hash('sha256', $remote->bytes),
    'statements' => $trace->snapshot()['statements'], 'present' => $remote->present, 'pending_seen' => $pendingSeen,
    'summary_attempts' => $sink->attempts, 'summary' => $sink->json, 'packet_calls' => $packetPeer->calls,
    'packet_statements' => $packetTrace->snapshot()['statements'], 'packet_seen' => $packetSeen];
if (file_put_contents($root . '/receipt.json', json_encode($receipt, JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot retain private synthetic receipt.');
}
chmod($root . '/receipt.json', 0600);
(new ResponseEmitter())->emit($response);
