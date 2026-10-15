<?php

declare(strict_types=1);

require __DIR__ . '/generated-s3/bootstrap.php';
require __DIR__ . '/generated-s3/tests/Fixture.php';
require __DIR__ . '/generated-s3/tests/lifecycle.php';
require __DIR__ . '/generated-s3/tests/contracts.php';

verifyGeneratedS3Lifecycle();
verifyGeneratedS3Bounds();
verifyGeneratedS3MetadataFailures();
verifyGeneratedS3Contracts();
$transport = runBoundedMaintainerProcess(['python3', __DIR__ . '/generated-s3/tests/transport.py'], dirname(__DIR__),
    ['PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'], 30_000, 8192, 8192);
if ($transport['exit_code'] !== 0 || $transport['stderr'] !== ''
    || $transport['stdout'] !== "PASS generated S3 transport: 18 exact requests; REFERENCE_ONLY\n") {
    throw new RuntimeException('Generated S3 loopback proof failed.');
}
fwrite(STDOUT, "PASS generated S3 lifecycle and bounds (REFERENCE_ONLY; no AWS evidence)\n");
