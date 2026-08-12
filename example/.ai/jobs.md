# Example durable-job context

Adoption classification: `SQLITE_ADR024_REFERENCE`. The executable example follows the current accepted ADR 024 profile as one application-owned SQLite proof. Accepted ADR 052 does not retroactively convert it into another backend adoption or `jobs:verify` proof, and the example defines no reusable queue API or production-readiness promise.

## Backend and publication

- Backend: SQLite through the current `ext-pdo_sqlite` runtime and the same file-backed database used by the example's business data. The example does not pin or certify an exact deployable SQLite application version; a production adopter records and tests its exact version, filesystem, journal, synchronization and locking facts.
- Topology and security: local application/test files only. No network service, broker client, TLS, remote credential, producer/consumer service identity, multi-tenant broker namespace, replication or failover applies. File and process authority remain application/test-owned and are not production least-privilege evidence.
- Publication: the business write and its job row use the same `PHPThis\Database\Connection` and one explicit transaction. Commit publishes both; rollback leaves neither. No second connection, broker, callback, or event bus participates.
- SQL: every producer, claim, completion, retry, dead-letter, and idempotent-effect statement is complete SQLite SQL at its direct `Connection` call with an explicit named parameter array.
- Envelope: bounded JSON with version `1`, one finite example-owned type, an application-generated idempotency key, and a strictly parsed operation-specific payload. Unknown or malformed fields, versions, types, and payloads never select a handler dynamically.

## Worker lifecycle

- One invocation composes a fresh connection, budgets and traces, clock, parser, finite dispatcher, and concrete handler.
- It claims zero or one due row in deterministic order through one constant SQLite `UPDATE ... RETURNING` statement and commits a fresh opaque lease token, finite expiry, and bounded attempt number in one short transaction. Scheduling uses integer Unix seconds from the injected clock.
- It samples that clock again before handler work and after handler success or failure. Completion, retry, and dead-letter updates require leased state, the row identity, current token, and a lease unexpired at that fresh transition time. Retry delay begins at the fresh failure time. An expired worker cannot finalize the job even before another claimant appears.
- A successful handler records one idempotent database effect and completion in one explicit SQLite transaction. Replaying the same idempotency key produces no second effect.
- Every lease lasts 30 seconds. At most three claimed deliveries are permitted: failure after attempt one schedules 5 seconds, failure after attempt two schedules 30 seconds, and failure or lease expiry on attempt three becomes a dead letter without incrementing beyond three. A claim consumes an attempt even when a crash, poison envelope, or pre-handler lease expiry prevents a handler start.
- Invalid JSON, malformed envelopes, unsupported version, and unsupported type are poison jobs and become dead letters immediately with finite code-owned diagnostic codes; they are not retried.
- `bin/console.php jobs:run-one` is the only direct worker command. It maps `idle`, `completed`, `retry_scheduled`, or `dead_lettered` to one `{"command":"jobs:run-one","outcome":"..."}` stdout line and exit `0`; operational or unexpected failure maps to the generic `command_failed` stderr line and exit `1`.
- `bin/console.php schedule:run` calls this exact in-process one-job operation at most once on a due pass under `.ai/cli.md`'s explicit clock and Redis owner-token lease. It does not enqueue, spawn, or select another worker path.
- The process exits after that single result. A supervisor repeats invocations. Clean stopping means not launching another invocation; the example has no worker loop, heartbeat, signal subsystem, reused connection, alternate worker entrypoint, or generic command map.

## Redaction

Durable diagnostics and terminal output contain only the finite result, code-owned diagnostic code when applicable, and bounded non-sensitive operational metadata. They omit job envelopes, payload values, idempotency keys, exception messages, stack traces, SQL, bindings, DSNs, credentials, request data, customer data, and external response bodies.

## Evidence and limits

Focused evidence runs with `composer test -- --group jobs`. The repository's ordinary `composer test` includes that group and the complete `composer check` invokes `composer test`. This established ADR 024 profile evidence is not an ADR 052 `jobs:verify` adoption and does not claim another backend.

The example's file-backed SQLite tests prove:

- commit publishes exactly one claimable job and rollback publishes none;
- success records the effect and completion atomically;
- duplicate delivery records one durable effect;
- failure schedules the exact bounded backoff and cannot be reclaimed early;
- an expired lease redelivers with a new token and rejects stale or expired-token finalization;
- the maximum attempt, an expired final-attempt lease, and every poison-envelope case become redacted dead letters;
- handler-time clock advancement to lease expiry rolls back the database effect, and failure-time advancement anchors the retry delay;
- a real subprocess terminated after claim is recovered by a fresh post-expiry invocation;
- multiple queued rows require multiple fresh subprocesses and each invocation handles at most one;
- an empty queue returns `idle` without handler work;
- both console commands emit one redacted result with the recorded exit and stream contract; and
- each transition stays within its explicit query budget and bounded trace across small and materially larger fixtures.

This proves at-least-once delivery and one idempotent database effect only for the exercised SQLite schema. ADR 028's Redis schedule lease may reduce overlapping scheduled passes but does not replace the SQLite claim token, fresh expiry checks, transaction, or idempotency. It does not prove exactly-once execution, exactly-once external effects, cross-database atomicity, production SQLite concurrency or capacity, Redis fencing, automatic replay safety, or MySQL/PostgreSQL job behavior.

The accepted ADR 052 common contract and `jobs:verify` guidance do not retroactively turn this example into a portable adoption, require a new example command, or replace ADR 024's evidence.

Forbidden in this example: framework-core job types, ORM, Active Record, repository, query builder, SQL/binding/placeholder helper, transaction callback, queue facade, generic dispatcher, event bus, runtime or command discovery, dynamic class resolution, service container, hidden retry or polling loop, long-running worker state, generic scheduler facade, and a coordination claim stronger than ADR 028. ADR 025 and ADR 028 keep the console and Redis lease application-owned.
