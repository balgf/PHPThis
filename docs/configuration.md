# Application-owned configuration

PHPThis applications use one application-owned boundary to turn external deployment inputs into final readonly typed values. PHPThis supplies no configuration service, generic bag, `config()` helper, facade, container binding, provider, discovery rule, dotenv loader, secret-manager adapter, or framework configuration directory.

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

Keep process-specific factories in the one environment-reading file. Each factory reads only the authority its process needs:

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

HTTP calls only `forHttp()`. A migration command calls only `forMigrations()`. Neither profile falls back to the other. If a deployment needs administrative authority, give it another exact input set, final readonly type, factory, entrypoint, and test; never expose it to HTTP composition.

## Explicit composition

The composition root receives the final typed value and constructs infrastructure visibly:

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

Do not pass `ApplicationEnvironment`, a configuration reader, or a generic configuration aggregate into routes, handlers, operations, policies, or SQL owners. Pass only the concrete typed dependency that behavior needs.

## Secrets and local development

Real values come from the deployment environment or an application-owned, explicitly selected secret-delivery path. Environment variables are delivery, not encryption. Never commit actual secret or deployment values to PHP, JSON, YAML, Markdown, `.ai/`, `.env.example`, fixtures, snapshots, logs, traces, exception messages, or command output.

Keep `.env` and `.env.*` ignored, with only `.env.example` allowed. If present, `.env.example` lists exact names and obviously non-secret local placeholders. PHPThis does not load it; a consumer may choose a development-only loader explicitly at its outer process boundary, but the resulting application source must still preserve the single typed boundary and must not add a framework dotenv dependency.

Mark constructor or function parameters that carry passwords, tokens, or private keys with `#[\SensitiveParameter]`. This reduces accidental stack-trace exposure only. Do not log, dump, serialize, interpolate, or include the typed configuration in request summaries.

## Required evidence

Application tests prove:

- valid external values create the exact final readonly types;
- after ordinary source/autoload loading, missing, empty, malformed, and oversized values fail before application-controlled database, network, writable-filesystem, migration, or business I/O;
- failure output is fixed and contains none of the supplied bytes;
- HTTP startup does not read migration or administrative inputs;
- migration startup has no runtime-credential fallback;
- composition passes the intended typed values to the visible infrastructure boundary; and
- child-process environment injection exercises the real entrypoint without application `putenv` calls.

`PHT007` proves only canonical direct access, direct positional or named arguments to supported native callback consumers under their built-in names, directly invoked local literal assignments before another assignment-operator occurrence, and one-file confinement. It does not detect hard-coded secrets, dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, secret-manager APIs, leaks, correct validation, safe deployment permissions, or least database privilege.
