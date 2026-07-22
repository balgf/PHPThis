# AI coding instructions for this PHPThis application

This file is the universal entrypoint for AI-authored changes in this application.

Before product feature work, replace the skeleton's generic facts in `.ai/project.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, and `.ai/operations.md` with verified application facts or explicit not-applicable statements.

## Authoring model

You are the primary code author and knowledge interface for this application. When asked how PHPThis or this project works, inspect the installed version, application context, concrete source, and tests, then name the evidence supporting your answer. Do not rely on remembered framework behavior or present a proposal as an existing feature.

The human supplies intent and remains accountable for the outcome. Surface missing facts and consequential product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment. You may investigate options and draft a decision record. Acceptance requires explicit approval from an accountable human; you may record that approval in the decision record.

## Read order

1. Read `vendor/phpthis/framework/docs/consumer-contract.md`.
2. Read `vendor/phpthis/framework/docs/knowledge-map.md`.
3. Read `.ai/README.md`.
4. Read `.ai/rules.md` and `.ai/change-workflow.md`.
5. Read only the task-specific guide selected by `.ai/README.md`.
6. Inspect the concrete source and tests on the execution path.

If the installed contract or knowledge map is missing in a fresh checkout, read `.ai/operations.md` only far enough to install dependencies, then restart this read order. If either remains unavailable, report the missing dependency instead of inventing framework behavior. Do not substitute the framework-maintainer `AGENTS.md` or `.ai/` directory for this application context.

## Project gate

Run `composer check` from the application root. A task is not complete until it passes. Focused behavior tests may shorten the repair loop but do not replace the complete check.

Every observable behavior change must add or update application-owned automated tests. The application owns the test library, runner, file placement, and organization. Static analysis, documentation, manual verification, and a no-op test command do not satisfy this requirement.

## Authority

- The installed PHPThis Consumer Contract v9 and Strict Profile v2 are the minimum accepted rules, including the PHP 8.4 runtime boundary, bounded multiple-typed routing, optional bounded application-owned request-handler decorators, explicit cookie/session and file-transfer boundaries, PHT006 finite compile-time-constant SQL, and the application-owned terminal request summary.
- This application's `.ai/` guides add project-specific facts and may strengthen those rules.
- Preserve the installed contract when a project instruction conflicts with it, and report the conflict.
- Distinguish installed framework behavior, application policy, and new proposals in explanations and implementation reports.
- Never add a baseline, suppression, hidden fallback, or second framework pattern to make a change pass.

`NOT_APPLICABLE(RESOURCE_ROUTE_IDENTIFIERS)`: the health-only starter has no path parameter or resource lookup. Before adding one, choose the narrowest fixed declaration: `positive-int`, `uuid`, or `ulid` for that canonical representation, and `token` only for a genuinely opaque identifier. Read it through the matching `PathParameters::positiveInteger()`, `uuid()`, `ulid()`, or `token()` accessor, immediately wrap the unchanged value in an application-owned route-specific identifier, and apply narrower domain rules before database work. Routing performs no normalization, domain binding, record lookup, identifier generation, persistence choice, or type fallback. Tests must prove invalid syntax selects `404` with zero handler and database work, while a canonical valid path with the wrong method selects `405`.

`NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`: the health-only starter composes `HealthHandler` directly and has no application-owned request-handler decorator. Before adding one, record in `.ai/architecture.md` and `.ai/testing.md` its final class, exactly one downstream `RequestHandler`, affected routes, visible unrolled nesting order, zero-or-one delegation with the exact same immutable `Request` instance, unchanged exception propagation, explicit immutable `Response` replacement and complete field preservation, and every named bounded side effect. Do not add a generic or framework middleware interface, pipeline, iterable registry, priorities, discovery, `$next` abstraction, context bag, hidden binding, or hidden I/O, and do not wrap `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter`.

`NOT_APPLICABLE(DATABASE)`: before introducing database work, replace `.ai/data.md` with verified SQL-structure and bounded-list choices, least-privileged runtime authority and prohibited capabilities, isolated migration or administrative authority, and verification sources and dates.

`NOT_APPLICABLE(INPUT)`: the health-only starter accepts no application-owned body, query, form, or header fields and creates no operation command. Before an operation accepts external data, record its raw source, complete bounds, absent-versus-null and unknown-field policy, exact canonical representations, field-specific normalization or explicit lack of normalization, typed request or command, downstream behavior or justified typed seam, parser position relative to request policy, generic failure contract, and duplicate-key proof limit in `.ai/architecture.md`; prove in `.ai/testing.md` that invalid input performs no operation-owned downstream I/O or mutation and makes zero typed-seam calls when one exists. Separately bound any policy work ordered before parsing. Do not add a generic validator, string-rule language, automatic hydration, mass assignment, or sanitization magic.

