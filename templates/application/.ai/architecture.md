# Application architecture

## Entry and composition

- Front controller: `{{FRONT_CONTROLLER_PATH}}`
- Composition root: `{{COMPOSITION_ROOT_PATH}}`
- Root route manifest: `{{ROUTE_MANIFEST_PATH}}`
- Application source root: `{{APPLICATION_SOURCE_PATH}}`

## Dependency direction

```text
{{DEPENDENCY_DIRECTION}}
```

Dependencies may point only in the direction shown above. Document every deliberate exception in `docs/decisions/` before implementation.

## Resource route identifiers

Every resource path identifier recorded here uses the narrowest fixed declaration: `positive-int`, `uuid`, or `ulid` for that canonical representation, and `token` only for a genuinely opaque identifier. Record the matching `PathParameters::positiveInteger()`, `uuid()`, `ulid()`, or `token()` accessor, the application-owned route-specific identifier that immediately wraps the unchanged value, and any narrower domain rule enforced before database work. Routing performs no normalization, domain binding, record lookup, identifier generation, persistence choice, or type fallback.

## Named boundaries

| Boundary | Path | Responsibility |
| --- | --- | --- |
| HTTP runtime | `{{HTTP_BOUNDARY_PATH}}` | {{HTTP_BOUNDARY_RESPONSIBILITY}} |
| Application-owned request-handler decorators | `{{REQUEST_HANDLER_DECORATOR_PATHS_OR_NOT_APPLICABLE}}` | {{REQUEST_HANDLER_DECORATOR_RESPONSIBILITIES_OR_NOT_APPLICABLE}} |
| Inbound operation data | `{{INPUT_BOUNDARY_PATHS_OR_NOT_APPLICABLE}}` | {{INPUT_BOUNDARY_RESPONSIBILITIES_OR_NOT_APPLICABLE}} |
| Typed session services | `{{SESSION_SERVICE_PATHS_OR_NOT_APPLICABLE}}` | {{SESSION_SERVICE_KEY_OWNERSHIP_OR_NOT_APPLICABLE}} |
| Typed cache services | `{{CACHE_SERVICE_PATHS_OR_NOT_APPLICABLE}}` | {{CACHE_SERVICE_PROJECTION_OWNERSHIP_OR_NOT_APPLICABLE}} |
| Durable jobs | `{{JOB_BOUNDARY_PATHS_OR_NOT_APPLICABLE}}` | {{JOB_PUBLICATION_ENVELOPE_WORKER_RESPONSIBILITY_OR_NOT_APPLICABLE}} |
| Database | `{{DATABASE_BOUNDARY_PATH_OR_NOT_APPLICABLE}}` | {{DATABASE_BOUNDARY_RESPONSIBILITY}} |
| External services | `{{INTEGRATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}` | {{INTEGRATION_BOUNDARY_RESPONSIBILITY}} |

## Inbound data boundaries

- Adoption or `NOT_APPLICABLE(INPUT)`: {{INPUT_BOUNDARY_ADOPTION_OR_NOT_APPLICABLE}}
- Canonical path: `bounded raw representation -> operation-specific named parser factory -> final readonly command or request -> downstream typed behavior or one justified typed operation seam`

| Operation | Raw source and complete bounds | Parser factory and typed result | Field shape and canonical representations | Normalization policy | Failure, disclosure, and side-effect barrier |
| --- | --- | --- | --- | --- | --- |
| `{{INPUT_OPERATION_1}}` | {{INPUT_OPERATION_1_SOURCE_AND_BOUNDS}} | `{{INPUT_OPERATION_1_FACTORY_AND_TYPE}}` | {{INPUT_OPERATION_1_FIELDS_AND_REPRESENTATIONS}} | {{INPUT_OPERATION_1_NORMALIZATION_OR_NONE}} | {{INPUT_OPERATION_1_FAILURE_AND_SIDE_EFFECT_POLICY}} |
| `{{INPUT_OPERATION_2_OR_NOT_APPLICABLE}}` | {{INPUT_OPERATION_2_SOURCE_AND_BOUNDS_OR_NOT_APPLICABLE}} | `{{INPUT_OPERATION_2_FACTORY_AND_TYPE_OR_NOT_APPLICABLE}}` | {{INPUT_OPERATION_2_FIELDS_AND_REPRESENTATIONS_OR_NOT_APPLICABLE}} | {{INPUT_OPERATION_2_NORMALIZATION_OR_NONE}} | {{INPUT_OPERATION_2_FAILURE_AND_SIDE_EFFECT_POLICY_OR_NOT_APPLICABLE}} |

For each operation, record complete byte, depth, field-count, list-count, item, and scalar limits as applicable; required and optional fields; absent-versus-explicit-null behavior; rejection of unknown fields; exact boolean, integer, string, enum, date, list, and object representations; deterministic validation order; and the native JSON duplicate-key limitation or a separately accepted parser. The factory checks runtime types before conversion and owns a private constructor. Downstream behavior uses only the completed value. Add a separate typed operation seam only when HTTP adaptation and an independently meaningful business or transaction responsibility need separate ownership, and record whether parsing occurs before or after authentication, tenant resolution, and authorization.

