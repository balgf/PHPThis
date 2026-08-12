<?php

declare(strict_types=1);

/**
 * @param array<string, string> $environment
 * @return non-empty-string
 */
function proveInstalledProtectedFileTransferReference(
    string $project,
    string $installedFramework,
    array $environment,
): string {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/docs/file-transfers/security.md' => [
            'For protected upload, keep `authenticate -> resolve tenant when applicable -> authorize upload -> validate CSRF when applicable -> rate/concurrency admission -> atomic quota reservation -> storage` visible and fail before every later step.',
            '`OPAQUE_BYTES` uses fixed code-owned stored/download names and only an `application/octet-stream` attachment with `nosniff`, explicit cache, and recorded authentication/authorization; it is not content-safety certification.',
            '`INSPECTED_CONTENT` uses byte-derived classification with a finite allowlist, pinned parser or scanner, process isolation, time, memory, nesting and decompression bounds, update ownership, quarantine and failure policy, and executable malicious and malformed fixtures.',
            '`SameSite` and an opaque identifier are not permission.',
            'PHPThis supplies no generic authentication, tenant, CSRF, quota, scanner, storage, or lifecycle API.',
        ],
        $installedFramework . '/docs/file-transfers/storage-ownership.md' => [
            'Quota checks are not a count-then-write race hidden behind a helper.',
            'authorizes the named download before lookup when existence is confidential',
            'The request identity has no authority or operational need to create, chmod, chown, repair, or replace the durable root.',
        ],
        $installedFramework . '/docs/file-transfers/testing.md' => [
            'Protected application evidence proves exact `authenticate -> resolve tenant when applicable -> authorize upload -> validate CSRF when applicable -> rate/concurrency admission -> atomic quota reservation -> storage` order',
            'The repository proof deliberately shows that an equal-size regular replacement and a symlink to a same-size regular target are emitted.',
            'Content evidence names exactly `OPAQUE_BYTES` or `INSPECTED_CONTENT`.',
            'Exercise exact-limit and maximum-plus-one requests at the owner expected to reject them.',
            'Static installed-context proof may require the authoritative application adoption record and its finite markers, routing, and complete project-gate link.',
        ],
        $installedFramework . '/templates/application/.ai/file-transfers.md' => [
            'This is the application\'s single authoritative file-transfer policy.',
            'authenticate -> resolve tenant when applicable -> authorize upload -> validate CSRF when applicable -> rate/concurrency admission -> atomic quota reservation -> storage',
            'Deployment-precreated durable root before HTTP handling',
            'Static context checks prove only that this authoritative record and its finite markers exist and route into the complete application gate.',
        ],
        $project . '/.ai/file-transfers.md' => [
            'NOT_APPLICABLE(FILE_TRANSFER)',
            'The starter accepts no upload and returns no file download.',
            'Keep movement, quota accounting, inspection, cleanup, retention, deletion, and authorization in concrete application operations.',
        ],
    ];
    requireInstalledArtifactMarkers($artifactMarkers, 'protected file-transfer reference');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    $proofPath = $project . '/installed-protected-file-transfer-proof.php';
    $adoptionPath = $project . '/.ai/file-transfers.md';
    $originalAdoptionRecord = file_get_contents($adoptionPath);

    if (!is_string($originalAdoptionRecord) || is_link($adoptionPath)) {
        throw new RuntimeException('The starter file-transfer adoption record must be a regular readable file.');
    }

    $protectedAdoptionRecord = <<<'MD'
# Installed synthetic protected document-transfer adoption

ADOPTED(FILE_TRANSFER:protected_document_upload,protected_document_download)

- Sole owner and scope: this transient application record owns only the synthetic protected document upload and download exercised by `installed-protected-file-transfer-proof.php`; it is restored byte-for-byte after the proof.
- Routes and input: `POST /accounts/{account_id:positive-int}/document-files` accepts exactly one PHP-normalized `document` `RequestUpload`; ordinary body empty; exact inclusive file size 1 through 8 bytes. PHP-normalized duplicate raw scalar `document` parts are accepted as the one exposed value in this synthetic operation; raw multiplicity is not observable. `GET /accounts/{account_id:positive-int}/document-files/{file_id:token}` returns one authorized document.
- Pre-PHP and transport: deployed proxy/web-server/web-SAPI request, buffering, timeout, rate/concurrency, `file_uploads`, `upload_max_filesize`, `post_max_size`, `max_file_uploads`, `max_multipart_body_parts`, and `upload_tmp_dir` values are `NOT_PROVED_HERE`; the repository real-SAPI fixture owns its exact local settings and exact/overflow requests. This synthetic adaptation begins from one already-normalized typed upload and makes no production-ingress claim.
- Request policy: cookie `session=synthetic`; principal 7; tenant account 42; named actions `document.upload` and `document.download`; upload order `authenticate -> resolve tenant -> authorize upload -> validate x-csrf-token -> rate admission -> concurrency lease -> pending upload -> atomic quota reservation -> storage`; download authorizes before confidential lookup. SameSite, route tokens, and identifiers are not permission.
- Admission and quota: one sequential in-process state machine admits at most 2 uploads per synthetic finite window and models 1 concurrency lease; release occurs after every post-admission success/failure. Its reservation owns exact object identity, with at most 8 committed bytes, 4 retained files, and one pending reservation; minimum 1 byte is also enforced independently so zero-byte uploads cannot consume identifiers/inodes without accounting. The four-file limit is proved while four byte-capacity units remain. This models the required atomic ownership and concurrency semantics but proves no cross-request/process atomicity, clock, lock, or production topology.
- Content posture and response: `OPAQUE_BYTES`; fixed stored representation, fixed `download.bin`, `application/octet-stream` attachment, `nosniff`, `private, no-store`, `Accept-Ranges: none`, full `200`, and no content-safety claim. Scanner rejection and scanner timeout are `NOT_APPLICABLE` and are not simulated.
- Storage authority: the request-scoped synthetic temporary path and hostile client metadata never enter the retained record, logs, jobs, traces, response, or terminal evidence. The process-local array proof owns no actual filesystem root; dedicated effective web-SAPI temporary-root authority and deployment-precreated durable-root owner/group/ACL/modes/mount/capacity/inode/topology facts are `NOT_PROVED_HERE` and remain deployment evidence. One synthetic application-owned retained-record operation generates identity and binds it to tenant 42, principal 7, and the named upload/download actions.
- Lifecycle and delivery: injected labels for temporary-root, disk-full, inode-exhausted, permission, native-false move, chmod, and cleanup select fail-closed in-process outcomes; they do not reproduce kernel or filesystem failures. Cleanup failure retains one process-local reconciliation identity, deletes its retained array entry idempotently, then releases exact accounting. No process crash, durable recovery, response ambiguity, retry/idempotency key, or cross-process lifecycle is simulated. Retention, expiry, ordinary deletion, replicas/backups/restores, legal hold, egress quota, and incident automation are `NOT_APPLICABLE` to the process-local transient bytes; production adopters must record their selected owners. The returned in-memory body is not `LocalFileBody` evidence and claims no path-only emission guarantee, but any adopted current path-only response must keep its selected pathname and bytes immutable under exclusive-writer authority from authorized selection through completed emission; identity-bound/open-handle/different response primitives require a separately accepted decision.
- Failures and evidence: finite generic private/no-store 401/403/404/409/429/500 responses and one code-only reconciliation signal redact client filename/media, temporary and durable paths, retained identifier/content, cookie/CSRF values, scanner details, and internal failures. The installed checker and transient behavior program are the complete synthetic gate.
- Explicit proof limits: repository real-SAPI example evidence separately owns PHP upload provenance, actual byte count, exact accepted/overflow SAPI requests, modes, hashes, and emission. This sequential synthetic record proves no deployed proxy/web-server settings, actual filesystem authority, production identity provider, production rate clock, cross-request/process atomicity or concurrency, durable crash recovery, response ambiguity, external scanner, backup, replica, legal-hold, or network-delivery behavior.

