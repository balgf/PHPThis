# Externally supervised one-shot durable jobs

This is the focused canonical operations guide for continual consumption of the accepted application-owned SQLite durable-job recipe. [The complete checked SQLite profile](sqlite.md) remains its decision and correctness guide. The separate accepted [backend-neutral contract](../jobs.md) supplies optional common operations requirements without importing this one-shot lifecycle. PHPThis supplies no queue runtime, daemon, scheduler, supervisor abstraction, deployment unit, or process manager.

## Production topology

Continual consumption runs the application's one-delivery operation directly under an external supervisor. In the accepted example that operation is the application-owned `jobs:run-one` command. Its name is evidence for that application, not a PHPThis command that every consumer must copy.

The supervisor is long-running; each PHP worker process is one-shot:

```text
external supervisor (long-running and separately owned)
  repeat only while the recorded worker slot is enabled
    start one fresh PHP process: application console jobs:run-one
    wait no longer than the recorded process timeout
    collect the one finite redacted result and exit status
    if stopping, do not start another child
    otherwise wait according to the recorded bounded pacing policy

one PHP worker process
  compose fresh application dependencies
  claim and finalize zero or one delivery
  emit one finite redacted result
  exit
```

Each invocation creates a fresh connection, query budget, bounded trace, clock, finite dispatcher, and concrete handler; it claims and finalizes zero or one delivery, emits one finite redacted result, and exits.

The accepted example's `schedule:run` is different. It is a bounded scheduled pass that may call the same one-delivery operation once when its explicit UTC cadence is due. It is not the ordinary queue-draining worker. A deployment that needs continual consumption supervises `jobs:run-one` directly rather than increasing the scheduler cadence or treating the scheduler as a daemon.

Every expected `jobs:run-one` outcome—`idle`, `completed`, `retry_scheduled`, and `dead_lettered`—emits one newline-terminated bounded redacted JSON object to stdout and exits `0`. Operational or unexpected failure exits `1` with one generic redacted stderr object, while invalid console input exits `2`. A supervisor configured to restart only after failure therefore stops after the first expected result and does not provide continual consumption. The recorded policy must launch the next fresh process after expected exit `0` as well as define what happens after failure.

## Recorded supervisor policy

Before production adoption, the accountable application owner records:

- the separately owned supervisor and the location and owner of its configuration, without making a named process manager or platform part of PHPThis;
- the exact application console invocation, deployment identity, configuration source and redaction boundary, and access to the selected database path;
- the invocation or restart delay, worker-slot count, total concurrency limit, process timeout, and forced-termination policy;
- the behavior after each expected exit `0`, operational exit `1`, invalid-input exit `2`, signal, and externally forced termination;
- the deployment replacement and configuration-change behavior, including when old children stop and fresh composition begins;
- the shutdown behavior and the finite allowance before a current child is terminated; and
- restart-storm protection for repeated startup, configuration, database, or other operational failures.

Every enabled slot uses a positive bounded idle delay or equivalent supervisor pacing before another invocation. An empty queue must not cause an uncontrolled hot restart loop. PHPThis does not prescribe one universal interval: the application chooses and proves a bound against its latency objective, process-start cost, failure behavior, and deployed capacity. Failure backoff or rate limiting remains distinct from ordinary idle pacing and is also application-owned.

Clean stopping has three steps: stop launching new invocations, allow the current child to finish or finitely terminate it according to the recorded policy, and verify that no next claim starts. If a child is terminated after claiming, its delivery is not immediately available merely because the process ended. Recovery relies on the existing finite SQLite lease: a later fresh process may reclaim the row only after lease expiry, and stale or expired lease tokens cannot finalize it. At-least-once delivery and the application-owned idempotency requirements still apply.

## SQLite capacity and signals

The accepted recipe uses SQLite `STRICT` tables and `UPDATE ... RETURNING`. The application records and tests the exact deployed SQLite version, database path, local filesystem topology, journal and synchronization policy, busy timeout, one-writer behavior, supported worker concurrency, and least-privilege process identity.

SQLite permits one writer at a time. Claim, handler-owned database effects, retry or dead-letter transitions, and completion all contend for that writer. A busy timeout bounds waiting; it does not add write capacity. Increasing supervisor slots is therefore not assumed to improve throughput and may increase contention or latency. Every concurrency choice must be measured on the deployed SQLite version, filesystem topology, journal and synchronization settings, and representative workload. The repository proves behavior on file-backed fixtures; it does not certify a network filesystem, production power-loss guarantees, real deployment concurrency, capacity, or another database engine.

The application owns bounded capacity and health signals for:

- queue depth and oldest-due-job age;
- per-process and per-delivery processing duration;
- counts of the finite `idle`, `completed`, `retry_scheduled`, and `dead_lettered` outcomes;
- operational failures and forced terminations; and
- dead-letter count and growth.

The application records alert thresholds and response ownership. Queries, stored diagnostics, logs, metrics, and terminal output remain bounded and redacted: they exclude envelopes, payloads, exception messages, stacks, SQL, bindings, DSNs, credentials, idempotency keys, customer data, and external response bodies. Queue inspection, completed-row and dead-letter retention, explicit replay and cancellation, schema rollout, backup and restore, disk-full response, corruption response, and recovery drills also remain application-owned.

## Required production evidence

Repository behavior tests prove the one-delivery operation; a production adopter additionally proves that its actual supervisor and deployment:

- launch another fresh process after a successful expected exit `0`;
- drain multiple queued jobs through multiple fresh processes, with each process claiming and finalizing at most one delivery;
- apply the recorded bounded idle pacing when no row is eligible;
- stop without launching another claim after stop begins;
- recover a forcibly terminated delivery only after its finite lease expires;
- respect the configured worker-slot, total-concurrency, process-timeout, and forced-termination bounds; and
- raise the recorded queue-depth, oldest-due-age, duration, operational-failure, and dead-letter-growth capacity alarms.

The evidence also measures useful throughput and writer contention at every supported slot count on the exact deployed SQLite and filesystem topology. It does not replace the durable-job behavior evidence for publication, envelope parsing, finite dispatch, retries, fencing, idempotency, redaction, external-effect ambiguity, or bounded statement counts.

## Reconsideration boundary

Reconsider this topology only when measured evidence shows one or more of the following:

- queue-age or throughput objectives remain unmet after the application tunes and proves one-shot supervision;
- PHP startup and fresh-composition cost materially dominates delivery work; or
- independent applications reproduce the same smaller lifecycle need.

A bounded multi-delivery process or an indefinite worker loop requires a separate accountable decision and evidence. It is not a small extension of `jobs:run-one`: it introduces polling and idle waiting, signal handling, graceful shutdown, resource and memory recycling, connection failure and recovery, deployment and configuration reload behavior, and mutable process state across deliveries. A design that places `Connection` database calls inside its delivery loop also conflicts with Strict Profile diagnostic `PHT003`, which forbids database I/O inside loops. Such a proposal must preserve or separately change that checked boundary rather than bypass it.

External effects retain their weaker boundary under every topology. Provider idempotency, durable request and receipt state, timeout-ambiguity handling, reconciliation, and compensation remain separately designed and proved per integration. Nothing in external supervision upgrades at-least-once delivery to exactly once.

See [the complete checked SQLite profile](sqlite.md), [the application CLI and scheduler guide](../cli.md), [Strict Profile](../strict-profile.md), and [ADR 024](../decisions/024-application-owned-sqlite-durable-jobs.md).
