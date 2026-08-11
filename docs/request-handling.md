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

`Router` first attempts direct exact-literal lookup. It then follows the deterministic state index for a path containing at most two full-segment placeholders. Consumer Contract version 12 carries forward four fixed types and requires the narrowest one: `positive-int` is canonical ASCII decimal in the range 1 through `PHP_INT_MAX`; `uuid` is lowercase `[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}`; `ulid` is lowercase `[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}`; and `token` is the case-sensitive 1-to-64-byte opaque fallback `[A-Za-z0-9][A-Za-z0-9_-]{0,63}`. The reader's no-decoding rule means `%31` does not become `1`, `%2F` does not create another segment, and an encoded spelling does not become a UUID or ULID. Routing returns bytes unchanged and never trims, case-folds, decodes, or otherwise normalizes them.

The state index is compiled once from explicit `Route` objects. Parameterized declarations whose accepted paths overlap fail at construction rather than relying on registration order, type preference, or backtracking. One compiled state cannot contain differing parameter types or a typed transition beside a parameterized literal transition accepted by that type, even when their later segments differ. A failed `uuid` or `ulid` match never falls back to `token`. Every route sharing a typed transition also shares that transition's parameter name and type regardless of method or later branch. Request-time matching and allowed-method lookup traverse the bounded request path and compiled transitions, not the declared route list or an index collection. Invalid or oversized parameter spellings miss routing before handler or database work; a valid path registered only under another method produces the indexed 405 result.

A successful match is an immutable `RouteMatch`. `Application` creates a new immutable `Request` carrying its immutable `PathParameters` and calls the unchanged `RequestHandler::handle(Request)` interface. A literal route receives empty parameters. Typed routes expose only `positiveInteger(name): int`, `token(name): string`, `uuid(name): string`, and `ulid(name): string`; route-specific code immediately converts each value to a concrete identifier before domain or database work. This metadata is not a generic context or domain-value bag, and it does not prove record existence, authorization, tenant scope, generation policy, or storage representation.

### Dependency-free simple endpoints

Ordinary composition constructs a handler in root `Routes::create()` and passes it into a named route-area list. One narrow locality exception applies when an unprotected exact-literal endpoint fits an existing named route-area manifest, its final handler has no constructor dependency, and it enters no application input, policy, session, cache, configuration, database, request-handler-decorator, external-I/O, or unresolved decision concern. In that exact case, the existing route-area file constructs the dependency-free handler inline in its `Route` declaration, and root `Routes::create()` remains unchanged.

That exception changes only visible construction placement. The route still enters the same explicit finite list, `Router`, `Application`, and `RequestHandler::handle(Request): Response` path. It adds no closure handler, discovery, automatic wiring, alternate router, or second execution pattern. A handler with any constructor dependency and every endpoint needing a new route area or root-composition change remains non-simple and follows ordinary root construction.

For example, an application whose account domain permits only UUID version 7 can make that narrower policy visible immediately after routing:

```php
$accountId = AccountId::fromCanonicalVersionSeven(
    $request->pathParameters->uuid('account_id'),
);
```

That illustrative application-owned factory validates the narrower version-7 rule before authorization or SQL; routing itself accepts the fixed version-1-through-8 UUID syntax. It performs no lookup, model binding, automatic conversion, or persistence cast.

### Application-owned identifier convention

Before adding an identifier class, inspect the nearest application-owned identifier using the same representation and preserve one coherent application convention. When the application has no existing identifier convention, PHPThis recommends a final readonly domain-specific class with a private scalar value and private constructor, one representation-named factory that validates the complete adopted acceptance invariant, an optional `generate()` only when the application owns and records generation, and one explicit representation accessor such as `toCanonicalUuid()`. A UUID factory named for the canonical representation accepts the fixed route grammar's versions 1 through 8 unless the application's recorded domain policy is narrower; a narrower rule uses a correspondingly narrow factory name and validation. This reference convention selects no generator, algorithm, package, exception type, or persistence representation.

Every public construction path preserves the complete adopted acceptance invariant. A boundary factory validates each supplied value independently; an adopted generation path constructs only values that also satisfy its separately recorded generated-version policy. Do not assume routing already established the invariant because the same identifier may enter through a request body, database row, job payload, or another application boundary. Keep semantically distinct domain identifiers as distinct classes even when their implementations resemble one another; do not replace that nominal separation with a generic `Id`, base class, trait, generic identifier interface, framework helper, automatic binding, or record lookup.