No framework auth, tenant, CSRF, admission, quota, scanner, storage, lifecycle, or checker API is adopted.
MD;

    if (file_exists($proofPath) || is_link($proofPath)) {
        throw new RuntimeException('The protected file-transfer proof path already exists.');
    }

    try {
        writeFile($adoptionPath, $protectedAdoptionRecord . "\n");
        writeFile($proofPath, installedProtectedFileTransferProofProgram());

        $lintResult = runProcess([PHP_BINARY, '-l', $proofPath], $project, $environment);
        requireExactProcessResult(
            $lintResult,
            0,
            "No syntax errors detected in {$proofPath}\n",
            '',
            'The installed protected file-transfer proof did not pass PHP syntax checking.',
        );

        $profileResult = runProcess(
            [$project . '/vendor/bin/phpthis', 'check'],
            $project,
            $environment,
        );
        requireSuccess(
            $profileResult,
            'The installed protected file-transfer proof failed the maximum consumer profile.',
        );
        requireOutputContains($profileResult, 'PASS PHPThis application check');

        $proofResult = runProcess(
            [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', $proofPath],
            $project,
            $environment,
        );
        requireExactProcessResult(
            $proofResult,
            0,
            "PASS installed protected file-transfer application adaptation\n",
            '',
            'The installed protected file-transfer behavior proof changed or emitted diagnostics.',
        );
    } finally {
        $proofCleanupFailure = null;

        try {
            if (is_file($proofPath) && !unlink($proofPath)) {
                throw new RuntimeException('Unable to remove the protected file-transfer proof.');
            }

            if (file_exists($proofPath) || is_link($proofPath)) {
                throw new RuntimeException('Protected file-transfer proof cleanup did not restore the consumer.');
            }
        } catch (Throwable $failure) {
            $proofCleanupFailure = $failure;
        } finally {
            writeFile($adoptionPath, $originalAdoptionRecord);

            $restoredAdoptionRecord = file_get_contents($adoptionPath);

            if (
                !is_string($restoredAdoptionRecord)
                || $restoredAdoptionRecord !== $originalAdoptionRecord
            ) {
                throw new RuntimeException(
                    'Protected file-transfer proof did not restore the starter adoption record exactly.',
                );
            }
        }

        if ($proofCleanupFailure instanceof Throwable) {
            throw $proofCleanupFailure;
        }
    }

    fwrite(STDOUT, "PASS installed protected file-transfer reference\n");

    return 'installed-protected-file-transfer-reference-proved';
}

