<?php

declare(strict_types=1);

namespace Example\Documents\ListDocuments;

use Example\Documents\AuthenticatedPrincipal;
use Example\Documents\ResolvedTenant;

interface AuthorizeListDocuments
{
    public function authorizeList(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void;
}
