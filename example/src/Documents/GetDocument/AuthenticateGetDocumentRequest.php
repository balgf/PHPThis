<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use PHPThis\Http\Request;

interface AuthenticateGetDocumentRequest
{
    public function authenticate(Request $request): AuthenticatedPrincipal;
}
