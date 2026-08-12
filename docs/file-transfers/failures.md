# File-transfer failures

Request-time failures use the existing exact-class boundary:

| Boundary | Public outcome |
| --- | --- |
| malformed method, multipart shape, boundary, fields, files, or metadata | generic 400 `invalid_request` |
| transport or application file limit exceeded | generic 413 `request_body_too_large` |
| multipart disabled or route-specific media type rejected | generic 415 `unsupported_media_type` |
| PHP runtime, provenance, storage, permission, or unexpected failure | generic 500 `internal_server_error` |
| missing stored application file | application-named generic 404 |

Authentication, authorization, tenant, CSRF, rate, quota, content-inspection, quarantine, retention, and lifecycle failures are application policies, not new PHPThis mappings. The authoritative application file-transfer record names each applicable finite outcome and its existing exact failure registration. A protected denial performs no later storage lookup or mutation; a scanner, quota, or cleanup outage does not silently fall back to success.

Record the authoritative internal state, generic public outcome, retry/idempotency consequence, cleanup attempt, reconciliation owner, and redacted signal for move failure, response or emission failure including an ambiguous client retry after a completed move, process crash at each ownership transition, disk full, inode exhaustion, permission failure, and partial cleanup. A client retry after an unreadable success response is not proof that the first operation failed and must not silently create an unbounded duplicate.

Exact internal messages never become public response bodies, headers, or terminal summaries. Client filenames, media types, full paths, temporary paths, storage roots, and identifiers remain absent from generic failures.

Emission occurs after `RequestBoundary` and the terminal-summary attempt. `ResponseEmissionFailed` therefore belongs to the outer front-controller emission boundary, not `ErrorResponseRegistry`. Before headers, one generic fallback is possible. After headers, do not retry, recurse, or append JSON to a partial file.

Operational observability remains application-owned and finite. A fixed code-owned emission-failure marker may be appropriate; the exception message and file path are not.
