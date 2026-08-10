<?php

declare(strict_types=1);

function workbenchRuntimePathIsForbidden(string $relativePath): bool
{
    if (!str_starts_with($relativePath, 'src/')) {
        return false;
    }

    foreach (explode('/', substr($relativePath, 4)) as $segment) {
        $name = preg_replace('/\.php\z/i', '', $segment);

        if (!is_string($name)) {
            continue;
        }

        $compactName = preg_replace('/[^A-Za-z0-9]+/', '', strtolower($name));

        if (
            is_string($compactName)
            && in_array(
                $compactName,
                ['workbench', 'workbenches', 'repl', 'repls', 'interactiveshell', 'interactiveshells'],
                true,
            )
        ) {
            return true;
        }

        $tokenizableName = str_replace('REPLs', 'Repls', $name);
        $wordSeparatedName = preg_replace(
            [
                '/(?<=[a-z0-9])(?=[A-Z])/',
                '/(?<=[A-Z])(?=[A-Z][a-z])/',
                '/(?<=[A-Za-z])(?=[0-9])/',
                '/(?<=[0-9])(?=[A-Za-z])/',
            ],
            '-',
            $tokenizableName,
        );

        if (!is_string($wordSeparatedName)) {
            continue;
        }

        $words = preg_split('/[^A-Za-z0-9]+/', strtolower($wordSeparatedName), -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            continue;
        }

        foreach ($words as $index => $word) {
            if (in_array($word, ['workbench', 'workbenches', 'repl', 'repls'], true)) {
                return true;
            }

            if (
                $word === 'interactive'
                && in_array($words[$index + 1] ?? null, ['shell', 'shells'], true)
            ) {
                return true;
            }
        }
    }

    return false;
}

