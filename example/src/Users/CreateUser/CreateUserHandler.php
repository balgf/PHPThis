<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticateAccountRequest;
use Example\Accounts\ResolveAccountTenant;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;

final readonly class CreateUserHandler implements RequestHandler
{
    public function __construct(
        private AuthenticateAccountRequest $authenticate,
        private ResolveAccountTenant $resolveTenant,
        private AuthorizeCreateUser $authorize,
        private CreateUserOperation $createUser,
    ) {
    }

    public function handle(Request $request): Response
    {
        $accountId = AccountId::fromPositiveInteger(
            $request->pathParameters->positiveInteger('account_id'),
        );
        $principal = $this->authenticate->authenticate($request);
        $tenant = $this->resolveTenant->resolve($principal, $accountId);
        $this->authorize->authorizeCreate($principal, $tenant);
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
            [
                'user' => [
                    'account_id' => $accountId->value,
                    'name' => $command->name,
                    'email' => $command->email,
                ],
            ],
            JSON_THROW_ON_ERROR,
        );

        $this->createUser->execute($principal, $tenant, $accountId, $command);

        return new Response(
            status: 201,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
            ],
            body: $responseBody . "\n",
        );
    }
}
