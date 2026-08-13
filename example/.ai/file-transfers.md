# Document file-transfer contract

This is the example application's single authoritative file-transfer policy. Other example context and tests reference it; they do not define a second upload, storage, content, request-policy, quota, lifecycle, or emission policy.

## Accepted operations

| Operation | Request | Success |
| --- | --- | --- |
| Upload one document | `POST /document-files` with `multipart/form-data` and the sole field `document` | `201` JSON with a top-level `data` object containing one generated `file_id`, `Location: /document-files/{file_id}`, and `Cache-Control: private, no-store` |
| Download one document | `GET /document-files/{file_id:token}` | `200` full file response with the fixed headers recorded below |

`{file_id}` in the `Location` entry is human-readable response-template shorthand for the generated token value; the exact route declaration is the typed `GET /document-files/{file_id:token}` shown above.

Both routes are public in this executable example: they perform no authentication, tenant resolution, or authorization check. The generated identifier is routing identity, not proof of permission.

## Public non-production limits

`PUBLIC_NON_PRODUCTION(FILE_TRANSFER)`: these routes exist only to prove bounded PHP normalization, concrete local storage, and full local-file emission. They are not a safe production adoption profile. The example has no authentication, tenant isolation, authorization, CSRF control, rate limit, concurrent request bound, principal/tenant/resource quota, aggregate storage quota, retention, deletion, orphan sweeper, backup/restore, legal hold, malware scanner, content parser, quarantine, audit, or incident-response operation. A production consumer must replace every applicable absence in its own authoritative `.ai/file-transfers.md` and executable evidence; it must not copy the public route shape as protected guidance.

`public/index.php` forwards `$_POST` and `$_FILES` through the terminal coordinator. `ApplicationComposition::http()` configures an 8,192-byte ordinary-body limit and a 2,097,152-byte total multipart limit. Multipart requires `POST`, `Content-Length`, one bounded boundary parameter, no transfer encoding, no text fields, and at most one PHP-normalized flat file value. The application then requires exactly the `document` field and accepts reported and actual content from 0 through 1,048,576 bytes inclusive; zero-byte files are deliberately accepted. Duplicate raw scalar parts collapse before the boundary and cannot be distinguished; the real-SAPI test records this limit rather than claiming rejection.

The checked built-in-server fixture pins `file_uploads=1`, `upload_max_filesize=2M`, `post_max_size=3M`, `max_file_uploads=2`, `max_multipart_body_parts=2`, and an isolated `upload_tmp_dir`. The two-part bound deliberately permits the normalized-multiple and duplicate-scalar probes; it bounds total raw parts but does not reject duplicate scalar names. These exact settings are local test-process evidence, not dated effective production proxy or web-SAPI facts. The example explicitly accepts PHP's duplicate-scalar ambiguity for this public proof; an adopter whose operation cannot accept it needs tested upstream rejection or a separately accepted raw parser.

The upload operation accepts only the runtime success status. Runtime size-limit outcomes map to `413`; partial, missing, malformed, nested, extra-field, or multiple uploads map to `400`; a non-multipart media type maps to `415`. Temporary-path provenance, runtime temporary-storage failures, size disagreement, move failure, permission failure, and other file unavailability become the generic `500` response. Public failures do not include submitted metadata or server paths.

Client filenames, client paths, and client media types do not choose the retained path, response filename, or response media type. `PendingDocumentUpload` verifies `is_uploaded_file`, checks actual size against the reported size, and passes only the verified temporary path and byte count to the write operation.

`OPAQUE_BYTES`: the example does not inspect, classify, parse, execute, render inline, or scan uploaded bytes. It does not claim that the bytes match the submitted filename, extension, or media type. The fixed attachment response below is the only serving posture. This is an explicit proof limit, not a recommendation to accept opaque bytes for a real product.

## Retained files and downloads

The real-SAPI fixture gives PHP a separate isolated `0700` temporary-upload directory and mirrors the example beneath `tmp/document-file-tests/real-sapi/server-root`; the copied bootstrap resolves its test-owned database as `server-root/tmp/example.sqlite` and durable root as `server-root/tmp/example.sqlite.files`. It never renames, displaces, or writes the developer's default `tmp/example.sqlite.files`. That local process proves runtime upload provenance and cleanup only for its test-owned paths; it does not prove a production temporary-volume mount, owner/group, non-executable policy, capacity, inode, restart, container, or multi-host behavior.

