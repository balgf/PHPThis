<?php

declare(strict_types=1);

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\ResolvedTenant;
use Example\Jobs\RecordUserWelcomeDelivery;
use Example\Jobs\SqliteUserWelcomeJobWorker;
use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobClock;
use Example\Jobs\UserWelcomeJobHandler;
use Example\Jobs\UserWelcomeJobOutcome;
use Example\Users\CreateUser\CreateUserCommand;
use Example\Users\CreateUser\TransactionalCreateUser;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final class TestUserWelcomeJobClock implements UserWelcomeJobClock
{
    public function __construct(public int $currentTime)
    {
    }

    public function now(): int
    {
        return $this->currentTime;
    }
}

final class SequenceUserWelcomeJobClock implements UserWelcomeJobClock
{
    private int $position = 0;

    /** @param non-empty-list<int> $times */
    public function __construct(private readonly array $times)
    {
    }

    public function now(): int
    {
        if (!array_key_exists($this->position, $this->times)) {
            throw new RuntimeException('Test job clock sequence is exhausted.');
        }

        return $this->times[$this->position++];
    }
}

/** @return array<string, Closure(): void> */
function jobTests(): array
{
    return [
        'durable job publication rolls back business event and job together' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-publication-rollback', 0, false);
            $schema = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(1),
                new QueryTrace(1),
            );
            $schema->executeStatement(
                <<<'SQL'
                    CREATE TRIGGER reject_welcome_job
                    BEFORE INSERT ON application_jobs
                    BEGIN
                        SELECT RAISE(ABORT, 'synthetic job publication failure');
                    END
                    SQL,
            );
            $budget = new QueryBudget(4);
            $trace = new QueryTrace(4);
            $operation = new TransactionalCreateUser(
                Connection::connect('sqlite:' . $databasePath, $budget, $trace),
            );
            $failed = false;

            try {
                $accountId = AccountId::fromPositiveInteger(42);
                $operation->execute(
                    AuthenticatedPrincipal::fromPositiveInteger(7),
                    ResolvedTenant::forAccount($accountId),
                    $accountId,
                    CreateUserCommand::fromJson(
                        '{"name":"Ada","email":"ada@example.com"}',
                    ),
                );
            } catch (PDOException) {
                $failed = true;
            }

            $counts = jobAggregate($databasePath);
            $summary = $trace->snapshot();

            if (
                !$failed
                || $budget->used() !== 4
                || $summary['statements'] !== 4
                || $summary['failures'] !== 1
                || $counts['user_count'] !== 0
                || $counts['account_user_count'] !== 0
                || $counts['event_count'] !== 0
                || $counts['job_count'] !== 0
                || $counts['effect_count'] !== 0
            ) {
                throw new RuntimeException('Job publication failure must roll back the complete business transaction.');
            }
        },

        'durable job worker is idle and keeps three statements across queue sizes' => static function (): void {
            $idlePath = createUserDatabaseFixture('job-worker-idle', 0, false);
            $idle = runUserWelcomeWorker(
                $idlePath,
                1_000,
                str_repeat('1', 32),
                1,
            );

            $smallPath = createUserDatabaseFixture('job-worker-small', 0, false);
            $smallEnvelope = UserWelcomeJobEnvelope::forEmail('scale@example.com');
            insertAvailableJob($smallPath, $smallEnvelope->jobId, $smallEnvelope->toJson(), 900);
            $small = runUserWelcomeWorker(
                $smallPath,
                1_000,
                str_repeat('2', 32),
                3,
            );

            $largePath = createUserDatabaseFixture('job-worker-large', 0, false);
            $largeEnvelope = UserWelcomeJobEnvelope::forEmail('scale@example.com');
            seedAvailableJobs($largePath, $largeEnvelope->toJson(), 500, 900);
            $large = runUserWelcomeWorker(
                $largePath,
                1_000,
                str_repeat('3', 32),
                3,
            );
            $largeCounts = jobAggregate($largePath);

            if (
                $idle['outcome'] !== UserWelcomeJobOutcome::Idle
                || $idle['budget']->used() !== 1
                || $idle['trace']->snapshot()['statements'] !== 1
                || $small['outcome'] !== UserWelcomeJobOutcome::Completed
                || $large['outcome'] !== UserWelcomeJobOutcome::Completed
                || workerEvidence($small) !== workerEvidence($large)
                || workerEvidence($small) !== [
                    'used' => 3,
                    'statements' => 3,
                    'failures' => 0,
                    'repeated_fingerprints' => 0,
                    'maximum_executions' => 1,
                ]
                || $largeCounts['available_count'] !== 499
                || $largeCounts['succeeded_count'] !== 1
                || $largeCounts['effect_count'] !== 1
            ) {
                throw new RuntimeException('One-shot job work must stay constant across queue cardinalities.');
            }
        },

        'durable job duplicate deliveries produce one idempotent effect' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-duplicate', 0, false);
            $first = UserWelcomeJobEnvelope::forEmail('duplicate@example.com');
            $second = UserWelcomeJobEnvelope::forEmail('duplicate@example.com');
            insertAvailableJob($databasePath, $first->jobId, $first->toJson(), 900);
            insertAvailableJob($databasePath, $second->jobId, $second->toJson(), 901);

            $firstRun = runUserWelcomeWorker(
                $databasePath,
                1_000,
                str_repeat('4', 32),
                3,
            );
            $secondRun = runUserWelcomeWorker(
                $databasePath,
                1_001,
                str_repeat('5', 32),
                3,
            );
            $counts = jobAggregate($databasePath);

            if (
                $first->jobId === $second->jobId
                || $first->idempotencyKey !== $second->idempotencyKey
                || $firstRun['outcome'] !== UserWelcomeJobOutcome::Completed
                || $secondRun['outcome'] !== UserWelcomeJobOutcome::Completed
                || $counts['succeeded_count'] !== 2
                || $counts['effect_count'] !== 1
            ) {
                throw new RuntimeException('Duplicate semantic delivery must create one durable effect.');
            }
        },

        'durable job retry uses exact bounded backoff and later succeeds' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-retry', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('retry@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 900);
            installRejectWelcomeDeliveryTrigger($databasePath);

            $first = runUserWelcomeWorker(
                $databasePath,
                1_000,
                str_repeat('6', 32),
                3,
            );
            $afterFirst = jobState($databasePath, $job->jobId);
            $early = runUserWelcomeWorker(
                $databasePath,
                1_004,
                str_repeat('7', 32),
                1,
            );
            dropRejectWelcomeDeliveryTrigger($databasePath);
            $second = runUserWelcomeWorker(
                $databasePath,
                1_005,
                str_repeat('8', 32),
                3,
            );
            $completed = jobState($databasePath, $job->jobId);

            if (
                $first['outcome'] !== UserWelcomeJobOutcome::RetryScheduled
                || workerEvidence($first)['statements'] !== 3
                || workerEvidence($first)['failures'] !== 1
                || $afterFirst['status'] !== 'available'
                || $afterFirst['attempts_started'] !== 1
                || $afterFirst['available_at'] !== 1_005
                || $afterFirst['last_failure_code'] !== 'handler_failure'
                || $early['outcome'] !== UserWelcomeJobOutcome::Idle
                || $early['budget']->used() !== 1
                || $second['outcome'] !== UserWelcomeJobOutcome::Completed
                || $completed['status'] !== 'succeeded'
                || $completed['attempts_started'] !== 2
                || $completed['last_failure_code'] !== null
                || jobAggregate($databasePath)['effect_count'] !== 1
            ) {
                throw new RuntimeException('Job retry must honor the exact five-second first backoff.');
            }
        },

        'durable job attempts stop at three and become a redacted dead letter' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-exhaustion', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('exhaustion@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 900);
            installRejectWelcomeDeliveryTrigger($databasePath);

            $first = runUserWelcomeWorker(
                $databasePath,
                1_000,
                str_repeat('9', 32),
                3,
            );
            $second = runUserWelcomeWorker(
                $databasePath,
                1_005,
                str_repeat('a', 32),
                3,
            );
            $afterSecond = jobState($databasePath, $job->jobId);
            $third = runUserWelcomeWorker(
                $databasePath,
                1_035,
                str_repeat('b', 32),
                3,
            );
            $dead = jobState($databasePath, $job->jobId);

            if (
                $first['outcome'] !== UserWelcomeJobOutcome::RetryScheduled
                || $second['outcome'] !== UserWelcomeJobOutcome::RetryScheduled
                || $afterSecond['available_at'] !== 1_035
                || $afterSecond['attempts_started'] !== 2
                || $third['outcome'] !== UserWelcomeJobOutcome::DeadLettered
                || $dead['status'] !== 'dead'
                || $dead['attempts_started'] !== 3
                || $dead['last_failure_code'] !== 'handler_failure'
                || $dead['lease_token'] !== null
                || $dead['lease_expires_at'] !== null
                || jobAggregate($databasePath)['effect_count'] !== 0
                || str_contains(json_encode($dead, JSON_THROW_ON_ERROR), 'synthetic delivery failure')
            ) {
                throw new RuntimeException('Three failed starts must produce one redacted terminal dead letter.');
            }
        },

        'durable job expired final lease becomes dead without a fourth attempt' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-final-crash', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('final-crash@example.com');
            insertExpiredFinalLease(
                $databasePath,
                $job->jobId,
                $job->toJson(),
                str_repeat('c', 32),
                1_000,
            );
            $handler = new class implements UserWelcomeJobHandler {
                public int $calls = 0;

                public function handle(
                    Connection $connection,
                    UserWelcomeJobEnvelope $job,
                    int $now,
                ): void {
                    $this->calls++;
                }
            };
            $run = runUserWelcomeWorker(
                $databasePath,
                1_000,
                str_repeat('d', 32),
                1,
                $handler,
            );
            $dead = jobState($databasePath, $job->jobId);

            if (
                $run['outcome'] !== UserWelcomeJobOutcome::DeadLettered
                || $run['budget']->used() !== 1
                || $handler->calls !== 0
                || $dead['status'] !== 'dead'
                || $dead['attempts_started'] !== 3
                || $dead['last_failure_code'] !== 'lease_expired_after_final_attempt'
                || $dead['dead_at'] !== 1_000
            ) {
                throw new RuntimeException('An expired final lease must become terminal without dispatch.');
            }
        },

        'durable job poison envelopes never dispatch and retain only redacted diagnostics' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-poison', 0, false);
            $valid = UserWelcomeJobEnvelope::forEmail('poison@example.com');
            $invalidSecret = 'poison-secret-value';
            $unsupportedVersion = json_encode([
                'version' => 2,
                'type' => UserWelcomeJobEnvelope::TYPE,
                'idempotency_key' => $valid->idempotencyKey,
                'payload' => ['email' => $valid->email],
            ], JSON_THROW_ON_ERROR);
            $unsupportedType = json_encode([
                'version' => UserWelcomeJobEnvelope::VERSION,
                'type' => 'unknown.type',
                'idempotency_key' => $valid->idempotencyKey,
                'payload' => ['email' => $valid->email],
            ], JSON_THROW_ON_ERROR);
            $invalidPayload = json_encode([
                'version' => UserWelcomeJobEnvelope::VERSION,
                'type' => UserWelcomeJobEnvelope::TYPE,
                'idempotency_key' => $valid->idempotencyKey,
                'payload' => ['email' => null],
            ], JSON_THROW_ON_ERROR);
            insertAvailableJob($databasePath, str_repeat('1', 32), '{"secret":"' . $invalidSecret, 900);
            insertAvailableJob($databasePath, str_repeat('2', 32), $unsupportedVersion, 901);
            insertAvailableJob($databasePath, str_repeat('3', 32), $unsupportedType, 902);
            insertAvailableJob($databasePath, str_repeat('4', 32), $invalidPayload, 903);
            $handler = new class implements UserWelcomeJobHandler {
                public int $calls = 0;

                public function handle(
                    Connection $connection,
                    UserWelcomeJobEnvelope $job,
                    int $now,
                ): void {
                    $this->calls++;
                }
            };

            $first = runUserWelcomeWorker($databasePath, 1_000, str_repeat('e', 32), 2, $handler);
            $second = runUserWelcomeWorker($databasePath, 1_001, str_repeat('f', 32), 2, $handler);
            $third = runUserWelcomeWorker($databasePath, 1_002, str_repeat('0', 32), 2, $handler);
            $fourth = runUserWelcomeWorker($databasePath, 1_003, str_repeat('5', 32), 2, $handler);
            $counts = jobAggregate($databasePath);
            $diagnostics = json_encode([
                jobState($databasePath, str_repeat('1', 32)),
                jobState($databasePath, str_repeat('2', 32)),
                jobState($databasePath, str_repeat('3', 32)),
                jobState($databasePath, str_repeat('4', 32)),
            ], JSON_THROW_ON_ERROR);

            if (
                $first['outcome'] !== UserWelcomeJobOutcome::DeadLettered
                || $second['outcome'] !== UserWelcomeJobOutcome::DeadLettered
                || $third['outcome'] !== UserWelcomeJobOutcome::DeadLettered
                || $fourth['outcome'] !== UserWelcomeJobOutcome::DeadLettered
                || $handler->calls !== 0
                || $counts['dead_count'] !== 4
                || $counts['effect_count'] !== 0
                || substr_count($diagnostics, 'invalid_envelope') !== 4
                || str_contains($diagnostics, $invalidSecret)
            ) {
                throw new RuntimeException('Poison jobs must dead-letter without dynamic dispatch or disclosure.');
            }
        },

        'durable job samples fresh time before dispatch and skips an expired lease' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-fresh-dispatch-time', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('fresh-dispatch@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 900);
            $handler = new class implements UserWelcomeJobHandler {
                public int $calls = 0;

                public function handle(
                    Connection $connection,
                    UserWelcomeJobEnvelope $job,
                    int $now,
                ): void {
                    $this->calls++;
                }
            };
            $expiredBeforeDispatch = false;

            try {
                runUserWelcomeWorker(
                    $databasePath,
                    1_000,
                    str_repeat('0', 32),
                    1,
                    $handler,
                    new SequenceUserWelcomeJobClock([1_000, 1_030]),
                );
            } catch (RuntimeException $exception) {
                $expiredBeforeDispatch = $exception->getMessage()
                    === 'User-welcome job lease expired before delivery began.';
            }

            $expired = jobState($databasePath, $job->jobId);
            $recovered = runUserWelcomeWorker(
                $databasePath,
                1_030,
                str_repeat('1', 32),
                3,
            );

            if (
                !$expiredBeforeDispatch
                || $handler->calls !== 0
                || $expired['status'] !== 'leased'
                || $expired['attempts_started'] !== 1
                || $expired['lease_expires_at'] !== 1_030
                || $recovered['outcome'] !== UserWelcomeJobOutcome::Completed
                || jobAggregate($databasePath)['effect_count'] !== 1
            ) {
                throw new RuntimeException('A fresh pre-dispatch clock read must prevent work under an expired lease.');
            }
        },

        'durable job completion samples fresh time and rejects an expired lease' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-fresh-completion-time', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('fresh-completion@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 900);
            $clock = new TestUserWelcomeJobClock(1_000);
            $handler = new class($clock) implements UserWelcomeJobHandler {
                public function __construct(private TestUserWelcomeJobClock $clock)
                {
                }

                public function handle(
                    Connection $connection,
                    UserWelcomeJobEnvelope $job,
                    int $now,
                ): void {
                    (new RecordUserWelcomeDelivery())->handle($connection, $job, $now);
                    $this->clock->currentTime = 1_030;
                }
            };
            $lostLease = false;

            try {
                runUserWelcomeWorker(
                    $databasePath,
                    1_000,
                    str_repeat('a', 32),
                    3,
                    $handler,
                    $clock,
                );
            } catch (RuntimeException $exception) {
                $lostLease = $exception->getMessage() === 'User-welcome completion lost its active lease.';
            }

            $expired = jobState($databasePath, $job->jobId);
            $beforeRecovery = jobAggregate($databasePath);
            $recovered = runUserWelcomeWorker(
                $databasePath,
                1_030,
                str_repeat('b', 32),
                3,
            );
            $completed = jobState($databasePath, $job->jobId);

            if (
                !$lostLease
                || $expired['status'] !== 'leased'
                || $expired['attempts_started'] !== 1
                || $expired['lease_expires_at'] !== 1_030
                || $beforeRecovery['effect_count'] !== 0
                || $recovered['outcome'] !== UserWelcomeJobOutcome::Completed
                || $completed['status'] !== 'succeeded'
                || $completed['attempts_started'] !== 2
                || jobAggregate($databasePath)['effect_count'] !== 1
            ) {
                throw new RuntimeException('Completion must recheck fresh time and roll back an effect after lease expiry.');
            }
        },

        'durable job retry backoff starts from freshly observed failure time' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-fresh-failure-time', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('fresh-failure@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 900);
            $clock = new TestUserWelcomeJobClock(2_000);
            $privateFailure = 'private-handler-failure';
            $handler = new class($clock, $privateFailure) implements UserWelcomeJobHandler {
                public function __construct(
                    private TestUserWelcomeJobClock $clock,
                    private readonly string $privateFailure,
                ) {
                }

                public function handle(
                    Connection $connection,
                    UserWelcomeJobEnvelope $job,
                    int $now,
                ): void {
                    $this->clock->currentTime = 2_012;

                    throw new RuntimeException($this->privateFailure);
                }
            };
            $failed = runUserWelcomeWorker(
                $databasePath,
                2_000,
                str_repeat('c', 32),
                2,
                $handler,
                $clock,
            );
            $scheduled = jobState($databasePath, $job->jobId);
            $early = runUserWelcomeWorker(
                $databasePath,
                2_016,
                str_repeat('d', 32),
                1,
            );
            $recovered = runUserWelcomeWorker(
                $databasePath,
                2_017,
                str_repeat('e', 32),
                3,
            );

            if (
                $failed['outcome'] !== UserWelcomeJobOutcome::RetryScheduled
                || $scheduled['available_at'] !== 2_017
                || $scheduled['last_failure_code'] !== 'handler_failure'
                || str_contains(json_encode($scheduled, JSON_THROW_ON_ERROR), $privateFailure)
                || $early['outcome'] !== UserWelcomeJobOutcome::Idle
                || $recovered['outcome'] !== UserWelcomeJobOutcome::Completed
            ) {
                throw new RuntimeException('Retry delay must start from fresh failure time without retaining failure details.');
            }
        },

        'durable job subprocess crash is fenced and safely redelivered after lease expiry' => static function (): void {
            $databasePath = createUserDatabaseFixture('job-process-crash', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('crash@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 900);
            $firstToken = str_repeat('6', 32);
            $secondToken = str_repeat('7', 32);
            terminateClaimedJobProcess($databasePath, 1_000, $firstToken);
            $firstLease = jobState($databasePath, $job->jobId);
            $early = runUserWelcomeWorker(
                $databasePath,
                1_029,
                str_repeat('8', 32),
                1,
            );
            terminateClaimedJobProcess($databasePath, 1_030, $secondToken);
            $secondLease = jobState($databasePath, $job->jobId);
            $staleWrite = attemptStaleJobCompletion(
                $databasePath,
                $job->jobId,
                $firstToken,
                1_031,
            );
            $recovered = runUserWelcomeWorker(
                $databasePath,
                1_060,
                str_repeat('9', 32),
                3,
            );
            $completed = jobState($databasePath, $job->jobId);

            if (
                $firstLease['status'] !== 'leased'
                || $firstLease['attempts_started'] !== 1
                || $firstLease['lease_token'] !== $firstToken
                || $firstLease['lease_expires_at'] !== 1_030
                || $early['outcome'] !== UserWelcomeJobOutcome::Idle
                || $secondLease['status'] !== 'leased'
                || $secondLease['attempts_started'] !== 2
                || $secondLease['lease_token'] !== $secondToken
                || $secondLease['lease_expires_at'] !== 1_060
                || $staleWrite !== 0
                || $recovered['outcome'] !== UserWelcomeJobOutcome::Completed
                || $completed['status'] !== 'succeeded'
                || $completed['attempts_started'] !== 3
                || jobAggregate($databasePath)['effect_count'] !== 1
            ) {
                throw new RuntimeException('A terminated claimant must be fenced and safely redelivered after expiry.');
            }
        },

    ];
}

