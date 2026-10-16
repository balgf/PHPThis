<?php

declare(strict_types=1);

use Aws\Credentials\Credentials;
use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\TransportSharing;

/** Test-only explicit loopback transport; the deployed composition has no endpoint override. */
function packetLoopbackClient(int $port): S3Client
{
    if ($port < 1 || $port > 65535) { throw new LogicException('Invalid loopback port.'); }
    $http = new Client(['handler' => new CurlHandler(['transport_sharing' => TransportSharing::NONE]),
        'allow_redirects' => false, 'cookies' => false, 'debug' => false, 'proxy' => '', 'protocols' => ['http'],
        'connect_timeout' => 0.3, 'timeout' => 1.0, 'decode_content' => false]);
    return new S3Client(['version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => 'http://127.0.0.1:' . $port,
        'use_path_style_endpoint' => true, 'credentials' => new Credentials('loopback-key', 'loopback-secret'),
        'retries' => 0, 'use_aws_shared_config_files' => false, 'ignore_configured_endpoint_urls' => true,
        'http_handler' => new GuzzleHandler($http), 'request_checksum_calculation' => 'when_supported',
        'response_checksum_validation' => 'when_supported',
        'http' => ['allow_redirects' => false, 'connect_timeout' => 0.3, 'timeout' => 1.0,
            'decode_content' => false, 'proxy' => '', 'protocols' => ['http']]]);
}
