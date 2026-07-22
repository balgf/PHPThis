# PHPThis application contract

Contract version: 9

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

- run on PHP 8.4.x and declare strict types in every application-owned PHP file; the supported Composer range excludes PHP 8.5 until separately reviewed and tested;
- declare `phpthis/framework` as a runtime Composer dependency under `require` when application code executes framework classes;
- provide `ext-session` required by the installed framework, even when the application does not configure session state;
- require `phpstan/phpstan` at `^2.1` and `phpstan/phpstan-strict-rules` at `^2.0` as development dependencies, then run the framework-owned analysis configuration at maximum level;
- use the installed `phpthis check` binary to enforce Strict Profile version 2;
- expose one documented project check command that runs static analysis, profile checks, and behavior tests;
- keep every application-owned named class final and expose an interface when an extension point is required;
- use ordinary constructors and a visible composition root instead of runtime discovery or service location;
- keep each optional application-owned request-handler decorator route-local, explicitly ordered, and limited to exactly one downstream `RequestHandler`;
- own one explicit terminal request-summary coordinator and one sink at the front-controller composition boundary, without adding framework logging types or hidden instrumentation;
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

`phpthis check` discovers every application-owned PHP file, runs structural profile checks and a report-only duplication scan, and invokes PHPStan with a temporary framework-owned configuration. The same discovered file manifest drives all three stages; structural checks and the duplication scan reuse one captured source read. It excludes only the resolved Composer dependency directory and version-control metadata; source under `config/`, `bin/`, migrations, hidden directories, or `tmp/` remains application-owned and checked. PHP files use the `.php` extension; extensionless executables beginning with `<?php` or `#!/usr/bin/env php` followed by `<?php` are also checked. A canonical PHP opening prefix under another extension is rejected rather than silently excluded. Symlinked source directories and checked source files are rejected instead of silently skipped.

The duplication scan is an advisory review signal, not program validity. A possible group requires at least 48 exact normalized PHP tokens after ordinary `<?php` opening tags, comments, docblocks, and whitespace are removed. Normal mode prints only a concise summary; `phpthis check --debug` intentionally adds bounded application-relative filenames, line ranges, token counts, and truncation state. Advisory lines print no PHP source snippets or normalized token text—including source-level identifiers, literals, and values—and no hashes or absolute paths. Because debug reveals relative filenames and line topology, application paths must not contain secrets or customer data. Duplication alone returns success and does not prevent PHPStan or the application-owned behavior-test stage from running. A resource limit or unavailable scan is also advisory and explicitly states that application validity is unaffected.

The advisory has no `PHT` diagnostic, baseline, suppression, ignore path, consumer configuration, score, automatic refactor, or required directory layout. It can miss renamed, reordered, semantic, short, non-PHP, or resource-skipped copies and can report deliberate explicit repetition. In particular, visible SQL, unrolled bounded operations, security sequencing, and independent tests are not presumed defective. Review a group in context; do not introduce a generic mechanism merely to remove the report. Any future promotion into validity requires a separate framework decision and consumer migration evidence.

Applications must not add PHPStan configuration artifacts named `phpstan*.neon`, `phpstan*.neon.dist`, or `phpstan*baseline*.php`, or add `@phpstan-ignore` comments. This reserved filename family includes the usual `phpstan.neon`, `phpstan.neon.dist`, and PHPStan baseline variants. These create a second apparent definition of valid code and are rejected as `PHT004`. Project-specific static-analysis customization remains deliberately unsupported in contract version 9.

## HTTP and application flow

