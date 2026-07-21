<?php

declare(strict_types=1);

namespace PHPThis\Verification;

use JsonException;
use Throwable;

final class ApplicationChecker
{
    private const PHPSTAN_CONSTRAINT = '^2.1';
    private const STRICT_RULES_CONSTRAINT = '^2.0';

    private const APPLICATION_CONTEXT_FILES = [
        'AGENTS.md',
        '.ai/README.md',
        '.ai/architecture.md',
        '.ai/change-workflow.md',
        '.ai/data.md',
        '.ai/integrations.md',
        '.ai/observability.md',
        '.ai/operations.md',
        '.ai/project.md',
        '.ai/rules.md',
        '.ai/testing.md',
        'docs/decisions/README.md',
    ];

    private const SUPERGLOBALS = [
        '$GLOBALS',
        '$_SERVER',
        '$_GET',
        '$_POST',
        '$_FILES',
        '$_COOKIE',
        '$_SESSION',
        '$_REQUEST',
        '$_ENV',
    ];

    private const NATIVE_SESSION_FUNCTIONS = [
        'session_abort',
        'session_cache_expire',
        'session_cache_limiter',
        'session_commit',
        'session_create_id',
        'session_decode',
        'session_destroy',
        'session_encode',
        'session_gc',
        'session_get_cookie_params',
        'session_id',
        'session_module_name',
        'session_name',
        'session_regenerate_id',
        'session_register_shutdown',
        'session_reset',
        'session_save_path',
        'session_set_cookie_params',
        'session_set_save_handler',
        'session_start',
        'session_status',
        'session_unset',
        'session_write_close',
    ];

    private const VCS_DIRECTORIES = [
        '.git',
        '.hg',
        '.svn',
        '.bzr',
        '_darcs',
        '.fossil-settings',
    ];

    public function check(string $projectRoot, bool $debug = false): int
    {
        $root = realpath($projectRoot);

        if (!is_string($root) || !is_dir($root)) {
            return $this->reportFailures(['The Composer project root does not exist.']);
        }

        $vendorResult = $this->resolveVendorDirectory($root);

        if ($vendorResult['failures'] !== []) {
            return $this->reportFailures($vendorResult['failures']);
        }

        $vendorDirectory = $vendorResult['directory'];

        if ($vendorDirectory === null) {
            return $this->reportFailures(['Cannot resolve the Composer vendor directory.']);
        }

        $discovery = $this->discoverPhpFiles($root, $vendorDirectory);
        $phpFiles = $discovery['files'];
        $duplicationScanner = new ApplicationDuplicationScanner();
        $failures = [
            ...$discovery['failures'],
            ...$this->applicationContextFailures($root),
        ];

        foreach ($phpFiles as $relativePath => $absolutePath) {
            $contents = file_get_contents($absolutePath);

            if (!is_string($contents)) {
                $failures[] = "Cannot read {$relativePath}.";
                continue;
            }

            $duplicationScanner->collect($relativePath, $contents);

            foreach ($this->phpFileFailures($contents, $relativePath) as $failure) {
                $failures[] = $failure;
            }
        }

        if ($phpFiles === []) {
            $failures[] = 'The application does not contain any application-owned PHP files.';
        }

        if ($failures !== []) {
            return $this->reportFailures($failures);
        }

        fwrite(STDOUT, sprintf("PASS application guardrails: %d PHP files\n", count($phpFiles)));

        try {
            $duplicationScanner->write($debug);
        } catch (Throwable) {
            fwrite(
                STDOUT,
                "ADVISORY application duplication scan unavailable; application validity is unaffected\n",
            );
        }

        return $this->runPhpStan($root, $vendorDirectory, array_values($phpFiles), $debug);
    }

