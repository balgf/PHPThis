<?php

declare(strict_types=1);

namespace Example\Coordination;

enum RedisScheduleRunLeaseAcquireOutcome: string
{
    case Acquired = 'acquired';
    case Contended = 'contended';
}
