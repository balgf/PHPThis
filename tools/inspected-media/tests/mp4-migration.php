<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

const CATALOG_PRE_MP4_SCHEMA = 'CREATE TABLE catalog_images (
            image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
            size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
            state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
            definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
            created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
            format TEXT NOT NULL DEFAULT \'png\' CHECK (format IN (\'png\', \'jpeg\'))
         )';

// Exact schema transition from the documented PNG-to-JPEG migration, not a migration runner.
function applyCatalogMp4Migration(Connection $database, int $interruptAfter): void
{
    $baseline = $database->selectOneRow('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name', ['type' => 'table', 'name' => 'catalog_images']);
    $dependent = $database->selectOneRow('SELECT name FROM sqlite_schema WHERE tbl_name = :table AND sql IS NOT NULL AND type IN (:index_type, :trigger_type) LIMIT 1',
        ['table' => 'catalog_images', 'index_type' => 'index', 'trigger_type' => 'trigger']);
    if ($baseline === null || $baseline['sql'] !== CATALOG_PRE_MP4_SCHEMA || $dependent !== null) { throw new LogicException('Unreviewed migration baseline.'); }
    $database->executeStatement(
        'CREATE TABLE catalog_images_mp4 (
            image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
            size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
            state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
            definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
            created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
            format TEXT NOT NULL DEFAULT \'png\' CHECK (format IN (\'png\', \'jpeg\', \'mp4\'))
         )',
    );
    if ($interruptAfter === 1) { throw new LogicException('fixture interruption'); }
    $database->executeStatement(
        'INSERT INTO catalog_images_mp4 (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format)
         SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images',
    );
    if ($interruptAfter === 2) { throw new LogicException('fixture interruption'); }
    $database->executeStatement('DROP TABLE catalog_images');
    if ($interruptAfter === 3) { throw new LogicException('fixture interruption'); }
    $database->executeStatement('ALTER TABLE catalog_images_mp4 RENAME TO catalog_images');
    if ($interruptAfter === 4) { throw new LogicException('fixture interruption'); }
}

function verifyCatalogMp4MigrationAttempt(int $interruptAfter): void
{
    $database = Connection::connect('sqlite::memory:', new QueryBudget(40), new QueryTrace(40));
    $database->executeStatement(
        CATALOG_PRE_MP4_SCHEMA,
    );
    $database->executeStatement(
        'INSERT INTO catalog_images (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format)
         VALUES (:first, 1, 10, :digest_first, :accepted, :definition_first, 1, 100, 86500, NULL, :png),
                (:second, 2, 20, :digest_second, :inspecting, :definition_second, 2, 101, 86501, :token, :jpeg)',
        ['first' => str_repeat('1', 32), 'second' => str_repeat('2', 32), 'digest_first' => str_repeat('a', 64),
            'digest_second' => str_repeat('b', 64), 'definition_first' => str_repeat('c', 64), 'definition_second' => str_repeat('d', 64),
            'accepted' => 'accepted', 'inspecting' => 'inspecting', 'token' => str_repeat('e', 32), 'png' => 'png', 'jpeg' => 'jpeg'],
    );
    $before = $database->selectAllRows('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images ORDER BY image_id LIMIT 33');
    $database->beginTransaction();
    try {
        applyCatalogMp4Migration($database, $interruptAfter);
        $database->commit();
    } catch (LogicException $failure) {
        requireCatalogProof($interruptAfter >= 1 && $interruptAfter <= 4 && $failure->getMessage() === 'fixture interruption');
    } finally { if ($database->inTransaction()) { $database->rollBack(); } }
    $after = $database->selectAllRows('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images ORDER BY image_id LIMIT 33');
    requireCatalogProof($before === $after);
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_mp4']) === null);
    if ($interruptAfter !== 0) {
        try { $database->executeStatement('UPDATE catalog_images SET format = :format', ['format' => 'mp4']); throw new LogicException('Partial migration accepted MP4.'); }
        catch (PDOException) {}
        return;
    }
    requireCatalogProof($database->executeStatement('UPDATE catalog_images SET format = :format WHERE image_id = :id', ['format' => 'mp4', 'id' => str_repeat('2', 32)]) === 1);
    try { $database->executeStatement('UPDATE catalog_images SET format = :format', ['format' => 'webm']); throw new LogicException('Unknown format stored.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET format = NULL'); throw new LogicException('Missing format stored.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET attempts = 3'); throw new LogicException('Retry constraint lost.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET expires_at = 1'); throw new LogicException('Expiry constraint lost.'); }
    catch (PDOException) {}
    try { applyCatalogMp4Migration($database, 0); throw new RuntimeException('Blind migration rerun succeeded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_mp4']) === null);
    // Other dependent objects/topologies remain the adopting application's explicit baseline obligation.
    $schema = $database->selectOneRow('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name', ['type' => 'table', 'name' => 'catalog_images']);
    requireCatalogProof($schema !== null && is_string($schema['sql']) && str_contains($schema['sql'], "'mp4'"));
}

function verifyCatalogMp4Migration(): void
{
    verifyCatalogMp4MigrationAttempt(1);
    verifyCatalogMp4MigrationAttempt(2);
    verifyCatalogMp4MigrationAttempt(3);
    verifyCatalogMp4MigrationAttempt(4);
    verifyCatalogMp4MigrationAttempt(0);
    verifyCatalogMp4MigrationBaselineRejection();
}

function verifyCatalogMp4MigrationBaselineRejection(): void
{
    $database = Connection::connect('sqlite::memory:', new QueryBudget(16), new QueryTrace(16));
    $database->executeStatement(CATALOG_PRE_MP4_SCHEMA);
    $database->executeStatement('CREATE INDEX unexpected_owner_index ON catalog_images (owner_id)');
    try { applyCatalogMp4Migration($database, 0); throw new RuntimeException('Unreviewed index was discarded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_mp4']) === null);
    $database->executeStatement('DROP INDEX unexpected_owner_index');
    $database->executeStatement('ALTER TABLE catalog_images ADD COLUMN unreviewed TEXT');
    try { applyCatalogMp4Migration($database, 0); throw new RuntimeException('Unreviewed column was discarded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_mp4']) === null);
}
