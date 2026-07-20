<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use Example\Documents\AccountId;
use Example\Documents\AuthenticatedPrincipal;
use Example\Documents\DocumentKey;
use Example\Documents\ResolvedTenant;

interface RetrieveAuthorizedDocument
{
    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails;
}
