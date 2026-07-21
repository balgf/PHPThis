# Example application CLI and scheduler context

The executable example follows ADR 025 with one application-owned entrypoint at `example/bin/console.php`; ADR 028 replaces only its schedule overlap file lock with one application-owned Redis lease. It adds no PHPThis core CLI, scheduler, lock, or lease API, and framework `bin/phpthis` remains the installed checker only.

## Command and argument grammar

From the repository root, the only accepted forms are:

```text
php example/bin/console.php jobs:run-one [--database=/absolute/path]
php example/bin/console.php schedule:run [--database=/absolute/path]
php example/bin/console.php database:migrate [--database=/absolute/path]
```

The command is required first. At most one option follows, spelled exactly `--database=` with its value in the same argument. That value is 1–4,096 bytes, absolute for the current host operating system, free of ASCII control bytes and DEL, and ends with neither `/` nor `\`. Empty, relative, oversized, duplicate, reordered, alternate-spelling, and extra arguments fail before Redis, filesystem, database, or job I/O.

The optional path overrides the code-owned local example database path for this invocation. It is never emitted. The finite parser and command `match` do not discover a class, executable, service, or handler from submitted text.

## Exit and stream contract

Every invocation writes one JSON object plus `\n` to exactly one stream:

| Condition | Exit | Exact channel and shape |
| --- | ---: | --- |
| `jobs:run-one` expected result | `0` | stdout `{"command":"jobs:run-one","outcome":"<idle|completed|retry_scheduled|dead_lettered>"}` |
| `schedule:run` expected result | `0` | stdout `{"command":"schedule:run","outcome":"<job-outcome|not_due|overlap_skipped>","coordination":[<finite-code-owned-outcomes>]}` |
| `database:migrate` applies pending history | `0` | stdout `{"command":"database:migrate","outcome":"applied"}` |
| `database:migrate` finds unchanged complete history | `0` | stdout `{"command":"database:migrate","outcome":"up_to_date"}` |
| migration lock, history, ledger, or application failure | `1` | stderr `{"error":"migration_failed","reason":"<finite-reason>","migration":<code-owned-id-or-null>}` |
| unknown command | `2` | stderr `{"error":"unknown_command"}` |
| invalid arguments | `2` | stderr `{"error":"invalid_arguments"}` |
| Redis lease operational failure | `1` | stderr `{"error":"command_failed","coordination":[<finite-code-owned-outcomes>]}` |
| other operational or unexpected failure | `1` | stderr `{"error":"command_failed"}` |

Success leaves stderr empty. Error exits leave stdout empty. Key order is stable. Schedule success orders `command`, `outcome`, then `coordination`; `not_due` uses `[]`, contention uses `["connected","contended"]`, and the demonstrated owned pass uses `["connected","acquired","renewed","released"]`. Redis operational failure orders `error`, then its at-most-eight-entry `coordination` list. Migration reasons are exactly `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`; the migration field is one of the seven code-owned identifiers in `.ai/migrations.md` or `null`. Output omits submitted values, database and migration-lock paths, Redis endpoints, Redis keys and values, owner tokens, raw replies, DSNs, credentials, exception classes and messages, stacks, SQL, bindings, ledger and schema contents, job identities, envelopes, payloads, idempotency keys, request data, and domain values.

## One-job command

`jobs:run-one` calls the same `SqliteUserWelcomeJobWorker::runOne` operation documented by `.ai/jobs.md`. `Example\ApplicationComposition::commands(UserWelcomeJobClock)` returns the explicit `ApplicationCommands` boundary; it constructs a fresh job connection, `QueryBudget`, `QueryTrace`, delivery handler, and worker only when the command reaches that one-job operation. It processes zero or one delivery in the current process and exits; there is no loop, subprocess, recursion, or second worker entrypoint.

## Scheduled pass

`schedule:run` uses the injected `UserWelcomeJobClock` as UTC. Seconds are ignored. The command is due only when `intdiv(epochSeconds, 60) % 5 === 0`; other minutes return `not_due` without job work. There is no slot ledger, catch-up, or missed-run replay. The external cron or supervisor must invoke at most once per minute.

For a due minute, `RedisScheduleRunLease` constructs `phpthis_example:<environment>:schedule_run:v1` and one fresh 128-bit lowercase-hex owner token. It attempts one nonblocking `SET key token NX PX 30000` with 250-millisecond connect and read timeouts. Ordinary contention immediately returns `overlap_skipped`. Connection, acquisition, explicit renewal, and owner-checked release failures produce `command_failed` with the bounded coordination outcomes recorded before failure.

While it owns the lease, `schedule:run` calls the same in-process one-job operation exactly once and performs at most four explicit renewals. It does not enqueue, spawn the console, wait, retry, poll, or renew in a background loop. Renewal and release each use a fixed Lua script that acts only when the stored value matches this process's token. A thrown failure still attempts owned release and returns `command_failed`; no persistent slot is marked. Two sequential invocations in the same due minute are not deduplicated and may each run one pass.

The lease defaults to `127.0.0.1:6380`, logical database `0`, on a `noeviction` process separate from the eviction-capable document cache at `127.0.0.1:6379`, logical database `0`; using another logical database in the cache process is insufficient. The lease coordinates only cooperating scheduled invocations using the same endpoint and namespace. Its owner token is not a fencing token, and the proof does not cover direct job commands, uncooperative processes, work continuing after TTL expiry, process pauses, partitions, restart, failover, replication, clock anomalies, or uncertain client timeouts. The SQLite job lease and idempotent effect remain the correctness boundary.

## Composition and evidence

`Example\ApplicationComposition` retains explicit immutable application configuration. `http()` creates a fresh terminal request coordinator, request-scoped database and cache state, budgets, traces, and correlation state. The explicit CLI boundary defers fresh Redis lease and job-scoped construction until a due command reaches coordination and creates fresh migration-scoped connection, budget, trace, ledger, manifest, and file-lock state only for `database:migrate`. The ledger insert explicitly selects SQLite `unixepoch()`; no PHP migration clock exists. No live Redis client, connection, budget, trace, request, session, correlation ID, or mutable clock is shared between HTTP and CLI, and the composition object is not injected into command behavior.

Real-console subprocess tests prove exact unknown and invalid failures, redacted missing-database failure, and `jobs:run-one` `completed` and `idle` output with at most one delivery per fresh process. A direct parser test proves the 4,096-byte acceptance and 4,097-byte rejection boundary. Deterministic and Redis integration tests prove cadence and exact coordination bytes, subprocess contention, explicit and real renewal, deterministic and real stale-owner rejection, deterministic and real server rejection, owned release, backend failure, process termination, and post-expiry acquisition. The integration evidence declares `ext-redis ^6.3` and Redis server `>=7.4` and `<9.0`. Composition tests prove distinct HTTP and command boundaries plus fresh HTTP correlation state. ADR 024 worker tests cover retry and dead-letter transitions, and exhaustive typed mapping plus static analysis cover their CLI mapping. This remains a backend- and topology-specific application proof, not a generic distributed scheduler, fencing system, persistent cadence ledger, generic console framework, or exactly-once claim.

Migration evidence is authoritative in `.ai/migrations.md`: fresh application, exact bounded ledger, no-op rerun, drift and invalid-history rejection, nonblocking `.migration.lock`, per-migration rollback, forward continuation, exact finite error bytes, redaction, and no HTTP migration path. It remains a SQLite application proof, not a framework or cross-engine migration claim.
