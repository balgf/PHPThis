# Amazon S3 file-transfer verification

Status: accepted optional guidance under ADR 053 and Consumer Contract version 13. This page defines the application-owned evidence shape for a deliberate `AMAZON_S3_ADR053` adoption; its presence or synthetic framework proof alone is not adoption.

The canonical gate is `file-transfers:s3:verify`. It runs finite local source checks, application behavior evidence, an isolated real-AWS test, and a deployment-evidence verifier. It fails closed when a module, credential, service fact, or dated deployment record is absent. A skip, fake S3 server, local emulator, mock, S3-compatible substitute, or source-only pass is never an adoption or release result.

## Evidence boundaries

The four application-owned parts prove different facts:

| Part | Proves | Does not prove |
| --- | --- | --- |
| source checker | exact reviewed local files, exact SDK/Guzzle/extension requirements and lock entries, explicit cURL-handler composition, one literal gate chain, and absence of a few finite prohibited spellings | PHP meaning in general, the actual native cURL/TLS/CA runtime, AWS behavior, IAM, KMS, topology, or production configuration |
| behavior tests | request-policy order, multipart/provenance/size/hash/key/state/failure/redirect/redaction behavior with injected operation results | that Amazon S3 produced those results |
| isolated real-AWS test | exact SDK/client/service request and response behavior inside one named non-production fault envelope | production account policy, failover, capacity, alarms, cost, or every ambiguous network failure |
| deployment verifier | dated version-controlled or safely queried account/bucket/IAM/KMS/lifecycle/monitoring facts selected by the application | client receipt, future drift, universal security, or facts it did not inspect |

The source checker is deliberately application-owned. PHPThis does not install, discover, host, or execute it and adds no Strict Profile diagnostic or framework checker rule. Exact hashes are review tripwires, not semantic certification: an accountable reviewer updates them only after reviewing the complete affected source and rerunning behavior, real-service, and deployment evidence.

## Required behavior evidence

Prove the common multipart, protected request-policy, quota, temporary-file, content-posture, lifecycle, and redaction cases in [file-transfer testing](testing.md), then add the S3 cases:

- exact 32-lowerhex identifier and `private-documents/v1/{identifier}/content` derivation, with request-controlled bucket, endpoint, region, prefix, key, encryption, credential and retry attempts rejected before external I/O;
- exact Guzzle `8.0.2` `CurlHandler` with `TransportSharing::NONE` -> Guzzle client -> AWS `GuzzleHandler` -> S3 `http_handler` composition, with no default-handler path; disabled redirects/cookies/debug/environment proxy, HTTPS-only protocols, finite timeouts, exact application-owned readable regular non-symlink CA path/hash validation, explicit legacy defaults/SigV4-only/endpoint-discovery/compression/telemetry/stats/validation settings, and failures for missing ext-curl, runtime/lock drift, ambient proxy mutation, CA mutation/symlink, stream-handler substitution, or TLS peer/hostname failure;
- one manifest-named credential source, a separate hashed provider source that reads `$configuration->credentialSource`, exact `CredentialProvider::memoize(` construction producing `$memoizedExplicitCredentialProvider`, and rejection of `CredentialProvider::defaultProvider(`, `CredentialProvider::chain(` or any other fallback; behavior, real-service and deployment evidence owns the selected provider's fetch/HTTP/timeout/retry/lifetime/refresh/skew/outage behavior or records each absent mechanism explicitly `N/A`;
- binary handle, regular-file/actual-size agreement, raw 32-byte SHA-256, base64 checksum, rewind or same-path reopen at byte zero, and handle closure after every outcome;
- one visible `PutObject` attempt with exact fixed fields, `StorageClass: STANDARD`, `IfNoneMatch: *`, expected owner, customer-managed KMS ARN, `BucketKeyEnabled: false`, checksum and byte count; its response must contain one non-empty VersionId and `ChecksumSHA256` exactly equal to the submitted base64 SHA-256 before `stored`;
- every `pending -> stored -> accepted` condition, with no serving before exact-version head checksum/size/encryption/version validation and the exact SDK `3.392.1` STANDARD representation: `StorageClass`, `ArchiveStatus`, `Restore`, and `Expiration` are all absent from the HeadObject result;
- pre-send local/configuration/credential failure; definitive service rejection; `400`, `403`, `409`, `412`, throttling, `5xx`, timeout/disconnect; missing/contradictory result fields; head failure/mismatch; metadata failure after S3 success; process-death fixtures at every state boundary; exact quota retain/release behavior; and no blind same-key write/delete replay;
- `412` never promotes, overwrites, or deletes the pre-existing object even when its bytes match; a later authorized semantic attempt receives a new identifier, key and reservation;
- ambiguous write observation after local request termination through exactly one retries-disabled finite-timeout `ListObjectVersions` call with the fixed bucket, full-key Prefix, `MaxKeys: 2`, `ExpectedBucketOwner`, result-key equality, no truncation/next marker, or one non-empty latest exact version and no delete markers; empty listings remain reconciliation-required with quota retained because S3 supplies no maximum remote-completion bound; incident-only handling for every empty/sibling/multiple/marker/truncated/malformed/denied/ambiguous write result; broad-prefix IAM denial; exact-VersionId SDK Delete response plus one bounded exact-full-key ListObjectVersions observation, empty post-delete completion only under proved no-reuse/no-other-writer and strong delete/LIST consistency, one finite reconciled exact-VersionId Delete only after an ambiguous result returns the exact recorded version and Head validates it, one final list, incident ownership after every second non-empty/ambiguity/mismatch/denial, and quota release only after exact absence plus durable `deleted`; lifecycle/operator/restore drift; KMS rotation and retained earlier ciphertext; bounded selection, calls, time, output and cleanup;
- every Put's durable pending-row/key/reservation predecessor, post-Put metadata-transition failure and its bounded row-driven reconciliation; no broad-list discovery/adoption/deletion when an authoritative row is lost; the named database restore/incident policy for total row loss; and retained quota until authoritative recovery/disposition;
- denial before metadata lookup/signing, accepted-only lookup, exact version selection, no extra required signed header, maximum 300-second expiry, expected URL scheme/host/path and inclusive 4,096-byte cap including the selected temporary-credential form, fixed overrides, overflow failure, and exact empty application `303` headers;
- URL-only real GET with `X-Amz-SignedHeaders=host`, repeated/range-request residual behavior, no one-time/range-free/receipt claim, top-level-navigation CORS non-applicability, no direct-S3 `nosniff` claim, and the accepted narrow Contract version 13 exception; and
- the private authoritative record retains the raw/base64 SHA-256, but this reference classifies both as sensitive integrity metadata: no object key, checksum, client metadata, local path, credential, SDK/provider message/body, pre-signed URL or query string appears in public failures, terminal summaries, logs, traces, CI artifacts, application-owned browser telemetry, or deployment evidence.

