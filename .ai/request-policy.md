# Request-policy contract

Authentication, tenant resolution, and authorization remain application-owned. Use ADR 020's one canonical shape for a protected route.

Rules:

- Implement one action-specific `RequestHandler` adapter with ordinary constructor injection.
- Inject narrowly named authenticator, tenant-resolver, and authorizer interfaces plus the protected handler or action.
- Execute exactly `authenticate -> resolve tenant -> authorize -> protected handler`; do not represent the sequence as an iterable registry.
- Return concrete immutable application principal and tenant types and pass them explicitly. Do not add them to `Request`, `PathParameters`, session state, globals, or a generic context bag.
- Re-evaluate authorization on every protected request. Stored or cached identity is never authorization.
- Keep any policy reads and protected data work on separately named connections with distinct budgets and traces.
- Keep protected SQL explicitly tenant- and resource-scoped after authorization; do not introduce an implicit or global scope.
- Map named denial failures exactly: generic Bearer `401` for absent or rejected credentials, and the same generic `403` for ordinary forbidden and cross-tenant decisions.
- Do not log known denials. Preserve class-only logging for an unexpected failure, and keep credentials and identifiers out of responses, logs, traces, and fixtures.
- Start authenticated and denied responses with `Cache-Control: private, no-store` unless a later accepted application decision proves another policy.

Tests record the exact call order, stop the sequence at every failing stage, assert zero protected queries and writes on denial, inspect redacted output and traces, prove explicit principal and tenant delivery on success, and replace every policy implementation through manual composition.

The checked-in composition is deny-all and the consumer proof uses I/O-free synthetic policies. PHPThis provides no credential parser or verifier. A concrete authenticator owns and tests its missing, malformed, expired, revoked, and rejected inputs; a policy that performs I/O owns a separate named connection, budget, trace, and failure proof.

Do not add middleware, a policy registry, service location, discovery, a request-context bag, model binding, a generic permission API, or hidden tenant resolution. This pattern changes no core source, Consumer Contract version, or Strict Profile version.
