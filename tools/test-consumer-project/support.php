<?php

declare(strict_types=1);

/**
 * @param array<string, list<string>> $artifactMarkers
 * @param non-empty-string $artifactLabel
 */
function requireInstalledArtifactMarkers(array $artifactMarkers, string $artifactLabel): void
{
    foreach ($artifactMarkers as $path => $markers) {
        if (!is_file($path)) {
            throw new RuntimeException("Required installed {$artifactLabel} artifact is not a regular file: {$path}.");
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed {$artifactLabel} artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("Installed {$artifactLabel} artifact {$path} is missing marker: {$marker}");
            }
        }
    }
}

/**
 * @param array<string, list<string>> $artifactMarkers
 * @param non-empty-string $artifactLabel
 */
function forbidInstalledArtifactMarkers(array $artifactMarkers, string $artifactLabel): void
{
    foreach ($artifactMarkers as $path => $markers) {
        if (!is_file($path)) {
            throw new RuntimeException("Required installed {$artifactLabel} artifact is not a regular file: {$path}.");
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read installed {$artifactLabel} artifact {$path}.");
        }

        foreach ($markers as $marker) {
            if (str_contains($contents, $marker)) {
                throw new RuntimeException("Installed {$artifactLabel} artifact {$path} contains forbidden marker: {$marker}");
            }
        }
    }
}

function requireInstalledNativeRuntimeDependencyBoundary(
    string $project,
    string $installedFramework,
): void {
    $installedComposer = jsonFile($installedFramework . '/composer.json');
    $installedRuntimeRequirements = $installedComposer['require'] ?? null;

    if (!is_array($installedRuntimeRequirements)) {
        throw new RuntimeException('Installed framework runtime requirements must be an explicit Composer map.');
    }

    foreach (array_keys($installedRuntimeRequirements) as $runtimePackage) {
        if (
            !is_string($runtimePackage)
            || (
                $runtimePackage !== 'php'
                && !str_starts_with($runtimePackage, 'ext-')
            )
        ) {
            throw new RuntimeException(
                'Installed framework runtime dependencies must remain native PHP and extensions.',
            );
        }
    }

    $consumerComposer = jsonFile($project . '/composer.json');
    $consumerRuntimeRequirements = $consumerComposer['require'] ?? null;

    if (!is_array($consumerRuntimeRequirements)) {
        throw new RuntimeException('Installed skeleton runtime requirements must be an explicit Composer map.');
    }

    $consumerRuntimePackages = array_keys($consumerRuntimeRequirements);

    foreach ($consumerRuntimePackages as $consumerRuntimePackage) {
        if (!is_string($consumerRuntimePackage)) {
            throw new RuntimeException('Installed skeleton runtime requirement names must be strings.');
        }
    }

    sort($consumerRuntimePackages, SORT_STRING);

    if ($consumerRuntimePackages !== ['php', 'phpthis/framework']) {
        throw new RuntimeException(
            'Installed default skeleton must require only PHP and phpthis/framework.',
        );
    }
}

