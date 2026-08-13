# Application-owned Amazon S3 file transfers

Status: accepted optional guidance under ADR 053 and Consumer Contract version 13. An application adopts it only by recording `AMAZON_S3_ADR053` and completing every required application-owned evidence stage; installation or reference-proof success alone is not adoption.

This profile defines one narrow server-mediated Amazon S3 path. It preserves PHPThis's bounded multipart ingress and application-owned policy, then makes every remote object choice visible. It is not a generic storage recommendation.

## Profile boundary

The complete profile selection is:

- PHP 8.4.x, `aws/aws-sdk-php` exactly `3.392.1`, `guzzlehttp/guzzle` exactly `8.0.2`, and S3 API model `2006-03-01`, with the SDK-required JSON, PCRE and SimpleXML extensions plus the selected cURL extension/runtime;
- one AWS commercial-region account, one fixed region, one general-purpose regional bucket whose exact lowercase DNS-compatible name contains no dot and uses none of AWS's reserved `xn--`, `sthree-`, `amzn-s3-demo-`, `-s3alias`, `--ol-s3`, `--x-s3`, or `--table-s3` forms, one exact expected account identifier, and one fixed object prefix;
- a private bucket with all four Block Public Access flags, `BucketOwnerEnforced`, versioning exactly `Enabled` and never suspended, Requester Pays disabled, MFA Delete disabled, Object Lock/legal hold disabled and not applicable, S3 replication disabled, external backup/restore not applicable, no S3 Lifecycle rule or action whose filter can cover `private-documents/v1/`, non-TLS denial, and one exact customer-managed same-account/same-region single-Region AWS KMS key ARN with a lowercase UUID key ID, `SYMMETRIC_DEFAULT`, `ENCRYPT_DECRYPT`, `AWS_KMS` origin, Enabled state and 365-day automatic rotation; KMS aliases, `mrk-` multi-Region IDs, external/custom key-store origin and disabled/pending-deletion keys are excluded;
- one server-mediated normalized multipart upload followed by one `PutObject`; and
- one protected application download endpoint that returns an empty `303` to one no-more-than-300-second pre-signed exact-version `GetObject` URL.

The application owns the SDK and HTTP-client dependencies, cURL/TLS/CA runtime and update review, configuration values, credential provider, clients, operations, metadata, quota, reconciliation, exact-version deletion, IAM, KMS, bucket policy, retention, alarms, cost, audit events, real-service tests, and production evidence. PHPThis owns none of them.

This profile excludes direct browser PUT/POST, S3 multipart or resumable upload, multiple files, PHP proxy streaming, CDN/proxy offload, public objects, directory buckets/S3 Express, access points, Multi-Region Access Points, Outposts, transfer acceleration, custom endpoints, other partitions, arbitrary S3-compatible services, runtime backend selection, and generic storage abstractions.

## Common multipart and request policy

The application's `.ai/file-transfers.md` remains the one writable policy owner. It records the exact route and sole field, normalized cardinality, text-field behavior, inclusive minimum/maximum bytes and zero-byte choice, dated effective pre-PHP ingress controls, duplicate-raw-scalar suitability, PHP upload-error map, temporary-root policy, and every quota and lifecycle fact required by the [file-transfer index](README.md).

The protected request sequence remains visible and short-circuits on every failure:

```text
authenticate
-> resolve tenant when applicable
-> authorize upload
-> validate CSRF when applicable
-> rate/concurrency admission
-> atomic quota reservation
-> verify upload provenance and bytes
-> Amazon S3 operation
```

The PHP temporary path remains request-scoped input. Verify `is_uploaded_file`, independently read the actual byte count, require it to equal the normalized reported count and operation bound, and compute SHA-256 over the exact temporary bytes before the SDK call. Never persist, enqueue, log, trace, or reuse the temporary path. The effective `upload_tmp_dir` deployment owner retains the bounded stale-temporary-file scan, age/action policy, capacity alert and redacted evidence from the common profile; S3 adoption does not transfer that responsibility to the application reconciler. Never use the hostile client filename, path, extension, or media type to select a bucket, key, content type, disposition, or policy.

