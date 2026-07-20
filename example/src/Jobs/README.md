# Example durable-job source

This directory is application-owned evidence for ADR 024, not framework runtime code. `UserWelcomeJobEnvelope` owns one bounded versioned stored shape, `SqliteUserWelcomeJobWorker` performs one SQLite-specific claim and fenced transition, and `RecordUserWelcomeDelivery` demonstrates one idempotent database effect. `example/bin/run-one-job.php` composes one fresh invocation.

Read `example/.ai/jobs.md`, `docs/jobs.md`, and ADR 024 before changing this path. Preserve same-`Connection` publication, finite dispatch, fresh-time lease fencing, at-least-once semantics, one delivery per process, redacted diagnostics, and the explicit statement budgets in `tests/jobs.php`.

Do not add an ORM, repository, query builder, SQL or binding helper, queue facade, event bus, automatic discovery, transaction callback, generic worker loop, broker abstraction, or exactly-once claim.
