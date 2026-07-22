# Request handling

PHPThis has one explicit boundary between the PHP runtime and application handlers.

Within each runnable application, `public/index.php` is the sole runtime entrypoint that reads `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES`. It passes all four arrays to the manually constructed application-owned `TerminalRequestCoordinator`, which calls the one `RequestBoundary`. The boundary uses `RequestReader` to read an ordinary configured input URI or normalize bounded parsed multipart input, create one immutable `Request`, optionally begin one session lifecycle, and delegate it to `Application` through `RequestHandler`.

## Normalization contract

`RequestReader` performs these transformations exactly once:

- uppercase an otherwise unmodified alphabetic method;
- remove the query suffix from `REQUEST_URI` without URL-decoding or rewriting the path;
- preserve top-level query values as external `mixed` values under validated string keys;
- translate `HTTP_*`, `CONTENT_TYPE`, and `CONTENT_LENGTH` runtime entries into lowercase header names;
- read at most the configured ordinary body limit plus one byte; or
- for multipart `POST`, require canonical bounded framing and normalize at most one flat PHP file entry without reading `php://input`; this cannot distinguish duplicate raw scalar parts already collapsed by PHP.

It rejects missing or wrongly typed method and URI values, relative or fragmented paths, invalid or conflicting headers, non-canonical content lengths, length mismatches, and excessive metadata. Some SAPIs expose `CONTENT_TYPE` and `CONTENT_LENGTH` again as identical `HTTP_*` entries; the reader collapses those identical normalized duplicates but rejects different values. The fixed profile bounds are 8,192 request-target bytes, 64 top-level query parameters, 64 headers, and 8,192 bytes per header value. The example configures an 8,192-byte outer body limit; `CreateUserCommand` applies its stricter 2,048-byte endpoint limit before JSON decoding.

Header names in `Request` are lowercase HTTP tokens. Handlers use explicit array access such as `$request->headers['content-type'] ?? null`; PHPThis intentionally provides no generic input or header helper.

## Query parameters

The top-level query-parameter count is bounded and names must be strings, but values remain external `mixed` data. An operation that accepts query parameters parses the complete array once through its own concrete boundary before I/O. The example `ListUsersPageRequest::fromQuery` accepts either no parameters or exactly one canonical positive-integer string named `after_user_id`; unknown, nested, coercive, padded, signed, and overflowing values fail before database work.

PHP has already normalized the raw query string into `$_GET` before this boundary receives it. PHPThis therefore does not claim to detect repeated raw query keys whose spellings collapse to one PHP array entry. Supporting that distinction requires a separate raw-query ingestion decision.

## Routing metadata

`Router` first attempts direct exact-literal lookup. It then follows the deterministic state index for a path containing at most two full-segment placeholders. Consumer Contract version 9 carries forward four fixed types and requires the narrowest one: `positive-int` is canonical ASCII decimal in the range 1 through `PHP_INT_MAX`; `uuid` is lowercase `[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}`; `ulid` is lowercase `[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}`; and `token` is the case-sensitive 1-to-64-byte opaque fallback `[A-Za-z0-9][A-Za-z0-9_-]{0,63}`. The reader's no-decoding rule means `%31` does not become `1`, `%2F` does not create another segment, and an encoded spelling does not become a UUID or ULID. Routing returns bytes unchanged and never trims, case-folds, decodes, or otherwise normalizes them.

The state index is compiled once from explicit `Route` objects. Parameterized declarations whose accepted paths overlap fail at construction rather than relying on registration order, type preference, or backtracking. One compiled state cannot contain differing parameter types or a typed transition beside a parameterized literal transition accepted by that type, even when their later segments differ. A failed `uuid` or `ulid` match never falls back to `token`. Every route sharing a typed transition also shares that transition's parameter name and type regardless of method or later branch. Request-time matching and allowed-method lookup traverse the bounded request path and compiled transitions, not the declared route list or an index collection. Invalid or oversized parameter spellings miss routing before handler or database work; a valid path registered only under another method produces the indexed 405 result.

A successful match is an immutable `RouteMatch`. `Application` creates a new immutable `Request` carrying its immutable `PathParameters` and calls the unchanged `RequestHandler::handle(Request)` interface. A literal route receives empty parameters. Typed routes expose only `positiveInteger(name): int`, `token(name): string`, `uuid(name): string`, and `ulid(name): string`; route-specific code immediately converts each value to a concrete identifier before domain or database work. This metadata is not a generic context or domain-value bag, and it does not prove record existence, authorization, tenant scope, generation policy, or storage representation.

For example, an application whose account domain permits only UUID version 7 can make that narrower policy visible immediately after routing:

```php
$accountId = AccountId::fromCanonicalVersionSeven(
    $request->pathParameters->uuid('account_id'),
);
```

