# Application request policy

Complete this guide before protecting a route. Read installed `vendor/phpthis/framework/docs/request-policy.md` and `vendor/phpthis/framework/docs/stateless-authentication.md` first. Record application facts and accepted decisions here; do not copy credentials, signing material, raw-token verifiers, provider secrets, or sensitive identifiers.

## Protected path

- Route and named action: {{PROTECTED_ROUTE_AND_ACTION}}
- Action-specific `RequestHandler` adapter: `{{REQUEST_POLICY_ADAPTER_PATH}}`
- Protected operation: `{{PROTECTED_OPERATION_PATH}}`
- Fixed order: `authenticate -> resolve tenant -> authorize -> protected operation`

## Concrete authority values

- Principal type and identity source: {{PRINCIPAL_TYPE_AND_SOURCE}}
- Tenant-context type and resolution source: {{TENANT_CONTEXT_TYPE_AND_SOURCE}}
- Authenticator interface and implementation: {{AUTHENTICATOR_INTERFACE_AND_IMPLEMENTATION}}
- Tenant-resolver interface and implementation: {{TENANT_RESOLVER_INTERFACE_AND_IMPLEMENTATION}}
- Authorizer interface and implementation: {{AUTHORIZER_INTERFACE_AND_IMPLEMENTATION}}

## Failure and runtime policy

- Sole credential source, TLS boundary, raw duplicate handling, proxy/SAPI forwarding, strict Bearer grammar, and byte bound: {{AUTHORIZATION_HEADER_BOUNDARY}}
- Selected JWT, opaque/PAT/API-token, or external-verification profile and explicitly unsupported kinds: {{CREDENTIAL_PROFILE}}
- Pinned maintained dependency, verifier or timing-safe comparison, exact non-secret configuration, and key or verifier ownership: {{CREDENTIAL_VERIFIER_AND_CONFIGURATION}}
- Issuance or one-time disclosure, refresh or replacement, authoritative clock, expiry, rotation, revocation, owner, tenant, scope, rate-limit, audit, and redaction: {{CREDENTIAL_LIFECYCLE}}
- Unauthenticated response and challenge: {{UNAUTHENTICATED_RESPONSE}}
- Bare reference challenge and explicit non-RFC-6750 compatibility, or fixed non-sensitive auth-param grammar and exact absent/`invalid_request`/`invalid_token`/`insufficient_scope` status and error profile: {{RFC_6750_COMPATIBILITY_POLICY}}
- Verifier-uncertainty generic `5xx`, no-invalid-token/no-refresh-signal behavior: {{UNVERIFIABLE_CREDENTIAL_RESPONSE}}
- Ordinary forbidden and cross-tenant response: {{FORBIDDEN_RESPONSE}}
- Terminal summary: use `.ai/observability.md`; this policy adds no denial-specific field or event.
- Authenticated and denial cache policy: {{POLICY_RESPONSE_CACHE_CONTROL}}
- Verifier database, trusted-key source, or external-provider I/O, timeout, response bound, budget, trace, cache staleness, and fail-closed outage behavior: {{POLICY_DEPENDENCY_FAILURE}}
- Frontend storage and XSS boundary, or cookie/session and CSRF policy; CORS and preflight when cross-origin: {{FRONTEND_CREDENTIAL_BOUNDARY}}

## Data and concurrency

- I/O-free policies or separately named policy connections, budgets, and traces: {{POLICY_IO_AND_BOUNDS}}
- Protected connection, budget, and trace: {{PROTECTED_IO_AND_BOUNDS}}
- Explicit tenant and resource SQL predicates: {{PROTECTED_SQL_SCOPE}}
- Authorization-to-write race or transaction rule: {{AUTHORIZATION_RACE_POLICY}}

## Evidence

- Focused command: {{REQUEST_POLICY_TEST_COMMAND}}
- Denial, order, zero-protected-work, redaction, and replacement evidence: {{REQUEST_POLICY_EVIDENCE}}
- Raw-boundary, credential-profile, lifecycle, outage, and frontend or non-browser evidence or explicit proof limit: {{CREDENTIAL_EVIDENCE_OR_LIMIT}}

Do not replace or obscure the action-specific adapter with an application-owned request-handler decorator, generic or framework middleware, a policy registry, a request-context bag, service location, discovery, hidden tenant resolution, an implicit or global authorization scope, or stored authorization decisions.