Prove every quota dimension independently: total request bytes, one file's bytes, principal and tenant limits or explicit inapplicability, deployment object count and retained bytes, request rate, concurrency for upload/reconciliation/deletion/URL issuance, egress bytes/rate, S3/KMS/CloudTrail request cost, and storage/noncurrent-version cost. Concurrency fixtures prove the named atomic reserve/commit/release/reconcile operation, reservation retention throughout ambiguity, `failed_collision` release, and exact-deletion release; no count-then-act race is accepted.

Each permitted HTTP retry policy is explicit. If the application does not accept one bounded idempotency key, every permitted retry is a new authorized semantic upload with a new identifier, key and quota reservation; an ambiguous earlier outcome can therefore create a product-level duplicate that the named lookup/reconciler owns. If a bounded idempotency key is selected, record its representation, principal/tenant/action scope, retention horizon, concurrent reservation, exact replay response, ambiguous-outcome behavior and evidence. Neither choice reuses an S3 key.

The first profile proves that no S3 Lifecycle rule or action whose filter can cover `private-documents/v1/` exists. This includes current/noncurrent transitions, current/noncurrent expiration, expired-object delete-marker actions, and every other lifecycle mutation. The application first makes metadata non-serving and enters `deletion_pending`, then the sole deletion path deletes the recorded exact VersionId and verifies the outcome. Any lifecycle rule for an isolated test or unrelated prefix has a disjoint exact filter whose non-overlap is proved. An operator, lifecycle, replica, backup or restore mismatch that makes an accepted version missing, noncurrent, archived or otherwise reclassified moves affected metadata to `reconciliation_required` or the exact recorded unavailable state, never silently to `accepted` or `deleted`. The selected KMS lifecycle uses AWS-owned origin and 365-day automatic rotation; existing ciphertext remains under KMS-retained earlier backing material rather than being re-encrypted. Key disablement/scheduled deletion is denied to application roles, alarmed and owned, and cannot race retained policy accidentally.

The application never persists a PHP temporary path. Dated deployment evidence for the effective web-SAPI `upload_tmp_dir` proves its independent bounded stale-file selection, age/action rule, capacity alert, permissions and redaction owner; the S3 reconciler never attempts temporary-file cleanup.

## Required real-AWS and deployment evidence

The real test pins PHP 8.4.x, `aws/aws-sdk-php` `3.392.1`, `guzzlehttp/guzzle` `8.0.2`, S3 model `2006-03-01`, the explicit CurlHandler/GuzzleHandler composition, the required client options, the exact named credential provider, one commercial-region general-purpose bucket, virtual-hosted endpoint, and all request/result fields on which the application relies. It runs from the immutable selected deployment image; asserts ext-curl/JSON/PCRE/SimpleXML presence; records and compares the exact PHP, ext-curl, libcurl, TLS backend and supported-protocol tuple plus CA-bundle path/hash; disables environment proxy selection; and proves TLS peer and hostname verification. It exercises and records every selected provider-specific fetch/HTTP/timeout/retry/lifetime/refresh/skew/outage fact or explicit `N/A`, including fail-closed acquisition/refresh with no default chain or alternate source. It uses unique run-owned keys under a finite test prefix, fixed bounded fixtures, exact-version cleanup in `finally`, and one bounded stale-run reconciler because hard termination can skip cleanup. It rejects production credentials and broad list/delete authority. Missing configuration or AWS access fails; it does not switch transport or use a fake.

