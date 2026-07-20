<?php

declare(strict_types=1);

namespace Example\Cli;

final readonly class ApplicationCommandExecution
{
    public function __construct(
        public ApplicationCommandName $command,
        public ApplicationCommandOutcome $outcome,
    ) {
    }

    public function stdoutLine(): string
    {
        return '{"command":"' . $this->command->value
            . '","outcome":"' . $this->outcome->value . '"}' . "\n";
    }
}
