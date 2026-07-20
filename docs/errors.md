# Error responses

`ErrorResponseRegistry` is an exact-class lookup constructed with immutable public responses in the composition root. It is deliberately not an inheritance matcher, callback chain, or exception-to-array convention.

The example registers only these client failures:

| Exception | Status | Public code |
| --- | ---: | --- |
| `InvalidRequest` | 400 | `invalid_request` |
| `RequestBodyTooLarge` | 413 | `request_body_too_large` |
| `UnsupportedMediaType` | 415 | `unsupported_media_type` |

Their internal exception messages explain the rejected boundary to developers but never enter the response. The registry returns a prebuilt generic JSON value with an explicit content type.

ADR 021 keeps operation-input failures on this static disclosure-safe path. Missing fields, explicit `null`, wrong types, unknown fields, malformed Unicode, and other invalid values share the stable `invalid_request` code; endpoint and outer body-limit failures share `request_body_too_large`. The parser uses a deterministic code-owned validation order, but neither the submitted value nor its internal rule message becomes public or enters the terminal request summary. Tests include secret-looking submitted fields and values and prove their absence from bodies, headers, summaries, query traces, and operation calls.

A consumer that needs field-addressable issue codes, localization, or a different status owns that finite response contract and its compatibility policy. It must not change `ErrorResponseRegistry` into a callback renderer, expose exception messages, or add a generic validation-result convention merely to produce details.

Broad runtime types are never registered. A database projection `UnexpectedValueException`, response-encoding `JsonException`, `PDOException`, `QueryBudgetExceeded`, cardinality `RuntimeException`, or other unknown failure is rethrown unchanged. The application coordinator catches it, calls `UnknownFailureBoundary::respond()` without passing the failure to select `internal_server_error` with status 500, and retains only its concrete class for the same terminal sink attempt used by every selected response.

Database conflicts do not become 409 merely because a driver threw `PDOException`; that would misclassify unrelated constraint, connection, and statement failures. A future conflict mapping needs a named application failure translated at a boundary that can prove the specific condition.

An application adopting ADR 020 defines narrowly named request-policy failures and maps their exact classes. Missing, malformed, and rejected Bearer credentials share one generic `401` response with `WWW-Authenticate: Bearer`. Ordinary forbidden and cross-tenant decisions share one generic `403`. These responses use `Cache-Control: private, no-store` and expose no credential, principal, tenant, resource identifier, or internal policy message. Their one terminal summary carries only the generic `known_failure` outcome and response status. Unexpected policy failures retain the generic `500` path and contribute only their concrete class to that same event.

ADR 023 accepts an application-owned generated correlation ID, injected sink, and bounded terminal request summary. They remain explicit application dependencies rather than hidden global logging; one sink invocation attempt is not a durable-delivery guarantee, and a throwing sink cannot alter the selected response.
