# Application CLI and scheduler

PHPThis accepts one application-owned operational console pattern and provides no core command or scheduler API. The application keeps its finite command map, typed arguments, dependencies, exit codes, output, clock, overlap policy, and process lifecycle explicit. The installed `vendor/bin/phpthis` executable remains the framework-owned `check` boundary; it is not an application command host.

ADR 025 records the initial job and scheduler proof through the executable example. ADR 027 extends that same application-owned console with one explicit SQLite migration command. ADR 028 replaces only the example's same-host schedule file lock with one Redis-specific owner-token lease. None adds a framework command, migration, cache, lock, or lease API. The concrete names and cadence below are evidence for that application, not reserved PHPThis APIs that every consumer must copy.

## Adoption boundary

An application with no operational command or scheduler records `NOT_APPLICABLE(CLI)`. Composer development scripts and `vendor/bin/phpthis check` do not by themselves mean that an application CLI has been adopted.

Before adoption, the accountable human records:

- the sole application console path and deployment identity;
- every finite command name, operation owner, typed argument, bound, default, and normalization or explicit non-normalization policy;
- exact exit codes, stdout and stderr JSON schemas, outcome vocabulary, redaction, and compatibility policy;
- fresh composition ownership and the immutable configuration, if any, shared with HTTP composition;
- explicit clock, timezone, cadence, due test, missed-run and catch-up behavior, maximum work per pass, and cron or supervisor invocation frequency;
- overlap mechanism, namespace, topology, acquisition, expiry, renewal, contention, failure, release, and crash behavior, including whether it is a same-host lock or an accepted backend-specific lease;
- timeout, forced termination, restart, capacity, and incident behavior; and
- tests for parsing, output bytes, time boundaries, overlap, failure, resource bounds, and secrets exclusion.

Do not add a command framework, registry, or scheduler in anticipation of future operations.

## One explicit console

The accepted example owns one executable PHP file:

```text
example/bin/console.php
```

Its complete public grammar is:

```text
php example/bin/console.php <jobs:run-one|schedule:run|database:migrate> [--database=/absolute/path]
```

