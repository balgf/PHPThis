<?php

declare(strict_types=1);

use MetadataS3Recipe\DocumentOwner;
use MetadataS3Recipe\DocumentUnavailable;
use MetadataS3Recipe\PacketGone;
use MetadataS3Recipe\PacketRole;
use MetadataS3Recipe\Tests\PacketFixture;
use function MetadataS3Recipe\Tests\requireProof;

/** @param Closure(): mixed $operation */
function packetFailure(Closure $operation, string $expected = DocumentUnavailable::class): void
{
    try { $operation(); }
    catch (Throwable $failure) { if ($failure::class === $expected) { return; } throw $failure; }
    throw new LogicException('Packet failure was not raised.');
}

function verifyPacketLifecycle(): void
{
    foreach (['write_started', 'stored', 'verified'] as $primary) {
        foreach (['write_started', 'stored', 'verified'] as $supporting) {
            $fixture = new PacketFixture();
            try {
                $id = $fixture->seed(primary: $primary, supporting: $supporting); $owner = new DocumentOwner(1);
                packetFailure(static fn () => $fixture->operation()->eligible($id, $owner), PacketGone::class);
                packetFailure(static fn () => $fixture->operation()->reconcile($id, new DocumentOwner(2)));
                requireProof($fixture->peer->calls === [], 'packet owner check before remote work');
                requireProof($fixture->operation()->reconcile($id, $owner) === 'reconciled', 'both roles recovered');
                $record = $fixture->operation()->eligible($id, $owner);
                requireProof($record->primary->version?->value === 'version-primary' && $record->supporting->version?->value === 'version-supporting'
                    && $record->metadata?->note === 'private-sentinel note', 'whole packet exact ownership');
                requireProof(count($fixture->peer->calls) === ($primary === 'verified' ? 0 : 2) + ($supporting === 'verified' ? 0 : 2), 'bounded read-only recovery');
                $before = $fixture->peer->calls;
                requireProof($fixture->operation()->reconcile($id, $owner) === 'not_due' && $fixture->peer->calls === $before, 'accepted recovery repeat performs no remote work');
            } finally { $fixture->cleanup(); }
        }
    }
    foreach (['ready', 'collision'] as $supporting) {
        $fixture = new PacketFixture();
        try {
            $id = $fixture->seed(primary: 'verified', supporting: $supporting); $owner = new DocumentOwner(1);
            // This foreign object must survive collision cleanup even if its bytes would match.
            $foreignKey = $id->key(PacketRole::Supporting);
            $fixture->peer->objects[$foreignKey] = ['bytes' => 'supporting', 'version' => 'foreign', 'metadata' => []];
            requireProof($fixture->operation()->reconcile($id, $owner) === 'cleanup_required', 'unattempted or collided partner irreversibly aborts');
            requireProof($fixture->operation(8)->purge($id, $owner) === 'failed', 'owned partial packet cleaned');
            requireProof($fixture->record()->metadata === null && $fixture->peer->retainedVersion($foreignKey) === 'foreign'
                && $fixture->peer->calls === ['DeleteObject:primary', 'ListObjectVersions:primary'], 'only owned role deleted and descriptions cleared');
        } finally { $fixture->cleanup(); }
    }
    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed(primary: 'ready', supporting: 'ready');
        requireProof($fixture->operation()->reconcile($id, new DocumentOwner(1)) === 'failed'
            && $fixture->peer->calls === [] && $fixture->record()->metadata === null, 'crash before claims releases complete reservation without remote work');
    } finally { $fixture->cleanup(); }

    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed(primary: 'write_started', supporting: 'ready'); $owner = new DocumentOwner(1);
        $object = $fixture->peer->objects[$id->key(PacketRole::Primary)]; $fixture->peer->objects = [];
        requireProof($fixture->operation()->reconcile($id, $owner) === 'cleanup_required'
            && $fixture->operation(8)->purge($id, $owner) === 'unresolved'
            && $fixture->record()->state === 'aborting', 'empty uncertain Put keeps reservation');
        $fixture->peer->objects[$id->key(PacketRole::Primary)] = $object;
        $fixture->operation()->reconcile($id, $owner);
        requireProof($fixture->record()->state === 'aborting' && $fixture->operation(8)->purge($id, $owner) === 'failed', 'late Put cleaned without resurrecting aborted packet');
    } finally { $fixture->cleanup(); }

    foreach (['sibling', 'multiple', 'marker', 'truncated', 'continuation', 'not_latest', 'list_denied',
        'head_denied', 'wrong_nonce', 'wrong_role', 'wrong_packet', 'extra_metadata', 'wrong_kms', 'storage_class'] as $fault) {
        $fixture = new PacketFixture();
        try {
            $id = $fixture->seed(); $fixture->peer->fault = 'primary:' . $fault;
            packetFailure(static fn () => $fixture->operation()->reconcile($id, new DocumentOwner(1)),
                in_array($fault, ['list_denied', 'head_denied'], true) ? RuntimeException::class : DocumentUnavailable::class);
            requireProof($fixture->record()->state === 'reconciliation_required' && $fixture->record()->primary->version === null
                && count($fixture->peer->objects) === 2, 'contradictory recovery never accepts or deletes: ' . $fault);
        } finally { $fixture->cleanup(); }
    }
    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed(primary: 'stored'); $fixture->peer->fault = 'primary:other_version';
        packetFailure(static fn () => $fixture->operation()->reconcile($id, new DocumentOwner(1)));
        requireProof($fixture->record()->primary->version?->value === 'version-primary', 'recorded exact version cannot be replaced');
    } finally { $fixture->cleanup(); }

    verifyPacketPromotionFailure('primary_state');
    verifyPacketPromotionFailure('state');
    verifyPacketPromotionFailure('commit');
    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed(); $owner = new DocumentOwner(1);
        $fixture->peer->before = static function (Aws\CommandInterface $command) use ($fixture): void {
            $key = $command['Key'];
            if ($command->getName() === 'HeadObject' && is_string($key) && str_ends_with($key, '/supporting')) {
                $fixture->base->clock->value += 86400;
            }
        };
        requireProof($fixture->operation()->reconcile($id, $owner) === 'cleanup_required'
            && $fixture->record()->state === 'aborting', 'expiry during recovery forbids acceptance');
    } finally { $fixture->cleanup(); }
    fwrite(STDOUT, "PASS packet recovery: all-or-nothing visibility, interrupted reads and exact ownership\n");
}

