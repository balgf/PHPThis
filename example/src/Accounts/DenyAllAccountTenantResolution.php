<?php

declare(strict_types=1);

namespace Example\Accounts;

final readonly class DenyAllAccountTenantResolution implements ResolveAccountTenant
{
    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant {
        throw new CrossTenant();
    }
}
