# Session state

PHPThis supports optional server-side session state through one explicit wrapper around PHP's native session engine. The boundary is deliberately smaller than an authentication system: applications still define identity, authentication, authorization, expiry, and CSRF policy.

## Execution contract

When configured, `RequestBoundary` calls `SessionLifecycle::begin` after a valid `Request` is created. This records the request's cookie header but does not start native storage. If the handler never uses the lifecycle, the response receives no session cookie, no session file is created, and no session lock is acquired.

Application code accesses the single lifecycle only through narrowly named typed services. For example, `AuthenticationSession` may expose `currentUserId`, `signIn`, and `signOut`, while `CartSession` owns only cart keys. Services have explicit non-overlapping key ownership; handlers do not exchange arbitrary session keys or depend directly on `$_SESSION`. Because a returned snapshot replaces the complete state, each mutation starts from `$current->values`, changes or removes only keys owned by that service, and preserves every unowned key.

The available state operations are intentionally distinct:

- `read()` returns a closed immutable `SessionSnapshot`; an anonymous request remains stateless;
- `update()` opens writable state only for the supplied callback and commits the returned snapshot;
- `regenerateAndUpdate()` changes the identifier before committing an authentication or privilege-elevation change and may recover rejected input only after the application has authenticated independently of session state;
- `invalidate()` makes the current server-side identifier obsolete and schedules an expired browser cookie; it is terminal, so every later session operation in that request raises `LogicException`;
- `finish()` adds any pending cookie to the immutable response and verifies that no native session lock remains;
- `abort()` closes active storage and destroys an identifier created but not emitted during this request; it cannot reverse a completed update to an identifier the browser already owned.

The request boundary owns `begin`, `finish`, and `abort`. A handler or application service does not call them. Mutation callbacks run while the native file lock is held, so they only derive and validate one snapshot from the supplied snapshot and already-computed values. They perform no database, network, filesystem, logging, nested session work, or other side effect.

`update()` and `regenerateAndUpdate()` commit before returning. They are operation-level commits, not request transactions, and are not atomic with a database or external service. Complete all fallible domain, database, and external work first, then make the final session mutation small. If later work nevertheless fails, an existing browser-owned session update remains committed; only never-issued state can be safely destroyed by the boundary.

## State bounds

`SessionSnapshot` accepts at most 64 values. Keys are application-owned tokens of at most 64 bytes and cannot use the reserved `__phpthis_` prefix. Values are only `bool`, `int`, `string`, or `null`; each string is limited to 8,192 bytes and all strings together are limited to 65,536 bytes. Nested arrays, objects, resources, and other value types are mechanically rejected. Credentials, authorization decisions, domain entities encoded as strings, and serialized payload strings are prohibited application content, but a scalar transport boundary cannot identify them by meaning.

These are transport bounds, not an application schema. The owning application services must define the smaller set of allowed keys and parse their meaning strictly. A stored identity is input to a current authorization decision, not proof that an operation remains authorized.

## Cookie contract

Session cookies are explicit `ResponseCookie` values rather than manually encoded response headers. `SessionConfiguration` supplies separate native and browser names, the environment's `Secure` policy, one explicit `CookieSameSite` value, and the exact expected native save path. The internal native name is at most 64 ASCII alphanumeric bytes and must contain at least one ASCII letter. The browser cookie name is an HTTP token of at most 128 bytes. The session cookie always uses `Path=/`, `HttpOnly`, no `Domain`, and browser-session lifetime. `__Host-` and `__Secure-` names require `Secure`; `SameSite=None` also requires `Secure`.

`Response` carries a list of validated cookies so multiple `Set-Cookie` fields remain distinct. `ResponseEmitter` is the only component that serializes those values into headers. Application code must not place `Set-Cookie` in the ordinary header map.