That illustrative application-owned factory validates the narrower version-7 rule before authorization or SQL; routing itself accepts the fixed version-1-through-8 UUID syntax. It performs no lookup, model binding, automatic conversion, or persistence cast.

## Application-owned request-handler decorators

Consumer Contract version 9 permits one optional route-local composition pattern: an **application-owned request-handler decorator**. The final application class implements the existing `RequestHandler`, receives exactly one downstream `RequestHandler`, and names one narrow concern. It may return an explicit early response without entering downstream or call that downstream exactly once with the exact same immutable `Request` instance.

The complete outer-to-inner sequence stays visible beside the affected `Route`. This illustrative shape uses ordinary constructors only:

```php
new Route(
    'GET',
    '/documents/{document_key:token}',
    new AddDocumentDownloadSecurityHeaders(
        new RequireDocumentDownloadWindow(
            new DownloadDocumentHandler(/* explicit dependencies */),
        ),
    ),
);
```

Those names are application-specific examples, not PHPThis classes. Do not replace the direct nesting with a middleware array, helper, factory, registry, priority, discovery rule, `$next` callable, or container. Shared leaf dependencies can be constructed elsewhere, but the route declaration retains the complete decorator order and terminal handler.

A decorator never changes the request or catches, translates, suppresses, retries, or replaces an exception. It may return the downstream response unchanged. If it deliberately replaces that immutable response, it passes through every unchanged status, header, body, cookie, and local-file-body field explicitly. Decorator-owned I/O has a visible named dependency and finite resource and failure contract; short-circuit tests prove zero downstream queries, mutation, and external effects.

The pattern wraps only a route handler. It cannot wrap `Application`, `RequestBoundary`, the application terminal request-summary coordinator, or `ResponseEmitter`, and it cannot relocate session finalization, error mapping, correlation, terminal summaries, sink invocation, or emission. It adds no core type or dependency. See [ADR 033](decisions/033-application-owned-request-handler-decorators.md).

## Protected request policy

A protected route may point to one application-owned action-specific adapter that still implements `RequestHandler`. After routing supplies typed path parameters, that adapter explicitly authenticates the request, resolves a concrete tenant context, authorizes the current principal and named action, and only then calls the protected handler with those concrete values. The order is straight-line code and the composition root injects every policy implementation.

This pattern does not alter `RequestBoundary`, `Application`, `Request`, `PathParameters`, or `RequestHandler`. Principal and tenant values do not enter a request attribute bag, session snapshot, global, model binding, middleware pipeline, or service container. Policy reads have named budgets and traces separate from the protected handler, and denial stops before protected queries and writes. See [Request policy](request-policy.md) and [ADR 020](decisions/020-application-owned-request-policy.md).

## Media types

The generic reader does not guess which representation a route accepts. `CreateUserHandler` explicitly requires `application/json`, allowing parameters such as `charset=utf-8`, before it parses the command or performs database work. Missing or incompatible media types cross the boundary as `UnsupportedMediaType`.

## Multipart file input

ADR 026 adds one narrow parsed multipart path. The composition root configures a separate total multipart request limit; `null` leaves multipart disabled. `RequestReader` accepts multipart only for `POST`, one syntactically valid non-empty boundary parameter, canonical `Content-Length`, no `Transfer-Encoding`, no parsed text fields, and zero or one normalized flat top-level file entry. It rejects nested or multiple normalized files, unknown or wrongly typed metadata, controls, an unreasonable temporary path, contradictory no-file metadata, and reported bytes greater than the total request. PHP may already have collapsed repeated raw scalar fields, which this path records as a proof limit rather than a rejection claim.

The immutable request carries at most one `RequestUpload` under its original field name. `untrustedClientFilename` and `untrustedClientMediaType` remain visibly hostile; optional client `full_path` is validated and discarded; `reportedSizeBytes` is not actual-size evidence. Routing preserves the upload while adding `PathParameters`.

The application then parses the complete upload map into its operation-specific value. The example requires exactly `document`, exhaustively maps `RequestUploadError`, applies a 1 MiB file limit inside the 2 MiB transport limit, verifies `is_uploaded_file` and actual size, and calls one concrete local-filesystem storage operation. See [File transfers](file-transfers/README.md).

## Operation input boundaries

After transport and route-specific media checks, an accepting operation parses the complete raw representation once through its own named factory. `CreateUserCommand::fromJson` owns the create-user JSON shape and bounds; `ListUsersPageRequest::fromQuery` owns its query shape. PHPThis does not add a validation helper, string-rule language, automatic binding, mass assignment, sanitizer, or reflection hydrator.