- `public/index.php` is the one front controller permitted to read PHP runtime globals. It passes `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES` explicitly to one application-owned terminal coordinator that calls one bounded `RequestBoundary`.
- Requests and responses are immutable values.
- Routes are explicit method, path declaration, and already-constructed handler objects. A path is literal or contains at most two named typed placeholders. Each placeholder occupies one complete segment, uses only `positive-int`, `token`, `uuid`, or `ulid`, and has a name beginning with a lowercase ASCII letter followed only by lowercase letters, digits, or underscores; names are unique within one route.
- A root route manifest combines named route-area lists; request-time route lookup remains indexed.
- Always use the narrowest route type. `positive-int` accepts only canonical ASCII integers from 1 through `PHP_INT_MAX`. `uuid` accepts lowercase `[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}` and rejects Nil and Max. `ulid` accepts lowercase `[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}`. `token` accepts only case-sensitive 1-to-64-byte ASCII values matching `[A-Za-z0-9][A-Za-z0-9_-]{0,63}` and is reserved for genuinely opaque string identifiers. Values are returned unchanged; URL-encoded, uppercase UUID or ULID, whitespace, Unicode, empty, oversized, or otherwise non-canonical spellings do not match and are never normalized.
- Exact literal lookup has precedence. Parameterized declarations whose paths overlap for one method and duplicate parameter names fail during router construction. To preserve one deterministic state, sibling parameter types and a typed transition beside any parameterized literal transition it accepts are rejected even when later segments differ. Every declaration sharing a typed transition uses the same name and type regardless of method or later branch. Registration order and parameter-type preference do not resolve those conflicts.
- Parameterized matching and allowed-method lookup traverse the bounded request path through a deterministic state index; neither scans the declared route list or an index collection during a request.
- `Route::segments()` exposes immutable compiled segment metadata from the same explicit declaration. PHPThis does not generate route source, persist a route cache, or add a second report or registration API.
- A successful lookup yields immutable routing metadata. `Application` creates an immutable `Request` copy carrying immutable `PathParameters`; static routes carry empty parameters, and handlers keep `RequestHandler::handle(Request): Response`.
- `PathParameters` exposes only `positiveInteger(name): int`, `token(name): string`, `uuid(name): string`, and `ulid(name): string`. Route-specific code immediately converts each validated value into a concrete identifier and applies any narrower domain rule before domain or database work. Path parameters are not a mixed domain bag and do not supply authorization, tenant scope, record existence, identifier generation, or persistence policy.
- Handlers receive dependencies through constructors.
- A route may explicitly wrap its terminal handler in one or more application-owned request-handler decorators under the bounded contract below. The complete nesting remains visible beside that route and introduces no framework middleware path.
- External `mixed` input is parsed once into a concrete final readonly boundary value before it enters typed application behavior: an operation-specific request or command for inbound data, or a projection for returned data.
- PHP multipart file input is normalized once by `RequestReader` into at most one typed `RequestUpload` before an application operation parses its exact field, error, bound, provenance, and actual bytes. Raw `$_FILES` never enters a handler. PHP may already have collapsed duplicate raw scalar parts; the typed boundary does not claim their rejection.
- Known public failures use named exception classes and exact-class response registration. Unknown failures remain generic externally. The application-owned terminal summary records a known failure only through its generic outcome and response status and records only the concrete exception class for an unknown failure.
- Response cookies use validated `ResponseCookie` values and remain separate from the ordinary single-value header map; application code does not manually encode `Set-Cookie`.
- An optional local-file response uses one `LocalFileBody` with an application-resolved absolute path and expected bytes, an empty ordinary body, exact `Content-Length`, explicit representation headers, and the single fixed-chunk `ResponseEmitter` path. Response copies preserve that file body.
- Framework-generated 404 and 405 responses explicitly use `Cache-Control: no-store`; the unknown-failure 500 uses `Cache-Control: private, no-store`. PHPThis does not rewrite arbitrary handler responses; each application response path owns its exact cache policy.

Do not add a third path parameter, a fifth parameter type, partial-segment placeholders, regular-expression or callback routes, arbitrary strings, type fallback or match priority, route or index scanning, route discovery, automatic input or domain binding, middleware pipelines, facades, global helpers, macros, dynamic proxies, reflection-based hydration, or magic methods other than constructors.

## Optional application-owned request-handler decorators

ADR 033 accepts one optional application composition pattern without adding framework runtime behavior. An **application-owned request-handler decorator** is a final application class that implements the existing `RequestHandler`, receives exactly one downstream `RequestHandler` through its ordinary constructor, and owns one narrowly named route concern.

The decorator is composed only as the handler of an explicit `Route`. When decorators are nested, the complete outer-to-inner order and terminal handler must remain visible beside that route declaration. Do not hide the chain behind an array, loop, registry, priority, configuration, helper, factory, container, attribute, reflection, or discovery mechanism.

