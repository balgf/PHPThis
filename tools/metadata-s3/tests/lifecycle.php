<?php

declare(strict_types=1);

use MetadataS3Recipe\DocumentId;
use MetadataS3Recipe\DocumentOwner;
use MetadataS3Recipe\DocumentUnavailable;
use MetadataS3Recipe\Tests\Fixture;
use function MetadataS3Recipe\Tests\requireProof;

/** @param Closure(): mixed $operation */
function requireDocumentFailure(Closure $operation): void
{
    try { $operation(); } catch (DocumentUnavailable) { return; }
    throw new LogicException('Expected document refusal.');
}

function verifyMetadataS3Lifecycle(): void
{
    foreach (['pending', 'stored', 'reconciliation_required'] as $state) {
        $fixture = new Fixture();
        try {
            $id = $fixture->seed($state);
            requireDocumentFailure(static fn () => $fixture->operation()->eligible($id, $fixture->owner));
            requireDocumentFailure(static fn () => $fixture->operation()->reconcile($id, new DocumentOwner(2)));
            requireDocumentFailure(static fn () => $fixture->operation()->expire($id, new DocumentOwner(2)));
            requireDocumentFailure(static fn () => $fixture->operation()->reconcile(new DocumentId(str_repeat('b', 32)), $fixture->owner));
            requireProof($fixture->remote->calls === [], 'unknown or differently owned metadata never lists');
            $before = $fixture->record()->metadata;
            requireProof($fixture->operation()->reconcile($id, $fixture->owner) === 'reconciled', 'recover exact pending or stored version');
            $after = $fixture->operation()->eligible($id, $fixture->owner);
            requireProof($after->metadata?->label === $before?->label && $after->metadata?->note === $before?->note && $after->owner->id === 1 && $after->version?->value === 'synthetic-version', 'recovery preserves private metadata binding');
            requireProof($fixture->remote->calls === ['ListObjectVersions', 'HeadObject'], 'recovery never replays Put');
            requireDocumentFailure(static fn () => $fixture->operation()->eligible($id, new DocumentOwner(2)));
            requireProof($fixture->operation()->expire($id, $fixture->owner) === 'not_due', '24-hour eligibility');
            $fixture->clock->value += 86_400;
            requireDocumentFailure(static fn () => $fixture->operation()->eligible($id, $fixture->owner));
            $fixture->remote->beforeDelete = static function () use ($fixture): void {
                requireProof($fixture->record()->state === 'deletion_pending' && $fixture->record()->attempts === 1
                    && $fixture->record()->metadata?->label === 'private-sentinel label', 'retain metadata through durable pre-delete accounting');
            };
            requireProof($fixture->operation()->expire($id, $fixture->owner) === 'deleted', 'verified deletion');
            requireProof($fixture->record()->metadata === null && $fixture->record()->owner->id === 1, 'clear descriptive fields and preserve ownership tombstone');
            $calls = $fixture->remote->calls;
            requireProof($fixture->operation()->expire($id, $fixture->owner) === 'already_deleted'
                && $fixture->operation()->reconcile($id, $fixture->owner) === 'already_deleted'
                && $fixture->remote->calls === $calls, 'deleted identity has no repeated effects');
        } finally { $fixture->cleanup(); }
    }
    $fixture = new Fixture();
    try {
        $id = $fixture->seed(); $fixture->remote->present = false;
        requireProof($fixture->operation()->reconcile($id, $fixture->owner) === 'unresolved'
            && $fixture->record()->metadata !== null, 'empty listing retains uncertain ownership and metadata');
        $fixture->remote->present = true;
        foreach (['sibling', 'multiple', 'marker', 'truncated', 'wrong_kms', 'wrong_metadata', 'storage_class'] as $fault) {
            $fixture->remote->fault = $fault;
            requireDocumentFailure(static fn () => $fixture->operation()->reconcile($id, $fixture->owner));
            requireProof($fixture->record()->state === 'reconciliation_required', 'ambiguous remote facts never promote');
        }
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $id = $fixture->seed('failed');
        requireProof($fixture->operation()->reconcile($id, $fixture->owner) === 'not_due'
            && $fixture->operation()->expire($id, $fixture->owner) === 'not_due'
            && $fixture->remote->calls === [] && $fixture->record()->metadata === null, 'collision tombstone never adopts or deletes another object');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $id = $fixture->seed('accepted'); $fixture->clock->value += 86_400;
        $fixture->remote->deleteTakesEffect = false;
        requireProof($fixture->operation()->expire($id, $fixture->owner) === 'unresolved' && $fixture->record()->attempts === 1, 'first deletion retains reservation');
        $before = count($fixture->remote->calls);
        requireProof($fixture->operation()->expire($id, $fixture->owner) === 'unresolved' && $fixture->record()->attempts === 2, 'one reconciled deletion');
        requireProof(array_slice($fixture->remote->calls, $before) === ['ListObjectVersions', 'HeadObject', 'DeleteObject', 'ListObjectVersions'], 'four-call bound');
        $before = count($fixture->remote->calls);
        requireProof($fixture->operation()->expire($id, $fixture->owner) === 'unresolved'
            && array_slice($fixture->remote->calls, $before) === ['ListObjectVersions']
            && $fixture->record()->metadata !== null, 'new invocation cannot reset deletion budget');
        $fixture->remote->present = false;
        requireProof($fixture->operation()->expire($id, $fixture->owner) === 'deleted' && $fixture->record()->metadata === null, 'late absence purges metadata');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $id = $fixture->seed('accepted'); $fixture->clock->value += 86_400;
        $fixture->remote->fault = 'lost_delete';
        $failed = false;
        try { $fixture->operation()->expire($id, $fixture->owner); } catch (RuntimeException) { $failed = true; }
        requireProof($failed && $fixture->record()->state === 'deletion_pending' && $fixture->record()->metadata !== null, 'lost delete remains private and reserved');
        $fixture->remote->fault = '';
        requireProof($fixture->operation()->expire($id, $fixture->owner) === 'deleted' && $fixture->record()->metadata === null
            && $fixture->remote->calls === ['DeleteObject', 'ListObjectVersions'], 'lost successful deletion reconciles without repetition');
    } finally { $fixture->cleanup(); }
}

