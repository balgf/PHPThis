# Generated S3 inventory reference

This optional application recipe is **REFERENCE_ONLY**. Offline implementation is approved; AWS execution and production adoption are pending. [Current guidance](../../docs/file-transfers/generated-exports.md) and [the application policy](.ai/file-transfers.md) define its scope. Issue #109 stays open.

From the framework checkout:

```sh
composer install --working-dir=tools/generated-s3 --no-interaction --no-progress --prefer-dist
composer test:generated-s3
composer analyse
```

The SDK lock belongs here; dependencies install under ignored `tmp/generated-s3-vendor`. Root analysis loads their types and checks every recipe PHP file at maximum level with the existing profile. No dependency enters the framework or starter package. Checks use PHP 8.4 with cURL, SimpleXML, PDO SQLite and POSIX, Python 3, and loopback sockets. They need no AWS inputs and perform no AWS calls. `schema.sql.fixture` records the separately provisioned private test/application schema; it is never loaded by runtime commands.

`tests/lifecycle.php` covers exact generation/reads, uncertain Put outcomes, collision refusal, bounded reconciliation, metadata promotion failure, expiry, quota/history bounds and deletion accounting across fresh operations. `tests/contracts.php` checks source/configuration, subprocess redaction, role separation and locking. `tests/transport.py` sends actual SDK/Guzzle requests to a disposable synthetic HTTP server; its receipt is `tmp/generated-s3-transport.json`. HTTP loopback and shorter timeouts are test composition only. Production composition uses regional HTTPS, the reviewed CA and the accepted two/ten-second bounds.

The application adoption command deliberately fails:

```sh
composer --working-dir=tools/generated-s3 file-transfers:s3:verify
```

That is a real missing-evidence result, not a skip. No receipt from offline tests can satisfy it. The original upload/redirect checker remains scoped to its own seven source roles.

## Remaining real-service packet

Once isolated resources exist, freeze the exact source/lock/image, account/region/bucket/KMS and four short-lived process identities without recording credential values. Retain a private run ledger of exact generated IDs, keys, sizes and observed versions before each remote mutation. Maximum: eight objects / 512 KiB and 100 total S3 calls, including 32 reserved cleanup calls; record the corresponding KMS bound and approved cost. Stop new writes before the cleanup reserve. Provisioning bucket, IAM, KMS, logging or lifecycle is outside this run.

Finalize and review the resource-specific driver before requesting execution approval. It must use the concrete operations here and prove real create/head/get, denial by the wrong owner/key/identity, checksum/version mismatches, uncertain response recovery and exact-version deletion. Record every call, reconcile only ledger-owned keys, preserve ambiguous rows/reservations after interruption and verify empty owned storage before cleanup completion. Never broad-list or delete unknown objects. A separate deployment module must verify all dated bucket, IAM/KMS, TLS/CA/image, SQLite/lock/topology, clock, supervision, monitoring, retention and incident facts. Cloud tests do not substitute for production evidence.
