# Typed upload value

`Request::$uploads` is an immutable `array<string, RequestUpload>` containing at most one PHP-normalized value. Route matching preserves it when `Application` copies the request with immutable `PathParameters`; it carries no evidence about duplicate raw scalar parts that PHP collapsed before the boundary.

`RequestUpload` exposes:

- `untrustedClientFilename`: bounded only by the accepted total request and never suitable for a path or response header;
- `untrustedClientMediaType`: a caller assertion, not inspected content;
- `temporaryPath`: request-scoped PHP runtime state that application code verifies with `is_uploaded_file`, never persists, enqueues, logs, traces, or reuses after the request;
- `reportedSizeBytes`: parsed upload metadata, not an actual-size proof; and
- `error`: one exhaustive `RequestUploadError` case.

PHP's optional client `full_path` is validated as a string without control bytes and then discarded. No generic getter exposes raw `$_FILES`, and no upload value moves, stores, scans, names, or deletes a file.

The operation parses the complete upload map once: it requires its exact field, exhaustively maps the error, applies its explicit inclusive minimum and maximum bytes including the zero-byte decision, verifies provenance and actual bytes, then passes the request-scoped path only to the immediate move operation. Durable or deferred work receives only the application-owned destination or bounded reference established after that move.
