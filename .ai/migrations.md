# Migration authoring contract

Read [ADR 027](../docs/decisions/027-application-owned-explicit-sqlite-migrations.md), [Explicit application migrations](../docs/migrations.md), `.ai/database.md`, `.ai/cli.md`, and `.ai/testing.md` before adding or changing a migration.

PHPThis provides no core migration API. The accepted proof is application-owned and SQLite-specific.

Rules:

- Keep `database:migrate` on the application's sole explicit console. Never run it during HTTP startup or through framework `bin/phpthis`.
- Give the migration process separately authorized schema credentials or the equivalent SQLite file/process authority; keep that authority unavailable to the web runtime.
- Keep one final concrete coordinator with one finite ordered source manifest and unrolled private migration-step methods. Do not scan files, discover classes, resolve strings, or load runtime `.sql` files.
- Give every migration one permanent bounded identifier, complete raw SQLite compile-time-constant statements, direct `Connection` calls, explicit unique named bindings where data exists, and a SHA-256 checksum covering the identifier and exact statement sequence.
- Validate the entire bounded ledger and every applied checksum before pending work. Reject overflow, unknown, duplicate, missing, reordered, malformed, or edited history; never repair it silently.
- Execute each pending migration and its ledger insert in its own explicit transaction. Commit inside `try`; roll back in `finally` only when still active. Earlier committed migrations remain committed after a later failure.
- Never call a database method in a loop, infer a down migration, execute a stored class or SQL value, hide transaction ownership in a callback, or retry implicitly.
- Acquire one application-private nonblocking exclusive `flock` before database work and release it in `finally`. Record that it coordinates only cooperating same-host processes using the same path.
- Use a fresh migration connection with an explicit finite budget and bounded trace. Store only position, identifier, checksum, and one explicitly selected and documented timestamp in the bounded ledger; the example uses SQLite `unixepoch()` in the ledger insert and no hidden default.
- Return only finite `applied` or `up_to_date` success and finite redacted nonzero failures. Never emit paths, DSNs, credentials, SQL, bindings, exception details, ledger contents, schema contents, or application data.
- Add a new forward migration to correct history. Backup restoration or another recovery mutation requires separate explicit human authorization.
- Keep MySQL, PostgreSQL, and other engines outside this proof; they require separate engine-specific transaction, DDL-lock, privilege, recovery, and integration decisions.

Evidence must cover empty application, deterministic order, exact bounded ledger, no-op rerun, drift rejection before further work, malformed and overflowing ledger, nonblocking overlap, partial failure and continuation, exact CLI bytes and redaction, no HTTP migration path, and the complete project gate.
