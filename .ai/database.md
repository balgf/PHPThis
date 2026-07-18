# Database contract

Use `PHPThis\Database\Connection`. It is an instrumented PDO boundary, not a query builder.

Read `docs/decisions/012-pdo-transport-application-owned-dialects.md` before changing connection behavior or driver certification. Preserve native PDO DSNs and application-owned SQL; do not add a driver enum, connection registry, dialect interface, SQL rewriting, or portability helper.

Rules:

- Execute SQL only through direct calls to `Connection`; do not add a second database execution boundary.
- Pass SQL that PHPStan resolves natively to a finite set of non-blank compile-time constant strings.
- Map an external structural choice such as ordering, selected columns, operators, or a bounded list shape to finite reviewed code-owned statements or fragments. Prefer complete statements, keep the final SQL finite and constant, and reject an unknown choice before database work.
- Keep every statement visibly specific to the application's recorded engine and version.
- Name every selected column; never use `SELECT *`.
- Give every selected expression a unique name or alias.
- Bind every SQL data value. Use one distinct named parameter such as `:user_id` for each placeholder occurrence; never interpolate values, repeat a placeholder name, or pass both `user_id` and `:user_id` as input keys.
- Do not create an SQL sanitizer. Escaping, filtering dangerous characters, or validating an identifier does not replace bound data and compile-time-constant structure.
- Call `selectOneRow` only when zero or one row is valid.
- Call `selectAllRows` only with an explicit bound in the SQL or a documented bounded result.
- Call `executeStatement` for writes and inspect the affected-row count when it matters.
- Parse every returned row immediately with a concrete `fromDatabaseRow` projection factory.
- Start transactions explicitly, commit inside `try`, and roll back in `finally` when still active.
- Never call any connection method from a loop.
- Give each request a deliberately chosen `QueryBudget` in the composition root.
- Give each request a bounded `QueryTrace` and pass it explicitly to `Connection::connect` after the query budget.
- Give each separately named connection an explicit budget and distinct trace; document any deliberately shared request-wide budget, and do not share a trace across engines or claim cross-connection atomicity.
- Test the same handler with small and large fixtures and assert equal query counts.
- Test adversarial strings as ordinary bound data and prove unsupported structural selectors and oversized list shapes fail before a statement is attempted.
- Inspect `QueryTrace::snapshot()` directly in tests; do not create broad query-log files.

For each connection, the application context must record the code authority for structural SQL choices, bounded-list policy, runtime identity and required capabilities, explicitly prohibited runtime capabilities, separate migration or administrative authority, the isolation mechanism, and the source and date of verification. Least privilege limits damage after a defect; it does not make unsafe SQL safe.

Raw `array<string, mixed>` rows must not reach a response or business operation. A query budget is a backstop, not proof of good query shape. The trace hashes exact SQL and never retains SQL or parameters. Budget-rejected calls are not traced because PDO was not attempted. Trace duration covers prepare, bind, and execute, not fetching. Review joins, indexes, result bounds, and execution plans for production queries.

The base transport harness defaults to SQLite. A requested driver must be installed and configured or the harness fails; dedicated CI runs SQLite, MySQL, and PostgreSQL. Changes to `Connection` must pass `composer test:database-drivers` locally and the complete three-engine CI job. Engine-specific application behavior still needs tests against the exact deployed version.

The sample read uses one bounded aggregate statement for user event counts. Its small and large fixtures must keep the same query count and output contract. The sample write performs exactly two named statements inside one explicit transaction; a one-statement budget must reject the second write and leave both tables unchanged. Do not replace either path with per-row queries or a transaction callback.
