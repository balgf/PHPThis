<?php

declare(strict_types=1);

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
    $vcsPrefixedPath = $project . '/.git/prefixed-negative-control.inc';
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

    try {
        writeFile($firstPath, $plain . "\n");
        writeFile($secondPath, $decorated . "\n");
        writeFile($frameworkPath, $plain . "\n");
        writeFile($dependencyPath, $decorated . "\n");
        writeFile($vcsPath, $plain . "\n");
        writeFile($vcsPrefixedPath, "\xEF\xBB\xBF \n<?PHP\n\necho 'excluded';\n");

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
                '.git/prefixed-negative-control.inc',
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
                $vcsPrefixedPath,
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
    $extensionlessPath = 'bin/OpenConsole';
    $diagnosticCanary = 'PHPTHIS_PREFIX_DIAGNOSTIC_PRIVATE_CANARY';
    $executionSentinelPath = $project . '/source-prefix-discovery-executed';
    $launcherPrefix = "#!/usr/bin/env php\n";
    $unsupportedExtensionSources = [
        'config/OpenInclude.inc' => "<?php\n\n// {$diagnosticCanary}\ndeclare(strict_types=1);\n",
        'config/OpenBomInclude.inc' => "\xEF\xBB\xBF<?php\n\nfile_put_contents(dirname(__DIR__)"
            . " . '/source-prefix-discovery-executed', '{$diagnosticCanary}');\n",
        '.hidden/OpenBomWhitespaceInclude.inc' => "\xEF\xBB\xBF \t\r\n<?php\n\ndeclare(strict_types=1);\n",
        'config/OpenWhitespaceInclude.inc' => " \t\r\n\v\f<?php\n\ndeclare(strict_types=1);\n",
        'config/OpenUppercaseInclude.inc' => "<?PHP\n\ndeclare(strict_types=1);\n",
        'config/OpenShortEchoInclude.inc' => "<?= 'executable';\n",
        'config/OpenShebangWhitespaceInclude.inc' => $launcherPrefix
            . "\xEF\xBB\xBF \t\r\n<?PHP\n\ndeclare(strict_types=1);\n",
        'config/OpenBoundaryInclude.inc' => str_repeat(' ', 4091)
            . "<?php echo 'boundary';\n",
        'config/OpenLongPreamble.inc' => str_repeat(' ', 4096)
            . "<?php\n\ndeclare(strict_types=1);\n",
        'config/OpenShebangLongPreamble.inc' => $launcherPrefix
            . str_repeat(' ', 4096 - strlen($launcherPrefix))
            . "<?php\n\ndeclare(strict_types=1);\n",
        'config/OpenPartialPrefix.inc' => str_repeat(' ', 4093) . '<?ph' . 'p',
        'config/OpenWhitespaceOnlyAmbiguous.inc' => str_repeat(' ', 4097),
        'config/OpenNonPhpLookahead.inc' => str_repeat(' ', 4091) . '<?phpx',
    ];
    $canonicalUnsupportedPath = 'config/OpenInclude.inc';
    $detectedUnsupportedPaths = [
        'config/OpenBomInclude.inc',
        '.hidden/OpenBomWhitespaceInclude.inc',
        'config/OpenWhitespaceInclude.inc',
        'config/OpenUppercaseInclude.inc',
        'config/OpenShortEchoInclude.inc',
        'config/OpenShebangWhitespaceInclude.inc',
        'config/OpenBoundaryInclude.inc',
    ];
    $nonCanonicalExtensionlessPath = 'bin/OpenWhitespaceConsole';
    $nonCanonicalLauncherPath = 'bin/OpenWhitespaceLauncher';
    $unreadablePath = 'config/OpenUnreadableAsset.txt';
    $fixturePaths = [
        ...$paths,
        $extensionlessPath,
        ...array_keys($unsupportedExtensionSources),
        $nonCanonicalExtensionlessPath,
        $nonCanonicalLauncherPath,
        $unreadablePath,
    ];

    try {
        foreach ($paths as $relativePath) {
            writeFile($project . '/' . $relativePath, $source);
        }

        writeFile($project . '/' . $extensionlessPath, $launcherPrefix . $source);

        foreach ($unsupportedExtensionSources as $relativePath => $unsupportedSource) {
            writeFile($project . '/' . $relativePath, $unsupportedSource);
        }

        writeFile(
            $project . '/' . $nonCanonicalExtensionlessPath,
            " \n<?php\n\ndeclare(strict_types=1);\n",
        );
        writeFile(
            $project . '/' . $nonCanonicalLauncherPath,
            $launcherPrefix . " \n<?php\n\ndeclare(strict_types=1);\n",
        );
        writeFile($project . '/' . $unreadablePath, "ordinary unreadable asset\n");

        if (!chmod($project . '/' . $unreadablePath, 0000)) {
            throw new RuntimeException('Unable to make the source-prefix control unreadable.');
        }

        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT002 files outside conventional roots unexpectedly passed.');

        foreach ($paths as $relativePath) {
            requireOutputContains($result, "PHT002 {$relativePath}:5");
        }

        requireOutputContains($result, "PHT002 {$extensionlessPath}:6");
        requireOutputContains(
            $result,
            "{$canonicalUnsupportedPath} contains PHP source but must use the .php extension or no extension for an executable.",
        );
        foreach ($detectedUnsupportedPaths as $relativePath) {
            requireOutputContains(
                $result,
                "{$relativePath} contains PHP source but must use the .php extension; remove the extension only after changing to an exact canonical byte-zero prefix.",
            );
        }

        requireOutputContains(
            $result,
            'config/OpenLongPreamble.inc has an ambiguous PHP-source preamble at the 4096-byte inspection limit',
        );
        requireOutputContains(
            $result,
            'config/OpenShebangLongPreamble.inc has an ambiguous PHP-source preamble at the 4096-byte inspection limit',
        );
        requireOutputContains(
            $result,
            'config/OpenPartialPrefix.inc has an ambiguous PHP-source preamble at the 4096-byte inspection limit',
        );
        requireOutputContains(
            $result,
            'config/OpenWhitespaceOnlyAmbiguous.inc has an ambiguous PHP-source preamble at the 4096-byte inspection limit',
        );
        requireOutputNotContains($result, 'config/OpenNonPhpLookahead.inc');
        requireOutputNotContains($result, $diagnosticCanary);
        requireOutputContains(
            $result,
            "{$nonCanonicalExtensionlessPath} contains PHP source but an extensionless executable must begin exactly with <?php",
        );
        requireOutputContains(
            $result,
            "{$nonCanonicalLauncherPath} contains PHP source but an extensionless executable must begin exactly with <?php",
        );
        requireOutputContains(
            $result,
            "Cannot inspect {$unreadablePath} for an executable PHP prefix.",
        );

        if (is_file($executionSentinelPath)) {
            throw new RuntimeException('Application source-prefix discovery executed an unsupported-suffix file.');
        }
    } finally {
        $unreadableFullPath = $project . '/' . $unreadablePath;

        if (is_file($unreadableFullPath) && !chmod($unreadableFullPath, 0600)) {
            throw new RuntimeException('Unable to restore the source-prefix control permissions.');
        }

        foreach ($fixturePaths as $relativePath) {
            $fixturePath = $project . '/' . $relativePath;

            if (is_file($fixturePath) && !unlink($fixturePath)) {
                throw new RuntimeException("Unable to remove source-prefix fixture {$relativePath}.");
            }
        }

        if (is_file($executionSentinelPath)) {
            unlink($executionSentinelPath);
        }

        foreach (['config', 'bin', 'migrations', '.hidden', 'tmp'] as $directory) {
            $fixtureDirectory = $project . '/' . $directory;

            if (is_dir($fixtureDirectory) && !rmdir($fixtureDirectory)) {
                throw new RuntimeException("Unable to remove source-prefix fixture directory {$directory}.");
            }
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
    $directPath = $project . '/bin/DirectHealthCommand';
    $documentationPath = $project . '/docs/PHPSourceExample.md';
    $boundaryNonSourcePath = $project . '/docs/BoundaryNonSource.txt';
    $executionSentinelPath = $project . '/source-discovery-executed';
    $sourceCanary = 'PHPTHIS_SOURCE_DISCOVERY_PRIVATE_CANARY';
    $source = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace App;

final class HealthCommand
{
}
PHP;

    try {
        writeFile($path, $source . "\n");
        writeFile(
            $directPath,
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace App;\n\nfile_put_contents(dirname(__DIR__) . '/source-discovery-executed', '"
                . $sourceCanary
                . "');\n\nfinal class DirectHealthCommand {}\n",
        );
        writeFile(
            $documentationPath,
            "# PHP source example\n\n```php\n<?php\n\necho 'documentation only';\n```\n",
        );
        writeFile($boundaryNonSourcePath, str_repeat(' ', 4096));

        $result = runProcess($profileCommand, $project, $environment);
        requireSuccess($result, 'Valid extensionless PHP executables or a Markdown example were rejected.');
        requireOutputContains($result, 'PASS application guardrails: 15 PHP files');
        requireOutputNotContains($result, $sourceCanary);

        if (is_file($executionSentinelPath)) {
            throw new RuntimeException('Application source discovery executed a candidate source file.');
        }
    } finally {
        foreach ([$path, $directPath, $documentationPath, $boundaryNonSourcePath] as $fixturePath) {
            if (is_file($fixturePath) && !unlink($fixturePath)) {
                throw new RuntimeException('Unable to remove a valid source-discovery fixture.');
            }
        }

        if (is_file($executionSentinelPath)) {
            unlink($executionSentinelPath);
        }

        if (is_dir(dirname($path)) && !rmdir(dirname($path))) {
            throw new RuntimeException('Unable to remove the valid source-discovery fixture directory.');
        }
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
function proveEvalIdentifiersAreAllowedAndLanguageConstructIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $methodPath = $project . '/src/EvalMethodControl.php';
    $methodSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class InstanceEvalMethodControl
{
    public function /* declaration comment */ EvAl(string $value): string
    {
        return $value;
    }
}

final class StaticEvalMethodControl
{
    public static function EVAL(string $value): string
    {
        return $value;
    }
}

final class EvalConstantControl
{
    public const string eval = 'constant';
}

enum EvalCaseControl: string
{
    case eval = 'case';
}

trait EvalAliasSource
{
    public function original(): string
    {
        return 'alias';
    }
}

final class EvalAliasControl
{
    use EvalAliasSource {
        original as eval;
    }
}

function acceptNamedEval(string $eval): string
{
    return $eval;
}

final class EvalNamedConstructorControl
{
    public function __construct(public string $eval) {}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class EvalNamedAttributeControl
{
    public function __construct(public string $eval) {}
}

#[EvalNamedAttributeControl(eval: 'attribute')]
final class EvalAttributedControl
{
}

/** @return array{string, ?string, string, string, EvalCaseControl, string, string, string} */
function evalMethodControl(?InstanceEvalMethodControl $optional): array
{
    $instance = new InstanceEvalMethodControl();
    $alias = new EvalAliasControl();
    $constructed = new EvalNamedConstructorControl(eval: 'constructor');

    return [
        $instance -> /* instance comment */ EvAl('instance'),
        $optional ?-> /* nullsafe comment */ EvAl('nullsafe'),
        StaticEvalMethodControl :: /* static comment */ EVAL('static'),
        EvalConstantControl::eval,
        EvalCaseControl::eval,
        $alias->eval(),
        acceptNamedEval(eval: 'function'),
        $constructed->eval,
    ];
}
PHP;
    $constructPath = $project . '/src/EvalLanguageConstructControl.php';
    $constructSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class EvalLanguageConstructControl
{
    public function run(string $source): mixed
    {
        return EVAL /* construct comment */ ($source);
    }
}
PHP;
    writeFile($methodPath, $methodSource . "\n");

    try {
        $methodResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($methodResult, 'Legal identifiers named eval unexpectedly failed.');
        requireOutputContains($methodResult, 'PASS PHPThis application check');

        writeFile($constructPath, $constructSource . "\n");
        $constructResult = runProcess($profileCommand, $project, $environment);
        requireFailure($constructResult, 'The eval language construct unexpectedly passed.');
        requireOutputContains(
            $constructResult,
            'src/EvalLanguageConstructControl.php:11 uses eval.',
        );
    } finally {
        if (is_file($constructPath)) {
            unlink($constructPath);
        }

        if (is_file($methodPath)) {
            unlink($methodPath);
        }
    }
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveDependencyDirectoryIsExcluded(string $project, array $profileCommand, array $environment): void
{
    $path = $project . '/vendor/dependency-negative-control/OpenDependencyClass.php';
    $prefixedPath = $project . '/vendor/dependency-negative-control/OpenDependencyInclude.inc';
    $configuredEntryPath = $project . '/dependency-entry';
    $aliasPath = $project . '/dependency-alias';
    $composerPath = $project . '/composer.json';
    $originalComposer = file_get_contents($composerPath);

    if (!is_string($originalComposer)) {
        throw new RuntimeException('Unable to preserve composer.json for the dependency-exclusion proof.');
    }

    try {
        writeFile($path, "<?php\n\nclass OpenDependencyClass {}\n");
        writeFile($prefixedPath, "\xEF\xBB\xBF \n<?PHP\n\nclass OpenDependencyInclude {}\n");

        $result = runProcess($profileCommand, $project, $environment);
        requireSuccess($result, 'Dependency-owned PHP was incorrectly treated as application source.');
        requireOutputNotContains($result, 'vendor/dependency-negative-control/OpenDependencyInclude.inc');

        $composer = jsonFile($composerPath);
        $config = $composer['config'] ?? [];

        if (!is_array($config)) {
            throw new RuntimeException('The dependency-exclusion proof requires object-shaped Composer config.');
        }

        $vendorStatus = @stat($project . '/vendor');
        $caseVariantStatus = @stat($project . '/VENDOR');
        $vendorDevice = is_array($vendorStatus) ? $vendorStatus['dev'] : null;
        $vendorInode = is_array($vendorStatus) ? $vendorStatus['ino'] : null;
        $caseVariantDevice = is_array($caseVariantStatus) ? $caseVariantStatus['dev'] : null;
        $caseVariantInode = is_array($caseVariantStatus) ? $caseVariantStatus['ino'] : null;

        if (
            is_int($vendorDevice)
            && is_int($vendorInode)
            && $vendorDevice === $caseVariantDevice
            && $vendorInode === $caseVariantInode
        ) {
            $caseVariantComposer = $composer;
            $caseVariantConfig = $config;
            $caseVariantConfig['vendor-dir'] = 'VENDOR';
            $caseVariantComposer['config'] = $caseVariantConfig;
            writeJson($composerPath, $caseVariantComposer);

            $caseVariant = runProcess($profileCommand, $project, $environment);
            requireSuccess(
                $caseVariant,
                'A configured dependency-directory case variant was treated as application source.',
            );
        }

        if (!symlink($project . '/vendor', $configuredEntryPath)) {
            throw new RuntimeException('Unable to create the configured dependency-entry symlink control.');
        }

        $config['vendor-dir'] = 'dependency-entry';
        $composer['config'] = $config;
        writeJson($composerPath, $composer);

        $configured = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $configured,
            'A configured dependency symlink or its physical in-tree target was treated as application source.',
        );
        requireOutputNotContains(
            $configured,
            'vendor/dependency-negative-control/OpenDependencyInclude.inc',
        );

        if (!symlink($project . '/vendor', $aliasPath)) {
            throw new RuntimeException('Unable to create the dependency-alias symlink negative control.');
        }

        $aliased = runProcess($profileCommand, $project, $environment);
        requireFailure($aliased, 'An application symlink alias to the dependency directory unexpectedly passed.');
        requireOutputContains(
            $aliased,
            'dependency-alias is a symlink; application checks do not follow symlinks',
        );
        requireOutputNotContains($aliased, 'dependency-alias/dependency-negative-control/OpenDependencyClass.php');
    } finally {
        if (is_link($aliasPath) && !unlink($aliasPath)) {
            throw new RuntimeException('Unable to remove the dependency-alias symlink negative control.');
        }

        if (is_link($configuredEntryPath) && !unlink($configuredEntryPath)) {
            throw new RuntimeException('Unable to remove the configured dependency-entry symlink control.');
        }

        if (
            file_get_contents($composerPath) !== $originalComposer
            && file_put_contents($composerPath, $originalComposer, LOCK_EX) !== strlen($originalComposer)
        ) {
            throw new RuntimeException('Unable to restore composer.json after the dependency-exclusion proof.');
        }

        foreach ([$path, $prefixedPath] as $fixturePath) {
            if (is_file($fixturePath) && !unlink($fixturePath)) {
                throw new RuntimeException('Unable to remove a dependency-exclusion fixture.');
            }
        }

        if (is_dir(dirname($path)) && !rmdir(dirname($path))) {
            throw new RuntimeException('Unable to remove the dependency-exclusion fixture directory.');
        }
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
function proveRepeatedSqlPlaceholderIsRejected(
    string $project,
    array $profileCommand,
    array $environment,
): void {
    $path = $project . '/src/RepeatedSqlPlaceholder.php';
    $repeatedSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PHPThis\Database\Connection;

final class RepeatedSqlPlaceholder
{
    public function run(Connection $connection, int $value): void
    {
        $connection->selectOneRow(
            'SELECT :value AS first_value, :value AS second_value',
            ['value' => $value],
        );
    }
}
PHP;
    writeFile($path, $repeatedSource . "\n");

    try {
        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'PHT008 repeated Connection SQL placeholder unexpectedly passed.');
        $output = $result['stdout'] . $result['stderr'];
        $normalizedOutput = preg_replace('/\s+/', ' ', $output);

        if (!is_string($normalizedOutput)) {
            throw new RuntimeException('Unable to normalize the installed PHT008 diagnostic.');
        }

        if (substr_count($output, 'phpthis.pht008') !== 1) {
            throw new RuntimeException('Expected repeated Connection SQL placeholder to emit exactly one PHT008 finding.');
        }

        $pht008Message = '[PHT008] Connection SQL must use a distinct named placeholder for each occurrence; '
            . 'rename repeated placeholders and bind each value separately.';
        $diagnosticCount = substr_count($normalizedOutput, $pht008Message);
        $pathCount = substr_count($output, 'src/RepeatedSqlPlaceholder.php');
        $tableLineCount = preg_match_all('/(?:\A|\R)\s*13\s+\[PHT008\]/', $output);
        $githubAnnotationCount = preg_match_all(
            '/(?:\A|\R)::error file=(?:[^\r\n]*\/)?src\/RepeatedSqlPlaceholder\.php,line=13,col=0::'
                . preg_quote($pht008Message, '/')
                . '(?=\R|\z)/',
            $output,
        );
        $plainRendererIsExact = $diagnosticCount === 1
            && $pathCount === 1
            && $githubAnnotationCount === 0;
        $githubRendererIsExact = $diagnosticCount === 2
            && $pathCount === 2
            && $githubAnnotationCount === 1;

        if (
            $tableLineCount !== 1
            || (!$plainRendererIsExact && !$githubRendererIsExact)
        ) {
            throw new RuntimeException('Installed PHT008 direct-call diagnostic changed.');
        }

        if (str_contains($output, 'phpthis.pht006')) {
            throw new RuntimeException('Finite repeated Connection SQL unexpectedly emitted PHT006.');
        }

        $distinctSource = str_replace(
            [':value AS first_value, :value AS second_value', "['value' => \$value]"],
            [':first_value AS first_value, :second_value AS second_value', "['first_value' => \$value, 'second_value' => \$value]"],
            $repeatedSource,
        );
        writeFile($path, $distinctSource . "\n");
        $distinctResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($distinctResult, 'Distinct Connection SQL placeholder occurrences failed PHT008.');
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
    $link = $project . '/linked-source';

    try {
        if (!mkdir($outside, 0700)) {
            throw new RuntimeException('Unable to create the symlink negative-control target.');
        }

        writeFile(
            $outside . '/External.php',
            "<?php\n\ndeclare(strict_types=1);\n\nclass LinkedSourceTraversalCanary {}\n",
        );

        if (!symlink($outside, $link)) {
            throw new RuntimeException('Unable to create the symlink negative control.');
        }

        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'A symlinked source directory unexpectedly passed.');
        requireOutputContains($result, 'linked-source is a symlink; application checks do not follow symlinks');
        requireOutputNotContains($result, 'linked-source/External.php');
        requireOutputNotContains($result, 'LinkedSourceTraversalCanary');
    } finally {
        if (is_link($link)) {
            unlink($link);
        }

        if (is_dir($outside)) {
            removeDirectory($outside);
        }
    }

    $outsideExecutable = $workspace . '/outside-command';
    $outsidePrefixedSource = $workspace . '/outside-prefixed-source';
    $outsideAsset = $workspace . '/outside-asset';
    $binDirectory = $project . '/bin';
    $executableLink = $binDirectory . '/linked-command';
    $prefixedLink = $binDirectory . '/linked-prefixed-source.inc';
    $brokenLink = $binDirectory . '/linked-broken-source.inc';
    $assetLink = $binDirectory . '/linked-asset.txt';

    try {
        writeFile(
            $outsideExecutable,
            "#!/usr/bin/env php\n<?php\n\ndeclare(strict_types=1);\n\nnamespace External;\n\nfinal class Command {}\n",
        );
        writeFile(
            $outsidePrefixedSource,
            "\xEF\xBB\xBF \t\r\n<?PHP\n\ndeclare(strict_types=1);\n",
        );
        writeFile($outsideAsset, "ordinary non-source asset\n");

        if (!mkdir($binDirectory, 0700)) {
            throw new RuntimeException('Unable to create the executable symlink negative-control directory.');
        }

        if (!symlink($outsideExecutable, $executableLink)) {
            throw new RuntimeException('Unable to create the executable symlink negative control.');
        }

        if (!symlink($outsidePrefixedSource, $prefixedLink)) {
            throw new RuntimeException('Unable to create the prefixed-source symlink negative control.');
        }

        if (!symlink($workspace . '/missing-source', $brokenLink)) {
            throw new RuntimeException('Unable to create the broken-source symlink negative control.');
        }

        if (!symlink($outsideAsset, $assetLink)) {
            throw new RuntimeException('Unable to create the asset symlink negative control.');
        }

        $result = runProcess($profileCommand, $project, $environment);
        requireFailure($result, 'Application symlinks unexpectedly passed.');
        requireOutputContains($result, 'bin/linked-command is a symlink; application checks do not follow symlinks');
        requireOutputContains($result, 'bin/linked-prefixed-source.inc is a symlink; application checks do not follow symlinks');
        requireOutputContains($result, 'bin/linked-broken-source.inc is a symlink; application checks do not follow symlinks');
        requireOutputContains($result, 'bin/linked-asset.txt is a symlink; application checks do not follow symlinks');
    } finally {
        foreach ([$executableLink, $prefixedLink, $brokenLink, $assetLink] as $sourceLink) {
            if (is_link($sourceLink)) {
                unlink($sourceLink);
            }
        }

        if (is_dir($binDirectory)) {
            rmdir($binDirectory);
        }

        foreach ([$outsideExecutable, $outsidePrefixedSource, $outsideAsset] as $outsideSource) {
            if (is_file($outsideSource)) {
                unlink($outsideSource);
            }
        }
    }
}