    /** @return array{directory: ?string, failures: list<string>} */
    private function resolveVendorDirectory(string $projectRoot): array
    {
        $composerPath = $projectRoot . '/composer.json';
        $contents = file_get_contents($composerPath);

        if (!is_string($contents)) {
            return [
                'directory' => null,
                'failures' => ['Run `phpthis check` from a Composer project root containing composer.json.'],
            ];
        }

        try {
            $composer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [
                'directory' => null,
                'failures' => ['composer.json is not valid JSON.'],
            ];
        }

        if (!is_array($composer)) {
            return [
                'directory' => null,
                'failures' => ['composer.json must contain a JSON object.'],
            ];
        }

        $scriptFailures = $this->composerContractFailures($composer);

        if ($scriptFailures !== []) {
            return ['directory' => null, 'failures' => $scriptFailures];
        }

        $configuredDirectory = 'vendor';
        $config = $composer['config'] ?? null;

        if (is_array($config) && array_key_exists('vendor-dir', $config)) {
            if (!is_string($config['vendor-dir']) || $config['vendor-dir'] === '') {
                return [
                    'directory' => null,
                    'failures' => ['composer.json config.vendor-dir must be a non-empty string.'],
                ];
            }

            $configuredDirectory = $config['vendor-dir'];
        }

        $candidate = $this->isAbsolutePath($configuredDirectory)
            ? $configuredDirectory
            : $projectRoot . '/' . $configuredDirectory;
        $resolved = realpath($candidate);

        if (!is_string($resolved) || !is_dir($resolved)) {
            return [
                'directory' => null,
                'failures' => ['Install Composer dependencies before running `phpthis check`.'],
            ];
        }

        if ($resolved === $projectRoot) {
            return [
                'directory' => null,
                'failures' => ['The Composer vendor directory must not be the project root.'],
            ];
        }

        return ['directory' => $resolved, 'failures' => []];
    }

    /**
     * @param array<array-key, mixed> $composer
     * @return list<string>
     */
    private function composerContractFailures(array $composer): array
    {
        $scripts = $composer['scripts'] ?? null;
        $requireDev = $composer['require-dev'] ?? null;
        $failures = [];

        if (!is_array($requireDev)) {
            $failures[] = 'composer.json must define PHPThis analysis dependencies under require-dev.';
        } else {
            if (($requireDev['phpstan/phpstan'] ?? null) !== self::PHPSTAN_CONSTRAINT) {
                $failures[] = 'composer.json must require-dev phpstan/phpstan at `^2.1`.';
            }

            if (($requireDev['phpstan/phpstan-strict-rules'] ?? null) !== self::STRICT_RULES_CONSTRAINT) {
                $failures[] = 'composer.json must require-dev phpstan/phpstan-strict-rules at `^2.0`.';
            }
        }

        if (!is_array($scripts)) {
            $failures[] = 'composer.json must define the canonical PHPThis application scripts.';

            return $failures;
        }

        if (($scripts['profile'] ?? null) !== 'phpthis check') {
            $failures[] = 'composer.json scripts.profile must be exactly `phpthis check`.';
        }

        if (!$this->isNonEmptyComposerScript($scripts['test'] ?? null)) {
            $failures[] = "composer.json scripts.test must execute the application's automated behavior tests.";
        }

        if (($scripts['check'] ?? null) !== ['@profile', '@test']) {
            $failures[] = 'composer.json scripts.check must be exactly [`@profile`, `@test`].';
        }

        return $failures;
    }

