# Application-owned local environment launcher

This is a checked, copyable reference for an application that wants one local-development command without adding a dotenv dependency, framework configuration API, configuration cache, or `config:clear` command. It is application source, not PHPThis runtime source. Production and supervised processes should receive a complete process profile from their deployment environment and should not depend on a project `.env` file.

Run the application-owned launcher from any directory with an absolute or relative launcher path:

```bash
php ./bin/application jobs:run-one
php ./bin/application database:migrate
```

The reference supports exactly these commands and profiles:

| Command | Selected profile | Exact child-process inputs |
| --- | --- | --- |
| `jobs:run-one` | worker | `APP_WORKER_DATABASE_DSN`, `APP_WORKER_DATABASE_USERNAME`, `APP_WORKER_DATABASE_PASSWORD` |
| `database:migrate` | migration | `APP_MIGRATION_DATABASE_DSN`, `APP_MIGRATION_DATABASE_USERNAME`, `APP_MIGRATION_DATABASE_PASSWORD` |

Adapt the finite command map, literal process-environment reads, and allowlist together when an application adopts different processes. Do not turn them into a dynamic command registry, generic configuration bag, or second configuration boundary.

## Exact selection and local-file contract

The launcher parses the command before preparation. It then resolves the canonical application root, a readable regular non-symlink private child, and the validated absolute `PHP_BINARY` before configuration may open the application-root `.env`.

A complete selected inherited profile wins and `.env` is not opened. A selected inherited profile with one or two present inputs fails; values are never filled from the file. If all three selected inherited inputs are absent, `.env` must provide one complete selected profile. Every selected inherited value and every represented local value receives the same transport validation: 1 to 4,096 visible ASCII bytes (`0x20` through `0x7e`). The private child retains responsibility for narrower DSN, username, credential, and application-semantic validation.

`.env` must be a readable regular non-symlink file. The boundary compares its `lstat` and opened-handle `fstat` device and inode, opens it once, and reads at most 65,537 bytes; 65,537 bytes means the 65,536-byte contract was exceeded. The file contains at most 256 physical LF- or CRLF-terminated lines. The final line may omit its LF, but every carriage return must be followed by LF. A line payload, excluding its LF or CRLF terminator, is at most 4,225 bytes.

An empty physical line is allowed. A line whose first byte is `#` is a comment and otherwise remains subject to the file, line, and visible-ASCII bounds. Leading-space comments and inline comments do not exist. Every other line is exactly `KEY=value`: the first `=` is the separator, the key matches `[A-Z][A-Z0-9_]{0,127}`, and the value follows the transport bound. Values are opaque; spaces, semicolons, quotes, additional equals signs, dollar signs, command-substitution text, backticks, pipes, and redirection characters are never evaluated or interpolated.

Only the six inputs in the command table are accepted. Unknown keys, duplicates, malformed lines, partial represented profiles, control bytes, DEL, bare carriage returns, unsupported syntax such as `export KEY=value`, and exceeded bounds produce the same fixed redacted preparation failure. A represented non-selected local profile must be absent or complete; it is validated but is not passed to the child.

The selected profile is the complete environment map supplied to the private child. The launcher uses array-form `proc_open`, inherited standard-stream resources, fixed application-root working directory, and `bypass_shell`; configuration is never placed in argv. A worker child receives no migration inputs and a migration child receives no worker inputs. The child exit code is propagated. Launcher argument and preparation failures remain fixed and redacted. After a child starts, it owns the streams; the launcher does not append another error line. If the runtime cannot report a valid child status, the launcher exits `1` silently.

## Copyable `bin/application` reference

