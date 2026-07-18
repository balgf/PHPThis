<?php

declare(strict_types=1);

namespace Example\Users\ListUsers;

use PHPThis\Database\Connection;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final class ListUsersHandler implements RequestHandler
{
    private const int USER_LIMIT = 50;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function handle(Request $request): Response
    {
        $rows = $this->connection->selectAllRows(
            <<<'SQL'
                SELECT
                    selected_users.id,
                    selected_users.name,
                    COUNT(user_events.id) AS event_count
                FROM (
                    SELECT users.id, users.name
                    FROM users
                    ORDER BY users.id
                    LIMIT :user_limit
                ) AS selected_users
                LEFT JOIN user_events ON user_events.user_id = selected_users.id
                GROUP BY selected_users.id, selected_users.name
                ORDER BY selected_users.id
                SQL,
            ['user_limit' => self::USER_LIMIT],
        );
        $users = [];

        foreach ($rows as $row) {
            $user = UserActivitySummary::fromDatabaseRow($row);
            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'event_count' => $user->eventCount,
            ];
        }

        $body = json_encode(['users' => $users], JSON_THROW_ON_ERROR);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            body: $body . "\n",
        );
    }
}
