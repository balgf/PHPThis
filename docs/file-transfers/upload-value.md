# Typed upload value

`Request::$uploads` is an immutable `array<string, RequestUpload>` containing at most one PHP-normalized value. Route matching preserves it when `Application` copies the request with immutable `PathParameters`; it carries no evidence about duplicate raw scalar parts that PHP collapsed before the boundary.

`RequestUpload` exposes:

- `untrustedClientFilename`: bounded only by the accepted total request and never suitable for a path or response header;
- `untrustedClientMediaType`: a caller assertion, not inspected content;
- `temporaryPath`: request-scoped PHP runtime state that application code verifies with `is_uploaded_file`, never persists, enqueues, logs, traces, or reuses after the request;
- `reportedSizeBytes`: parsed upload metadata, not an actual-size proof; and
- `error`: one exhaustive `RequestUploadError` case.

PHP's optional client `full_path` is validated as a string of at most 4,096 bytes without controls and then discarded. No generic getter exposes raw `$_FILES`, and no upload value moves, stores, scans, names, or deletes a file.

The operation parses the complete upload map once: it requires its exact field, exhaustively maps the error, applies its explicit inclusive minimum and maximum bytes including the zero-byte decision, verifies provenance and actual bytes, then passes the request-scoped path only to the immediate selected storage operation. Durable or deferred work receives only the application-owned destination or bounded reference established by that operation; it never receives the PHP temporary path.

`MultipartForm` is the immutable owner of normalized fields and uploads; `Request::$uploads` projects the same map. Validate all supplied fields and upload outcomes before performing storage. Native `MAX_FILE_SIZE` remains an untrusted client convention that can only lower PHP's file threshold and produce `FormSize`. The exact transport bounds and normalization limits belong to [request ingestion](request-ingestion.md).
