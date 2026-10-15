# MySQL welcome-work reference

Current policy and limits: [application-owned MySQL durable work](../../docs/jobs/mysql.md). This is a maintainer-only executable reference for #107, with synthetic data and disposable services. It adds no framework runtime or dependency.

Run `python3 tools/mysql-jobs/run.py` from the checkout after installing locked Composer dependencies and pulling the pinned image documented in the guide. The runner owns provisioning, three credential roles, process bounds, crash probes and cleanup. Keep Docker available until cleanup completes. Never point the PHP verifier at an existing database: its literal fixture schema is destructive during tests.

The application path is `console.php → MySqlWelcomeJobWorker → RecordMySqlWelcomeDelivery`, with direct constant SQL through `Connection`. Publication is the separate business operation `PublishWelcomeRequest`. The `claimOne`/`finishClaim` seams support controlled process evidence; the normal console calls only `runOne`.

`composer check` in this directory requires real fixture configuration and runs the fixed `tools/verify-jobs.php` entrypoint. `tools/probe-worker.php` and `tools/processes.py` are verifier-only controls; they are not alternate application commands. Their pauses and privileged expiry edits exist only to establish crash/concurrency boundaries. The root gate also checks source structure and verifier failure behavior without claiming a MySQL result.

[Retained live evidence](evidence.json) records the tested engine/client, source hashes, 1/501-row samples, two-process concurrency and successful owned-resource cleanup. It covers the cases in the guide; deployment durability and throughput remain separate work.
