<?php

declare(strict_types=1);

namespace Example\Coordination;

enum RedisScheduleRunLeaseTraceOutcome: string
{
    case Connected = 'connected';
    case ConnectFailed = 'connect_failed';
    case Acquired = 'acquired';
    case Contended = 'contended';
    case AcquireFailed = 'acquire_failed';
    case Renewed = 'renewed';
    case LostOnRenewal = 'lost_on_renewal';
    case RenewFailed = 'renew_failed';
    case RenewalLimitReached = 'renewal_limit_reached';
    case Released = 'released';
    case LostBeforeRelease = 'lost_before_release';
    case ReleaseFailed = 'release_failed';
}
