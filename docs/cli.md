# Application CLI and scheduler

PHPThis accepts one application-owned operational console pattern and provides no core command or scheduler API. The application keeps its finite command map, typed arguments, dependencies, exit codes, output, one-pass bounds, and process lifecycle explicit. When a scheduled pass is adopted, its clock, cadence, overlap, and supervisor policy are explicit too. The installed `vendor/bin/phpthis` executable remains the framework-owned `check` boundary; it is not an application command host. The optional separate `phpthis/workbench` development package is an unchecked expression workspace, not this operational console and not a production one-off path.

ADR 025 records the initial job and scheduler proof through the executable example. ADR 027 extends that same application-owned console with one explicit SQLite migration command, while ADR 043 separates its transaction, rollback, and same-host `flock` choices from the universal application-owned migration invariants. ADR 028 replaces only the example's same-host schedule file lock with one Redis-specific owner-token lease. None adds a framework command, migration, cache, lock, or lease API. The concrete names and cadence below are evidence for that application, not reserved PHPThis APIs that every consumer must copy.

## Adoption boundary

An application with no operational command or scheduler records `NOT_APPLICABLE(CLI)`. Composer development scripts, `vendor/bin/phpthis check`, and an optional `composer workbench` script do not by themselves mean that an application CLI has been adopted.

Before adoption, the accountable human records:

- the sole application console path plus each command's configuration-profile and authority references; `.ai/configuration.md` owns exact process identity and configuration, `.ai/data.md` owns effective database-authority facts and accountable transition ownership, and `.ai/migrations.md` owns each history's transition implementation and handoff constraints;
- every finite command name, operation owner, typed argument, bound, default, and normalization or explicit non-normalization policy;
- exact exit codes, stdout and stderr JSON schemas, outcome vocabulary, redaction, and compatibility policy;
- fresh composition ownership and the immutable configuration, if any, shared with HTTP composition;
- the one-pass work and resource bound for each command; and
- real-console tests for parsing, output bytes, expected and unexpected failure, resource bounds, and secrets exclusion.

A scheduled pass additionally records:

- its explicit clock, timezone, cadence, due test, missed-run, catch-up, and repeated-slot behavior;
- its overlap mechanism and namespace, topology, acquisition, expiry, renewal when applicable, contention, failure, release, crash behavior, maximum work, and coordination limit;
- cron or supervisor invocation frequency, timeout, forced termination, restart, capacity, and incident behavior; and
- real-console time-boundary, not-due, overlap, cleanup or release, supervisor, and topology evidence.

A console with no scheduled pass records those schedule-only facts as not applicable. In particular, a migration-only console does not need a scheduler overlap lock or cadence policy. Its engine- and topology-specific writer coordination or serialization is recorded and proved independently in `.ai/migrations.md` under [ADR 043](decisions/043-engine-specific-application-migration-invariants.md).

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

A scheduled invocation evaluates that clock and cadence immediately after typed argument and immutable-configuration composition, before database-path inspection. A non-due minute returns `not_due` with empty coordination and performs no database-path filesystem inspection, Redis or PDO work, token generation, SQL, or job work. This is a cadence result, not database or Redis readiness evidence. There is no persistent slot ledger, missed-run replay, or catch-up. The external cron or supervisor invokes the command at most once per minute. If it misses a due minute, the next ordinary minute remains not due and the next five-minute boundary is the next opportunity.

On a due minute, the command first requires its database path to exist and resolve. Failure produces only the generic `command_failed` result before Redis, PDO, SQL, or job work. A valid path then permits `RedisScheduleRunLease` to construct `phpthis_example:<environment>:schedule_run:v1` and a fresh 128-bit lowercase-hex owner token. It attempts one nonblocking `SET key token NX PX 30000` against the application lease endpoint. Connect and read timeouts are each 250 milliseconds. Contention returns `overlap_skipped` immediately. Connection, acquisition, explicit renewal, or owned-release failure fails closed as `command_failed` with the bounded coordination evidence collected before failure. Renewal and release each use one fixed Lua script that changes the key only when its current value matches the caller's token.

