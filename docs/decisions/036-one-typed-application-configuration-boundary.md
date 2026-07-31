# ADR 036: One typed application configuration boundary

Status: accepted

## Context

Application credentials and deployment settings become difficult to audit when entrypoints, handlers, operations, migration code, or database owners read them independently. Repeated reads also make it easy for an HTTP process to receive migration authority, for one credential to become an accidental fallback for another, or for a missing value to survive until the first external I/O.

PHPThis needs one AI-discoverable authoring pattern without adding a framework configuration service, string-keyed bag, global helper, facade, service container, discovery mechanism, automatic dotenv loader, or secret-manager abstraction. Applications must retain ownership of their deployment mechanism, configuration names, validation, typed values, privilege separation, and rotation policy.

On 2026-07-31 in Asia/Manila, the accountable human approved this typed application configuration boundary, Consumer Contract version 10, Strict Profile version 3, and permanent diagnostic `PHT007`.

## Decision

Consumer Contract version 10 and Strict Profile version 3 add permanent structural rule `PHT007`. An application may read process environment only through direct `\getenv('EXACT_LITERAL_KEY')` calls. The call receives exactly one positional non-empty uppercase literal key of at most 128 bytes. Every such call in the Composer project occurs in one application-owned PHP file; PHPThis does not prescribe that file's directory, namespace, class name, or number of process-specific factories.

`PHT007` rejects environment reads spread across multiple application files, unqualified or non-literal `getenv` calls, imports, literal callable indirection passed directly as a positional or named argument to supported native callback APIs under their built-in names, or held in local literal assignments later invoked directly before another assignment-operator occurrence, including through explicit closure/arrow capture, `$_ENV`, direct, imported, or aliased references to the global `INPUT_ENV` constant, literal `constant('INPUT_ENV')` and `constant('\\INPUT_ENV')` lookups, `putenv`, Apache environment access, indexed `$_SERVER`, and other bare `$_SERVER` uses. Bare `$_SERVER` remains valid only in the exact `$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)` front-controller transport tuple. Dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, non-environment secret-manager APIs, and downstream misuse after that tuple remain contract and review concerns rather than claimed checker coverage. Variables implicitly reassigned through `foreach`, destructuring, or by-reference mutation may be conservatively reported from their earlier literal assignment. Application-defined unqualified functions or `Closure` classes deliberately sharing supported native callback names are conservatively treated as those native callbacks.

The application records the selected boundary file, exact input names without values, adopted process-specific factories, final readonly output types, validation bounds, applicable authority separation, failure behavior, rotation/restart behavior, redaction evidence, and tests in required `.ai/configuration.md`. It records injection sites for adopted process or infrastructure composition, or explicit connection-composition deferral for configuration-only scope.

The boundary is invoked once for each entrypoint/bootstrap composition. After ordinary source/autoload loading, it immediately distinguishes `false` from a string and rejects missing, empty, malformed, or oversized values before application-controlled database, network, writable-filesystem, migration, or business I/O. It returns narrowly named final readonly application types, not an array, arbitrary string-key lookup, or configuration service. HTTP, CLI, worker, WebSocket, migration, and administrative entrypoints call only their matching factory and inject only its typed result into visible manual composition.

Each adopted runtime, migration, or administrative profile uses distinct exact names and output types. Adopted migration or administrative configuration never falls back to runtime configuration. The HTTP composition path never calls an elevated factory. When connection scope is adopted, `Connection::connect` remains visibly constructed with its `QueryBudget` and `QueryTrace` in the composition root; configuration-only scope records that composition as deferred.

Actual values remain outside source, committed AI context, logs, traces, exceptions, and test output. Deployment-injected environment is a delivery mechanism, not encrypted storage. `.env` and `.env.*` remain ignored except for an optional `.env.example` containing names and obviously non-secret placeholders. PHPThis never loads that file automatically. Tests that need process values supply an explicit child-process environment instead of mutating application process state through `putenv`.

`Connection::connect` marks its password parameter with `#[\SensitiveParameter]`. That narrows accidental stack-trace disclosure at this wrapper boundary only; it does not encrypt the value or prevent explicit logging, dumping, copying, or serialization.

No application or deployment configuration runtime or class enters framework `src/`, and no runtime dependency is added. Existing narrowly scoped framework value types such as `SessionConfiguration` remain unrelated to this application deployment-input boundary. The one `SensitiveParameter` declaration increases reviewed framework core from 2,592 to 2,593 physical lines within the existing 2,600-line ceiling.

## Consequences

An AI has one local source of truth for how deployment inputs become typed application values. Reviewers can trace each process from exact external names to validation, authority, composition, and tests without searching for credentials across behavior code. Applications keep freedom over source layout and deployment technology.

The structural rule proves only canonical direct process-environment access, direct positional or named arguments to supported native callback consumers under their built-in names, directly invoked local literal assignments before another assignment-operator occurrence, and one-file confinement. It does not find hard-coded secrets, dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, arbitrary secret-manager reads, unsafe deployment permissions, incorrect validation, overprivileged database accounts, or explicit disclosure. Runtime tests, deployment review, secret scanning, and engine-specific privilege evidence remain separate obligations.

Existing Contract-version-9 applications must add `.ai/configuration.md`, centralize every process-environment read, construct final readonly typed configuration, separate each adopted authority profile without fallback, and pass the complete application gate before adopting version 10. A health-only application can record `NOT_APPLICABLE(CONFIGURATION)` and add no artificial configuration source.

## Reconsider when

Independent consumer evidence shows that one application-owned source file cannot keep process-specific credential access explicit without creating a correctness or deployment failure, or PHP supplies a more precise native typed configuration boundary. Reconsider the narrow boundary and diagnostic with migration evidence; do not add a generic configuration runtime.