`ApplicationComposition` supplies the resolved SQLite database path plus `.files` to `LocalDocumentFiles`. Deployment or test setup must pre-create that durable root as a non-symlink directory with mode `0700`; request handling refuses an absent root and never provisions it. A successful upload creates a 32-character lowercase-hex identifier and verified `0700` directory, moves the runtime upload to the fixed name `content`, then uses `lstat` to recheck regular non-symlink type, exact `PendingDocumentUpload::$sizeBytes`, and mode `0600` before returning and publishing the identifier. Checked filesystem failures suppress native path-bearing warnings and become named generic failures. Move failure attempts to remove the reserved identifier directory; failed post-move verification attempts to remove both the moved file and directory. The example has no aggregate capacity accounting, quota reservation, retention, deletion, orphan-recovery, backup, restore, legal-hold, or lifecycle operation.

Download accepts only the 32-character lowercase-hex identifier. A missing root, identifier directory, or file maps to a private `404`; a symlink, changed private mode, or unreadable size is unavailable and maps to the generic `500`. The successful response is:

- `Content-Type: application/octet-stream`
- `Content-Disposition: attachment; filename="document.bin"`
- exact decimal `Content-Length`
- `Cache-Control: private, no-store`
- `X-Content-Type-Options: nosniff`
- `Accept-Ranges: none`

Range requests deliberately receive the same `200` full representation; partial responses and validators are not implemented. The terminal coordinator preserves the selected local-file body while adding the request ID. The emitter first rejects already-sent headers, then opens the file, verifies it is regular and still has the declared size, and emits 8,192-byte chunks. Open, stat, or size failures are path-free named failures before response start; prior output and later read failures cannot be replaced by a second response. The front controller records only `application.response_emission_failed` and emits its generic `500` response only when response output has not started.

The exact emitter guarantee is one pre-header regular-file and size check followed by one sequential read pass that either echoes the declared count or throws. It never intentionally emits more than the declared count, retries, or replaces a response after start. The current path-only profile has a mandatory deployment invariant: from route selection—authorized selection when protection applies—through completed emission, the selected pathname and bytes remain immutable under exclusive-writer authority, with no replacement, mutation, unlink, relink, or symlink substitution. The checked example assumes that invariant for the durable document root and each generated identifier directory, but this public proof does not establish production enforcement. Its permission, non-symlink, regular-file, and size checks narrow the path, but they do not bind an inode or content identity across checks. The emitter evidence deliberately replaces a selected path with a different same-size regular file and with a symlink to a same-size regular target; both are emitted when the invariant is violated. A digest is additional integrity evidence only and cannot replace the mandatory invariant because a pre-emission digest cannot prevent a later same-size swap. An identity-bound body, open-handle body, or different response primitive requires a separately accepted core/response decision; the current example and checks prove none. The example also does not prove an integrity digest at download time, client receipt, network completion, durable delivery, or buffering outside the application loop. Its upload-time test hash compares one fixture copy only and is not retained application integrity metadata.

## Evidence

- `tests/upload-request-boundary.php` covers explicit multipart opt-in, normalized flat metadata, rejected boundaries, methods, encodings, fields, nesting, counts, sizes, statuses, and disabled multipart.
- `tests/document-files.php` covers all runtime upload outcomes, the 1 MiB application boundary, the exact local PHP settings above, provenance, refusal to provision the durable root during a request, private storage modes, post-move regular-file/size/mode verification, metadata and path redaction with displayed errors enabled, normalized-multiple rejection, accepted raw-scalar-duplicate collapse, fixed opaque storage and download behavior, real PHP-SAPI upload/download bytes, full-response range deferral, and 16 MiB bounded-memory emission. It does not inject an actual-size race, kernel move/chmod/cleanup failure, concurrent mutation after `fstat`, or a mid-read failure.
- `tests/response-emitter.php` covers exact local-file headers and bytes, framing rejection, prior output, pre-header open and size failures, and path redaction. It also proves the limit directly: an equal-size regular replacement and a symlink to a same-size regular target are emitted because the emitter checks the opened target's regular-file type and expected size, not path identity or a digest.

The tests deliberately prove no authentication, tenant, authorization, CSRF, quota, scanner/parser, lifecycle, production ingress, proxy, shared-storage, or production buffering behavior. Static installed-context checks can pin this public non-production record and the application template's required markers; they do not turn these omissions into safe defaults.

Run `composer test` for behavior evidence and `composer check` for the complete repository gate.

## Accepted Amazon S3 reference only

REFERENCE_ONLY(AMAZON_S3_FILE_TRANSFER_GUIDANCE)

Accepted ADR 053 and its current pages do not change this example. It remains the public non-production `LOCAL_ADR026` reference with fixed `nosniff` and full local response behavior. It has no AWS dependency, configuration, credential source, S3 client, remote object, pre-signed URL, `file-transfers:s3:verify` gate, or production S3 evidence, and it cannot be cited as Amazon S3 adoption.
