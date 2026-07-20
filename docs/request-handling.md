# Request handling

PHPThis has one explicit boundary between the PHP runtime and application handlers.

`example/public/index.php` is the only repository file allowed to read `$_SERVER` and `$_GET`. It passes both arrays to the manually constructed application-owned `TerminalRequestCoordinator`, which calls the one `RequestBoundary`. The boundary uses `RequestReader` to read the configured input URI with a hard byte limit, create one immutable `Request`, optionally begin one session lifecycle, and delegate it to `Application` through `RequestHandler`.

## Normalization contract

`RequestReader` performs these transformations exactly once:

- uppercase an otherwise unmodified alphabetic method;
- remove the query suffix from `REQUEST_URI` without URL-decoding or rewriting the path;
- preserve top-level query values as external `mixed` values under validated string keys;
- translate `HTTP_*`, `CONTENT_TYPE`, and `CONTENT_LENGTH` runtime entries into lowercase header names;
- read at most the configured body limit plus one byte.

It rejects missing or wrongly typed method and URI values, relative or fragmented paths, invalid or conflicting headers, non-canonical content lengths, length mismatches, and excessive metadata. Some SAPIs expose `CONTENT_TYPE` and `CONTENT_LENGTH` again as identical `HTTP_*` entries; the reader collapses those identical normalized duplicates but rejects different values. The fixed profile bounds are 8,192 request-target bytes, 64 top-level query parameters, 64 headers, and 8,192 bytes per header value. The example configures an 8,192-byte outer body limit; `CreateUserCommand` applies its stricter 2,048-byte endpoint limit before JSON decoding.

Header names in `Request` are lowercase HTTP tokens. Handlers use explicit array access such as `$request->headers['content-type'] ?? null`; PHPThis intentionally provides no generic input or header helper.

## Query parameters

The top-level query-parameter count is bounded and names must be strings, but values remain external `mixed` data. An operation that accepts query parameters parses the complete array once through its own concrete boundary before I/O. The example `ListUsersPageRequest::fromQuery` accepts either no parameters or exactly one canonical positive-integer string named `after_user_id`; unknown, nested, coercive, padded, signed, and overflowing values fail before database work.

PHP has already normalized the raw query string into `$_GET` before this boundary receives it. PHPThis therefore does not claim to detect repeated raw query keys whose spellings collapse to one PHP array entry. Supporting that distinction requires a separate raw-query ingestion decision.

## Routing metadata

`Router` first attempts direct exact-literal lookup. It then follows the deterministic state index for a path containing at most two full-segment placeholders. A `positive-int` segment must be canonical ASCII decimal in the range 1 through `PHP_INT_MAX`. A `token` segment is a case-sensitive 1-to-64-byte ASCII value matching `[A-Za-z0-9][A-Za-z0-9_-]{0,63}` and is returned unchanged. The reader's no-decoding rule means `%31` does not become `1`, `%2F` does not create another segment, and any percent-encoded token spelling fails the token grammar.

The state index is compiled once from explicit `Route` objects. Parameterized declarations whose accepted paths overlap fail at construction rather than relying on registration order, type preference, or backtracking. One compiled state cannot contain both parameter types or a typed transition beside a parameterized literal transition accepted by that type, even when their later segments differ. Every route sharing a typed transition also shares that transition's parameter name and type regardless of method or later branch. Request-time matching and allowed-method lookup traverse the bounded request path and compiled transitions, not the declared route list or an index collection. Invalid or oversized parameter spellings miss routing; a valid path registered only under another method produces the indexed 405 result.

A successful match is an immutable `RouteMatch`. `Application` creates a new immutable `Request` carrying its immutable `PathParameters` and calls the unchanged `RequestHandler::handle(Request)` interface. A literal route receives empty parameters. Typed routes expose only `positiveInteger(name): int` and `token(name): string`; route-specific code immediately converts each value to a concrete identifier before domain or database work. This metadata is not a generic context or domain-value bag, and it does not prove record existence, authorization, or tenant scope.

## Protected request policy

A protected route may point to one application-owned action-specific adapter that still implements `RequestHandler`. After routing supplies typed path parameters, that adapter explicitly authenticates the request, resolves a concrete tenant context, authorizes the current principal and named action, and only then calls the protected handler with those concrete values. The order is straight-line code and the composition root injects every policy implementation.

