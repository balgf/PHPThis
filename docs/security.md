# Security baseline

AI-oriented explicitness does not replace security review.

- Separate SQL data from SQL structure. Bind every application or external data value with a unique named parameter, even after validation.
- Keep SQL passed to the three canonical direct `Connection` calls within the finite non-blank compile-time constant-string set enforced by `PHT006`.
- Keep statements static by default. Map a genuine structural choice to finite, code-owned, operation-specific reviewed statements and reject unknown choices before database work.
- Do not interpolate data or use a generic SQL sanitizer, escaping helper, identifier validator, query builder, or SQL template engine to turn arbitrary input into structure.
- Validate untrusted input before domain or database use.
- Reject unknown fields and coercive values at the boundary; do not cast malformed input into an accepted type.
- Distinguish absent keys from explicit `null`, apply the operation's recorded total and applicable field or collection bounds, and validate in a deterministic code-owned order before downstream operation behavior.
- Enforce `PHT001` so scalar conversion cannot silently turn unresolved `mixed` input into a trusted value.
- Keep normalization operation-specific and explicit; never strip or rewrite malformed input into an apparently valid default.
- Encode or escape output only for its actual sink; sink encoding is not input validation or authorization.
- Keep credentials outside committed configuration.
- Map only named client failures to generic public responses; broad built-in exceptions remain unknown failures.
- Log an unknown failure once without its message, SQL, parameters, or stack, then return the generic 500 response.
- Keep superglobal reads in the front controller and enforce body, request-target, query-count, header-count, and header-value bounds in `RequestReader`.
- Query traces contain only SHA-256 SQL fingerprints and aggregate metrics; never add SQL, parameters, credentials, exception messages, or driver details.
- Keep application code out of `$_SESSION` and native `session_*` calls. Access optional session state through narrowly named typed services with non-overlapping key ownership over one `SessionLifecycle`.
- Regenerate the session identifier before committing authenticated identity or another privilege elevation, invalidate it at logout, and reject malformed, attacker-selected, and obsolete identifiers.
- Keep session cookies `HttpOnly`, use the environment's explicit `Secure` and SameSite policy, omit Domain, and emit them only as validated `ResponseCookie` values.
- Add CSRF, authentication, authorization, idle and absolute session expiry, rate limiting, and security headers as explicit application policies when required. `SameSite` is not a replacement for CSRF validation.
- Treat cache keys and values as sensitive external storage: bound their sizes, separate application, environment, version, and tenant ownership, and never log complete keys or values.
- Parse every cache hit into its expected typed projection and treat malformed, stale-schema, cross-tenant, or otherwise invalid values as a recorded miss or failure; never deserialize PHP objects from a cache.
- Keep authentication, authorization, permissions, sessions, CSRF material, secrets, and other security decisions out of the initial application data-cache slice.
- Give personalized, session-affecting, authenticated, and sensitive HTTP responses an explicit `private, no-store` policy until the application records and tests a different safe policy; test cookie-emitting responses separately because `Set-Cookie` is not a cache prohibition.
- Do not deserialize untrusted PHP values or execute generated PHP.

Security mechanisms must remain visible in the route-to-handler path or in the one explicitly registered request boundary. Hidden defaults are not considered protection.

## Typed input boundaries

ADR 021 keeps field validation and any deliberate normalization in one operation-owned parser. The parser constructs a final readonly command only after the exact object or list shape, runtime types, ranges, and bounds succeed. Invalid input stops before operation-owned downstream I/O and mutation and makes zero calls to a separate typed operation seam when one exists. On a protected route, ADR 020's separately bounded authentication, tenant, and authorization work may deliberately occur before the protected handler parses its operation input.

Validation answers whether a representation is accepted. Normalization changes it under a recorded field policy. Output encoding answers how an accepted value is represented at one sink. Authorization answers whether the current principal may perform the named action. None substitutes for another: a normalized value can remain unauthorized, HTML encoding does not make SQL interpolation safe, and a validated SQL value remains a named parameter.

The first proof returns only generic prebuilt input failures and never includes submitted values or internal validation messages. Native JSON decoding rejects malformed UTF-8 but does not expose duplicate object keys and does not normalize valid Unicode. Applications must not claim either property without a separately reviewed parser or Unicode policy. See [Type safety](type-safety.md) and [ADR 021](decisions/021-application-owned-typed-input-boundaries.md).

## Request policy

ADR 020 keeps protected-request policy in one application-owned action-specific adapter. The visible order is authenticate, resolve tenant, authorize the current named action, then invoke the protected handler with concrete immutable principal and tenant values. Applications manually wire independently replaceable policies; PHPThis adds no middleware, request context, identity provider, tenant model, permission engine, discovery, or service location.

