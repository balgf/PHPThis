# Database design

The database layer deliberately exposes SQL. Its job is limited to connection policy, typed parameter binding, predictable fetching, transactions, statement counting, and bounded query tracing. It does not sanitize or generate statements.

Applications create this boundary with `Connection::connect` in their composition root. `PHT005` resolves names and types and rejects application-owned construction of `PDO` or its subclasses so query budgets and traces cannot be bypassed with an alias, typed class-string, or anonymous subclass.

## Driver and dialect policy

PHPThis provides PDO transport, not portable SQL. `Connection::connect` accepts a native PDO DSN, optional credentials, and additional driver options. The framework keeps `ext-pdo` as its only runtime database requirement. An application declares the actual runtime extension for every engine it uses, such as `ext-pdo_sqlite`, `ext-pdo_mysql`, or `ext-pdo_pgsql`.

The base transport certification covers SQLite, MySQL, and PostgreSQL. It proves native connection, named scalar and null binding, associative fetching, deliberate single-row DML counts, local commit and rollback, independent connections, query budgeting, query tracing, and PDO failure propagation. The PHPThis framework repository runs SQLite in its maintainer `composer check`; dedicated framework CI services run all three through its `composer test:database-drivers` script. Dependency scripts are not inherited by consuming applications, which own their engine-specific integration command.

The harness uses fixed, code-owned table names so its statements remain finite under PHT006. It creates and drops those tables. MySQL and PostgreSQL certification therefore requires a disposable or dedicated test database with credentials intentionally authorized for that fixture; never point it at a shared or production database. Its DDL-capable test credential is not a production least-privilege example. The harness does not pre-drop a table and drops only a table created by that run, so an interrupted run can require resetting the dedicated database before retrying.

Certification does not make SQL dialects interchangeable. Complete SQL, DDL, schema and migration policy, identifier quoting, generated identifiers, returned scalar representations, error translation, isolation, locking, execution plans, charset, timezone, TLS, and timeouts remain application-owned and engine-specific. Other PDO drivers may be passed to the same connection API, but PHPThis does not call them certified until they pass the reviewed base harness.

An application using multiple databases constructs separately named `Connection` objects in its composition root. Connections do not participate in a distributed transaction. Give each connection an explicit budget and a distinct trace: a query trace contains no connection identity, so sharing one across engines could merge identical SQL fingerprints. A deliberately shared request-wide budget is valid only when the application records that combined limit.

## Why no ORM or query builder

Lazy relationships can perform I/O during property access, while fluent query APIs can obscure the SQL shape and encourage broad reusable abstractions. Both increase the context an AI needs to reason about cost. PHPThis keeps the statement at the behavior boundary.

This is not an argument that every ORM query is slow or that raw SQL is automatically fast. An AI can still put an explicit query in a loop. That is why the framework also requires query budgets, loop rules, bounded reads, and scale tests.

## SQL data and finite structure

PHT006 requires direct calls to `selectAllRows`, `selectOneRow`, and `executeStatement` to receive SQL whose native inferred type is one or more non-blank compile-time constant strings. Literals, native constants, non-interpolated nowdocs or heredocs, and finite constant-string `match` or conditional results are valid. General strings, non-constant interpolation or concatenation, blank variants, argument unpacking, PHPDoc-only narrowing, first-class callables, and callable-array indirection are not.

Statements remain static by default. If one operation needs variable structure, parse the external value into a concrete typed choice and map it to a finite, code-owned set of complete reviewed statements. Prefer complete statements; use a finite operation-local constant fragment only when it keeps the call clearer and the resulting SQL still has a finite constant-string type. Reject an unknown choice before database work. Do not add a generic SQL sanitizer, identifier quoting helper, query builder, SQL template engine, or dialect abstraction.

Only named data parameters are accepted. Names use an optional leading colon followed by a letter or underscore and then letters, digits, or underscores. Invalid names and inputs containing both prefixed and unprefixed forms of the same name fail before a query budget or trace records database work. Every application or external data value remains a parameter even after validation, and each placeholder occurrence uses a distinct name because repeated named placeholders behave differently across native PDO drivers. Prepared statements cannot bind identifiers, keywords, operators, ordering directions, or arbitrary SQL fragments.

`Connection` binds strings, integers, booleans, and null with explicit PDO parameter types. Arrays and objects must be transformed by application code before execution. Selected columns and expressions must have unique names or aliases because associative fetching cannot preserve duplicate keys. Raw driver values remain `mixed` and are parsed immediately by an application projection because engines can return different scalar representations.

`executeStatement` returns PDO's affected-row count. PHPThis certifies exact counts only for unambiguous single-row inserts and deletes. Do not use affected-row counts for reads, and test any update matched-versus-changed semantics against the selected engine.

## Database authority

Every application records the runtime authority of each named connection in `.ai/data.md`: required objects and actions, explicitly unavailable schema or administrative actions, verification evidence, and the source and date of that evidence. The web runtime must not receive schema-owner, migration, role-management, grant-management, or administrator credentials. A separately authorized migration or deployment path receives elevated credentials only for that operation and does not expose them to request handling.

Least privilege is engine-specific and not enforced by `Connection`. SQLite uses file ownership, permissions, and process isolation rather than database roles. Separate `Connection` objects using the same DSN or credential do not prove privilege separation. Integration tests or safe privilege inspection against the deployed engine establish the recorded policy; do not probe a shared or production database with destructive statements.

## Transaction policy

Transactions are manual: begin, execute, and commit inside `try`; in `finally`, roll back if the transaction remains active. This preserves normal exception propagation while making cleanup visible. PHPThis will not add a callback helper that hides this control flow.

A transaction belongs to one PDO connection. Work across two connections or engines is not atomic, even when both local transactions commit successfully.

The sample `POST /users` path performs two writes: one user row and one `user.created` event selected through the unique email. Both affected-row counts must be one. The handler prepares its success response before beginning, commits only after both writes, and rolls back when a failure or query-budget rejection leaves the transaction active.

The sample `GET /users` path binds the validated last-emitted user ID, or the code-owned first-page sentinel `0`, and selects up to 51 ascending users with event counts in one aggregate statement. The handler emits at most 50 rows and uses the extra row only to prove that a continuation exists. The derived user selection applies the keyset predicate and bound before joining events, and `user_events.user_id` is indexed in the sample schema. Every accepted page receives its own one-statement budget and trace; 125-row traversal evidence proves 50/50/25 output with no gaps or duplicates in a static fixture.

## Query trace policy

Each request constructs one `QueryTrace` per connection with an explicit retained-fingerprint limit and passes it to `Connection` beside the `QueryBudget`. The trace performs no file or network I/O. It aggregates a SHA-256 fingerprint of each exact SQL string with execution count, failure count, and prepare/bind/execute duration in integer microseconds.

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
