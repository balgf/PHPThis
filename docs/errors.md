# Error responses

`ErrorResponseRegistry` is an exact-class lookup constructed with immutable public responses in the composition root. It is deliberately not an inheritance matcher, callback chain, or exception-to-array convention.

The example registers only these client failures:

| Exception | Status | Public code |
| --- | ---: | --- |
| `InvalidRequest` | 400 | `invalid_request` |
| application-owned `UnacceptableCreateUserValues` | 422 | `unprocessable_content` |
| `RequestBodyTooLarge` | 413 | `request_body_too_large` |
| `UnsupportedMediaType` | 415 | `unsupported_media_type` |

Their internal exception messages explain the rejected boundary to developers but never enter the response. The registry returns a prebuilt generic JSON value with an explicit content type.

ADR 042 supersedes only ADR 021's original blanket-`400` default for application-owned structured request-body content. Its parser first validates the complete payload structure and every native type. Malformed JSON or UTF-8, excessive parser depth, a wrong top-level kind, missing or unknown fields, disallowed explicit `null`, wrong native field types, and wrong nested container or item shapes use `InvalidRequest` and the stable generic `400 invalid_request` response. Only after that entire phase succeeds may an operation-owned grammar, range, length, enum, date, canonical-representation, or cross-field failure use an application-owned exact exception such as `UnacceptableCreateUserValues` and the generic `422 unprocessable_content` response:

```json
{"error":{"code":"unprocessable_content","message":"Request content is unacceptable."}}
```

This precedence is deterministic. A payload containing both an unacceptable string and another field of the wrong native type returns `400` regardless of schema or submitted property order. Endpoint and outer body-limit failures remain `413 request_body_too_large`; unsupported media remains `415 unsupported_media_type`. Query-string, header, route, and transport representations retain their separately recorded contracts. Neither body status exposes the submitted field name, value, internal rule, or exception message in a response, terminal summary, log, or trace. Tests include secret-looking submitted fields and values and prove their absence from both paths, plus zero operation calls and downstream work.

A consumer may record and prove a different finite public status contract, and a consumer that needs field-addressable issue codes or localization owns that response contract and its compatibility policy. It must not change `ErrorResponseRegistry` into a callback renderer, expose exception messages, or add a generic validation-result convention merely to produce details. PHPThis adds no core unacceptable-value exception: each adopted application failure remains named and exact, and multiple exact classes may visibly reuse one immutable generic `422` response.

## Optional application-owned field issues

The generic `400` and `422` responses above remain PHPThis's recommended default and require no field details. When a product has a concrete frontend or external-client need for field-addressable value failures, an application may instead adopt this advisory reference profile for one recorded operation. Adoption changes that operation's public `422` contract and therefore requires an explicit compatibility or versioning decision; it is not an automatic framework upgrade.

This documentation-only advisory adds no PHPThis runtime or core API, dependency, Consumer Contract requirement, Strict Profile or `PHT` rule, checker diagnostic, generated code, or mandatory response schema. Existing consumers and operations that retain the generic responses remain valid.

The reference response is an exact JSON object with one `error` member. `error` is an exact object with the required string members `code` and `message` plus the required non-empty array `issues`. Every issue is an exact object containing only the required string members `field` and `code`. No member accepts `null`; no member is optional; unknown members and a per-issue `message` member are absent from this profile.

```json
{
  "error": {
    "code": "validation_failed",
    "message": "One or more fields are invalid.",
    "issues": [
      {
        "field": "field_name",
        "code": "invalid_format"
      }
    ]
  }
}
```

The outer `code` and `message` are exactly `validation_failed` and `One or more fields are invalid.` The `issues` array contains from 1 through 20 entries and at most one entry for each field path. When more value rules fail, the operation emits the first 20 under the ordering below; it does not append an unbounded count or a submitted value. The complete UTF-8 JSON representation is at most 16,384 bytes, including punctuation and escaping, and is encoded with `JSON_THROW_ON_ERROR`. The exact envelope fixes the semantic nesting; a decoder with a configurable depth uses a maximum of 8, while a decoder without that control applies the byte bound before decoding and still rejects every nested unknown or wrongly shaped member. The reference response uses `Content-Type: application/json; charset=utf-8` and `Cache-Control: no-store`; a protected or personalized operation uses `Cache-Control: private, no-store`.

