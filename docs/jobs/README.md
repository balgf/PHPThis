# Durable-job knowledge index

Use this index when a task concerns deferred work. PHPThis currently accepts the optional [backend-neutral contract](../jobs.md) and [verification structure](verification.md) under ADR 052. Its first and only checked backend-specific profile remains [application-owned SQLite durable jobs](sqlite.md) under ADR 024.

Current optional common route:

- [Backend-neutral contract](../jobs.md): exact adoption record, publication and recovery, envelope, real delivery semantics, effects, retry, lifecycle, operations, checker boundary and optional-profile model.
- [Verification structure](verification.md): backend-neutral evidence matrix, one application-owned `jobs:verify` entrypoint, three explicit modules and complete-gate wiring.

Current accepted SQLite profile:

- [Envelope and dispatch](envelope.md): stored shape, parsing, versioning, and finite handler selection.
- [Lifecycle and fencing](lifecycle.md): publication, claim, lease, retry, completion, and dead-letter state.
- [SQLite schema](schema.md): accepted row shapes, constraints, indexes, and migration boundary.
- [Externally supervised one-shot operations](operations.md): canonical production topology, direct continual consumption, supervisor policy, SQLite capacity, monitoring, recovery, and reconsideration triggers.
- [Testing](testing.md): mandatory behavior and process-failure evidence.

The five profile slices above and [the complete SQLite profile](sqlite.md) describe one application-owned SQLite recipe. Do not generalize its transaction, `UPDATE ... RETURNING`, lease, one-shot process, SQL statement bounds or exact outcomes to another backend. Neither route adds a PHPThis core API, ORM, repository, queue facade, event bus, discovery mechanism, transaction callback, transport adapter or exactly-once guarantee.
