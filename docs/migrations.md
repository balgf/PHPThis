# Explicit application migrations

PHPThis accepts one application-owned SQLite migration-ledger pattern and provides no core migration runtime. [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md) records the first executable proof. Its concrete migration names and schema belong to the example; consumers adopt the constraints deliberately and own their own history.

## Adoption boundary

An application with no database or no application-owned migration path records `NOT_APPLICABLE(MIGRATIONS)`. Before adoption, the accountable human approves and the application records:

- the exact engine and supported version, schema authority, and integration-test command;
- the sole operational migration command and separately authorized process identity;
- the permanent identifier grammar, finite manifest maximum, canonical order, and checksum byte format;
- the ledger name, complete schema, maximum rows, parser bounds, and explicit timestamp source and representation;
- the SQL and transaction ownership for ledger bootstrap and every migration;
- lock path, ownership, permissions, filesystem and host topology, contention and failure behavior;
- DDL locks, timeouts, busy behavior, expected duration, maintenance-window and availability policy;
- immutable-history, forward-correction, failed-deployment, backup, restore, and recovery policy;
- exact exits, stdout and stderr objects, finite diagnostic vocabulary, and redaction; and
- automated empty-database, rerun, drift, concurrency, partial-failure, recovery, bounded-ledger, and real-console evidence.

Do not copy the SQLite proof into another engine by changing its DSN. Record a separate engine-specific decision first.

## One finite source manifest

Keep every migration in ordinary application source. One final concrete coordinator names each migration step in its permanent order and invokes pending private step methods explicitly. A migration step owns its permanent identifier, complete engine-specific SQL constants, direct `Connection` calls, and SHA-256 checksum source.

The checksum covers a canonical byte sequence containing the permanent identifier and every executed statement in order. Include any finite code-owned schema-shape policy that governs a conditional step. The same statement constants used in that sequence are passed directly to `selectAllRows`, `selectOneRow`, or `executeStatement`; data values, if any, use explicit unique named bindings. PHPStan must resolve every direct SQL argument to finite non-blank compile-time constants.

Never load executable SQL from a runtime file, ledger row, environment value, command argument, or network source. Never scan a directory, derive a class name, or discover migrations. Do not add an ORM, schema builder, migration DSL, query builder, repository, SQL/binding/placeholder helper, generic database facade, transaction callback, or method that accepts arbitrary SQL.

Do not perform a database call in a loop. The manifest is deliberately unrolled so execution order and the maximum number of calls remain visible. A finite loop may validate already fetched bounded ledger values when it performs no I/O.

The accepted example uses final `Example\Migrations\SqliteApplicationMigrations`. Its six permanent steps are `0001_create_user_schema`, `0002_create_job_schema`, `0003_prepare_document_schema`, `0004_add_document_category`, `0005_add_document_sort_rank`, and `0006_create_document_access_schema`. The manifest cap is 512 and the bounded ledger query uses `LIMIT 513`. Those names and limits document the proof; they are not reserved consumer migrations.

## Bounded inspectable ledger

The accepted SQLite example stores one row per committed migration in `application_migrations`. Its columns are `position INTEGER PRIMARY KEY`, `migration_id TEXT UNIQUE`, `checksum_sha256 TEXT`, and `applied_at_epoch INTEGER`. Before reading history, it either creates that exact `STRICT` table or compares the complete stored SQLite table and automatic-index object set, including table SQL byte-for-byte, and rejects an incompatible definition or extra trigger or index. The ledger insert explicitly obtains `applied_at_epoch` from SQLite `unixepoch()` rather than a hidden default or PHP clock; it is insert-time evidence, while manifest position defines order. Each row therefore contains only:

- its expected manifest position;
- its permanent migration identifier;
- the lowercase-hex SHA-256 content checksum; and
- the explicit SQLite epoch timestamp.

The ledger stores no SQL, PHP class, callback, source path, serialized object, credentials, or migration output. Read at most the application maximum plus one row. The accepted history query selects and parses only position, identifier, and checksum; `applied_at_epoch` remains inspectable operational evidence constrained by the `STRICT` schema and is not an ordering input. Before applying any pending entry, reject overflow and unknown, duplicate, missing, reordered, malformed, or checksum-mismatched history. Do not silently repair, delete, reorder, or overwrite ledger rows.

Once recorded, a migration is immutable. Repair an incorrect historical step with a new forward migration. A down migration is neither inferred nor automatically generated; restoring a backup or executing an application-specific recovery action requires separate human authorization and operational policy.

Do not infer or manufacture a ledger prefix from tables found in an unledgered database. Adopting an existing schema requires a separate, reviewed application decision that proves its exact structure and data assumptions before recording any history. If a local example database is disposable, an accountable developer may instead back it up if needed and rebuild it explicitly; the migration command never deletes or silently baselines it.

