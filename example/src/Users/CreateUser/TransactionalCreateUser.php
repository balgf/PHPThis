<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use PHPThis\Database\Connection;
use RuntimeException;

/**
 * Narrow SQL owner for the create-user operation's two-statement transaction.
 */
final readonly class TransactionalCreateUser implements CreateUserOperation
{
    public function __construct(private Connection $connection)
    {
    }

    public function execute(CreateUserCommand $command): void
    {
        $this->connection->beginTransaction();

        try {
            $insertedUsers = $this->connection->executeStatement(
                'INSERT INTO users (name, email) VALUES (:name, :email)',
                ['name' => $command->name, 'email' => $command->email],
            );

            if ($insertedUsers !== 1) {
                throw new RuntimeException('Create user must insert exactly one user row.');
            }

            $insertedEvents = $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO user_events (user_id, event_type)
                    SELECT users.id, :event_type
                    FROM users
                    WHERE users.email = :email
                    SQL,
                ['event_type' => 'user.created', 'email' => $command->email],
            );

            if ($insertedEvents !== 1) {
                throw new RuntimeException('Create user must insert exactly one event row.');
            }

            $this->connection->commit();
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }
}
