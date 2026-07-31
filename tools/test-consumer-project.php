<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composerBinary = composerBinary($root);
$workspace = sys_get_temp_dir() . '/phpthis-consumer-proof-' . bin2hex(random_bytes(12));

if (!mkdir($workspace, 0700)) {
    throw new RuntimeException('Unable to create the isolated consumer-proof directory.');
}

try {
    $environment = processEnvironment([
        'COMPOSER_CACHE_DIR' => $workspace . '/composer-cache',
        'COMPOSER_DISABLE_NETWORK' => '1',
        'COMPOSER_ROOT_VERSION' => 'dev-main',
    ]);
    $archiveDirectory = $workspace . '/archive';

    if (!mkdir($archiveDirectory, 0700)) {
        throw new RuntimeException('Unable to create the package-archive directory.');
    }

    $archiveResult = runProcess(
        composerCommand($composerBinary, [
            'archive',
            '--format=tar',
            '--dir=' . $archiveDirectory,
            '--file=phpthis-framework',
        ]),
        $root,
        $environment,
    );
    requireSuccess($archiveResult, 'Framework archive creation failed.');

    $archivePath = $archiveDirectory . '/phpthis-framework.tar';

    if (!is_file($archivePath)) {
        throw new RuntimeException('Composer did not create the expected framework archive.');
    }

    $expectedArchiveFiles = expectedArchiveFiles($root);
    $archiveFiles = archiveFiles($archivePath);
    verifyExportPolicies($root, $workspace, $expectedArchiveFiles, $environment);
    verifySkeletonPublicationBoundary($root);

    if ($archiveFiles !== $expectedArchiveFiles) {
        throw new RuntimeException(inventoryDifference($expectedArchiveFiles, $archiveFiles));
    }

    $project = $workspace . '/application';
    copyDirectory($root . '/skeleton', $project);
    configureIsolatedConsumer($root, $project, $archivePath);

    $installResult = runProcess(
        composerCommand($composerBinary, [
            'install',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ]),
        $project,
        $environment,
    );
    requireSuccess($installResult, 'Isolated consumer dependency installation failed.');

    $validateResult = runProcess(
        composerCommand($composerBinary, ['validate', '--strict', '--no-check-publish']),
        $project,
        $environment,
    );
    requireSuccess($validateResult, 'Isolated consumer Composer validation failed.');

    $installedFramework = $project . '/vendor/phpthis/framework';

    if (!is_dir($installedFramework) || is_link($installedFramework)) {
        throw new RuntimeException('The consumer must install a mirrored framework package, not a symlink.');
    }

    if (
        !is_executable($installedFramework . '/bin/phpthis')
        || !is_executable($project . '/vendor/bin/phpthis')
    ) {
        throw new RuntimeException('The installed PHPThis consumer command is not executable.');
    }

    $installedFiles = directoryFiles($installedFramework);

    if ($installedFiles !== $expectedArchiveFiles) {
        throw new RuntimeException('The installed framework inventory differs from the verified archive.');
    }

    $profileCommand = [$project . '/vendor/bin/phpthis', 'check'];
    proveInstalledUuidAndUlidRouting($project, $environment);
    proveInstalledTypedConfiguration($project, $profileCommand, $environment);
    $requestHandlerDecoratorProofPath = proveInstalledRequestHandlerDecorator($project, $environment);

    try {
        $profileResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($profileResult, 'The clean skeleton and request-handler decorator proof failed the installed profile check.');
        requireStdoutContains(
            $profileResult,
            'PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)',
        );
        requireStdoutNotContains($profileResult, 'ADVISORY');
        requireOutputContains($profileResult, 'PASS PHPThis application check');
        requireOutputNotContains($profileResult, $project . '/bootstrap.php');
    } finally {
        if (is_file($requestHandlerDecoratorProofPath) && !unlink($requestHandlerDecoratorProofPath)) {
            throw new RuntimeException('Unable to remove the installed request-handler decorator proof.');
        }
    }

    if (!is_file($project . '/vendor/.phpthis/phpstan/resultCache.php')) {
        throw new RuntimeException('The normal application check did not create its persistent PHPStan cache.');
    }

    $debugResult = runProcess(
        [$project . '/vendor/bin/phpthis', 'check', '--debug'],
        $project,
        $environment,
    );
    requireSuccess($debugResult, 'The explicit diagnostic profile check failed.');
    requireStdoutContains(
        $debugResult,
        'PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)',
    );
    requireStdoutNotContains($debugResult, 'ADVISORY');
    requireOutputContains($debugResult, $project . '/bootstrap.php');

    $completeResult = runProcess(
        composerCommand($composerBinary, ['check']),
        $project,
        $environment,
    );
    requireSuccess($completeResult, 'The clean skeleton failed its complete application check.');
    requireOutputContains($completeResult, 'PASS application behavior and front controller');

    proveDuplicationAdvisoryIsReportOnly(
        $project,
        $composerBinary,
        $profileCommand,
        $environment,
    );
    proveObservabilityContextIsRequired($project, $profileCommand, $environment);
    proveConfigurationContextIsRequired($project, $profileCommand, $environment);
    proveEveryApplicationDirectoryIsChecked($project, $profileCommand, $environment);
    proveValidExtensionlessExecutableIsChecked($project, $profileCommand, $environment);
    proveMagicMethodsAreRejected($project, $profileCommand, $environment);
    proveDependencyDirectoryIsExcluded($project, $profileCommand, $environment);
    proveMixedCoercionIsRejected($project, $profileCommand, $environment);
    proveDirectPdoConstructionIsRejected($project, $profileCommand, $environment);
    proveNativeSessionAccessIsRejected($project, $profileCommand, $environment);
    proveEnvironmentAccessIsRejected($project, $profileCommand, $environment);
    proveDynamicSqlIsRejected($project, $profileCommand, $environment);
    proveConfigurationCannotReplaceProfile($project, $profileCommand, $environment);
    proveBaselinesAndInlineIgnoresAreRejected($project, $profileCommand, $environment);
    proveComposerGateCannotDrift($project, $composerBinary, $profileCommand, $environment);
    proveSymlinkedSourceIsRejected($workspace, $project, $profileCommand, $environment);

    $restoredResult = runProcess($profileCommand, $project, $environment);
    requireSuccess($restoredResult, 'The skeleton did not return to a valid state after negative controls.');

    fwrite(
        STDOUT,
        sprintf(
            "PASS isolated consumer: %d release files, clean install, complete check, and adversarial controls\n",
            count($archiveFiles),
        ),
    );
} finally {
    removeDirectory($workspace);
}

