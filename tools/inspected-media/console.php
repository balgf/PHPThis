<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogImages;
use CatalogImageRecipe\CatalogLock;
use CatalogImageRecipe\DockerPngInspector;
use CatalogImageRecipe\ImageId;
use CatalogImageRecipe\ReferenceEnvironment;
use CatalogImageRecipe\SystemCatalogClock;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

$lock = null;
$exitCode = 0;
umask(0077);
try {
    $command = $argv[1] ?? '';
    if (!in_array($command, ['images:activate-definition', 'images:inspect-one', 'images:retry', 'images:reconcile-one', 'images:expire-one'], true)) {
        fwrite(STDERR, "{\"error\":\"unknown_command\"}\n");
        exit(2);
    }
    if ($argc !== ($command === 'images:retry' ? 3 : 2)
        || ($command === 'images:retry' && preg_match('/\A[0-9a-f]{32}\z/D', $argv[2] ?? '') !== 1)) {
        fwrite(STDERR, "{\"error\":\"invalid_arguments\"}\n");
        exit(2);
    }
    require __DIR__ . '/bootstrap.php';
    $id = $command === 'images:retry' ? new ImageId($argv[2]) : null;
    $configuration = ReferenceEnvironment::worker();
    $lock = new CatalogLock($configuration->root);
    $lock->acquire(true);
    $database = Connection::connect('sqlite:' . $configuration->root->path . '/catalog.sqlite', new QueryBudget(16), new QueryTrace(16), options: [PDO::ATTR_TIMEOUT => 1]);
    $images = new CatalogImages($database, $configuration->root, $lock, new SystemCatalogClock());
    $inspector = new DockerPngInspector($configuration->definition);
    $outcome = match ($command) {
        'images:activate-definition' => $images->activate($configuration->definition),
        'images:inspect-one' => $images->inspectOne($configuration->definition, $inspector),
        'images:retry' => $id !== null ? $images->retry($id, $configuration->definition, $inspector) : throw new LogicException('Missing retry identity.'),
        'images:reconcile-one' => $images->reconcileOne($inspector),
        'images:expire-one' => $images->expireOne($inspector),
    };
    fwrite(STDOUT, json_encode(['command' => $command, 'outcome' => $outcome], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable) {
    fwrite(STDERR, "{\"error\":\"command_failed\"}\n");
    $exitCode = 1;
} finally {
    if ($lock instanceof CatalogLock) { $lock->release(); }
}
exit($exitCode);