When multiple distinct identifiers deliberately adopt the same complete representation invariant, the application may record and compose one narrowly named application-owned representation primitive. That primitive may own only the shared validation and canonical scalar representation; every domain identifier still constructs and returns its own concrete type, and application operations continue to require that concrete type rather than the shared primitive. Generation remains a separate, explicitly versioned application policy owned by a narrowly named generator or a concrete identifier's recorded generation path. Do not extract a representation primitive from method-name resemblance alone, give it an unqualified generic `generate()`, let it choose an unrecorded UUID version or normalization policy, or turn it into a framework identifier, generic domain identifier, binding mechanism, or persistence abstraction. Add direct tests for the primitive and for every concrete identifier's construction boundary.

An application may instead record coherent property storage and visibility, constructor visibility, validated construction mechanism, and factory or accessor naming choices in its `.ai/architecture.md`, with acceptance, generation, and persistence policy in `.ai/data.md`. Those recorded optional choices replace only the corresponding reference choices. Every alternative remains subject to the complete Consumer Contract and Strict Profile, including the final-class requirement, independent validation at external construction boundaries, the complete adopted acceptance and generated-version invariants, and distinct concrete route-specific domain identifiers.

#### UUID acceptance and generation policy

Treat accepted UUID versions and newly generated UUID versions as separate decisions. The reference acceptance policy is the unchanged canonical lowercase RFC-variant route representation for versions 1 through 8. A concrete domain identifier may narrow that set when its recorded business or interoperability contract requires it, but a choice to generate one version does not by itself reject, convert, or normalize other accepted versions already supplied or persisted.

For new database row identifiers, PHPThis recommends UUID version 7 when disclosing its embedded approximate creation time is acceptable. Record the generator's same-timestamp ordering scope, clock-regression behavior, process or node scope, failure behavior, and exact source before relying on more than canonical version and variant bits; finite samples do not prove uniqueness or total creation order. Prefer UUID version 4 when embedded time disclosure from newly generated values is unacceptable or random-only identifiers are the recorded policy. That generation choice does not address metadata disclosure from other accepted or persisted time-bearing UUID versions such as 1, 6, and 7; separately record whether those values may be accepted and exposed. Use UUID version 5 only for a deliberately deterministic identity whose namespace UUID, exact name bytes, canonicalization, and change policy are recorded. Other accepted versions remain valid interoperability or specialized application choices rather than the default generation recommendation.

Record the application decision in `.ai/data.md` before implementation. The following is an illustrative candidate shape, not an adopted application fact; replace every angle-bracketed choice and obtain accountable-human approval before changing the marker to `ADOPTED`:

```text
UUID_POLICY(<ADOPTED only after approval>)
Policy scope and concrete identifiers: <...>
Accepted canonical versions: <1-8 reference or narrower recorded set>
Generated version and purpose: <7 candidate for new database row identifiers, another version, or not applicable>
Generation owner and exact source: <application source path, selected package and version, database facility and engine version, or explicit external owner>
Newly generated value metadata/time-disclosure decision: <...>
Accepted metadata-bearing UUID exposure and handling: <...>
Same-timestamp ordering scope and clock-regression behavior: <...>
Failure behavior and fallback policy: <...>
Narrower domain rules: <...>
Persistence representation and ordering assumptions: <...>
Evidence source: <...>
```

PHPThis supplies no UUID value object, generator, package choice, database function, schema rule, binding, or persistence abstraction.

## Application-owned request-handler decorators

Consumer Contract version 12 carries forward ADR 033's optional route-local composition pattern, introduced under version 9: an **application-owned request-handler decorator**. The final application class implements the existing `RequestHandler`, receives exactly one downstream `RequestHandler`, and names one narrow concern. It may return an explicit early response without entering downstream or call that downstream exactly once with the exact same immutable `Request` instance.

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

## Recommended structured JSON resource success envelope

For a new application operation returning a successful structured JSON resource representation, PHPThis recommends an application-owned `{"data":{...}}` outer shape for one resource and `{"data":[...]}` for a resource collection. An empty collection is `{"data":[]}`, not `null`. Optional operation-owned pagination or other non-resource information belongs in a top-level `meta` object, for example `{"data":[...],"meta":{"next_cursor":"..."}}`.

