# Application request policy

Complete this guide before protecting a route. Read installed `vendor/phpthis/framework/docs/request-policy.md` first. Record application facts and accepted decisions here; do not copy credentials or sensitive identifiers.

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

- Credential scheme, parser, verifier, expiry, and revocation: {{CREDENTIAL_POLICY}}
- Unauthenticated response and challenge: {{UNAUTHENTICATED_RESPONSE}}
- Ordinary forbidden and cross-tenant response: {{FORBIDDEN_RESPONSE}}
- Terminal summary: use `.ai/observability.md`; this policy adds no denial-specific field or event.
- Authenticated and denial cache policy: {{POLICY_RESPONSE_CACHE_CONTROL}}
- Policy dependency outage behavior: {{POLICY_DEPENDENCY_FAILURE}}

## Data and concurrency

- I/O-free policies or separately named policy connections, budgets, and traces: {{POLICY_IO_AND_BOUNDS}}
- Protected connection, budget, and trace: {{PROTECTED_IO_AND_BOUNDS}}
- Explicit tenant and resource SQL predicates: {{PROTECTED_SQL_SCOPE}}
- Authorization-to-write race or transaction rule: {{AUTHORIZATION_RACE_POLICY}}

## Evidence

- Focused command: {{REQUEST_POLICY_TEST_COMMAND}}
- Denial, order, zero-protected-work, redaction, and replacement evidence: {{REQUEST_POLICY_EVIDENCE}}
- Credential-parser evidence or explicit proof limit: {{CREDENTIAL_PARSER_EVIDENCE_OR_LIMIT}}

Do not replace or obscure the action-specific adapter with an application-owned request-handler decorator, generic or framework middleware, a policy registry, a request-context bag, service location, discovery, hidden tenant resolution, an implicit or global authorization scope, or stored authorization decisions.
