# Example application CLI and scheduler context

The executable example follows ADR 025 with one application-owned entrypoint at `example/bin/console.php`. It adds no PHPThis core CLI or scheduler API, and framework `bin/phpthis` remains the installed checker only.

## Command and argument grammar

From the repository root, the only accepted forms are:

```text
php example/bin/console.php jobs:run-one [--database=/absolute/path]
php example/bin/console.php schedule:run [--database=/absolute/path]
php example/bin/console.php database:migrate [--database=/absolute/path]
```

The command is required first. At most one option follows, spelled exactly `--database=` with its value in the same argument. That value is 1–4,096 bytes, absolute for the current host operating system, free of ASCII control bytes and DEL, and ends with neither `/` nor `\`. Empty, relative, oversized, duplicate, reordered, alternate-spelling, and extra arguments fail before filesystem, lock, database, or job I/O.

The optional path overrides the code-owned local example database path for this invocation. It is never emitted. The finite parser and command `match` do not discover a class, executable, service, or handler from submitted text.

## Exit and stream contract

Every invocation writes one JSON object plus `\n` to exactly one stream:

| Condition | Exit | Exact channel and shape |
| --- | ---: | --- |
| `jobs:run-one` expected result | `0` | stdout `{"command":"jobs:run-one","outcome":"<idle|completed|retry_scheduled|dead_lettered>"}` |
| `schedule:run` expected result | `0` | stdout with command `schedule:run` and a job outcome, `not_due`, or `overlap_skipped` |
| `database:migrate` applies pending history | `0` | stdout `{"command":"database:migrate","outcome":"applied"}` |
| `database:migrate` finds unchanged complete history | `0` | stdout `{"command":"database:migrate","outcome":"up_to_date"}` |
| migration lock, history, ledger, or application failure | `1` | stderr `{"error":"migration_failed","reason":"<finite-reason>","migration":<code-owned-id-or-null>}` |
| unknown command | `2` | stderr `{"error":"unknown_command"}` |
| invalid arguments | `2` | stderr `{"error":"invalid_arguments"}` |
| operational or unexpected failure | `1` | stderr `{"error":"command_failed"}` |

Success leaves stderr empty. Error exits leave stdout empty. Key order is stable. Migration reasons are exactly `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`; the migration field is one of the six code-owned identifiers in `.ai/migrations.md` or `null`. Output omits submitted values, database and lock paths, DSNs, credentials, exception classes and messages, stacks, SQL, bindings, ledger and schema contents, job identities, envelopes, payloads, idempotency keys, request data, and domain values.

## One-job command

`jobs:run-one` calls the same `SqliteUserWelcomeJobWorker::runOne` operation documented by `.ai/jobs.md`. `Example\ApplicationComposition::commands(UserWelcomeJobClock)` returns the explicit `ApplicationCommands` boundary; it constructs a fresh job connection, `QueryBudget`, `QueryTrace`, delivery handler, and worker only when the command reaches that one-job operation. It processes zero or one delivery in the current process and exits; there is no loop, subprocess, recursion, or second worker entrypoint.

## Scheduled pass

`schedule:run` uses the injected `UserWelcomeJobClock` as UTC. Seconds are ignored. The command is due only when `intdiv(epochSeconds, 60) % 5 === 0`; other minutes return `not_due` without job work. There is no slot ledger, catch-up, or missed-run replay. The external cron or supervisor must invoke at most once per minute.

For a due minute, the command resolves the canonical database path, appends `.schedule.lock`, opens that application-private path, and attempts a nonblocking exclusive `flock`. Ordinary contention immediately returns `overlap_skipped`. Open, non-contention acquisition, and unlock failures produce `command_failed`. An acquired lock is released in `finally`.

While holding the lock, `schedule:run` calls the same in-process one-job operation exactly once. It does not enqueue, spawn the console, or loop. A thrown failure releases the lock and returns `command_failed`; no persistent slot is marked. Two sequential invocations in the same due minute are therefore not deduplicated and may each run one pass. The lock coordinates only cooperating scheduled invocations that use the same canonical database and shared same-host lock filesystem. It does not coordinate multiple hosts or direct `jobs:run-one` processes.

## Composition and evidence

`Example\ApplicationComposition` retains only the immutable canonical database path. `http()` creates a fresh terminal request coordinator and request-scoped connections, budgets, traces, and correlation state. The explicit CLI boundary defers fresh job-scoped construction until due and lock checks pass and creates fresh migration-scoped connection, budget, trace, ledger, manifest, and lock state only for `database:migrate`. The ledger insert explicitly selects SQLite `unixepoch()`; no PHP migration clock exists. No live connection, budget, trace, request, session, correlation ID, or mutable clock is shared between HTTP and CLI, and the composition object is not injected into command behavior.

Real-console subprocess tests prove exact unknown and invalid failures, redacted missing-database failure, and `jobs:run-one` `completed` and `idle` output with at most one delivery per fresh process. A direct parser test proves the 4,096-byte acceptance and 4,097-byte rejection boundary. Direct command tests with deterministic clocks prove `not_due` and `completed` cadence behavior, two sequential passes in one due minute, one delivery per pass, and immediate `overlap_skipped` under a subprocess-held lock followed by `completed` after release. Composition tests prove distinct HTTP and command boundaries plus fresh HTTP correlation state. ADR 024 worker tests cover retry and dead-letter transitions, and exhaustive typed mapping plus static analysis cover their CLI mapping. The current proof does not inject lock open, non-contention acquisition, release, or arbitrary throwable failures, nor does it drive every scheduled or worker outcome through the real console. This remains a single-host application proof, not a distributed scheduler, persistent cadence ledger, generic console framework, or exactly-once claim.

Migration evidence is authoritative in `.ai/migrations.md`: fresh application, exact bounded ledger, no-op rerun, drift and invalid-history rejection, nonblocking `.migration.lock`, per-migration rollback, forward continuation, exact finite error bytes, redaction, and no HTTP migration path. It remains a SQLite application proof, not a framework or cross-engine migration claim.