## Explicit transaction path

Acquire the application-private nonblocking migration lock before database work. After bounded ledger bootstrap and complete history validation, execute each pending migration through its own visible transaction:

```text
begin
  concrete migration-step direct SQL calls
  exact ledger insert
commit
finally rollback only if still active
```

The migration statements and ledger insert commit together. A failed migration leaves neither its schema changes nor its row when the selected SQLite statement supports the exercised transaction. Earlier migrations remain committed. Do not wrap the manifest in one transaction, hide cleanup in a callback, retry implicitly, or continue after a failure.

Use a fresh migration-scoped `Connection`, `QueryBudget`, and `QueryTrace`. The accepted six-step example uses a 21-statement budget and trace plus PDO SQLite timeout 5. The migration identity is separate from the web runtime and receives only the schema and ledger authority required by this command. Do not expose it through HTTP configuration or compose the coordinator during request startup.

## Command and output

Add the migration operation to the application's sole console. Do not add it to framework `vendor/bin/phpthis`, Composer dependency hooks, the front controller, or an automatically discovered command map. A deployment may call the explicit application command only after its own human-approved release policy has authorized that external mutation.

Successful invocations exit `0`, emit one newline-terminated JSON object to stdout, and write nothing to stderr. The finite success outcomes are `applied` and `up_to_date`. Every example migration failure exits `1`, leaves stdout empty, and writes `{"error":"migration_failed","reason":"<reason>","migration":<code-owned-id-or-null>}\n` to stderr. Its reasons are exactly `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`. The migration field is one code-owned manifest identifier or `null`; stored or submitted identifiers are never reflected. Invalid command syntax retains the application's exit-2 input contract.

Never include paths, DSNs, credentials, SQL, bindings, exception details, ledger contents, schema contents, or application data in command output. Operational diagnosis belongs in a separately reviewed bounded destination; diagnostics must not create a second migration outcome channel.

## SQLite topology and other engines

The accepted proof is SQLite-specific. SQLite permits only one writer at a time, so the application still records its transaction mode, busy timeout, journal and synchronization settings, local filesystem behavior, and maintenance-window policy. The example appends `.migration.lock` to the canonical database path, sets mode `0600`, and verifies that the opened regular single-link inode still matches the path before and after taking a nonblocking same-host `flock`. The parent directory must prevent an untrusted principal from unlinking or replacing that path; file mode alone cannot provide that guarantee. The lock coordinates only cooperating processes using that exact path and does not coordinate multiple hosts or processes that bypass it. See [SQLite transactions](https://www.sqlite.org/lang_transaction.html).

MySQL DDL commonly commits implicitly and cannot generally share atomicity with a ledger insert. PostgreSQL permits many transactional DDL operations but has command-specific exceptions and server-side lock semantics. See [MySQL implicit commits](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html), [MySQL atomic DDL](https://dev.mysql.com/doc/refman/8.4/en/atomic-ddl.html), [PostgreSQL explicit locking](https://www.postgresql.org/docs/current/explicit-locking.html), [`CREATE DATABASE`](https://www.postgresql.org/docs/current/sql-createdatabase.html), and [`CREATE INDEX`](https://www.postgresql.org/docs/current/sql-createindex.html).

Those are warnings, not alternative PHPThis recipes. Each non-SQLite adoption needs its own reviewed statements, transaction and lock strategy, privilege boundary, failure recovery, and integration evidence.

## Required evidence

Execute the real application console in fresh subprocesses and prove:

- a fresh empty database applies the complete manifest in its exact order;
- the exact bounded ledger contains each expected identifier, position, checksum, and valid explicit timestamp;
- rerunning unchanged history returns `up_to_date` and executes no pending manifest step;
- editing previously applied checksum-covered content rejects drift before later migration work;
- an unknown, duplicate, missing, reordered, malformed, or overflowing ledger fails closed;
- a held same-host lock produces the exact nonblocking failure and changes no database state;
- a failure inside one migration rolls back that migration and its row while preserving earlier committed entries;
- correcting the still-unapplied failing condition without editing committed history allows a fresh invocation to continue from the last valid row;
- HTTP startup and ordinary requests never create the ledger or execute migration SQL;
- every success and failure has exact exit, stream exclusivity, bytes, finite vocabulary, and complete redaction; and
- the complete project gate passes with PHT006 and maximum-level PHPStan.

The proof does not establish production lock duration, availability, free-space behavior, crash recovery, backup restore, or another engine. Rehearse those properties against the exact deployment before migration authority is used on shared data.
