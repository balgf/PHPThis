<?php

declare(strict_types=1);

require __DIR__ . '/metadata-s3/bootstrap.php';
require __DIR__ . '/metadata-s3/tests/Fixture.php';
require __DIR__ . '/metadata-s3/tests/lifecycle.php';
require __DIR__ . '/metadata-s3/tests/contracts.php';

verifyMetadataS3Lifecycle();
verifyMetadataS3FailureAccounting();
verifyMetadataS3Contracts();
$values = runBoundedMaintainerProcess([PHP_BINARY, __DIR__ . '/metadata-s3/tests/values.php'], dirname(__DIR__), [], 3000, 8192, 8192);
if ($values !== ['exit_code' => 0, 'stdout' => "PASS metadata S3 values: 17 cases; every upload error and structural precedence\n", 'stderr' => '']) {
    throw new RuntimeException('Metadata S3 value proof failed.');
}
$http = runBoundedMaintainerProcess(['python3', __DIR__ . '/metadata-s3/tests/http-proof.py'], dirname(__DIR__),
    ['PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'], 30_000, 8192, 8192);
if ($http['exit_code'] !== 0 || $http['stderr'] !== ''
    || $http['stdout'] !== "PASS metadata S3 HTTP: 65 cases; synthetic SDK only; no AWS calls\n") {
    throw new RuntimeException('Metadata S3 HTTP proof failed: ' . $http['stdout'] . $http['stderr']);
}
$transport = runBoundedMaintainerProcess(['python3', __DIR__ . '/metadata-s3/tests/transport.py'], dirname(__DIR__),
    ['PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'], 30_000, 8192, 8192);
if ($transport !== ['exit_code' => 0, 'stdout' => "PASS metadata S3 transport: 9 exact requests; REFERENCE_ONLY\n", 'stderr' => '']) {
    throw new RuntimeException('Metadata S3 transport proof failed: ' . $transport['stdout'] . $transport['stderr']);
}
fwrite(STDOUT, "PASS metadata S3 lifecycle, values and HTTP (REFERENCE_ONLY; no AWS evidence)\n");
