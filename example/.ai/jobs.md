# Example durable-job context

The executable example follows ADR 024 as one application-owned SQLite proof. It does not define a reusable queue API or promise production readiness.

## Backend and publication

- Backend: the same file-backed SQLite database used by the example's business data.
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
- Each invocation emits exactly one bounded redacted JSON result: `idle`, `completed`, `retry_scheduled`, or `dead_lettered`. Expected outcomes exit `0`; unexpected bootstrap or worker failures exit `1`.
- The process exits after that single result. A supervisor repeats invocations. Clean stopping means not launching another invocation; the example has no worker loop, heartbeat, signal subsystem, reused connection, or general command map.

## Redaction

Durable diagnostics and terminal output contain only the finite result, code-owned diagnostic code when applicable, and bounded non-sensitive operational metadata. They omit job envelopes, payload values, idempotency keys, exception messages, stack traces, SQL, bindings, DSNs, credentials, request data, customer data, and external response bodies.

## Evidence and limits

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
- every invocation emits one redacted result with the recorded exit status; and
- each transition stays within its explicit query budget and bounded trace across small and materially larger fixtures.

This proves at-least-once delivery and one idempotent database effect only for the exercised SQLite schema. It does not prove exactly-once execution, exactly-once external effects, cross-database atomicity, production SQLite concurrency or capacity, automatic replay safety, or MySQL/PostgreSQL job behavior.

Forbidden in this example: framework-core job types, ORM, Active Record, repository, query builder, SQL/binding/placeholder helper, transaction callback, queue facade, generic dispatcher, event bus, runtime discovery, dynamic class resolution, service container, hidden retry loop, and long-running worker state. Issue #8 owns any general application command-map or scheduler investigation.
