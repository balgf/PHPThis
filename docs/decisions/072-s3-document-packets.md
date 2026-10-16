# ADR 072: Two-file S3 document packets

Status: accepted

## Decision

For issue #139, the maintainer approved Consumer Contract 26 and the concrete [two-file document packet](../file-transfers/document-packets.md) on 2026-10-16. Both fixed opaque roles must pass verification before the packet becomes application-visible. SQLite ownership and S3 writes remain separate effects with explicit partial failure and recovery.

Bind owner-scoped caller retry IDs to a generated packet ID and exact submission fingerprint. Repeated submissions observe the same operation without repeating Put. Reserve both objects before either write and persist each role's write claim before sending. Use separate code-owned role keys and private random ownership markers; matching bytes alone cannot justify recovering an unknown version. Never adopt/delete a known collision or replay an uncertain Put.

Keep both uploads and maintenance in the existing optional metadata application, with shared quotas and its single console. Preserve ambiguity accounting, no partial visibility, 24-hour eligibility, exact-version cleanup and at most two durable deletion attempts per role. Unresolved physical deletion can outlast eligibility. Drain both writers during schema/shared-quota migration and retain unresolved ownership on rollback.

## Consequences and evidence

This permits only the explicit application-owned operation. No generic storage transaction layer, backend interface, resumable upload, automatic migration, core API, framework dependency, diagnostic or Strict Profile change is introduced. Original upload/redirect and generated-export scopes remain separate; the health-only starter remains a non-adopter.

Offline reference tests plus complete `composer check` suffice for reference completion under the accepted #109 policy. Evidence includes real PHP uploads/SQLite, failure injection, a deferred commit failure, fresh-process interruption/recovery, exact-owner cleanup and pinned SDK/cURL loopback requests. Real AWS and deployment verification remains an application-adoption obligation. Acceptance authorizes no cloud execution, merge or release.