The application records exact limits for total request and file bytes; per-principal and per-tenant retained object count/bytes; deployment-wide object count/bytes; upload and download request rate; concurrent upload, reconciliation, deletion and URL-issuance work; egress bytes/rate; S3/KMS/CloudTrail request cost; and retained storage/noncurrent-version cost. It records every inapplicable principal or tenant dimension. One named metadata/quota operation atomically reserves the applicable object/byte/cost allowance before S3 I/O, commits it only at `accepted`, retains it throughout ambiguity, releases it after a proved pre-send failure or definitive `failed_collision`, and releases retained capacity only after exact-version absence plus the durable `deleted` transition. One bounded reconciler owns mismatches; a count-then-Put check is not a quota reservation.

The first reference posture is an S3-specific `OPAQUE_BYTES` variant. Use the fixed stored name `content` and fixed download name `document.bin`; never parse or serve the retained bytes as active content. Direct S3 response overrides cannot add `X-Content-Type-Options: nosniff`, and a header on the application's `303` cannot govern S3's later `200`. ADR 053 and Contract version 13 accept fixed `application/octet-stream` plus attachment without `nosniff` only as this profile's narrow exception; `LOCAL_ADR026` keeps its `nosniff` requirement unchanged. The remaining posture still does not certify absence of malware or safety for another parser. `INSPECTED_CONTENT` needs a separately completed application inspection definition and evidence and is not covered by this reference.

## Exact dependency and client construction

Pin the application dependency and lockfile exactly:

```json
{
  "require": {
    "php": "8.4.*",
    "aws/aws-sdk-php": "3.392.1",
    "ext-curl": "*",
    "ext-json": "*",
    "ext-pcre": "*",
    "ext-simplexml": "*",
    "guzzlehttp/guzzle": "8.0.2"
  }
}
```

The complete reviewed `composer.lock` pins every transitive dependency; release CI runs Composer's strict validation and platform-requirement check against the immutable production image. Composer cannot pin an extension's native library version, so the deployment image digest and evidence additionally pin the exact PHP/ext-curl/libcurl/TLS-library tuple. Do not treat a successful dependency resolution on another host as equivalent evidence.

Construct each client at one visible application composition site. The exact final readonly configuration type reads one fixed region, bucket, expected account identifier, customer-managed KMS key ARN, one code-owned absolute CA-bundle path, its expected lowercase SHA-256, and non-secret credential-source selection through the application's ordinary typed configuration boundary. A separate reviewed `credential_provider` source reads `$configuration->credentialSource`, constructs exactly the one manifest-named provider, wraps it with `CredentialProvider::memoize(`, and exposes the resulting `$memoizedExplicitCredentialProvider` to composition. It does not call `CredentialProvider::defaultProvider(` or `CredentialProvider::chain(` and has no second or ambient fallback. Before composition, require that CA bundle to be the expected bounded readable regular non-symlink file from the immutable release image and require its digest to match. Neither source stores credential values in `.ai/`, fixtures, logs, or diagnostics.

The required client shape is:

```php
$caBundleStat = lstat($configuration->caBundlePath);

if (!str_starts_with($configuration->caBundlePath, '/')
    || !is_array($caBundleStat)
    || ($caBundleStat['mode'] & 0170000) !== 0100000
    || is_link($configuration->caBundlePath)
    || !is_readable($configuration->caBundlePath)
) {
    throw new RuntimeException('S3 CA bundle is unavailable.');
}

$caBundleSize = filesize($configuration->caBundlePath);
$caBundleHash = hash_file('sha256', $configuration->caBundlePath);

if (!is_int($caBundleSize)
    || $caBundleSize < 1
    || $caBundleSize > 1048576
    || !is_string($caBundleHash)
    || !hash_equals($configuration->caBundleSha256, $caBundleHash)
) {
    throw new RuntimeException('S3 CA bundle is not the reviewed release input.');
}

$httpClient = new GuzzleHttp\Client([
    'handler' => new GuzzleHttp\Handler\CurlHandler([
        'transport_sharing' => GuzzleHttp\TransportSharing::NONE,
    ]),
    'allow_redirects' => false,
    'cookies' => false,
    'debug' => false,
    'proxy' => '',
    'protocols' => ['https'],
    'connect_timeout' => 2.0,
    'timeout' => 10.0,
    'verify' => $configuration->caBundlePath,
]);

$awsHttpHandler = new Aws\Handler\Guzzle\GuzzleHandler($httpClient);

$s3 = new Aws\S3\S3Client([
    'version' => '2006-03-01',
    'region' => $configuration->region,
    'credentials' => $memoizedExplicitCredentialProvider,
    'defaults_mode' => 'legacy',
    'signature_version' => 'v4',
    'auth_scheme_preference' => ['aws.auth#sigv4'],
    'scheme' => 'https',
    'endpoint_discovery' => ['enabled' => false],
    'use_aws_shared_config_files' => false,
    'ignore_configured_endpoint_urls' => true,
    'use_path_style_endpoint' => false,
    'bucket_endpoint' => false,
    'use_accelerate_endpoint' => false,
    'use_dual_stack_endpoint' => false,
    'use_fips_endpoint' => false,
    's3_us_east_1_regional_endpoint' => 'regional',
    'use_arn_region' => false,
    'disable_multiregion_access_points' => true,
    'disable_express_session_auth' => true,
    'request_checksum_calculation' => 'when_supported',
    'response_checksum_validation' => 'when_supported',
    'disable_request_compression' => true,
    'retries' => 0,
    'app_id' => '',
    'csm' => false,
    'debug' => false,
    'stats' => false,
    'validate' => true,
    'http_handler' => $awsHttpHandler,
    'http' => [
        'allow_redirects' => false,
        'connect_timeout' => 2.0,
        'proxy' => '',
        'protocols' => ['https'],
        'timeout' => 10.0,
        'verify' => $configuration->caBundlePath,
    ],
]);
```