/** @param array<string, string> $environment */
function proveInstalledUuidAndUlidRouting(string $project, array $environment): void
{
    $proofPath = $project . '/installed-routing-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require __DIR__ . '/vendor/autoload.php';

$handler = new class implements RequestHandler {
    public function handle(Request $request): Response
    {
        return new Response(204, [], '');
    }
};
$router = new Router([
    new Route('GET', '/accounts/{account_id:uuid}', $handler),
    new Route('POST', '/events/{event_id:ulid}', $handler),
]);
$uuid = '01890f5a-4c96-7a2b-8c3d-123456789abc';
$ulid = '01arz3ndektsv4rrffq69g5fav';
$uuidMatch = $router->match(new Request('GET', '/accounts/' . $uuid));
$ulidMatch = $router->match(new Request('POST', '/events/' . $ulid));

if (
    $uuidMatch?->pathParameters->uuid('account_id') !== $uuid
    || $ulidMatch?->pathParameters->ulid('event_id') !== $ulid
    || $router->match(new Request('GET', '/accounts/' . strtoupper($uuid))) !== null
    || $router->match(new Request('POST', '/events/' . strtoupper($ulid))) !== null
    || $router->allowedMethodsForPath('/accounts/' . $uuid) !== ['GET']
    || $router->allowedMethodsForPath('/events/' . $ulid) !== ['POST']
) {
    throw new RuntimeException('Installed UUID and ULID routing did not preserve the canonical contract.');
}

fwrite(STDOUT, "PASS installed UUID and ULID routing\n");
PHP,
    );

    try {
        $result = runProcess([PHP_BINARY, $proofPath], $project, $environment);
        requireSuccess($result, 'The installed framework failed UUID and ULID routing proof.');
        requireOutputContains($result, 'PASS installed UUID and ULID routing');
    } finally {
        if (is_file($proofPath) && !unlink($proofPath)) {
            throw new RuntimeException('Unable to remove the installed routing proof.');
        }
    }
}

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
    $originalContext = file_get_contents($contextPath);

    if (!is_string($originalContext)) {
        throw new RuntimeException('Unable to read the installed configuration context proof.');
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
            'empty runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => '',
            ],
            'malformed runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized runtime DSN' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'malformed runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => 'INVALID RUNTIME USER',
            ],
            'oversized runtime username' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty runtime password' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => '',
            ],
            'oversized runtime password' => [
                ...$runtimeDeliveryValues,
                'PHPTHIS_PROOF_RUNTIME_DATABASE_PASSWORD' => str_repeat('p', 65),
            ],
        ];
        $migrationInvalidCases = [
            'empty migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => '',
            ],
            'malformed migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'mysql:synthetic',
            ],
            'oversized migration DSN' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_DSN' => 'sqlite:' . str_repeat('d', 122),
            ],
            'malformed migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => 'INVALID MIGRATION USER',
            ],
            'oversized migration username' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_USERNAME' => str_repeat('u', 65),
            ],
            'empty migration password' => [
                ...$migrationDeliveryValues,
                'PHPTHIS_PROOF_MIGRATION_DATABASE_PASSWORD' => '',
            ],
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
    } finally {
        writeFile($contextPath, $originalContext);

        foreach ([$boundaryPath, $runtimePath, $migrationPath] as $proofPath) {
            if (is_file($proofPath) && !unlink($proofPath)) {
                throw new RuntimeException("Unable to remove installed configuration proof {$proofPath}.");
            }
        }
    }
}

/** @param array<string, string> $environment */
function proveInstalledRequestHandlerDecorator(string $project, array $environment): string
{
    $proofPath = $project . '/installed-handler-decorator-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require __DIR__ . '/vendor/autoload.php';

final class InstalledDecoratorTrace
{
    /** @var list<string> */
    private array $steps = [];

    private int $downstreamCalls = 0;

    private ?int $decoratorRequestId = null;

    private ?int $downstreamRequestId = null;

    public function recordBefore(Request $request): void
    {
        $this->steps[] = 'before';
        $this->decoratorRequestId = spl_object_id($request);
    }

    public function recordAfter(): void
    {
        $this->steps[] = 'after';
    }

    public function recordHandler(Request $request): void
    {
        $this->steps[] = 'handler';
        $this->downstreamCalls++;
        $this->downstreamRequestId = spl_object_id($request);
    }

    /** @return list<string> */
    public function steps(): array
    {
        return $this->steps;
    }

    public function downstreamCalls(): int
    {
        return $this->downstreamCalls;
    }

    public function decoratorRequestId(): ?int
    {
        return $this->decoratorRequestId;
    }

    public function downstreamRequestId(): ?int
    {
        return $this->downstreamRequestId;
    }
}

final readonly class InstalledHeaderDecorator implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private InstalledDecoratorTrace $trace,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordBefore($request);
        $response = $this->downstream->handle($request);
        $this->trace->recordAfter();

        return new Response(
            $response->status,
            [...$response->headers, 'X-Decorator-Proof' => 'present'],
            $response->body,
            $response->cookies,
            $response->fileBody,
        );
    }
}

final readonly class InstalledRejectingDecorator implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private bool $reject,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->reject) {
            return new Response(429, ['Cache-Control' => 'no-store'], "Rejected\n");
        }

        return $this->downstream->handle($request);
    }
}

final readonly class InstalledDecoratedHandler implements RequestHandler
{
    public function __construct(private InstalledDecoratorTrace $trace)
    {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordHandler($request);

        return new Response(200, ['Cache-Control' => 'no-store'], "Decorated\n");
    }
}

function assertInstalledDecoratedResponse(
    Response $response,
    InstalledDecoratorTrace $trace,
): void {
    if (
        $response->status !== 200
        || $response->headers !== [
            'Cache-Control' => 'no-store',
            'X-Decorator-Proof' => 'present',
        ]
        || $response->body !== "Decorated\n"
        || $trace->steps() !== ['before', 'handler', 'after']
        || $trace->downstreamCalls() !== 1
        || $trace->decoratorRequestId() === null
        || $trace->decoratorRequestId() !== $trace->downstreamRequestId()
    ) {
        throw new RuntimeException('Installed route decorator did not preserve explicit composition.');
    }
}

function assertInstalledDecoratorRejection(
    Response $response,
    InstalledDecoratorTrace $trace,
): void {
    if ($response->status !== 429 || $trace->downstreamCalls() !== 1) {
        throw new RuntimeException('Installed rejecting decorator entered downstream work.');
    }
}

function assertInstalledDecoratorIsolation(InstalledDecoratorTrace $trace): void
{
    if (
        $trace->downstreamCalls() !== 1
        || $trace->steps() !== ['before', 'handler', 'after']
    ) {
        throw new RuntimeException('Route miss or method rejection entered decorated work.');
    }
}

$trace = new InstalledDecoratorTrace();
$application = new Application(new Router([
    new Route(
        'GET',
        '/decorated',
        new InstalledHeaderDecorator(
            new InstalledDecoratedHandler($trace),
            $trace,
        ),
    ),
    new Route(
        'GET',
        '/rejected',
        new InstalledRejectingDecorator(
            new InstalledDecoratedHandler($trace),
            true,
        ),
    ),
    new Route('GET', '/plain', new InstalledDecoratedHandler($trace)),
]));
$request = new Request('GET', '/decorated');
$response = $application->handle($request);
assertInstalledDecoratedResponse($response, $trace);

$rejectedResponse = $application->handle(new Request('GET', '/rejected'));
assertInstalledDecoratorRejection($rejectedResponse, $trace);

$application->handle(new Request('POST', '/decorated'));
$application->handle(new Request('GET', '/missing'));
assertInstalledDecoratorIsolation($trace);

fwrite(STDOUT, "PASS installed request-handler decorator composition\n");
PHP,
    );

    $result = runProcess([PHP_BINARY, $proofPath], $project, $environment);
    requireSuccess($result, 'The installed framework failed request-handler decorator proof.');
    requireOutputContains($result, 'PASS installed request-handler decorator composition');

    return $proofPath;
}

/**
 * @param array<string, string> $overrides
 * @return array<string, string>
 */
function processEnvironment(array $overrides): array
{
    $environment = getenv();

    foreach ($overrides as $name => $value) {
        $environment[$name] = $value;
    }

    return $environment;
}

/**
 * @param array<string, string> $environment
 * @param list<string> $names
 * @return array<string, string>
 */
