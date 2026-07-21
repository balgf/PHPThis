<?php

declare(strict_types=1);

namespace Example\Coordination;

use OverflowException;

final class RedisScheduleRunLeaseTrace
{
    private const int MAXIMUM_OUTCOMES = 8;

    /** @var list<RedisScheduleRunLeaseTraceOutcome> */
    private array $outcomes = [];

    public function record(RedisScheduleRunLeaseTraceOutcome $outcome): void
    {
        if (count($this->outcomes) >= self::MAXIMUM_OUTCOMES) {
            throw new OverflowException('Redis schedule-run lease trace is full.');
        }

        $this->outcomes[] = $outcome;
    }

    /** @return list<string> */
    public function snapshot(): array
    {
        return array_map(
            static fn (RedisScheduleRunLeaseTraceOutcome $outcome): string => $outcome->value,
            $this->outcomes,
        );
    }
}