/** @return non-empty-string */
function installedProtectedFileTransferProofProgram(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\Request;
use PHPThis\Http\RequestUpload;
use PHPThis\Http\RequestUploadError;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\PathParameters;

require __DIR__ . '/vendor/autoload.php';

final readonly class InstalledFilePrincipal
{
    public function __construct(public int $id)
    {
    }
}

final readonly class InstalledFileTenant
{
    public function __construct(public int $accountId)
    {
    }
}

final class InstalledFileUnauthenticated extends RuntimeException
{
}

final class InstalledFileForbidden extends RuntimeException
{
}

final class InstalledFileCrossTenant extends RuntimeException
{
}

final class InstalledFileCsrfRejected extends RuntimeException
{
}

final class InstalledFileRateExceeded extends RuntimeException
{
}

final class InstalledFileConcurrencyExceeded extends RuntimeException
{
}

final class InstalledFileQuotaExceeded extends RuntimeException
{
}

final class InstalledFileStorageUnavailable extends RuntimeException
{
}

final class InstalledFileUnexpectedPolicyFailure extends RuntimeException
{
}

final class InstalledFileCleanupRequired extends RuntimeException
{
    public function __construct(public readonly string $identifier)
    {
        parent::__construct('Document cleanup requires reconciliation.');
    }
}

final class InstalledFileNotFound extends RuntimeException
{
}

final class InstalledFileTrace
{
    /** @var list<string> */
    private array $steps = [];

    /** @var list<string> */
    private array $operationalCodes = [];

    public function step(string $step): void
    {
        $this->steps[] = $step;
    }

    public function operational(string $code): void
    {
        $this->operationalCodes[] = $code;
    }

    /** @return list<string> */
    public function steps(): array
    {
        return $this->steps;
    }

    /** @return list<string> */
    public function operationalCodes(): array
    {
        return $this->operationalCodes;
    }
}

final readonly class InstalledFileReservation
{
    public function __construct(
        public string $token,
        public int $accountId,
        public int $bytes,
    ) {
    }
}

final readonly class InstalledFileReconciliationRecord
{
    public function __construct(
        public string $identifier,
        public InstalledFileReservation $reservation,
    ) {
    }
}

final class InstalledDocumentQuota
{
    private const int MAXIMUM_TENANT_BYTES = 8;

    private const int MAXIMUM_TENANT_FILES = 4;

    private int $committedBytes = 0;

    private int $committedFiles = 0;

    private ?InstalledFileReservation $pending = null;

    private bool $reconciliationRequired = false;

    private int $proofObservations = 0;

    public function reserve(int $accountId, int $bytes): InstalledFileReservation
    {
        if ($this->pending !== null) {
            throw new InstalledFileConcurrencyExceeded('A document reservation is already pending.');
        }

        if (
            $bytes < 1
            || $this->committedFiles >= self::MAXIMUM_TENANT_FILES
            || $bytes > self::MAXIMUM_TENANT_BYTES - $this->committedBytes
        ) {
            throw new InstalledFileQuotaExceeded('Document quota is unavailable.');
        }

        $reservation = new InstalledFileReservation(
            bin2hex(random_bytes(16)),
            $accountId,
            $bytes,
        );
        $this->pending = $reservation;

        return $reservation;
    }

    public function commit(InstalledFileReservation $reservation): void
    {
        $this->requireOwner($reservation);
        $this->committedBytes += $reservation->bytes;
        $this->committedFiles++;
        $this->pending = null;
    }

    public function release(InstalledFileReservation $reservation): void
    {
        $this->requireOwner($reservation);
        $this->pending = null;
    }

    public function requireReconciliation(InstalledFileReservation $reservation): void
    {
        $this->requireOwner($reservation);
        $this->reconciliationRequired = true;
    }

    public function reconcileDeleted(
        InstalledFileReservation $reservation,
        InstalledDocumentStorage $storage,
        string $identifier,
    ): void
    {
        $this->requireOwner($reservation);

        if ($storage->contains($identifier)) {
            throw new LogicException('Durable document bytes must be deleted before quota release.');
        }

        $this->pending = null;
        $this->reconciliationRequired = false;
    }

    public function committedBytes(): int
    {
        return $this->committedBytes;
    }

    public function committedFiles(): int
    {
        return $this->committedFiles;
    }

    /** @return array{committed_bytes: int, committed_files: int, pending: bool} */
    public function proofState(): array
    {
        $this->proofObservations++;

        return [
            'committed_bytes' => $this->committedBytes,
            'committed_files' => $this->committedFiles,
            'pending' => $this->pending !== null,
        ];
    }

    public function pending(): bool
    {
        return $this->pending !== null;
    }

    public function reconciliationRequired(): bool
    {
        return $this->reconciliationRequired;
    }

    public function pendingReservation(): ?InstalledFileReservation
    {
        return $this->pending;
    }

    private function requireOwner(InstalledFileReservation $reservation): void
    {
        if ($this->pending !== $reservation) {
            throw new LogicException('Document reservation owner is stale.');
        }
    }
}

final readonly class InstalledPendingDocumentUpload
{
    private const int MAXIMUM_FILE_BYTES = 8;

    private function __construct(
        public string $temporaryPath,
        public int $reportedSizeBytes,
    ) {
    }

    /** @param array<string, RequestUpload> $uploads */
    public static function fromUploads(array $uploads): self
    {
        if (array_keys($uploads) !== ['document']) {
            throw new InstalledFileForbidden('The document upload field is invalid.');
        }

        $upload = $uploads['document'];

        if ($upload->error !== RequestUploadError::Success) {
            throw new InstalledFileStorageUnavailable('The PHP upload is unavailable.');
        }

        if (
            $upload->reportedSizeBytes < 1
            || $upload->reportedSizeBytes > self::MAXIMUM_FILE_BYTES
        ) {
            throw new InstalledFileQuotaExceeded('The document file limit is unavailable.');
        }

        return new self($upload->temporaryPath, $upload->reportedSizeBytes);
    }
}

final class InstalledDocumentStorage
{
    /**
     * @var array<string, array{
     *     account_id: int,
     *     owner_principal_id: int,
     *     upload_action: string,
     *     download_action: string,
     *     bytes: string
     * }>
     */
    private array $files = [];

    private string $nextWriteFailure = 'none';

    public int $writeCalls = 0;

    public int $lookupCalls = 0;

    public int $reconcileDeleteCalls = 0;

    private int $proofObservations = 0;

    public function failNextWriteAt(string $failure): void
    {
        $this->nextWriteFailure = $failure;
    }

    public function store(
        int $accountId,
        int $ownerPrincipalId,
        InstalledPendingDocumentUpload $pending,
    ): string {
        $this->writeCalls++;
        $failure = $this->nextWriteFailure;
        $this->nextWriteFailure = 'none';

        if (
            in_array(
                $failure,
                [
                    'temporary_root_unavailable',
                    'durable_disk_full',
                    'durable_inode_exhausted',
                    'durable_permission_denied',
                    'move_native_false',
                ],
                true,
            )
        ) {
            // Native false is one selected recovery outcome; no errno is inferred from it.
            throw new InstalledFileStorageUnavailable(
                'Document durable storage is unavailable at /private/durable/secret.',
            );
        }

        $identifier = bin2hex(random_bytes(16));
        $this->files[$identifier] = [
            'account_id' => $accountId,
            'owner_principal_id' => $ownerPrincipalId,
            'upload_action' => 'document.upload',
            'download_action' => 'document.download',
            'bytes' => str_repeat('B', $pending->reportedSizeBytes),
        ];

        if ($failure === 'chmod_cleanup_success') {
            unset($this->files[$identifier]);
            throw new InstalledFileStorageUnavailable('Document permissions could not be finalized.');
        }

        if ($failure === 'chmod_cleanup_failure') {
            throw new InstalledFileCleanupRequired($identifier);
        }

        return $identifier;
    }

    public function read(
        int $accountId,
        int $principalId,
        string $namedAction,
        string $identifier,
    ): string {
        $this->lookupCalls++;
        $record = $this->files[$identifier] ?? null;

        if (
            !is_array($record)
            || $record['account_id'] !== $accountId
            || $record['owner_principal_id'] !== $principalId
            || $record['upload_action'] !== 'document.upload'
            || $record['download_action'] !== $namedAction
        ) {
            throw new InstalledFileNotFound('Document file was not found.');
        }

        return $record['bytes'];
    }

    public function reconcileDelete(string $identifier): void
    {
        $this->reconcileDeleteCalls++;
        unset($this->files[$identifier]);
    }

    public function retainedIdentifierForProof(): string
    {
        $identifier = array_key_first($this->files);

        if (!is_string($identifier)) {
            throw new LogicException('A retained proof document is unavailable.');
        }

        return $identifier;
    }

    public function fileCount(): int
    {
        return count($this->files);
    }

    public function contains(string $identifier): bool
    {
        return isset($this->files[$identifier]);
    }

    public function serializedRecordsForProof(): string
    {
        return json_encode($this->files, JSON_THROW_ON_ERROR);
    }

    /** @return array{writes: int, lookups: int, retained: int, reconciliation_deletes: int} */
    public function proofState(): array
    {
        $this->proofObservations++;

        return [
            'writes' => $this->writeCalls,
            'lookups' => $this->lookupCalls,
            'retained' => count($this->files),
            'reconciliation_deletes' => $this->reconcileDeleteCalls,
        ];
    }
}

final class InstalledDocumentReconciliation
{
    private ?InstalledFileReconciliationRecord $pending = null;

    public function __construct(private InstalledFileTrace $trace)
    {
    }

    public function retain(string $identifier, InstalledFileReservation $reservation): void
    {
        if ($this->pending !== null) {
            throw new LogicException('A document reconciliation is already pending.');
        }

        $this->pending = new InstalledFileReconciliationRecord($identifier, $reservation);
    }

    public function run(InstalledDocumentStorage $storage, InstalledDocumentQuota $quota): void
    {
        $pending = $this->pending;

        if (!$pending instanceof InstalledFileReconciliationRecord) {
            return;
        }

        $this->trace->step('reconciliation_delete');
        $storage->reconcileDelete($pending->identifier);
        $this->trace->step('reconciliation_release');
        $quota->reconcileDeleted($pending->reservation, $storage, $pending->identifier);
        $this->pending = null;
    }

    public function pending(): bool
    {
        return $this->pending !== null;
    }
}

interface InstalledAuthenticateDocumentRequest
{
    public function authenticate(Request $request): InstalledFilePrincipal;
}

interface InstalledResolveDocumentTenant
{
    public function resolve(
        InstalledFilePrincipal $principal,
        int $accountId,
    ): InstalledFileTenant;
}

interface InstalledAuthorizeDocumentUpload
{
    public function authorizeUpload(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
    ): void;
}

interface InstalledAuthorizeDocumentDownload
{
    public function authorizeDownload(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
        string $identifier,
    ): void;
}

interface InstalledValidateDocumentUploadCsrf
{
    public function validateCsrf(Request $request): void;
}

interface InstalledAdmitDocumentUpload
{
    public function admitRate(InstalledFilePrincipal $principal, InstalledFileTenant $tenant): void;

    public function admitConcurrency(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
    ): void;

    public function releaseConcurrency(): void;
}

final readonly class InstalledDocumentAuthentication implements InstalledAuthenticateDocumentRequest
{
    public function __construct(
        private InstalledFileTrace $trace,
        private bool $authenticated,
        private int $principalId,
    ) {
    }

    public function authenticate(Request $request): InstalledFilePrincipal
    {
        $this->trace->step('authenticate');

        if (!$this->authenticated || ($request->headers['cookie'] ?? null) !== 'session=synthetic') {
            throw new InstalledFileUnauthenticated('Authentication is required.');
        }

        return new InstalledFilePrincipal($this->principalId);
    }
}

final readonly class InstalledDocumentTenantResolution implements InstalledResolveDocumentTenant
{
    public function __construct(
        private InstalledFileTrace $trace,
        private bool $tenantMatches,
    ) {
    }

    public function resolve(
        InstalledFilePrincipal $principal,
        int $accountId,
    ): InstalledFileTenant {
        $this->trace->step('resolve_tenant');

        if (!$this->tenantMatches || $principal->id < 1 || $accountId !== 42) {
            throw new InstalledFileCrossTenant('The tenant does not match.');
        }

        return new InstalledFileTenant($accountId);
    }
}

final readonly class InstalledDocumentUploadAuthorization implements InstalledAuthorizeDocumentUpload
{
    public function __construct(
        private InstalledFileTrace $trace,
        private bool $authorized,
        private bool $policyHealthy,
    ) {
    }

    public function authorizeUpload(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
    ): void {
        $this->trace->step('authorize_upload');

        if (!$this->policyHealthy) {
            throw new InstalledFileUnexpectedPolicyFailure('Upload policy is unavailable.');
        }

        if (!$this->authorized || $principal->id !== 7 || $tenant->accountId < 1) {
            throw new InstalledFileForbidden('Upload is forbidden.');
        }
    }
}

final readonly class InstalledDocumentDownloadAuthorization implements InstalledAuthorizeDocumentDownload
{
    public function __construct(
        private InstalledFileTrace $trace,
        private bool $authorized,
        private bool $policyHealthy,
    ) {
    }

    public function authorizeDownload(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
        string $identifier,
    ): void {
        $this->trace->step('authorize_download');

        if (!$this->policyHealthy) {
            throw new InstalledFileUnexpectedPolicyFailure('Download policy is unavailable.');
        }

        if (
            !$this->authorized
            || $principal->id !== 7
            || $tenant->accountId < 1
            || preg_match('/^[0-9a-f]{32}$/D', $identifier) !== 1
        ) {
            throw new InstalledFileForbidden('Download is forbidden.');
        }
    }
}

final readonly class InstalledDocumentUploadCsrf implements InstalledValidateDocumentUploadCsrf
{
    public function __construct(
        private InstalledFileTrace $trace,
        private bool $accepted,
    ) {
    }

    public function validateCsrf(Request $request): void
    {
        $this->trace->step('csrf');

        if (!$this->accepted || ($request->headers['x-csrf-token'] ?? null) !== 'synthetic-csrf') {
            throw new InstalledFileCsrfRejected('CSRF validation failed.');
        }
    }
}

final class InstalledDocumentUploadAdmission implements InstalledAdmitDocumentUpload
{
    private const int MAXIMUM_UPLOADS_PER_WINDOW = 2;

    private int $rateAdmissions = 0;

    private bool $concurrencyLease = false;

    private int $proofObservations = 0;

    public function __construct(
        private InstalledFileTrace $trace,
        private bool $rateAccepted,
        private bool $concurrencyAccepted,
    ) {
    }

    public function admitRate(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
    ): void {
        $this->trace->step('rate');

        if (
            !$this->rateAccepted
            || $principal->id < 1
            || $tenant->accountId < 1
            || $this->rateAdmissions >= self::MAXIMUM_UPLOADS_PER_WINDOW
        ) {
            throw new InstalledFileRateExceeded('Upload rate is unavailable.');
        }

        $this->rateAdmissions++;
    }

    public function admitConcurrency(
        InstalledFilePrincipal $principal,
        InstalledFileTenant $tenant,
    ): void {
        $this->trace->step('concurrency');

        if (
            !$this->concurrencyAccepted
            || $principal->id < 1
            || $tenant->accountId < 1
            || $this->concurrencyLease
        ) {
            throw new InstalledFileConcurrencyExceeded('Upload concurrency is unavailable.');
        }

        $this->concurrencyLease = true;
    }

    public function releaseConcurrency(): void
    {
        if (!$this->concurrencyLease) {
            throw new LogicException('Document upload concurrency lease is unavailable.');
        }

        $this->concurrencyLease = false;
    }

    /** @return array{rate_admissions: int, concurrency_lease: bool} */
    public function proofState(): array
    {
        $this->proofObservations++;

        return [
            'rate_admissions' => $this->rateAdmissions,
            'concurrency_lease' => $this->concurrencyLease,
        ];
    }
}

final readonly class InstalledUploadDocumentHandler implements RequestHandler
{
    public function __construct(
        private InstalledFileTrace $trace,
        private InstalledAuthenticateDocumentRequest $authenticate,
        private InstalledResolveDocumentTenant $resolveTenant,
        private InstalledAuthorizeDocumentUpload $authorize,
        private InstalledValidateDocumentUploadCsrf $csrf,
        private InstalledAdmitDocumentUpload $admission,
        private InstalledDocumentQuota $quota,
        private InstalledDocumentStorage $storage,
        private InstalledDocumentReconciliation $reconciliation,
    ) {
    }

    public function handle(Request $request): Response
    {
        $accountId = $request->pathParameters->positiveInteger('account_id');
        $principal = $this->authenticate->authenticate($request);
        $tenant = $this->resolveTenant->resolve($principal, $accountId);
        $this->authorize->authorizeUpload($principal, $tenant);
        $this->csrf->validateCsrf($request);
        $this->admission->admitRate($principal, $tenant);
        $this->admission->admitConcurrency($principal, $tenant);

        try {
            $pending = $this->pendingUpload($request);
            $reservation = $this->quota->reserve($tenant->accountId, $pending->reportedSizeBytes);
            $this->trace->step('quota_reserved');

            try {
                $identifier = $this->storage->store($tenant->accountId, $principal->id, $pending);
                $this->trace->step('stored');
                $this->quota->commit($reservation);
                $this->trace->step('quota_committed');
            } catch (InstalledFileCleanupRequired $failure) {
                $this->quota->requireReconciliation($reservation);
                $this->reconciliation->retain($failure->identifier, $reservation);
                $this->trace->operational('document_file_reconciliation_required');

                throw $failure;
            } catch (InstalledFileStorageUnavailable $failure) {
                $this->quota->release($reservation);

                throw $failure;
            }
        } finally {
            $this->admission->releaseConcurrency();
        }

        return new Response(
            201,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
            "{\"data\":{\"accepted\":true}}\n",
        );
    }

    private function pendingUpload(Request $request): InstalledPendingDocumentUpload
    {
        $this->trace->step('pending_upload');

        if ($request->body !== '') {
            throw new InstalledFileForbidden('Multipart requests do not use an ordinary body.');
        }

        return InstalledPendingDocumentUpload::fromUploads($request->uploads);
    }
}

final readonly class InstalledDownloadDocumentHandler implements RequestHandler
{
    public function __construct(
        private InstalledFileTrace $trace,
        private InstalledAuthenticateDocumentRequest $authenticate,
        private InstalledResolveDocumentTenant $resolveTenant,
        private InstalledAuthorizeDocumentDownload $authorize,
        private InstalledDocumentStorage $storage,
    ) {
    }

    public function handle(Request $request): Response
    {
        $accountId = $request->pathParameters->positiveInteger('account_id');
        $identifier = $request->pathParameters->token('file_id');
        $principal = $this->authenticate->authenticate($request);
        $tenant = $this->resolveTenant->resolve($principal, $accountId);
        $this->authorize->authorizeDownload($principal, $tenant, $identifier);
        $bytes = $this->storage->read(
            $tenant->accountId,
            $principal->id,
            'document.download',
            $identifier,
        );
        $this->trace->step('lookup');

        return new Response(
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="download.bin"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Accept-Ranges' => 'none',
            ],
            $bytes,
        );
    }
}

