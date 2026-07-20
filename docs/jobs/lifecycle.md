# Durable-job lifecycle and fencing

Publication is commit-visible only when the business write and job insert use the same `Connection`, explicit transaction, and SQLite database. Commit exposes both; rollback leaves neither. Another connection, database, broker, or external service is outside that atomic boundary.

One fresh worker invocation claims zero or one due row with one complete SQLite `UPDATE ... RETURNING` statement in deterministic order. The claim writes a fresh opaque token, a finite expiry, and a bounded attempt number. Every completion, retry, and dead-letter transition matches the row identity, leased state, exact token, attempt number, and an expiry later than the freshly observed transition time.

Delivery is at least once. An expired lease can be reclaimed with a new token, while the stale owner must affect zero rows. The idempotent database effect and successful completion share one explicit transaction. Retry uses a finite code-owned schedule; maximum attempts and an expired final-attempt lease become terminal dead letters. Poison envelopes never dispatch and store only a finite redacted code.

The lease is not proof that work cannot overlap. A production application bounds execution externally, selects its lease from measured behavior, and keeps effects safe under duplicate delivery. External effects need their own idempotency, receipt, reconciliation, timeout, and compensation decisions.

See [the complete durable-jobs guide](../jobs.md) and [ADR 024](../decisions/024-application-owned-sqlite-durable-jobs.md).
