<?php

declare(strict_types=1);

use MySqlWelcomeRecipe\MySqlWelcomeSession;

/** @return list<non-empty-string> */
function verifyJobsOperations(): array
{
    $proof = new JobProof();
    $proof->clear();
    try {
        proveWelcomePermissions($proof);
        proveConcurrentWelcomeWorkers($proof);
        proveWelcomeProcessRecovery($proof);
        proveWelcomeSupervisor($proof);
        $proof->clear();
        $committed = $proof->publish('database-restart@example.invalid');
        $proof->processes('database-crash');
        // Recovery starts a fresh invocation; no operation reconnects/retries.
        $proof = new JobProof();
        jobProof($proof->counts() === ['requests' => 1, 'jobs' => 1, 'deliveries' => 0], 'database_crash_lost_committed_or_kept_uncommitted_pair');
        $proof->processes('completed');
        jobProof($proof->row($committed->jobId)['status'] === 'succeeded', 'database_recovery_not_deliverable');
        return [];
    } finally {
        (new JobProof())->clear();
    }
}

function proveWelcomePermissions(JobProof $proof): void
{
    $worker = $proof->workerConnection();
    try {
        $worker->executeStatement('DELETE FROM application_jobs');
        throw new LogicException('worker_delete_allowed');
    } catch (PDOException $error) {
        jobProof(($error->errorInfo[1] ?? null) === 1142, 'unexpected_delete_rejection');
    }
    try {
        $worker->executeStatement('INSERT INTO welcome_requests (idempotency_key, recipient_email, job_id, requested_at) VALUES (:key, :email, :id, 0)', ['key' => str_repeat('a', 64), 'email' => 'denied@example.invalid', 'id' => str_repeat('b', 32)]);
        throw new LogicException('worker_publication_allowed');
    } catch (PDOException $error) {
        jobProof(($error->errorInfo[1] ?? null) === 1142, 'unexpected_publication_rejection');
    }
    try {
        $worker->executeStatement('ALTER TABLE application_jobs ADD forbidden_column INT');
        throw new LogicException('worker_schema_change_allowed');
    } catch (PDOException $error) {
        jobProof(($error->errorInfo[1] ?? null) === 1142, 'unexpected_schema_rejection');
    }
    try {
        $proof->producerConnection()->executeStatement('DELETE FROM welcome_deliveries');
        throw new LogicException('producer_effect_delete_allowed');
    } catch (PDOException $error) {
        jobProof(($error->errorInfo[1] ?? null) === 1142, 'unexpected_producer_rejection');
    }
    $proof->admin->executeStatement('SET GLOBAL innodb_flush_log_at_trx_commit = 0');
    try {
        try {
            MySqlWelcomeSession::configure($proof->workerConnection());
            throw new LogicException('durability_drift_accepted');
        } catch (RuntimeException) {
            jobProof($proof->counts()['jobs'] === 0, 'drift_check_wrote_state');
        }
    } finally {
        $proof->admin->executeStatement('SET GLOBAL innodb_flush_log_at_trx_commit = 1');
    }
    $proof->processes('console-rejection');
}

