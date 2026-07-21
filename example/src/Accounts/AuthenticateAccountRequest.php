<?php

declare(strict_types=1);

namespace Example\Accounts;

use PHPThis\Http\Request;

interface AuthenticateAccountRequest
{
    public function authenticate(Request $request): AuthenticatedPrincipal;
}
