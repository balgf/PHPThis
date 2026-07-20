# Example durable-job source

This directory is application-owned evidence for ADR 024, not framework runtime code. `UserWelcomeJobEnvelope` owns one bounded versioned stored shape, `SqliteUserWelcomeJobWorker` performs one SQLite-specific claim and fenced transition, and `RecordUserWelcomeDelivery` demonstrates one idempotent database effect. ADR 025's sole `example/bin/console.php` entrypoint exposes that operation as `jobs:run-one`; `schedule:run` calls the same in-process operation at most once on a due locked pass.

Read `example/.ai/jobs.md`, `docs/jobs.md`, and ADR 024 before changing this path. Preserve same-`Connection` publication, finite dispatch, fresh-time lease fencing, at-least-once semantics, one delivery per process, redacted diagnostics, and the explicit statement budgets in `tests/jobs.php`.

Do not add an ORM, repository, query builder, SQL or binding helper, queue facade, event bus, automatic command or job discovery, transaction callback, generic worker or scheduler loop, broker abstraction, second job entrypoint, or exactly-once claim.