For each call, a decorator must either return an explicit early `Response` with zero downstream calls or call its one downstream handler exactly once. It passes the exact same immutable `Request` instance downstream. It does not catch, wrap, translate, suppress, retry, or replace an exception. It may return the downstream `Response` unchanged; if it replaces that immutable response, the construction is explicit and preserves every unchanged status, header, body, cookie, and local-file-body field.

Any decorator-owned I/O is narrowly named and bounded at the visible class, constructor, call site, and test. Database work has its own named connection, budget, and trace and remains constant across materially different fixture sizes; another external destination has an equivalent finite attempt, byte, time, and failure contract. Short-circuit evidence proves zero downstream queries, mutation, and external effects.

This pattern cannot wrap `Application`, `RequestBoundary`, the application terminal request-summary coordinator, or `ResponseEmitter`, and it cannot take ownership of routing, runtime ingestion, error registration, session finalization, correlation, terminal summaries, sink invocation, or emission. Do not add a generic or framework middleware interface, pipeline, stack, runner, registry, priority list, discovery rule, `$next` callable, request-context bag, request attributes, or framework-owned decorator. Principal, tenant, authorization, session, cache, and domain values stay in their existing explicit typed paths rather than entering `Request`.

An adopting application records each decorator's purpose, routes, explicit order, constructor dependencies, I/O bounds, early-response policy, response replacement, and failure behavior in project context. Automated evidence covers the applicable early and downstream paths, zero-or-one downstream invocation, exact request identity, exception identity, immutable response-field preservation, explicit nesting order, and every I/O or side-effect bound. Applications that do not need the pattern change no source. These obligations add no `PHT` diagnostic and leave Strict Profile version 2 unchanged.

## Application-owned WebSocket profile

PHPThis has no WebSocket runtime or core WebSocket API. ADR 034 records evidence that an application can pin a mature third-party runtime and run one separate visible WebSocket composition root without changing framework source. Frames never become PHPThis HTTP `Request` or `Response` values and do not enter `Application`, `Router`, `RequestBoundary`, `RequestHandler`, an application-owned request-handler decorator, the HTTP terminal coordinator, or `ResponseEmitter`.

An application exploring this profile owns its exact raw handshake request target and URI-normalization policy, origin, raw credential grammar before parser normalization, expiry, revocation, current authorization, frame and message parsing, connection and rate limits, idle and absolute lifetime, heartbeat, backpressure, ordering, reconnect, delivery, redaction, shutdown, restart, proxy, supervisor, and scaling policy. It parses accepted input once into a final readonly operation-specific command and calls only a narrowly typed application operation. It proves transport claims with real sockets and process claims with a real child process.

Do not add a framework WebSocket server, client, event loop, connection manager, daemon, supervisor, generic channel, broadcaster, pub/sub, event bus, middleware, context bag, service locator, discovery mechanism, hidden retry, replay, deduplication, acknowledgement, reconnect, or exactly-once behavior. A different application may select a different mature runtime and numeric policy, but it must pin the versions and repeat the corresponding resource and deployment evidence.

The independent consumer's exact Amp 4.0.0 numbers are a measured local recipe, not universal framework requirements or production recommendations. This accepted profile is optional routing and review guidance, not a framework runtime capability or a new validity obligation. Contract version 9, Strict Profile version 2, the framework runtime dependencies, and the core line ceiling remain unchanged.

## Optional bounded file transfers

ADR 026 accepts one narrow file-transfer transport without adding storage policy to core. An application that accepts multipart files must:

- configure a finite total multipart request limit separately from the ordinary body limit; a `null` multipart limit keeps multipart disabled;
- accept only `POST` with one valid boundary parameter, canonical `Content-Length`, no `Transfer-Encoding`, no parsed text fields, and at most one normalized flat file entry; record that duplicate raw scalar parts cannot be distinguished after PHP normalization;
- require one exact application field, exhaustively map `RequestUploadError`, apply an operation-specific file limit, verify `is_uploaded_file`, and require the actual size to equal `reportedSizeBytes` before storage;
- treat `untrustedClientFilename` and `untrustedClientMediaType` as hostile display metadata, accept no client storage path, and rely on no client MIME or extension claim; PHPThis validates then discards client `full_path`;
- use one narrowly named application-owned storage operation with a server-generated identity and destination, visible `move_uploaded_file`, explicit permissions, and recorded ownership before and after the move; and
- record authorization, tenant placement, quota, inspection, retention, deletion, backup, recovery, deployment topology, PHP/server/proxy limits, buffering, observability, and complete automated evidence.

