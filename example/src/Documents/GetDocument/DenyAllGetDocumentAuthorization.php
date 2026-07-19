<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

final readonly class DenyAllGetDocumentAuthorization implements AuthorizeGetDocument
{
    public function authorize(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        DocumentKey $documentKey,
    ): void {
        throw new Forbidden();
    }
}
