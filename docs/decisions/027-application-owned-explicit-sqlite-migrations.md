# ADR 027: Application-owned explicit SQLite migrations

Status: accepted

## Context

An application needs a repeatable way to move an empty or older database to its current schema and to prove which changes were applied. Executing setup SQL manually leaves ordering, concurrency, partial failure, and edited-history behavior undefined. Running schema changes from HTTP startup gives an ordinary request administrative authority and makes latency, locks, and failure depend on hidden deployment state.

PHPThis already supplies explicit `Connection` transactions, finite constant SQL, query budgets, bounded traces, and one application-owned console. It does not supply a schema builder, migration base class, migration registry, discovery mechanism, rollback inference, or cross-engine DDL contract. The framework core also occupies 2,495 of its 2,500-line Alpha 2 ceiling. A generic migration runtime would hide the engine-specific transaction and locking behavior that this project is designed to keep visible.

## Decision

The first migration proof is entirely application-owned and SQLite-specific. It adds one `database:migrate` command to the example's existing application console and no framework core type, runtime dependency, command, migration interface, schema builder, DSL, discovery mechanism, filesystem scan, runtime `.sql` loader, generic database facade, transaction callback, inferred rollback, or portable-DDL claim. Framework `bin/phpthis` remains the installed `check` boundary. Consumer Contract version 6, Strict Profile version 2, and the 2,500-line core ceiling remain unchanged.

The command is operational and explicit. It is never called from the front controller, HTTP composition, request handling, application bootstrap, or an automatic deployment hook. An accountable operator or deployment workflow invokes it with the separately authorized migration identity after the application's backup, maintenance-window, timeout, and recovery policy has been satisfied. The web runtime does not receive that authority.

### Finite manifest and immutable history

One final application-owned migration coordinator names every concrete migration step in one reviewed order and invokes each pending private step method in straight-line, unrolled code. Adding a migration requires an ordinary source edit to that coordinator and the matching tests. No filename, directory entry, class name, attribute, annotation, service registration, reflection result, or database value selects executable PHP or SQL.

The accepted example uses final `Example\Migrations\SqliteApplicationMigrations` and these permanent identifiers in order: `0001_create_user_schema`, `0002_create_job_schema`, `0003_prepare_document_schema`, `0004_add_document_category`, `0005_add_document_sort_rank`, and `0006_create_document_access_schema`. Its manifest cap is 512 and its ledger query uses `LIMIT 513` to expose overflow. These names and bounds are example evidence, not a framework registry or consumer schema.

This paragraph records the six-step acceptance state of ADR 027. ADR 029 later appends the forward `0007_create_account_users` migration without rewriting any applied checksum and raises the current fresh-run ceiling from 21 to 23 statements. The new table deliberately receives no ID-based backfill from `account_memberships`, because authenticated-principal and user identities are not interchangeable.

Each migration owns:

- one permanent bounded identifier whose position in the manifest never changes;
- complete raw SQLite statements expressed as non-blank compile-time constants;
- direct `Connection` calls with explicit named bindings where data exists; and
- a lowercase hexadecimal SHA-256 content checksum derived from one canonical code-owned byte sequence that covers the identifier, every executed statement, and statement order.

The exact statement constants used to derive the checksum are the constants passed at the direct calls. A step may additionally include a finite code-owned policy that governs conditional work, but this example's six schema steps are unconditional once their ledger entry is pending. A committed migration is immutable. Changing its identifier, order, checksum-covered statement or policy bytes, or recorded checksum after it has been applied is drift and stops the command before any pending migration executes. A correction is a new forward migration with a new identifier; the coordinator neither infers nor executes a down migration.

The manifest has one finite maximum. The history read selects position, identifier, and checksum for at most that maximum plus one row, rejects overflow, and validates the complete returned prefix before pending work. Unknown identifiers, duplicates, gaps, reordered entries, malformed selected values, and checksum mismatch are closed failures. `applied_at_epoch` is constrained by the `STRICT` ledger schema and inspected by tests but is not an ordering input. The ledger is inspectable application data, not permission to execute stored text; it contains no SQL, PHP class name, callback, path, or serialized object.

The coordinator does not infer an applied prefix from existing tables or manufacture baseline rows. An unledgered existing application needs its own reviewed adoption decision and exact structural and data evidence before history is recorded. A disposable local example database may instead be explicitly rebuilt after any required backup; the command itself never deletes it or silently claims that prior changes ran.

### Ledger and transactions

The migration path owns one SQLite `application_migrations` ledger with one row per successfully applied manifest entry. Its exact columns are `position INTEGER PRIMARY KEY`, `migration_id TEXT UNIQUE`, `checksum_sha256 TEXT`, and `applied_at_epoch INTEGER`. The ledger insert explicitly selects SQLite `unixepoch()` for `applied_at_epoch`; there is no hidden column default or PHP migration clock. This timestamp is insert-time evidence, while manifest position remains authoritative for order.

Ledger bootstrap uses reviewed constant SQLite DDL. It first reads the code-owned table's complete stored SQLite object set; absence creates the exact `STRICT` table, while any incompatible table SQL, automatic-index shape, extra trigger, or extra index fails before history or migration work. After bootstrap, the coordinator validates the complete bounded applied prefix and all applied checksums before it starts a pending migration. Each pending migration then receives its own explicit transaction:

1. begin the transaction;
2. execute that migration's complete constant SQLite statements through direct `Connection` calls;
3. insert that migration's ledger row with its expected position, identifier, checksum, and explicit SQLite `unixepoch()` timestamp;
4. commit; and
5. in `finally`, roll back only when that transaction remains active.

