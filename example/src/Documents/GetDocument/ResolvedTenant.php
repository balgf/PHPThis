<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

final readonly class ResolvedTenant
{
    private function __construct(public AccountId $accountId)
    {
    }

    public static function forAccount(AccountId $accountId): self
    {
        return new self($accountId);
    }
}
