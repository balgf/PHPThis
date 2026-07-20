# File-transfer failures

Request-time failures use the existing exact-class boundary:

| Boundary | Public outcome |
| --- | --- |
| malformed method, multipart shape, boundary, fields, files, or metadata | generic 400 `invalid_request` |
| transport or application file limit exceeded | generic 413 `request_body_too_large` |
| multipart disabled or route-specific media type rejected | generic 415 `unsupported_media_type` |
| PHP runtime, provenance, storage, permission, or unexpected failure | generic 500 `internal_server_error` |
| missing stored application file | application-named generic 404 |

Exact internal messages never become public response bodies, headers, or terminal summaries. Client filenames, media types, full paths, temporary paths, storage roots, and identifiers remain absent from generic failures.

Emission occurs after `RequestBoundary` and the terminal-summary attempt. `ResponseEmissionFailed` therefore belongs to the outer front-controller emission boundary, not `ErrorResponseRegistry`. Before headers, one generic fallback is possible. After headers, do not retry, recurse, or append JSON to a partial file.

Operational observability remains application-owned and finite. A fixed code-owned emission-failure marker may be appropriate; the exception message and file path are not.