No normalization is implicit. A deliberate field transformation records its order, pre- and post-transform bounds, collision behavior, and retained canonical value. Validation decides whether input is accepted; sink-specific output encoding, named SQL bindings, and current authorization remain separate responsibilities. Rejection prevents operation-owned downstream I/O and mutation and makes zero calls to a typed seam when present. Earlier transport or request-policy work remains separately bounded under its recorded order. Keep the public failure finite, stable, generic, and free of submitted values or internal messages. Do not introduce a generic validator, result wrapper, rule-string language, reflection hydration, mass assignment, sanitization magic, or automatic request binding.

## Optional application-owned request-handler decorators

- Adoption or `NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`: {{REQUEST_HANDLER_DECORATOR_ADOPTION_OR_NOT_APPLICABLE}}
- Final decorator classes, one downstream handler each, and narrowly named concerns: {{REQUEST_HANDLER_DECORATOR_CLASSES_AND_CONCERNS_OR_NOT_APPLICABLE}}
- Affected routes and complete visible outer-to-inner order: {{REQUEST_HANDLER_DECORATOR_ROUTES_AND_ORDER_OR_NOT_APPLICABLE}}
- Early response, downstream response, and immutable replacement policy: {{REQUEST_HANDLER_DECORATOR_RESPONSE_POLICY_OR_NOT_APPLICABLE}}
- Named bounded I/O, side effects, and failure policy: {{REQUEST_HANDLER_DECORATOR_SIDE_EFFECT_AND_FAILURE_POLICY_OR_NOT_APPLICABLE}}

Every adopted application-owned request-handler decorator is a final application class implementing only `RequestHandler` and receives exactly one downstream `RequestHandler` through its ordinary constructor. Construct the complete nesting as an unrolled expression beside every affected route. For each call, delegate zero or one time with the exact same immutable `Request` instance, propagate exceptions unchanged, and return the downstream `Response` unchanged or construct one explicit immutable replacement preserving every unchanged status, header, body, `ResponseCookie`, and `LocalFileBody` field. Make every owned side effect apparent in the class name, constructor dependencies, call site, bounds, and tests. Do not add a generic or framework middleware interface, pipeline, iterable registry, priorities, discovery, `$next` abstraction, context bag, hidden binding, or hidden I/O. Never wrap `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter`, and do not move session, cache, request-policy, or terminal-observability ownership into a decorator.

## Identity and authorization

- Identity source and representation: {{IDENTITY_SOURCE_AND_REPRESENTATION}}
- Authentication boundary: `{{AUTHENTICATION_BOUNDARY_PATH}}`
- Session adoption and allowed key schema: {{SESSION_ADOPTION_AND_KEY_SCHEMA_OR_NOT_APPLICABLE}}
- Authentication and privilege-regeneration points: {{SESSION_REGENERATION_POINTS_OR_NOT_APPLICABLE}}
- Idle and absolute expiry: {{SESSION_EXPIRY_POLICY_OR_NOT_APPLICABLE}}
- Logout and account-wide revocation: {{SESSION_LOGOUT_AND_REVOCATION_POLICY_OR_NOT_APPLICABLE}}
- CSRF owner, token lifecycle, and protected methods: {{CSRF_POLICY_OR_NOT_APPLICABLE}}
- Authorization owner and check location: `{{AUTHORIZATION_OWNER_AND_PATH}}`
- Tenant boundary: {{TENANT_BOUNDARY_OR_NOT_APPLICABLE}}
- Deny-by-default rule: {{DENY_BY_DEFAULT_RULE}}
- Protected routes, named actions, and action-specific adapter paths: {{PROTECTED_ROUTE_POLICY_ADAPTERS_OR_NOT_APPLICABLE}}
- Authenticator, tenant-resolver, and authorizer interfaces and implementations: {{REQUEST_POLICY_IMPLEMENTATIONS_OR_NOT_APPLICABLE}}
- Fixed policy order and explicit principal/tenant delivery: {{REQUEST_POLICY_ORDER_AND_DELIVERY_OR_NOT_APPLICABLE}}
- Missing or rejected credential response and challenge: {{UNAUTHENTICATED_RESPONSE_POLICY_OR_NOT_APPLICABLE}}
- Ordinary forbidden and cross-tenant disclosure policy: {{FORBIDDEN_AND_CROSS_TENANT_RESPONSE_POLICY_OR_NOT_APPLICABLE}}
- Current per-request authorization, expiry, and revocation source: {{CURRENT_AUTHORIZATION_SOURCE_OR_NOT_APPLICABLE}}
- Separately named policy and protected query connections, budgets, and traces: {{REQUEST_POLICY_QUERY_BOUNDS_OR_NOT_APPLICABLE}}
- Tenant- and resource-scoped protected SQL and authorization-to-write race policy: {{TENANT_SQL_AND_AUTHORIZATION_RACE_POLICY_OR_NOT_APPLICABLE}}
- Terminal-summary authority for every protected outcome: `.ai/observability.md`; no route-specific denial field or event.

