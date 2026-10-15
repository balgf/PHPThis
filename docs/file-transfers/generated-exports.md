# Generated inventory exports in Amazon S3

Consumer Contract 22 and [ADR 068](../decisions/068-generated-s3-inventory-exports.md) extend `AMAZON_S3_ADR053` with a concrete generated inventory export and bounded internal read. Use this alongside the existing [S3 owner](amazon-s3.md) for bucket, KMS, credentials, transport and operational obligations. The original HTTP upload/redirect path keeps its existing verifier and provenance requirements.

The [optional executable reference](https://github.com/balgf/PHPThis/tree/main/tools/generated-s3) implements this application operation. Its current evidence is `REFERENCE_ONLY`: local SQLite lifecycle tests and actual pinned SDK/Guzzle requests to a synthetic loopback server. Real AWS and deployment evidence are outstanding. A successful framework `composer check` does not grant S3 adoption.

## Generate and retain

Select up to 1,001 inventory rows in one constant SQL query, ordered by SKU. Reject more than 1,000. Parse each row once into a typed SKU/quantity value: SKU is 1–32 ASCII uppercase letters, digits, underscore or hyphen, starting with a letter or digit; quantity is an integer from 0 through 2,147,483,647. Emit fixed `sku,quantity` columns and LF newlines. Retain 1–65,536 bytes as `OPAQUE_BYTES`, not inspected or spreadsheet-certified content.

Generate one random 128-bit identity and the existing `private-documents/v1/{32-lowerhex}/content` key. Persist the trusted digest, size, exact bucket/account/region/KMS binding and 24-hour expiry while atomically reserving capacity. Only then issue one `PutObject` with a memory stream, exact length/checksum, fixed metadata/content type, STANDARD, conditional create and SSE-KMS. Require its exact nonempty/non-`null` VersionId and checksum, persist `stored`, then validate the exact version using Head before `accepted`. Never fabricate upload provenance or reuse an identity.

The retained-object check requires matching size, SHA-256, version, KMS key, `aws:kms`, `BucketKeyEnabled: false`, fixed content type and profile metadata. StorageClass, ArchiveStatus, Restore and Expiration must be absent. ContentEncoding, ContentRange and DeleteMarker are also rejected. STANDARD is requested on Put but is absent in a valid Head result; returning the string STANDARD is not equivalent.

## Internal consumption and bounds

An internal reader needs its separately scoped workload authority and an accepted, unexpired authoritative row. Hold the shared application lock through reading and validation. Issue one exact-version Get with expected bucket owner and checksum mode. The pinned PSR-7 `DroppingStream` wraps `php://memory`, capped at the recorded size. Guzzle's explicit cURL handler rejects short sink writes. Disable retries, redirects and decompression. Verify complete byte length and actual SHA-256 before returning the immutable byte value; recheck expiry before handing it off. The console's `verify` command discards those bytes and emits only its finite outcome.

The reference caps control/error response bodies at 16 KiB, data at 64 KiB, PHP allocation at 64 MiB, connects at two seconds and each S3 operation at ten seconds. One command performs one operation: generate uses at most Put + Head; read uses one Get; reconciliation/deletion uses at most four calls. An external supervisor enforces a 60-second process deadline and a separately recorded OS memory bound. No polling, replay, default disk buffering or unclosed stream is part of the recipe.

Use single-host nonblocking exclusive locks for mutations and shared locks for reads. The application precreates private SQLite metadata and the lock, excludes unrelated writers and verifies storage durability. It reserves at most 32 live/ambiguous objects and 2 MiB, and retains at most 256 identities including tombstones. Reaching the history limit stops new generation; production history archival is a separate decision. The 24-hour deadline ends eligibility, while supervised physical deletion may remain unresolved during an outage.

## Failure and reconciliation

| Observation | Outcome |
| --- | --- |
| Proven local failure before send | Failed, reservation released |
| Definitive conditional-create 412 | Failed collision; never inspect, adopt or delete the conflicting object |
| Uncertain Put, contradictory result, interruption or metadata promotion failure | Non-serving pending/stored/reconciliation-required ownership; capacity retained; no repeated Put |
| Empty exact-key listing after uncertain Put | Unresolved; an earlier remote write can still complete |
| One exact current version with fully matching Head | Accepted; expiry still controls consumption |
| Marker, sibling, multiple versions, truncation, changed known version or lost metadata | Incident/restore path; no broad discovery or deletion |
| Expiry | Persist deletion-pending and attempt accounting before exact-version Delete |
| Verified post-delete absence | Persist deleted, then capacity is released; later deletion is a no-op |

Deletion permits one initial attempt and at most one reconciled attempt across process restarts. A reconciled pass observes the exact version, validates it, deletes it and verifies absence. A lost response retains the attempt; a later empty listing can resolve that deletion. Each process has only its named actions. The reconciler cannot perform deletion; the deletion process owns its bounded reconciliation. No transaction spans SQLite and S3.

## Adoption and remaining AWS proof

Record the generated operation in the application's single `.ai/file-transfers.md`. Record CLI-only ingress, browser authentication, tenant and CSRF dimensions as inapplicable when that is actually true. The one configuration reader owns distinct process input names; the sole console owns the finite command map. An HTTP adopter must add the existing explicit authentication/authorization/CSRF sequence before protected work.

Keep source, offline behavior, actual selected-backend behavior and dated deployment facts separate. The original [verification template](amazon-s3-verification.md) covers its seven upload/redirect roles; it does not verify this extension. The generated application owns the additional source and evidence under the same `file-transfers:s3:verify` alias. Missing real-service or deployment evidence fails that gate, even when all offline tests pass.

Before cloud work, identify the non-production account, region, private versioned bucket, KMS key, four least-privilege short-lived identities and exact immutable runtime/CA facts. Approve a frozen packet with at most eight generated objects / 512 KiB, 100 S3 calls including reserved cleanup, bounded KMS activity, exact owned keys/versions and a private cleanup ledger. Prove actual writes/reads, checksums, KMS and authority denials, version mismatch, ambiguous outcomes and exact-version cleanup. Do not provision resources, use an emulator as AWS evidence or discard ambiguous ownership. The executable AWS driver and resource-specific assertions are finalized with that packet; offline preparation authorizes no cloud call.