`field` is either the single reserved whole-request path `$` or a code-owned ASCII path under this grammar:

```text
field   := segment ("." segment | "[" index "]")*
segment := [a-z][a-z0-9_]{0,63}
index   := 0 | [1-9][0-9]{0,5}
```

Each segment and list index is one path component. A path has at most eight components and 256 bytes. An index is zero-based, canonical decimal, at most `999999`, derived by the server while walking a structurally valid list, and also remains below that operation's accepted list-count bound. The application declares paths from its known schema; it never copies an unknown submitted key or another client-controlled label into `field`. For example, `parent.child` denotes a nested property and `items[0].field_name` denotes a property in the first accepted list item. These are neutral shapes, not prescribed domain names.

`code` is a 1-to-64-byte lowercase ASCII identifier matching `[a-z][a-z0-9_]{0,63}` and selected from a finite operation-owned allowlist. It describes only the public value failure, not an exception, database condition, validator implementation, or submitted value. When several value rules fail for one path, the operation selects one issue code by a fixed code-owned priority. It orders paths by the parser's fixed schema order, orders list items by ascending index, and places the whole-request `$` issue last. JSON object-member order is not contractual; issue-array order is.

A cross-field rule either assigns one fixed issue to each participating code-owned path or assigns one issue to `$`; the operation records one choice and fixed priority rather than deriving a path from request order. `$` also represents a whole-request value rule that has no single field. Structural failures never use either form. Malformed JSON or a wrong top-level kind fails on the generic `400 invalid_request` path during bounded decoding. After a successful object decode, the parser evaluates the complete fixed field-set, absent-versus-`null`, native-type, and nested-shape phase before any value rule; any failure in that phase returns the same generic `400`. Only after the whole phase succeeds may the operation collect these `422` value issues. Mixed structural and unacceptable-value inputs remain `400` regardless of submitted property order. Whole-body overflow remains `413`; unsupported media remains `415`.

The application keeps the branch literal and operation-specific. This non-runnable structure sketch shows ownership and order without defining a framework helper:

```text
complete bounded shape and native-type phase
  structural failure -> existing generic 400 path
complete value-rule phase in fixed schema order
  one or more issues -> construct this operation's bounded 422 JSON response
success -> construct the final readonly command -> enter operation-owned I/O
```

The issue branch occurs before the operation seam, database, cache, external-service call, or mutation. Separately bounded transport, session, authentication, tenant, or authorization work may already have occurred when the application's recorded request-policy order requires it. Do not put data-dependent issue rendering in `ErrorResponseRegistry`, add a catch-all validation exception, or introduce a validator, error bag, string-rule language, response wrapper, reflection hydrator, middleware, discovery mechanism, or generated consumer code. Another operation may reuse the documented outer vocabulary only through another explicit finite branch and its own path and code allowlists.

Every issue is disclosure-safe by construction: paths and codes come only from finite code-owned tables, list indices come only from accepted bounded structure, and the fixed public message contains no input. Never include submitted values, unknown submitted keys, free-form exception or database text, credentials, tokens, principal or tenant details, resource identifiers, or authorization reasons. Missing resources, persisted-state conflicts, precondition failures, authentication, authorization, routing, rate or operational failures, and unknown failures retain their separately named status contracts rather than becoming field issues. In particular, a login operation keeps malformed structure on the generic `400` path and credential rejection on its generic authentication-denial path; it does not expose credential-field issues that aid probing.

Per-issue human messages are deliberately absent. A frontend that needs localized text owns a finite map keyed by operation and issue `code`, with any field label derived from the same code-owned `field` grammar. If an application instead publishes a per-issue `message`, it defines a different finite contract with an exact string type, presence policy, byte bound, localization owner, and safe code-owned text; `null`, submitted text, and exception messages remain invalid.

