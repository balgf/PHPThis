# Frontend integration

PHPThis is a server-side HTTP foundation. A browser or other frontend may use React, Vue, Svelte, plain JavaScript, a native mobile stack, another client stack, or no client framework at all; PHPThis does not select or operate that stack. The frontend consumes the application's explicit HTTP and any adopted WebSocket contracts and owns its own source, dependencies, state, rendering, routing, build, deployment, and evidence. Browser-origin policy applies only where a browser enforces it; other clients still follow the recorded operation contract.

PHPThis recommends a separately owned frontend and API, exposed through one same-origin reverse proxy when the product permits it. Separate ownership does not require separate repositories, but it keeps the separately built browser application out of PHP composition and keeps the API contract reviewable. One public origin lets the frontend and API deploy independently without making ordinary browser requests depend on CORS.

This guide adds no framework runtime, Composer dependency, HTTP type, route behavior, CORS behavior, HTML renderer, templating engine, static-file server, OpenAPI or JSON Schema generator, client generator, checker rule, Consumer Contract change, or Strict Profile change.

## Prefer one browser origin

Give frontend pages and API routes disjoint, explicit path ownership behind one reviewed reverse proxy. For example, the frontend may own `/` and its client-side routes while the PHPThis application owns `/api/...`. The proxy or static host, not PHPThis routing, owns frontend document and asset fallback. Never let a single-page-application fallback convert an unknown `/api/...` path into `index.html`; PHPThis's explicit API `404` and `405` behavior must remain observable.

Record the public origin, API prefix, frontend fallback scope, TLS termination, forwarded path and method behavior, cookie scope, static-asset owner, health routes, cache policy, and deployment evidence. Do not infer a trusted proxy identity, client address, scheme, or host from unreviewed forwarded headers. A reverse proxy must preserve the request and response fields required by the adopted operation, including `Content-Type`, authorization or session fields, cache validators, cookies, and `X-Request-ID`.

Same-origin deployment removes the browser's CORS exchange; it does not remove authentication, authorization, CSRF, content-security, cache, or input-validation responsibilities. Those remain explicit application and frontend decisions.

## Record one handoff per operation

Before frontend implementation, record the exact backend handoff for each operation the frontend calls:

- method, literal or typed path, query fields, request headers, request media type, body shape, and every byte, depth, collection, and scalar bound that the frontend must respect;
- credential location, browser credential and cookie mode, authentication, tenant resolution, and authorization position and outcomes, session-cookie behavior where adopted, and CSRF token transport and rotation where required;
- every success status, response media type, exact field set and native JSON types, absent-versus-`null` behavior, enum vocabulary, identifier representation, date and time representation, and compatibility policy;
- every expected HTTP failure status, media type, stable public code or non-JSON body, retry policy, disclosure policy, and whether rejected work has no operation-owned side effect;
- pagination input, stable order, continuation field, end-of-list representation, cursor invalidation rules, and whether traversal is a snapshot;
- cache policy, validators, conditional-read behavior, and write preconditions or concurrency outcomes when adopted;
- upload or download fields, bounds, progress and cancellation expectations, filenames and media types, and full-response or range behavior; and
- the `X-Request-ID` response field and the frontend behavior that makes a non-sensitive value available for support without treating it as authentication or a retry key.

The handoff belongs to the application and frontend context. Do not infer it from framework classes, controller names, a UI component, or a database schema. `Route::segments()` exposes routing metadata from the explicit declaration, but it does not describe request bodies, response schemas, public failures, credentials, caching, or compatibility.

## Recommended structured JSON resource success envelope

For a new application operation that returns a successful structured JSON resource representation, PHPThis recommends one small application-owned convention. Put one resource in a top-level `data` object. Put a resource collection in a top-level `data` array, including `[]` when the collection is empty. Put optional pagination or other operation-owned non-resource information in a top-level `meta` object. For example:

```json
{"data":{"id":42,"name":"Ada"}}
{"data":[{"id":42,"name":"Ada"}],"meta":{"next_after_user_id":"42"}}
{"data":[],"meta":{"next_after_user_id":null}}
```

Those values illustrate only the outer shape. Each operation owns the exact fields inside `data` and `meta`. Pagination continuation names, grammar, ordering, bounds, filter compatibility, invalidation, snapshot behavior, and end-of-list representation remain distinct operation contracts. `next_after_user_id` and `next_cursor` may both live under `meta` without becoming interchangeable. A frontend treats each continuation as opaque, returns it only with the operation, ordering, and filters that produced it, resets it when those inputs change, and never assumes a framework paginator.

