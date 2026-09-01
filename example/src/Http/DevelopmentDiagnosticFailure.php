<?php

declare(strict_types=1);

namespace Example\Http;

use RuntimeException;

final class DevelopmentDiagnosticFailure extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Development diagnostic failure.');
    }
}
