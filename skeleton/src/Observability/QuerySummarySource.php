<?php

declare(strict_types=1);

namespace App\Observability;

use InvalidArgumentException;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

/**
 * @phpstan-import-type QuerySnapshot from QueryTrace
 * @phpstan-type QuerySourceSnapshot array{
 *     name: string,
 *     budget_limit: int,
 *     budget_used: int,
 *     budget_exceeded: bool,
 *     query_trace: QuerySnapshot
 * }
 */
final readonly class QuerySummarySource
{
    public function __construct(
        public string $name,
        private QueryBudget $budget,
        private QueryTrace $trace,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]{0,31}\z/D', $name) !== 1) {
            throw new InvalidArgumentException(
                'A query-summary source must be a 1-to-32-byte code-owned lowercase label.',
            );
        }
    }

    /** @return QuerySourceSnapshot */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'budget_limit' => $this->budget->limit(),
            'budget_used' => $this->budget->used(),
            'budget_exceeded' => $this->budget->exceeded(),
            'query_trace' => $this->trace->snapshot(),
        ];
    }

    public function sharesObservationStateWith(self $other): bool
    {
        return $this->budget === $other->budget || $this->trace === $other->trace;
    }
}
