<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

interface RetrieveAuthorizedDocument
{
    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails;
}