Exercise the exact bucket owner, versioning, Put `StorageClass: STANDARD`, Put response `ChecksumSHA256`, retained-object checksum, exact HeadObject absence of `StorageClass`, `ArchiveStatus`, `Restore`, and `Expiration`, SSE-KMS, `BucketKeyEnabled`, conditional-create, head, pre-sign, URL-only GET, range, expiration-boundary, wrong-owner, wrong-key, denied-action, exact-version delete, post-delete LIST consistency, ambiguous-delete observation/retry bound, and noncurrent-version behaviors. Pin the exact SDK result keys actually observed. KMS `DescribeKey` must return the exact configured ARN/account/region, `KeyManager: CUSTOMER`, `MultiRegion: false`, `KeySpec: SYMMETRIC_DEFAULT`, `KeyUsage: ENCRYPT_DECRYPT`, `Origin: AWS_KMS`, `Enabled: true`, `KeyState: Enabled`, and exactly `EncryptionAlgorithms: [SYMMETRIC_DEFAULT]`. `GetKeyRotationStatus` must return `KeyRotationEnabled: true`, `RotationPeriodInDays: 365`, and a valid dated next-rotation value. Prove the exact key policy, retained-ciphertext decrypt behavior across rotation, and disable/scheduled-deletion denial and alarm. Prove the finite IAM matrix: upload/validation `s3:PutObject` + `s3:GetObjectVersion`; pre-signing `s3:GetObjectVersion`; reconciliation prefix-constrained `s3:ListBucketVersions` + `s3:GetObjectVersion`; deletion `s3:DeleteObjectVersion` and an explicit `s3:DeleteObject` denial/absence, plus constrained list/read only when the same process owns observation; and exact proved KMS permissions for each. `ExpectedBucketOwner` is mandatory on server-executed Put/Head/ListObjectVersions/Delete but forbidden from the browser-navigation pre-signed Get command because that navigation cannot add its signed custom header. The pre-signed URL requires `X-Amz-SignedHeaders=host` and zero other required request headers.

Dated deployment evidence names the exact image digest, Composer lock, PHP/ext-curl/libcurl/TLS tuple, cURL protocols, CA-bundle path/origin/version/hash and update owner; account, region, no-dot DNS-compatible bucket, expected account identifier, the exact KMS DescribeKey/rotation/key-policy/disablement/scheduled-deletion facts above, identities and resource/action policies, Block Public Access settings, `BucketOwnerEnforced`, versioning exactly `Enabled` and never suspended, Requester Pays disabled, MFA Delete disabled, Object Lock/legal hold disabled/not applicable, S3 replication disabled, external backup/restore not applicable, non-TLS denial, endpoint/network boundary, exact credential-source and provider behavior or explicit `N/A`, Put `StorageClass: STANDARD` and the exact HeadObject absent-field representation, absence of every S3 Lifecycle rule or action whose filter can cover `private-documents/v1/`, disjoint exact filters for any test/unrelated rule, region/residency, every quota/capacity/cost threshold, monitoring/alarms, log and redirect-header scrubbing, incident/revocation limits, stale-run cleanup, and accountable owners. It selects CloudTrail S3 data events for `PutObject`, `HeadObject`, `ListObjectVersions`, `GetObject`, and `DeleteObject` under the exact prefix plus named management-event coverage, and records destination, retention, access, alert, cost and redaction owners. It contains no secret, object content, customer identifier, or pre-signed URL.

## Exact reviewed-source manifest shape

Copy this application-owned file byte-for-byte to `tools/amazon-s3-file-transfer-checker-manifest.json`, preserve its exact two-space indentation, key order and one trailing LF, then replace every placeholder with the reviewed non-secret boundary, project-relative source path, or lowercase SHA-256 before the first adopted gate. The checker reconstructs this one finite canonical representation after decoding and requires exact raw-byte equality, so duplicate member names, reordered keys and alternate formatting fail closed. The boundary pins the one allowed deployment region, bucket, expected account, prefix, KMS key, CA file and named credential source without storing credentials. The seven roles are finite and mandatory: `configuration` points to one hashed application PHP source containing those exact named non-secret values; `credential_provider` reads `$configuration->credentialSource`, owns the one explicit `CredentialProvider::memoize(` construction and resulting `$memoizedExplicitCredentialProvider`, and contains neither `CredentialProvider::defaultProvider(` nor `CredentialProvider::chain(`; and the other roles each point to the one concrete file that visibly owns that responsibility rather than a facade or registry. Provider-specific acquisition, transport, timeout, retry, lifetime, refresh, skew and outage semantics remain behavior, real-service and deployment evidence, with explicit `N/A` for mechanisms the named provider does not use. JSON is data-only: the checker never executes the manifest or reviewed sources.