/**
 * @return array{
 *     outcome: UserWelcomeJobOutcome,
 *     budget: QueryBudget,
 *     trace: QueryTrace
 * }
 */
function runUserWelcomeWorker(
    string $databasePath,
    int $now,
    string $leaseToken,
    int $budgetLimit,
    ?UserWelcomeJobHandler $handler = null,
    ?UserWelcomeJobClock $clock = null,
): array {
    $budget = new QueryBudget($budgetLimit);
    $trace = new QueryTrace($budgetLimit);
    $connection = Connection::connect('sqlite:' . $databasePath, $budget, $trace);
    $worker = new SqliteUserWelcomeJobWorker(
        $connection,
        $handler ?? new RecordUserWelcomeDelivery(),
        $clock ?? new TestUserWelcomeJobClock($now),
    );

    return [
        'outcome' => $worker->runOne($leaseToken),
        'budget' => $budget,
        'trace' => $trace,
    ];
}

/**
 * @param array{outcome: UserWelcomeJobOutcome, budget: QueryBudget, trace: QueryTrace} $run
 * @return array{used: int, statements: int, failures: int, repeated_fingerprints: int, maximum_executions: int}
 */
function workerEvidence(array $run): array
{
    $summary = $run['trace']->snapshot();

    return [
        'used' => $run['budget']->used(),
        'statements' => $summary['statements'],
        'failures' => $summary['failures'],
        'repeated_fingerprints' => $summary['repeated_fingerprints'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
    ];
}

function insertAvailableJob(
    string $databasePath,
    string $jobId,
    string $envelopeJson,
    int $availableAt,
): void {
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $inserted = $connection->executeStatement(
        <<<'SQL'
            INSERT INTO application_jobs (
                job_id,
                envelope_json,
                status,
                available_at,
                attempts_started,
                max_attempts,
                lease_token,
                lease_expires_at,
                last_failure_code,
                created_at,
                updated_at,
                completed_at,
                dead_at
            )
            VALUES (
                :job_id,
                :envelope_json,
                'available',
                :available_at,
                0,
                3,
                NULL,
                NULL,
                NULL,
                :created_at,
                :updated_at,
                NULL,
                NULL
            )
            SQL,
        [
            'job_id' => $jobId,
            'envelope_json' => $envelopeJson,
            'available_at' => $availableAt,
            'created_at' => $availableAt,
            'updated_at' => $availableAt,
        ],
    );

    if ($inserted !== 1) {
        throw new RuntimeException('Job fixture must insert exactly one available row.');
    }
}

function seedAvailableJobs(
    string $databasePath,
    string $envelopeJson,
    int $jobCount,
    int $availableAt,
): void {
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $inserted = $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                UNION ALL
                SELECT sequence.value + 1
                FROM sequence
                WHERE sequence.value < :queue_job_count
            )
            INSERT INTO application_jobs (
                job_id,
                envelope_json,
                status,
                available_at,
                attempts_started,
                max_attempts,
                lease_token,
                lease_expires_at,
                last_failure_code,
                created_at,
                updated_at,
                completed_at,
                dead_at
            )
            SELECT
                printf('%032x', sequence.value),
                :queue_envelope_json,
                'available',
                :queue_available_at,
                0,
                3,
                NULL,
                NULL,
                NULL,
                :queue_created_at,
                :queue_updated_at,
                NULL,
                NULL
            FROM sequence
            SQL,
        [
            'queue_job_count' => $jobCount,
            'queue_envelope_json' => $envelopeJson,
            'queue_available_at' => $availableAt,
            'queue_created_at' => $availableAt,
            'queue_updated_at' => $availableAt,
        ],
    );

    if ($inserted !== $jobCount) {
        throw new RuntimeException('Queue fixture did not insert its exact bounded row count.');
    }
}