```php
<?php

declare(strict_types=1);

use App\Configuration\ApplicationEnvironment;

function launcherInvalidArguments(): never
{
    fwrite(STDERR, "{\"error\":\"invalid_arguments\"}\n");
    exit(2);
}

function launcherUnknownCommand(): never
{
    fwrite(STDERR, "{\"error\":\"unknown_command\"}\n");
    exit(2);
}

function launcherPreparationFailed(): never
{
    fwrite(STDERR, "{\"error\":\"local_environment_failed\"}\n");
    exit(1);
}

/** @param array<array-key, mixed> $stat */
function launcherStatIsRegular(array $stat): bool
{
    $mode = $stat['mode'] ?? null;

    return is_int($mode) && ($mode & 0170000) === 0100000;
}

function launcherRequireReadableRegularFile(string $path): void
{
    $pathStat = @lstat($path);

    if (!is_array($pathStat) || !launcherStatIsRegular($pathStat)) {
        throw new RuntimeException('Launcher preparation failed.');
    }

    $stream = @fopen($path, 'rb');

    if (!is_resource($stream)) {
        throw new RuntimeException('Launcher preparation failed.');
    }

    try {
        $streamStat = fstat($stream);

        if (
            !is_array($streamStat)
            || !launcherStatIsRegular($streamStat)
            || ($pathStat['dev'] ?? null) !== ($streamStat['dev'] ?? null)
            || ($pathStat['ino'] ?? null) !== ($streamStat['ino'] ?? null)
        ) {
            throw new RuntimeException('Launcher preparation failed.');
        }
    } finally {
        fclose($stream);
    }
}

if (
    !isset($argv)
    || !is_array($argv)
    || count($argv) !== 2
    || !isset($argv[1])
    || !is_string($argv[1])
) {
    launcherInvalidArguments();
}

$command = $argv[1];

if ($command !== 'jobs:run-one' && $command !== 'database:migrate') {
    launcherUnknownCommand();
}

try {
    $root = realpath(dirname(__DIR__));

    if (!is_string($root)) {
        throw new RuntimeException('Launcher preparation failed.');
    }

    $child = $root . '/bin/console.php';
    launcherRequireReadableRegularFile($child);

    $phpBinary = realpath(PHP_BINARY);

    if (
        !is_string($phpBinary)
        || !is_file($phpBinary)
        || !is_executable($phpBinary)
    ) {
        throw new RuntimeException('Launcher preparation failed.');
    }

    $autoload = $root . '/vendor/autoload.php';
    launcherRequireReadableRegularFile($autoload);
    require $autoload;

    if ($command === 'jobs:run-one') {
        $configuration = ApplicationEnvironment::workerForLocalLauncher($root);

        $childEnvironment = [
            'APP_WORKER_DATABASE_DSN' => $configuration->dsn,
            'APP_WORKER_DATABASE_USERNAME' => $configuration->username,
            'APP_WORKER_DATABASE_PASSWORD' => $configuration->password,
        ];
    } else {
        $configuration = ApplicationEnvironment::migrationsForLocalLauncher($root);

        $childEnvironment = [
            'APP_MIGRATION_DATABASE_DSN' => $configuration->dsn,
            'APP_MIGRATION_DATABASE_USERNAME' => $configuration->username,
            'APP_MIGRATION_DATABASE_PASSWORD' => $configuration->password,
        ];
    }

    $process = @proc_open(
        [$phpBinary, $child, $command],
        [0 => STDIN, 1 => STDOUT, 2 => STDERR],
        $pipes,
        $root,
        $childEnvironment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Launcher preparation failed.');
    }

    $exitCode = proc_close($process);

    if ($exitCode < 0 || $exitCode > 255) {
        exit(1);
    }

    exit($exitCode);
} catch (Throwable) {
    launcherPreparationFailed();
}
```

## Copyable `src/Configuration/ApplicationEnvironment.php` reference

