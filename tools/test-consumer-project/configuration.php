<?php

declare(strict_types=1);

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledTypedConfiguration(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $boundaryPath = $project . '/installed-configuration-boundary.php';
    $runtimePath = $project . '/installed-runtime-entrypoint.php';
    $migrationPath = $project . '/installed-migration-entrypoint.php';
    $contextPath = $project . '/.ai/configuration.md';
    $dataContextPath = $project . '/.ai/data.md';
    $originalContext = file_get_contents($contextPath);
    $originalDataContext = file_get_contents($dataContextPath);

    if (!is_string($originalContext) || !is_string($originalDataContext)) {
        throw new RuntimeException('Unable to read the installed configuration and data context proof.');
    }

    writeFile(
        $boundaryPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final readonly class InstalledRuntimeDatabaseConfiguration
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final readonly class InstalledMigrationDatabaseConfiguration
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final class InstalledApplicationEnvironment
{
    public static function forHttp(): InstalledRuntimeDatabaseConfiguration
    {
        return new InstalledRuntimeDatabaseConfiguration(
            self::dsn(\getenv('PHPTHIS_PROOF_RUNTIME_DATABASE_DSN')),
            self::username(\getenv('PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME')),
            self::password(\getenv('PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD')),
        );
    }

    public static function forMigrations(): InstalledMigrationDatabaseConfiguration
    {
        return new InstalledMigrationDatabaseConfiguration(
            self::dsn(\getenv('PHPTHIS_PROOF_MIGRATION_DATABASE_DSN')),
            self::username(\getenv('PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME')),
            self::password(\getenv('PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD')),
        );
    }

    private static function dsn(string|false $value): string
    {
        if (
            $value === false
            || $value === ''
            || strlen($value) > 128
            || !str_starts_with($value, 'sqlite:')
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function username(string|false $value): string
    {
        if (
            $value === false
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function password(#[\SensitiveParameter] string|false $value): string
    {
        if ($value === false || $value === '' || strlen($value) > 64) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }
}

final class InstalledConnectionRecordingSeam
{
    private static int $calls = 0;

    public static function recordAndDelegateToInstalledConnection(
        string $dsn,
        QueryBudget $queryBudget,
        QueryTrace $queryTrace,
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $expectedDsn,
        string $expectedUsername,
        #[\SensitiveParameter]
        string $expectedPassword,
    ): Connection {
        self::$calls++;

        if (
            $dsn !== $expectedDsn
            || $username !== $expectedUsername
            || $password !== $expectedPassword
        ) {
            throw new RuntimeException('Installed configuration delivery changed.');
        }

        return Connection::connect(
            $dsn,
            $queryBudget,
            $queryTrace,
            $username,
            $password,
        );
    }

    public static function calls(): int
    {
        return self::$calls;
    }
}

final class InstalledConfigurationContractProof
{
    public static function assertSensitiveParametersAndReadonlyTypes(): void
    {
        if (
            !(new ReflectionClass(InstalledRuntimeDatabaseConfiguration::class))->isReadOnly()
            || !(new ReflectionClass(InstalledMigrationDatabaseConfiguration::class))->isReadOnly()
        ) {
            throw new RuntimeException('Installed application configuration must be readonly.');
        }

        $expected = [
            InstalledRuntimeDatabaseConfiguration::class . '::__construct' => ['password'],
            InstalledMigrationDatabaseConfiguration::class . '::__construct' => ['password'],
            InstalledApplicationEnvironment::class . '::password' => ['value'],
            InstalledConnectionRecordingSeam::class . '::recordAndDelegateToInstalledConnection' => [
                'password',
                'expectedPassword',
            ],
            Connection::class . '::connect' => ['password'],
        ];

        foreach ($expected as $method => $expectedNames) {
            [$class, $methodName] = explode('::', $method, 2);
            $actualNames = [];

            foreach ((new ReflectionMethod($class, $methodName))->getParameters() as $parameter) {
                if ($parameter->getAttributes(SensitiveParameter::class) !== []) {
                    $actualNames[] = $parameter->getName();
                }
            }

            if ($actualNames !== $expectedNames) {
                throw new RuntimeException('Installed sensitive-parameter contract changed.');
            }
        }
    }
}
PHP,
    );
    writeFile(
        $runtimePath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/installed-configuration-boundary.php';

$recordDelivery = ($argv[1] ?? '') === 'record';

try {
    $configuration = InstalledApplicationEnvironment::forHttp();
    InstalledConfigurationContractProof::assertSensitiveParametersAndReadonlyTypes();

    if ($recordDelivery) {
        if (!isset($argv[2], $argv[3], $argv[4])) {
            throw new RuntimeException('Installed configuration recording evidence is incomplete.');
        }

        $connection = InstalledConnectionRecordingSeam::recordAndDelegateToInstalledConnection(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
            $argv[2],
            $argv[3],
            $argv[4],
        );

        if (InstalledConnectionRecordingSeam::calls() !== 1) {
            throw new RuntimeException('Installed runtime configuration recording count changed.');
        }
    } else {
        $connection = Connection::connect(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
        );
    }

    if ($connection->selectOneRow('SELECT 1 AS configured') !== ['configured' => 1]) {
        throw new RuntimeException('Installed runtime configuration did not reach the visible connection boundary.');
    }

    fwrite(
        STDOUT,
        $recordDelivery
            ? "PASS installed runtime typed configuration delivery\n"
            : "PASS installed runtime typed configuration\n",
    );
} catch (InvalidArgumentException) {
    if (InstalledConnectionRecordingSeam::calls() !== 0) {
        fwrite(STDERR, "INFRASTRUCTURE_BOUNDARY_REACHED\n");
        exit(3);
    }

    fwrite(STDERR, "CONFIGURATION_INVALID\n");
    exit(2);
}
PHP,
    );
    writeFile(
        $migrationPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/installed-configuration-boundary.php';

$recordDelivery = ($argv[1] ?? '') === 'record';

try {
    $configuration = InstalledApplicationEnvironment::forMigrations();
    InstalledConfigurationContractProof::assertSensitiveParametersAndReadonlyTypes();

    if ($recordDelivery) {
        if (!isset($argv[2], $argv[3], $argv[4])) {
            throw new RuntimeException('Installed configuration recording evidence is incomplete.');
        }

        $connection = InstalledConnectionRecordingSeam::recordAndDelegateToInstalledConnection(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
            $argv[2],
            $argv[3],
            $argv[4],
        );

        if (InstalledConnectionRecordingSeam::calls() !== 1) {
            throw new RuntimeException('Installed migration configuration recording count changed.');
        }
    } else {
        $connection = Connection::connect(
            $configuration->dsn,
            new QueryBudget(1),
            new QueryTrace(1),
            $configuration->username,
            $configuration->password,
        );
    }

    if ($connection->selectOneRow('SELECT 1 AS configured') !== ['configured' => 1]) {
        throw new RuntimeException('Installed migration configuration did not reach the visible connection boundary.');
    }

    fwrite(
        STDOUT,
        $recordDelivery
            ? "PASS installed migration typed configuration delivery\n"
            : "PASS installed migration typed configuration\n",
    );
} catch (InvalidArgumentException) {
    if (InstalledConnectionRecordingSeam::calls() !== 0) {
        fwrite(STDERR, "INFRASTRUCTURE_BOUNDARY_REACHED\n");
        exit(3);
    }

    fwrite(STDERR, "CONFIGURATION_INVALID\n");
    exit(2);
}
PHP,
    );
    writeFile(
        $contextPath,
        <<<'MD'
# Application configuration context

- Boundary: `installed-configuration-boundary.php` is the only process-environment reader; `installed-runtime-entrypoint.php` and `installed-migration-entrypoint.php` are separate executable composition roots.
- Runtime input `PHPTHIS_PROOF_RUNTIME_DATABASE_DSN`: required with no default or fallback; non-empty, at most 128 bytes, and begins exactly with `sqlite:`.
- Runtime input `PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME`: required with no default or fallback; 1 to 64 lowercase ASCII bytes matching `[a-z][a-z0-9-]{0,63}`.
- Runtime input `PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD`: required with no default or fallback; opaque and 1 to 64 bytes.
- Migration input `PHPTHIS_PROOF_MIGRATION_DATABASE_DSN`: required with no default or fallback; non-empty, at most 128 bytes, and begins exactly with `sqlite:`.
- Migration input `PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME`: required with no default or fallback; 1 to 64 lowercase ASCII bytes matching `[a-z][a-z0-9-]{0,63}`.
- Migration input `PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD`: required with no default or fallback; opaque and 1 to 64 bytes.
- Factories and types: `InstalledApplicationEnvironment::forHttp()` returns `InstalledRuntimeDatabaseConfiguration`; `InstalledApplicationEnvironment::forMigrations()` returns `InstalledMigrationDatabaseConfiguration`; both values are final readonly objects.
- Injection: each entrypoint visibly passes its concrete process-specific DSN, username, and password to the installed `Connection::connect`; proof-only recording mode records the same exact arguments before delegating to that installed connection.
- Authority: the HTTP/runtime entrypoint reads only runtime inputs and never falls back to migration authority; the migration entrypoint reads only migration inputs and never falls back to runtime authority.
- Failure: after source and autoload loading, every missing, empty, malformed, or oversized input fails before the proof-only recording seam, installed connection, or query with exact exit `2`, empty stdout, and `CONFIGURATION_INVALID` on stderr.
- Rotation and reload: deployment supplies fresh values to each newly started process; this proof records no in-process reload or hidden refresh behavior.
- Redaction: passwords and raw password validation are sensitive parameters; exact process output contains no input names or values, DSNs, usernames, passwords, exception text, or traces.
- Evidence: child-process tests execute both real entrypoint files, exact delivery through the installed connection, accepted bounds, every validation branch, poisoned opposite-authority inputs, per-field no-fallback controls, zero infrastructure calls on rejection, sensitivity reflection, exact redacted bytes, a real query, and the installed public checker.
MD,
    );
    writeFile($dataContextPath, installedSyntheticDatabaseContext());

    $configurationNames = [
        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD',
    ];
    $cleanEnvironment = environmentWithout($environment, $configurationNames);
    $runtimeDatabaseValues = [
        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite::memory:',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'runtime-user',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => 'runtime-synthetic-password',
    ];
    $migrationDatabaseValues = [
        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite::memory:',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'migration-user',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => 'migration-synthetic-password',
    ];
    $runtimeDeliveryValues = [
        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite:file:runtime-recording?mode=memory&cache=private',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'runtime-recorder',
        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => 'runtime-recording-password',
    ];
    $migrationDeliveryValues = [
        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite:file:migration-recording?mode=memory&cache=private',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'migration-recorder',
        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => 'migration-recording-password',
    ];
    $runtimeRecordingCommand = [
        PHP_BINARY,
        $runtimePath,
        'record',
        $runtimeDeliveryValues['PHPTHIS_PROOF_RUNTIME_DATABASE_DSN'],
        $runtimeDeliveryValues['PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME'],
        $runtimeDeliveryValues['PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD'],
    ];
    $migrationRecordingCommand = [
        PHP_BINARY,
        $migrationPath,
        'record',
        $migrationDeliveryValues['PHPTHIS_PROOF_MIGRATION_DATABASE_DSN'],
        $migrationDeliveryValues['PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME'],
        $migrationDeliveryValues['PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD'],
    ];
    $maximumDsn = 'sqlite:file:' . str_repeat('d', 90) . '?mode=memory&cache=private';

    try {
        $runtimeResult = runProcess(
            [PHP_BINARY, $runtimePath],
            $project,
            [...$cleanEnvironment, ...$runtimeDatabaseValues],
        );
        requireExactProcessResult(
            $runtimeResult,
            0,
            "PASS installed runtime typed configuration\n",
            '',
            'Runtime typed configuration failed without migration credentials.',
        );

        $migrationResult = runProcess(
            [PHP_BINARY, $migrationPath],
            $project,
            [...$cleanEnvironment, ...$migrationDatabaseValues],
        );
        requireExactProcessResult(
            $migrationResult,
            0,
            "PASS installed migration typed configuration\n",
            '',
            'Migration typed configuration failed without runtime credentials.',
        );

        foreach (
            [
                'runtime minimum credential bounds' => [
                    $runtimePath,
                    [
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite::memory:',
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'a',
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => 'p',
                    ],
                    "PASS installed runtime typed configuration\n",
                ],
                'runtime maximum credential bounds' => [
                    $runtimePath,
                    [
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => $maximumDsn,
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => str_repeat('u', 64),
                        'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('p', 64),
                    ],
                    "PASS installed runtime typed configuration\n",
                ],
                'migration minimum credential bounds' => [
                    $migrationPath,
                    [
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite::memory:',
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'a',
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => 'p',
                    ],
                    "PASS installed migration typed configuration\n",
                ],
                'migration maximum credential bounds' => [
                    $migrationPath,
                    [
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => $maximumDsn,
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => str_repeat('u', 64),
                        'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => str_repeat('p', 64),
                    ],
                    "PASS installed migration typed configuration\n",
                ],
            ] as $label => [$entrypoint, $values, $expectedStdout]
        ) {
            $boundaryResult = runProcess(
                [PHP_BINARY, $entrypoint],
                $project,
                [...$cleanEnvironment, ...$values],
            );
            requireExactProcessResult(
                $boundaryResult,
                0,
                $expectedStdout,
                '',
                "Installed configuration rejected {$label}.",
            );
        }

        $runtimeDeliveryResult = runProcess(
            $runtimeRecordingCommand,
            $project,
            [...$cleanEnvironment, ...$runtimeDeliveryValues],
        );
        requireExactProcessResult(
            $runtimeDeliveryResult,
            0,
            "PASS installed runtime typed configuration delivery\n",
            '',
            'Runtime configuration did not deliver the exact DSN, username, and password.',
        );

        $migrationDeliveryResult = runProcess(
            $migrationRecordingCommand,
            $project,
            [...$cleanEnvironment, ...$migrationDeliveryValues],
        );
        requireExactProcessResult(
            $migrationDeliveryResult,
            0,
            "PASS installed migration typed configuration delivery\n",
            '',
            'Migration configuration did not deliver the exact DSN, username, and password.',
        );

        $runtimeWithPoisonedMigrationResult = runProcess(
            [PHP_BINARY, $runtimePath],
            $project,
            [
                ...$cleanEnvironment,
                ...$runtimeDatabaseValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'not-a-migration-dsn',
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'INVALID MIGRATION USER',
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => str_repeat('migration-secret-', 8),
            ],
        );
        requireExactProcessResult(
            $runtimeWithPoisonedMigrationResult,
            0,
            "PASS installed runtime typed configuration\n",
            '',
            'Runtime entrypoint read or validated migration credentials.',
        );

        $migrationWithPoisonedRuntimeResult = runProcess(
            [PHP_BINARY, $migrationPath],
            $project,
            [
                ...$cleanEnvironment,
                ...$migrationDatabaseValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'not-a-runtime-dsn',
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'INVALID RUNTIME USER',
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('runtime-secret-', 8),
            ],
        );
        requireExactProcessResult(
            $migrationWithPoisonedRuntimeResult,
            0,
            "PASS installed migration typed configuration\n",
            '',
            'Migration entrypoint read or validated runtime credentials.',
        );

        foreach (array_keys($runtimeDeliveryValues) as $omittedName) {
            $runtimeWithoutOneCredential = [
                ...$cleanEnvironment,
                ...$runtimeDeliveryValues,
                ...$migrationDeliveryValues,
            ];
            unset($runtimeWithoutOneCredential[$omittedName]);
            $runtimeNoFallbackResult = runProcess(
                $runtimeRecordingCommand,
                $project,
                $runtimeWithoutOneCredential,
            );
            requireExactProcessResult(
                $runtimeNoFallbackResult,
                2,
                '',
                "CONFIGURATION_INVALID\n",
                "Runtime configuration unexpectedly fell back for {$omittedName}.",
            );
        }

        foreach (array_keys($migrationDeliveryValues) as $omittedName) {
            $migrationWithoutOneCredential = [
                ...$cleanEnvironment,
                ...$runtimeDeliveryValues,
                ...$migrationDeliveryValues,
            ];
            unset($migrationWithoutOneCredential[$omittedName]);
            $migrationNoFallbackResult = runProcess(
                $migrationRecordingCommand,
                $project,
                $migrationWithoutOneCredential,
            );
            requireExactProcessResult(
                $migrationNoFallbackResult,
                2,
                '',
                "CONFIGURATION_INVALID\n",
                "Migration configuration unexpectedly fell back for {$omittedName}.",
            );
        }

        $runtimeInvalidCases = [
            'empty runtime DSN' => environmentWithEmptyValue(
                $runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
            ),
            'malformed runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'empty runtime username' => environmentWithEmptyValue(
                $runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
            ),
            'malformed runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'INVALID RUNTIME USER',
            ],
            'oversized runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty runtime password' => environmentWithEmptyValue(
                $runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
            ),
            'oversized runtime password' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('p', 65),
            ],
        ];
        $migrationInvalidCases = [
            'empty migration DSN' => environmentWithEmptyValue(
                $migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN',
            ),
            'malformed migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'empty migration username' => environmentWithEmptyValue(
                $migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME',
            ),
            'malformed migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'INVALID MIGRATION USER',
            ],
            'oversized migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty migration password' => environmentWithEmptyValue(
                $migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD',
            ),
            'oversized migration password' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => str_repeat('p', 65),
            ],
        ];

        foreach (
            [
                'runtime' => [$runtimeRecordingCommand, $runtimeInvalidCases],
                'migration' => [$migrationRecordingCommand, $migrationInvalidCases],
            ] as $process => [$recordingCommand, $invalidCases]
        ) {
            foreach ($invalidCases as $label => $invalidValues) {
                $invalidResult = runProcess(
                    $recordingCommand,
                    $project,
                    [...$cleanEnvironment, ...$invalidValues],
                );
                requireExactProcessResult(
                    $invalidResult,
                    2,
                    '',
                    "CONFIGURATION_INVALID\n",
                    "{$process} {$label} did not fail before infrastructure with exact redacted output.",
                );
            }
        }

        $profileResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($profileResult, 'Canonical one-file configuration failed the installed profile.');
        proveComposerScriptsCannotAssignApplicationConfiguration(
            $project,
            $profileCommand,
            $environment,
        );
    } finally {
        writeFile($contextPath, $originalContext);
        writeFile($dataContextPath, $originalDataContext);

        foreach ([$boundaryPath, $runtimePath, $migrationPath] as $proofPath) {
            if (is_file($proofPath) && !unlink($proofPath)) {
                throw new RuntimeException("Unable to remove installed configuration proof {$proofPath}.");
            }
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveComposerScriptsCannotAssignApplicationConfiguration(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $composerPath = $project . '/composer.json';
    $original = file_get_contents($composerPath);

    if (!is_string($original)) {
        throw new RuntimeException('Unable to read the installed Composer configuration boundary.');
    }

    $secretSentinel = 'PHPTHIS_COMPOSER_CONFIGURATION_SECRET_SENTINEL';
    $rejectedScripts = [
        'string assignment' => [
            'realtime',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN="' . $secretSentinel . '" @php bin/websocket-server.php',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'list assignment' => [
            'realtime',
            [
                'Composer\\Config::disableProcessTimeout',
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME=$PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME @php bin/websocket-server.php',
            ],
            'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        ],
        'case-folded assignment text' => [
            'realtime',
            'phpthis_proof_runtime_database_dsn=' . $secretSentinel . ' @php bin/websocket-server.php',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'Composer environment clear' => [
            'realtime',
            '@putenv PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'POSIX environment clear' => [
            'realtime',
            'env -i -u PHPTHIS_PROOF_RUNTIME_DATABASE_DSN @php bin/websocket-server.php',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'POSIX unset with option' => [
            'realtime',
            'unset -v PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        ],
        'POSIX unset with option terminator' => [
            'realtime',
            'unset -- PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        ],
        'POSIX environment clear with joined option' => [
            'realtime',
            'env -uPHPTHIS_PROOF_RUNTIME_DATABASE_DSN @php bin/websocket-server.php',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'POSIX export without value' => [
            'realtime',
            'export PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'Windows persistent assignment' => [
            'realtime',
            'setx.exe /M PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME ' . $secretSentinel,
            'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        ],
        'Windows remote persistent assignment' => [
            'realtime',
            'setx /s computer1 /u domain\\user /p ' . $secretSentinel . ' PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME value',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        ],
        'case-insensitive PowerShell assignment' => [
            'realtime',
            '$env:phpthis_proof_runtime_database_username=' . "'{$secretSentinel}'",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME',
        ],
        'braced PowerShell assignment' => [
            'realtime',
            '${env:PHPTHIS_PROOF_RUNTIME_DATABASE_DSN} += ' . "'{$secretSentinel}'",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'PowerShell environment clear' => [
            'realtime',
            'Remove-Item -LiteralPath Env:\\PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'PowerShell environment set' => [
            'realtime',
            'Set-Item Env:PHPTHIS_PROOF_RUNTIME_DATABASE_DSN ' . $secretSentinel,
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'PowerShell environment provider create' => [
            'realtime',
            'New-Item -Path Env: -Name PHPTHIS_PROOF_RUNTIME_DATABASE_DSN -Value ' . $secretSentinel,
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'PowerShell environment provider copy' => [
            'realtime',
            'Copy-Item -Path Env:OTHER -Destination Env:\\PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'PowerShell environment provider rename' => [
            'realtime',
            'Rename-Item -Path Env:OTHER -NewName PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'runtime environment mutation' => [
            'realtime',
            '[System.Environment]::SetEnvironmentVariable(' . "'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD', '" . $secretSentinel . "')",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'inline PHP environment clear' => [
            'realtime',
            '@php -r ' . "'putenv(\"PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD\");'",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'inline PHP named-argument environment clear' => [
            'realtime',
            '@php -r ' . "'putenv(assignment:\n\"PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD\");'",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'inline PHP environment assignment' => [
            'realtime',
            '@php -r ' . "'\$_ENV[\n\"PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD\"\n]\n=\"{$secretSentinel}\";'",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'multiline runtime environment mutation' => [
            'realtime',
            '[System.Environment]::SetEnvironmentVariable(' . "\n'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD', '" . $secretSentinel . "')",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD',
        ],
        'assignment-looking argument text' => [
            'realtime',
            '@php tests/run.php --filter=PHPTHIS_PROOF_RUNTIME_DATABASE_DSN=' . $secretSentinel,
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
        'script-name redaction' => [
            "realtime\n{$secretSentinel}",
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN=value @php bin/websocket-server.php',
            'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ],
    ];

    try {
        foreach ($rejectedScripts as $label => [$scriptName, $script, $key]) {
            $composer = jsonFile($composerPath);
            $scripts = $composer['scripts'] ?? null;

            if (!is_array($scripts)) {
                throw new RuntimeException('The installed Composer scripts are missing.');
            }

            $scripts[$scriptName] = $script;
            $composer['scripts'] = $scripts;
            writeJson($composerPath, $composer);

            $result = runProcess($profileCommand, $project, $environment);
            requireFailure($result, "{$label} unexpectedly passed the installed Composer configuration boundary.");
            requireOutputContains(
                $result,
                "composer.json scripts must not contain assignment or mutation text for application configuration input {$key}",
            );

            if (str_contains($result['stdout'] . $result['stderr'], $secretSentinel)) {
                throw new RuntimeException("{$label} disclosed an assigned Composer configuration value.");
            }

            if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
                throw new RuntimeException('Unable to restore the installed Composer configuration boundary.');
            }
        }

        $composer = jsonFile($composerPath);
        $scripts = $composer['scripts'] ?? null;

        if (!is_array($scripts)) {
            throw new RuntimeException('The restored installed Composer scripts are missing.');
        }

        $scripts['realtime'] = [
            'Composer\\Config::disableProcessTimeout',
            '@php bin/websocket-server.php',
        ];
        $scripts['tooling'] = [
            'XDEBUG_MODE=off @php tests/run.php --filter=PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
            'setx UNRELATED PHPTHIS_PROOF_RUNTIME_DATABASE_DSN',
        ];
        $composer['scripts'] = $scripts;
        writeJson($composerPath, $composer);

        $safeResult = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $safeResult,
            'Value-free Composer entrypoints or an unrelated tooling assignment unexpectedly failed.',
        );
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the installed Composer configuration boundary.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledConfigurationEvidenceReference(
    string $project,
    string $installedFramework,
    array $profileCommand,
    array $environment,
): void {
    $guidePath = $installedFramework . '/docs/configuration.md';
    $guide = file_get_contents($guidePath);

    if (!is_string($guide)) {
        throw new RuntimeException('Unable to read the installed configuration evidence guide.');
    }

    $headingMarker = '### Copyable child-process configuration evidence';
    $markerOffset = strpos($guide, $headingMarker);

    if ($markerOffset === false) {
        throw new RuntimeException('The installed configuration evidence reference is missing.');
    }

    $blockMarker = "\n```php\n";
    $blockOffset = strpos($guide, $blockMarker, $markerOffset + strlen($headingMarker));

    if ($blockOffset === false) {
        throw new RuntimeException('The installed configuration evidence PHP block is missing.');
    }

    $sourceOffset = $blockOffset + strlen($blockMarker);
    $sourceEnd = strpos($guide, "\n```", $sourceOffset);

    if ($sourceEnd === false) {
        throw new RuntimeException('The installed configuration evidence reference is incomplete.');
    }

    $referenceSource = substr($guide, $sourceOffset, $sourceEnd - $sourceOffset);

    if ($referenceSource === '') {
        throw new RuntimeException('The installed configuration evidence reference is empty.');
    }

    $referencePath = $project . '/tests/configuration-child-process-reference.php';
    $fixtureDirectory = $project . '/tests/fixtures';
    $entrypointPath = $fixtureDirectory . '/runtime-configuration-entrypoint.php';
    $emptyEntrypointPath = $fixtureDirectory . '/empty-configuration-entrypoint.php';
    $boundaryPath = $project . '/configuration-reference-boundary.php';
    $contextPath = $project . '/.ai/configuration.md';
    $originalContext = file_get_contents($contextPath);

    if (!is_string($originalContext)) {
        throw new RuntimeException('Unable to read the installed configuration context.');
    }

    $createdFixtureDirectory = false;

    try {
        if (!is_dir($fixtureDirectory)) {
            if (!mkdir($fixtureDirectory, 0700)) {
                throw new RuntimeException('Unable to create the installed configuration evidence fixture directory.');
            }

            $createdFixtureDirectory = true;
        }

        writeFile(
            $boundaryPath,
            <<<'PHP'
<?php

declare(strict_types=1);

final class ReferenceEmptyRuntimeMode extends InvalidArgumentException
{
}

final readonly class ReferenceRuntimeConfiguration
{
    public function __construct(
        public string $mode,
        public string $endpoint,
        #[\SensitiveParameter]
        public string $credential,
    ) {
    }
}

final class ReferenceApplicationEnvironment
{
    public static function forHttp(): ReferenceRuntimeConfiguration
    {
        return new ReferenceRuntimeConfiguration(
            self::mode(\getenv('APP_RUNTIME_MODE')),
            self::endpoint(\getenv('APP_RUNTIME_ENDPOINT')),
            self::credential(\getenv('APP_RUNTIME_CREDENTIAL')),
        );
    }

    private static function mode(string|false $value): string
    {
        if ($value === '') {
            throw new ReferenceEmptyRuntimeMode('Required application configuration is invalid.');
        }

        if ($value !== 'synthetic') {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function endpoint(string|false $value): string
    {
        if (
            $value === false
            || $value === ''
            || strlen($value) > 128
            || !str_starts_with($value, 'https://')
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    private static function credential(#[\SensitiveParameter] string|false $value): string
    {
        if ($value === false || $value === '' || strlen($value) > 64) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }
}
PHP,
        );
        writeFile(
            $entrypointPath,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/configuration-reference-boundary.php';

try {
    ReferenceApplicationEnvironment::forHttp();
    fwrite(STDOUT, "CONFIGURATION_OK\n");
} catch (InvalidArgumentException) {
    fwrite(STDERR, "CONFIGURATION_INVALID\n");
    exit(2);
}
PHP,
        );
        writeFile(
            $emptyEntrypointPath,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/configuration-reference-boundary.php';

try {
    ReferenceApplicationEnvironment::forHttp();
} catch (ReferenceEmptyRuntimeMode) {
    fwrite(STDOUT, "PASS installed empty configuration delivery\n");
    exit(0);
} catch (InvalidArgumentException) {
    fwrite(STDERR, "EMPTY_CONFIGURATION_NOT_DELIVERED\n");
    exit(2);
}

fwrite(STDERR, "EMPTY_CONFIGURATION_NOT_DELIVERED\n");
exit(2);
PHP,
        );
        writeFile($referencePath, $referenceSource . "\n");
        writeFile(
            $contextPath,
            <<<'MD'
# Installed configuration evidence reference context

- Boundary: `configuration-reference-boundary.php` is the only process-environment reader.
- Inputs: `APP_RUNTIME_MODE`, `APP_RUNTIME_ENDPOINT`, and `APP_RUNTIME_CREDENTIAL` are required without defaults or fallback; values are never recorded here.
- Factory and type: `ReferenceApplicationEnvironment::forHttp()` returns the final readonly `ReferenceRuntimeConfiguration` before application-controlled I/O.
- Authority: this proof adopts one runtime parser only; worker, migration, and administrative profiles are not applicable.
- Injection: configuration-only scope is selected, so infrastructure composition is deferred.
- Failure: missing, empty, malformed, and oversized inputs produce exact exit `2`, empty stdout, and `CONFIGURATION_INVALID` on stderr before infrastructure or business I/O.
- Rotation: every evidence invocation is a fresh process; no hidden reload behavior is adopted.
- Redaction: the public reference asserts exact stream bytes and explicit absence of one supplied synthetic sentinel.
- Evidence: the exact PHP block extracted from installed `docs/configuration.md` passes the installed maximum-level profile and executes the intentionally short-lived, tiny-fixed-output parser fixture in fresh child processes with an explicit synthetic application environment and no null inheritance; a focused probe separately invokes the matching factory and proves that the raw `NAME=` form reaches its exact empty-value validation branch, while a paired run with the mode omitted proves that missing remains distinct; a hard timeout remains caller- or CI-owned and is not established by this harness.
MD,
        );

        $cleanEnvironment = environmentWithout(
            $environment,
            ['APP_RUNTIME_MODE', 'APP_RUNTIME_ENDPOINT', 'APP_RUNTIME_CREDENTIAL'],
        );
        $profileResult = runProcess($profileCommand, $project, $cleanEnvironment);
        requireSuccess(
            $profileResult,
            'The installed public configuration evidence reference failed the maximum-level profile.',
        );

        $emptyDeliveryResult = runProcess(
            [PHP_BINARY, $emptyEntrypointPath],
            $project,
            [
                '' => 'APP_RUNTIME_MODE=',
                'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
                'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
            ],
        );
        requireExactProcessResult(
            $emptyDeliveryResult,
            0,
            "PASS installed empty configuration delivery\n",
            '',
            'The installed empty configuration environment entry was not delivered as empty.',
        );

        $missingDeliveryResult = runProcess(
            [PHP_BINARY, $emptyEntrypointPath],
            $project,
            [
                'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
                'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
            ],
        );
        requireExactProcessResult(
            $missingDeliveryResult,
            2,
            '',
            "EMPTY_CONFIGURATION_NOT_DELIVERED\n",
            'The installed missing runtime mode was misclassified as empty.',
        );

        $referenceResult = runProcess(
            [PHP_BINARY, $referencePath],
            $project,
            $cleanEnvironment,
        );
        requireExactProcessResult(
            $referenceResult,
            0,
            "PASS child-process configuration evidence\n",
            '',
            'The installed public configuration evidence reference changed behavior.',
        );
        requireOutputNotContains(
            $referenceResult,
            'synthetic-rejected-value-must-not-appear',
        );
    } finally {
        writeFile($contextPath, $originalContext);

        foreach ([$referencePath, $entrypointPath, $emptyEntrypointPath, $boundaryPath] as $proofPath) {
            if (is_file($proofPath) && !unlink($proofPath)) {
                throw new RuntimeException("Unable to remove installed configuration evidence proof {$proofPath}.");
            }
        }

        if ($createdFixtureDirectory && is_dir($fixtureDirectory) && !rmdir($fixtureDirectory)) {
            throw new RuntimeException('Unable to remove the installed configuration evidence fixture directory.');
        }
    }
}
