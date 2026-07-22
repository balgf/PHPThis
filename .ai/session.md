# Session contract

`SessionLifecycle` is the one framework boundary around PHP's native session engine. It is optional at `RequestBoundary`, remains inactive until application code calls it, and is not authentication, authorization, or CSRF protection.

Rules:

- Inject the single `SessionLifecycle` only into narrowly named application services such as `AuthenticationSession` or `CartSession`; give each service explicit non-overlapping key ownership and do not expose arbitrary keys throughout handlers.
- Every service mutation starts from `$current->values`, changes or removes only keys that service owns, and preserves every unowned key. Returning a partial snapshot deletes omitted state owned by another service.
- Do not read `$_SESSION`, call native `session_*` functions, parse the session cookie, or emit its `Set-Cookie` header in application code.
- Keep session state within `SessionSnapshot`: at most 64 application-owned keys, scalar or `null` values only, and no domain objects, credentials, authorization decisions, or unbounded payloads.
- Use `read()` for a closed immutable snapshot, `update()` for ordinary callback-scoped mutation, `regenerateAndUpdate()` before storing identity after authentication or another privilege elevation, and terminal `invalidate()` for logout or revocation. Only authenticated regeneration may replace rejected input with a fresh server-generated identifier; ordinary stale mutation remains rejected.
- Keep every mutation callback bounded, synchronous, and side-effect-free: derive one `SessionSnapshot` only from the supplied snapshot and already-computed values, with no database, network, filesystem, logging, or nested session work.
- Complete fallible domain, database, and external work before the final small session mutation. A successful mutation commits immediately; `abort()` cannot roll it back or make it atomic with another resource.
- Do not retain a `SessionSnapshot` as mutable authority. Re-evaluate authorization from current application data on each protected operation.
- Keep applicable authentication, authorization, idle and absolute expiry, and CSRF concerns as explicit application policies with named owners and tests; record each absent concern explicitly not applicable.
- Configure `SessionConfiguration` once in the composition root with the exact effective save path. Record and verify that this path is isolated to one application identity; the native session name does not namespace files.
- The certified storage mechanism is PHP's native `files` handler. Do not install a custom `SessionHandlerInterface`, database session table, Redis adapter, or alternate identifier format without a new accepted decision and concurrency evidence.
- Keep session use lazy. A stateless handler must not create storage, issue a cookie, or acquire a native session lock.
- Treat `SessionUnavailable` as an ordinary stale-mutation result that must not be retried inside the same request. Passive invalid or obsolete input emits no cookie, preventing a delayed response from replacing a freshly rotated cookie. After invalidation, no further session operation is allowed in that request.
- Native read-and-close does not refresh file modification time. Configure native or external cleanup retention beyond the application's absolute session lifetime; do not rely on read traffic to keep files alive.

The request boundary owns `begin`, `finish`, and `abort`. Application services own only the operation they need. Never move this lifecycle into an application-owned request-handler decorator or add a global helper, generic or framework middleware layer, request attribute, or generic session repository around it.
