# Typed S3 documents and two-file packets

Optional application reference for [one document / ADR 071](../../docs/file-transfers/metadata-uploads.md) and [two-file packets / Contract 26 / ADR 072](../../docs/file-transfers/document-packets.md), marked `REFERENCE_ONLY`. Read the selected operation guide and the [single local policy](.ai/file-transfers.md). It adds no framework runtime feature or dependency.

Run from the framework root:

```sh
composer test:metadata-s3
composer check
```

Use the existing optional locked SDK installation described in [generated S3 setup](../generated-s3/README.md). Both recipes load `tmp/generated-s3-vendor`; there is no second lock or SDK abstraction. The maintained tests use synthetic credentials and responses, real PHP uploads and SQLite, and loopback SDK/cURL traffic. No AWS configuration is needed.

The explicit path is `public/index.php` → `http.php` → canonical reader/router. `/documents` uses `UploadDocument` → `LockedDocumentReceiver` → `Documents` → concrete `DocumentS3`; `/document-packets` uses `UploadPacket` → `LockedPacketReceiver` → `Packets` → concrete `PacketS3`. Both authorize and parse immutable input before storage, sharing the private SQLite authority and lock. The unchanged starter observability classes supply the reference coordinator. Adopters wire their existing application-owned coordinator rather than adopting a second one.

Packets bind two required opaque roles to one owner-scoped retry identity, reserve both files, verify both exact versions and expose only the accepted pair. Repeats do not repeat Put. Interrupted writes and cleanup retain capacity until ownership is resolved. The console adds `reconcile-packet <id> <owner>` and `purge-packet <id> <owner>` beside the original single-document commands. Provision both tables and deploy both shared-quota statements together; follow the Contract 26 migration before enabling either writer.

The checked composition uses `DenyDocumentUploads` and `DenyPacketUploads`. Its real configuration has no synthetic-policy switch, fault injection, endpoint override or credential fallback. `tests/router.php` is a test fixture, never a deployed entrypoint. `schema.sql.fixture` is provisioning input, never executed by HTTP or Composer. `console.php` is the sole finite maintenance console. `verify-adoption.php` intentionally returns failure until the adopting application supplies actual AWS and deployment verification.
