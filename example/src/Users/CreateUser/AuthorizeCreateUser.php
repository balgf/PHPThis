<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\ResolvedTenant;

interface AuthorizeCreateUser
{
    public function authorizeCreate(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void;
}