The explicit `CurlHandler` and application-supplied AWS `GuzzleHandler` prevent the SDK/Guzzle default-handler path from selecting cURL or streams from ambient extension availability. Guzzle `8.0.2`'s `TransportSharing::NONE` makes the no-sharing choice explicit. Empty `proxy` disables Guzzle and libcurl environment-proxy fallback; an application that needs a proxy requires a separately reviewed exact network profile. HTTPS-only protocols, disabled redirects, finite timeouts and the exact CA bundle apply at both composition layers so an operation cannot inherit broader Guzzle defaults. The explicit defaults mode, sole SigV4 preference, disabled endpoint discovery, request compression, client-side monitoring and app identifier, plus disabled stats and enabled parameter validation prevent ambient AWS settings from widening protocol, endpoint, telemetry or request-shape behavior. The release evidence records `curl_version()`'s libcurl version, TLS backend/version and supported protocols, the PHP/ext-curl build, CA-bundle origin/version/hash, image digest, patch/update owner and rollback rule; it proves peer and hostname verification against the expected regional S3 host. A missing extension, wrong runtime tuple, missing/mutated CA bundle, proxy environment mutation, or transport substitution fails before external I/O.

The accountable application may select smaller finite timeouts after measurement; it does not remove or make them unbounded. `retries => 0` is deliberate because automatic replay can conceal whether a write happened. The checksum options are explicit so ambient SDK configuration cannot change calculation or validation behavior; the application still supplies its precomputed SHA-256 and explicitly compares the exact-version head result. If separately constructed read-only or pre-sign clients use another finite retry policy, record and prove that exact client and do not share it with write/delete operations. Do not use the default AWS/Guzzle HTTP handler, default credential chain, ambient profile files, endpoint or proxy environment variables, request values, a service container, client registry, facade, or fallback client.

The selected credential provider is itself an external-I/O boundary when it obtains or refreshes role/workload credentials. Behavior, real-service and deployment evidence record the named source's exact construction, cache/memoization and refresh owner, HTTP transport, finite connect/operation timeouts and retry count, credential lifetime, pre-expiry refresh margin, clock/skew behavior, outage outcome, restart behavior and redaction. A provider without one of those mechanisms records that field explicitly `N/A` and proves why; it does not invent a generic credential HTTP client. S3 client timeouts do not bound a metadata or identity-provider request. Credential acquisition or refresh failure stops before S3 I/O and never falls back to another credential source or ambient HTTP handler.

Use role or workload credentials appropriate to the deployment. Give upload/validation, download-issuance, reconciliation, deletion, and deployment administration separate least-privilege identities when their process boundaries differ. Record exact actions, object/bucket/KMS resource ARNs and conditions; never grant `s3:*`, `kms:*`, wildcard bucket access, public ACL authority, bucket-policy administration, or key administration to an HTTP identity. The upload/validation identity has `s3:PutObject` and `s3:GetObjectVersion` only for the application object ARN plus the exact KMS `GenerateDataKey`/Decrypt actions proved by the real `3.392.1` operations. The pre-signing identity has `s3:GetObjectVersion` on that object namespace and KMS Decrypt only. The reconciler has bucket-level `s3:ListBucketVersions` constrained by `s3:prefix` to `private-documents/v1/`, object-level `s3:GetObjectVersion`, and only the proved KMS read actions. The deletion identity has object-level `s3:DeleteObjectVersion`; it is never granted `s3:DeleteObject`, which could create a current delete marker. Grant the deletion process list/read actions only when it also owns the bounded post-delete observation, with the same prefix and KMS constraints. Do not send server-side-encryption request headers on `HeadObject` or `GetObject`: the object already carries its encryption state and S3 rejects inappropriate encryption headers.

