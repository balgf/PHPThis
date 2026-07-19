# Request policy

PHPThis keeps authentication, tenant resolution, and authorization application-owned. ADR 020 accepts one explicit composition shape without adding a framework policy engine.

## Execution contract

A protected route points to one application adapter that implements `RequestHandler`. The adapter receives narrowly named interfaces through its ordinary constructor and executes this fixed order:

```text
Request with typed path parameters
  -> authenticate -> immutable principal
  -> resolve tenant -> immutable tenant context
  -> authorize principal + tenant + named action
  -> protected handler with explicit principal, tenant, and route identifiers
  -> Response
```

The composition root selects each implementation. Do not discover policies, register a middleware list, place identity on `Request`, add a generic context bag, or load a domain object while routing. The protected handler receives the concrete values its operation requires.

The reference proof is stateless. `RequestReader` supplies one bounded `authorization` header to the application authenticator, but PHPThis provides no credential parser or verifier. The checked-in composition is deny-all and the consumer test replaces it with an I/O-free synthetic authenticator. A concrete application treats missing, malformed, or rejected Bearer credentials identically and tests its parser and verifier. The proof establishes composition and failure boundaries, not credential issuance or a production authentication algorithm. A session-backed application still follows [Session state](sessions.md) and treats stored identity only as input to a fresh request authorization decision.

## Failure and disclosure contract

- Missing, malformed, and rejected credentials map to one generic `401` with `WWW-Authenticate: Bearer`.
- Ordinary forbidden and cross-tenant decisions map to the same generic `403`.
- Known denials are not logged.
- Public error bodies and headers contain no credential, principal, tenant, or resource identifier.
- Unexpected failures retain the existing class-only unknown-failure log and generic `500` response.
- Authenticated and denied responses start with `Cache-Control: private, no-store`.

The application uses named failure classes and exact `ErrorResponseRegistry` entries. It does not expose policy exception messages or register a broad built-in exception type.

## Database and side-effect contract

Authentication, tenant resolution, and authorization may perform only their recorded reads. When a policy reads storage, give it a separately named connection, budget, and trace from protected handler work. The reference policies are I/O-free. A denied request may consume only its declared policy-read budget, but it executes no protected query, handler write, session mutation, cache mutation, or external business side effect.

Authorization is evaluated for every protected request. A successful decision is not a global database scope: protected SQL still binds both tenant and resource identifiers explicitly. Record the transaction or concurrency policy when authorization could change between the decision and a write.

## Required evidence

Tests cover unauthenticated, ordinary forbidden, cross-tenant, permitted, and unexpected policy-failure paths. They assert the exact call sequence, zero later calls after failure, zero protected query and write work on every denial, exact generic responses, redaction from bodies, headers, logs, and query-trace snapshots, and explicit principal and tenant delivery on success. A concrete credential parser additionally covers absent, malformed, wrong-scheme, oversized, expired, revoked, and rejected credentials according to its recorded policy.

The consumer proof replaces each authenticator, tenant resolver, and authorizer independently through the composition root. A test double or alternate implementation must require no framework edit, discovery metadata, or service-container configuration.

The reference protected operation is a read. Its denial evidence is zero protected-operation entry and zero protected statements. A protected mutation additionally proves that persistent state is unchanged on every denial.

See [ADR 020](decisions/020-application-owned-request-policy.md), [Security baseline](security.md), [Error responses](errors.md), and [Request handling](request-handling.md).
