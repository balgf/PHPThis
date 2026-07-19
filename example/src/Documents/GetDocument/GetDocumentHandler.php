<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final class GetDocumentHandler implements RequestHandler
{
    public function handle(Request $request): Response
    {
        $accountId = AccountId::fromPositiveInteger(
            $request->pathParameters->positiveInteger('account_id'),
        );
        $documentKey = DocumentKey::fromToken(
            $request->pathParameters->token('document_key'),
        );
        $body = json_encode(
            [
                'document' => [
                    'account_id' => $accountId->value,
                    'key' => $documentKey->value,
                ],
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
