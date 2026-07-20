<?php

declare(strict_types=1);

namespace Example\Jobs;

enum UserWelcomeJobOutcome: string
{
    case Idle = 'idle';
    case Completed = 'completed';
    case RetryScheduled = 'retry_scheduled';
    case DeadLettered = 'dead_lettered';
}
