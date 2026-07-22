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
| WebSocket runtime | `NOT_APPLICABLE(WEBSOCKETS)` | The health-only starter has no WebSocket process, listener, protocol, or connection state. |
| Application-owned request-handler decorators | `NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)` | `HealthHandler` is constructed directly for the sole route. |
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

## Optional application-owned WebSockets

`NOT_APPLICABLE(WEBSOCKETS)`: the health-only starter has no WebSocket dependency, listener, process, event loop, handshake, connection state, message command, or delivery policy. The existing `GET /health` execution path is HTTP only.

Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and replace `.ai/websockets.md` with one approved application-specific contract. Record one explicitly selected mature third-party runtime, separate process entrypoint and composition root, exact handshake and current authorization path, complete bounded message-to-final-readonly-command boundary, one narrowly named typed operation, finite connection and sequential send path, backpressure, lifecycle, redaction, deployment, supervisor, and scaling decisions. Keep frames outside PHPThis `Request`, `Response`, `Router`, `RequestBoundary`, `ResponseEmitter`, and terminal request-summary types. Do not add a generic WebSocket abstraction or adapt the runtime into the HTTP execution model.

## Optional application-owned request-handler decorators

`NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`: `src/Routes.php` constructs `HealthHandler` directly. The starter has no route-local wrapping concern, decorator side effect, early response, response replacement, or nesting order.

Before adoption, record every final application-owned request-handler decorator, its one downstream `RequestHandler`, affected routes, complete unrolled outer-to-inner construction, zero-or-one delegation with the exact same immutable `Request` instance, unchanged exception propagation, explicit immutable `Response` replacement with complete field preservation, and named bounded side effects and tests. Do not add a generic or framework middleware interface, pipeline, iterable registry, priorities, discovery, `$next` abstraction, context bag, hidden binding, or hidden I/O. Never wrap `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter`, and do not move session, cache, request-policy, or terminal-observability ownership into a decorator.

## Identity and authorization

- Request-policy composition: `NOT_APPLICABLE(REQUEST_POLICY)` because the starter has only one public liveness route.
- Identity, authentication, authorization, and tenant boundaries: `NOT_APPLICABLE(public liveness route only)`.
- Session state, authentication regeneration, idle or absolute expiry, logout, revocation, and CSRF: `NOT_APPLICABLE(public liveness route only)`.
- Deny-by-default rule: only explicit routes are accepted; other paths return `404`, and unsupported methods on a known path return `405`.

Before protecting a route, record its named action, concrete principal and tenant types, credential source and lifecycle, action-specific adapter, replaceable policies, fixed `authenticate -> resolve tenant -> authorize -> handler` order, generic denial responses, current authorization source, separate policy and protected query budgets and traces, explicitly tenant-scoped protected SQL, authorization-to-write race policy, redaction, and tests. Do not replace or obscure that adapter with an application-owned request-handler decorator, generic or framework middleware, a request-context bag, hidden tenant resolution, or an implicit scope.

## Terminal request summary

`.ai/observability.md` is the single project authority for starter correlation, source registration, sink, scope, and evidence facts. The architecture constraint is `public/index.php -> bootstrap.php -> TerminalRequestCoordinator -> RequestBoundary -> selected Response -> RequestSummarySink -> ResponseEmitter`; the types remain application-owned under `src/Observability/`.

## Cache policies

### HTTP response cache

`HTTP_CACHE_POLICY(NO_STORE)`: `GET /health`, route misses, method rejections, and mapped invalid or oversized input emit `Cache-Control: no-store` at their explicit response-producing path; the generic unknown-failure response emits `Cache-Control: private, no-store`. No current response is intentionally storable, so freshness, validators, conditional requests, and `Vary` are not applicable. Every response path added later starts with explicit `no-store` and requires its own recorded and tested decision before it may use `private` or `public`; no helper, application-owned request-handler decorator, generic or framework middleware default, or response post-processor supplies that policy.

### Optional server-side data cache

`NOT_APPLICABLE(CACHE)`: the starter has no cache-aside call path, typed cache projection, authoritative rebuild source, cacheable data classification, backend, key schema, or cache decision record. Before adoption, record narrowly named typed service ownership and keep hit, miss, authoritative read, write, and invalidation visible. Cached values must remain derived reproducible data and must not contain sessions, authentication state, authorization decisions, permissions, credentials, or secrets.

## Optional CRUD reference profile

`NOT_APPLICABLE(RESOURCE_ROUTE_IDENTIFIERS)`: this starter has only the exact public `GET /health` liveness operation. It has no path parameter, resource identifier, CRUD route, create/list/update/delete operation, resource lookup, resource authorization, audit event, or CRUD directory convention.

Before adding resource behavior, record adoption of or one coherent alternative to `vendor/phpthis/framework/docs/crud.md`, plus the identifier, explicit route, authorization, and audit policy. Every resource path identifier uses the narrowest fixed declaration: `positive-int`, `uuid`, or `ulid` for that canonical representation, and `token` only for a genuinely opaque identifier. Record the matching `PathParameters::positiveInteger()`, `uuid()`, `ulid()`, or `token()` accessor, the application-owned route-specific identifier that immediately wraps the unchanged value, and any narrower domain rule enforced before database work. Routing performs no normalization, domain binding, record lookup, identifier generation, persistence choice, or type fallback. An alternate layout cannot weaken the installed consumer contract or Strict Profile.

## Placement rules

- Group routes in narrowly named `src/*Routes.php` route-area classes.
- Place handlers at `src/*Handler.php`.
- Add commands and projections only at explicit external-data boundaries.
- Keep terminal coordinator, summary, source, correlation, and sink types under `src/Observability/` and wire them manually in `bootstrap.php`.
- Keep `NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)` until one route-local concern and its complete bounded contract are recorded. Do not invent providers, generic or framework middleware infrastructure, policy registries, request-context bags, discovery, helper layers, or a generic cache service.
