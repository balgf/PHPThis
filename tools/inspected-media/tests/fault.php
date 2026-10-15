<?php

declare(strict_types=1);

$mode = fgets(STDIN, 32);
if ($mode === "timeout\n") {
    sleep(30);
} elseif (in_array($mode, ["memory\n", "memory_small\n", "memory_kill\n"], true)) {
    require __DIR__ . '/memory-fault.php';
    exit(runMemoryFault($mode));
} elseif ($mode === "output\n") {
    fwrite(STDOUT, str_repeat('x', 16_384));
} elseif ($mode === "cpu\n") {
    $deadline = hrtime(true) + 30_000_000_000;
    $value = 'cpu-fixture';
    while (hrtime(true) < $deadline) { $value = hash('sha256', $value); }
    echo strlen($value);
} elseif ($mode === "exit\n") {
    exit(17);
}
