<?php

declare(strict_types=1);

namespace Example\Accounts;

use PHPThis\Http\Request;

final readonly class DenyAllAccountAuthentication implements AuthenticateAccountRequest
{
    public function authenticate(Request $request): AuthenticatedPrincipal
    {
        throw new Unauthenticated();
    }
}