This pattern does not alter `RequestBoundary`, `Application`, `Request`, `PathParameters`, or `RequestHandler`. Principal and tenant values do not enter a request attribute bag, session snapshot, global, model binding, middleware pipeline, or service container. Policy reads have named budgets and traces separate from the protected handler, and denial stops before protected queries and writes. See [Request policy](request-policy.md) and [ADR 020](decisions/020-application-owned-request-policy.md).

## Media types

The generic reader does not guess which representation a route accepts. `CreateUserHandler` explicitly requires `application/json`, allowing parameters such as `charset=utf-8`, before it parses the command or performs database work. Missing or incompatible media types cross the boundary as `UnsupportedMediaType`.

## Operation input boundaries

After transport and route-specific media checks, an accepting operation parses the complete raw representation once through its own named factory. `CreateUserCommand::fromJson` owns the create-user JSON shape and bounds; `ListUsersPageRequest::fromQuery` owns its query shape. PHPThis does not add a validation helper, string-rule language, automatic binding, mass assignment, sanitizer, or reflection hydrator.

The parser distinguishes missing keys from explicit `null`, rejects unknown fields and non-canonical types, applies deterministic operation-owned bounds, and constructs its final readonly value only after every field succeeds. Downstream behavior uses that value, not the raw `Request`, body, or mixed array. `ListUsersHandler` keeps its small typed post-parse behavior local. Create separates HTTP media/parsing/response work from its independently meaningful transaction through `CreateUserOperation`; `TransactionalCreateUser` owns the direct two-statement transaction. Invalid Create input consequently produces zero operation calls and zero Create database work.

For an ADR 020 protected route, its action-specific adapter retains `authenticate -> resolve tenant -> authorize -> protected handler` order. A body command parsed inside that protected handler is therefore validated before protected operation behavior but after any separately bounded policy work. Input rejection prevents the protected operation and its I/O; it does not claim that earlier authentication, tenant, authorization, or policy reads never occurred. Validation never grants access, and the typed command never installs an implicit tenant or database scope.

See [Type safety](type-safety.md) and [ADR 021](decisions/021-application-owned-typed-input-boundaries.md) for canonical scalar, enum, date, list, normalization, and error rules.

## Cookies and optional sessions

Request headers retain the raw `cookie` field as bounded transport input. PHPThis does not add a generic request cookie helper. The optional `SessionLifecycle` alone parses its configured session-cookie name; application handlers do not read `$_COOKIE`, `$_SESSION`, or native session state.

Beginning a configured lifecycle records the header but does not start storage. A handler that never uses sessions remains stateless. Normal and registered-error responses pass through `SessionLifecycle::finish`, which adds a pending validated cookie without leaving a native lock active. An unknown failure triggers `abort` before it escapes; this destroys never-issued state but cannot roll back an earlier commit to a browser-owned identifier. Session mutation is therefore the final small operation after fallible work. Session state is not added to `Request`.

`Response` carries validated `ResponseCookie` values separately from its ordinary single-value header map. `ResponseEmitter` emits each cookie as a distinct `Set-Cookie` field. Application code does not manually encode that field. The complete state, cookie, native-runtime, and application-policy contract is in [Session state](sessions.md).

## Terminal request summary

The application front-controller composition generates one 128-bit lowercase-hex correlation ID before bounded request ingestion and adds it as `X-Request-ID` to the final immutable response. After normal, mapped-failure, or generic unknown-failure response selection and any session finalization, one application-owned coordinator builds the closed bounded ADR 023 event and makes exactly one sink invocation attempt before `ResponseEmitter`.

The event contains no method, path, query data, headers, cookies, body, response body, session data, domain identifiers, SQL, or bindings. Known denials contribute only the generic known-failure outcome and status; an unknown failure contributes only its concrete class. A sink failure is swallowed without retry or fallback and cannot replace or mutate the response. This scope records application response selection, not durable event delivery or successful network emission. See [Terminal request summaries](logging.md) and [ADR 023](decisions/023-application-owned-terminal-request-summaries.md).

## HTTP cache policy

Framework-generated 404 and 405 responses and the unknown-failure 500 response explicitly emit `Cache-Control: no-store`. Every current skeleton and example handler includes the `no-store` directive; protected responses additionally use `private`. PHPThis does not add or replace headers on an arbitrary application handler response: every additional success, mapped failure, redirect, or other response path remains application-owned and must record and test its exact HTTP cache policy. Server-side data caching is a separate optional application decision and is not implied by these headers.

Uploads, streaming bodies, trusted proxy interpretation, generic request-cookie parsing, and worker-specific lifecycle behavior require separate evidence and contracts before they enter the request boundary.
