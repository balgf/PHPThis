<?php

declare(strict_types=1);

use GeneratedS3Recipe\TestToneBytes;
use GeneratedS3Recipe\TestToneProducer;
use GeneratedS3Recipe\Tests\Fixture;
use function GeneratedS3Recipe\Tests\requireProof;

function verifyGeneratedTone(): void
{
    $tone = TestToneProducer::generate();
    requireProof(strlen($tone->bytes->value) === 32_044
        && $tone->bytes->sha256 === '7d56186cf1e74ae7a6413d3c4975188ff6464025df047f81326a40dbc83087b0', 'known generated PCM output');
    foreach (['', substr($tone->bytes->value, 0, -1), $tone->bytes->value . 'x', str_repeat('x', 32_044),
        'RIFX' . substr($tone->bytes->value, 4), substr($tone->bytes->value, 0, -1) . "\0"] as $invalid) {
        requireExportFailure(static fn () => TestToneBytes::fromProducerOutput($invalid));
    }
    $fixture = new Fixture();
    try {
        $fixture->database->executeStatement('DROP TABLE inventory');
        $id = $fixture->operation()->generateTestTone($tone);
        requireProof($fixture->remote->calls === ['PutObject', 'HeadObject'] && $fixture->record()->state === 'accepted', 'tone accepted through existing version validation');
        requireProof($fixture->operation()->read($id)->value === $tone->bytes->value, 'tone exact internal read');
        $fixture->remote->fault = 'corrupt';
        requireExportFailure(static fn () => $fixture->operation()->read($id));
        requireProof($fixture->remote->lastBody?->getMetadata() === [], 'corrupt tone read closes body');
        $fixture->remote->fault = ''; $fixture->clock->value += 86_400;
        $before = count($fixture->remote->calls);
        requireExportFailure(static fn () => $fixture->operation()->read($id));
        requireProof(count($fixture->remote->calls) === $before, 'tone expiry before I/O');
        $fixture->remote->fault = 'lost_delete';
        try { $fixture->operation()->expire($id); throw new LogicException('Lost delete accepted.'); }
        catch (RuntimeException) {}
        requireProof($fixture->record()->state === 'deletion_pending' && $fixture->record()->attempts === 1, 'tone interrupted delete retains accounting');
        $fixture->remote->fault = '';
        requireProof($fixture->operation()->expire($id) === 'deleted', 'tone reconciles exact deletion');
        $before = count($fixture->remote->calls);
        requireProof($fixture->operation()->expire($id) === 'already_deleted' && count($fixture->remote->calls) === $before, 'tone deletion does not replay');
    } finally { $fixture->cleanup(); }
    verifyToneWriteFailure($tone, 'lost_put');
    verifyToneWriteFailure($tone, 'put412');
    verifyToneWriteFailure($tone, 'head_denied');
    verifyToneMetadataFailure($tone, 'reservation');
    verifyToneMetadataFailure($tone, 'promotion');
    verifyToneMetadataFailure($tone, 'acceptance');
    verifyToneSharedCapacity($tone, false);
    verifyToneSharedCapacity($tone, true);
}

function verifyToneWriteFailure(TestToneBytes $tone, string $fault): void
{
    $fixture = new Fixture();
    try {
        $fixture->remote->fault = $fault;
        requireExportFailure(static fn () => $fixture->operation()->generateTestTone($tone));
        $record = $fixture->record();
        requireProof($record->bytes === 32_044 && $record->sha256 === $tone->bytes->sha256, 'tone reserves exact bytes and digest');
        requireExportFailure(static fn () => $fixture->operation()->read($record->id));
        $fixture->remote->fault = '';
        if ($fault === 'put412') {
            requireProof($record->state === 'failed' && $fixture->operation()->reconcile($record->id) === 'not_due'
                && $fixture->remote->calls === ['PutObject'], 'tone collision never adopted');
        } else {
            requireProof($record->state === 'reconciliation_required', 'tone uncertainty retains reservation');
            if ($fault === 'lost_put') {
                $fixture->remote->present = false;
                requireProof($fixture->operation()->reconcile($record->id) === 'unresolved', 'empty uncertain tone listing stays unresolved');
                $fixture->remote->present = true;
            }
            requireProof($fixture->operation()->reconcile($record->id) === 'reconciled'
                && $fixture->operation()->read($record->id)->value === $tone->bytes->value, 'tone late-write recovery');
            requireProof(count(array_filter($fixture->remote->calls, static fn (string $name): bool => $name === 'PutObject')) === 1, 'tone Put never repeated');
        }
    } finally { $fixture->cleanup(); }
}

function verifyToneMetadataFailure(TestToneBytes $tone, string $stage): void
{
    $fixture = new Fixture();
    try {
        if ($stage === 'reservation') {
            $fixture->database->executeStatement("CREATE TRIGGER tone_failure BEFORE INSERT ON exports BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        } elseif ($stage === 'promotion') {
            $fixture->database->executeStatement("CREATE TRIGGER tone_failure BEFORE UPDATE ON exports WHEN NEW.state = 'stored' BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        } else {
            $fixture->database->executeStatement("CREATE TRIGGER tone_failure BEFORE UPDATE ON exports WHEN NEW.state = 'accepted' BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        }
        try { $fixture->operation()->generateTestTone($tone); throw new LogicException('Tone metadata failure ignored.'); }
        catch (PDOException) {}
        if ($stage === 'reservation') {
            requireProof($fixture->remote->calls === [], 'tone reservation failure stops before S3');
        } else {
            requireProof($fixture->record()->state === ($stage === 'promotion' ? 'pending' : 'stored'), 'tone interruption retains durable ownership');
            requireExportFailure(static fn () => $fixture->operation()->read($fixture->record()->id));
            $fixture->database->executeStatement('DROP TRIGGER tone_failure');
            requireProof($fixture->operation()->reconcile($fixture->record()->id) === 'reconciled', 'tone metadata recovery');
        }
    } finally { $fixture->cleanup(); }
}

function verifyToneSharedCapacity(TestToneBytes $tone, bool $history): void
{
    $fixture = new Fixture();
    try {
        $fixture->database->executeStatement("WITH RECURSIVE items(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM items WHERE n < :count)
            INSERT INTO exports (export_id, size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target, reason)
            SELECT printf('%032x', n), 65536, :digest, :state, NULL, 0, 1, 86401, :target, 'fixture' FROM items",
            ['count' => $history ? 256 : 31, 'digest' => hash('sha256', 'fixture'),
                'state' => $history ? 'failed' : 'reconciliation_required', 'target' => $fixture->remote->target->fingerprint()]);
        if ($history) {
            requireExportFailure(static fn () => $fixture->operation()->generateTestTone($tone));
            requireProof($fixture->remote->calls === [], 'tone respects retained-identity cap');
        } else {
            $fixture->operation()->generateTestTone($tone);
            requireExportFailure(static fn () => $fixture->operation()->generate());
            requireExportFailure(static fn () => $fixture->operation()->generateTestTone($tone));
            requireProof($fixture->remote->calls === ['PutObject', 'HeadObject'], 'CSV and tone share live capacity including ambiguity');
        }
    } finally { $fixture->cleanup(); }
}
