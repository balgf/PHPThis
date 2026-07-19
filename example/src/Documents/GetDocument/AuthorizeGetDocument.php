<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

interface AuthorizeGetDocument
{
    public function authorize(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        DocumentKey $documentKey,
    ): void;
}