While it owns the lease, the scheduler synchronously calls the exact same in-process one-job operation as `jobs:run-one`, once. One invocation performs at most four explicit renewals. It does not spawn the console, enqueue through another path, loop, maintain a second operation, wait for the key, retry, or run a background renewal heartbeat. If work throws, the command attempts owned release and reports `command_failed`. No slot is marked, so a later invocation during that same due minute may try again.

The lease reduces overlap only for cooperating scheduled processes that reach the same Redis endpoint and namespace. The executable proof defaults it to `127.0.0.1:6380`, logical database `0`, on a single-primary `noeviction` process separate from the eviction-capable cache at `127.0.0.1:6379`, logical database `0`; a different logical database on one process is not sufficient separation. The lease does not deduplicate two sequential invocations, coordinate direct `jobs:run-one` commands, or fence work after its 30-second TTL expires. It cannot prove mutual exclusion through pauses, partitions, restart, failover, replication, clock anomalies, or an uncertain client timeout. The SQLite job lease and idempotent effect remain the correctness boundary. See [Redis cache and schedule coordination](redis-coordination.md) and [ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md).

## Explicit SQLite migration command

`database:migrate` is the sole migration spelling in the accepted example. It freshly composes the separately authorized migration connection, final concrete coordinator, finite ordered manifest, unrolled private migration-step calls, bounded ledger, budget, trace, and application-private nonblocking same-host lock. The ledger insert explicitly records SQLite `unixepoch()`; no PHP migration clock exists. The command applies every pending migration once in manifest order and exits; an unchanged database returns `up_to_date`.

The command does not run from HTTP startup, `schedule:run`, Composer dependency hooks, or framework `vendor/bin/phpthis`. It neither discovers migration files nor loads runtime SQL. Each migration and its ledger row use one explicit transaction, and a later failure preserves earlier committed entries. See [Explicit application migrations](migrations.md), [ADR 043](decisions/043-engine-specific-application-migration-invariants.md), and [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md) for the universal invariants and this proof's ledger, checksum, authority, transaction, lock, recovery, and engine boundary.

## Composition boundary

HTTP and CLI entrypoints may share immutable application configuration and narrowly named explicit construction code. They do not share live connections, budgets, traces, request objects, session state, correlation state, clocks with mutable test state, or other invocation-scoped objects. Each HTTP request and each console process receives fresh dependencies appropriate to its boundary.

The accepted example's `example/.ai/configuration.md` records its code-owned and command-line input sources, typed values, lifecycle, disclosure policy, and lack of credential or database-grant evidence. `Example\ApplicationComposition` retains only the already validated immutable database path and code-owned Redis cache and lease values; it does not read deployment inputs. `http()` builds a fresh terminal request coordinator and complete request-scoped graph, including a request-owned cache trace and lazily connected cache client only for the protected operation. `commands(UserWelcomeJobClock)` returns one explicit `ApplicationCommands` boundary; that boundary inspects the database path and constructs fresh lease state only after a scheduled pass is due, then constructs fresh job connection, budget, trace, and worker only when a direct or leased command reaches the one-job operation. This is ordinary application composition, not a service container, framework extension point, global registry, generic factory API, or object injected into business behavior.

## Consumer adoption evidence

A production adopter must execute its real console in fresh subprocesses and add evidence for every applicable item below. These are adoption requirements, not claims about the current example test suite:

