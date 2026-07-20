# File-transfer contract

Use ADR 026 and `docs/file-transfers/README.md` for multipart uploads or local-file responses. Keep transport normalization in core and every storage decision in the application.

## Multipart request path

- Only the front controller reads `$_POST` and `$_FILES`; pass both explicitly through the application terminal coordinator and `RequestBoundary` to `RequestReader`.
- Configure the ordinary body limit, input URI, and a separate positive multipart request limit in the composition root. A `null` multipart limit disables multipart input.
- Accept multipart only for `POST`, one syntactically bounded boundary parameter, one canonical `Content-Length`, no `Transfer-Encoding`, no parsed text fields, and zero or one normalized flat top-level file entry.
- Reject nested or multiple file shapes, unknown file metadata keys, wrong runtime types, control bytes, an unreasonable temporary path, contradictory no-file metadata, and reported file bytes greater than the declared multipart request.
- Do not claim rejection of duplicate raw scalar parts: PHP normalizes them before `RequestReader` and exposes only one scalar entry. A requirement to distinguish them needs an upstream rule or separately accepted raw parser.
- Deliver only `array<string, RequestUpload>` on immutable `Request::$uploads`. Preserve it when routing creates a request copy.
- Treat `RequestUpload::$untrustedClientFilename` and `$untrustedClientMediaType` as display-only hostile data. Validate PHP's optional client `full_path`, then discard it.
- Match `RequestUploadError` exhaustively. An unknown PHP upload error remains an operational failure rather than a client error.

## Application-owned upload policy

- Require one exact field name and an operation-specific file limit before storage. The example uses `document`, a 2 MiB total multipart transport limit, and a 1 MiB file limit.
- Do not infer content from the client media type or filename. If content classification is required, add an application-owned inspected-content decision and evidence.
- Verify `is_uploaded_file`, read the actual size, and require it to equal the reported size before calling the one narrowly named storage operation.
- Generate the storage identifier and destination server-side. Keep the root outside the public tree with explicit directory and file permissions.
- Use `move_uploaded_file` visibly. Before a successful move PHP owns temporary cleanup; after it, the application owns retention, deletion, backup, and incident handling.
- Do not add a generic storage interface, facade, disk registry, binding helper, automatic persistence, or hidden cleanup.

## Local-file response path

- The application resolves one bounded absolute regular-file path and expected byte count into `LocalFileBody`; it does not pass a client filename or storage identifier to the emitter.
- A file `Response` has an empty string body, exact canonical `Content-Length`, no `Transfer-Encoding` or `Content-Range`, and a status supported by the concrete full-response contract.
- Set `Content-Type`, code-owned `Content-Disposition`, cache policy, `X-Content-Type-Options`, and `Accept-Ranges: none` explicitly in the handler.
- `ResponseEmitter` opens and checks the regular file before headers, verifies the expected length, emits fixed 8,192-byte chunks, and closes in `finally`.
- A pre-header `ResponseEmissionFailed(false)` may select one generic fallback at the front controller. After headers, do not attempt a replacement response; the declared length exposes truncation to the client.
- Ignore `Range` and return the complete `200` response. Do not add `206`, `Content-Range`, or range parsing without a new accepted decision.

## Required evidence

Prove the typed runtime shape, every `RequestUploadError`, missing, nested, normalized-multiple, raw-scalar-duplicate limitation, text-field, wrong-media, oversized, unsafe-metadata, provenance, actual-size, move, permission, missing-file, framing, header-control, duplicate-header, pre-header emission failure, complete-byte, full-range-request, and fixed-chunk paths that the application claims. Include a real SAPI request with output written directly to a file and state any uninjectable filesystem/read failures plus PHP, web-server, proxy, buffering, antivirus, quota, retention, authorization, and topology limits that remain unproved.

Do not introduce an ORM, automatic binding, discovery, generic helper, image processing, MIME or filename trust, automatic cleanup, or a range implementation while changing this path.
