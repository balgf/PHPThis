<?php

declare(strict_types=1);

use Example\ApplicationComposition;
use Example\ApplicationDatabasePath;
use Example\Cli\ApplicationCommandLine;
use Example\Cli\ApplicationCommandName;
use Example\Cli\InvalidApplicationCommandArguments;
use Example\Jobs\UserWelcomeJobEnvelope;

/** @return array<string, Closure(): void> */
function cliTests(): array
{
    return [
        'application console rejects unknown commands before database work' => static function (): void {
            $missingDatabasePath = dirname(__DIR__)
                . '/tmp/application-tests/unknown-command-private.sqlite';
            resetCliMissingPath($missingDatabasePath);
            $expected = [
                'exit_code' => 2,
                'stdout' => '',
                'stderr' => "{\"error\":\"unknown_command\"}\n",
            ];
            $withoutOption = runApplicationConsole(['unknown:command']);
            $withOption = runApplicationConsole([
                'unknown:command',
                '--database=' . $missingDatabasePath,
            ]);

            if (
                $withoutOption !== $expected
                || $withOption !== $expected
                || str_contains($withOption['stderr'], $missingDatabasePath)
                || is_file($missingDatabasePath)
                || is_file($missingDatabasePath . '.schedule.lock')
                || is_file($missingDatabasePath . '.migration.lock')
            ) {
                throw new RuntimeException('Unknown commands must fail before I/O with one redacted stderr line.');
            }
        },

        'application console rejects every invalid argument shape before database work' => static function (): void {
            $missingDatabasePath = dirname(__DIR__)
                . '/tmp/application-tests/invalid-arguments-private.sqlite';
            resetCliMissingPath($missingDatabasePath);
            $expected = [
                'exit_code' => 2,
                'stdout' => '',
                'stderr' => "{\"error\":\"invalid_arguments\"}\n",
            ];
            $invalidArguments = [
                [],
                ['jobs:run-one', '--database='],
                ['jobs:run-one', '--database=relative.sqlite'],
                ['jobs:run-one', '--database=/tmp/'],
                ['jobs:run-one', '--database=/tmp/invalid\\'],
                ['jobs:run-one', "--database=/tmp/invalid\npath.sqlite"],
                ['jobs:run-one', '--database=/' . str_repeat('a', 4_096)],
                ['jobs:run-one', '--data=' . $missingDatabasePath],
                ['--database=' . $missingDatabasePath, 'jobs:run-one'],
                [
                    'jobs:run-one',
                    '--database=' . $missingDatabasePath,
                    '--database=' . $missingDatabasePath,
                ],
                ['jobs:run-one', '--database=' . $missingDatabasePath, 'unexpected'],
                ['schedule:run', '--database=' . $missingDatabasePath, 'unexpected'],
                ['database:migrate', '--database=' . $missingDatabasePath, 'unexpected'],
            ];

            foreach ($invalidArguments as $arguments) {
                $result = runApplicationConsole($arguments);

                if (
                    $result !== $expected
                    || str_contains($result['stderr'], $missingDatabasePath)
                ) {
                    throw new RuntimeException('Invalid CLI arguments must produce only the stable redacted usage error.');
                }
            }

            if (
                is_file($missingDatabasePath)
                || is_file($missingDatabasePath . '.schedule.lock')
                || is_file($missingDatabasePath . '.migration.lock')
            ) {
                throw new RuntimeException('Invalid CLI arguments must perform no database or lock I/O.');
            }
        },

        'application command parser accepts exactly 4096 absolute path bytes' => static function (): void {
            $maximumPath = '/' . str_repeat('a', 4_095);
            $oversizedPath = $maximumPath . 'a';
            $parsed = ApplicationCommandLine::fromArguments(
                ['console.php', 'jobs:run-one', '--database=' . $maximumPath],
                '/unused-default.sqlite',
            );
            $oversizedRejected = false;

            try {
                ApplicationCommandLine::fromArguments(
                    ['console.php', 'jobs:run-one', '--database=' . $oversizedPath],
                    '/unused-default.sqlite',
                );
            } catch (InvalidApplicationCommandArguments) {
                $oversizedRejected = true;
            }

            if (
                strlen($maximumPath) !== 4_096
                || $parsed->command !== ApplicationCommandName::JobsRunOne
                || $parsed->databasePath->value !== $maximumPath
                || !$oversizedRejected
            ) {
                throw new RuntimeException('The typed database path boundary must accept 4096 bytes and reject 4097.');
            }
        },

        'application console reports missing databases as one redacted operational failure' => static function (): void {
            $missingDatabasePath = dirname(__DIR__)
                . '/tmp/application-tests/missing-command-private.sqlite';
            resetCliMissingPath($missingDatabasePath);
            $expected = [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => "{\"error\":\"command_failed\"}\n",
            ];

            foreach (['jobs:run-one', 'schedule:run'] as $command) {
                $result = runApplicationConsole([
                    $command,
                    '--database=' . $missingDatabasePath,
                ]);

                if (
                    $result !== $expected
                    || str_contains($result['stderr'], $missingDatabasePath)
                    || is_file($missingDatabasePath)
                    || is_file($missingDatabasePath . '.schedule.lock')
                ) {
                    throw new RuntimeException('Operational failures must use one redacted stderr line.');
                }
            }
        },

        'jobs run-one command handles at most one delivery in each fresh process' => static function (): void {
            $databasePath = createUserDatabaseFixture('cli-jobs-run-one', 0, false);
            $firstJob = UserWelcomeJobEnvelope::forEmail('cli-process-one@example.com');
            $secondJob = UserWelcomeJobEnvelope::forEmail('cli-process-two@example.com');
            insertAvailableJob($databasePath, $firstJob->jobId, $firstJob->toJson(), 0);
            insertAvailableJob($databasePath, $secondJob->jobId, $secondJob->toJson(), 0);

            $first = runApplicationConsole([
                'jobs:run-one',
                '--database=' . $databasePath,
            ]);
            $afterFirst = jobAggregate($databasePath);
            $second = runApplicationConsole([
                'jobs:run-one',
                '--database=' . $databasePath,
            ]);
            $afterSecond = jobAggregate($databasePath);
            $idle = runApplicationConsole([
                'jobs:run-one',
                '--database=' . $databasePath,
            ]);

            if (
                $first !== successfulConsoleResult('jobs:run-one', 'completed')
                || $afterFirst['available_count'] !== 1
                || $afterFirst['succeeded_count'] !== 1
                || $afterFirst['effect_count'] !== 1
                || $second !== successfulConsoleResult('jobs:run-one', 'completed')
                || $afterSecond['available_count'] !== 0
                || $afterSecond['succeeded_count'] !== 2
                || $afterSecond['effect_count'] !== 2
                || $idle !== successfulConsoleResult('jobs:run-one', 'idle')
            ) {
                throw new RuntimeException('Each fresh jobs:run-one process must handle at most one delivery.');
            }
        },

        'schedule run uses explicit UTC five-minute slots and handles at most one delivery' => static function (): void {
            $databasePath = createUserDatabaseFixture('cli-schedule-slots', 0, false);

            foreach (['one', 'two', 'three'] as $suffix) {
                $job = UserWelcomeJobEnvelope::forEmail('schedule-' . $suffix . '@example.com');
                insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 0);
            }

            $composition = new ApplicationComposition(
                ApplicationDatabasePath::fromString($databasePath),
            );
            $beforeSlot = $composition
                ->commands(new TestUserWelcomeJobClock(299))
                ->run(ApplicationCommandName::ScheduleRun);
            $afterBeforeSlot = jobAggregate($databasePath);
            $slotStart = $composition
                ->commands(new TestUserWelcomeJobClock(300))
                ->run(ApplicationCommandName::ScheduleRun);
            $afterSlotStart = jobAggregate($databasePath);
            $slotEnd = $composition
                ->commands(new TestUserWelcomeJobClock(359))
                ->run(ApplicationCommandName::ScheduleRun);
            $afterSlotEnd = jobAggregate($databasePath);
            $afterSlot = $composition
                ->commands(new TestUserWelcomeJobClock(360))
                ->run(ApplicationCommandName::ScheduleRun);
            $afterAfterSlot = jobAggregate($databasePath);

            if (
                $beforeSlot->stdoutLine() !== successfulConsoleLine('schedule:run', 'not_due')
                || $afterBeforeSlot['available_count'] !== 3
                || $afterBeforeSlot['succeeded_count'] !== 0
                || $afterBeforeSlot['effect_count'] !== 0
                || $slotStart->stdoutLine() !== successfulConsoleLine('schedule:run', 'completed')
                || $afterSlotStart['available_count'] !== 2
                || $afterSlotStart['succeeded_count'] !== 1
                || $afterSlotStart['effect_count'] !== 1
                || $slotEnd->stdoutLine() !== successfulConsoleLine('schedule:run', 'completed')
                || $afterSlotEnd['available_count'] !== 1
                || $afterSlotEnd['succeeded_count'] !== 2
                || $afterSlotEnd['effect_count'] !== 2
                || $afterSlot->stdoutLine() !== successfulConsoleLine('schedule:run', 'not_due')
                || $afterAfterSlot !== $afterSlotEnd
            ) {
                throw new RuntimeException('The explicit clock must select complete UTC five-minute slots without catch-up work.');
            }
        },

        'schedule run skips a subprocess-held same-host lock without blocking or delivering' => static function (): void {
            $databasePath = createUserDatabaseFixture('cli-schedule-overlap', 0, false);
            $job = UserWelcomeJobEnvelope::forEmail('schedule-overlap@example.com');
            insertAvailableJob($databasePath, $job->jobId, $job->toJson(), 0);
            $composition = new ApplicationComposition(
                ApplicationDatabasePath::fromString($databasePath),
            );
            $contended = runScheduleWithSubprocessLock($composition, $databasePath);

            $completed = $composition
                ->commands(new TestUserWelcomeJobClock(300))
                ->run(ApplicationCommandName::ScheduleRun);
            $afterRelease = jobAggregate($databasePath);

            if (
                $contended['stdout'] !== successfulConsoleLine('schedule:run', 'overlap_skipped')
                || $contended['duration_us'] > 1_000_000
                || $contended['available_count'] !== 1
                || $contended['succeeded_count'] !== 0
                || $contended['effect_count'] !== 0
                || $completed->stdoutLine() !== successfulConsoleLine('schedule:run', 'completed')
                || $afterRelease['available_count'] !== 0
                || $afterRelease['succeeded_count'] !== 1
                || $afterRelease['effect_count'] !== 1
            ) {
                throw new RuntimeException('A subprocess-contended local schedule lock must skip immediately and perform no delivery.');
            }
        },

        'application composition keeps CLI execution outside fresh HTTP request state' => static function (): void {
            $databasePath = createUserDatabaseFixture('cli-http-composition', 0, false);
            $composition = new ApplicationComposition(
                ApplicationDatabasePath::fromString($databasePath),
            );
            $firstHttp = $composition->http();
            $firstCommands = $composition->commands(new TestUserWelcomeJobClock(300));
            $secondCommands = $composition->commands(new TestUserWelcomeJobClock(300));
            $errorLogPath = $databasePath . '.http.log';

            if (is_file($errorLogPath) && !unlink($errorLogPath)) {
                throw new RuntimeException('Unable to reset the HTTP composition test log.');
            }

            $previousErrorLog = ini_set('error_log', $errorLogPath);

            if ($previousErrorLog === false) {
                throw new RuntimeException('Unable to isolate the HTTP composition test log.');
            }

            try {
                $firstResponse = $firstHttp->handle(
                    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
                    [],
                );
                $commandExecution = $firstCommands->run(ApplicationCommandName::JobsRunOne);
                $secondHttp = $composition->http();
                $secondResponse = $secondHttp->handle(
                    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
                    [],
                );
            } finally {
                ini_set('error_log', $previousErrorLog);
            }

            $firstRequestId = $firstResponse->headers['X-Request-ID'] ?? null;
            $secondRequestId = $secondResponse->headers['X-Request-ID'] ?? null;

            if (
                $firstHttp === $secondHttp
                || $firstCommands === $secondCommands
                || $commandExecution->stdoutLine() !== successfulConsoleLine('jobs:run-one', 'idle')
                || $firstResponse->status !== 200
                || $secondResponse->status !== 200
                || $firstResponse->body !== "{\"status\":\"ok\"}\n"
                || $secondResponse->body !== "{\"status\":\"ok\"}\n"
                || !is_string($firstRequestId)
                || !is_string($secondRequestId)
                || $firstRequestId === $secondRequestId
            ) {
                throw new RuntimeException('HTTP and CLI composition must create separate finite state for each execution.');
            }
        },
    ];
}