The operation still owns every field inside `data` and `meta`. Continuation names such as `next_after_user_id` and `next_cursor` deliberately remain distinct, and their grammar, ordering, bounds, filter compatibility, invalidation, snapshot behavior, null-versus-absence policy, and end-of-list semantics do not become a framework pagination contract merely because they share the `meta` location.

A missing resource normally follows that operation's recorded `404` failure mapping rather than returning successful `{"data":null}`. Errors keep their separate explicit status, exact media type, and stable public error representation. HTTP status remains authoritative; do not add a body-level success flag or duplicated status field. The convention does not wrap bodyless `204`, `205`, or `304` responses, explicit `HEAD`, downloads, HTML, plain text, health responses, or other non-resource representations.

For every adopting operation, the handler explicitly sets the status and exact `Content-Type`, encodes its concrete application-owned array with `JSON_THROW_ON_ERROR`, and proves the exact field set, native JSON types, null-versus-absence behavior, scalar and collection bounds, identifier representation, temporal representation, collection ordering, and compatibility policy. A top-level `data` member alone is not JSON:API; adopting JSON:API requires a separate application decision for its media type, resource `type`/`id`/`attributes` model, relationships, errors, links, and compatibility rules.

This greenfield recommendation is advisory. Existing published resource-named or bare success representations remain valid application contracts, and changing one to `data` is a breaking API change requiring an explicit migration or versioning decision. PHPThis adds no runtime wrapper, serializer, resource class, paginator, middleware, facade, helper, reflection, discovery, OpenAPI or JSON Schema artifact, SDK, or client generator.

### Nested child data

Fields inside `data` may include operation-owned nested child objects or collections. Give each relationship an operation-specific role name; this guidance uses `parent.child` only as neutral notation and does not prescribe either response field name. Record each nested field's exact native type and bound, object-versus-`null`-versus-absence behavior, ordering for arrays, whether authorization or tenant scope applies to either role and, where applicable, the exact policy and evidence, and compatibility policy. Return only fields required by the operation. If the parent emits a child relationship identifier beside the nested child's identifier, record and prove their equality invariant.

Load the complete bounded representation before mapping rows to resource arrays and before calling `json_encode`. Mapping, serialization, callbacks, and recursive traversal perform no database, cache, or external-service I/O. The complete operation-owned I/O count stays fixed as the parent page grows: prefer one explicit bounded join for an ordinary to-one relationship and use only a finite fixed-count batch plan when multiple reads are justified. Never query one child per parent. `PHT003` rejects direct lexical database calls inside loops but cannot prove that an indirect mapper, cache client, or integration is I/O-free, so one-parent and maximum-page evidence must compare all relevant operation counters.

Parent pagination and ordering remain authoritative when child data is joined. Join fan-out must not alter the parent limit, stable order, continuation, or duplicate-parent behavior. Give each nested collection its own exact cardinality, ordering, truncation, continuation, and response-size policy. Frontend decoder tests reject malformed nested shapes and incompatible exact field sets without treating JSON object-member order as contractual. Adding or changing a nested relationship follows the published operation's compatibility or versioning policy.

