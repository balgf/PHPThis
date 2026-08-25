# Vision

## North star

**AI-first authoring with human accountability.**

AI is the primary code author and knowledge interface for a PHPThis application. A developer should not need to learn a separate framework manual before asking how the installed system works or requesting a change. The AI reads the installed framework contract, the application's owned context, and the concrete source and tests before it explains or implements anything.

Humans provide intent, authority, and judgment. They decide consequential product, architecture, security, data, and operational tradeoffs and remain accountable for the resulting system. PHPThis is designed to make the AI's work reviewable and verifiable, not to transfer responsibility to a model.

PHPThis therefore does not publish a traditional framework manual as its primary interface. It ships compact, versioned contracts, knowledge maps, decision records, diagnostics, source, and tests that an AI can route and a human can audit.

## Problem

Many mature frameworks improve human development speed through implicit behavior: lazy relations, facades, runtime discovery, generated proxies, convention-only bindings, and broad helper APIs. Those features enlarge the amount of non-local context an AI must infer. The resulting code can be syntactically convincing while being operationally wrong.

PHPThis reduces that inference surface. It does not attempt to make AI infallible. It makes mistakes easier to prevent, detect, and explain.

## Design principles

1. **AI-first:** framework knowledge and authoring workflows are routed for an AI working in the repository.
2. **Accountable:** consequential choices remain explicit for human judgment and approval.
3. **Local:** a change should require a small, named set of files.
4. **Literal:** executed behavior is represented by ordinary PHP calls and values.
5. **One-way:** each framework operation has one canonical execution pattern; optional application structure is selected and documented once.
6. **Typed:** all files use strict types; inputs and outputs cross explicit boundaries.
7. **Bounded:** database work, dependency depth, and core size have measurable limits.
8. **Verified:** important rules are executable checks, not prose alone.
9. **Inspectable:** SQL, routes, dependencies, errors, and side effects remain visible.
10. **Checked:** accepted PHP is a versioned subset with stable, executable diagnostics.

## Locality metric

A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision.

After universal entrypoints, a simple-endpoint change has exactly four task-specific files: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test.

Any report of this metric states the universal read cost separately, using the exact framework or application revision, ordered universal-file inventory, and recorded word and byte method. The four files are the task-specific authoring set, not the total context read. Universal authority remains mandatory and another concern's guide, policy, source, or evidence is never skipped to preserve either the file count or a smaller reported context size.

Query, form, or application-owned header input also makes an endpoint non-simple because it requires an additional boundary contract. External I/O in this metric means endpoint-owned work; the application's already-adopted outer request boundary and terminal request-summary path remain in force. If the task enters another concern or requires a new decision, it leaves this metric and follows the applicable routed guide instead.

The existing named route-area manifest constructs the dependency-free handler inline in its exact `Route` declaration. The root `Routes::create()` already includes that route area and remains unchanged. A handler with any constructor dependency follows ordinary root composition and is not a simple endpoint under this metric.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

## Performance-obscuring shorthand

PHPThis does not reject every convenience method. It rejects shorthand when its cost depends on hidden I/O, hidden iteration, runtime discovery, mutable global state, or an implicit stale-data decision. A small response constructor is acceptable; a property access that may execute SQL or a remember-style callback that may perform network and database work is not.

## Success measures