`NOT_APPLICABLE(WEBSOCKETS)`: the health-only starter has no WebSocket runtime or process. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md`, obtain approval for the security and operational policy, and replace the marker in `.ai/websockets.md`. Keep the selected mature third-party runtime, separate process, handshake, current authentication and authorization, bounded typed command and operation, sequential send path, connection and lifecycle limits, redacted summary, supervisor, scaling decisions, and real process/socket evidence application-owned. Frames never become PHPThis HTTP `Request` or `Response` values. Do not add core WebSocket primitives, HTTP adaptation, generic middleware, gateways, channels, broadcasters, pub/sub, context, discovery, hidden retry, replay, acknowledgement, resume, or exactly-once claims.

`NOT_APPLICABLE(FILE_TRANSFER)`: the health-only starter has no upload or download route, multipart byte limit, temporary-file ownership, application file path, or file-body response. The front controller still forwards PHP's parsed form and file arrays through the terminal coordinator; that wiring does not enable multipart. Before adoption, read installed `docs/file-transfers/README.md` and replace `.ai/file-transfers.md` with the exact accepted routes, limits, metadata treatment, file lifecycle, response headers, failures, redaction, and evidence.

`NOT_APPLICABLE(REQUEST_POLICY)`: the health-only starter has no identity, tenant, or protected action. Before protecting a route, read installed `docs/request-policy.md` and replace the request-policy sections in `.ai/architecture.md` and `.ai/testing.md` with verified principal, tenant, credential, current authorization, disclosure, query-bound, transaction, redaction, and replacement decisions. Use one explicit action-specific adapter; do not replace or obscure it with an application-owned request-handler decorator, generic or framework middleware, a request-context bag, hidden tenant resolution, or an implicit authorization scope.

`NOT_APPLICABLE(SESSION)`: before introducing session-backed behavior, replace the session sections in `.ai/architecture.md`, `.ai/operations.md`, and `.ai/testing.md` with verified typed key ownership, cookie and isolated native-file storage, concurrency and transport evidence, plus each applicable identity, expiry, revocation, and CSRF policy or explicit non-applicability.

The starter's terminal request summary is mandatory rather than optional: preserve its application-owned front-controller coordinator and injected sink, generated 128-bit lowercase-hex correlation ID, `X-Request-ID`, closed redacted event, and exactly one failure-isolated sink invocation attempt. Before database adoption, register every request-scoped connection that can execute in this path through at most eight finite code-owned sources with distinct budgets and traces. Do not move terminal observability into an application-owned request-handler decorator or add framework logging types, generic or framework logging middleware, facades, helpers, discovery, per-query log I/O, hidden instrumentation, or a durable-delivery claim.

`HTTP_CACHE_POLICY(NO_STORE)`: the starter emits explicit `Cache-Control: no-store` on health success, route miss, method rejection, and mapped invalid or oversized input; its generic unknown-failure response emits `Cache-Control: private, no-store`. Preserve those exact policies for every current path. Start a path added later with `no-store`; use `private` or `public` only after its application-owned freshness or revalidation, validators, `Vary`, intermediary topology, observability, and tests are recorded. Do not add a helper, application-owned request-handler decorator, generic or framework middleware default, or response post-processor, and keep this decision separate from server-side data caching.

`NOT_APPLICABLE(CACHE)`: before introducing server-side caching, replace the cache sections in `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, and `.ai/testing.md` with verified narrowly named typed service ownership, backend and topology, versioned environment- and tenant-scoped keys, bounded payloads and finite TTLs, invalidation, stale-refill and failure behavior, stampede ownership, observability, and cold, warm, failure, isolation, stale-refill race, and concurrency evidence. Do not add a generic cache helper or treat cached data as authoritative.

`NOT_APPLICABLE(JOBS)`: before introducing durable deferred work, read installed `docs/jobs.md` and replace `.ai/jobs.md` with verified backend, transaction, envelope, idempotency, lease, retry, dead-letter, worker, supervisor, redaction, and test decisions. Do not add a framework queue, discovery, event bus, transaction callback, worker loop, or exactly-once external-effect claim.

`NOT_APPLICABLE(CLI)`: the health-only starter has no operational application console or scheduled pass. Composer scripts and `vendor/bin/phpthis check` are development gates, not an adopted application CLI. Before adding one, read installed `docs/cli.md` and replace `.ai/cli.md` plus the CLI sections in `.ai/operations.md` and `.ai/testing.md` with the sole console path, finite command and typed-argument map, closed exit and stream contract, fresh composition, explicit clock and cadence, one-pass behavior, same-host lock and topology, supervisor, redaction, and real-console evidence. Do not add application commands to `vendor/bin/phpthis`, command discovery, a service container, scheduler facade, daemon, hidden loop, or distributed-coordination claim.

`NOT_APPLICABLE(MIGRATIONS)`: the health-only starter has no database or migration path. Before adopting one, read installed `docs/migrations.md` and replace `.ai/migrations.md` plus the migration sections in `.ai/data.md`, `.ai/operations.md`, and `.ai/testing.md` with the exact engine, sole console command, separate authority, final concrete coordinator, finite ordered manifest and unrolled private step methods, permanent identifiers, checksum source, bounded ledger, per-migration transaction, same-host lock, immutable forward recovery, finite redacted output, and complete real-console evidence. Never run migrations during HTTP startup or add a framework migration API, per-migration classes, schema builder, DSL, discovery, runtime `.sql` loading, generic database facade, inferred rollback, database call in a loop, or cross-engine claim.

## Context safety

Do not write credentials, tokens, private keys, customer data, production payloads, or secrets into AI context, source comments, fixtures, logs, or reports. Use documented secret references and redacted examples.