function environmentWithout(array $environment, array $names): array
{
    foreach ($names as $name) {
        unset($environment[$name]);
    }

    return $environment;
}

function composerBinary(string $root): string
{
    $configured = getenv('COMPOSER_BINARY');

    if (is_string($configured) && $configured !== '') {
        $resolved = realpath($configured);

        if (is_string($resolved) && is_file($resolved)) {
            return $resolved;
        }

        return $configured;
    }

    $localPhar = $root . '/composer.phar';

    if (is_file($localPhar)) {
        return $localPhar;
    }

    throw new RuntimeException('COMPOSER_BINARY is unavailable; run this proof through Composer.');
}

/**
 * @param list<string> $arguments
 * @return list<string>
 */
function composerCommand(string $binary, array $arguments): array
{
    $command = str_ends_with(strtolower($binary), '.phar') ? [PHP_BINARY, $binary] : [$binary];

    return [...$command, ...$arguments];
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runProcess(array $command, string $workingDirectory, array $environment): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory,
        $environment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start process: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read process output.');
    }

    return [
        'exit_code' => $exitCode >= 0 ? $exitCode : 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireExactProcessResult(
    array $result,
    int $exitCode,
    string $stdout,
    string $stderr,
    string $message,
): void {
    if (
        $result['exit_code'] !== $exitCode
        || $result['stdout'] !== $stdout
        || $result['stderr'] !== $stderr
    ) {
        throw new RuntimeException($message);
    }
}

/**
 * @param array{exit_code: int, stdout: string, stderr: string} $result
 * @param list<string> $expected
 */
function requireExactFailureLines(
    array $result,
    array $expected,
    string $message,
): void {
    requireExactProcessResult(
        $result,
        1,
        '',
        implode("\n", $expected) . "\n",
        $message,
    );
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireSuccess(array $result, string $message): void
{
    if ($result['exit_code'] !== 0) {
        throw new RuntimeException($message . "\n" . $result['stderr'] . $result['stdout']);
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireFailure(array $result, string $message): void
{
    if ($result['exit_code'] === 0) {
        throw new RuntimeException($message . "\n" . $result['stdout']);
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireOutputContains(array $result, string $expected): void
{
    if (!str_contains($result['stdout'] . $result['stderr'], $expected)) {
        throw new RuntimeException("Expected process output to contain: {$expected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireOutputNotContains(array $result, string $unexpected): void
{
    if (str_contains($result['stdout'] . $result['stderr'], $unexpected)) {
        throw new RuntimeException("Expected process output not to contain: {$unexpected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireStdoutContains(array $result, string $expected): void
{
    if (!str_contains($result['stdout'], $expected)) {
        throw new RuntimeException("Expected process stdout to contain: {$expected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireStdoutNotContains(array $result, string $unexpected): void
{
    if (str_contains($result['stdout'], $unexpected)) {
        throw new RuntimeException("Expected process stdout not to contain: {$unexpected}");
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function advisoryOutput(array $result): string
{
    $lines = preg_split('/\R/', $result['stdout']);

    if (!is_array($lines)) {
        throw new RuntimeException('Unable to split checker advisory output.');
    }

    return implode(
        "\n",
        array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, 'ADVISORY'),
        )),
    );
}

/** @return list<string> */
function expectedArchiveFiles(string $root): array
{
    $manifestPath = $root . '/tools/package-files.txt';
    $files = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($files) || $files === []) {
        throw new RuntimeException('The framework package inventory manifest is empty or unreadable.');
    }

    foreach ($files as $file) {
        if ($file === '' || str_starts_with($file, '/') || !is_file($root . '/' . $file)) {
            throw new RuntimeException("Invalid framework package inventory entry: {$file}");
        }
    }

    sort($files, SORT_STRING);

    if (count($files) !== count(array_unique($files))) {
        throw new RuntimeException('The framework package inventory contains a duplicate path.');
    }

    return $files;
}

/**
 * @param list<string> $expectedFiles
 * @param array<string, string> $environment
 */
function verifyExportPolicies(
    string $root,
    string $workspace,
    array $expectedFiles,
    array $environment,
): void {
    $composer = jsonFile($root . '/composer.json');
    $archive = $composer['archive'] ?? null;
    $composerExclusions = is_array($archive) ? ($archive['exclude'] ?? null) : null;

    if (!is_array($composerExclusions) || !array_is_list($composerExclusions)) {
        throw new RuntimeException('composer.json must define a list of archive exclusions.');
    }

    foreach ($composerExclusions as $exclusion) {
        if (!is_string($exclusion)) {
            throw new RuntimeException('Composer archive exclusions must be strings.');
        }
    }

    $attributeLines = file($root . '/.gitattributes', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($attributeLines)) {
        throw new RuntimeException('Unable to read .gitattributes export policy.');
    }

    $attributeExclusions = [];

    foreach ($attributeLines as $line) {
        $matches = [];

        if (preg_match('/\A(\/\S+) export-ignore\z/', $line, $matches) !== 1) {
            throw new RuntimeException("Unexpected .gitattributes release-policy line: {$line}");
        }

        $attributeExclusions[] = $matches[1];
    }

    sort($composerExclusions, SORT_STRING);
    sort($attributeExclusions, SORT_STRING);

    if ($composerExclusions !== $attributeExclusions) {
        throw new RuntimeException('Composer and Git export exclusions must remain identical.');
    }

    $status = runProcess(
        ['git', 'status', '--porcelain', '--untracked-files=all'],
        $root,
        $environment,
    );
    requireSuccess($status, 'Unable to determine whether the Git export can be verified.');

    if (trim($status['stdout']) !== '') {
        return;
    }

    $gitArchivePath = $workspace . '/git-export.tar';
    $gitArchive = runProcess(
        [
            'git',
            'archive',
            '--format=tar',
            '--worktree-attributes',
            '--output=' . $gitArchivePath,
            'HEAD',
        ],
        $root,
        $environment,
    );
    requireSuccess($gitArchive, 'Git release-archive creation failed.');

    $gitFiles = archiveFiles($gitArchivePath);

    if ($gitFiles !== $expectedFiles) {
        throw new RuntimeException(inventoryDifference($expectedFiles, $gitFiles));
    }
}

/** @return list<string> */
function archiveFiles(string $archivePath): array
{
    $resolvedArchivePath = realpath($archivePath);

    if (!is_string($resolvedArchivePath)) {
        throw new RuntimeException('Unable to resolve the package archive.');
    }

    $archive = new PharData($resolvedArchivePath);
    $prefix = 'phar://' . $resolvedArchivePath . '/';
    $files = [];
    $iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();

        if (!str_starts_with($path, $prefix)) {
            throw new RuntimeException('Unable to resolve a package-archive entry.');
        }

        $files[] = substr($path, strlen($prefix));
    }

    sort($files, SORT_STRING);

    return $files;
}

/** @return list<string> */
function directoryFiles(string $root, string $prefix = ''): array
{
    if (!is_dir($root)) {
        throw new RuntimeException("Required directory is missing: {$root}");
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($root) + 1);
        $files[] = $prefix . str_replace('\\', '/', $relativePath);
    }

    sort($files, SORT_STRING);

    return $files;
}

/**
 * @param list<string> $expected
 * @param list<string> $actual
 */
function inventoryDifference(array $expected, array $actual): string
{
    $missing = array_values(array_diff($expected, $actual));
    $unexpected = array_values(array_diff($actual, $expected));

    return sprintf(
        "Framework archive inventory changed.\nMissing: %s\nUnexpected: %s",
        $missing === [] ? 'none' : implode(', ', $missing),
        $unexpected === [] ? 'none' : implode(', ', $unexpected),
    );
}

function configureIsolatedConsumer(string $root, string $project, string $archivePath): void
{
    $composerPath = $project . '/composer.json';
    $composer = jsonFile($composerPath);
    $rootComposer = jsonFile($root . '/composer.json');
    $phpstanVersion = lockedVersion($root, 'phpstan/phpstan');
    $strictRulesVersion = lockedVersion($root, 'phpstan/phpstan-strict-rules');
    $frameworkVersion = is_file($root . '/skeleton/composer.lock')
        ? lockedVersion($root . '/skeleton', 'phpthis/framework')
        : 'dev-main';
    $projectLock = $project . '/composer.lock';

    if (is_file($projectLock) && !unlink($projectLock)) {
        throw new RuntimeException('Unable to remove the copied skeleton lock for the local archive proof.');
    }

    $composer['repositories'] = [
        [
            'type' => 'package',
            'package' => [
                'name' => 'phpthis/framework',
                'version' => $frameworkVersion,
                'type' => 'library',
                'dist' => ['type' => 'tar', 'url' => 'file://' . $archivePath],
                'require' => $rootComposer['require'],
                'autoload' => $rootComposer['autoload'],
                'bin' => $rootComposer['bin'],
            ],
        ],
        pathRepository($root . '/vendor/phpstan/phpstan', 'phpstan/phpstan', $phpstanVersion),
        pathRepository(
            $root . '/vendor/phpstan/phpstan-strict-rules',
            'phpstan/phpstan-strict-rules',
            $strictRulesVersion,
        ),
        ['packagist.org' => false],
    ];

    writeJson($composerPath, $composer);
}

function verifySkeletonPublicationBoundary(string $root): void
{
    $composer = jsonFile($root . '/skeleton/composer.json');
    $require = $composer['require'] ?? null;
    $frameworkConstraint = is_array($require) ? ($require['phpthis/framework'] ?? null) : null;

    if (!is_string($frameworkConstraint) || $frameworkConstraint === '') {
        throw new RuntimeException('The skeleton must declare its framework constraint.');
    }

    if ($frameworkConstraint === 'dev-main') {
        $expectedBootstrapRepository = [[
            'type' => 'vcs',
            'url' => 'https://github.com/balgf/PHPThis.git',
        ]];

        if (($composer['repositories'] ?? null) !== $expectedBootstrapRepository) {
            throw new RuntimeException('The pre-alpha skeleton must use only the documented framework VCS bootstrap.');
        }

        return;
    }

    if (array_key_exists('repositories', $composer)) {
        throw new RuntimeException('A tagged skeleton must remove the pre-alpha framework VCS repository override.');
    }

    if (!is_file($root . '/skeleton/composer.lock')) {
        throw new RuntimeException('A tagged skeleton must commit its Composer lockfile.');
    }
}

/** @return array<array-key, mixed> */
function jsonFile(string $path): array
{
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        throw new RuntimeException("Unable to read JSON file {$path}.");
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException("JSON file {$path} must contain an object.");
    }

    return $decoded;
}

/** @param array<array-key, mixed> $contents */
function writeJson(string $path, array $contents): void
{
    $encoded = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (file_put_contents($path, $encoded . "\n", LOCK_EX) === false) {
        throw new RuntimeException("Unable to write JSON file {$path}.");
    }
}

/** @return array<string, mixed> */
function pathRepository(string $path, string $package, string $version): array
{
    return [
        'type' => 'path',
        'url' => $path,
        'options' => [
            'symlink' => false,
            'versions' => [$package => $version],
        ],
    ];
}

function lockedVersion(string $root, string $package): string
{
    $lock = jsonFile($root . '/composer.lock');

    foreach (['packages', 'packages-dev'] as $section) {
        $packages = $lock[$section] ?? null;

        if (!is_array($packages)) {
            continue;
        }

        foreach ($packages as $candidate) {
            if (
                is_array($candidate)
                && ($candidate['name'] ?? null) === $package
                && is_string($candidate['version'] ?? null)
            ) {
                return $candidate['version'];
            }
        }
    }

    throw new RuntimeException("Locked package is missing: {$package}");
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDuplicationAdvisoryIsReportOnly(
    string $project,
    string $composerBinary,
    array $profileCommand,
    array $environment,
): void {
    $firstPath = $project . '/.hidden/duplication/FirstDuplicationProof.php';
    $secondPath = $project . '/unconventional/duplication/SecondDuplicationProof.php';
    $frameworkPath = $project . '/vendor/phpthis/framework/duplication-negative-control.php';
    $dependencyPath = $project . '/vendor/dependency-negative-control/DuplicationProof.php';
    $vcsPath = $project . '/.git/duplication-negative-control.php';
    $largeAdvisoryPath = $project . '/unconventional/duplication/LargeAdvisory.php';
    $structuralFailurePath = $project . '/unconventional/duplication/StructuralFailure.php';
    $phpStanFailurePath = $project . '/unconventional/duplication/PhpStanFailure.php';
    $plain = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DuplicationProof;

final class FirstDuplicationProof
{
    public function calculate(): int
    {
        $total = 0;
        $canary = 'DUPLICATION_PRIVATE_CANARY_7b4f';
        $total += 101;
        $total += 102;
        $total += 103;
        $total += 104;
        $total += 105;
        $total += 106;
        $total += 107;
        $total += 108;
        $total += 109;
        $total += 110;
        $total += 111;
        $total += 112;

        return $total + strlen($canary);
    }
}
PHP;
    $decorated = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DuplicationProof;

final class SecondDuplicationProof
{
    public function calculate(): int
    {
        /* Formatting and comments are deliberately different. */
        $total=0;
        $canary =
            'DUPLICATION_PRIVATE_CANARY_7b4f';
        $total /* one */ += 101;
        $total += 102;
        $total += 103;
        $total += 104;
        $total += 105;
        $total += 106;
        $total += 107;
        $total += 108;
        $total += 109;
        $total += 110;
        $total += 111;
        $total += 112;

        return $total +
            strlen($canary);
    }
}
PHP;

    writeFile($firstPath, $plain . "\n");
    writeFile($secondPath, $decorated . "\n");
    writeFile($frameworkPath, $plain . "\n");
    writeFile($dependencyPath, $decorated . "\n");
    writeFile($vcsPath, $plain . "\n");

    try {
        $normal = runProcess($profileCommand, $project, $environment);
        requireSuccess($normal, 'A possible duplication advisory invalidated the consumer.');
        requireStdoutContains(
            $normal,
            'ADVISORY possible application duplication: 1 group (minimum 48 normalized tokens)',
        );
        requireStdoutContains($normal, 'application validity is unaffected');
        requireStdoutContains($normal, 'PASS PHPThis application check');
        $normalAdvisories = advisoryOutput($normal);

        if (
            $normalAdvisories
                !== 'ADVISORY possible application duplication: 1 group (minimum 48 normalized tokens); run `phpthis check --debug` for details; application validity is unaffected'
        ) {
            throw new RuntimeException('The installed normal duplication advisory was not exactly one concise line.');
        }

        foreach (
            [
                '.hidden/duplication/FirstDuplicationProof.php',
                'unconventional/duplication/SecondDuplicationProof.php',
                $project,
                'DUPLICATION_PRIVATE_CANARY_7b4f',
            ] as $privateNormalValue
        ) {
            requireOutputNotContains($normal, $privateNormalValue);
        }

        $debug = runProcess(
            [$project . '/vendor/bin/phpthis', 'check', '--debug'],
            $project,
            $environment,
        );
        requireSuccess($debug, 'The duplication diagnostic mode failed.');
        $advisories = advisoryOutput($debug);

        if (substr_count($advisories, 'ADVISORY duplication group ') !== 1) {
            throw new RuntimeException('The installed checker did not consolidate the copied block into one group.');
        }

        if (substr_count($advisories, 'ADVISORY duplication location 1.') !== 2) {
            throw new RuntimeException('The installed checker did not report exactly two application-owned locations.');
        }

        if (
            preg_match(
                '/^ADVISORY duplication group 1: [0-9]+ normalized tokens across 2 locations$/m',
                $advisories,
            ) !== 1
        ) {
            throw new RuntimeException('Duplication debug output omitted its bounded token and location counts.');
        }

        foreach (
            [
                '/^ADVISORY duplication location 1\.1: "\.hidden\/duplication\/FirstDuplicationProof\.php":[0-9]+(?:-[0-9]+)?$/m',
                '/^ADVISORY duplication location 1\.2: "unconventional\/duplication\/SecondDuplicationProof\.php":[0-9]+(?:-[0-9]+)?$/m',
            ] as $locationPattern
        ) {
            if (preg_match($locationPattern, $advisories) !== 1) {
                throw new RuntimeException('Duplication debug output omitted a bounded application-relative line range.');
            }
        }

        if (str_contains($advisories, $project)) {
            throw new RuntimeException('Duplication debug output disclosed the temporary project absolute path.');
        }

        foreach (
            [
                '".hidden/duplication/FirstDuplicationProof.php"',
                '"unconventional/duplication/SecondDuplicationProof.php"',
            ] as $relativeLocation
        ) {
            if (!str_contains($advisories, $relativeLocation)) {
                throw new RuntimeException("Duplication debug output omitted {$relativeLocation}.");
            }
        }

        foreach (
            [
                'vendor/phpthis/framework/duplication-negative-control.php',
                'vendor/dependency-negative-control/DuplicationProof.php',
                '.git/duplication-negative-control.php',
                'DUPLICATION_PRIVATE_CANARY_7b4f',
            ] as $excludedValue
        ) {
            if (str_contains($advisories, $excludedValue)) {
                throw new RuntimeException("Duplication advisory output disclosed excluded content: {$excludedValue}");
            }
        }

        $complete = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireSuccess($complete, 'A possible duplication advisory stopped the canonical consumer gate.');
        requireStdoutContains($complete, 'ADVISORY possible application duplication: 1 group');
        requireStdoutContains($complete, 'PASS application behavior and front controller');

        $largeAdvisory = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\DuplicationProof;\n\n/*"
            . str_repeat('bounded-advisory-padding-', 1_500)
            . "*/\nfinal class LargeAdvisory {}\n";
        writeFile($largeAdvisoryPath, $largeAdvisory);
        $incomplete = runProcess($profileCommand, $project, $environment);
        requireSuccess($incomplete, 'A bounded incomplete duplication scan invalidated the consumer.');
        requireStdoutContains($incomplete, 'found within an incomplete bounded scan');
        requireStdoutContains($incomplete, 'application validity is unaffected');
        requireStdoutContains($incomplete, 'PASS PHPThis application check');

        $largeStaticFailure = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\DuplicationProof;\n\nfinal class LargeAdvisory\n{\n    public function value(): int\n    {\n        return 'invalid';\n    }\n}\n\n/*"
            . str_repeat('bounded-advisory-padding-', 1_500)
            . "*/\n";
        writeFile($largeAdvisoryPath, $largeStaticFailure);
        $incompleteStaticFailure = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $incompleteStaticFailure,
            'A scanner-skipped oversized application file was also skipped by PHPStan.',
        );
        requireStdoutContains($incompleteStaticFailure, 'found within an incomplete bounded scan');
        requireOutputContains($incompleteStaticFailure, 'return.type');
        unlink($largeAdvisoryPath);

        writeFile($structuralFailurePath, "<?php\n\nclass StructuralFailure {}\n");
        $structuralFailure = runProcess($profileCommand, $project, $environment);
        requireFailure($structuralFailure, 'A duplication advisory masked a structural failure.');
        requireOutputContains($structuralFailure, 'PHT002 unconventional/duplication/StructuralFailure.php:3');
        requireOutputNotContains($structuralFailure, 'ADVISORY possible application duplication');
        unlink($structuralFailurePath);

        $phpStanFailure = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DuplicationProof;

final class PhpStanFailure
{
    public function value(): int
    {
        return 'invalid';
    }
}
PHP;
        writeFile($phpStanFailurePath, $phpStanFailure . "\n");
        $staticFailure = runProcess($profileCommand, $project, $environment);
        requireFailure($staticFailure, 'A duplication advisory masked a PHPStan failure.');
        requireStdoutContains($staticFailure, 'ADVISORY possible application duplication: 1 group');
        requireOutputContains($staticFailure, 'return.type');
        unlink($phpStanFailurePath);
    } finally {
        foreach (
            [
                $firstPath,
                $secondPath,
                $frameworkPath,
                $dependencyPath,
                $vcsPath,
                $largeAdvisoryPath,
                $structuralFailurePath,
                $phpStanFailurePath,
            ] as $path
        ) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (
            [
                $project . '/.hidden',
                $project . '/unconventional',
                $project . '/vendor/dependency-negative-control',
                $project . '/.git',
            ] as $directory
        ) {
            removeDirectory($directory);
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveObservabilityContextIsRequired(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/.ai/observability.md';
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read the consumer observability context control.');
    }

    if (!unlink($path)) {
        throw new RuntimeException('Unable to remove the consumer observability context control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A consumer without observability context unexpectedly passed.');
        requireOutputContains(
            $result,
            'Required application context file is missing: .ai/observability.md.',
        );
    } finally {
        writeFile($path, $contents);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveConfigurationContextIsRequired(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/.ai/configuration.md';
    $sourcePath = $project . '/ConfigurationContextControl.php';
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read the consumer configuration context control.');
    }

    if (!unlink($path)) {
        throw new RuntimeException('Unable to remove the consumer configuration context control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A consumer without configuration context unexpectedly passed.');
        requireOutputContains(
            $result,
            'Required application context file is missing: .ai/configuration.md.',
        );
    } finally {
        writeFile($path, $contents);
    }

    writeFile(
        $sourcePath,
        <<<'PHP'
<?php

declare(strict_types=1);

final readonly class ConfigurationContextValue
{
    public function __construct(public string $value)
    {
    }
}

final class ConfigurationContextControl
{
    public static function fromEnvironment(): ConfigurationContextValue
    {
        $value = \getenv('PHPTHIS_CONFIGURATION_CONTEXT_CONTROL');

        if (
            $value === false
            || preg_match('/\A[a-z][a-z0-9-]{0,15}\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return new ConfigurationContextValue($value);
    }
}

final readonly class ConfigurationContextConsumer
{
    public function __construct(private ConfigurationContextValue $configuration)
    {
    }

    public function configuredValue(): string
    {
        return $this->configuration->value;
    }
}

final class ConfigurationContextComposition
{
    public static function create(): ConfigurationContextConsumer
    {
        return new ConfigurationContextConsumer(
            ConfigurationContextControl::fromEnvironment(),
        );
    }
}
PHP,
    );

    try {
        $notApplicableResult = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $notApplicableResult,
            'Configuration environment access passed while the application context remained not applicable.',
        );
        requireOutputContains(
            $notApplicableResult,
            'Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
        );

        writeFile(
            $path,
            "# Application configuration context\r\n\r\n`NOT_APPLICABLE(CONFIGURATION)`\r\n",
        );
        $crlfNotApplicableResult = runProcess($profileCommand, $project, $environment);
        requireFailure(
            $crlfNotApplicableResult,
            'CRLF configuration context bypassed the not-applicable environment-read check.',
        );
        requireOutputContains(
            $crlfNotApplicableResult,
            'Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
        );

        writeFile(
            $path,
            <<<'MD'
# Application configuration context

- Boundary: `ConfigurationContextControl.php` is the sole process-environment reader.
- Input `PHPTHIS_CONFIGURATION_CONTEXT_CONTROL`: required with no default or fallback; 1 to 16 lowercase ASCII bytes matching `[a-z][a-z0-9-]{0,15}`.
- Factory and type: `ConfigurationContextControl::fromEnvironment()` validates once and returns the final readonly `ConfigurationContextValue`.
- Injection: `ConfigurationContextComposition::create()` visibly calls the environment factory and supplies its concrete value to `ConfigurationContextConsumer::__construct`; the consumer does not receive an environment name or unvalidated scalar.
- Authority: this ordinary application-process input has no migration, administration, or cross-process credential fallback.
- Failure: missing or invalid input raises `InvalidArgumentException` before application-controlled I/O; this correlation fixture performs no I/O.
- Rotation and reload: a fresh process samples the deployment value once; no in-process reload or hidden refresh is claimed.
- Redaction: submitted values are absent from checker diagnostics and this fixture emits no configuration output.
- Evidence: the fixture contains the exact `ConfigurationContextComposition::create()` constructor-injection path, and the installed public checker correlates this complete context with the one canonical environment read while rejecting absent or `NOT_APPLICABLE(CONFIGURATION)` context, including CRLF form.
MD,
        );
        $completedContextResult = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $completedContextResult,
            'A completed configuration context failed the installed public checker.',
        );
    } finally {
        writeFile($path, $contents);

        if (is_file($sourcePath) && !unlink($sourcePath)) {
            throw new RuntimeException('Unable to remove the configuration context control.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveEveryApplicationDirectoryIsChecked(string $project, array $profileCommand, array $environment): void
{
    $paths = [
        'OpenRoot.php',
        'config/OpenConfig.php',
        'bin/OpenBin.php',
        'migrations/OpenMigration.php',
        '.hidden/OpenHidden.php',
        'tmp/OpenTemporary.php',
    ];
    $source = "<?php\n\ndeclare(strict_types=1);\n\nclass OpenClass {}\n";

    foreach ($paths as $relativePath) {
        writeFile($project . '/' . $relativePath, $source);
    }

    $extensionlessPath = 'bin/OpenConsole';
    writeFile($project . '/' . $extensionlessPath, "#!/usr/bin/env php\n" . $source);
    $unsupportedExtensionPath = 'config/OpenInclude.inc';
    writeFile(
        $project . '/' . $unsupportedExtensionPath,
        "<?php\n\ndeclare(strict_types=1);\n\nfinal class IncludeClass {}\n",
    );

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT002 files outside conventional roots unexpectedly passed.');

        foreach ($paths as $relativePath) {
            requireOutputContains($result, "PHT002 {$relativePath}:5");
        }

        requireOutputContains($result, "PHT002 {$extensionlessPath}:6");
        requireOutputContains(
            $result,
            "{$unsupportedExtensionPath} contains PHP source but must use the .php extension",
        );
    } finally {
        foreach ($paths as $relativePath) {
            unlink($project . '/' . $relativePath);
        }

        unlink($project . '/' . $extensionlessPath);
        unlink($project . '/' . $unsupportedExtensionPath);

        foreach (['config', 'bin', 'migrations', '.hidden', 'tmp'] as $directory) {
            rmdir($project . '/' . $directory);
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveValidExtensionlessExecutableIsChecked(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/bin/HealthCommand';
    $source = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace App;

final class HealthCommand
{
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireSuccess($result, 'A valid extensionless PHP executable was rejected.');
        requireOutputContains($result, 'PASS application guardrails: 13 PHP files');
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveMagicMethodsAreRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/src/MagicMethods.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class MagicMethods
{
    public function /* comment */ __isset(string $name): bool
    {
        return $name !== '';
    }

    public function &__get(string $name): mixed
    {
        $value = $name;

        return $value;
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Obscured magic methods unexpectedly passed.');
        requireOutputContains($result, 'defines forbidden magic method __isset');
        requireOutputContains($result, 'defines forbidden magic method __get');
    } finally {
        unlink($path);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDependencyDirectoryIsExcluded(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/vendor/dependency-negative-control/OpenDependencyClass.php';
    writeFile($path, "<?php\n\nclass OpenDependencyClass {}\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireSuccess($result, 'Dependency-owned PHP was incorrectly treated as application source.');
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveMixedCoercionIsRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/unconventional/MixedCoercion.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class MixedCoercion
{
    public function convert(mixed $value): int
    {
        return (int) $value;
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT001 mixed coercion unexpectedly passed.');
        requireOutputContains($result, 'phpthis.pht001');
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDirectPdoConstructionIsRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/src/DirectPdo.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDO as Driver;

final class DirectPdo
{
    public function direct(): PDO
    {
        return new PDO('sqlite::memory:');
    }

    public function aliased(): Driver
    {
        return new Driver('sqlite::memory:');
    }

    public function fullyQualified(): \PDO
    {
        return new \PDO('sqlite::memory:');
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT005 direct PDO construction unexpectedly passed.');

        if (substr_count($result['stdout'] . $result['stderr'], 'phpthis.pht005') !== 3) {
            throw new RuntimeException('Expected literal, aliased, and fully qualified PDO to emit PHT005.');
        }
    } finally {
        unlink($path);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveNativeSessionAccessIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/src/DirectSession.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use function session_destroy as destroy_session;

final class DirectSession
{
    public function start(): void
    {
        session_start();
        destroy_session();
        call_user_func('session_write_close');
        $_SESSION['identity_id'] = 1;
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Direct native session access unexpectedly passed.');
        requireOutputContains($result, 'calls native session function session_start');
        requireOutputContains($result, 'imports native session function session_destroy');
        requireOutputContains($result, 'references native session function session_write_close indirectly');
        requireOutputContains($result, 'reads a PHP superglobal outside PHPThis\\Session\\SessionLifecycle');
    } finally {
        unlink($path);
    }

    $frontControllerPath = $project . '/public/index.php';
    $originalFrontController = file_get_contents($frontControllerPath);

    if (!is_string($originalFrontController)) {
        throw new RuntimeException('Unable to read the consumer front controller session control.');
    }

    $frontControllerSource = <<<'PHP'
<?php

declare(strict_types=1);

session_start();
$_SESSION['identity_id'] = 1;
PHP;
    writeFile($frontControllerPath, $frontControllerSource . "\n");

    try {
        $frontControllerResult = runProcess($profileCommand, $project, $environment);
        requireFailure($frontControllerResult, 'Native session access in public/index.php unexpectedly passed.');
        requireOutputContains($frontControllerResult, 'calls native session function session_start');
        requireOutputContains(
            $frontControllerResult,
            'public/index.php:6 reads a PHP superglobal outside PHPThis\\Session\\SessionLifecycle',
        );
    } finally {
        if (file_put_contents($frontControllerPath, $originalFrontController, LOCK_EX) !== strlen($originalFrontController)) {
            throw new RuntimeException('Unable to restore the consumer front controller session control.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveEnvironmentAccessIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $firstPath = $project . '/src/EnvironmentOne.php';
    $secondPath = $project . '/src/EnvironmentTwo.php';
    $boundarySource = static fn (string $class, string $key): string => sprintf(
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class %s
{
    public static function read(): string|false
    {
        return \getenv('%s');
    }
}
PHP,
        $class,
        $key,
    );
    writeFile($firstPath, $boundarySource('EnvironmentOne', 'APP_FIRST_VALUE') . "\n");
    writeFile($secondPath, $boundarySource('EnvironmentTwo', 'APP_SECOND_VALUE') . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT007 process-environment reads in two files unexpectedly passed.');
        requireExactFailureLines(
            $result,
            [
                'FAIL PHT007 src/EnvironmentOne.php:11 reads process environment in more than one application-owned PHP file; centralize every \getenv call in one configuration boundary.',
                'FAIL PHT007 src/EnvironmentTwo.php:11 reads process environment in more than one application-owned PHP file; centralize every \getenv call in one configuration boundary.',
                'FAIL Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
            ],
            'Installed PHT007 scattered-boundary diagnostics changed.',
        );
    } finally {
        unlink($firstPath);
        unlink($secondPath);
    }

    $invalidPath = $project . '/src/InvalidEnvironmentAccess.php';
    $invalidSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use function getenv as importedGetenv;
use function putenv;

$key = 'APP_KEY';
getenv('APP_KEY');
\GeTeNv('APP_KEY');
App\getenv('APP_KEY');
\getenv();
\getenv($key);
\getenv('APP_KEY', true);
\getenv(name: 'APP_KEY');
\getenv(...['APP_KEY']);
$fromEnvironment = $_ENV['APP_KEY'];
$fromServer = $_SERVER['APP_KEY'];
$filtered = filter_input(INPUT_ENV, 'APP_KEY');
\putenv('APP_KEY=value');
\apache_getenv('APP_KEY');
\apache_setenv('APP_KEY', 'value');
$reader = "get\x65nv";
$reader('APP_KEY');
$filteredIndirect = filter_input(constant("INPUT_\x45NV"), 'APP_KEY');
$directLiteral = ('getenv')('APP_KEY');
$mapped = array_map('getenv', ['APP_KEY']);
$namedMapped = array_map(callback: 'getenv', arrays: ['APP_KEY']);
$reduced = array_reduce([], 'getenv');
register_shutdown_function('putenv', 'APP_KEY=value');
$called = call_user_func(('apache_getenv'), 'APP_KEY');
$closure = \Closure::fromCallable('getenv');
$namedInput = filter_input(constant(name: 'INPUT_ENV'), 'APP_KEY');
$parenthesizedInput = filter_input(constant(('INPUT_ENV')), 'APP_KEY');
$harmless = 'getenv';
PHP;
    writeFile($invalidPath, $invalidSource . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT007 alternate environment access unexpectedly passed.');
        requireExactFailureLines(
            $result,
            [
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:7 imports environment function getenv; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:8 imports environment function putenv; use direct \getenv calls only.',
                "FAIL PHT007 src/InvalidEnvironmentAccess.php:11 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
                "FAIL PHT007 src/InvalidEnvironmentAccess.php:12 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
                "FAIL PHT007 src/InvalidEnvironmentAccess.php:13 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:14 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:15 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:16 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:17 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:18 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:19 reads $_ENV; read exact keys with \getenv in the single application configuration boundary.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:20 indexes $_SERVER; pass the HTTP transport array unchanged or read configuration with \getenv in the single configuration boundary.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:21 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:22 calls environment function putenv; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:23 calls environment function apache_getenv; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:24 calls environment function apache_setenv; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:25 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:27 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:28 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:29 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:30 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:31 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:32 references environment function putenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:33 references environment function apache_getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:34 references environment function getenv indirectly; use direct \getenv calls only.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:35 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
                'FAIL PHT007 src/InvalidEnvironmentAccess.php:36 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
                'FAIL src/InvalidEnvironmentAccess.php:20 reads a PHP superglobal outside public/index.php.',
                'FAIL Application configuration context records NOT_APPLICABLE(CONFIGURATION) while application-owned PHP reads process environment; replace the marker with the explicit configuration boundary contract.',
            ],
            'Installed PHT007 alternate-access diagnostics changed.',
        );
    } finally {
        unlink($invalidPath);
    }

    $frontControllerPath = $project . '/public/index.php';
    $frontController = file_get_contents($frontControllerPath);

    if (!is_string($frontController)) {
        throw new RuntimeException('Unable to read the installed front-controller environment control.');
    }

    writeFile(
        $frontControllerPath,
        <<<'PHP'
<?php

declare(strict_types=1);

$server = $_SERVER;
Configuration::fromServer($_SERVER);
$configurationReader->handle($_SERVER, $_GET, $_POST, $_FILES);
PHP,
    );

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Bare front-controller $_SERVER aliases unexpectedly passed PHT007.');
        requireExactFailureLines(
            $result,
            [
                'FAIL PHT007 public/index.php:5 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
                'FAIL PHT007 public/index.php:6 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
                'FAIL PHT007 public/index.php:7 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
            ],
            'Installed PHT007 bare-server diagnostics changed.',
        );
    } finally {
        writeFile($frontControllerPath, $frontController);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDynamicSqlIsRejected(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/src/DynamicSql.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PHPThis\Database\Connection;

final class DynamicSql
{
    public function run(Connection $connection, string $sql): void
    {
        $connection->selectAllRows($sql);
    }
}
PHP;
    writeFile($path, $source . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT006 dynamic Connection SQL unexpectedly passed.');

        if (substr_count($result['stdout'] . $result['stderr'], 'phpthis.pht006') !== 1) {
            throw new RuntimeException('Expected dynamic Connection SQL to emit exactly one PHT006 finding.');
        }
    } finally {
        unlink($path);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveConfigurationCannotReplaceProfile(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/phpstan.neon';
    writeFile($path, "parameters:\n    level: 0\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A consumer PHPStan configuration unexpectedly replaced the installed profile.');
        requireOutputContains($result, 'PHT004');
    } finally {
        unlink($path);
    }

    $target = $project . '/alternate-analysis.neon';
    writeFile($target, "parameters:\n    level: 0\n");

    if (!symlink($target, $path)) {
        throw new RuntimeException('Unable to create the PHPStan configuration symlink control.');
    }

    try {
        $symlinkResult = runProcess($profileCommand, $project, $environment);
        requireFailure($symlinkResult, 'A symlinked consumer PHPStan configuration unexpectedly passed.');
        requireOutputContains($symlinkResult, 'PHT004 phpstan.neon is forbidden');
    } finally {
        unlink($path);
        unlink($target);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveBaselinesAndInlineIgnoresAreRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    foreach (
        ['phpstan.project.neon', 'phpstanLocal.neon', 'phpstan-baseline.neon.dist', 'phpstanbaseline.php']
        as $basename
    ) {
        $configuration = $project . '/' . $basename;
        writeFile($configuration, "parameters:\n    ignoreErrors: []\n");

        try {
            $configurationResult = runProcess($profileCommand, $project, $environment);
            requireFailure($configurationResult, "PHPStan artifact {$basename} unexpectedly passed.");
            requireOutputContains($configurationResult, "PHT004 {$basename} is forbidden");
        } finally {
            unlink($configuration);
        }
    }

    $ignoredPath = $project . '/src/IgnoredFinding.php';
    $ignoredSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

/** @phpstan-ignore class.name */
final class IgnoredFinding
{
    /** @phpstan-ignore-next-line */
    public function value(): int
    {
        // @phpstan-ignore-line
        return 1;
    }
}
PHP;
    writeFile($ignoredPath, $ignoredSource . "\n");

    try {
        $ignoreResult = runProcess($profileCommand, $project, $environment);
        requireFailure($ignoreResult, 'Inline PHPStan suppressions unexpectedly passed.');

        foreach ([7, 10, 13] as $line) {
            requireOutputContains($ignoreResult, "PHT004 src/IgnoredFinding.php:{$line}");
        }

        if (substr_count($ignoreResult['stdout'] . $ignoreResult['stderr'], 'PHT004') !== 3) {
            throw new RuntimeException('Expected every inline PHPStan suppression form to produce PHT004.');
        }
    } finally {
        unlink($ignoredPath);
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveComposerGateCannotDrift(
    string $project,
    string $composerBinary,
    array $profileCommand,
    array $environment,
): void
{
    $composerPath = $project . '/composer.json';
    $original = file_get_contents($composerPath);

    if (!is_string($original)) {
        throw new RuntimeException('Unable to read the consumer Composer gate.');
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The consumer Composer scripts are missing.');
    }

    $scripts['profile'] = 'php -r "exit(0);"';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A weakened Composer profile command unexpectedly passed.');
        requireOutputContains($result, 'scripts.profile must be exactly `phpthis check`');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer Composer gate.');
        }
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['test'] = '';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $testResult = runProcess($profileCommand, $project, $environment);
        requireFailure($testResult, 'A missing application behavior-test command unexpectedly passed.');
        requireOutputContains($testResult, "scripts.test must execute the application's automated behavior tests");
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer behavior-test command.');
        }
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['check'] = ['@profile'];
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $checkResult = runProcess($profileCommand, $project, $environment);
        requireFailure($checkResult, 'A complete gate without the application behavior-test stage unexpectedly passed.');
        requireOutputContains($checkResult, 'scripts.check must be exactly [`@profile`, `@test`]');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the complete consumer gate.');
        }
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['test'] = 'php -r "fwrite(STDERR, \'PHPTHIS_BEHAVIOR_STAGE_FAILED\'); exit(23);"';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $behaviorFailureResult = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireFailure($behaviorFailureResult, 'A failing application behavior-test stage did not fail the complete gate.');
        requireOutputContains($behaviorFailureResult, 'PASS PHPThis application check');
        requireOutputContains($behaviorFailureResult, 'PHPTHIS_BEHAVIOR_STAGE_FAILED');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer behavior-test stage.');
        }
    }

    $checksDirectory = $project . '/checks';
    $originalRunner = $project . '/tests/run.php';
    $movedRunner = $checksDirectory . '/behavior.php';

    if (!mkdir($checksDirectory, 0700)) {
        throw new RuntimeException('Unable to create the alternate behavior-test directory.');
    }

    if (!rename($originalRunner, $movedRunner)) {
        throw new RuntimeException('Unable to move the behavior-test runner for the path-neutrality control.');
    }

    $composer = jsonFile($composerPath);
    $scripts = $composer['scripts'] ?? null;

    if (!is_array($scripts)) {
        throw new RuntimeException('The restored consumer Composer scripts are missing.');
    }

    $scripts['test'] = 'php checks/behavior.php';
    $composer['scripts'] = $scripts;
    writeJson($composerPath, $composer);

    try {
        $alternatePathResult = runProcess(
            composerCommand($composerBinary, ['check']),
            $project,
            $environment,
        );
        requireSuccess($alternatePathResult, 'An application-owned behavior-test path unexpectedly failed.');
        requireOutputContains($alternatePathResult, 'PASS application behavior and front controller');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer test path.');
        }

        if (!rename($movedRunner, $originalRunner)) {
            throw new RuntimeException('Unable to restore the original behavior-test runner.');
        }

        if (!rmdir($checksDirectory)) {
            throw new RuntimeException('Unable to remove the alternate behavior-test directory.');
        }
    }

    $composer = jsonFile($composerPath);
    $requireDev = $composer['require-dev'] ?? null;

    if (!is_array($requireDev)) {
        throw new RuntimeException('The consumer analysis dependencies are missing.');
    }

    $requireDev['phpstan/phpstan'] = '*';
    $composer['require-dev'] = $requireDev;
    writeJson($composerPath, $composer);

    try {
        $dependencyResult = runProcess($profileCommand, $project, $environment);
        requireFailure($dependencyResult, 'A floating PHPStan constraint unexpectedly passed.');
        requireOutputContains($dependencyResult, 'must require-dev phpstan/phpstan at `^2.1`');
    } finally {
        if (file_put_contents($composerPath, $original, LOCK_EX) !== strlen($original)) {
            throw new RuntimeException('Unable to restore the consumer analysis dependencies.');
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveSymlinkedSourceIsRejected(
    string $workspace,
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $outside = $workspace . '/outside-source';

    if (!mkdir($outside, 0700)) {
        throw new RuntimeException('Unable to create the symlink negative-control target.');
    }

    writeFile($outside . '/External.php', "<?php\n\ndeclare(strict_types=1);\n");
    $link = $project . '/linked-source';

    if (!symlink($outside, $link)) {
        throw new RuntimeException('Unable to create the symlink negative control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A symlinked source directory unexpectedly passed.');
        requireOutputContains($result, 'linked-source is a symlink directory');
    } finally {
        unlink($link);
        removeDirectory($outside);
    }

    $outsideExecutable = $workspace . '/outside-command';
    writeFile(
        $outsideExecutable,
        "#!/usr/bin/env php\n<?php\n\ndeclare(strict_types=1);\n\nnamespace External;\n\nfinal class Command {}\n",
    );
    $binDirectory = $project . '/bin';

    if (!mkdir($binDirectory, 0700)) {
        throw new RuntimeException('Unable to create the executable symlink negative-control directory.');
    }

    $executableLink = $binDirectory . '/linked-command';

    if (!symlink($outsideExecutable, $executableLink)) {
        throw new RuntimeException('Unable to create the executable symlink negative control.');
    }

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A symlinked extensionless PHP executable unexpectedly passed.');
        requireOutputContains($result, 'bin/linked-command is a symlink file');
    } finally {
        unlink($executableLink);
        rmdir($binDirectory);
        unlink($outsideExecutable);
    }
}

function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory {$directory}.");
    }

    if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
        throw new RuntimeException("Unable to write file {$path}.");
    }
}

function copyDirectory(string $source, string $destination): void
{
    if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create directory {$destination}.");
    }

    $entries = scandir($source);

    if (!is_array($entries)) {
        throw new RuntimeException("Unable to read directory {$source}.");
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $sourcePath = $source . '/' . $entry;
        $destinationPath = $destination . '/' . $entry;

        if (is_dir($sourcePath) && !is_link($sourcePath)) {
            copyDirectory($sourcePath, $destinationPath);
            continue;
        }

        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Unable to copy {$sourcePath}.");
        }
    }
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory) || is_link($directory)) {
        if (is_link($directory)) {
            unlink($directory);
        }

        return;
    }

    $entries = scandir($directory);

    if (!is_array($entries)) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . '/' . $entry;

        if (is_dir($path) && !is_link($path)) {
            removeDirectory($path);
            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}
