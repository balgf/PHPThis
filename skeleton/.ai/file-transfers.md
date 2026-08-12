# Application file-transfer contract

This file is the single authoritative file-transfer policy for the checked health-only starter. Its exact current adoption marker is:

NOT_APPLICABLE(FILE_TRANSFER)

The starter accepts no upload and returns no file download. It owns no file-transfer route, multipart field, pre-PHP transfer profile, duplicate-raw-part suitability decision, request-policy path, temporary or durable file root, content parser or scanner, quota, lifecycle operation, file identifier, retained file, authorization rule, CSRF rule, tenant placement, or file-specific response.

`public/index.php` forwards `$_POST` and `$_FILES` through the terminal coordinator so the runtime boundary is explicit. This does not opt the application into file transfers: `bootstrap.php` constructs `RequestReader` without a multipart byte limit, so multipart input remains disabled. The starter claims no effective proxy or web-SAPI upload controls, including no `max_multipart_body_parts` policy, because no file-transfer deployment profile is adopted.

The front controller already owns one redacted response-emission failure event. It emits a generic no-store `500` only when output has not started; after partial output it records the event and ends without attempting a second response. This generic outer behavior does not adopt local-file emission or prove file delivery.

## Adoption directives

Before adoption, read installed `vendor/phpthis/framework/docs/file-transfers/README.md`, remove the not-applicable marker, and record one complete policy here for:

- exact routes, methods, media types, upload fields, normalized cardinality, text-field behavior, total request, inclusive minimum and maximum file bytes including zero-byte policy, rate, concurrency, and execution limits;
- dated effective proxy, web-server, and PHP web-SAPI connection, header/body, rate, concurrency, buffering, timeout, and PHP upload settings, including the first rejecting layer and observable response plus `file_uploads`, `upload_max_filesize`, `post_max_size`, `max_file_uploads`, `max_multipart_body_parts`, and `upload_tmp_dir`;
- whether PHP-normalized duplicate raw scalar parts are suitable and, if not, the tested upstream rule or separately accepted bounded raw parser;
- every accepted and rejected runtime upload status, temporary-file provenance check, client metadata treatment, and pre-write failure;
- exact protected-upload `authenticate -> resolve tenant when applicable -> authorize upload -> validate CSRF when applicable -> rate/concurrency admission -> atomic quota reservation -> storage` order, authorization-before-lookup for confidential downloads, retained metadata/storage-identity binding to applicable tenant, owner/principal, and named action, and explicit public, stateless, non-browser, tenant-free, owner-free, or CSRF-free facts;
- one `OPAQUE_BYTES` posture with fixed code-owned stored/download names, `application/octet-stream` attachment, `nosniff`, explicit cache and authentication/authorization policy, and no content-safety claim; or `INSPECTED_CONTENT` with a versioned definition, pinned tools/configuration/signatures and bounds, non-serving states and accepted promotion, definition-change reinspection or explicit no-reinspection, bounded transition/recovery, and external-service facts;
- dedicated effective web-SAPI `upload_tmp_dir` with named non-root request identity, owner/group, exact ACL or `NONE`, modes, no-execute/non-public mount, capacity/inode monitoring and alert owner, topology, stale cleanup, and no persistence/jobs/logs/traces/reuse of the request-scoped path; plus a separate deployment-precreated durable root before HTTP handling whose request identity cannot and need not create/chmod/chown/repair/replace it, with owner/group, exact ACL or `NONE`, modes, local-or-network mount policy, unrelated-writer exclusion, capacity/inode monitoring, topology, server-generated durable identity/name, post-move regular non-symlink/exact-size/mode recheck, exact digest lifecycle when integrity is claimed, and cleanup;
- applicable principal, tenant, resource, deployment, egress, byte, count, rate, and aggregate quotas; atomic concurrent reservation and release; retry, idempotency, duplicate and ambiguous-client-response policy; move/response/process-crash/disk-full/inode-exhaustion/permission/partial-cleanup outcomes; retention start and expiry; deletion, orphan and partial-write cleanup; one reconciliation path; replica/backup deletion and restore interaction; legal hold; and incident owners;
- download lookup and authorization order, missing and unavailable behavior, exact headers, cache and range policy, the framework emitter's exact guarantee and failure behavior, and the mandatory current path-only invariant that the selected pathname and bytes remain immutable under exclusive-writer authority from authorized selection through completed emission; a digest is additional integrity evidence only, while an identity-bound body, open-handle body, or different response primitive requires a separately accepted core/response decision; and
- application-owned boundary, request-policy, filesystem, content, quota/lifecycle, real-SAPI, exact-byte, bounded-memory, deployed-ingress, and topology evidence wired into `composer check`.

Cardinality at `RequestReader` applies to PHP-normalized entries. `max_multipart_body_parts` is a total raw-part resource bound, not proof of duplicate-name rejection. Client filenames, paths, extensions, and media types remain untrusted.

Keep movement, quota accounting, inspection, cleanup, retention, deletion, and authorization in concrete application operations. Do not add a generic authentication, tenant, CSRF, quota, scanner, upload, storage, filesystem, lifecycle, or checker API. A transfer is not adopted until the complete application gate proves every claimed success and failure without disclosing submitted metadata, stored identifiers, or server paths.
