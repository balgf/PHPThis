# Application-owned configuration

PHPThis applications use one application-owned boundary to turn external deployment inputs into final readonly typed values. PHPThis supplies no configuration service, generic bag, `config()` helper, facade, container binding, provider, discovery rule, dotenv loader, secret-manager adapter, or framework configuration directory.

## Scope database setup before implementation

Selecting a database engine does not by itself authorize a connection attempt or probe, server provisioning, database or role creation, schema changes, migrations, or production operations. Inspect the prompt and current application context first. When no environment is named, use local development only as the working context; it does not grant permission to perform external database I/O, install packages, start services or containers, or mutate a database. A current not-applicable marker describes present behavior and does not resolve intent for a new adoption request.

Resolve these two choices independently before external database I/O or mutation:

1. **Database scope:** add configuration structure only, connect to an existing server, or provision a project-local server.
2. **Schema scope:** defer migrations or add an application-owned migration foundation.

Ask every unresolved scope choice in one concise message. Do not repeat a choice already resolved by the prompt or an explicit accepted project decision. Once scope is selected, ask only for concrete facts genuinely missing from that path, such as existing-server connection input names.

For the frozen prompt:

> Please setup PostgreSQL as our main DB.

the expected clarification is:

> Before I change anything: should I only add PostgreSQL configuration, connect this project to an existing PostgreSQL server, or provision a project-local PostgreSQL server? Should migrations remain deferred, or should I add an application-owned migration foundation too?

An explicit request such as “Provision a project-local PostgreSQL server, configure it, and do not add migrations” proceeds without that scope question. Production hardening, backups, high availability, deployment credentials, recovery, and unrelated operations remain excluded unless requested.

| Selected scope | Work and evidence allowed by that selection |
| --- | --- |
| Configuration structure only | Record the non-secret input contract and add its typed parser or factory with parsing, failure, redaction, and child-process evidence. Do not create dead infrastructure wiring, construct a connection, or require server integration. Record composition injection as deferred until connection scope is selected. |
| Connect to an existing server | Add the typed boundary and visible connection composition, then exercise only the approved connectivity, authority, and integration evidence. Do not provision the server. |
| Provision a project-local server | Add the explicitly approved local lifecycle and authority work plus its configuration and integration evidence. Do not infer production suitability. |
| Add a migration foundation | Follow ADR 043's universal application-owned invariants. For SQLite, ADR 027 is the recorded reference proof. For PostgreSQL or another engine, first record the exact accepted initial baseline, ledger-consistency boundary for all checksum-covered effects, transaction and DDL atomicity limits, every supported coordination topology and shared exclusion or authority gate, separately scoped authority/configuration per history, partial-failure recovery, and integration evidence; do not port the SQLite recipe or invent a portable framework migration API. If migrations are deferred, do not create elevated factories, credentials, commands, schema files, or migration tests. |
| Production operations | Add only the specifically requested deployment, backup, availability, credential, or recovery evidence. Local success is not production proof. |

## Canonical process-environment access

When an application uses process environment, every read in the Composer project must occur in one PHP file and use exactly:

```php
\getenv('APP_RUNTIME_DATABASE_DSN')
```

`PHT007` requires one positional non-empty uppercase literal key of at most 128 bytes. Several named reads and several process-specific factories may share the selected file. The application chooses and records its path; PHPThis does not require `config/` or a particular class name.

The application checker rejects a standalone `NOT_APPLICABLE(CONFIGURATION)` marker in `.ai/configuration.md` when PHT007 records any process-environment read. Replace that marker with the completed non-secret configuration boundary contract.

The rule rejects a second reading file, unqualified direct `getenv`, non-literal arguments, imports, literal callable indirection passed directly as a positional or named argument to supported native callback APIs under their built-in names, or held in local literal assignments later invoked directly before another assignment-operator occurrence, including through explicit closure/arrow capture, `$_ENV`, direct, imported, or aliased references to the global `INPUT_ENV` constant, literal `constant('INPUT_ENV')` and `constant('\\INPUT_ENV')` lookups, `putenv`, Apache environment functions, indexed `$_SERVER`, and other bare `$_SERVER` uses. Dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, and local callable variables passed onward as callback arguments remain review limitations. Variables implicitly reassigned through `foreach`, destructuring, or by-reference mutation may be conservatively reported from their earlier literal assignment. An application-defined unqualified function or `Closure` class deliberately sharing a supported native callback name is conservatively treated as that native callback by this structural rule. Bare `$_SERVER` is accepted only in the exact `$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)` transport tuple in `public/index.php`; downstream misuse after that handoff remains an application review and test concern.

