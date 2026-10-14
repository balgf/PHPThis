<?php

declare(strict_types=1);

namespace NamedHeaderRecipe;

use Example\Accounts\AuthenticateAccountRequest;
use Example\Accounts\AuthenticatedPrincipal;
use PHPThis\Http\Request;

final readonly class AuthenticateMachineRequest implements AuthenticateAccountRequest
{
    public function __construct(private VerifyMachineCredential $verifier)
    {
    }

    public function authenticate(Request $request): AuthenticatedPrincipal
    {
        $credential = MachineCredential::fromRequest($request);
        return $this->verifier->verify($credential);
    }
}