## Object identity and upload state

Generate one cryptographically random 128-bit identifier rendered as exactly 32 lowercase hexadecimal characters. Derive exactly:

```text
private-documents/v1/{32-lowerhex}/content
```

The route, filename, tenant, user, request body, headers, client metadata, and database values do not choose the prefix or suffix. Bind the identifier, key, bucket, exact tenant when applicable, owner/principal, upload/download authorization actions, byte count, raw SHA-256 and base64 SHA-256, KMS key ARN, state, and exact S3 `VersionId` in application metadata. Never reuse an identifier or key, including after deletion.

The only states are:

| State | Meaning | Serving |
| --- | --- | --- |
| `pending` | quota and metadata reserved; S3 outcome not accepted | no |
| `stored` | one Put response supplied a non-empty VersionId; exact-version validation is pending | no |
| `accepted` | exact-version validation matched all recorded facts | yes, after current authorization |
| `failed` | operation failed with an exact known no-object outcome and accounting is resolved | no |
| `reconciliation_required` | an external result is ambiguous or contradictory | no |
| `deletion_pending` | serving is disabled and exact-version deletion/reconciliation remains | no |
| `deleted` | application-owned exact-version deletion and quota/accounting transition are proved | no |

No database transaction spans metadata/quota state and S3. The application creates `pending` and reserves quota durably before S3 I/O. It then makes exactly one write attempt:

```php
$result = $s3->putObject([
    'Bucket' => $configuration->bucket,
    'Key' => $objectKey,
    'Body' => $verifiedUploadStream,
    'ContentLength' => $sizeBytes,
    'ContentType' => 'application/octet-stream',
    'StorageClass' => 'STANDARD',
    'ChecksumAlgorithm' => 'SHA256',
    'ChecksumSHA256' => $sha256Base64,
    'IfNoneMatch' => '*',
    'ServerSideEncryption' => 'aws:kms',
    'SSEKMSKeyId' => $configuration->kmsKeyArn,
    'BucketKeyEnabled' => false,
    'ExpectedBucketOwner' => $configuration->expectedAccountId,
    'Metadata' => [
        'profile' => 'private-documents-v1',
    ],
]);
```

The concrete operation opens the already verified PHP temporary file in binary read mode, obtains and compares its regular-file size, hashes from byte zero through EOF to a raw 32-byte SHA-256 value, base64-encodes those exact 32 bytes, then rewinds and proves offset zero or closes and reopens the same upload path before the one SDK call. It closes every handle in `finally`; no storage wrapper hides the body. A size change, short read, rewind/reopen failure, or digest mismatch stops before acceptance. A successful write must return one non-empty `VersionId` and `ChecksumSHA256` exactly equal to the submitted base64 SHA-256. A missing or mismatched field enters `reconciliation_required`; the record remains non-serving. Only after those response fields match does the application persist the version and move to `stored`, then make one server-executed exact-version `HeadObject` using the fixed bucket/key, `VersionId`, `ChecksumMode: ENABLED`, and `ExpectedBucketOwner`. Compare the SDK `3.392.1` result's exact proved keys for byte count, base64 SHA-256, `aws:kms`, the configured KMS key, `BucketKeyEnabled: false`, fixed content type/metadata, and version. The Put request pins `StorageClass: STANDARD`, but S3 omits the HeadObject `StorageClass` result for STANDARD objects and returns that field for other classes. The exact retained-object check is therefore:

```php
if ($headResult->hasKey('StorageClass')
    || $headResult->hasKey('ArchiveStatus')
    || $headResult->hasKey('Restore')
    || $headResult->hasKey('Expiration')
) {
    throw new UnexpectedValueException('S3 retained object is not in the required STANDARD state.');
}
```

