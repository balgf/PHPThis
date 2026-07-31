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
| Add a migration foundation | For SQLite, follow the recorded application-owned proof. For PostgreSQL or another engine, first record an engine-specific application decision covering DDL, transactions, locking, authority, recovery, and integration evidence; do not port the SQLite recipe or invent a portable framework migration API. If migrations are deferred, do not create elevated factories, credentials, commands, schema files, or migration tests. |
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

Keep process-specific factories in the one environment-reading file. Each factory reads only the authority its process needs. The complete example below illustrates both runtime and migration profiles; when migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests rather than creating an unused elevated path.

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

HTTP calls only `forHttp()`. When migrations are adopted, their command calls only `forMigrations()` and neither profile falls back to the other. If a deployment explicitly needs administrative authority, give it another exact input set, final readonly type, factory, entrypoint, and test; never expose it to HTTP composition.

Typed separation proves only which values each entrypoint can receive. It does not create database identities, activate authority, establish a namespace or object control-or-ownership model, or prove that the runtime can execute application statements. When connection scope is selected, record those engine-specific facts and their verification in `.ai/data.md`; record migration and authority-transition ownership in `.ai/migrations.md` when adopted, and the pre-traffic release sequence in `.ai/operations.md`. See [ADR 038](decisions/038-application-owned-database-authority-lifecycle.md).

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

Do not pass `ApplicationEnvironment`, a configuration reader, or a generic configuration aggregate into routes, handlers, operations, policies, or SQL owners. Pass only the concrete typed dependency that behavior needs.

## Secrets and local development

Real values come from the deployment environment or an application-owned, explicitly selected secret-delivery path. Environment variables are delivery, not encryption. Never commit actual secret or deployment values to PHP, JSON, YAML, Markdown, `.ai/`, `.env.example`, fixtures, snapshots, logs, traces, exception messages, or command output.

Keep `.env` and `.env.*` ignored, with only `.env.example` allowed. If present, `.env.example` lists exact names and obviously non-secret local placeholders. PHPThis does not load it; a consumer may choose a development-only loader explicitly at its outer process boundary, but the resulting application source must still preserve the single typed boundary and must not add a framework dotenv dependency.

Mark constructor or function parameters that carry passwords, tokens, or private keys with `#[\SensitiveParameter]`. This reduces accidental stack-trace exposure only. Do not log, dump, serialize, interpolate, or include the typed configuration in request summaries.

## Required evidence

For every implemented configuration parser or factory, application tests prove:

- valid external values create the exact final readonly types;
- after ordinary source/autoload loading, missing, empty, malformed, and oversized values fail before application-controlled database, network, writable-filesystem, migration, or business I/O;
- failure output is fixed and contains none of the supplied bytes;
- child-process environment injection exercises the selected parser or factory, or the real process entrypoint when that process is in scope, without application `putenv` calls.

When connection or another infrastructure composition is selected, tests additionally prove that composition passes the intended typed values to the visible infrastructure boundary. Configuration-only scope records infrastructure injection and connection evidence as deferred and does not create dead wiring. When migration or administrative configuration is selected, tests prove that HTTP startup does not read those inputs and that elevated startup has no runtime-credential fallback. When those profiles are not selected, record them as not applicable and do not invent elevated factories, credentials, entrypoints, or tests. Provisioning and production evidence is required only for an explicitly selected scope.

`PHT007` proves only canonical direct access, direct positional or named arguments to supported native callback consumers under their built-in names, directly invoked local literal assignments before another assignment-operator occurrence, and one-file confinement. It does not detect hard-coded secrets, dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, secret-manager APIs, leaks, correct validation, safe deployment permissions, or least database privilege.