A failed migration and its ledger row roll back together. Earlier migration transactions remain committed and their rows remain valid, so the next explicitly authorized invocation can continue after the application defect is corrected with immutable-history rules preserved. The runner does not wrap multiple migrations in one transaction and does not hide transaction ownership in a callback.

Every migration connection is freshly composed for the command with an explicit finite `QueryBudget` and distinct bounded `QueryTrace`. The accepted example uses a 21-statement budget and trace plus PDO SQLite timeout 5; its complete empty-database path reaches that 21-statement ceiling, while an unchanged path performs one exact ledger-object read and one bounded history read. The accepted command performs no database call inside `for`, `foreach`, `while`, or recursion. Its cost is bounded by the finite manifest and ledger cap rather than database cardinality.

### Concurrency, output, and redaction

Before migration database work, the command appends `.migration.lock` to the canonical SQLite database path, rejects a symlink, opens the lock, sets mode `0600`, and verifies that the opened regular single-link inode matches the path before and after a nonblocking exclusive `flock`. Ordinary contention is the exit-1 `busy` migration result. Lock open, identity, non-contention acquisition, permission, close, and release failures fail closed as `lock_failed`. The lock is released in `finally`.

This file lock coordinates only cooperating processes using the same path on one host and one filesystem with verified advisory-lock behavior. The parent directory must prevent an untrusted principal from unlinking or replacing the lock after verification; mode `0600` alone cannot protect a name in an attacker-writable directory. The lock does not coordinate another host, a copied or aliased database path, a process that ignores the lock, or a remote database server. SQLite itself permits only one simultaneous write transaction; the application still records busy-timeout, filesystem, process, and deployment behavior. See the [SQLite transaction documentation](https://www.sqlite.org/lang_transaction.html).

Success exits `0` and emits one stable newline-terminated JSON object to stdout with either `applied` or `up_to_date`; stderr remains empty. Invalid command arguments retain the console's existing exit-2 contract. Every migration failure exits `1`, leaves stdout empty, and writes exactly `{"error":"migration_failed","reason":"<reason>","migration":<code-owned-id-or-null>}\n` to stderr. The finite reasons are `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`. The migration field is one of the six code-owned manifest identifiers or `null`; stored or submitted identifiers are never reflected. Output omits database and lock paths, DSNs, credentials, SQL, bindings, exception classes and messages, stacks, ledger rows, schema contents, and application data.

### Evidence and engine boundary

Repository evidence creates a fresh empty SQLite database, applies the complete manifest in deterministic order, inspects the exact ledger, and proves a second run returns `up_to_date` without executing a pending manifest step. It rejects an incompatible preexisting ledger definition and edited migration content before later execution, proves lock contention is nonblocking and changes no database state, and injects a partial failure whose migration transaction and ledger row roll back while earlier committed migrations remain recorded. After the failing condition is explicitly corrected without altering committed history, a fresh invocation continues from the last valid row. Real-console subprocess tests prove exact exits, stream exclusivity and bytes, finite redacted failures, and that HTTP startup performs no migration work. Ledger-overflow and malformed-ledger evidence proves the read and parser bounds.

This is not a portable DDL contract:

- SQLite allows multiple readers but only one simultaneous writer, and transaction mode and `SQLITE_BUSY` behavior remain deployment policy. The proof is tied to the recorded SQLite runtime and filesystem.
- MySQL DDL commonly causes implicit commits and generally cannot be rolled back with the surrounding ledger insert. Atomic DDL does not make it transactional with other statements. See [MySQL statements that cause an implicit commit](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html) and [atomic DDL](https://dev.mysql.com/doc/refman/8.4/en/atomic-ddl.html).
- PostgreSQL supports transactional behavior for many DDL statements, but lock modes and exceptions such as `CREATE DATABASE` or `CREATE INDEX CONCURRENTLY` require command-specific policy. Server advisory locks also have session- and transaction-scoped forms rather than this proof's local-file topology. See [PostgreSQL explicit locking](https://www.postgresql.org/docs/current/explicit-locking.html), [`CREATE DATABASE`](https://www.postgresql.org/docs/current/sql-createdatabase.html), and [`CREATE INDEX`](https://www.postgresql.org/docs/current/sql-createindex.html).

An application targeting MySQL, PostgreSQL, or another engine records a separate engine-specific migration decision and integration evidence. It must not port this proof by changing only the DSN or by introducing a runtime dialect switch.

## Consequences

The application owns more concrete source: migration identifiers, statements, checksums, ordering, ledger parsing, locks, timestamps, credentials, output, deployment policy, and recovery tests. That ownership makes the exact schema path and its failure state auditable by an AI and accountable human.

Forward-only history means a failed deployment may require a new corrective migration or a separately authorized restore rather than an automatic down command. Per-migration transactions preserve earlier progress but do not make the whole manifest atomic. A schema change can still block readers or writers, exceed a maintenance window, exhaust storage, or fail during process or host termination; production adoption must prove the selected SQLite version, journal and synchronization settings, busy timeout, backup and restore, free-space policy, lock filesystem, process termination behavior, observability, and incident procedure.

The checksum detects divergence from recorded migration content; it does not prove that SQL is correct, safe, reversible, authorized, performant, or compatible with live data. Code review, PHT006, least privilege, engine integration tests, production-scale rehearsal, backups, and recovery evidence remain complementary.

No framework migration API, schema abstraction, reusable runner, discovery rule, core change, Consumer Contract version, Strict Profile version, or cross-engine claim is introduced.

## Reconsider when

At least two independent applications prove the same smaller boundary under materially different schema histories and deployment conditions, or the SQLite application proof exposes a concrete framework defect that cannot remain local. Reconsider one narrow evidence-backed contract without hiding engine-specific DDL, SQL order, checksum ownership, transaction boundaries, lock topology, elevated authority, or recovery policy behind a generic migration abstraction.