function installedFileUploadHandler(
    InstalledFileTrace $trace,
    InstalledDocumentQuota $quota,
    InstalledDocumentStorage $storage,
    InstalledDocumentReconciliation $reconciliation,
    bool $authenticated = true,
    bool $tenantMatches = true,
    bool $authorized = true,
    bool $csrfAccepted = true,
    bool $rateAccepted = true,
    bool $concurrencyAccepted = true,
    bool $policyHealthy = true,
    int $principalId = 7,
    ?InstalledDocumentUploadAdmission $admission = null,
): InstalledUploadDocumentHandler {
    return new InstalledUploadDocumentHandler(
        $trace,
        new InstalledDocumentAuthentication($trace, $authenticated, $principalId),
        new InstalledDocumentTenantResolution($trace, $tenantMatches),
        new InstalledDocumentUploadAuthorization($trace, $authorized, $policyHealthy),
        new InstalledDocumentUploadCsrf($trace, $csrfAccepted),
        $admission
            ?? new InstalledDocumentUploadAdmission($trace, $rateAccepted, $concurrencyAccepted),
        $quota,
        $storage,
        $reconciliation,
    );
}

function installedFileDownloadHandler(
    InstalledFileTrace $trace,
    InstalledDocumentStorage $storage,
    bool $authenticated = true,
    bool $tenantMatches = true,
    bool $authorized = true,
    bool $policyHealthy = true,
    int $principalId = 7,
): InstalledDownloadDocumentHandler {
    return new InstalledDownloadDocumentHandler(
        $trace,
        new InstalledDocumentAuthentication($trace, $authenticated, $principalId),
        new InstalledDocumentTenantResolution($trace, $tenantMatches),
        new InstalledDocumentDownloadAuthorization($trace, $authorized, $policyHealthy),
        $storage,
    );
}

