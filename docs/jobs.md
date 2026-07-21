# Durable jobs

PHPThis has one accepted durable-job recipe and no framework queue mechanism. The recipe is intentionally application-owned and SQLite-specific: it demonstrates how a business write and the requirement for deferred work can share one durable commit without adding a queue facade, event bus, automatic discovery, or hidden transaction behavior.

This is at-least-once delivery. It is not a claim that every application needs background work, that SQLite is a universal queue backend, or that an external side effect can execute exactly once.

For focused AI retrieval, use the [job knowledge index](jobs/README.md): envelope and dispatch, lifecycle and fencing, operations, and required testing are split into small authoritative slices. This page remains the complete decision guide.

## Adoption boundary

Before adopting this recipe, the accountable human records:

- why the work must survive request or process termination instead of running synchronously;
- the exact SQLite version, database path and filesystem topology, locking mode, busy-timeout policy, and supported worker concurrency;
- the producer transaction, finite job types and envelope versions, payload bounds, idempotency-key owner, and retention classification;
- lease duration, maximum attempts, exact finite backoff schedule, diagnostic-code catalogue, clock source, and clock-skew assumptions;
- the worker supervisor, invocation interval, timeout and forced-termination policy, deployment user, shutdown behavior, and capacity alarm;
- completed-row and dead-letter retention, inspection, replay, cancellation, schema migration, backup, and recovery ownership; and
- whether each effect is confined to the same SQLite database or reaches an external system with weaker guarantees.

If the application does not need durable deferred work, record `NOT_APPLICABLE(JOBS)`. Do not install an abstraction in anticipation of a future need.

## Atomic publication

The producer uses one explicit transaction on one `Connection` to the same SQLite database:

1. begin the transaction;
2. execute the business write;
3. insert exactly one job row containing the bounded versioned envelope and scheduling metadata;
4. commit; and
5. roll back in `finally` whenever the transaction remains active.

The job becomes claimable only after the commit makes the row visible. A failed business write or job insert rolls back both. This closes the gap in which a business write commits but later process termination prevents a separate enqueue call.

The guarantee ends at that transaction boundary. Two `Connection` instances do not share one transaction merely because they use the same database file. A second database, message broker, HTTP call, email, object store, or other external system cannot join this SQLite commit. PHPThis adds no transaction callback, after-commit hook, distributed transaction, or hidden dispatcher.

## Versioned envelope

The stored JSON envelope has four conceptual fields:

```json
{
  "version": 1,
  "type": "finite.application.type",
  "idempotency_key": "application-generated-bounded-key",
  "payload": {}
}
```

The application records the exact key grammar, byte and nesting bounds, supported version and type combinations, and operation-specific payload fields. The idempotency key is generated or derived from an already validated durable application identity; it is not accepted blindly from an untrusted request.

JSON text and decoded arrays are untrusted stored input. A named factory rejects invalid JSON, unknown fields, missing fields, explicit `null` where disallowed, incorrect runtime types, excessive bytes or nesting, unknown versions, unknown types, and invalid payload shapes. It returns one concrete final readonly value. PHP object serialization, class names in storage, reflection hydration, and arbitrary arrays crossing into a handler are forbidden.

Native `json_decode` retains the final value for a repeated object key. This recipe discloses that limit rather than claiming duplicate-key rejection; an application that requires duplicate-key detection needs a separately reviewed parser decision and evidence.

Dispatch is a finite code-owned `match` over the parsed type and version. Adding a job type or changing a payload requires an explicit envelope-version decision, parser and handler changes, migration or compatibility policy for stored jobs, and complete behavior evidence.

## One-delivery worker lifecycle

Each worker process or invocation performs exactly one bounded cycle:

1. compose a fresh SQLite connection, budgets and traces, clock, envelope parser, finite dispatcher, and concrete handler;
2. claim at most one eligible job in deterministic code-owned order;
3. commit a finite lease containing a fresh opaque token, expiry, and bounded attempt number through one short explicit transaction;
4. return `idle` and exit when no row is eligible;
5. parse and dispatch the claimed envelope;
6. finalize a successful idempotent database effect and completion together, or record one retry or dead-letter transition; and
7. emit one bounded redacted terminal result and exit.

A supervisor creates repetition by starting another process. The recipe deliberately has no long-running loop, reused container or connection, mutable state carried between deliveries, signal subsystem, automatic heartbeat, implicit retry, or graceful-stop protocol. Stopping cleanly means the supervisor does not launch the next invocation. ADR 025 keeps this composition application-owned: the accepted example routes the one-job operation through its sole console as `jobs:run-one`, and ADR 028's current `schedule:run` may call that exact in-process operation once under its explicit UTC cadence and Redis owner-token lease. Neither decision adds a framework command map, scheduler, process manager, or second job path.

## Claim, lease, and fencing

Only a pending row whose availability time has arrived, or an abandoned leased row whose lease has expired, is eligible. The SQLite-specific claim is one finite complete `UPDATE ... RETURNING` statement with explicit bindings and deterministic candidate ordering such as availability time followed by row identity. It runs inside one short explicit transaction. Claiming increments the bounded delivery attempt and writes a newly generated opaque lease token plus one finite expiry. The job table uses state-shape constraints and due-job indexes appropriate to its exact schema; application tests and production evidence verify those constraints and query plans on the deployed SQLite version.

