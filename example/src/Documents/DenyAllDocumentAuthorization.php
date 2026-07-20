<?php

declare(strict_types=1);

namespace Example\Documents;

use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\ListDocuments\AuthorizeListDocuments;

final readonly class DenyAllDocumentAuthorization implements AuthorizeGetDocument, AuthorizeListDocuments
{
    public function authorize(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        DocumentKey $documentKey,
    ): void {
        throw new Forbidden();
    }

    public function authorizeList(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void {
        throw new Forbidden();
    }
}
