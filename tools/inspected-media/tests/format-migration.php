<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogImageFormat;
use CatalogImageRecipe\CatalogNotFound;
use CatalogImageRecipe\CatalogPrincipal;
use CatalogImageRecipe\CatalogUnavailable;
use CatalogImageRecipe\ImageRecord;
use CatalogImageRecipe\Tests\Fixture;
use CatalogImageRecipe\Tests\FixtureInspector;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

function verifyCatalogFormatMigration(): void
{
    // Statement semantics against the previous PNG-only schema, not an adopter's migration runner.
    $database = Connection::connect('sqlite::memory:', new QueryBudget(32), new QueryTrace(32));
    $database->executeStatement(
        'CREATE TABLE catalog_images (
            image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
            size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
            state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
            definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
            created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT
         )',
    );
    $database->executeStatement(
        'INSERT INTO catalog_images (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token)
         VALUES (:id, 1, :bytes, :digest, :state, :definition, 1, 100, 86500, NULL)',
        ['id' => str_repeat('a', 32), 'bytes' => strlen(catalogPng()), 'digest' => hash('sha256', catalogPng()),
            'state' => 'accepted', 'definition' => str_repeat('b', 64)],
    );
    $before = $database->selectOneRow('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token FROM catalog_images WHERE image_id = :id', ['id' => str_repeat('a', 32)]);
    requireCatalogProof($before !== null);
    if ($before === null) { throw new LogicException('Legacy fixture missing.'); }
    try { ImageRecord::fromDatabaseRow($before); throw new LogicException('Unmigrated row accepted.'); }
    catch (CatalogUnavailable) {}

    $database->beginTransaction();
    try {
        $database->executeStatement('ALTER TABLE catalog_images ADD COLUMN format TEXT NOT NULL DEFAULT \'png\' CHECK (format IN (\'png\', \'jpeg\'))');
        // Deliberate interruption before commit must not leave a partial schema.
    } finally { if ($database->inTransaction()) { $database->rollBack(); } }
    $rolledBack = $database->selectOneRow('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name', ['type' => 'table', 'name' => 'catalog_images']);
    requireCatalogProof($rolledBack !== null && is_string($rolledBack['sql']) && !str_contains($rolledBack['sql'], 'format'));

    $database->beginTransaction();
    try {
        $database->executeStatement('ALTER TABLE catalog_images ADD COLUMN format TEXT NOT NULL DEFAULT \'png\' CHECK (format IN (\'png\', \'jpeg\'))');
        $database->commit();
    } finally { if ($database->inTransaction()) { $database->rollBack(); } }
    $after = $database->selectOneRow('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images WHERE image_id = :id', ['id' => str_repeat('a', 32)]);
    if ($after === null) { throw new LogicException('Migrated row missing.'); }
    requireCatalogProof($after === $before + ['format' => 'png']);
    requireCatalogProof(ImageRecord::fromDatabaseRow($after)->format === CatalogImageFormat::Png);
    // The migration preserves acceptance history; definition activation separately invalidates it.
    foreach ([null, '', 'webp', 'JPEG', 1] as $invalid) {
        $row = $after;
        $row['format'] = $invalid;
        try { ImageRecord::fromDatabaseRow($row); throw new LogicException('Invalid format parsed.'); }
        catch (CatalogUnavailable) {}
    }
    try { $database->executeStatement('UPDATE catalog_images SET format = :format', ['format' => 'webp']); throw new LogicException('Unknown format stored.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET format = NULL'); throw new LogicException('Missing format stored.'); }
    catch (PDOException) {}
    try { $database->executeStatement('ALTER TABLE catalog_images ADD COLUMN format TEXT'); throw new LogicException('Blind migration rerun succeeded.'); }
    catch (PDOException) {}
    $database->executeStatement('UPDATE catalog_images SET format = :format', ['format' => 'jpeg']);
    $jpeg = $database->selectOneRow('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images WHERE image_id = :id', ['id' => str_repeat('a', 32)]);
    requireCatalogProof($jpeg !== null && ImageRecord::fromDatabaseRow($jpeg)->format === CatalogImageFormat::Jpeg);
}

function verifyJpegPromotionFailure(): void
{
    $fixture = new Fixture(dirname(__DIR__, 3) . '/tmp/png-proof-' . bin2hex(random_bytes(8)), format: CatalogImageFormat::Jpeg);
    $inspector = new FixtureInspector();
    try {
        $id = $fixture->seed(str_repeat('1', 32), catalogJpegSeeds()['sampling420']);
        $fixture->lock->acquire(true);
        $fixture->database->executeStatement('CREATE TRIGGER fail_promotion BEFORE UPDATE ON catalog_images WHEN NEW.state = \'accepted\' BEGIN SELECT RAISE(ABORT, \'fixture\'); END');
        try { $fixture->images->inspectOne($fixture->definition, $inspector); throw new LogicException('Failed promotion succeeded.'); }
        catch (PDOException) {}
        $record = $fixture->record($id);
        requireCatalogProof($record !== null && $record->state === 'inspecting' && $record->token !== null);
        try { $fixture->images->read($id, new CatalogPrincipal(1), CatalogImageFormat::Jpeg); throw new LogicException('Failed promotion served.'); }
        catch (CatalogNotFound) {}
        $fixture->database->executeStatement('DROP TRIGGER fail_promotion');
        requireCatalogProof($fixture->images->reconcileOne($inspector) === 'inspection_quarantined');
        requireCatalogProof($fixture->images->retry($id, $fixture->definition, $inspector) === 'retry_pending');
        $fixture->clock->value++;
        requireCatalogProof($fixture->images->inspectOne($fixture->definition, $inspector) === 'accepted');
        $fixture->clock->value += 86_400;
        file_put_contents($fixture->root->objectDirectory($id) . '/unexpected', 'unowned');
        try { $fixture->images->expireOne($inspector); throw new LogicException('Failed deletion released quota.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($fixture->record($id) !== null && is_file($fixture->root->objectDirectory($id) . '/unexpected'));
        unlink($fixture->root->objectDirectory($id) . '/unexpected');
        requireCatalogProof($fixture->images->expireOne($inspector) === 'expired' && $fixture->record($id) === null);
    } finally { $fixture->cleanup(); }
}
