<?php

declare(strict_types=1);

namespace Example\Jobs;

interface UserWelcomeJobClock
{
    public function now(): int;
}
