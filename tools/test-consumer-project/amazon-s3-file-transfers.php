<?php

declare(strict_types=1);

/**
 * @return array{manifest: non-empty-string, checker: non-empty-string, composer: non-empty-string}
 */
function installedAmazonS3FileTransferVerificationReferences(string $installedFramework): array
{
    $guidePath = $installedFramework . '/docs/file-transfers/amazon-s3-verification.md';
    $guide = file_get_contents($guidePath);

    if (!is_string($guide)) {
        throw new RuntimeException('Unable to read the installed Amazon S3 verification reference.');
    }

    $references = [
        'manifest' => 'uninitialized',
        'checker' => 'uninitialized',
        'composer' => 'uninitialized',
    ];

    foreach (
        [
            'manifest' => ['## Exact reviewed-source manifest shape', 'json'],
            'checker' => ['## Exact application-owned checker shape', 'php'],
            'composer' => ['## Exact Composer gate wiring', 'json'],
        ] as $name => [$heading, $language]
    ) {
        if (substr_count($guide, $heading) !== 1) {
            throw new RuntimeException("The installed Amazon S3 {$name} reference heading is not unique.");
        }

        $headingOffset = strpos($guide, $heading);

        if ($headingOffset === false) {
            throw new RuntimeException("The installed Amazon S3 {$name} reference is missing.");
        }

        $blockMarker = "\n```{$language}\n";
        $blockOffset = strpos($guide, $blockMarker, $headingOffset + strlen($heading));

        if ($blockOffset === false) {
            throw new RuntimeException("The installed Amazon S3 {$name} reference block is missing.");
        }

        $sourceOffset = $blockOffset + strlen($blockMarker);
        $sourceEnd = strpos($guide, "\n```", $sourceOffset);

        if ($sourceEnd === false) {
            throw new RuntimeException("The installed Amazon S3 {$name} reference is incomplete.");
        }

        $source = substr($guide, $sourceOffset, $sourceEnd - $sourceOffset);

        if ($source === '') {
            throw new RuntimeException("The installed Amazon S3 {$name} reference is empty.");
        }

        $references[$name] = $source . "\n";
    }

    return [
        'manifest' => $references['manifest'],
        'checker' => $references['checker'],
        'composer' => $references['composer'],
    ];
}

/**
 * @return array{
 *   configuration: array{path: non-empty-string, bytes: non-empty-string},
 *   credential_provider: array{path: non-empty-string, bytes: non-empty-string},
 *   composition: array{path: non-empty-string, bytes: non-empty-string},
 *   upload: array{path: non-empty-string, bytes: non-empty-string},
 *   reconciliation: array{path: non-empty-string, bytes: non-empty-string},
 *   download: array{path: non-empty-string, bytes: non-empty-string},
 *   deletion: array{path: non-empty-string, bytes: non-empty-string}
 * }
 */
function installedAmazonS3FileTransferVerificationFixtureSources(): array
{
    return [
        'configuration' => [
            'path' => 'src/AmazonS3ReferenceProof/Configuration.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

return [
    'region' => 'us-east-1',
    'bucket' => 'phpthis-s3-reference-proof',
    'expected_account_id' => '000000000000',
    'prefix' => 'private-documents/v1/',
    'kms_key_arn' => 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000000',
    'ca_bundle_path' => '/etc/ssl/certs/ca-certificates.crt',
    'ca_bundle_sha256' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'credential_source' => 'PHPTHIS_S3_REFERENCE_PROOF',
];
PHP,
        ],
        'credential_provider' => [
            'path' => 'src/AmazonS3ReferenceProof/CredentialProvider.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

$applicationCredentialProvider = static function () use ($configuration): never {
    throw new RuntimeException('Synthetic credential provider is never executed.');
};
$selectedCredentialSource = $configuration->credentialSource;
$memoizedExplicitCredentialProvider = Aws\Credentials\CredentialProvider::memoize(
    $applicationCredentialProvider,
);

return [$selectedCredentialSource, $memoizedExplicitCredentialProvider];
PHP,
        ],
        'composition' => [
            'path' => 'src/AmazonS3ReferenceProof/Composition.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

$caBundleStat = lstat($configuration->caBundlePath);

if (!str_starts_with($configuration->caBundlePath, '/')
    || !is_array($caBundleStat)
    || ($caBundleStat['mode'] & 0170000) !== 0100000
    || is_link($configuration->caBundlePath)
    || !is_readable($configuration->caBundlePath)
) {
    throw new RuntimeException('S3 CA bundle is unavailable.');
}

$caBundleSize = filesize($configuration->caBundlePath);
$caBundleHash = hash_file('sha256', $configuration->caBundlePath);

if (!is_int($caBundleSize)
    || $caBundleSize < 1
    || $caBundleSize > 1048576
    || !is_string($caBundleHash)
    || !hash_equals($configuration->caBundleSha256, $caBundleHash)
) {
    throw new RuntimeException('S3 CA bundle is not the reviewed release input.');
}

$httpClient = new GuzzleHttp\Client([
    'handler' => new GuzzleHttp\Handler\CurlHandler([
        'transport_sharing' => GuzzleHttp\TransportSharing::NONE,
    ]),
    'allow_redirects' => false,
    'cookies' => false,
    'debug' => false,
    'proxy' => '',
    'protocols' => ['https'],
    'connect_timeout' => 2.0,
    'timeout' => 10.0,
    'verify' => $configuration->caBundlePath,
]);

$awsHttpHandler = new Aws\Handler\Guzzle\GuzzleHandler($httpClient);

$s3 = new Aws\S3\S3Client([
    'version' => '2006-03-01',
    'region' => $configuration->region,
    'credentials' => $memoizedExplicitCredentialProvider,
    'defaults_mode' => 'legacy',
    'signature_version' => 'v4',
    'auth_scheme_preference' => ['aws.auth#sigv4'],
    'scheme' => 'https',
    'endpoint_discovery' => ['enabled' => false],
    'use_aws_shared_config_files' => false,
    'ignore_configured_endpoint_urls' => true,
    'use_path_style_endpoint' => false,
    'bucket_endpoint' => false,
    'use_accelerate_endpoint' => false,
    'use_dual_stack_endpoint' => false,
    'use_fips_endpoint' => false,
    's3_us_east_1_regional_endpoint' => 'regional',
    'use_arn_region' => false,
    'disable_multiregion_access_points' => true,
    'disable_express_session_auth' => true,
    'request_checksum_calculation' => 'when_supported',
    'response_checksum_validation' => 'when_supported',
    'disable_request_compression' => true,
    'retries' => 0,
    'app_id' => '',
    'csm' => false,
    'debug' => false,
    'stats' => false,
    'validate' => true,
    'http_handler' => $awsHttpHandler,
    'http' => [
        'allow_redirects' => false,
        'connect_timeout' => 2.0,
        'proxy' => '',
        'protocols' => ['https'],
        'timeout' => 10.0,
        'verify' => $configuration->caBundlePath,
    ],
]);

return $s3;
PHP,
        ],
        'upload' => [
            'path' => 'src/AmazonS3ReferenceProof/Upload.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

$putResult = $client->putObject([
    'ExpectedBucketOwner' => '000000000000',
    'IfNoneMatch' => '*',
    'ChecksumAlgorithm' => 'SHA256',
    'ChecksumSHA256' => 'synthetic-checksum',
    'StorageClass' => 'STANDARD',
    'ServerSideEncryption' => 'aws:kms',
    'BucketKeyEnabled' => false,
    'Prefix' => 'private-documents/v1/',
]);
$headResult = $client->headObject([
    'ExpectedBucketOwner' => '000000000000',
    'ChecksumMode' => 'ENABLED',
    'Prefix' => 'private-documents/v1/',
    'VersionId' => 'synthetic-version',
]);

if ($headResult->hasKey('StorageClass')
    || $headResult->hasKey('ArchiveStatus')
    || $headResult->hasKey('Restore')
    || $headResult->hasKey('Expiration')
) {
    throw new UnexpectedValueException('S3 retained object is not in the required STANDARD state.');
}

return [$putResult, $headResult];
PHP,
        ],
        'reconciliation' => [
            'path' => 'src/AmazonS3ReferenceProof/Reconciliation.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

$listResult = $client->listObjectVersions([
    'ExpectedBucketOwner' => '000000000000',
    'Prefix' => 'private-documents/v1/synthetic-key',
    'MaxKeys' => 2,
]);
$headResult = $client->headObject([
    'ExpectedBucketOwner' => '000000000000',
    'VersionId' => 'synthetic-version',
]);

return [$listResult, $headResult];
PHP,
        ],
        'download' => [
            'path' => 'src/AmazonS3ReferenceProof/Download.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

return [
    'X-Amz-SignedHeaders' => 'host',
    'VersionId' => 'synthetic-version',
];
PHP,
        ],
        'deletion' => [
            'path' => 'src/AmazonS3ReferenceProof/Deletion.php',
            'bytes' => <<<'PHP'
<?php

declare(strict_types=1);

$deleteResult = $client->deleteObject([
    'ExpectedBucketOwner' => '000000000000',
    'VersionId' => 'synthetic-version',
]);
$listResult = $client->listObjectVersions([
    'ExpectedBucketOwner' => '000000000000',
    'Prefix' => 'private-documents/v1/synthetic-key',
    'MaxKeys' => 2,
]);
$headResult = $client->headObject([
    'ExpectedBucketOwner' => '000000000000',
    'VersionId' => 'synthetic-version',
]);

return [$deleteResult, $listResult, $headResult];
PHP,
        ],
    ];
}

