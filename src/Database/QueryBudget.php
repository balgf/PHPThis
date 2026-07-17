<?php

declare(strict_types=1);

namespace PHPThis\Database;

use InvalidArgumentException;

final class QueryBudget
{
    private int $used = 0;

    public function __construct(private readonly int $limit)
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Query budget limit must be at least 1.');
        }
    }

    public function recordStatement(): void
    {
        if ($this->used >= $this->limit) {
            throw new QueryBudgetExceeded(
                sprintf('Query budget of %d statements was exceeded.', $this->limit),
            );
        }

        $this->used++;
    }

    public function used(): int
    {
        return $this->used;
    }

    public function limit(): int
    {
        return $this->limit;
    }
}
