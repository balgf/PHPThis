# Application architecture

## Entry and composition

- Front controller: `public/index.php`
- Composition root: `bootstrap.php`
- Root route manifest: `src/Routes.php`
- Application source root: `src/`

## Dependency direction

```text
public/index.php -> bootstrap.php -> Routes -> HealthRoutes -> HealthHandler -> PHPThis
```

Dependencies may point only in the direction shown above. Record a deliberate exception in `docs/decisions/` before implementation.

## Named boundaries

| Boundary | Path | Responsibility |
| --- | --- | --- |
| HTTP runtime | `public/index.php` | Read PHP runtime globals, invoke the request boundary, map unknown failures, and emit one response. |
| Typed session services | `NOT_APPLICABLE` | The starter does not configure session state. |
| Typed cache services | `NOT_APPLICABLE(CACHE)` | The starter does not cache server-side data. |
| Database | `NOT_APPLICABLE` | The starter application has no database. |
| External services | `NOT_APPLICABLE` | The starter application has no external integrations. |

## Identity and authorization

- Identity, authentication, authorization, and tenant boundaries: `NOT_APPLICABLE(public liveness route only)`.
- Session state, authentication regeneration, idle or absolute expiry, logout, revocation, and CSRF: `NOT_APPLICABLE(public liveness route only)`.
- Deny-by-default rule: only explicit routes are accepted; other paths return `404`, and unsupported methods on a known path return `405`.

## Cache policies

### HTTP response cache

`UNRESOLVED(HTTP_CACHE_POLICY)`: the starter emits no `Cache-Control`, validator, or `Vary` policy for `GET /health`; that absence does not make HTTP caching inapplicable. Before deployment, choose explicit `no-store`, `private`, or `public` behavior for each response-producing path, record finite freshness or revalidation plus validators and complete `Vary` dimensions where applicable, and test personalized, authorization-sensitive, and cookie-bearing responses independently from server-side cached data.

### Optional server-side data cache

`NOT_APPLICABLE(CACHE)`: the starter has no cache-aside call path, typed cache projection, authoritative rebuild source, cacheable data classification, backend, key schema, or cache decision record. Before adoption, record narrowly named typed service ownership and keep hit, miss, authoritative read, write, and invalidation visible. Cached values must remain derived reproducible data and must not contain sessions, authentication state, authorization decisions, permissions, credentials, or secrets.

## Optional CRUD reference profile

`NOT_APPLICABLE`: this starter has only a public liveness operation. It has no resource identifier, CRUD routes, create/list/update/delete operations, resource authorization, audit events, or CRUD directory convention. Before adding resource behavior, record adoption of or one coherent alternative to `vendor/phpthis/framework/docs/crud.md`, plus identifier, explicit route, authorization, and audit policy. An alternate layout cannot weaken the installed consumer contract or Strict Profile.

## Placement rules

- Group routes in narrowly named `src/*Routes.php` route-area classes.
- Place handlers at `src/*Handler.php`.
- Add commands and projections only at explicit external-data boundaries.
- Do not invent providers, middleware, discovery, helper layers, or a generic cache service.
