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
| Introduce cookie-backed session state | installed `vendor/phpthis/framework/docs/sessions.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | `bootstrap.php`, typed key ownership, isolated save path, mandatory transport evidence, and each applicable security-policy test |
| Resolve or change HTTP response cache policy | installed `vendor/phpthis/framework/docs/caching.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | response-producing path, explicit `no-store`, `private`, or `public` policy, freshness or revalidation, validators, `Vary`, intermediary topology, and behavior tests |
| Introduce server-side cached data | installed `vendor/phpthis/framework/docs/caching.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, `.ai/testing.md` | `bootstrap.php`, narrowly named typed service, authoritative data path, backend boundary, key and tenant ownership, bounds, invalidation, observability, and cold, warm, failure, and concurrency tests |
| Introduce CRUD-shaped resource operations | installed `vendor/phpthis/framework/docs/crud.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/testing.md` | explicit resource routes, operation area, data path, and behavior tests |
| Add data access or a structural SQL selector | `.ai/data.md`, `.ai/testing.md` | schema authority, direct `Connection` call, finite code-owned SQL mapping, runtime authority, and adversarial and scale tests |
| Add an external side effect | `.ai/integrations.md` | the named client boundary and failure tests |
| Change runtime or logging | `.ai/operations.md` | `public/index.php`, `bootstrap.php`, and operational tests |
| Add or change tests | `.ai/testing.md` | `tests/run.php` and `composer check` |

`NOT_APPLICABLE(CRUD_PROFILE)`: the health-only starter has no CRUD-shaped resource behavior or CRUD directory convention. Before adding one, record adoption of the installed optional profile or one coherent alternate organization. Consumer Contract v3 and Strict Profile v2 remain mandatory.

`NOT_APPLICABLE(SESSION)`: the health-only starter does not configure `SessionLifecycle` or issue cookies. Authentication, authorization, credential expiry, revocation, and CSRF remain independent application concerns if later introduced without sessions.

`HTTP_CACHE_POLICY(NO_STORE)`: every response path currently shipped by the starter emits `Cache-Control: no-store`: health success, route miss, method rejection, mapped invalid or oversized input, and unknown failure. No current response is intentionally storable, so validators and `Vary` are not applicable. Every response path added later still requires its own explicit policy and behavior test; the framework does not inject a cache default.

`NOT_APPLICABLE(CACHE)`: the health-only starter has no server-side cache, cache backend, typed cache service, cache key or payload schema, TTL, invalidation, stampede control, or cache operation metrics. No cache code or dependency is included.

Accepted architectural decisions live in `docs/decisions/`. AI may draft and update a decision record, but acceptance requires explicit approval from an accountable human.