/**
 * @param array{
 *   profile: string,
 *   boundary: array{
 *     region: string,
 *     bucket: string,
 *     expected_account_id: string,
 *     prefix: string,
 *     kms_key_arn: string,
 *     ca_bundle_path: string,
 *     ca_bundle_sha256: string,
 *     credential_source: string
 *   },
 *   sources: array{
 *     configuration: array{0: string, 1: string},
 *     credential_provider: array{0: string, 1: string},
 *     composition: array{0: string, 1: string},
 *     upload: array{0: string, 1: string},
 *     reconciliation: array{0: string, 1: string},
 *     download: array{0: string, 1: string},
 *     deletion: array{0: string, 1: string}
 *   }
 * } $manifest
 */
function writeInstalledAmazonS3FileTransferVerificationManifest(
    string $manifestPath,
    array $manifest,
): void {
    $lines = [
        '{',
        '  "profile": "AMAZON_S3_ADR053",',
    ];
    $lines[] = '  "boundary": {';
    $lastBoundaryName = array_key_last($manifest['boundary']);

    foreach ($manifest['boundary'] as $name => $value) {
        $encodedValue = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $suffix = $name === $lastBoundaryName ? '' : ',';
        $lines[] = '    "' . $name . '": ' . $encodedValue . $suffix;
    }

    $lines[] = '  },';
    $lines[] = '  "sources": {';
    $lastRole = array_key_last($manifest['sources']);

    foreach ($manifest['sources'] as $role => $source) {
        $encodedSource = json_encode(
            $source,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $suffix = $role === $lastRole ? '' : ',';
        $lines[] = '    "' . $role . '": ' . $encodedSource . $suffix;
    }

    $lines[] = '  }';
    $lines[] = '}';
    writeFile($manifestPath, implode("\n", $lines) . "\n");
}

/** @param array<string, array{path: string, bytes: string}> $fixtures */
function writeInstalledAmazonS3FileTransferVerificationFixtures(
    string $project,
    array $fixtures,
): void {
    foreach ($fixtures as $fixture) {
        writeFile($project . '/' . $fixture['path'], $fixture['bytes']);
    }
}

/**
 * @param array<string, string> $environment
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runInstalledAmazonS3FileTransferSourceChecker(
    string $checkerPath,
    string $project,
    array $environment,
): array {
    return runProcess([PHP_BINARY, $checkerPath], $project, $environment);
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireInstalledAmazonS3FileTransferVerificationFailure(
    array $result,
    string $message,
): void {
    requireExactProcessResult(
        $result,
        1,
        '',
        "AMAZON S3 FILE TRANSFER SOURCE VERIFY FAIL\n",
        $message,
    );
    requireOutputNotContains($result, 'phpthis-consumer-proof-');
    requireOutputNotContains($result, 'synthetic-version');
    requireOutputNotContains($result, '000000000000');
}

/**
 * @param array<string, string> $environment
 * @return non-empty-string
 */
function proveInstalledAmazonS3FileTransferVerificationReference(
    string $project,
    string $installedFramework,
    string $composerBinary,
    array $environment,
): string {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/docs/decisions/053-application-owned-amazon-s3-file-transfers.md' => [
            'Status: accepted',
            'Accountable-human approval: accepted on 2026-08-13 (Asia/Manila).',
            'Consumer Contract version 13',
            'Direct S3 delivery cannot add `X-Content-Type-Options: nosniff`',
            'Contract version 13',
            'PHPThis adds no S3 client',
            'The health-only skeleton remains `NOT_APPLICABLE(FILE_TRANSFER)`.',
        ],
        $installedFramework . '/docs/file-transfers/amazon-s3.md' => [
            'Status: accepted optional guidance under ADR 053 and Consumer Contract version 13.',
            '`aws/aws-sdk-php` exactly `3.392.1`',
            'arbitrary S3-compatible services',
            'narrow `AMAZON_S3_ADR053` exception',
            '`ExpectedBucketOwner` is required on server-executed S3 operations',
            'Do not put it into this browser-navigation pre-signed command.',
            '`X-Amz-SignedHeaders=host`',
            "'StorageClass' => 'STANDARD'",
            "\$headResult->hasKey('StorageClass')",
            'no S3 Lifecycle rule or action whose filter can cover `private-documents/v1/`',
            '`PutObject`, `HeadObject`, `ListObjectVersions`, `GetObject`, and `DeleteObject`',
        ],
        $installedFramework . '/docs/file-transfers/amazon-s3-verification.md' => [
            'Status: accepted optional guidance under ADR 053 and Consumer Contract version 13.',
            '## Exact reviewed-source manifest shape',
            '## Exact application-owned checker shape',
            '## Exact Composer gate wiring',
            '## Framework reference proof',
            'The canonical gate is `file-transfers:s3:verify`.',
            '`NOT_APPLICABLE(FILE_TRANSFER)` and `REFERENCE_ONLY(AMAZON_S3_FILE_TRANSFER_VERIFICATION_STRUCTURE)`',
            'without loading the AWS SDK or contacting AWS',
            'That synthetic pass proves no upload, Amazon S3 service, SDK request, bucket, account, IAM, KMS, encryption, checksum, conditional write, versioning, pre-signed URL, delivery, deletion, deployment, or production fact.',
            'no S3 Lifecycle rule or action whose filter can cover `private-documents/v1/`',
            '`PutObject`, `HeadObject`, `ListObjectVersions`, `GetObject`, and `DeleteObject`',
        ],
        $installedFramework . '/templates/application/.ai/file-transfers.md' => [
            '## Optional accepted Amazon S3 profile fields',
            '{{AMAZON_S3_FILE_TRANSFER_VERSION_OWNER_OR_NOT_APPLICABLE}}',
            '{{AMAZON_S3_FILE_TRANSFER_GATE_OR_NOT_APPLICABLE}}',
        ],
        $project . '/.ai/file-transfers.md' => [
            'NOT_APPLICABLE(FILE_TRANSFER)',
            'REFERENCE_ONLY(AMAZON_S3_FILE_TRANSFER_GUIDANCE)',
            'select exactly one `LOCAL_ADR026` or `AMAZON_S3_ADR053` profile',
        ],
    ];
    requireInstalledArtifactMarkers($artifactMarkers, 'Amazon S3 file-transfer verification reference');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    foreach (directoryFiles($installedFramework) as $installedFile) {
        if (
            str_starts_with($installedFile, 'src/AmazonS3/')
            || str_starts_with($installedFile, 'src/S3/')
            || str_starts_with($installedFile, 'src/Storage/')
        ) {
            throw new RuntimeException('The installed framework unexpectedly contains an Amazon S3 or storage runtime API.');
        }
    }

    $references = installedAmazonS3FileTransferVerificationReferences($installedFramework);
    $expectedReferenceHashes = [
        'manifest' => '3b62146a52aaf783f0289d4a5e419306217fb63be885f48306021274198e31fb',
        'checker' => '02735e81dadb907ad7756b5f25ff368d950919a4543e508c3f5b18810bc02a86',
        'composer' => 'dccb0ef07e7c60cfd8c7e5507c148fc9c51b3012bd8bcf8e65ac3703069be833',
    ];

    foreach ($expectedReferenceHashes as $name => $expectedReferenceHash) {
        if (!hash_equals($expectedReferenceHash, hash('sha256', $references[$name]))) {
            throw new RuntimeException("The installed Amazon S3 {$name} reference changed.");
        }
    }

    $manifestReference = json_decode($references['manifest'], true, 32, JSON_THROW_ON_ERROR);
    $expectedManifestReference = [
        'profile' => 'AMAZON_S3_ADR053',
        'boundary' => [
            'region' => '{{S3_REGION}}',
            'bucket' => '{{S3_BUCKET}}',
            'expected_account_id' => '{{S3_EXPECTED_ACCOUNT_ID}}',
            'prefix' => 'private-documents/v1/',
            'kms_key_arn' => '{{S3_KMS_KEY_ARN}}',
            'ca_bundle_path' => '{{S3_CA_BUNDLE_ABSOLUTE_PATH}}',
            'ca_bundle_sha256' => '{{S3_CA_BUNDLE_SHA256}}',
            'credential_source' => '{{S3_CREDENTIAL_SOURCE_NAME}}',
        ],
        'sources' => [
            'configuration' => ['{{S3_CONFIGURATION_SOURCE}}', '{{S3_CONFIGURATION_SHA256}}'],
            'credential_provider' => [
                '{{S3_CREDENTIAL_PROVIDER_SOURCE}}',
                '{{S3_CREDENTIAL_PROVIDER_SHA256}}',
            ],
            'composition' => ['{{S3_COMPOSITION_SOURCE}}', '{{S3_COMPOSITION_SHA256}}'],
            'upload' => ['{{S3_UPLOAD_SOURCE}}', '{{S3_UPLOAD_SHA256}}'],
            'reconciliation' => ['{{S3_RECONCILIATION_SOURCE}}', '{{S3_RECONCILIATION_SHA256}}'],
            'download' => ['{{S3_DOWNLOAD_SOURCE}}', '{{S3_DOWNLOAD_SHA256}}'],
            'deletion' => ['{{S3_DELETION_SOURCE}}', '{{S3_DELETION_SHA256}}'],
        ],
    ];

    if ($manifestReference !== $expectedManifestReference) {
        throw new RuntimeException('The installed Amazon S3 reviewed-source manifest shape changed.');
    }

    $expectedScripts = [
        'profile' => 'phpthis check',
        'file-transfers:s3:source' => 'php tools/verify-amazon-s3-file-transfer-source.php',
        'file-transfers:s3:behavior' => 'php tests/amazon-s3-file-transfers.php',
        'file-transfers:s3:real' => 'php tests/amazon-s3-file-transfers-real-aws.php',
        'file-transfers:s3:deployment' => 'php tools/verify-amazon-s3-file-transfer-deployment.php',
        'file-transfers:s3:verify' => [
            '@file-transfers:s3:source',
            '@file-transfers:s3:behavior',
            '@file-transfers:s3:real',
            '@file-transfers:s3:deployment',
        ],
        'test:application' => 'php tests/run.php',
        'test' => [
            '@file-transfers:s3:verify',
            '@test:application',
        ],
        'check' => [
            '@profile',
            '@test',
        ],
    ];
    $expectedRequirements = [
        'php' => '8.4.*',
        'aws/aws-sdk-php' => '3.392.1',
        'ext-curl' => '*',
        'ext-json' => '*',
        'ext-pcre' => '*',
        'ext-simplexml' => '*',
        'guzzlehttp/guzzle' => '8.0.2',
    ];
    $composerReference = json_decode($references['composer'], true, 32, JSON_THROW_ON_ERROR);

    if (
        !is_array($composerReference)
        || array_keys($composerReference) !== ['scripts']
        || ($composerReference['scripts'] ?? null) !== $expectedScripts
    ) {
        throw new RuntimeException('The installed Amazon S3 Composer gate reference changed.');
    }

    foreach (
        [
            'createPresignedRequest',
            'curl_',
            'require ',
            'include ',
        ] as $runtimeMarker
    ) {
        if (str_contains($references['checker'], $runtimeMarker)) {
            throw new RuntimeException('The installed Amazon S3 source checker gained a runtime or AWS execution path.');
        }
    }

    foreach (
        [
            "'ExpectedBucketOwner'",
            "'X-Amz-SignedHeaders'",
            "'host'",
            "'endpoint' =>",
            "'handler' => new GuzzleHttp\\Handler\\CurlHandler([",
            "'transport_sharing' => GuzzleHttp\\TransportSharing::NONE",
            'new Aws\\Handler\\Guzzle\\GuzzleHandler($httpClient)',
            "'http_handler'",
            '$awsHttpHandler',
            'StorageInterface',
            "'file-transfers:s3:verify'",
            "'check' =>",
            '$amazonS3VerificationState = new class {',
            'register_shutdown_function(',
            '$amazonS3VerificationState->completed = true;',
            'AMAZON S3 FILE TRANSFER SOURCE VERIFY FAIL\n',
            'AMAZON S3 FILE TRANSFER SOURCE VERIFY PASS\n',
        ] as $checkerMarker
    ) {
        if (!str_contains($references['checker'], $checkerMarker)) {
            throw new RuntimeException('The installed Amazon S3 source checker lost a reviewed static boundary.');
        }
    }

    $contextPath = $project . '/.ai/file-transfers.md';
    $composerPath = $project . '/composer.json';
    $lockPath = $project . '/composer.lock';
    $toolsPath = $project . '/tools';
    $manifestPath = $toolsPath . '/amazon-s3-file-transfer-checker-manifest.json';
    $checkerPath = $toolsPath . '/verify-amazon-s3-file-transfer-source.php';
    $sourceRoot = $project . '/src/AmazonS3ReferenceProof';
    $originalContext = file_get_contents($contextPath);
    $originalComposer = file_get_contents($composerPath);
    $originalLock = file_get_contents($lockPath);

    if (
        !is_string($originalContext)
        || !is_string($originalComposer)
        || !is_string($originalLock)
        || is_link($contextPath)
        || is_link($composerPath)
        || is_link($lockPath)
        || file_exists($toolsPath)
        || is_link($toolsPath)
        || file_exists($sourceRoot)
        || is_link($sourceRoot)
    ) {
        throw new RuntimeException('The Amazon S3 verification proof requires an untouched regular starter.');
    }

    $referenceContext = <<<'MD'
