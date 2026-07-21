<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticateAccountRequest;
use Example\Documents\DocumentKey;
use Example\Accounts\ResolveAccountTenant;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final readonly class GetDocumentHandler implements RequestHandler
{
    public function __construct(
        private AuthenticateAccountRequest $authenticate,
        private ResolveAccountTenant $resolveTenant,
        private AuthorizeGetDocument $authorize,
        private RetrieveAuthorizedDocument $retrieve,
    ) {
    }

    public function handle(Request $request): Response
    {
        $accountId = AccountId::fromPositiveInteger(
            $request->pathParameters->positiveInteger('account_id'),
        );
        $documentKey = DocumentKey::fromToken(
            $request->pathParameters->token('document_key'),
        );
        $principal = $this->authenticate->authenticate($request);
        $tenant = $this->resolveTenant->resolve($principal, $accountId);
        $this->authorize->authorize($principal, $tenant, $documentKey);
        $document = $this->retrieve->retrieve(
            $principal,
            $tenant,
            $accountId,
            $documentKey,
        );

        if ($document === null) {
            return new Response(
                status: 404,
                headers: [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'private, no-store',
                ],
                body: "{\"error\":{\"code\":\"document_not_found\",\"message\":\"Document was not found.\"}}\n",
            );
        }

        $body = json_encode(
            [
                'document' => [
                    'account_id' => $accountId->value,
                    'key' => $documentKey->value,
                    'title' => $document->title,
                ],
            ],
            JSON_THROW_ON_ERROR,
        );

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
            ],
            body: $body . "\n",
        );
    }
}
