<?php

declare(strict_types=1);

namespace CatalogImageRecipe\Tests;

use CatalogImageRecipe\CatalogClock;
use CatalogImageRecipe\CatalogImageFormat;
use CatalogImageRecipe\CatalogImages;
use CatalogImageRecipe\CatalogLock;
use CatalogImageRecipe\CatalogRoot;
use CatalogImageRecipe\ImageId;
use CatalogImageRecipe\ImageRecord;
use CatalogImageRecipe\InspectionDefinition;
use CatalogImageRecipe\InspectionOutcome;
use CatalogImageRecipe\CatalogInspector;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final class FixtureClock implements CatalogClock
{
    public function __construct(public int $value) {}
    public function now(): int { return $this->value; }
}

final class FixtureInspector implements CatalogInspector
{
    public InspectionOutcome $outcome = InspectionOutcome::Accepted;
    public bool $cleanup = true;
    public int $calls = 0;
    public ?CatalogImageFormat $format = null;
    public function inspect(string $bytes, string $token, CatalogImageFormat $format): InspectionOutcome { $this->format = $format; $this->calls++; return $this->outcome; }
    public function discard(string $token): bool { return $this->cleanup; }
}

final readonly class Fixture
{
    public CatalogRoot $root;
    public Connection $database;
    public CatalogLock $lock;
    public FixtureClock $clock;
    public CatalogImages $images;
    public InspectionDefinition $definition;

    public function __construct(string $path, string $image = 'sha256:1111111111111111111111111111111111111111111111111111111111111111', private CatalogImageFormat $format = CatalogImageFormat::Png)
    {
        if (!str_contains($path, '/tmp/png-proof-') || file_exists($path)) { throw new \RuntimeException('Fixture path unavailable.'); }
        mkdir($path, 0700, true);
        mkdir($path . '/objects', 0700);
        touch($path . '/catalog.sqlite'); chmod($path . '/catalog.sqlite', 0600);
        touch($path . '/catalog.lock'); chmod($path . '/catalog.lock', 0600);
        $this->root = CatalogRoot::fromInput($path);
        $this->database = Connection::connect('sqlite:' . $path . '/catalog.sqlite', new QueryBudget(500), new QueryTrace(64));
        $this->database->executeStatement('CREATE TABLE catalog_policy (singleton INTEGER PRIMARY KEY CHECK (singleton = 1), definition TEXT NOT NULL, source_fingerprint TEXT NOT NULL, last_started INTEGER NOT NULL)');
        $this->database->executeStatement('CREATE TABLE catalog_definitions (definition TEXT PRIMARY KEY)');
        $this->database->executeStatement(
            'CREATE TABLE catalog_images (
                image_id TEXT PRIMARY KEY, owner_id INTEGER NOT NULL CHECK (owner_id > 0),
                size_bytes INTEGER NOT NULL CHECK (size_bytes BETWEEN 1 AND 1048576), digest TEXT,
                state TEXT NOT NULL CHECK (state IN (\'reserved\', \'pending\', \'inspecting\', \'accepted\', \'rejected\', \'scanner_error\')),
                definition TEXT NOT NULL, attempts INTEGER NOT NULL CHECK (attempts BETWEEN 0 AND 2),
                created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL CHECK (expires_at = created_at + 86400), inspection_token TEXT,
                format TEXT NOT NULL CHECK (format IN (\'png\', \'jpeg\'))
             )',
        );
        $this->definition = InspectionDefinition::fromImageId($image, dirname(__DIR__));
        $this->database->executeStatement('INSERT INTO catalog_policy (singleton, definition, source_fingerprint, last_started) VALUES (1, :definition, :source, 0)',
            ['definition' => $this->definition->value, 'source' => $this->definition->sourceFingerprint]);
        $this->database->executeStatement('INSERT INTO catalog_definitions (definition) VALUES (:definition)', ['definition' => $this->definition->value]);
        $this->lock = new CatalogLock($this->root);
        $this->clock = new FixtureClock(time());
        $this->images = new CatalogImages($this->database, $this->root, $this->lock, $this->clock);
    }

    public function seed(string $hex, string $bytes, string $state = 'pending', int $attempts = 0, ?string $token = null): ImageId
    {
        $id = new ImageId($hex);
        mkdir($this->root->objectDirectory($id), 0700);
        file_put_contents($this->root->contentPath($id), $bytes); chmod($this->root->contentPath($id), 0600);
        $this->database->executeStatement(
            'INSERT INTO catalog_images (image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format)
             VALUES (:id, 1, :bytes, :digest, :state, (SELECT definition FROM catalog_policy WHERE singleton = 1), :attempts, :created, :expires, :token, :format)',
            ['id' => $id->value, 'bytes' => strlen($bytes), 'digest' => $state === 'reserved' ? null : hash('sha256', $bytes),
                'state' => $state, 'attempts' => $attempts,
                'created' => $this->clock->value, 'expires' => $this->clock->value + 86_400, 'token' => $token, 'format' => $this->format->value],
        );
        return $id;
    }

    public function record(ImageId $id): ?ImageRecord
    {
        $row = $this->database->selectOneRow('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images WHERE image_id = :id', ['id' => $id->value]);
        return $row === null ? null : ImageRecord::fromDatabaseRow($row);
    }

    public function cleanup(): void
    {
        $this->lock->release();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root->path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) { throw new \RuntimeException('Invalid fixture entry.'); }
            if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
            else { unlink($entry->getPathname()); }
        }
        rmdir($this->root->path);
    }
}
