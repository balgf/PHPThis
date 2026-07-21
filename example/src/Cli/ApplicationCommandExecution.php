<?php

declare(strict_types=1);

namespace Example\Cli;

final readonly class ApplicationCommandExecution
{
    /** @param list<string>|null $coordination */
    public function __construct(
        public ApplicationCommandName $command,
        public ApplicationCommandOutcome $outcome,
        public ?array $coordination = null,
    ) {
    }

    public function stdoutLine(): string
    {
        $event = [
            'command' => $this->command->value,
            'outcome' => $this->outcome->value,
        ];

        if ($this->coordination !== null) {
            $event['coordination'] = $this->coordination;
        }

        return json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