The isolated real-service test pins this exact absent-field representation under SDK `3.392.1`; it does not expect a returned `StorageClass: STANDARD` string. Only a complete match promotes to `accepted`. Encryption and storage availability are validated against the retained object rather than inferred from request intent. The fixed metadata is operational evidence only; it grants no authorization and does not classify content. Do not use ETag as a general digest.

`IfNoneMatch: *` checks the current version and can succeed when the current version is a delete marker. Never reusing keys is therefore mandatory. A definitive `412 Precondition Failed` transitions this record to `failed` with code-owned reason `failed_collision`, releases only this request's reservation, emits the recorded security/operations event, and never lists, inspects, promotes, overwrites or deletes the pre-existing object—even if its bytes match. A `409 ConditionalRequestConflict`, timeout, disconnect, throttling or `5xx` after send, missing/mismatched response checksum or VersionId, mismatched head, SDK exception with an uncertain write result, process death, or metadata-write failure after S3 success enters `reconciliation_required`. Never replay `PutObject` for that key. If the product permits another semantic attempt, it receives a new authorization decision, identifier, key and reservation without closing an ambiguous earlier record.

Record and prove a finite failure table. A local parse/configuration/credential failure demonstrably before send may transition `failed` and release its reservation through the named accounting operation. A definitive `412` follows `failed_collision` above. Authentication/authorization/KMS `403`, checksum or request `400`, `409`, throttling, service `5xx`, timeout/disconnect, contradictory success, missing or mismatched version/checksum/encryption/size, head unavailability/mismatch, and any result whose no-object outcome is not proved remain non-serving and enter reconciliation. Public responses stay finite and generic. This reference classifies the raw and base64 SHA-256 as sensitive integrity metadata kept only in the private authoritative record; public responses, diagnostics, terminal summaries, logs, traces, CI artifacts and deployment evidence contain only code-owned result classes and permitted request IDs, never object keys, checksums, client metadata, paths, credentials, URLs, SDK messages, provider response bodies, or object content.

## Reconciliation, deletion, and lifecycle

One named non-HTTP application command selects a finite ordered batch of eligible `pending`, `stored`, `reconciliation_required`, and `deletion_pending` records. It uses only recorded exact bucket/key/version facts and server-executed calls with `ExpectedBucketOwner`. Every Put is preceded by durable `pending` metadata, the exact key and its reservation, so an “orphan” or missing-transition case inside this selected fault envelope means an object still owned by that pending/reconciliation record or a post-Put metadata-transition failure; this command owns it. Loss of the authoritative row itself is outside that envelope: never discover, adopt, serve or delete an object by broad bucket listing or by inferring ownership from an S3 key. A named database restore and security/operations incident policy owns that case and retains affected capacity until authoritative recovery or accountable disposition. The command bounds requests, elapsed time, bytes, rate, concurrency, output, and retries and emits only finite redacted outcomes.

For an ambiguous write where no VersionId reached metadata, after local request termination the separately authorized reconciler makes exactly one `ListObjectVersions` request through its retries-disabled finite-timeout client. Local termination does not prove the remote Put is quiescent. The observation uses the configured bucket, `Prefix` equal to the complete exact never-reused key, `MaxKeys: 2`, and `ExpectedBucketOwner`. Prefix is not equality: every returned key must then equal the requested key. The HTTP upload identity has no list authority; the reconciler's `s3:ListBucketVersions` is constrained by `s3:prefix` to the recorded application namespace, and evidence proves denial for a broader prefix.

An empty `Versions` list, empty `DeleteMarkers` list and `IsTruncated: false` still remains `reconciliation_required` with quota retained: S3 exposes no operation-status or maximum remote-completion bound proving that an earlier timed-out request cannot finish later. Exactly one version may proceed only when there are no delete markers, `IsTruncated` is false, its key equals the complete requested key, its VersionId is non-empty and `IsLatest` is true; exact-version Head validation must still match every recorded fact before promotion. Any empty, sibling-key, marker, second-version, truncation/next-marker, missing/invalid-field, `403`, timeout, disconnect or other result stays non-serving and enters the incident path. A definite `412` never lists for adoption and never inspects, promotes or deletes the pre-existing object. Do not infer an exact version from an unversioned current-object head.