/** @param list<string> $expected */
function installedFileExpectSteps(InstalledFileTrace $trace, array $expected): void
{
    if ($trace->steps() !== $expected) {
        throw new RuntimeException('Protected file-transfer order changed.');
    }
}

function installedFileRequest(
    int $reportedSizeBytes = 6,
    RequestUploadError $error = RequestUploadError::Success,
    ?string $csrfToken = 'synthetic-csrf',
): Request
{
    $headers = ['cookie' => 'session=synthetic'];

    if ($csrfToken !== null) {
        $headers['x-csrf-token'] = $csrfToken;
    }

    return new Request(
        'POST',
        '/accounts/42/document-files',
        [],
        '',
        $headers,
        PathParameters::fromValues(['account_id' => 42], []),
        [
            'document' => new RequestUpload(
                'hostile-client.php',
                'text/x-php',
                '/private/upload/tmp-secret',
                $reportedSizeBytes,
                $error,
            ),
        ],
    );
}

function installedFileDownloadRequest(string $identifier): Request
{
    return new Request(
        'GET',
        '/accounts/42/document-files/' . $identifier,
        [],
        '',
        ['cookie' => 'session=synthetic'],
        PathParameters::fromValues(['account_id' => 42], ['file_id' => $identifier]),
    );
}

function installedFileCrossTenantUploadRequest(): Request
{
    $request = installedFileRequest();

    return new Request(
        $request->method,
        '/accounts/43/document-files',
        $request->query,
        $request->body,
        $request->headers,
        PathParameters::fromValues(['account_id' => 43], []),
        $request->uploads,
    );
}

function installedFileExpectFailure(callable $operation, string $class): void
{
    try {
        $operation();
    } catch (Throwable $failure) {
        if ($failure::class === $class) {
            return;
        }

        throw $failure;
    }

    throw new RuntimeException('Expected protected file-transfer failure did not occur.');
}

function installedFileExpectPublicFailure(callable $operation, string $class): Response
{
    try {
        $operation();
    } catch (Throwable $failure) {
        if ($failure::class !== $class) {
            throw $failure;
        }

        [$status, $code] = match (true) {
            $failure instanceof InstalledFileUnauthenticated => [401, 'authentication_required'],
            $failure instanceof InstalledFileForbidden,
            $failure instanceof InstalledFileCrossTenant,
            $failure instanceof InstalledFileCsrfRejected => [403, 'operation_forbidden'],
            $failure instanceof InstalledFileRateExceeded,
            $failure instanceof InstalledFileConcurrencyExceeded => [429, 'admission_unavailable'],
            $failure instanceof InstalledFileQuotaExceeded => [409, 'quota_unavailable'],
            $failure instanceof InstalledFileNotFound => [404, 'document_not_found'],
            default => [500, 'internal_server_error'],
        };

        return new Response(
            $status,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
            ],
            json_encode(['error' => ['code' => $code]], JSON_THROW_ON_ERROR) . "\n",
        );
    }

    throw new RuntimeException('Expected protected file-transfer failure did not occur.');
}