    private function isNonEmptyComposerScript(mixed $script): bool
    {
        if (is_string($script)) {
            return trim($script) !== '';
        }

        if (!is_array($script) || $script === [] || !array_is_list($script)) {
            return false;
        }

        foreach ($script as $command) {
            if (!is_string($command) || trim($command) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{files: array<string, string>, failures: list<string>}
     */
    private function discoverPhpFiles(string $projectRoot, string $vendorDirectory): array
    {
        /** @var list<string> $directories */
        $directories = [$projectRoot];
        /** @var array<string, string> $files */
        $files = [];
        $failures = [];

        while ($directories !== []) {
            $directory = array_pop($directories);
            $entries = scandir($directory);

            if (!is_array($entries)) {
                $failures[] = 'Cannot read application directory ' . $this->relativePath($projectRoot, $directory) . '.';
                continue;
            }

            sort($entries, SORT_STRING);

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (in_array($entry, self::VCS_DIRECTORIES, true)) {
                    continue;
                }

                $path = $directory . '/' . $entry;
                $relativePath = $this->relativePath($projectRoot, $path);
                $resolvedPath = realpath($path);

                if (is_string($resolvedPath) && $resolvedPath === $vendorDirectory) {
                    continue;
                }

                if ($this->isPhpStanConfigurationArtifact($entry)) {
                    $failures[] = "PHT004 {$relativePath} is forbidden; `phpthis check` supplies the complete PHPStan configuration.";
                }

                if (is_link($path)) {
                    if (is_dir($path)) {
                        $failures[] = "{$relativePath} is a symlink directory; application checks do not follow symlinks.";
                    } elseif (
                        strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php'
                        || $this->hasPhpSourcePrefix($path)
                        || in_array($relativePath, self::APPLICATION_CONTEXT_FILES, true)
                    ) {
                        $failures[] = "{$relativePath} is a symlink file; checked PHP and application context must be owned files.";
                    }

                    continue;
                }

                if (is_dir($path)) {
                    $directories[] = $path;
                    continue;
                }

                if (!is_file($path)) {
                    continue;
                }

                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $hasPhpSourcePrefix = $this->hasPhpSourcePrefix($path);

                if ($extension === 'php' || ($extension === '' && $hasPhpSourcePrefix)) {
                    $files[$relativePath] = $path;
                } elseif ($hasPhpSourcePrefix) {
                    $failures[] = "{$relativePath} contains PHP source but must use the .php extension or no extension for an executable.";
                }
            }
        }

        ksort($files, SORT_STRING);

        return ['files' => $files, 'failures' => $failures];
    }

    private function isPhpStanConfigurationArtifact(string $basename): bool
    {
        $normalized = strtolower($basename);

        if (preg_match('/\Aphpstan[a-z0-9._-]*\.neon(?:\.dist)?\z/', $normalized) === 1) {
            return true;
        }

        return preg_match('/\Aphpstan[a-z0-9._-]*baseline[a-z0-9._-]*\.php\z/', $normalized) === 1;
    }

    private function hasPhpSourcePrefix(string $path): bool
    {
        $prefix = file_get_contents($path, false, null, 0, 128);

        if (!is_string($prefix)) {
            return false;
        }

        return preg_match('/\A(?:#!\/usr\/bin\/env php\R)?<\?php\b/', $prefix) === 1;
    }

    /** @return list<string> */
    private function applicationContextFailures(string $projectRoot): array
    {
        $failures = [];

        foreach (self::APPLICATION_CONTEXT_FILES as $relativePath) {
            $path = $projectRoot . '/' . $relativePath;

            if (!is_file($path) || is_link($path)) {
                $failures[] = "Required application context file is missing: {$relativePath}.";
                continue;
            }

            $contents = file_get_contents($path);

            if (!is_string($contents)) {
                $failures[] = "Cannot read application context file {$relativePath}.";
                continue;
            }

            if (preg_match('/\{\{[A-Z0-9_]+\}\}/', $contents) === 1) {
                $failures[] = "Application context file {$relativePath} contains an unresolved placeholder.";
            }
        }

        return $failures;
    }

    /** @return list<string> */
    private function phpFileFailures(string $contents, string $relativePath): array
    {
        $failures = [];

        if (preg_match('/\A(?:#!\/usr\/bin\/env php\R)?<\?php\s+declare\(strict_types=1\);/', $contents) !== 1) {
            $failures[] = "{$relativePath} must declare strict types immediately after <?php.";
        }

        foreach (SyntaxProfile::failures($contents, $relativePath) as $failure) {
            $failures[] = $failure;
        }

        $tokens = token_get_all($contents);
        $line = 1;
        $functionImportPending = false;
        $insideFunctionImport = false;

        foreach ($tokens as $index => $token) {
            $tokenId = is_array($token) ? $token[0] : null;
            $tokenText = is_array($token) ? $token[1] : $token;
            $tokenLine = is_array($token) ? $token[2] : $line;
            $isSignificant = !is_array($token)
                || !in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);

            if ($functionImportPending && $isSignificant) {
                $insideFunctionImport = $tokenId === T_FUNCTION;
                $functionImportPending = false;
            }

            if ($tokenId === T_USE) {
                $functionImportPending = true;
            } elseif ($insideFunctionImport && $tokenText === ';') {
                $insideFunctionImport = false;
            }

            if ($tokenId === T_EVAL) {
                $failures[] = "{$relativePath}:{$tokenLine} uses eval.";
            }

            if ($tokenText === '$') {
                $failures[] = "{$relativePath}:{$tokenLine} uses a variable variable.";
            }

            if (
                $tokenId === T_VARIABLE
                && in_array($tokenText, self::SUPERGLOBALS, true)
                && ($tokenText === '$_SESSION' || $relativePath !== 'public/index.php')
            ) {
                $boundary = $tokenText === '$_SESSION'
                    ? 'PHPThis\\Session\\SessionLifecycle'
                    : 'public/index.php';
                $failures[] = "{$relativePath}:{$tokenLine} reads a PHP superglobal outside {$boundary}.";
            }

            $nativeSessionFunction = strtolower(ltrim($tokenText, '\\'));

            if (
                in_array($tokenId, [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                && in_array($nativeSessionFunction, self::NATIVE_SESSION_FUNCTIONS, true)
                && ($insideFunctionImport || $this->isFunctionCall($tokens, $index))
            ) {
                $action = $insideFunctionImport ? 'imports' : 'calls';
                $failures[] = "{$relativePath}:{$tokenLine} {$action} native session function {$nativeSessionFunction}; use PHPThis\\Session\\SessionLifecycle.";
            }

            if ($tokenId === T_CONSTANT_ENCAPSED_STRING && strlen($tokenText) >= 2) {
                $literalFunction = strtolower(ltrim(stripcslashes(substr($tokenText, 1, -1)), '\\'));

                if (in_array($literalFunction, self::NATIVE_SESSION_FUNCTIONS, true)) {
                    $failures[] = "{$relativePath}:{$tokenLine} references native session function {$literalFunction} indirectly; use PHPThis\\Session\\SessionLifecycle.";
                }
            }

            if (
                in_array($tokenId, [T_COMMENT, T_DOC_COMMENT], true)
                && preg_match('/@phpstan-ignore[A-Za-z0-9_-]*/i', $tokenText) === 1
            ) {
                $failures[] = "PHT004 {$relativePath}:{$tokenLine} PHPStan comment suppressions are forbidden.";
            }

            $line += substr_count($tokenText, "\n");
        }

        return $failures;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private function isFunctionCall(array $tokens, int $index): bool
    {
        $nextSignificantToken = null;

        for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
            $candidate = $tokens[$next];

            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $nextSignificantToken = $candidate;
            break;
        }

        if ($nextSignificantToken !== '(') {
            return false;
        }

        for ($previous = $index - 1; $previous >= 0; $previous--) {
            $candidate = $tokens[$previous];

            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $previousTokenId = is_array($candidate) ? $candidate[0] : null;

            return !in_array(
                $previousTokenId,
                [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON],
                true,
            );
        }

        return true;
    }

    /** @param list<string> $phpFiles */
    private function runPhpStan(
        string $projectRoot,
        string $vendorDirectory,
        array $phpFiles,
        bool $debug,
    ): int
    {
        $phpstanBinary = $vendorDirectory . '/bin/phpstan';
        $strictRules = $vendorDirectory . '/phpstan/phpstan-strict-rules/rules.neon';
        $profileExtension = __DIR__ . '/phpstan/extension.php';
        $dependencyFailures = [];

        if (!is_file($phpstanBinary)) {
            $dependencyFailures[] = 'The application must require phpstan/phpstan as a development dependency.';
        }

        if (!is_file($strictRules)) {
            $dependencyFailures[] = 'The application must require phpstan/phpstan-strict-rules as a development dependency.';
        }

        if (!is_file($profileExtension)) {
            $dependencyFailures[] = 'The installed PHPThis package is missing its PHPStan extension.';
        }

        if ($dependencyFailures !== []) {
            return $this->reportFailures($dependencyFailures);
        }

        $cacheDirectory = $this->phpStanCacheDirectory($vendorDirectory);

        if ($cacheDirectory === null) {
            return $this->reportFailures(['Cannot create the persistent PHPThis analysis cache.']);
        }

        try {
            $temporaryDirectory = sys_get_temp_dir() . '/phpthis-check-' . bin2hex(random_bytes(16));
        } catch (Throwable) {
            return $this->reportFailures(['Cannot create a unique PHPThis check directory.']);
        }

        if (!mkdir($temporaryDirectory, 0700)) {
            return $this->reportFailures(['Cannot create the temporary PHPThis check directory.']);
        }

        $configurationPath = $temporaryDirectory . '/phpstan.neon';

        try {
            $configuration = $this->phpStanConfiguration(
                $strictRules,
                $profileExtension,
                $cacheDirectory,
                $phpFiles,
            );

            if (file_put_contents($configurationPath, $configuration, LOCK_EX) === false) {
                return $this->reportFailures(['Cannot write the trusted PHPStan configuration.']);
            }

            $exitCode = $this->executePhpStan($phpstanBinary, $configurationPath, $projectRoot, $debug);

            if ($exitCode === 0) {
                fwrite(STDOUT, "PASS PHPThis application check\n");
            }

            return $exitCode;
        } finally {
            $this->removeDirectory($temporaryDirectory);
        }
    }

    /** @param list<string> $phpFiles */
    private function phpStanConfiguration(
        string $strictRules,
        string $profileExtension,
        string $cacheDirectory,
        array $phpFiles,
    ): string {
        $lines = [
            'includes:',
            '    - ' . $this->neonString($strictRules),
            '    - ' . $this->neonString($profileExtension),
            '',
            'parameters:',
            '    level: max',
            '    strictRules:',
            '        allRules: true',
            '    paths:',
        ];

        foreach ($phpFiles as $phpFile) {
            $lines[] = '        - ' . $this->neonString($phpFile);
        }

        // PHPStan does not persist a result cache when every configured path is a file.
        // This stable, excluded directory changes only that cache mode; the analyzed files remain the discovered manifest.
        $manifestAnchorDirectory = $cacheDirectory . '/manifest-anchor';
        $lines[] = '        - ' . $this->neonString($manifestAnchorDirectory);
        $lines[] = '    excludePaths:';
        $lines[] = '        - ' . $this->neonString($manifestAnchorDirectory . '/anchor.php');
        $lines[] = '    tmpDir: ' . $this->neonString($cacheDirectory);
        $lines[] = '    checkUninitializedProperties: true';
        $lines[] = '    reportUnmatchedIgnoredErrors: true';
        $lines[] = '    resultCacheChecksProjectExtensionFilesDependencies: true';
        $lines[] = '    treatPhpDocTypesAsCertain: true';

        return implode("\n", $lines) . "\n";
    }

    private function neonString(string $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return '""';
        }
    }

    private function executePhpStan(
        string $binary,
        string $configuration,
        string $projectRoot,
        bool $debug,
    ): int
    {
        if (!function_exists('proc_open')) {
            return $this->reportFailures(['PHP must allow proc_open to run the consumer PHPStan process.']);
        }

        $command = [
            PHP_BINARY,
        ];

        if (!$debug && !$this->canBindLocalTcpServer()) {
            $disabledFunctions = array_filter(
                array_map('trim', explode(',', (string) ini_get('disable_functions'))),
                static fn (string $function): bool => $function !== '',
            );

            if (!in_array('proc_open', $disabledFunctions, true)) {
                $disabledFunctions[] = 'proc_open';
            }

            $command[] = '-d';
            $command[] = 'disable_functions=' . implode(',', $disabledFunctions);
        }

        $command = [
            ...$command,
            $binary,
            'analyse',
            '--configuration=' . $configuration,
            '--no-progress',
        ];

        if ($debug) {
            $command[] = '--debug';
        }

        $descriptors = [
            0 => ['file', 'php://stdin', 'r'],
            1 => ['file', 'php://stdout', 'w'],
            2 => ['file', 'php://stderr', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, $projectRoot, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            return $this->reportFailures(['Cannot start the consumer PHPStan process.']);
        }

        $exitCode = proc_close($process);

        return $exitCode >= 0 ? $exitCode : 1;
    }

    private function phpStanCacheDirectory(string $vendorDirectory): ?string
    {
        $stateDirectory = $vendorDirectory . '/.phpthis';
        $cacheDirectory = $stateDirectory . '/phpstan';
        $manifestAnchorDirectory = $cacheDirectory . '/manifest-anchor';

        foreach ([$stateDirectory, $cacheDirectory, $manifestAnchorDirectory] as $directory) {
            if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                return null;
            }

            if (!is_dir($directory) && !mkdir($directory, 0700) && !is_dir($directory)) {
                return null;
            }
        }

        $anchorPath = $manifestAnchorDirectory . '/anchor.php';
        $anchor = "<?php\n\ndeclare(strict_types=1);\n";

        if (
            is_link($anchorPath)
            || (file_exists($anchorPath) && !is_file($anchorPath))
            || file_put_contents($anchorPath, $anchor, LOCK_EX) !== strlen($anchor)
        ) {
            return null;
        }

        return $cacheDirectory;
    }

    private function canBindLocalTcpServer(): bool
    {
        if (!function_exists('stream_socket_server')) {
            return false;
        }

        $errorCode = 0;
        $errorMessage = '';
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if (!is_resource($server)) {
            return false;
        }

        fclose($server);

        return true;
    }

    private function removeDirectory(string $directory): void
    {
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
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }

    /** @param list<string> $failures */
    private function reportFailures(array $failures): int
    {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL {$failure}\n");
        }

        return 1;
    }

    private function relativePath(string $root, string $path): string
    {
        if ($path === $root) {
            return '.';
        }

        return str_replace('\\', '/', substr($path, strlen($root) + 1));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