Read once means once during each entrypoint/bootstrap composition, not once for an entire PHP-FPM deployment. A long-running worker chooses and records whether rotation requires process restart; it does not silently reread values during behavior.

## Typed source pattern

Keep process-specific factories in the one environment-reading file. Each factory reads only its process-specific external inputs and returns only that profile's validated credentials and other typed values. The complete example below illustrates one runtime profile and one single-history migration profile. When migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests rather than creating an unused elevated path. When several histories are adopted, give each history its own exact input set, final readonly type, factory, entrypoint, and no-fallback tests; never return combined migration credentials from one type or let one history call another's factory.

```php
<?php

declare(strict_types=1);

namespace App\Configuration;

use InvalidArgumentException;

final readonly class RuntimeDatabaseConfiguration
{
    public function __construct(
        public string $dsn,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}

final readonly class MigrationDatabaseConfiguration
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
    public static function forHttp(): RuntimeDatabaseConfiguration
    {
        return new RuntimeDatabaseConfiguration(
            self::required(\getenv('APP_RUNTIME_DATABASE_DSN'), 2_048),
            self::required(\getenv('APP_RUNTIME_DATABASE_USERNAME'), 128),
            self::required(\getenv('APP_RUNTIME_DATABASE_PASSWORD'), 1_024),
        );
    }

    public static function forMigrations(): MigrationDatabaseConfiguration
    {
        return new MigrationDatabaseConfiguration(
            self::required(\getenv('APP_MIGRATION_DATABASE_DSN'), 2_048),
            self::required(\getenv('APP_MIGRATION_DATABASE_USERNAME'), 128),
            self::required(\getenv('APP_MIGRATION_DATABASE_PASSWORD'), 1_024),
        );
    }

    private static function required(#[\SensitiveParameter] string|false $value, int $maximumBytes): string
    {
        if ($value === false || $value === '' || strlen($value) > $maximumBytes) {
            throw new InvalidArgumentException('Required application configuration is invalid.');
        }

        return $value;
    }
}
```

These names and bounds illustrate the structure; each application owns its real names, grammar, limits, and types. Validate ports, hosts, DSNs, enums, durations, paths, URLs, identifiers, and finite modes according to their actual contract. A database DSN excludes embedded username, password, token, or private-key material; pass separately named credentials through the explicitly sensitive parameters. Do not postpone narrowing by returning `array<string, mixed>` or a string-keyed getter.

HTTP calls only `forHttp()`. In the illustrated single-history case, that history's command calls only `forMigrations()` and neither profile falls back to the other. A multi-history application uses one separately named factory and final type per history, and each finite command composes only its own profile. If a deployment explicitly needs administrative authority, give it another exact input set, final readonly type, factory, entrypoint, and test; never expose it to HTTP composition or combine it with a migration profile.

Typed separation proves only which values each entrypoint can receive. It does not create database identities, activate authority, establish a namespace or object control-or-ownership model, or prove that the runtime can execute application statements. When connection scope is selected, record engine-specific effective authority, accountable transition ownership, and verification in `.ai/data.md`; record migration-owned transition implementation and per-history constraints in `.ai/migrations.md`; and record the pre-traffic release sequence plus operational runbooks in `.ai/operations.md`. See [ADR 038](decisions/038-application-owned-database-authority-lifecycle.md).

## Explicit composition when connection scope is selected

When connection scope is selected, the composition root receives the final typed value and constructs infrastructure visibly. Configuration-only scope stops after the tested typed boundary and records this connection composition as deferred:

```php
$configuration = ApplicationEnvironment::forHttp();
$budget = new QueryBudget(12);
$trace = new QueryTrace(32);
$connection = Connection::connect(
    $configuration->dsn,
    $budget,
    $trace,
    $configuration->username,
    $configuration->password,
);
```

### Eager composition and probe semantics

`Connection::connect()` constructs native `PDO` immediately rather than returning a deferred handle. Depending on the selected driver and DSN, construction may perform database, filesystem, or network I/O and may fail during composition. When a shared HTTP composition root opens a connection that requires an external service, every route behind that root inherits that requirement. In the current starter front-controller shape, composition completes before the terminal request-summary coordinator handles a request. A composition failure therefore occurs outside that coordinator and receives none of its application `Response`, `X-Request-ID`, or terminal-summary guarantees. An application that selects another outer failure policy records and tests that exact behavior; PHPThis supplies no hidden fallback.