function installedFileAssertPrivateFailure(Response $response): void
{
    if (
        ($response->headers['Cache-Control'] ?? null) !== 'private, no-store'
        || ($response->headers['Content-Type'] ?? null) !== 'application/json; charset=utf-8'
        || !in_array($response->status, [401, 403, 404, 409, 429, 500], true)
    ) {
        throw new RuntimeException('Protected file-transfer failure mapping changed.');
    }
}

/** @var list<Response> $publicFailures */
$publicFailures = [];

foreach (
    [
        [false, true, true, true, true, true, true, InstalledFileUnauthenticated::class, ['authenticate']],
        [true, false, true, true, true, true, true, InstalledFileCrossTenant::class, ['authenticate', 'resolve_tenant']],
        [true, true, false, true, true, true, true, InstalledFileForbidden::class, ['authenticate', 'resolve_tenant', 'authorize_upload']],
        [true, true, true, true, true, true, false, InstalledFileUnexpectedPolicyFailure::class, ['authenticate', 'resolve_tenant', 'authorize_upload']],
        [true, true, true, false, true, true, true, InstalledFileCsrfRejected::class, ['authenticate', 'resolve_tenant', 'authorize_upload', 'csrf']],
        [true, true, true, true, false, true, true, InstalledFileRateExceeded::class, ['authenticate', 'resolve_tenant', 'authorize_upload', 'csrf', 'rate']],
        [true, true, true, true, true, false, true, InstalledFileConcurrencyExceeded::class, ['authenticate', 'resolve_tenant', 'authorize_upload', 'csrf', 'rate', 'concurrency']],
    ] as $case
) {
    [
        $authenticated,
        $tenantMatches,
        $authorized,
        $csrfAccepted,
        $rateAccepted,
        $concurrencyAccepted,
        $policyHealthy,
        $failureClass,
        $steps,
    ] = $case;
    $trace = new InstalledFileTrace();
    $quota = new InstalledDocumentQuota();
    $storage = new InstalledDocumentStorage();
    $reconciliation = new InstalledDocumentReconciliation($trace);
    $admission = new InstalledDocumentUploadAdmission(
        $trace,
        $rateAccepted,
        $concurrencyAccepted,
    );
    $operation = installedFileUploadHandler(
        $trace,
        $quota,
        $storage,
        $reconciliation,
        $authenticated,
        $tenantMatches,
        $authorized,
        $csrfAccepted,
        $rateAccepted,
        $concurrencyAccepted,
        $policyHealthy,
        admission: $admission,
    );
    $failureResponse = installedFileExpectPublicFailure(
        static fn() => $operation->handle(installedFileRequest()),
        $failureClass,
    );
    installedFileAssertPrivateFailure($failureResponse);
    $publicFailures[] = $failureResponse;
    installedFileExpectSteps($trace, $steps);
    $admissionState = $admission->proofState();

    if (
        $storage->writeCalls !== 0
        || $quota->pending()
        || $quota->committedBytes() !== 0
        || $reconciliation->pending()
        || $admissionState['concurrency_lease']
    ) {
        throw new RuntimeException('Denied upload entered quota or storage work.');
    }
}

foreach ([null, 'wrong-csrf'] as $submittedCsrfToken) {
    $trace = new InstalledFileTrace();
    $quota = new InstalledDocumentQuota();
    $storage = new InstalledDocumentStorage();
    $operation = installedFileUploadHandler(
        $trace,
        $quota,
        $storage,
        new InstalledDocumentReconciliation($trace),
    );
    $failureResponse = installedFileExpectPublicFailure(
        static fn() => $operation->handle(
            installedFileRequest(csrfToken: $submittedCsrfToken),
        ),
        InstalledFileCsrfRejected::class,
    );
    installedFileAssertPrivateFailure($failureResponse);
    $publicFailures[] = $failureResponse;
    installedFileExpectSteps(
        $trace,
        ['authenticate', 'resolve_tenant', 'authorize_upload', 'csrf'],
    );

    if ($storage->writeCalls !== 0 || $quota->pending()) {
        throw new RuntimeException('Invalid submitted CSRF token entered quota or storage work.');
    }
}

$routeTenantTrace = new InstalledFileTrace();
$routeTenantQuota = new InstalledDocumentQuota();
$routeTenantStorage = new InstalledDocumentStorage();
$routeTenantOperation = installedFileUploadHandler(
    $routeTenantTrace,
    $routeTenantQuota,
    $routeTenantStorage,
    new InstalledDocumentReconciliation($routeTenantTrace),
);
$routeTenantFailure = installedFileExpectPublicFailure(
    static fn() => $routeTenantOperation->handle(installedFileCrossTenantUploadRequest()),
    InstalledFileCrossTenant::class,
);
installedFileAssertPrivateFailure($routeTenantFailure);
$publicFailures[] = $routeTenantFailure;
installedFileExpectSteps($routeTenantTrace, ['authenticate', 'resolve_tenant']);

if ($routeTenantQuota->pending() || $routeTenantStorage->writeCalls !== 0) {
    throw new RuntimeException('Mismatched route tenant entered authorization, quota, or storage work.');
}

$successTrace = new InstalledFileTrace();
$successQuota = new InstalledDocumentQuota();
$successStorage = new InstalledDocumentStorage();
$successReconciliation = new InstalledDocumentReconciliation($successTrace);
$successAdmission = new InstalledDocumentUploadAdmission($successTrace, true, true);
$successOperation = installedFileUploadHandler(
    $successTrace,
    $successQuota,
    $successStorage,
    $successReconciliation,
    admission: $successAdmission,
);
$success = $successOperation->handle(installedFileRequest(8));
$successAdmissionState = $successAdmission->proofState();

if (
    $success->status !== 201
    || $successQuota->committedBytes() !== 8
    || $successQuota->committedFiles() !== 1
    || $successQuota->pending()
    || $successStorage->writeCalls !== 1
    || $successAdmissionState !== ['rate_admissions' => 1, 'concurrency_lease' => false]
) {
    throw new RuntimeException('Protected upload did not commit its exact quota and storage work.');
}
installedFileExpectSteps(
    $successTrace,
    [
        'authenticate',
        'resolve_tenant',
        'authorize_upload',
        'csrf',
        'rate',
        'concurrency',
        'pending_upload',
        'quota_reserved',
        'stored',
        'quota_committed',
    ],
);

$retainedRecordEvidence = $successStorage->serializedRecordsForProof();

if (
    str_contains($retainedRecordEvidence, '/private/upload/tmp-secret')
    || str_contains($retainedRecordEvidence, 'hostile-client.php')
    || str_contains($retainedRecordEvidence, 'text/x-php')
) {
    throw new RuntimeException('Request-scoped upload state entered the retained record.');
}

$identifier = $successStorage->retainedIdentifierForProof();
$permittedDownloadTrace = new InstalledFileTrace();
$permittedDownload = installedFileDownloadHandler(
    $permittedDownloadTrace,
    $successStorage,
);
$download = $permittedDownload->handle(installedFileDownloadRequest($identifier));

