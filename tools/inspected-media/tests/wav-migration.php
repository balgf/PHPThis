<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

// Exact SQLite transition from the two recorded pre-WAV baselines. The existing fixture has no format default;
// the documented prior upgrade retains DEFAULT 'png'. Preserve each exact baseline.
const WAV_UPGRADED_BASELINE = 'CREATE TABLE "catalog_images" (
            image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
            size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
            state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
            definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
            created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
            format TEXT NOT NULL DEFAULT \'png\' CHECK (format IN (\'png\', \'jpeg\', \'mp4\'))
         )';
const WAV_FRESH_BASELINE = 'CREATE TABLE catalog_images (
                image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
                size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
                state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
                definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
                created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
                format TEXT NOT NULL CHECK (format IN (\'png\', \'jpeg\', \'mp4\'))
             )';

function applyCatalogWavMigration(Connection $database, int $interruptAfter): void
{
    if (!$database->inTransaction()) { throw new LogicException('Transaction required.'); }
    $baseline = $database->selectOneRow('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name', ['type' => 'table', 'name' => 'catalog_images']);
    $dependent = $database->selectOneRow('SELECT name FROM sqlite_schema WHERE tbl_name = :table AND sql IS NOT NULL AND type IN (:index_type, :trigger_type) LIMIT 1',
        ['table' => 'catalog_images', 'index_type' => 'index', 'trigger_type' => 'trigger']);
    if ($baseline === null || $dependent !== null) { throw new LogicException('Unreviewed migration baseline.'); }
    if ($baseline['sql'] === WAV_UPGRADED_BASELINE) {
        $database->executeStatement(
            'CREATE TABLE catalog_images_wav (
                image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
                size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
                state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
                definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
                created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
                format TEXT NOT NULL DEFAULT \'png\' CHECK (format IN (\'png\', \'jpeg\', \'mp4\', \'wav\'))
             )',
        );
    } elseif ($baseline['sql'] === WAV_FRESH_BASELINE) {
        $database->executeStatement(
            'CREATE TABLE catalog_images_wav (
                image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
                size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
                state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
                definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
                created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
                format TEXT NOT NULL CHECK (format IN (\'png\', \'jpeg\', \'mp4\', \'wav\'))
             )',
        );
    } else { throw new LogicException('Unreviewed migration baseline.'); }
    if ($interruptAfter === 1) { throw new LogicException('fixture interruption'); }
    $database->executeStatement(
        'INSERT INTO catalog_images_wav (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format)
         SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images',
    );
    if ($interruptAfter === 2) { throw new LogicException('fixture interruption'); }
    $database->executeStatement('DROP TABLE catalog_images');
    if ($interruptAfter === 3) { throw new LogicException('fixture interruption'); }
    $database->executeStatement('ALTER TABLE catalog_images_wav RENAME TO catalog_images');
    if ($interruptAfter === 4) { throw new LogicException('fixture interruption'); }
}

function verifyCatalogWavMigrationAttempt(bool $upgraded, int $interruptAfter): void
{
    $database = Connection::connect('sqlite::memory:', new QueryBudget(48), new QueryTrace(48));
    if ($upgraded) {
        // Reproduce the existing upgrade, rather than assuming its resulting DDL.
        $database->executeStatement(CATALOG_PRE_MP4_SCHEMA);
        $database->beginTransaction();
        try { applyCatalogMp4Migration($database, 0); $database->commit(); }
        finally { if ($database->inTransaction()) { $database->rollBack(); } }
    } else { $database->executeStatement(WAV_FRESH_BASELINE); }
    $database->executeStatement(
        'INSERT INTO catalog_images (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format)
         VALUES (:first, 1, 100, :digest_first, :accepted, :definition_first, 1, 100, 86500, NULL, :png),
                (:second, 2, 200, :digest_second, :inspecting, :definition_second, 2, 101, 86501, :token_second, :jpeg),
                (:third, 3, 300, :digest_third, :scanner_error, :definition_third, 2, 102, 86502, :token_third, :mp4)',
        ['first' => str_repeat('1', 32), 'second' => str_repeat('2', 32), 'third' => str_repeat('3', 32),
            'digest_first' => str_repeat('a', 64), 'digest_second' => str_repeat('b', 64), 'digest_third' => str_repeat('c', 64),
            'definition_first' => str_repeat('d', 64), 'definition_second' => str_repeat('e', 64), 'definition_third' => str_repeat('f', 64),
            'accepted' => 'accepted', 'inspecting' => 'inspecting', 'scanner_error' => 'scanner_error',
            'token_second' => str_repeat('e', 32), 'token_third' => str_repeat('f', 32), 'png' => 'png', 'jpeg' => 'jpeg', 'mp4' => 'mp4'],
    );
    $before = $database->selectAllRows('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images ORDER BY image_id LIMIT 33');
    $schemaBefore = $database->selectOneRow('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name', ['type' => 'table', 'name' => 'catalog_images']);
    requireCatalogProof($schemaBefore !== null && $schemaBefore['sql'] === ($upgraded ? WAV_UPGRADED_BASELINE : WAV_FRESH_BASELINE));
    $database->beginTransaction();
    try { applyCatalogWavMigration($database, $interruptAfter); $database->commit(); }
    catch (LogicException $failure) { requireCatalogProof($interruptAfter > 0 && $failure->getMessage() === 'fixture interruption'); }
    finally { if ($database->inTransaction()) { $database->rollBack(); } }
    $after = $database->selectAllRows('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images ORDER BY image_id LIMIT 33');
    requireCatalogProof($before === $after);
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_wav']) === null);
    if ($interruptAfter !== 0) {
        requireCatalogProof($database->selectOneRow('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name', ['type' => 'table', 'name' => 'catalog_images']) === $schemaBefore);
        return;
    }
    requireCatalogProof($database->executeStatement('UPDATE catalog_images SET format = :format WHERE image_id = :id', ['format' => 'wav', 'id' => str_repeat('3', 32)]) === 1);
    $formatColumn = $database->selectOneRow('SELECT dflt_value FROM pragma_table_info(:table) WHERE name = :column', ['table' => 'catalog_images', 'column' => 'format']);
    requireCatalogProof($formatColumn !== null && $formatColumn['dflt_value'] === ($upgraded ? "'png'" : null));
    try { $database->executeStatement('UPDATE catalog_images SET format = :format', ['format' => 'mp3']); throw new RuntimeException('Unknown format stored.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET format = NULL'); throw new RuntimeException('NULL stored.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET attempts = 3'); throw new RuntimeException('Retry constraint lost.'); }
    catch (PDOException) {}
    try { $database->executeStatement('UPDATE catalog_images SET expires_at = 1'); throw new RuntimeException('Expiry constraint lost.'); }
    catch (PDOException) {}
    $database->beginTransaction();
    try { applyCatalogWavMigration($database, 0); throw new RuntimeException('Blind rerun succeeded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    finally { $database->rollBack(); }
}

function verifyCatalogWavMigration(): void
{
    verifyCatalogWavMigrationAttempt(false, 0);
    verifyCatalogWavMigrationAttempt(false, 1);
    verifyCatalogWavMigrationAttempt(false, 2);
    verifyCatalogWavMigrationAttempt(false, 3);
    verifyCatalogWavMigrationAttempt(false, 4);
    verifyCatalogWavMigrationAttempt(true, 0);
    verifyCatalogWavMigrationAttempt(true, 1);
    verifyCatalogWavMigrationAttempt(true, 2);
    verifyCatalogWavMigrationAttempt(true, 3);
    verifyCatalogWavMigrationAttempt(true, 4);
    verifyCatalogWavMigrationBaselineRejection();
}

function verifyCatalogWavMigrationBaselineRejection(): void
{
    $database = Connection::connect('sqlite::memory:', new QueryBudget(40), new QueryTrace(40));
    $database->executeStatement(WAV_FRESH_BASELINE);
    try { applyCatalogWavMigration($database, 0); throw new RuntimeException('Nontransactional migration started.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Transaction required.'); }
    $database->executeStatement('CREATE INDEX unexpected_owner_index ON catalog_images (owner_id)');
    $database->beginTransaction();
    try { applyCatalogWavMigration($database, 0); throw new RuntimeException('Unreviewed index discarded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    finally { $database->rollBack(); }
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_wav']) === null);
    $database->executeStatement('DROP INDEX unexpected_owner_index');
    $database->executeStatement('CREATE TRIGGER unexpected_trigger BEFORE UPDATE ON catalog_images BEGIN SELECT RAISE(ABORT, \'fixture\'); END');
    $database->beginTransaction();
    try { applyCatalogWavMigration($database, 0); throw new RuntimeException('Unreviewed trigger discarded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    finally { $database->rollBack(); }
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_wav']) === null);
    $database->executeStatement('DROP TRIGGER unexpected_trigger');
    $database->executeStatement('ALTER TABLE catalog_images ADD COLUMN unreviewed TEXT');
    $database->beginTransaction();
    try { applyCatalogWavMigration($database, 0); throw new RuntimeException('Unreviewed column discarded.'); }
    catch (LogicException $failure) { requireCatalogProof($failure->getMessage() === 'Unreviewed migration baseline.'); }
    finally { $database->rollBack(); }
    requireCatalogProof($database->selectOneRow('SELECT name FROM sqlite_schema WHERE name = :name', ['name' => 'catalog_images_wav']) === null);
}