For a local-file response, application code resolves authorization and storage identity before constructing `LocalFileBody`. It sets exact `Content-Length`, media, disposition, cache, sniffing, and range headers. `ResponseEmitter` opens and verifies a regular file and exact size before headers, emits at most 8,192 bytes per read, and closes in `finally`. `ResponseEmissionFailed(false)` may receive one generic fallback at the visible front controller; after `ResponseEmissionFailed(true)`, do not emit a replacement response. Terminal request summaries still prove response selection, not file or network delivery.

Ranges are deferred. Return the complete `200` representation with `Accept-Ranges: none`; do not add `206`, `416`, `Content-Range`, range parsing, or partial reads. Do not add an ORM, automatic binding, generic storage or stream facade, discovery, MIME or filename trust, automatic persistence or cleanup, image processing, remote-object abstraction, or hidden lifecycle. Applications not adopting file transfers leave multipart disabled and do not construct `LocalFileBody`.

## Application-owned terminal request summary

ADR 023 defines the mandatory request-level observability boundary without adding a framework runtime API. Each application must:

- generate 128 random bits during request-scoped composition before bounded request ingestion, encode them as exactly 32 lowercase hexadecimal characters, and propagate that value as both event `correlation_id` and the single `X-Request-ID` response header on every selected response, replacing any case-insensitive application response spelling;
- use one application-owned coordinator and one explicitly injected sink in the visible front-controller path;
- build the closed version-1 `application.request_summary` event after the final immutable `Response` is selected and before response emission;
- include monotonic duration, selected response status, generic outcome, nullable class-only unknown failure, aggregate query counts, failures, execution time, and budget-exceeded state;
- register every request-scoped connection that can execute inside the coordinator path through one finite list of at most eight database sources, using unique non-sensitive names matching `[a-z][a-z0-9_]{0,31}` and ensuring that no two sources share a `QueryBudget` or `QueryTrace`;
- preserve the existing bounded redacted `QueryTrace` version-1 evidence, keep the rejected budget call absent from the trace, and never include SQL or parameter values;
- give a known authentication, authorization, validation, routing, or other mapped failure only the generic `known_failure` outcome and selected status, with no denial type, class, reason, principal, tenant, resource, or credential field;
- give a named unknown failure only its concrete class name; for an anonymous throwable, use its nearest named parent class because PHP's anonymous-class name embeds source location; never include its message, code, previous exception, source location, or stack;
- omit request method, path, query data, headers, cookies, authorization data, body, response body, session and CSRF values, cache keys or values, customer and domain identifiers, SQL, bindings, DSNs, credentials, and driver details; and
- make exactly one sink invocation attempt after the response and event are fixed, catch any sink `Throwable`, and preserve that same response without retry, fallback logging, a second event, or a delivery guarantee.

The closed top-level keys are exactly `schema_version`, `event`, `correlation_id`, `duration_us`, `response_status`, `outcome`, `unknown_failure_class`, `query_count`, `query_failures`, `query_execute_duration_us`, `query_budget_exceeded`, and `database_sources`. Each source contains exactly `name`, `budget_limit`, `budget_used`, `budget_exceeded`, and the existing bounded `query_trace` snapshot. Numeric aggregates saturate at `PHP_INT_MAX`. `outcome` is `unknown_failure` only for a caught unknown `Throwable`; otherwise a status below 400 is `success` and a status of 400 or above is `known_failure`.

Exactly one sink invocation attempt is not durable delivery. The event records application response selection, not successful response emission or network delivery. Bootstrap failures before the coordinator, process-fatal errors outside the handled path, and emitter or client-connection failures require separate application evidence. Do not add a core event, sink, coordinator, logger, facade, middleware, service locator, global helper, per-query log, discovery hook, or hidden `Connection` instrumentation.

