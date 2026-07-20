<?php

declare(strict_types=1);

namespace Example\Documents;

final readonly class DenyAllDocumentTenantResolution implements ResolveDocumentTenant
{
    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant {
        throw new CrossTenant();
    }
}
