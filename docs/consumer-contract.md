# PHPThis application contract

Contract version: 4

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
- declare `phpthis/framework` as a runtime Composer dependency under `require` when application code executes framework classes;
- provide `ext-session` required by the installed framework, even when the application does not configure session state;
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

The `php tests/run.php` value is the skeleton's concrete example, not a required command or path. The contractual structure is the exact `profile` and `check` values plus a non-empty application-owned `test` script.

## Automated behavior evidence

Every observable behavior change must add or update application-owned automated tests. The evidence covers expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits. Static analysis, documentation, manual verification, and a test command that merely exits successfully do not satisfy this obligation.

The application owns its test library, runner, file placement, and organization. PHPThis does not require PHPUnit, Pest, a `tests/` directory, or a particular distinction between unit, integration, and end-to-end tests. Composer `scripts.test` must execute the application's automated behavior evidence and return a non-zero status when that evidence fails. The complete project check must run it after `phpthis check`; an implementation task is incomplete until both stages pass.

The installed checker can verify the canonical gate wiring, but it cannot determine the semantic adequacy of an arbitrary test suite. The AI implementing a change must name the automated tests added or updated and the behavior they prove. The accountable human decides whether that evidence is sufficient for the requested outcome and risk.

`phpthis check` discovers every application-owned PHP file, runs structural profile checks, and invokes PHPStan with a temporary framework-owned configuration. The same discovered file manifest drives both stages. It excludes only the resolved Composer dependency directory and version-control metadata; source under `config/`, `bin/`, migrations, hidden directories, or `tmp/` remains application-owned and checked. PHP files use the `.php` extension; extensionless executables beginning with `<?php` or `#!/usr/bin/env php` followed by `<?php` are also checked. A canonical PHP opening prefix under another extension is rejected rather than silently excluded. Symlinked source directories and checked source files are rejected instead of silently skipped.

Applications must not add PHPStan configuration artifacts named `phpstan*.neon`, `phpstan*.neon.dist`, or `phpstan*baseline*.php`, or add `@phpstan-ignore` comments. This reserved filename family includes the usual `phpstan.neon`, `phpstan.neon.dist`, and PHPStan baseline variants. These create a second apparent definition of valid code and are rejected as `PHT004`. Project-specific static-analysis customization remains deliberately unsupported in contract version 4.

## HTTP and application flow

- `public/index.php` is the one front controller permitted to read PHP runtime globals and pass them to one bounded request boundary.
- Requests and responses are immutable values.
- Routes are explicit method, path declaration, and already-constructed handler objects. A path is literal or contains at most two named typed placeholders. Each placeholder occupies one complete segment, uses only `positive-int` or `token`, and has a name beginning with a lowercase ASCII letter followed only by lowercase letters, digits, or underscores; names are unique within one route.
- A root route manifest combines named route-area lists; request-time route lookup remains indexed.
- `positive-int` accepts only canonical ASCII integers from 1 through `PHP_INT_MAX`. `token` accepts only case-sensitive 1-to-64-byte ASCII values matching `[A-Za-z0-9][A-Za-z0-9_-]{0,63}` and returns the bytes unchanged. URL-encoded, whitespace, Unicode, empty, oversized, or otherwise non-canonical spellings do not match.
- Exact literal lookup has precedence. Parameterized declarations whose paths overlap for one method and duplicate parameter names fail during router construction. To preserve one deterministic state, sibling parameter types and a typed transition beside any parameterized literal transition it accepts are rejected even when later segments differ. Every declaration sharing a typed transition uses the same name and type regardless of method or later branch. Registration order and parameter-type preference do not resolve those conflicts.
- Parameterized matching and allowed-method lookup traverse the bounded request path through a deterministic state index; neither scans the declared route list or an index collection during a request.
- `Route::segments()` exposes immutable compiled segment metadata from the same explicit declaration. PHPThis does not generate route source, persist a route cache, or add a second report or registration API.
- A successful lookup yields immutable routing metadata. `Application` creates an immutable `Request` copy carrying immutable `PathParameters`; static routes carry empty parameters, and handlers keep `RequestHandler::handle(Request): Response`.
- `PathParameters` exposes only `positiveInteger(name): int` and `token(name): string`. Route-specific code immediately converts each validated value into a concrete identifier. Path parameters are not a mixed domain bag and do not supply authorization, tenant scope, or record existence.
- Handlers receive dependencies through constructors.
- External `mixed` input is parsed once into a concrete final readonly boundary value before it enters typed application behavior: an operation-specific request or command for inbound data, or a projection for returned data.
- Known public failures use named exception classes and exact-class response registration. Unknown failures remain generic externally and are logged once by exception class without the exception message or sensitive request or database data.
- Response cookies use validated `ResponseCookie` values and remain separate from the ordinary single-value header map; application code does not manually encode `Set-Cookie`.
- Framework-generated 404, 405, and unknown-failure 500 responses explicitly use `Cache-Control: no-store`. PHPThis does not rewrite arbitrary handler responses; each application response path owns its exact cache policy.

