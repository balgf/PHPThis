<?php

declare(strict_types=1);

use CatalogImageRecipe\DockerPngInspector;
use CatalogImageRecipe\InspectionDefinition;
use CatalogImageRecipe\InspectionOutcome;

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/parser.php';
require dirname(__DIR__, 2) . '/process-support.php';

$failure = 'setup_arguments';
$cleanup = 'not_started';
try {
    if ($argc !== 3) { throw new InvalidArgumentException('Invalid real-parser fixture arguments.'); }
    $failure = 'setup_definition';
    $definition = InspectionDefinition::fromImageId($argv[1], dirname(__DIR__));
    $inspector = new DockerPngInspector($definition);
    $failure = 'setup_fixtures';
    $valid = catalogPng();
    $largePixels = '';
    for ($row = 0; $row < 512; $row++) {
        $largePixels .= "\0";
        for ($block = 0; $block < 48; $block++) {
            $largePixels .= hash('sha256', 'row/' . $row . '/block/' . $block, true);
        }
    }
    $largePng = catalogPng(512, 512, raw: $largePixels);
    requireCatalogProof(strlen($largePng) > 700_000 && strlen($largePng) < 1_048_576);
    $cases = [
        ['valid_rgb', $valid, InspectionOutcome::Accepted],
        ['valid_rgba', catalogPng(colour: 6), InspectionOutcome::Accepted],
        ['large_pipe_input', $largePng, InspectionOutcome::Accepted],
        ['maximum_dimensions', catalogPng(1024, 1024, 6), InspectionOutcome::Accepted],
        ['svg', '<svg/>', InspectionOutcome::Rejected],
        ['missing_iend', substr($valid, 0, -12), InspectionOutcome::Rejected],
        ['truncated_idat', substr($valid, 0, 42), InspectionOutcome::Rejected],
        ['bad_crc', substr($valid, 0, 29) . "\0\0\0\0" . substr($valid, 33), InspectionOutcome::Rejected],
        ['short_row', catalogPng(raw: "\0ab"), InspectionOutcome::Rejected],
        ['missing_row', catalogPng(1, 2, raw: "\0abc"), InspectionOutcome::Rejected],
        ['extra_decoded', catalogPng(raw: str_repeat("\0abc", 5)), InspectionOutcome::Rejected],
        ['expansion_bomb', catalogPng(raw: str_repeat("\0", 8_388_608)), InspectionOutcome::Rejected],
        ['bad_filter', catalogPng(raw: "\x05abc"), InspectionOutcome::Rejected],
        ['oversize_dimension', catalogPng(1025), InspectionOutcome::Rejected],
        ['forbidden_metadata', substr($valid, 0, 33) . pngChunk('tEXt', "comment\0hidden") . substr($valid, 33), InspectionOutcome::Rejected],
        ['animation', substr($valid, 0, 33) . pngChunk('acTL', pack('NN', 1, 0)) . substr($valid, 33), InspectionOutcome::Rejected],
        ['trailing_bytes', $valid . '<?php ?>', InspectionOutcome::Rejected],
        ['file_byte_limit', $valid . str_repeat('x', 1_048_576), InspectionOutcome::Rejected],
    ];
    $passed = [];
    foreach ($cases as [$name, $bytes, $expected]) {
        $failure = 'format_' . $name . '_inspect';
        $cleanup = 'not_started';
        $token = bin2hex(random_bytes(16));
        try {
            requireCatalogProof($inspector->inspect($bytes, $token) === $expected);
            $failure = 'format_' . $name . '_cleanup';
        } finally {
            // Keep the failed assertion's label if cleanup also fails.
            $cleanup = 'failed';
            requireCatalogProof($inspector->discard($token));
            $cleanup = 'passed';
        }
        $passed[] = $name;
    }
    $failure = 'setup_fault_definition';
    $cleanup = 'not_started';
    $faults = new DockerPngInspector(InspectionDefinition::fromImageId($argv[2], dirname(__DIR__)));
    $resources = [];
    foreach (['exit', 'output', 'cpu', 'memory', 'timeout'] as $mode) {
        $failure = 'resource_' . $mode . '_inspect';
        $cleanup = 'not_started';
        $token = bin2hex(random_bytes(16));
        $started = hrtime(true);
        try {
            $input = $mode . "\n" . ($mode === 'timeout' ? str_repeat('x', 1_048_000) : '');
            requireCatalogProof($faults->inspect($input, $token) === InspectionOutcome::ScannerError);
            $failure = 'resource_' . $mode . '_container_state';
            $observed = runBoundedMaintainerProcess(['docker', '--host', 'unix:///var/run/docker.sock', 'inspect', '--format',
                '{{.State.OOMKilled}} {{.HostConfig.Memory}} {{.HostConfig.MemorySwap}} {{.HostConfig.ReadonlyRootfs}} {{.HostConfig.NetworkMode}} {{.Config.User}} {{.HostConfig.PidsLimit}}', 'phpthis-catalog-' . $token],
                '/', ['PATH' => '/usr/local/bin:/usr/bin:/bin'], 3_000, 8192, 8192);
            $expectedOom = $mode === 'memory' ? 'true' : 'false';
            requireCatalogProof($observed['exit_code'] === 0 && $observed['stderr'] === ''
                && $observed['stdout'] === $expectedOom . " 67108864 67108864 true none 65534:65534 8\n");
            if ($mode === 'timeout') {
                $failure = 'resource_timeout_elapsed';
                requireCatalogProof(hrtime(true) - $started >= 9_000_000_000);
            }
            $failure = 'resource_' . $mode . '_cleanup';
        } finally {
            $cleanup = 'failed';
            requireCatalogProof($faults->discard($token));
            $cleanup = 'passed';
        }
        $resources[] = $mode;
    }
    $failure = 'setup_report';
    $cleanup = 'not_started';
    echo json_encode(['format_cases' => $passed, 'resource_cases' => $resources], JSON_THROW_ON_ERROR), "\n";
} catch (Throwable) {
    fwrite(STDERR, 'REAL_PARSER_FAIL ' . $failure . ' cleanup=' . $cleanup . "\n");
    exit(1);
}