Use precise operational claims:

- **External-service-independent liveness** says the selected process or application execution boundary is alive without requiring any separately operated service to succeed. Record every shared-bootstrap requirement and synchronous request-path destination, including logging or summary sinks, even when it is local or in-process.
- **Shared-bootstrap startup viability** says the selected composition root and probe handler could be constructed and executed, including every eager dependency they share.
- **Readiness** says the application satisfies its explicitly recorded conditions for receiving traffic. Those conditions may include dependencies and exact operation requirements, but PHPThis does not choose them.
- **Dependency health** reports only the named dependency behavior it actually exercises.

A route behind a shared bootstrap that eagerly opens a required external-service connection is not external-service-independent liveness. For example, this applies when the selected DSN connects to a network database; it does not classify an in-memory or local-file driver as an external service. Successful connection construction is also not evidence of schema compatibility, migration completion, capacity, per-operation database authority, or complete application readiness. The database contract requires exact-statement authority evidence separately.

Record each adopted HTTP or non-HTTP probe in `.ai/operations.md`: its exact claim, inherited bootstrap and synchronous dependencies, destinations, bounded work, failure response or process behavior, local or deployment operations owner or explicit non-applicability, and application-test or deployment evidence. Failure isolation that preserves a selected response does not by itself bound a synchronous sink's latency or make that probe external-service-independent. A deployment-owned process probe and an HTTP probe may prove different facts. Do not disguise a dependency bypass as the ordinary application bootstrap or add a second hidden HTTP execution path.

Do not pass `ApplicationEnvironment`, a configuration reader, or a generic configuration aggregate into routes, handlers, operations, policies, or SQL owners. Pass only the concrete typed dependency that behavior needs.

## Workbench process authority

ADR 041's optional PHPThis Workbench performs no automatic configuration or infrastructure loading. Its application-owned bootstrap receives only the concrete typed configuration and dependencies that visible bootstrap source deliberately composes. Configuration-only database setup must not create dead Workbench wiring, and an adopted runtime connection does not authorize adding migration, administrative, or broader infrastructure credentials to the workspace.

Treat the combined operating-system identity, inherited environment, independently loaded child PHP CLI configuration, ambient filesystem, network, process, and service access, native PHP functions and Composer-autoloaded code, and explicitly composed dependencies as the authority boundary. The narrow workspace limits the intended application surface; it is not containment against arbitrary PHP. Use a dedicated development profile without fallback and exclude production, migration, administrative, and unrelated secrets. Each expression child independently loads the PHP CLI configuration for its fresh `PHP_BINARY` invocation; parent runtime `ini_set()` changes and parent-launch `-d` options do not carry into the child and are not authority controls. An environment label, debug flag, local hostname, or `.env` filename is not an authority check. If an experiment needs a real development side effect, record and inject only that operation's narrowly typed dependency and least authority; never expose a configuration bag, credential object, raw elevated connection, or secret lookup through `$workspace`.

## Secrets and local development

Real values come from the deployment environment or an application-owned, explicitly selected secret-delivery path. Environment variables are delivery, not encryption. Never commit actual secret or deployment values to PHP, JSON, YAML, Markdown, `.ai/`, `.env.example`, fixtures, snapshots, logs, traces, exception messages, or command output.

Keep `.env` and `.env.*` ignored, with only `.env.example` allowed. If present, `.env.example` lists exact names and obviously non-secret local placeholders. PHPThis does not load it; a consumer may choose a development-only loader explicitly at its outer process boundary, but the resulting application source must still preserve the single typed boundary and must not add a framework dotenv dependency.

When local developers need a checked command that reads a non-shell `.env` without manually exporting every value, use the [application-owned local environment launcher reference](configuration/local-environment-launcher.md). It defines a finite command-to-profile map, literal bounded file grammar, complete-profile precedence without merging, opposite-authority removal, fixed redacted failures, out-of-directory invocation, and executable subprocess evidence. The launcher remains application source outside PHPThis runtime; it is not a dotenv API, configuration cache, `config:clear` facility, or production secret-delivery mechanism.

Mark constructor or function parameters that carry passwords, tokens, or private keys with `#[\SensitiveParameter]`. This reduces accidental stack-trace exposure only. Do not log, dump, serialize, interpolate, or include the typed configuration in request summaries.