/**
 * @param list<string> $arguments
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runApplicationConsole(array $arguments): array
{
    return runIsolatedPhpTest(
        dirname(__DIR__) . '/example/bin/console.php',
        $arguments,
    );
}

/** @return array{exit_code: int, stdout: string, stderr: string} */
function successfulConsoleResult(string $command, string $outcome): array
{
    return [
        'exit_code' => 0,
        'stdout' => successfulConsoleLine($command, $outcome),
        'stderr' => '',
    ];
}

function successfulConsoleLine(string $command, string $outcome): string
{
    return json_encode(
        ['command' => $command, 'outcome' => $outcome],
        JSON_THROW_ON_ERROR,
    ) . "\n";
}

function resetCliMissingPath(string $databasePath): void
{
    foreach (
        [
            $databasePath,
            $databasePath . '.schedule.lock',
            $databasePath . '.migration.lock',
        ] as $path
    ) {
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to reset a CLI missing-path test artifact.');
        }
    }
}

/**
 * @return array{
 *     stdout: string,
 *     duration_us: int,
 *     available_count: int,
 *     succeeded_count: int,
 *     effect_count: int
 * }
 */
function runScheduleWithSubprocessLock(
    ApplicationComposition $composition,
    string $databasePath,
): array {
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/cli-schedule-lock-holder.php', $databasePath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the schedule lock-holder process.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = hrtime(true) + 5_000_000_000;

    while (!str_contains($stdout, "READY\n") && hrtime(true) < $deadline) {
        $stdoutChunk = stream_get_contents($pipes[1]);
        $stderrChunk = stream_get_contents($pipes[2]);

        if (is_string($stdoutChunk)) {
            $stdout .= $stdoutChunk;
        }

        if (is_string($stderrChunk)) {
            $stderr .= $stderrChunk;
        }

        if (!str_contains($stdout, "READY\n")) {
            usleep(10_000);
        }
    }

    if (!str_contains($stdout, "READY\n")) {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new RuntimeException('Schedule lock holder did not become ready: ' . $stderr);
    }

    try {
        $startedAt = hrtime(true);
        $execution = $composition
            ->commands(new TestUserWelcomeJobClock(300))
            ->run(ApplicationCommandName::ScheduleRun);
        $durationUs = max(0, intdiv(hrtime(true) - $startedAt, 1_000));
        $aggregate = jobAggregate($databasePath);
    } finally {
        proc_terminate($process);
        $remainingStdout = stream_get_contents($pipes[1]);
        $remainingStderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (is_string($remainingStdout)) {
            $stdout .= $remainingStdout;
        }

        if (is_string($remainingStderr)) {
            $stderr .= $remainingStderr;
        }
    }

    if ($stdout !== "READY\n" || $stderr !== '') {
        throw new RuntimeException('Schedule lock holder emitted unexpected output.');
    }

    return [
        'stdout' => $execution->stdoutLine(),
        'duration_us' => $durationUs,
        'available_count' => $aggregate['available_count'],
        'succeeded_count' => $aggregate['succeeded_count'],
        'effect_count' => $aggregate['effect_count'],
    ];
}
