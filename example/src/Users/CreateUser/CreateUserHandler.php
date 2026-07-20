<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;

final readonly class CreateUserHandler implements RequestHandler
{
    public function __construct(private CreateUserOperation $createUser)
    {
    }

    public function handle(Request $request): Response
    {
        $contentType = $request->headers['content-type'] ?? null;

        if ($contentType === null) {
            throw new UnsupportedMediaType('Create user requires a Content-Type header.');
        }

        $parameterPosition = strpos($contentType, ';');
        $mediaType = $parameterPosition === false
            ? $contentType
            : substr($contentType, 0, $parameterPosition);

        if (strtolower(trim($mediaType)) !== 'application/json') {
            throw new UnsupportedMediaType('Create user requires application/json.');
        }

        $command = CreateUserCommand::fromJson($request->body);
        $responseBody = json_encode(
            ['user' => ['name' => $command->name, 'email' => $command->email]],
            JSON_THROW_ON_ERROR,
        );

        $this->createUser->execute($command);

        return new Response(
            status: 201,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            body: $responseBody . "\n",
        );
    }
}
