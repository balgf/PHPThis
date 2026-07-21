<?php

declare(strict_types=1);

namespace Example\Accounts;

interface ResolveAccountTenant
{
    public function resolve(
        AuthenticatedPrincipal $principal,
        AccountId $accountId,
    ): ResolvedTenant;
}
