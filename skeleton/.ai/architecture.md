# Application architecture

## Entry and composition

- Front controller: `public/index.php`
- Composition root: `bootstrap.php`
- Root route manifest: `src/Routes.php`
- Application source root: `src/`

## Dependency direction

```text
public/index.php -> bootstrap.php -> TerminalRequestCoordinator -> RequestBoundary -> Routes -> HealthRoutes -> HealthHandler -> Response -> RequestSummarySink -> ResponseEmitter
```

Dependencies may point only in the direction shown above. Record a deliberate exception in `docs/decisions/` before implementation.

## Named boundaries

| Boundary | Path | Responsibility |
| --- | --- | --- |
| HTTP runtime | `public/index.php` | Load request-scoped composition, read PHP runtime globals, invoke the terminal coordinator, and emit its unchanged response. |
| Terminal request summary | `src/Observability/` | Own correlation, finite database-source observation, the closed redacted event, one injected sink, and failure-isolated attempt semantics. |
| Inbound operation data | `NOT_APPLICABLE(INPUT)` | The public health operation accepts no application-owned fields and constructs no request or command. |
| Typed session services | `NOT_APPLICABLE` | The starter does not configure session state. |
| Typed cache services | `NOT_APPLICABLE(CACHE)` | The starter does not cache server-side data. |
| Durable jobs | `NOT_APPLICABLE(JOBS)` | The starter has no deferred work or worker runtime. |
| Database | `NOT_APPLICABLE` | The starter application has no database. |
| External services | `NOT_APPLICABLE` | The starter application has no external integrations. |

## Inbound data boundaries

`NOT_APPLICABLE(INPUT)`: `GET /health` accepts no application-owned body, query, form, or header fields. The outer `RequestBoundary` still bounds and validates PHP runtime transport data, but no operation-specific parser or typed command is needed for this health-only state.

Before an operation accepts external data, record one path from its bounded raw representation through an operation-specific named parser factory into a final readonly request or command with a private constructor, then into downstream typed behavior. Add a separate typed operation seam only when HTTP adaptation and an independently meaningful business or transaction responsibility need separate ownership. Record byte, depth, field, list, item, and scalar bounds; required, optional, absent, explicit-null, and unknown-field behavior; exact boolean, integer, string, enum, date, list, and object representations; deterministic validation order; field-specific normalization or explicit none; parser position relative to request policy; generic public failure and redaction; exclusion from operation-owned downstream work; and the native JSON duplicate-key limitation where applicable. Do not add a generic validator, result wrapper, rule-string language, reflection hydration, mass assignment, sanitization magic, or automatic request binding.

## Identity and authorization

- Request-policy composition: `NOT_APPLICABLE(REQUEST_POLICY)` because the starter has only one public liveness route.
- Identity, authentication, authorization, and tenant boundaries: `NOT_APPLICABLE(public liveness route only)`.
- Session state, authentication regeneration, idle or absolute expiry, logout, revocation, and CSRF: `NOT_APPLICABLE(public liveness route only)`.
- Deny-by-default rule: only explicit routes are accepted; other paths return `404`, and unsupported methods on a known path return `405`.

Before protecting a route, record its named action, concrete principal and tenant types, credential source and lifecycle, action-specific adapter, replaceable policies, fixed `authenticate -> resolve tenant -> authorize -> handler` order, generic denial responses, current authorization source, separate policy and protected query budgets and traces, explicitly tenant-scoped protected SQL, authorization-to-write race policy, redaction, and tests. Do not add middleware, a request-context bag, hidden tenant resolution, or an implicit scope.

## Terminal request summary

`.ai/observability.md` is the single project authority for starter correlation, source registration, sink, scope, and evidence facts. The architecture constraint is `public/index.php -> bootstrap.php -> TerminalRequestCoordinator -> RequestBoundary -> selected Response -> RequestSummarySink -> ResponseEmitter`; the types remain application-owned under `src/Observability/`.

## Cache policies

### HTTP response cache

`HTTP_CACHE_POLICY(NO_STORE)`: `GET /health`, route misses, method rejections, and mapped invalid or oversized input emit `Cache-Control: no-store` at their explicit response-producing path; the generic unknown-failure response emits `Cache-Control: private, no-store`. No current response is intentionally storable, so freshness, validators, conditional requests, and `Vary` are not applicable. Every response path added later starts with explicit `no-store` and requires its own recorded and tested decision before it may use `private` or `public`; no helper, middleware, or response post-processor supplies a default.

### Optional server-side data cache

`NOT_APPLICABLE(CACHE)`: the starter has no cache-aside call path, typed cache projection, authoritative rebuild source, cacheable data classification, backend, key schema, or cache decision record. Before adoption, record narrowly named typed service ownership and keep hit, miss, authoritative read, write, and invalidation visible. Cached values must remain derived reproducible data and must not contain sessions, authentication state, authorization decisions, permissions, credentials, or secrets.

## Optional CRUD reference profile

`NOT_APPLICABLE`: this starter has only a public liveness operation. It has no resource identifier, CRUD routes, create/list/update/delete operations, resource authorization, audit events, or CRUD directory convention. Before adding resource behavior, record adoption of or one coherent alternative to `vendor/phpthis/framework/docs/crud.md`, plus identifier, explicit route, authorization, and audit policy. An alternate layout cannot weaken the installed consumer contract or Strict Profile.

## Placement rules

- Group routes in narrowly named `src/*Routes.php` route-area classes.
- Place handlers at `src/*Handler.php`.
- Add commands and projections only at explicit external-data boundaries.
- Keep terminal coordinator, summary, source, correlation, and sink types under `src/Observability/` and wire them manually in `bootstrap.php`.
- Do not invent providers, middleware, policy registries, request-context bags, discovery, helper layers, or a generic cache service.
