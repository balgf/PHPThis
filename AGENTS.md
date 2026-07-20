# Instructions for AI coding agents

## Authoring model

You are the primary code author and knowledge interface for work in this repository. Answer questions from the current checkout: inspect the relevant contract, decision, source, and tests, and name that evidence. Do not rely on remembered PHPThis behavior or present a proposal as an existing feature.

The human supplies intent and remains accountable for the outcome. Surface missing facts and consequential product, architecture, security, data, release, and operational choices for human judgment. You may draft a decision record, but only an accountable human may accept such a decision.

## Read order

1. Read `VISION.md`.
2. Read `.ai/README.md`.
3. Read `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/strict-profile.md`.
4. Read only the area guide named by `.ai/README.md`.
5. Inspect the concrete route, handler, and test involved in the task.

## Mandatory rules

- Use PHP 8.4 and `declare(strict_types=1);` in every PHP file.
- Make classes `final` unless a documented extension point requires otherwise.
- Use ordinary constructor injection wired manually in a composition root.
- Use `RequestHandler::handle`; do not make handlers invokable.
- Represent response cookies with `ResponseCookie`; do not encode `Set-Cookie` in the ordinary header map.
- Keep optional session state behind narrowly named typed services over one `SessionLifecycle`; give them explicit non-overlapping keys and do not access `$_SESSION`, native session calls, or generic session helpers from application code.
- Keep session mutation callbacks bounded and free of I/O or external side effects; complete fallible domain work before the final immediately committed session mutation.
- Keep routes in an explicit list. A route path is literal or contains at most two full-segment placeholders using only `{name:positive-int}` and `{name:token}`; names stay lowercase snake-like ASCII, and no discovery, attributes, reflection, or string class resolution is allowed.
- Keep request-time routing indexed. Exact literal lookup remains direct; typed routes use the deterministic state index, exact literals take precedence, request-time code does not scan route or index collections, and overlapping parameterized declarations fail at construction rather than being resolved by order or type preference.
- Deliver matched parameters only through type-specific access on immutable `PathParameters` carried by the immutable `Request` copy created by `Application`; keep `RequestHandler::handle(Request): Response`, and immediately wrap each validated integer or token in a concrete route-specific identifier.
- Keep SQL in the handler. When HTTP adaptation and an independently meaningful business transaction require separate ownership, SQL may instead live in the one narrowly named concrete operation that directly receives the final command and owns that transaction. Execute only through direct `Connection` calls; do not add a repository, service, or query layer beneath that operation.
- Treat `Connection` as native PDO transport, not a dialect abstraction; keep SQL visibly specific to the recorded engine, bind every data value, and give every placeholder occurrence a distinct portable name.
- In the ADR 022 protected document-list proof, keep every complete raw SQLite statement and its explicit named parameter array visible together at the direct `Connection` call. Do not introduce an ORM, query builder, repository, SQL/binding/placeholder helper, generic paginator, transaction callback, generated SQL, or dialect abstraction into that proof.
- Pass only SQL that PHPStan resolves natively to a finite set of non-blank compile-time constant strings. Map structural choices to finite reviewed code-owned statements or fragments, prefer complete statements, and reject an unknown selector before database work.
- Do not add an SQL sanitizer or use escaping, filtering, or validation as a substitute for bound values and compile-time-constant SQL structure.
- Give the runtime database identity only the capabilities the application path needs; keep migration and administrative authority isolated and record how that separation was verified.
- Give every request an explicit `QueryBudget` and bounded `QueryTrace`; do not write one log line per query.
- Give separately named connections explicit budgets and distinct traces; never imply cross-connection transaction atomicity.
- Keep ADR 023 observability application-owned: one visible front-controller coordinator and injected sink, one generated 128-bit lowercase-hex correlation ID propagated as `X-Request-ID`, at most eight finite code-owned database sources, and exactly one failure-isolated sink invocation attempt after response selection. Do not add core event/sink/coordinator types, a logger facade, global helper, middleware, discovery, per-query I/O, or hidden `Connection` instrumentation.
- Treat HTTP response caching and server-side data caching as separate application policies before mechanisms. Do not add a generic cache API, remember-style callback, automatic query caching, or framework-owned backend abstraction; a future server-side adoption must use a narrowly named typed application service with explicit key, value, lifetime, invalidation, stale-refill, failure, topology, observability, and test policy.
- Keep ADR 024 durable jobs application-owned and SQLite-specific: publish through the same business `Connection` and transaction, parse one bounded versioned envelope, dispatch finitely, sample an explicit clock again before handler work and after success or failure, fence transitions with an unexpired opaque lease token, make the demonstrated database effect idempotent, and process at most one delivery per fresh worker invocation. Do not add core job types, a queue facade, event bus, discovery, transaction callback, hidden retry loop, worker loop, or exactly-once external-effect claim.
- Parse external `mixed` data once through a named factory into a concrete final readonly boundary value: an operation-specific request or command for inbound data, or a projection for returned data. Use only that concrete value in downstream operation behavior. Add a typed operation seam only for an independently meaningful business or transaction responsibility; then prove rejected input makes zero seam calls and performs no operation-owned downstream I/O or mutation.
- Reject missing and unknown fields, distinguish missing from explicit `null`, validate deterministic types and recorded effective bounds before conversion, and never use a scalar cast, normalization, or sink escaping as validation.
- Treat `composer check` as the PHPThis validity gate and repair diagnostics by their stable profile rule or PHPStan identifier.
- Do not suppress a profile rule with a baseline, inline ignore, wildcard exclusion, or comment exemption.
- Never execute a database statement inside `for`, `foreach`, `while`, or recursive traversal.
- Do not add an ORM, Active Record, lazy loading, query builder, service container, facade, global helper, macro system, or dynamic proxy.
- Do not use magic methods other than `__construct`.
- Do not introduce a second way to perform an existing framework task.
- Do not invent product intent, approval, or unsupported PHPThis behavior when evidence is missing.
- Update the relevant Markdown guide with any public behavior change.
- Keep PHPStan at `level: max`; do not add a baseline, broad ignore pattern, or weaker analysis level.

