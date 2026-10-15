<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class DockerPngInspector implements PngInspector
{
    public function __construct(private InspectionDefinition $definition) {}

    public function inspect(string $bytes, string $token): InspectionOutcome
    {
        $name = $this->name($token);
        if ($bytes === '' || strlen($bytes) > 1_048_576) { return InspectionOutcome::Rejected; }
        $result = $this->capture([
            'run', '--pull', 'never', '--name', $name, '--label', 'phpthis.catalog-inspector=' . $token,
            '--network', 'none', '--read-only', '--user', '65534:65534', '--cap-drop', 'ALL',
            '--security-opt', 'no-new-privileges', '--pids-limit', '8', '--memory', '64m', '--memory-swap', '64m',
            '--cpus', '1', '--ulimit', 'cpu=2:2', '--log-driver', 'none', '-i', $this->definition->image,
        ], $bytes, 10);
        if ($result === null || $result['code'] !== 0 || $result['err'] !== '') { return InspectionOutcome::ScannerError; }
        return match ($result['out']) {
            "accepted\n" => InspectionOutcome::Accepted,
            "rejected\n" => InspectionOutcome::Rejected,
            default => InspectionOutcome::ScannerError,
        };
    }

    public function discard(string $token): bool
    {
        $name = $this->name($token);
        $query = ['container', 'ls', '-a', '--filter', 'name=^/' . $name . '$',
            '--format', '{{.Names}} {{.Label "phpthis.catalog-inspector"}}'];
        $before = $this->capture($query, '', 3);
        if ($before === null || $before['code'] !== 0 || $before['err'] !== '') { return false; }
        if ($before['out'] === '') { return true; }
        if ($before['out'] !== $name . ' ' . $token . "\n") { return false; }
        $removed = $this->capture(['container', 'rm', '--force', $name], '', 3);
        if ($removed === null || $removed['code'] !== 0) { return false; }
        $after = $this->capture($query, '', 3);
        return $after !== null && $after['code'] === 0 && $after['err'] === '' && $after['out'] === '';
    }

    private function name(string $token): string
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $token) !== 1) { throw new CatalogUnavailable('Invalid inspection identity.'); }
        return 'phpthis-catalog-' . $token;
    }

    /** @param list<string> $arguments
     * @return array{code: int, out: string, err: string}|null
     */
    private function capture(array $arguments, string $input, int $seconds): ?array
    {
        $source = tmpfile();
        if ($source === false) { return null; }
        $process = null;
        $pipes = [];
        try {
            if (fwrite($source, $input) !== strlen($input) || !rewind($source)) { return null; }
            // Only the supervisor has local daemon authority. No inherited app environment.
            $process = @proc_open(
                ['docker', '--host', 'unix:///var/run/docker.sock', ...$arguments],
                [0 => $source, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
                '/', ['PATH' => '/usr/local/bin:/usr/bin:/bin'],
            );
            if (!is_resource($process)) { return null; }
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $out = '';
            $err = '';
            $deadline = hrtime(true) + $seconds * 1_000_000_000;
            do {
                $a = stream_get_contents($pipes[1], 8193 - strlen($out));
                $b = stream_get_contents($pipes[2], 8193 - strlen($err));
                if ($a === false || $b === false) { return null; }
                $out .= $a;
                $err .= $b;
                if (strlen($out) > 8192 || strlen($err) > 8192) { return null; }
                $status = proc_get_status($process);
                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    return ['code' => $status['exitcode'], 'out' => $out, 'err' => $err];
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline);
            return null;
        } finally {
            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process, 9);
                    $stop = hrtime(true) + 1_000_000_000;
                    do {
                        usleep(10_000);
                        $status = proc_get_status($process);
                    } while ($status['running'] && hrtime(true) < $stop);
                }
                foreach ($pipes as $pipe) { fclose($pipe); }
                if (!$status['running']) { proc_close($process); }
            }
            fclose($source);
        }
    }
}