ADR 028's executable Redis proof adopts an application-owned schema version `2` that preserves every field above and adds exactly one bounded `document_cache` outcome snapshot. That backend-specific extension does not change the mandatory version-1 application floor, add a framework event field, or authorize optional payload bags. An application adopts it only with the cache proof and its exact schema, redaction, and single-sink evidence.

## Optional application-owned durable jobs

ADR 024 records one SQLite-specific application recipe and adds no framework queue or worker API. An application that adopts or changes durable jobs records the complete backend, producer transaction, envelope, idempotency, lease, retry, dead-letter, supervisor, and test policy in `.ai/jobs.md` as described in installed `docs/jobs.md`. The current skeleton includes `NOT_APPLICABLE(JOBS)` for new projects; Contract version 9 does not make that additional file a checker requirement for an existing application with no durable-job path.

An adopted SQLite path inserts its bounded versioned job envelope through the same `Connection` and explicit transaction as the business write, so commit makes both visible and rollback leaves neither. It parses stored JSON as untrusted input into one concrete readonly value, dispatches only finite code-owned type/version combinations, claims at most one row per fresh process through one complete constant `UPDATE ... RETURNING`, fences every transition with leased state, job identity, an opaque token, and an unexpired deadline, and commits the demonstrated idempotent database effect with successful completion on that same connection. Retries and dead letters are finite and persist only redacted code-owned diagnostics.

Delivery remains at least once. A process may stop after claim, a lease may expire during work, and an external destination may accept an effect while losing its response. Do not add a core job type, generic queue facade, event bus, automatic discovery, serialized PHP object, transaction callback, hidden polling or retry loop, reused mutable worker state, cross-connection atomicity claim, or exactly-once external-effect claim.

## Optional application-owned CLI and scheduler

ADR 025 records one application-owned operational console and initial single-host one-shot scheduler pattern without adding a framework CLI or scheduler API. ADR 028 replaces only the executable example's schedule file lock with one application-owned Redis owner-token lease. Framework `bin/phpthis` and installed `vendor/bin/phpthis` remain dedicated to the framework-owned `check`; an application does not register its operational commands there.

An application that adopts operational commands records one explicit console, a finite code-owned command map, one typed and bounded argument grammar, a closed exit and stdout/stderr contract, fresh composition, an explicit clock, one-pass execution, and complete subprocess tests as described in installed `docs/cli.md`. A scheduled pass additionally records its timezone and cadence, missed-run and catch-up policy, supervisor frequency, app-private overlap mechanism and namespace, topology, acquisition, expiry, renewal when applicable, contention, failure, release, crash behavior, maximum work, and coordination limit. A same-host lock remains valid only for its recorded single-host topology; a backend-specific lease requires its own accepted atomic operations and evidence. An application with no operational command or scheduler records `NOT_APPLICABLE(CLI)` in its project context.

Do not add a core command interface, registry, argument parser, scheduler facade, clock, lock, lease, daemon, process manager, command discovery, class-name dispatch, service-container resolution, hidden loop, or distributed guarantee. Composer scripts and the installed check are not an application operational console. This remains a Contract-version-7-compatible optional application clarification, not a new checker requirement or accepted PHP syntax.

## Optional application-owned database migrations

ADR 027 records one application-owned SQLite migration-ledger pattern without adding a framework migration or schema API. An application that adopts or changes migrations records the engine and supported version, sole application console command, separately authorized migration identity, permanent identifier and checksum policy, finite ordered unrolled manifest, bounded ledger, per-migration transactions, same-host lock topology, immutable forward history, backup and recovery policy, finite output and redaction, and complete test evidence in `.ai/migrations.md` as described in installed `docs/migrations.md`. The current skeleton includes `NOT_APPLICABLE(MIGRATIONS)` for new projects; Contract version 9 does not make that additional file a checker requirement for an existing application with no migration path.

An adopted SQLite path validates the complete bounded position/identifier/checksum prefix before pending work. One final application coordinator contains the finite manifest; every private migration-step method keeps its complete SQLite compile-time-constant statements at direct `Connection` calls and is invoked explicitly, so no database call occurs in a loop. Each migration and its ledger row commit through one explicit transaction. Applied identifiers, order, and checksum-covered content are immutable, and corrections are new forward migrations rather than inferred down operations.

