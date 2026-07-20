# Durable-job operations

The accepted example uses SQLite `STRICT` tables and `UPDATE ... RETURNING`; an application records and tests the exact deployed SQLite version that supports its schema and statements. It also records the database path, local filesystem topology, journal and synchronization policy, busy timeout, one-writer behavior, supported worker concurrency, and least-privilege process identity.

Each invocation creates a fresh connection, query budget, bounded trace, clock, finite dispatcher, and concrete handler; it processes at most one delivery, emits one bounded redacted outcome, and exits. A separately owned supervisor supplies repetition, interval, concurrency, timeout, forced termination, restart, deployment, and stop behavior. No in-process polling loop or framework process manager is implied.

Operations must own queue-depth and oldest-due-age capacity signals, completed-row and dead-letter retention, inspection, explicit replay and cancellation, schema rollout, backup and restore, disk-full response, corruption response, and recovery drills. Diagnostic state and output exclude envelopes, payloads, exception messages, stacks, SQL, bindings, DSNs, credentials, and external response bodies.

The repository proves behavior on file-backed fixtures. It does not certify a network filesystem, production power-loss guarantees, real deployment concurrency, capacity, or another database engine.

See [the complete durable-jobs guide](../jobs.md) and [ADR 024](../decisions/024-application-owned-sqlite-durable-jobs.md).
