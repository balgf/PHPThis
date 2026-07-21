<?php

declare(strict_types=1);

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticateAccountRequest;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\CrossTenant;
use Example\Accounts\DenyAllAccountAuthorization;
use Example\Accounts\Forbidden;
use Example\Accounts\ResolveAccountTenant;
use Example\Accounts\ResolvedTenant;
use Example\Accounts\Unauthenticated;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Documents\GetDocument\DocumentDetailsCacheTrace;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Observability\CorrelationId;
use Example\Observability\QuerySummarySource;
use Example\Observability\RequestSummary;
use Example\Observability\RequestSummarySink;
use Example\Observability\TerminalRequestCoordinator;
use Example\Routes;
use Example\Users\CreateUser\AuthorizeCreateUser;
use Example\Users\CreateUser\CreateUserCommand;
use Example\Users\CreateUser\TransactionalCreateUser;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Router;

final class ConsumerProfilePolicyTrace
{
    /** @var list<string> */
    public array $steps = [];

    public function record(string $step): void
    {
        $this->steps[] = $step;
    }
}

final readonly class ConsumerProfileAccountPolicy implements
    AuthenticateAccountRequest,
    ResolveAccountTenant,
    AuthorizeCreateUser
{
    public const string CREDENTIAL = 'Bearer ConsumerProfileCredentialMarker';

    /** @param 'authenticate'|'resolve_tenant'|'authorize_create'|null $failureStage */
    public function __construct(
        private ConsumerProfilePolicyTrace $trace,
        private ?string $failureStage,
    ) {
    }

    public function authenticate(Request $request): AuthenticatedPrincipal
    {
        $this->trace->record('authenticate');

        if (
            $this->failureStage === 'authenticate'
            || ($request->headers['authorization'] ?? null) !== self::CREDENTIAL
        ) {
            throw new Unauthenticated('ConsumerProfileCredentialFailureMarker');
        }

        return AuthenticatedPrincipal::fromPositiveInteger(7);
    }

    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant {
        $this->trace->record('resolve_tenant');

        if (
            $this->failureStage === 'resolve_tenant'
            || $principal->id !== 7
            || $accountId->value !== 42
        ) {
            throw new CrossTenant('ConsumerProfileTenantFailureMarker');
        }

        return ResolvedTenant::forAccount($accountId);
    }

    public function authorizeCreate(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void {
        $this->trace->record('authorize_create');

        if (
            $this->failureStage === 'authorize_create'
            || $principal->id !== 7
            || $tenant->accountId->value !== 42
        ) {
            throw new Forbidden('ConsumerProfileAuthorizationFailureMarker');
        }
    }
}

final class ConsumerProfileSummarySink implements RequestSummarySink
{
    public int $attempts = 0;

    private ?RequestSummary $summary = null;

    public function emit(RequestSummary $summary): void
    {
        $this->attempts++;
        $this->summary = $summary;
    }

    public function onlySummary(): RequestSummary
    {
        if ($this->attempts !== 1 || !$this->summary instanceof RequestSummary) {
            throw new RuntimeException('Consumer profile requires exactly one terminal summary.');
        }

        return $this->summary;
    }
}

final readonly class ConsumerProfileCounts
{
    public function __construct(
        public int $users,
        public int $accountUsers,
        public int $events,
        public int $jobs,
        public ?UserWelcomeJobEnvelope $job,
    ) {
    }
}

/** @phpstan-import-type QuerySnapshot from QueryTrace */
final readonly class ConsumerProfileResult
{
    /**
     * @param list<string> $policySteps
     * @param QuerySnapshot $queryTrace
     */
    public function __construct(
        public Response $response,
        public RequestSummary $summary,
        public int $sinkAttempts,
        public array $policySteps,
        public int $budgetUsed,
        public array $queryTrace,
        public bool $inTransaction,
        public ConsumerProfileCounts $counts,
    ) {
    }
}

/** @return array<string, Closure(): void> */
function consumerProfileTests(): array
{
    return [
        'consumer profile composes policy typed input transaction job and correlation' => static function (): void {
            $small = runConsumerProfileScenario('success-small', 0);
            $large = runConsumerProfileScenario('success-large', 500);

            foreach (['small' => $small, 'large' => $large] as $size => $result) {
                $payload = $result->summary->toArray();
                $encodedSummary = json_encode($payload, JSON_THROW_ON_ERROR);
                $source = $result->summary->querySources[0] ?? null;

                if (
                    $result->response->status !== 201
                    || $result->response->headers !== [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Cache-Control' => 'private, no-store',
                        'X-Request-ID' => $result->summary->correlationId->value,
                    ]
                    || $result->response->body
                        !== "{\"user\":{\"account_id\":42,\"name\":\"Profile Name Marker\",\"email\":\"profile-secret@example.com\"}}\n"
                    || $result->policySteps
                        !== ['authenticate', 'resolve_tenant', 'authorize_create']
                    || $result->sinkAttempts !== 1
                    || $result->budgetUsed !== 4
                    || $result->queryTrace['statements'] !== 4
                    || $result->queryTrace['failures'] !== 0
                    || $result->queryTrace['repeated_fingerprints'] !== 0
                    || $result->queryTrace['maximum_executions_per_fingerprint'] !== 1
                    || $result->inTransaction
                    || $result->counts->users !== 1
                    || $result->counts->accountUsers !== 1
                    || $result->counts->events !== 1
                    || $result->counts->jobs !== 1
                    || !$result->counts->job instanceof UserWelcomeJobEnvelope
                    || $result->counts->job->version !== UserWelcomeJobEnvelope::VERSION
                    || $result->counts->job->type !== UserWelcomeJobEnvelope::TYPE
                    || $result->counts->job->email !== 'profile-secret@example.com'
                    || $result->counts->job->idempotencyKey
                        !== hash('sha256', "user-welcome:v1\0profile-secret@example.com")
                    || $result->summary->outcome !== 'success'
                    || $result->summary->queryStatements !== 4
                    || $result->summary->queryFailures !== 0
                    || $result->summary->queryBudgetExceeded
                    || count($result->summary->querySources) !== 1
                    || !is_array($source)
                    || $source['name'] !== 'create_user'
                    || str_contains($encodedSummary, ConsumerProfileAccountPolicy::CREDENTIAL)
                    || str_contains($encodedSummary, 'Profile Name Marker')
                    || str_contains($encodedSummary, 'profile-secret@example.com')
                    || str_contains($encodedSummary, 'INSERT INTO')
                    || str_contains($encodedSummary, 'sqlite:')
                ) {
                    throw new RuntimeException(
                        "Consumer profile success evidence changed for the {$size} fixture.",
                    );
                }
            }
        },
        'consumer profile denials and invalid input stop before protected SQL' => static function (): void {
            $cases = [
                'authenticate' => ['authenticate', 401, ['authenticate']],
                'resolve_tenant' => [
                    'resolve_tenant',
                    403,
                    ['authenticate', 'resolve_tenant'],
                ],
                'authorize_create' => [
                    'authorize_create',
                    403,
                    ['authenticate', 'resolve_tenant', 'authorize_create'],
                ],
                'invalid_input' => [
                    null,
                    400,
                    ['authenticate', 'resolve_tenant', 'authorize_create'],
                ],
            ];

            foreach ($cases as $case => [$failureStage, $status, $steps]) {
                $body = $case === 'invalid_input'
                    ? '{"name":"Profile Name Marker","email":"profile-secret@example.com","api_token":"ConsumerProfileInputSecretMarker"}'
                    : consumerProfileValidBody();
                $result = runConsumerProfileScenario(
                    'denial-' . $case,
                    0,
                    $failureStage,
                    $body,
                );
                $encodedEvidence = json_encode(
                    [$result->response->headers, $result->response->body, $result->summary->toArray()],
                    JSON_THROW_ON_ERROR,
                );
                $expectedHeaders = [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'private, no-store',
                ];

                if ($case === 'authenticate') {
                    $expectedHeaders['WWW-Authenticate'] = 'Bearer';
                }

                $expectedHeaders['X-Request-ID'] = $result->summary->correlationId->value;

                if (
                    $result->response->status !== $status
                    || $result->response->headers !== $expectedHeaders
                    || $result->policySteps !== $steps
                    || $result->budgetUsed !== 0
                    || $result->queryTrace['statements'] !== 0
                    || $result->summary->queryStatements !== 0
                    || $result->summary->outcome !== 'known_failure'
                    || $result->counts->users !== 0
                    || $result->counts->accountUsers !== 0
                    || $result->counts->events !== 0
                    || $result->counts->jobs !== 0
                    || $result->counts->job !== null
                    || str_contains($encodedEvidence, ConsumerProfileAccountPolicy::CREDENTIAL)
                    || str_contains($encodedEvidence, 'Profile Name Marker')
                    || str_contains($encodedEvidence, 'profile-secret@example.com')
                    || str_contains($encodedEvidence, 'ConsumerProfileInputSecretMarker')
                    || str_contains($encodedEvidence, 'ConsumerProfileCredentialFailureMarker')
                    || str_contains($encodedEvidence, 'ConsumerProfileTenantFailureMarker')
                    || str_contains($encodedEvidence, 'ConsumerProfileAuthorizationFailureMarker')
                ) {
                    throw new RuntimeException(
                        "Consumer profile denial evidence changed for {$case}.",
                    );
                }
            }
        },
        'consumer profile job and budget failures roll back every scoped write' => static function (): void {
            $jobFailure = runConsumerProfileScenario(
                'job-failure',
                0,
                rejectJob: true,
            );
            $budgetFailure = runConsumerProfileScenario(
                'budget-failure',
                0,
                queryLimit: 3,
            );

            foreach (['job' => $jobFailure, 'budget' => $budgetFailure] as $case => $result) {
                $encodedEvidence = json_encode(
                    [$result->response->headers, $result->response->body, $result->summary->toArray()],
                    JSON_THROW_ON_ERROR,
                );

                if (
                    $result->response->status !== 500
                    || $result->response->body
                        !== "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n"
                    || $result->response->headers !== [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Cache-Control' => 'private, no-store',
                        'X-Request-ID' => $result->summary->correlationId->value,
                    ]
                    || $result->policySteps
                        !== ['authenticate', 'resolve_tenant', 'authorize_create']
                    || $result->summary->outcome !== 'unknown_failure'
                    || $result->inTransaction
                    || $result->counts->users !== 0
                    || $result->counts->accountUsers !== 0
                    || $result->counts->events !== 0
                    || $result->counts->jobs !== 0
                    || $result->counts->job !== null
                    || str_contains($encodedEvidence, 'ConsumerProfileDatabaseSecretMarker')
                    || str_contains($encodedEvidence, ConsumerProfileAccountPolicy::CREDENTIAL)
                    || str_contains($encodedEvidence, 'profile-secret@example.com')
                ) {
                    throw new RuntimeException(
                        "Consumer profile rollback evidence changed for {$case} failure.",
                    );
                }
            }

            if (
                $jobFailure->budgetUsed !== 4
                || $jobFailure->queryTrace['statements'] !== 4
                || $jobFailure->queryTrace['failures'] !== 1
                || $jobFailure->summary->queryFailures !== 1
                || $jobFailure->summary->queryBudgetExceeded
                || $budgetFailure->budgetUsed !== 3
                || $budgetFailure->queryTrace['statements'] !== 3
                || $budgetFailure->queryTrace['failures'] !== 0
                || !$budgetFailure->summary->queryBudgetExceeded
            ) {
                throw new RuntimeException('Consumer profile must distinguish execution and budget failure.');
            }
        },
        'consumer profile SQL rejects mismatched tenant and missing actor membership' => static function (): void {
            assertConsumerProfileSqlPolicy('mismatched_tenant', 7, 43, 43);
            assertConsumerProfileSqlPolicy('missing_actor_membership', 8, 42, 43);
        },
    ];
}

function assertConsumerProfileSqlPolicy(
    string $case,
    int $principalId,
    int $resolvedAccountId,
    int $seedMembershipAccountId,
): void {
    $databasePath = createUserDatabaseFixture(
        'consumer-profile-sql-' . $case,
        0,
        false,
    );
    Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    )->executeStatement(
        'INSERT INTO account_memberships (principal_id, account_id) VALUES (:principal_id, :account_id)',
        [
            'principal_id' => $principalId,
            'account_id' => $seedMembershipAccountId,
        ],
    );
    $budget = new QueryBudget(4);
    $trace = new QueryTrace(4);
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        $budget,
        $trace,
    );
    $operation = new TransactionalCreateUser($connection);
    $requestedAccount = AccountId::fromPositiveInteger(42);
    $rejected = false;

    try {
        $operation->execute(
            AuthenticatedPrincipal::fromPositiveInteger($principalId),
            ResolvedTenant::forAccount(
                AccountId::fromPositiveInteger($resolvedAccountId),
            ),
            $requestedAccount,
            CreateUserCommand::fromJson(consumerProfileValidBody()),
        );
    } catch (Forbidden) {
        $rejected = true;
    }

    $counts = consumerProfileCounts($databasePath);
    $snapshot = $trace->snapshot();

    if (
        !$rejected
        || $connection->inTransaction()
        || $budget->used() !== 1
        || $snapshot['statements'] !== 1
        || $snapshot['failures'] !== 0
        || $counts->users !== 0
        || $counts->accountUsers !== 0
        || $counts->events !== 0
        || $counts->jobs !== 0
        || $counts->job !== null
    ) {
        throw new RuntimeException(
            "Consumer profile SQL policy changed for {$case}.",
        );
    }
}

