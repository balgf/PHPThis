# Database contract

Use `PHPThis\Database\Connection`. It is an instrumented PDO boundary, not a query builder.

Rules:

- Write complete SQL strings at the call site.
- Name every selected column; never use `SELECT *`.
- Use named parameters such as `:user_id`; never interpolate values.
- Call `selectOneRow` only when zero or one row is valid.
- Call `selectAllRows` only with an explicit bound in the SQL or a documented bounded result.
- Call `executeStatement` for writes and inspect the affected-row count when it matters.
- Parse every returned row immediately with a concrete `fromDatabaseRow` projection factory.
- Start, commit, and roll back transactions explicitly.
- Never call any connection method from a loop.
- Give each request a deliberately chosen `QueryBudget` in the composition root.
- Give each request a bounded `QueryTrace` and pass it explicitly to `Connection::connect` after the query budget.
- Test the same handler with small and large fixtures and assert equal query counts.
- Inspect `QueryTrace::snapshot()` directly in tests; do not create broad query-log files.

Raw `array<string, mixed>` rows must not reach a response or business operation. A query budget is a backstop, not proof of good query shape. The trace hashes exact SQL and never retains SQL or parameters. Budget-rejected calls are not traced because PDO was not attempted. Trace duration covers prepare, bind, and execute, not fetching. Review joins, indexes, result bounds, and execution plans for production queries.
