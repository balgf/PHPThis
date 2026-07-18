# PHPThis application contract

Contract version: 2

This is the canonical contract for an application built with the installed PHPThis version. It defines the minimum development rules supplied by that version. Application instructions may add stricter rules and project-specific facts, but they must not weaken this contract.

The root `AGENTS.md` and `.ai/` directory in the PHPThis framework repository are maintainer instructions. They are not an application template. A consuming application owns its own `AGENTS.md` and `.ai/` directory.

## Authority and read order

For application work:

1. Read this contract from the installed PHPThis package.
2. Use the installed `docs/knowledge-map.md` to route the framework question or task.
3. Read the application's root `AGENTS.md` and `.ai/README.md`.
4. Read only the application guide relevant to the task.
5. Inspect the concrete source and tests on the execution path.

The PHPThis Strict Profile and executable application checks are the hard floor. Tests demonstrate behavior but do not authorize a contract violation. When application instructions conflict with this contract, preserve the contract and report the conflict.

Files under `vendor/` belong to installed dependencies. Do not edit them to customize application behavior or silence a finding; change application-owned code or propose an upstream framework change.

## AI authoring and human accountability

AI is the expected primary code author and knowledge interface for a PHPThis application. This does not make AI output authoritative and does not exclude human-authored contributions. When asked how PHPThis works or how application code should be written, the AI must inspect the installed version, this contract, the matching application context, and the relevant source and tests. Model memory alone is not evidence.

An answer must distinguish:

- behavior and constraints supplied by the installed PHPThis version;
- policy and facts owned by this application;
- a proposed capability or decision that does not exist yet.

Name the supporting paths, symbols, diagnostics, or check output. Report missing or conflicting evidence instead of inventing framework behavior, product intent, schema meaning, authorization policy, production limits, or external contracts.

Humans direct the work and remain accountable for outcomes. Consequential product, architecture, security, data, migration, deployment, and external-side-effect choices must be made visible for human judgment. An AI may investigate options and draft a decision record, but it cannot approve its own consequential choice or infer authorization from silence. After explicit accountable-human approval, the AI may record the decision as accepted.

PHPThis therefore has no traditional framework manual as its canonical knowledge interface. Its contracts, knowledge map, decisions, source, diagnostics, and tests remain readable by humans, but are structured primarily to ground the AI working in the repository.

## Program validity

A PHPThis application must:

- run on PHP 8.4 and declare strict types in every application-owned PHP file;
- require `phpstan/phpstan` at `^2.1` and `phpstan/phpstan-strict-rules` at `^2.0` as development dependencies, then run the framework-owned analysis configuration at maximum level;
- use the installed `phpthis check` binary to enforce Strict Profile version 2;
- expose one documented project check command that runs static analysis, profile checks, and behavior tests;
- keep every application-owned named class final and expose an interface when an extension point is required;
- use ordinary constructors and a visible composition root instead of runtime discovery or service location;
- keep one canonical spelling and execution pattern for each framework operation;
- own every required application-context file listed below and resolve every template placeholder before feature work;
- fix findings at their cause rather than adding baselines, broad ignores, consumer PHPStan configuration, or comment suppressions.

Composer does not inherit a dependency's root scripts or development dependencies. An application therefore declares `phpstan/phpstan`, `phpstan/phpstan-strict-rules`, its behavior-test command, and this canonical sequence itself:

```json
{
  "scripts": {
    "profile": "phpthis check",
    "test": "php tests/run.php",
    "check": ["@profile", "@test"]
  }
}
```

`phpthis check` discovers every application-owned PHP file, runs structural profile checks, and invokes PHPStan with a temporary framework-owned configuration. The same discovered file manifest drives both stages. It excludes only the resolved Composer dependency directory and version-control metadata; source under `config/`, `bin/`, migrations, hidden directories, or `tmp/` remains application-owned and checked. PHP files use the `.php` extension; extensionless executables beginning with `<?php` or `#!/usr/bin/env php` followed by `<?php` are also checked. A canonical PHP opening prefix under another extension is rejected rather than silently excluded. Symlinked source directories and checked source files are rejected instead of silently skipped.

Applications must not add PHPStan configuration artifacts named `phpstan*.neon`, `phpstan*.neon.dist`, or `phpstan*baseline*.php`, or add `@phpstan-ignore` comments. This reserved filename family includes the usual `phpstan.neon`, `phpstan.neon.dist`, and PHPStan baseline variants. These create a second apparent definition of valid code and are rejected as `PHT004`. Project-specific static-analysis customization remains deliberately unsupported in contract version 2.

## HTTP and application flow

- `public/index.php` is the one front controller permitted to read PHP runtime globals and pass them to one bounded request boundary.
- Requests and responses are immutable values.
- Routes are explicit method, literal path, and already-constructed handler objects.
- A root route manifest combines named route-area lists; request-time route lookup remains indexed.
- Handlers implement `RequestHandler::handle` and receive dependencies through constructors.
- External `mixed` input is parsed once into a concrete final readonly command or projection before it enters typed application behavior.
- Known public failures use named exception classes and exact-class response registration. Unknown failures remain generic externally and are logged once by exception class without the exception message or sensitive request or database data.

Do not add route discovery, automatic input binding, middleware pipelines, facades, global helpers, macros, dynamic proxies, reflection-based hydration, or magic methods other than constructors.

## Optional CRUD reference structure

PHPThis supplies a feature-first CRUD reference profile in `docs/crud.md`. It is optional application structure, not a runtime API or an additional condition of program validity. The PHPThis consumer contract and Strict Profile remain mandatory regardless of source placement.

