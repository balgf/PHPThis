<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tools/process-support.php';

/**
 * @param list<string> $arguments
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runIsolatedPhpTest(string $path, array $arguments = []): array
{
    return runBoundedMaintainerProcess(
        [PHP_BINARY, $path, ...$arguments],
        dirname(__DIR__),
        null,
        30_000,
        1_048_576,
        1_048_576,
    );
}
