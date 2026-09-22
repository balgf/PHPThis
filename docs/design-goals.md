# Detailed design goals

Read this companion when reviewing framework direction, a concern's design goal, or its rationale. [VISION.md](../VISION.md) retains the universal principles, accountability, locality metric, and validity expectations. Current operational rules remain in the selected concern guide. Moving these goals here changes their location, not their force.

## Problem

Many mature frameworks improve human development speed through implicit behavior: lazy relations, facades, runtime discovery, generated proxies, convention-only bindings, and broad helper APIs. Those features enlarge the amount of non-local context an AI must infer. The resulting code can be syntactically convincing while being operationally wrong.

PHPThis reduces that inference surface. It does not attempt to make AI infallible. It makes mistakes easier to prevent, detect, and explain.

## Performance-obscuring shorthand

PHPThis does not reject every convenience method. It rejects shorthand when its cost depends on hidden I/O, hidden iteration, runtime discovery, mutable global state, or an implicit stale-data decision. A small response constructor is acceptable; a property access that may execute SQL or a remember-style callback that may perform network and database work is not.

## Success measures

- A human can inspect one explicitly composed development object or operation through a fresh strict process without introducing a temporary HTTP route, production command, service container, discovery, or persistent hidden state.
- The request path remains directly traceable through route, handler, at most one operation-specific typed seam when required, database, and response.
- A protected request exposes its fixed authentication, tenant-resolution, authorization, and handler order with independently replaceable application policies and separately bounded policy and protected data work.
- An adopted application-owned request-handler decorator exposes its complete route-local order, one downstream handler, zero-or-one same-request invocation, response replacement, and bounded named I/O without creating a second execution model.
- Database tests compare small and large fixtures and assert a constant query count.
- The same explicit PDO transport contract passes the exact SQLite, MySQL, and PostgreSQL versions in the maintained [PDO transport certification matrix](database.md#pdo-transport-certification-matrix) without a dialect abstraction.
- Direct database calls resolve to finite reviewed statements, SQL-looking values remain bound data, and unknown structural choices fail before database work.
- Every process reads exact external configuration names through one application-owned source file, validates into process-specific final readonly values before application-controlled I/O, and injects them visibly without exposing elevated credentials to HTTP.
- Complete raw engine-specific SQL and explicit named parameter arrays remain visible at direct call sites; bounded list cardinalities and cursor choices do not create generated SQL, binding helpers, or a generic paginator.
- One application-owned terminal summary correlates a selected response with bounded per-connection query evidence through exactly one failure-isolated sink invocation attempt, without claiming durable delivery or adding a framework logger.
- An adopted schema history remains application-owned, finite, checksum-locked, forward-only, and engine-specific, with explicit authority, ordering, transaction, lock, and recovery evidence rather than a framework migration abstraction.
- An adopted cache remains a backend-specific application optimization after current authorization, and any distributed lease states its owner-token, expiry, topology, outage, and non-fencing limits without becoming a generic framework API.
- An application that needs WebSockets can keep its pinned mature runtime, message boundary, current authorization, backpressure, delivery, and process lifecycle explicit in a separate composition root without adding a framework real-time runtime or adapting frames into HTTP values.
- CRUD-shaped work follows the optional feature-first reference profile or one recorded application-owned alternative without runtime discovery or filesystem enforcement.
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
