<?php

declare(strict_types=1);

namespace Example\Migrations;

enum ApplicationMigrationOutcome: string
{
    case Applied = 'applied';
    case UpToDate = 'up_to_date';
}
