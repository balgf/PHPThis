<?php

declare(strict_types=1);

use GeneratedS3Recipe\ExportBytes;
use GeneratedS3Recipe\ExportId;
use GeneratedS3Recipe\ExportUnavailable;
use GeneratedS3Recipe\InventoryLine;
use GeneratedS3Recipe\ObjectVersion;
use GeneratedS3Recipe\Tests\Fixture;
use PHPThis\Database\QueryBudget;
use function GeneratedS3Recipe\Tests\requireProof;

/** @param Closure(): mixed $operation */
function requireExportFailure(Closure $operation): void
{
    try { $operation(); } catch (ExportUnavailable) { return; }
    throw new LogicException('Expected export refusal.');
}

function verifyGeneratedS3Lifecycle(): void
{
    foreach (['', str_repeat('a', 65_537)] as $invalid) { requireExportFailure(static fn () => ExportBytes::fromBytes($invalid)); }
    foreach (['', 'null', "bad\nversion", str_repeat('x', 1025)] as $invalid) { requireExportFailure(static fn () => ObjectVersion::fromInput($invalid)); }
    foreach ([['sku' => '=FORMULA', 'quantity' => 1], ['sku' => 'A', 'quantity' => '1'], ['sku' => 'A', 'quantity' => -1]] as $invalid) {
        requireExportFailure(static fn () => InventoryLine::fromDatabaseRow($invalid));
    }
    $fixture = new Fixture();
    try {
        $fixture->database->executeStatement("INSERT INTO inventory (sku, quantity) VALUES ('SKU-B', 2), ('SKU-A', 0)");
        $id = $fixture->operation()->generate();
        requireProof($fixture->remote->calls === ['PutObject', 'HeadObject'] && $fixture->record()->state === 'accepted', 'write and head acceptance');
        requireProof($fixture->remote->bytes === "sku,quantity\nSKU-A,0\nSKU-B,2\n", 'bounded ordered serialization');
        $bytes = $fixture->operation()->read($id);
        requireProof($bytes->value === $fixture->remote->bytes && $fixture->remote->lastBody?->getMetadata() === [], 'read exact bytes and close handle');
        foreach (['corrupt', 'wrong_kms', 'wrong_metadata', 'storage_class'] as $fault) {
            $fixture->remote->fault = $fault;
            requireExportFailure(static fn () => $fixture->operation()->read($id));
            requireProof($fixture->remote->lastBody?->getMetadata() === [], 'failure closes read handle');
        }
        $fixture->remote->fault = '';
        $fixture->clock->value += 86_400;
        $before = count($fixture->remote->calls);
        requireExportFailure(static fn () => $fixture->operation()->read($id));
        requireProof(count($fixture->remote->calls) === $before, 'expiry rejects before remote read');
        $fixture->remote->beforeDelete = static function () use ($fixture): void {
            requireProof($fixture->record()->state === 'deletion_pending' && $fixture->record()->attempts === 1, 'durable pre-delete accounting');
        };
        requireProof($fixture->operation()->expire($id) === 'deleted' && $fixture->record()->state === 'deleted', 'verified deletion');
        $before = count($fixture->remote->calls);
        requireProof($fixture->operation()->expire($id) === 'already_deleted' && count($fixture->remote->calls) === $before, 'idempotent deletion');
        requireExportFailure(static fn () => $fixture->operation()->reconcile(new ExportId(str_repeat('e', 32))));
        requireProof(count($fixture->remote->calls) === $before, 'missing metadata never lists');
    } finally { $fixture->cleanup(); }

    foreach (['put412', 'put409', 'put403', 'put500', 'lost_put', 'null_version', 'wrong_kms'] as $fault) {
        $fixture = new Fixture();
        try {
            $fixture->remote->fault = $fault;
            requireExportFailure(static fn () => $fixture->operation()->generate());
            $record = $fixture->record();
            requireProof($record->state === ($fault === 'put412' ? 'failed' : 'reconciliation_required'), 'write uncertainty classification');
            requireExportFailure(static fn () => $fixture->operation()->read($record->id));
            if ($fault === 'put412') {
                requireProof($fixture->operation()->reconcile($record->id) === 'not_due' && $fixture->remote->calls === ['PutObject'], 'collision never adopted');
            }
            if ($fault === 'lost_put') {
                $fixture->remote->fault = ''; $fixture->remote->present = false;
                requireProof($fixture->operation()->reconcile($record->id) === 'unresolved' && $fixture->record()->state === 'reconciliation_required', 'empty ambiguous write remains reserved');
                $fixture->remote->present = true;
                foreach (['sibling', 'multiple', 'marker', 'truncated'] as $listingFault) {
                    $fixture->remote->fault = $listingFault;
                    requireExportFailure(static fn () => $fixture->operation()->reconcile($record->id));
                }
                $fixture->remote->fault = '';
                requireProof($fixture->operation()->reconcile($record->id) === 'reconciled' && $fixture->record()->state === 'accepted', 'late Put recovered without replay');
                requireProof(count(array_filter($fixture->remote->calls, static fn (string $name): bool => $name === 'PutObject')) === 1, 'single write attempt');
            }
        } finally { $fixture->cleanup(); }
    }

    $fixture = new Fixture();
    try {
        $fixture->remote->afterPut = static function () use ($fixture): void {
            $fixture->database->executeStatement("CREATE TRIGGER reject_promotion BEFORE UPDATE ON exports BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        };
        try { $fixture->operation()->generate(); throw new LogicException('Promotion failure ignored.'); } catch (PDOException) {}
        requireProof($fixture->record()->state === 'pending' && $fixture->remote->present, 'post-write metadata failure retains pending ownership');
        $fixture->database->executeStatement('DROP TRIGGER reject_promotion');
        requireProof($fixture->operation()->reconcile($fixture->record()->id) === 'reconciled', 'crash-equivalent pending recovery');
    } finally { $fixture->cleanup(); }

    $fixture = new Fixture();
    try {
        $id = $fixture->operation()->generate(); $fixture->clock->value += 86_400;
        $fixture->remote->deleteTakesEffect = false;
        requireProof($fixture->operation()->expire($id) === 'unresolved' && $fixture->record()->attempts === 1, 'initial delete retained');
        $before = count($fixture->remote->calls);
        requireProof($fixture->operation()->expire($id) === 'unresolved' && $fixture->record()->attempts === 2, 'one reconciled delete');
        requireProof(array_slice($fixture->remote->calls, $before) === ['ListObjectVersions', 'HeadObject', 'DeleteObject', 'ListObjectVersions'], 'four-call reconciliation bound');
        $before = count($fixture->remote->calls);
        requireProof($fixture->operation()->expire($id) === 'unresolved' && array_slice($fixture->remote->calls, $before) === ['ListObjectVersions'], 'fresh process cannot reset delete attempts');
        $fixture->remote->present = false;
        requireProof($fixture->operation()->expire($id) === 'deleted', 'late deletion absence resolves accounting');
    } finally { $fixture->cleanup(); }

    $fixture = new Fixture();
    try {
        $id = $fixture->operation()->generate(); $fixture->clock->value += 86_400;
        $fixture->remote->fault = 'lost_delete';
        try { $fixture->operation()->expire($id); throw new LogicException('Lost delete accepted.'); }
        catch (RuntimeException) {}
        requireProof($fixture->record()->state === 'deletion_pending' && $fixture->record()->attempts === 1, 'lost delete stays pending');
        $fixture->remote->fault = '';
        requireProof($fixture->operation()->expire($id) === 'deleted', 'lost successful delete reconciles without repetition');
    } finally { $fixture->cleanup(); }
}

function verifyExportCardinality(int $size): void
{
        $fixture = new Fixture();
        try {
            $fixture->database->executeStatement("WITH RECURSIVE items(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM items WHERE n < :size)
                INSERT INTO inventory (sku, quantity) SELECT printf('SKU%04d', n), n FROM items", ['size' => $size]);
            $budget = new QueryBudget(16);
            if ($size === 1001) {
                requireExportFailure(static fn () => $fixture->operation($budget)->generate());
                requireProof($fixture->remote->calls === [] && $budget->used() === 1, 'overflow before Put');
            } else {
                $fixture->operation($budget)->generate();
                requireProof(count($fixture->remote->calls) === 2 && $budget->used() === 5, 'constant SQL and remote growth');
            }
        } finally { $fixture->cleanup(); }
}

function verifyExportCapacity(string $capacity): void
{
        $fixture = new Fixture();
        try {
            $fixture->database->executeStatement("WITH RECURSIVE items(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM items WHERE n < :count)
                INSERT INTO exports (export_id, size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target, reason)
                SELECT printf('%032x', n), 65536, :digest, :state, NULL, 0, 1, 86401, :target, 'fixture' FROM items",
                ['count' => $capacity === 'live' ? 32 : 256, 'digest' => hash('sha256', 'fixture'),
                    'state' => $capacity === 'live' ? 'reconciliation_required' : 'failed', 'target' => $fixture->remote->target->fingerprint()]);
            requireExportFailure(static fn () => $fixture->operation()->generate());
            requireProof($fixture->remote->calls === [], 'capacity includes ambiguity and never discards identities');
        } finally { $fixture->cleanup(); }
}

function verifyGeneratedS3Bounds(): void
{
    verifyExportCardinality(1);
    verifyExportCardinality(1000);
    verifyExportCardinality(1001);
    verifyExportCapacity('live');
    verifyExportCapacity('tombstones');
}

function verifyGeneratedS3MetadataFailures(): void
{
    $fixture = new Fixture();
    try {
        $fixture->database->executeStatement("INSERT INTO inventory (sku, quantity) VALUES (hex(zeroblob(65536)), 1)");
        requireExportFailure(static fn () => $fixture->operation()->generate());
        requireProof($fixture->remote->calls === [], 'oversized database field rejected before remote work');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $fixture->database->executeStatement("CREATE TRIGGER deny_reservation BEFORE INSERT ON exports BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        try { $fixture->operation()->generate(); throw new LogicException('Reservation failure ignored.'); } catch (PDOException) {}
        requireProof($fixture->remote->calls === [], 'reservation must commit before write');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $fixture->database->executeStatement("CREATE TRIGGER deny_acceptance BEFORE UPDATE ON exports WHEN NEW.state = 'accepted' BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        try { $fixture->operation()->generate(); throw new LogicException('Acceptance failure ignored.'); } catch (PDOException) {}
        requireProof($fixture->record()->state === 'stored', 'validated bytes remain non-serving on metadata failure');
        requireExportFailure(static fn () => $fixture->operation()->read($fixture->record()->id));
        $fixture->database->executeStatement('DROP TRIGGER deny_acceptance');
        requireProof($fixture->operation()->reconcile($fixture->record()->id) === 'reconciled', 'stored record can reconcile');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $id = $fixture->operation()->generate(); $fixture->clock->value += 86_400;
        $fixture->database->executeStatement("CREATE TRIGGER deny_delete_claim BEFORE UPDATE ON exports WHEN NEW.delete_attempts > OLD.delete_attempts BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        try { $fixture->operation()->expire($id); throw new LogicException('Delete claim failure ignored.'); } catch (PDOException) {}
        requireProof($fixture->remote->calls === ['PutObject', 'HeadObject'] && $fixture->record()->state === 'deletion_pending'
            && $fixture->record()->attempts === 0, 'attempt commits before any deletion');
        $fixture->database->executeStatement('DROP TRIGGER deny_delete_claim');
        $fixture->database->executeStatement("CREATE TRIGGER deny_deleted BEFORE UPDATE ON exports WHEN NEW.state = 'deleted' BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        try { $fixture->operation()->expire($id); throw new LogicException('Deletion accounting failure ignored.'); } catch (PDOException) {}
        requireProof(!$fixture->remote->present && $fixture->record()->state === 'deletion_pending' && $fixture->record()->attempts === 1, 'post-delete metadata failure retains quota');
        $fixture->database->executeStatement('DROP TRIGGER deny_deleted');
        requireProof($fixture->operation()->expire($id) === 'deleted', 'post-delete metadata recovery');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $id = $fixture->operation()->generate(); $fixture->clock->value += 86_400;
        $fixture->remote->deleteTakesEffect = false;
        requireProof($fixture->operation()->expire($id) === 'unresolved', 'prepare deletion mismatch');
        $fixture->remote->fault = 'other_version'; $before = count($fixture->remote->calls);
        requireExportFailure(static fn () => $fixture->operation()->expire($id));
        requireProof(array_slice($fixture->remote->calls, $before) === ['ListObjectVersions'] && $fixture->record()->attempts === 1, 'changed version never deleted');
    } finally { $fixture->cleanup(); }
}