```php
<?php

declare(strict_types=1);

namespace App\Configuration;

use InvalidArgumentException;

final readonly class WorkerLauncherTransport
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final readonly class MigrationLauncherTransport
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final class ApplicationEnvironment
{
    /** @var non-empty-list<non-empty-string> */
    private const array WORKER_NAMES = [
        'APP_WORKER_DATABASE_DSN',
        'APP_WORKER_DATABASE_USERNAME',
        'APP_WORKER_DATABASE_PASSWORD',
    ];

    /** @var non-empty-list<non-empty-string> */
    private const array MIGRATION_NAMES = [
        'APP_MIGRATION_DATABASE_DSN',
        'APP_MIGRATION_DATABASE_USERNAME',
        'APP_MIGRATION_DATABASE_PASSWORD',
    ];

    public static function workerForLocalLauncher(string $root): WorkerLauncherTransport
    {
        $inherited = self::workerSnapshot();

        if (self::presentCount($inherited) === 3) {
            return self::workerFrom(self::requireComplete($inherited));
        }

        if (self::presentCount($inherited) !== 0) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        $local = self::localProfiles($root);

        return self::workerFrom(self::requireLocalProfile($local, self::WORKER_NAMES));
    }

    public static function migrationsForLocalLauncher(string $root): MigrationLauncherTransport
    {
        $inherited = self::migrationSnapshot();

        if (self::presentCount($inherited) === 3) {
            return self::migrationFrom(self::requireComplete($inherited));
        }

        if (self::presentCount($inherited) !== 0) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        $local = self::localProfiles($root);

        return self::migrationFrom(self::requireLocalProfile($local, self::MIGRATION_NAMES));
    }

    /** @return array<non-empty-string, string|false> */
    private static function workerSnapshot(): array
    {
        return [
            'APP_WORKER_DATABASE_DSN' => \getenv('APP_WORKER_DATABASE_DSN'),
            'APP_WORKER_DATABASE_USERNAME' => \getenv('APP_WORKER_DATABASE_USERNAME'),
            'APP_WORKER_DATABASE_PASSWORD' => \getenv('APP_WORKER_DATABASE_PASSWORD'),
        ];
    }

    /** @return array<non-empty-string, string|false> */
    private static function migrationSnapshot(): array
    {
        return [
            'APP_MIGRATION_DATABASE_DSN' => \getenv('APP_MIGRATION_DATABASE_DSN'),
            'APP_MIGRATION_DATABASE_USERNAME' => \getenv('APP_MIGRATION_DATABASE_USERNAME'),
            'APP_MIGRATION_DATABASE_PASSWORD' => \getenv('APP_MIGRATION_DATABASE_PASSWORD'),
        ];
    }

    /** @param array<non-empty-string, string|false> $profile */
    private static function presentCount(array $profile): int
    {
        return count(array_filter($profile, static fn (string|false $value): bool => $value !== false));
    }

    /**
     * @param array<non-empty-string, string|false> $profile
     * @return array<non-empty-string, non-empty-string>
     */
    private static function requireComplete(array $profile): array
    {
        if (self::presentCount($profile) !== 3) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        $complete = [];

        foreach ($profile as $name => $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Required application configuration is invalid.');
            }

            $complete[$name] = self::transportValue($value);
        }

        return $complete;
    }

    /**
     * @param array<non-empty-string, non-empty-string> $profiles
     * @param non-empty-list<non-empty-string> $names
     * @return array<non-empty-string, non-empty-string>
     */
    private static function requireLocalProfile(array $profiles, array $names): array
    {
        $selected = [];

        foreach ($names as $name) {
            if (!isset($profiles[$name])) {
                throw new InvalidArgumentException('Required application configuration is invalid.');
            }

            $selected[$name] = $profiles[$name];
        }

        return $selected;
    }

    /** @return array<non-empty-string, non-empty-string> */
    private static function localProfiles(string $root): array
    {
        $path = $root . '/.env';
        $pathStat = @lstat($path);

        if (!is_array($pathStat) || !self::statIsRegular($pathStat)) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        $stream = @fopen($path, 'rb');

        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        try {
            $streamStat = fstat($stream);

            if (
                !is_array($streamStat)
                || !self::statIsRegular($streamStat)
                || ($pathStat['dev'] ?? null) !== ($streamStat['dev'] ?? null)
                || ($pathStat['ino'] ?? null) !== ($streamStat['ino'] ?? null)
            ) {
                throw new InvalidArgumentException('Required application configuration is invalid.');
            }

            $contents = @stream_get_contents($stream, 65_537);
        } finally {
            fclose($stream);
        }

        if (!is_string($contents) || strlen($contents) > 65_536) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return self::parseLocalProfiles($contents);
    }

    /** @param array<array-key, mixed> $stat */
    private static function statIsRegular(array $stat): bool
    {
        $mode = $stat['mode'] ?? null;

        return is_int($mode) && ($mode & 0170000) === 0100000;
    }

    /** @return array<non-empty-string, non-empty-string> */
    private static function parseLocalProfiles(string $contents): array
    {
        if (preg_match('/[^\x0A\x0D\x20-\x7E]/D', $contents) !== 0) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        if (preg_match('/\r(?!\n)/D', $contents) !== 0) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        if ($contents === '') {
            $lines = [];
        } else {
            $lines = explode("\n", $contents);

            if (str_ends_with($contents, "\n")) {
                array_pop($lines);
            }
        }

        if (count($lines) > 256) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        $profiles = [];
        $allowed = [...self::WORKER_NAMES, ...self::MIGRATION_NAMES];

        foreach ($lines as $line) {
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }

            if (strlen($line) > 4_225) {
                throw new InvalidArgumentException('Required application configuration is invalid.');
            }

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                throw new InvalidArgumentException('Required application configuration is invalid.');
            }

            $name = substr($line, 0, $separator);
            $value = substr($line, $separator + 1);

            if (
                preg_match('/\A[A-Z][A-Z0-9_]{0,127}\z/D', $name) !== 1
                || !in_array($name, $allowed, true)
                || array_key_exists($name, $profiles)
            ) {
                throw new InvalidArgumentException('Required application configuration is invalid.');
            }

            $profiles[$name] = self::transportValue($value);
        }

        self::requireAbsentOrComplete($profiles, self::WORKER_NAMES);
        self::requireAbsentOrComplete($profiles, self::MIGRATION_NAMES);

        return $profiles;
    }

    /**
     * @param array<non-empty-string, non-empty-string> $profiles
     * @param non-empty-list<non-empty-string> $names
     */
    private static function requireAbsentOrComplete(array $profiles, array $names): void
    {
        $present = 0;

        foreach ($names as $name) {
            if (isset($profiles[$name])) {
                $present++;
            }
        }

        if ($present !== 0 && $present !== 3) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }
    }

    /** @return non-empty-string */
    private static function transportValue(#[\SensitiveParameter] string $value): string
    {
        if (
            $value === ''
            || strlen($value) > 4_096
            || preg_match('/\A[\x20-\x7E]+\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }

    /** @param array<non-empty-string, non-empty-string> $profile */
    private static function workerFrom(array $profile): WorkerLauncherTransport
    {
        $dsn = $profile['APP_WORKER_DATABASE_DSN'] ?? null;
        $username = $profile['APP_WORKER_DATABASE_USERNAME'] ?? null;
        $password = $profile['APP_WORKER_DATABASE_PASSWORD'] ?? null;

        if (!is_string($dsn) || !is_string($username) || !is_string($password)) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return new WorkerLauncherTransport(
            $dsn,
            $username,
            $password,
        );
    }

    /** @param array<non-empty-string, non-empty-string> $profile */
    private static function migrationFrom(array $profile): MigrationLauncherTransport
    {
        $dsn = $profile['APP_MIGRATION_DATABASE_DSN'] ?? null;
        $username = $profile['APP_MIGRATION_DATABASE_USERNAME'] ?? null;
        $password = $profile['APP_MIGRATION_DATABASE_PASSWORD'] ?? null;

        if (!is_string($dsn) || !is_string($username) || !is_string($password)) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return new MigrationLauncherTransport(
            $dsn,
            $username,
            $password,
        );
    }
}
```

