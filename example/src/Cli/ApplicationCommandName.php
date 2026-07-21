<?php

declare(strict_types=1);

namespace Example\Cli;

enum ApplicationCommandName: string
{
    case DatabaseMigrate = 'database:migrate';
    case JobsRunOne = 'jobs:run-one';
    case ScheduleRun = 'schedule:run';
}
