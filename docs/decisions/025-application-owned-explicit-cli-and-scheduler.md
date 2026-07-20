# ADR 025: Application-owned explicit CLI and scheduler

Status: accepted

## Context

Applications need repeatable operational commands and cron-driven work with stable machine behavior. A framework command registry, automatic discovery, service-container lookup, scheduler facade, daemon, or hidden process lifecycle would enlarge the non-local state an AI and human must infer. PHPThis already has one framework-owned executable for the installed `check` gate and ADR 024 has one application-owned one-job operation, but neither is an application command host or scheduler.

The first proof needs typed argument boundaries, stable exit and stream behavior, an explicit clock, bounded same-host overlap behavior, and fresh composition shared safely with the HTTP entrypoint. Distributed coordination, persistent schedule history, deployment-specific cron delivery, and a general-purpose CLI API are separate concerns.

## Decision

The CLI and scheduler remain an application-owned pattern. PHPThis adds no core command, command interface, registry, argument parser, scheduler, clock, lock, daemon, process manager, service-container integration, or command discovery. Framework `bin/phpthis` remains the installed `check` boundary only. Consumer Contract version 5 and Strict Profile version 2 remain unchanged.

The executable example owns one `example/bin/console.php` entrypoint with exactly this grammar:

```text
php example/bin/console.php <jobs:run-one|schedule:run> [--database=/absolute/path]
```

The finite command is first and zero or one exact `--database=` token may follow. Its value is 1–4,096 bytes, absolute for the current host operating system, free of ASCII control bytes and DEL, and has no trailing slash or backslash. Duplicate, reordered, missing-value, alternate-spelling, and extra arguments are invalid. Unknown command and argument validation occur before filesystem, lock, database, job, or external I/O. Submitted text never selects a PHP class, callback, service, executable, SQL fragment, or environment name.

Expected outcomes exit `0` and write one newline-terminated JSON object to stdout with exact key order `command`, then `outcome`; stderr stays empty. `jobs:run-one` permits `idle`, `completed`, `retry_scheduled`, and `dead_lettered`. `schedule:run` permits those outcomes plus `not_due` and `overlap_skipped`. Unknown command writes only `{"error":"unknown_command"}` to stderr and exits `2`; invalid arguments write only `{"error":"invalid_arguments"}` and exit `2`; operational or unexpected failure writes only `{"error":"command_failed"}` and exits `1`. Error exits leave stdout empty. No output includes submitted text, paths, DSNs, credentials, exception details, SQL, bindings, job data, or domain values.

`jobs:run-one` freshly composes and synchronously calls the exact ADR 024 one-job operation in the current process. It handles zero or one delivery and exits. There is no polling, worker loop, subprocess recursion, hidden retry, daemon, or second command spelling.

`schedule:run` reads the injected Unix clock as UTC, ignores seconds, and is due when `intdiv(epoch_seconds, 60) % 5 === 0`. It has no persistent slot ledger, catch-up, or missed-run replay; its external supervisor invokes at most once per minute. A non-due invocation returns `not_due` without scheduled work.

A due invocation resolves the canonical database path, appends `.schedule.lock`, and attempts one application-private nonblocking exclusive `flock` on a filesystem shared by cooperating invocations on that host. Ordinary contention returns `overlap_skipped`. Lock open, non-contention acquisition, and unlock failures fail closed. The lock is released in `finally`. While holding it, the scheduler directly invokes the same in-process one-job operation once and returns that operation's finite outcome. It does not enqueue through a second path or invoke a subprocess.

The lock bounds concurrent same-host scheduled passes only. It does not deduplicate sequential invocations within one due minute, and a failed pass records no completed slot, so another invocation in that minute may try again. It does not coordinate hosts or direct job commands. ADR 024 leases and idempotent effects remain authoritative; distributed coordination is deferred to a separately accepted backend-specific lease decision.

The example uses `Example\ApplicationComposition` as an ordinary explicit owner of immutable canonical database-path configuration. `http()` creates fresh request-scoped connections, budgets, traces, correlation, and coordinator state. `commands(UserWelcomeJobClock)` returns one explicit `ApplicationCommands` boundary, which creates the fresh job connection, budget, trace, dispatcher, and handler only when a due or direct command reaches the one-job operation. The two entrypoints share no mutable request or invocation state, and the composition object is not injected into behavior or used as a generic factory, registry, or container.

Repository tests execute the real console in fresh subprocesses for exact unknown-command and invalid-argument failures, redacted missing-database failure, and `jobs:run-one` `completed` and `idle` results with at most one delivery per process. A parser test proves the exact 4,096-byte acceptance and 4,097-byte rejection boundary. Direct `ApplicationCommands` tests use a deterministic clock to prove `not_due` and `completed` at the UTC cadence boundaries, two sequential passes within one due minute, and at most one delivery per pass. A subprocess-held lock proves immediate `overlap_skipped` without delivery and permits `completed` after release. Composition tests prove distinct HTTP and command boundary objects plus fresh HTTP correlation state. ADR 024 worker tests cover retry and dead-letter transitions; the exhaustive typed outcome mapping and static analysis cover their CLI mapping. The current example does not inject lock open, non-contention acquisition, release, or arbitrary throwable failures, and it does not drive every scheduled or worker outcome through the real console. A production adopter adds the applicable failure-injection and real-entrypoint evidence required by its recorded operational policy.

## Consequences

The application owns a small amount of repeated parsing, composition, output mapping, lock handling, and deployment policy. That repetition makes command names, side effects, time, locking, and failure behavior visible without introducing a framework runtime abstraction.

Exit `0` means the command produced a documented expected outcome; it does not mean that a job was available, that scheduled work ran, that a retry succeeded, or that a dead letter was repaired. Callers inspect the finite `outcome`. Exit `1` is deliberately generic and redacted, so richer operational diagnosis requires a separately owned bounded destination that does not alter the console contract.

The five-minute test is wall-clock scheduling without a ledger. Correct invocation frequency belongs to cron or the deployment supervisor. Missed minutes are not replayed, and sequential invocations within one due minute can each perform work. The file lock is advisory and topology-dependent; production adoption must verify path ownership, permissions, local filesystem behavior, every cooperating process, timeout, restart, and incident response.

No framework core, Consumer Contract version, Strict Profile version, diagnostic, checker rule, durable-job guarantee, or distributed-coordination claim changes.

## Reconsider when

At least two independent applications prove the same smaller command boundary across materially different operations and deployment runtimes, or the explicit application pattern exposes a concrete safety defect that cannot remain local. Reconsider one narrow evidence-backed contract without adding discovery, a generic scheduler facade, hidden mutable state, daemon magic, or distributed guarantees unsupported by the selected backend.