```json
{
  "profile": "AMAZON_S3_ADR053",
  "boundary": {
    "region": "{{S3_REGION}}",
    "bucket": "{{S3_BUCKET}}",
    "expected_account_id": "{{S3_EXPECTED_ACCOUNT_ID}}",
    "prefix": "private-documents/v1/",
    "kms_key_arn": "{{S3_KMS_KEY_ARN}}",
    "ca_bundle_path": "{{S3_CA_BUNDLE_ABSOLUTE_PATH}}",
    "ca_bundle_sha256": "{{S3_CA_BUNDLE_SHA256}}",
    "credential_source": "{{S3_CREDENTIAL_SOURCE_NAME}}"
  },
  "sources": {
    "configuration": ["{{S3_CONFIGURATION_SOURCE}}", "{{S3_CONFIGURATION_SHA256}}"],
    "credential_provider": ["{{S3_CREDENTIAL_PROVIDER_SOURCE}}", "{{S3_CREDENTIAL_PROVIDER_SHA256}}"],
    "composition": ["{{S3_COMPOSITION_SOURCE}}", "{{S3_COMPOSITION_SHA256}}"],
    "upload": ["{{S3_UPLOAD_SOURCE}}", "{{S3_UPLOAD_SHA256}}"],
    "reconciliation": ["{{S3_RECONCILIATION_SOURCE}}", "{{S3_RECONCILIATION_SHA256}}"],
    "download": ["{{S3_DOWNLOAD_SOURCE}}", "{{S3_DOWNLOAD_SHA256}}"],
    "deletion": ["{{S3_DELETION_SOURCE}}", "{{S3_DELETION_SHA256}}"]
  }
}
```

## Exact application-owned checker shape

Copy this exact source to `tools/verify-amazon-s3-file-transfer-source.php`. It intentionally fails while the manifest contains a placeholder, a source changes without reviewed re-pinning, Composer does not pin the SDK/Guzzle/platform requirements exactly, explicit cURL-handler composition is absent, or the finite local exclusions appear. It emits only fixed bytes and never loads the AWS SDK, credentials, application bootstrap, or source files as PHP.

