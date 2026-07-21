<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use Example\Accounts\AuthenticatedPrincipal;
use Example\Documents\DocumentKey;
use Example\Accounts\ResolvedTenant;

interface AuthorizeGetDocument
{
    public function authorize(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        DocumentKey $documentKey,
    ): void;
}