/**
 * @param 'authenticate'|'resolve_tenant'|'authorize_create'|null $failureStage
 */
function runConsumerProfileScenario(
    string $name,
    int $preexistingUsers,
    ?string $failureStage = null,
    string $body = '{"name":"Profile Name Marker","email":"profile-secret@example.com"}',
    int $queryLimit = 4,
    bool $rejectJob = false,
): ConsumerProfileResult {
    $databasePath = createUserDatabaseFixture(
        'consumer-profile-' . $name,
        $preexistingUsers,
        $preexistingUsers > 0,
    );

    if ($rejectJob) {
        Connection::connect(
            'sqlite:' . $databasePath,
            new QueryBudget(1),
            new QueryTrace(1),
        )->executeStatement(
            <<<'SQL'
                CREATE TRIGGER reject_consumer_profile_job
                BEFORE INSERT ON application_jobs
                BEGIN
                    SELECT RAISE(ABORT, 'ConsumerProfileDatabaseSecretMarker');
                END
                SQL,
        );
    }

    $budget = new QueryBudget($queryLimit);
    $queryTrace = new QueryTrace(4);
    $createConnection = Connection::connect(
        'sqlite:' . $databasePath,
        $budget,
        $queryTrace,
    );
    $policyTrace = new ConsumerProfilePolicyTrace();
    $policy = new ConsumerProfileAccountPolicy($policyTrace, $failureStage);
    $documentAuthorization = new DenyAllAccountAuthorization();
    $dsn = 'sqlite:' . $databasePath;
    $application = new Application(new Router(Routes::create(
        Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1)),
        Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1)),
        $createConnection,
        new SelectAuthorizedDocument(
            Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1)),
        ),
        Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1)),
        $policy,
        $policy,
        $policy,
        $documentAuthorization,
        $documentAuthorization,
        new LocalDocumentFiles(__DIR__ . '/../tmp/application-tests/consumer-profile-files'),
    )));
    $correlationId = CorrelationId::generate();
    $sink = new ConsumerProfileSummarySink();
    $coordinator = new TerminalRequestCoordinator(
        new RequestBoundary(
            consumerProfileRequestReader($name, $body),
            $application,
            consumerProfileErrorResponses(),
        ),
        new UnknownFailureBoundary(),
        $correlationId,
        $sink,
        new DocumentDetailsCacheTrace(),
        [new QuerySummarySource('create_user', $budget, $queryTrace)],
    );
    $response = $coordinator->handle(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/accounts/42/users',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) strlen($body),
            'HTTP_AUTHORIZATION' => ConsumerProfileAccountPolicy::CREDENTIAL,
        ],
        [],
    );

    return new ConsumerProfileResult(
        $response,
        $sink->onlySummary(),
        $sink->attempts,
        $policyTrace->steps,
        $budget->used(),
        $queryTrace->snapshot(),
        $createConnection->inTransaction(),
        consumerProfileCounts($databasePath),
    );
}

