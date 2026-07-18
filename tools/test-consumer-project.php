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
    $profileResult = runProcess($profileCommand, $project, $environment);
    requireSuccess($profileResult, 'The clean skeleton failed the installed profile check.');
    requireOutputContains($profileResult, 'PASS PHPThis application check');
    requireOutputNotContains($profileResult, $project . '/bootstrap.php');

    if (!is_file($project . '/vendor/.phpthis/phpstan/resultCache.php')) {
        throw new RuntimeException('The normal application check did not create its persistent PHPStan cache.');
    }

    $debugResult = runProcess(
        [$project . '/vendor/bin/phpthis', 'check', '--debug'],
        $project,
        $environment,
    );
    requireSuccess($debugResult, 'The explicit diagnostic profile check failed.');
    requireOutputContains($debugResult, $project . '/bootstrap.php');

    $completeResult = runProcess(
        composerCommand($composerBinary, ['check']),
        $project,
        $environment,
    );
    requireSuccess($completeResult, 'The clean skeleton failed its complete application check.');
    requireOutputContains($completeResult, 'PASS application behavior and front controller');

    proveEveryApplicationDirectoryIsChecked($project, $profileCommand, $environment);
    proveValidExtensionlessExecutableIsChecked($project, $profileCommand, $environment);
    proveMagicMethodsAreRejected($project, $profileCommand, $environment);
    proveDependencyDirectoryIsExcluded($project, $profileCommand, $environment);
    proveMixedCoercionIsRejected($project, $profileCommand, $environment);
    proveDirectPdoConstructionIsRejected($project, $profileCommand, $environment);
    proveDynamicSqlIsRejected($project, $profileCommand, $environment);
    proveConfigurationCannotReplaceProfile($project, $profileCommand, $environment);
    proveBaselinesAndInlineIgnoresAreRejected($project, $profileCommand, $environment);
    proveComposerGateCannotDrift($project, $profileCommand, $environment);
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
        requireOutputContains($result, 'PASS application guardrails: 7 PHP files');
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
function proveComposerGateCannotDrift(string $project, array $profileCommand, array $environment): void
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
