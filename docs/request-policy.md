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

When ADR 033 request-handler decorators are adopted on a protected route, the route declaration keeps their complete order visible relative to this action-specific adapter. An outer decorator may perform only a concern that the application has explicitly approved before authentication; it cannot read protected data, install principal or tenant state on `Request`, or weaken denial non-entry. Protected queries, mutation, session changes, cache mutation, and external business effects remain behind successful current authorization. The decorator still has exactly one downstream `RequestHandler`, zero-or-one invocation, the same immutable request, unchanged exception propagation, and its own bounded named I/O evidence. It does not turn request policy into a generic middleware pipeline.

The reference proof is stateless. `RequestReader` exposes the bounded lowercase `authorization` header to the application authenticator, but PHPThis supplies no credential parser, verifier, issuer, revoker, identity provider, or authentication runtime/API. `WWW-Authenticate: Bearer` is response semantics, not token support. The checked-in composition is deny-all and the consumer test replaces it with an I/O-free synthetic authenticator; its test Bearers are never production credential evidence. A concrete application follows [Application-owned stateless authentication](stateless-authentication.md), accepts one strict Bearer header over TLS with no alternate credential source, treats missing, malformed, or rejected credentials identically, and tests its selected parser and verifier. The proof establishes composition and failure boundaries, not JWT, opaque/PAT/API-token, external-provider, credential-issuance, or production-authentication support. A session-backed application still follows [Session state](sessions.md) and treats stored identity only as input to a fresh request authorization decision.

## Failure and disclosure contract

- Missing, malformed, and rejected credentials map to one generic `401` with `WWW-Authenticate: Bearer`.
- Verifier, trusted-key, or introspection uncertainty maps to the application's one named generic `5xx`, with no invalid-token assertion or automatic-refresh signal; it never produces an authenticated principal.
- Ordinary forbidden and cross-tenant decisions map to the same generic `403`.
- Known denials produce only the common terminal summary's generic `known_failure` outcome and selected status; no denial-specific field or event is permitted.
- Public error bodies and headers contain no credential, principal, tenant, or resource identifier.
- Unexpected failures retain the generic `500` response and contribute only their concrete class to that same terminal summary.
- Authenticated, denied, and post-policy mapped or unknown responses start with `Cache-Control: private, no-store`.

Those bullets are the accepted disclosure-minimizing reference contract. Its bare `WWW-Authenticate: Bearer` challenge and same generic `401` for malformed and rejected credentials are explicitly not an RFC-6750-compatible challenge and error profile. A consuming application makes no RFC 6750 resource-server compatibility claim unless it records and proves the fixed non-sensitive authentication parameter and exact absent, `invalid_request`, `invalid_token`, and `insufficient_scope` status/error behavior described by [Application-owned stateless authentication](stateless-authentication.md). That application still keeps verifier uncertainty on a generic `5xx` without an invalid-token or refresh signal.

The application uses named failure classes and exact `ErrorResponseRegistry` entries. It does not expose policy exception messages or register a broad built-in exception type.

## Database and side-effect contract

Authentication, tenant resolution, and authorization may perform only their recorded reads. When a policy reads storage, a trusted-key endpoint, or an external verifier, give it a separately named dependency, budget, trace, timeout, response bound, cache-staleness rule, and outage proof distinct from protected handler work. The reference policies are I/O-free. A denied or unverifiable request may consume only its declared policy-read budget, but it executes no protected query, handler write, session mutation, cache mutation, or external business side effect.

Authorization is evaluated for every protected request. A successful decision is not a global database scope: protected SQL still binds both tenant and resource identifiers explicitly. Record the transaction or concurrency policy when authorization could change between the decision and a write.

## Required evidence

Tests cover unauthenticated, ordinary forbidden, cross-tenant, permitted, and unexpected policy-failure paths. They assert the exact call sequence, zero later calls after failure, zero protected query and write work on every denial, exact generic responses, status-only denial summaries, class-only unknown-failure summaries, redaction from bodies, headers, summaries, and query-trace snapshots, and explicit principal and tenant delivery on success. A concrete credential boundary additionally covers absent, duplicate at the first raw boundary, malformed, wrong-scheme, alternate-source, oversized, expired, revoked, and rejected credentials according to its recorded policy. JWT, opaque/PAT, and external-verification adopters add the distinct lifecycle, outage, cache-staleness, redaction, and frontend or non-browser evidence required by [Application-owned stateless authentication](stateless-authentication.md).

The consumer proof replaces each authenticator, tenant resolver, and authorizer independently through the composition root. A test double or alternate implementation must require no framework edit, discovery metadata, or service-container configuration.

The reference protected operations include document reads and account-scoped user Create. Shared `AccountId`, `AuthenticatedPrincipal`, authentication, tenant resolution, denial, and deny-all boundaries live under application-owned `Accounts`; `DocumentKey` remains document-specific, and every action keeps its separately named authorization interface and operation behavior. Document List SQL binds requested account, resolved tenant account, principal, and actor-membership account separately. Get and List denials prove zero protected data work. Create proves zero transaction statements and unchanged user, `account_users`, event, and job state for policy and input denials; direct operation tests also reject mismatched tenant values and missing actor `account_memberships`. Successful Create records user association in `account_users`, never by treating a user ID as a principal ID. Malformed post-policy input and unknown failures return generic `private, no-store` responses. These explicit predicates are path evidence, not a global scope or universal authorization proof.

See [Application-owned stateless authentication](stateless-authentication.md), [ADR 020](decisions/020-application-owned-request-policy.md), [ADR 022](decisions/022-application-owned-finite-data-paths.md), [Security baseline](security.md), [Error responses](errors.md), and [Request handling](request-handling.md).
