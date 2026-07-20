<?php

declare(strict_types=1);

namespace Example\Cli;

enum ApplicationCommandOutcome: string
{
    case Idle = 'idle';
    case Completed = 'completed';
    case RetryScheduled = 'retry_scheduled';
    case DeadLettered = 'dead_lettered';
    case NotDue = 'not_due';
    case OverlapSkipped = 'overlap_skipped';
}
