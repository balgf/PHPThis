<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/process-support.php';

use CatalogImageRecipe\DockerPngInspector;
use CatalogImageRecipe\InspectionDefinition;
use CatalogImageRecipe\InspectionOutcome;

$inspector = new DockerPngInspector(InspectionDefinition::fromImageId($argv[1], dirname(__DIR__)));
$failures = 0;
for ($iteration = 1; $iteration <= 20; $iteration++) {
    $token = bin2hex(random_bytes(16));
    try {
        $started = hrtime(true);
        $outcome = $inspector->inspect("memory\n", $token);
        $observations = [];
        for ($sample = 0; $sample < 4; $sample++) {
            $result = runBoundedMaintainerProcess(['docker', '--host', 'unix:///var/run/docker.sock', 'inspect', '--format',
                '{{.State.OOMKilled}} {{.State.Running}} {{.State.ExitCode}} {{.HostConfig.Memory}} {{.HostConfig.MemorySwap}} {{.HostConfig.ReadonlyRootfs}} {{.HostConfig.NetworkMode}} {{.Config.User}} {{.HostConfig.PidsLimit}}',
                'phpthis-catalog-' . $token], '/', ['PATH' => '/usr/local/bin:/usr/bin:/bin'], 3000, 8192, 8192);
            $expected = "true false 137 67108864 67108864 true none 65534:65534 8\n";
            $observations[] = ['sample' => $sample, 'elapsed_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'exit' => $result['exit_code'], 'stderr_bytes' => strlen($result['stderr']),
                'state' => preg_match('/\A(?:true|false) (?:true|false) -?[0-9]+ [0-9]+ [0-9]+ (?:true|false) none 65534:65534 [0-9]+\n\z/D', $result['stdout']) === 1
                    ? trim($result['stdout']) : 'unrecognized'];
            if ($sample === 0 && $outcome === InspectionOutcome::ScannerError && $result['exit_code'] === 0 && $result['stderr'] === '' && $result['stdout'] === $expected) { break; }
            if ($sample === 0) { $failures++; }
            usleep(100_000);
        }
        echo json_encode(['iteration' => $iteration, 'scanner_error' => $outcome === InspectionOutcome::ScannerError, 'observations' => $observations], JSON_THROW_ON_ERROR), "\n";
    } finally {
        if (!$inspector->discard($token)) { throw new RuntimeException('Owned fixture cleanup failed.'); }
    }
}
echo json_encode(['iterations' => 20, 'initial_failures' => $failures], JSON_THROW_ON_ERROR), "\n";