For deletion, stop serving and durably enter `deletion_pending`, then issue one retries-disabled SDK `DeleteObject` request with only the configured bucket, recorded key, recorded exact `VersionId`, and `ExpectedBucketOwner`; its IAM action is `s3:DeleteObjectVersion`, never `s3:DeleteObject`. A definite response has `VersionId` equal to the request and `DeleteMarker` absent or false. Observe absence with one retries-disabled finite-timeout `ListObjectVersions` call using the fixed bucket, Prefix equal to the complete key, `MaxKeys: 2`, and `ExpectedBucketOwner`; require empty `Versions` and `DeleteMarkers`, `IsTruncated: false`, no continuation markers, and no sibling key. Under the proved no-reuse/no-other-writer invariant and S3's strong LIST consistency after a delete, only that exact empty post-delete result permits the durable `deleted` transition and quota release.

If the initial Delete result was ambiguous and the observation returns exactly one exact-key version with the recorded non-empty VersionId, no marker, no truncation/continuation, and no sibling, make one exact-version Head and require all recorded checksum/size/encryption facts to match. Only then permit at most one code-owned reconciled SDK Delete request for that same VersionId followed by one final bounded ListObjectVersions observation. An empty final result permits completion even if the retry response was ambiguous; a claimed-success response with a retained version, mismatched version or Head, empty/marker/multiple/sibling/truncated/malformed result outside the allowed point, denial, timeout, second non-empty result, or any other outcome remains `deletion_pending`, retains quota, and enters the incident path. Never delete without VersionId, grant `s3:DeleteObject`, create a delete marker, loop, or broad-delete. The empty-list result after an ambiguous Put remains incident-only because that earlier write may still be in flight; the post-delete proof relies on a completed Delete request plus S3's delete/LIST consistency and cannot be reused for write reconciliation.

Bucket versioning is mandatory, but a delete marker or one deleted version does not remove all versions. The first profile prohibits every S3 Lifecycle rule and action whose filter can cover `private-documents/v1/`, including current/noncurrent transitions, current/noncurrent expiration, expired-object delete-marker actions, and any other lifecycle mutation. The sole deletion path first makes metadata non-serving and enters `deletion_pending`, then deletes the recorded exact VersionId and proves its outcome before `deleted` and quota release. A lifecycle rule for an isolated real-test or unrelated prefix must have a disjoint exact filter and deployment evidence proving it cannot match the application prefix.

Record who owns current and noncurrent-version retention, incomplete/failed exact-version cleanup, abandoned records, KMS-key disablement/deletion, quota-accounting mismatch, incident response, and cost alarms. Automatic KMS rotation is enabled with `RotationPeriodInDays: 365`; old ciphertext remains decryptable through KMS-retained backing material and this first profile does not re-encrypt accepted objects on rotation. Key disablement or scheduled deletion is denied to application identities, alarmed, and owned by the named security operator; an emergency accountable action makes affected metadata non-serving before any loss of decrypt authority. S3 replication is disabled; external backup/restore, Object Lock and legal hold are explicitly not applicable. An operator or configuration drift that makes an accepted version missing or noncurrent moves its metadata to `reconciliation_required` or the recorded unavailable state; it never silently becomes `accepted` or `deleted`. Cross-system tags cannot repair this boundary and are outside the first profile.

## Protected pre-signed download

The application endpoint performs `authenticate -> resolve tenant when applicable -> authorize named download before confidential lookup -> load exact accepted record -> issue redirect`. A database identifier and pre-signed URL are bearer values, not permission. Denial performs no S3 lookup or signing.

Build `GetObject` only from the recorded exact bucket, key and `VersionId`, with fixed response overrides:

- `ResponseContentType: application/octet-stream`;
- `ResponseContentDisposition: attachment; filename="document.bin"`; and
- `ResponseCacheControl: private, no-store`.

S3 does not provide an override for `X-Content-Type-Options`; the direct S3 `200` therefore has no guaranteed `nosniff`. Do not claim that the application's redirect header carries across origins or responses. ADR 053 and Contract version 13 accept this only as the narrow `AMAZON_S3_ADR053` exception; they do not alter `LOCAL_ADR026`.

Pre-sign for no more than 300 seconds. Require HTTPS, no userinfo, no fragment, no explicit port other than `443`, the exact virtual-hosted AWS regional S3 host for the configured no-dot bucket/region, the expected encoded object path, and at most 4,096 URL bytes inclusive. Reject path-style output. Pin the exact presigner source and SDK version and prove the produced query through a real-service request rather than maintaining an invented query-key allowlist that could drift from SigV4. The real test proves the selected temporary-credential URL fits the bound; overflow fails closed pending an accountable profile adjustment. The application response is empty `303` with `Location`, `Content-Length: 0`, `Cache-Control: private, no-store`, and `Referrer-Policy: no-referrer`. Do not add a response cookie.

