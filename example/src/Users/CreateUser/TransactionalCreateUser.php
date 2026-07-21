<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\Forbidden;
use Example\Accounts\ResolvedTenant;
use Example\Jobs\UserWelcomeJobEnvelope;
use PHPThis\Database\Connection;
use RuntimeException;

/**
 * Narrow SQL owner for the account-scoped create-user operation's four-statement transaction.
 */
final readonly class TransactionalCreateUser implements CreateUserOperation
{
    public function __construct(private Connection $connection)
    {
    }

    public function execute(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        CreateUserCommand $command,
    ): void {
        $job = UserWelcomeJobEnvelope::forEmail($command->email);
        $publishedAt = time();
        $this->connection->beginTransaction();

        try {
            $insertedUsers = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO users (name, email)
                    SELECT :name, :email
                    WHERE :requested_account_id = :resolved_tenant_account_id
                      AND EXISTS (
                          SELECT 1
                          FROM account_memberships
                          WHERE account_memberships.principal_id = :actor_principal_id
                            AND account_memberships.account_id = :actor_membership_account_id
                      )
                    SQL,
                [
                    'name' => $command->name,
                    'email' => $command->email,
                    'requested_account_id' => $accountId->value,
                    'resolved_tenant_account_id' => $tenant->accountId->value,
                    'actor_principal_id' => $principal->id,
                    'actor_membership_account_id' => $tenant->accountId->value,
                ],
            );

            if ($insertedUsers !== 1) {
                throw new Forbidden('Create user requires current account membership.');
            }

            $insertedAccountUsers = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO account_users (user_id, account_id)
                    SELECT users.id, :new_account_user_account_id
                    FROM users
                    WHERE users.email = :account_user_email
                      AND :account_user_requested_account_id = :account_user_resolved_account_id
                      AND EXISTS (
                          SELECT 1
                          FROM account_memberships AS actor_memberships
                          WHERE actor_memberships.principal_id = :account_user_actor_principal_id
                            AND actor_memberships.account_id = :account_user_actor_account_id
                      )
                    SQL,
                [
                    'new_account_user_account_id' => $tenant->accountId->value,
                    'account_user_email' => $command->email,
                    'account_user_requested_account_id' => $accountId->value,
                    'account_user_resolved_account_id' => $tenant->accountId->value,
                    'account_user_actor_principal_id' => $principal->id,
                    'account_user_actor_account_id' => $tenant->accountId->value,
                ],
            );

            if ($insertedAccountUsers !== 1) {
                throw new Forbidden('Create user must attach exactly one current account relation.');
            }

            $insertedEvents = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO user_events (user_id, event_type)
                    SELECT users.id, :event_type
                    FROM users
                    INNER JOIN account_users
                        ON account_users.user_id = users.id
                    WHERE users.email = :event_email
                      AND account_users.account_id = :event_account_user_account_id
                      AND :event_requested_account_id = :event_resolved_account_id
                    SQL,
                [
                    'event_type' => 'user.created',
                    'event_email' => $command->email,
                    'event_account_user_account_id' => $tenant->accountId->value,
                    'event_requested_account_id' => $accountId->value,
                    'event_resolved_account_id' => $tenant->accountId->value,
                ],
            );

            if ($insertedEvents !== 1) {
                throw new RuntimeException('Create user must insert exactly one account-scoped event row.');
            }

            $insertedJobs = $this->connection->executeStatement(
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
                    SELECT
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
                    FROM users
                    INNER JOIN account_users
                        ON account_users.user_id = users.id
                    WHERE users.email = :job_email
                      AND account_users.account_id = :job_account_user_account_id
                      AND :job_requested_account_id = :job_resolved_account_id
                    SQL,
                [
                    'job_id' => $job->jobId,
                    'envelope_json' => $job->toJson(),
                    'available_at' => $publishedAt,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                    'job_email' => $command->email,
                    'job_account_user_account_id' => $tenant->accountId->value,
                    'job_requested_account_id' => $accountId->value,
                    'job_resolved_account_id' => $tenant->accountId->value,
                ],
            );

            if ($insertedJobs !== 1) {
                throw new RuntimeException('Create user must publish exactly one welcome job.');
            }

            $this->connection->commit();
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }
}