function installRejectWelcomeDeliveryTrigger(string $databasePath): void
{
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TRIGGER reject_welcome_delivery
            BEFORE INSERT ON welcome_deliveries
            BEGIN
                SELECT RAISE(ABORT, 'synthetic delivery failure');
            END
            SQL,
    );
}

function dropRejectWelcomeDeliveryTrigger(string $databasePath): void
{
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $connection->executeStatement('DROP TRIGGER reject_welcome_delivery');
}

function insertExpiredFinalLease(
    string $databasePath,
    string $jobId,
    string $envelopeJson,
    string $leaseToken,
    int $leaseExpiresAt,
): void {
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO application_jobs (
                job_id,
                envelope_json,
                status,
                available_at,
                attempts_started,
                max_attempts,
                lease_token,
                lease_expires_at,
                last_failure_code,
                created_at,
                updated_at,
                completed_at,
                dead_at
            )
            VALUES (
                :final_job_id,
                :final_envelope_json,
                'leased',
                :final_available_at,
                3,
                3,
                :final_lease_token,
                :final_lease_expires_at,
                'lease_expired',
                :final_created_at,
                :final_updated_at,
                NULL,
                NULL
            )
            SQL,
        [
            'final_job_id' => $jobId,
            'final_envelope_json' => $envelopeJson,
            'final_available_at' => 0,
            'final_lease_token' => $leaseToken,
            'final_lease_expires_at' => $leaseExpiresAt,
            'final_created_at' => 0,
            'final_updated_at' => $leaseExpiresAt - 30,
        ],
    );
}

