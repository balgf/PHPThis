<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use PHPThis\Http\Request;

final readonly class DenyAllGetDocumentAuthentication implements AuthenticateGetDocumentRequest
{
    public function authenticate(Request $request): AuthenticatedPrincipal
    {
        throw new Unauthenticated();
    }
}
