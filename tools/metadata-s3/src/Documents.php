<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Database\Connection;

interface DocumentClock { public function now(): int; }
final class SystemDocumentClock implements DocumentClock { public function now(): int { return time(); } }

final readonly class Documents
{
    public function __construct(private Connection $database, private DocumentLock $lock, private DocumentS3 $s3, private DocumentClock $clock) {}

    public function receive(DocumentInput $input, DocumentOwner $owner): DocumentId
    {
        $this->lock->requireHeld(true);
        $upload = VerifiedUpload::open($input->upload);
        try {
            $this->database->executeStatement('PRAGMA busy_timeout = 0');
            $this->database->executeStatement('PRAGMA synchronous = FULL');
            $id = new DocumentId(bin2hex(random_bytes(16)));
            $now = $this->now();
            $reserved = $this->database->executeStatement(
                'INSERT INTO documents (document_id, owner_id, label, note, size_bytes, sha256, state, version_id, created_at, expires_at, target)
                 SELECT :id, :owner, :label, :note, :bytes, :digest, :state, NULL, :created, :expires, :target
                 WHERE (SELECT COUNT(*) FROM documents) < 256
                   AND (SELECT COUNT(*) FROM documents WHERE state NOT IN (:failed_count, :deleted_count)) < 32
                   AND (SELECT COALESCE(SUM(size_bytes), 0) FROM documents WHERE state NOT IN (:failed_bytes, :deleted_bytes)) + :quota <= 2097152
                   AND (SELECT COUNT(*) FROM documents WHERE owner_id = :principal_count AND state NOT IN (:owner_failed_count, :owner_deleted_count)) < 8
                   AND (SELECT COALESCE(SUM(size_bytes), 0) FROM documents WHERE owner_id = :principal_bytes AND state NOT IN (:owner_failed_bytes, :owner_deleted_bytes)) + :owner_quota <= 524288',
                ['id' => $id->value, 'owner' => $owner->id, 'label' => $input->metadata->label, 'note' => $input->metadata->note,
                    'bytes' => $upload->bytes, 'digest' => $upload->sha256, 'state' => 'pending', 'created' => $now, 'expires' => $now + 86400,
                    'target' => $this->s3->target->fingerprint(), 'failed_count' => 'failed', 'deleted_count' => 'deleted',
                    'failed_bytes' => 'failed', 'deleted_bytes' => 'deleted', 'quota' => $upload->bytes, 'principal_count' => $owner->id,
                    'owner_failed_count' => 'failed', 'owner_deleted_count' => 'deleted', 'principal_bytes' => $owner->id,
                    'owner_failed_bytes' => 'failed', 'owner_deleted_bytes' => 'deleted', 'owner_quota' => $upload->bytes],
            );
            if ($reserved !== 1) { throw new CapacityUnavailable('Document capacity unavailable.'); }
            try { $version = $this->s3->create($id, $upload); }
            catch (DocumentCollision) {
                $this->transition($id, $owner, 'failed', null, 'failed_collision');
                throw new DocumentUnavailable('Document collision.');
            } catch (\Throwable) {
                $this->transition($id, $owner, 'reconciliation_required', null, 'write_unresolved');
                throw new DocumentUnavailable('Document write unresolved.');
            }
            // Durable pending/stored ownership survives either post-Put database failure.
            $this->transition($id, $owner, 'stored', $version, 'validation_pending');
            try { $this->s3->head($this->record($id, $owner), $version); }
            catch (\Throwable) {
                $this->transition($id, $owner, 'reconciliation_required', $version, 'validation_unresolved');
                throw new DocumentUnavailable('Document validation unresolved.');
            }
            $this->transition($id, $owner, 'accepted', $version, 'validated');
            return $id;
        } finally { $upload->close(); }
    }

    /** Private application use only; callers must perform current action-specific authorization. */
    public function eligible(DocumentId $id, DocumentOwner $owner): DocumentRecord
    {
        $this->lock->requireHeld(false);
        $record = $this->record($id, $owner);
        if ($record->state !== 'accepted' || $record->expires <= $this->now()) { throw new DocumentUnavailable('Document not eligible.'); }
        return $record;
    }

    public function reconcile(DocumentId $id, DocumentOwner $owner): string
    {
        $this->lock->requireHeld(true);
        $record = $this->record($id, $owner);
        if ($record->state === 'deleted') { return 'already_deleted'; }
        if ($record->state === 'failed' || $record->state === 'accepted') { return 'not_due'; }
        // Deletion reconciliation requires the separately scoped deletion identity.
        if ($record->state === 'deletion_pending') { throw new DocumentUnavailable('Deletion process required.'); }
        $version = $this->s3->version($record);
        if ($version === null) {
            $this->transition($id, $owner, 'reconciliation_required', $record->version, 'write_unresolved');
            return 'unresolved';
        }
        $this->s3->head($record, $version);
        $this->transition($id, $owner, 'accepted', $version, 'reconciled');
        return 'reconciled';
    }

    public function expire(DocumentId $id, DocumentOwner $owner): string
    {
        $this->lock->requireHeld(true);
        $record = $this->record($id, $owner);
        if ($record->state === 'deleted') { return 'already_deleted'; }
        if ($record->state === 'failed' || $record->expires > $this->now()) { return 'not_due'; }
        if ($record->state !== 'accepted' && $record->state !== 'deletion_pending') { throw new DocumentUnavailable('Reconciliation required before deletion.'); }
        if ($record->state === 'accepted') {
            $this->transition($id, $owner, 'deletion_pending', $record->version, 'expiry');
            $record = $this->record($id, $owner);
        }
        if ($record->attempts > 0) {
            $version = $this->s3->version($record);
            if ($version === null) { $this->transition($id, $owner, 'deleted', $record->version, 'absence_verified'); return 'deleted'; }
            if ($record->attempts >= 2) { return 'unresolved'; }
            $this->s3->head($record, $version);
        }
        $claimed = $this->database->executeStatement(
            'UPDATE documents SET delete_attempts = delete_attempts + 1 WHERE document_id = :id AND owner_id = :owner AND state = :state AND delete_attempts = :attempts',
            ['id' => $id->value, 'owner' => $owner->id, 'state' => 'deletion_pending', 'attempts' => $record->attempts],
        );
        if ($claimed !== 1) { throw new DocumentUnavailable('Document deletion accounting unresolved.'); }
        $this->s3->delete($record);
        if ($this->s3->version($record) !== null) { return 'unresolved'; }
        $this->transition($id, $owner, 'deleted', $record->version, 'absence_verified');
        return 'deleted';
    }

    private function record(DocumentId $id, DocumentOwner $owner): DocumentRecord
    {
        $row = $this->database->selectOneRow(
            'SELECT document_id, owner_id,
                CASE WHEN length(CAST(label AS BLOB)) BETWEEN 1 AND 120 THEN label ELSE NULL END AS label,
                CASE WHEN note IS NULL OR length(CAST(note AS BLOB)) <= 1024 THEN note ELSE zeroblob(1) END AS note,
                size_bytes, sha256, state, version_id, delete_attempts, created_at, expires_at, target
             FROM documents WHERE document_id = :id AND owner_id = :owner',
            ['id' => $id->value, 'owner' => $owner->id],
        );
        if ($row === null) { throw new DocumentUnavailable('Document ownership unavailable.'); }
        $record = DocumentRecord::fromDatabaseRow($row);
        if (!hash_equals($record->target, $this->s3->target->fingerprint())) { throw new DocumentUnavailable('Document target changed.'); }
        return $record;
    }

    private function transition(DocumentId $id, DocumentOwner $owner, string $state, ?ObjectVersion $version, string $reason): void
    {
        $changed = $this->database->executeStatement(
            'UPDATE documents SET state = :state, version_id = :version, reason = :reason,
                label = CASE WHEN :clear_label THEN NULL ELSE label END,
                note = CASE WHEN :clear_note THEN NULL ELSE note END
             WHERE document_id = :id AND owner_id = :owner',
            ['state' => $state, 'version' => $version?->value, 'reason' => $reason, 'id' => $id->value, 'owner' => $owner->id,
                'clear_label' => in_array($state, ['failed', 'deleted'], true), 'clear_note' => in_array($state, ['failed', 'deleted'], true)],
        );
        if ($changed !== 1) { throw new DocumentUnavailable('Document metadata transition unresolved.'); }
    }

    private function now(): int
    {
        $now = $this->clock->now();
        if ($now < 1 || $now > PHP_INT_MAX - 86_400) { throw new DocumentUnavailable('Document clock unavailable.'); }
        return $now;
    }
}
