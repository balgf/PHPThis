<?php

declare(strict_types=1);

use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobHandler;
use MySqlWelcomeRecipe\MySqlWelcomeClock;
use MySqlWelcomeRecipe\MySqlWelcomeJobWorker;
use MySqlWelcomeRecipe\MySqlWelcomeSession;
use MySqlWelcomeRecipe\ProducerConfiguration;
use MySqlWelcomeRecipe\PublishWelcomeRequest;
use MySqlWelcomeRecipe\RecordMySqlWelcomeDelivery;
use MySqlWelcomeRecipe\ReferenceEnvironment;
use MySqlWelcomeRecipe\VerificationConfiguration;
use MySqlWelcomeRecipe\WelcomeLeaseLost;
use MySqlWelcomeRecipe\WorkerConfiguration;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__) . '/bootstrap.php';

final class ProbeWelcomeHandler implements UserWelcomeJobHandler
{
    public int $calls = 0;

    public function __construct(private readonly bool $pauseAfterEffect)
    {
    }

    public function handle(Connection $connection, UserWelcomeJobEnvelope $job, int $now): void
    {
        ++$this->calls;
        (new RecordMySqlWelcomeDelivery())->handle($connection, $job, $now);
        if ($this->pauseAfterEffect) {
            fwrite(STDOUT, "READY\n");
            usleep(60_000_000);
            throw new RuntimeException('Probe was not terminated.');
        }
    }
}

try {
    $mode = $argc === 2 ? $argv[1] : '';
    if ($mode === 'expire') {
        $configuration = ReferenceEnvironment::forVerification();
        $connection = Connection::connect($configuration->dsn, new QueryBudget(8), new QueryTrace(8), VerificationConfiguration::USERNAME, $configuration->password, [PDO::ATTR_TIMEOUT => 2]);
        $changed = $connection->executeStatement('UPDATE application_jobs SET lease_expires_at = UNIX_TIMESTAMP() - 1 WHERE status = \'leased\'');
        if ($changed !== 1) {
            throw new RuntimeException('Probe did not expire exactly one lease.');
        }
        fwrite(STDOUT, "EXPIRED\n");
        exit(0);
    }
    if ($mode === 'publish-pause' || $mode === 'uncommitted-pause') {
        $configuration = ReferenceEnvironment::forProducer();
        $connection = Connection::connect($configuration->dsn, new QueryBudget(32), new QueryTrace(32), ProducerConfiguration::USERNAME, $configuration->password, [PDO::ATTR_TIMEOUT => 2]);
        MySqlWelcomeSession::configure($connection);
        $job = UserWelcomeJobEnvelope::forEmail('lost-ack@example.invalid');
        if ($mode === 'publish-pause') {
            (new PublishWelcomeRequest($connection, new MySqlWelcomeClock($connection)))->publish($job);
        } else {
            $connection->beginTransaction();
            $connection->executeStatement('INSERT INTO welcome_requests (idempotency_key, recipient_email, job_id, requested_at) VALUES (:key, :email, :id, UNIX_TIMESTAMP())', ['key' => $job->idempotencyKey, 'email' => $job->email, 'id' => $job->jobId]);
            $connection->executeStatement('INSERT INTO application_jobs (job_id, envelope_json, status, attempts_started, available_at, created_at, updated_at) VALUES (:id, :json, \'available\', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP())', ['id' => $job->jobId, 'json' => $job->toJson()]);
        }
        fwrite(STDOUT, "READY\n");
        usleep(60_000_000);
        throw new RuntimeException('Producer probe was not terminated.');
    }
    if (!in_array($mode, ['claim-pause', 'claim-wait', 'claim-finish-wait', 'effect-pause'], true)) {
        throw new RuntimeException('Unsupported probe.');
    }
    $configuration = ReferenceEnvironment::forWorker();
    $connection = Connection::connect($configuration->dsn, new QueryBudget(32), new QueryTrace(32), WorkerConfiguration::USERNAME, $configuration->password, [PDO::ATTR_TIMEOUT => 2]);
    MySqlWelcomeSession::configure($connection);
    $handler = new ProbeWelcomeHandler($mode === 'effect-pause');
    $worker = new MySqlWelcomeJobWorker($connection, $handler, new MySqlWelcomeClock($connection));
    if ($mode === 'effect-pause') {
        $worker->runOne(bin2hex(random_bytes(16)));
        throw new RuntimeException('Effect probe was not terminated.');
    }
    $lease = $worker->claimOne(bin2hex(random_bytes(16)));
    if ($lease === null || $lease->status !== 'leased') {
        throw new RuntimeException('Probe did not claim one delivery.');
    }
    fwrite(STDOUT, "READY\n");
    if ($mode === 'claim-pause') {
        usleep(60_000_000);
        throw new RuntimeException('Claim probe was not terminated.');
    }
    if (fgets(STDIN, 8) !== "GO\n") {
        throw new RuntimeException('Probe resume was not received.');
    }
    if ($mode === 'claim-finish-wait') {
        if ($worker->finishClaim($lease)->value !== 'completed' || $handler->calls !== 1) {
            throw new RuntimeException('Concurrent delivery failed.');
        }
        fwrite(STDOUT, "COMPLETED\n");
        exit(0);
    }
    try {
        $worker->finishClaim($lease);
        throw new LogicException('Stale probe completed.');
    } catch (WelcomeLeaseLost) {
        if ($handler->calls !== 0) {
            throw new RuntimeException('Stale probe entered its handler.');
        }
        fwrite(STDOUT, "STALE_REJECTED\n");
    }
} catch (Throwable) {
    fwrite(STDERR, "PROBE_FAILED\n");
    exit(1);
}
