<?php

declare(strict_types=1);

use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobHandler;
use MySqlWelcomeRecipe\MySqlWelcomeClock;
use MySqlWelcomeRecipe\MySqlWelcomeJobLease;
use MySqlWelcomeRecipe\MySqlWelcomeJobWorker;
use MySqlWelcomeRecipe\MySqlWelcomeSession;
use MySqlWelcomeRecipe\ProducerConfiguration;
use MySqlWelcomeRecipe\PublishWelcomeRequest;
use MySqlWelcomeRecipe\RecordMySqlWelcomeDelivery;
use MySqlWelcomeRecipe\ReferenceEnvironment;
use MySqlWelcomeRecipe\VerificationConfiguration;
use MySqlWelcomeRecipe\WorkerConfiguration;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require_once dirname(__DIR__, 3) . '/process-support.php';

final readonly class JobProof
{
    public Connection $admin;
    public ProducerConfiguration $producer;
    public WorkerConfiguration $worker;

    public function __construct()
    {
        $admin = ReferenceEnvironment::forVerification();
        $this->producer = ReferenceEnvironment::forProducer();
        $this->worker = ReferenceEnvironment::forWorker();
        // Prove the exact owned container/loopback port before any connection or
        // destructive fixture statement. DSN shape alone is not isolation.
        $this->processes('fixture-preflight');
        $this->admin = Connection::connect($admin->dsn, new QueryBudget(2_000), new QueryTrace(1), VerificationConfiguration::USERNAME, $admin->password, [PDO::ATTR_TIMEOUT => 2]);
        MySqlWelcomeSession::configure($this->admin);
    }

    public function clear(): void
    {
        $this->admin->executeStatement('DELETE FROM welcome_deliveries');
        $this->admin->executeStatement('DELETE FROM application_jobs');
        $this->admin->executeStatement('DELETE FROM welcome_requests');
    }

    public function producerConnection(?QueryBudget $budget = null): Connection
    {
        $connection = Connection::connect($this->producer->dsn, $budget ?? new QueryBudget(32), new QueryTrace(32), ProducerConfiguration::USERNAME, $this->producer->password, [PDO::ATTR_TIMEOUT => 2]);
        MySqlWelcomeSession::configure($connection);
        return $connection;
    }

    public function workerConnection(?QueryBudget $budget = null): Connection
    {
        $connection = Connection::connect($this->worker->dsn, $budget ?? new QueryBudget(32), new QueryTrace(32), WorkerConfiguration::USERNAME, $this->worker->password, [PDO::ATTR_TIMEOUT => 2]);
        MySqlWelcomeSession::configure($connection);
        return $connection;
    }

    public function publish(string $email, int $delay = 0): UserWelcomeJobEnvelope
    {
        $job = UserWelcomeJobEnvelope::forEmail($email);
        $connection = $this->producerConnection();
        (new PublishWelcomeRequest($connection, new MySqlWelcomeClock($connection)))->publish($job, $delay);
        return $job;
    }

    public function consumer(?UserWelcomeJobHandler $handler = null, ?QueryBudget $budget = null): MySqlWelcomeJobWorker
    {
        $connection = $this->workerConnection($budget);
        return new MySqlWelcomeJobWorker($connection, $handler ?? new RecordMySqlWelcomeDelivery(), new MySqlWelcomeClock($connection));
    }

    /** @return array<string, mixed> */
    public function row(string $id): array
    {
        return $this->admin->selectOneRow(
            'SELECT job_id, envelope_json, status, attempts_started, lease_token, lease_expires_at, available_at, updated_at, last_failure_code FROM application_jobs WHERE job_id = :id',
            ['id' => $id],
        ) ?? throw new RuntimeException('proof_job_missing');
    }

    public function lease(string $id): MySqlWelcomeJobLease
    {
        $row = $this->admin->selectOneRow(
            'SELECT job_id, envelope_json, status, attempts_started, lease_token, lease_expires_at FROM application_jobs WHERE job_id = :id',
            ['id' => $id],
        );
        return MySqlWelcomeJobLease::fromDatabaseRow($row ?? throw new RuntimeException('proof_lease_missing'));
    }

    public function expire(string $id): void
    {
        $this->admin->executeStatement('UPDATE application_jobs SET lease_expires_at = UNIX_TIMESTAMP() - 1 WHERE job_id = :id AND status = \'leased\'', ['id' => $id]);
    }

    public function makeDue(string $id): void
    {
        $this->admin->executeStatement('UPDATE application_jobs SET available_at = UNIX_TIMESTAMP() WHERE job_id = :id AND status = \'available\'', ['id' => $id]);
    }

    /** @return array{requests: int, jobs: int, deliveries: int} */
    public function counts(): array
    {
        $row = $this->admin->selectOneRow('SELECT (SELECT COUNT(*) FROM welcome_requests) AS requests, (SELECT COUNT(*) FROM application_jobs) AS jobs, (SELECT COUNT(*) FROM welcome_deliveries) AS deliveries');
        if ($row === null || !is_int($row['requests'] ?? null) || !is_int($row['jobs'] ?? null) || !is_int($row['deliveries'] ?? null)) {
            throw new RuntimeException('proof_invalid_counts');
        }
        return ['requests' => $row['requests'], 'jobs' => $row['jobs'], 'deliveries' => $row['deliveries']];
    }

    public function duplicate(UserWelcomeJobEnvelope $job): string
    {
        $id = bin2hex(random_bytes(16));
        $this->admin->executeStatement(
            'INSERT INTO application_jobs (job_id, envelope_json, status, attempts_started, available_at, created_at, updated_at) VALUES (:id, :json, \'available\', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP())',
            ['id' => $id, 'json' => $job->toJson()],
        );
        return $id;
    }

    public function processes(string $case): void
    {
        $verification = ReferenceEnvironment::forVerification();
        $fixture = ReferenceEnvironment::forFixture();
        $result = runBoundedMaintainerProcess(
            ['python3', __DIR__ . '/../processes.py', $case, PHP_BINARY],
            dirname(__DIR__, 2),
            [
                'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin',
                'LANG' => 'C',
                'PHPTHIS_MYSQL_JOBS_PRODUCER_DSN' => $this->producer->dsn,
                'PHPTHIS_MYSQL_JOBS_PRODUCER_PASSWORD' => $this->producer->password,
                'PHPTHIS_MYSQL_JOBS_WORKER_DSN' => $this->worker->dsn,
                'PHPTHIS_MYSQL_JOBS_WORKER_PASSWORD' => $this->worker->password,
                'PHPTHIS_MYSQL_JOBS_VERIFICATION_DSN' => $verification->dsn,
                'PHPTHIS_MYSQL_JOBS_VERIFICATION_PASSWORD' => $verification->password,
                'PHPTHIS_MYSQL_JOBS_RUN_ID' => $fixture->id,
            ],
            90_000,
            4096,
            4096,
        );
        jobProof($result === ['exit_code' => 0, 'stdout' => "PROCESSES PASS\n", 'stderr' => ''], 'process_case_failed_' . $case);
    }
}

function jobProof(bool $condition, string $code): void
{
    if (!$condition) {
        throw new RuntimeException($code);
    }
}