- An AI can answer a framework question from the installed version and name the contract, source, test, or decision that supports its answer.
- A human can inspect one explicitly composed development object or operation through a fresh strict process without introducing a temporary HTTP route, production command, service container, discovery, or persistent hidden state.
- The framework and application task routers preserve the exact simple-endpoint locality metric above.
- A completed change reports its behavior, evidence, resource cost, and any consequential decision that still belongs to a human.
- The request path remains directly traceable through route, handler, at most one operation-specific typed seam when required, database, and response.
- A protected request exposes its fixed authentication, tenant-resolution, authorization, and handler order with independently replaceable application policies and separately bounded policy and protected data work.
- An adopted application-owned request-handler decorator exposes its complete route-local order, one downstream handler, zero-or-one same-request invocation, response replacement, and bounded named I/O without creating a second execution model.
- Database tests compare small and large fixtures and assert a constant query count.
- The same explicit PDO transport contract passes the exact SQLite, MySQL, and PostgreSQL versions in the maintained [PDO transport certification matrix](docs/database.md#pdo-transport-certification-matrix) without a dialect abstraction.
- Direct database calls resolve to finite reviewed statements, SQL-looking values remain bound data, and unknown structural choices fail before database work.
- Every process reads exact external configuration names through one application-owned source file, validates into process-specific final readonly values before application-controlled I/O, and injects them visibly without exposing elevated credentials to HTTP.
- Complete raw engine-specific SQL and explicit named parameter arrays remain visible at direct call sites; bounded list cardinalities and cursor choices do not create generated SQL, binding helpers, or a generic paginator.
- One application-owned terminal summary correlates a selected response with bounded per-connection query evidence through exactly one failure-isolated sink invocation attempt, without claiming durable delivery or adding a framework logger.
- An adopted schema history remains application-owned, finite, checksum-locked, forward-only, and engine-specific, with explicit authority, ordering, transaction, lock, and recovery evidence rather than a framework migration abstraction.
- An adopted cache remains a backend-specific application optimization after current authorization, and any distributed lease states its owner-token, expiry, topology, outage, and non-fencing limits without becoming a generic framework API.
- An application that needs WebSockets can keep its pinned mature runtime, message boundary, current authorization, backpressure, delivery, and process lifecycle explicit in a separate composition root without adding a framework real-time runtime or adapting frames into HTTP values.
- CRUD-shaped work follows the optional feature-first reference profile or one recorded application-owned alternative without runtime discovery or filesystem enforcement.
- PHPStan passes at `level: max` with strict rules and no baseline.
- Every PHPThis-owned profile rule has a permanent identifier and passing and failing fixtures.
- All framework PHP files pass the strict-types and no-magic guardrails.
- Markdown files continue to outnumber PHP files.
- Core source remains at or below the 2,620-line limit enforced by repository guardrails. ADR 026 raised the prior ceiling to 2,500 for bounded typed multipart ingestion and concrete local-file emission. ADR 032 raised it only for canonical UUID and ULID routing; that implementation occupied 2,593 lines after one sensitive-password annotation. ADR 033 added no core runtime or further increase. ADR 045 used the remaining seven lines only for bounded session-cleanup failure retention while its response-framing refactor was line-neutral. The tagged Alpha 6 framework source removed the redundant public-prerelease `PathParameters::onePositiveInteger()` convenience factory and occupies 2,595 lines under its historical 2,600-line ceiling. ADR 049 raises the current accepted ceiling only to 2,620 lines for the final readable 2,618-line response-cookie correction. Its remaining two lines are unallocated and authorize no adjacent cookie attribute, helper, authentication mechanism, session mechanism, response feature, or other adjacent mechanism.

## Non-goals

- Maintaining a tutorial-style framework manual as the canonical knowledge interface.
- Treating AI output as authority or removing human responsibility for software decisions and outcomes.
- Recreating a convention-heavy full-stack framework with different names.
- Hiding SQL behind models or a fluent query language.
- Treating a generic sanitizer, identifier-quoting helper, or query builder as a substitute for bound data and finite reviewed statement choices.
- Hiding complete statements or parameter ownership behind an ORM, repository, SQL/binding/placeholder helper, generic paginator, transaction callback, generated SQL, or dialect abstraction.
- Forcing an application directory layout or turning CRUD into a generic persistence API.
- Providing a generic cache facade, automatic query cache, or backend abstraction that hides topology, invalidation, failure, and consistency choices.
- Providing a generic distributed-lock or lease abstraction, automatic renewal, or a fencing or exactly-once claim unsupported by the selected backend and protected operation.
- Providing generic or framework middleware, a middleware pipeline, or a request-context, identity, tenant, or authorization engine that hides application policy or its I/O. ADR 033's route-local application-owned request-handler decorator is not such an engine.
- Providing a WebSocket server, client, event loop, connection manager, daemon, supervisor, generic channel, broadcaster, pub/sub, real-time middleware, connection context, automatic retry, replay, acknowledgement, reconnect, or exactly-once delivery API; ADR 034 keeps those concerns application-owned in a pinned third-party runtime.
- Providing a global logger, facade, middleware logger, event bus, automatically discovered sink, per-query logging, or hidden database instrumentation.
- Providing a framework configuration service, string-keyed bag, global `config()` helper, facade, provider, container binding, discovery, automatic dotenv loader, secret-manager abstraction, or hidden reload.
- Providing a generic upload, storage, filesystem, or stream facade; trusting client filenames or media types; or hiding persistence, cleanup, ranges, content processing, or file ownership.
- Providing a core migration API, schema builder, migration DSL, automatic discovery, inferred rollback, runtime SQL loading, HTTP-startup migration, or portable DDL guarantee.
- Providing a framework-owned production shell, container-backed console, administrative execution path, generic dispatcher, or remotely accessible Workbench.
- Supporting multiple equivalent styles for the same task.
- Eliminating the need for PHP, database, security, and operational expertise when reviewing or operating a real system.
- Claiming that raw SQL by itself prevents inefficient access patterns.
