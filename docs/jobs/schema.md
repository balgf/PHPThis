# Durable-job SQLite schema

The accepted example owns two SQLite `STRICT` tables. `application_jobs` stores the bounded envelope and lifecycle metadata; `welcome_deliveries` is the concrete idempotent-effect proof. They are application schema, not PHPThis core migrations or a portable queue model.

`application_jobs` constrains identity and lease tokens to lowercase hexadecimal, the envelope to 2–2,048 bytes, timestamps to non-negative integers, the status and failure codes to finite sets, and claimed deliveries to exactly the recorded maximum of three. Its row-shape check permits only:

- `available`: no lease, completion, or dead timestamp, with another start remaining;
- `leased`: one to three claimed deliveries, a token and expiry, and no terminal timestamp;
- `succeeded`: no lease, one completion timestamp, and no dead timestamp; or
- `dead`: no lease, one dead timestamp, and one finite redacted failure code.

One partial index orders available rows by `available_at`, creation time, and identity. A second partial index orders leased rows by expiry, creation time, and identity. The claim statement still owns its complete deterministic order and state transition; indexes are reviewed execution support, not business logic or proof of a production query plan.

`welcome_deliveries` gives the semantic idempotency key a unique primary key and stores only the bounded fields required by this local database effect. A duplicate insert affects zero rows and is followed by fenced completion in the same explicit transaction. This schema does not make an external action exactly once.

Applications own DDL rollout, exact SQLite feature level, compatibility with already stored envelopes, online or offline migration policy, indexes and query-plan evidence, retention, backup, restore, and rollback. PHPThis supplies no migration runner, schema builder, ORM mapping, repository, SQL generator, or dialect translation.

See [the complete durable-jobs guide](../jobs.md), [lifecycle and fencing](lifecycle.md), and [ADR 024](../decisions/024-application-owned-sqlite-durable-jobs.md).