The reference proof is stateless. Its checked-in authenticator is deny-all and its consumer replacement is synthetic and I/O-free; PHPThis supplies no credential parser or verifier. A concrete Bearer implementation maps missing, malformed, and rejected credentials to one generic `401` response with `WWW-Authenticate: Bearer`. Ordinary forbidden and cross-tenant attempts share one generic `403`, so the response does not reveal whether a different tenant relationship exists. Known denials are not logged. Unexpected failures retain class-only logging, and authenticated plus denied responses start as `private, no-store`.

Authorization is current per request. Session or cache state may supply strictly parsed identity input but never a current authorization decision. Any policy reads use named connections, budgets, and traces distinct from protected handler work. Every denial stops before protected queries, writes, session mutation, cache mutation, and external business side effects. A successful decision does not install an implicit database scope: every protected statement still binds the applicable tenant and resource identifiers, and the application records any authorization-to-write race policy.

Credential issuance, verification algorithm, expiry, rotation, revocation, CSRF, rate limiting, audit events, identity-provider availability, and tenant-discovery semantics remain application and deployment decisions. See [Request policy](request-policy.md) and [ADR 020](decisions/020-application-owned-request-policy.md).

## Session limits

The optional session boundary certifies only PHP 8.4's native file handler with fixed identifier settings and an exact application-isolated save path. It bounds stored values and shortens native lock duration, but it does not authenticate a principal, authorize an operation, select expiry windows, revoke every session for an account, or prove a multi-node storage topology. Applications record and test applicable choices and must not treat possession of stored identity as current authorization.

Session identifiers, cookie fields, CSRF tokens, and complete snapshots are sensitive and do not enter logs, query traces, exception messages, or public errors. See [Session state](sessions.md) and [ADR 015](decisions/015-explicit-native-session-lifecycle.md).

## Cache limits

A cache is not an authorization boundary or source of truth. Tenant-aware key construction reduces collision risk but does not replace authorization before a response is returned. Expiration does not prove immediate invalidation, eviction can remove an unexpired value, and a database commit plus cache invalidation is not one atomic operation. An in-flight miss can also repopulate stale data after a concurrent writer commits and invalidates. Applications record whether invalidation failure or stale refill causes a request failure, bounded staleness, a version fence, or another explicit outcome and test that choice.

Cache backends receive the least network and command authority the application path needs. Network exposure, transport protection, authentication, memory policy, persistence, replication, and administrative access remain backend-specific deployment responsibilities. See [Caching policy](caching.md) and [ADR 016](decisions/016-cache-policy-before-cache-mechanism.md).

## SQL data and structure

PDO placeholders represent complete data literals. They cannot stand for identifiers, keywords, operators, ordering directions, or arbitrary fragments. A validated value is still data and remains bound; validation is not permission to interpolate it. See the [PHP `PDO::prepare` contract](https://www.php.net/manual/en/pdo.prepare.php) and [PHP SQL-injection guidance](https://www.php.net/manual/en/security.database.sql-injection.php).

When an operation needs variable structure, prefer mapping a typed choice to complete constant statements. A finite constant fragment is acceptable only when it is code-owned, local to that operation, and every resulting statement remains a compile-time constant-string choice. Unknown external choices fail rather than being stripped, escaped, or silently converted to a default. OWASP likewise recommends parameterized queries, finite allowlisting where binding cannot represent structure, and least privilege; it warns that generic table validation can be unsafe across different query contexts. See the [OWASP SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html).

Tests use valid domain values containing quotes, semicolons, SQL comment markers, and Unicode and prove exact round-trip through parameters without a changed statement fingerprint or leaked trace value. Every variable structural option has success evidence, and malformed or unknown choices fail before the query budget or trace records work. These adversarial examples test a separation property; they are not a blacklist of forbidden characters.

## Database authority

Each runtime connection uses only the database objects and actions its process needs. The web runtime does not receive schema-owner, migration, role-management, grant-management, or administrative credentials. Migrations and administration run through a separately authorized path whose credentials are unavailable to request handling. The application records and verifies these engine-specific grants in `.ai/data.md`; SQLite applications record the equivalent file ownership and process boundary.

Least privilege limits impact but does not replace application authorization. A permitted statement can still expose another tenant's data or perform the wrong domain action.

## Proof limits

PHT006 recognizes only the three direct canonical `Connection` calls and the native finite string type passed to them. It does not parse SQL, review statement intent, inspect stored procedures or server-side dynamic SQL, validate grants, or cover reflection and non-canonical invocation. A finite statement may still be destructive, logically incorrect, overprivileged, or unsafe inside the database. Parameterization, static analysis, runtime tests, authorization review, least-privilege verification, and engine-specific integration tests are complementary evidence.
