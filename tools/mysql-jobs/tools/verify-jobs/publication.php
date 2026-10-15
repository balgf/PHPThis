<?php

declare(strict_types=1);

use Example\Jobs\UserWelcomeJobEnvelope;
use MySqlWelcomeRecipe\MySqlWelcomeClock;
use MySqlWelcomeRecipe\PublishWelcomeRequest;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;

require_once __DIR__ . '/Fixture.php';

/** @return list<non-empty-string> */
function verifyJobsPublication(): array
{
    $proof = new JobProof();
    $proof->clear();
    try {
        $job = $proof->publish('first@example.invalid');
        jobProof($proof->counts() === ['requests' => 1, 'jobs' => 1, 'deliveries' => 0], 'publication_pair_missing');
        jobProof($proof->row($job->jobId)['status'] === 'available', 'publication_not_available');

        $proof->clear();
        // Five session statements, the clock and business insert fit; publication
        // exceeds the budget and must roll back the earlier independent write.
        $connection = $proof->producerConnection(new QueryBudget(7));
        try {
            (new PublishWelcomeRequest($connection, new MySqlWelcomeClock($connection)))->publish(UserWelcomeJobEnvelope::forEmail('rollback@example.invalid'));
            return ['publication_budget_was_not_enforced'];
        } catch (QueryBudgetExceeded) {
            jobProof($proof->counts() === ['requests' => 0, 'jobs' => 0, 'deliveries' => 0], 'publication_rollback_left_state');
        }
        $delayed = $proof->publish('delayed@example.invalid', 2);
        jobProof($proof->consumer()->runOne(bin2hex(random_bytes(16)))->value === 'idle', 'delayed_job_claimed_early');
        $row = $proof->row($delayed->jobId);
        jobProof(is_int($row['available_at']) && is_int($row['updated_at']) && $row['available_at'] - $row['updated_at'] === 2, 'delay_not_exact');
        usleep(2_100_000);
        $proof->processes('completed');
        jobProof($proof->row($delayed->jobId)['status'] === 'succeeded', 'delayed_delivery_missing');
        $proof->clear();
        $maximum = $proof->publish('maximum-delay@example.invalid', 86_400);
        $maximumRow = $proof->row($maximum->jobId);
        jobProof(is_int($maximumRow['available_at']) && is_int($maximumRow['updated_at']) && $maximumRow['available_at'] - $maximumRow['updated_at'] === 86_400, 'maximum_delay_changed');
        proveInvalidWelcomeDelay($proof, -1);
        proveInvalidWelcomeDelay($proof, 86_401);
        $proof->clear();
        $proof->processes('publish-loss');
        jobProof($proof->counts() === ['requests' => 1, 'jobs' => 1, 'deliveries' => 0], 'lost_ack_pair_missing');
        $proof->processes('completed');
        jobProof($proof->counts()['deliveries'] === 1, 'lost_ack_not_recoverable');
        return [];
    } finally {
        $proof->clear();
    }
}

function proveInvalidWelcomeDelay(JobProof $proof, int $delay): void
{
    $budget = new QueryBudget(32);
    $connection = $proof->producerConnection($budget);
    $before = $budget->used();
    try {
        (new PublishWelcomeRequest($connection, new MySqlWelcomeClock($connection)))->publish(UserWelcomeJobEnvelope::forEmail('invalid-delay@example.invalid'), $delay);
        throw new LogicException('invalid_delay_accepted');
    } catch (InvalidArgumentException) {
        jobProof($budget->used() === $before, 'invalid_delay_reached_database');
    }
}
