# Durable-job knowledge index

Use this index when a task concerns deferred work. Read [the complete durable-jobs guide](../jobs.md) and ADR 024 before changing the accepted recipe; these smaller pages make the relevant invariant easier for an AI author to retrieve without inventing a framework queue.

- [Envelope and dispatch](envelope.md): stored shape, parsing, versioning, and finite handler selection.
- [Lifecycle and fencing](lifecycle.md): publication, claim, lease, retry, completion, and dead-letter state.
- [SQLite schema](schema.md): accepted row shapes, constraints, indexes, and migration boundary.
- [Operations](operations.md): SQLite authority, one-shot supervision, retention, recovery, and proof limits.
- [Testing](testing.md): mandatory behavior and process-failure evidence.

All pages describe one application-owned SQLite recipe. They add no PHPThis core API, ORM, repository, queue facade, event bus, discovery mechanism, transaction callback, or exactly-once guarantee.
