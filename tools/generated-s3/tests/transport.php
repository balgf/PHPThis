<?php

declare(strict_types=1);

// Actual recipe operations and pinned SDK over loopback; never an adopted production composition.
require dirname(__DIR__) . '/bootstrap.php';

use Aws\Credentials\Credentials;
use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use GeneratedS3Recipe\ExportBytes;
use GeneratedS3Recipe\ExportId;
use GeneratedS3Recipe\ExportRecord;
use GeneratedS3Recipe\InventoryS3;
use GeneratedS3Recipe\S3Target;
use GeneratedS3Recipe\TestToneProducer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\TransportSharing;

try {
    if ($argc !== 2 || !ctype_digit($argv[1]) || (int) $argv[1] < 1 || (int) $argv[1] > 65_535) { throw new RuntimeException('arguments'); }
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
    $payload = ExportBytes::fromBytes(str_repeat('x', 65_536));
    $write = new InventoryS3($client, $target);
    $version = $write->create(new ExportId(str_repeat('f', 32)), $payload);
    if ($version->value !== 'fixture-version' || $write->calls() !== 1) { throw new RuntimeException('put'); }
    $cases = ['valid', 'oversized', 'chunked_oversized', 'short', 'wrong_version', 'wrong_checksum',
        'corrupted', 'denied', 'missing', 'redirect', 'timeout', 'wrong_kms', 'bucket_key', 'metadata', 'storage', 'encoded'];
    $lastRecord = null;
    foreach ($cases as $index => $case) {
        $record = ExportRecord::fromDatabaseRow(['export_id' => sprintf('%032x', $index + 1), 'size_bytes' => 65_536,
            'sha256' => $payload->sha256, 'state' => 'accepted', 'version_id' => 'fixture-version', 'delete_attempts' => 0,
            'created_at' => 1_800_000_000, 'expires_at' => 1_800_086_400, 'target' => $target->fingerprint()]);
        $s3 = new InventoryS3($client, $target); $accepted = false;
        try { $result = $s3->read($record); $accepted = $result->value === $payload->value; }
        catch (Throwable) {}
        if ($accepted !== ($case === 'valid') || $s3->calls() !== 1) { throw new RuntimeException('read_case'); }
        $lastRecord = $record;
    }
    $control = new InventoryS3($client, $target);
    try { $control->version($lastRecord); throw new LogicException('oversized_control_accepted'); }
    catch (RuntimeException) {}
    $tone = TestToneProducer::generate()->bytes;
    $toneWrite = new InventoryS3($client, $target);
    $toneVersion = $toneWrite->create(new ExportId(str_repeat('e', 32)), $tone);
    $toneCases = ['tone_valid', 'tone_oversized', 'tone_chunked_oversized', 'tone_corrupted'];
    foreach ($toneCases as $index => $case) {
        $record = ExportRecord::fromDatabaseRow(['export_id' => str_repeat(dechex(14 - $index), 32), 'size_bytes' => 32_044,
            'sha256' => $tone->sha256, 'state' => 'accepted', 'version_id' => $toneVersion->value, 'delete_attempts' => 0,
            'created_at' => 1_800_000_000, 'expires_at' => 1_800_086_400, 'target' => $target->fingerprint()]);
        $s3 = new InventoryS3($client, $target); $accepted = false;
        if ($case === 'tone_valid') { $s3->head($record, $toneVersion); }
        try { $accepted = $s3->read($record)->value === $tone->value; } catch (Throwable) {}
        if ($accepted !== ($case === 'tone_valid') || $s3->calls() !== ($case === 'tone_valid' ? 2 : 1)) {
            throw new RuntimeException('tone_read_case');
        }
    }
    $curl = curl_version();
    if ($curl === false) { throw new RuntimeException('curl unavailable'); }
    echo json_encode(['scope' => 'REFERENCE_ONLY', 'cases' => ['generated_memory_put', ...$cases, 'oversized_control',
        'generated_tone_put', 'generated_tone_head', ...$toneCases],
        'php' => PHP_VERSION, 'curl' => $curl['version'], 'tls' => $curl['ssl_version'],
        'sdk' => Aws\Sdk::VERSION, 'guzzle' => Composer\InstalledVersions::getPrettyVersion('guzzlehttp/guzzle'),
        'psr7' => Composer\InstalledVersions::getPrettyVersion('guzzlehttp/psr7'),
        'php_peak_allocated_bytes' => memory_get_peak_usage(true), 'object_sink_cap' => 65_536,
        'control_sink_cap' => 16_384, 'disk_staging' => false], JSON_THROW_ON_ERROR), "\n";
} catch (Throwable) { fwrite(STDERR, "transport_proof_failed\n"); exit(1); }
