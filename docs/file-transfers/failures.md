# File-transfer failures

Request-time failures use the existing exact-class boundary:

| Boundary | Public outcome |
| --- | --- |
| malformed method, multipart shape, boundary, fields, files, or metadata | generic 400 `invalid_request` |
| transport or application file limit exceeded | generic 413 `request_body_too_large` |
| multipart disabled or route-specific media type rejected | generic 415 `unsupported_media_type` |
| PHP runtime, provenance, selected storage/permission/service, or unexpected failure | generic 500 `internal_server_error` |
| missing accepted application file/object | application-named generic 404 or the selected profile's recorded unavailable outcome |

Authentication, authorization, tenant, CSRF, rate, quota, content-inspection, quarantine, retention, and lifecycle failures are application policies, not new PHPThis mappings. The authoritative application file-transfer record names each applicable finite outcome and its existing exact failure registration. A protected denial performs no later storage lookup or mutation; a scanner, quota, or cleanup outage does not silently fall back to success.

Every profile records the authoritative internal state, generic public outcome, retry/idempotency consequence, cleanup attempt, reconciliation owner, and redacted signal at each ownership transition. `LOCAL_ADR026` covers move failure, response/emission failure including an ambiguous client retry after a completed move, process crash, disk full, inode exhaustion, permission failure, and partial cleanup. `AMAZON_S3_ADR053` follows the exact finite pre-send, write, ambiguous-result, validation, redirect, reconciliation and exact-version-deletion failure table in [Amazon S3](amazon-s3.md); it never translates those failures into local move/emitter assumptions. A client retry after an unreadable success response is not proof that the first operation failed and must not silently create an unbounded duplicate.

Exact internal messages never become public response bodies, headers, or terminal summaries. Client filenames, media types, full paths, temporary paths, storage roots, and identifiers remain absent from generic failures.

Under `LOCAL_ADR026`, emission occurs after `RequestBoundary` and the terminal-summary attempt. `ResponseEmissionFailed` therefore belongs to the outer front-controller emission boundary, not `ErrorResponseRegistry`. Before headers, one generic fallback is possible. After headers, do not retry, recurse, or append JSON to a partial file. `AMAZON_S3_ADR053` instead owns its application `303` selection and separate S3-response limits; it does not claim local emitter completion.

Operational observability remains application-owned and finite. For `LOCAL_ADR026`, a fixed code-owned emission-failure marker may be appropriate; the exception message and file path are not. The S3 profile uses its fixed redacted outcome and sensitive-URL/key/checksum rules.