## Required evidence

For every implemented configuration parser or factory, application tests prove:

- valid external values create the exact final readonly types;
- after ordinary source/autoload loading, missing, empty, malformed, and oversized values fail before application-controlled database, network, writable-filesystem, migration, or business I/O;
- failure output is fixed and contains none of the supplied bytes;
- child-process environment injection exercises the selected parser or factory, or the real process entrypoint when that process is in scope, without application `putenv` calls.

### Copyable child-process configuration evidence

The following is application-owned test code, not a PHPThis helper or required filename. This example assumes the file is placed under `tests/` and that `tests/fixtures/runtime-configuration-entrypoint.php` loads ordinary source or Composer autoloading, invokes one adopted runtime parser or factory, writes exactly `CONFIGURATION_OK` on success, and maps only its known validation failure to the fixed rejection below. Configuration-only scope keeps that fixture parser-only and performs no application-controlled infrastructure or business I/O. If a real process entrypoint is already in scope, point the fixed command at that entrypoint instead.

```php
<?php

declare(strict_types=1);

/** @param resource $stream */
function readConfigurationProcessStream($stream): string
{
    $output = stream_get_contents($stream);

    if (!is_string($output)) {
        throw new RuntimeException('Unable to read configuration evidence process output.');
    }

    return $output;
}

/**
 * @param array<string, string> $environment
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runConfigurationProcess(
    string $entrypoint,
    string $workingDirectory,
    array $environment,
): array {
    $process = proc_open(
        [PHP_BINARY, $entrypoint],
        [0 => ['pipe', 'rb'], 1 => ['pipe', 'wb'], 2 => ['pipe', 'wb']],
        $pipes,
        $workingDirectory,
        $environment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start configuration evidence process.');
    }

    fclose($pipes[0]);

    try {
        $stdout = readConfigurationProcessStream($pipes[1]);
        $stderr = readConfigurationProcessStream($pipes[2]);
    } finally {
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    }

    return ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireExactConfigurationProcessResult(
    array $result,
    int $exitCode,
    string $stdout,
    string $stderr,
): void {
    if (
        $result['exit_code'] !== $exitCode
        || $result['stdout'] !== $stdout
        || $result['stderr'] !== $stderr
    ) {
        throw new RuntimeException('Configuration process result changed.');
    }
}

/** @param array{exit_code: int, stdout: string, stderr: string} $result */
function requireConfigurationOutputExcludes(array $result, string $sentinel): void
{
    if ($sentinel === '' || str_contains($result['stdout'] . $result['stderr'], $sentinel)) {
        throw new RuntimeException('Configuration rejection disclosed supplied bytes.');
    }
}

/**
 * @param array<string, string> $environment
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function requireRejectedConfiguration(
    string $entrypoint,
    string $workingDirectory,
    array $environment,
): array {
    $result = runConfigurationProcess(
        $entrypoint,
        $workingDirectory,
        $environment,
    );
    requireExactConfigurationProcessResult(
        $result,
        2,
        '',
        "CONFIGURATION_INVALID\n",
    );

    return $result;
}

$projectRoot = dirname(__DIR__);
$entrypoint = __DIR__ . '/fixtures/runtime-configuration-entrypoint.php';
$validEnvironment = [
    'APP_RUNTIME_MODE' => 'synthetic',
    'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
    'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
];

$validResult = runConfigurationProcess(
    $entrypoint,
    $projectRoot,
    $validEnvironment,
);
requireExactConfigurationProcessResult($validResult, 0, "CONFIGURATION_OK\n", '');

$rejectedEnvironments = [
    [
        'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
        'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
    ],
    [
        'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
        'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
        '' => 'APP_RUNTIME_MODE=',
    ],
    [
        'APP_RUNTIME_MODE' => 'synthetic',
        'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
    ],
    [
        'APP_RUNTIME_MODE' => 'synthetic',
        'APP_RUNTIME_CREDENTIAL' => 'synthetic-non-secret-credential',
        '' => 'APP_RUNTIME_ENDPOINT=',
    ],
    [...$validEnvironment, 'APP_RUNTIME_ENDPOINT' => 'not-an-endpoint'],
    [
        ...$validEnvironment,
        'APP_RUNTIME_ENDPOINT' => 'https://' . str_repeat('e', 121),
    ],
    [
        'APP_RUNTIME_MODE' => 'synthetic',
        'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
    ],
    [
        'APP_RUNTIME_MODE' => 'synthetic',
        'APP_RUNTIME_ENDPOINT' => 'https://example.invalid',
        '' => 'APP_RUNTIME_CREDENTIAL=',
    ],
    [...$validEnvironment, 'APP_RUNTIME_CREDENTIAL' => str_repeat('x', 65)],
];

foreach ($rejectedEnvironments as $rejectedEnvironment) {
    requireRejectedConfiguration($entrypoint, $projectRoot, $rejectedEnvironment);
}

$sentinel = 'synthetic-rejected-value-must-not-appear';
$malformedResult = requireRejectedConfiguration(
    $entrypoint,
    $projectRoot,
    [...$validEnvironment, 'APP_RUNTIME_MODE' => $sentinel],
);
requireConfigurationOutputExcludes($malformedResult, $sentinel);

fwrite(STDOUT, "PASS child-process configuration evidence\n");
```

