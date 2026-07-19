<?php

declare(strict_types=1);

namespace Example\Users\ListUsers;

use LogicException;
use PHPThis\Database\Connection;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final class ListUsersHandler implements RequestHandler
{
    private const int USER_PAGE_SIZE = 50;
    private const int USER_FETCH_LIMIT = self::USER_PAGE_SIZE + 1;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function handle(Request $request): Response
    {
        $pageRequest = ListUsersPageRequest::fromQuery($request->query);
        $rows = $this->connection->selectAllRows(
            <<<'SQL'
                SELECT
                    selected_users.id,
                    selected_users.name,
                    COUNT(user_events.id) AS event_count
                FROM (
                    SELECT users.id, users.name
                    FROM users
                    WHERE users.id > :after_user_id
                    ORDER BY users.id
                    LIMIT :fetch_limit
                ) AS selected_users
                LEFT JOIN user_events ON user_events.user_id = selected_users.id
                GROUP BY selected_users.id, selected_users.name
                ORDER BY selected_users.id
                SQL,
            [
                'after_user_id' => $pageRequest->afterUserId ?? 0,
                'fetch_limit' => self::USER_FETCH_LIMIT,
            ],
        );

        $users = [];
        $lastUserId = null;
        $hasNextPage = false;

        foreach ($rows as $index => $row) {
            $user = UserActivitySummary::fromDatabaseRow($row);

            if ($index >= self::USER_PAGE_SIZE) {
                $hasNextPage = true;
                continue;
            }

            $lastUserId = $user->id;
            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'event_count' => $user->eventCount,
            ];
        }

        $nextAfterUserId = null;

        if ($hasNextPage) {
            if ($lastUserId === null) {
                throw new LogicException('A continued user page must contain a last returned identifier.');
            }

            $nextAfterUserId = (string) $lastUserId;
        }

        $body = json_encode(
            [
                'users' => $users,
                'next_after_user_id' => $nextAfterUserId,
            ],
            JSON_THROW_ON_ERROR,
        );

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            body: $body . "\n",
        );
    }
}
