<?php

declare(strict_types=1);

use Example\ApplicationComposition;
use Example\ApplicationDatabasePath;

$databasePath = ApplicationDatabasePath::fromString(
    dirname(__DIR__) . '/tmp/example.sqlite',
);

return (new ApplicationComposition($databasePath))->http();
