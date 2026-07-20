# ADR 020: Application-owned request policy composition

Status: accepted

## Context

A protected tenant-scoped request must establish a current principal, resolve its tenant context, and authorize one named action before protected business or database work. PHPThis already supplies immutable normalized requests, bounded typed route parameters, explicit handlers, exact error mapping, query budgets, and redacted query traces. It does not supply identity meaning, credential verification, tenant membership, or permission policy.

A generic middleware pipeline, request context bag, policy registry, model-bound authorization, or global tenant scope would hide order, dependencies, queries, and failure behavior. Universal principal and tenant types would also force unrelated applications into one representation without evidence that their security policies are equivalent. The Alpha 2 core already occupies 2,246 of its 2,300 reviewed lines, and its maintenance margin does not authorize an adjacent request mechanism.

## Decision

The first request-policy proof is an application pattern and adds no core runtime contract. Consumer Contract version 4 and Strict Profile version 2 remain unchanged.

One route-specific application adapter implements `RequestHandler`. Its ordinary constructor receives three narrowly named application-owned interfaces and the protected handler or action. Its `handle` method executes one visible straight-line sequence:

1. authenticate the normalized request into a concrete immutable principal;
2. resolve a concrete immutable tenant context from the already validated route identifier;
3. authorize the current principal, tenant, and named action for this request;
4. pass the principal, tenant context, and route-specific identifiers explicitly to the protected operation.

Every interface is an application extension point and every implementation is wired manually in the composition root. The adapter is not a reusable policy registry or generic middleware mechanism. Principal and tenant context do not enter `Request`, `PathParameters`, session state, a global, or a generic attribute bag.

The proof is stateless and exposes the explicitly bounded `Authorization` request header to the application authenticator. The checked-in composition wires a deny-all implementation, while consumer evidence replaces it with an I/O-free synthetic implementation. Missing, malformed, and rejected Bearer credentials use the same generic `401` response with `WWW-Authenticate: Bearer`, but PHPThis supplies no credential parser or verifier. A principal is authenticated anew for each protected request. Session adoption, credential issuance, cryptographic verification, expiry, rotation, and revocation remain application and deployment decisions; the proof does not claim a production identity provider.

Authorization is current per request. The proof policies are deliberately I/O-free. If an application policy reads storage, it uses a separately named connection with its own `QueryBudget` and `QueryTrace`; the protected handler uses a distinct connection, budget, and trace. A denial may consume only its declared policy-read budget but performs no protected query, handler write, or session mutation. Policy evaluation has no implicit retry, cache, or fallback.

Ordinary forbidden and cross-tenant attempts return the same generic `403` response. Public responses and Bearer challenges contain no credential, principal, tenant, or resource identifier. This decision originally added no denial logger and retained the separate class-only unknown-failure line. ADR 023 supersedes that wording: every selected denial response receives the one application-owned terminal summary attempt with only its generic known-failure outcome and status, while an unknown failure contributes only its concrete class to that same event. There is no denial-specific event, separate unknown-failure log, or framework log sink.

Every authenticated, denied, and sensitive response begins with `Cache-Control: private, no-store` unless a later accepted application policy proves a different result. Authentication and authorization decisions never enter the application data cache.

Authorization does not become an implicit database scope. A protected query still binds its explicit tenant and resource identifiers, even after authorization succeeds, and runtime database authority remains least privileged. The application records any authorization-to-write race or transaction rule rather than assuming that an earlier decision stays true indefinitely.

## Consequences

The policy order and all dependencies remain visible in one route adapter and one composition root. Tests can replace each policy implementation independently, record the exact call order, and prove that a denied request never reaches protected work. When a policy performs I/O, its reads and protected data work remain distinguishable in separate budgets and traces.

Applications repeat small action-specific adapters and types instead of inheriting a universal principal, tenant, permission, middleware, or policy engine. The first proof establishes a canonical composition shape, not common domain semantics. A public liveness-only application records request policy as not applicable until it adds a protected route.

No core PHP file, runtime dependency, Consumer Contract version, Strict Profile version, or PHPThis diagnostic changes. Authentication algorithms, token formats, identity providers, permission storage, tenant discovery, CSRF, rate limiting, credential lifecycle, and audit logging remain explicitly application-owned.

## Reconsider when

At least two independent applications prove the same smaller runtime contract with compatible identity and failure semantics, replaceable implementations, bounded policy queries, and no generic context or middleware; or a protected request cannot be expressed through one explicit application adapter without duplicating framework-owned transport behavior. Reconsider one evidence-backed contract rather than a policy engine.