A missing resource normally follows the operation's recorded `404` failure contract instead of returning a successful `{"data":null}`. Errors remain separate from this success envelope and retain their explicit status, media type, and stable public error shape. Bodyless `204`, `205`, and `304` responses, explicit `HEAD`, downloads, HTML, plain text, health responses, and other non-resource representations are not automatically wrapped.

Every adopting operation records and proves its exact `Content-Type`, status, field set, native JSON types, null-versus-absence behavior, scalar and collection bounds, identifier representation, temporal representation, collection ordering, and compatibility policy. Frontend fixtures and decoders reject incompatible media types, malformed JSON, unknown or missing fields where the operation forbids them, wrong native types, out-of-bound values, and incompatible envelope changes as decode or contract failures.

A top-level `data` member alone is not JSON:API. Formal JSON:API adoption is a separate application decision covering its media type, resource `type`/`id`/`attributes` model, relationships, errors, links, and compatibility rules. Existing published resource-named or bare responses remain valid application contracts; moving one to `data` is a breaking API change that requires an explicit migration or versioning decision.

This recommendation adds no runtime wrapper, serializer, resource class, paginator, middleware, helper, reflection, discovery, OpenAPI or JSON Schema artifact, SDK, or client generator. The application still constructs the exact response body with `JSON_THROW_ON_ERROR` and sets the exact content type.

## Embed nested resources without N+1 I/O

An operation may embed a child object or collection inside its application-owned `data` representation. Give the relationship an operation-specific role name. This guide uses `parent.child` only as neutral notation; an application does not copy those words when its domain has a more precise name. A conceptual bounded to-one representation can have this shape:

```json
{
  "data": [
    {
      "id": "parent-id",
      "child_id": "child-id",
      "child": {
        "id": "child-id"
      }
    }
  ]
}
```

This is neutral notation, not a framework resource schema or a recommendation to emit fields literally named `child` or `child_id`. The operation decides whether its relationship field is required, is `null`, or is absent when no visible related row exists. If it emits both a child relationship identifier and the nested child's identifier, it records and tests that they are equal. It exposes only fields required by this handoff; a field is not public merely because the data query can select it. The operation records whether tenant scope or authorization applies to either relationship role and, when applicable, keeps those predicates and the policy for a related row the principal may not see explicit.

The complete I/O plan remains fixed and bounded independently of parent-page cardinality. Perform all database, cache, and external-service operations before resource mapping and JSON encoding; those later phases perform no I/O, whether directly, through a callback, or through recursive traversal. For an ordinary to-one `parent.child` relationship, prefer one explicit bounded join. When one statement would be inappropriate, a finite batch plan is valid only when its complete I/O count is fixed; one child lookup per parent is never valid. `PHT003` catches direct lexical database calls inside loops, but it does not prove that an indirectly called mapper, cache client, or integration performs no I/O. Query budgets, operation counters, and one-parent-versus-maximum-page scale evidence close that gap.

