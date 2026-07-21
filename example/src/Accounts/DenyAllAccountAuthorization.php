<?php

declare(strict_types=1);

namespace Example\Accounts;

use Example\Documents\DocumentKey;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\ListDocuments\AuthorizeListDocuments;
use Example\Users\CreateUser\AuthorizeCreateUser;

final readonly class DenyAllAccountAuthorization implements
    AuthorizeCreateUser,
    AuthorizeGetDocument,
    AuthorizeListDocuments
{
    public function authorizeCreate(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void {
        throw new Forbidden();
    }

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
