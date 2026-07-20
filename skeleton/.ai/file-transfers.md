# Application file-transfer contract

`NOT_APPLICABLE(FILE_TRANSFER)`: the health-only starter accepts no file upload and returns no file download. It owns no upload field, file identifier, temporary-file transition, retained file, transfer authorization, retention policy, or file-specific response.

`public/index.php` forwards `$_POST` and `$_FILES` through the terminal coordinator so the runtime boundary is explicit. This does not opt the application into file transfers: `bootstrap.php` constructs `RequestReader` without a multipart byte limit, so multipart input remains disabled.

When adopted, cardinality applies to PHP's normalized arrays. Duplicate raw scalar parts may already have collapsed before the application boundary; record that proof limit or enforce the requirement upstream instead of claiming `RequestReader` rejected them.

The front controller already owns one redacted response-emission failure event. It emits a generic no-store `500` only when output has not started; after partial output it records the event and ends without attempting a second response.

Before adoption, read installed `vendor/phpthis/framework/docs/file-transfers/README.md` and replace this marker with the application's concrete decisions for:

- exact route, method, media type, upload field, cardinality, text-field policy, total request limit, and per-file limit;
- every accepted and rejected runtime upload status, temporary-file provenance check, client metadata treatment, and pre-write failure;
- the exact application path, server-generated identifier and filename, directory and file permissions, collision behavior, cleanup, retention, and deletion;
- lookup and authorization order, missing and unavailable behavior, exact download headers, cache policy, full-versus-range behavior, and emission failure handling; and
- automated boundary, filesystem, redaction, real-runtime, exact-byte, and bounded-memory evidence.

Keep client filenames, paths, and media types untrusted. A transfer is not adopted until the complete application gate proves both success and every recorded failure without disclosing submitted metadata or server paths.
