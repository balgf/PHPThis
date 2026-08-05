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

Broad runtime types are never registered. A database projection `UnexpectedValueException`, response-encoding `JsonException`, `PDOException`, `QueryBudgetExceeded`, cardinality `RuntimeException`, or other unknown failure is rethrown unchanged. The application coordinator catches it, calls `UnknownFailureBoundary::respond()` without passing the failure to select `internal_server_error` with status 500, and retains only its concrete class for the same terminal sink attempt used by every selected response.

Database conflicts do not become 409 merely because a driver threw `PDOException`; that would misclassify unrelated constraint, connection, and statement failures. A future conflict mapping needs a named application failure translated at a boundary that can prove the specific condition.

An application adopting ADR 020 defines narrowly named request-policy failures and maps their exact classes. Missing, malformed, and rejected Bearer credentials share one generic `401` response with `WWW-Authenticate: Bearer`. Ordinary forbidden and cross-tenant decisions share one generic `403`. These responses use `Cache-Control: private, no-store` and expose no credential, principal, tenant, resource identifier, or internal policy message. Their one terminal summary carries only the generic `known_failure` outcome and response status. Unexpected policy failures retain the generic `500` path and contribute only their concrete class to that same event.

ADR 023 accepts an application-owned generated correlation ID, injected sink, and bounded terminal request summary. They remain explicit application dependencies rather than hidden global logging; one sink invocation attempt is not a durable-delivery guarantee, and a throwing sink cannot alter the selected response.

ADR 026 uses the same exact registry for file-transfer request failures. Malformed multipart input, partial upload, or missing required file maps to generic 400; total or application file overflow maps to 413; disabled or route-incompatible multipart maps to 415. PHP temporary-storage, extension, provenance, move, permission, unknown upload-code, and unexpected filesystem failures remain generic unknown 500 outcomes. No public response includes client metadata, a temporary or stored path, storage identity, PHP error text, or internal message.

`ResponseEmissionFailed` is different: emission occurs after `RequestBoundary` has returned and the terminal summary has recorded response selection. The front controller catches it directly. When `responseStarted` is false, it may attempt one generic 500 response; when true, it must not append or replace output. The failure message is fixed and carries no path. See [File-transfer failures](file-transfers/failures.md).
