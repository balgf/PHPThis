<?php

declare(strict_types=1);

use Example\ApplicationComposition;
use Example\Cli\ApplicationCommandLine;
use Example\Cli\InvalidApplicationCommandArguments;
use Example\Cli\UnknownApplicationCommand;
use Example\Jobs\SystemUserWelcomeJobClock;

require dirname(__DIR__, 2) . '/autoload.php';

try {
    /** @var list<string> $arguments */
    $arguments = $argv;
    $commandLine = ApplicationCommandLine::fromArguments(
        $arguments,
        dirname(__DIR__, 2) . '/tmp/example.sqlite',
    );
    $composition = new ApplicationComposition($commandLine->databasePath);
    $execution = $composition
        ->commands(new SystemUserWelcomeJobClock())
        ->run($commandLine->command);

    fwrite(STDOUT, $execution->stdoutLine());
    exit(0);
} catch (UnknownApplicationCommand) {
    fwrite(STDERR, "{\"error\":\"unknown_command\"}\n");
    exit(2);
} catch (InvalidApplicationCommandArguments) {
    fwrite(STDERR, "{\"error\":\"invalid_arguments\"}\n");
    exit(2);
} catch (Throwable) {
    fwrite(STDERR, "{\"error\":\"command_failed\"}\n");
    exit(1);
}
