<?php

declare(strict_types=1);

namespace Example\Cli;

enum ApplicationCommandName: string
{
    case JobsRunOne = 'jobs:run-one';
    case ScheduleRun = 'schedule:run';
}