Do not add a third path parameter, another parameter type, partial-segment placeholders, regular-expression or callback routes, arbitrary strings, route or index scanning, route discovery, automatic input or domain binding, middleware pipelines, facades, global helpers, macros, dynamic proxies, reflection-based hydration, or magic methods other than constructors.

## Application-owned request policy

ADR 020 records the canonical application composition for a protected route without adding a framework runtime API. One action-specific adapter implements `RequestHandler`, receives narrowly named application policy interfaces through its constructor, and executes visible straight-line `authenticate -> resolve tenant -> authorize -> protected handler` order. It passes concrete immutable principal, tenant, and route-specific values explicitly to the protected operation.

An application adopting this pattern must:

- choose and record its credential source, concrete principal and tenant representations, action vocabulary, tenant source, current per-request authorization rule, credential lifecycle, and concurrency policy;
- wire every authenticator, tenant resolver, and authorizer explicitly and make each implementation independently replaceable without framework edits or discovery metadata;
- keep principal, tenant, and authorization state out of `Request`, `PathParameters`, globals, generic context bags, session snapshots, and application data caches;
- give any policy reads separately named connections, budgets, and traces from protected handler work and prove that every denial stops before protected queries, writes, session mutation, cache mutation, or external business side effects;
- keep protected SQL explicitly tenant- and resource-scoped after authorization rather than relying on an implicit or global scope;
- use named exact-class denial failures, generic disclosure-safe responses, no denial logging, class-only unknown-failure logging, and an explicit authenticated-response cache policy;
- test missing or rejected credentials, ordinary forbidden access, cross-tenant access, permitted access, every failing stage, exact call order, zero protected denial work, redaction, and policy replacement.

The accepted reference proof is stateless, exposes the bounded authorization header to a replaceable authenticator, wires deny-all in its checked-in composition, uses I/O-free synthetic consumer policies, maps unauthenticated requests to one generic `401` with `WWW-Authenticate: Bearer`, maps ordinary forbidden and cross-tenant access to the same generic `403`, re-evaluates authorization on every protected request, and starts authenticated and denied responses with `private, no-store`. PHPThis supplies no credential parser or verifier. These are the proof's application decisions, not a framework identity provider, token format, permission store, tenant model, audit contract, or middleware facility. A public route may record request policy as not applicable.

## Optional session state

PHPThis provides one optional lazy `SessionLifecycle` over PHP 8.4's native file session handler. It is session transport, not authentication, authorization, expiry, or CSRF policy.

When an application adopts session state, it must:

- construct one `SessionConfiguration` and `SessionLifecycle` in the composition root, pass that lifecycle to `RequestBoundary`, and inject the single lifecycle only into narrowly named typed services with explicit non-overlapping key ownership;
- make each typed service start mutation from the supplied snapshot, change or remove only its owned keys, and preserve every unowned key because the returned snapshot replaces the complete session state;
- keep session state out of `Request`, handlers' direct global access, helpers, middleware, and generic key-value repositories;
- call `read()` for a closed immutable snapshot, `update()` for ordinary callback-scoped mutation, `regenerateAndUpdate()` before committing authenticated identity or another privilege elevation, and terminal `invalidate()` for logout or revocation; after invalidation, every later session operation in that request raises `LogicException`;
- surface `SessionUnavailable` as a deterministic stale-state response without retrying session mutation in that request; explicitly authenticated regeneration may replace rejected input with a fresh server-generated identifier, while an explicit client-requested restart may use terminal invalidation to expire malformed or collected anonymous state;
- keep mutation callbacks bounded, synchronous, and side-effect-free, with no database, network, filesystem, logging, or nested session operation; finish fallible domain and external work before the final small session mutation because a successful mutation commits immediately and is not atomic with another resource or the rest of the request;
- store only the bounded scalar or `null` values accepted by `SessionSnapshot`, then narrow the allowed keys and meanings further in their owning typed services;
- treat a stored identity as input to a current authorization decision, never as authorization itself;
- record the applicable identity, authentication, authorization, expiry, logout, revocation, CSRF, cookie, storage, garbage-collection, and concurrency policies in application context, marking each absent concern explicitly not applicable;
- validate and date the deployed PHP session settings and prove that the configured effective save path is isolated to this application as described in `docs/sessions.md`;
- test mandatory transport behavior: anonymous stateless access, invalid, duplicated, attacker-selected, stale, and obsolete identifiers, complete state bounds, callback rollback, lock release, unissued-ID cleanup, delayed-response cookie safety, explicit invalidation, cookie attributes, save-path mismatch, and concurrent requests; additionally test authentication-time regeneration, expiry, CSRF, authorization, and revocation when each policy applies.

