<?php

declare(strict_types=1);

use GeneratedS3Recipe\ExportLock;
use GeneratedS3Recipe\ExportRoot;
use GeneratedS3Recipe\S3Settings;
use GeneratedS3Recipe\S3Target;
use GeneratedS3Recipe\Tests\Fixture;
use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;
use function GeneratedS3Recipe\Tests\requireProof;

require_once dirname(__DIR__, 2) . '/process-support.php';
require_once dirname(__DIR__, 3) . '/verification/SyntaxProfile.php';
require_once dirname(__DIR__, 3) . '/verification/EnvironmentAccessProfile.php';

function verifyGeneratedS3Contracts(): void
{
    $root = dirname(__DIR__);
    $sources = ['src/Values.php', 'src/ExportLock.php', 'src/S3Settings.php', 'src/ReferenceEnvironment.php',
        'src/CredentialProvider.php', 'src/Composition.php', 'src/InventoryS3.php', 'src/InventoryExports.php',
        'src/ExportInvocation.php', 'console.php'];
    foreach ($sources as $path) {
        $source = file_get_contents($root . '/' . $path);
        if (!is_string($source)) { throw new LogicException('Source unavailable.'); }
        requireProof(SyntaxProfile::failures($source, $path) === [], 'application syntax profile');
        $inspection = EnvironmentAccessProfile::inspect($source, $path);
        requireProof($inspection['failures'] === [] && ($path === 'src/ReferenceEnvironment.php' || $inspection['reads'] === []), 'one environment owner');
        requireProof(!str_contains($source, 'RequestUpload') && !str_contains($source, 'is_uploaded_file'), 'separate generated provenance');
    }
    foreach ([[], ['bad-secret'], ['generate', 'unexpected-secret'], ['verify'], ['verify', 'wrong-secret'], ['expire-one', str_repeat('a', 33)]] as $arguments) {
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', ...$arguments], $root, [], 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 2, 'stdout' => '', 'stderr' => ($arguments === ['bad-secret']
            ? "{\"error\":\"unknown_command\"}\n" : "{\"error\":\"invalid_arguments\"}\n")], 'argument redaction and pre-I/O failure');
    }
    foreach ([['generate'], ['verify', str_repeat('a', 32)], ['reconcile-one', str_repeat('a', 32)], ['expire-one', str_repeat('a', 32)]] as $arguments) {
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', ...$arguments], $root, [], 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"command_failed\"}\n"], 'missing role configuration redacted');
    }
    $gate = runBoundedMaintainerProcess([PHP_BINARY, $root . '/verify-adoption.php'], $root, [], 3000, 4096, 4096);
    requireProof($gate === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"real_aws_and_deployment_evidence_required\"}\n"], 'offline receipt cannot grant adoption');
    foreach (['xn--bucket', 'bucket.suffix', 'bucket--x-s3', 'ab', str_repeat('a', 64)] as $bucket) {
        try { S3Target::fromInputs($bucket, 'us-east-1', '000000000000', 'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001');
            throw new LogicException('Invalid bucket accepted.'); } catch (InvalidArgumentException) {}
    }
    foreach (['us-gov-east-1', 'us-iso-east-1', 'cn-north-1'] as $region) {
        try { S3Target::fromInputs('fixture-bucket', $region, '000000000000', 'arn:aws:kms:' . $region . ':000000000000:key/00000000-0000-0000-0000-000000000001');
            throw new LogicException('Unsupported partition accepted.'); } catch (InvalidArgumentException) {}
    }
    foreach ([false, '', 'relative', '/tmp/../hidden'] as $path) {
        try { ExportRoot::fromInput($path); throw new LogicException('Invalid root accepted.'); } catch (InvalidArgumentException) {}
    }
    $fixture = new Fixture();
    try {
        $competing = new ExportLock($fixture->root);
        requireExportFailure(static fn () => $competing->acquire(false));
        requireExportFailure(static fn () => $competing->acquire(true));
        $fixture->lock->release(); $fixture->lock->acquire(false); $competing->acquire(false);
        $writer = new ExportLock($fixture->root);
        requireExportFailure(static fn () => $writer->acquire(true));
        $competing->release(); $fixture->lock->release(); $writer->acquire(true); $writer->release();
        $fixture->lock->acquire(true);
        $id = $fixture->operation()->generate();
        $fixture->lock->release();
        $ca = $fixture->root->path . '/reviewed-ca'; file_put_contents($ca, 'synthetic CA bytes');
        $environment = [
            'PHPTHIS_EXPORT_DELETION_ROOT' => $fixture->root->path,
            'PHPTHIS_EXPORT_DELETION_BUCKET' => 'fixture-bucket', 'PHPTHIS_EXPORT_DELETION_REGION' => 'us-east-1',
            'PHPTHIS_EXPORT_DELETION_ACCOUNT' => '000000000000', 'PHPTHIS_EXPORT_DELETION_KMS' => $fixture->remote->target->kms,
            'PHPTHIS_EXPORT_DELETION_CA_PATH' => $ca, 'PHPTHIS_EXPORT_DELETION_CA_SHA256' => hash('sha256', 'synthetic CA bytes'),
            'PHPTHIS_EXPORT_DELETION_ACCESS_KEY' => 'ASIA' . str_repeat('A', 16), 'PHPTHIS_EXPORT_DELETION_SECRET' => str_repeat('s', 40),
            'PHPTHIS_EXPORT_DELETION_TOKEN' => str_repeat('t', 32), 'PHPTHIS_EXPORT_DELETION_EXPIRES' => (string) (time() + 3600),
        ];
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', 'expire-one', $id->value], $root, $environment, 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 0, 'stdout' => "{\"command\":\"expire-one\",\"outcome\":\"not_due\"}\n", 'stderr' => ''], 'real console expected no-I/O outcome');
        // Other processes cannot inherit the deletion tuple as a fallback.
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', 'verify', $id->value], $root, $environment, 3000, 4096, 4096);
        requireProof($result['exit_code'] === 1 && $result['stdout'] === '' && $result['stderr'] === "{\"error\":\"command_failed\"}\n", 'role input separation');
        $settings = S3Settings::fromInputs($fixture->root->path, 'fixture-bucket', 'us-east-1', '000000000000', $fixture->remote->target->kms,
            $ca, hash('sha256', 'synthetic CA bytes'), 'ASIA' . str_repeat('A', 16), str_repeat('s', 40), str_repeat('t', 32), (string) (time() + 120));
        requireExportFailure(static fn () => $settings->verify(time()));
        unlink($ca);
    } finally {
        if (is_file($fixture->root->path . '/reviewed-ca')) { unlink($fixture->root->path . '/reviewed-ca'); }
        $fixture->cleanup();
    }
}
