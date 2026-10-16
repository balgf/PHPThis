# Typed document metadata with one S3 upload

Optional application reference for [Contract 25 / ADR 071](../../docs/file-transfers/metadata-uploads.md), marked `REFERENCE_ONLY`. Read that operation guide and the [single local policy](.ai/file-transfers.md). It adds no framework runtime feature or dependency.

Run from the framework root:

```sh
composer test:metadata-s3
composer check
```

Use the existing optional locked SDK installation described in [generated S3 setup](../generated-s3/README.md). Both recipes load `tmp/generated-s3-vendor`; there is no second lock or SDK abstraction. The maintained tests use synthetic credentials and responses, real PHP uploads and SQLite, and loopback SDK/cURL traffic. No AWS configuration is needed.

The explicit path is `public/index.php` → `http.php` → canonical reader/router → `UploadDocument` policy and immutable input → `LockedDocumentReceiver` → `Documents` → concrete `DocumentS3`. The unchanged starter observability classes supply the reference coordinator. Adopters wire their existing application-owned coordinator rather than adopting a second one.

The checked composition uses `DenyDocumentUploads`. Its real configuration has no synthetic-policy switch, fault injection, endpoint override or credential fallback. `tests/router.php` is a test fixture, never a deployed entrypoint. `schema.sql.fixture` is provisioning input, never executed by HTTP or Composer. `console.php` is the sole finite maintenance console. `verify-adoption.php` intentionally returns failure until the adopting application supplies actual AWS and deployment verification.
