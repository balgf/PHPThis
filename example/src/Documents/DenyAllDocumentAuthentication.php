<?php

declare(strict_types=1);

namespace Example\Documents;

use PHPThis\Http\Request;

final readonly class DenyAllDocumentAuthentication implements AuthenticateDocumentRequest
{
    public function authenticate(Request $request): AuthenticatedPrincipal
    {
        throw new Unauthenticated();
    }
}