function installedSyntheticDatabaseContext(): string
{
    return <<<'MD'
# Installed synthetic SQLite data contract

- Connection and engine: proof-only in-memory SQLite through `pdo_sqlite`; no persistent or shared database is contacted.
- Schema definition source: no persistent schema or migration is adopted; the executable proof statement is the code-owned constant `SELECT 1 AS configured`.
- Structural namespace/control model: SQLite's default `main` attachment namespace exists only inside each in-memory proof connection; this is structural context, not live namespace ownership or authority evidence.
- Runtime operation and capability: the synthetic configuration proof may connect and execute only its named constant `SELECT 1 AS configured` statement.
- Elevated path: the separately composed synthetic migration-profile connection proves typed configuration delivery only; it performs no DDL, identity-management, authority-management, or administrative action and never falls back to runtime configuration.
- Authority evidence: installed static checking and isolated synthetic execution prove only the recorded code and process separation. They do not inspect or prove any engine's effective-authority resolution, activation or deactivation, production identity isolation, or deployment order; no live authority probe runs.
MD;
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

/**
 * @param array<string, string> $environment
 * @return array<string, string>
 */
function environmentWithEmptyValue(array $environment, string $name): array
{
    unset($environment[$name]);
    $environment[''] = $name . '=';

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

/** @param 'verified'|'skipped-dirty' $state */
function gitExportParityResultLine(int $releaseFiles, string $state): string
{
    if ($state === 'verified') {
        return sprintf(
            "PASS isolated consumer: %d release files, clean install, complete check, and adversarial controls; git-export-parity=verified\n",
            $releaseFiles,
        );
    }

    return sprintf(
        "PASS isolated consumer development checks: %d release files, clean install, complete check, and adversarial controls; git-export-parity=skipped-dirty; not release evidence\n",
        $releaseFiles,
    );
}

/**
 * @param array<string, string> $environment
 * @return array{environment: array<string, string>, empty_hooks: non-empty-string}
 */
function gitExportParityFixtureEnvironment(
    string $workspace,
    array $environment,
): array {
    $emptyGlobalConfig = $workspace . '/git-export-empty-global-config';
    $emptyXdgConfig = $workspace . '/git-export-empty-xdg';
    $emptyTemplate = $workspace . '/git-export-empty-template';
    $emptyHooks = $workspace . '/git-export-empty-hooks';
    writeFile($emptyGlobalConfig, '');

    foreach ([$emptyXdgConfig, $emptyTemplate, $emptyHooks] as $emptyDirectory) {
        if (!mkdir($emptyDirectory, 0700) && !is_dir($emptyDirectory)) {
            throw new RuntimeException('Unable to create the isolated Git-export configuration boundary.');
        }
    }

    foreach (array_keys($environment) as $name) {
        if (
            in_array(
                $name,
                [
                    'GIT_ATTR_NOSYSTEM',
                    'GIT_CONFIG',
                    'GIT_CONFIG_COUNT',
                    'GIT_CONFIG_GLOBAL',
                    'GIT_CONFIG_NOSYSTEM',
                    'GIT_CONFIG_PARAMETERS',
                    'GIT_CONFIG_SYSTEM',
                    'GIT_DIR',
                    'GIT_INDEX_FILE',
                    'GIT_TEMPLATE_DIR',
                    'GIT_WORK_TREE',
                    'XDG_CONFIG_HOME',
                ],
                true,
            )
            || str_starts_with($name, 'GIT_CONFIG_KEY_')
            || str_starts_with($name, 'GIT_CONFIG_VALUE_')
        ) {
            unset($environment[$name]);
        }
    }

    $environment['GIT_ATTR_NOSYSTEM'] = '1';
    $environment['GIT_CONFIG_GLOBAL'] = $emptyGlobalConfig;
    $environment['GIT_CONFIG_NOSYSTEM'] = '1';
    $environment['GIT_CONFIG_SYSTEM'] = $emptyGlobalConfig;
    $environment['GIT_TEMPLATE_DIR'] = $emptyTemplate;
    $environment['XDG_CONFIG_HOME'] = $emptyXdgConfig;

    return ['environment' => $environment, 'empty_hooks' => $emptyHooks];
}

/**
 * @param array<string, string> $environment
 * @return list<string>
 */
function createGitExportParityFixture(
    string $root,
    string $emptyHooks,
    array $environment,
): array {
    if (!mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create the Git-export parity fixture.');
    }

    writeJson($root . '/composer.json', ['archive' => ['exclude' => ['/excluded.txt']]]);
    writeFile($root . '/.gitattributes', "/excluded.txt export-ignore\n");
    writeFile($root . '/included.txt', "included\n");
    writeFile($root . '/excluded.txt', "excluded\n");

    foreach ([
        ['git', 'init', '--quiet'],
        ['git', 'config', 'user.email', 'proof@example.invalid'],
        ['git', 'config', 'user.name', 'PHPThis proof'],
        ['git', 'add', '--force', '--all'],
        [
            'git',
            '-c',
            'core.hooksPath=' . $emptyHooks,
            'commit',
            '--quiet',
            '--no-gpg-sign',
            '--no-verify',
            '-m',
            'fixture',
        ],
    ] as $command) {
        if (runProcess($command, $root, $environment)['exit_code'] !== 0) {
            throw new RuntimeException('Unable to prepare the Git-export parity fixture.');
        }
    }

    return ['.gitattributes', 'composer.json', 'included.txt'];
}

/** @param array<string, string> $environment */
function proveGitExportParityStates(string $workspace, array $environment): void
{
    $hostileGlobalConfig = $workspace . '/git-export-hostile-global-config';
    $hostileSystemConfig = $workspace . '/git-export-hostile-system-config';
    $hostileXdgConfig = $workspace . '/git-export-hostile-xdg';
    $hostileTemplate = $workspace . '/git-export-hostile-template';
    $hostileHooks = $workspace . '/git-export-hostile-hooks';
    $hostileExcludes = $workspace . '/git-export-hostile-excludes';
    $hostileAttributes = $workspace . '/git-export-hostile-attributes';
    $hostileHook = $hostileHooks . '/pre-commit';
    $hostileConfiguration = <<<CFG
[commit]
    gpgSign = true
[gpg]
    program = /definitely/missing-gpg
[core]
    excludesFile = {$hostileExcludes}
    hooksPath = {$hostileHooks}
    attributesFile = {$hostileAttributes}
[init]
    templateDir = {$hostileTemplate}
CFG;
    writeFile($hostileGlobalConfig, $hostileConfiguration);
    writeFile($hostileSystemConfig, $hostileConfiguration);
    writeFile($hostileXdgConfig . '/git/config', $hostileConfiguration);
    writeFile($hostileXdgConfig . '/git/ignore', "PrivateUntrackedFilename.marker\n");
    writeFile($hostileXdgConfig . '/git/attributes', "included.txt export-ignore\n");
    writeFile($hostileTemplate . '/info/exclude', "PrivateUntrackedFilename.marker\n");
    writeFile($hostileTemplate . '/hooks/pre-commit', "#!/bin/sh\nexit 91\n");
    writeFile($hostileExcludes, "PrivateUntrackedFilename.marker\n");
    writeFile($hostileAttributes, "included.txt export-ignore\n");
    writeFile($hostileHook, "#!/bin/sh\nexit 92\n");

    if (
        !chmod($hostileTemplate . '/hooks/pre-commit', 0700)
        || !chmod($hostileHook, 0700)
    ) {
        throw new RuntimeException('Unable to prepare hostile Git-export fixture controls.');
    }

    $hostileEnvironment = $environment;
    $hostileEnvironment['GIT_CONFIG'] = $hostileGlobalConfig;
    $hostileEnvironment['GIT_CONFIG_COUNT'] = '6';
    $hostileEnvironment['GIT_CONFIG_GLOBAL'] = $hostileGlobalConfig;
    $hostileEnvironment['GIT_CONFIG_KEY_0'] = 'commit.gpgSign';
    $hostileEnvironment['GIT_CONFIG_KEY_1'] = 'gpg.program';
    $hostileEnvironment['GIT_CONFIG_KEY_2'] = 'core.excludesFile';
    $hostileEnvironment['GIT_CONFIG_KEY_3'] = 'core.hooksPath';
    $hostileEnvironment['GIT_CONFIG_KEY_4'] = 'init.templateDir';
    $hostileEnvironment['GIT_CONFIG_KEY_5'] = 'core.attributesFile';
    $hostileEnvironment['GIT_CONFIG_PARAMETERS'] =
        "'commit.gpgSign'='true' 'gpg.program'='/definitely/missing-gpg'";
    $hostileEnvironment['GIT_CONFIG_SYSTEM'] = $hostileSystemConfig;
    $hostileEnvironment['GIT_CONFIG_VALUE_0'] = 'true';
    $hostileEnvironment['GIT_CONFIG_VALUE_1'] = '/definitely/missing-gpg';
    $hostileEnvironment['GIT_CONFIG_VALUE_2'] = $hostileExcludes;
    $hostileEnvironment['GIT_CONFIG_VALUE_3'] = $hostileHooks;
    $hostileEnvironment['GIT_CONFIG_VALUE_4'] = $hostileTemplate;
    $hostileEnvironment['GIT_CONFIG_VALUE_5'] = $hostileAttributes;
    $hostileEnvironment['GIT_TEMPLATE_DIR'] = $hostileTemplate;
    $hostileEnvironment['XDG_CONFIG_HOME'] = $hostileXdgConfig;
    $isolatedGit = gitExportParityFixtureEnvironment(
        $workspace,
        $hostileEnvironment,
    );
    $gitEnvironment = $isolatedGit['environment'];
    $emptyHooks = $isolatedGit['empty_hooks'];

    foreach (array_keys($gitEnvironment) as $name) {
        if (
            str_starts_with($name, 'GIT_CONFIG_KEY_')
            || str_starts_with($name, 'GIT_CONFIG_VALUE_')
        ) {
            throw new RuntimeException(
                'The Git-export fixture environment retained command-scope configuration.',
            );
        }
    }

    if (
        isset($gitEnvironment['GIT_CONFIG'])
        || isset($gitEnvironment['GIT_CONFIG_COUNT'])
        || isset($gitEnvironment['GIT_CONFIG_PARAMETERS'])
        || ($gitEnvironment['GIT_ATTR_NOSYSTEM'] ?? null) !== '1'
        || ($gitEnvironment['GIT_CONFIG_GLOBAL'] ?? null)
            !== $workspace . '/git-export-empty-global-config'
        || ($gitEnvironment['GIT_CONFIG_NOSYSTEM'] ?? null) !== '1'
        || ($gitEnvironment['GIT_CONFIG_SYSTEM'] ?? null)
            !== $workspace . '/git-export-empty-global-config'
        || ($gitEnvironment['GIT_TEMPLATE_DIR'] ?? null)
            !== $workspace . '/git-export-empty-template'
        || ($gitEnvironment['XDG_CONFIG_HOME'] ?? null)
            !== $workspace . '/git-export-empty-xdg'
    ) {
        throw new RuntimeException('The Git-export fixture environment retained ambient configuration.');
    }

    $cleanRoot = $workspace . '/git-export-clean';
    $cleanArchiveWorkspace = $workspace . '/git-export-clean-archive';
    $expectedFiles = createGitExportParityFixture($cleanRoot, $emptyHooks, $gitEnvironment);

    if (!mkdir($cleanArchiveWorkspace, 0700)) {
        throw new RuntimeException('Unable to create the clean Git-export proof workspace.');
    }

    if (
        verifyExportPolicies(
            $cleanRoot,
            $cleanArchiveWorkspace,
            $expectedFiles,
            $gitEnvironment,
        ) !== 'verified'
        || !is_file($cleanArchiveWorkspace . '/git-export.tar')
    ) {
        throw new RuntimeException('A clean temporary repository must verify Git-export parity.');
    }

    $trackedRoot = $workspace . '/git-export-tracked-dirty';
    $trackedArchiveWorkspace = $workspace . '/git-export-tracked-dirty-archive';
    $trackedExpectedFiles = createGitExportParityFixture(
        $trackedRoot,
        $emptyHooks,
        $gitEnvironment,
    );
    writeFile($trackedRoot . '/included.txt', "PrivateTrackedSourceMarker\n");

    if (
        !mkdir($trackedArchiveWorkspace, 0700)
        || verifyExportPolicies(
            $trackedRoot,
            $trackedArchiveWorkspace,
            $trackedExpectedFiles,
            $gitEnvironment,
        ) !== 'skipped-dirty'
        || file_exists($trackedArchiveWorkspace . '/git-export.tar')
    ) {
        throw new RuntimeException('A tracked dirty temporary repository must skip Git-export parity.');
    }

    $stagedRoot = $workspace . '/git-export-staged-dirty';
    $stagedArchiveWorkspace = $workspace . '/git-export-staged-dirty-archive';
    $stagedExpectedFiles = createGitExportParityFixture(
        $stagedRoot,
        $emptyHooks,
        $gitEnvironment,
    );
    writeFile($stagedRoot . '/included.txt', "PrivateStagedSourceMarker\n");
    $stageResult = runProcess(
        ['git', 'add', '--force', 'included.txt'],
        $stagedRoot,
        $gitEnvironment,
    );

    if (
        $stageResult['exit_code'] !== 0
        || !mkdir($stagedArchiveWorkspace, 0700)
        || verifyExportPolicies(
            $stagedRoot,
            $stagedArchiveWorkspace,
            $stagedExpectedFiles,
            $gitEnvironment,
        ) !== 'skipped-dirty'
        || file_exists($stagedArchiveWorkspace . '/git-export.tar')
    ) {
        throw new RuntimeException('A staged dirty temporary repository must skip Git-export parity.');
    }

    $untrackedRoot = $workspace . '/git-export-untracked-dirty';
    $untrackedArchiveWorkspace = $workspace . '/git-export-untracked-dirty-archive';
    $untrackedExpectedFiles = createGitExportParityFixture(
        $untrackedRoot,
        $emptyHooks,
        $gitEnvironment,
    );
    $untrackedFilename = 'PrivateUntrackedFilename.marker';
    writeFile($untrackedRoot . '/' . $untrackedFilename, "PrivateUntrackedSourceMarker\n");

    if (
        !mkdir($untrackedArchiveWorkspace, 0700)
        || verifyExportPolicies(
            $untrackedRoot,
            $untrackedArchiveWorkspace,
            $untrackedExpectedFiles,
            $gitEnvironment,
        ) !== 'skipped-dirty'
        || file_exists($untrackedArchiveWorkspace . '/git-export.tar')
    ) {
        throw new RuntimeException('An untracked dirty temporary repository must skip Git-export parity.');
    }

    $verifiedLine = gitExportParityResultLine(count($expectedFiles), 'verified');
    $skippedLine = gitExportParityResultLine(count($expectedFiles), 'skipped-dirty');

    if (
        $verifiedLine
            !== "PASS isolated consumer: 3 release files, clean install, complete check, and adversarial controls; git-export-parity=verified\n"
        || $skippedLine
            !== "PASS isolated consumer development checks: 3 release files, clean install, complete check, and adversarial controls; git-export-parity=skipped-dirty; not release evidence\n"
        || str_contains($skippedLine, $workspace)
        || str_contains($skippedLine, $untrackedFilename)
        || str_contains($skippedLine, 'PrivateUntrackedSourceMarker')
    ) {
        throw new RuntimeException('Git-export parity result lines must be exact, bounded, and non-disclosing.');
    }

    $statusFailureRoot = $workspace . '/git-export-status-failure';
    $statusFailureWorkspace = $workspace . '/git-export-status-failure-archive';
    $statusExpectedFiles = createGitExportParityFixture(
        $statusFailureRoot,
        $emptyHooks,
        $gitEnvironment,
    );
    $statusFailureEnvironment = $gitEnvironment;
    $statusFailureEnvironment['GIT_DIR'] = $workspace . '/PrivateMissingGitDirectory';

    if (!mkdir($statusFailureWorkspace, 0700)) {
        throw new RuntimeException('Unable to create the Git-status failure workspace.');
    }

    try {
        verifyExportPolicies(
            $statusFailureRoot,
            $statusFailureWorkspace,
            $statusExpectedFiles,
            $statusFailureEnvironment,
        );
    } catch (RuntimeException $failure) {
        if ($failure->getMessage() === 'Unable to determine whether the Git export can be verified.') {
            $statusFailure = true;
        }
    }

    if (!isset($statusFailure)) {
        throw new RuntimeException('Git status inspection failure must remain fixed and non-disclosing.');
    }

    $creationFailureRoot = $workspace . '/git-export-creation-failure';
    $creationFailureWorkspace = $workspace . '/git-export-creation-failure-archive';
    $creationExpectedFiles = createGitExportParityFixture(
        $creationFailureRoot,
        $emptyHooks,
        $gitEnvironment,
    );

    if (
        !mkdir($creationFailureWorkspace, 0700)
        || !mkdir($creationFailureWorkspace . '/git-export.tar', 0700)
    ) {
        throw new RuntimeException('Unable to create the Git-archive failure workspace.');
    }

    try {
        verifyExportPolicies(
            $creationFailureRoot,
            $creationFailureWorkspace,
            $creationExpectedFiles,
            $gitEnvironment,
        );
    } catch (RuntimeException $failure) {
        if ($failure->getMessage() === 'Git release-archive creation failed.') {
            $creationFailure = true;
        }
    }

    if (!isset($creationFailure)) {
        throw new RuntimeException('Git archive creation failure must remain fixed and non-disclosing.');
    }

    $readFailureRoot = $workspace . '/git-export-read-failure';
    $readFailureWorkspace = $workspace . '/git-export-read-failure-archive';
    $readExpectedFiles = createGitExportParityFixture(
        $readFailureRoot,
        $emptyHooks,
        $gitEnvironment,
    );

    if (
        !mkdir($readFailureWorkspace, 0700)
        || !symlink('/dev/null', $readFailureWorkspace . '/git-export.tar')
    ) {
        throw new RuntimeException('Unable to create the Git-archive read-failure workspace.');
    }

    try {
        verifyExportPolicies(
            $readFailureRoot,
            $readFailureWorkspace,
            $readExpectedFiles,
            $gitEnvironment,
        );
    } catch (RuntimeException $failure) {
        if ($failure->getMessage() === 'Git release-archive inspection failed.') {
            $readFailure = true;
        }
    }

    if (!isset($readFailure)) {
        throw new RuntimeException('Git archive inspection failure must remain fixed and non-disclosing.');
    }

    $comparisonFailureRoot = $workspace . '/git-export-comparison-failure';
    $comparisonFailureWorkspace = $workspace . '/git-export-comparison-failure-archive';
    $comparisonExpectedFiles = createGitExportParityFixture(
        $comparisonFailureRoot,
        $emptyHooks,
        $gitEnvironment,
    );

    if (!mkdir($comparisonFailureWorkspace, 0700)) {
        throw new RuntimeException('Unable to create the Git-archive comparison-failure workspace.');
    }

    try {
        verifyExportPolicies(
            $comparisonFailureRoot,
            $comparisonFailureWorkspace,
            [...$comparisonExpectedFiles, 'missing.txt'],
            $gitEnvironment,
        );
    } catch (RuntimeException $failure) {
        if (str_starts_with($failure->getMessage(), 'Framework archive inventory changed.')) {
            $comparisonFailure = true;
        }
    }

    if (!isset($comparisonFailure)) {
        throw new RuntimeException('Git archive comparison failure must remain a hard failure.');
    }
}

/**
 * @param list<string> $expectedFiles
 * @param array<string, string> $environment
 * @return 'verified'|'skipped-dirty'
 */
function verifyExportPolicies(
    string $root,
    string $workspace,
    array $expectedFiles,
    array $environment,
): string {
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

    if ($status['exit_code'] !== 0) {
        throw new RuntimeException('Unable to determine whether the Git export can be verified.');
    }

    if (trim($status['stdout']) !== '') {
        return 'skipped-dirty';
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

    if ($gitArchive['exit_code'] !== 0) {
        throw new RuntimeException('Git release-archive creation failed.');
    }

    try {
        $gitFiles = archiveFiles($gitArchivePath);
    } catch (Throwable) {
        throw new RuntimeException('Git release-archive inspection failed.');
    }

    if ($gitFiles !== $expectedFiles) {
        throw new RuntimeException(inventoryDifference($expectedFiles, $gitFiles));
    }

    return 'verified';
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
