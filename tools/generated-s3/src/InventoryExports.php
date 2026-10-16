<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

use PHPThis\Database\Connection;

interface ExportClock { public function now(): int; }
final class SystemExportClock implements ExportClock { public function now(): int { return time(); } }

final readonly class InventoryExports
{
    public function __construct(private Connection $database, private ExportLock $lock, private InventoryS3 $s3, private ExportClock $clock) {}

    public function generate(): ExportId
    {
        $this->lock->requireHeld(true);
        $rows = $this->database->selectAllRows(
            'SELECT CASE WHEN length(CAST(sku AS BLOB)) BETWEEN 1 AND 32 THEN sku ELSE NULL END AS sku, quantity
             FROM inventory ORDER BY inventory.sku LIMIT 1001',
        );
        if (count($rows) > 1000) { throw new ExportUnavailable('Inventory collection exceeds export bound.'); }
        $csv = "sku,quantity\n";
        foreach ($rows as $row) {
            $line = InventoryLine::fromDatabaseRow($row);
            $csv .= $line->sku . ',' . $line->quantity . "\n";
        }
        $bytes = ExportBytes::fromBytes($csv);
        return $this->retain($bytes);
    }

    public function generateTestTone(TestToneBytes $tone): ExportId
    {
        $this->lock->requireHeld(true);
        return $this->retain($tone->bytes);
    }

    private function retain(ExportBytes $bytes): ExportId
    {
        $id = new ExportId(bin2hex(random_bytes(16)));
        $now = $this->now();
        $reserved = $this->database->executeStatement(
            'INSERT INTO exports (export_id, size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target, reason)
             SELECT :id, :bytes, :digest, :state, NULL, 0, :created, :expires, :target, :reason
             WHERE (SELECT COUNT(*) FROM exports) < 256
               AND (SELECT COUNT(*) FROM exports WHERE state NOT IN (:failed_count, :deleted_count)) < 32
               AND (SELECT COALESCE(SUM(size_bytes), 0) FROM exports WHERE state NOT IN (:failed_bytes, :deleted_bytes)) + :quota <= 2097152',
            ['id' => $id->value, 'bytes' => strlen($bytes->value), 'digest' => $bytes->sha256, 'state' => 'pending',
                'created' => $now, 'expires' => $now + 86_400, 'target' => $this->s3->target->fingerprint(), 'reason' => 'reserved',
                'failed_count' => 'failed', 'deleted_count' => 'deleted', 'failed_bytes' => 'failed', 'deleted_bytes' => 'deleted',
                'quota' => strlen($bytes->value)],
        );
        if ($reserved !== 1) { throw new ExportUnavailable('Export capacity unavailable.'); }
        try {
            $version = $this->s3->create($id, $bytes);
        } catch (BeforePut) {
            $this->transition($id, 'failed', null, 'failed_before_send');
            throw new ExportUnavailable('Export failed before send.');
        } catch (ExportCollision) {
            $this->transition($id, 'failed', null, 'failed_collision');
            throw new ExportUnavailable('Export collision.');
        } catch (\Throwable) {
            $this->transition($id, 'reconciliation_required', null, 'write_unresolved');
            throw new ExportUnavailable('Export write unresolved.');
        }
        // If either metadata update fails, the durable pending/stored record retains ownership and quota.
        $this->transition($id, 'stored', $version, 'validation_pending');
        try { $this->s3->head($this->record($id), $version); }
        catch (\Throwable) {
            $this->transition($id, 'reconciliation_required', $version, 'validation_unresolved');
            throw new ExportUnavailable('Export validation unresolved.');
        }
        $this->transition($id, 'accepted', $version, 'validated');
        return $id;
    }

    public function read(ExportId $id): ExportBytes
    {
        $this->lock->requireHeld(false);
        $record = $this->record($id);
        if ($record->state !== 'accepted' || $record->expires <= $this->now()) { throw new ExportUnavailable('Export not eligible.'); }
        $bytes = $this->s3->read($record);
        if ($record->expires <= $this->now()) { throw new ExportUnavailable('Export expired during read.'); }
        return $bytes;
    }

    public function reconcile(ExportId $id): string
    {
        $this->lock->requireHeld(true);
        $record = $this->record($id);
        if ($record->state === 'deleted') { return 'already_deleted'; }
        if ($record->state === 'failed' || $record->state === 'accepted') { return 'not_due'; }
        // Deletion reconciliation requires the separately scoped deletion identity.
        if ($record->state === 'deletion_pending') { throw new ExportUnavailable('Deletion process required.'); }
        $version = $this->s3->version($record);
        if ($version === null) {
            $this->transition($id, 'reconciliation_required', $record->version, 'write_unresolved');
            return 'unresolved';
        }
        $this->s3->head($record, $version);
        $this->transition($id, 'accepted', $version, 'reconciled');
        return 'reconciled';
    }

    public function expire(ExportId $id): string
    {
        $this->lock->requireHeld(true);
        $record = $this->record($id);
        if ($record->state === 'deleted') { return 'already_deleted'; }
        if ($record->state === 'failed' || $record->expires > $this->now()) { return 'not_due'; }
        if ($record->state !== 'accepted' && $record->state !== 'deletion_pending') { throw new ExportUnavailable('Reconciliation required before deletion.'); }
        if ($record->state === 'accepted') {
            $this->transition($id, 'deletion_pending', $record->version, 'expiry');
            $record = $this->record($id);
        }
        if ($record->attempts > 0) {
            $version = $this->s3->version($record);
            if ($version === null) { $this->transition($id, 'deleted', $record->version, 'absence_verified'); return 'deleted'; }
            if ($record->attempts >= 2) { return 'unresolved'; }
            $this->s3->head($record, $version);
        }
        $claimed = $this->database->executeStatement(
            'UPDATE exports SET delete_attempts = delete_attempts + 1 WHERE export_id = :id AND state = :state AND delete_attempts = :attempts',
            ['id' => $id->value, 'state' => 'deletion_pending', 'attempts' => $record->attempts],
        );
        if ($claimed !== 1) { throw new ExportUnavailable('Export deletion accounting unresolved.'); }
        $this->s3->delete($record);
        if ($this->s3->version($record) !== null) { return 'unresolved'; }
        $this->transition($id, 'deleted', $record->version, 'absence_verified');
        return 'deleted';
    }

    private function record(ExportId $id): ExportRecord
    {
        $row = $this->database->selectOneRow(
            'SELECT export_id, size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target FROM exports WHERE export_id = :id',
            ['id' => $id->value],
        );
        if ($row === null) { throw new ExportUnavailable('Export ownership unavailable.'); }
        $record = ExportRecord::fromDatabaseRow($row);
        if (!hash_equals($record->target, $this->s3->target->fingerprint())) { throw new ExportUnavailable('Export target changed.'); }
        return $record;
    }

    private function transition(ExportId $id, string $state, ?ObjectVersion $version, string $reason): void
    {
        $changed = $this->database->executeStatement(
            'UPDATE exports SET state = :state, version_id = :version, reason = :reason WHERE export_id = :id',
            ['state' => $state, 'version' => $version?->value, 'reason' => $reason, 'id' => $id->value],
        );
        if ($changed !== 1) { throw new ExportUnavailable('Export metadata transition unresolved.'); }
    }

    private function now(): int
    {
        $now = $this->clock->now();
        if ($now < 1 || $now > PHP_INT_MAX - 86_400) { throw new ExportUnavailable('Export clock unavailable.'); }
        return $now;
    }
}
