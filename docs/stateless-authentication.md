# Application-owned stateless authentication

PHPThis is Bearer-ready only at its bounded HTTP header and explicit request-policy seams. It does not provide JWT, personal access token (PAT), API-token, OAuth, or external-identity-provider support. PHPThis supplies no credential parser, verifier, issuer, revoker, identity provider, or authentication runtime/API.

The credential format, every library and external service, issuance and refresh behavior, key or verifier storage, lifecycle, and production evidence remain application-owned. This guide changes no core source, runtime dependency, Consumer Contract, Strict Profile, checker rule, or `PHT` diagnostic.

Here, stateless means that each request presents its credential without using PHPThis session or cookie identity. It does not mean that authentication is I/O-free: an application-owned verifier may perform explicit bounded database, trusted-key, or external-provider I/O under the budgets and outage contract below.

## Start at the exact HTTP boundary

`RequestReader` translates `HTTP_*` runtime fields to lowercase header names and exposes the accepted credential field as `$request->headers['authorization'] ?? null`. The complete request boundary accepts at most 64 headers and at most 8,192 bytes per header value, rejects control characters and conflicting normalized fields, and collapses identical normalized duplicates. Those are framework transport ceilings, not a credential grammar or a safe credential-size default.

The first server or reverse-proxy boundary that can still observe raw field multiplicity must require either no `Authorization` field or exactly one and reject duplicates before SAPI normalization. It must preserve the accepted field unchanged into `HTTP_AUTHORIZATION`, use TLS with certificate validation for every credential-bearing request, and apply its recorded request-header limits. The application authenticator then accepts one Bearer representation under a smaller recorded byte bound and finite grammar. HTTP authentication-scheme matching is ASCII case-insensitive, while the credential bytes are case-sensitive and opaque. An [RFC 6750](https://www.rfc-editor.org/rfc/rfc6750) profile accepts one or more ASCII `SP` bytes after the scheme and never a tab. A controlled-client application may deliberately require exactly one `SP`, but it must label that narrower grammar non-RFC-6750-compatible, record the interoperability limit, and claim no security benefit from the difference.

Do not fall back to a query parameter, request body, cookie, path segment, alternate header, or previously stored identity when the `Authorization` field is absent or invalid. Do not trim, decode, case-fold, or otherwise normalize credential bytes unless the selected protocol explicitly requires and tests that transformation. Keep the complete field and credential out of URLs, responses, exceptions, logs, terminal summaries, traces, fixtures, and support output.

`WWW-Authenticate: Bearer` is response semantics for the generic unauthenticated result. It is not evidence that PHPThis parses, verifies, issues, refreshes, rotates, or revokes any kind of token. The checked-in authenticator is deny-all, and test Bearers are synthetic only; neither is production credential evidence.

ADR 020's checked reference deliberately uses the bare `WWW-Authenticate: Bearer` challenge and the same generic `401` for malformed and rejected credentials. That disclosure-minimizing reference challenge and error policy is not RFC-6750-compatible: RFC 6750 Section 3 requires a Bearer challenge to contain at least one authentication parameter and defines distinct error mappings. Do not change or describe the accepted reference contract merely to imply interoperability.

An application claiming RFC 6750 resource-server compatibility records its complete replacement challenge and error profile. Include at least one fixed non-sensitive authentication parameter, such as a reviewed `realm`, and prove its exact quoting and character grammar. Record the exact absent-credential challenge, `invalid_request` status and error mapping for the applicable malformed request cases, `invalid_token` `401` mapping for definitively invalid credentials, and `insufficient_scope` `403` mapping where that application can disclose the classification safely. Omit optional `error_description`, `error_uri`, and required-scope detail unless each value is finite, non-sensitive, reviewed, and tested. No challenge or error parameter may contain credential bytes, token claims, principal, tenant, resource identity, verifier detail, or provider failure information. The application still keeps verifier uncertainty on its separate generic `5xx` path and does not emit an invalid-token or refresh signal for an outage.

## Record one credential profile

Before protecting a route, an accountable human records the accepted credential profile and why it fits the callers and deployment. `Bearer` names the HTTP authorization scheme; it does not say whether the carried credential is a JWT, an opaque access token, a PAT, or an application API token.

For every accepted credential kind, record:

- the caller types, exact scheme and parser grammar, effective byte bound, version or prefix, and whether multiple credential kinds are deliberately supported;
- the issuer or generator, intended recipient, subject or owner meaning, tenant binding, accepted lifetime, authoritative clock, allowed skew, and current expiry and revocation checks;
- the credential's scopes or other bounded authority input and its mapping to the application's named actions; a scope or claim is input to current authorization, not the authorization decision itself;
- the pinned maintained parser or cryptographic dependency and exact version, supported profile, security-update owner, configuration inputs, key or verifier storage, and removal or migration plan;
- issuance, one-time disclosure, refresh or replacement, overlap, rotation, revocation, compromise response, audit, retention, and deletion behavior;
- rate limits, abuse detection, redacted operational outcomes, and every accepted production proof limit; and
- the generic public failure mapping and the behavior for a verifier, key source, clock source, database, or external identity provider that is slow, unavailable, malformed, or inconsistent.

An ordinary Bearer credential is replayable by any party that possesses it. Restrict its audience, resource, actions or scopes, and lifetime to the least authority the caller needs, and record the residual replay window. Record whether sender constraint is not applicable or is a separately adopted and proved profile. OAuth mTLS-bound tokens and DPoP are separate protocol, key, proxy, proof-replay, nonce, and failure decisions; this guide does not implement either. See [RFC 9700, Best Current Practice for OAuth 2.0 Security](https://www.rfc-editor.org/rfc/rfc9700).

When more than one credential kind is accepted, give each a mutually exclusive grammar and verification profile. Never guess a format after one verifier fails, accept the same bytes under multiple profiles, or use a fallback verifier.

## JWT profile

A JWT is a structured security token, not a framework feature and not proof of current authorization. Select a mature maintained JWT or JOSE library, pin it in the consuming application's dependency lock, and use its verified high-level validation API. Do not hand-roll token encoding, signature or encryption verification, algorithm selection, key parsing, or constant-time comparison.

The application profile follows [RFC 8725, JSON Web Token Best Current Practices](https://www.rfc-editor.org/rfc/rfc8725) and records at least:

- one fixed code-owned set of acceptable algorithms, selected independently of the received `alg`; reject `none`, algorithm substitution, and every algorithm outside that set, and bind each key to its one intended algorithm and use;
- one fixed JOSE protection and serialization profile, such as one exact compact JWS shape; reject unsupported JWE, nesting, compression, detached payload, unencoded payload, serialization, and critical-header behavior, and reject every unrecognized or unsupported `crit` entry;
- UTF-8 for the protected header and claims JSON, one finite allowlist of protected-header parameter names, and rejection of every unsupported member; an untrusted `x5c`, embedded key, certificate, thumbprint, or other header never supplies or substitutes verification trust material;
- validation of every cryptographic operation and rejection of the complete JWT when any operation fails;
- the exact trusted issuer and its binding to the verification keys, the expected subject or issuer-subject rule, and the exact required audience for this API;
- required claim names, native JSON types, scalar and collection bounds, canonical comparison rules, and the handling of unknown claims;
- rejection of duplicate protected-header and claim member names, or one explicitly recorded canonical duplicate-member behavior of the selected library when it cannot expose duplicates; test that exact behavior and do not claim duplicate rejection when the parser cannot prove it;
- the required `exp`, permitted `nbf` and `iat` relationships, maximum accepted lifetime, authoritative injected clock, and finite allowed clock skew;
- an explicit token type and mutually exclusive validation rules when another JWT kind could be confused with the API access token; an ID token, refresh token, or token for another service is not an API access token;
- a bounded grammar for untrusted `kid` before finite key lookup; a received `jku`, `x5u`, issuer, or other claim never selects an arbitrary file, database expression, class, command, or outbound URL; and
- key generation, entropy, private-key authority, publication, cache, rollover, overlap, emergency revocation, and removal evidence.

Local signature verification does not prove current revocation. Record one enforceable mechanism and its maximum revocation lag: a short maximum JWT lifetime, a current token denylist, a subject or account security-version/cutoff check, RFC 7662 introspection, key-wide emergency invalidation, or a deliberately composed combination. Apply the selected current checks on every request and prove token revocation, disabled-subject or disabled-account behavior, key rollover, emergency invalidation, and the maximum accepted stale window. Do not claim immediate revocation when the chosen lifetime or cache permits later acceptance.

Do not read claims before successful cryptographic and profile validation except through the library's explicitly untrusted inspection path needed for a bounded key choice. Even after validation, parse accepted claims into one concrete immutable principal input and run current tenant resolution and action authorization. Do not place secrets or unbounded personal data in a JWT payload; signed JWT content is not thereby confidential.

## Opaque access tokens, PATs, and API tokens

An opaque credential carries no client-trusted claims. A PAT normally represents a human owner and selected scopes; an application API token may represent a machine or integration. The application records those meanings instead of inferring them from a shared string shape.

Generate the secret with a cryptographically secure random source at the recorded entropy and byte length. Use a finite version or non-secret lookup prefix only when the complete format, collision bound, and migration policy are recorded. Show a newly issued secret once, never make the raw value retrievable later, and store only a purpose-built one-way verification value rather than the raw credential. Name the exact maintained verifier construction: for example, a keyed verifier with a separately stored and rotated key or a reviewed password-verifier construction with recorded entropy, cost, and denial-of-service bounds. Record what an offline database reader and an application-host compromise can recover. The database artifact must not be reversible into or directly reusable as the Bearer credential. Require a timing-safe final secret comparison and test that the raw credential never persists and that stored lookup, display, and verifier artifacts are rejected when presented as credentials; do not invent token cryptography.

Each durable record has a bounded stable identity plus its owner or service principal, tenant binding where applicable, finite scopes, creation time, expiry, active or revoked state, revocation time or bounded reason code when needed, and safe operational metadata. Every request checks the verifier, active state, expiry, revocation, owner state, tenant relationship, and scopes needed as input to the separate authorizer. A lookup prefix, display suffix, token ID, or stored digest is not itself authentication and must not become a bearer credential.

Issuance, listing safe metadata, rotation, and revocation are separately authorized application operations. Record whether rotation has no overlap or one finite overlap window, which credential becomes invalid at each step, how emergency and owner-wide revocation work, and how concurrent requests observe the change. Audit only bounded code-owned outcomes and safe record identities; never record the presented secret or its reusable verifier.

## External identity provider and introspection

An external issuer does not move authentication or authorization into PHPThis. The application pins the provider and protocol, exact issuer and audience, metadata or key endpoint when used, TLS and certificate policy, client identity, timeouts, response byte and field bounds, retry owner, rate limits, key-refresh behavior, and provider update policy. A token-controlled issuer or key URL never selects an outbound destination.

Local JWT verification may use a separately fetched trusted key set only through one configured endpoint. Record key-cache freshness, maximum staleness, refresh coordination, unknown-key behavior, rollover overlap, startup behavior, and the failure mode when a trustworthy current key is unavailable. Key retrieval is external I/O; give it a named client, finite connection and total timeouts, response bounds, budget, trace, and redacted failure evidence. Disable HTTP redirects for key retrieval and introspection by default. If the application deliberately permits redirects, allow only exact configured same-trust destinations, revalidate scheme, authority, path, TLS, and certificate policy at every hop, cap the hops, and never forward the caller's Bearer credential or introspection client credentials across a redirect.

When the selected provider supports [RFC 7662 token introspection](https://www.rfc-editor.org/rfc/rfc7662), use one authenticated application-owned client to send the exact protocol `POST` to the configured endpoint over TLS with certificate validation. Strictly parse the bounded response and accept only the recorded `active: true`, issuer, audience, subject, client, time, and scope profile. A trusted `active: false` is definitive credential rejection. An incomplete or malformed response, unauthorized introspection client, timeout, transport failure, untrusted TLS result, or provider outage is verifier uncertainty: it fails closed and never produces an authenticated principal, but it is not evidence that the caller's credential is invalid.

An introspection cache is a separate security decision: its finite lifetime is also a maximum revocation-staleness window. Derive its lookup key from the credential with one selected maintained one-way keyed primitive and a cache-specific key so neither a cache key nor value contains the raw Bearer credential or another bearer-reusable artifact. Record the derivation domain, collision bound, application, environment, provider, and tenant separation, key rotation, payload fields and bounds, maximum stale acceptance, invalidation, clock, outage behavior, and evidence. Prove that retained cache keys and values are rejected when presented as credentials. Do not describe cached activity as current without the accepted bound. Introspection client credentials are distinct secrets and never reuse or expose the caller's Bearer credential except in the protocol's exact protected request.

Authentication-provider success establishes only the accepted principal input. The application still resolves the current tenant and authorizes the current named action. Provider account availability, token issuance, consent, federation, recovery, user suspension, and incident response remain application and deployment concerns.

## Keep issuance and refresh separate

A resource route's authenticator consumes an already issued access credential. Login, authorization-code exchange, token issuance, refresh, PAT creation, and credential recovery are distinct application operations with their own request boundaries, client authentication, redirect and origin rules, anti-replay values, rate limits, side effects, public failures, and evidence.

Do not accept a refresh token at an API resource route or let a failed access token trigger an implicit refresh. A frontend may coordinate an explicitly recorded refresh operation, but it must serialize or otherwise bound concurrent refresh, define replay and rotation failure behavior, and avoid retrying a mutation merely because refresh or transport failed. The issuer, not the protected handler, owns credential creation.

This resource-server guide does not define an OAuth authorization server or client flow. If an application adopts either, record it as a separate security decision following [RFC 9700](https://www.rfc-editor.org/rfc/rfc9700) and the selected provider profile. Do not use the resource-owner password credentials grant or the implicit grant. A browser authorization-code client uses PKCE with `S256`, an exact registered redirect URI, transaction-bound redirect and CSRF protection, and `state` and OpenID Connect `nonce` where their selected protocols require them; it also records authorization-response issuer validation, code single use, client authentication, refresh-token rotation or sender constraint, and every timeout and error boundary. Bearer resource handling does not establish any of that behavior.

## Preserve the request-policy order

Credential parsing and verification occur inside the action-specific authenticator. Every protected request still executes exactly:

```text
authenticate -> resolve tenant -> authorize -> protected handler
```

Authentication returns one concrete immutable application principal. Tenant resolution and authorization receive it explicitly; no credential, claims map, principal, tenant, or authorization decision is installed on `Request`, in a global or generic context bag, or in a session or cache snapshot. Authorization runs for the current named action on every request. Protected SQL remains explicitly tenant- and resource-scoped after authorization.

Verifier database or network work uses a separately named dependency, budget, and trace from tenant policy and protected data work. A denial or verifier outage executes no protected query, write, session or cache mutation, or external business effect. Record the exact fail-closed public mapping for dependency uncertainty or outage as one named generic `5xx` application failure, distinct from a definitive invalid-credential `401`. It must not assert that the credential is invalid, emit an invalid-token or automatic-refresh signal, silently try another source, or disclose whether a credential, subject, tenant, or account exists.

Missing, malformed, oversized, expired, not-yet-valid, revoked, definitively inactive, wrong-issuer, wrong-audience, wrong-type, invalid-signature, and otherwise definitively rejected credentials share the application's generic `401` Bearer response. Verifier uncertainty uses the separately recorded generic `5xx`; it never triggers client refresh automatically. Ordinary forbidden and cross-tenant decisions share the generic `403`. Authenticated, denied, and post-policy failures start with `Cache-Control: private, no-store` unless a later accepted policy proves another result.

## Frontend boundary

A separately owned frontend records how it obtains, retains, sends, refreshes, and discards a credential. A JavaScript-readable Bearer credential is exposed to successful script execution in that origin, so the application must explicitly assess XSS, third-party scripts, browser extensions, persistence, backup or sync, logout, multi-tab behavior, and crash reports before selecting memory or durable browser storage. Do not put a Bearer credential in a URL, HTML, page source, client log, analytics event, error report, or service-worker cache.

An `HttpOnly` cookie prevents ordinary frontend JavaScript from reading the value, but cookie-backed authentication enters the separate session, `Secure`, SameSite, expiry, logout, and CSRF contract; SameSite does not replace CSRF validation. Do not describe a cookie as stateless Bearer transport merely because its server-side identity record is small.

Same-origin frontend and API delivery avoids CORS but not XSS, authentication, authorization, or CSRF. Cross-origin Bearer use enters the complete CORS policy in [Frontend integration](frontend-integration.md): the exact origin, `Authorization` request header, preflight, allowed and exposed headers, credential mode, cache variation, every success and failure path, proxy behavior, and real-browser evidence must agree. A refresh credential or identity-provider redirect adds its own origin and browser-security decisions.

## Required evidence

Keep fixtures synthetic, non-production, bounded, and unmistakably invalid outside the test profile. Never copy a real credential, signing key, provider response, user record, or production endpoint into source or retained output.

| Boundary | Evidence |
| --- | --- |
| Raw HTTP and deployment | TLS and certificate validation; absent and exactly one raw `Authorization` field; duplicate rejection at the first observable boundary; proxy/SAPI forwarding; ASCII-case-insensitive scheme, exact separator, case-sensitive credential; no query, body, cookie, path, or alternate-header fallback; header and credential bounds |
| Parser and generic failures | absent, malformed, wrong scheme, whitespace and case policy, control bytes, oversized value, and unsupported credential kind; the reference's bare disclosure-minimizing challenge and identical redacted `401` behavior are explicitly non-RFC-6750-compatible; an application claiming RFC 6750 compatibility proves fixed non-sensitive auth-param grammar plus exact absent, `invalid_request`, `invalid_token`, and `insufficient_scope` status/error behavior; verifier uncertainty remains the distinct generic `5xx` with no refresh signal |
| JWT | exact JOSE serialization/protection profile and UTF-8; finite protected-header allowlist; unsupported nesting, JWE, compression, `crit`, `x5c`, embedded trust material, and duplicate protected-header or claim members under the recorded library policy; algorithm and key substitution; invalid signature; wrong issuer, audience, subject, type, and required claim shape; expired, future, overlong, and skew-bound time cases; untrusted `kid` and remote-URL claims; current revocation and disabled-subject checks; key rollover, maximum revocation lag, and unavailable-key behavior |
| Opaque/PAT | generation and one-time display; named verifier and compromise assumptions; absence of raw or reversible durable storage; timing-safe verifier mismatch; stored-artifact non-reusability; active, expiry, revocation, owner, tenant, and scope checks; rotation overlap; concurrent revocation; safe listing and redaction |
| External verification | bounded authenticated RFC 7662 `POST` with TLS certificate validation; redirect rejection and, when deliberately allowed, exact same-trust hop revalidation without credential forwarding; active false, incomplete, unauthorized-client, malformed, timeout, rate-limit, and outage responses; invalid `401` versus uncertainty `5xx`; fail-closed behavior; key or activity-cache freshness and maximum revocation staleness; derived cache-key collision and provider/tenant separation; no raw or bearer-reusable cache artifact |
| Request policy | exact `authenticate -> resolve tenant -> authorize -> protected handler` order; no later call after failure; zero protected work on every denial; independent manual replacement; explicit principal and tenant delivery |
| Lifecycle and operations | Bearer replay limit and sender-constraint decision; issuance and refresh separation; least-authority audience, resource, action, scopes, keys, and client credentials; rotation and emergency revocation; authoritative clock; rate limiting, audit, incident response, and redacted observability |
| Frontend | chosen storage and disclosure boundary; logout and refresh concurrency; XSS review; cookie and CSRF behavior when adopted; complete CORS preflight and actual-response behavior; real-browser proof |

The checked-in deny-all implementation and synthetic Bearer fixtures prove only PHPThis's request-policy composition and generic failure boundary. Production acceptance requires evidence for the consuming application's selected parser, verifier, credential lifecycle, external dependencies, deployment, and clients.

See [Request policy](request-policy.md), [Security baseline](security.md), [Frontend integration](frontend-integration.md), [Session state](sessions.md), [Configuration](configuration.md), [Error responses](errors.md), and [Terminal request summaries](observability/README.md).
