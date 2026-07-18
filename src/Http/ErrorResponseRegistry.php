<?php

declare(strict_types=1);

namespace PHPThis\Http;

use Throwable;

final readonly class ErrorResponseRegistry
{
    /** @param array<class-string<Throwable>, Response> $responses */
    public function __construct(private array $responses)
    {
    }

    public function responseFor(Throwable $failure): ?Response
    {
        return $this->responses[$failure::class] ?? null;
    }
}