# Installed synthetic Amazon S3 verification structure reference

NOT_APPLICABLE(FILE_TRANSFER)
REFERENCE_ONLY(AMAZON_S3_FILE_TRANSFER_VERIFICATION_STRUCTURE)

- This transient non-adopter exercises only the exact accepted static source checker copied from the installed framework documentation.
- ADR 053 and Consumer Contract version 13 accept optional application-owned guidance, but this transient reference remains a non-adopter and supplies no application or deployment evidence.
- Direct S3 GetObject cannot add `X-Content-Type-Options: nosniff`; the accepted narrow profile exception does not make this transient non-adopter an adopter or prove a consumer or AWS deployment.
- The source fixture, hashes, exact SDK manifest/lock identity and Composer gate are static review tripwires only.
- No AWS SDK is installed or loaded, no endpoint or credential is read, and no Amazon S3, IAM, KMS, bucket, upload, download, deletion, deployment or production behavior is contacted, simulated, adopted or proved.
MD;

    if (
        str_contains($referenceContext, 'ADOPTED(FILE_TRANSFER')
        || substr_count($referenceContext, 'NOT_APPLICABLE(FILE_TRANSFER)') !== 1
        || substr_count(
            $referenceContext,
            'REFERENCE_ONLY(AMAZON_S3_FILE_TRANSFER_VERIFICATION_STRUCTURE)',
        ) !== 1
    ) {
        throw new RuntimeException('The Amazon S3 structure-only context classification changed.');
    }

    $fixtures = installedAmazonS3FileTransferVerificationFixtureSources();
    $projectComposer = json_decode($originalComposer, true, 32, JSON_THROW_ON_ERROR);
    $projectLock = json_decode($originalLock, true, 64, JSON_THROW_ON_ERROR);

    if (
        !is_array($projectComposer)
        || !is_array($projectComposer['require'] ?? null)
        || array_key_exists('aws/aws-sdk-php', $projectComposer['require'])
        || array_key_exists('guzzlehttp/guzzle', $projectComposer['require'])
        || !is_array($projectLock)
        || !is_array($projectLock['packages'] ?? null)
        || !is_array($projectLock['platform'] ?? null)
    ) {
        throw new RuntimeException('The installed starter unexpectedly has an S3 transport dependency or invalid lock.');
    }

    foreach ($expectedRequirements as $package => $version) {
        $projectComposer['require'][$package] = $version;
    }
    $projectComposer['scripts'] = $expectedScripts;
    $awsPackageIndex = count($projectLock['packages']);
    $projectLock['packages'][] = [
        'name' => 'aws/aws-sdk-php',
        'version' => '3.392.1',
    ];
    $guzzlePackageIndex = count($projectLock['packages']);
    $projectLock['packages'][] = [
        'name' => 'guzzlehttp/guzzle',
        'version' => '8.0.2',
    ];
    $expectedPlatformRequirements = [
        'php' => '8.4.*',
        'ext-curl' => '*',
        'ext-json' => '*',
        'ext-pcre' => '*',
        'ext-simplexml' => '*',
    ];

    foreach ($expectedPlatformRequirements as $platformPackage => $platformVersion) {
        $projectLock['platform'][$platformPackage] = $platformVersion;
    }

    $adaptedManifest = [
        'profile' => 'AMAZON_S3_ADR053',
        'boundary' => [
            'region' => 'us-east-1',
            'bucket' => 'phpthis-s3-reference-proof',
            'expected_account_id' => '000000000000',
            'prefix' => 'private-documents/v1/',
            'kms_key_arn' => 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000000',
            'ca_bundle_path' => '/etc/ssl/certs/ca-certificates.crt',
            'ca_bundle_sha256' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'credential_source' => 'PHPTHIS_S3_REFERENCE_PROOF',
        ],
        'sources' => [
            'configuration' => [
                $fixtures['configuration']['path'],
                hash('sha256', $fixtures['configuration']['bytes']),
            ],
            'credential_provider' => [
                $fixtures['credential_provider']['path'],
                hash('sha256', $fixtures['credential_provider']['bytes']),
            ],
            'composition' => [
                $fixtures['composition']['path'],
                hash('sha256', $fixtures['composition']['bytes']),
            ],
            'upload' => [
                $fixtures['upload']['path'],
                hash('sha256', $fixtures['upload']['bytes']),
            ],
            'reconciliation' => [
                $fixtures['reconciliation']['path'],
                hash('sha256', $fixtures['reconciliation']['bytes']),
            ],
            'download' => [
                $fixtures['download']['path'],
                hash('sha256', $fixtures['download']['bytes']),
            ],
            'deletion' => [
                $fixtures['deletion']['path'],
                hash('sha256', $fixtures['deletion']['bytes']),
            ],
        ],
    ];

    $cleanupFailure = null;

    try {
        writeFile($contextPath, $referenceContext . "\n");
        writeFile($checkerPath, $references['checker']);
        writeFile($manifestPath, $references['manifest']);

        $lintResult = runProcess([PHP_BINARY, '-l', $checkerPath], $project, $environment);
        requireSuccess($lintResult, 'The installed Amazon S3 source checker did not pass PHP syntax checking.');

        $checkerFunctionOpening = "function verifyAmazonS3FileTransferSource(): array\n{\n";

        foreach (
            [
                'premature exit' => '    exit(0);',
                'global completion-state bypass' =>
                    "    \$GLOBALS['amazonS3VerificationState']->completed = true; exit(0);",
            ] as $terminationName => $terminationSource
        ) {
            $mutatedChecker = str_replace(
                $checkerFunctionOpening,
                $checkerFunctionOpening . $terminationSource . "\n",
                $references['checker'],
                $checkerMutationCount,
            );

            if ($checkerMutationCount !== 1) {
                throw new RuntimeException('Unable to create an Amazon S3 source-checker reachability mutation.');
            }

            writeFile($checkerPath, $mutatedChecker);
            $terminationResult = runInstalledAmazonS3FileTransferSourceChecker(
                $checkerPath,
                $project,
                $environment,
            );
            requireInstalledAmazonS3FileTransferVerificationFailure(
                $terminationResult,
                "The Amazon S3 source checker accepted a {$terminationName}.",
            );
            requireOutputNotContains($terminationResult, 'amazonS3VerificationState');
        }
        writeFile($checkerPath, $references['checker']);

        requireInstalledAmazonS3FileTransferVerificationFailure(
            runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
            'The unadapted Amazon S3 source checker did not fail closed.',
        );

        if (!unlink($manifestPath) || file_exists($manifestPath) || is_link($manifestPath)) {
            throw new RuntimeException('Unable to remove the Amazon S3 manifest for the missing control.');
        }
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'A missing Amazon S3 manifest did not fail closed.');

        writeFile($manifestPath, "{\n");
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'An invalid Amazon S3 manifest did not fail closed.');

        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        $canonicalManifestBytes = file_get_contents($manifestPath);

        if (!is_string($canonicalManifestBytes)) {
            throw new RuntimeException('Unable to read the canonical Amazon S3 manifest control.');
        }

        $duplicateProfileLine = "  \"profile\": \"AMAZON_S3_ADR053\",\n";
        $duplicateMemberManifest = str_replace(
            $duplicateProfileLine,
            $duplicateProfileLine . $duplicateProfileLine,
            $canonicalManifestBytes,
            $duplicateMemberCount,
        );

        if ($duplicateMemberCount !== 1) {
            throw new RuntimeException('Unable to create the duplicate Amazon S3 manifest control.');
        }

        writeFile($manifestPath, $duplicateMemberManifest);
        requireInstalledAmazonS3FileTransferVerificationFailure(
            runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
            'An Amazon S3 manifest with duplicate JSON members did not fail closed.',
        );

        writeFile($manifestPath, str_repeat('x', 16_385));
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'An oversized Amazon S3 manifest did not fail closed.');

        if (!unlink($manifestPath) || !symlink($composerPath, $manifestPath)) {
            throw new RuntimeException('Unable to create the Amazon S3 manifest symlink control.');
        }
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'A symlinked Amazon S3 manifest did not fail closed.');

        if (!unlink($manifestPath)) {
            throw new RuntimeException('Unable to remove the Amazon S3 manifest symlink control.');
        }

        writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        writeJson($composerPath, $projectComposer);
        writeJson($lockPath, $projectLock);

        foreach ($fixtures as $fixture) {
            $fixturePath = $project . '/' . $fixture['path'];
            requireSuccess(
                runProcess([PHP_BINARY, '-l', $fixturePath], $project, $environment),
                'One Amazon S3 static source fixture did not pass PHP syntax checking.',
            );
        }

        requireExactProcessResult(
            runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
            0,
            "AMAZON S3 FILE TRANSFER SOURCE VERIFY PASS\n",
            '',
            'The adapted Amazon S3 source checker did not select exact success.',
        );

        $canonicalResult = runProcess(
            composerCommand($composerBinary, ['file-transfers:s3:source']),
            $project,
            $environment,
        );
        requireSuccess($canonicalResult, 'The canonical Amazon S3 source-check command failed.');
        requireOutputContains($canonicalResult, "AMAZON S3 FILE TRANSFER SOURCE VERIFY PASS\n");
        requireOutputNotContains($canonicalResult, "AMAZON S3 FILE TRANSFER SOURCE VERIFY FAIL\n");
        requireOutputNotContains($canonicalResult, $project);

        $boundaryMutations = [
            'region' => 'us-west-2',
            'bucket' => 'phpthis-s3-reference-drift',
            'expected_account_id' => '111111111111',
            'prefix' => 'private-documents/v2/',
            'kms_key_arn' => 'arn:aws:kms:us-east-1:000000000000:key/11111111-1111-1111-1111-111111111111',
            'ca_bundle_path' => '/etc/ssl/certs/phpthis-ca.crt',
            'ca_bundle_sha256' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'credential_source' => 'PHPTHIS_S3_REFERENCE_DRIFT',
        ];

        foreach ($boundaryMutations as $boundaryName => $boundaryValue) {
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['boundary'][$boundaryName] = $boundaryValue;
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A drifted {$boundaryName} Amazon S3 boundary was accepted.",
            );
        }
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        foreach (
            [
                'xn--phpthis-s3-reference',
                'sthree-phpthis-s3-reference',
                'amzn-s3-demo-phpthis-reference',
                'phpthis-s3-reference-s3alias',
                'phpthis-s3-reference--ol-s3',
                'phpthis-s3-reference--x-s3',
                'phpthis-s3-reference--table-s3',
            ] as $reservedBucketName
        ) {
            $mutatedConfiguration = str_replace(
                "'bucket' => 'phpthis-s3-reference-proof'",
                "'bucket' => '{$reservedBucketName}'",
                $fixtures['configuration']['bytes'],
                $bucketMutationCount,
            );

            if ($bucketMutationCount !== 1) {
                throw new RuntimeException('One reserved Amazon S3 bucket mutation had no exact configuration owner.');
            }

            writeFile($project . '/' . $fixtures['configuration']['path'], $mutatedConfiguration);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['boundary']['bucket'] = $reservedBucketName;
            $mutatedManifest['sources']['configuration'][1] = hash('sha256', $mutatedConfiguration);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "The reserved Amazon S3 bucket name {$reservedBucketName} was accepted.",
            );
            writeFile($project . '/' . $fixtures['configuration']['path'], $fixtures['configuration']['bytes']);
        }
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        foreach (
            [
                'arn:aws:kms:us-east-1:000000000000:alias/phpthis-reference',
                'arn:aws:kms:us-east-1:000000000000:key/mrk-00000000000000000000000000000000',
                'arn:aws:kms:us-east-1:000000000000:key/phpthis-reference',
                'arn:aws:kms:us-west-2:000000000000:key/00000000-0000-0000-0000-000000000000',
            ] as $forbiddenKmsKeyArn
        ) {
            $mutatedConfiguration = str_replace(
                "'kms_key_arn' => 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000000'",
                "'kms_key_arn' => '{$forbiddenKmsKeyArn}'",
                $fixtures['configuration']['bytes'],
                $kmsMutationCount,
            );

            if ($kmsMutationCount !== 1) {
                throw new RuntimeException('One forbidden Amazon S3 KMS mutation had no exact configuration owner.');
            }

            writeFile($project . '/' . $fixtures['configuration']['path'], $mutatedConfiguration);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['boundary']['kms_key_arn'] = $forbiddenKmsKeyArn;
            $mutatedManifest['sources']['configuration'][1] = hash('sha256', $mutatedConfiguration);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "The forbidden Amazon S3 KMS key ARN {$forbiddenKmsKeyArn} was accepted.",
            );
            writeFile($project . '/' . $fixtures['configuration']['path'], $fixtures['configuration']['bytes']);
        }
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        foreach ($adaptedManifest['boundary'] as $boundaryName => $boundaryValue) {
            $configurationToken = "'{$boundaryName}' => '{$boundaryValue}'";
            $mutatedConfiguration = str_replace(
                $configurationToken,
                "'{$boundaryName}' => 'configuration-drift'",
                $fixtures['configuration']['bytes'],
                $configurationMutationCount,
            );

            if ($configurationMutationCount !== 1) {
                throw new RuntimeException('One Amazon S3 configuration mutation had no exact source owner.');
            }

            writeFile($project . '/' . $fixtures['configuration']['path'], $mutatedConfiguration);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources']['configuration'][1] = hash('sha256', $mutatedConfiguration);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A hash-repinned {$boundaryName} Amazon S3 configuration mutation was accepted.",
            );
        }
        writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        foreach ($fixtures as $role => $fixture) {
            $fixturePath = $project . '/' . $fixture['path'];
            writeFile($fixturePath, $fixture['bytes'] . "\n// unreviewed-{$role}\n");
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "The {$role} Amazon S3 source-hash mutation was accepted.",
            );
            writeFile($fixturePath, $fixture['bytes']);
        }

        foreach ($fixtures as $role => $fixture) {
            $missingSourceManifest = $adaptedManifest;
            $missingSourceManifest['sources'][$role][0] =
                'src/AmazonS3ReferenceProof/Missing' . ucfirst($role) . '.php';
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $missingSourceManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A missing {$role} Amazon S3 reviewed source was accepted.",
            );
        }
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        $duplicatePathManifest = $adaptedManifest;
        $duplicatePathManifest['sources']['deletion'] = $duplicatePathManifest['sources']['download'];
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $duplicatePathManifest);
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'A duplicate Amazon S3 reviewed-source path was accepted.');
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        $duplicateCredentialProviderManifest = $adaptedManifest;
        $duplicateCredentialProviderManifest['sources']['credential_provider'] =
            $duplicateCredentialProviderManifest['sources']['configuration'];
        writeInstalledAmazonS3FileTransferVerificationManifest(
            $manifestPath,
            $duplicateCredentialProviderManifest,
        );
        requireInstalledAmazonS3FileTransferVerificationFailure(
            runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
            'A duplicate Amazon S3 credential-provider source path was accepted.',
        );
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        foreach (['upload', 'credential_provider'] as $symlinkedSourceRole) {
            $symlinkedSourcePath = $project . '/' . $fixtures[$symlinkedSourceRole]['path'];
            $symlinkedSourceBytes = file_get_contents($symlinkedSourcePath);

            if (
                !is_string($symlinkedSourceBytes)
                || !unlink($symlinkedSourcePath)
                || !symlink($composerPath, $symlinkedSourcePath)
            ) {
                throw new RuntimeException('Unable to create an Amazon S3 reviewed-source symlink control.');
            }
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A symlinked {$symlinkedSourceRole} Amazon S3 reviewed source was accepted.",
            );

            if (!unlink($symlinkedSourcePath)) {
                throw new RuntimeException('Unable to remove an Amazon S3 reviewed-source symlink control.');
            }
            writeFile($symlinkedSourcePath, $symlinkedSourceBytes);
        }

        foreach (
            [
                '$configuration->credentialSource',
                'CredentialProvider::memoize(',
                '$memoizedExplicitCredentialProvider',
            ] as $credentialProviderToken
        ) {
            $mutatedCredentialProvider = str_replace(
                $credentialProviderToken,
                strtoupper($credentialProviderToken),
                $fixtures['credential_provider']['bytes'],
                $credentialProviderMutationCount,
            );

            if ($credentialProviderMutationCount < 1) {
                throw new RuntimeException('One Amazon S3 credential-provider mutation had no source owner.');
            }

            writeFile(
                $project . '/' . $fixtures['credential_provider']['path'],
                $mutatedCredentialProvider,
            );
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources']['credential_provider'][1] = hash(
                'sha256',
                $mutatedCredentialProvider,
            );
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                'A hash-repinned Amazon S3 credential-provider token mutation was accepted.',
            );
            writeFile(
                $project . '/' . $fixtures['credential_provider']['path'],
                $fixtures['credential_provider']['bytes'],
            );
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        foreach (
            [
                'CredentialProvider::defaultProvider(',
                'CredentialProvider::chain(',
            ] as $forbiddenCredentialProviderToken
        ) {
            $mutatedCredentialProvider =
                $fixtures['credential_provider']['bytes']
                . "\n// {$forbiddenCredentialProviderToken}\n";
            writeFile(
                $project . '/' . $fixtures['credential_provider']['path'],
                $mutatedCredentialProvider,
            );
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources']['credential_provider'][1] = hash(
                'sha256',
                $mutatedCredentialProvider,
            );
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                'A hash-repinned fallback Amazon S3 credential-provider token was accepted.',
            );
            writeFile(
                $project . '/' . $fixtures['credential_provider']['path'],
                $fixtures['credential_provider']['bytes'],
            );
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        $compositionTokens = [
            'str_starts_with($configuration->caBundlePath,',
            "(\$caBundleStat['mode'] & 0170000) !== 0100000",
            'filesize($configuration->caBundlePath)',
            '$caBundleSize < 1',
            '$caBundleSize > 1048576',
            'hash_equals($configuration->caBundleSha256, $caBundleHash)',
            'new GuzzleHttp\\Client([',
            "'handler' => new GuzzleHttp\\Handler\\CurlHandler([",
            "'transport_sharing' => GuzzleHttp\\TransportSharing::NONE",
            "'allow_redirects' => false",
            "'cookies' => false",
            "'debug' => false",
            "'proxy' => ''",
            "'protocols' => ['https']",
            "'connect_timeout' => 2.0",
            "'timeout' => 10.0",
            "'verify' => \$configuration->caBundlePath",
            'new Aws\\Handler\\Guzzle\\GuzzleHandler($httpClient)',
            'new Aws\\S3\\S3Client([',
            "'http_handler' => \$awsHttpHandler",
            "'version' => '2006-03-01'",
            "'region' => \$configuration->region",
            "'credentials' => \$memoizedExplicitCredentialProvider",
            "'defaults_mode' => 'legacy'",
            "'signature_version' => 'v4'",
            "'auth_scheme_preference' => ['aws.auth#sigv4']",
            "'scheme' => 'https'",
            "'endpoint_discovery' => ['enabled' => false]",
            "'use_aws_shared_config_files' => false",
            "'ignore_configured_endpoint_urls' => true",
            "'use_path_style_endpoint' => false",
            "'bucket_endpoint' => false",
            "'use_accelerate_endpoint' => false",
            "'use_dual_stack_endpoint' => false",
            "'use_fips_endpoint' => false",
            "'s3_us_east_1_regional_endpoint' => 'regional'",
            "'use_arn_region' => false",
            "'disable_multiregion_access_points' => true",
            "'disable_express_session_auth' => true",
            "'request_checksum_calculation' => 'when_supported'",
            "'response_checksum_validation' => 'when_supported'",
            "'disable_request_compression' => true",
            "'retries' => 0",
            "'app_id' => ''",
            "'csm' => false",
            "'stats' => false",
            "'validate' => true",
            "'http' => [",
            'lstat($configuration->caBundlePath)',
            'is_readable($configuration->caBundlePath)',
            'is_link($configuration->caBundlePath)',
            "hash_file('sha256', \$configuration->caBundlePath)",
            '$configuration->caBundleSha256',
        ];
        $serverOperationTokens = [
            'upload' => [
                '->putObject([',
                '->headObject([',
                "'ChecksumSHA256'",
                "'ChecksumMode' => 'ENABLED'",
            ],
            'reconciliation' => [
                '->listObjectVersions([',
                '->headObject([',
                "'Prefix'",
                "'MaxKeys' => 2",
            ],
            'deletion' => [
                '->deleteObject([',
                '->listObjectVersions([',
                '->headObject([',
                "'VersionId'",
                "'Prefix'",
                "'MaxKeys' => 2",
            ],
        ];

        foreach ($serverOperationTokens as $serverRole => $operationTokens) {
            foreach ($operationTokens as $operationToken) {
                $mutatedBytes = str_replace(
                    $operationToken,
                    strtoupper($operationToken),
                    $fixtures[$serverRole]['bytes'],
                    $operationMutationCount,
                );

                if ($operationMutationCount < 1) {
                    throw new RuntimeException('One Amazon S3 operation-shape mutation had no source owner.');
                }

                writeFile($project . '/' . $fixtures[$serverRole]['path'], $mutatedBytes);
                $mutatedManifest = $adaptedManifest;
                $mutatedManifest['sources'][$serverRole][1] = hash('sha256', $mutatedBytes);
                writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
                requireInstalledAmazonS3FileTransferVerificationFailure(
                    runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                    "A hash-repinned {$serverRole} Amazon S3 operation-shape mutation was accepted.",
                );
                writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
                writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
            }
        }

        foreach (
            [
                "'StorageClass' => 'STANDARD'",
                "\$headResult->hasKey('StorageClass')",
                "\$headResult->hasKey('ArchiveStatus')",
                "\$headResult->hasKey('Restore')",
                "\$headResult->hasKey('Expiration')",
            ] as $standardStorageToken
        ) {
            $mutatedUpload = str_replace(
                $standardStorageToken,
                strtoupper($standardStorageToken),
                $fixtures['upload']['bytes'],
                $standardStorageMutationCount,
            );

            if ($standardStorageMutationCount !== 1) {
                throw new RuntimeException('One Amazon S3 STANDARD-storage mutation had no exact upload owner.');
            }

            writeFile($project . '/' . $fixtures['upload']['path'], $mutatedUpload);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources']['upload'][1] = hash('sha256', $mutatedUpload);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                'A hash-repinned Amazon S3 STANDARD-storage mutation was accepted.',
            );
            writeFile($project . '/' . $fixtures['upload']['path'], $fixtures['upload']['bytes']);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        $requiredTokens = [
            ...$compositionTokens,
            "'ExpectedBucketOwner'",
            "'IfNoneMatch' => '*'",
            "'ChecksumAlgorithm' => 'SHA256'",
            "'ChecksumMode' => 'ENABLED'",
            "'MaxKeys' => 2",
            "'ServerSideEncryption' => 'aws:kms'",
            "'BucketKeyEnabled' => false",
            'private-documents/v1/',
            "'VersionId'",
        ];

        foreach ($requiredTokens as $requiredToken) {
            $mutatedFixtures = $fixtures;
            $mutatedManifest = $adaptedManifest;
            $changed = false;

            foreach ($mutatedFixtures as $role => $fixture) {
                if (!str_contains($fixture['bytes'], $requiredToken)) {
                    continue;
                }

                $mutatedFixtures[$role]['bytes'] = str_replace(
                    $requiredToken,
                    strtoupper($requiredToken),
                    $fixture['bytes'],
                );
                $mutatedManifest['sources'][$role][1] = hash(
                    'sha256',
                    $mutatedFixtures[$role]['bytes'],
                );
                $changed = true;
            }

            if (!$changed) {
                throw new RuntimeException('One required Amazon S3 source-token mutation had no owner.');
            }

            writeInstalledAmazonS3FileTransferVerificationFixtures($project, $mutatedFixtures);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                'A hash-repinned required Amazon S3 source-token mutation was accepted.',
            );
            writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        foreach (
            [
                "'endpoint' =>",
                'Aws\\default_http_handler',
                'GuzzleHttp\\Handler\\StreamHandler',
                'GuzzleHttp\\Utils::chooseHandler',
                'StorageInterface',
                'StorageFacade',
                'DiskRegistry',
                'S3ClientRegistry',
                'createPresignedPost',
                'MultipartUploader',
            ] as $forbiddenToken
        ) {
            $mutatedComposition = $fixtures['composition']['bytes'] . "\n// {$forbiddenToken}\n";
            writeFile($project . '/' . $fixtures['composition']['path'], $mutatedComposition);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources']['composition'][1] = hash('sha256', $mutatedComposition);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                'A hash-repinned forbidden Amazon S3 source token was accepted.',
            );
            writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        foreach (['upload', 'reconciliation', 'deletion'] as $serverRole) {
            $mutatedBytes = str_replace(
                "'ExpectedBucketOwner'",
                "'EXPECTED_BUCKET_OWNER_REMOVED'",
                $fixtures[$serverRole]['bytes'],
            );
            writeFile($project . '/' . $fixtures[$serverRole]['path'], $mutatedBytes);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources'][$serverRole][1] = hash('sha256', $mutatedBytes);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A {$serverRole} source without ExpectedBucketOwner was accepted.",
            );
            writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        $downloadBytes = $fixtures['download']['bytes'] . "\n// 'ExpectedBucketOwner'\n";
        writeFile($project . '/' . $fixtures['download']['path'], $downloadBytes);
        $downloadManifest = $adaptedManifest;
        $downloadManifest['sources']['download'][1] = hash('sha256', $downloadBytes);
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $downloadManifest);
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'A browser download requiring ExpectedBucketOwner was accepted.');
        writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
        writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);

        foreach (["'X-Amz-SignedHeaders'", "'host'"] as $downloadToken) {
            $mutatedDownload = str_replace(
                $downloadToken,
                strtoupper($downloadToken),
                $fixtures['download']['bytes'],
            );
            writeFile($project . '/' . $fixtures['download']['path'], $mutatedDownload);
            $mutatedManifest = $adaptedManifest;
            $mutatedManifest['sources']['download'][1] = hash('sha256', $mutatedDownload);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $mutatedManifest);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                'A browser download without the exact host-only signed-header shape was accepted.',
            );
            writeInstalledAmazonS3FileTransferVerificationFixtures($project, $fixtures);
            writeInstalledAmazonS3FileTransferVerificationManifest($manifestPath, $adaptedManifest);
        }

        foreach ($expectedRequirements as $package => $version) {
            $mutatedComposer = $projectComposer;
            $mutatedComposer['require'][$package] = $version . '-drift';
            writeJson($composerPath, $mutatedComposer);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A drifted {$package} Composer requirement was accepted.",
            );
        }
        writeJson($composerPath, $projectComposer);

        $mutatedLock = $projectLock;
        $mutatedLock['packages'][$awsPackageIndex] = [
            'name' => 'aws/aws-sdk-php',
            'version' => '3.392.0',
        ];
        writeJson($lockPath, $mutatedLock);
        requireInstalledAmazonS3FileTransferVerificationFailure(
            runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
            'A drifted aws/aws-sdk-php lock entry was accepted.',
        );

        $mutatedLock = $projectLock;
        $mutatedLock['packages'][$guzzlePackageIndex] = [
            'name' => 'guzzlehttp/guzzle',
            'version' => '8.0.1',
        ];
        writeJson($lockPath, $mutatedLock);
        requireInstalledAmazonS3FileTransferVerificationFailure(
            runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
            'A drifted guzzlehttp/guzzle lock entry was accepted.',
        );

        foreach (
            [
                'aws/aws-sdk-php' => '3.392.1',
                'guzzlehttp/guzzle' => '8.0.2',
            ] as $package => $version
        ) {
            $duplicatePackageLock = $projectLock;
            $duplicatePackageLock['packages'][] = [
                'name' => $package,
                'version' => $version,
            ];
            writeJson($lockPath, $duplicatePackageLock);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "Duplicate {$package} lock entries were accepted.",
            );
        }

        foreach ($expectedPlatformRequirements as $package => $version) {
            $mutatedLock = $projectLock;
            $mutatedLock['platform'][$package] = $version . '-drift';
            writeJson($lockPath, $mutatedLock);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A drifted {$package} lock platform entry was accepted.",
            );
        }
        writeJson($lockPath, $projectLock);

        foreach ([$composerPath, $lockPath] as $composerFilePath) {
            $composerFileBytes = file_get_contents($composerFilePath);

            if (
                !is_string($composerFileBytes)
                || !unlink($composerFilePath)
                || !symlink($contextPath, $composerFilePath)
            ) {
                throw new RuntimeException('Unable to create one Composer-file symlink control.');
            }

            requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'A symlinked Composer file was accepted.');

            if (!unlink($composerFilePath)) {
                throw new RuntimeException('Unable to remove one Composer-file symlink control.');
            }

            writeFile($composerFilePath, $composerFileBytes);
        }

        foreach (array_keys($expectedScripts) as $scriptName) {
            $mutatedComposer = $projectComposer;
            unset($mutatedComposer['scripts'][$scriptName]);
            writeJson($composerPath, $mutatedComposer);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A missing {$scriptName} Amazon S3 gate script was accepted.",
            );
        }

        foreach (['file-transfers:s3:verify', 'test', 'check'] as $orderedScript) {
            $mutatedComposer = $projectComposer;
            $script = $expectedScripts[$orderedScript];

            [$script[0], $script[1]] = [$script[1], $script[0]];
            $mutatedComposer['scripts'][$orderedScript] = $script;
            writeJson($composerPath, $mutatedComposer);
            requireInstalledAmazonS3FileTransferVerificationFailure(
                runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment),
                "A reordered {$orderedScript} Amazon S3 gate was accepted.",
            );
        }

        $mutatedComposer = $projectComposer;
        $mutatedComposer['scripts']['test'] = ['@test:application'];
        writeJson($composerPath, $mutatedComposer);
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'An Amazon S3 complete-gate skip was accepted.');

        $mutatedComposer = $projectComposer;
        $mutatedComposer['scripts']['check'][] = '@test:application';
        writeJson($composerPath, $mutatedComposer);
        requireInstalledAmazonS3FileTransferVerificationFailure(runInstalledAmazonS3FileTransferSourceChecker($checkerPath, $project, $environment), 'An extra Amazon S3 complete-gate bypass was accepted.');
        writeJson($composerPath, $projectComposer);
    } finally {
        try {
            if (is_dir($toolsPath) && !is_link($toolsPath)) {
                removeDirectory($toolsPath);
            }

            if (is_dir($sourceRoot) && !is_link($sourceRoot)) {
                removeDirectory($sourceRoot);
            }

            if (
                file_exists($toolsPath)
                || is_link($toolsPath)
                || file_exists($sourceRoot)
                || is_link($sourceRoot)
            ) {
                throw new RuntimeException('Amazon S3 verification proof cleanup left a transient path.');
            }
        } catch (Throwable $failure) {
            $cleanupFailure = $failure;
        } finally {
            writeFile($contextPath, $originalContext);
            writeFile($composerPath, $originalComposer);
            writeFile($lockPath, $originalLock);

            if (
                file_get_contents($contextPath) !== $originalContext
                || file_get_contents($composerPath) !== $originalComposer
                || file_get_contents($lockPath) !== $originalLock
            ) {
                throw new RuntimeException('Amazon S3 verification proof did not restore starter files exactly.');
            }
        }

        if ($cleanupFailure instanceof Throwable) {
            throw $cleanupFailure;
        }
    }

    if (is_dir($project . '/vendor/aws') || is_file($project . '/vendor/autoload.php.tmp')) {
        throw new RuntimeException('The Amazon S3 verification proof unexpectedly installed an AWS runtime dependency.');
    }

    fwrite(STDOUT, "PASS installed Amazon S3 file-transfer verification structure\n");

    return 'installed-amazon-s3-file-transfer-verification-reference-proved';
}
