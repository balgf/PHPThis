# Application request policy

`NOT_APPLICABLE(REQUEST_POLICY)`: the health-only starter has no credential, principal, tenant, protected action, policy query, protected data query, or authorization decision.

Before protecting a route, read installed `vendor/phpthis/framework/docs/request-policy.md` and `vendor/phpthis/framework/docs/stateless-authentication.md`, then replace this file with verified application facts:

- route, named action, action-specific adapter, and protected operation;
- concrete immutable principal and tenant-context types;
- manually wired, independently replaceable authenticator, tenant resolver, and authorizer;
- fixed `authenticate -> resolve tenant -> authorize -> protected operation` order;
- one strict TLS-protected `Authorization: Bearer` source with no alternate or fallback source, plus the raw-boundary duplicate, proxy/SAPI forwarding, parser grammar, and credential byte-bound policy;
- selected JWT, opaque/PAT/API-token, or external-verification profile; pinned maintained dependency; verifier and timing-safe comparison where applicable; exact configuration; and explicit unsupported credential kinds;
- issuance or one-time disclosure, refresh or replacement, authoritative clock, expiry, rotation, revocation, owner, tenant, scope, rate-limit, audit, and redaction policy;
- separately named verifier database, trusted-key source, or external-provider I/O with finite timeout, response, budget, trace, cache-staleness, and fail-closed outage policy;
- frontend credential storage and XSS boundary, or cookie/session and CSRF policy; exact CORS and preflight behavior when cross-origin;
- generic definitive-invalid `401`, verifier-uncertainty `5xx` with no refresh signal, ordinary forbidden, and cross-tenant disclosure policy; preserve the bare non-RFC-6750-compatible reference challenge or record the fixed non-sensitive auth-param and exact status/error evidence for an application RFC 6750 compatibility claim;
- status-only known-denial summary, class-only unexpected-failure, redaction, and response-cache policy;
- I/O-free policies or separately named policy connections, budgets, and traces;
- protected connection, budget, trace, tenant/resource SQL scope, and authorization race policy;
- denial, order, zero-protected-work, redaction, replacement, parser, lifecycle, dependency-outage, and frontend or non-browser credential tests.

Do not replace or obscure the action-specific adapter with an application-owned request-handler decorator, generic or framework middleware, a policy registry, a request-context bag, service location, discovery, hidden tenant resolution, an implicit or global authorization scope, or stored authorization decisions.
