# Application-owned MySQL durable welcome work

Use this optional recipe when the business write and deferred effect belong in the same MySQL database. The concrete operation accepts a welcome request, then records one welcome-delivery receipt. It sends no email. Source, schema, composition and real-service proofs live in the maintainer [MySQL reference](https://github.com/balgf/PHPThis/tree/main/tools/mysql-jobs); they are not installed runtime APIs. The [common jobs contract](../jobs.md) still governs deliberate application adoption.

## Execution and failure boundaries

`PublishWelcomeRequest` inserts `welcome_requests` and `application_jobs` using one `Connection` and transaction. Report success only after commit. An exception or lost acknowledgement can mean the commit outcome is unknown: retain the original semantic identity, inspect the committed business/job pair, and reconcile before retrying. An absent lookup while the original transaction can still commit is not proof of failure. The producer process-death proof exercises a committed pair whose caller receives no business acknowledgement.

`console.php jobs:run-one` constructs fresh configuration, connection, budget, trace, clock, handler and worker. It claims at most one row in a short transaction with `SELECT ... FOR UPDATE SKIP LOCKED`, then updates and reads its lease. This is MySQL SQL; it does not use SQLite's `UPDATE ... RETURNING`.

The delivery transaction locks the exact job, token and attempt, reads the database clock after acquiring that lock, checks expiry, records the idempotent receipt, and completes the job with a fenced update affecting exactly one row. Failure or zero affected rows rolls back the effect. A separate bounded transition schedules a handler failure or dead-letters invalid input. A crashed third attempt becomes dead when a later invocation observes expiry. No background reaper is required; consumption must resume for recovery to occur.

The handler verifies the previously committed request before writing. The receipt's primary key is the v1 SHA-256 identity of the exact email string; duplicate deliveries share that key and must match the recorded email. This proves a database effect and its completion in one transaction. External email, HTTP and storage effects need their own idempotency/reconciliation design. They cannot inherit this transaction's atomicity.

## Checked profile and bounds

| Property | Reference policy |
| --- | --- |
| Service/client | MySQL **8.4.11**, PHP **8.4.19**, PDO MySQL/mysqlnd **8.4.19**; pinned container digest in the reference runner |
| Storage/isolation | InnoDB, READ COMMITTED, binary logging enabled with ROW format, `innodb_flush_log_at_trx_commit=1`, `sync_binlog=1`, doublewrite ON; fail closed on drift at composition |
| Claim index | Stored `next_action_at` (availability or lease expiry; NULL for terminal rows), index `(next_action_at, job_id)`; primary key on job ID |
| Envelope | Existing explicit `user.welcome` v1 parser: 2–2,048 JSON bytes, exact fields, email at most 254 bytes, bounded JSON depth; unsupported/malformed input is terminal |
| Publication delay | Integer seconds, 0–86,400 inclusive; invalid bounds rejected before SQL |
| Clock/lease | MySQL `UNIX_TIMESTAMP()` in whole seconds; 30-second lease, fresh opaque 128-bit token per claim, no renewal/heartbeat |
| Attempts | At most three started attempts, including crashes; handler retry delays 5 then 30 seconds; fixed redacted terminal reason |
| Process | One delivery; 32-statement budget including session checks, trace capacity 32; lock waits 2 seconds, read-only SELECT timeout setting 1 second, external wall limit 5 seconds |
| Supervisor | At most two concurrent processes; at least one second between starts per slot; TERM on deadline/shutdown, KILL after 0.5 seconds, reap within one second; no new claim after stopping |
| Ordering | Order-independent welcome receipts. Due-order selection is best effort; locked rows are skipped, and no FIFO/fairness guarantee is made |

Database time is the single lease authority. Backward time relative to a sampled operation fails closed; forward jumps can expire leases early. Require a stable service clock and stop consumers for significant clock changes. Locks and tokens fence these database transitions, not other systems. The native JSON parser does not reject duplicate object members before decoding; no stronger raw-JSON claim is made.

MySQL documents [locking reads and SKIP LOCKED](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html), [READ COMMITTED](https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html), and [flush settings](https://dev.mysql.com/doc/refman/8.4/en/innodb-parameters.html#sysvar_innodb_flush_log_at_trx_commit). Deadlocks and outages still fail an invocation; there is no implicit reconnect or SQL retry. The supervisor owns bounded restart pacing.

## Authority, retention and adoption

The reference uses synthetic addresses and a system-owned committed obligation. It has no tenant, user session, revocable grant, scheduler or external provider. A real operation must define its publication authorization and any revalidation before effects.

Producer and worker credentials are separate. The worker can read obligations and update only delivery-state columns; it cannot publish, delete or change schema. The verifier alone owns provisioning and teardown authority. Credentials enter through one literal-key environment reader and private process-specific values, with no privileged fallback. The disposable fixture uses loopback TCP without a TLS claim; deployment TLS, credential rotation, network policy and encryption at rest require application evidence.

Requests, jobs and receipts remain together for the fixture's entire lifetime, including terminal rows; there is no selective purge, replay, cancel or administrative console. Teardown destroys the whole isolated database after workers stop. A real adoption must select retention, bounded capacity/backpressure, lag/dead-letter alerts and an authorized audited operator procedure. Preserve receipts beyond every permitted retry/replay horizon. Restore the matching business, queue and receipt state together; stop consumers and reconcile inconsistencies before resuming. The mismatch test rejects a conflicting receipt, but it is not a backup/restore drill.

Use the application's explicit schema provisioning/migration path before enabling publishers, then consumers. On replacement, stop new starts, terminate/reap old processes within the bound, and start fresh processes with validated configuration. Do not automatically run this fixture DDL in HTTP or worker startup.

## Evidence and how to run it

From a framework source checkout with locked Composer dependencies, PHP 8.4.19/PDO MySQL, Python 3 and Docker:

```sh
docker pull mysql:8.4@sha256:0744ee5ef89ce6ccfa13de3e579fe6b9e27f93dd70da9c06d2c908b1b193fb8d
python3 tools/mysql-jobs/run.py
```

Before destructive SQL, the verifier checks the run identity, owned container, loopback port and all three DSNs. The runner creates a unique loopback-only service and temporary least-privilege credentials, invokes the reference's `composer check → test → jobs:verify`, and removes its own container and volume in `finally`. The three literal PHP modules cover publication, delivery and operations; missing configuration fails with `JOBS VERIFY FAIL`, never a skip. The literal reference bootstrap loads checkout classes; an adopting application supplies its own Composer/bootstrap path and copies the operation-specific ownership into its own code. Installing PHPThis alone does not install or execute this reference.

Live cases include rollback, delayed visibility, producer death after commit, independent concurrent claims and duplicate receipts, SKIP LOCKED, stale and expired workers, poisoned payloads, affected-row fencing, bounded retries, worker crash/deadline recovery, the real 30-second final-attempt expiry, finite supervisor pacing, restricted credentials and MySQL process kill/restart on the same volume. Other expiry cases use explicit fixture-only expiry edits. The process restart preserves committed pairs and discards an unfinished pair. The 1-row and 501-row queue samples retain the same statement count (at most 20 for a successful delivery), and the larger sample uses the due index. This measures statement count, not throughput. Host/power failure, lost storage, replicas, failover, partitions and production throughput remain unproved.

An abandoned fixture is eligible after 20 minutes. The runner owns exact-ID reconciliation: `python3 tools/mysql-jobs/run.py --cleanup-run RUN_ID` verifies owner/identity/expiry labels before removing that container and its volume. It never scans or deletes unrelated services. The retained evidence and CI job identify the tested source and engine. Root `composer check` covers the reference's source and fail-closed checks; the separate MySQL CI job supplies real-service evidence.
