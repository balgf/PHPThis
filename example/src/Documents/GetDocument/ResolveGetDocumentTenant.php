<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

interface ResolveGetDocumentTenant
{
    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant;
}
