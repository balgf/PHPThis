<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogImageFormat;
use CatalogImageRecipe\DockerCatalogInspector;
use CatalogImageRecipe\InspectionDefinition;
use CatalogImageRecipe\InspectionOutcome;

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/parser.php';
require __DIR__ . '/mp4-parser.php';

$failure = 'setup_arguments'; $cleanup = 'not_started';
try {
    if ($argc !== 5) { throw new InvalidArgumentException('Invalid fixture arguments.'); }
    $passed = []; $bytes = catalogMp4Seeds()['max-dimensions-duration-frames'];
    foreach (['stdout' => $argv[1], 'stderr' => $argv[2], 'timeout' => $argv[3], 'exit' => $argv[4]] as $mode => $image) {
        $failure = 'setup_definition';
        $inspector = new DockerCatalogInspector(InspectionDefinition::fromImageId($image, dirname(__DIR__)));
        $token = bin2hex(random_bytes(16)); $started = hrtime(true); $cleanup = 'not_started';
        try {
            $failure = 'mp4_probe_' . $mode . '_inspect';
            requireCatalogProof($inspector->inspect($bytes, $token, CatalogImageFormat::Mp4) === InspectionOutcome::ScannerError);
            if ($mode === 'timeout') { requireCatalogProof(hrtime(true) - $started >= 7_000_000_000); }
            $failure = 'mp4_probe_' . $mode . '_cleanup';
        } finally {
            $cleanup = 'failed'; requireCatalogProof($inspector->discard($token)); $cleanup = 'passed';
        }
        $passed[] = $mode;
    }
    echo json_encode(['mp4_probe_faults' => $passed], JSON_THROW_ON_ERROR), "\n";
} catch (Throwable) {
    fwrite(STDERR, 'REAL_PARSER_FAIL ' . $failure . ' cleanup=' . $cleanup . "\n");
    exit(1);
}
