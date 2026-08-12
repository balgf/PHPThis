# Multipart request ingestion

The front controller passes `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES` explicitly through the application terminal coordinator and `RequestBoundary`. Handlers never read superglobals.

`RequestReader` has two independent materialization policies:

- ordinary input reads at most the configured body limit plus one byte from the configured URI and verifies any declared length;
- multipart input uses PHP's already parsed arrays, leaves `Request::$body` empty, and enforces the separately configured total multipart limit from canonical `Content-Length`.

A multipart request is accepted only for `POST`, `multipart/form-data` with one non-empty boundary of at most 70 accepted bytes, no `Transfer-Encoding`, no parsed text fields, and zero or one normalized flat file field. Missing upload data reaches the operation as an empty upload map or `NoFile`; the operation decides its required field. Nested arrays, multiple normalized fields, unknown metadata keys, wrong scalar types, controls, malformed no-file state, and a reported size larger than the declared request fail before handler work.

An unquoted boundary is deliberately limited to ASCII letters, digits, apostrophe, plus, underscore, period, and hyphen—the intersection of the accepted MIME boundary characters and an HTTP token. The quoted form accepts the wider bounded MIME character set and forbids a trailing space. Other parameters and duplicate boundaries fail.

PHP constructs `$_FILES` before `RequestReader` runs. Repeated raw parts using the same scalar field name collapse to one normalized entry, so this boundary cannot detect or reject their raw multiplicity. The real-SAPI proof records that limit. If raw duplicate rejection is required, enforce it before PHP normalization or accept a separately bounded raw multipart parser; do not infer proof from `count($_FILES)`.

The application's adoption record must decide whether that limitation is suitable for each operation. PHP's `max_multipart_body_parts` is still required as a dated effective web-SAPI resource bound over raw form and file parts, but it is not duplicate-name validation. Depending on the effective runtime configuration, PHP can reject or stop parsing before the front controller, and the application may observe only missing or partial normalized state. Prove the selected value through the real deployed SAPI and keep any upstream duplicate-name rule separate and explicit.

Reference: [PHP multiple-file upload behavior](https://www.php.net/manual/en/features.file-upload.multiple.php).

A `null` multipart limit deliberately disables this representation. The example configures 2,097,152 total bytes. PHP, the web server, and every proxy must reject larger requests compatibly; an upstream rejection may occur before PHPThis executes.

Before enabling multipart, record the owner and dated effective value for proxy and web-server request bytes, method and media-type filtering, buffering, timeouts, rate and concurrency limits, plus PHP `file_uploads`, `upload_max_filesize`, `post_max_size`, `max_file_uploads`, `max_multipart_body_parts`, `upload_tmp_dir`, and applicable input or execution limits. Align them with the application transport and operation bounds. Evidence at `RequestReader` proves only requests that reached PHPThis; test upstream and PHP-owned rejections at those boundaries.