function consumerProfileValidBody(): string
{
    return '{"name":"Profile Name Marker","email":"profile-secret@example.com"}';
}

function consumerProfileRequestReader(string $name, string $body): RequestReader
{
    $directory = __DIR__ . '/../tmp/request-bodies';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the consumer-profile body directory.');
    }

    $path = $directory . '/consumer-profile-' . hash('sha256', $name . "\0" . $body) . '.body';
    $written = file_put_contents($path, $body, LOCK_EX);

    if (!is_int($written) || $written !== strlen($body)) {
        throw new RuntimeException('Unable to write the consumer-profile body fixture.');
    }

    return new RequestReader(8_192, $path);
}

function consumerProfileErrorResponses(): ErrorResponseRegistry
{
    $privateHeaders = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'private, no-store',
    ];
    $forbidden = new Response(
        403,
        $privateHeaders,
        "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n",
    );

    return new ErrorResponseRegistry([
        Unauthenticated::class => new Response(
            401,
            $privateHeaders + ['WWW-Authenticate' => 'Bearer'],
            "{\"error\":{\"code\":\"unauthenticated\",\"message\":\"Authentication is required.\"}}\n",
        ),
        CrossTenant::class => $forbidden,
        Forbidden::class => $forbidden,
        InvalidRequest::class => new Response(
            400,
            $privateHeaders,
            "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ),
        RequestBodyTooLarge::class => new Response(
            413,
            $privateHeaders,
            "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
        ),
        UnsupportedMediaType::class => new Response(
            415,
            $privateHeaders,
            "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type is unsupported.\"}}\n",
        ),
    ]);
}

