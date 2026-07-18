# ADR 015: Explicit native session lifecycle

Status: accepted

## Context

Applications need short-lived server-side state for capabilities such as authenticated identity and a shopping cart. Direct `$_SESSION` access looks local but starts from mutable process-global state, uses string keys without an application contract, and can hold PHP's session lock across unrelated work. A global helper, middleware chain, or session field on `Request` would hide the same lifecycle behind another spelling.

PHP's native session engine already provides identifier generation, persistence, locking, and strict-ID validation. Replacing it with a framework store or custom identifier scheme would add security and concurrency responsibilities before consumer evidence justifies them. Native session calls can also emit cookies and cache headers implicitly, which conflicts with PHPThis's immutable response and single response emitter.

## Decision

PHPThis adds one optional `SessionLifecycle` at `RequestBoundary`. The boundary records the request cookie after successful request parsing, delegates to the handler, attaches any pending validated `ResponseCookie` before emission, and aborts native state when an unknown failure escapes. Merely configuring the lifecycle does not start storage; stateless handlers remain stateless.

`SessionLifecycle` is the only framework code that parses the configured session cookie or calls PHP's native session functions. It exposes a closed `read()` snapshot, callback-scoped `update()`, `regenerateAndUpdate()` for authentication and other privilege elevation, and terminal `invalidate()` for logout or revocation. Reads use read-and-close and writes close immediately after a bounded side-effect-free callback. Passive rejected input emits no cookie; ordinary stale mutation raises `SessionUnavailable`; authenticated regeneration may replace rejected input only with a fresh server-generated identifier. The lifecycle tracks and destroys identifiers created but not emitted, and never adopts an attacker-selected identifier.

`SessionSnapshot` permits only a bounded map of application-owned scalar or `null` values. Applications place narrowly named typed services with explicit non-overlapping key ownership in front of the single lifecycle and define each applicable identity, expiry, authentication, authorization, CSRF, and revocation policy there. Each mutation preserves the supplied snapshot's unowned keys because its return value replaces the complete state. A stored identity is never itself an authorization decision.

Cookies are explicit response values. `Response` carries validated `ResponseCookie` objects and `ResponseEmitter` serializes separate `Set-Cookie` fields. The native session engine is configured not to emit cookies or cache headers. Session cookies use `Path=/`, `HttpOnly`, an explicit SameSite policy, no Domain, browser-session lifetime, and `Secure` when required by their name, SameSite mode, and deployment policy.

PHPThis certifies only PHP 8.4 `ext-session` with the native `files` save handler, fixed settings, and an explicitly configured save path proven isolated to one application identity. Native and cookie names do not namespace files. Custom `SessionHandlerInterface` implementations, database or Redis stores, alternate identifier shapes, and unsupported multi-node storage topologies require a separate accepted decision and concurrent integration proof.

Consumer Contract version 3 adds this cookie and session boundary while carrying Strict Profile version 2 forward unchanged.

The Phase 1 core-source cap increases from 900 to 1,700 physical lines for this accepted cookie and session slice, including the save-path isolation, stale-request rejection, and unissued-state cleanup required by security and concurrency review. This margin does not pre-authorize another mechanism.

## Consequences

Session I/O, cookie emission, identifier rotation, invalidation, and locking have one inspectable execution path. Anonymous and session-free endpoints do not acquire state merely because the application supports sessions. Application code receives bounded immutable values instead of PHP globals, while native PHP retains responsibility for the storage format and lock implementation.

PHPThis does not become an authentication, authorization, or CSRF framework. Applications must record and test those policies. File-backed storage requires explicit deployment ownership, cleanup, and topology evidence and is not automatically appropriate for a multi-node service.

Session mutation commits before returning and cannot be rolled back with later database, external, or handler work. Applications complete fallible work first and keep the final mutation callback I/O-free. Read-and-close does not refresh file modification time, so cleanup retention exceeds the application absolute lifetime rather than treating read traffic as activity.

Immediate deletion of the old file during identifier regeneration can race with concurrent requests. The lifecycle instead marks the previous identifier obsolete, refuses to recover state through it, and leaves physical cleanup to the configured native garbage-collection policy. Account-wide revocation remains application policy; physical cleanup remains deployment policy.

## References

- [PHP manual: securing session configuration](https://www.php.net/manual/en/session.security.ini.php)
- [PHP manual: session security management](https://www.php.net/manual/en/features.session.security.management.php)
- [PHP manual: basic session locking](https://www.php.net/manual/en/session.examples.basic.php)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP Cross-Site Request Forgery Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)

## Reconsider when

Consumer evidence requires a shared session store, a long-lived worker runtime, an application cannot meet the fixed native configuration, the bounded scalar snapshot cannot represent a proven use case, or concurrency tests expose behavior the native file handler cannot satisfy. Reconsider through one replacement contract, not a second helper or parallel session lifecycle.
