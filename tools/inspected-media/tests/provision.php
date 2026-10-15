<?php

declare(strict_types=1);

use CatalogImageRecipe\Tests\Fixture;

try {
    if ($argc !== 3) { throw new InvalidArgumentException('Invalid fixture arguments.'); }
    umask(0077);
    require dirname(__DIR__) . '/bootstrap.php';
    require __DIR__ . '/Fixture.php';
    new Fixture($argv[1], $argv[2]);
    fwrite(STDOUT, "PROVISIONED\n");
} catch (Throwable) {
    fwrite(STDERR, "PROVISION FAILED\n");
    exit(1);
}
