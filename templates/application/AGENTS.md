# AI coding instructions for {{PROJECT_NAME}}

This file is the universal entrypoint for AI-authored changes in this application. Replace every double-braced template token before feature work begins.

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

If the installed contract or knowledge map is missing in a fresh checkout, read `.ai/operations.md` only far enough to run its dependency-install command, then restart this read order. If either remains unavailable, report the missing dependency instead of inventing or copying framework rules. If Composer uses a non-default vendor directory, update both installed-framework paths above. Do not substitute PHPThis's framework-maintainer `AGENTS.md` or `.ai/` directory for this application context.

## Project gate

Run the complete application validity gate documented in `.ai/testing.md` from the repository root. A task is not complete until that command passes. A focused test may shorten the repair loop but does not replace the complete check.

Every observable behavior change must add or update application-owned automated tests. The application owns the test library, runner, file placement, and organization. Static analysis, documentation, manual verification, and a no-op test command do not satisfy this requirement.

## Authority

- The installed PHPThis Consumer Contract v7 and Strict Profile v2 are the minimum accepted rules, including the PHP 8.4 runtime boundary, bounded multiple-typed routing, explicit cookie/session and file-transfer boundaries, PHT006 finite compile-time-constant SQL, and the application-owned terminal request summary.
- This application's `.ai/` guides add project-specific facts and may strengthen those rules.
- If a project instruction conflicts with the consumer contract, preserve the contract and report the conflict.
- Distinguish installed framework behavior, application policy, and new proposals in explanations and implementation reports.
- Never add a baseline, broad ignore, hidden fallback, or second framework pattern to make a change pass.

Before database work, `.ai/data.md` must identify the reviewed SQL-structure mappings and bounded-list choices, runtime database authority and prohibited capabilities, isolated migration or administrative authority, and the source and date that those facts were verified. Do not infer them from successful connectivity.

Before an operation accepts JSON, query values, form data, headers, or another external representation, `.ai/architecture.md` must record its raw source, byte/depth/field/list/scalar bounds, absent-versus-null and unknown-field policy, exact canonical representations, field-specific normalization or explicit lack of normalization, typed request or command, downstream behavior or justified typed seam, parser position relative to request policy, public failure contract, and duplicate-key proof limit where applicable. Parse once through one operation-specific named factory into a final readonly value with a private constructor; `.ai/testing.md` must prove invalid input performs no operation-owned downstream I/O or mutation and makes zero typed-seam calls when one exists. Separately record and bound any transport, session, authentication, tenant, or authorization work ordered before parsing. Do not add a generic validator, string-rule language, automatic hydration, mass assignment, or sanitization magic.

Before a file upload or download is accepted, read installed `docs/file-transfers/README.md` and complete `.ai/file-transfers.md` with the exact routes, transport and per-file limits, cardinality, metadata treatment, concrete file lifecycle, permissions, response headers, range policy, failures, redaction, and end-to-end evidence. Forward the runtime's parsed form and file values explicitly at the front controller, and enable multipart only with a recorded finite request limit.

Before adding a protected route, read the installed `docs/request-policy.md` and record the application principal, tenant, action, credential, expiry, revocation, failure-disclosure, policy-query, protected-query, transaction, and test decisions in `.ai/architecture.md`, `.ai/operations.md`, and `.ai/testing.md`. Keep one visible action-specific `authenticate -> resolve tenant -> authorize -> handler` adapter with independently replaceable policies; do not add middleware, a request-context bag, hidden tenant resolution, or an implicit authorization scope.

Before session-backed work, `.ai/architecture.md`, `.ai/operations.md`, and `.ai/testing.md` must record typed service and non-overlapping key ownership, cookie and isolated save-path policy, deployment and transport tests, plus each applicable authentication, authorization, regeneration, expiry, logout, revocation, and CSRF policy. Mark absent concerns explicitly not applicable; session-free applications mark only session transport and session-backed concerns not applicable.

Keep one application-owned terminal request-summary coordinator and one injected sink at the visible front-controller boundary. `.ai/observability.md` owns the generated correlation, response propagation, finite database-source, destination, and attempt facts; `.ai/architecture.md`, `.ai/operations.md`, and `.ai/testing.md` link to that authority and add only their local boundary, runtime, and evidence facts. A throwing-sink test proves the selected response cannot change. Do not add framework logging types, middleware, facades, global helpers, discovery, per-query log I/O, hidden instrumentation, or a durable-delivery claim.

Before cache work, record HTTP response caching and server-side data caching as separate decisions. Every HTTP response path requires a response-specific `no-store`, `private`, or `public` policy plus freshness or revalidation, validators, and `Vary` behavior where applicable in `.ai/architecture.md`, `.ai/operations.md`, and `.ai/testing.md`. Start new or unreviewed success and failure paths with an explicit `Cache-Control: no-store` header; do not implement that policy through a helper, middleware default, or response post-processor. Server-side adoption additionally requires `.ai/data.md` and `.ai/integrations.md` to record adoption or `NOT_APPLICABLE(CACHE)`, narrowly named typed service ownership, backend and topology, versioned environment- and tenant-scoped keys, bounded payloads and TTLs, invalidation, stale-refill and failure behavior, stampede ownership, cache observability, and cold, warm, failure, isolation, stale-refill race, and concurrency evidence. A cache remains derived application data, never a source of truth or an implicit application-wide helper.

Before durable-job work, read installed `docs/jobs.md` and record adoption or `NOT_APPLICABLE(JOBS)` in `.ai/jobs.md`. An adopted path must name its backend and version, same-transaction producer boundary, finite envelope and parser, idempotency owner, lease and bounded retry policy, redacted dead-letter codes, one-shot worker lifecycle, supervisor, and complete tests. Do not add core job types, a generic queue facade, event bus, discovery, serialized PHP objects, transaction callback, hidden retry or polling loop, or exactly-once external-effect claim.

Before operational CLI or scheduler work, read installed `docs/cli.md` and record adoption or `NOT_APPLICABLE(CLI)` in `.ai/cli.md`, `.ai/operations.md`, and `.ai/testing.md`. An adopted pattern must name the sole application console, finite command and typed-argument map, closed exit and stream contract, fresh composition, explicit clock and cadence, one-pass behavior, same-host overlap lock and topology, supervisor, redaction, and real-console evidence. Keep framework `vendor/bin/phpthis` dedicated to `check`; do not add command discovery, a container resolver, scheduler facade, daemon, hidden loop, or distributed-coordination claim.

Before migration work, read installed `docs/migrations.md` and record adoption or `NOT_APPLICABLE(MIGRATIONS)` in `.ai/migrations.md`, `.ai/data.md`, `.ai/operations.md`, and `.ai/testing.md`. An adopted pattern must name the exact engine, sole application console command, separate migration authority, final concrete coordinator, finite ordered manifest and unrolled private step methods, permanent identifiers, checksum source, bounded ledger, per-migration transactions, same-host lock topology, immutable forward recovery, finite redacted output, and complete real-console evidence. Never run migrations during HTTP startup or add a framework migration API, per-migration classes, schema builder, DSL, discovery, runtime `.sql` loading, stored executable SQL or classes, generic database facade, inferred rollback, database call in a loop, or cross-engine claim.

## Context safety

Do not write credentials, tokens, private keys, customer data, production payloads, or secrets into `AGENTS.md`, `.ai/`, source comments, fixtures, logs, or reports. Use documented secret references and redacted examples.
