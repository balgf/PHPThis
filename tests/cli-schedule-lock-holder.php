<?php

declare(strict_types=1);

/** @var list<string> $arguments */
$arguments = $argv;

if (count($arguments) !== 2) {
    throw new InvalidArgumentException('Schedule lock holder requires one database path.');
}

$databasePath = $arguments[1];

if (!str_starts_with($databasePath, DIRECTORY_SEPARATOR) || !is_file($databasePath)) {
    throw new InvalidArgumentException('Schedule lock holder database path is invalid.');
}

$handle = fopen($databasePath . '.schedule.lock', 'c+b');

if (!is_resource($handle) || !flock($handle, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Schedule lock holder could not acquire the local lock.');
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
sleep(60);

$unlocked = flock($handle, LOCK_UN);
$closed = fclose($handle);

if (!$unlocked || !$closed) {
    throw new RuntimeException('Schedule lock holder could not release the local lock.');
}