An application either follows the reference placement or records one coherent alternate placement and naming rule in `.ai/architecture.md`. Project instructions can replace that directory and naming recommendation, but cannot weaken explicit routes and dependencies, concrete boundary types, visible engine-specific SQL, query budgets and traces, bounded reads, scale-sensitive tests, static analysis, or the complete application check.

Do not infer a generic persistence layer from the CRUD label. There is no CRUD base handler, generic repository, automatic resource registration, mass assignment, generated SQL, runtime discovery, or filesystem enforcement. Commands, projections, authorization, transactions, failure behavior, and database work remain specific to each operation.

Current PHPThis executable evidence covers only part of Create and List: structure, boundary parsing, transaction shape, and query cost. It does not establish authorization, Create identity/conflict policy, or List continuation. Get, Update, and Delete wait for typed item routes and application-owned decisions covering pagination, concurrency, deletion, authorization, and conflicts. An application must not present any operation or policy as supplied by PHPThis without concrete source, accepted local decisions, and tests.

## Database work

When an application uses a database:

- require the matching runtime PDO extension in the application Composer package and record the connection's engine, version, configuration source, schema authority, and dialect assumptions in `.ai/data.md`;
- create the request connection with `PHPThis\Database\Connection::connect` in the composition root and execute visible SQL through that connection with named parameters; `PHT005` rejects application-owned construction of `PDO` or its subclasses, including aliases and anonymous subclasses;
- call `selectAllRows`, `selectOneRow`, and `executeStatement` directly with SQL that resolves from native PHP code to one or more non-blank compile-time constant strings; `PHT006` rejects arbitrary strings, dynamic interpolation, blank variants, argument unpacking, PHPDoc-only narrowing, and callable indirection;
- pass every application or external data value through a unique named parameter, even after validation; PDO parameters represent complete data literals and cannot represent identifiers, keywords, operators, directions, or SQL fragments;
- keep statements static by default; when structure must vary, map a typed operation-specific choice to a finite set of complete, code-owned, reviewed constant statements and reject unknown choices before database work;
- do not add a generic SQL sanitizer, identifier-quoting helper, query builder, SQL template engine, or dialect layer to turn arbitrary input into statement structure;
- treat `Connection` as PDO transport, not a portable SQL abstraction; write each query for the selected engine and never infer that SQLite evidence proves MySQL or PostgreSQL behavior;
- use a distinct portable name for every placeholder occurrence and a unique column name or alias for every selected expression;
- give every request connection an explicit `QueryBudget` and bounded `QueryTrace`;
- give separately named connections explicit budgets and distinct traces, document any deliberately shared request-wide budget, and do not claim atomicity across connections;
- name selected columns and bound every collection read;
- never execute a database statement from a loop or recursive traversal;
- parse selected rows immediately into concrete projections;
- keep transactions explicit and preserve the original failure;
- test materially different fixture sizes and prove that statement count stays constant;
- run integration tests against every engine and version whose SQL, returned values, errors, isolation, locking, or plans the application relies on;
- give each runtime connection only the database objects and actions its process needs; do not expose schema-owner, migration, grant-management, or administrative credentials to the web runtime;
- keep migration and administrative credentials in a separately authorized execution path, and record engine-specific privilege evidence or the equivalent SQLite file/process boundary in `.ai/data.md`;
- test SQL-looking valid data as unchanged bound values and test every variable structural choice, including rejection before database work;
- treat query budgets as backstops, not proof of an efficient SQL shape.

Production-specific table sizes, indexes, scalar representations, locking constraints, retention rules, driver/session options, query limits, SQL structural choices, and database authority belong in the application's `.ai/data.md`, not in the framework contract.

PHT006 proves only the finite native string type passed to the canonical direct calls. It does not parse SQL, prove a statement is safe or authorized, inspect stored procedures or server-side dynamic SQL, validate grants, or cover reflection and non-canonical invocation. Parameterization does not replace authorization, least privilege, engine-specific integration tests, or security review.

## Project-owned AI context

Every application must complete and commit:

```text
AGENTS.md
.ai/
  README.md
  rules.md
  change-workflow.md
  project.md
  architecture.md
  data.md
  integrations.md
  operations.md
  testing.md
docs/
  decisions/
    README.md
```

The application context records facts the framework cannot infer: domain vocabulary, accountable human decision roles, real source paths, architectural boundaries, data scale, resource limits, external side effects, runtime assumptions, verification commands, and prohibited operations.

Keep the context compact and route tasks through `.ai/README.md`; do not load every guide for every change. Do not store credentials, tokens, private keys, customer data, production payloads, or other secrets in AI instructions. Detailed rationale and decision history belong in the application's `docs/decisions/` directory.

## Contract evolution

Clarifications may update wording without changing the contract version. The AI-authoring and accountability model clarifies how the existing application context is used; it does not change the accepted PHP program set. A change that accepts or rejects a materially different class of application code requires a new contract or Strict Profile version and explicit upgrade notes. Updating PHPThis never grants permission to overwrite an application's project-owned context.

Contract version 2 carries contract version 1 forward and adds Strict Profile version 2 with PHT006, explicit SQL data-versus-structure rules, adversarial binding evidence, and application-owned database-authority requirements. Before adopting version 2, audit every canonical `Connection` call, replace arbitrary SQL strings and indirect invocation with finite direct constant-string choices, bind every data value, record runtime and migration credential separation, add the required tests, and run `composer check`.

Contract version 1 replaced consumer-owned PHPStan configuration with the installed checker and added the runnable skeleton. A contract-version-0 application must complete that version-1 migration before applying the version-2 steps above.
