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
- Keep routes in an explicit list. A route path is literal or has the one accepted trailing full-segment `{name:positive-int}` form; do not add other dynamic shapes, discovery, attributes, reflection, or string class resolution.
- Keep request-time routing indexed. Literal routes retain direct lookup; typed routes use the bounded prefix index, literal matches take precedence, and ambiguous typed declarations fail at construction rather than being resolved by order.
- Deliver matched parameters only through immutable `PathParameters` on the immutable `Request` copy created by `Application`; keep `RequestHandler::handle(Request): Response`, and immediately wrap a validated path integer in a concrete route-specific identifier.
- Keep SQL in the handler or its narrowly named query object and execute it only through direct `Connection` calls.
- Treat `Connection` as native PDO transport, not a dialect abstraction; keep SQL visibly specific to the recorded engine, bind every data value, and give every placeholder occurrence a distinct portable name.
- Pass only SQL that PHPStan resolves natively to a finite set of non-blank compile-time constant strings. Map structural choices to finite reviewed code-owned statements or fragments, prefer complete statements, and reject an unknown selector before database work.
- Do not add an SQL sanitizer or use escaping, filtering, or validation as a substitute for bound values and compile-time-constant SQL structure.
- Give the runtime database identity only the capabilities the application path needs; keep migration and administrative authority isolated and record how that separation was verified.
- Give every request an explicit `QueryBudget` and bounded `QueryTrace`; do not write one log line per query.
- Give separately named connections explicit budgets and distinct traces; never imply cross-connection transaction atomicity.
- Treat HTTP response caching and server-side data caching as separate application policies before mechanisms. Do not add a generic cache API, remember-style callback, automatic query caching, or framework-owned backend abstraction; a future server-side adoption must use a narrowly named typed application service with explicit key, value, lifetime, invalidation, stale-refill, failure, topology, observability, and test policy.
- Parse external `mixed` data once through a named factory into a concrete final readonly boundary value: an operation-specific request or command for inbound data, or a projection for returned data.
- Reject missing and unknown fields and validate before conversion; never use a scalar cast as validation.
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

For an application that adopts caching, preserve the same database proof with a cold cache. A warm-cache result does not prove bounded SQL behavior.
