# ADR 012: PDO transport with application-owned SQL dialects

Status: accepted

## Context

`Connection` accepts a native PDO DSN and already owns the behavior PHPThis needs around PDO: named scalar binding, associative fetching, explicit local transactions, query budgets, and bounded query traces. The framework's executable evidence previously used only SQLite, so that shape did not justify a MySQL or PostgreSQL support claim.

Database engines differ beyond connection transport. SQL grammar, schema definitions, identifier quoting, generated identifiers, scalar representations, pagination, error codes, transaction isolation, locking, query plans, charset, timezone, TLS, and timeout behavior are not safely normalized by a thin shared API. A dialect layer or query builder would hide exactly the database behavior PHPThis intends to keep visible.

## Decision

`PHPThis\Database\Connection` is a PDO transport boundary, not a database abstraction. It accepts native DSNs, credentials, and driver options. PHPThis certifies a deliberately small base transport contract against SQLite, MySQL, and PostgreSQL:

- connection through the engine's PDO driver;
- unique named parameters containing letters, digits, and underscores;
- explicit string, integer, boolean, and null binding;
- associative one-row and collection fetching;
- unambiguous single-row insert and delete counts;
- connection-local commit and rollback;
- independent explicit connections;
- pre-execution query budgets, bounded query traces, and PDO failure propagation.

The certification harness runs SQLite by default and all three engines in dedicated CI services. A release may claim an engine only while its current certification job passes. Other PDO drivers remain possible inputs to `Connection::connect`, but are not certified by PHPThis until they pass the same reviewed base contract.

Applications own complete SQL strings and all engine-specific behavior. Every application records each connection's engine and version, required `ext-pdo_*` Composer extension, non-secret configuration source, schema authority, dialect assumptions, and integration-test command in `.ai/data.md`. The framework continues to require only `ext-pdo`; applications require their actual runtime drivers.

Every placeholder occurrence uses a distinct name because native prepared-statement behavior for repeated names differs by driver. Selected expressions use unique column names or aliases because associative fetching cannot preserve duplicate keys. `rowCount()` is not used for reads, and application tests establish any update semantics they rely on.

Multiple databases are wired as separately named `Connection` objects in the composition root. Each receives an explicit `QueryBudget` and a distinct `QueryTrace`; traces have no connection identity and must not be shared across engines. This decision originally allowed a deliberately documented shared request-wide budget. ADR 023 supersedes that option for terminal-summary sources: no two registered sources may share a budget or trace. Transactions are local to one connection. PHPThis provides no distributed transaction or cross-database atomicity guarantee.

## Consequences

An application can use SQLite, MySQL, PostgreSQL, or several explicit connections without an ORM, driver enum, connection registry, dialect interface, SQL rewriting, or query builder. Its SQL remains visibly correct for the selected engine rather than superficially portable.

The shared certification is intentionally narrower than production support. Applications still test their real schemas, queries, result representations, errors, isolation, locking, indexes, plans, and operational settings against the exact engine version they deploy.

The portable parameter-name grammar rejects previously unreliable driver-dependent names before PDO work is counted or traced. Optional leading colons remain accepted at the binding boundary.

## Reconsider when

A certified PDO driver cannot implement the base contract, a production application demonstrates a repeated connection-level policy that cannot remain explicit, or independent connections require coordinated work. Any replacement must preserve visible SQL and must not imply portable dialect or distributed-transaction semantics it cannot prove.
