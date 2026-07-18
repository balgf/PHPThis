<?php

declare(strict_types=1);

namespace Example;

use PHPThis\Database\Connection;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use RuntimeException;

final class CreateUserHandler implements RequestHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function handle(Request $request): Response
    {
        $command = CreateUserCommand::fromJson($request->body);
        $responseBody = json_encode(
            ['user' => ['name' => $command->name, 'email' => $command->email]],
            JSON_THROW_ON_ERROR,
        );

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

        return new Response(
            status: 201,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            body: $responseBody . "\n",
        );
    }
}