The parser distinguishes missing keys from explicit `null`, rejects unknown fields and non-canonical types, applies deterministic operation-owned bounds, and constructs its final readonly value only after every field succeeds. Downstream behavior uses that value, not the raw `Request`, body, or mixed array. `ListUsersHandler` keeps its small typed post-parse behavior local. Account-scoped Create performs authentication, tenant resolution, and action authorization before parsing, then separates HTTP media/parsing/response work from its independently meaningful transaction through `CreateUserOperation`; `TransactionalCreateUser` owns the direct four-statement user, `account_users` relation, event, and commit-visible job transaction. Actor authority stays in `account_memberships`, and no numeric ID equality maps a principal to a user. Invalid Create input consequently produces zero operation calls and zero Create database work.

For an ADR 020 protected route, its action-specific adapter retains `authenticate -> resolve tenant -> authorize -> protected handler` order. A body command parsed inside that protected handler is therefore validated before protected operation behavior but after any separately bounded policy work. Input rejection prevents the protected operation and its I/O; it does not claim that earlier authentication, tenant, authorization, or policy reads never occurred. Validation never grants access, and the typed command never installs an implicit tenant or database scope.

See [Type safety](type-safety.md) and [ADR 021](decisions/021-application-owned-typed-input-boundaries.md) for canonical scalar, enum, date, list, normalization, and error rules.

## Cookies and optional sessions

Request headers retain the raw `cookie` field as bounded transport input. PHPThis does not add a generic request cookie helper. The optional `SessionLifecycle` alone parses its configured session-cookie name; application handlers do not read `$_COOKIE`, `$_SESSION`, or native session state.

Beginning a configured lifecycle records the header but does not start storage. A handler that never uses sessions remains stateless. Normal and registered-error responses pass through `SessionLifecycle::finish`, which adds a pending validated cookie without leaving a native lock active. An unknown failure triggers `abort` before it escapes; this destroys never-issued state but cannot roll back an earlier commit to a browser-owned identifier. Session mutation is therefore the final small operation after fallible work. Session state is not added to `Request`.

`Response` carries validated `ResponseCookie` values separately from its ordinary single-value header map. Header names are unique case-insensitively and values contain no ASCII control byte or DEL. `ResponseEmitter` emits each cookie as a distinct `Set-Cookie` field. Application code does not manually encode that field. The complete state, cookie, native-runtime, and application-policy contract is in [Session state](sessions.md).

## Terminal request summary

The application front-controller composition generates one 128-bit lowercase-hex correlation ID before bounded request ingestion and adds it as `X-Request-ID` to the final immutable response. After normal, mapped-failure, or generic unknown-failure response selection and any session finalization, one application-owned coordinator builds the closed bounded ADR 023 event and makes exactly one sink invocation attempt before `ResponseEmitter`.

The event contains no method, path, query data, headers, cookies, body, response body, session data, domain identifiers, SQL, or bindings. Known denials contribute only the generic known-failure outcome and status; an unknown failure contributes only its concrete class. A sink failure is swallowed without retry or fallback and cannot replace or mutate the response. This scope records application response selection, not durable event delivery or successful network emission. See [Terminal request summaries](logging.md) and [ADR 023](decisions/023-application-owned-terminal-request-summaries.md).

## Local-file response emission

An application returns a local file only through `LocalFileBody` on an immutable `Response`. The handler resolves the application-owned absolute path and expected bytes, leaves the ordinary body empty, and sets exact `Content-Length` plus its explicit media, disposition, cache, sniffing, and range policy. Correlation and session response copies preserve the file body.

`ResponseEmitter` rejects already-sent headers, then opens and verifies the regular file and exact size before headers, emits at most 8,192 bytes per read, and closes the handle in `finally`. A pre-header `ResponseEmissionFailed` may receive one generic fallback in the front controller; after output starts, a replacement response is impossible. Range support is deferred: the example returns the complete `200` with `Accept-Ranges: none` even when `Range` is present. The terminal request summary precedes emission and makes no delivery claim.

## HTTP cache policy

Framework-generated 404 and 405 responses explicitly emit `Cache-Control: no-store`; the unknown-failure 500 emits `private, no-store`. Every current skeleton and example handler includes the `no-store` directive, and protected example outcomes use `private`. PHPThis does not add or replace headers on an arbitrary application handler response: every additional success, mapped failure, redirect, or other response path remains application-owned and must record and test its exact HTTP cache policy. Server-side data caching is a separate optional application decision and is not implied by these headers.

Redirects, non-local or callback streams, multiple or mixed multipart forms, resumable uploads, trusted proxy interpretation, and generic request-cookie parsing require separate evidence and contracts before they enter the HTTP boundary. ADR 024's one-shot durable-job process is a separate CLI boundary and never enters `RequestBoundary`.
