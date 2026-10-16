<?php

declare(strict_types=1);

use CatalogImageRecipe\BoundedPng;
use CatalogImageRecipe\BoundedJpeg;
use CatalogImageRecipe\CatalogImageFormat;
use CatalogImageRecipe\RejectedPng;
use CatalogImageRecipe\RejectedJpeg;

require __DIR__ . '/src/Failures.php';
require __DIR__ . '/src/PngHeader.php';
require __DIR__ . '/src/BoundedPng.php';
require __DIR__ . '/src/BoundedJpeg.php';
require __DIR__ . '/src/CatalogImageFormat.php';

try {
    if (PHP_VERSION !== '8.4.26') { throw new RuntimeException('Inspector version mismatch.'); }
    if ($argc !== 2) { throw new RuntimeException('Inspector format missing.'); }
    $format = CatalogImageFormat::tryFrom($argv[1]);
    if ($format === null) { throw new RuntimeException('Inspector format invalid.'); }
    $bytes = file_get_contents('php://stdin', false, null, 0, 1_048_577);
    if (!is_string($bytes)) { throw new RuntimeException('Input unavailable.'); }
    try {
        if ($format === CatalogImageFormat::Png) { BoundedPng::fromBytes($bytes); }
        else { BoundedJpeg::fromBytes($bytes); }
    } catch (RejectedPng | RejectedJpeg) { fwrite(STDOUT, "rejected\n"); exit(0); }
    $pipes = [];
    $command = $format === CatalogImageFormat::Png ? ['/usr/local/bin/pngcheck', '-q']
        : ['/usr/local/bin/djpeg', '-strict', '-maxscans', '1', '-maxmemory', '16M', '-rgb', '-pnm', '-outfile', '/dev/null'];
    $process = proc_open($command,
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
    // A strict decoder failure cannot distinguish bad entropy from resource failure.
    if ($format === CatalogImageFormat::Jpeg && $code !== 0) { throw new RuntimeException('Decoder failed.'); }
    if ($code < 0 || $code > 3) { throw new RuntimeException('Inspector failed.'); }
    fwrite(STDOUT, $code === 0 ? "accepted\n" : "rejected\n");
} catch (Throwable) {
    exit(1);
}
