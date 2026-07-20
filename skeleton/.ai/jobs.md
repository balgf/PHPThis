# Application durable-job contract

`NOT_APPLICABLE(JOBS)`: the health-only starter performs no deferred work and has no job table, envelope, worker, lease, retry, dead letter, scheduler, supervisor, or external side effect.

Before adopting jobs, read installed `vendor/phpthis/framework/docs/jobs.md` and ADR 024, then replace this statement with the application-owned backend, transaction, envelope, idempotency, lease, retry, dead-letter, lifecycle, redaction, and test decisions. PHPThis supplies no core queue or worker API.

Do not introduce a queue abstraction in anticipation of future use. Never claim cross-connection atomicity or exactly-once external effects.
