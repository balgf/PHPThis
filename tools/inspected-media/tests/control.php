<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogLock;
use CatalogImageRecipe\CatalogRoot;
use CatalogImageRecipe\ImageId;
use CatalogImageRecipe\ImageRecord;
use CatalogImageRecipe\DockerCatalogInspector;
use CatalogImageRecipe\InspectionDefinition;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__) . '/bootstrap.php';
$lock = null;
try {
    if ($argc < 3 || $argc > 4 || !str_contains($argv[1], '/tmp/png-proof-')) { throw new InvalidArgumentException('Invalid fixture arguments.'); }
    $root = CatalogRoot::fromInput($argv[1]);
    $lock = new CatalogLock($root);
    $lock->acquire(true);
    $database = Connection::connect('sqlite:' . $root->path . '/catalog.sqlite', new QueryBudget(4), new QueryTrace(4));
    $id = isset($argv[3]) && $argv[2] !== 'containers' ? new ImageId($argv[3]) : null;
    if ($argv[2] === 'containers' && isset($argv[3])) {
        $inspector = new DockerCatalogInspector(InspectionDefinition::fromImageId($argv[3], dirname(__DIR__)));
        $rows = $database->selectAllRows('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images WHERE inspection_token IS NOT NULL LIMIT 32');
        foreach ($rows as $row) {
            $record = ImageRecord::fromDatabaseRow($row);
            if ($record->token !== null && !$inspector->discard($record->token)) { throw new RuntimeException('Fixture container cleanup failed.'); }
        }
        echo "CLEAN\n";
    } elseif ($argv[2] === 'expire' && $id !== null) {
        $now = time();
        $database->executeStatement('UPDATE catalog_images SET created_at = :created, expires_at = :expires WHERE image_id = :id',
            ['created' => $now - 86_400, 'expires' => $now, 'id' => $id->value]);
        echo "EXPIRED\n";
    } elseif ($argv[2] === 'state' && $id !== null) {
        $row = $database->selectOneRow('SELECT image_id, owner_id, size_bytes, digest, state, definition, attempts, created_at, expires_at, inspection_token, format FROM catalog_images WHERE image_id = :id', ['id' => $id->value]);
        $record = $row === null ? null : ImageRecord::fromDatabaseRow($row);
        echo json_encode($record === null ? null : ['state' => $record->state, 'attempts' => $record->attempts, 'bytes' => $record->bytes, 'format' => $record->format->value], JSON_THROW_ON_ERROR), "\n";
    } else {
        throw new InvalidArgumentException('Invalid fixture command.');
    }
} catch (Throwable) {
    fwrite(STDERR, "CONTROL FAILED\n");
    exit(1);
} finally {
    if ($lock instanceof CatalogLock) { $lock->release(); }
}
