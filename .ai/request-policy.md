# Request-policy contract

Authentication, tenant resolution, and authorization remain application-owned. Use ADR 020's one canonical shape for a protected route.

Rules:

- Implement one action-specific `RequestHandler` adapter with ordinary constructor injection.
- Inject narrowly named authenticator, tenant-resolver, and authorizer interfaces plus the protected handler or action.
- Execute exactly `authenticate -> resolve tenant -> authorize -> protected handler`; do not represent the sequence as an iterable registry.
- Return concrete immutable application principal and tenant types and pass them explicitly. Do not add them to `Request`, `PathParameters`, session state, globals, or a generic context bag.
- Treat a validated `positive-int`, `uuid`, `ulid`, or genuinely opaque `token` path value only as routing metadata. Read it through the matching `PathParameters` accessor, immediately wrap it in an application-owned route-specific identifier, and enforce any narrower domain rule before domain or database work; do not normalize, bind, look up, or fall back to another route type.
- Re-evaluate authorization on every protected request. Stored or cached identity is never authorization.
- Keep any policy reads and protected data work on separately named connections with distinct budgets and traces.
- Keep protected SQL explicitly tenant- and resource-scoped after authorization; do not introduce an implicit or global scope.
- Map named denial failures exactly: generic Bearer `401` for absent or rejected credentials, and the same generic `403` for ordinary forbidden and cross-tenant decisions.
- Give a known denial only the common terminal summary's generic known-failure outcome and selected status. Give an unexpected failure only its concrete class in that same event; keep credentials and identifiers out of responses, summaries, traces, and fixtures.
- Start authenticated and denied responses with `Cache-Control: private, no-store` unless a later accepted application decision proves another policy.

Tests record the exact call order, stop the sequence at every failing stage, assert zero protected queries and writes on denial, inspect redacted output and traces, prove explicit principal and tenant delivery on success, and replace every policy implementation through manual composition.

The document-list proof reuses the shared application principal, account, authentication, and tenant-resolution types but keeps `AuthorizeListDocuments` action-specific. Its complete SQLite statements bind requested account, resolved tenant account, and principal membership separately. Account-scoped Create keeps `AuthorizeCreateUser` distinct, stops all policy and input denials before its transaction, and leaves user, account-user, event, and job state unchanged. Its SQL independently rejects mismatched requested/resolved accounts and actors without current `account_memberships`; successful creation writes the separate `account_users` relation and never equates principal and user IDs. Those predicates are defense-in-depth evidence for those paths, not a global tenant scope or universal authorization proof.

The checked-in composition is deny-all and the consumer proof uses I/O-free synthetic policies. PHPThis provides no credential parser or verifier. A concrete authenticator owns and tests its missing, malformed, expired, revoked, and rejected inputs; a policy that performs I/O owns a separate named connection, budget, trace, and failure proof.

Do not replace or obscure the action-specific adapter with an application-owned request-handler decorator, generic or framework middleware, a policy registry, service location, discovery, a request-context bag, model binding, a generic permission API, or hidden tenant resolution. ADR 023 adds no policy logger or core source; Consumer Contract v9 carries Strict Profile v2 forward unchanged.
