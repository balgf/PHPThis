# ADR 024: Application-owned SQLite durable jobs

Status: accepted

## Context

An application can require deferred work to survive termination after an independently meaningful business write. Running the work in the request extends latency and couples availability. Committing the business write and then enqueueing separately creates a loss window: the process can stop after commit but before the job is durable. A callback that hides enqueueing after commit does not close that window, and an in-memory event or after-response action is not durable delivery.

PHPThis has explicit `Connection` transactions, finite constant SQL, query budgets, and bounded traces, but no queue, event bus, worker, scheduler, command registry, ORM, discovery mechanism, or production process topology. Adding a generic abstraction before application evidence would conceal materially different broker, database, lease, concurrency, retry, and failure semantics.

## Decision

The first durable-job proof is entirely application-owned and SQLite-specific. It adds no framework core type, runtime dependency, CLI command, queue facade, dispatcher, event bus, discovery mechanism, transaction callback, ORM, query builder, repository, SQL/binding/placeholder helper, or transport abstraction. Consumer Contract version 5 and Strict Profile version 2 remain unchanged.

The producer inserts one bounded versioned JSON job envelope through the same `Connection` and in the same explicit SQLite transaction as the business write that requires it. The job is invisible to another claimant until commit. Rollback removes both changes. This atomicity applies only to that one connection and database; it does not extend to another connection, database, broker, or external service.

The envelope contains a finite code-owned type, explicit version, bounded application-generated idempotency key, and operation-specific payload. Stored JSON is untrusted and is parsed once through a named factory into a concrete final readonly job value. Missing, unknown, malformed, oversized, unsupported-version, and unsupported-type data cannot reach a handler. Dispatch uses a finite `match`; storage never selects a PHP class or service dynamically.

One application-owned worker process or invocation composes fresh dependencies and claims at most one due job. One finite complete SQLite `UPDATE ... RETURNING` statement and explicit bindings claim the deterministically ordered candidate inside a short explicit transaction, recording a finite lease expiry, a fresh opaque lease token, and a bounded attempt count. The worker samples its explicit application clock again before handler work and after handler success or failure; a claim-time snapshot is not accepted as evidence that the lease remains current. Every completion, retry, or dead-letter transition is conditional on leased state, row identity, the current token, and an unexpired lease so a stale worker cannot overwrite a later lease or finalize an already expired one. The claim path terminates an expired final-attempt lease so a crash cannot strand it. An idle invocation performs no handler work and exits. Repetition belongs to an external supervisor, not a framework or in-process worker loop.

Delivery is at least once. An expired lease is eligible for a fresh claim, including after forced process termination. The accepted database-effect proof uses a unique idempotency key and one explicit SQLite transaction to combine the idempotent effect with completion of the currently leased job. A duplicate delivery observes the prior effect rather than creating another. This does not claim exactly-once execution or exactly-once external effects.

Ordinary handler failure follows one finite application-owned backoff table until a finite maximum attempt count is reached. The example lease is 30 seconds and permits three claimed deliveries: failure after attempt one waits 5 seconds, failure after attempt two waits 30 seconds, and failure or lease expiry on attempt three becomes a dead letter without incrementing beyond three. A claim consumes an attempt even when a crash, poison envelope, or pre-handler lease expiry prevents a handler start. All scheduling values are integer Unix seconds from the injected application clock, and retry delay begins at the freshly observed failure time rather than the earlier claim time. Invalid envelopes and unsupported finite dispatch choices are poison jobs and become dead letters without arbitrary execution. Retry and dead-letter records contain only a finite redacted diagnostic code and bounded scheduling metadata—never exception messages, stacks, SQL, bindings, envelopes, payloads, credentials, or external response bodies.

The example's finite terminal outcomes are `idle`, `completed`, `retry_scheduled`, and `dead_lettered`. ADR 025 later records the application-owned console that maps all four to exit `0` and a redacted stdout line under `jobs:run-one`; operational or unexpected failure maps to exit `1` and one generic stderr line. Its `schedule:run` command calls the same operation directly for one due pass. This remains application supervisor evidence, not a PHPThis CLI, scheduler, or process-management contract.

Tests use a file-backed SQLite database and real subprocesses where lifecycle evidence matters. They prove commit and rollback publication boundaries; success; idempotent duplicate delivery; bounded retry timing from a freshly observed failure; completion rollback after the clock advances to lease expiry; lease expiry, new-token redelivery, and stale or expired-token fencing; maximum-attempt, expired-final-lease, and poison-job dead-lettering; forced termination after claim followed by post-expiry recovery; idle behavior; multiple jobs requiring multiple fresh processes; one terminal line per invocation; complete diagnostic redaction; and explicit statement budgets with constant counts for every exercised transition.

## Consequences

The application owns more schema, complete SQL, envelope parsing, finite dispatch, clock policy, supervisor configuration, and failure tests than it would with a general queue library. Those responsibilities remain visible at the operational boundary where their semantics can be reviewed.

Keeping publication in the business transaction removes the commit-to-enqueue gap, but job-table writes now participate in the business database's locking, capacity, backup, migration, and failure domain. SQLite worker concurrency and throughput are deliberately not generalized. Production adoption must prove the exact runtime, filesystem, locking and busy-timeout behavior, indexes and plans, retention, disk and corruption response, backup and restore, process supervision, least privilege, and dead-letter operations.

A finite lease enables crash recovery but can also permit overlapping delivery when work outlives it. Idempotency remains required. Database transaction evidence cannot make a remote API, email, filesystem write, or other external effect exactly once; each integration needs an explicit timeout, provider-idempotency, durable receipt, reconciliation, and compensation decision.

No Consumer Contract, Strict Profile, framework core, generic job lifecycle, reusable worker API, or cross-engine queue claim is introduced.

## Reconsider when

At least two independent applications prove the same smaller job boundary under materially different workloads and failure modes, or the SQLite proof shows a concrete core defect that cannot remain application-owned. Reconsider one narrow evidence-backed contract without hiding SQL, transaction ownership, envelope versions, lease semantics, retry cost, dispatch, external-effect ambiguity, or process lifecycle behind a generic queue abstraction.
