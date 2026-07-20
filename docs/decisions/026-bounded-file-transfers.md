# ADR 026: Bounded explicit file transfers

Status: accepted

## Context

An application needs to accept one uploaded document and return the stored bytes without materializing the complete download in a PHP string. PHP has already parsed `multipart/form-data` into `$_POST` and `$_FILES`; under the normal runtime configuration the raw multipart body is not the canonical application input. Passing the irregular nested `$_FILES` shape to handlers would leave repeated mixed-data parsing and easy multiple-file mistakes throughout the application.

A generic upload helper, automatic binder, storage facade, interchangeable disk interface, framework persistence policy, callback stream, or range engine would hide input shape, I/O, ownership, failure, or memory cost. Client filenames and media types are assertions, not trustworthy storage or response policy. PHP temporary-file cleanup also ends at the request boundary, while durable retention is application and deployment policy.

The first proof requires one bounded upload shape and one concrete local-file response. Remote object stores, user-selected download names, content inspection, image processing, resumable input, and byte ranges are materially different contracts.

## Decision

PHPThis adds a narrow transport normalization and local-file response type. Storage remains entirely application-owned. Consumer Contract version 6 accepts typed multipart uploads on `Request` and an optional `LocalFileBody` on `Response`; Strict Profile version 2 is unchanged.

### Multipart ingestion

The front controller is the only application file that reads `$_POST` and `$_FILES`. It passes those arrays explicitly through the application-owned terminal coordinator and `RequestBoundary` to `RequestReader`, beside `$_SERVER` and `$_GET`.

`RequestReader` retains its ordinary body limit and input URI and accepts a separate optional multipart request limit. A `null` limit disables multipart input. Multipart normalization accepts only:

- normalized method `POST`;
- `multipart/form-data` with exactly one non-empty boundary parameter of at most 70 accepted bytes, using the narrow unquoted token-safe set or the explicit quoted MIME set;
- one canonical non-negative `Content-Length` within the configured multipart request limit;
- no `Transfer-Encoding`;
- no parsed multipart text fields; and
- zero or one PHP-normalized flat top-level file field.

The runtime file entry has only `name`, optional `full_path`, `type`, `tmp_name`, `error`, and `size`, with exact scalar runtime types. Nested and multiple normalized shapes, unknown keys, control bytes, an overlong temporary path, a negative or request-exceeding reported size, contradictory no-file metadata, and incomplete successful metadata fail as `InvalidRequest`. A declared request above the total cap fails as `RequestBodyTooLarge`; configured-disabled multipart fails as `UnsupportedMediaType`. An integer not represented by `RequestUploadError` is an unexpected PHP/runtime condition and remains an operational `RuntimeException`, not a client-controlled mapped error.

PHP constructs `$_FILES` before PHPThis runs. Duplicate raw parts using the same scalar name collapse to one normalized entry and therefore cannot be distinguished or rejected by this boundary. The real-SAPI test records that limitation. Raw duplicate rejection requires compatible upstream enforcement or a separately accepted bounded raw multipart parser.

`Request::$uploads` contains at most one string field mapped to one immutable `RequestUpload`. It exposes only `untrustedClientFilename`, `untrustedClientMediaType`, `temporaryPath`, `reportedSizeBytes`, and the exhaustive `RequestUploadError`. The optional client `full_path` is type- and control-validated, then discarded. Routing preserves the same upload value when `Application` copies a request with `PathParameters`.

The installed example configures a 2,097,152-byte total multipart request limit. Its upload operation accepts exactly the `document` field and applies a separate 1,048,576-byte file limit. Those values are example policy, not universal limits; every adopting application records compatible PHP, web-server, and proxy limits plus its own operation-specific maximum.

### Application storage ownership

The example maps `IniSize` and `FormSize` to the generic 413 path; `Partial` and `NoFile` to the generic 400 path; and `NoTemporaryDirectory`, `CannotWrite`, and `Extension` to an operational failure. It ignores the client filename and media type for content, path, extension, response, and logging decisions. Checked filesystem operations suppress native path-bearing warnings and replace them with named path-free failures.

Before storage, the application checks `is_uploaded_file`, reads the actual temporary-file size, requires it to equal the reported size, and reapplies its 1 MiB limit. One concrete `LocalDocumentFiles` operation requires a non-symlink `0700` storage root, generates a random 128-bit lowercase-hex identifier, creates and verifies a `0700` application-private directory, moves the upload to a fixed `content` name with `move_uploaded_file`, and verifies `0600` file permissions. No request value chooses the storage root, directory, or filename.

PHP owns an uploaded temporary file until a successful move or request termination. After `move_uploaded_file` succeeds, the destination is application-owned. Retention, deletion, backup, quota, malware scanning, authorization, replication, recovery, and cleanup are explicit application and deployment policies; PHPThis adds no automatic lifecycle.

### Local-file responses

`LocalFileBody` is one immutable absolute local path plus an expected non-negative byte count. It is not a stream interface, callback, resource wrapper, storage abstraction, remote object, or authorization token.

