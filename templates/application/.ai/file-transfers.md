# Application file-transfer contract

- Adoption or `NOT_APPLICABLE(FILE_TRANSFER)`: {{FILE_TRANSFER_ADOPTION_OR_NOT_APPLICABLE}}
- Accepted upload routes, methods, and media types: {{FILE_UPLOAD_ROUTES_METHODS_AND_MEDIA_TYPES_OR_NOT_APPLICABLE}}
- Upload field names, cardinality, text-field policy, and operation ownership: {{FILE_UPLOAD_FIELDS_CARDINALITY_AND_OWNER_OR_NOT_APPLICABLE}}
- Total request, per-file, field-count, and filename/path byte limits: {{FILE_UPLOAD_LIMITS_OR_NOT_APPLICABLE}}
- Accepted runtime upload statuses and temporary-file provenance checks: {{FILE_UPLOAD_STATUS_AND_PROVENANCE_POLICY_OR_NOT_APPLICABLE}}
- Client filename, path, and media-type treatment: {{FILE_UPLOAD_METADATA_POLICY_OR_NOT_APPLICABLE}}
- Concrete application path, generated identifier, and retained filename: {{FILE_PATH_IDENTIFIER_AND_NAME_OR_NOT_APPLICABLE}}
- Directory and file permissions, collision behavior, and write transition: {{FILE_WRITE_AND_PERMISSION_POLICY_OR_NOT_APPLICABLE}}
- Partial-write cleanup, retention, deletion, and orphan handling: {{FILE_LIFECYCLE_POLICY_OR_NOT_APPLICABLE}}
- Download routes, lookup, and authorization order: {{FILE_DOWNLOAD_LOOKUP_AND_AUTHORIZATION_OR_NOT_APPLICABLE}}
- Success status, media type, disposition, length, cache, and sniffing headers: {{FILE_DOWNLOAD_RESPONSE_POLICY_OR_NOT_APPLICABLE}}
- Full-versus-range behavior and validators: {{FILE_DOWNLOAD_RANGE_AND_VALIDATOR_POLICY_OR_NOT_APPLICABLE}}
- Missing, unavailable, and response-emission failure mapping: {{FILE_TRANSFER_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Redaction and terminal-summary behavior: {{FILE_TRANSFER_REDACTION_POLICY_OR_NOT_APPLICABLE}}
- Boundary, filesystem, real-runtime, exact-byte, and bounded-memory evidence: {{FILE_TRANSFER_EVIDENCE_OR_NOT_APPLICABLE}}

Before adoption, read installed `vendor/phpthis/framework/docs/file-transfers/README.md`. Forward the runtime's parsed form and file values at the front controller, then opt into multipart with one finite total request limit. Record a stricter operation-owned per-file limit when applicable.

Cardinality applies to PHP-normalized file entries. Duplicate raw scalar parts may already have collapsed before this boundary; record that proof limit and any upstream enforcement instead of claiming application rejection.

Treat every client filename, path, and media type as untrusted input. Generate retained identifiers and names on the server, keep file movement and cleanup in the concrete application operation, and define authorization before lookup when disclosure policy requires it. A download owns explicit framing, cache, sniffing, disposition, and range behavior; application response decoration must preserve any selected file body.

Tests must exercise the real runtime upload boundary, exact limits, malformed and multiple inputs, each runtime upload status, provenance failure, cleanup after write failure, metadata and path redaction, missing files, exact downloaded bytes and headers, range behavior, pre-header emission failure, and bounded memory for a file materially larger than the emission chunk size.
