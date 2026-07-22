# Global implementation rules

## Required

- Write direct, typed PHP with strict types.
- Prefer public data in immutable request/response values and explicit methods for behavior.
- Construct dependencies manually where the application starts.
- Keep I/O visible in method names and call sites.
- Parse external `mixed` values once into concrete final readonly boundary values: operation-specific requests or commands for inbound data, and projections for returned data.
- Execute application SQL only through direct `Connection` calls, bind every data value with a distinct named placeholder per occurrence, and keep the final SQL a finite non-blank compile-time constant.
- Map external SQL-structure selectors to finite reviewed code-owned choices and reject unknown selectors before database work.
- Keep every adopted cache read, write, and invalidation visible behind a narrowly named typed application service and an explicitly wired backend; parse cache hits as untrusted external values before use.
- Keep durable-job publication, claim, effect, completion, retry, and dead-letter SQL visible in the application-owned backend-specific path; parse stored envelopes as untrusted values and dispatch only finite code-owned type/version combinations.
- Keep protected request policy in one application-owned action-specific adapter with explicit `authenticate -> resolve tenant -> authorize -> handler` order, concrete immutable principal and tenant values, and independently replaceable constructor-injected policies.
- Give any policy reads distinct named connections, budgets, and traces from protected handler work; every denial must stop before protected queries, writes, session mutation, cache mutation, or external business side effects.
- Keep every application-owned request-handler decorator final, route-local, and explicit: implement only `RequestHandler`, own one narrowly named route concern, wrap exactly one downstream `RequestHandler`, construct any nesting as one visible unrolled expression beside the route, delegate zero or one time with the exact same immutable `Request` instance, propagate its own and downstream exceptions unchanged, replace a response only through an explicit immutable `Response` that preserves every unchanged field, and name, bound, and test every owned side effect.
- Keep one application-owned ADR 023 terminal coordinator and sink at the visible front-controller boundary, with generated correlation, finite distinct database sources, complete redaction, and exactly one failure-isolated invocation attempt.
- Keep ADR 026 file transfer explicit: one typed bounded multipart upload, application-owned provenance and storage, one concrete local-file body, exact framing, fixed-chunk emission, and no range implementation.
- Keep ADR 027 schema migration explicit and application-owned: one SQLite-only console path, finite ordered unrolled manifest, checksum-locked immutable history, bounded inspectable ledger, one transaction per migration, separate migration authority, and one application-private same-host lock.
- Pass the complete Strict Profile; PHP execution without `composer check` is not sufficient verification.
- Add a test for success, expected failure, and resource bounds when relevant.
- Use one stable term for each concept: route, handler, application-owned request-handler decorator, connection, request, request upload, response, local file body, response cookie, session lifecycle, session snapshot, session unavailable, query budget, query trace, terminal request summary, correlation ID, sink invocation attempt, HTTP cache policy, application cache service, stale-refill race, application migration, migration manifest, migration ledger, migration drift.

## Forbidden

- Runtime discovery, reflection-based wiring, dynamic properties, macros, facades, service location, hidden globals, and magic methods except constructors.
- Database calls in loops or property access that can perform I/O.
- Positional SQL parameters, interpolated data values, runtime-built SQL structure, SQL sanitizers, `SELECT *`, and unbounded collection reads.
- A runtime database identity with migration, schema-change, user-management, or other authority not required by its application paths.
- Silent exception conversion, implicit retries, or default success values after failure.
- Scalar casts or conversion functions used as validation for `mixed` input.
- Reflection hydration, generic domain collections, and unvalidated arrays crossing a boundary.
- Aliases or shortcuts that provide a second spelling for existing behavior.
- Direct application access to `$_SESSION`, native `session_*` calls, generic session helpers, or authentication state stored without a typed application boundary.
- Generic or framework middleware interfaces, pipelines, iterable registries, priority ordering, discovery, `$next` abstractions, generic request-context or attribute bags, hidden binding or I/O, service-located policies, hidden tenant resolution, implicit or global authorization scopes, and treating stored or cached identity as current authorization.
- Generic cache facades or bags, remember-style callbacks, implicit cache fallback or retries, automatic query caching, hidden cache middleware, unbounded keys or values, implicit forever lifetimes, and claims that distinct backends are behaviorally interchangeable.
- Framework job types, generic queue or worker facades, event buses, automatic job discovery, serialized PHP objects, hidden after-commit callbacks, in-process polling or retry loops, and exactly-once external-effect claims.
- Core logging event, sink, or coordinator types; logger facades, global log helpers, generic or framework logging middleware, observability inside an application-owned request-handler decorator, event pipelines, automatic sink discovery, per-query log I/O, hidden database instrumentation, or claims that one sink invocation attempt guarantees delivery.
- Raw `$_FILES` outside the front controller, trusted client file metadata, generic storage or stream facades, automatic file persistence or cleanup, image processing, and partial range support.
- Core migration types, schema builders or DSLs, automatic migration discovery, runtime `.sql` loading, stored executable SQL or class names, migration database calls in loops, inferred down migrations, HTTP-startup migrations, and a SQLite proof presented as portable DDL.
- Baselines, inline ignores, wildcard exclusions, or comment exemptions for Strict Profile findings.
- Invented product intent, inferred human approval, or claims about PHPThis behavior unsupported by the current checkout.

If a task appears to require a forbidden mechanism, stop and propose a decision record describing the concrete need and a more explicit alternative.
