<?php

declare(strict_types=1);

namespace Example\Jobs;

use PHPThis\Database\Connection;

interface UserWelcomeJobHandler
{
    public function handle(
        Connection $connection,
        UserWelcomeJobEnvelope $job,
        int $now,
    ): void;
}