```php
<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function verifyAmazonS3FileTransferSource(): array
{
    $root = dirname(__DIR__);
    $manifestPath = __DIR__ . '/amazon-s3-file-transfer-checker-manifest.json';

    $manifestStat = lstat($manifestPath);

    if (!is_array($manifestStat)
        || ($manifestStat['mode'] & 0170000) !== 0100000
        || is_link($manifestPath)
    ) {
        return ['manifest_missing'];
    }

    $manifestSize = filesize($manifestPath);
    $manifestBytes = file_get_contents($manifestPath);

    if (!is_int($manifestSize) || $manifestSize < 1 || $manifestSize > 16384 || !is_string($manifestBytes)) {
        return ['manifest_invalid'];
    }

    $manifest = json_decode($manifestBytes, true, 32, JSON_THROW_ON_ERROR);

    if (!is_array($manifest)
        || array_keys($manifest) !== ['profile', 'boundary', 'sources']
        || $manifest['profile'] !== 'AMAZON_S3_ADR053'
        || !is_array($manifest['boundary'])
        || array_keys($manifest['boundary']) !== ['region', 'bucket', 'expected_account_id', 'prefix', 'kms_key_arn', 'ca_bundle_path', 'ca_bundle_sha256', 'credential_source']
        || !is_array($manifest['sources'])
        || array_keys($manifest['sources']) !== ['configuration', 'credential_provider', 'composition', 'upload', 'reconciliation', 'download', 'deletion']
    ) {
        return ['manifest_invalid'];
    }

    $boundary = $manifest['boundary'];

    if (!is_string($boundary['region'])
        || preg_match('/\A(?:af|ap|ca|eu|il|me|mx|sa|us)-(?:central|east|north|northeast|northwest|south|southeast|southwest|west)-[1-9][0-9]*\z/D', $boundary['region']) !== 1
        || !is_string($boundary['bucket'])
        || preg_match('/\A[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])\z/D', $boundary['bucket']) !== 1
        || filter_var($boundary['bucket'], FILTER_VALIDATE_IP) !== false
        || str_starts_with($boundary['bucket'], 'xn--')
        || str_starts_with($boundary['bucket'], 'sthree-')
        || str_starts_with($boundary['bucket'], 'amzn-s3-demo-')
        || str_ends_with($boundary['bucket'], '-s3alias')
        || str_ends_with($boundary['bucket'], '--ol-s3')
        || str_ends_with($boundary['bucket'], '--x-s3')
        || str_ends_with($boundary['bucket'], '--table-s3')
        || !is_string($boundary['expected_account_id'])
        || preg_match('/\A[0-9]{12}\z/D', $boundary['expected_account_id']) !== 1
        || $boundary['prefix'] !== 'private-documents/v1/'
        || !is_string($boundary['kms_key_arn'])
        || preg_match(
            '/\Aarn:aws:kms:' . preg_quote($boundary['region'], '/') . ':' . preg_quote($boundary['expected_account_id'], '/') . ':key\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D',
            $boundary['kms_key_arn'],
        ) !== 1
        || !is_string($boundary['ca_bundle_path'])
        || preg_match('/\A\/[A-Za-z0-9._\/-]{1,254}\z/D', $boundary['ca_bundle_path']) !== 1
        || str_contains($boundary['ca_bundle_path'], '//')
        || str_contains($boundary['ca_bundle_path'], '/../')
        || !is_string($boundary['ca_bundle_sha256'])
        || preg_match('/\A[a-f0-9]{64}\z/D', $boundary['ca_bundle_sha256']) !== 1
        || !is_string($boundary['credential_source'])
        || preg_match('/\A[A-Z][A-Z0-9_]{2,63}\z/D', $boundary['credential_source']) !== 1
    ) {
        return ['manifest_boundary_invalid'];
    }

    $canonicalManifestLines = [
        '{',
        '  "profile": "AMAZON_S3_ADR053",',
        '  "boundary": {',
    ];
    $boundaryNames = ['region', 'bucket', 'expected_account_id', 'prefix', 'kms_key_arn', 'ca_bundle_path', 'ca_bundle_sha256', 'credential_source'];

    foreach ($boundaryNames as $index => $name) {
        $encodedValue = json_encode($boundary[$name], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $suffix = $index === count($boundaryNames) - 1 ? '' : ',';
        $canonicalManifestLines[] = '    "' . $name . '": ' . $encodedValue . $suffix;
    }

    $canonicalManifestLines[] = '  },';
    $canonicalManifestLines[] = '  "sources": {';
    $manifestRoles = ['configuration', 'credential_provider', 'composition', 'upload', 'reconciliation', 'download', 'deletion'];

    foreach ($manifestRoles as $index => $role) {
        $encodedSource = json_encode($manifest['sources'][$role], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $suffix = $index === count($manifestRoles) - 1 ? '' : ',';
        $canonicalManifestLines[] = '    "' . $role . '": ' . $encodedSource . $suffix;
    }

    $canonicalManifestLines[] = '  }';
    $canonicalManifestLines[] = '}';
    $canonicalManifestBytes = implode("\n", $canonicalManifestLines) . "\n";

    if (!hash_equals($canonicalManifestBytes, $manifestBytes)) {
        return ['manifest_invalid'];
    }

    $failures = [];
    $seenPaths = [];
    $sourceBytesByRole = [];
    $combinedSource = '';

    foreach ($manifest['sources'] as $role => $source) {
        if (!is_array($source)
            || array_keys($source) !== [0, 1]
            || !is_string($source[0])
            || preg_match('/\\Asrc\/[A-Za-z0-9_\/-]+\.php\z/D', $source[0]) !== 1
            || str_contains($source[0], '//')
            || str_contains($source[0], '..')
            || isset($seenPaths[$source[0]])
            || !is_string($source[1])
            || preg_match('/\\A[a-f0-9]{64}\z/D', $source[1]) !== 1
        ) {
            $failures[] = $role . '_manifest_invalid';
            continue;
        }

        $seenPaths[$source[0]] = true;
        $absolutePath = $root . '/' . $source[0];
        $stat = lstat($absolutePath);

        if (!is_array($stat)
            || ($stat['mode'] & 0170000) !== 0100000
            || is_link($absolutePath)
        ) {
            $failures[] = $role . '_source_invalid';
            continue;
        }

        $size = filesize($absolutePath);

        if (!is_int($size) || $size < 1 || $size > 131072) {
            $failures[] = $role . '_source_invalid';
            continue;
        }

        $hash = hash_file('sha256', $absolutePath);
        $bytes = file_get_contents($absolutePath);

        if (!is_string($hash) || !hash_equals($source[1], $hash) || !is_string($bytes)) {
            $failures[] = $role . '_source_unreviewed';
            continue;
        }

        $sourceBytesByRole[$role] = $bytes;
        $combinedSource .= "\n" . $bytes;
    }

    foreach (['upload', 'reconciliation', 'deletion'] as $serverRole) {
        if (!isset($sourceBytesByRole[$serverRole])
            || !str_contains($sourceBytesByRole[$serverRole], "'ExpectedBucketOwner'")
        ) {
            $failures[] = 'expected_bucket_owner_missing';
            break;
        }
    }

    $serverOperationTokens = [
        'upload' => [
            '->putObject([',
            '->headObject([',
            "'StorageClass' => 'STANDARD'",
            "'ChecksumSHA256'",
            "'ChecksumMode' => 'ENABLED'",
        ],
        'reconciliation' => [
            '->listObjectVersions([',
            '->headObject([',
            "'Prefix'",
            "'MaxKeys' => 2",
        ],
        'deletion' => [
            '->deleteObject([',
            '->listObjectVersions([',
            '->headObject([',
            "'VersionId'",
            "'Prefix'",
            "'MaxKeys' => 2",
        ],
    ];

    foreach ($serverOperationTokens as $role => $tokens) {
        foreach ($tokens as $token) {
            if (!isset($sourceBytesByRole[$role])
                || !str_contains($sourceBytesByRole[$role], $token)
            ) {
                $failures[] = $role . '_operation_shape_invalid';
                break 2;
            }
        }
    }

    $expectedStandardHeadValidationBlock = <<<'PHP'
if ($headResult->hasKey('StorageClass')
    || $headResult->hasKey('ArchiveStatus')
    || $headResult->hasKey('Restore')
    || $headResult->hasKey('Expiration')
) {
    throw new UnexpectedValueException('S3 retained object is not in the required STANDARD state.');
}
PHP;

    if (!isset($sourceBytesByRole['upload'])
        || !str_contains($sourceBytesByRole['upload'], $expectedStandardHeadValidationBlock)
    ) {
        $failures[] = 'upload_standard_head_shape_invalid';
    }

    $configurationKeys = [
        'region',
        'bucket',
        'expected_account_id',
        'prefix',
        'kms_key_arn',
        'ca_bundle_path',
        'ca_bundle_sha256',
        'credential_source',
    ];

    foreach ($configurationKeys as $configurationKey) {
        $configurationValue = $boundary[$configurationKey];

        if (!is_string($configurationValue)) {
            $failures[] = 'configuration_boundary_invalid';
            break;
        }

        $expectedLiteral = "'" . $configurationKey . "' => '" . $configurationValue . "'";

        if (!isset($sourceBytesByRole['configuration'])
            || !str_contains($sourceBytesByRole['configuration'], $expectedLiteral)
        ) {
            $failures[] = 'configuration_boundary_invalid';
            break;
        }
    }

    $credentialProviderTokens = [
        '$configuration->credentialSource',
        'CredentialProvider::memoize(',
        '$memoizedExplicitCredentialProvider',
    ];

    foreach ($credentialProviderTokens as $token) {
        if (!isset($sourceBytesByRole['credential_provider'])
            || !str_contains($sourceBytesByRole['credential_provider'], $token)
        ) {
            $failures[] = 'credential_provider_shape_invalid';
            break;
        }
    }

    foreach (['CredentialProvider::defaultProvider(', 'CredentialProvider::chain('] as $forbiddenProviderToken) {
        if (isset($sourceBytesByRole['credential_provider'])
            && str_contains($sourceBytesByRole['credential_provider'], $forbiddenProviderToken)
        ) {
            $failures[] = 'credential_provider_fallback_present';
            break;
        }
    }

    if (!isset($sourceBytesByRole['download'])
        || str_contains($sourceBytesByRole['download'], "'ExpectedBucketOwner'")
        || !str_contains($sourceBytesByRole['download'], "'X-Amz-SignedHeaders'")
        || !str_contains($sourceBytesByRole['download'], "'host'")
    ) {
        $failures[] = 'presigned_download_shape_invalid';
    }

    $composerPath = $root . '/composer.json';
    $lockPath = $root . '/composer.lock';
    $composerStat = lstat($composerPath);
    $lockStat = lstat($lockPath);

    if (!is_array($composerStat)
        || ($composerStat['mode'] & 0170000) !== 0100000
        || is_link($composerPath)
        || !is_array($lockStat)
        || ($lockStat['mode'] & 0170000) !== 0100000
        || is_link($lockPath)
    ) {
        $failures[] = 'composer_files_invalid';
    } else {
        $composerSize = filesize($composerPath);
        $lockSize = filesize($lockPath);

        if (!is_int($composerSize)
            || $composerSize < 1
            || $composerSize > 1048576
            || !is_int($lockSize)
            || $lockSize < 1
            || $lockSize > 8388608
        ) {
            $failures[] = 'composer_files_invalid';
            return $failures;
        }

        $composerBytes = file_get_contents($composerPath);
        $lockBytes = file_get_contents($lockPath);

        if (!is_string($composerBytes) || !is_string($lockBytes)) {
            $failures[] = 'composer_files_invalid';
            return $failures;
        }

        $composer = json_decode($composerBytes, true, 64, JSON_THROW_ON_ERROR);
        $lock = json_decode($lockBytes, true, 64, JSON_THROW_ON_ERROR);
        $requirements = is_array($composer) && isset($composer['require']) && is_array($composer['require'])
            ? $composer['require']
            : [];
        $expectedRequirements = [
            'php' => '8.4.*',
            'aws/aws-sdk-php' => '3.392.1',
            'ext-curl' => '*',
            'ext-json' => '*',
            'ext-pcre' => '*',
            'ext-simplexml' => '*',
            'guzzlehttp/guzzle' => '8.0.2',
        ];

        foreach ($expectedRequirements as $package => $version) {
            if (($requirements[$package] ?? null) !== $version) {
                $failures[] = 'composer_requirements_invalid';
                break;
            }
        }

        $lockedVersions = [
            'aws/aws-sdk-php' => [],
            'guzzlehttp/guzzle' => [],
        ];

        if (is_array($lock) && isset($lock['packages']) && is_array($lock['packages'])) {
            foreach ($lock['packages'] as $package) {
                if (!is_array($package) || !is_string($package['name'] ?? null)) {
                    continue;
                }

                $name = $package['name'];

                if (array_key_exists($name, $lockedVersions)) {
                    $lockedVersions[$name][] = $package['version'] ?? null;
                }
            }
        }

        if ($lockedVersions !== [
            'aws/aws-sdk-php' => ['3.392.1'],
            'guzzlehttp/guzzle' => ['8.0.2'],
        ]) {
            $failures[] = 'composer_lock_invalid';
        }

        $platform = is_array($lock) && isset($lock['platform']) && is_array($lock['platform'])
            ? $lock['platform']
            : [];

        foreach (array_diff_key($expectedRequirements, $lockedVersions) as $package => $version) {
            if (($platform[$package] ?? null) !== $version) {
                $failures[] = 'composer_lock_platform_invalid';
                break;
            }
        }

        $scripts = is_array($composer) && isset($composer['scripts']) && is_array($composer['scripts'])
            ? $composer['scripts']
            : [];
        $expectedScripts = [
            'profile' => 'phpthis check',
            'file-transfers:s3:source' => 'php tools/verify-amazon-s3-file-transfer-source.php',
            'file-transfers:s3:behavior' => 'php tests/amazon-s3-file-transfers.php',
            'file-transfers:s3:real' => 'php tests/amazon-s3-file-transfers-real-aws.php',
            'file-transfers:s3:deployment' => 'php tools/verify-amazon-s3-file-transfer-deployment.php',
            'file-transfers:s3:verify' => [
                '@file-transfers:s3:source',
                '@file-transfers:s3:behavior',
                '@file-transfers:s3:real',
                '@file-transfers:s3:deployment',
            ],
            'test:application' => 'php tests/run.php',
            'test' => [
                '@file-transfers:s3:verify',
                '@test:application',
            ],
            'check' => [
                '@profile',
                '@test',
            ],
        ];

        foreach ($expectedScripts as $name => $expected) {
            if (($scripts[$name] ?? null) !== $expected) {
                $failures[] = 'composer_gate_invalid';
                break;
            }
        }
    }

    $expectedCompositionBlock = <<<'PHP'
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
PHP;

    if (!isset($sourceBytesByRole['composition'])
        || !str_contains($sourceBytesByRole['composition'], $expectedCompositionBlock)
    ) {
        $failures[] = 'http_transport_shape_invalid';
    }

    $requiredSourceTokens = [
        "'version' => '2006-03-01'",
        "'ignore_configured_endpoint_urls' => true",
        "'request_checksum_calculation' => 'when_supported'",
        "'response_checksum_validation' => 'when_supported'",
        "'retries' => 0",
        "'ExpectedBucketOwner'",
        "'IfNoneMatch' => '*'",
        "'ChecksumAlgorithm' => 'SHA256'",
        "'ChecksumMode' => 'ENABLED'",
        "'MaxKeys' => 2",
        "'ServerSideEncryption' => 'aws:kms'",
        "'BucketKeyEnabled' => false",
        'private-documents/v1/',
        "'VersionId'",
    ];

    foreach ($requiredSourceTokens as $token) {
        if (!str_contains($combinedSource, $token)) {
            $failures[] = 'required_source_token_missing';
            break;
        }
    }

    $forbiddenSourceTokens = [
        "'endpoint' =>",
        'Aws\\default_http_handler',
        'GuzzleHttp\\Handler\\StreamHandler',
        'GuzzleHttp\\Utils::chooseHandler',
        'StorageInterface',
        'StorageFacade',
        'DiskRegistry',
        'S3ClientRegistry',
        'createPresignedPost',
        'MultipartUploader',
    ];

    foreach ($forbiddenSourceTokens as $token) {
        if (str_contains($combinedSource, $token)) {
            $failures[] = 'forbidden_source_token_present';
            break;
        }
    }

    return $failures;
}

(static function (): void {
    $amazonS3VerificationState = new class {
        public bool $completed = false;
    };

    register_shutdown_function(
        static function () use ($amazonS3VerificationState): void {
            if ($amazonS3VerificationState->completed) {
                return;
            }

            fwrite(STDERR, "AMAZON S3 FILE TRANSFER SOURCE VERIFY FAIL\n");
            exit(1);
        },
    );

    set_error_handler(
        static function (int $severity): never {
            throw new ErrorException('Amazon S3 source verification warning.', 0, $severity);
        },
    );

    try {
        $failures = verifyAmazonS3FileTransferSource();
    } catch (Throwable) {
        $failures = ['verification_failed'];
    } finally {
        restore_error_handler();
    }

    if ($failures !== []) {
        $amazonS3VerificationState->completed = true;
        fwrite(STDERR, "AMAZON S3 FILE TRANSFER SOURCE VERIFY FAIL\n");
        exit(1);
    }

    $passBytes = "AMAZON S3 FILE TRANSFER SOURCE VERIFY PASS\n";
    $written = fwrite(STDOUT, $passBytes);

    if ($written !== strlen($passBytes)) {
        exit(1);
    }

    $amazonS3VerificationState->completed = true;
})();
```

