<?php

declare(strict_types=1);

use Example\Jobs\UserWelcomeJobEnvelope;
use Example\Jobs\UserWelcomeJobHandler;
use Example\Jobs\UserWelcomeJobOutcome;
use MySqlWelcomeRecipe\RecordMySqlWelcomeDelivery;
use MySqlWelcomeRecipe\WelcomeLeaseLost;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;

final class FailingWelcomeHandler implements UserWelcomeJobHandler
{
    public int $calls = 0;

    public function handle(Connection $connection, UserWelcomeJobEnvelope $job, int $now): void
    {
        ++$this->calls;
        (new RecordMySqlWelcomeDelivery())->handle($connection, $job, $now);
        throw new RuntimeException('SyntheticPrivateFailureMarker');
    }
}

final readonly class ExpireAtCompletionHandler implements UserWelcomeJobHandler
{
    public function handle(Connection $connection, UserWelcomeJobEnvelope $job, int $now): void
    {
        (new RecordMySqlWelcomeDelivery())->handle($connection, $job, $now);
        $connection->executeStatement('UPDATE application_jobs SET lease_expires_at = UNIX_TIMESTAMP() - 1 WHERE job_id = :id', ['id' => $job->jobId]);
    }
}

/** @return list<non-empty-string> */
function verifyJobsDelivery(): array
{
    $proof = new JobProof();
    $proof->clear();
    try {
        $job = $proof->publish('success@example.invalid');
        $budget = new QueryBudget(32);
        jobProof($proof->consumer(budget: $budget)->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::Completed, 'valid_delivery_failed');
        jobProof($proof->counts()['deliveries'] === 1 && $proof->row($job->jobId)['status'] === 'succeeded', 'effect_completion_not_atomic');
        jobProof($budget->used() <= 20, 'worker_statement_bound_exceeded');
        $duplicate = $proof->duplicate($job);
        jobProof($proof->consumer()->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::Completed, 'duplicate_not_completed');
        jobProof($proof->counts()['deliveries'] === 1 && $proof->row($duplicate)['status'] === 'succeeded', 'duplicate_created_effect');

        $proof->clear();
        $retry = $proof->publish('retry@example.invalid');
        $handler = new FailingWelcomeHandler();
        jobProof($proof->consumer($handler)->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::RetryScheduled, 'first_failure_not_retried');
        $first = $proof->row($retry->jobId);
        jobProof(is_int($first['available_at']) && is_int($first['updated_at']) && $first['available_at'] - $first['updated_at'] === 5, 'first_backoff_changed');
        jobProof($proof->counts()['deliveries'] === 0, 'failed_handler_effect_survived');
        jobProof($proof->consumer()->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::Idle, 'retry_claimed_early');
        $proof->makeDue($retry->jobId);
        jobProof($proof->consumer($handler)->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::RetryScheduled, 'second_failure_not_retried');
        $second = $proof->row($retry->jobId);
        jobProof(is_int($second['available_at']) && is_int($second['updated_at']) && $second['available_at'] - $second['updated_at'] === 30, 'second_backoff_changed');
        $proof->makeDue($retry->jobId);
        jobProof($proof->consumer($handler)->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::DeadLettered, 'third_failure_not_terminal');
        jobProof($handler->calls === 3 && $proof->row($retry->jobId)['attempts_started'] === 3, 'attempt_bound_changed');
        jobProof($proof->consumer()->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::Idle, 'terminal_job_was_retried');

        provePoisonWelcomeJobs($proof);
        proveStaleWelcomeLeases($proof);
        proveWelcomeAuthorityAndRollback($proof);
        proveWelcomeQueueScale($proof);
        return [];
    } finally {
        $proof->clear();
    }
}

function proveWelcomeQueueScale(JobProof $proof): void
{
    $proof->clear();
    $small = measureOneWelcomeDelivery($proof, 'small-queue@example.invalid');
    $proof->clear();
    // One fixture statement, 500 future-due rows; no per-row database loop.
    $proof->admin->executeStatement(
        <<<'SQL'
            INSERT INTO application_jobs (job_id, envelope_json, status, attempts_started, available_at, created_at, updated_at)
            WITH RECURSIVE sequence (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM sequence WHERE n < 500)
            SELECT LPAD(LOWER(HEX(n)), 32, '0'), '{}', 'available', 0, UNIX_TIMESTAMP() + 86400, UNIX_TIMESTAMP(), UNIX_TIMESTAMP() FROM sequence
            SQL,
    );
    $large = measureOneWelcomeDelivery($proof, 'large-queue@example.invalid');
    jobProof($small === $large && $large <= 20, 'queue_size_changed_statement_count');
    $plan = $proof->admin->selectOneRow('EXPLAIN SELECT job_id, attempts_started, status FROM application_jobs WHERE next_action_at <= UNIX_TIMESTAMP() ORDER BY next_action_at, job_id LIMIT 1 FOR UPDATE SKIP LOCKED');
    jobProof(($plan['key'] ?? null) === 'due_job', 'due_index_not_used');
}

function measureOneWelcomeDelivery(JobProof $proof, string $email): int
{
    $proof->publish($email);
    $budget = new QueryBudget(32);
    jobProof($proof->consumer(budget: $budget)->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::Completed, 'scale_delivery_failed');
    return $budget->used();
}

