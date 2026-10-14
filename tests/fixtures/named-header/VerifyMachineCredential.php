<?php

declare(strict_types=1);

namespace NamedHeaderRecipe;

use Example\Accounts\AuthenticatedPrincipal;

interface VerifyMachineCredential
{
    /**
     * @throws MachineCredentialRejected Definitive invalidity under the adopted lifecycle.
     * @throws MachineVerificationUnavailable A trustworthy current decision is unavailable.
     */
    public function verify(MachineCredential $credential): AuthenticatedPrincipal;
}
