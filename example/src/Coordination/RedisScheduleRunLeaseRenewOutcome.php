<?php

declare(strict_types=1);

namespace Example\Coordination;

enum RedisScheduleRunLeaseRenewOutcome: string
{
    case Renewed = 'renewed';
    case Lost = 'lost';
}
