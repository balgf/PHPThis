<?php

declare(strict_types=1);

namespace Example\Users\GetUser;

use PHPThis\Database\Connection;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final class GetUserHandler implements RequestHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function handle(Request $request): Response
    {
        $userId = UserId::fromPositiveInteger(
            $request->pathParameters->positiveInteger('user_id'),
        );
        $row = $this->connection->selectOneRow(
            'SELECT users.id, users.name FROM users WHERE users.id = :user_id',
            ['user_id' => $userId->value],
        );

        if ($row === null) {
            return new Response(
                status: 404,
                headers: [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'no-store',
                ],
                body: "{\"error\":{\"code\":\"user_not_found\",\"message\":\"User was not found.\"}}\n",
            );
        }

        $user = UserDetails::fromDatabaseRow($row);
        $body = json_encode(
            ['user' => ['id' => $user->id->value, 'name' => $user->name]],
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
