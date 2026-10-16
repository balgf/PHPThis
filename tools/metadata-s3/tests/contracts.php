<?php

declare(strict_types=1);

use MetadataS3Recipe\CapacityUnavailable;
use MetadataS3Recipe\DocumentLock;
use MetadataS3Recipe\DocumentRoot;
use MetadataS3Recipe\S3Settings;
use MetadataS3Recipe\Tests\Fixture;
use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;
use function MetadataS3Recipe\Tests\requireProof;

require_once dirname(__DIR__, 2) . '/process-support.php';
require_once dirname(__DIR__, 3) . '/verification/SyntaxProfile.php';
require_once dirname(__DIR__, 3) . '/verification/EnvironmentAccessProfile.php';

function verifyMetadataS3Contracts(): void
{
    $root = dirname(__DIR__);
    $sources = ['src/Values.php', 'src/DocumentInput.php', 'src/DocumentLock.php', 'src/S3Settings.php', 'src/ReferenceEnvironment.php',
        'src/CredentialProvider.php', 'src/Composition.php', 'src/VerifiedUpload.php', 'src/DocumentS3.php', 'src/Documents.php',
        'src/DocumentInvocation.php', 'src/UploadDocument.php', 'src/HttpComposition.php', 'http.php', 'public/index.php', 'console.php'];
    foreach ($sources as $path) {
        $source = file_get_contents($root . '/' . $path);
        if (!is_string($source)) { throw new LogicException('Source unavailable.'); }
        requireProof(SyntaxProfile::failures($source, $path) === [], 'application syntax: ' . $path);
        $inspection = EnvironmentAccessProfile::inspect($source, $path);
        requireProof($inspection['failures'] === [] && ($path === 'src/ReferenceEnvironment.php' || $inspection['reads'] === []), 'one environment owner');
    }
    foreach ([[], ['bad-secret'], ['expire-one'], ['expire-one', str_repeat('a', 32)], ['expire-one', str_repeat('a', 32), '0'],
        ['expire-one', str_repeat('a', 32), '9999999999999999999'], ['reconcile-one', 'bad-id', '1'],
        ['reconcile-one', str_repeat('a', 32), '1', 'extra-secret']] as $arguments) {
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', ...$arguments], $root, [], 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 2, 'stdout' => '', 'stderr' => ($arguments === ['bad-secret']
            ? "{\"error\":\"unknown_command\"}\n" : "{\"error\":\"invalid_arguments\"}\n")], 'argument rejection and redaction before I/O');
    }
    foreach (['reconcile-one', 'expire-one'] as $command) {
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', $command, str_repeat('a', 32), '1'], $root, [], 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"command_failed\"}\n"], 'missing role configuration redacted');
    }
    $gate = runBoundedMaintainerProcess([PHP_BINARY, $root . '/verify-adoption.php'], $root, [], 3000, 4096, 4096);
    requireProof($gate === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"real_aws_and_deployment_evidence_required\"}\n"], 'offline evidence cannot grant adoption');
    foreach ([false, '', 'relative', '/tmp/../hidden'] as $path) {
        try { DocumentRoot::fromInput($path); throw new LogicException('Invalid root accepted.'); } catch (InvalidArgumentException) {}
    }
    $fixture = new Fixture();
    try {
        $id = $fixture->seed('accepted');
        $competing = new DocumentLock($fixture->root);
        try { $competing->acquire(true); throw new LogicException('Contention accepted.'); } catch (CapacityUnavailable) {}
        $fixture->lock->release(); $fixture->lock->acquire(false); $competing->acquire(false);
        $writer = new DocumentLock($fixture->root);
        try { $writer->acquire(true); throw new LogicException('Shared reader bypassed.'); } catch (CapacityUnavailable) {}
        $competing->release(); $fixture->lock->release(); $writer->acquire(true); $writer->release();
        $ca = $fixture->root->path . '/reviewed-ca'; file_put_contents($ca, 'synthetic CA bytes');
        $environment = [
            'PHPTHIS_DOCUMENT_DELETION_ROOT' => $fixture->root->path,
            'PHPTHIS_DOCUMENT_DELETION_BUCKET' => 'fixture-bucket', 'PHPTHIS_DOCUMENT_DELETION_REGION' => 'us-east-1',
            'PHPTHIS_DOCUMENT_DELETION_ACCOUNT' => '000000000000', 'PHPTHIS_DOCUMENT_DELETION_KMS' => $fixture->remote->target->kms,
            'PHPTHIS_DOCUMENT_DELETION_CA_PATH' => $ca, 'PHPTHIS_DOCUMENT_DELETION_CA_SHA256' => hash('sha256', 'synthetic CA bytes'),
            'PHPTHIS_DOCUMENT_DELETION_ACCESS_KEY' => 'ASIA' . str_repeat('A', 16), 'PHPTHIS_DOCUMENT_DELETION_SECRET' => str_repeat('s', 40),
            'PHPTHIS_DOCUMENT_DELETION_TOKEN' => str_repeat('t', 32), 'PHPTHIS_DOCUMENT_DELETION_EXPIRES' => (string) (time() + 3600),
        ];
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', 'expire-one', $id->value, '1'], $root, $environment, 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 0, 'stdout' => "{\"command\":\"expire-one\",\"outcome\":\"not_due\"}\n", 'stderr' => ''], 'real console expected outcome without remote I/O');
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', 'reconcile-one', $id->value, '1'], $root, $environment, 3000, 4096, 4096);
        requireProof($result === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"command_failed\"}\n"], 'role input separation');
        $settings = S3Settings::fromInputs($fixture->root->path, 'fixture-bucket', 'us-east-1', '000000000000', $fixture->remote->target->kms,
            $ca, hash('sha256', 'synthetic CA bytes'), 'ASIA' . str_repeat('A', 16), str_repeat('s', 40), str_repeat('t', 32), (string) (time() + 120));
        requireDocumentFailure(static fn () => $settings->verify(time()));
        chmod($fixture->root->path . '/documents.sqlite', 0644);
        requireDocumentFailure(static fn () => $fixture->root->verify());
        chmod($fixture->root->path . '/documents.sqlite', 0600);
        unlink($ca);
    } finally {
        if (is_file($fixture->root->path . '/reviewed-ca')) { unlink($fixture->root->path . '/reviewed-ca'); }
        $fixture->cleanup();
    }
}
