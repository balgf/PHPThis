<?php

declare(strict_types=1);

namespace Example\Cli;

use InvalidArgumentException;

final class UnknownApplicationCommand extends InvalidArgumentException
{
}
