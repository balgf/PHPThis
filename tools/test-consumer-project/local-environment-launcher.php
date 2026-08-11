<?php

declare(strict_types=1);

/** @return array{launcher: string, environment: string} */
function installedLocalEnvironmentLauncherReferences(string $installedFramework): array
{
    $guidePath = $installedFramework . '/docs/configuration/local-environment-launcher.md';
    $guide = file_get_contents($guidePath);

    if (!is_string($guide)) {
        throw new RuntimeException('Unable to read the installed local environment launcher reference.');
    }

    $blocks = [];

    foreach (
        [
            'launcher' => '## Copyable `bin/application` reference',
            'environment' => '## Copyable `src/Configuration/ApplicationEnvironment.php` reference',
        ] as $name => $heading
    ) {
        $headingOffset = strpos($guide, $heading);

        if ($headingOffset === false) {
            throw new RuntimeException("The installed local environment {$name} reference is missing.");
        }

        $blockMarker = "\n```php\n";
        $blockOffset = strpos($guide, $blockMarker, $headingOffset + strlen($heading));

        if ($blockOffset === false) {
            throw new RuntimeException("The installed local environment {$name} PHP block is missing.");
        }

        $sourceOffset = $blockOffset + strlen($blockMarker);
        $sourceEnd = strpos($guide, "\n```", $sourceOffset);

        if ($sourceEnd === false) {
            throw new RuntimeException("The installed local environment {$name} reference is incomplete.");
        }

        $source = substr($guide, $sourceOffset, $sourceEnd - $sourceOffset);

        if ($source === '' || !str_starts_with($source, "<?php\n")) {
            throw new RuntimeException("The installed local environment {$name} source is invalid.");
        }

        $blocks[$name] = $source . "\n";
    }

    return ['launcher' => $blocks['launcher'], 'environment' => $blocks['environment']];
}

/** @return non-empty-list<non-empty-string> */
function localEnvironmentLauncherInputNames(): array
{
    return [
        'APP_WORKER_DATABASE_DSN',
        'APP_WORKER_DATABASE_USERNAME',
        'APP_WORKER_DATABASE_PASSWORD',
        'APP_MIGRATION_DATABASE_DSN',
        'APP_MIGRATION_DATABASE_USERNAME',
        'APP_MIGRATION_DATABASE_PASSWORD',
    ];
}

/** @param array<string, string> $values */
function localEnvironmentLauncherFile(
    array $values,
    string $lineEnding = "\n",
    bool $finalNewline = true,
): string {
    $lines = ['# application-owned local values', ''];

    foreach ($values as $name => $value) {
        $lines[] = $name . '=' . $value;
    }

    return implode($lineEnding, $lines) . ($finalNewline ? $lineEnding : '');
}

function padLocalEnvironmentLauncherFile(string $contents, int $targetBytes): string
{
    if (strlen($contents) > $targetBytes) {
        throw new RuntimeException('Local environment launcher padding target is too small.');
    }

    while (strlen($contents) < $targetBytes) {
        $remaining = $targetBytes - strlen($contents);

        if ($remaining === 1) {
            $contents .= "\n";
            continue;
        }

        $payloadBytes = min(4_225, $remaining - 1);
        $contents .= '#' . str_repeat('p', $payloadBytes - 1) . "\n";
    }

    return $contents;
}

function replaceLocalEnvironmentLauncherFile(string $path, string $contents): void
{
    if ((is_file($path) || is_link($path)) && !unlink($path)) {
        throw new RuntimeException('Unable to replace the local environment launcher file.');
    }

    writeFile($path, $contents);
}