The real-service proof requires `X-Amz-SignedHeaders=host`, no additional required request headers, and a successful URL-only GET from a client that adds no AWS headers. Temporary credentials can cause the effective lifetime to end before the requested 300 seconds; they never extend it.

`ExpectedBucketOwner` is required on server-executed S3 operations, but it maps to a request header that an ordinary browser navigation cannot add. Do not put it into this browser-navigation pre-signed command. The fixed bucket/key/version, issuance role, bucket policy, expected host, and release-gate account/bucket proof own that boundary.

Treat the complete URL as a reusable bearer credential until expiry. Never log, trace, persist, enqueue, report, or expose its query string. It can still appear in browser history and developer tools, copied links, intermediary diagnostics, and response-header logging unless each owner disables or scrubs that data. `Referrer-Policy: no-referrer` reduces downstream `Referer` leakage but does not erase those copies. Record the exact proxy/logging scrubbing and incident response, including object-version deletion and credential revocation limits. The URL is not one-time and cannot guarantee immediate revocation, client receipt, network completion, or durable delivery. S3 may honor a single `Range` request and the same URL may be used repeatedly until effective expiry; this profile makes no `Accept-Ranges: none`, full-`200`, no-partial-content, per-URL request-count, or per-URL egress bound claim. Top-level navigation makes CORS not applicable; browser fetch/XHR, cross-origin embedding, or credentialed API use needs its own exact CORS and CSRF design.

Bound issuance rate, concurrent issuance, object size, egress, lifetime and cost. Expiry does not cancel an already-started transfer. Record clock, time source and skew evidence for signing and expiration.

## Deployment facts and evidence

Before release, record dated evidence for the exact application image digest; PHP, ext-curl, libcurl and TLS-library versions; supported cURL protocols; CA-bundle absolute path/origin/version/hash; Composer lock; account, region, bucket ARN, expected account identifier, API endpoint, identities, policies, Block Public Access flags, `BucketOwnerEnforced`, versioning exactly `Enabled` and never suspended, Requester Pays disabled, MFA Delete disabled, Object Lock/legal hold disabled and not applicable, S3 replication disabled, external backup/restore not applicable, TLS denial, encryption behavior, Put `StorageClass: STANDARD` and the exact HeadObject absent `StorageClass`/`ArchiveStatus`/`Restore`/`Expiration` representation, the absence of every S3 Lifecycle rule or action whose filter can cover `private-documents/v1/`, exact disjoint filters for any test/unrelated lifecycle rule, logs/metrics/alarms, cost thresholds, network path, region/residency, retention/exact-version deletion, incident ownership, and credential/runtime/CA rotation and revocation. KMS `DescribeKey` evidence requires the exact ARN/account/region, `KeyManager: CUSTOMER`, `MultiRegion: false`, `KeySpec: SYMMETRIC_DEFAULT`, `KeyUsage: ENCRYPT_DECRYPT`, `Origin: AWS_KMS`, `Enabled: true`, `KeyState: Enabled`, and exactly `EncryptionAlgorithms: [SYMMETRIC_DEFAULT]`; `GetKeyRotationStatus` requires `KeyRotationEnabled: true`, `RotationPeriodInDays: 365`, and the dated next rotation. Also prove the key policy and identity policies' exact roles/actions, denial/alarm/owner for disablement and scheduled deletion, and continued decrypt authority for retained earlier ciphertext. Store no secret values or pre-signed URLs in that evidence.

Select CloudTrail S3 data events for all five relied-on operations—`PutObject`, `HeadObject`, `ListObjectVersions`, `GetObject`, and `DeleteObject`—on the configured bucket/application prefix and the exact management-event coverage used to detect bucket, versioning, lifecycle, public-access, ownership, replication, IAM and KMS drift. Record the trail or event-data-store destination, exact retention, readers/administrators, alert rules, cost owner, availability delay, and incident owner. CloudTrail records object keys, so classify and restrict that audit metadata explicitly and redact keys from every other sink; object content, raw/base64 checksums, client metadata, local paths, credentials, response bodies, and complete pre-signed URLs/query strings are never intentionally recorded. If one required operation cannot be selected as an S3 data event, record the exact CloudTrail management event or another version-controlled evidence source rather than claiming coverage.