- every accepted command and exact finite outcome, including idle and failure-shaped expected outcomes;
- missing, unknown, duplicate, reordered, malformed, empty, control-byte, relative, trailing-separator, 4,096-byte, and 4,097-byte option cases;
- unknown commands and invalid arguments perform no filesystem, lock, database, job, or external I/O;
- exact exit codes, stream exclusivity, key order, one-line JSON bytes, and final newline;
- a missing or inaccessible database on a direct database command or due scheduled pass and an unexpected throwable produce only `command_failed`, while Redis lease connection, acquisition, renewal, or release failure produces `command_failed` plus its finite coordination list;
- for an adopted scheduled pass, UTC minute boundaries immediately before, on, and after the five-minute cadence using an explicit deterministic clock;
- for an adopted cadence-first scheduled pass, `not_due` performs no database-path, coordination-backend, database, token-generation, SQL, or scheduled application work and is not reported as readiness;
- for the adopted Redis-coordinated scheduled pass, two concurrent owner-token lease attempts produce one bounded pass and one `overlap_skipped` without waiting;
- for that scheduled pass, stale-owner renewal and release cannot change a later owner's lease, and process termination permits later acquisition only after finite expiry;
- for that scheduled pass, sequential invocations in one due minute are not misreported as deduplicated;
- for that scheduled pass, one due invocation calls the same one-job operation at most once and handles at most one delivery;
- HTTP and CLI composition create fresh mutable state while sharing only the recorded immutable configuration;
- stdout, stderr, durable job state, terminal request summaries, and traces omit every submitted or sensitive value; and
- an adopted migration command also proves its exact recorded initial baseline and manifest order, finite exact-engine ledger-metadata acceptance and incompatible or additional-object rejection, exact bounded ledger, unchanged no-op rerun, checksum drift rejection before pending work, malformed and overflowing ledger rejection, same- and cross-topology exclusion or authority gating, stable coordination namespace and protected interval at its owning boundary, prior-owner fencing or confirmed termination when coordination can expire or be lost, bypass denial for external serialization, every applicable transaction, implicit-commit, non-atomic, and crash-visible state across checksum-covered DDL, data, and authority effects, cross-history recovery when histories interact, immutable committed history, forward continuation, and no HTTP migration path; the ADR 027 SQLite proof additionally requires its empty-database case, nonblocking same-host `flock` contention, and per-migration rollback with earlier commits preserved.

The complete application gate remains mandatory. Focused CLI tests shorten feedback but do not replace static analysis, the Strict Profile, or the application's other behavior evidence.

The current example's proof keeps exact unknown and invalid failures, redacted direct-command missing-database failure, and `jobs:run-one` `completed` and `idle` output with at most one delivery per fresh process. A direct parser test covers the exact 4,096/4,097-byte boundary. Deterministic scheduler evidence proves that a non-due missing path returns before path and Redis work while the same path fails on a due minute before Redis. Redis-specific tests cover cadence and exact coordination bytes, subprocess contention, explicit renewal, real TTL renewal, deterministic and real stale-owner rejection, deterministic and real server rejection, safe release, backend failure, process termination, and post-expiry acquisition against the recorded integration topology. Composition tests cover distinct HTTP and command boundary objects plus fresh HTTP correlation state. ADR 024 worker tests cover retry and dead-letter transitions; exhaustive typed mapping and static analysis cover their CLI mapping. The proof does not establish production Redis failover, restart, replication, partition, pause, clock, readiness, or fencing behavior, and it does not run every scheduled or worker outcome through the real console.

## Unsupported boundary

PHPThis ships no application console, command interface, command registry, argument parser, input or output helper, scheduler, cadence type, clock, lock, lease, daemon, worker manager, migration API, schema builder, process manager, signal handler, cron installer, deployment unit, or distributed coordinator. It adds no operational command to `bin/phpthis`. ADR 041's separately installed Workbench does not change that boundary: it has no production or noninteractive mode, stable operational output contract, redaction guarantee, queue claim, or deployment authority.

The example proves one application-owned Redis-specific overlap pattern. It does not promise command compatibility across applications, absolute distributed exclusion, persistent schedule deduplication, catch-up, production cron delivery, a fencing token, exactly-once job execution, or exactly-once external effects.

See [ADR 025](decisions/025-application-owned-explicit-cli-and-scheduler.md) for the console boundary, [ADR 043](decisions/043-engine-specific-application-migration-invariants.md) for universal application-owned migration invariants, [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md) for the SQLite migration proof, [ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md) for the Redis lease boundary, and [PHPThis Workbench](workbench.md) for the distinct development-only inspection boundary.
