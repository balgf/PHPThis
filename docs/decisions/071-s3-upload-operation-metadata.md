# ADR 071: One S3 upload with typed operation metadata

Status: accepted

## Decision

For issue #138, the maintainer approved the operation and Consumer Contract 25 on 2026-10-16. Extend `AMAZON_S3_ADR053` only with the optional opaque-document operation owned by [metadata uploads](../file-transfers/metadata-uploads.md): one normalized 1–65,536-byte upload, a required 1–120-byte UTF-8 label and an optional 0–1,024-byte UTF-8 note. Both strings exclude ASCII controls and DEL. Private immutable operation values reject unknown structures before value validation or protected effects.

Authorize the upload before operation validation and admission. Verify the real PHP upload before the atomic ownership/quota insert. Bind descriptive fields to the trusted principal, exact bytes, target and eventual S3 version in private SQLite. Descriptions grant no authority, select no destination and certify no content. Preserve conditional creation, fixed S3 metadata, retained-object validation, ambiguity accounting, bounded reconciliation and exact-version deletion. Clear descriptive fields when failed/deleted state commits, retaining ownership tombstones.

Accept PHP's normalized duplicate and framing limitations explicitly. This decision changes only the original profile's no-operation-text restriction for this concrete operation. It grants no multiple-file storage, generic metadata parser, storage abstraction, automatic migration or production credential policy. Existing original upload/redirect and generated-export recipes keep their separate evidence.

## Consequences and evidence

The optional application owns parsing, authentication/authorization, private data, lifecycle and manual composition. Core APIs, framework dependencies, Strict Profile 4 and PHT001–PHT008 are unchanged. Source and installed guidance route to the operation; the starter remains a non-adopter. [Contract 25 migration](../consumer-contract-upgrades.md#contract-version-25) requires explicit application adoption and pre-route schema provisioning.

Offline tests and complete `composer check` suffice for reference completion, following the accepted #109 evidence policy. Maintained tests cover real native uploads, structural/value failures, policy denial, metadata/object binding, quotas, database/S3 uncertainty, recovery, purge, redaction and bounded SDK transport. Real AWS and deployment evidence remain an adoption obligation under `file-transfers:s3:verify`; the reference adoption gate stays failing. Acceptance authorizes no cloud work, merge or release.