A `Response` with `fileBody` must have an empty string body and an exact canonical `Content-Length` equal to the file body's expected bytes. It rejects duplicate case-insensitive header names, control bytes, `Transfer-Encoding`, `Content-Range`, status codes below `200`, and `204`, `205`, `206`, and `304`. Application response copies, including correlation and session finalization, preserve the optional file body.

The example download response is an explicit full `200` with application-owned headers: `Content-Type: application/octet-stream`, fixed `Content-Disposition: attachment; filename="document.bin"`, exact `Content-Length`, `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, and `Accept-Ranges: none`. The client upload filename and media type never influence those headers.

Before emission, `ResponseEmitter` rejects already-sent headers as `ResponseEmissionFailed(true)`. It otherwise opens the path read-only, requires a regular file, and requires the current size to equal the expected bytes before headers. It then emits exactly that many bytes in chunks of at most 8,192 and closes the handle in `finally`. A failure before headers raises `ResponseEmissionFailed(false)`, allowing the visible front controller to attempt one generic 500. A read failure after headers raises `ResponseEmissionFailed(true)`; the front controller does not emit a second response. The exact `Content-Length` lets the client detect an incomplete transfer. The ADR 023 terminal summary still records response selection before emission, not successful file or network delivery.

Range support is explicitly deferred. A request carrying `Range` receives the same complete `200` representation and `Accept-Ranges: none`. File responses reject `206` and `Content-Range`; PHPThis does not parse, normalize, combine, or validate ranges.

### Evidence and limits

Focused boundary tests cover ordinary-body compatibility, configured multipart disablement, canonical and overflowing length, total cap, bounded boundary syntax, POST-only behavior, transfer encoding, text fields, missing, nested and multiple normalized files, exact metadata types and keys, controls and temporary-path bounds, every recognized PHP upload error, unknown runtime error, and request-copy preservation.

Emitter tests cover ordinary response compatibility, exact file framing, header controls and case-insensitive duplicates, unsupported range framing, prior output, missing and changed files before headers, fixed-size chunk completion, and path-free failure messages. A real PHP built-in SAPI plus `curl` proof submits exact-limit, oversized, missing, normalized-multiple, duplicate-scalar, unsafe-filename, and hostile-media uploads, verifies server-generated storage, modes, byte hashes, and redacted unavailable-root failure with displayed errors enabled, downloads directly to files, and proves a `Range` request still returns the complete `200` bytes.

The example does not inject an actual-size race after provenance, a kernel-level `move_uploaded_file` or `chmod` failure, cleanup failure, or a mid-read failure after headers. Those branches remain explicit and path-free in source, but production claims require application- and deployment-specific fault injection. The suite proves prior-output classification and the front controller's no-replacement branch; it does not claim successful delivery.

The fixed 8,192-byte read loop bounds application-level emitter allocation independently of file size, and the real client writes response bytes to a file rather than collecting them in the test process. This is not an end-to-end peak-memory guarantee: PHP output buffering, web-server and reverse-proxy buffering, kernel caching, TLS, and client behavior remain deployment-specific and require production-representative measurement.

The Alpha 2 core-source ceiling increases from 2,300 to 2,500 physical lines only for this typed multipart boundary, concrete local-file response, framing validation, and fixed-chunk emitter. The accepted implementation occupies 2,495 physical core lines and leaves five lines of maintenance margin. That margin authorizes no adjacent file-transfer mechanism or compatibility API.

## Consequences

AI-authored handlers see a finite typed upload value rather than PHP's irregular mixed array, while the dangerous metadata remains visibly named as untrusted. The one application storage path makes provenance checks, move ownership, permissions, and cleanup behavior reviewable. Downloads avoid a framework-sized in-memory response string and carry exact framing.

The narrow contract rejects legitimate multipart use cases such as text-plus-file forms and multiple uploads. Applications use separate requests or seek another accepted contract rather than weakening the one-file shape locally. Remote storage normally requires redirects, proxy offload, or a separately accepted response type; `LocalFileBody` must not be generalized by adding callbacks or arbitrary resources.

PHPThis adds no ORM behavior, automatic or domain binding, implicit or global scopes, observers, facades, helpers, discovery, generic storage interface, disk registry, automatic persistence, automatic cleanup, MIME or filename trust, image processing, resumable upload, virus scanner, quota engine, authorization engine, or range implementation.

This decision supersedes ADR 008 only where that record listed uploads and streaming as future reconsideration work. It does not change ADR 008's single visible request-boundary rationale.

## Reconsider when

At least two independent applications prove one repeated requirement that the current shape cannot represent—such as multiple files, mixed fields, remote-object delivery, resumable uploads, inspected content types, or byte ranges—and can preserve finite typed input, explicit ownership, bounded memory, deterministic framing and failure, deployment evidence, and one canonical path. Reconsider that requirement alone rather than introducing a general storage or streaming abstraction.
