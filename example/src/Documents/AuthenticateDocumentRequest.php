<?php

declare(strict_types=1);

namespace Example\Documents;

use PHPThis\Http\Request;

interface AuthenticateDocumentRequest
{
    public function authenticate(Request $request): AuthenticatedPrincipal;
}
