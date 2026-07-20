# Document file-transfer contract

## Accepted operations

| Operation | Request | Success |
| --- | --- | --- |
| Upload one document | `POST /document-files` with `multipart/form-data` and the sole field `document` | `201` JSON containing one generated `file_id`, `Location: /document-files/{file_id}`, and `Cache-Control: private, no-store` |
| Download one document | `GET /document-files/{file_id:token}` | `200` full file response with the fixed headers recorded below |

Both routes are public in this executable example: they perform no authentication, tenant resolution, or authorization check. The generated identifier is routing identity, not proof of permission.

`public/index.php` forwards `$_POST` and `$_FILES` through the terminal coordinator. `ApplicationComposition::http()` configures an 8,192-byte ordinary-body limit and a 2,097,152-byte total multipart limit. Multipart requires `POST`, `Content-Length`, one bounded boundary parameter, no transfer encoding, no text fields, and at most one PHP-normalized flat file value. The application then requires exactly the `document` field and limits its reported and actual content to 1,048,576 bytes. Duplicate raw scalar parts collapse before the boundary and cannot be distinguished; the real-SAPI test records this limit rather than claiming rejection.

The upload operation accepts only the runtime success status. Runtime size-limit outcomes map to `413`; partial, missing, malformed, nested, extra-field, or multiple uploads map to `400`; a non-multipart media type maps to `415`. Temporary-path provenance, runtime temporary-storage failures, size disagreement, move failure, permission failure, and other file unavailability become the generic `500` response. Public failures do not include submitted metadata or server paths.

Client filenames, client paths, and client media types do not choose the retained path, response filename, or response media type. `PendingDocumentUpload` verifies `is_uploaded_file`, checks actual size against the reported size, and passes only the verified temporary path and byte count to the write operation.

## Retained files and downloads

`ApplicationComposition` supplies the resolved SQLite database path plus `.files` to `LocalDocumentFiles`. A successful upload requires a non-symlink root with mode `0700`, creates a 32-character lowercase-hex identifier and verified `0700` directory, moves the runtime upload to the fixed name `content`, and verifies mode `0600`. Checked filesystem failures suppress native path-bearing warnings and become named generic failures. Move failure attempts to remove the reserved identifier directory; permission failure attempts to remove both the moved file and directory. The example has no retention, deletion, or orphan-recovery operation.

Download accepts only the 32-character lowercase-hex identifier. A missing root, identifier directory, or file maps to a private `404`; a symlink, changed private mode, or unreadable size is unavailable and maps to the generic `500`. The successful response is:

- `Content-Type: application/octet-stream`
- `Content-Disposition: attachment; filename="document.bin"`
- exact decimal `Content-Length`
- `Cache-Control: private, no-store`
- `X-Content-Type-Options: nosniff`
- `Accept-Ranges: none`

Range requests deliberately receive the same `200` full representation; partial responses and validators are not implemented. The terminal coordinator preserves the selected local-file body while adding the request ID. The emitter first rejects already-sent headers, then opens the file, verifies it is regular and still has the declared size, and emits 8,192-byte chunks. Open, stat, or size failures are path-free named failures before response start; prior output and later read failures cannot be replaced by a second response. The front controller records only `application.response_emission_failed` and emits its generic `500` response only when response output has not started.

## Evidence

- `tests/upload-request-boundary.php` covers explicit multipart opt-in, normalized flat metadata, rejected boundaries, methods, encodings, fields, nesting, counts, sizes, statuses, and disabled multipart.
- `tests/document-files.php` covers all runtime upload outcomes, the 1 MiB application boundary, provenance, private storage modes, metadata and path redaction with displayed errors enabled, normalized-multiple rejection, raw-scalar-duplicate collapse, fixed storage and download behavior, real PHP-SAPI upload/download bytes, full-response range deferral, and 16 MiB bounded-memory emission. It does not inject an actual-size race, kernel move/chmod or cleanup failure, or a mid-read failure.
- `tests/response-emitter.php` covers exact local-file headers and bytes, framing rejection, prior output, pre-header open and size failures, and path redaction.

Run `composer test` for behavior evidence and `composer check` for the complete repository gate.
