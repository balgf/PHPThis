# Example SQLite migration context

The executable example follows ADR 027 through the application-owned `Example\Migrations\SqliteApplicationMigrations` coordinator and the sole console command:

```text
php example/bin/console.php database:migrate [--database=/absolute/path]
```

No PHPThis core migration type or dependency is added. The front controller, `ApplicationComposition::http()`, and ordinary request startup never construct or invoke the coordinator. `tools/setup-example.php` delegates schema work to this exact coordinator and then only seeds local example data; it is not a second migration implementation.

## Finite immutable manifest

The final coordinator names and invokes these six concrete migration steps through unrolled private methods in this permanent order:

1. `0001_create_user_schema`
2. `0002_create_job_schema`
3. `0003_prepare_document_schema`
4. `0004_add_document_category`
5. `0005_add_document_sort_rank`
6. `0006_create_document_access_schema`

Every step lives in `example/src/Migrations/SqliteApplicationMigrations.php`, owns complete raw SQLite compile-time-constant SQL, and calls `Connection` directly with explicit bindings where data exists. The manifest and private method calls are unrolled: no database call occurs in a loop, no directory is scanned, and no filename, class string, attribute, ledger value, or runtime `.sql` file selects work.

Each permanent identifier and its exact ordered statement bytes determine one lowercase-hex SHA-256 checksum. The same SQL constants are executed and checksummed, and all six pending schema steps are unconditional. An applied identifier, position, or checksum-covered statement sequence is immutable. A correction is a new forward migration; there is no inferred or automatic down migration.

## Bounded ledger and transactions

The SQLite ledger is a `STRICT` `application_migrations` table with exact columns `position INTEGER PRIMARY KEY`, `migration_id TEXT UNIQUE`, `checksum_sha256 TEXT`, and `applied_at_epoch INTEGER`, plus the source constraints. Before history is read, the coordinator either creates that exact table or compares its complete stored SQLite table and automatic-index object set, including table SQL byte-for-byte; an incompatible definition or extra trigger or index fails closed. The ledger insert explicitly selects SQLite `unixepoch()`; there is no hidden default or PHP migration clock. The manifest cap is 512 migrations and the ordered position/identifier/checksum history read uses `LIMIT 513` so overflow is detected rather than silently truncated. The coordinator parses every selected history scalar and validates the complete applied prefix before pending work. `applied_at_epoch` remains independently inspectable and schema-constrained but does not determine order. Unknown, duplicate, missing, reordered, malformed, overflowing, or checksum-mismatched history fails closed.

Each pending migration step and its exact ledger insert use one explicit transaction. Commit occurs only after both succeed; `finally` rolls back when the transaction remains active. A failed migration leaves neither its changes nor its row, while earlier committed migration transactions remain. The next explicitly authorized run may continue only after the source is corrected without editing applied history.

The command freshly composes its migration `Connection` with `QueryBudget(21)`, `QueryTrace(21)`, and PDO SQLite timeout `5`, plus one application-private lock. A complete empty-database run consumes the accepted 21-statement ceiling; an unchanged run performs only the exact ledger-object read and bounded history selection. The migration process owns SQLite schema authority; the example's local file/process separation is evaluation evidence only, not production least-privilege proof.

The database parent directory must already resolve. The database file may be absent and is then created by SQLite; an existing path must be a regular non-symlink file whose canonical path can be resolved. A missing or unsafe parent or database path fails as `ledger_unavailable` without reflecting the path.

The command does not infer a ledger prefix from an existing unledgered schema, and every pending example schema step is unconditional. Preserve and review any non-disposable existing data before designing an explicit adoption migration. A developer may explicitly rebuild a disposable local example database, but neither `database:migrate` nor `tools/setup-example.php` deletes or silently baselines it.

## Same-host lock

The migration lock path is the canonical database path plus `.migration.lock`. The command rejects a symlink, opens the path, sets mode `0600`, verifies that the opened regular single-link inode matches the path before and after `flock(LOCK_EX | LOCK_NB)`, and only then begins migration database work. Ordinary contention is the exit-1 `busy` result with `migration: null`. Lock open, identity, non-contention acquisition, permission, close, and release failures fail closed as `lock_failed`. An acquired lock is released in `finally`.

This coordinates only cooperating processes using the same database and lock path on one host and verified filesystem. The parent directory must prevent untrusted unlink or replacement after verification; file mode cannot secure a name in an attacker-writable directory. It does not coordinate multiple hosts, copied or aliased paths, remote databases, or a process that bypasses the command. SQLite still permits only one writer at a time; journal mode, synchronization, busy timeout, capacity, backup, restore, maintenance windows, and incident response remain deployment policy.

## Exit and stream contract

Success exits `0`, writes one line to stdout, and leaves stderr empty:

```json
{"command":"database:migrate","outcome":"applied"}
{"command":"database:migrate","outcome":"up_to_date"}
```

Every migration failure exits `1`, leaves stdout empty, and writes exactly this key order plus `\n` to stderr:

```json
{"error":"migration_failed","reason":"<reason>","migration":null}
```

The finite reasons are `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`. `migration` is either one of the six code-owned manifest identifiers above or `null`; no submitted or stored identifier is reflected. Unknown command and invalid arguments retain the console's existing exit-2 errors.

Output omits submitted values, database and lock paths, DSNs, credentials, SQL, bindings, exception classes and messages, stacks, ledger rows, schema contents, request data, and domain data.

## Evidence and limits

The migration tests prove fresh empty-database application in exact manifest order, exact ledger values, unchanged rerun as `up_to_date`, checksum drift before later work, invalid and overflowing history, immediate same-host lock contention without database mutation, per-migration rollback with earlier commits preserved, forward continuation, exact real-console bytes and redaction, and no migration execution during HTTP startup. The complete project gate proves PHT006, PHPStan, Strict Profile, and behavior evidence together.

This is SQLite application evidence only. It does not certify MySQL or PostgreSQL DDL transactions, server locks, online schema change, production duration, availability, crash recovery, backup restore, or distributed exclusion. A non-SQLite adoption requires a separate accepted engine-specific application decision.
