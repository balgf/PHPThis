<?php

declare(strict_types=1);

namespace Example\Documents;

interface ResolveDocumentTenant
{
    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant;
}
