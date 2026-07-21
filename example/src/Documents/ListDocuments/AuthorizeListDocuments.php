<?php

declare(strict_types=1);

namespace Example\Documents\ListDocuments;

use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\ResolvedTenant;

interface AuthorizeListDocuments
{
    public function authorizeList(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void;
}