The exact-hash manifest makes the finite token checks review aids rather than a parser or proof that the PHP executes as intended. Its boundary plus hashed configuration and credential-provider sources pins the reviewed non-secret names, allowed values, explicit memoization and absence of the two broad SDK provider combinators; it does not prove those bytes were deployed, that provider-specific acquisition/refresh behavior is correct, or that AWS owns the named resources. Canonical raw-byte comparison rejects duplicate members only in this fixed manifest. `composer.json` and `composer.lock` are bounded, regular, non-symlink files whose decoded effective dependency and script values are checked, but this small checker does not separately detect duplicate JSON members in those Composer-owned files; the application's ordinary Composer validation and review own that additional limitation. The checker rejects a source that is a symlink when observed, but its path-based `lstat`/read/hash sequence assumes an exclusive-writer stable source tree for the bounded run and does not claim resistance to a concurrent path-swap attack. CI checks out immutable reviewed bytes and prevents concurrent writers. The IIFE-local completion state and shutdown guard turn a premature `exit(0)` or `die` without a message into the fixed failure and exit `1`; mutation evidence also proves that `$GLOBALS` cannot flip that local state. Failure before PHP starts, engine-fatal output, direct arbitrary writes, `SIGKILL`, another hard termination, or a partial terminal write remains owned by the outer CI process boundary and cannot become a pass. The required `ExpectedBucketOwner` token does not authorize it in the pre-signed browser request; behavior and real-service evidence must prove it appears only on server-executed Put/Head/ListObjectVersions/Delete and that the pre-signed command requires only `host`. Add specific application mutations for boundary values and reserved bucket forms, single-Region UUID KMS grammar, per-role operation shapes, exact Put STANDARD plus absent Head storage/archive fields, provider memoization/default-chain exclusion, `s3:DeleteObject` denial/deletion consistency, premature exit/global bypass, and that signed-header role distinction. Do not broaden the checker into recursive scanning, reflection, AST policy, runtime discovery, an AWS client call, or a generic storage rule.