The command occupies the first application argument. Zero or one option may follow it, and the only option spelling is one token beginning `--database=`. The value is 1 through 4,096 bytes, must be absolute under the current host operating system, contains no ASCII control byte or DEL, and ends with neither `/` nor `\`. A duplicate option, an option before the command, an empty value, an unsupported spelling, an extra argument, or any other shape is invalid. The console rejects unknown and invalid input before Redis, filesystem, lock, or database I/O.

The database path is operational configuration, not output. The example has one code-owned default for local evaluation; a supplied path does not appear in stdout, stderr, durable diagnostics, or request summaries. Redis endpoint, namespace, timeout, and TTL configuration is also never output. A real application records how its trusted supervisor supplies non-secret configuration and protects the selected database and coordination endpoints.

The parser selects from a finite code-owned map and constructs typed command behavior directly. It does not convert submitted text into a PHP class, callback, service identifier, executable path, SQL fragment, or environment-variable name. No command discovery, filesystem scan, reflection, service container, facade, alias, abbreviation, or fallback command exists.

## Exit and output contract

The accepted example writes exactly one JSON object followed by `\n` to exactly one stream:

| Condition | Exit | stdout | stderr |
| --- | ---: | --- | --- |
| `jobs:run-one` expected result | `0` | `{"command":"jobs:run-one","outcome":"<job-outcome>"}\n` | empty |
| `schedule:run` expected result | `0` | `{"command":"schedule:run","outcome":"<schedule-outcome>","coordination":[<finite-code-owned-outcomes>]}\n` | empty |
| `database:migrate` expected result | `0` | `{"command":"database:migrate","outcome":"<applied|up_to_date>"}\n` | empty |
| finite migration failure | `1` | empty | `{"error":"migration_failed","reason":"<finite-reason>","migration":<code-owned-id-or-null>}\n` |
| unknown command | `2` | empty | `{"error":"unknown_command"}\n` |
| invalid arguments | `2` | empty | `{"error":"invalid_arguments"}\n` |
| Redis lease operational failure | `1` | empty | `{"error":"command_failed","coordination":[<finite-code-owned-outcomes>]}\n` |
| other operational or unexpected failure | `1` | empty | `{"error":"command_failed"}\n` |

The job outcomes are `idle`, `completed`, `retry_scheduled`, and `dead_lettered`. The schedule outcomes add `not_due` and `overlap_skipped`; when a due scheduled pass invokes the job operation, it returns that operation's finite outcome under the `schedule:run` command name. Schedule output always includes `coordination`: `not_due` uses `[]`, contention uses `["connected","contended"]`, and the demonstrated owned pass uses `["connected","acquired","renewed","released"]`. Redis operational failures retain `command_failed` and add the finite list captured before failure. Idle, not-due, overlap, retry, and dead-letter states are expected scriptable results rather than process failures.

Key order and bytes are stable. A schedule success orders `command`, `outcome`, then `coordination`; a Redis lease failure orders `error`, then `coordination`. The coordination list has at most eight code-owned outcome strings and omits endpoints, keys, values, owner tokens, replies, and exception details. The generic and migration errors intentionally omit submitted command and option text, paths, DSNs, environment values, credentials, exception classes and messages, stacks, SQL, bindings, ledger contents, schema contents, job identities, envelopes, payloads, idempotency keys, and domain values. PHP warnings or diagnostics must not become a second output line.

The migration failure reasons are exactly `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`. Its `migration` field is one code-owned manifest identifier or `null`; submitted or stored identifiers are never reflected.

## Direct one-job command

`jobs:run-one` freshly composes the application-owned SQLite job connection, query budget, bounded trace, clock, finite envelope dispatcher, and concrete handler. It calls the exact one-job operation accepted by ADR 024 in the current process. That operation claims and finalizes zero or one delivery, and the console exits after mapping its finite outcome.

There is no worker loop, polling, recursive console call, subprocess handoff, implicit retry, daemon, hidden supervisor, or second job command. A deployment that wants continual work explicitly starts fresh `jobs:run-one` processes under its recorded supervisor policy.

## UTC five-minute scheduled pass

`schedule:run` uses the injected Unix clock as UTC. It ignores seconds and is due precisely when:

```text
intdiv(epoch_seconds, 60) % 5 === 0
```

A non-due minute returns `not_due` without application work. There is no persistent slot ledger, missed-run replay, or catch-up. The external cron or supervisor invokes the command at most once per minute. If it misses a due minute, the next ordinary minute remains not due and the next five-minute boundary is the next opportunity.

On a due minute, `RedisScheduleRunLease` constructs `phpthis_example:<environment>:schedule_run:v1` and a fresh 128-bit lowercase-hex owner token. It attempts one nonblocking `SET key token NX PX 30000` against the application lease endpoint. Connect and read timeouts are each 250 milliseconds. Contention returns `overlap_skipped` immediately. Connection, acquisition, explicit renewal, or owned-release failure fails closed as `command_failed` with the bounded coordination evidence collected before failure. Renewal and release each use one fixed Lua script that changes the key only when its current value matches the caller's token.

While it owns the lease, the scheduler synchronously calls the exact same in-process one-job operation as `jobs:run-one`, once. One invocation performs at most four explicit renewals. It does not spawn the console, enqueue through another path, loop, maintain a second operation, wait for the key, retry, or run a background renewal heartbeat. If work throws, the command attempts owned release and reports `command_failed`. No slot is marked, so a later invocation during that same due minute may try again.

The lease reduces overlap only for cooperating scheduled processes that reach the same Redis endpoint and namespace. The executable proof defaults it to `127.0.0.1:6380`, logical database `0`, on a single-primary `noeviction` process separate from the eviction-capable cache at `127.0.0.1:6379`, logical database `0`; a different logical database on one process is not sufficient separation. The lease does not deduplicate two sequential invocations, coordinate direct `jobs:run-one` commands, or fence work after its 30-second TTL expires. It cannot prove mutual exclusion through pauses, partitions, restart, failover, replication, clock anomalies, or an uncertain client timeout. The SQLite job lease and idempotent effect remain the correctness boundary. See [Redis cache and schedule coordination](redis-coordination.md) and [ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md).

## Explicit SQLite migration command

`database:migrate` is the sole migration spelling in the accepted example. It freshly composes the separately authorized migration connection, final concrete coordinator, finite ordered manifest, unrolled private migration-step calls, bounded ledger, budget, trace, and application-private nonblocking same-host lock. The ledger insert explicitly records SQLite `unixepoch()`; no PHP migration clock exists. The command applies every pending migration once in manifest order and exits; an unchanged database returns `up_to_date`.

The command does not run from HTTP startup, `schedule:run`, Composer dependency hooks, or framework `vendor/bin/phpthis`. It neither discovers migration files nor loads runtime SQL. Each migration and its ledger row use one explicit transaction, and a later failure preserves earlier committed entries. See [Explicit application migrations](migrations.md) and [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md) for the ledger, checksum, authority, transaction, lock, recovery, and engine boundary.

## Composition boundary

HTTP and CLI entrypoints may share immutable application configuration and narrowly named explicit construction code. They do not share live connections, budgets, traces, request objects, session state, correlation state, clocks with mutable test state, or other invocation-scoped objects. Each HTTP request and each console process receives fresh dependencies appropriate to its boundary.

The accepted example uses `Example\ApplicationComposition` for this visible ownership. Its constructor retains the validated immutable database path, while the local proof's Redis cache and lease settings are code-owned composition values. `http()` builds a fresh terminal request coordinator and complete request-scoped graph, including a request-owned cache trace and lazily connected cache client only for the protected operation. `commands(UserWelcomeJobClock)` returns one explicit `ApplicationCommands` boundary; that boundary constructs fresh lease state for a due scheduled pass and fresh job connection, budget, trace, and worker only when a direct or leased command reaches the one-job operation. This is ordinary application composition, not a service container, framework extension point, global registry, generic factory API, or object injected into business behavior.

## Consumer adoption evidence

A production adopter must execute its real console in fresh subprocesses and add evidence for every applicable item below. These are adoption requirements, not claims about the current example test suite:

- every accepted command and exact finite outcome, including idle and failure-shaped expected outcomes;
- missing, unknown, duplicate, reordered, malformed, empty, control-byte, relative, trailing-separator, 4,096-byte, and 4,097-byte option cases;
- unknown commands and invalid arguments perform no filesystem, lock, database, job, or external I/O;
- exact exit codes, stream exclusivity, key order, one-line JSON bytes, and final newline;
- a missing or inaccessible database and an unexpected throwable produce only `command_failed`, while Redis lease connection, acquisition, renewal, or release failure produces `command_failed` plus its finite coordination list;
- UTC minute boundaries immediately before, on, and after the five-minute cadence using an explicit deterministic clock;
- `not_due` performs no scheduled application work;
- two concurrent owner-token lease attempts produce one bounded pass and one `overlap_skipped` without waiting;
- stale-owner renewal and release cannot change a later owner's lease, and process termination permits later acquisition only after finite expiry;
- sequential invocations in one due minute are not misreported as deduplicated;
- one due pass invokes the same one-job operation at most once and handles at most one delivery;
- HTTP and CLI composition create fresh mutable state while sharing only the recorded immutable configuration; and
- stdout, stderr, durable job state, terminal request summaries, and traces omit every submitted or sensitive value; and
- an adopted migration command also proves fresh-database manifest order, exact bounded ledger, unchanged no-op rerun, checksum drift rejection before pending work, malformed and overflowing ledger rejection, nonblocking migration-lock contention, per-migration rollback with earlier commits preserved, forward continuation, and no HTTP migration path.

The complete application gate remains mandatory. Focused CLI tests shorten feedback but do not replace static analysis, the Strict Profile, or the application's other behavior evidence.

The current example's proof keeps exact unknown and invalid failures, redacted missing-database failure, and `jobs:run-one` `completed` and `idle` output with at most one delivery per fresh process. A direct parser test covers the exact 4,096/4,097-byte boundary. Redis-specific tests cover deterministic cadence and exact coordination bytes, subprocess contention, explicit renewal, real TTL renewal, deterministic and real stale-owner rejection, deterministic and real server rejection, safe release, backend failure, process termination, and post-expiry acquisition against the recorded integration topology. Composition tests cover distinct HTTP and command boundary objects plus fresh HTTP correlation state. ADR 024 worker tests cover retry and dead-letter transitions; exhaustive typed mapping and static analysis cover their CLI mapping. The proof does not establish production Redis failover, restart, replication, partition, pause, clock, or fencing behavior, and it does not run every scheduled or worker outcome through the real console.

## Unsupported boundary

PHPThis ships no application console, command interface, command registry, argument parser, input or output helper, scheduler, cadence type, clock, lock, lease, daemon, worker manager, migration API, schema builder, process manager, signal handler, cron installer, deployment unit, or distributed coordinator. It adds no operational command to `bin/phpthis`.

The example proves one application-owned Redis-specific overlap pattern. It does not promise command compatibility across applications, absolute distributed exclusion, persistent schedule deduplication, catch-up, production cron delivery, a fencing token, exactly-once job execution, or exactly-once external effects.

See [ADR 025](decisions/025-application-owned-explicit-cli-and-scheduler.md) for the console boundary, [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md) for the migration boundary, and [ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md) for the Redis lease boundary.