/** @return array<string, bool|int|string|null> */
function jobState(string $databasePath, string $jobId): array
{
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $row = $connection->selectOneRow(
        <<<'SQL'
            SELECT
                application_jobs.status,
                application_jobs.available_at,
                application_jobs.attempts_started,
                application_jobs.lease_token,
                application_jobs.lease_expires_at,
                application_jobs.last_failure_code,
                application_jobs.completed_at,
                application_jobs.dead_at
            FROM application_jobs
            WHERE application_jobs.job_id = :state_job_id
            SQL,
        ['state_job_id' => $jobId],
    );

    if ($row === null) {
        throw new RuntimeException('Expected a durable job state row.');
    }

    $state = [];

    foreach ($row as $name => $value) {
        if (
            !is_bool($value)
            && !is_int($value)
            && !is_string($value)
            && $value !== null
        ) {
            throw new RuntimeException('Job state contains an unsupported SQLite value.');
        }

        $state[$name] = $value;
    }

    return $state;
}

/**
 * @return array{
 *     user_count: int,
 *     account_user_count: int,
 *     event_count: int,
 *     job_count: int,
 *     available_count: int,
 *     leased_count: int,
 *     succeeded_count: int,
 *     dead_count: int,
 *     effect_count: int
 * }
 */
function jobAggregate(string $databasePath): array
{
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );
    $row = $connection->selectOneRow(
        <<<'SQL'
            SELECT
                (SELECT COUNT(users.id) FROM users) AS user_count,
                (SELECT COUNT(account_users.user_id) FROM account_users) AS account_user_count,
                (SELECT COUNT(user_events.id) FROM user_events) AS event_count,
                COUNT(application_jobs.job_id) AS job_count,
                COUNT(CASE WHEN application_jobs.status = 'available' THEN 1 END) AS available_count,
                COUNT(CASE WHEN application_jobs.status = 'leased' THEN 1 END) AS leased_count,
                COUNT(CASE WHEN application_jobs.status = 'succeeded' THEN 1 END) AS succeeded_count,
                COUNT(CASE WHEN application_jobs.status = 'dead' THEN 1 END) AS dead_count,
                (SELECT COUNT(welcome_deliveries.idempotency_key) FROM welcome_deliveries) AS effect_count
            FROM application_jobs
            SQL,
    );

    if ($row === null) {
        throw new RuntimeException('Expected a durable job aggregate row.');
    }

    $values = [];

    foreach ($row as $name => $value) {
        if (!is_int($value)) {
            throw new RuntimeException('Job aggregate values must be SQLite integers.');
        }

        $values[$name] = $value;
    }

    if (
        count($values) !== 9
        || !isset(
            $values['user_count'],
            $values['account_user_count'],
            $values['event_count'],
            $values['job_count'],
            $values['available_count'],
            $values['leased_count'],
            $values['succeeded_count'],
            $values['dead_count'],
            $values['effect_count'],
        )
    ) {
        throw new RuntimeException('Job aggregate projection is incomplete.');
    }

    return [
        'user_count' => $values['user_count'],
        'account_user_count' => $values['account_user_count'],
        'event_count' => $values['event_count'],
        'job_count' => $values['job_count'],
        'available_count' => $values['available_count'],
        'leased_count' => $values['leased_count'],
        'succeeded_count' => $values['succeeded_count'],
        'dead_count' => $values['dead_count'],
        'effect_count' => $values['effect_count'],
    ];
}

