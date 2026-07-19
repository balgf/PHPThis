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

## Named boundaries

| Boundary | Path | Responsibility |
| --- | --- | --- |
| HTTP runtime | `{{HTTP_BOUNDARY_PATH}}` | {{HTTP_BOUNDARY_RESPONSIBILITY}} |
| Typed session services | `{{SESSION_SERVICE_PATHS_OR_NOT_APPLICABLE}}` | {{SESSION_SERVICE_KEY_OWNERSHIP_OR_NOT_APPLICABLE}} |
| Typed cache services | `{{CACHE_SERVICE_PATHS_OR_NOT_APPLICABLE}}` | {{CACHE_SERVICE_PROJECTION_OWNERSHIP_OR_NOT_APPLICABLE}} |
| Database | `{{DATABASE_BOUNDARY_PATH_OR_NOT_APPLICABLE}}` | {{DATABASE_BOUNDARY_RESPONSIBILITY}} |
| External services | `{{INTEGRATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}` | {{INTEGRATION_BOUNDARY_RESPONSIBILITY}} |

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

## Cache policies

### HTTP response cache

- Required policy decision: {{HTTP_CACHE_POLICY_DECISION}}
- Per-response owner and `no-store`, `private`, or `public` policy: {{HTTP_CACHE_RESPONSE_POLICY}}
- Storable-response freshness lifetime, revalidation, and stale-response policy: {{CACHEABLE_RESPONSE_FRESHNESS_AND_REVALIDATION_POLICY}}
- Validator generation and conditional-request behavior: {{HTTP_CACHE_VALIDATOR_POLICY_OR_NOT_APPLICABLE}}
- Complete `Vary` dimensions and normalization source: {{HTTP_CACHE_VARY_POLICY_OR_NOT_APPLICABLE}}
- Personalized, authorization-sensitive, and cookie-bearing response policy: {{HTTP_CACHE_PERSONALIZED_RESPONSE_POLICY_OR_NOT_APPLICABLE}}

HTTP response caching is separate from server-side data caching. Each response-producing path owns an explicit policy; a server-side cache hit does not make an HTTP response public, and `Set-Cookie` alone is not a cache prohibition.

Use an explicit `Cache-Control: no-store` policy as the safe starting point for every new or not-yet-reviewed success, redirect, client-error, server-error, and cookie-emitting response. Replace it with `private` or `public` behavior only after the application records and tests the complete policy above. This is an application decision at each response-producing path, not a framework default or middleware behavior.

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
- Commands and projections are placed at `{{BOUNDARY_VALUE_PATH_RULE}}`.
- Cross-cutting application behavior requires an accepted decision record; do not invent providers, middleware, discovery, helper layers, a generic session repository, or a generic cache service.
