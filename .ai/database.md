# Database contract

Use `PHPThis\Database\Connection`. It is an instrumented PDO boundary, not a query builder.

Read `docs/decisions/012-pdo-transport-application-owned-dialects.md` before changing connection behavior or driver certification. Preserve native PDO DSNs and application-owned SQL; do not add a driver enum, connection registry, dialect interface, SQL rewriting, or portability helper.

Rules:

- Write complete SQL strings at the call site.
- Keep every statement visibly specific to the application's recorded engine and version.
- Name every selected column; never use `SELECT *`.
- Give every selected expression a unique name or alias.
- Use one distinct named parameter such as `:user_id` for each placeholder occurrence; never interpolate values, repeat a placeholder name, or pass both `user_id` and `:user_id` as input keys.
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
- Inspect `QueryTrace::snapshot()` directly in tests; do not create broad query-log files.

Raw `array<string, mixed>` rows must not reach a response or business operation. A query budget is a backstop, not proof of good query shape. The trace hashes exact SQL and never retains SQL or parameters. Budget-rejected calls are not traced because PDO was not attempted. Trace duration covers prepare, bind, and execute, not fetching. Review joins, indexes, result bounds, and execution plans for production queries.

The base transport harness defaults to SQLite. A requested driver must be installed and configured or the harness fails; dedicated CI runs SQLite, MySQL, and PostgreSQL. Changes to `Connection` must pass `composer test:database-drivers` locally and the complete three-engine CI job. Engine-specific application behavior still needs tests against the exact deployed version.

The sample read uses one bounded aggregate statement for user event counts. Its small and large fixtures must keep the same query count and output contract. The sample write performs exactly two named statements inside one explicit transaction; a one-statement budget must reject the second write and leave both tables unchanged. Do not replace either path with per-row queries or a transaction callback.