if (
    $download->status !== 200
    || $download->body !== 'BBBBBBBB'
    || $download->headers !== [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="download.bin"',
        'Content-Length' => '8',
        'Cache-Control' => 'private, no-store',
        'X-Content-Type-Options' => 'nosniff',
        'Accept-Ranges' => 'none',
    ]
) {
    throw new RuntimeException('OPAQUE_BYTES download contract changed.');
}
installedFileExpectSteps(
    $permittedDownloadTrace,
    ['authenticate', 'resolve_tenant', 'authorize_download', 'lookup'],
);

$quotaFailure = installedFileExpectPublicFailure(
    static fn() => $successOperation->handle(installedFileRequest(1)),
    InstalledFileQuotaExceeded::class,
);
installedFileAssertPrivateFailure($quotaFailure);
$publicFailures[] = $quotaFailure;
$successAdmissionState = $successAdmission->proofState();

if ($successAdmissionState !== ['rate_admissions' => 2, 'concurrency_lease' => false]) {
    throw new RuntimeException('Post-acquire quota failure did not release exact admission state.');
}

$rateFailure = installedFileExpectPublicFailure(
    static fn() => $successOperation->handle(installedFileRequest(1)),
    InstalledFileRateExceeded::class,
);
installedFileAssertPrivateFailure($rateFailure);
$publicFailures[] = $rateFailure;
$successAdmissionState = $successAdmission->proofState();
$successStorageState = $successStorage->proofState();

if (
    $successAdmissionState !== ['rate_admissions' => 2, 'concurrency_lease' => false]
    || $successStorageState['writes'] !== 1
) {
    throw new RuntimeException('Exact rate limit plus one entered quota or storage work.');
}

$inputLimitTrace = new InstalledFileTrace();
$inputLimitQuota = new InstalledDocumentQuota();
$inputLimitStorage = new InstalledDocumentStorage();
$inputLimitAdmission = new InstalledDocumentUploadAdmission($inputLimitTrace, true, true);
$inputLimitOperation = installedFileUploadHandler(
    $inputLimitTrace,
    $inputLimitQuota,
    $inputLimitStorage,
    new InstalledDocumentReconciliation($inputLimitTrace),
    admission: $inputLimitAdmission,
);
$inputLimitFailure = installedFileExpectPublicFailure(
    static fn() => $inputLimitOperation->handle(installedFileRequest(9)),
    InstalledFileQuotaExceeded::class,
);
installedFileAssertPrivateFailure($inputLimitFailure);
$publicFailures[] = $inputLimitFailure;

$zeroLimitFailure = installedFileExpectPublicFailure(
    static fn() => $inputLimitOperation->handle(installedFileRequest(0)),
    InstalledFileQuotaExceeded::class,
);
installedFileAssertPrivateFailure($zeroLimitFailure);
$publicFailures[] = $zeroLimitFailure;
$inputLimitAdmissionState = $inputLimitAdmission->proofState();

if (
    $inputLimitQuota->pending()
    || $inputLimitStorage->writeCalls !== 0
    || $inputLimitAdmissionState['concurrency_lease']
) {
    throw new RuntimeException('Reported-size minimum or overflow entered quota or storage work.');
}

$phpTemporaryTrace = new InstalledFileTrace();
$phpTemporaryAdmission = new InstalledDocumentUploadAdmission($phpTemporaryTrace, true, true);
$phpTemporaryOperation = installedFileUploadHandler(
    $phpTemporaryTrace,
    new InstalledDocumentQuota(),
    new InstalledDocumentStorage(),
    new InstalledDocumentReconciliation($phpTemporaryTrace),
    admission: $phpTemporaryAdmission,
);
$phpTemporaryFailure = installedFileExpectPublicFailure(
    static fn() => $phpTemporaryOperation->handle(
        installedFileRequest(4, RequestUploadError::NoTemporaryDirectory),
    ),
    InstalledFileStorageUnavailable::class,
);
installedFileAssertPrivateFailure($phpTemporaryFailure);
$publicFailures[] = $phpTemporaryFailure;
$phpTemporaryAdmissionState = $phpTemporaryAdmission->proofState();

if ($phpTemporaryAdmissionState['concurrency_lease']) {
    throw new RuntimeException('PHP temporary failure retained a concurrency lease.');
}

$admissionTrace = new InstalledFileTrace();
$admission = new InstalledDocumentUploadAdmission($admissionTrace, true, true);
$admissionPrincipal = new InstalledFilePrincipal(7);
$admissionTenant = new InstalledFileTenant(42);
$admission->admitConcurrency($admissionPrincipal, $admissionTenant);
installedFileExpectFailure(
    static fn() => $admission->admitConcurrency($admissionPrincipal, $admissionTenant),
    InstalledFileConcurrencyExceeded::class,
);
$admissionState = $admission->proofState();

if (!$admissionState['concurrency_lease']) {
    throw new RuntimeException('Concurrent admission did not retain the first exact lease.');
}

$admission->releaseConcurrency();
$admission->admitConcurrency($admissionPrincipal, $admissionTenant);
$admission->releaseConcurrency();
$admissionState = $admission->proofState();

if ($admissionState['concurrency_lease']) {
    throw new RuntimeException('Released concurrency admission remained active.');
}

$concurrentQuota = new InstalledDocumentQuota();
$firstReservation = $concurrentQuota->reserve(42, 4);
installedFileExpectFailure(
    static fn() => $concurrentQuota->reserve(42, 1),
    InstalledFileConcurrencyExceeded::class,
);

foreach (
    [
        new InstalledFileReservation(str_repeat('0', 32), 42, 4),
        new InstalledFileReservation($firstReservation->token, 43, 4),
        new InstalledFileReservation($firstReservation->token, 42, 5),
    ] as $forgedReservation
) {
    installedFileExpectFailure(
        static fn() => $concurrentQuota->release($forgedReservation),
        LogicException::class,
    );
}
$concurrentQuota->release($firstReservation);

$countQuota = new InstalledDocumentQuota();

for ($retainedFile = 1; $retainedFile <= 4; $retainedFile++) {
    $reservation = $countQuota->reserve(42, 1);
    $countQuota->commit($reservation);
}

$countQuotaState = $countQuota->proofState();

if (
    $countQuotaState
        !== ['committed_bytes' => 4, 'committed_files' => 4, 'pending' => false]
) {
    throw new RuntimeException('Exact retained-file count and byte quota did not commit.');
}

installedFileExpectFailure(
    static fn() => $countQuota->reserve(42, 1),
    InstalledFileQuotaExceeded::class,
);
$countQuotaState = $countQuota->proofState();

if (
    $countQuotaState
        !== ['committed_bytes' => 4, 'committed_files' => 4, 'pending' => false]
) {
    throw new RuntimeException('Retained-file count plus one changed exact accounting.');
}

