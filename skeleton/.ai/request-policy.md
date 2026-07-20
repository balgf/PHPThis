# Application request policy

`NOT_APPLICABLE(REQUEST_POLICY)`: the health-only starter has no credential, principal, tenant, protected action, policy query, protected data query, or authorization decision.

Before protecting a route, read installed `vendor/phpthis/framework/docs/request-policy.md` and replace this file with verified application facts:

- route, named action, action-specific adapter, and protected operation;
- concrete immutable principal and tenant-context types;
- manually wired, independently replaceable authenticator, tenant resolver, and authorizer;
- fixed `authenticate -> resolve tenant -> authorize -> protected operation` order;
- credential parser, verifier, expiry, revocation, and dependency-failure policy;
- generic unauthenticated, ordinary forbidden, and cross-tenant disclosure policy;
- status-only known-denial summary, class-only unexpected-failure, redaction, and response-cache policy;
- I/O-free policies or separately named policy connections, budgets, and traces;
- protected connection, budget, trace, tenant/resource SQL scope, and authorization race policy;
- denial, order, zero-protected-work, redaction, replacement, and credential-parser tests.

Do not add middleware, a policy registry, a request-context bag, service location, discovery, hidden tenant resolution, an implicit or global authorization scope, or stored authorization decisions.
