<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

final class TestToneProducer
{
    public static function generate(): TestToneBytes
    {
        $started = hrtime(true);
        $directory = dirname(__DIR__) . '/worker';
        $worker = $directory . '/test-tone.php';
        $stat = @lstat($worker);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1
            || realpath($worker) !== $worker || $stat['size'] < 1 || $stat['size'] > 8192) {
            throw new ExportUnavailable('Test tone unavailable.');
        }
        $pipes = [];
        $process = @proc_open([
            PHP_BINARY, '-n', '-d', 'memory_limit=16M', '-d', 'max_execution_time=2',
            '-d', 'display_errors=0', '-d', 'display_startup_errors=0', '-d', 'log_errors=0',
            '-d', 'zend.exception_ignore_args=1', '-d', 'allow_url_fopen=0',
            '-d', 'disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_fork,pcntl_exec',
            $worker,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, []);
        if (!is_resource($process)) { throw new ExportUnavailable('Test tone unavailable.'); }
        $out = ''; $err = '';
        try {
            fclose($pipes[0]); unset($pipes[0]);
            if (!stream_set_blocking($pipes[1], false) || !stream_set_blocking($pipes[2], false)) {
                throw new ExportUnavailable('Test tone unavailable.');
            }
            do {
                $outputRemaining = 32_045 - strlen($out); $errorRemaining = 1025 - strlen($err);
                if ($outputRemaining < 1 || $errorRemaining < 1) { throw new ExportUnavailable('Test tone unavailable.'); }
                $a = fread($pipes[1], min(8192, $outputRemaining));
                $b = fread($pipes[2], $errorRemaining);
                if ($a === false || $b === false) { throw new ExportUnavailable('Test tone unavailable.'); }
                $out .= $a; $err .= $b;
                if (strlen($out) > 32_044 || strlen($err) > 1024) { throw new ExportUnavailable('Test tone unavailable.'); }
                $status = proc_get_status($process);
                if (hrtime(true) - $started >= 3_000_000_000) { throw new ExportUnavailable('Test tone unavailable.'); }
                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    if ($status['exitcode'] !== 0 || $err !== '') { throw new ExportUnavailable('Test tone unavailable.'); }
                    break;
                }
                usleep(5000);
            } while (true);
        } finally {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 15); $stop = hrtime(true) + 250_000_000;
                do { usleep(5000); $status = proc_get_status($process); }
                while ($status['running'] && hrtime(true) < $stop);
                if ($status['running']) {
                    proc_terminate($process, 9); $stop = hrtime(true) + 1_000_000_000;
                    do { usleep(5000); $status = proc_get_status($process); }
                    while ($status['running'] && hrtime(true) < $stop);
                }
            }
            foreach ($pipes as $pipe) { fclose($pipe); }
            // Never return bytes or block in proc_close while the child is known to be running.
            if ($status['running']) { throw new ExportUnavailable('Test tone cleanup unresolved.'); }
            proc_close($process);
        }
        return TestToneBytes::fromProducerOutput($out);
    }
}
