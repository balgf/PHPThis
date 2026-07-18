# Database design

The database layer deliberately exposes SQL. Its job is limited to connection policy, typed parameter binding, predictable fetching, transactions, statement counting, and bounded query tracing.

Applications create this boundary with `Connection::connect` in their composition root. `PHT005` resolves names and types and rejects application-owned construction of `PDO` or its subclasses so query budgets and traces cannot be bypassed with an alias, typed class-string, or anonymous subclass.

## Why no ORM or query builder

Lazy relationships can perform I/O during property access, while fluent query APIs can obscure the SQL shape and encourage broad reusable abstractions. Both increase the context an AI needs to reason about cost. PHPThis keeps the statement at the behavior boundary.

This is not an argument that every ORM query is slow or that raw SQL is automatically fast. An AI can still put an explicit query in a loop. That is why the framework also requires query budgets, loop rules, bounded reads, and scale tests.

## Parameter policy

Only named parameters are accepted. `Connection` binds strings, integers, booleans, and null with explicit PDO parameter types. Arrays and objects must be transformed by application code before execution.

## Transaction policy

Transactions are manual: begin, execute, and commit inside `try`; in `finally`, roll back if the transaction remains active. This preserves normal exception propagation while making cleanup visible. PHPThis will not add a callback helper that hides this control flow.

The sample `POST /users` path performs two writes: one user row and one `user.created` event selected through the unique email. Both affected-row counts must be one. The handler prepares its success response before beginning, commits only after both writes, and rolls back when a failure or query-budget rejection leaves the transaction active.

The sample `GET /users` path selects at most 50 users with event counts in one aggregate statement. The derived user selection applies the bound before joining events, and `user_events.user_id` is indexed in the sample schema. Equivalent small and large fixtures must both use exactly one statement.

## Query trace policy

Each request constructs one `QueryTrace` with an explicit retained-fingerprint limit and passes it to `Connection` beside the `QueryBudget`. The trace performs no file or network I/O. It aggregates a SHA-256 fingerprint of each exact SQL string with execution count, failure count, and prepare/bind/execute duration in integer microseconds.

The trace never retains SQL text, parameter names or values, DSNs, credentials, exception messages, driver details, or stack traces. Different bindings for the same SQL therefore produce one fingerprint without exposing the bindings. When the fingerprint bound is full, global counts and timing continue while `truncated` and `untracked_statements` make the missing detail explicit.

`QueryTrace::snapshot()` is a versioned JSON-compatible record. Tests inspect it in memory. A request boundary may later add request metadata and emit the record once; `Connection` never writes one log entry per statement. Calls rejected by `QueryBudget` are absent because PDO was never attempted. Timing does not include fetch or row conversion.

```json
{
  "schema_version": 1,
  "event": "database.query_summary",
  "statements": 2,
  "failures": 0,
  "tracked_fingerprints": 1,
  "repeated_fingerprints": 1,
  "maximum_executions_per_fingerprint": 2,
  "total_execute_duration_us": 420,
  "slowest_execute_duration_us": 230,
  "truncated": false,
  "untracked_statements": 0,
  "queries": [
    {
      "fingerprint": "sha256:0000000000000000000000000000000000000000000000000000000000000000",
      "executions": 2,
      "failures": 0,
      "total_execute_duration_us": 420,
      "max_execute_duration_us": 230
    }
  ]
}
```

Every key is present even when its value is zero or the query list is empty. Query aggregates remain in first-seen order, making the output deterministic for the same execution path.