/** @return list<string> */
function operationGuardrailFailures(string $root): array
{
    $failures = [];

    $durableJobArtifactMarkers = [
        '.ai/README.md' => [
            '| Change durable deferred work | `.ai/jobs.md` | producer transaction, worker path, and lifecycle tests |',
        ],
        '.ai/jobs.md' => [
            '# Durable jobs contract',
            'Use [Externally supervised one-shot durable jobs](../docs/jobs/operations.md) as the canonical production-operations guide when continual consumption or worker deployment is involved.',
            'same `Connection`, in the same explicit SQLite transaction',
            'Treat delivery as at-least-once.',
            'claim and finalize zero or one delivery',
            '`schedule:run` is a bounded cadence-gated pass that may invoke the operation once and is not the ordinary queue-draining worker.',
            'Every finite `jobs:run-one` outcome—`idle`, `completed`, `retry_scheduled`, and `dead_lettered`—exits `0`',
            'Require positive bounded idle pacing or equivalent supervisor delay;',
            'SQLite has one writer, and its busy timeout does not add write capacity',
            'A production adopter separately supplies the deployment and supervisor evidence required by the operations guide.',
            'generic framework command map',
            'Do not add an ORM',
        ],
        '.ai/testing.md' => [
            'exact finite retry delays from freshly observed failure time',
            'completion rollback when handler time reaches lease expiry',
        ],
        'docs/jobs.md' => [
            'one accepted durable-job recipe and no framework queue mechanism',
            '[externally supervised one-shot durable jobs](jobs/operations.md) is the focused canonical production-operations guide.',
            'This is at-least-once delivery.',
            'one finite complete `UPDATE ... RETURNING` statement',
            'claim-time snapshot is not sufficient',
            'failure-only restart behavior does not provide continual consumption.',
            'Each enabled supervisor slot requires a positive bounded idle delay or equivalent pacing',
            'Production one-offs also stay in the finite tested application console rather than an arbitrary expression process.',
            'PHPThis ships no job or envelope type',
        ],
        'docs/jobs/README.md' => [
            'Durable-job knowledge index',
            'SQLite schema',
            '[Externally supervised one-shot operations](operations.md): canonical production topology, direct continual consumption, supervisor policy, SQLite capacity, monitoring, recovery, and reconsideration triggers.',
        ],
        'docs/jobs/envelope.md' => [
            'bounded untrusted input',
            'Dispatch is an exhaustive finite `match`',
        ],
        'docs/jobs/lifecycle.md' => [
            'same `Connection`, explicit transaction, and SQLite database',
            'freshly observed transition time',
        ],
        'docs/jobs/operations.md' => [
            '# Externally supervised one-shot durable jobs',
            'This is the focused canonical operations guide for continual consumption',
            'The supervisor is long-running; each PHP worker process is one-shot:',
            'Each invocation creates a fresh connection',
            'It is not the ordinary queue-draining worker.',
            'A supervisor configured to restart only after failure therefore stops after the first expected result',
            'the separately owned supervisor and the location and owner of its configuration, without making a named process manager or platform part of PHPThis;',
            'the exact application console invocation, deployment identity, configuration source and redaction boundary, and access to the selected database path;',
            'the invocation or restart delay, worker-slot count, total concurrency limit, process timeout, and forced-termination policy;',
            'the deployment replacement and configuration-change behavior, including when old children stop and fresh composition begins;',
            'the shutdown behavior and the finite allowance before a current child is terminated;',
            'restart-storm protection for repeated startup, configuration, database, or other operational failures.',
            'Every enabled slot uses a positive bounded idle delay or equivalent supervisor pacing',
            'Clean stopping has three steps:',
            'Recovery relies on the existing finite SQLite lease:',
            'SQLite permits one writer at a time.',
            'repository proves behavior on file-backed fixtures',
            '## Required production evidence',
            'launch another fresh process after a successful expected exit `0`;',
            'drain multiple queued jobs through multiple fresh processes, with each process claiming and finalizing at most one delivery;',
            'raise the recorded queue-depth, oldest-due-age, duration, operational-failure, and dead-letter-growth capacity alarms.',
            'A bounded multi-delivery process or an indefinite worker loop requires a separate accountable decision and evidence.',
            'queue-age or throughput objectives remain unmet after the application tunes and proves one-shot supervision;',
            'PHP startup and fresh-composition cost materially dominates delivery work;',
            'independent applications reproduce the same smaller lifecycle need.',
            'Strict Profile diagnostic `PHT003`',
        ],
        'docs/jobs/schema.md' => [
            'SQLite `STRICT` tables',
            'partial index',
            'PHPThis supplies no migration runner',
        ],
        'docs/jobs/testing.md' => [
            'real worker subprocess terminated after claim',
            'sample it again before every fenced transition',
            'Production supervisor evidence is separately required by [externally supervised one-shot durable jobs](operations.md).',
        ],
        'docs/cli.md' => [
            'Continual durable-job consumption follows the separate [externally supervised one-shot operations guide](jobs/operations.md).',
            '`schedule:run` remains a bounded cadence-gated scheduled pass that may call the same operation once; it is not the ordinary queue-draining worker.',
            'A supervisor configured to restart only after failure will stop after any expected outcome.',
            'Continual consumption therefore launches another fresh process after expected exit `0`',
            'the production evidence required by [the durable-job operations guide](jobs/operations.md)',
        ],
        'docs/cli/README.md' => [
            'continual job consumption instead follows [externally supervised one-shot operations](../jobs/operations.md).',
        ],
        'docs/cli/scheduling-locking.md' => [
            'It is a bounded scheduled pass, not the ordinary queue-draining worker;',
            'continual consumption directly supervises fresh `jobs:run-one` processes under [the durable-job operations guide](../jobs/operations.md).',
        ],
        'docs/decisions/024-application-owned-sqlite-durable-jobs.md' => [
            'Status: accepted',
            'Consumer Contract version 5 and Strict Profile version 2 remain unchanged.',
            'entirely application-owned and SQLite-specific',
            'claims at most one due job',
            'Delivery is at least once.',
            'No Consumer Contract, Strict Profile, framework core, generic job lifecycle, reusable worker API, or cross-engine queue claim is introduced.',
        ],
        'docs/consumer-contract.md' => [
            '## Optional application-owned durable jobs',
            'Contract version 9 does not make that additional file a checker requirement',
            'Delivery remains at least once.',
        ],
        'docs/decisions/README.md' => [
            '024-application-owned-sqlite-durable-jobs.md',
        ],
        'docs/getting-started.md' => [
            '`NOT_APPLICABLE(JOBS)` in `.ai/jobs.md`',
            'fresh-time lease fencing',
        ],
        'docs/guardrails.md' => [
            'The durable-job guard retains ADR 024',
            'the canonical externally supervised continual-consumption policy',
            'The installed proof rereads the packaged job, CLI, operations, index, testing, and application-context guidance.',
            'it does not inspect or run a process manager, drain a real production queue, certify a filesystem or SQLite deployment, measure throughput or contention, activate an alarm',
            'continued absence from framework core and package runtime APIs',
        ],
        'docs/knowledge-map.md' => [
            '`docs/jobs.md`, `docs/security.md`, `docs/jobs/operations.md` for production supervision',
            'externally supervised successful-exit repetition, pacing, stop, capacity, and alarm policy',
            'verify that no framework queue mechanism exists',
        ],
        'docs/security.md' => [
            'Treat every stored job envelope as untrusted input',
            '## Durable-job limits',
            'do not prove exactly-once execution',
        ],
        'docs/vocabulary.md' => [
            '| durable-job envelope |',
            '| commit-visible job publication |',
            '| one-shot worker |',
            '| at-least-once delivery |',
            '| dead letter |',
        ],
        'ROADMAP.md' => [
            'ADR 024 accepts one application-owned SQLite durable-job proof',
            'ADR 024 accepts one SQLite-specific application recipe, not core job, worker, dispatcher, broker, or exactly-once contracts',
        ],
        'example/.ai/README.md' => [
            'Change durable-job publication, envelopes, worker lifecycle, retries, or dead letters',
            '`.ai/jobs.md`, `.ai/data.md`, `.ai/observability.md`',
        ],
        'example/.ai/data.md' => [
            '## Durable-job tables',
            '`application_jobs` and `welcome_deliveries`',
            'No document-list or durable-job application SQL is certified on those engines.',
        ],
        'example/.ai/jobs.md' => [
            'The executable example follows ADR 024',
            'Every lease lasts 30 seconds.',
            'At most three claimed deliveries are permitted',
            'both console commands emit one redacted result with the recorded exit and stream contract',
        ],
        'example/src/Jobs/README.md' => [
            'application-owned evidence for ADR 024',
            'fresh-time lease fencing',
        ],
        'example/src/Jobs/UserWelcomeJobEnvelope.php' => [
            "public const string TYPE = 'user.welcome';",
            'public static function fromStored(string $jobId, string $json): self',
            'hash_equals(self::idempotencyKeyForEmail($email), $idempotencyKey)',
        ],
        'example/src/Jobs/UserWelcomeJobClock.php' => [
            'interface UserWelcomeJobClock',
            'public function now(): int;',
        ],
        'example/src/Jobs/SystemUserWelcomeJobClock.php' => [
            'final readonly class SystemUserWelcomeJobClock implements UserWelcomeJobClock',
            'return time();',
        ],
        'example/src/Jobs/SqliteUserWelcomeJobWorker.php' => [
            'public function runOne(string $leaseToken): UserWelcomeJobOutcome',
            '$claimNow = $this->currentTime(0);',
            '$completionNow = $this->currentTime($handlerNow);',
            'UPDATE application_jobs',
            'AND lease_expires_at > :completion_checked_at',
            'lease_expired_after_final_attempt',
        ],
        'example/src/Jobs/RecordUserWelcomeDelivery.php' => [
            'ON CONFLICT (idempotency_key) DO NOTHING',
        ],
        'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
            '$job = UserWelcomeJobEnvelope::forEmail($command->email);',
            'INSERT INTO application_jobs (',
            '$this->connection->commit();',
        ],
        'example/src/Cli/ApplicationCommands.php' => [
            'private function runOneJob(?string $databasePath = null): ApplicationCommandOutcome',
            '$worker->runOne(bin2hex(random_bytes(16)))',
            'new QueryBudget(3)',
            'new QueryTrace(3)',
        ],
        'example/bin/console.php' => [
            'new SystemUserWelcomeJobClock()',
            '"{\"error\":\"command_failed\"}\n"',
        ],
        'tests/run.php' => [
            "require __DIR__ . '/jobs.php';",
            "frameworkBehaviorGroupDefinitions('jobs', jobTests())",
        ],
        'tests/crud.php' => [
            'account-scoped user creation publishes one job with four writes across dataset sizes',
        ],
        'tests/jobs.php' => [
            'durable job publication rolls back business event and job together',
            'durable job worker is idle and keeps three statements across queue sizes',
            'durable job samples fresh time before dispatch and skips an expired lease',
            'durable job completion samples fresh time and rejects an expired lease',
            'durable job retry backoff starts from freshly observed failure time',
            'durable job subprocess crash is fenced and safely redelivered after lease expiry',
        ],
        'tests/cli.php' => [
            'jobs run-one command handles at most one delivery in each fresh process',
            'schedule run uses explicit UTC five-minute slots and handles at most one delivery',
        ],
        'tests/job-worker-crash.php' => [
            'fwrite(STDOUT, "READY\\n")',
            'sleep(60);',
        ],
        'tools/setup-example.php' => [
            'new ApplicationComposition($applicationDatabasePath)',
            '->commands(new SystemUserWelcomeJobClock())',
            '->run(ApplicationCommandName::DatabaseMigrate);',
        ],
        'templates/application/.ai/jobs.md' => [
            '{{JOBS_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{JOBS_WORKER_LIFECYCLE_OR_NOT_APPLICABLE}}',
            'PHPThis provides no core queue or worker API.',
        ],
        'templates/application/.ai/testing.md' => [
            'exact retry delays from freshly observed failure time',
            'completion rollback when handler time reaches lease expiry',
        ],
        'skeleton/.ai/jobs.md' => [
            '`NOT_APPLICABLE(JOBS)`',
            'Never claim cross-connection atomicity or exactly-once external effects.',
        ],
        'skeleton/.ai/testing.md' => [
            '`NOT_APPLICABLE(JOBS_EVIDENCE)`',
            'completion rollback when handler time reaches lease expiry',
        ],
        'skeleton/.ai/operations.md' => [
            '## Durable-job runtime',
            'fresh one-delivery processes',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/024-application-owned-sqlite-durable-jobs.md',
            'docs/jobs/operations.md',
            'docs/jobs/schema.md',
            'templates/application/.ai/jobs.md',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledOneShotWorkerSupervisionGuidanceDistribution($project, $installedFramework);',
            'function proveInstalledOneShotWorkerSupervisionGuidanceDistribution(',
            'PASS installed one-shot worker supervision guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $durableJobArtifactMarkers, 'durable-job', $failures);

    $exampleSetup = file_get_contents($root . '/tools/setup-example.php');

    if (
        is_string($exampleSetup)
        && preg_match(
            '/\b(?:CREATE\s+(?:TABLE|INDEX|TRIGGER|VIEW)|ALTER\s+TABLE|DROP\s+(?:TABLE|INDEX|TRIGGER|VIEW)|REINDEX|VACUUM)\b/i',
            $exampleSetup,
        ) === 1
    ) {
        $failures[] = 'tools/setup-example.php must delegate schema DDL instead of duplicating it.';
    }

    foreach (['src/Jobs', 'src/Queue'] as $forbiddenCoreDirectory) {
        if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
            $failures[] = "Durable-job runtime must remain application-owned outside {$forbiddenCoreDirectory}.";
        }
    }

    $applicationChecker = file_get_contents($root . '/verification/ApplicationChecker.php');

    if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/jobs.md',")) {
        $failures[] = 'Contract version 9 must not checker-require the optional durable-job context file.';
    }

    $consumerProjectProof = file_get_contents($root . '/tools/test-consumer-project.php');

    if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveJobsContextIsRequired')) {
        $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/jobs.md is absent.';
    }

    $durableJobPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (
        is_string($durableJobPackageInventory)
        && preg_match('/^src\/(?:Jobs|Queue)\//m', $durableJobPackageInventory) === 1
    ) {
        $failures[] = 'Application-owned durable-job runtime must remain outside the framework package API.';
    }

    $applicationCliArtifactMarkers = [
        '.ai/README.md' => [
            '| Change an application command or scheduled pass | `.ai/cli.md` | console composition, one-pass operation, and real-console tests |',
        ],
        '.ai/application-context.md' => [
            '`NOT_APPLICABLE(CLI)`',
            'installed `vendor/phpthis/framework/docs/cli.md`',
            'framework-owned check',
        ],
        '.ai/cli.md' => [
            '# Application CLI and scheduler contract',
            'PHPThis provides no core CLI command, command map, argument parser, scheduler, lock, lease, daemon, or process manager.',
            'Reject an unknown command separately from invalid, duplicate, misplaced, oversized, or unsupported arguments before application I/O.',
            'HTTP and CLI may share immutable configuration and explicit application construction code',
            'application-private Redis lease',
        ],
        '.ai/testing.md' => [
            'execute its real console in fresh subprocesses',
            'explicit-clock cadence boundaries',
            'Do not mock a generic console or scheduler',
        ],
        'docs/cli.md' => [
            '# Application CLI and scheduler',
            'PHPThis accepts one application-owned operational console pattern and provides no core command or scheduler API.',
            'php example/bin/console.php <jobs:run-one|schedule:run|database:migrate> [--database=/absolute/path]',
            '`database:migrate` is the sole migration spelling in the accepted example.',
            'intdiv(epoch_seconds, 60) % 5 === 0',
            'SET key token NX PX 30000',
            '`Example\\ApplicationComposition`',
            '## Unsupported boundary',
        ],
        'docs/cli/README.md' => [
            '# Application CLI knowledge index',
            'Arguments and output',
            'Scheduling and coordination',
            'Composition',
            'Testing',
        ],
        'docs/cli/arguments-output.md' => [
            '# CLI arguments and output',
            'Unknown command and invalid, duplicate, reordered, alternate, or extra arguments fail before application I/O.',
            '`command`, then `outcome`',
        ],
        'docs/cli/composition.md' => [
            '# CLI composition',
            'HTTP and CLI share only immutable application configuration and visible construction code.',
            'not a container, service locator, registry, generic factory, framework extension point, or global',
        ],
        'docs/cli/scheduling-locking.md' => [
            '# CLI scheduling and coordination',
            'intdiv(epoch_seconds, 60) % 5 === 0',
            'Sequential invocations in the same due minute are not deduplicated',
        ],
        'docs/cli/testing.md' => [
            '# CLI testing',
            'For production adoption, execute the real application console in fresh subprocesses.',
            'The current example proof is intentionally bounded',
            'stale-owner renewal and release rejection',
        ],
        'docs/consumer-contract.md' => [
            '## Optional application-owned CLI and scheduler',
            'Contract-version-7-compatible optional application clarification, not a new checker requirement',
            'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
        ],
        'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md' => [
            'Status: accepted',
            'Consumer Contract version 5 and Strict Profile version 2 remain unchanged.',
            'PHPThis adds no core command, command interface, registry, argument parser, scheduler, clock, lock, daemon, process manager, service-container integration, or command discovery.',
            'intdiv(epoch_seconds, 60) % 5 === 0',
            'nonblocking exclusive `flock`',
            'No framework core, Consumer Contract version, Strict Profile version, diagnostic, checker rule, durable-job guarantee, or distributed-coordination claim changes.',
        ],
        'docs/decisions/README.md' => [
            '025-application-owned-explicit-cli-and-scheduler.md',
        ],
        'docs/knowledge-map.md' => [
            'Add or assess an operational application command or scheduled pass',
            '`docs/cli.md`',
            'no framework CLI, scheduler, lock, or lease API exists',
        ],
        'ROADMAP.md' => [
            'ADR 025 accepts one application-owned explicit console and cron-friendly scheduled pass',
            'ADR 028 accepts one Redis-specific application cache and schedule lease',
        ],
        'example/.ai/README.md' => [
            'Change an application command, argument, exit, stream, cadence, or overlap policy',
            '`bin/console.php`, `ApplicationComposition`, `src/Cli/`',
        ],
        'example/.ai/cli.md' => [
            '# Example application CLI and scheduler context',
            'php example/bin/console.php jobs:run-one [--database=/absolute/path]',
            'php example/bin/console.php schedule:run [--database=/absolute/path]',
            'php example/bin/console.php database:migrate [--database=/absolute/path]',
            'intdiv(epochSeconds, 60) % 5 === 0',
            'SET key token NX PX 30000',
            'No live Redis client, connection, budget, trace, request, session, correlation ID, or mutable clock is shared between HTTP and CLI',
        ],
        'example/src/Cli/README.md' => [
            '# Example application CLI source',
            'application-owned evidence for ADR 025 and ADR 028, not PHPThis core runtime code',
            '`example/bin/console.php` is the only operational entrypoint',
            'Do not add command discovery, dynamic class or service resolution, a second console, generic parser or scheduler facade, daemon, polling or renewal loop',
        ],
        'example/AGENTS.md' => [
            'Keep `bin/console.php` as the sole application operational console.',
            'Do not add another entrypoint, command discovery, a service container, scheduler facade, daemon, persistent slot ledger, catch-up, or generic distributed-coordination API.',
        ],
        'templates/application/.ai/cli.md' => [
            '{{CLI_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{CLI_CONSOLE_EXECUTABLE_OR_NOT_APPLICABLE}}',
            '{{CLI_COMMAND_PROFILE_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            '{{CLI_COMMAND_MAP_AND_BOUNDS_OR_NOT_APPLICABLE}}',
            '{{CLI_OVERLAP_POLICY_OR_NOT_APPLICABLE}}',
            'PHPThis provides no core application CLI or scheduler API',
        ],
        'skeleton/.ai/cli.md' => [
            '`NOT_APPLICABLE(CLI)`',
            'Keep framework `phpthis` dedicated to `check`.',
            'Do not add command discovery, class-name dispatch, a service-container resolver, generic console or scheduler facade, daemon, hidden loop, or distributed-coordination claim.',
        ],
        'tools/package-files.txt' => [
            'docs/cli.md',
            'docs/cli/testing.md',
            'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md',
            'templates/application/.ai/cli.md',
        ],
        'example/bootstrap.php' => [
            'new ApplicationComposition($databasePath)',
            '->http()',
        ],
        'example/src/ApplicationComposition.php' => [
            'final readonly class ApplicationComposition',
            'public function http(): TerminalRequestCoordinator',
            'public function commands(UserWelcomeJobClock $clock): ApplicationCommands',
            'return new ApplicationCommands(',
            '$this->databasePath,',
        ],
        'example/src/ApplicationDatabasePath.php' => [
            'strlen($value) > 4_096',
            "str_ends_with(\$value, '\\\\')",
            "preg_match('/[\\x00-\\x1F\\x7F]/', \$value)",
        ],
        'example/src/Cli/ApplicationCommandName.php' => [
            "case DatabaseMigrate = 'database:migrate';",
            "case JobsRunOne = 'jobs:run-one';",
            "case ScheduleRun = 'schedule:run';",
        ],
        'example/src/Cli/ApplicationCommandOutcome.php' => [
            "case Idle = 'idle';",
            "case Completed = 'completed';",
            "case RetryScheduled = 'retry_scheduled';",
            "case DeadLettered = 'dead_lettered';",
            "case NotDue = 'not_due';",
            "case OverlapSkipped = 'overlap_skipped';",
            "case Applied = 'applied';",
            "case UpToDate = 'up_to_date';",
        ],
        'example/src/Cli/ApplicationCommandLine.php' => [
            "str_starts_with(\$arguments[1], '--')",
            'ApplicationCommandName::tryFrom($arguments[1])',
            'count($arguments) > 3',
            "str_starts_with(\$submitted, '--database=')",
            'ApplicationDatabasePath::fromString($databasePath)',
        ],
        'example/src/Cli/ApplicationCommands.php' => [
            'return match ($command)',
            'ApplicationCommandName::DatabaseMigrate => new ApplicationCommandExecution(',
            'intdiv($this->clock->now(), 60)',
            '$currentMinute % 5 !== 0',
            'RedisScheduleRunLease::connect(',
            '$scheduleLease->acquire() === RedisScheduleRunLeaseAcquireOutcome::Contended',
            '$scheduleLease->renew() === RedisScheduleRunLeaseRenewOutcome::Lost',
            '$scheduleLease->release() === RedisScheduleRunLeaseReleaseOutcome::Lost',
            'private function runOneJob(?string $databasePath = null): ApplicationCommandOutcome',
            'private function runMigrations(): ApplicationCommandOutcome',
        ],
        'example/src/Coordination/RedisScheduleRunLease.php' => [
            "'NX'",
            "'PX' => self::LEASE_TTL_MILLISECONDS",
            'self::RENEW_SCRIPT',
            'self::RELEASE_SCRIPT',
            'MAXIMUM_RENEWALS = 4',
        ],
        'example/bin/console.php' => [
            'ApplicationCommandLine::fromArguments(',
            '->commands(new SystemUserWelcomeJobClock())',
            '"{\"error\":\"unknown_command\"}\n"',
            '"{\"error\":\"invalid_arguments\"}\n"',
            '"{\"error\":\"command_failed\"}\n"',
        ],
        'tests/cli.php' => [
            'application console rejects unknown commands before database work',
            'application console rejects every invalid argument shape before database work',
            'application command parser accepts exactly 4096 absolute path bytes',
            'application console reports missing databases as one redacted operational failure',
            'jobs run-one command handles at most one delivery in each fresh process',
            'schedule run uses explicit UTC five-minute slots and handles at most one delivery',
            'schedule run skips a subprocess-held Redis lease without blocking or delivering',
            'schedule run recovers after a lease-holder process dies and Redis expires ownership',
            'application composition keeps CLI execution outside fresh HTTP request state',
        ],
        'tests/redis-schedule-lease-holder.php' => [
            "fwrite(STDOUT, \"READY\\n\")",
            'RedisScheduleRunLease::connect(',
            'RedisScheduleRunLeaseAcquireOutcome::Acquired',
            'RedisScheduleRunLeaseRenewOutcome::Renewed',
            'RedisScheduleRunLeaseReleaseOutcome::Released',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $applicationCliArtifactMarkers, 'application CLI', $failures);

    if (is_file($root . '/example/bin/run-one-job.php')) {
        $failures[] = 'The superseded one-shot job entrypoint must not coexist with the explicit application command map.';
    }

    foreach (['src/Cli', 'src/Command', 'src/Commands', 'src/Scheduler'] as $forbiddenCoreDirectory) {
        if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
            $failures[] = "Application CLI and schedule runtime must remain outside framework core: {$forbiddenCoreDirectory}.";
        }
    }

    $applicationCliPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (
        is_string($applicationCliPackageInventory)
        && preg_match('/^src\/(?:Cli|Command|Commands|Scheduler)\//m', $applicationCliPackageInventory) === 1
    ) {
        $failures[] = 'Application CLI and schedule runtime must remain outside the framework package API.';
    }

    $frameworkEntrypoint = file_get_contents($root . '/bin/phpthis');

    if (is_string($frameworkEntrypoint)) {
        if (!str_contains($frameworkEntrypoint, 'Usage: phpthis check [--debug]')) {
            $failures[] = 'The framework entrypoint must retain its check-only usage contract.';
        }

        foreach (['jobs:run-one', 'schedule:run', 'database:migrate'] as $applicationCommand) {
            if (str_contains($frameworkEntrypoint, $applicationCommand)) {
                $failures[] = "The application command {$applicationCommand} must not enter bin/phpthis.";
            }
        }
    }

    $composerManifest = file_get_contents($root . '/composer.json');

    if (is_string($composerManifest) && str_contains($composerManifest, 'example/bin/console.php')) {
        $failures[] = 'The application console must not be exported as a framework Composer binary.';
    }

    if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/cli.md',")) {
        $failures[] = 'Contract version 9 must not checker-require the optional application CLI context file.';
    }

    if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveCliContextIsRequired')) {
        $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/cli.md is absent.';
    }

    $applicationCliSourceFiles = [
        'example/bin/console.php',
        'example/src/Cli/ApplicationCommandExecution.php',
        'example/src/Cli/ApplicationCommandLine.php',
        'example/src/Cli/ApplicationCommandName.php',
        'example/src/Cli/ApplicationCommandOutcome.php',
        'example/src/Cli/ApplicationCommands.php',
        'example/src/Cli/InvalidApplicationCommandArguments.php',
        'example/src/Cli/UnknownApplicationCommand.php',
        'example/src/Coordination/RedisScheduleRunLease.php',
        'example/src/Coordination/RedisScheduleRunLeaseTrace.php',
    ];
    $forbiddenApplicationCliMarkers = [
        'class_exists(',
        'get_declared_classes(',
        'glob(',
        'scandir(',
        'DirectoryIterator',
        'ReflectionClass',
        'ContainerInterface',
        'ServiceLocator',
        'sleep(',
        'usleep(',
    ];

    foreach ($applicationCliSourceFiles as $relativePath) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            continue;
        }

        foreach ($forbiddenApplicationCliMarkers as $marker) {
            if (str_contains($contents, $marker)) {
                $failures[] = "Application CLI source {$relativePath} contains forbidden discovery container or daemon marker {$marker}.";
            }
        }

        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && in_array($token[0], [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
                $failures[] = "Application CLI source {$relativePath} must remain one-shot without an in-process loop.";
                break;
            }
        }
    }

    $workbenchArtifactMarkers = [
        'docs/decisions/041-optional-development-workbench.md' => [
            'Status: accepted',
            'optional separate `phpthis/workbench` development package',
            'For each entered expression it starts a fresh `PHP_BINARY` child',
            'parent-process `ini_set()` changes and parent-launch `-d` options do not carry into it',
            'The generated child program is not a security boundary.',
            'an expression can target the parent or other processes and can leave external state changed',
            'Workbench provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation',
            'Direct deferred-work handler execution does not prove publication or queued delivery.',
            'existing adopted business operation',
            'This decision adds no framework-core PHP, runtime dependency, command, checker rule, `PHT` diagnostic',
        ],
        'docs/decisions/README.md' => [
            '`041-optional-development-workbench.md`',
        ],
        'docs/workbench.md' => [
            '# PHPThis Workbench',
            'separate `phpthis/workbench` Composer package',
            'returns exactly one concrete application-owned object',
            'Every expression is sent over standard input to a fresh `PHP_BINARY` child',
            'Composer\\\\Config::disableProcessTimeout',
            '`-d` options used to launch that parent are not inherited by the child',
            'Arbitrary PHP can still signal or terminate other processes and can leave filesystem, database, network, queue, or other external state changed.',
            'Workbench supplies no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
            'A non-returning or blocked expression prevents the next prompt until the child is externally interrupted or terminated.',
            'existing adopted business operation',
            'recorded finite tested one-delivery operational command',
            'Workbench supplies no `dispatch()`',
            'An entered expression is unchecked arbitrary PHP.',
            'Workbench output is exploratory evidence, not application validity evidence.',
        ],
        'docs/consumer-contract.md' => [
            '## Optional development Workbench',
            'Existing applications need not add `.ai/workbench.md` when they do not adopt the package',
            'This changes neither the carried-forward Workbench contract nor Strict Profile version 3',
        ],
        'docs/knowledge-map.md' => [
            '| Adopt, use, or review PHPThis Workbench |',
            'verify that no container, discovery, generic dispatch, second publisher, core runtime, batch, HTTP, remote, or production shell was introduced',
        ],
        'docs/cli.md' => [
            'The optional separate `phpthis/workbench` development package is an unchecked expression workspace',
            'ADR 041\'s separately installed Workbench does not change that boundary',
        ],
        'docs/jobs.md' => [
            '## Development exploration is not delivery',
            'A direct deferred-work handler call bypasses publication, stored-envelope parsing, claim order, lease and fencing',
            'existing adopted business operation',
            'recorded finite tested one-delivery console command',
        ],
        'docs/configuration.md' => [
            '## Workbench process authority',
            'parent runtime `ini_set()` changes and parent-launch `-d` options do not carry into the child',
            'An environment label, debug flag, local hostname, or `.env` filename is not an authority check.',
        ],
        'docs/security.md' => [
            '## Workbench limits',
            'signal or terminate the parent or another process',
            'Workbench also provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
            'Parent-process `ini_set()` changes and parent-launch `-d` restrictions do not carry into the child',
            'Workbench is not a sandbox, dry run, redactor, authorization layer, output bound, environment verifier, or production-safety control.',
        ],
        'docs/vocabulary.md' => [
            '| development Workbench |',
            '| Workbench workspace |',
        ],
        'docs/guardrails.md' => [
            'The Workbench guard retains only the accepted integration contract for the separately owned `phpthis/workbench` package.',
            'It keeps `.ai/workbench.md` optional under Consumer Contract version 11.',
        ],
        'VISION.md' => [
            'A human can inspect one explicitly composed development object or operation through a fresh strict process',
            'Providing a framework-owned production shell, container-backed console, administrative execution path, generic dispatcher, or remotely accessible Workbench.',
        ],
        'ROADMAP.md' => [
            'Complete: ADR 041 accepts PHPThis Workbench as a separate optional development-only package',
            'ADR 041 accepts only a separate development Workbench package',
        ],
        '.ai/README.md' => [
            '| Change the optional development Workbench | `.ai/workbench.md` | separate package, checked bootstrap, explicit workspace, and retained tests |',
        ],
        '.ai/application-context.md' => [
            'Include `.ai/workbench.md` in the current skeleton and template with `NOT_APPLICABLE(WORKBENCH)`',
            'Contract version 11 carries that optional file forward, and it is not a checker requirement.',
            'existing adopted business operation and transaction',
            'recorded finite tested console commands',
        ],
        '.ai/workbench.md' => [
            '# Optional development Workbench contract',
            'When the workspace exposes a real side effect, also read `docs/security.md` and `.ai/database.md`',
            '`skeleton/.ai/data.md`, `skeleton/.ai/integrations.md`, `skeleton/.ai/operations.md`',
            'one checked project-relative application bootstrap returns exactly one concrete final named object exposed as `$workspace`',
            'no execution timeout or CPU, memory, resource, or operating-system termination isolation',
            'operating-system identity, inherited environment, independently loaded child CLI configuration, ambient filesystem, network, process, and service access',
            'existing adopted business producer transaction',
            'no sandbox, redaction, dry-run, output-bound, production-safety, authorization, or validity claim',
        ],
        '.ai/testing.md' => [
            'An application that adopts ADR 041 Workbench keeps its bootstrap and concrete workspace type inside the ordinary application manifest and complete check.',
            'Entered expressions and displayed values remain unchecked exploratory evidence.',
        ],
        'templates/application/.ai/README.md' => [
            '| Change the development Workbench | `.ai/workbench.md` | approved package, checked bootstrap, explicit workspace, and retained tests |',
        ],
        'templates/application/.ai/workbench.md' => [
            '{{WORKBENCH_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_EXCLUDED_AUTHORITY_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_RESOURCE_LIMITS_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_SIDE_EFFECT_POLICY_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_JOB_PATH_OR_NOT_APPLICABLE}}',
            'Workbench is arbitrary development code, not a sandbox',
        ],
        'skeleton/.ai/README.md' => [
            '| Change the development Workbench | `.ai/workbench.md` | approved package, checked bootstrap, explicit workspace, and retained tests |',
        ],
        'skeleton/.ai/workbench.md' => [
            '`NOT_APPLICABLE(WORKBENCH)`',
            'dedicated development operating-system identity, inherited environment, independently loaded child CLI configuration',
            'absence of a Workbench execution timeout or CPU, memory, resource, and operating-system termination isolation',
            'existing adopted business producer transaction and the application-recorded finite one-delivery console command',
            'Install Workbench only through `require-dev`',
            'Production artifacts install with `--no-dev`',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/041-optional-development-workbench.md',
            'docs/workbench.md',
            'templates/application/.ai/workbench.md',
        ],
        'tools/test-consumer-project.php' => [
            '$installedWorkbenchGuidanceProof = proveInstalledWorkbenchGuidanceDistribution(',
            "if (\$installedWorkbenchGuidanceProof !== 'installed-workbench-guidance-proved')",
            "return 'installed-workbench-guidance-proved';",
            'The installed checker rejected a consumer only because .ai/workbench.md was absent.',
            'PASS installed Workbench guidance distribution',
            'without explicit application approval and verified Composer-source availability.',
        ],
        'tools/guardrails/operations.php' => [
            'function workbenchRuntimePathIsForbidden(string $relativePath): bool',
            "'src/Development/Workbench.php' => true,",
            "'src/Development/Workbenches/Runner.php' => true,",
            "'src/Console/InteractiveShell.php' => true,",
            "'src/Console/InteractiveShells.php' => true,",
            "'src/Development/ReplConsole.php' => true,",
            "'src/Console/Repls/Runner.php' => true,",
            "'src/Console/REPLs/Runner.php' => true,",
            "'src/Console/DevelopmentREPLs.php' => true,",
            "'src/Language/Replacement.php' => false,",
            "'src/Language/Replay.php' => false,",
        ],
    ];

    requireGuardrailArtifactMarkers($root, $workbenchArtifactMarkers, 'Workbench boundary', $failures);

    $workbenchRuntimePathFixtures = [
        'src/Development/Workbench.php' => true,
        'src/Development/Workbenches/Runner.php' => true,
        'src/Console/InteractiveShell.php' => true,
        'src/Console/InteractiveShells.php' => true,
        'src/Development/ReplConsole.php' => true,
        'src/Console/Repls/Runner.php' => true,
        'src/Console/REPLs/Runner.php' => true,
        'src/Console/DevelopmentREPLs.php' => true,
        'src/Tools/REPL/Runner.php' => true,
        'src/Language/Replacement.php' => false,
        'src/Language/Replay.php' => false,
        'src/Http/Reply.php' => false,
        'docs/workbench.md' => false,
    ];

    foreach ($workbenchRuntimePathFixtures as $fixturePath => $expectedForbidden) {
        if (workbenchRuntimePathIsForbidden($fixturePath) !== $expectedForbidden) {
            $failures[] = "Workbench runtime path guard fixture has drifted: {$fixturePath}.";
        }
    }

    $workbenchFrameworkSourceFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($workbenchFrameworkSourceFiles as $workbenchFrameworkSourceFile) {
        if (!$workbenchFrameworkSourceFile instanceof SplFileInfo || !$workbenchFrameworkSourceFile->isFile()) {
            continue;
        }

        $relativePath = substr($workbenchFrameworkSourceFile->getPathname(), strlen($root) + 1);

        if (workbenchRuntimePathIsForbidden($relativePath)) {
            $failures[] = "Workbench runtime must remain outside framework core: {$relativePath}.";
        }
    }

    $workbenchPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($workbenchPackageInventory)) {
        $workbenchPackagePaths = preg_split('/\R/', $workbenchPackageInventory);

        if (is_array($workbenchPackagePaths)) {
            foreach ($workbenchPackagePaths as $workbenchPackagePath) {
                if (workbenchRuntimePathIsForbidden($workbenchPackagePath)) {
                    $failures[] = "Workbench runtime must remain outside the framework package API: {$workbenchPackagePath}.";
                }
            }
        }
    }

    foreach (['composer.json', 'skeleton/composer.json'] as $workbenchDependencyManifest) {
        $contents = file_get_contents($root . '/' . $workbenchDependencyManifest);

        if (is_string($contents) && str_contains($contents, '"phpthis/workbench"')) {
            $failures[] = "Workbench must not enter {$workbenchDependencyManifest} without explicit application approval and verified Composer-source availability.";
        }
    }

    if (
        is_string($frameworkEntrypoint)
        && (str_contains($frameworkEntrypoint, 'workbench') || str_contains($frameworkEntrypoint, 'phpthis-workbench'))
    ) {
        $failures[] = 'The check-only framework entrypoint must not host Workbench.';
    }

    if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveWorkbenchContextIsRequired')) {
        $failures[] = 'Consumer Contract version 11 must not reject an existing consumer only because .ai/workbench.md is absent.';
    }

    $redisCoordinationArtifactMarkers = [
        '.ai/cache.md' => [
            'framework currently provides no generic cache API',
            'Do not cache credentials, session state, CSRF tokens, or authorization decisions.',
            'ADR 028',
        ],
        '.ai/cli.md' => [
            'one fresh owner token',
            '`SET NX PX` acquisition',
            'Do not add retry, waiting, a renewal loop, a fencing-token claim, or a generic distributed-lock API.',
        ],
        'docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md' => [
            'Status: accepted',
            'PHPThis accepts one application-owned Redis proof in the executable example and adds no framework cache, Redis, lock, or lease API.',
            'authentication, tenant resolution, and current authorization complete',
            'phpthis_example:<environment>:tenant:<account-id>:document_details:v1:<document-key>',
            'phpthis_example:<environment>:schedule_run:v1',
            'SET key token NX PX 30000',
            'not a monotonically increasing fencing token',
        ],
        'docs/redis-coordination.md' => [
            'A logical database number does not create the required separation.',
            'authenticate -> resolve tenant -> authorize -> cache read',
            'execute authoritative SQLite autocommit update -> invalidate exact Redis key',
            'The token is an ownership check, not a fencing token.',
        ],
        'docs/redis/topology.md' => [
            'noeviction',
            'A logical database number is not separation',
        ],
        'example/.ai/cache.md' => [
            'RedisDocumentDetailsCache',
            'RedisInvalidatingDocumentTitleUpdate',
            'The cache excludes not-found results, credentials, principals, memberships, permission data, denials, session state, secrets, and authorization decisions.',
        ],
        'example/src/ApplicationComposition.php' => [
            'new RedisDocumentDetailsCache(',
            "':schedule_run:v1'",
        ],
        'example/src/Documents/GetDocument/RedisDocumentDetailsCache.php' => [
            'implements RetrieveAuthorizedDocument',
            'MAXIMUM_PAYLOAD_BYTES = 1_024',
            'document_details:v1',
            'setOption(Redis::OPT_MAX_RETRIES, 0)',
            "['px' => \$this->ttlMilliseconds]",
        ],
        'example/src/Documents/UpdateDocumentTitle/RedisInvalidatingDocumentTitleUpdate.php' => [
            'UPDATE documents',
            'account_memberships.principal_id = :principal_id',
            '$this->cache->invalidate($accountId, $documentKey)',
        ],
        'example/src/Coordination/RedisScheduleRunLease.php' => [
            'LEASE_TTL_MILLISECONDS = 30_000',
            'CONNECT_TIMEOUT_SECONDS = 0.25',
            'READ_TIMEOUT_SECONDS = 0.25',
            'setOption(Redis::OPT_MAX_RETRIES, 0)',
            "'NX'",
            "'PX' => self::LEASE_TTL_MILLISECONDS",
            'self::RENEW_SCRIPT',
            'self::RELEASE_SCRIPT',
        ],
        'example/src/Observability/RequestSummary.php' => [
            "'schema_version' => self::SCHEMA_VERSION,",
            "'document_cache' => \$this->documentCache,",
        ],
        'tests/cache.php' => [
            'Redis proof uses distinct recorded cache and noeviction lease endpoints',
            "phpversion('redis')",
            "version_compare(\$clientVersion, '6.3.0', '<')",
            "version_compare(\$leaseInfo['redis_version'], '9.0.0', '>=')",
            'getOption(Redis::OPT_MAX_RETRIES) !== 0',
            'authorization denial performs no cache or protected source work',
            'Redis document cache preserves constant authoritative SQL on cold small and large fixtures',
            'Redis document cache bounds the accepted stale-refill race with finite TTL',
            'authoritative document update survives explicit invalidation outage',
        ],
        'tests/redis-coordination.php' => [
            'Redis schedule lease cannot renew or delete a successor lease',
            'Redis schedule lease preserves safe cleanup after an uncertain renewal',
            'Redis schedule lease bounds renewals and its structured outcome trace',
        ],
        'tests/cli.php' => [
            'schedule run skips a subprocess-held Redis lease without blocking or delivering',
            'schedule run recovers after a lease-holder process dies and Redis expires ownership',
        ],
        'tests/run.php' => [
            "require __DIR__ . '/cache.php';",
            "require __DIR__ . '/redis-coordination.php';",
            "frameworkBehaviorGroupDefinitions('cache', cacheTests())",
            "frameworkBehaviorGroupDefinitions('redis-coordination', redisCoordinationTests())",
        ],
        '.github/workflows/ci.yml' => [
            'redis-cache:',
            'redis-lease:',
            'extensions: pdo, pdo_sqlite, redis',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md',
            'docs/redis-coordination.md',
            'docs/redis/topology.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $redisCoordinationArtifactMarkers, 'Redis coordination', $failures);

    foreach (
        [
            'example/src/Documents/GetDocument/RedisDocumentDetailsCache.php',
            'example/src/Coordination/RedisScheduleRunLease.php',
        ] as $redisClientSource
    ) {
        $contents = file_get_contents($root . '/' . $redisClientSource);

        if (!is_string($contents)) {
            continue;
        }

        $connectPosition = strpos($contents, '->connect(');
        $retryOptionPosition = strpos($contents, 'setOption(Redis::OPT_MAX_RETRIES, 0)');

        if (
            $connectPosition === false
            || $retryOptionPosition === false
            || $retryOptionPosition <= $connectPosition
        ) {
            $failures[] = "Redis client {$redisClientSource} must disable phpredis retries after connect resets client options.";
        }
    }

    $leaseSource = file_get_contents(
        $root . '/example/src/Coordination/RedisScheduleRunLease.php',
    );

    if (
        is_string($leaseSource)
        && (
            substr_count($leaseSource, 'setOption(Redis::OPT_MAX_RETRIES, 0)') !== 2
            || substr_count(
                $leaseSource,
                'setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE)',
            ) !== 2
            || substr_count(
                $leaseSource,
                'setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE)',
            ) !== 2
            || substr_count($leaseSource, 'setOption(Redis::OPT_REPLY_LITERAL, false)') !== 2
        )
    ) {
        $failures[] = 'Every Redis schedule-lease construction path must normalize all correctness-sensitive client options.';
    }

    foreach (['src/Cache', 'src/Caching', 'src/Redis', 'src/Coordination', 'src/Lease', 'src/Lock'] as $forbiddenCoreDirectory) {
        if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
            $failures[] = "Redis cache and schedule-lease runtime must remain outside framework core: {$forbiddenCoreDirectory}.";
        }
    }

    $redisPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (
        is_string($redisPackageInventory)
        && preg_match('/^src\/(?:Cache|Caching|Redis|Coordination|Lease|Lock)\//m', $redisPackageInventory) === 1
    ) {
        $failures[] = 'Redis cache and schedule-lease runtime must remain outside the framework package API.';
    }

    if (is_string($composerManifest)) {
        $redisComposer = json_decode($composerManifest, true);

        if (!is_array($redisComposer)) {
            $failures[] = 'Cannot decode composer.json for the Redis evidence boundary.';
        } else {
            $runtimeRequirements = $redisComposer['require'] ?? null;
            $developmentRequirements = $redisComposer['require-dev'] ?? null;

            if (is_array($runtimeRequirements) && array_key_exists('ext-redis', $runtimeRequirements)) {
                $failures[] = 'The Redis extension must not become a framework runtime dependency.';
            }

            if (
                !is_array($developmentRequirements)
                || ($developmentRequirements['ext-redis'] ?? null) !== '^6.3'
            ) {
                $failures[] = 'Repository Redis evidence must declare the tested ext-redis ^6.3 development range.';
            }
        }
    }

    $engineSpecificMigrationInvariantArtifactMarkers = [
        '.ai/README.md' => [
            '| Change database migrations | `.ai/migrations.md` | command, configuration, authority, manifest, ledger, coordination, and exact-engine tests |',
        ],
        '.ai/application-context.md' => [
            'exact recorded initial baseline',
            'shared exclusion or pairwise authority gating across concurrently reachable topologies',
            '`.ai/operations.md` alone owns the application-wide release and cross-history recovery execution sequence',
            'typed-configuration/process-identity reference to `.ai/configuration.md`, database-authority reference to `.ai/data.md`',
            'exact creation/acquisition/use/release permissions',
            'Preserve ADR 027\'s explicit timestamp, per-migration transaction, same-host `flock`, active-transaction rollback, and earlier-commit proof only when the application deliberately adopts that SQLite reference shape.',
        ],
        '.ai/migrations.md' => [
            'ADR 043 defines engine-neutral application-owned invariants; ADR 027 remains one accepted SQLite-specific reference proof.',
            'For each history, record one exact initial baseline.',
            'every accepted present object, data assumption, ledger row, and checksum',
            'accepted-ledger-prefix validation, every pending checksum-covered statement',
            '`.ai/operations.md` alone owns the application-wide release sequence',
            'Record a finite exact-engine metadata acceptance policy for the ledger',
            'every code-owned binding name/type/literal value or finite binding-derivation policy',
            'exact creation, acquisition, use, and release permissions or authority',
            'Concurrently reachable topologies for the same history must share one exclusion domain or be pairwise authority-gated',
            'An expiring or lost owner is fenced from later mutations or confirmed terminated with no in-flight work before successor mutation.',
            'the next owner reacquires coordination and re-detects exact state before mutating',
            'cross-history partial-deployment evidence',
            'typed-configuration/process-identity reference to `.ai/configuration.md`, database-authority reference to `.ai/data.md`',
            'These are conditional SQLite/example and host-topology mechanics, not engine-neutral requirements.',
        ],
        'ROADMAP.md' => [
            'ADR 043 separates universal application-owned migration invariants from ADR 027\'s SQLite-only transaction, rollback, ledger-definition, and same-host `flock` proof',
            'ADR 043 accepts engine-neutral application-owned migration invariants, not a reusable ledger, coordinator, transaction, lock, or recovery implementation',
        ],
        'docs/cli.md' => [
            'ADR 043 separates its transaction, rollback, and same-host `flock` choices from the universal application-owned migration invariants.',
            'each command\'s configuration-profile and authority references',
            '`.ai/configuration.md` owns exact process identity and configuration, `.ai/data.md` owns effective database-authority facts and accountable transition ownership, and `.ai/migrations.md` owns each history\'s transition implementation and handoff constraints',
            'the ADR 027 SQLite proof additionally requires its empty-database case, nonblocking same-host `flock` contention, and per-migration rollback with earlier commits preserved.',
            'a migration-only console does not need a scheduler overlap lock or cadence policy.',
        ],
        'docs/cli/testing.md' => [
            'When a scheduled pass is adopted, use its explicit deterministic clock',
            'When a scheduled pass adopts the ADR 028 lease',
            'For an adopted migration command, prove the exact recorded initial baseline and manifest order',
            'statement and code-owned binding or finite binding-policy drift',
            'For the ADR 027 SQLite proof, additionally prove its empty-database case, immediate same-host `flock` contention with no database change, and per-migration rollback with earlier commits preserved',
        ],
        'docs/consumer-contract.md' => [
            'ADR 043 defines universal application-owned migration invariants',
            'engine-specific ledger-consistency boundary',
            'every code-owned binding name/type/literal value or complete finite binding-derivation policy',
            'ADR 027 remains the one executable SQLite reference proof.',
            'SQLite- and topology-specific choices, not universal migration requirements.',
            'PHPThis supplies no universal lock.',
            'Exact configuration and process identity remain authoritative in `.ai/configuration.md`',
        ],
        'docs/decisions/043-engine-specific-application-migration-invariants.md' => [
            '# ADR 043: Engine-specific application migration invariants',
            'On 2026-08-08 in Asia/Manila, the accountable human approved Issue #30',
            '### Universal application-owned invariants',
            'These invariants require ledger consistency, not one universal transaction shape.',
            'These invariants also require explicit concurrency decisions, not one universal lock.',
            'record the exact effective-authority overlap between migration and runtime',
            'including SQLite file-level authority limits',
            'additional fields are finite, non-executable, validated, and never select migration work, define order, or authorize behavior',
            'checksum-covered exact statement sequences plus every code-owned binding value or finite binding-derivation policy',
            'All writer topologies that can reach one history must participate in one shared exclusion domain or use explicit authority gating',
            'An expiring or losable mechanism is valid only when a successor cannot begin a mutation while an earlier owner\'s statement may still be executing',
            'Before implementing any adoption, the accountable human approves an application decision',
            '`.ai/configuration.md` owns exact configuration and process identity, `.ai/data.md` owns effective database-authority facts and accountable transition ownership, `.ai/migrations.md` owns the per-history migration constraints and transition implementation, and `.ai/operations.md` alone owns the application-wide sequence and operational runbooks.',
            '### SQLite reference proof',
            'Consumer Contract version 10 and Strict Profile version 3 remain unchanged.',
            'No framework migration API, schema builder, DSL, discovery rule, generic ledger or lock type, transaction callback, permission abstraction, automatic rollback, runtime SQL loading, HTTP-startup behavior, core change, contract-version change, or Strict Profile change is introduced.',
        ],
        'docs/decisions/README.md' => [
            '`043-engine-specific-application-migration-invariants.md`',
        ],
        'docs/getting-started.md' => [
            'one accepted engine-specific migration policy following ADR 043',
            'engine-specific ledger-consistency boundary and every non-atomic state',
            'record the application-wide sequence through exact-engine verification, rollout, traffic enablement, later deactivation, and namespace removal only in `.ai/operations.md`',
            'ADR 027\'s per-migration transaction, rollback, and same-host `flock` are required only when adopting its SQLite reference boundary',
        ],
        'docs/knowledge-map.md' => [
            'ADR 043, ADR 027 for the SQLite reference proof',
            '`.ai/configuration.md` for exact no-fallback process configuration and identity',
            '`.ai/data.md` for effective database-authority facts, accountable transition ownership',
            '`.ai/operations.md` for the application-wide release order and operational runbooks',
            'scope transaction, rollback, and lock claims to their proved engine and topology',
        ],
        'docs/migrations.md' => [
            '[universal application-owned migration invariants](decisions/043-engine-specific-application-migration-invariants.md)',
            'Ledger consistency is universal; one transaction shape is not.',
            'Concurrency coverage is universal; one lock is not.',
            'Additional fields are finite, non-executable, validated, and never select migration work, define order, or authorize behavior.',
            'every code-owned binding name, type, and literal value',
            'checksum the complete finite derivation policy and its input contract instead of the runtime result',
            'first pending migration may run',
            'explicitly accepted ledger prefix that the migration identity validates rather than re-executes',
            '`.ai/operations.md` alone owns the application-wide release sequence',
            '## Engine-specific ledger-consistency path',
            '### SQLite reference transaction',
            'Those are SQLite reference requirements, not substitutes for another engine\'s exact coordination and partial-failure evidence.',
            'exact creation, acquisition, use, and release permissions or authority',
        ],
        'docs/guardrails.md' => [
            'The engine-specific migration-invariant guard is a separate documentation boundary.',
            'retired exact unqualified wording on named universal guidance surfaces',
            'they do not reject broad words such as transaction, rollback, lock, or `flock` in ADR 027, the executable example, or its tests.',
            'A separate installed distribution proof checks ADR 043\'s engine-specific migration-invariant boundary',
            'checksums covering every statement, code-owned binding value or finite binding-derivation policy',
            'exact no-fallback configuration/process identity owned by `.ai/configuration.md`',
            'exact creation, acquisition, use, and release permissions or authority',
            'ADR 039\'s alternative migration-placement proof remains separate and unchanged.',
        ],
        'skeleton/.ai/README.md' => [
            '| Change database migrations | `.ai/migrations.md` | configuration, authority, manifest, ledger, operations, and exact-engine tests |',
        ],
        'skeleton/.ai/migrations.md' => [
            'each separately tracked history\'s exact initial baseline',
            'required position, identifier, and checksum',
            'code-owned binding name/type/literal value or finite binding-derivation policy',
            'every accepted present object, data assumption, ledger row, and checksum',
            'exact-baseline and accepted-ledger-prefix validation, every pending checksum-covered',
            'application-wide release sequence recorded only in `.ai/operations.md`',
            'shared exclusion across concurrently reachable topologies for one history or pairwise authority gating',
            'owner fencing or confirmed termination',
            'next owner to reacquire coordination and re-detect exact state before mutating',
            'disjoint managed objects, data, authority transitions, and coordination domains between separately tracked histories',
            'exact creation, acquisition, use, and release permissions or authority',
            'ADR 027 remains the accepted SQLite reference proof.',
            'Those mechanics and names are not another engine, topology, or application\'s defaults.',
        ],
        'skeleton/.ai/operations.md' => [
            'Record exact configuration and process identity only in `.ai/configuration.md`',
            'effective authority facts and accountable transition ownership only in `.ai/data.md`',
            'This file records only stable-history-keyed operational owners, mappings, runbooks, and evidence references',
            'it does not restate migration, configuration, identity, or authority policy',
        ],
        'skeleton/.ai/testing.md' => [
            'exact initial baseline',
            'every concurrently reachable topology pair',
            'migration-effect/ledger consistency at every failure boundary',
            'validate the accepted ledger prefix; prove every pending checksum-covered statement',
            'Multiple histories prove disjoint managed objects, data, authority transitions, and coordination domains before they are called independent',
            'When ADR 027\'s SQLite reference shape is adopted',
            'Do not generalize that SQLite transaction, file-lock, rollback, output, or filesystem-authority evidence to another engine or host topology.',
            'Migration evidence separately proves exact creation, acquisition, use, and release permissions or authority',
        ],
        'templates/application/.ai/README.md' => [
            '| Change database migrations | `.ai/migrations.md` | configuration, authority, manifest, ledger, operations, and exact-engine tests |',
        ],
        'templates/application/.ai/migrations.md' => [
            '{{MIGRATION_CONSOLE_EXECUTABLE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_HISTORY_STABLE_NAME_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_HISTORY_COMMAND_OR_NOT_APPLICABLE}}',
            '## Separately tracked history: `{{MIGRATION_HISTORY_STABLE_NAME_OR_NOT_APPLICABLE}}`',
            'copy this complete section once for every separately tracked history and replace every placeholder inside each copy',
            'Use one stable application-owned history name consistently',
            'Do not combine several histories in one field.',
            '## Shared migration rules',
            '{{MIGRATION_INITIAL_BASELINE_OR_NOT_APPLICABLE}}',
            'every accepted present object, data assumption, ledger row, and checksum',
            '{{MIGRATION_RELEASE_CONSTRAINTS_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_ATOMICITY_AND_LEDGER_CONSISTENCY_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_COORDINATION_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONFIGURATION_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_AUTHORITY_TRANSITION_IMPLEMENTATION_OR_NOT_APPLICABLE}}',
            'exact creation, acquisition, use, and release permissions or authority',
            '{{MIGRATION_CROSS_TOPOLOGY_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_COORDINATION_COVERAGE_POLICY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CROSS_HISTORY_POLICY_OR_NOT_APPLICABLE}}',
            'proved disjoint managed objects, data, authority transitions, and coordination domains',
            'Ledger requiring position, identifier, and checksum',
            'every code-owned binding name/type/literal value or finite binding-derivation policy',
            'any selected extra metadata, including a timestamp, has an explicit source, representation, and bound, is parsed and validated as non-executable data, and cannot select work, define order, or grant authority',
            'finite exact-engine accepted metadata and explicitly permitted supporting objects',
            'rejection of missing, incompatible, and additional unrecorded ledger-related objects',
            'next-owner reacquisition and exact-state redetection before mutation',
            'partial-failure detection, forward-correction, backup, restore, and recovery policy',
            'ADR 027 remains the accepted SQLite reference proof.',
            'Those mechanics and names are conditional SQLite/example policy, not portable defaults.',
        ],
        'templates/application/.ai/operations.md' => [
            '{{CLI_NON_MIGRATION_DEPLOYMENT_RUNNER_AND_INCIDENT_MAPPING_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_DEPLOYMENT_RUNNER_MAPPING_OR_NOT_APPLICABLE}}',
            'Exact initial baseline per stable history name: `.ai/migrations.md`; do not duplicate it here.',
            '{{MIGRATION_COORDINATION_RUNBOOK_AND_EVIDENCE_MAPPING_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_MAINTENANCE_CAPACITY_TERMINATION_AND_INCIDENT_MAPPING_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_RECOVERY_AND_CROSS_HISTORY_RUNBOOK_MAPPING_OR_NOT_APPLICABLE}}',
            'The bullets above record only stable-history-keyed operational owners, mappings, runbooks, and evidence references; they do not restate those policies.',
            'exact process identity and configuration remain authoritative in `.ai/configuration.md`, and effective authority facts plus accountable transition ownership remain authoritative in `.ai/data.md`',
            'the underlying per-history and shared-mechanism policy remains in `.ai/migrations.md`',
            'This guide owns the application-specific release sequence and operational runbooks',
        ],
        'templates/application/.ai/testing.md' => [
            'exact recorded initial baseline',
            'every concurrently reachable topology pair',
            'migration-effect/ledger consistency at every failure boundary',
            'validates the accepted ledger prefix; proves every pending checksum-covered statement',
            'Multiple histories prove disjoint managed objects, data, authority transitions, and coordination domains before they are called independent',
            'When ADR 027\'s SQLite reference shape is adopted',
            'Do not generalize that SQLite transaction, file-lock, rollback, output, or filesystem-authority evidence to another engine or host topology.',
            'exact creation, acquisition, use, and release permissions or authority',
        ],
        'skeleton/.ai/configuration.md' => [
            'one separately named factory, final readonly output type, and process identity for each adopted process profile',
            'each migration history records its own exact input names and never inherits, combines, or falls back',
        ],
        'skeleton/.ai/data.md' => [
            'each future history\'s source and namespace; exact initial baseline',
            'stable coordination namespace, collision, creation/acquisition/use/release permissions, reachable-topology exclusion, and lost-owner behavior',
            '`.ai/configuration.md` owns exact no-fallback configuration and process identity, this file owns effective authority facts and accountable transition ownership, and `.ai/operations.md` alone owns the application-wide release and cross-history recovery execution sequence',
        ],
        'templates/application/.ai/configuration.md' => [
            '{{ELEVATED_CONFIGURATION_FACTORIES_TYPES_IDENTITIES_AND_HISTORY_OWNERSHIP_OR_NOT_APPLICABLE}}',
            'Runtime, each migration history, and administrative profile, input-name, and credential separation with no inheritance, combined credentials, or fallback',
        ],
        'templates/application/.ai/data.md' => [
            '{{ELEVATED_PROFILE_1_HISTORY_OR_ADMIN_NAME_OR_NOT_APPLICABLE}}',
            'Record one separate row per adopted migration history',
            '{{ELEVATED_PROFILE_1_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}}',
            'capability isolation where supported or exact effective overlap and residual risk',
            'otherwise record the exact effective-authority overlap and residual risk, including SQLite file-level limits',
            '{{ELEVATED_PROFILE_1_AUTHORITY_TRANSITION_OWNER_AND_IMPLEMENTATION_REFERENCE_OR_NOT_APPLICABLE}}',
        ],
        'skeleton/.ai/cli.md' => [
            'When a scheduled pass is adopted, additionally record',
            'every adopted migration history has its own separately scoped references',
            'exact process identity, process-specific configuration factory, and final readonly type recorded in `.ai/configuration.md`',
            'A migration-only console records writer coordination or serialization in `.ai/migrations.md` under ADR 043.',
        ],
        'templates/application/.ai/cli.md' => [
            '{{CLI_CONSOLE_EXECUTABLE_OR_NOT_APPLICABLE}}',
            '{{CLI_COMMAND_PROFILE_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}',
            'Complete the clock, cadence, overlap, and supervisor fields only when a scheduled pass is adopted',
            'A migration-only console records writer coordination or serialization in `.ai/migrations.md` under ADR 043',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledEngineSpecificMigrationInvariantGuidanceDistribution(',
            'PASS installed engine-specific migration-invariant guidance distribution',
            'PASS installed migration alternative structure',
        ],
        'tools/package-files.txt' => [
            'docs/decisions/043-engine-specific-application-migration-invariants.md',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $engineSpecificMigrationInvariantArtifactMarkers,
        'engine-specific migration-invariant',
        $failures,
    );

    $retiredUnqualifiedMigrationRequirements = [
        '.ai/README.md' => [
            'bounded ledger, per-migration transactions, migration and authority-management capabilities',
        ],
        '.ai/application-context.md' => [
            'bounded ledger, per-migration transaction, exact elevated required and prohibited capabilities, authority-transition ownership, same-host lock',
        ],
        '.ai/migrations.md' => [
            'Execute each pending migration and its ledger insert in its own explicit transaction. Commit inside `try`; roll back in `finally` only when still active.',
            'Acquire one application-private nonblocking exclusive `flock` before database work and release it in `finally`.',
        ],
        'docs/consumer-contract.md' => [
            'bounded ledger, per-migration transactions, same-host lock topology',
            'Each migration and its ledger row commit through one explicit transaction.',
            'fresh separately authorized state and one application-private nonblocking same-host `flock`.',
        ],
        'docs/getting-started.md' => [
            'bounded ledger, per-migration transactions, lock topology, immutable forward recovery',
        ],
        'docs/knowledge-map.md' => [
            'bounded ledger, per-migration transactions, migration and authority-management capabilities',
        ],
        'docs/migrations.md' => [
            '## Explicit transaction path',
            'Acquire the application-private nonblocking migration lock before database work. After bounded ledger bootstrap and complete history validation, execute each pending migration through its own visible transaction:',
        ],
        'skeleton/AGENTS.md' => [
            'bounded ledger, per-migration transaction, same-host lock, immutable forward recovery',
        ],
        'skeleton/.ai/migrations.md' => [
            'one explicit transaction per migration and ledger insert, immutable history, forward correction, and backup or restore policy',
            'one application-private nonblocking same-host lock path, permissions, filesystem topology, contention, and failure policy',
        ],
        'skeleton/.ai/testing.md' => [
            'nonblocking same-host lock contention with no state change, per-migration rollback with earlier commits preserved',
        ],
        'templates/application/AGENTS.md' => [
            'bounded ledger, per-migration transactions, same-host lock topology, immutable forward recovery',
        ],
        'templates/application/.ai/migrations.md' => [
            'Commit each pending migration and its ledger row in one visible transaction.',
            'fresh separately authorized state and one application-private nonblocking same-host lock',
        ],
        'templates/application/.ai/testing.md' => [
            'nonblocking same-host lock contention with no database state change, per-migration rollback with earlier commits preserved',
        ],
    ];

    foreach ($retiredUnqualifiedMigrationRequirements as $relativePath => $markers) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            continue;
        }

        foreach ($markers as $marker) {
            if (str_contains($contents, $marker)) {
                $failures[] = "Universal migration guidance {$relativePath} retains retired unqualified SQLite wording: {$marker}.";
            }
        }
    }

    $migrationArtifactMarkers = [
        '.ai/README.md' => [
            '| Change database migrations | `.ai/migrations.md` | command, configuration, authority, manifest, ledger, coordination, and exact-engine tests |',
        ],
        '.ai/application-context.md' => [
            '`NOT_APPLICABLE(MIGRATIONS)`',
            'Contract version 9 does not make that additional file a checker requirement',
            'Recommend `src/Database/Migrations/` with the matching application namespace',
            'preserve any coherent application-owned alternative',
            'multiple named database connections adopt separately tracked migration histories',
        ],
        '.ai/migrations.md' => [
            '# Migration authoring contract',
            'PHPThis provides no core migration API.',
            'recommend `src/Database/Migrations/` with a matching namespace',
            'Accept any coherent application-owned alternative.',
            'A relocation is an application architecture change requiring explicit human approval.',
            'multiple named database connections own separately tracked migration histories',
            'do not prescribe or scaffold speculative subdivisions for a single-database application',
            'never run any migration command during HTTP startup or through framework `bin/phpthis`.',
            'Do not scan files, discover classes, resolve strings, or load runtime `.sql` files.',
            'Never call a database method in a loop',
        ],
        '.ai/testing.md' => [
            'An application that adopts ADR 027 migrations must execute the real console in fresh subprocesses',
            'zero migration work during HTTP startup',
        ],
        'docs/migrations.md' => [
            '# Explicit application migrations',
            'PHPThis defines [universal application-owned migration invariants]',
            '## Recommended application structure',
            'src/',
            'Database/',
            'Migrations/',
            'record the actual path and namespace in `.ai/migrations.md`',
            'A consumer may choose any coherent alternative.',
            'does not enforce a path through the checker or Strict Profile',
            'A database-free skeleton creates no empty migration directory.',
            'PHPThis recommends no subdivision spelling',
            'connection without its own migration history',
            'does not recommend a generic `Database/Queries` directory, repository, query-object layer, or alternate SQL execution boundary',
            'PHPStan must resolve every direct SQL argument to finite non-blank compile-time constants.',
            'The manifest cap is 512 and the bounded ledger query uses `LIMIT 513`.',
            '`0007_create_account_users`',
            '23-statement budget and trace',
            'Do not expose migration configuration through HTTP or compose the coordinator during request startup.',
        ],
        'docs/database.md' => [
            'Migrations are specialized application-owned database evolution.',
            'multiple named database connections adopt separately tracked migration histories',
            'creates no speculative connection directories for a single-database application',
        ],
        'docs/decisions/027-application-owned-explicit-sqlite-migrations.md' => [
            'Status: accepted',
            'Consumer Contract version 6, Strict Profile version 2, and the 2,500-line core ceiling remain unchanged.',
            'final `Example\\Migrations\\SqliteApplicationMigrations`',
            '`0001_create_user_schema`',
            '`0006_create_document_access_schema`',
            '`application_migrations`',
            '`LIMIT 513`',
            '21-statement budget and trace',
            'mode `0600`',
            'No framework migration API, schema abstraction, reusable runner, discovery rule, core change, Consumer Contract version, Strict Profile version, or cross-engine claim is introduced.',
            'This record preserves the original `Example\\Migrations` name as historical evidence',
            'the current example was subsequently moved to `Example\\Database\\Migrations`',
        ],
        'docs/decisions/039-recommended-database-migration-structure.md' => [
            'Status: accepted',
            'Migrations are specialized application-owned database evolution.',
            'src/',
            'Database/',
            'Migrations/',
            'A consumer may instead record any coherent application-owned path and namespace.',
            'does not reject an alternative, enforce this directory through the checker or Strict Profile',
            'must preserve the current structure unless an accountable human explicitly approves',
            'The database-free skeleton does not create an empty migration directory.',
            'multiple named database connections own separately tracked migration histories',
            'histories are called independent only after their managed objects, data, authority transitions, and coordination domains are proved disjoint',
            'does not create speculative connection directories for a single-database application',
            'does not establish a generic database layer',
            'Consumer Contract version 10 and Strict Profile version 3 remain unchanged',
        ],
        'docs/consumer-contract.md' => [
            '## Optional application-owned database migrations',
            'Contract version 9 does not make that additional file a checker requirement',
            'ADR 039 recommends `src/Database/Migrations/`',
            'A coherent consumer-selected alternative remains valid',
            'does not enforce migration placement through the checker or Strict Profile',
            'no empty migration directory',
            'explicit connection-owned subdivision for each adopted history',
            'Do not combine their credentials or invent connection subdivisions for a single-database application',
            'The command never runs from the front controller, request composition, HTTP startup, framework `vendor/bin/phpthis`, command discovery, or dependency hooks.',
        ],
        'docs/vocabulary.md' => [
            'recommended migration placement',
            'connection-owned migration subdivision',
            'speculative single-database directory',
        ],
        'docs/decisions/README.md' => [
            '027-application-owned-explicit-sqlite-migrations.md',
            '039-recommended-database-migration-structure.md',
        ],
        'docs/knowledge-map.md' => [
            'Add, place, apply, explain, or recover a database migration',
            '`docs/migrations.md`, `docs/database.md`, `docs/security.md`',
            'connection-owned subdivision only for a named connection with a separately tracked migration history',
        ],
        'docs/guardrails.md' => [
            "ADR 039's migration-structure recommendation",
            'exact seven-file `example/src/Database/Migrations/` source set and namespace',
            'Multiple named connections may receive application-selected connection-owned subdivisions only when they adopt separately tracked migration histories',
            'Composer-autoload and installed-checker proof using the alternative `src/Infrastructure/ChangeHistory/` source and `App\\Infrastructure\\ChangeHistory` namespace',
            'installed-consumer proof separately runs the canonical checker with a coherent nonrecommended source directory and matching namespace',
            'places one valid final class there, proves Composer can autoload it, and requires the installed canonical checker to pass',
        ],
        'ROADMAP.md' => [
            'ADR 027 accepts one application-owned SQLite migration ledger',
            'not a core schema API, migration discovery, down-migration engine, HTTP bootstrap behavior, or portable DDL contract',
        ],
        'example/.ai/README.md' => [
            'Change database schema migrations, migration history, migration placement, or migration recovery',
            '`bin/console.php`, `ApplicationComposition`, `src/Database/Migrations/`',
        ],
        'example/.ai/migrations.md' => [
            '# Example SQLite migration context',
            '`Example\\Database\\Migrations\\SqliteApplicationMigrations` coordinator',
            'application-relative source directory `src/Database/Migrations/` (repository path `example/src/Database/Migrations/`)',
            'not framework discovery or checker-enforced placement',
            'The manifest cap is 512 migrations and the ordered position/identifier/checksum history read uses `LIMIT 513`',
            '`0007_create_account_users`',
            'QueryBudget(23)',
            'The migration lock path is the canonical database path plus `.migration.lock`.',
            '`tools/setup-example.php` delegates schema work to this exact coordinator',
        ],
        'example/AGENTS.md' => [
            'Keep `database:migrate` as the sole application migration command',
            '`tools/setup-example.php` may delegate to that exact coordinator before seeding; it must not duplicate schema SQL.',
        ],
        'templates/application/.ai/migrations.md' => [
            '{{MIGRATION_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_SOURCE_DIRECTORY_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_APPLICATION_NAMESPACE_OR_NOT_APPLICABLE}}',
            '{{MIGRATION_CONNECTION_OWNERSHIP_OR_NOT_APPLICABLE}}',
            'PHPThis recommends `src/Database/Migrations/`',
            'A coherent consumer-selected alternative is authoritative',
            'connection without a separately tracked migration history',
            '{{MIGRATION_MANIFEST_SOURCE_OR_NOT_APPLICABLE}}',
            'no database call occurs in a loop',
            'Every adoption requires separate engine/version transaction and DDL atomicity limits, ledger-consistency design, coordination, privilege, recovery, and integration evidence.',
        ],
        'skeleton/.ai/migrations.md' => [
            '`NOT_APPLICABLE(MIGRATIONS)`',
            'No migration directory, code, or dependency is included',
            'PHPThis recommends `src/Database/Migrations/`',
            '`App\\Database\\Migrations` namespace',
            'A coherent consumer-selected alternative is authoritative',
            'multiple named database connections later adopt separately tracked migration histories',
            'do not pre-create or prescribe connection subdivisions',
            'HTTP startup performs no data-definition or authority-transition work.',
            'runtime `.sql` loading',
        ],
        'example/src/Database/Migrations/ApplicationMigrationFailureReason.php' => [
            "case Busy = 'busy';",
            "case ChecksumDrift = 'checksum_drift';",
            "case HistoryInvalid = 'history_invalid';",
            "case LedgerUnavailable = 'ledger_unavailable';",
            "case ApplyFailed = 'apply_failed';",
            "case LockFailed = 'lock_failed';",
        ],
        'example/src/Database/Migrations/ApplicationMigrationOutcome.php' => [
            "case Applied = 'applied';",
            "case UpToDate = 'up_to_date';",
        ],
        'example/src/Database/Migrations/ApplicationMigrationFailed.php' => [
            "'error' => 'migration_failed'",
            "'reason' => \$this->reason->value",
            "'migration' => \$this->migrationIdentifier",
        ],
        'example/src/Database/Migrations/LocalMigrationLock.php' => [
            "fopen(\$this->path, 'c+b')",
            '@chmod($this->path, 0600)',
            '@fstat($handle)',
            '@lstat($this->path)',
            "\$handleStatus['nlink'] === 1",
            "\$handleStatus['ino'] === \$pathStatus['ino']",
            'flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)',
            'flock($handle, LOCK_UN)',
        ],
        'example/src/Database/Migrations/MigrationHistory.php' => [
            'count($rows) > 512',
            "array_keys(\$row) !== ['position', 'migration_id', 'checksum_sha256']",
            "ApplicationMigrationFailureReason::ChecksumDrift",
        ],
        'example/src/Database/Migrations/SqliteMigrationLedger.php' => [
            'CREATE TABLE application_migrations',
            'position INTEGER PRIMARY KEY',
            'migration_id TEXT NOT NULL UNIQUE',
            'checksum_sha256 TEXT NOT NULL',
            'applied_at_epoch INTEGER NOT NULL',
            'sqlite_autoindex_application_migrations_1',
            'ORDER BY sqlite_master.type ASC, sqlite_master.name ASC',
            'LIMIT 513',
            'unixepoch()',
        ],
        'example/src/Database/Migrations/SqliteApplicationMigrations.php' => [
            'final readonly class SqliteApplicationMigrations',
            'private const int QUERY_LIMIT = 23;',
            "private const string USER_SCHEMA_IDENTIFIER = '0001_create_user_schema';",
            "private const string JOB_SCHEMA_IDENTIFIER = '0002_create_job_schema';",
            "private const string PREPARE_DOCUMENT_IDENTIFIER = '0003_prepare_document_schema';",
            "private const string DOCUMENT_CATEGORY_IDENTIFIER = '0004_add_document_category';",
            "private const string DOCUMENT_SORT_RANK_IDENTIFIER = '0005_add_document_sort_rank';",
            "private const string DOCUMENT_ACCESS_IDENTIFIER = '0006_create_document_access_schema';",
            "private const string ACCOUNT_USERS_IDENTIFIER = '0007_create_account_users';",
            'private const string CREATE_ACCOUNT_USERS_SQL',
            'new QueryBudget(self::QUERY_LIMIT)',
            'new QueryTrace(self::QUERY_LIMIT)',
            'options: [PDO::ATTR_TIMEOUT => 5]',
            '$connection->beginTransaction();',
            '$ledger->record(',
            '$connection->commit();',
        ],
        'example/src/Cli/ApplicationCommands.php' => [
            'new SqliteApplicationMigrations(',
            "new LocalMigrationLock(\$databasePath . '.migration.lock')",
            'ApplicationMigrationOutcome::Applied => ApplicationCommandOutcome::Applied',
            'ApplicationMigrationOutcome::UpToDate => ApplicationCommandOutcome::UpToDate',
        ],
        'example/bin/console.php' => [
            'catch (ApplicationMigrationFailed $exception)',
            'fwrite(STDERR, $exception->stderrLine());',
        ],
        'tests/run.php' => [
            "require __DIR__ . '/migrations.php';",
            "frameworkBehaviorGroupDefinitions('migrations', migrationTests())",
        ],
        'tests/migrations.php' => [
            'database migrate applies an ordered inspectable ledger and reruns as a no-op',
            'database migrate adds account users without conflating principal identities',
            'database migrate rejects checksum drift before pending migration work',
            'database migrate rejects an incompatible preexisting ledger schema',
            'database migrate reports exact redacted ledger and lock failures',
            'migration history rejects malformed and oversized database snapshots',
            'database migrate preserves earlier commits across a later migration failure',
            'database migrate refuses to infer a baseline for an unledgered existing schema',
            'database migrate fails fast under a subprocess-held migration lock',
            'HTTP startup must not create the database or migration ledger.',
        ],
        'tests/cli-migration-lock-holder.php' => [
            "\$databasePath . '.migration.lock'",
            'chmod($lockPath, 0600)',
            'flock($handle, LOCK_EX | LOCK_NB)',
            'fwrite(STDOUT, "READY\\n")',
        ],
        'tools/setup-example.php' => [
            '->run(ApplicationCommandName::DatabaseMigrate);',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledMigrationStructureGuidanceDistribution(',
            "\$project . '/src/Infrastructure/ChangeHistory'",
            "\$alternativeDirectory . '/ApplicationMigrations.php'",
            'The consumer-selected migration path and namespace are not Composer-autoload coherent.',
            'The installed checker rejected a coherent consumer-selected migration structure.',
            'PASS installed migration alternative structure',
            'PASS installed migration structure guidance distribution',
            'The database-free installed skeleton unexpectedly contains a migration directory.',
        ],
        'tools/package-files.txt' => [
            'docs/migrations.md',
            'docs/decisions/027-application-owned-explicit-sqlite-migrations.md',
            'docs/decisions/039-recommended-database-migration-structure.md',
            'docs/decisions/043-engine-specific-application-migration-invariants.md',
            'templates/application/.ai/migrations.md',
        ],
    ];

    requireGuardrailArtifactMarkers($root, $migrationArtifactMarkers, 'migration', $failures);

    $recommendedExampleMigrationDirectory = $root . '/example/src/Database/Migrations';
    $legacyExampleMigrationDirectory = $root . '/example/src/Migrations';
    $expectedExampleMigrationFiles = [
        'ApplicationMigrationFailed.php',
        'ApplicationMigrationFailureReason.php',
        'ApplicationMigrationOutcome.php',
        'LocalMigrationLock.php',
        'MigrationHistory.php',
        'SqliteApplicationMigrations.php',
        'SqliteMigrationLedger.php',
    ];

    if (is_dir($legacyExampleMigrationDirectory)) {
        $failures[] = 'The maintained example must use the recommended src/Database/Migrations structure.';
    }

    if (!is_dir($recommendedExampleMigrationDirectory)) {
        $failures[] = 'The maintained example migration directory is missing: example/src/Database/Migrations.';
    } else {
        $actualExampleMigrationFiles = [];

        foreach (new DirectoryIterator($recommendedExampleMigrationDirectory) as $migrationEntry) {
            if ($migrationEntry->isDot()) {
                continue;
            }

            if (!$migrationEntry->isFile()) {
                $failures[] = 'The maintained example migration directory must contain only the reviewed PHP files.';
                continue;
            }

            $actualExampleMigrationFiles[] = $migrationEntry->getFilename();
            $migrationContents = file_get_contents($migrationEntry->getPathname());

            if (
                !is_string($migrationContents)
                || !str_contains($migrationContents, 'namespace Example\\Database\\Migrations;')
            ) {
                $failures[] = "Maintained example migration {$migrationEntry->getFilename()} must use the Example\\Database\\Migrations namespace.";
            }
        }

        sort($actualExampleMigrationFiles);
        sort($expectedExampleMigrationFiles);

        if ($actualExampleMigrationFiles !== $expectedExampleMigrationFiles) {
            $failures[] = 'The maintained example migration file set changed without review.';
        }
    }

    foreach (
        ['src/Migration', 'src/Migrations', 'src/Schema', 'src/SchemaBuilder']
        as $forbiddenCoreDirectory
    ) {
        if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
            $failures[] = "Migration runtime must remain application-owned outside framework core: {$forbiddenCoreDirectory}.";
        }
    }

    $databaseCoreDirectory = $root . '/src/Database';

    if (is_dir($databaseCoreDirectory)) {
        foreach (new DirectoryIterator($databaseCoreDirectory) as $databaseCoreEntry) {
            if ($databaseCoreEntry->isDot()) {
                continue;
            }

            if (
                str_starts_with($databaseCoreEntry->getFilename(), 'Migration')
                || str_starts_with($databaseCoreEntry->getFilename(), 'Schema')
            ) {
                $failures[] = 'Migration or schema-building runtime must not enter src/Database.';
            }
        }
    }

    $migrationPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($migrationPackageInventory)) {
        foreach (
            [
                '/^src\/(?:Migration|Migrations|Schema|SchemaBuilder)(?:\/|\.php$)/m',
                '/^src\/Database\/(?:Migration|Schema)/m',
                '/^\.ai\/migrations\.md$/m',
                '/^example\/src\/(?:Database\/)?Migrations\//m',
                '/^skeleton\/\.ai\/migrations\.md$/m',
                '/^tests\/(?:migrations|cli-migration-lock-holder)\.php$/m',
            ] as $forbiddenMigrationPackagePattern
        ) {
            if (preg_match($forbiddenMigrationPackagePattern, $migrationPackageInventory) === 1) {
                $failures[] = 'Application-owned migration runtime and evidence must remain outside the framework package inventory.';
            }
        }
    }

    if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/migrations.md',")) {
        $failures[] = 'Contract version 9 must not checker-require the optional migration context file.';
    }

    if (
        is_string($applicationChecker)
        && (
            str_contains($applicationChecker, 'src/Database/Migrations')
            || str_contains($applicationChecker, 'src/Migrations')
        )
    ) {
        $failures[] = 'The consumer checker must not enforce a migration source directory.';
    }

    if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveMigrationsContextIsRequired')) {
        $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/migrations.md is absent.';
    }

    foreach (['skeleton/src/Database/Migrations', 'skeleton/src/Migrations'] as $emptySkeletonMigrationPath) {
        if (is_dir($root . '/' . $emptySkeletonMigrationPath)) {
            $failures[] = "The database-free skeleton must not create an empty migration directory: {$emptySkeletonMigrationPath}.";
        }
    }

    $runtimeSqlRoots = ['src', 'example', 'skeleton', 'templates/application', 'tools'];

    foreach ($runtimeSqlRoots as $runtimeSqlRoot) {
        $runtimeSqlPath = $root . '/' . $runtimeSqlRoot;

        if (!is_dir($runtimeSqlPath)) {
            continue;
        }

        $runtimeSqlFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($runtimeSqlPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($runtimeSqlFiles as $runtimeSqlFile) {
            if (
                $runtimeSqlFile instanceof SplFileInfo
                && $runtimeSqlFile->isFile()
                && strtolower($runtimeSqlFile->getExtension()) === 'sql'
            ) {
                $relativeSqlPath = substr($runtimeSqlFile->getPathname(), strlen($root) + 1);
                $failures[] = "Runtime .sql files are forbidden; keep direct finite SQL in PHP source: {$relativeSqlPath}.";
            }
        }
    }

    $migrationRuntimeSourceFiles = ['example/src/Cli/ApplicationCommands.php'];
    $migrationRuntimeDirectory = $root . '/example/src/Database/Migrations';
    $migrationRuntimeFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($migrationRuntimeDirectory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($migrationRuntimeFiles as $migrationRuntimeFile) {
        if (
            $migrationRuntimeFile instanceof SplFileInfo
            && $migrationRuntimeFile->isFile()
            && strtolower($migrationRuntimeFile->getExtension()) === 'php'
        ) {
            $migrationRuntimeSourceFiles[] = substr(
                $migrationRuntimeFile->getPathname(),
                strlen($root) + 1,
            );
        }
    }

    sort($migrationRuntimeSourceFiles);
    $forbiddenMigrationDiscoveryMarkers = [
        'class_exists(',
        'get_declared_classes(',
        'get_declared_interfaces(',
        'get_declared_traits(',
        'glob(',
        'scandir(',
        'DirectoryIterator',
        'FilesystemIterator',
        'RecursiveDirectoryIterator',
        'RecursiveIteratorIterator',
        'ReflectionClass',
        'ReflectionFunction',
        'ReflectionMethod',
        'SplFileObject',
    ];
    $forbiddenMigrationFileFunctions = [
        'file',
        'file_get_contents',
        'fgets',
        'fread',
        'parse_ini_file',
        'readfile',
        'stream_get_line',
        'stream_get_contents',
    ];
    $forbiddenMigrationAbstractions = [
        'MigrationInterface',
        'MigrationRegistry',
        'QueryBuilder',
        'SchemaBuilder',
        'TransactionCallback',
        'bindParam(',
        'bindValue(',
    ];

    foreach ($migrationRuntimeSourceFiles as $relativePath) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            continue;
        }

        foreach (array_merge($forbiddenMigrationDiscoveryMarkers, $forbiddenMigrationAbstractions) as $marker) {
            if (str_contains($contents, $marker)) {
                $failures[] = "Migration runtime source {$relativePath} contains forbidden discovery loading or abstraction marker {$marker}.";
            }
        }

        foreach (token_get_all($contents) as $token) {
            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                $failures[] = "Migration runtime source {$relativePath} must not load executable source at runtime.";
                continue;
            }

            if (!in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
                continue;
            }

            $functionName = strtolower(ltrim($token[1], '\\'));
            $separator = strrpos($functionName, '\\');

            if ($separator !== false) {
                $functionName = substr($functionName, $separator + 1);
            }

            if (
                in_array($functionName, $forbiddenMigrationFileFunctions, true)
                || ($functionName === 'fopen' && $relativePath !== 'example/src/Database/Migrations/LocalMigrationLock.php')
            ) {
                $failures[] = "Migration runtime source {$relativePath} must not load migration SQL or source from runtime files.";
            }
        }
    }

    $migrationCoordinator = file_get_contents(
        $root . '/example/src/Database/Migrations/SqliteApplicationMigrations.php',
    );

    if (is_string($migrationCoordinator)) {
        foreach (
            [
                'if (!$history->contains(' => 7,
                '$connection->beginTransaction();' => 7,
                '$ledger->record(' => 7,
                '$connection->commit();' => 7,
                '$connection->rollBack();' => 7,
            ] as $migrationCoordinatorMarker => $expectedCount
        ) {
            if (substr_count($migrationCoordinator, $migrationCoordinatorMarker) !== $expectedCount) {
                $failures[] = sprintf(
                    'SqliteApplicationMigrations marker %s must occur exactly %d times.',
                    $migrationCoordinatorMarker,
                    $expectedCount,
                );
            }
        }

        foreach (token_get_all($migrationCoordinator) as $token) {
            if (is_array($token) && in_array($token[0], [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
                $failures[] = 'SqliteApplicationMigrations must keep every migration and database call explicitly unrolled.';
                break;
            }
        }

        $migrationSqlOrderMarkers = [
            <<<'PHP'
                $connection->executeStatement(self::CREATE_USERS_SQL);
                $connection->executeStatement(self::CREATE_USER_EVENTS_SQL);
                $connection->executeStatement(self::CREATE_USER_EVENTS_INDEX_SQL);
                PHP,
            <<<'PHP'
                self::USER_SCHEMA_IDENTIFIER . "\0"
                    . self::CREATE_USERS_SQL . "\0"
                    . self::CREATE_USER_EVENTS_SQL . "\0"
                    . self::CREATE_USER_EVENTS_INDEX_SQL,
                PHP,
            <<<'PHP'
                $connection->executeStatement(self::CREATE_APPLICATION_JOBS_SQL);
                $connection->executeStatement(self::CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL);
                $connection->executeStatement(self::CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL);
                $connection->executeStatement(self::CREATE_WELCOME_DELIVERIES_SQL);
                PHP,
            <<<'PHP'
                self::JOB_SCHEMA_IDENTIFIER . "\0"
                    . self::CREATE_APPLICATION_JOBS_SQL . "\0"
                    . self::CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL . "\0"
                    . self::CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL . "\0"
                    . self::CREATE_WELCOME_DELIVERIES_SQL,
                PHP,
            '$connection->executeStatement(self::CREATE_DOCUMENTS_SQL);',
            <<<'PHP'
                self::PREPARE_DOCUMENT_IDENTIFIER . "\0"
                    . self::CREATE_DOCUMENTS_SQL,
                PHP,
            '$connection->executeStatement(self::ADD_DOCUMENT_CATEGORY_SQL);',
            <<<'PHP'
                self::DOCUMENT_CATEGORY_IDENTIFIER . "\0"
                    . self::ADD_DOCUMENT_CATEGORY_SQL,
                PHP,
            '$connection->executeStatement(self::ADD_DOCUMENT_SORT_RANK_SQL);',
            <<<'PHP'
                self::DOCUMENT_SORT_RANK_IDENTIFIER . "\0"
                    . self::ADD_DOCUMENT_SORT_RANK_SQL,
                PHP,
            <<<'PHP'
                $connection->executeStatement(self::CREATE_DOCUMENT_INDEX_SQL);
                $connection->executeStatement(self::CREATE_ACCOUNT_MEMBERSHIPS_SQL);
                PHP,
            <<<'PHP'
                self::DOCUMENT_ACCESS_IDENTIFIER . "\0"
                    . self::CREATE_DOCUMENT_INDEX_SQL . "\0"
                    . self::CREATE_ACCOUNT_MEMBERSHIPS_SQL,
                PHP,
            '$connection->executeStatement(self::CREATE_ACCOUNT_USERS_SQL);',
            <<<'PHP'
                self::ACCOUNT_USERS_IDENTIFIER . "\0"
                    . self::CREATE_ACCOUNT_USERS_SQL,
                PHP,
        ];

        $normalizedMigrationCoordinator = preg_replace('/\s+/', ' ', $migrationCoordinator);

        foreach ($migrationSqlOrderMarkers as $migrationSqlOrderMarker) {
            $normalizedMigrationSqlOrderMarker = preg_replace(
                '/\s+/',
                ' ',
                trim($migrationSqlOrderMarker),
            );

            if (
                !is_string($normalizedMigrationCoordinator)
                || !is_string($normalizedMigrationSqlOrderMarker)
                || !str_contains($normalizedMigrationCoordinator, $normalizedMigrationSqlOrderMarker)
            ) {
                $failures[] = 'Migration execution order and checksum-covered SQL order must remain paired explicitly.';
            }
        }
    }

    $httpMigrationBoundaryFiles = [
        'example/bootstrap.php',
        'example/public/index.php',
        'example/src/ApplicationComposition.php',
        'skeleton/bootstrap.php',
        'skeleton/public/index.php',
    ];
    $forbiddenHttpMigrationMarkers = [
        'ApplicationCommandName::DatabaseMigrate',
        'SqliteApplicationMigrations',
        'application_migrations',
        'database:migrate',
        'setup-example.php',
    ];

    foreach ($httpMigrationBoundaryFiles as $relativePath) {
        $contents = file_get_contents($root . '/' . $relativePath);

        if (!is_string($contents)) {
            continue;
        }

        foreach ($forbiddenHttpMigrationMarkers as $marker) {
            if (str_contains($contents, $marker)) {
                $failures[] = "HTTP startup boundary {$relativePath} must not wire migration marker {$marker}.";
            }
        }
    }

    $frameworkRuntimePaths = ['bin/phpthis'];
    $frameworkSourceFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($frameworkSourceFiles as $frameworkSourceFile) {
        if (
            $frameworkSourceFile instanceof SplFileInfo
            && $frameworkSourceFile->isFile()
            && strtolower($frameworkSourceFile->getExtension()) === 'php'
        ) {
            $frameworkRuntimePaths[] = substr($frameworkSourceFile->getPathname(), strlen($root) + 1);
        }
    }

    foreach ($frameworkRuntimePaths as $frameworkRuntimePath) {
        $frameworkRuntime = file_get_contents($root . '/' . $frameworkRuntimePath);

        if (
            is_string($frameworkRuntime)
            && preg_match('/\bmigrat(?:e|es|ed|ing|ion|ions)\b/i', $frameworkRuntime) === 1
        ) {
            $failures[] = "Migration behavior must remain outside framework runtime source: {$frameworkRuntimePath}.";
        }
    }

    $composerLifecycleEvents = [
        'pre-install-cmd',
        'post-install-cmd',
        'pre-update-cmd',
        'post-update-cmd',
        'post-root-package-install',
        'post-create-project-cmd',
        'pre-autoload-dump',
        'post-autoload-dump',
        'pre-status-cmd',
        'post-package-install',
        'post-package-update',
        'pre-package-uninstall',
        'post-package-uninstall',
    ];

    foreach (['composer.json', 'skeleton/composer.json'] as $composerLifecyclePath) {
        $contents = file_get_contents($root . '/' . $composerLifecyclePath);
        $manifest = is_string($contents) ? json_decode($contents, true) : null;
        $scripts = is_array($manifest) ? ($manifest['scripts'] ?? null) : null;

        if (!is_array($scripts)) {
            continue;
        }

        foreach ($composerLifecycleEvents as $composerLifecycleEvent) {
            $commands = $scripts[$composerLifecycleEvent] ?? null;

            if ($commands === null) {
                continue;
            }

            $encodedCommands = json_encode($commands, JSON_THROW_ON_ERROR);

            foreach (
                ['database:migrate', 'SqliteApplicationMigrations', 'setup-example.php', '@example:setup']
                as $migrationLifecycleMarker
            ) {
                if (str_contains($encodedCommands, $migrationLifecycleMarker)) {
                    $failures[] = "Composer lifecycle {$composerLifecyclePath}:{$composerLifecycleEvent} must not run migrations.";
                }
            }
        }
    }

    if (is_dir($root . '/src/Observability')) {
        $failures[] = 'Terminal request-summary types must remain application-owned outside framework core.';
    }

    $unknownFailureBoundary = file_get_contents($root . '/src/Http/UnknownFailureBoundary.php');

    if (is_string($unknownFailureBoundary)) {
        foreach (['logAndRespond', 'error_log(', 'phpthis.request.unhandled', 'Throwable'] as $forbiddenMarker) {
            if (str_contains($unknownFailureBoundary, $forbiddenMarker)) {
                $failures[] = "UnknownFailureBoundary must not retain terminal logging marker {$forbiddenMarker}.";
            }
        }
    }

    foreach (
        [
            'example/src/Observability/TerminalRequestCoordinator.php',
            'skeleton/src/Observability/TerminalRequestCoordinator.php',
        ] as $coordinatorPath
    ) {
        $coordinator = file_get_contents($root . '/' . $coordinatorPath);

        if (
            is_string($coordinator)
            && substr_count($coordinator, '$this->summarySink->emit($summary);') !== 1
        ) {
            $failures[] = "Terminal request coordinator must retain exactly one sink invocation: {$coordinatorPath}.";
        }
    }

    foreach (
        [
            'example/src/Observability/CorrelationId.php',
            'skeleton/src/Observability/CorrelationId.php',
        ] as $correlationIdPath
    ) {
        $correlationId = file_get_contents($root . '/' . $correlationIdPath);

        if (is_string($correlationId) && str_contains($correlationId, 'fromString')) {
            $failures[] = "Correlation IDs must remain generated-only: {$correlationIdPath}.";
        }
    }

    $observabilityPackageInventory = file_get_contents($root . '/tools/package-files.txt');

    if (is_string($observabilityPackageInventory)) {
        foreach (
            [
                '/^\.ai\/observability\.md$/m',
                '/^example\//m',
                '/^skeleton\//m',
                '/^tests\/observability\.php$/m',
                '/^src\/Observability\//m',
            ] as $forbiddenPackagePattern
        ) {
            if (preg_match($forbiddenPackagePattern, $observabilityPackageInventory) === 1) {
                $failures[] = 'Application-owned observability artifacts must remain outside the framework package inventory.';
            }
        }
    }

    $listDocumentsHandlerPath = $root . '/example/src/Documents/ListDocuments/ListDocumentsHandler.php';
    $listDocumentsHandler = file_get_contents($listDocumentsHandlerPath);

    if (!is_string($listDocumentsHandler)) {
        $failures[] = 'Cannot read the direct raw-SQL document-list handler.';
    } else {
        $finiteSqlCounts = [
            "<<<'SQL'" => 8,
            '$this->connection->selectAllRows(' => 8,
            'documents.category IN (:category_1)' => 2,
            'documents.category IN (:category_1, :category_2)' => 2,
            'documents.category IN (:category_1, :category_2, :category_3)' => 2,
            'ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC' => 4,
            'ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC' => 4,
            "'requested_account_id' =>" => 8,
            "'resolved_tenant_account_id' =>" => 8,
            "'principal_id' =>" => 8,
            "'membership_tenant_account_id' =>" => 8,
            ':cursor_is_absent = 1' => 8,
            "'cursor_is_absent' =>" => 8,
            "'cursor_primary_sort_rank' =>" => 8,
            "'cursor_tie_sort_rank' =>" => 8,
            "'cursor_document_key' =>" => 8,
            "'category_1' =>" => 6,
            "'category_2' =>" => 4,
            "'category_3' =>" => 2,
            "'fetch_limit' =>" => 8,
        ];

        foreach ($finiteSqlCounts as $marker => $expectedCount) {
            if (substr_count($listDocumentsHandler, $marker) !== $expectedCount) {
                $failures[] = sprintf(
                    'Document-list raw-SQL marker %s must occur exactly %d times.',
                    $marker,
                    $expectedCount,
                );
            }
        }

        foreach (
            [
                'Repository',
                'QueryBuilder',
                'Paginator',
                'Hydrator',
                'bindValue',
                'bindParam',
                'buildPlaceholders',
                'sprintf(',
                'implode(',
            ] as $forbiddenDataHelper
        ) {
            if (str_contains($listDocumentsHandler, $forbiddenDataHelper)) {
                $failures[] = "Document-list SQL must remain direct and helper-free: {$forbiddenDataHelper}.";
            }
        }
    }

    return $failures;
}
