<?php

declare(strict_types=1);

namespace Example\Coordination;

use RuntimeException;
use Throwable;

final class RedisScheduleRunLeaseUnavailable extends RuntimeException
{
    /** @param list<string> $coordination */
    public function __construct(
        public readonly array $coordination = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct('The Redis schedule-run lease is unavailable.', 0, $previous);
    }

    public function stderrLine(): string
    {
        return json_encode(
            ['error' => 'command_failed', 'coordination' => $this->coordination],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ) . "\n";
    }
}
