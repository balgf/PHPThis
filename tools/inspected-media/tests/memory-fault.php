<?php

declare(strict_types=1);

// Test-image supervisor only. Keeping PID 1 alive preserves the kernel counters
// until the allocating child has been reaped, independently of Docker events.
/**
 * @phpstan-impure
 * @return array{oom: int, oom_kill: int}|null
 */
function memoryFaultCounters(): ?array
{
    $events = @file_get_contents('/sys/fs/cgroup/memory.events', false, null, 0, 4096);
    if (!is_string($events) || strlen($events) === 4096
        || preg_match('/^oom ([0-9]{1,9})$/m', $events, $oom) !== 1
        || preg_match('/^oom_kill ([0-9]{1,9})$/m', $events, $kills) !== 1) {
        return null;
    }
    return ['oom' => (int) $oom[1], 'oom_kill' => (int) $kills[1]];
}

function runMemoryFault(string $mode): int
{
    // A fresh cgroup-v2 container is required; absent evidence never passes.
    if (memoryFaultCounters() !== ['oom' => 0, 'oom_kill' => 0]) { return 88; }
    $program = match ($mode) {
        "memory\n" => "echo strlen(str_repeat('x', 268_435_456));",
        "memory_small\n" => "echo strlen(str_repeat('x', 1024));",
        "memory_kill\n" => 'sleep(30);',
        default => throw new InvalidArgumentException('Unknown memory control.'),
    };
    $child = proc_open([PHP_BINARY, '-n', '-d', 'memory_limit=-1', '-r', $program], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ], $pipes, '/', []);
    if (!is_resource($child)) { return 88; }
    try {
        if ($mode === "memory_kill\n" && !proc_terminate($child, 9)) { return 88; }
        $deadline = hrtime(true) + 5_000_000_000;
        do {
            $status = proc_get_status($child);
            if (!$status['running']) {
                $events = memoryFaultCounters();
                if ($events === null) { return 88; }
                if ($status['signaled'] && $status['termsig'] === 9
                    && $events['oom'] > 0 && $events['oom_kill'] === 1) { return 86; }
                if ($events !== ['oom' => 0, 'oom_kill' => 0]) { return 88; }
                if ($status['signaled'] && $status['termsig'] === 9) { return 89; }
                if (!$status['signaled'] && $status['exitcode'] === 0) { return 87; }
                return 88;
            }
            usleep(10_000);
        } while (hrtime(true) < $deadline);
        return 88;
    } finally {
        $status = proc_get_status($child);
        if ($status['running']) { proc_terminate($child, 9); }
        $deadline = hrtime(true) + 1_000_000_000;
        while ($status['running'] && hrtime(true) < $deadline) {
            usleep(10_000);
            $status = proc_get_status($child);
        }
        if (!$status['running']) { proc_close($child); }
        // The outer fixture always removes this exact owned container as well.
    }
}
