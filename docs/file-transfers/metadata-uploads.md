# One S3 document with typed operation metadata

Consumer Contract 25 and [ADR 071](../decisions/071-s3-upload-operation-metadata.md) permit this optional `AMAZON_S3_ADR053` operation: `POST /documents` registers one opaque document with a descriptive label and optional note. The [S3 owner](amazon-s3.md) retains target, versioning, KMS, transport and deployment policy. The original upload/redirect and generated-export operations keep their separate scopes. Contract 26 adds the separate [two-file packet](document-packets.md) to the same optional reference application.

The [executable reference](https://github.com/balgf/PHPThis/tree/main/tools/metadata-s3) is `REFERENCE_ONLY`. Offline tests plus the complete framework `composer check` suffice for this recipe; actual AWS and deployment verification remain required at application adoption. It uses the existing optional SDK lock and adds no core API or framework dependency.

## Input and failure contract

| Part | Accepted input |
| --- | --- |
| `document` | Exactly one normalized PHP upload, 1–65,536 opaque bytes |
| `label` | Required string, 1–120 UTF-8 bytes, excluding ASCII controls and DEL |
| `note` | Optional string, 0–1,024 UTF-8 bytes, excluding ASCII controls and DEL |

Retain exact text without trimming, coercion or Unicode normalization. An absent note is `null`; an explicitly empty note remains `''`. These are flat descriptive strings: JSON depth, nested-object counts and HTML interpretation are inapplicable. Escape text for its destination if an application later displays it. Metadata does not inspect or certify the file.

The one-file operation keeps the envelope `MultipartLimits(131072, 65536, 65536, 1024, 1153)`: request 128 KiB, file/aggregate file 64 KiB, per-field value 1,024 bytes, aggregate field names and values 1,153 bytes. The last bound includes `label` and `note` names and their maximum values. The combined reference reader uses the larger packet envelope; this handler checks its 128 KiB request, 64 KiB aggregate-file and 1,153-byte field aggregate after parsing and before policy I/O. The operation narrows the label to 120 bytes. PHP's native pre-materialization limits remain 32 fields, eight files and 40 parts; the operation accepts at most two normalized fields and one normalized file.

Validate the complete observable field/file set before value rules. Missing, unknown, nested or extra fields/files produce generic 400. Invalid UTF-8, controls, label length and an empty file produce 422. Transport overflow produces 413; incompatible media produces 415. Preserve native upload-error mapping: partial/missing 400, size 413, temporary-directory/write/extension and unknown errors 500. Operational failures remain generic; admission/quota exhaustion produces 503.

PHP collapses scalar duplicates to their final value, including duplicate `document` parts. This operation deliberately processes that normalized value. Name normalization, discarded raw parts and permissive raw framing remain the [native parsing limits](request-ingestion.md); this recipe supplies neither raw uniqueness nor multiple-file partial-success semantics. An adopter needing those properties verifies a separate upstream boundary before adopting this operation.

## Explicit protected operation

1. The canonical reader parses bounded native multipart before dispatch. The action-specific adapter authenticates and authorizes document upload, then passes a typed trusted owner. This tenant-free, non-browser Bearer operation records tenant resolution and CSRF as inapplicable. The checked-in policy denies every request; only the test fixture accepts synthetic credentials.
2. `DocumentInput` and `DocumentMetadata` parse the complete operation into immutable values with private constructors. Invalid or denied input reaches no protected SQL or S3 work.
3. Acquire the precreated nonblocking admission lock. Check real `is_uploaded_file` provenance, regular-file size, exact bytes, SHA-256 and rewind of the same handle before the first protected write. Keep the handle through the one Put and close it in `finally`.
4. Generate a random 128-bit identity and fixed `private-documents/v1/{32-lowerhex}/content` key. One constant SQL insert atomically reserves quota and binds owner, label/note, bytes, digest, target and 24-hour expiry in `pending`.
5. Make one conditional Put with fixed content type, profile metadata, STANDARD and SSE-KMS. Persist the exact returned nonempty/non-`null` version as `stored`, then validate that exact version with Head before `accepted`. Success is 201 containing only the generated identifier with `private, no-store` caching.

Caller fields never select ownership, tenant, bucket, key, destination, encryption, content type, download name, S3 user metadata or inspection status. Keep label/note in private SQLite, absent from S3 metadata, public responses, logs and terminal summaries. The unchanged starter terminal coordinator supplies generated correlation and one redacted sink attempt. No download route is added; later use requires current action-specific authorization and accepted, unexpired exact ownership.

## Capacity, retention and recovery

Use a private single-host SQLite database and one nonblocking mutation lock, precreated by an authorized non-HTTP process. Retain at most 32 live/reserved objects, 2 MiB and 256 total operation identities including tombstones across both routes; each packet reserves two objects and all its bytes. Add limits of eight live/reserved objects and 512 KiB per trusted owner. This reference and the generated-export application have independent stores; a shared deployment needs one coordinated aggregate quota policy.

Ambiguous writes and post-Put database failures retain pending/stored/reconciliation-required ownership, descriptive fields and quota. Never replay Put for the same key. A definitive 412 collision marks only this reservation failed and clears its label/note; never inspect, adopt or delete the conflicting object. Recovery uses one exact-key, at-most-two-entry version listing and one matching Head, with no broad discovery. The pinned SDK represents listing size as a decimal string; compare it against the exact expected byte count without permissive numeric conversion.

At 24 hours, eligibility ends. Physical deletion follows durable `deletion_pending`, exact-version deletion and verified absence. A lost result retains quota and metadata; allow at most one reconciled second deletion, with attempts persisted across processes. Clear label/note only on durable `failed` or `deleted`, preserving the bounded ownership tombstone and never reusing its identity. Missing authoritative rows require incident recovery. Outages may delay physical deletion and metadata clearing; the eligibility deadline is not a guaranteed erasure deadline.

The sole console has `reconcile-one <document-id> <trusted-owner-id>` and `expire-one <document-id> <trusted-owner-id>`. It performs one bounded operation with separately scoped credentials, at most four S3 calls, a 16 KiB control-response sink, 16 SQL statements and no retries or internal loop. The owner argument is supplied by an authorized local operator, never derived from submitted descriptive metadata. Schedule/catch-up policy is inapplicable to this reference. An adopting supervisor bounds overall elapsed work, rate and concurrency.

## Adoption and verification

Record the exact operation, normalized-duplicate suitability, fields, limits, principal policy, private schema, quotas, expiry, metadata retention and failure rules in the application's single `.ai/file-transfers.md`. The reference's adjacent policy records configuration, process authority and operational facts. Deliberately wire the application's verified policy and existing terminal coordinator. Provision or migrate the private schema through its authorized non-HTTP path before enabling the route. Existing objects acquire no fabricated labels or implicit migration; disable the new route before rolling its schema back.

Run `composer test:metadata-s3` and the complete `composer check` to verify the reference. Evidence includes real PHP multipart with synthetic SDK results, exact field boundaries, authority/provenance checks, private metadata binding, database failures, quotas, reconciliation/purge, the deny-all composition, console/configuration checks and actual pinned SDK/cURL control requests to loopback. Tests also preserve the generated recipe's listing-size compatibility. Neither synthetic results nor loopback traffic are AWS observations.

At application adoption, extend the existing `file-transfers:s3:verify` alias with this operation's source, behavior, isolated real-AWS and dated deployment evidence. The original seven-role [verification template](amazon-s3-verification.md) does not cover this extension. Identify resources and obtain approval for a bounded cloud packet before execution. Prove real IAM/KMS separation, version/integrity behavior and cleanup, plus the target SAPI, ingress, private SQLite durability, native temporary storage, rate/cost controls and supervisor behavior. `verify-adoption.php` deliberately fails until those application-specific obligations are supplied. The health-only starter remains a non-adopter.
