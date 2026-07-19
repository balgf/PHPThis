<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

final readonly class DenyAllGetDocumentTenantResolution implements ResolveGetDocumentTenant
{
    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant {
        throw new CrossTenant();
    }
}
