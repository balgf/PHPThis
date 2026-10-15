<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\TransportSharing;

function exportS3Client(S3Settings $configuration): S3Client
{
    $memoizedExplicitCredentialProvider = exportCredentialProvider($configuration);
    $httpClient = new Client([
        'handler' => new CurlHandler(['transport_sharing' => TransportSharing::NONE]),
        'allow_redirects' => false, 'cookies' => false, 'debug' => false, 'proxy' => '',
        'protocols' => ['https'], 'connect_timeout' => 2.0, 'timeout' => 10.0,
        'verify' => $configuration->caPath, 'decode_content' => false,
    ]);
    return new S3Client([
        'version' => '2006-03-01', 'region' => $configuration->target->region,
        'credentials' => $memoizedExplicitCredentialProvider,
        'defaults_mode' => 'legacy', 'signature_version' => 'v4', 'auth_scheme_preference' => ['aws.auth#sigv4'],
        'scheme' => 'https', 'endpoint_discovery' => ['enabled' => false],
        'use_aws_shared_config_files' => false, 'ignore_configured_endpoint_urls' => true,
        'use_path_style_endpoint' => false, 'bucket_endpoint' => false, 'use_accelerate_endpoint' => false,
        'use_dual_stack_endpoint' => false, 'use_fips_endpoint' => false,
        's3_us_east_1_regional_endpoint' => 'regional', 'use_arn_region' => false,
        'disable_multiregion_access_points' => true, 'disable_express_session_auth' => true,
        'request_checksum_calculation' => 'when_supported', 'response_checksum_validation' => 'when_supported',
        'disable_request_compression' => true, 'retries' => 0, 'app_id' => '', 'csm' => false,
        'debug' => false, 'stats' => false, 'validate' => true, 'http_handler' => new GuzzleHandler($httpClient),
        'http' => ['allow_redirects' => false, 'connect_timeout' => 2.0, 'timeout' => 10.0,
            'proxy' => '', 'protocols' => ['https'], 'verify' => $configuration->caPath, 'decode_content' => false],
    ]);
}