foreach (
    [
        'temporary_root_unavailable',
        'durable_disk_full',
        'durable_inode_exhausted',
        'durable_permission_denied',
        'move_native_false',
        'chmod_cleanup_success',
    ] as $fault
) {
    $trace = new InstalledFileTrace();
    $quota = new InstalledDocumentQuota();
    $storage = new InstalledDocumentStorage();
    $reconciliation = new InstalledDocumentReconciliation($trace);
    $admission = new InstalledDocumentUploadAdmission($trace, true, true);
    $storage->failNextWriteAt($fault);
    $operation = installedFileUploadHandler(
        $trace,
        $quota,
        $storage,
        $reconciliation,
        admission: $admission,
    );
    $failureResponse = installedFileExpectPublicFailure(
        static fn() => $operation->handle(installedFileRequest(4)),
        InstalledFileStorageUnavailable::class,
    );
    installedFileAssertPrivateFailure($failureResponse);
    $publicFailures[] = $failureResponse;
    $admissionState = $admission->proofState();

    if (
        $quota->pending()
        || $quota->committedBytes() !== 0
        || $quota->reconciliationRequired()
        || $storage->fileCount() !== 0
        || $admissionState['concurrency_lease']
    ) {
        throw new RuntimeException('Recoverable storage failure retained quota or bytes.');
    }
}

$reconcileTrace = new InstalledFileTrace();
$reconcileQuota = new InstalledDocumentQuota();
$reconcileStorage = new InstalledDocumentStorage();
$reconciliation = new InstalledDocumentReconciliation($reconcileTrace);
$reconcileAdmission = new InstalledDocumentUploadAdmission($reconcileTrace, true, true);
$reconcileStorage->failNextWriteAt('chmod_cleanup_failure');
$reconcileOperation = installedFileUploadHandler(
    $reconcileTrace,
    $reconcileQuota,
    $reconcileStorage,
    $reconciliation,
    admission: $reconcileAdmission,
);
$reconcileFailure = installedFileExpectPublicFailure(
    static fn() => $reconcileOperation->handle(installedFileRequest(4)),
    InstalledFileCleanupRequired::class,
);
installedFileAssertPrivateFailure($reconcileFailure);
$publicFailures[] = $reconcileFailure;
$reconcileAdmissionState = $reconcileAdmission->proofState();

if (
    !$reconcileQuota->pending()
    || !$reconcileQuota->reconciliationRequired()
    || !$reconciliation->pending()
    || $reconcileStorage->fileCount() !== 1
    || $reconcileAdmissionState['concurrency_lease']
    || $reconcileTrace->operationalCodes() !== ['document_file_reconciliation_required']
) {
    throw new RuntimeException('Cleanup failure did not retain bounded reconciliation state.');
}

$reconciliation->run($reconcileStorage, $reconcileQuota);
$reconciliation->run($reconcileStorage, $reconcileQuota);

if (
    $reconcileQuota->pending()
    || $reconcileQuota->reconciliationRequired()
    || $reconciliation->pending()
    || $reconcileStorage->fileCount() !== 0
    || $reconcileStorage->reconcileDeleteCalls !== 1
) {
    throw new RuntimeException('Reconciliation did not delete bytes before releasing exact accounting.');
}
installedFileExpectSteps(
    $reconcileTrace,
    [
        'authenticate',
        'resolve_tenant',
        'authorize_upload',
        'csrf',
        'rate',
        'concurrency',
        'pending_upload',
        'quota_reserved',
        'reconciliation_delete',
        'reconciliation_release',
    ],
);

foreach (
    [
        [false, true, true, InstalledFileUnauthenticated::class, ['authenticate']],
        [true, false, true, InstalledFileCrossTenant::class, ['authenticate', 'resolve_tenant']],
        [true, true, false, InstalledFileForbidden::class, ['authenticate', 'resolve_tenant', 'authorize_download']],
        [true, true, true, InstalledFileUnexpectedPolicyFailure::class, ['authenticate', 'resolve_tenant', 'authorize_download']],
    ] as $case
) {
    [$authenticated, $tenantMatches, $authorized, $failureClass, $steps] = $case;
    $trace = new InstalledFileTrace();
    $operation = installedFileDownloadHandler(
        $trace,
        $successStorage,
        $authenticated,
        $tenantMatches,
        $authorized,
        policyHealthy: $failureClass !== InstalledFileUnexpectedPolicyFailure::class,
    );
    $lookupCalls = $successStorage->lookupCalls;
    $failureResponse = installedFileExpectPublicFailure(
        static fn() => $operation->handle(installedFileDownloadRequest($identifier)),
        $failureClass,
    );
    installedFileAssertPrivateFailure($failureResponse);
    $publicFailures[] = $failureResponse;
    installedFileExpectSteps($trace, $steps);

    if ($successStorage->lookupCalls !== $lookupCalls) {
        throw new RuntimeException('Denied protected download looked up an identifier.');
    }
}

$wrongOwnerTrace = new InstalledFileTrace();
$wrongOwnerOperation = installedFileDownloadHandler(
    $wrongOwnerTrace,
    $successStorage,
    principalId: 8,
);
$wrongOwnerLookupCalls = $successStorage->lookupCalls;
$identifierFailure = installedFileExpectPublicFailure(
    static fn() => $wrongOwnerOperation->handle(installedFileDownloadRequest($identifier)),
    InstalledFileForbidden::class,
);
installedFileAssertPrivateFailure($identifierFailure);
$publicFailures[] = $identifierFailure;

if ($successStorage->lookupCalls !== $wrongOwnerLookupCalls) {
    throw new RuntimeException('Identifier possession bypassed operation-specific authorization.');
}

installedFileExpectFailure(
    static fn() => $successStorage->read(42, 8, 'document.download', $identifier),
    InstalledFileNotFound::class,
);

installedFileExpectFailure(
    static fn() => $successStorage->read(43, 7, 'document.download', $identifier),
    InstalledFileNotFound::class,
);

installedFileExpectFailure(
    static fn() => $successStorage->read(42, 7, 'document.replace', $identifier),
    InstalledFileNotFound::class,
);

$secrets = [
    'hostile-client.php',
    'text/x-php',
    '/private/upload/tmp-secret',
    '/private/durable/secret',
    $identifier,
    'BBBBBBBB',
    'synthetic-csrf',
    'session=synthetic',
    'Document durable storage is unavailable',
    'Document cleanup requires reconciliation',
];
$publicEvidence = json_encode(
    [
        'failures' => array_map(
            static fn(Response $response): array => [
                'status' => $response->status,
                'headers' => $response->headers,
                'body' => $response->body,
            ],
            $publicFailures,
        ),
        'terminal_operational_codes' => $reconcileTrace->operationalCodes(),
    ],
    JSON_THROW_ON_ERROR,
);

foreach ($secrets as $secret) {
    if (str_contains($publicEvidence, $secret)) {
        throw new RuntimeException('Protected file-transfer public or terminal evidence disclosed sensitive state.');
    }
}

// Installed proof markers: scanner_rejection=NOT_APPLICABLE;
// scanner_timeout=NOT_APPLICABLE. OPAQUE_BYTES calls no parser or scanner, so these
// are static adoption facts rather than simulated INSPECTED_CONTENT behavior.
fwrite(STDOUT, "PASS installed protected file-transfer application adaptation\n");
PHP;
}
