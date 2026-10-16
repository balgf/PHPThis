<?php

declare(strict_types=1);

// Test-only loopback endpoint. Production composition has no endpoint override.
require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/generated-s3/bootstrap.php';

use Aws\Credentials\Credentials;
use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use MetadataS3Recipe\DocumentRecord;
use MetadataS3Recipe\DocumentS3;
use MetadataS3Recipe\S3Target;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\TransportSharing;

try {
    if ($argc !== 2 || !ctype_digit($argv[1]) || (int) $argv[1] < 1 || (int) $argv[1] > 65535) { throw new RuntimeException('arguments'); }
    $http = new Client(['handler' => new CurlHandler(['transport_sharing' => TransportSharing::NONE]),
        'allow_redirects' => false, 'cookies' => false, 'debug' => false, 'proxy' => '',
        'protocols' => ['http'], 'connect_timeout' => 0.3, 'timeout' => 0.5, 'decode_content' => false]);
    $client = new S3Client(['version' => '2006-03-01', 'region' => 'us-east-1',
        'endpoint' => 'http://127.0.0.1:' . $argv[1], 'use_path_style_endpoint' => true,
        'credentials' => new Credentials('loopback-fixture-key', 'loopback-fixture-secret'), 'retries' => 0,
        'use_aws_shared_config_files' => false, 'ignore_configured_endpoint_urls' => true,
        'http_handler' => new GuzzleHandler($http),
        'request_checksum_calculation' => 'when_supported', 'response_checksum_validation' => 'when_supported',
        'http' => ['allow_redirects' => false, 'connect_timeout' => 0.3, 'timeout' => 0.5,
            'decode_content' => false, 'proxy' => '', 'protocols' => ['http']]]);
    $target = S3Target::fromInputs('fixture-bucket', 'us-east-1', '000000000000', 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001');
    $record = DocumentRecord::fromDatabaseRow(['document_id' => str_repeat('a', 32), 'owner_id' => 1,
        'label' => 'private-sentinel label', 'note' => 'private-sentinel note', 'size_bytes' => 1,
        'sha256' => hash('sha256', 'x'), 'state' => 'accepted', 'version_id' => 'fixture-version', 'delete_attempts' => 0,
        'created_at' => 1_800_000_000, 'expires_at' => 1_800_086_400, 'target' => $target->fingerprint()]);
    foreach (['valid', 'oversized', 'redirect', 'denied', 'timeout', 'truncated', 'sibling'] as $case) {
        $s3 = new DocumentS3($client, $target); $accepted = false;
        try { $accepted = $s3->version($record)?->value === 'fixture-version'; }
        catch (Throwable) {}
        if ($accepted !== ($case === 'valid') || $s3->calls() !== 1) { throw new RuntimeException('listing_case'); }
    }
    // The established generated recipe shares this SDK representation; prevent regression there too.
    $generatedTarget = GeneratedS3Recipe\S3Target::fromInputs($target->bucket, $target->region, $target->account, $target->kms);
    $generated = new GeneratedS3Recipe\InventoryS3($client, $generatedTarget);
    $generatedRecord = GeneratedS3Recipe\ExportRecord::fromDatabaseRow(['export_id' => str_repeat('a', 32),
        'size_bytes' => 1, 'sha256' => hash('sha256', 'x'), 'state' => 'accepted', 'version_id' => 'fixture-version',
        'delete_attempts' => 0, 'created_at' => 1_800_000_000, 'expires_at' => 1_800_086_400, 'target' => $generatedTarget->fingerprint()]);
    if ($generated->version($generatedRecord)?->value !== 'fixture-version' || $generated->calls() !== 1) {
        throw new RuntimeException('generated_listing_compatibility');
    }
    $s3 = new DocumentS3($client, $target);
    $s3->delete($record);
    if ($s3->calls() !== 1) { throw new RuntimeException('delete_bound'); }
    fwrite(STDOUT, "PASS metadata S3 transport: 9 exact requests; REFERENCE_ONLY\n");
} catch (Throwable) { fwrite(STDERR, "metadata_s3_transport_failed\n"); exit(1); }