The lifecycle accepts only one exact configured session-cookie occurrence and the certified 32-character lowercase hexadecimal identifier shape. Passive reads of malformed, duplicated, unknown, nonexistent, or obsolete identifiers produce anonymous state without `Set-Cookie`; a delayed response must not delete or replace a fresh cookie issued by a concurrent login. Ordinary mutation using rejected or stale state raises `SessionUnavailable` without retaining storage or emitting a cookie. After the application authenticates independently of session state, `regenerateAndUpdate()` may recover malformed or garbage-collected input with a fresh server-generated identifier. A successful ordinary mutation from a truly cookieless request also issues a new identifier. Only explicit terminal `invalidate()` schedules expiration, and an obsolete delayed invalidation emits no cookie.

An application maps `SessionUnavailable` to one deterministic stale-state response and performs no further session mutation in that request. A browser retry can then use a cookie installed by a concurrent successful response. If malformed or garbage-collected anonymous state persists, an explicit client-requested restart operation may call terminal `invalidate()` to expire it; the application does not silently retry or regenerate an unauthenticated mutation.

## Native runtime policy

The current certification uses PHP 8.4, `ext-session`, and the native `files` save handler. Before any storage access, `SessionLifecycle` validates:

```ini
session.auto_start=0
session.use_only_cookies=1
session.use_trans_sid=0
session.sid_length=32
session.sid_bits_per_character=4
session.save_handler=files
session.save_path=<application-isolated configured value>
```

The identifier length and alphabet are PHP 8.4 defaults; do not override their deprecated directives. The lifecycle disables native cookie and cache-header emission for each start, enables strict mode, and emits the reviewed cookie through `Response`. Read-only access uses PHP's read-and-close option; writable access closes immediately after its bounded callback and commit. Keeping the callback side-effect-free prevents the lock from covering database, network, rendering, or other external work.

`SessionConfiguration` requires the exact effective native save path, and the lifecycle fails if `session.save_path` differs. The application proves that this path is owned and isolated for one application identity; changing the native session name or browser cookie name does not namespace `sess_<id>` files, so sharing a writable path can cause cross-application state confusion. Record the non-secret source and verification date, ownership, permissions, deployment topology, and cleanup policy in `.ai/operations.md`. Multi-node deployments without a correctly shared and locked filesystem, custom save handlers, Redis, and database-backed storage are unsupported until a separate decision and concurrent integration evidence exist.

Read-and-close does not refresh a native session file's modification time. Native or external garbage-collection retention must exceed the application's absolute session lifetime and its documented concurrency margin; active read-only traffic is not evidence that a file remains fresh. Production proof covers cleanup under the exact runtime policy.

## Application-owned security policy

PHPThis does not infer:

- which credential flow authenticates a user;
- which identity or tenant keys are stored;
- which operations require authorization;
- idle or absolute expiration windows and their timestamp keys;
- CSRF token generation, comparison, rotation, and protected methods;
- account-wide revocation or concurrent-session policy.

Applications record those decisions in `.ai/architecture.md` and `.ai/operations.md`. Cookie-authenticated state-changing operations require an explicit CSRF policy; `SameSite` is defense in depth, not a substitute. Authentication and privilege changes call `regenerateAndUpdate()` before the new identity is committed. Logout calls `invalidate()`. Idle and absolute expiration are checked by the typed services that own authenticated state before returning it.

Framework tests cover anonymous stateless access, malformed, duplicated, attacker-selected, stale, and obsolete identifiers, exact scalar bounds, rollback, unissued-state cleanup, save-path mismatch, identifier change at privilege elevation, delayed-response cookie safety, logout invalidation, cookie attributes, and lock release. Each consuming application adds authentication, expiry, CSRF, authorization, revocation, and concurrent-request tests when those policies apply, recording absent concerns explicitly not applicable. Production deployment evidence additionally covers session-file cleanup under the configured runtime.

Do not add a global session helper, generic key-value repository, request attribute bag, middleware pipeline, custom identifier generator, or application-owned native session call. Those mechanisms create a second lifecycle and hide lock and cookie side effects.