Every later completion, retry, or dead-letter write is fenced by leased state, row identity, that exact lease token, and an expiry still later than the application's current time. A zero-row transition means the worker no longer owns the delivery and must not overwrite the state recorded by a later claimant. A previous worker that resumes after its lease expires therefore cannot acknowledge or reschedule the job even before a newer claimant appears.

The worker samples its explicit application clock again before handler work and after handler success or failure. Retry delay begins at the freshly observed failure time, and completion uses the freshly observed completion time; a claim-time snapshot is not sufficient to prove that the lease remains current after work.

The lease is a recovery boundary, not mutual-exclusion proof for the whole effect. If work exceeds the lease, another process may receive the same job while the first still runs. The application selects a lease longer than its measured ordinary work, bounds the process externally, and still makes the effect safe under duplicate and overlapping delivery. This recipe adds no lease-renewal helper.

## Idempotent database effect

At-least-once delivery means a job can run again after success when the process stops before durable completion is observed. The accepted proof gives the database effect a unique application-owned idempotency key. The handler begins an explicit SQLite transaction, records or observes that unique effect, and marks the currently leased job complete in the same transaction. If the effect was already recorded by an earlier delivery, the replay performs no second effect but still completes its current valid lease. A failed fenced completion rolls the transaction back.

This proves one durable database effect for duplicate deliveries in the exercised SQLite schema. It does not prove exactly-once execution, exactly-once external effects, universal concurrency safety, or correctness for another engine. An HTTP provider may process a request and lose the response; a worker may then retry. Provider-supported idempotency keys, a durable request/receipt model, reconciliation, compensation, and timeout ambiguity must be designed and tested per integration.

## Retry and dead-letter policy

An ordinary handler failure schedules one next eligible time from a finite application-owned backoff table while the attempt is below the accepted maximum. There is no random, unbounded, recursive, or in-process retry. The attempt limit includes every claimed delivery according to the application's recorded policy.

Once the maximum is reached, the job becomes a dead letter. The claim path also makes an expired final-attempt lease terminal so a process crash on the last permitted delivery cannot strand the row forever. Invalid JSON, a malformed or oversized envelope, an unsupported version, and an unsupported type are poison jobs and become dead letters without dynamic class resolution or arbitrary execution. A dead letter is terminal until an explicit application-owned inspection and replay operation chooses otherwise; this recipe does not silently replay it.

Retry and dead-letter state stores only a finite code-owned diagnostic code plus bounded scheduling metadata. Exception messages and external response bodies are untrusted and may contain credentials, personal data, payloads, SQL, filesystem paths, or other internals. They are never copied into durable job state or the terminal result. Any richer operational destination is separately application-owned, bounded, redacted, and failure-isolated.

## Required evidence

The application uses a real file-backed SQLite fixture and proves:

- a committed business transaction publishes exactly one claimable row;
- rollback leaves neither the business change nor a job row;
- a successful delivery records its database effect and completion atomically;
- duplicate delivery with the same idempotency key records one durable effect;
- a handler failure schedules the exact bounded delay and no early claim succeeds;
- an expired lease permits redelivery with a new token, while a stale or merely expired token cannot finalize it;
- the maximum attempt and an expired final-attempt lease become terminal dead letters;
- invalid JSON, malformed fields, unsupported version, and unsupported type cannot reach a handler and become redacted poison dead letters;
- a real subprocess terminated after claim is recovered by a fresh invocation only after deterministic lease expiry;
- multiple queued rows require multiple fresh subprocesses, each claiming and finalizing at most one;
- an empty queue returns the finite `idle` outcome;
- the finite terminal outcomes `idle`, `completed`, `retry_scheduled`, and `dead_lettered` use the recorded exit contract and one redacted bounded JSON line;
- output and durable diagnostics omit the envelope, payload, idempotency key, exception message, stack, SQL, bindings, DSN, credentials, and external response values; and
- every transition has an explicit query budget and bounded trace, with constant statement counts across materially different fixture cardinalities.

ADR 025 fixes the checked example's console mapping: every finite worker outcome exits `0` as one redacted `{"command":"jobs:run-one","outcome":"..."}` stdout line, while operational or unexpected failure exits `1` with only `{"error":"command_failed"}` on stderr. That is an application supervisor contract, not a framework CLI API. See [the application CLI and scheduler guide](cli.md).

## Unsupported boundary

PHPThis ships no job or envelope type, queue interface, dispatcher, worker loop, retry service, lease service, scheduler, process manager, command registry, event bus, transport adapter, broker integration, queue facade, ORM mapping, query builder, transaction callback, discovery convention, or exactly-once claim.

The example is evaluation evidence for one SQLite schema and execution model. Production adoption must prove the deployed SQLite runtime, filesystem and locking behavior, real concurrency, query plans and indexes, disk-full and corruption response, backup and restore, clock behavior, supervisor restart policy, capacity and retention, least-privilege identity, and operational dead-letter handling.

See [ADR 024](decisions/024-application-owned-sqlite-durable-jobs.md) for the accepted decision boundary.
