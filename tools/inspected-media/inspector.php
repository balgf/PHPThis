<?php

declare(strict_types=1);

use CatalogImageRecipe\BoundedPng;
use CatalogImageRecipe\RejectedPng;

require __DIR__ . '/src/Failures.php';
require __DIR__ . '/src/PngHeader.php';
require __DIR__ . '/src/BoundedPng.php';

try {
    if (PHP_VERSION !== '8.4.26') { throw new RuntimeException('Inspector version mismatch.'); }
    $bytes = file_get_contents('php://stdin', false, null, 0, 1_048_577);
    if (!is_string($bytes)) { throw new RuntimeException('Input unavailable.'); }
    try { BoundedPng::fromBytes($bytes); }
    catch (RejectedPng) { fwrite(STDOUT, "rejected\n"); exit(0); }
    $pipes = [];
    $process = proc_open(['/usr/local/bin/pngcheck', '-q'],
        [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes,
        '/', ['PATH' => '/usr/local/bin:/usr/bin:/bin']);
    if (!is_resource($process)) { throw new RuntimeException('Inspector unavailable.'); }
    // The external container supervisor owns this entire process scope and its deadline.
    $offset = 0;
    while ($offset < strlen($bytes)) {
        $written = @fwrite($pipes[0], substr($bytes, $offset, 8192));
        if ($written === false || $written === 0) {
            fclose($pipes[0]);
            proc_terminate($process, 9);
            proc_close($process);
            throw new RuntimeException('Inspector input failed.');
        }
        $offset += $written;
    }
    fclose($pipes[0]);
    $code = proc_close($process);
    if ($code < 0 || $code > 3) { throw new RuntimeException('Inspector failed.'); }
    fwrite(STDOUT, $code === 0 ? "accepted\n" : "rejected\n");
} catch (Throwable) {
    exit(1);
}