function proveConcurrentWelcomeWorkers(JobProof $proof): void
{
    $proof->clear();
    $first = $proof->publish('concurrent-first@example.invalid');
    $second = $proof->publish('concurrent-second@example.invalid');
    $proof->processes('parallel');
    jobProof($proof->counts()['deliveries'] === 2 && $proof->row($first->jobId)['attempts_started'] === 1 && $proof->row($second->jobId)['attempts_started'] === 1, 'concurrent_claim_not_exclusive');
    $proof->clear();
    $original = $proof->publish('parallel-duplicate@example.invalid');
    $duplicate = $proof->duplicate($original);
    $proof->processes('parallel');
    jobProof($proof->counts()['deliveries'] === 1 && $proof->row($original->jobId)['status'] === 'succeeded' && $proof->row($duplicate)['status'] === 'succeeded', 'concurrent_duplicate_effect');
    $proof->clear();
    $locked = $proof->publish('locked@example.invalid');
    $other = $proof->publish('skip-locked@example.invalid');
    $proof->admin->beginTransaction();
    try {
        $proof->admin->selectOneRow('SELECT job_id FROM application_jobs WHERE job_id = :id FOR UPDATE', ['id' => $locked->jobId]);
        $proof->processes('completed');
    } finally {
        $proof->admin->rollBack();
    }
    jobProof($proof->row($locked->jobId)['attempts_started'] === 0 && $proof->row($other->jobId)['status'] === 'succeeded', 'skip_locked_blocked_other_work');
    $proof->clear();
    $stale = $proof->publish('process-stale@example.invalid');
    $proof->processes('stale');
    jobProof($proof->counts()['deliveries'] === 1 && $proof->row($stale->jobId)['attempts_started'] === 2, 'stale_process_effect_or_attempt_changed');
}

function proveWelcomeProcessRecovery(JobProof $proof): void
{
    $proof->clear();
    $claimed = $proof->publish('claim-crash@example.invalid');
    $proof->processes('claim-crash');
    $proof->processes('idle');
    jobProof($proof->row($claimed->jobId)['status'] === 'leased' && $proof->counts()['deliveries'] === 0, 'claim_crash_state_changed');
    $proof->expire($claimed->jobId);
    $proof->processes('completed');
    jobProof($proof->row($claimed->jobId)['attempts_started'] === 2, 'claim_crash_not_reclaimed');
    $proof->clear();
    $effect = $proof->publish('effect-crash@example.invalid');
    $proof->processes('effect-crash');
    jobProof($proof->counts()['deliveries'] === 0, 'crashed_effect_committed');
    $proof->expire($effect->jobId);
    $proof->processes('completed');
    jobProof($proof->counts()['deliveries'] === 1, 'effect_crash_recovery_failed');
    $proof->clear();
    $final = $proof->publish('final-process-crash@example.invalid');
    $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    $proof->expire($final->jobId);
    $proof->consumer()->claimOne(bin2hex(random_bytes(16)));
    $proof->expire($final->jobId);
    $proof->processes('claim-crash');
    jobProof($proof->row($final->jobId)['attempts_started'] === 3, 'final_process_did_not_consume_attempt');
    $proof->processes('idle');
    // Exercise the real service-clock deadline once; other cases use an explicit
    // fixture-only expiry edit to avoid repeating this wait.
    usleep(31_000_000);
    $proof->processes('dead_lettered');
    jobProof($proof->row($final->jobId)['last_failure_code'] === 'lease_expired_after_final_attempt' && $proof->counts()['deliveries'] === 0, 'final_process_crash_stranded');
}

function proveWelcomeSupervisor(JobProof $proof): void
{
    $proof->clear();
    $proof->publish('supervisor-one@example.invalid');
    $proof->publish('supervisor-two@example.invalid');
    $proof->publish('supervisor-three@example.invalid');
    $proof->publish('supervisor-four@example.invalid');
    $proof->processes('supervise');
    jobProof($proof->counts() === ['requests' => 4, 'jobs' => 4, 'deliveries' => 3], 'finite_supervisor_did_not_stop');
    $remaining = $proof->admin->selectOneRow('SELECT COUNT(*) AS remaining FROM application_jobs WHERE status = \'available\' AND attempts_started = 0');
    jobProof(($remaining['remaining'] ?? null) === 1, 'shutdown_claimed_extra_delivery');
    $proof->clear();
    $proof->processes('idle-pacing');
    $deadline = $proof->publish('deadline@example.invalid');
    $proof->processes('deadline');
    jobProof($proof->counts()['deliveries'] === 0, 'terminated_transaction_committed');
    $proof->expire($deadline->jobId);
    $proof->processes('completed');
}