See [Frontend integration](frontend-integration.md#embed-nested-resources-without-n1-io) for the neutral `parent.child` representation and decoder handoff, and [Database design](database.md#n1-safe-nested-resource-plans) for the query-plan requirements. This remains application-owned response construction; PHPThis adds no relationship mechanism, loader, serializer, generic batcher, or expansion API.

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

Failed session update or regeneration cleanup preserves coherent begun-request and earlier pending state so an exact registered original failure can still finalize normally when cleanup succeeds. A cleanup failure is retained with its primary failure through redacted `SessionCleanupFailed`, is not retried, and is excluded from registered response mapping. `finish()` and `abort()` terminally reset the request lifecycle even after cleanup failure.

`Response` carries validated `ResponseCookie` values separately from its ordinary single-value header map. A cookie name is a non-empty HTTP token, and its name plus cookie-safe ASCII value is at most 4,096 bytes. Its explicit `Path` is absolute, at most 1,024 bytes, and contains only visible ASCII except semicolon; path scoping controls delivery and is not an authorization or security boundary. An optional `expiresAt` is from 1 through the smaller of `PHP_INT_MAX` and 253,402,300,799, producing an IMF-fixdate with a four-digit UTC year; the upper bound is 253,402,300,799 on 64-bit PHP. An optional `maximumAgeSeconds` is from 0 through 34,560,000 seconds; zero means immediate deletion. If both lifetime attributes are present, `Max-Age` is authoritative. The application deliberately supplies a coherent `Expires` fallback because this immutable value has no clock from which to infer the relationship, and a user agent may clamp or evict a cookie earlier.

Prefix requirements are checked case-insensitively without changing the emitted case-sensitive cookie name. `__Secure-` requires `Secure`; `__Host-` requires `Secure` and `Path=/`; `__Http-` requires `Secure` and `HttpOnly`; and `__Host-Http-` requires all of those host and HTTP constraints. Canonical prefix casing is recommended. `SameSite=None` also requires `Secure`. Every current `ResponseCookie` is host-only because `Domain` cannot be expressed. `Domain`, `Partitioned`/CHIPS, and `Priority` are unsupported; cross-subdomain or embedded third-party cookie requirements need a separate problem statement and decision.

One response accepts at most 50 cookies and at most 8,192 bytes summed across their exact `headerValue()` strings. It rejects a repeated case-sensitive name regardless of path; names that differ by case remain distinct. The byte total excludes the `Set-Cookie` field names, framing, and ordinary response headers, so the deployed server and proxy still require a compatible whole-header limit. `ResponseEmitter` emits each accepted value as a distinct `Set-Cookie` field and never combines them. Header names remain unique case-insensitively in the ordinary map, their values contain no ASCII control byte or DEL, and application code does not manually encode `Set-Cookie`. The complete native-session and application-policy contract is in [Session state](sessions.md).

An ordinary final response has a status from `200` through `599`, no `Transfer-Encoding`, and one explicit string body. It may omit `Content-Length`; when it supplies one, the canonical decimal value equals the ordinary body's byte length. A `204`, `205`, or `304` has no ordinary body and no `Content-Length`. A `HEAD` route is explicit application code and returns an empty body without inferred representation length. `ResponseEmitter` receives only a `Response`: PHPThis does not use a `GET` fallback, silently suppress output, or add request knowledge to emission. Local-file responses retain their separate stricter contract below.

## Terminal request summary

The application front-controller composition generates one 128-bit lowercase-hex correlation ID before bounded request ingestion and adds it as `X-Request-ID` to the final immutable response. After normal, mapped-failure, or generic unknown-failure response selection and any session finalization, one application-owned coordinator builds the closed bounded ADR 023 event and makes exactly one sink invocation attempt before `ResponseEmitter`.

The event contains no method, path, query data, headers, cookies, body, response body, session data, domain identifiers, SQL, or bindings. Known denials contribute only the generic known-failure outcome and status; an unknown failure contributes only its concrete class. A sink failure is swallowed without retry or fallback and cannot replace or mutate the response. This scope records application response selection, not durable event delivery or successful network emission. See [Terminal request summaries](logging.md) and [ADR 023](decisions/023-application-owned-terminal-request-summaries.md).

## Local-file response emission

An application returns a local file only through `LocalFileBody` on an immutable `Response`. The handler resolves the application-owned absolute path and expected bytes, leaves the ordinary body empty, and sets exact `Content-Length` plus its explicit media, disposition, cache, sniffing, and range policy. Correlation and session response copies preserve the file body.

`ResponseEmitter` rejects already-sent headers, then opens and verifies the regular file and exact size before headers, emits at most 8,192 bytes per read, and closes the handle in `finally`. A pre-header `ResponseEmissionFailed` may receive one generic fallback in the front controller; after output starts, a replacement response is impossible. Range support is deferred: the example returns the complete `200` with `Accept-Ranges: none` even when `Range` is present. The terminal request summary precedes emission and makes no delivery claim. ADR 045's ordinary-response restrictions do not weaken the file body's exact framing checks.

## HTTP cache policy

Framework-generated 404 and 405 responses explicitly emit `Cache-Control: no-store`; the unknown-failure 500 emits `private, no-store`. Every current skeleton and example handler includes the `no-store` directive, and protected example outcomes use `private`. PHPThis does not add or replace headers on an arbitrary application handler response: every additional success, mapped failure, redirect, or other response path remains application-owned and must record and test its exact HTTP cache policy. Server-side data caching is a separate optional application decision and is not implied by these headers.

Redirects, non-local or callback streams, multiple or mixed multipart forms, resumable uploads, trusted proxy interpretation, and generic request-cookie parsing require separate evidence and contracts before they enter the HTTP boundary. ADR 024's one-shot durable-job process is a separate CLI boundary and never enters `RequestBoundary`.