The migration command runs only through the application's explicit operational console with fresh separately authorized state and one application-private nonblocking same-host `flock`. It never runs from the front controller, request composition, HTTP startup, framework `vendor/bin/phpthis`, command discovery, or dependency hooks. Do not add a core migration type, schema builder, DSL, runtime `.sql` loader, stored executable SQL or class name, generic database facade, transaction callback, hidden retry, automatic rollback inference, or portable-DDL claim. MySQL, PostgreSQL, and other engines require separate application-owned DDL, transaction, locking, privilege, recovery, and integration decisions.

## Application-owned request policy

ADR 020 records the canonical application composition for a protected route without adding a framework runtime API. One action-specific adapter implements `RequestHandler`, receives narrowly named application policy interfaces through its constructor, and executes visible straight-line `authenticate -> resolve tenant -> authorize -> protected handler` order. It passes concrete immutable principal, tenant, and route-specific values explicitly to the protected operation.

An application adopting this pattern must:

- choose and record its credential source, concrete principal and tenant representations, action vocabulary, tenant source, current per-request authorization rule, credential lifecycle, and concurrency policy;
- wire every authenticator, tenant resolver, and authorizer explicitly and make each implementation independently replaceable without framework edits or discovery metadata;
- keep principal, tenant, and authorization state out of `Request`, `PathParameters`, globals, generic context bags, session snapshots, and application data caches;
- give any policy reads separately named connections, budgets, and traces from protected handler work and prove that every denial stops before protected queries, writes, session mutation, cache mutation, or external business side effects;
- keep protected SQL explicitly tenant- and resource-scoped after authorization rather than relying on an implicit or global scope;
- use named exact-class denial failures, generic disclosure-safe responses, status-only known-failure summaries, class-only unknown-failure summaries, and an explicit authenticated-response cache policy;
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

Current PHPThis executable evidence covers only part of Create, List, and Get policy. Account-scoped user Create proves explicit authentication, tenant resolution, action authorization, typed input, four fixed transaction writes, and complete rollback. Its example keeps actor `account_memberships` distinct from created-user `account_users` and performs no ID-based identity inference, but it still does not establish a named identity/conflict policy. User List proves one example-owned keyset contract with a strict optional `after_user_id`, ascending identifiers, fixed 50-row pages, one-row lookahead, and one statement per accepted page. Protected document List proves a separate SQLite-only application contract with two order directions, an exact versioned composite cursor, omitted/empty/one-to-three categories, eight complete statements, and one statement per non-empty page. Those are explicit examples, not framework policies inherited by consumers. The first user Get slice does not establish authorization or tenant policy. Update and Delete have no executable reference and still require application-owned concurrency, deletion, authorization, and conflict decisions. An application must not present any operation or policy as supplied by PHPThis without concrete source, accepted local decisions, and tests.

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
- give separately named connections and terminal-summary sources distinct budgets and traces, never share observation state across sources, and do not claim atomicity across connections;
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

PHT006 proves only the finite native string type passed to the canonical direct calls. It does not parse SQL, prove a statement is safe or authorized, inspect stored procedures or server-side dynamic SQL, validate grants, or cover reflection and non-canonical invocation. Parameterization, tenant predicates, and adversarial binding probes do not replace authorization, least privilege, engine-specific integration tests, or security review. A composite cursor does not imply snapshot traversal.

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
  observability.md
  operations.md
  testing.md
docs/
  decisions/
    README.md