The application-owned source checker uses a canonical non-secret manifest to pin the exact allowed region, bucket, account, `private-documents/v1/` prefix, KMS ARN, CA path/hash and named credential source plus separate hashes for the application PHP configuration and explicit credential-provider sources. Placeholder, grammar, value, source-hash, configuration-name, memoization, default-chain or fallback drift fails closed. This is a reviewed-source tripwire, not deployment, credential-provider behavior or AWS proof.

The isolated real-AWS gate uses the locked SDK/Guzzle packages and explicit CurlHandler/GuzzleHandler composition from the immutable release image, never the default handler. It rejects a missing/wrong extension or runtime tuple, CA hash, proxy-disabled setting, transport, Composer lock, or manifest/deployed-boundary disagreement. It uses a non-production account/bucket or an exact bounded test prefix, one unique run identity, fixed byte fixtures, exact cleanup, and a finite stale-run reconciler because hard termination can skip cleanup. It proves TLS peer/hostname validation, write preconditions, exact VersionId and Put checksum response, exact-version head and checksum, encryption, denied cross-owner/wrong-key behavior, authorization-before-lookup at the application boundary, pre-signed fixed response facts, expiry assumptions within the measured boundary, ambiguous-result reconciliation fixtures, exact-version deletion, and redaction. It never broad-lists or broad-deletes production data.

Use [Amazon S3 verification](amazon-s3-verification.md) for the accepted application-owned gate shape. Static success alone is never S3 adoption or production evidence.

## References

- [AWS SDK for PHP 3.392.1 release](https://github.com/aws/aws-sdk-php/releases/tag/3.392.1) and [tagged package requirements](https://github.com/aws/aws-sdk-php/blob/3.392.1/composer.json)
- [AWS SDK for PHP client configuration](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html) and [S3Client API](https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.S3.S3Client.html)
- [Guzzle 8.0.2 CurlHandler](https://github.com/guzzle/guzzle/blob/8.0.2/src/Handler/CurlHandler.php), [Client](https://github.com/guzzle/guzzle/blob/8.0.2/src/Client.php), and [proxy resolution](https://github.com/guzzle/guzzle/blob/8.0.2/src/Handler/ProxyEnv.php)
- [Amazon S3 data-integrity checks](https://docs.aws.amazon.com/AmazonS3/latest/userguide/checking-object-integrity.html), [conditional writes](https://docs.aws.amazon.com/AmazonS3/latest/userguide/conditional-writes.html), and [`PutObject`](https://docs.aws.amazon.com/AmazonS3/latest/API/API_PutObject.html)
- [`HeadObject`](https://docs.aws.amazon.com/AmazonS3/latest/API/API_HeadObject.html), [`ListObjectVersions`](https://docs.aws.amazon.com/AmazonS3/latest/API/API_ListObjectVersions.html), [`DeleteObject`](https://docs.aws.amazon.com/AmazonS3/latest/API/API_DeleteObject.html), and [S3 strong consistency](https://aws.amazon.com/s3/consistency/)
- [Amazon S3 pre-signed URLs](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html), [versioning](https://docs.aws.amazon.com/AmazonS3/latest/userguide/Versioning.html), and [lifecycle configuration](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lifecycle-mgmt.html)
- [S3 Block Public Access](https://docs.aws.amazon.com/AmazonS3/latest/userguide/access-control-block-public-access.html), [Object Ownership](https://docs.aws.amazon.com/AmazonS3/latest/userguide/about-object-ownership.html), [SSE-KMS](https://docs.aws.amazon.com/AmazonS3/latest/userguide/UsingKMSEncryption.html), and [CloudTrail S3 data events](https://docs.aws.amazon.com/AmazonS3/latest/userguide/cloudtrail-logging-s3-info.html)
- [Amazon S3 general-purpose bucket naming rules](https://docs.aws.amazon.com/AmazonS3/latest/userguide/bucketnamingrules.html)
- [KMS `DescribeKey`](https://docs.aws.amazon.com/kms/latest/APIReference/API_DescribeKey.html), [`GetKeyRotationStatus`](https://docs.aws.amazon.com/kms/latest/APIReference/API_GetKeyRotationStatus.html), and [automatic rotation](https://docs.aws.amazon.com/kms/latest/developerguide/rotating-keys-enable.html)