function verifyPacketCleanup(): void
{
    verifyPacketCleanupFailure('lost_delete');
    verifyPacketCleanupFailure('database_deleted');
    verifyPacketCleanupFailure('database_attempt');
    verifyPacketCleanupFailure('database_terminal');
    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed('accepted', 'verified', 'verified'); $owner = new DocumentOwner(1);
        requireProof($fixture->operation(8)->purge($id, $owner) === 'not_due' && $fixture->peer->calls === [], 'accepted packet retained before expiry');
        $fixture->base->clock->value += 86400; $fixture->peer->fault = 'supporting:retained_delete';
        requireProof($fixture->operation(8)->purge($id, $owner) === 'unresolved', 'partial deletion retains aggregate reservation');
        requireProof($fixture->record()->primary->state === 'deleted' && $fixture->record()->supporting->attempts === 1, 'each exact role has separate progress');
        $fixture->operation(8)->purge($id, $owner);
        $fixture->operation(8)->purge($id, $owner);
        requireProof($fixture->record()->supporting->attempts === 2
            && count(array_filter($fixture->peer->calls, static fn (string $call): bool => $call === 'DeleteObject:supporting')) === 2,
            'durable two-attempt ceiling across fresh operations');
        unset($fixture->peer->objects[$id->key(PacketRole::Supporting)]);
        requireProof($fixture->operation(8)->purge($id, $owner) === 'deleted', 'late deletion absence permits accounting completion');
        $calls = $fixture->peer->calls;
        requireProof($fixture->operation(8)->purge($id, $owner) === 'already_deleted' && $fixture->peer->calls === $calls, 'terminal cleanup is read-only and idempotent');
    } finally { $fixture->cleanup(); }
    fwrite(STDOUT, "PASS packet cleanup: partial deletion, accounting failures and persistent attempt limits\n");
}

