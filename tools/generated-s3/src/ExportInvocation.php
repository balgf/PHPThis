<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

final readonly class ExportInvocation
{
    private function __construct(public string $command, public ?ExportId $id) {}

    /** @param list<string> $arguments */
    public static function fromArguments(array $arguments): self
    {
        if (count($arguments) < 1 || count($arguments) > 2) { throw new \InvalidArgumentException('Invalid arguments.'); }
        $command = $arguments[0];
        if (!in_array($command, ['generate', 'generate-test-tone', 'verify', 'reconcile-one', 'expire-one'], true)) { throw new UnknownExportCommand('Unknown command.'); }
        if (in_array($command, ['generate', 'generate-test-tone'], true) ? count($arguments) !== 1 : count($arguments) !== 2) {
            throw new \InvalidArgumentException('Invalid arguments.');
        }
        return new self($command, isset($arguments[1]) ? new ExportId($arguments[1]) : null);
    }
}
