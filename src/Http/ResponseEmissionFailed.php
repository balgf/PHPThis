<?php

declare(strict_types=1);

namespace PHPThis\Http;

use RuntimeException;

final class ResponseEmissionFailed extends RuntimeException
{
    public function __construct(public readonly bool $responseStarted)
    {
        parent::__construct('Response emission failed.');
    }
}