The command is an array containing only code-owned executable and entrypoint paths. Its binary pipe descriptors, working directory, fifth `proc_open` environment argument, and `bypass_shell` option are explicit. That fifth argument supplies the application's explicit child environment instead of requesting null inheritance: put only the synthetic non-secret configuration values the selected fixture needs in this application-supplied map, use an absolute `PHP_BINARY`, and do not copy parent deployment credentials through `getenv`, `$_ENV`, `$_SERVER`, or configuration values in command arguments. The host, executable, or PHP runtime may still add its own required environment entries, so this proves absence of deliberate parent-configuration inheritance rather than exclusive ownership of the final operating-system environment block.

This deliberately small helper uses blocking pipes sequentially. It is suitable only for a short-lived fixture whose contract permits tiny fixed output before exit, as shown here. The application test runner or CI job owns the hard outer timeout. If an adopted real entrypoint can stream, remain resident, or emit enough data to fill either pipe, the application needs its own reviewed capture and termination strategy. Do not grow this configuration example into a general process runner, worker, or supervisor.

PHP 8.4 omits a named `proc_open` environment entry when its value is the empty string. It treats an empty-string array key as a raw environment entry, so the explicit `'' => 'APP_RUNTIME_MODE='` form above delivers a code-owned named input with an empty value instead of omitting it while retaining the `array<string, string>` shape. This is pinned PHP 8.4 implementation behavior rather than a general environment-array convention; retain an executable exact-empty-delivery probe when adapting the pattern or changing the supported PHP runtime. Keep each raw name literal and application-owned; never construct a raw environment entry from request, command, or other submitted input.

Adapt the exact names, valid grammar, byte bounds, output bytes, fixture path, and test integration to the application. Run a fresh child for the valid case and each required key's missing and empty cases. Also run representative malformed and maximum-plus-one cases wherever that key's recorded grammar or byte bound defines them; assert the exact exit, stdout, and stderr each time. The sentinel assertion proves only that those supplied bytes are absent from the two captured streams. It does not prove redaction from operating-system metadata, deployment logs, files, network destinations, exception sinks, or unrelated values.

Test each actually adopted runtime, worker, migration-history, or administrative entrypoint independently. Poison the other adopted profiles' values and omit each required value to prove that no profile reads or falls back to another, including between two migration histories. Do not create an elevated or otherwise unused profile merely to copy this example. When an invalid real entrypoint would otherwise reach application-controlled I/O, use the application's existing concrete recording seam or observable boundary to prove zero calls; do not add a framework interceptor or generic I/O abstraction for the test.

When connection or another infrastructure composition is selected, tests additionally prove that composition passes the intended typed values to the visible infrastructure boundary. Configuration-only scope records infrastructure injection and connection evidence as deferred and does not create dead wiring. When migration or administrative configuration is selected, tests prove that HTTP startup does not read those inputs, elevated startup has no runtime-credential fallback, and each history remains unable to obtain another history's credentials. When those profiles are not selected, record them as not applicable and do not invent elevated factories, credentials, entrypoints, or tests. Provisioning and production evidence is required only for an explicitly selected scope.

`PHT007` proves only canonical direct access, direct positional or named arguments to supported native callback consumers under their built-in names, directly invoked local literal assignments before another assignment-operator occurrence, and one-file confinement. It does not detect hard-coded secrets, dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, secret-manager APIs, leaks, correct validation, safe deployment permissions, or least database privilege.
