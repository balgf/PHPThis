<?php

declare(strict_types=1);

namespace Example\Cli;

use Example\ApplicationDatabasePath;
use Example\InvalidApplicationDatabasePath;

final readonly class ApplicationCommandLine
{
    private function __construct(
        public ApplicationCommandName $command,
        public ApplicationDatabasePath $databasePath,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public static function fromArguments(array $arguments, string $defaultDatabasePath): self
    {
        if (!array_key_exists(1, $arguments)) {
            throw new InvalidApplicationCommandArguments();
        }

        if (str_starts_with($arguments[1], '--')) {
            throw new InvalidApplicationCommandArguments();
        }

        $command = ApplicationCommandName::tryFrom($arguments[1]);

        if (!$command instanceof ApplicationCommandName) {
            throw new UnknownApplicationCommand();
        }

        if (count($arguments) > 3) {
            throw new InvalidApplicationCommandArguments();
        }

        $databasePath = $defaultDatabasePath;

        if (array_key_exists(2, $arguments)) {
            $submitted = $arguments[2];

            if (!str_starts_with($submitted, '--database=')) {
                throw new InvalidApplicationCommandArguments();
            }

            $databasePath = substr($submitted, strlen('--database='));
        }

        try {
            $path = ApplicationDatabasePath::fromString($databasePath);
        } catch (InvalidApplicationDatabasePath $exception) {
            throw new InvalidApplicationCommandArguments(previous: $exception);
        }

        return new self($command, $path);
    }
}
