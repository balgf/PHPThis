<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Database\Connection;

final readonly class Packets
{
    public function __construct(private Connection $database, private DocumentLock $lock, private PacketS3 $s3, private DocumentClock $clock) {}

    public function receive(PacketInput $input, DocumentOwner $owner): PacketReceipt
    {
        $this->lock->requireHeld(true);
        $primary = VerifiedUpload::open($input->primary);
        try {
            $supporting = VerifiedUpload::open($input->supporting);
            try {
                $this->prepare();
                $fingerprint = $input->fingerprint($primary, $supporting);
                $prior = $this->lookup($owner, null, $input->requestId);
                if ($prior !== null) { return PacketReceipt::repeated($prior, $fingerprint, $this->now()); }
                $id = new PacketId(bin2hex(random_bytes(16)));
                $now = $this->now();
                $inserted = $this->database->executeStatement(
                    "INSERT INTO document_packets (packet_id, owner_id, request_id, request_fingerprint, label, note,
                        primary_bytes, primary_sha256, primary_nonce, supporting_bytes, supporting_sha256, supporting_nonce,
                        state, created_at, expires_at, target)
                     SELECT :id, :owner, :retry, :fingerprint, :label, :note, :primary_bytes, :primary_digest, :primary_nonce,
                        :supporting_bytes, :supporting_digest, :supporting_nonce, :state, :created, :expires, :target
                     WHERE (SELECT COUNT(*) FROM documents) + (SELECT COUNT(*) FROM document_packets) < 256
                       AND (SELECT COUNT(*) FROM documents WHERE state NOT IN ('failed','deleted'))
                         + 2 * (SELECT COUNT(*) FROM document_packets WHERE state NOT IN ('failed','deleted')) + 2 <= 32
                       AND (SELECT COALESCE(SUM(size_bytes), 0) FROM documents WHERE state NOT IN ('failed','deleted'))
                         + (SELECT COALESCE(SUM(primary_bytes + supporting_bytes), 0) FROM document_packets WHERE state NOT IN ('failed','deleted'))
                         + :global_bytes <= 2097152
                       AND (SELECT COUNT(*) FROM documents WHERE owner_id = :document_count_owner AND state NOT IN ('failed','deleted'))
                         + 2 * (SELECT COUNT(*) FROM document_packets WHERE owner_id = :packet_count_owner AND state NOT IN ('failed','deleted')) + 2 <= 8
                       AND (SELECT COALESCE(SUM(size_bytes), 0) FROM documents WHERE owner_id = :document_byte_owner AND state NOT IN ('failed','deleted'))
                         + (SELECT COALESCE(SUM(primary_bytes + supporting_bytes), 0) FROM document_packets WHERE owner_id = :packet_byte_owner AND state NOT IN ('failed','deleted'))
                         + :owner_bytes <= 524288",
                    ['id' => $id->value, 'owner' => $owner->id, 'retry' => $input->requestId, 'fingerprint' => $fingerprint,
                        'label' => $input->metadata->label, 'note' => $input->metadata->note, 'primary_bytes' => $primary->bytes,
                        'primary_digest' => $primary->sha256, 'primary_nonce' => bin2hex(random_bytes(16)),
                        'supporting_bytes' => $supporting->bytes, 'supporting_digest' => $supporting->sha256,
                        'supporting_nonce' => bin2hex(random_bytes(16)), 'state' => 'pending', 'created' => $now, 'expires' => $now + 86400,
                        'target' => $this->s3->target->fingerprint(), 'global_bytes' => $primary->bytes + $supporting->bytes,
                        'document_count_owner' => $owner->id, 'packet_count_owner' => $owner->id,
                        'document_byte_owner' => $owner->id, 'packet_byte_owner' => $owner->id, 'owner_bytes' => $primary->bytes + $supporting->bytes],
                );
                if ($inserted !== 1) { throw new CapacityUnavailable('Packet capacity unavailable.'); }
                $record = $this->record($id, $owner);
                $this->write($record, $record->primary, $primary);
                $this->write($record, $record->supporting, $supporting);
                $this->accept($id, $owner);
                return PacketReceipt::created($id);
            } finally { $supporting->close(); }
        } finally { $primary->close(); }
    }

    /** Private use only after current action-specific authorization; both versions are returned together. */
    public function eligible(PacketId $id, DocumentOwner $owner): PacketRecord
    {
        $this->lock->requireHeld(false);
        $record = $this->record($id, $owner);
        if ($record->state !== 'accepted' || $record->expires <= $this->now()) { throw new PacketGone(); }
        return $record;
    }

    public function reconcile(PacketId $id, DocumentOwner $owner): string
    {
        $this->prepare();
        $record = $this->record($id, $owner);
        if (in_array($record->state, ['failed', 'deleted'], true)) { return 'not_due'; }
        if ($record->state === 'deletion_pending') { return 'cleanup_required'; }
        if ($record->state === 'accepted') {
            if ($record->expires > $this->now()) { return 'not_due'; }
            $this->state($record, 'deletion_pending');
            return 'cleanup_required';
        }
        $aborting = $record->state === 'aborting' || $record->expires <= $this->now()
            || in_array($record->primary->state, ['ready', 'collision'], true)
            || in_array($record->supporting->state, ['ready', 'collision'], true);
        $this->state($record, $aborting ? 'aborting' : 'reconciliation_required');
        // Each role's result commits independently; recovery never replays a write.
        $this->reconcileRole($record, $record->primary);
        $this->reconcileRole($record, $record->supporting);
        $current = $this->record($id, $owner);
        if ($current->expires <= $this->now() && $current->state !== 'aborting') {
            $this->state($current, 'aborting');
            $current = $this->record($id, $owner);
        }
        if ($current->state === 'aborting') {
            return $this->finishCleanup($current) ? 'failed' : 'cleanup_required';
        }
        if ($current->primary->state === 'verified' && $current->supporting->state === 'verified') {
            $this->accept($id, $owner);
            return 'reconciled';
        }
        return 'unresolved';
    }

    public function purge(PacketId $id, DocumentOwner $owner): string
    {
        $this->prepare();
        $record = $this->record($id, $owner);
        if ($record->state === 'deleted') { return 'already_deleted'; }
        if ($record->state === 'failed') { return 'not_due'; }
        if ($record->state === 'accepted') {
            if ($record->expires > $this->now()) { return 'not_due'; }
            $this->state($record, 'deletion_pending');
        } elseif (in_array($record->state, ['pending', 'reconciliation_required'], true)) {
            if ($record->expires > $this->now()
                && !in_array($record->primary->state, ['ready', 'collision'], true)
                && !in_array($record->supporting->state, ['ready', 'collision'], true)) { return 'not_due'; }
            $this->state($record, 'aborting');
        }
        $record = $this->record($id, $owner);
        $this->purgeRole($record, $record->primary);
        $this->purgeRole($record, $record->supporting);
        $current = $this->record($id, $owner);
        if (!$this->finishCleanup($current)) { return 'unresolved'; }
        return $current->state === 'aborting' ? 'failed' : 'deleted';
    }

    private function write(PacketRecord $record, PacketObject $object, VerifiedUpload $upload): void
    {
        // The durable marker is intentionally conservative if the process dies before send.
        $this->role($record, $object, 'write_started', null, 'ready');
        try { $version = $this->s3->create($record->id, $object, $upload); }
        catch (DocumentCollision) {
            $this->role($record, $object, 'collision', null, 'write_started');
            throw new DocumentUnavailable('Packet collision.');
        }
        $this->role($record, $object, 'stored', $version, 'write_started');
        $current = $this->record($record->id, $record->owner);
        $stored = $object->role === PacketRole::Primary ? $current->primary : $current->supporting;
        $this->s3->head($record->id, $stored, $version);
        $this->role($current, $stored, 'verified', $version, 'stored');
    }

    private function reconcileRole(PacketRecord $record, PacketObject $object): void
    {
        if (!in_array($object->state, ['write_started', 'stored'], true)) { return; }
        $version = $this->s3->version($record->id, $object);
        // An empty list never resolves an uncertain Put; the remote may still complete later.
        if ($version === null) { return; }
        $this->s3->head($record->id, $object, $version);
        $this->role($record, $object, 'verified', $version, $object->state);
    }

    private function purgeRole(PacketRecord $record, PacketObject $object): void
    {
        if (!in_array($object->state, ['verified', 'deletion_pending'], true)) { return; }
        if ($object->state === 'verified') { $this->role($record, $object, 'deletion_pending', $object->version, 'verified'); }
        if ($object->attempts > 0) {
            $version = $this->s3->version($record->id, $object);
            if ($version === null) { $this->role($record, $object, 'deleted', $object->version, 'deletion_pending'); return; }
            if ($object->attempts >= 2) { return; }
            $this->s3->head($record->id, $object, $version);
        }
        $sql = match ($object->role) {
            PacketRole::Primary => "UPDATE document_packets SET primary_delete_attempts = primary_delete_attempts + 1
                WHERE packet_id = :id AND owner_id = :owner AND state IN ('aborting','deletion_pending')
                  AND primary_state = 'deletion_pending' AND primary_delete_attempts = :attempts AND primary_version = :version",
            PacketRole::Supporting => "UPDATE document_packets SET supporting_delete_attempts = supporting_delete_attempts + 1
                WHERE packet_id = :id AND owner_id = :owner AND state IN ('aborting','deletion_pending')
                  AND supporting_state = 'deletion_pending' AND supporting_delete_attempts = :attempts AND supporting_version = :version",
        };
        $claimed = $this->database->executeStatement($sql, ['id' => $record->id->value, 'owner' => $record->owner->id,
            'attempts' => $object->attempts, 'version' => $object->version?->value]);
        if ($claimed !== 1) { throw new DocumentUnavailable('Packet deletion accounting unavailable.'); }
        $this->s3->delete($record->id, $object);
        if ($this->s3->version($record->id, $object) === null) { $this->role($record, $object, 'deleted', $object->version, 'deletion_pending'); }
    }

    private function finishCleanup(PacketRecord $record): bool
    {
        if (!$record->primary->resolved() || !$record->supporting->resolved()) { return false; }
        if (!in_array($record->state, ['aborting', 'deletion_pending'], true)) { throw new DocumentUnavailable('Packet cleanup state unavailable.'); }
        $changed = $this->database->executeStatement(
            "UPDATE document_packets SET state = :state, label = NULL, note = NULL
             WHERE packet_id = :id AND owner_id = :owner AND state = :expected
               AND primary_state IN ('ready','collision','deleted') AND supporting_state IN ('ready','collision','deleted')",
            ['state' => $record->state === 'aborting' ? 'failed' : 'deleted', 'id' => $record->id->value,
                'owner' => $record->owner->id, 'expected' => $record->state],
        );
        if ($changed !== 1) { throw new DocumentUnavailable('Packet cleanup commit unavailable.'); }
        return true;
    }

    private function accept(PacketId $id, DocumentOwner $owner): void
    {
        $changed = $this->database->executeStatement(
            "UPDATE document_packets SET state = 'accepted' WHERE packet_id = :id AND owner_id = :owner
             AND state IN ('pending','reconciliation_required') AND expires_at > :now
             AND primary_state = 'verified' AND supporting_state = 'verified'
             AND primary_version IS NOT NULL AND supporting_version IS NOT NULL",
            ['id' => $id->value, 'owner' => $owner->id, 'now' => $this->now()],
        );
        if ($changed !== 1) { throw new DocumentUnavailable('Packet acceptance unavailable.'); }
    }

    private function role(PacketRecord $record, PacketObject $object, string $state, ?ObjectVersion $version, string $expected): void
    {
        $sql = match ($object->role) {
            PacketRole::Primary => "UPDATE document_packets SET primary_state = :state, primary_version = :version,
                state = CASE WHEN :collision THEN 'aborting' ELSE state END
                WHERE packet_id = :id AND owner_id = :owner AND primary_state = :expected AND primary_version IS :old_version
                  AND state IN ('pending','reconciliation_required','aborting','deletion_pending')",
            PacketRole::Supporting => "UPDATE document_packets SET supporting_state = :state, supporting_version = :version,
                state = CASE WHEN :collision THEN 'aborting' ELSE state END
                WHERE packet_id = :id AND owner_id = :owner AND supporting_state = :expected AND supporting_version IS :old_version
                  AND state IN ('pending','reconciliation_required','aborting','deletion_pending')",
        };
        $changed = $this->database->executeStatement($sql, ['state' => $state, 'version' => $version?->value, 'collision' => $state === 'collision',
            'id' => $record->id->value, 'owner' => $record->owner->id, 'expected' => $expected, 'old_version' => $object->version?->value]);
        if ($changed !== 1) { throw new DocumentUnavailable('Packet role transition unavailable.'); }
    }

    private function state(PacketRecord $record, string $state): void
    {
        $changed = $this->database->executeStatement('UPDATE document_packets SET state = :state
            WHERE packet_id = :id AND owner_id = :owner AND state = :expected',
            ['state' => $state, 'id' => $record->id->value, 'owner' => $record->owner->id, 'expected' => $record->state]);
        if ($changed !== 1) { throw new DocumentUnavailable('Packet state transition unavailable.'); }
    }

    private function record(PacketId $id, DocumentOwner $owner): PacketRecord
    {
        return $this->lookup($owner, $id, null) ?? throw new DocumentUnavailable('Packet ownership unavailable.');
    }

    private function lookup(DocumentOwner $owner, ?PacketId $id, ?string $retry): ?PacketRecord
    {
        if (($id === null) === ($retry === null)) { throw new \LogicException('Packet selector unavailable.'); }
        $row = $this->database->selectOneRow(
            'SELECT packet_id, owner_id, request_id, request_fingerprint, label, note, state, created_at, expires_at, target,
                primary_bytes, primary_sha256, primary_nonce, primary_state, primary_version, primary_delete_attempts,
                supporting_bytes, supporting_sha256, supporting_nonce, supporting_state, supporting_version, supporting_delete_attempts
             FROM document_packets WHERE owner_id = :owner
               AND ((:by_id IS NOT NULL AND packet_id = :id) OR (:by_retry IS NOT NULL AND request_id = :retry))',
            ['owner' => $owner->id, 'by_id' => $id?->value, 'id' => $id?->value, 'by_retry' => $retry, 'retry' => $retry],
        );
        if ($row === null) { return null; }
        $record = PacketRecord::fromDatabaseRow($row);
        if (!hash_equals($record->target, $this->s3->target->fingerprint())) { throw new DocumentUnavailable('Packet target changed.'); }
        return $record;
    }

    private function prepare(): void
    {
        $this->lock->requireHeld(true);
        $this->database->executeStatement('PRAGMA busy_timeout = 0');
        $this->database->executeStatement('PRAGMA synchronous = FULL');
    }

    private function now(): int
    {
        $now = $this->clock->now();
        if ($now < 1 || $now > PHP_INT_MAX - 86400) { throw new DocumentUnavailable('Packet clock unavailable.'); }
        return $now;
    }
}