function provePoisonWelcomeJobs(JobProof $proof): void
{
    $valid = UserWelcomeJobEnvelope::forEmail('poison@example.invalid')->toJson();
    foreach (['{broken', '{}', str_replace('"version":1', '"version":2', $valid), str_replace('user.welcome', 'unknown.work', $valid), str_replace('"email":', '"unknown":', $valid)] as $json) {
        proveOnePoisonWelcomeJob($proof, $json);
    }
    $proof->clear();
    $job = $proof->publish('bound@example.invalid');
    try {
        overwriteWelcomeEnvelope($proof, $job->jobId, str_repeat('a', 2049));
        throw new RuntimeException('oversized_job_was_stored');
    } catch (PDOException $error) {
        jobProof($error->getCode() === 'HY000' && ($error->errorInfo[1] ?? null) === 3819, 'unexpected_size_constraint_error');
    }
}

function proveOnePoisonWelcomeJob(JobProof $proof, string $json): void
{
    $proof->clear();
    $job = $proof->publish('poison@example.invalid');
    overwriteWelcomeEnvelope($proof, $job->jobId, $json);
    $handler = new FailingWelcomeHandler();
    jobProof($proof->consumer($handler)->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::DeadLettered, 'poison_not_terminal');
    jobProof($handler->calls === 0 && $proof->counts()['deliveries'] === 0, 'poison_reached_effect');
    jobProof($proof->row($job->jobId)['last_failure_code'] === 'invalid_envelope', 'poison_diagnostic_changed');
}

function overwriteWelcomeEnvelope(JobProof $proof, string $id, string $json): void
{
    $proof->admin->executeStatement('UPDATE application_jobs SET envelope_json = :json WHERE job_id = :id', ['json' => $json, 'id' => $id]);
}

function proveStaleWelcomeLeases(JobProof $proof): void
{
    $proof->clear();
    $job = $proof->publish('stale@example.invalid');
    $old = $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    jobProof($old !== null && $old->attemptsStarted === 1, 'first_claim_failed');
    if ($old === null) {
        throw new RuntimeException('first_claim_missing');
    }
    $proof->expire($job->jobId);
    $fresh = $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    jobProof($fresh !== null && $fresh->leaseToken !== $old->leaseToken && $fresh->attemptsStarted === 2, 'reclaim_did_not_fence');
    try {
        $proof->consumer()->finishClaim($old);
        throw new LogicException('stale_claim_finished');
    } catch (WelcomeLeaseLost) {
        jobProof($proof->counts()['deliveries'] === 0, 'stale_claim_wrote_effect');
    }
    if ($fresh === null) {
        throw new RuntimeException('fresh_claim_missing');
    }
    jobProof($proof->consumer()->finishClaim($fresh) === UserWelcomeJobOutcome::Completed, 'fresh_claim_not_completed');

    $proof->clear();
    $final = $proof->publish('final@example.invalid');
    $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    $proof->expire($final->jobId);
    $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    $proof->expire($final->jobId);
    $third = $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    jobProof($third !== null && $third->attemptsStarted === 3, 'final_claim_not_counted');
    $proof->expire($final->jobId);
    jobProof($proof->consumer()->runOne(bin2hex(random_bytes(16))) === UserWelcomeJobOutcome::DeadLettered, 'expired_final_claim_stranded');
    jobProof($proof->row($final->jobId)['last_failure_code'] === 'lease_expired_after_final_attempt', 'final_crash_diagnostic_changed');
}

function proveWelcomeAuthorityAndRollback(JobProof $proof): void
{
    $proof->clear();
    $job = $proof->publish('affected-rows@example.invalid');
    try {
        $proof->consumer(new ExpireAtCompletionHandler())->runOne(bin2hex(random_bytes(16)));
        throw new LogicException('zero_row_completion_accepted');
    } catch (WelcomeLeaseLost) {
        jobProof($proof->counts()['deliveries'] === 0 && $proof->row($job->jobId)['status'] === 'leased', 'zero_row_completion_did_not_roll_back');
    }
    $proof->expire($job->jobId);
    $expired = $proof->lease($job->jobId);
    $handler = new FailingWelcomeHandler();
    try {
        $proof->consumer($handler)->finishClaim($expired);
        throw new LogicException('expired_unreclaimed_lease_accepted');
    } catch (WelcomeLeaseLost) {
        jobProof($handler->calls === 0, 'expired_lease_reached_handler');
    }
    $proof->clear();
    $proof->duplicate(UserWelcomeJobEnvelope::forEmail('uncommitted-obligation@example.invalid'));
    $proof->processes('retry_scheduled');
    jobProof($proof->counts()['deliveries'] === 0, 'worker_credentials_authorized_forged_obligation');
    $proof->clear();
    $mismatch = $proof->publish('restored@example.invalid');
    $proof->admin->executeStatement('INSERT INTO welcome_deliveries (idempotency_key, recipient_email, created_at) VALUES (:key, :email, UNIX_TIMESTAMP())', ['key' => $mismatch->idempotencyKey, 'email' => 'different@example.invalid']);
    $proof->processes('retry_scheduled');
    jobProof($proof->row($mismatch->jobId)['status'] === 'available', 'mismatched_receipt_accepted');
}
