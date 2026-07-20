# File-transfer security

The accepted path narrows attack surface but is not a complete upload-security system.

- Enforce compatible limits before PHP and again at the application operation.
- Reject multiple, nested, text-field, chunked, malformed, and control-bearing input before storage.
- Ignore client path, filename, extension, and media type for executable decisions.
- Verify PHP upload provenance and actual bytes immediately before the move.
- Generate storage identities with sufficient randomness and keep fixed stored names outside the public tree.
- Give the request process only create, move, read, and delete authority required by the selected root; keep configuration and unrelated filesystem authority unavailable.
- Return fixed response headers with `nosniff`, explicit cache policy, and no reflected filename.
- Authorize upload and download operations independently; possession of a file identifier is not permission.
- Keep rejected metadata, paths, stored bytes, and identifiers out of public errors and terminal summaries.

Applications handling active content, archives, media, personal data, or regulated records must add explicit content inspection, parser isolation, decompression limits, quota, retention, deletion, audit, backup, legal, and incident-response policies. Image processing, antivirus scanning, and content-type inference are not PHPThis features.