function attemptStaleJobCompletion(
    string $databasePath,
    string $jobId,
    string $leaseToken,
    int $now,
): int {
    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(1),
        new QueryTrace(1),
    );

    return $connection->executeStatement(
        <<<'SQL'
            UPDATE application_jobs
            SET
                status = 'succeeded',
                lease_token = NULL,
                lease_expires_at = NULL,
                last_failure_code = NULL,
                updated_at = :stale_updated_at,
                completed_at = :stale_completed_at
            WHERE job_id = :stale_job_id
              AND status = 'leased'
              AND lease_token = :stale_lease_token
              AND lease_expires_at > :stale_checked_at
            SQL,
        [
            'stale_updated_at' => $now,
            'stale_completed_at' => $now,
            'stale_job_id' => $jobId,
            'stale_lease_token' => $leaseToken,
            'stale_checked_at' => $now,
        ],
    );
}

function terminateClaimedJobProcess(
    string $databasePath,
    int $now,
    string $leaseToken,
): void {
    $process = proc_open(
        [
            PHP_BINARY,
            __DIR__ . '/job-worker-crash.php',
            $databasePath,
            (string) $now,
            $leaseToken,
        ],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the crash worker process.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = hrtime(true) + 5_000_000_000;

    while (!str_contains($stdout, "READY\n") && hrtime(true) < $deadline) {
        $stdoutChunk = stream_get_contents($pipes[1]);
        $stderrChunk = stream_get_contents($pipes[2]);

        if (is_string($stdoutChunk)) {
            $stdout .= $stdoutChunk;
        }

        if (is_string($stderrChunk)) {
            $stderr .= $stderrChunk;
        }

        if (!str_contains($stdout, "READY\n")) {
            usleep(10_000);
        }
    }

    if (!str_contains($stdout, "READY\n")) {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new RuntimeException('Crash worker did not confirm its committed lease: ' . $stderr);
    }

    proc_terminate($process);
    $remainingStdout = stream_get_contents($pipes[1]);
    $remainingStderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if (is_string($remainingStdout)) {
        $stdout .= $remainingStdout;
    }

    if (is_string($remainingStderr)) {
        $stderr .= $remainingStderr;
    }

    if ($stdout !== "READY\n" || $stderr !== '') {
        throw new RuntimeException('Crash worker emitted unexpected output.');
    }
}