```

The application context records facts the framework cannot infer: domain vocabulary, accountable human decision roles, real source paths, architectural boundaries, data scale, resource limits, external side effects, runtime assumptions, verification commands, and prohibited operations. An application adopting request-handler decorators additionally records their named concern, routes, visible order, dependencies, bounded I/O, early response, response replacement, failure behavior, and tests. An application adopting ADR 034 records its selected WebSocket runtime and versions, separate composition root, handshake and message contract, current authorization, resource and delivery bounds, redaction, process/deployment ownership, and real process/socket evidence without converting frames into PHPThis HTTP values. An application adopting file transfers additionally records exact fields and limits, metadata trust, provenance, storage and cleanup ownership, permissions, authorization, retention, response headers, range policy, emission failures, deployment buffering, and real-SAPI evidence. An application adopting migrations additionally owns `.ai/migrations.md` with its engine, command, manifest, checksum, ledger, transaction, migration authority, lock, forward recovery, output, redaction, and test facts.

Keep the context compact and route tasks through `.ai/README.md`; do not load every guide for every change. Do not store credentials, tokens, private keys, customer data, production payloads, or other secrets in AI instructions. Detailed rationale and decision history belong in the application's `docs/decisions/` directory.

## Contract evolution

Clarifications may update wording without changing the contract version. The AI-authoring and accountability model clarifies how the existing application context is used; it does not change the accepted PHP program set. The automated-behavior-evidence language clarifies the existing behavior-test stage while leaving its library, runner, file placement, and organization application-owned. ADR 020 records an application-owned protected-request composition, ADR 021 records operation-owned typed input parsing and evidence, and ADR 022 records one finite SQLite application data path using the existing request, error, database, static-analysis, and application-testing contracts. ADR 023 adds one required application-owned terminal request-summary path without adding core event, sink, or coordinator types, accepted PHP syntax, or a diagnostic. ADR 024 adds one optional SQLite durable-job recipe and a new-project context route without adding a checker requirement, framework queue, core lifecycle type, accepted PHP syntax, or diagnostic. ADR 025 adds one optional application-owned CLI and single-host scheduler pattern without adding a checker requirement, core command or scheduler type, accepted PHP syntax, or diagnostic. ADR 026 adds typed bounded multipart input and an optional concrete local-file response without changing the Strict Profile. ADR 027 adds one optional application-owned SQLite migration-ledger pattern and new-project context route without adding a checker requirement, core migration or schema type, accepted PHP syntax, diagnostic, or cross-engine DDL claim. ADR 029 aligns the advertised runtime with the tested PHP 8.4 line and records the integrated application evidence without adding accepted syntax or a diagnostic. ADR 032 adds the two fixed canonical identifier route types without adding a diagnostic, binding behavior, identifier runtime, or persistence policy. ADR 033 accepts the bounded application-owned request-handler decorator pattern without adding core behavior, runtime dependencies, middleware infrastructure, or a diagnostic. ADR 034 documents one independent application-owned WebSocket proof without accepting a framework WebSocket runtime, changing application validity, or making its recipe limits universal. Consumer Contract version 9 carries Strict Profile version 2 forward unchanged. A change that accepts or rejects a materially different class of application code requires a new contract or Strict Profile version and explicit upgrade notes. Updating PHPThis never grants permission to overwrite an application's project-owned context.

Contract version 9 carries contract version 8 and Strict Profile version 2 forward. An application that does not need request-handler decorators changes no source and reruns its complete gate. An adopting application uses final named classes implementing only `RequestHandler`, gives each exactly one downstream `RequestHandler`, and makes the complete nesting order visible beside every affected `Route`. Remove any generic middleware interface, pipeline, stack, registry, priority list, discovery, `$next` callable, request-context bag, or wrapper around `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter`. Add evidence for zero-or-one downstream invocation, the exact immutable request instance, unchanged exception identity, complete immutable response-field preservation, explicit order, short-circuit non-entry, and bounded named I/O. Version 9 adds no core class, framework middleware, runtime dependency, static diagnostic, request attribute, or automatic composition.

Contract version 8 carries contract version 7 and Strict Profile version 2 forward. Existing literal, `positive-int`, and genuinely opaque `token` routes remain valid. Audit every identifier-shaped token route: when its intended representation is a UUID or ULID, change the declaration and matching `PathParameters` accessor, immediately wrap the unchanged value in an application-owned concrete identifier, and enforce any narrower domain policy before domain or database work. Add canonical success, malformed, uppercase, encoded, alternate-spelling, 404, 405, overlap, wrong-accessor, and zero-downstream-work evidence, then run the complete application gate. A deployment requiring a non-canonical external representation records a separate boundary decision instead of normalizing inside routing. Version 8 adds no data rewrite, schema change, identifier generator, model binding, database lookup, storage policy, or runtime dependency.

Contract version 7 carries contract version 6 and Strict Profile version 2 forward. Framework and skeleton Composer requirements change from `^8.4`, which also admitted unreviewed PHP 8.5 and later 8.x minors, to `~8.4.0`, which accepts the reviewed PHP 8.4 line only. An application already running PHP 8.4 changes no source for this upgrade and must rerun its complete check. An application running PHP 8.5 or later cannot adopt this contract until a separate PHPThis runtime review passes; it must not bypass Composer's platform check. An earlier release's broader Composer constraint is not PHPThis support evidence, so remaining there requires independent consumer validation. The generic unknown-failure response now uses `Cache-Control: private, no-store`; update exact response-header assertions accordingly. ADR 029's account-scoped Create, identity-separated `account_users` migration, and consumer-profile harness remain optional application evidence, not mandatory consumer domain schema or policy.

Contract version 6 carries contract version 5 and Strict Profile version 2 forward. Update the front controller and application terminal coordinator to pass parsed fields and files explicitly; configure a multipart cap only when adopting uploads. Preserve `Response::$fileBody` in every response-copy path, including session finalization and correlation. Remove case-insensitively duplicated response headers and any response header value containing an ASCII control byte or DEL. Existing non-multipart requests and string responses otherwise retain their behavior through optional arguments. An adopting application adds its exact field, upload-error, operation-limit, provenance, storage, ownership, response-header, range-deferral, emitter-failure, real-SAPI, and deployment evidence. Version 6 does not add a storage facade, automatic persistence or cleanup, client metadata trust, image processing, remote-object abstraction, or range implementation.

Contract version 5 carries contract version 4 and Strict Profile version 2 forward. Before adopting version 5, replace the separate `UnknownFailureBoundary` global-error-log flow with one application-owned terminal coordinator and sink, generate and propagate the required `X-Request-ID`, register a finite list of at most eight distinct database budget and trace sources, and add success, mapped failure, known denial where applicable, unknown failure, duplicate-query, budget-overrun, trace-bound, redaction, identifier, exactly-one-attempt, and throwing-sink tests. A sink attempt does not establish durable delivery. Complete raw SQL and explicit named parameter arrays remain application-owned at direct `Connection` call sites; version 5 does not add an ORM, repository, query builder, SQL generator, SQL/binding/placeholder helper, logger facade, middleware, service locator, discovery mechanism, or hidden instrumentation.

Contract version 4 carries contract version 3 and Strict Profile version 2 forward. It replaces ADR 017's one-trailing-positive-integer grammar with ADR 019's at-most-two full-segment grammar, adds the bounded `token` type and type-specific token access, and replaces the one-prefix metadata and index with immutable `Route::segments()` metadata and a deterministic state index. Existing literal and one-trailing-positive-integer declarations remain valid. Before adopting version 4, migrate any direct calls to `Route::literalPrefix()` or `Route::parameterName()` to `Route::segments()`, reject any newly exposed route overlap at construction, add malformed, oversized, encoded, 404, 405, and scale evidence, and run the complete application gate.

Contract version 3 carries contract version 2 and Strict Profile version 2 forward unchanged. It adds explicit response cookies and the optional native-file session lifecycle. Before adopting version 3, ensure unconditional `ext-session` availability and replace manually encoded response cookies. When adopting session state, also verify the fixed PHP 8.4 settings and an application-isolated save path, record applicable policy or explicit non-applicability, place state behind narrowly named typed services over the single lifecycle, and add the required transport and application-policy tests. Then run the complete application gate.

Contract version 2 carried contract version 1 forward and added Strict Profile version 2 with PHT006, explicit SQL data-versus-structure rules, adversarial binding evidence, and application-owned database-authority requirements. A version-1 application must complete that migration before applying the version-3 steps above: audit every canonical `Connection` call, replace arbitrary SQL strings and indirect invocation with finite direct constant-string choices, bind every data value, record runtime and migration credential separation, add the required tests, and run the complete application gate.

Contract version 1 replaced consumer-owned PHPStan configuration with the installed checker and added the runnable skeleton. A contract-version-0 application must complete that version-1 migration before applying later migrations.