function consumerProfileCounts(string $databasePath): ConsumerProfileCounts
{
    $row = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    )->selectOneRow(
        <<<'SQL'
            SELECT
                (
                    SELECT COUNT(*)
                    FROM users
                    WHERE users.email = :email
                ) AS user_count,
                (SELECT COUNT(*) FROM account_users) AS account_user_count,
                (
                    SELECT COUNT(*)
                    FROM user_events
                    WHERE user_events.event_type = :event_type
                ) AS event_count,
                (
                    SELECT COUNT(*)
                    FROM application_jobs
                    WHERE application_jobs.status = :job_status
                ) AS job_count,
                (
                    SELECT application_jobs.job_id
                    FROM application_jobs
                    WHERE application_jobs.status = :job_id_status
                    ORDER BY application_jobs.job_id ASC
                    LIMIT 1
                ) AS job_id,
                (
                    SELECT application_jobs.envelope_json
                    FROM application_jobs
                    WHERE application_jobs.status = :job_envelope_status
                    ORDER BY application_jobs.job_id ASC
                    LIMIT 1
                ) AS envelope_json
            SQL,
        [
            'event_type' => 'user.created',
            'job_status' => 'available',
            'job_id_status' => 'available',
            'job_envelope_status' => 'available',
            'email' => 'profile-secret@example.com',
        ],
    );

    $users = $row['user_count'] ?? null;
    $accountUsers = $row['account_user_count'] ?? null;
    $events = $row['event_count'] ?? null;
    $jobs = $row['job_count'] ?? null;
    $jobId = $row['job_id'] ?? null;
    $envelopeJson = $row['envelope_json'] ?? null;

    if (!is_int($users) || !is_int($accountUsers) || !is_int($events) || !is_int($jobs)) {
        throw new RuntimeException('Consumer-profile verification counts must be integers.');
    }

    if ($jobs === 0 && ($jobId !== null || $envelopeJson !== null)) {
        throw new RuntimeException('Consumer-profile job projection must be absent when no job exists.');
    }

    if ($jobs > 0 && (!is_string($jobId) || !is_string($envelopeJson))) {
        throw new RuntimeException('Consumer-profile job projection must be complete when a job exists.');
    }

    $job = is_string($jobId) && is_string($envelopeJson)
        ? UserWelcomeJobEnvelope::fromStored($jobId, $envelopeJson)
        : null;

    return new ConsumerProfileCounts($users, $accountUsers, $events, $jobs, $job);
}