/** @param array<string, string> $values */
function writeLocalEnvironmentLauncherExpectation(
    string $path,
    string $command,
    string $profile,
    array $values,
    int $exitCode = 0,
): void {
    $contents = json_encode(
        [
            'command' => $command,
            'profile' => $profile,
            'values' => $values,
            'exit_code' => $exitCode,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );

    writeFile($path, $contents . "\n");
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 */
function requireLocalEnvironmentLauncherFailure(
    array $command,
    string $workingDirectory,
    array $environment,
    string $message,
): void {
    requireExactProcessResult(
        runProcess($command, $workingDirectory, $environment),
        1,
        '',
        "{\"error\":\"local_environment_failed\"}\n",
        $message,
    );
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 */
function requireLocalEnvironmentLauncherSuccess(
    array $command,
    string $workingDirectory,
    array $environment,
    string $expectedOutput,
    string $message,
): void {
    requireExactProcessResult(
        runProcess($command, $workingDirectory, $environment),
        0,
        $expectedOutput,
        '',
        $message,
    );
}

function localEnvironmentLauncherProofBoundary(string $source): string
{
    $classEnd = strrpos($source, "\n}\n");

    if ($classEnd === false) {
        throw new RuntimeException('The local environment proof boundary cannot be extended.');
    }

    $proofMethods = <<<'PHP'

    public static function proofWorkerForChild(): WorkerLauncherTransport
    {
        $profile = self::requireComplete(self::workerSnapshot());

        return self::workerFrom($profile);
    }

    public static function proofMigrationsForChild(): MigrationLauncherTransport
    {
        $profile = self::requireComplete(self::migrationSnapshot());

        return self::migrationFrom($profile);
    }

    /** @param list<string> $expectedNames */
    public static function proofExactChildEnvironment(array $expectedNames): void
    {
        $snapshot = [
            'APP_WORKER_DATABASE_DSN' => \getenv('APP_WORKER_DATABASE_DSN'),
            'APP_WORKER_DATABASE_USERNAME' => \getenv('APP_WORKER_DATABASE_USERNAME'),
            'APP_WORKER_DATABASE_PASSWORD' => \getenv('APP_WORKER_DATABASE_PASSWORD'),
            'APP_MIGRATION_DATABASE_DSN' => \getenv('APP_MIGRATION_DATABASE_DSN'),
            'APP_MIGRATION_DATABASE_USERNAME' => \getenv('APP_MIGRATION_DATABASE_USERNAME'),
            'APP_MIGRATION_DATABASE_PASSWORD' => \getenv('APP_MIGRATION_DATABASE_PASSWORD'),
            'APP_ADMIN_DATABASE_PASSWORD' => \getenv('APP_ADMIN_DATABASE_PASSWORD'),
            'APP_UNRELATED_SENTINEL' => \getenv('APP_UNRELATED_SENTINEL'),
        ];
        $actualNames = [];

        foreach ($snapshot as $name => $value) {
            if ($value !== false) {
                $actualNames[] = $name;
            }
        }

        sort($actualNames, SORT_STRING);

        if ($actualNames !== $expectedNames) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }
    }

PHP;

    return substr($source, 0, $classEnd) . $proofMethods . substr($source, $classEnd);
}

/**
 * @param array<string, string> $environment
 * @return non-empty-string
 */
function proveInstalledLocalEnvironmentLauncherReference(
    string $project,
    string $installedFramework,
    array $environment,
): string {
    $references = installedLocalEnvironmentLauncherReferences($installedFramework);
    $expectedReferenceHashes = [
        'launcher' => '1696b1bb2694539588bad9a540bd4121a4a106bb3810803e742283e9f98e020a',
        'environment' => '18d2dce2559fe9d276a7430c438b409848a5949174e0c3c48cf70d388a4a3fb1',
    ];

    foreach ($expectedReferenceHashes as $name => $expectedReferenceHash) {
        if (!hash_equals($expectedReferenceHash, hash('sha256', $references[$name]))) {
            throw new RuntimeException("The installed local environment {$name} reference changed.");
        }
    }

    foreach (
        [
            "    shell_exec('true');\n\n    \$process = @proc_open(",
            "    \$childEnvironment += ['PATH' => '/usr/bin'];\n\n    \$process = @proc_open(",
        ] as $mutation
    ) {
        $mutatedLauncher = str_replace(
            '    $process = @proc_open(',
            $mutation,
            $references['launcher'],
        );

        if (
            $mutatedLauncher === $references['launcher']
            || hash_equals($expectedReferenceHashes['launcher'], hash('sha256', $mutatedLauncher))
        ) {
            throw new RuntimeException('The local launcher exact-source mutation control failed.');
        }
    }

    $proofEnvironmentSource = localEnvironmentLauncherProofBoundary($references['environment']);
    $launcherProject = dirname($project)
        . '/local-environment-launcher-proof-'
        . bin2hex(random_bytes(8));
    $launcherPath = $launcherProject . '/bin/application';
    $childPath = $launcherProject . '/bin/console.php';
    $environmentBoundaryPath = $launcherProject
        . '/src/Configuration/ApplicationEnvironment.php';
    $autoloadPath = $launcherProject . '/vendor/autoload.php';
    $expectationPath = $launcherProject . '/expected.json';
    $environmentPath = $launcherProject . '/.env';
    $outsideDirectory = dirname($launcherProject);
    $inputNames = localEnvironmentLauncherInputNames();
    $cleanEnvironment = environmentWithout($environment, $inputNames);
    $cleanEnvironment['APP_ADMIN_DATABASE_PASSWORD'] = 'poisoned-elevated-password';
    $cleanEnvironment['APP_UNRELATED_SENTINEL'] = 'poisoned-unrelated-value';
    $workerOutput = "PASS installed local environment launcher worker\n";
    $migrationOutput = "PASS installed local environment launcher migration\n";
    $workerValues = [
        'APP_WORKER_DATABASE_DSN' => 'pgsql:host=127.0.0.1;port=5432;dbname=worker_local',
        'APP_WORKER_DATABASE_USERNAME' => 'worker-local',
        'APP_WORKER_DATABASE_PASSWORD' => 'worker-synthetic-password',
    ];
    $migrationValues = [
        'APP_MIGRATION_DATABASE_DSN' => 'pgsql:host=127.0.0.1;port=5432;dbname=migration_local',
        'APP_MIGRATION_DATABASE_USERNAME' => 'migration-local',
        'APP_MIGRATION_DATABASE_PASSWORD' => 'migration-synthetic-password',
    ];
    $poisonedWorkerValues = [
        'APP_WORKER_DATABASE_DSN' => 'poisoned-worker-dsn',
        'APP_WORKER_DATABASE_USERNAME' => 'poisoned-worker-username',
        'APP_WORKER_DATABASE_PASSWORD' => 'poisoned-worker-password',
    ];
    $poisonedMigrationValues = [
        'APP_MIGRATION_DATABASE_DSN' => 'poisoned-migration-dsn',
        'APP_MIGRATION_DATABASE_USERNAME' => 'poisoned-migration-username',
        'APP_MIGRATION_DATABASE_PASSWORD' => 'poisoned-migration-password',
    ];

    try {
        writeFile($launcherPath, $references['launcher']);
        writeFile($environmentBoundaryPath, $proofEnvironmentSource);
        writeFile(
            $autoloadPath,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Configuration/ApplicationEnvironment.php';
PHP,
        );

        $childSource = <<<'PHP'
<?php

declare(strict_types=1);

use App\Configuration\ApplicationEnvironment;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $command = $argv[1] ?? '';

    if ($command === 'jobs:run-one') {
        $configuration = ApplicationEnvironment::proofWorkerForChild();
        $profile = 'worker';
        $values = [
            'APP_WORKER_DATABASE_DSN' => $configuration->dsn,
            'APP_WORKER_DATABASE_USERNAME' => $configuration->username,
            'APP_WORKER_DATABASE_PASSWORD' => $configuration->password,
        ];
        $success = "PASS installed local environment launcher worker\n";
        $expectedApplicationNames = [
            'APP_WORKER_DATABASE_DSN',
            'APP_WORKER_DATABASE_PASSWORD',
            'APP_WORKER_DATABASE_USERNAME',
        ];

    } elseif ($command === 'database:migrate') {
        $configuration = ApplicationEnvironment::proofMigrationsForChild();
        $profile = 'migration';
        $values = [
            'APP_MIGRATION_DATABASE_DSN' => $configuration->dsn,
            'APP_MIGRATION_DATABASE_USERNAME' => $configuration->username,
            'APP_MIGRATION_DATABASE_PASSWORD' => $configuration->password,
        ];
        $success = "PASS installed local environment launcher migration\n";
        $expectedApplicationNames = [
            'APP_MIGRATION_DATABASE_DSN',
            'APP_MIGRATION_DATABASE_PASSWORD',
            'APP_MIGRATION_DATABASE_USERNAME',
        ];

    } else {
        throw new RuntimeException('Unexpected private-child command.');
    }

    $canonicalRoot = realpath(dirname(__DIR__));

    if (!is_string($canonicalRoot) || getcwd() !== $canonicalRoot) {
        throw new RuntimeException('Private-child working directory changed.');
    }

    ApplicationEnvironment::proofExactChildEnvironment($expectedApplicationNames);

    $expectedContents = file_get_contents(dirname(__DIR__) . '/expected.json');

    if (!is_string($expectedContents)) {
        throw new RuntimeException('Missing private-child expectation.');
    }

    $expected = json_decode($expectedContents, true, 512, JSON_THROW_ON_ERROR);
    $exitCode = is_array($expected) ? ($expected['exit_code'] ?? null) : null;
    $actual = [
        'command' => $command,
        'profile' => $profile,
        'values' => $values,
        'exit_code' => $exitCode,
    ];

    if ($expected !== $actual || !is_int($exitCode)) {
        throw new RuntimeException('Private-child configuration changed.');
    }

    if ($exitCode === 7) {
        fwrite(STDOUT, "PASS installed local environment launcher propagated exit\n");
        fwrite(STDERR, "CHILD_EXIT_7\n");
        exit(7);
    }

    if ($exitCode !== 0) {
        throw new RuntimeException('Unexpected private-child exit proof.');
    }

    fwrite(STDOUT, $success);
} catch (Throwable) {
    fwrite(STDERR, "CHILD_INVALID\n");
    exit(4);
}
PHP;
        writeFile($childPath, $childSource . "\n");

        foreach ([$launcherPath, $environmentBoundaryPath, $childPath] as $syntaxPath) {
            requireSuccess(
                runProcess([PHP_BINARY, '-l', $syntaxPath], $outsideDirectory, $cleanEnvironment),
                "The installed local environment launcher syntax failed for {$syntaxPath}.",
            );
        }

        foreach (['getenv(', 'putenv(', '$_ENV', '$_SERVER'] as $forbiddenLauncherAccess) {
            if (str_contains($references['launcher'], $forbiddenLauncherAccess)) {
                throw new RuntimeException('The local launcher bypassed the sole environment reader.');
            }
        }

        $environmentSourceWithoutExpectedReads = $references['environment'];

        foreach (localEnvironmentLauncherInputNames() as $inputName) {
            $expectedRead = "\\getenv('{$inputName}')";

            if (substr_count($environmentSourceWithoutExpectedReads, $expectedRead) !== 1) {
                throw new RuntimeException('The local environment boundary literal-read inventory changed.');
            }

            $environmentSourceWithoutExpectedReads = str_replace(
                $expectedRead,
                '',
                $environmentSourceWithoutExpectedReads,
            );
        }

        foreach (['getenv', 'putenv', '$_ENV', '$_SERVER'] as $forbiddenEnvironmentAccess) {
            if (str_contains($environmentSourceWithoutExpectedReads, $forbiddenEnvironmentAccess)) {
                throw new RuntimeException('The local environment boundary gained alternate process access.');
            }
        }

        foreach (
            [
                <<<'PHP'
private const array WORKER_NAMES = [
        'APP_WORKER_DATABASE_DSN',
        'APP_WORKER_DATABASE_USERNAME',
        'APP_WORKER_DATABASE_PASSWORD',
    ];
PHP,
                <<<'PHP'
private const array MIGRATION_NAMES = [
        'APP_MIGRATION_DATABASE_DSN',
        'APP_MIGRATION_DATABASE_USERNAME',
        'APP_MIGRATION_DATABASE_PASSWORD',
    ];
PHP,
                '$allowed = [...self::WORKER_NAMES, ...self::MIGRATION_NAMES];',
                '!in_array($name, $allowed, true)',
            ] as $requiredEnvironmentMarker
        ) {
            if (substr_count($references['environment'], $requiredEnvironmentMarker) !== 1) {
                throw new RuntimeException('The local environment finite key map changed.');
            }
        }

        $expectedChildEnvironmentBlocks = [
            <<<'PHP'
$childEnvironment = [
            'APP_WORKER_DATABASE_DSN' => $configuration->dsn,
            'APP_WORKER_DATABASE_USERNAME' => $configuration->username,
            'APP_WORKER_DATABASE_PASSWORD' => $configuration->password,
        ];
PHP,
            <<<'PHP'
$childEnvironment = [
            'APP_MIGRATION_DATABASE_DSN' => $configuration->dsn,
            'APP_MIGRATION_DATABASE_USERNAME' => $configuration->username,
            'APP_MIGRATION_DATABASE_PASSWORD' => $configuration->password,
        ];
PHP,
        ];

        foreach (
            [
                <<<'PHP'
if ($command !== 'jobs:run-one' && $command !== 'database:migrate') {
    launcherUnknownCommand();
}
PHP,
                '$root = realpath(dirname(__DIR__));',
                "\$child = \$root . '/bin/console.php';",
                'launcherRequireReadableRegularFile($child);',
                '$phpBinary = realpath(PHP_BINARY);',
                "!is_file(\$phpBinary)",
                "!is_executable(\$phpBinary)",
                "\$autoload = \$root . '/vendor/autoload.php';",
                'launcherRequireReadableRegularFile($autoload);',
                'require $autoload;',
                '$pathStat = @lstat($path);',
                "\$stream = @fopen(\$path, 'rb');",
                '$streamStat = fstat($stream);',
                "(\$pathStat['dev'] ?? null) !== (\$streamStat['dev'] ?? null)",
                "(\$pathStat['ino'] ?? null) !== (\$streamStat['ino'] ?? null)",
                '($mode & 0170000) === 0100000',
                '[$phpBinary, $child, $command]',
                '[0 => STDIN, 1 => STDOUT, 2 => STDERR]',
                "['bypass_shell' => true]",
                ...$expectedChildEnvironmentBlocks,
            ] as $requiredLauncherMarker
        ) {
            if (substr_count($references['launcher'], $requiredLauncherMarker) !== 1) {
                throw new RuntimeException('The local launcher selected-environment handoff changed.');
            }
        }

        if (
            substr_count($references['launcher'], '$childEnvironment = [') !== 2
            || substr_count($references['launcher'], "\n        \$childEnvironment,\n") !== 1
            || str_contains($references['launcher'], '$childEnvironment[')
        ) {
            throw new RuntimeException('The local launcher child environment is not exact.');
        }

        requireExactProcessResult(
            runProcess([PHP_BINARY, $launcherPath], $outsideDirectory, $cleanEnvironment),
            2,
            '',
            "{\"error\":\"invalid_arguments\"}\n",
            'The installed local environment launcher accepted missing arguments.',
        );
        requireExactProcessResult(
            runProcess(
                [PHP_BINARY, '-d', 'register_argc_argv=0', $launcherPath],
                $outsideDirectory,
                $cleanEnvironment,
            ),
            2,
            '',
            "{\"error\":\"invalid_arguments\"}\n",
            'The installed local environment launcher depended on argv registration.',
        );
        requireExactProcessResult(
            runProcess(
                [PHP_BINARY, $launcherPath, 'jobs:run-one', 'unexpected'],
                $outsideDirectory,
                $cleanEnvironment,
            ),
            2,
            '',
            "{\"error\":\"invalid_arguments\"}\n",
            'The installed local environment launcher accepted extra arguments.',
        );
        requireExactProcessResult(
            runProcess(
                [PHP_BINARY, $launcherPath, 'unknown'],
                $outsideDirectory,
                $cleanEnvironment,
            ),
            2,
            '',
            "{\"error\":\"unknown_command\"}\n",
            'The installed local environment launcher accepted an unknown command.',
        );

        $symlinkTarget = $launcherProject . '/not-the-environment';
        writeFile($symlinkTarget, "APP_UNKNOWN=value\n");

        if (!symlink($symlinkTarget, $environmentPath)) {
            throw new RuntimeException('Unable to create the local environment launcher bypass control.');
        }

        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $workerValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            [...$cleanEnvironment, ...$workerValues, ...$poisonedMigrationValues],
            $workerOutput,
            'A complete inherited worker profile did not bypass the local file.',
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, './bin/application', 'jobs:run-one'],
            $launcherProject,
            [
                ...$cleanEnvironment,
                ...$workerValues,
                'APP_MIGRATION_DATABASE_DSN' => 'partial-opposite-profile',
            ],
            $workerOutput,
            'The relative in-project launcher or selected-only child environment changed.',
        );

        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'database:migrate',
            'migration',
            $migrationValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'database:migrate'],
            $outsideDirectory,
            [...$cleanEnvironment, ...$migrationValues, ...$poisonedWorkerValues],
            $migrationOutput,
            'A complete inherited migration profile did not bypass the local file.',
        );

        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            [
                ...$cleanEnvironment,
                ...$workerValues,
                'APP_WORKER_DATABASE_PASSWORD' => str_repeat('x', 4_097),
            ],
            'The inherited worker transport maximum-plus-one was accepted.',
        );
        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'database:migrate'],
            $outsideDirectory,
            [
                ...$cleanEnvironment,
                ...$migrationValues,
                'APP_MIGRATION_DATABASE_PASSWORD' => "control\tvalue",
            ],
            'An inherited migration control byte was accepted.',
        );

        if (!unlink($environmentPath)) {
            throw new RuntimeException('Unable to remove the local environment launcher bypass control.');
        }

        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            'The local environment launcher accepted a missing local file.',
        );

        if (!symlink($symlinkTarget, $environmentPath)) {
            throw new RuntimeException('Unable to create the local environment launcher symlink control.');
        }

        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            'The local environment launcher accepted a symlinked local file.',
        );

        if (!unlink($environmentPath)) {
            throw new RuntimeException('Unable to remove the local environment launcher symlink control.');
        }

        writeFile($environmentPath, localEnvironmentLauncherFile($workerValues));

        if (!chmod($environmentPath, 0000)) {
            throw new RuntimeException('Unable to make the local environment file unreadable.');
        }

        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            'The local environment launcher accepted an unreadable local file.',
        );

        if (!chmod($environmentPath, 0600) || !unlink($environmentPath)) {
            throw new RuntimeException('Unable to remove the unreadable local environment control.');
        }

        $childSymlinkTarget = $launcherProject . '/child-symlink-target.php';
        writeFile($childSymlinkTarget, $childSource . "\n");

        if (!unlink($childPath) || !symlink($childSymlinkTarget, $childPath)) {
            throw new RuntimeException('Unable to create the local environment launcher child control.');
        }

        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            [...$cleanEnvironment, ...$workerValues],
            'The local environment launcher accepted a symlinked private child.',
        );

        if (!unlink($childPath)) {
            throw new RuntimeException('Unable to remove the local environment launcher child control.');
        }

        writeFile($childPath, $childSource . "\n");

        if (!chmod($childPath, 0000)) {
            throw new RuntimeException('Unable to make the private child unreadable for preparation proof.');
        }

        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            [...$cleanEnvironment, ...$workerValues],
            'The local environment launcher accepted an unreadable private child.',
        );

        if (!chmod($childPath, 0600)) {
            throw new RuntimeException('Unable to restore the private child permissions.');
        }

        $bothProfiles = [...$workerValues, ...$migrationValues];
        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile($bothProfiles),
        );
        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            [
                ...$cleanEnvironment,
                ...$workerValues,
                'APP_WORKER_DATABASE_PASSWORD' => str_repeat('x', 4_097),
            ],
            'An invalid inherited worker profile fell back to a valid local profile.',
        );
        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $workerValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'The local worker profile fallback changed.',
        );

        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile($bothProfiles, "\r\n", false),
        );
        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'database:migrate',
            'migration',
            $migrationValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'database:migrate'],
            $outsideDirectory,
            $cleanEnvironment,
            $migrationOutput,
            'CRLF or optional-final-LF local migration selection changed.',
        );

        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile($bothProfiles),
        );

        foreach (
            [
                'worker' => ['jobs:run-one', $workerValues],
                'migration' => ['database:migrate', $migrationValues],
            ] as $profile => [$command, $profileValues]
        ) {
            for ($mask = 1; $mask < 7; $mask++) {
                $partialEnvironment = $cleanEnvironment;
                $index = 0;

                foreach ($profileValues as $name => $value) {
                    if (($mask & (1 << $index)) !== 0) {
                        $partialEnvironment[$name] = $value;
                    }

                    $index++;
                }

                requireLocalEnvironmentLauncherFailure(
                    [PHP_BINARY, $launcherPath, $command],
                    $outsideDirectory,
                    $partialEnvironment,
                    "The partial inherited {$profile} profile was merged with the local file.",
                );
            }

            for ($mask = 1; $mask < 7; $mask++) {
                $partialLocalProfile = [];
                $index = 0;

                foreach ($profileValues as $name => $value) {
                    if (($mask & (1 << $index)) !== 0) {
                        $partialLocalProfile[$name] = $value;
                    }

                    $index++;
                }

                replaceLocalEnvironmentLauncherFile(
                    $environmentPath,
                    localEnvironmentLauncherFile($partialLocalProfile),
                );
                requireLocalEnvironmentLauncherFailure(
                    [PHP_BINARY, $launcherPath, $command],
                    $outsideDirectory,
                    $cleanEnvironment,
                    "The partial local {$profile} profile was accepted.",
                );
            }
        }

        $emptyInheritedWorker = environmentWithEmptyValue(
            [
                ...$cleanEnvironment,
                'APP_WORKER_DATABASE_DSN' => $workerValues['APP_WORKER_DATABASE_DSN'],
                'APP_WORKER_DATABASE_USERNAME' => $workerValues['APP_WORKER_DATABASE_USERNAME'],
            ],
            'APP_WORKER_DATABASE_PASSWORD',
        );
        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $emptyInheritedWorker,
            'The complete inherited profile accepted an empty transport value.',
        );

        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile([
                ...$workerValues,
                'APP_MIGRATION_DATABASE_DSN' => $migrationValues['APP_MIGRATION_DATABASE_DSN'],
            ]),
        );
        requireLocalEnvironmentLauncherFailure(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            'A partial non-selected local profile was accepted.',
        );

        $validWorkerFile = localEnvironmentLauncherFile($workerValues);
        $invalidLocalFiles = [
            'duplicate key' => $validWorkerFile
                . 'APP_WORKER_DATABASE_DSN=duplicate' . "\n",
            'unknown key' => $validWorkerFile . 'APP_UNKNOWN=value' . "\n",
            'export syntax' => $validWorkerFile
                . 'export APP_WORKER_DATABASE_DSN=value' . "\n",
            'leading-space comment' => $validWorkerFile . ' # not a comment' . "\n",
            'malformed line' => $validWorkerFile . 'not-an-assignment' . "\n",
            'empty value' => localEnvironmentLauncherFile([
                ...$workerValues,
                'APP_WORKER_DATABASE_PASSWORD' => '',
            ]),
            'invalid complete non-selected profile' => localEnvironmentLauncherFile([
                ...$workerValues,
                ...$migrationValues,
                'APP_MIGRATION_DATABASE_PASSWORD' => "control\tvalue",
            ]),
            'lowercase key' => $validWorkerFile . 'app_unknown=value' . "\n",
            'control byte' => $validWorkerFile . "APP_UNKNOWN=tab\tvalue\n",
            'DEL byte' => $validWorkerFile . "APP_UNKNOWN=value\x7f\n",
            'high byte' => $validWorkerFile . "APP_UNKNOWN=value\x80\n",
            'NUL byte' => $validWorkerFile . "APP_UNKNOWN=value\0tail\n",
            'bare carriage return' => $validWorkerFile . "APP_UNKNOWN=value\rtail\n",
            'final unterminated carriage return' => rtrim($validWorkerFile, "\n") . "\r",
            'empty file' => '',
        ];

        foreach ($invalidLocalFiles as $label => $invalidLocalFile) {
            replaceLocalEnvironmentLauncherFile($environmentPath, $invalidLocalFile);
            requireLocalEnvironmentLauncherFailure(
                [PHP_BINARY, $launcherPath, 'jobs:run-one'],
                $outsideDirectory,
                $cleanEnvironment,
                "The local environment launcher accepted {$label}.",
            );
        }

        $executionMarker = $launcherProject . '/environment-value-executed';
        $opaqueWorkerValues = [
            ...$workerValues,
            'APP_WORKER_DATABASE_DSN' => 'pgsql:host=local;port=5432;options=a=b=c',
            'APP_WORKER_DATABASE_USERNAME' => $workerValues['APP_WORKER_DATABASE_USERNAME'],
            'APP_WORKER_DATABASE_PASSWORD' => '# inline-comment-is-data; $('
                . 'touch ' . $executionMarker . ')'
                . ';`touch ' . $executionMarker . '`'
                . '; >' . $executionMarker
                . "; | & = \"quoted\" \\ 'single'",
        ];
        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile($opaqueWorkerValues, "\n", false),
        );
        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $opaqueWorkerValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'Opaque local environment metacharacters changed.',
        );

        if (file_exists($executionMarker)) {
            throw new RuntimeException('An opaque local environment value was executed.');
        }

        foreach (
            [
                '$(' . 'touch ' . $executionMarker . ')',
                '`touch ' . $executionMarker . '`',
                '>' . $executionMarker,
                'exit 0',
                'PATH=' . $launcherProject,
            ] as $executableSyntax
        ) {
            replaceLocalEnvironmentLauncherFile(
                $environmentPath,
                $validWorkerFile . $executableSyntax . "\n",
            );
            requireLocalEnvironmentLauncherFailure(
                [PHP_BINARY, $launcherPath, 'jobs:run-one'],
                $outsideDirectory,
                $cleanEnvironment,
                'Executable local environment syntax was accepted.',
            );

            if (file_exists($executionMarker)) {
                throw new RuntimeException('Executable local environment syntax ran.');
            }
        }

        $maximumWorkerValues = [
            ...$workerValues,
            'APP_WORKER_DATABASE_PASSWORD' => str_repeat('x', 4_096),
        ];
        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile($maximumWorkerValues),
        );
        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $maximumWorkerValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'The 4,096-byte local transport boundary was rejected.',
        );

        $allVisibleAscii = '';

        for ($byte = 0x20; $byte <= 0x7e; $byte++) {
            $allVisibleAscii .= chr($byte);
        }

        $visibleAsciiWorkerValues = [
            ...$workerValues,
            'APP_WORKER_DATABASE_PASSWORD' => $allVisibleAscii,
        ];
        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            localEnvironmentLauncherFile($visibleAsciiWorkerValues),
        );
        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $visibleAsciiWorkerValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'The complete visible-ASCII value grammar was rejected.',
        );

        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            $validWorkerFile . '#' . str_repeat('l', 4_224) . "\n",
        );
        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $workerValues,
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'The 4,225-byte physical-line boundary was rejected.',
        );

        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            $validWorkerFile . str_repeat("#\n", 251),
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'The 256-physical-line boundary was rejected.',
        );

        replaceLocalEnvironmentLauncherFile(
            $environmentPath,
            padLocalEnvironmentLauncherFile($validWorkerFile, 65_536),
        );
        requireLocalEnvironmentLauncherSuccess(
            [PHP_BINARY, $launcherPath, 'jobs:run-one'],
            $outsideDirectory,
            $cleanEnvironment,
            $workerOutput,
            'The 65,536-byte local-file boundary was rejected.',
        );

        foreach (
            [
                '4,097-byte value' => localEnvironmentLauncherFile([
                    ...$workerValues,
                    'APP_WORKER_DATABASE_PASSWORD' => str_repeat('x', 4_097),
                ]),
                '4,226-byte physical line' => $validWorkerFile
                    . '#'
                    . str_repeat('l', 4_225)
                    . "\n",
                '257 physical lines' => $validWorkerFile . str_repeat("#\n", 252),
                '65,537-byte file' => padLocalEnvironmentLauncherFile(
                    $validWorkerFile,
                    65_537,
                ),
                '129-byte key' => $validWorkerFile
                    . 'A'
                    . str_repeat('B', 128)
                    . "=value\n",
            ] as $label => $oversizedFile
        ) {
            replaceLocalEnvironmentLauncherFile($environmentPath, $oversizedFile);
            requireLocalEnvironmentLauncherFailure(
                [PHP_BINARY, $launcherPath, 'jobs:run-one'],
                $outsideDirectory,
                $cleanEnvironment,
                "The local environment launcher accepted {$label}.",
            );
        }

        $reloadOne = [
            ...$workerValues,
            'APP_WORKER_DATABASE_DSN' => 'pgsql:dbname=reload_one',
        ];
        $reloadTwo = [
            ...$workerValues,
            'APP_WORKER_DATABASE_DSN' => 'pgsql:dbname=reload_two',
        ];

        foreach ([$reloadOne, $reloadTwo] as $reloadValues) {
            replaceLocalEnvironmentLauncherFile(
                $environmentPath,
                localEnvironmentLauncherFile($reloadValues),
            );
            writeLocalEnvironmentLauncherExpectation(
                $expectationPath,
                'jobs:run-one',
                'worker',
                $reloadValues,
            );
            requireLocalEnvironmentLauncherSuccess(
                [PHP_BINARY, $launcherPath, 'jobs:run-one'],
                $outsideDirectory,
                $cleanEnvironment,
                $workerOutput,
                'Fresh-process local environment reload changed.',
            );
        }

        writeLocalEnvironmentLauncherExpectation(
            $expectationPath,
            'jobs:run-one',
            'worker',
            $reloadTwo,
            7,
        );
        requireExactProcessResult(
            runProcess(
                [PHP_BINARY, $launcherPath, 'jobs:run-one'],
                $outsideDirectory,
                $cleanEnvironment,
            ),
            7,
            "PASS installed local environment launcher propagated exit\n",
            "CHILD_EXIT_7\n",
            'The local environment launcher did not propagate the private-child exit.',
        );
    } finally {
        removeDirectory($launcherProject);
    }

    if (file_exists($launcherProject) || is_link($launcherProject)) {
        throw new RuntimeException('The local environment launcher proof did not clean its workspace.');
    }

    return 'installed-local-environment-launcher-reference-proved';
}
