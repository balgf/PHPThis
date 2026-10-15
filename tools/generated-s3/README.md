# Generated S3 inventory reference

This optional application recipe is **REFERENCE_ONLY**. Its offline tests and the complete framework `composer check` are sufficient for reference completion, including issue #109. Real S3 and deployment verification are deferred until application adoption, when they remain required. [Current guidance](../../docs/file-transfers/generated-exports.md) and [the application policy](.ai/file-transfers.md) define its scope.

From the framework checkout:

```sh
composer install --working-dir=tools/generated-s3 --no-interaction --no-progress --prefer-dist
composer test:generated-s3
composer analyse
```

The SDK lock belongs here; dependencies install under ignored `tmp/generated-s3-vendor`. Root analysis loads their types and checks every recipe PHP file at maximum level with the existing profile. No dependency enters the framework or starter package. Checks use PHP 8.4 with cURL, SimpleXML, PDO SQLite and POSIX, Python 3, and loopback sockets. They need no AWS inputs and perform no AWS calls. `schema.sql.fixture` records the separately provisioned private test/application schema; it is never loaded by runtime commands.

The [supply-chain checks](../../SECURITY.md#framework-supply-chain-checks) audit this lockfile alongside the root lockfile and track its updates through Dependabot. Keep dependency updates exact, reviewed and verified by the recipe's offline checks and complete framework gate.

`tests/lifecycle.php` covers exact generation/reads, uncertain Put outcomes, collision refusal, bounded reconciliation, metadata promotion failure, expiry, quota/history bounds and deletion accounting across fresh operations. `tests/contracts.php` checks source/configuration, subprocess redaction, role separation and locking. `tests/transport.py` sends actual SDK/Guzzle requests to a disposable synthetic HTTP server; its receipt is `tmp/generated-s3-transport.json`. HTTP loopback and shorter timeouts are test composition only. Production composition uses regional HTTPS, the reviewed CA and the accepted two/ten-second bounds.

After focused checks, run the complete framework `composer check`. Reference development and CI require no live AWS environment. The separate application-adoption command deliberately fails while adoption evidence is absent:

```sh
composer --working-dir=tools/generated-s3 file-transfers:s3:verify
```

That expected missing-adoption-evidence result does not block reference completion. No receipt from offline tests can satisfy it. The existing reference tests assert this failure. The original upload/redirect checker remains scoped to its own seven source roles.

## Deferred application-adoption packet

When an application adopts this recipe, supply isolated resources and freeze the exact source/lock/image, account/region/bucket/KMS and four short-lived process identities without recording credential values. Retain a private run ledger of exact generated IDs, keys, sizes and observed versions before each remote mutation. Maximum: eight objects / 512 KiB and 100 total S3 calls, including 32 reserved cleanup calls; record the corresponding KMS bound and approved cost. Stop new writes before the cleanup reserve. Provisioning bucket, IAM, KMS, logging or lifecycle is outside this run.

Finalize and review the resource-specific driver before requesting execution approval. It must use the concrete operations here and prove real create/head/get, denial by the wrong owner/key/identity, checksum/version mismatches, uncertain response recovery and exact-version deletion. Record every call, reconcile only ledger-owned keys, preserve ambiguous rows/reservations after interruption and verify empty owned storage before cleanup completion. Never broad-list or delete unknown objects. A separate deployment module must verify all dated bucket, IAM/KMS, TLS/CA/image, SQLite/lock/topology, clock, supervision, monitoring, retention and incident facts. Cloud tests do not substitute for production evidence.
