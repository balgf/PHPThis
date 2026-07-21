<?php

declare(strict_types=1);

namespace Example\Coordination;

enum RedisScheduleRunLeaseReleaseOutcome: string
{
    case Released = 'released';
    case Lost = 'lost';
}