function verifyMetadataS3FailureAccounting(): void
{
    $fixture = new Fixture();
    try {
        $id = $fixture->seed('accepted'); $fixture->clock->value += 86_400;
        $fixture->database->executeStatement("CREATE TRIGGER deny_claim BEFORE UPDATE ON documents WHEN NEW.delete_attempts > OLD.delete_attempts BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        try { $fixture->operation()->expire($id, $fixture->owner); throw new LogicException('Claim failure ignored.'); } catch (PDOException) {}
        requireProof($fixture->remote->calls === [] && $fixture->record()->metadata !== null, 'database claim precedes Delete');
        $fixture->database->executeStatement('DROP TRIGGER deny_claim');
        $fixture->database->executeStatement("CREATE TRIGGER deny_purge BEFORE UPDATE ON documents WHEN NEW.state = 'deleted' BEGIN SELECT RAISE(ABORT, 'fixture'); END");
        try { $fixture->operation()->expire($id, $fixture->owner); throw new LogicException('Purge failure ignored.'); } catch (PDOException) {}
        requireProof(!$fixture->remote->present && $fixture->record()->state === 'deletion_pending' && $fixture->record()->metadata !== null, 'post-delete database failure retains quota and descriptive binding');
        $fixture->database->executeStatement('DROP TRIGGER deny_purge');
        requireProof($fixture->operation()->expire($id, $fixture->owner) === 'deleted' && $fixture->record()->metadata === null, 'post-delete database recovery clears fields');
    } finally { $fixture->cleanup(); }
    $fixture = new Fixture();
    try {
        $id = $fixture->seed('stored'); $fixture->remote->fault = 'other_version';
        requireDocumentFailure(static fn () => $fixture->operation()->reconcile($id, $fixture->owner));
        requireProof($fixture->remote->calls === ['ListObjectVersions'] && $fixture->record()->metadata !== null, 'known version cannot change');
        $fixture->database->executeStatement('UPDATE documents SET target = :target', ['target' => str_repeat('0', 64)]);
        $before = count($fixture->remote->calls);
        requireDocumentFailure(static fn () => $fixture->operation()->reconcile($id, $fixture->owner));
        requireProof(count($fixture->remote->calls) === $before, 'changed target stops before S3');
    } finally { $fixture->cleanup(); }
}
