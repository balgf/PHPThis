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

// Synthetic policy/fault inputs are private test files, never a deployed credential verifier.
final readonly class SyntheticUploadPolicy implements DocumentUploadPolicy
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
$response = documentCoordinator(new SyntheticUploadPolicy(), $receiver, $budget, $trace, $sink)->handle($_SERVER, $_GET);
$receipt = ['calls' => $remote->calls, 'key' => $remote->key, 'sha256' => hash('sha256', $remote->bytes),
    'statements' => $trace->snapshot()['statements'], 'present' => $remote->present, 'pending_seen' => $pendingSeen,
    'summary_attempts' => $sink->attempts, 'summary' => $sink->json];
if (file_put_contents($root . '/receipt.json', json_encode($receipt, JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot retain private synthetic receipt.');
}
chmod($root . '/receipt.json', 0600);
(new ResponseEmitter())->emit($response);