For each protected route, use one action-specific adapter with visible `authenticate -> resolve tenant -> authorize -> protected handler` order. Pass concrete immutable principal and tenant values explicitly; do not add them to `Request`, session state, a generic context bag, or a global. Every denial stops before protected queries, writes, session mutation, cache mutation, and external business side effects.

## Terminal request summary

Project-owned correlation, coordinator, sink, database-source, destination, and attempt facts live only in `.ai/observability.md`. The dependency position is `front controller -> application terminal coordinator -> RequestBoundary -> selected Response -> one sink attempt -> ResponseEmitter`. Keep this application-owned and explicit; do not move it into an application-owned request-handler decorator, copy the installed schema here, or add a core logging type, facade, global helper, generic or framework logging middleware, event pipeline, automatic discovery, per-query I/O, or hidden `Connection` instrumentation.

## Cache policies

### HTTP response cache

- Required policy decision: {{HTTP_CACHE_POLICY_DECISION}}
- Per-response owner and `no-store`, `private`, or `public` policy: {{HTTP_CACHE_RESPONSE_POLICY}}
- Storable-response freshness lifetime, revalidation, and stale-response policy: {{CACHEABLE_RESPONSE_FRESHNESS_AND_REVALIDATION_POLICY}}
- Validator generation and conditional-request behavior: {{HTTP_CACHE_VALIDATOR_POLICY_OR_NOT_APPLICABLE}}
- Complete `Vary` dimensions and normalization source: {{HTTP_CACHE_VARY_POLICY_OR_NOT_APPLICABLE}}
- Personalized, authorization-sensitive, and cookie-bearing response policy: {{HTTP_CACHE_PERSONALIZED_RESPONSE_POLICY_OR_NOT_APPLICABLE}}

HTTP response caching is separate from server-side data caching. Each response-producing path owns an explicit policy; a server-side cache hit does not make an HTTP response public, and `Set-Cookie` alone is not a cache prohibition.

Use an explicit `Cache-Control: no-store` policy as the safe starting point for every new or not-yet-reviewed success, redirect, client-error, server-error, and cookie-emitting response. Replace it with `private` or `public` behavior only after the application records and tests the complete policy above. This is an application decision at each response-producing path, not behavior supplied by an application-owned request-handler decorator or a generic or framework middleware default.

### Optional server-side data cache

- Adoption or `NOT_APPLICABLE(CACHE)`: {{CACHE_ADOPTION_OR_NOT_APPLICABLE}}
- Narrowly named typed services, paths, and owned projections: {{CACHE_TYPED_SERVICE_OWNERSHIP_OR_NOT_APPLICABLE}}
- Authoritative source and explicit cache-aside call path: {{CACHE_SOURCE_OF_TRUTH_AND_CALL_PATH_OR_NOT_APPLICABLE}}
- Cacheable and prohibited data classes: {{CACHE_DATA_CLASSIFICATION_OR_NOT_APPLICABLE}}
- Stale-refill race policy and accepted staleness bound or mitigation: {{CACHE_STALE_REFILL_POLICY_OR_NOT_APPLICABLE}}
- Accepted decision record: `{{CACHE_DECISION_RECORD_OR_NOT_APPLICABLE}}`

Cached values are derived, reproducible data and remain outside sessions, authentication, authorization, permissions, credentials, secrets, and other recorded prohibited classes. A service must expose a domain-specific typed result rather than a generic key/value API, and the handler's hit, miss, authoritative read, and write path must remain visible.

## Optional CRUD reference profile

- Adoption or coherent alternate organization: {{CRUD_PROFILE_DECISION}}
- Feature and operation directory rule: `{{CRUD_DIRECTORY_RULE_OR_NOT_APPLICABLE}}`
- Identifier representation and route binding: {{CRUD_IDENTIFIER_AND_ROUTE_BINDING_POLICY_OR_NOT_APPLICABLE}}
- Explicit route-shape and HTTP-method policy: {{CRUD_ROUTE_AND_METHOD_POLICY_OR_NOT_APPLICABLE}}
- Authorization owner and check location by operation: {{CRUD_AUTHORIZATION_POLICY_OR_NOT_APPLICABLE}}
- Audit-event owner and emission boundary: {{CRUD_AUDIT_POLICY_OR_NOT_APPLICABLE}}
- Accepted deviation record: `{{CRUD_PROFILE_DECISION_RECORD_OR_NOT_APPLICABLE}}`

The installed `vendor/phpthis/framework/docs/crud.md` profile recommends structure only. An alternate directory and naming policy cannot weaken the installed consumer contract or Strict Profile, and layout never determines identifiers, routes, authorization, or audit behavior.

## Placement rules

- Routes are grouped by `{{ROUTE_AREA_RULE}}`.
- Handlers are placed at `{{HANDLER_PATH_RULE}}`.
- Operation requests, commands, and projections are placed at `{{BOUNDARY_VALUE_PATH_RULE}}`.
- Cross-cutting application behavior requires an accepted decision record. Use an application-owned request-handler decorator only within the bounded route-local shape above; do not invent providers, generic or framework middleware infrastructure, policy registries, request-context bags, discovery, helper layers, a generic session repository, or a generic cache service.
