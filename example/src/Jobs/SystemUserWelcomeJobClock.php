<?php

declare(strict_types=1);

namespace Example\Jobs;

final readonly class SystemUserWelcomeJobClock implements UserWelcomeJobClock
{
    public function now(): int
    {
        return time();
    }
}