The checked application-owned nested-resource fixture uses one bounded left join for a capped collection of at most 50 parents, preserves the selected parent count and order, applies explicit tenant, visibility, and fixed-principal predicates to both relationship roles, emits the optional child as an exact object or `null`, proves identifier equality when present, and preserves the existing published example endpoint contracts. A separate denial control stops before connection creation and executes zero statements. This isolated proof defines no continuation and does not establish request authentication or policy composition. See [Database boundaries](database.md#n1-safe-nested-resource-plans).

Parent pagination remains the controlling contract. A joined child must not change the number or order of emitted parents, continuation value, or duplicate-parent behavior. Each nested collection needs its own exact maximum cardinality, deterministic ordering, truncation behavior, continuation policy, and contribution to the response byte limit; do not let an unbounded child collection hide inside a bounded parent page.

The frontend decoder owns the same exactness. It verifies the nested object's or collection's field set, native JSON types, null-versus-absence policy, bounds, identifier equality when emitted, and deterministic array ordering. It rejects malformed nested shapes but does not make JSON object-member order contractual. Adding a nested relationship, removing it, changing its optionality, or changing its fields can break an exact field-set decoder and therefore follows the operation's recorded compatibility or versioning policy.

Nested representations add no PHPThis relationship loader, ORM, lazy loading, resource class, serializer, generic batcher, expansion syntax, or JSON:API relationship support. The application owns the query, concrete projections, response construction, decoder, and evidence.

## Keep frontend failures distinct

A frontend operation has at least three failure classes:

1. **Transport failure:** no usable HTTP response reached the frontend application. Offline state, name resolution, TLS, a browser CORS block, cancellation, and a frontend-owned timeout belong here. Do not invent an HTTP status or parse an error body that the browser did not expose.
2. **HTTP failure:** a response exists with a status, headers, media type, and body. Preserve those facts, read the response according to its declared media type, and retain an exposed `X-Request-ID` when available. Retrying remains an operation decision; never retry a mutation merely because a request failed visibly.
3. **Decode or contract failure:** the response claims an accepted operation result but its media type, framing, JSON syntax, field set, native types, bounds, or vocabulary does not match the recorded handoff. Treat this as an integration failure, not a domain denial or submitted-value failure.

Do not call a JSON decoder before checking the response status and media type. Application-mapped failures and the generic unknown-failure response use explicit JSON in the current reference paths, while framework-owned route misses and method rejections are `text/plain`. A `204`, `205`, or `304` has no ordinary body, and an explicit `HEAD` route has an empty body under the current safe subset. Frontend code and fixtures must preserve those distinctions rather than converting every non-success into one guessed object.

The default structured-body contract keeps `400 invalid_request` and `422 unprocessable_content` generic and redacted. [Optional application-owned field issues](errors.md#optional-application-owned-field-issues) defines the sole detailed reference profile and its decoder, localization, migration, bounds, and fixture obligations. Adoption is an explicit operation-level compatibility decision; a frontend must not extract internal rules from generic messages or guess between response shapes.

## Treat cross-origin access as a complete policy

Record CORS as not applicable when the browser and API share one origin; separate build, repository, or deployment ownership does not itself make a request cross-origin.

When a product genuinely requires a different browser origin, an accountable human records the complete application or deployment policy before implementation:

- every exact allowed origin and its normalization source, with no reflection of arbitrary request data;
- whether credentials are accepted, the frontend request credential mode, the compatible cookie attributes, the exact allowed methods and request headers, the exact response headers exposed to browser code, and the finite preflight cache lifetime; a credentialed response never uses `*` as its allowed origin;
- `Vary: Origin` and any other cache-key dimensions when the response varies by origin;
- the behavior for absent, malformed, duplicate, opaque, disallowed, and permitted origins;
- the exact response fields on actual requests and preflights, including `Access-Control-Expose-Headers: X-Request-ID` on the actual response when browser code must read the correlation value; and
- evidence for success, mapped failure, routing-owned `404` and `405`, unknown failure, session or credential denial, and every proxy or cache path that may add, remove, or reuse those fields.

Record the exact successful `2xx` preflight status. Put `Access-Control-Allow-Origin` on the preflight and every actual response that the browser is allowed to expose. Put `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` when requested fields are accepted, `Access-Control-Max-Age` when adopted, and `Access-Control-Allow-Credentials: true` when the selected credential mode requires it on the preflight; preserve the applicable origin and credential fields on actual responses and expose only the response headers browser code needs. A PHPThis `204` preflight has an empty body and no `Content-Length`. The ordinary HTTP `Allow` header on a `405` reports route methods; it is not CORS permission.

PHPThis provides no CORS middleware, automatic preflight, origin policy, or response post-processor. An application can declare an explicit `OPTIONS` route, but that route proves only its own request path. A route-local request-handler decorator cannot establish complete CORS behavior because it cannot wrap routing-owned 404 or 405 responses, exact failure mapping, the unknown-failure boundary, the terminal coordinator, or response emission.

A deployment-owned reverse proxy with one exact, tested cross-origin policy may be the smallest complete boundary. That boundary records what happens for its own failures and for bootstrap, composition, fatal, and emission-fallback failures outside the ordinary PHPThis coordinator. Either it attaches the exact adopted CORS fields to those outward responses, or it explicitly classifies them as opaque browser transport or infrastructure failures with no readable status, body, or request ID. Do not imply that PHPThis application code can decorate a response it never receives.

Raw duplicate `Origin` or preflight-request-header handling belongs to the first server or proxy boundary that can observe the raw field multiplicity. PHPThis receives application request headers after SAPI normalization, so an application does not claim duplicate-field proof unless its selected deployment preserves and exposes that fact explicitly.

If the required dynamic policy cannot cover every selected response without changing PHPThis's fixed ownership and ordering, stop and make a separate application or framework decision rather than introducing generic middleware or claiming that success-route headers prove CORS support. Exercise the adopted policy in a real browser; command-line HTTP success alone does not prove browser access.

## Route adjacent concerns to their owners

Load only the guides for concerns the frontend operation actually enters:

| Concern | Read | Frontend/API handoff to record |
| --- | --- | --- |
| Typed JSON, query, header, and failure boundaries | [Type safety](type-safety.md), [Error responses](errors.md), [Request handling](request-handling.md) | exact input and output representations, media types, statuses, bounds, redaction, and decode behavior |
| Stateless Bearer/JWT/opaque-PAT/external-provider authentication, tenant, and authorization | [Application-owned stateless authentication](stateless-authentication.md), [Request policy](request-policy.md), [Security baseline](security.md) | exact header-only credential profile, storage and lifecycle owner, generic denial behavior, current authorization, outage, redaction, and browser evidence |
| Cookie-backed session state and CSRF | [Session state](sessions.md), [Security baseline](security.md) | cookie policy, login and logout, regeneration, expiry, revocation, CSRF token transport, comparison, rotation, protected methods, and failure response |
| HTTP caching and conditional requests | [Cache policy](caching.md) | response cache class, freshness, `Vary`, validator grammar, `304`, invalidation, and any write precondition |
| Dates, times, timezones, and durations | [Date and time](date-time.md) | temporal concept, exact serialized representation, timezone or offset, precision, and boundary cases |
| Cursor traversal and list filters | [Database boundaries](database.md), [CRUD profile](crud.md) | ordering, opaque continuation, filter compatibility, end condition, mutation behavior, and query-cost evidence |
| Uploads and downloads | [File transfers](file-transfers/README.md) | exact fields and limits, metadata trust, response headers, full-body range policy, cancellation, and deployment limits |
| WebSockets | [Application-owned WebSockets](websockets.md) | separate endpoint, origin and credentials, message schema, current authorization, bounds, reconnect, ordering, and delivery semantics |
| Request correlation | [Terminal request summaries](logging.md), [Observability](observability/README.md) | exposed `X-Request-ID`, support display or capture, redaction, and the absence of a client-selected correlation guarantee |

For session authentication, PHPThis supplies bounded native transport rather than a login system. The frontend does not read an `HttpOnly` session cookie, and `SameSite` does not replace a CSRF check. For Bearer authentication, PHPThis is ready only to deliver the bounded lowercase `authorization` header to the application authenticator; it supplies no JWT, PAT, API-token, OAuth, parser, verifier, issuer, revoker, identity-provider, or authentication runtime/API. The frontend and application follow [Application-owned stateless authentication](stateless-authentication.md) and record one TLS-protected header with no alternate credential source, the JWT or opaque-token profile, storage and XSS exposure, issuance, refresh concurrency, expiry, rotation, revocation, outage, and redaction. An `HttpOnly` cookie instead enters the session and CSRF contract. Cross-origin `Authorization` use enters preflight and both the complete CORS policy above and the selected credential policy.

## Keep static assets frontend-owned

The frontend build owns scripts, styles, images, fonts, source maps, chunk names, integrity metadata, and any asset manifest. A static server, CDN, or reverse proxy normally serves its output with explicit content types, sniffing policy, cache lifetime, compression, fallback, and deployment rollback. PHPThis supplies no package manager, bundler, development server, asset discovery, manifest reader, fingerprint helper, or static-file route.

Keep the frontend document fallback separate from API and file-download paths. Without a separately justified application-owned asset operation, a missing asset should not execute a PHPThis application operation, and an API miss should not return frontend HTML. Do not use `LocalFileBody` as an asset pipeline; it is the bounded application-owned local-file response described by the file-transfer guide.

If measured product evidence requires one PHPThis operation to serve application-owned assets, record that exception separately. Keep its finite code-owned paths, media types, size and request bounds, authorization, cache and content-security headers, missing behavior, `LocalFileBody` use, deployment limits, and real HTTP evidence explicit. That bounded operation does not establish a generic asset server, directory walk, fallback, manifest discovery, or framework capability.

If an application-owned HTML response references built assets, use code-owned reviewed paths or one separately bounded and verified build handoff. Do not let request data select a filesystem path, template, script, style, or asset-manifest entry.

## Optional application-owned HTML rendering

An application may return an explicit `text/html; charset=utf-8` string in an ordinary `Response`. That capability is application-owned HTML rendering, not a PHPThis view layer, server-side frontend framework, component model, or template engine.

Keep the handler or one narrowly named application renderer responsible for the exact document and response headers. Pass one final readonly operation-specific view model rather than `mixed`, an associative context bag, or service objects. Complete database, network, and application filesystem reads before rendering. Templates perform no database or network I/O, filesystem discovery, service lookup, environment or session access, mutable global-state access, or dynamic code execution; the renderer may load only its finite code-owned template set through the recorded package behavior.

Encode every untrusted value at its final output sink with the encoder for that precise HTML text, quoted attribute, URL, JavaScript, CSS, or other context; validation and normalization do not replace output encoding. Avoid placing untrusted data in executable script or style contexts, dynamic markup, event-handler attributes, or raw output. Record the response media type, character encoding, renderer failure mapping, output-size and execution bounds, template compilation and cache ownership, development-versus-production behavior, content-security policy, form CSRF, response cache, localization, accessibility, and browser evidence where applicable.

Before adding a template package, record an application decision explaining why explicit string construction no longer suffices and owning the package, version, security and update policy, compilation and cache behavior, deployment inputs, resource bounds, failure contract, and removal cost. Select a mature, maintained package, pin the exact package and version, keep automatic escaping enabled for the selected context, select templates only from a finite code-owned set, keep untrusted template source and dynamic class or function dispatch out, make extensions and globals explicit, and test every deliberate raw-output boundary. The dependency belongs only to that application; it does not become a framework or skeleton dependency, a service container, route discovery mechanism, or second response path.

## Defer machine-readable API description

Machine-readable API description remains a separate future decision. This guide does not decide whether an application or PHPThis would own such an artifact. PHPThis currently supplies no OpenAPI document, JSON Schema catalogue, runtime reflection, route scanner, client generator, or schema-to-handler binding, and this guide does not imply one.

A later issue and decision first settle application-versus-framework ownership, the normative-versus-derived source and drift-check direction, the selected OpenAPI version or other format, the supported JSON Schema subset, unsupported semantics and explicit extensions, included operations and failure paths, security and disclosure boundaries, compatibility and deprecation policy, publication path, tooling and dependency ownership, generated-client review and commit policy, and whether enforcement is advisory or changes consumer validity. Request-time specification validation or specification serving is not implied.

Route metadata or a machine-readable description alone cannot prove validation and request-policy order, source-specific failure classification, authorization, redaction, cache behavior, side-effect exclusion, or resource bounds. Adopt the smallest artifact only after a real frontend or integration needs it, and retain executable backend and frontend evidence for every claim it cannot express. Do not add framework runtime discovery to avoid maintaining an explicit public API decision.

## Frontend-owned evidence

The frontend owns its project instructions and task-routed AI context, pinned runtime and package lock, compiler and linter settings, production build, unit and component checks, browser support, accessibility evidence, dependency review, and deployment rollback. Record its exact build and verification commands beside its source rather than making PHP application context authoritative for another stack. `composer check` verifies the PHPThis application boundary; it does not verify frontend source or browser behavior.

For every adopted operation, keep backend behavior evidence plus frontend-owned finite fixtures or contract tests for successful decoding, every expected HTTP status and media type, empty-body responses, generic and operation-owned errors, bounds, and decode failures. Add real integration evidence against the actual PHP application for route, header, cookie, cache, file, and correlation claims. Add real-browser evidence for navigation, credentials, CSRF, same-origin proxy behavior, downloads, and any browser security policy. Add real process and socket evidence for WebSockets through the separate WebSocket profile.

When cross-origin access is adopted, prove it at an exact local or otherwise non-production browser boundary. Cover preflight and the actual response, permitted and denied origins, credentialed or uncredentialed behavior as selected, mapped and unknown failures, routing-owned `404` and `405`, exposed `X-Request-ID`, and exact cache and `Vary` headers. Do not contact production or claim that a command-line HTTP request proves browser access.

Tests also cover offline or aborted transport, frontend timeout ownership, duplicate submission, pagination reset and end behavior, stale or incompatible cursors, temporal boundary examples, cache revalidation or write preconditions when adopted, SPA fallback exclusion from API paths, and safe display or capture of an exposed `X-Request-ID`. A frontend failure report may retain bounded code-owned classification and a non-sensitive request ID; it does not log credentials, cookies, CSRF material, request or response bodies, personal data, or internal exception detail by default.
