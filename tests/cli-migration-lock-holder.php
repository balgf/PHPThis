<?php

declare(strict_types=1);

/** @var list<string> $arguments */
$arguments = $argv;

if (count($arguments) !== 2) {
    throw new InvalidArgumentException('Migration lock holder requires one database path.');
}

$databasePath = $arguments[1];

if (!str_starts_with($databasePath, DIRECTORY_SEPARATOR) || is_file($databasePath)) {
    throw new InvalidArgumentException('Migration lock holder database path is invalid.');
}

$lockPath = $databasePath . '.migration.lock';
$handle = fopen($lockPath, 'c+b');

if (
    !is_resource($handle)
    || !chmod($lockPath, 0600)
    || !flock($handle, LOCK_EX | LOCK_NB)
) {
    throw new RuntimeException('Migration lock holder could not acquire the local lock.');
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
sleep(60);

$unlocked = flock($handle, LOCK_UN);
$closed = fclose($handle);

if (!$unlocked || !$closed) {
    throw new RuntimeException('Migration lock holder could not release the local lock.');
}