The frontend checks status and media type before decoding, accepts the exact object shape and bounds, treats `issues` array order as deterministic, and applies the operation's compatibility policy to an unknown issue code. Rejecting an unknown code as a decode or contract failure is the safe reference behavior; a fallback or ignore policy must be explicit and tested. Moving an already published generic `422` response to this shape, adding or removing a path or code, changing a rule's selected code, or changing issue priority or array order can break clients and requires the application's recorded rollout, versioning, or coordinated-deployment policy. A temporary decoder that accepts both old and new shapes is valid only as an explicit finite migration, never as shape guessing.

Like the other native JSON guidance, this reference decoder does not claim rejection of duplicate object-member names: common decoders retain one occurrence before shape inspection. A client that must reject duplicate members needs a separately selected raw parser and evidence; the application producer still uses `json_encode` and does not emit duplicates.

Application evidence covers one issue, multiple issues, one failure per path, rule priority, schema and list-index ordering, `$` ordering, the 20-issue truncation, path/code/component/index and 16,384-byte limits, nested and list paths, cross-field choice, malformed and mixed structural/value inputs remaining `400`, and zero operation-owned I/O on every rejection. Redaction cases include secret-looking values and unknown keys and prove their absence from bodies, headers, terminal summaries, logs, and traces. Frontend decoder fixtures cover valid single and multiple issues, JSON object members in another order, missing, unknown, `null`, wrongly typed and oversized members, unknown codes, localization lookup, both sides of any migration, and incompatible media or malformed JSON.

Broad runtime types are never registered. A database projection `UnexpectedValueException`, response-encoding `JsonException`, `PDOException`, `QueryBudgetExceeded`, cardinality `RuntimeException`, or other unknown failure is rethrown unchanged. The application coordinator catches it, calls `UnknownFailureBoundary::respond()` without passing the failure to select `internal_server_error` with status 500, and retains only its concrete class for the same terminal sink attempt used by every selected response.

`SessionCleanupFailed` is the narrow framework failure raised only when a session operation already failed and bounded native cleanup also failed. It retains both causes for diagnosis while its public text remains redacted. It is not a retry instruction, log event, or client response, and `RequestBoundary` deliberately excludes it from `ErrorResponseRegistry` mapping. It escapes to the ordinary generic unknown-failure boundary so cleanup failure cannot silently replace a previously selected known response with another registered public response.

Database conflicts do not become 409 merely because a driver threw `PDOException`; that would misclassify unrelated constraint, connection, and statement failures. A future conflict mapping needs a named application failure translated at a boundary that can prove the specific condition.

An application adopting ADR 020 defines narrowly named request-policy failures and maps their exact classes. Missing, malformed, and rejected Bearer credentials share one generic `401` response with `WWW-Authenticate: Bearer`. Ordinary forbidden and cross-tenant decisions share one generic `403`. These responses use `Cache-Control: private, no-store` and expose no credential, principal, tenant, resource identifier, or internal policy message. Their one terminal summary carries only the generic `known_failure` outcome and response status. Unexpected policy failures retain the generic `500` path and contribute only their concrete class to that same event.

ADR 023 accepts an application-owned generated correlation ID, injected sink, and bounded terminal request summary. They remain explicit application dependencies rather than hidden global logging; one sink invocation attempt is not a durable-delivery guarantee, and a throwing sink cannot alter the selected response.

ADR 026 uses the same exact registry for file-transfer request failures. Malformed multipart input, partial upload, or missing required file maps to generic 400; total or application file overflow maps to 413; disabled or route-incompatible multipart maps to 415. PHP temporary-storage, extension, provenance, move, permission, unknown upload-code, and unexpected filesystem failures remain generic unknown 500 outcomes. No public response includes client metadata, a temporary or stored path, storage identity, PHP error text, or internal message.

`ResponseEmissionFailed` is different: emission occurs after `RequestBoundary` has returned and the terminal summary has recorded response selection. The front controller catches it directly. When `responseStarted` is false, it may attempt one generic 500 response; when true, it must not append or replace output. The failure message is fixed and carries no path. See [File-transfer failures](file-transfers/failures.md).