function verifyPacketPromotionFailure(string $boundary): void
{
    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed(); $owner = new DocumentOwner(1);
        $fixture->base->database->executeStatement('CREATE TABLE packet_commit_parent (id INTEGER PRIMARY KEY) STRICT');
        $fixture->base->database->executeStatement('CREATE TABLE packet_commit_probe (id INTEGER REFERENCES packet_commit_parent(id) DEFERRABLE INITIALLY DEFERRED) STRICT');
        $sql = match ($boundary) {
            'primary_state' => "CREATE TRIGGER fail_packet BEFORE UPDATE ON document_packets WHEN NEW.primary_state = 'verified' BEGIN SELECT RAISE(ABORT, 'synthetic'); END",
            'commit' => "CREATE TRIGGER fail_packet AFTER UPDATE ON document_packets WHEN NEW.state = 'accepted' BEGIN INSERT INTO packet_commit_probe (id) VALUES (1); END",
            default => "CREATE TRIGGER fail_packet BEFORE UPDATE ON document_packets WHEN NEW.state = 'accepted' BEGIN SELECT RAISE(ABORT, 'synthetic'); END",
        };
        $fixture->base->database->executeStatement($sql);
        packetFailure(static fn () => $fixture->operation(foreignKeys: true)->reconcile($id, $owner), PDOException::class);
        requireProof($fixture->record()->state === 'reconciliation_required', 'interrupted promotion remains invisible');
        $fixture->base->database->executeStatement('DROP TRIGGER fail_packet');
        $fixture->peer->calls = [];
        requireProof($fixture->operation()->reconcile($id, $owner) === 'reconciled'
            && count($fixture->peer->calls) === ($boundary === 'primary_state' ? 4 : 0), 'interrupted recovery resumes with reads only');
    } finally { $fixture->cleanup(); }
}

function verifyPacketCleanupFailure(string $fault): void
{
    $fixture = new PacketFixture();
    try {
        $id = $fixture->seed('accepted', 'verified', 'verified'); $owner = new DocumentOwner(1);
        $fixture->base->clock->value += 86400;
        if ($fault === 'lost_delete') { $fixture->peer->fault = 'primary:lost_delete'; }
        if ($fault === 'database_deleted') {
            $fixture->base->database->executeStatement("CREATE TRIGGER fail_packet BEFORE UPDATE ON document_packets WHEN NEW.primary_state = 'deleted' BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        }
        if ($fault === 'database_attempt') {
            $fixture->base->database->executeStatement("CREATE TRIGGER fail_packet BEFORE UPDATE ON document_packets WHEN NEW.primary_delete_attempts > OLD.primary_delete_attempts BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        }
        if ($fault === 'database_terminal') {
            $fixture->base->database->executeStatement("CREATE TRIGGER fail_packet BEFORE UPDATE ON document_packets WHEN NEW.state = 'deleted' BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        }
        packetFailure(static fn () => $fixture->operation(8)->purge($id, $owner), $fault === 'lost_delete' ? RuntimeException::class : PDOException::class);
        requireProof($fixture->record()->state === 'deletion_pending' && $fixture->record()->metadata !== null, 'cleanup failure retains metadata and quota');
        if ($fault === 'database_attempt') { requireProof($fixture->peer->calls === [], 'failed deletion claim prevents remote effect'); }
        $fixture->peer->fault = '';
        if ($fault !== 'lost_delete') { $fixture->base->database->executeStatement('DROP TRIGGER fail_packet'); }
        requireProof($fixture->operation(8)->purge($id, $owner) === 'deleted' && $fixture->record()->metadata === null, 'cleanup resumes from durable facts');
        requireProof(count(array_filter($fixture->peer->calls, static fn (string $call): bool => str_starts_with($call, 'DeleteObject:'))) === 2,
            'lost cleanup accounting does not duplicate deletions');
    } finally { $fixture->cleanup(); }
}
