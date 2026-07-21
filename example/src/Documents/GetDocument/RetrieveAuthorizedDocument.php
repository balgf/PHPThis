<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Documents\DocumentKey;
use Example\Accounts\ResolvedTenant;

interface RetrieveAuthorizedDocument
{
    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails;
}
