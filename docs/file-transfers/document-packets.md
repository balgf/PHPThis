# Two-file S3 document packets

Consumer Contract 26 / [ADR 072](../decisions/072-s3-document-packets.md) permits one optional `AMAZON_S3_ADR053` operation: `POST /document-packets` accepts two opaque files as one application-visible packet. Both exact versions must pass verification before one database update accepts the packet. Partial private objects can exist; SQLite and S3 do not share an atomic transaction.

The [executable reference](https://github.com/balgf/PHPThis/tree/main/tools/metadata-s3) extends the existing metadata application, front controller and console. It is `REFERENCE_ONLY`. Offline tests and complete `composer check` suffice for the reference; actual AWS and deployment evidence remain required at adoption. Core APIs and framework dependencies are unchanged.

## Bounded input and authority

| Part | Exact normalized input |
| --- | --- |
| `primary`, `supporting` | Two required named PHP uploads; each 1–65,536 opaque bytes |
| `label` | Required 1–120 UTF-8 bytes, excluding ASCII controls and DEL |
| `note` | Optional 0–1,024 UTF-8 bytes with the same exclusions; absent and empty differ |
| `request_id` | Required 32 lowercase hexadecimal characters, retained by the caller for repeats |

Part order is irrelevant. Reject unknown/missing/nested fields and files before value rules; inspect both upload error states before values. Preserve exact text and fixed role assignment. No field selects authority, a destination, encryption or inspection status. These descriptions certify no content safety.

The shared reader uses `MultipartLimits(196608, 65536, 131072, 1024, 1195)`: 192 KiB request, 64 KiB per file, 128 KiB aggregate file and 1,195 aggregate field-name/value bytes. Native 32-field/eight-file/40-part bounds and [PHP normalization limitations](request-ingestion.md) remain. Duplicate raw roles collapse to their final normalized upload; raw uniqueness is not claimed. The older `/documents` handler preserves its smaller request/aggregate ceilings after parsing, before policy I/O.

Use action-specific authentication/authorization on every request, including repeats. The checked composition denies all requests; test policies are synthetic. Tenant and CSRF are inapplicable to this tenant-free, non-browser Bearer reference. After complete typed validation, acquire the private same-host nonblocking lock and verify both real native upload handles, actual size, SHA-256 and rewind before any protected SQL. Close both handles on every exit; never retain PHP temporary paths.

Malformed shape/missing/partial uploads yield 400, invalid values/empty files 422, transport excess 413 and incompatible media 415. Operational failures remain generic 500; lock/quota/history exhaustion yields 503. Protected responses use `private, no-store`; descriptions and integrity/ownership metadata never enter public responses or terminal summaries.

## Identities and visibility

One atomic reservation binds `(trusted owner, request_id)` uniquely to a generated 128-bit packet ID, exact metadata/role-byte fingerprint, both roles' sizes/digests/nonces, target and 24-hour expiry. The fingerprint distinguishes absent/empty note and swapped role bytes. Both roles reserve capacity before either S3 call.

The two keys are fixed `private-documents/v1/packets/{32-lowerhex-packet-id}/primary` and `/supporting`. Never reuse keys or owner/retry bindings. Each conditional Put carries exactly code-owned metadata `profile=private-document-packet-v1`, `operation={packet-id}`, `role=primary|supporting` and a separate random 128-bit `ownership` nonce persisted before sending. Label, note, owner and caller retry ID remain in private SQLite. Retained-object checks compare every marker and the [S3 profile's](amazon-s3.md) exact version, bytes, digest, KMS and storage facts. Header-map ordering is immaterial.

For each role, explicitly: persist `write_started`, attempt one conditional Put, persist its exact version as `stored`, Head that version, then persist `verified`. A failed claim prevents the Put. SDK retries are disabled. Accept only when both roles are verified and the packet remains unexpired. No individual role is eligible independently; later application use requires current authorization and returns both exact versions together. No download route is added.

| Request outcome | Response |
| --- | --- |
| New packet accepted | 201 with generated ID and `status: accepted` |
| Exact same owner/submission already accepted and unexpired | 200 with the same ID/status; no new reservation or S3 call |
| Exact submission pending/reconciling/aborting and unexpired | 202 with the same ID and `status: pending`; no per-file success |
| Same owner/retry ID with different metadata or role bytes | Generic 409; no S3 work |
| Exact submission expired or terminal | Generic 410; no resurrection |

A repeat supplies the complete multipart submission and rechecks native provenance. A lost first response may be retried with the same request ID; it never resends a Put. A new request ID denotes a new semantic submission and leaves earlier uncertain capacity reserved. Different owners have separate retry namespaces.

## Recovery, capacity and deletion

Packet states are `pending`, `reconciliation_required`, `accepted`, `aborting`, `deletion_pending`, `failed`, `deleted`. Only unexpired `accepted` is eligible. Each role separately records `ready`, `write_started`, `stored`, `verified`, `collision`, `deletion_pending` or `deleted` and durable deletion attempts.

`ready` proves no remote call was authorized. Once the local writer ends, any unattempted/colliding partner makes an incomplete packet irreversibly `aborting`; the missing role is never filled by a later request. If both roles were attempted, read-only reconciliation may accept both verified versions before expiry. An expired accepted packet becomes `deletion_pending`; an expired incomplete packet becomes `aborting`.

`reconcile-packet <packet-id> <trusted-owner-id>` uses the existing separately scoped reconciliation identity and lock. Inspect only uncertain attempted roles with exact-key, at-most-two-entry ListObjectVersions and exact-version Head. Persist each verified result independently. Empty observations after uncertain Put retain quota because a late completion remains possible. Foreign/missing ownership markers, changed versions, sibling/multiple versions, delete markers, truncation, malformed responses and provider failures prevent acceptance and keep the object untouched. Known collisions are never inspected or deleted. If a collision result could not be committed, recovery treats it as uncertain and must still verify its private nonce; matching bytes alone cannot establish ownership.

`purge-packet <packet-id> <trusted-owner-id>` uses the deletion identity. Run `reconcile-packet` first for uncertain attempted roles; purge leaves them unresolved until read-only ownership reconciliation succeeds. Clean aborting packets immediately and accepted packets only after expiry. Make serving impossible before deletion. Delete recorded exact versions only, with at most two persisted attempts per role across processes and the existing bounded recheck/absence proof. A failed attempt claim prevents Delete. Partial deletion or uncertain database accounting retains the **whole packet's reservation**. Finish `failed` or `deleted` and clear label/note only when every role is proved never sent, definitively foreign or exactly deleted. Keep bounded ownership/retry tombstones and fingerprints; authoritative row loss requires incident recovery, never broad listing or inferred ownership.

Both routes share 32 live/reserved objects and 2 MiB globally, eight objects and 512 KiB per owner, and 256 operation identities including tombstones. A packet counts as two objects and its full combined bytes. Both reservation statements account for both tables. Uncertainty and partial cleanup retain the complete reservation. Eligibility ends at 24 hours; outages may retain physical bytes and private metadata longer under incident ownership. No automatic history pruning or identity recycling exists.

Packet HTTP/reconciliation permit four S3 calls and a 60-second supervisor wall bound; cleanup permits eight calls and 90 seconds. Retain 2-second connect/10-second operation timeouts, 16 KiB control buffers, 64 MiB memory, 32 SQL statements/trace fingerprints and one active mutation. There is no retry loop or scheduler; adoption supplies rate/cost supervision and verifies actual runtime bounds.

## Adoption and evidence

Follow [Contract 26 migration](../consumer-contract-upgrades.md#contract-version-26). Record this operation in the single application `.ai/file-transfers.md`, including normalized-duplicate suitability, authority, fields, ownership metadata, quotas, retention and recovery. Drain both mutation routes before provisioning the packet table and deploying shared quota checks. Existing documents remain single-file records. Disable/drain the packet route and commands before rollback; preserve unresolved rows and shared quota enforcement until all packet capacity is resolved. Never restore a writer that ignores outstanding packet reservations.

`composer test:metadata-s3` verifies native multipart/provenance, repeated and concurrent attempts, exact role identity, shared quota, write/statement/deferred-commit failures, interrupted recovery, process termination, exact cleanup and redaction. Twelve actual SDK/cURL requests to loopback cover Put/Head/List/Delete; synthetic service behavior is not AWS evidence. Complete `composer check` additionally proves strict analysis, installed guidance and regressions. The original profile verifier does not automatically cover packets: extend `file-transfers:s3:verify` with this exact operation's source/behavior, isolated real-AWS IAM/KMS/version/metadata/cleanup tests and dated target ingress/SAPI, durability, supervision and cost evidence at adoption. The reference adoption gate remains failing.
