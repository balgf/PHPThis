# Error responses

`ErrorResponseRegistry` is an exact-class lookup constructed with immutable public responses in the composition root. It is deliberately not an inheritance matcher, callback chain, or exception-to-array convention.

The example registers only these client failures:

| Exception | Status | Public code |
| --- | ---: | --- |
| `InvalidRequest` | 400 | `invalid_request` |
| `RequestBodyTooLarge` | 413 | `request_body_too_large` |
| `UnsupportedMediaType` | 415 | `unsupported_media_type` |

Their internal exception messages explain the rejected boundary to developers but never enter the response. The registry returns a prebuilt generic JSON value with an explicit content type.

Broad runtime types are never registered. A database projection `UnexpectedValueException`, response-encoding `JsonException`, `PDOException`, `QueryBudgetExceeded`, cardinality `RuntimeException`, or other unknown failure is rethrown unchanged. The front controller passes it to `UnknownFailureBoundary`, which writes one redacted log entry containing only the event and exception class, then returns `internal_server_error` with status 500.

Database conflicts do not become 409 merely because a driver threw `PDOException`; that would misclassify unrelated constraint, connection, and statement failures. A future conflict mapping needs a named application failure translated at a boundary that can prove the specific condition.

An application adopting ADR 020 defines narrowly named request-policy failures and maps their exact classes. Missing, malformed, and rejected Bearer credentials share one generic `401` response with `WWW-Authenticate: Bearer`. Ordinary forbidden and cross-tenant decisions share one generic `403`. These responses use `Cache-Control: private, no-store` and expose no credential, principal, tenant, resource identifier, or internal policy message. Known denials are deliberately not logged; unexpected policy failures remain unknown failures and retain the class-only `500` path.

Request IDs, structured log sinks, and one request-level query-trace summary remain production-evaluation work. They must be added as explicit dependencies rather than hidden global logging.
