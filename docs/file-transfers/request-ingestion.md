# Multipart request ingestion

The front controller passes `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES` explicitly through the application terminal coordinator and `RequestBoundary`. Handlers never read superglobals.

`RequestReader` has two independent materialization policies:

- ordinary input reads at most the configured body limit plus one byte from the configured URI and verifies any declared length;
- multipart input uses PHP's already parsed arrays, leaves `Request::$body` empty, and enforces the separately configured total multipart limit from canonical `Content-Length`.

A multipart request is accepted only for `POST`, `multipart/form-data` with one non-empty boundary of at most 70 accepted bytes, no `Transfer-Encoding`, no parsed text fields, and zero or one normalized flat file field. Missing upload data reaches the operation as an empty upload map or `NoFile`; the operation decides its required field. Nested arrays, multiple normalized fields, unknown metadata keys, wrong scalar types, controls, malformed no-file state, and a reported size larger than the declared request fail before handler work.

An unquoted boundary is deliberately limited to ASCII letters, digits, apostrophe, plus, underscore, period, and hyphen—the intersection of the accepted MIME boundary characters and an HTTP token. The quoted form accepts the wider bounded MIME character set and forbids a trailing space. Other parameters and duplicate boundaries fail.

PHP constructs `$_FILES` before `RequestReader` runs. Repeated raw parts using the same scalar field name collapse to one normalized entry, so this boundary cannot detect or reject their raw multiplicity. The real-SAPI proof records that limit. If raw duplicate rejection is required, enforce it before PHP normalization or accept a separately bounded raw multipart parser; do not infer proof from `count($_FILES)`.

Reference: [PHP multiple-file upload behavior](https://www.php.net/manual/en/features.file-upload.multiple.php).

A `null` multipart limit deliberately disables this representation. The example configures 2,097,152 total bytes. PHP, the web server, and every proxy must reject larger requests compatibly; an upstream rejection may occur before PHPThis executes.