## Private child handoff

`bin/console.php` remains private application composition. It accepts only the fixed command passed above. The copied `WorkerLauncherTransport`, `MigrationLauncherTransport`, and `*ForLocalLauncher()` methods are deliberately transport-only and belong only to local launcher selection. They are not final application configuration types and the private child does not call them.

Keep the application's command-specific worker and migration factories in this same canonical `ApplicationEnvironment.php` file. Each child factory directly re-reads only its own exact literal names, applies the real DSN, identity, credential, and byte grammar, and returns a separately named final readonly application type before I/O. `jobs:run-one` calls only the worker factory; `database:migrate` calls only the migration factory. The child never reads `.env`, merges profiles, falls back to another factory, receives a launcher transport object, or receives a configuration bag. This seam must be implemented for the application's actual database contract; the 1-to-4,096-byte visible-ASCII launcher bound is not semantic database validation.

Map known validation failure to fixed redacted output. Do not print DSNs, usernames, passwords, raw exception messages, environment maps, or traces.

Each invocation snapshots configuration in fresh launcher and child processes. Editing `.env` affects the next local invocation without a cache-clear command; there is no hidden in-process reload. Tests should extract both exact installed PHP blocks into a fresh mini-application and invoke the launcher through array-form `proc_open` with `bypass_shell`. Cover both command maps, inherited bypass, local fallback, every partial-profile no-merge case, duplicate and unknown keys, exact bounds, CRLF and optional-final-LF behavior, opaque metacharacters, non-execution controls, opposite-profile exclusion, process-replacement reload, out-of-directory use, fixed stream bytes, and cleanup.
