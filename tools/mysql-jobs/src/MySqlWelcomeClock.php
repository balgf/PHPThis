<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use Example\Jobs\UserWelcomeJobClock;
use PHPThis\Database\Connection;
use UnexpectedValueException;

final readonly class MySqlWelcomeClock implements UserWelcomeJobClock
{
    public function __construct(private Connection $connection)
    {
    }

    public function now(): int
    {
        $row = $this->connection->selectOneRow('SELECT UNIX_TIMESTAMP() AS now_seconds');
        $now = $row['now_seconds'] ?? null;
        if (!is_int($now) || $now < 0 || $now > PHP_INT_MAX - 86_400) {
            throw new UnexpectedValueException('MySQL returned an invalid clock value.');
        }
        return $now;
    }
}