Application-owned PHP must not read or write `$_SESSION`, call or dynamically reference native `session_*` functions, parse the framework session cookie, implement `SessionHandlerInterface`, or emit its cookie manually. The installed check rejects `$_SESSION`, direct and imported native session calls, and literal indirect references in every application file, including the front controller; dynamically obscuring a call remains a contract violation rather than an escape hatch. Custom session handlers, Redis or database stores, alternate identifier shapes, and unsupported shared-storage topologies require a separate accepted framework decision; application context cannot silently substitute them.

Applications that do not use sessions record the session transport and session-backed policy fields as `NOT_APPLICABLE`. Stateless authentication, authorization, credential expiry, revocation, and CSRF decisions remain independent application facts and must not be erased merely because `SessionLifecycle` is absent. Configuring `SessionLifecycle` alone must not create storage, issue a cookie, or acquire a native lock for a stateless handler.

## Optional CRUD reference structure

PHPThis supplies a feature-first CRUD reference profile in `docs/crud.md`. It is optional application structure, not a runtime API or an additional condition of program validity. The PHPThis consumer contract and Strict Profile remain mandatory regardless of source placement.

An application either follows the reference placement or records one coherent alternate placement and naming rule in `.ai/architecture.md`. Project instructions can replace that directory and naming recommendation, but cannot weaken explicit routes and dependencies, concrete boundary types, visible engine-specific SQL, query budgets and traces, bounded reads, scale-sensitive tests, static analysis, or the complete application check.

Do not infer a generic persistence layer from the CRUD label. There is no CRUD base handler, generic repository, automatic resource registration, mass assignment, generated SQL, runtime discovery, or filesystem enforcement. Commands, projections, authorization, transactions, failure behavior, and database work remain specific to each operation.

Current PHPThis executable evidence covers only part of Create and List: structure, boundary parsing, transaction shape, and query cost. List additionally proves one example-owned keyset contract with a strict optional `after_user_id`, ascending identifiers, fixed 50-row pages, one-row lookahead, and one statement per accepted page. That is evidence for the explicit pattern, not a framework pagination API or a policy inherited by consumers. The example still does not establish authorization or Create identity/conflict policy. The first Get slice proves the trailing positive-integer route, immediate concrete-identifier conversion, not-found shape, concrete projection, and bounded query count; it does not establish authorization or tenant policy. Update and Delete have no executable reference and still require application-owned concurrency, deletion, authorization, and conflict decisions. An application must not present any operation or policy as supplied by PHPThis without concrete source, accepted local decisions, and tests.

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

Clarifications may update wording without changing the contract version. The AI-authoring and accountability model clarifies how the existing application context is used; it does not change the accepted PHP program set. The automated-behavior-evidence language clarifies the existing behavior-test stage while leaving its library, runner, file placement, and organization application-owned. ADR 020 likewise records an application-owned protected-request composition using existing `RequestHandler`, request, error, and database contracts. ADR 021 records operation-owned typed input parsing, generic safe failures, and executable boundary evidence using the existing request, error, static-analysis, and application-testing contracts. Neither adds a core API, required mechanism, accepted PHP syntax, or diagnostic, so Consumer Contract version 4 and Strict Profile version 2 remain unchanged. A change that accepts or rejects a materially different class of application code requires a new contract or Strict Profile version and explicit upgrade notes. Updating PHPThis never grants permission to overwrite an application's project-owned context.

Contract version 4 carries contract version 3 and Strict Profile version 2 forward. It replaces ADR 017's one-trailing-positive-integer grammar with ADR 019's at-most-two full-segment grammar, adds the bounded `token` type and type-specific token access, and replaces the one-prefix metadata and index with immutable `Route::segments()` metadata and a deterministic state index. Existing literal and one-trailing-positive-integer declarations remain valid. Before adopting version 4, migrate any direct calls to `Route::literalPrefix()` or `Route::parameterName()` to `Route::segments()`, reject any newly exposed route overlap at construction, add malformed, oversized, encoded, 404, 405, and scale evidence, and run the complete application gate.

Contract version 3 carries contract version 2 and Strict Profile version 2 forward unchanged. It adds explicit response cookies and the optional native-file session lifecycle. Before adopting version 3, ensure unconditional `ext-session` availability and replace manually encoded response cookies. When adopting session state, also verify the fixed PHP 8.4 settings and an application-isolated save path, record applicable policy or explicit non-applicability, place state behind narrowly named typed services over the single lifecycle, and add the required transport and application-policy tests. Then run the complete application gate.

Contract version 2 carried contract version 1 forward and added Strict Profile version 2 with PHT006, explicit SQL data-versus-structure rules, adversarial binding evidence, and application-owned database-authority requirements. A version-1 application must complete that migration before applying the version-3 steps above: audit every canonical `Connection` call, replace arbitrary SQL strings and indirect invocation with finite direct constant-string choices, bind every data value, record runtime and migration credential separation, add the required tests, and run the complete application gate.

Contract version 1 replaced consumer-owned PHPStan configuration with the installed checker and added the runnable skeleton. A contract-version-0 application must complete that version-1 migration before applying later migrations.
