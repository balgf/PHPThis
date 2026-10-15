<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

use PHPThis\Database\Connection;
use PHPThis\Http\LocalFileBody;

final readonly class CatalogImages
{
    public function __construct(
        private Connection $database, private CatalogRoot $root,
        private CatalogLock $lock, private CatalogClock $clock,
    ) {}

    public function retain(PendingImageUpload $upload, CatalogPrincipal $owner): ImageId
    {
        $this->lock->requireHeld(true);
        $now = $this->now();
        $settings = $this->settings();
        if ($settings->sourceFingerprint !== InspectionDefinition::sourceFingerprint(dirname(__DIR__))) {
            throw new CatalogUnavailable('Catalog definition requires activation.');
        }
        $id = new ImageId(bin2hex(random_bytes(16)));
        // The committed reservation precedes filesystem mutation and survives a crash.
        $reserved = $this->database->executeStatement(
            'INSERT INTO catalog_images (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token)
             SELECT :id, :owner, :bytes, NULL, :state, :definition, 0, :created, :expires, NULL
             WHERE (SELECT COUNT(*) FROM catalog_images) < 32
               AND (SELECT COALESCE(SUM(size_bytes), 0) FROM catalog_images) + :quota_bytes <= 33554432',
            ['id' => $id->value, 'owner' => $owner->id, 'bytes' => $upload->bytes, 'state' => 'reserved',
                'definition' => $settings->definition, 'created' => $now, 'expires' => $now + 86_400, 'quota_bytes' => $upload->bytes],
        );
        if ($reserved !== 1) { throw new CatalogBusy('Catalog capacity unavailable.'); }
        $directory = $this->root->objectDirectory($id);
        if (!@mkdir($directory, 0700)) { throw new CatalogUnavailable('Catalog reservation incomplete.'); }
        $this->root->requireMode($directory, 0040000, 0700);
        $path = $this->root->contentPath($id);
        if (!@move_uploaded_file($upload->temporaryPath, $path) || !@chmod($path, 0600)) {
            throw new CatalogUnavailable('Catalog move incomplete.');
        }
        $this->root->requireMode($path, 0100000, 0600);
        $bytes = @file_get_contents($path, false, null, 0, 1_048_577);
        if (!is_string($bytes) || strlen($bytes) !== $upload->bytes) {
            throw new CatalogUnavailable('Catalog move could not be verified.');
        }
        $written = $this->database->executeStatement(
            'UPDATE catalog_images SET digest = :digest, state = :pending WHERE image_id = :id AND state = :reserved',
            ['digest' => hash('sha256', $bytes), 'pending' => 'pending', 'id' => $id->value, 'reserved' => 'reserved'],
        );
        if ($written !== 1) { throw new CatalogUnavailable('Catalog promotion failed.'); }
        return $id;
    }

    public function read(ImageId $id, CatalogPrincipal $owner): LocalFileBody
    {
        $this->lock->requireHeld(false);
        $row = $this->database->selectOneRow(
            'SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token
             FROM catalog_images WHERE image_id = :id AND owner_id = :owner AND state = :accepted
             AND expires_at > :now AND definition = (SELECT definition FROM catalog_policy WHERE singleton = 1 AND source_fingerprint = :source)',
            ['id' => $id->value, 'owner' => $owner->id, 'accepted' => 'accepted', 'now' => $this->now(),
                'source' => InspectionDefinition::sourceFingerprint(dirname(__DIR__))],
        );
        if ($row === null) { throw new CatalogNotFound('Image not available.'); }
        $record = ImageRecord::fromDatabaseRow($row);
        $this->root->bytes($record);
        return new LocalFileBody($this->root->contentPath($record->id), $record->bytes);
    }

    public function activate(InspectionDefinition $definition): string
    {
        $this->lock->requireHeld(true);
        if ($this->settings()->definition === $definition->value) { return 'already_active'; }
        $this->database->beginTransaction();
        try {
            // Retired definitions cannot resurrect old acceptance or reset their retry history.
            $created = $this->database->executeStatement(
                'INSERT INTO catalog_definitions (definition) SELECT :definition WHERE (SELECT COUNT(*) FROM catalog_definitions) < 32
                 ON CONFLICT (definition) DO NOTHING', ['definition' => $definition->value],
            );
            if ($created !== 1) { throw new CatalogUnavailable('Definition is retired or registry is full.'); }
            $changed = $this->database->executeStatement(
                'UPDATE catalog_policy SET definition = :definition, source_fingerprint = :source WHERE singleton = 1',
                ['definition' => $definition->value, 'source' => $definition->sourceFingerprint],
            );
            if ($changed !== 1) { throw new CatalogUnavailable('Definition activation failed.'); }
            $this->database->commit();
        } finally {
            if ($this->database->inTransaction()) { $this->database->rollBack(); }
        }
        return 'activated';
    }

    public function inspectOne(InspectionDefinition $definition, PngInspector $inspector): string
    {
        $this->lock->requireHeld(true);
        $now = $this->now();
        $settings = $this->settings();
        if ($settings->definition !== $definition->value) { throw new CatalogUnavailable('Inspector definition is not active.'); }
        if ($now < $settings->lastStarted + 1) { return 'not_due'; }
        $row = $this->database->selectOneRow(
            'SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token
             FROM catalog_images WHERE expires_at > :now AND state NOT IN (:reserved, :inspecting) AND inspection_token IS NULL
             AND (definition <> :definition OR state = :pending) ORDER BY created_at, image_id LIMIT 1',
            ['now' => $now, 'reserved' => 'reserved', 'inspecting' => 'inspecting', 'definition' => $definition->value, 'pending' => 'pending'],
        );
        if ($row === null) { return 'empty'; }
        $record = ImageRecord::fromDatabaseRow($row);
        $attempt = $record->definition === $definition->value ? $record->attempts + 1 : 1;
        if ($attempt > 2) { throw new CatalogUnavailable('Inspection retry exhausted.'); }
        $token = bin2hex(random_bytes(16));
        $this->database->beginTransaction();
        try {
            $claimed = $this->database->executeStatement(
                'UPDATE catalog_images SET state = :state, definition = :definition, attempts = :attempt, inspection_token = :token WHERE image_id = :id',
                ['state' => 'inspecting', 'definition' => $definition->value, 'attempt' => $attempt, 'token' => $token, 'id' => $record->id->value],
            );
            $paced = $this->database->executeStatement('UPDATE catalog_policy SET last_started = :now WHERE singleton = 1', ['now' => $now]);
            if ($claimed !== 1 || $paced !== 1) { throw new CatalogUnavailable('Inspection claim failed.'); }
            $this->database->commit();
        } finally {
            if ($this->database->inTransaction()) { $this->database->rollBack(); }
        }
        try {
            $outcome = $inspector->inspect($this->root->bytes($record), $token);
            // Verify the immutable retained input again before accepting the result.
            $this->root->bytes($record);
        } catch (\Throwable) {
            $outcome = InspectionOutcome::ScannerError;
        }
        $cleaned = $inspector->discard($token);
        if (!$cleaned) { $outcome = InspectionOutcome::ScannerError; }
        $finished = $this->database->executeStatement(
            'UPDATE catalog_images SET state = :state, inspection_token = :remaining_token WHERE image_id = :id AND state = :inspecting
             AND inspection_token = :token AND definition = :definition',
            ['state' => $outcome->value, 'remaining_token' => $cleaned ? null : $token, 'id' => $record->id->value, 'inspecting' => 'inspecting',
                'token' => $token, 'definition' => $definition->value],
        );
        if ($finished !== 1 || $outcome === InspectionOutcome::ScannerError) {
            throw new CatalogUnavailable('Image inspection unavailable.');
        }
        return $outcome->value;
    }

    public function retry(ImageId $id, InspectionDefinition $definition, PngInspector $inspector): string
    {
        $this->lock->requireHeld(true);
        if ($this->settings()->definition !== $definition->value) { throw new CatalogUnavailable('Inactive inspector.'); }
        $row = $this->database->selectOneRow(
            'SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token
             FROM catalog_images WHERE image_id = :id AND definition = :definition AND state = :error AND attempts < 2 AND expires_at > :now',
            ['id' => $id->value, 'definition' => $definition->value, 'error' => 'scanner_error', 'now' => $this->now()],
        );
        if ($row === null) { return 'ineligible'; }
        $record = ImageRecord::fromDatabaseRow($row);
        if ($record->token !== null && !$inspector->discard($record->token)) { throw new CatalogUnavailable('Inspection cleanup unresolved.'); }
        $changed = $this->database->executeStatement(
            'UPDATE catalog_images SET state = :pending, inspection_token = NULL WHERE image_id = :id',
            ['pending' => 'pending', 'id' => $id->value],
        );
        if ($changed !== 1) { throw new CatalogUnavailable('Inspection retry failed.'); }
        return 'retry_pending';
    }

    public function reconcileOne(PngInspector $inspector): string
    {
        $this->lock->requireHeld(true);
        $row = $this->database->selectOneRow(
            'SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token
             FROM catalog_images WHERE state IN (:reserved, :inspecting) OR (state = :error AND inspection_token IS NOT NULL)
             ORDER BY created_at, image_id LIMIT 1',
            ['reserved' => 'reserved', 'inspecting' => 'inspecting', 'error' => 'scanner_error'],
        );
        if ($row === null) { return 'empty'; }
        $record = ImageRecord::fromDatabaseRow($row);
        if ($record->state === 'reserved') {
            $this->root->remove($record->id);
            $this->database->executeStatement('DELETE FROM catalog_images WHERE image_id = :id', ['id' => $record->id->value]);
            return 'reservation_removed';
        }
        if ($record->token === null || !$inspector->discard($record->token)) { throw new CatalogUnavailable('Inspection cleanup unresolved.'); }
        $this->database->executeStatement('UPDATE catalog_images SET state = :state, inspection_token = NULL WHERE image_id = :id',
            ['state' => 'scanner_error', 'id' => $record->id->value]);
        return 'inspection_quarantined';
    }

    public function expireOne(PngInspector $inspector): string
    {
        $this->lock->requireHeld(true);
        $row = $this->database->selectOneRow(
            'SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token
             FROM catalog_images WHERE expires_at <= :now ORDER BY created_at, image_id LIMIT 1', ['now' => $this->now()],
        );
        if ($row === null) { return 'empty'; }
        $record = ImageRecord::fromDatabaseRow($row);
        if ($record->token !== null && !$inspector->discard($record->token)) { throw new CatalogUnavailable('Inspection cleanup unresolved.'); }
        $this->root->remove($record->id);
        $removed = $this->database->executeStatement('DELETE FROM catalog_images WHERE image_id = :id', ['id' => $record->id->value]);
        if ($removed !== 1) { throw new CatalogUnavailable('Catalog capacity release failed.'); }
        return 'expired';
    }

    private function settings(): CatalogSettings
    {
        $row = $this->database->selectOneRow('SELECT definition, source_fingerprint, last_started FROM catalog_policy WHERE singleton = 1');
        if ($row === null) { throw new CatalogUnavailable('Catalog policy missing.'); }
        return CatalogSettings::fromDatabaseRow($row);
    }

    private function now(): int
    {
        $now = $this->clock->now();
        if ($now < 1 || $now > PHP_INT_MAX - 86_400) { throw new CatalogUnavailable('Catalog clock unavailable.'); }
        return $now;
    }
}