## Exact Composer gate wiring

```json
{
  "scripts": {
    "profile": "phpthis check",
    "file-transfers:s3:source": "php tools/verify-amazon-s3-file-transfer-source.php",
    "file-transfers:s3:behavior": "php tests/amazon-s3-file-transfers.php",
    "file-transfers:s3:real": "php tests/amazon-s3-file-transfers-real-aws.php",
    "file-transfers:s3:deployment": "php tools/verify-amazon-s3-file-transfer-deployment.php",
    "file-transfers:s3:verify": [
      "@file-transfers:s3:source",
      "@file-transfers:s3:behavior",
      "@file-transfers:s3:real",
      "@file-transfers:s3:deployment"
    ],
    "test:application": "php tests/run.php",
    "test": [
      "@file-transfers:s3:verify",
      "@test:application"
    ],
    "check": [
      "@profile",
      "@test"
    ]
  }
}
```

The literal chain is the accepted recommendation: `file-transfers:s3:verify` aggregates all four evidence owners, `test` invokes it, and `check` invokes `test` after the installed profile. An application may preserve another established behavior-test runner name, but its `.ai/file-transfers.md` and `.ai/testing.md` record and prove an equivalent unskippable chain. No script is an HTTP/runtime command or PHPThis API.

## Framework reference proof

PHPThis proves only that these accepted packaged pages, template fields, and exact code blocks remain internally reachable and mechanically coherent. A transient synthetic fixture may replace the fail-closed manifest placeholders with one syntactically valid non-secret boundary, create the seven fixed source roles, pin their hashes, supply a synthetic Composer lock, and exercise finite positive and negative mutations without loading the AWS SDK or contacting AWS. It remains marked `NOT_APPLICABLE(FILE_TRANSFER)` and `REFERENCE_ONLY(AMAZON_S3_FILE_TRANSFER_VERIFICATION_STRUCTURE)`.

That synthetic pass proves no upload, Amazon S3 service, SDK request, bucket, account, IAM, KMS, encryption, checksum, conditional write, versioning, pre-signed URL, delivery, deletion, deployment, or production fact. Only deliberate profile selection plus complete application-owned evidence can support an adoption claim.