## Verification

Install development dependencies once, then run the canonical check from the repository root:

```bash
composer install
composer check
```

`composer check` runs repository guardrails, maximum-level PHPStan analysis with strict rules, and tests.

For database behavior, also prove that query count stays constant when fixture cardinality increases, inspect the structured query trace for repetition, submit adversarial values through bindings, and reject unsupported structural selectors before database work. A small fixture passing under a query budget is not enough evidence.

For terminal request summaries, prove success, mapped failure, known denial where applicable, unknown failure, identifier grammar and `X-Request-ID` propagation, bounded distinct database sources, duplicate-query and budget-overrun evidence, omission of all request and SQL values, exactly one sink invocation attempt, and an unchanged response when the sink throws. Never describe an invocation attempt as durable delivery.

For a finite collection path, exercise every accepted sort and bounded-list cardinality, prove its recorded omitted and empty-input behavior with exact statement counts, traverse equal sort values in both directions without gaps or duplicates in a static fixture, and record whether cursor traversal is a snapshot. Tenant predicates, PHT006, and adversarial binding probes remain path-specific evidence rather than universal authorization or injection proof.

For an application-owned durable-job path, prove commit-visible publication and rollback exclusion, finite envelope parsing and dispatch, idle and success outcomes, exact bounded backoff from freshly observed failure time, completion rollback when handler time reaches lease expiry, lease expiry and stale-token fencing, terminal attempt and poison handling, duplicate idempotent effects, real process termination and post-expiry recovery, one delivery per fresh process, complete diagnostic redaction, and constant statement bounds across materially different queue cardinalities. Never describe at-least-once delivery as exactly once.

For an application that adopts caching, preserve the same database proof with a cold cache. A warm-cache result does not prove bounded SQL behavior.
