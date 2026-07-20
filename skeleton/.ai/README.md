# Application AI context index

This directory is owned by the consuming application. It grounds the AI that explains and authors this project; it is not a framework manual. Replace its generic starter facts with verified project facts before adding product behavior. Keep it committed, current, concise, and free of secrets.

Always read:

1. `.ai/rules.md`
2. `.ai/change-workflow.md`
3. `.ai/project.md`

Then read only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Explain framework or application behavior | installed PHPThis knowledge map, matching application guide | installed framework source, application execution path, and tests |
| Change structure or dependencies | `.ai/architecture.md` | `bootstrap.php` and the affected source boundary |
| Add or change a route | `.ai/architecture.md` | `src/Routes.php`, the route area, handler, and tests |
| Introduce inbound operation data | installed `vendor/phpthis/framework/docs/type-safety.md`, `.ai/architecture.md`, `.ai/testing.md` | raw representation and bounds, operation-specific parser factory, final readonly request or command, downstream typed behavior or justified seam, request-policy order, public error mapping, and adversarial tests |
| Protect a route or change identity, tenant, or authorization policy | installed `vendor/phpthis/framework/docs/request-policy.md`, `.ai/request-policy.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/operations.md`, `.ai/testing.md` | `bootstrap.php`, action-specific policy adapter, concrete principal and tenant values, policy and protected connections, exact denial registrations, and order, denial, redaction, and replacement tests |
| Introduce cookie-backed session state | installed `vendor/phpthis/framework/docs/sessions.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | `bootstrap.php`, typed key ownership, isolated save path, mandatory transport evidence, and each applicable security-policy test |
| Resolve or change HTTP response cache policy | installed `vendor/phpthis/framework/docs/caching.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | response-producing path, explicit `no-store`, `private`, or `public` policy, freshness or revalidation, validators, `Vary`, intermediary topology, and behavior tests |
| Introduce server-side cached data | installed `vendor/phpthis/framework/docs/caching.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, `.ai/testing.md` | `bootstrap.php`, narrowly named typed service, authoritative data path, backend boundary, key and tenant ownership, bounds, invalidation, observability, and cold, warm, failure, and concurrency tests |
| Introduce durable deferred work | installed `vendor/phpthis/framework/docs/jobs.md`, `.ai/jobs.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, `.ai/testing.md` | producer transaction, complete job SQL, versioned envelope parser, finite dispatch, idempotent effect, lease and retry policy, one-shot worker composition, and crash plus redaction tests |
| Introduce an operational application command or scheduled pass | installed `vendor/phpthis/framework/docs/cli.md`, `.ai/cli.md`, `.ai/operations.md`, `.ai/testing.md`, and `.ai/jobs.md` when invoking durable work | sole application console, finite command map, typed argument boundary, exit and stream contract, explicit clock and cadence, one-pass operation, same-host overlap lock, supervisor, composition root, and real-console tests |
| Introduce CRUD-shaped resource operations | installed `vendor/phpthis/framework/docs/crud.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/testing.md` | explicit resource routes, operation area, data path, and behavior tests |
| Add data access or a structural SQL selector | `.ai/data.md`, `.ai/testing.md` | schema authority, direct `Connection` call, finite code-owned SQL mapping, runtime authority, and adversarial and scale tests |
| Add an external side effect | `.ai/integrations.md` | the named client boundary and failure tests |
| Change runtime or logging | `.ai/operations.md` | `public/index.php`, `bootstrap.php`, and operational tests |
| Change request correlation or terminal summaries | installed `vendor/phpthis/framework/docs/observability/README.md`, `.ai/observability.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | `public/index.php`, application-owned coordinator and sink, finite database sources, response propagation, redaction, budget, trace, and throwing-sink tests |
| Add or change tests | `.ai/testing.md` | `tests/run.php` and `composer check` |

`NOT_APPLICABLE(CRUD_PROFILE)`: the health-only starter has no CRUD-shaped resource behavior or CRUD directory convention. Before adding one, record adoption of the installed optional profile or one coherent alternate organization. Consumer Contract v5 and Strict Profile v2 remain mandatory.

`NOT_APPLICABLE(INPUT)`: the health-only starter accepts no application-owned body, query, form, or header fields and creates no operation request or command. Its outer `RequestBoundary` still validates and bounds PHP runtime transport input. Before adding product input, record and test one operation-specific typed boundary in the existing `.ai/architecture.md` and `.ai/testing.md`; do not add a generic input guide or validation mechanism.

`NOT_APPLICABLE(REQUEST_POLICY)`: the health-only starter has no credential, principal, tenant, protected action, policy query, or authorization decision. Before protecting a route, adopt the installed application-owned request-policy composition and record every project-specific security decision and test.

`NOT_APPLICABLE(SESSION)`: the health-only starter does not configure `SessionLifecycle` or issue cookies. Authentication, authorization, credential expiry, revocation, and CSRF remain independent application concerns if later introduced without sessions.

`HTTP_CACHE_POLICY(NO_STORE)`: every response path currently shipped by the starter emits `Cache-Control: no-store`: health success, route miss, method rejection, mapped invalid or oversized input, and unknown failure. No current response is intentionally storable, so validators and `Vary` are not applicable. Every response path added later still requires its own explicit policy and behavior test; the framework does not inject a cache default.

`NOT_APPLICABLE(CACHE)`: the health-only starter has no server-side cache, cache backend, typed cache service, cache key or payload schema, TTL, invalidation, stampede control, or cache operation metrics. No cache code or dependency is included.

`NOT_APPLICABLE(JOBS)`: the health-only starter has no durable deferred work, job backend, worker, lease, retry, dead letter, or supervisor. No job code or dependency is included.

`NOT_APPLICABLE(CLI)`: the health-only starter has no operational application console, finite command map, typed command argument, scheduled pass, application clock, overlap lock, cron policy, or CLI-specific output. `.ai/cli.md` owns any future adoption. Composer scripts and the installed `phpthis check` remain development gates only.

Accepted architectural decisions live in `docs/decisions/`. AI may draft and update a decision record, but acceptance requires explicit approval from an accountable human.
