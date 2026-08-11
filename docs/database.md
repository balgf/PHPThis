# Database design

The database layer deliberately exposes SQL. Its job is limited to connection policy, typed parameter binding, predictable fetching, transactions, statement counting, and bounded query tracing. It does not sanitize or generate statements.

Applications create this boundary with `Connection::connect` in their composition root. `PHT005` resolves names and types and rejects application-owned construction of `PDO` or its subclasses so query budgets and traces cannot be bypassed with an alias, typed class-string, or anonymous subclass.

Credentials reach that composition root through the application-owned pattern in `docs/configuration.md`. One PHP file reads exact external names and validates them before application-controlled I/O into separately named final readonly runtime or migration values. HTTP invokes only the runtime factory and visibly passes its fields with an explicit `QueryBudget` and `QueryTrace`; migration and administrative values never fall back to runtime values. `Connection::connect` marks its password parameter with `#[\SensitiveParameter]`, which reduces stack-trace disclosure only.

## Driver and dialect policy

PHPThis provides PDO transport, not portable SQL. `Connection::connect` accepts a native PDO DSN, optional credentials, and additional driver options. The framework keeps `ext-pdo` as its only runtime database requirement. An application declares the actual runtime extension for every engine it uses, such as `ext-pdo_sqlite`, `ext-pdo_mysql`, or `ext-pdo_pgsql`.

The [base transport certification matrix](#pdo-transport-certification-matrix) covers SQLite, MySQL, and PostgreSQL. It proves native connection, named scalar and null binding, associative fetching, deliberate single-row DML counts, local commit and rollback, independent connections, query budgeting, query tracing, and PDO failure propagation. The PHPThis framework repository runs SQLite in its maintainer `composer check`; dedicated framework CI services run all three through its `composer test:database-drivers` script. Dependency scripts are not inherited by consuming applications, which own their engine-specific integration command.

The harness uses fixed, code-owned table names so its statements remain finite under PHT006. It creates and drops those tables. MySQL and PostgreSQL certification therefore requires a disposable or dedicated test database with credentials intentionally authorized for that fixture; never point it at a shared or production database. Its DDL-capable test credential is not a production least-privilege example. The harness does not pre-drop a table and drops only a table created by that run, so an interrupted run can require resetting the dedicated database before retrying.

Certification does not make SQL dialects interchangeable. Complete SQL, DDL, schema and migration policy, identifier quoting, generated identifiers, returned scalar representations, error translation, isolation, locking, execution plans, charset, timezone, TLS, and timeouts remain application-owned and engine-specific. Other versions and PDO drivers may be passed to the same connection API, but PHPThis does not call them certified until that exact version passes the reviewed base harness and the matrix is updated.

### PDO transport certification matrix

This is the one maintained matrix for current framework PDO transport certification. The dedicated CI job supplies the exact expected version for every selected driver; the harness queries the connected engine through one separately budgeted and traced statement, rejects a mismatch before fixture DDL, and reports the observed version. Its maintained SQLite negative control first supplies an impossible expected version, requires the exact bounded mismatch diagnostic and removal of the pre-DDL fixture, then proves clean recovery through the normal certification run. A green candidate run proves the rows below for that candidate. Repository wording or a configured service tag without that passing run is not certification evidence.

| PDO driver | CI provision | Required exact engine or server version |
| --- | --- | --- |
| `sqlite` | PHP 8.4 `pdo_sqlite` on the `ubuntu-24.04` runner | SQLite `3.45.1` |
| `mysql` | Official `mysql:8.4` service | MySQL `8.4.11` |
| `pgsql` | Official `postgres:17` service | PostgreSQL `17.10` |

The runner and service selectors may receive upstream updates. When an update changes the engine or server version reported by the connection, the expected-version assertion fails visibly rather than silently broadening the claim; maintainers then review the new exact version, update this matrix and the CI expectation together, and obtain a new green three-driver run. Rebuilds that retain the same reported version are not distinguished by this harness. A local harness run with no expected-version variable reports the exact engine it exercised but does not change this CI matrix.

No unlisted patch, minor, major, distribution build, extension build, service topology, or managed offering inherits certification from a listed row. Applications test their exact deployed engine, extension, schema, statements, returned representations, errors, isolation, locking, privileges, TLS, timeouts, and operational topology independently.

An application using multiple databases constructs separately named `Connection` objects in its composition root. Connections do not participate in a distributed transaction. Give each connection an explicit budget and a distinct trace: a query trace contains no connection identity, so sharing one across engines could merge identical SQL fingerprints. Consumer Contract v5's terminal summary requires each registered source to own distinct budget and trace observation state; do not share a request-wide budget across those sources.

## Why no ORM or query builder

Lazy relationships can perform I/O during property access, while fluent query APIs can obscure the SQL shape and encourage broad reusable abstractions. Both increase the context an AI needs to reason about cost. PHPThis keeps the statement at the behavior boundary.

This is not an argument that every ORM query is slow or that raw SQL is automatically fast. An AI can still put an explicit query in a loop. That is why the framework also requires query budgets, loop rules, bounded reads, and scale tests.

## N+1-safe nested resource plans

Nested response data is valid only when its operation owns a complete, visible, and bounded I/O plan. The number of database statements, cache operations, and external calls remains fixed as the bounded parent page grows. Execute that plan first, parse database rows through concrete projections, and then map and JSON-encode only already-loaded values. Per-resource mapping, serialization, callbacks, and recursive traversal perform no I/O.

For an ordinary to-one relationship such as `workspace.creator`, prefer one explicit bounded join that selects each required workspace and creator expression by name. Keep the join condition uniqueness-preserving and bind explicit parent and child tenant and authorization predicates. Select only public operation fields; availability in the joined row does not authorize disclosure of email, role, status, or another sensitive value. When both `created_by_user_id` and `creator.id` are emitted, their equality is an explicit tested invariant rather than an assumption made by a mapper.

One statement is not a universal requirement. A finite batch plan may instead use a fixed number of reviewed statements when one join is inappropriate, such as one bounded parent-page statement followed by one bounded child statement over a finite application-owned identifier shape. Every structural shape remains a PHT006-finite reviewed choice with explicit named bindings, and the complete plan still has a compile-time-known maximum statement count. Never execute one child query per parent, hide repeated I/O behind a mapper, or introduce a repository, relationship loader, generic batcher, or generated placeholder list.

Parent pagination remains authoritative. A joined child must not change the maximum number of parents, stable parent ordering, continuation, or duplicate-parent behavior. Before joining a to-many child, prove that fan-out cannot apply the parent `LIMIT` to joined rows or otherwise skip or duplicate parents; a bounded parent-page read followed by one fixed child batch is often clearer. Every nested collection separately owns its maximum cardinality, deterministic order, truncation, continuation, and response-byte contribution. An optional to-one relationship owns one exact object, `null`, or absence rule.

`PHT003` catches direct lexical calls to `selectAllRows`, `selectOneRow`, or `executeStatement` inside a loop. It cannot prove that an indirectly called method performs no database work, and it does not inspect cache or integration I/O. Therefore compare a one-parent fixture with the maximum parent-page fixture and assert the same envelope and item shape, identical fixed database-statement/cache-operation/external-call counts, and no counter changes during mapping or JSON encoding. Cover empty and maximum pages, present and supported missing children, exact nested fields and native types, deterministic ordering, tenant isolation, authorization denial, and parent pagination under representative fan-out. Frontend decoder evidence separately rejects malformed nested shapes without making JSON object-member order contractual.

A constant statement count does not prove bounded database cost. Review exact-engine indexes, execution plans, rows scanned, join fan-out, projection width, result cardinality, and response size with representative data. `QueryBudget` contains accidental statement growth; it does not certify those costs. The existing isolated N+1 negative control in `tools/test-query-scaling.php` demonstrates both query growth and budget containment and is deliberately invalid repository evidence, not an accepted application implementation.

This guidance adds no PHPThis runtime relationship mechanism, ORM, lazy loading, resource serializer, paginator, or new Strict Profile diagnostic. The response representation and its SQL, projections, mapping, decoder, compatibility policy, and evidence remain application-owned.

## SQL data and finite structure

PHT006 requires direct calls to `selectAllRows`, `selectOneRow`, and `executeStatement` to receive SQL whose native inferred type is one or more non-blank compile-time constant strings. Literals, native constants, non-interpolated nowdocs or heredocs, and finite constant-string `match` or conditional results are valid. General strings, non-constant interpolation or concatenation, blank variants, argument unpacking, PHPDoc-only narrowing, first-class callables, and callable-array indirection are not.

Statements remain static by default. If one operation needs variable structure, parse the external value into a concrete typed choice and map it to a finite, code-owned set of complete reviewed statements. Prefer complete statements; use a finite operation-local constant fragment only when it keeps the call clearer and the resulting SQL still has a finite constant-string type. Reject an unknown choice before database work. Keep complete raw SQL and its explicit named parameter array visible together at the direct call whenever practical. Do not add an ORM, repository, generic SQL sanitizer, identifier quoting helper, query builder, SQL/binding/placeholder helper, generic paginator, runtime SQL generator, arbitrary SQL string, transaction callback, SQL template engine, or dialect abstraction.

Only named data parameters are accepted. Names use an optional leading colon followed by a letter or underscore and then letters, digits, or underscores. Invalid names and inputs containing both prefixed and unprefixed forms of the same name fail before a query budget or trace records database work. Every application or external data value remains a parameter even after validation, and each placeholder occurrence uses a distinct name because repeated named placeholders behave differently across native PDO drivers. Prepared statements cannot bind identifiers, keywords, operators, ordering directions, or arbitrary SQL fragments.

`Connection` binds strings, integers, booleans, and null with explicit PDO parameter types. Arrays and objects must be transformed by application code before execution. Selected columns and expressions must have unique names or aliases because associative fetching cannot preserve duplicate keys. Raw driver values remain `mixed` and are parsed immediately by an application projection because engines can return different scalar representations.

A projection also validates any representation required by its next explicit sink. For example, a selected string later emitted as JSON must be valid UTF-8 at the named database-projection boundary; do not defer corrupt stored bytes to `json_encode`, substitute them, or normalize them implicitly. A failed database projection remains an application-owned unexpected failure unless the application has accepted and proved a narrower explicit policy. Field byte or character limits remain separate schema or operation decisions and must not be inferred from an optional cache's admission policy.

`executeStatement` returns PDO's affected-row count. PHPThis certifies exact counts only for unambiguous single-row inserts and deletes. Do not use affected-row counts for reads, and test any update matched-versus-changed semantics against the selected engine.

## Database authority

Every application records the runtime authority of each named connection in `.ai/data.md` per named operation: exact objects and actions, code-owned statement source, explicitly unavailable namespace or administrative actions, engine-specific effective-authority resolution, verification evidence, and the source and date of that evidence. Record only applicable sources, such as direct, role or inherited, public or default, database or global, ownership-chain, IAM, or filesystem and process authority. The web runtime must not receive namespace-control, migration, identity-management, authority-management, or administrator credentials. A separately authorized migration or deployment path records its own exact required and prohibited capabilities and receives elevated credentials only for that operation without exposing them to request handling.

Least privilege is engine-specific and not enforced by `Connection`. SQLite uses file ownership, permissions, and process isolation rather than database roles. Separate `Connection` objects using the same DSN or credential do not prove privilege separation. Integration tests or safe privilege inspection against the deployed engine establish the recorded policy; do not probe a shared or production database with destructive statements.

### Authority activation lifecycle

Configuration and source presence do not activate database authority. A successful connection or trivial query proves reachability only, and migration success or object existence does not prove that a separate runtime identity can execute an application's statements.

Database and object definition source; database/catalog/schema/attachment namespace selection and qualification as supported; namespace and object control-or-ownership; and active authority are separate facts. PHPThis neither requires nor discourages an application-specific namespace. Record whether the application uses an engine-default or separately named namespace, how statements qualify it or use an engine-supported bounded lookup policy, which identity controls or owns namespaces and created objects when the engine has that concept, and how future-object or default authority behaves. Record the control-or-ownership model as not applicable when unsupported. PHPThis supplies no namespace, controlling identity, lookup policy, identity topology, or permission set.

Zero runtime object access is valid while no named application operation requires it. When an operation is added, activate only its exact object actions and verify every statement it can execute under the runtime identity before enabling traffic. Also verify selected prohibited namespace, DDL, identity or role, authority-management, administrative, migration-ledger, and unrelated-object actions through safe exact-engine tests or engine-supported authority inspection. Effective authority can come from sources other than a direct object grant, including applicable role or inherited, public or default, database or global, ownership-chain, IAM, or filesystem and process mechanisms. Do not run destructive negative probes against shared or production data.

Each adopted authority activation or deactivation has one explicit application-owned owner and path. Engine-specific `GRANT` or `REVOKE` SQL may be visible and checksum-covered inside a migration when the selected engine supports and uses it, or a separately authorized application or external provisioning stage may own the transition. Record which path applies, its failure and redaction behavior, and its verification handoff. Never activate or deactivate database authority, migrate, or perform identity or namespace administration from HTTP request handling.

`.ai/operations.md` records the chosen order among provisioning, migration, authority activation, exact-engine verification, application rollout, and traffic enablement. PHPThis chooses no universal migration-first, code-first, rolling, or maintenance-window sequence. A failed dependent stage stops rollout; remove or drain dependent code before deactivating its authority or removing its namespace. See [ADR 038](decisions/038-application-owned-database-authority-lifecycle.md).

## Transaction policy

Transactions are manual: begin, execute, and commit inside `try`; in `finally`, roll back if the transaction remains active. This preserves normal exception propagation while making cleanup visible. PHPThis will not add a callback helper that hides this control flow.

A transaction belongs to one PDO connection. Work across two connections or engines is not atomic, even when both local transactions commit successfully.

## Database migration policy

Migrations are specialized application-owned database evolution. For a new application that adopts them, ADR 039 recommends `src/Database/Migrations/` with a matching namespace such as `App\Database\Migrations`. The application records its actual source path and namespace in `.ai/migrations.md`; any coherent alternative remains valid. PHPThis does not enforce placement, discover work from a directory, silently relocate established source, or create an empty migration directory in the database-free skeleton. This recommendation does not establish `Database/Queries`, a repository layer, or another request-time SQL boundary: runtime SQL remains in its handler or one justified concrete operation.

When multiple named database connections adopt separately tracked migration histories, `.ai/migrations.md` records each connection's engine/version decision, exact accepted initial baseline, source, namespace, finite command ownership within the sole application console, typed-configuration/process-identity reference to `.ai/configuration.md`, database-authority reference to `.ai/data.md`, transition implementation, coordinator, manifest, ledger, atomicity, coordination, recovery, output, per-history compatibility and handoff constraints, exact-engine evidence, and any application-selected connection-owned subdivision. Exact configuration and process identity remain authoritative in `.ai/configuration.md`; effective database-authority facts and accountable transition ownership remain authoritative in `.ai/data.md`; `.ai/operations.md` alone owns the application-wide release sequence and operational runbooks. No policy inherits implicitly and credentials are never combined. Histories are independent only when their managed objects, data, authority transitions, and coordination domains are proved disjoint; intersecting histories require a dependency order, shared exclusion or serialization, cross-history partial-state detection, and application-wide forward recovery. PHPThis prescribes no subdivision spelling and creates no speculative connection directories for a single-database application.

ADR 043 defines the engine-neutral application-owned invariants, and ADR 027 accepts one SQLite migration-ledger proof without extending `Connection`. Every adopted history records its exact accepted initial state, finite ledger metadata acceptance surface, all checksum-covered DDL, data, and authority effects, and one stable application/environment/history coordination identifier or namespace with its collision policy. All writer topologies that can reach that history share one exclusion domain or are authority-gated against overlap. An expiring or losable owner does not release a successor to mutate until prior work is fenced, its database session and in-flight work are confirmed invalidated or terminated, or another exact-engine exclusion boundary proves termination. PHPThis supplies no ledger, coordinator, fencing, lock, authority-gating, or recovery primitive. The ADR 027 SQLite proof's sole `database:migrate` command runs outside HTTP through a separately composed process entrypoint and configuration; the application records effective SQLite file/process authority overlap and residual schema-mutation risk instead of claiming capability separation the engine cannot express. One final concrete coordinator contains a finite manifest, names migration steps in permanent order, and invokes private step methods explicitly; no loop performs database I/O, and no directory, class name, ledger value, or runtime `.sql` file selects executable work. ADR 038 adds no migration mechanism: it requires an adopting application to state whether any runtime authority transition is checksum-covered migration work or a separately authorized ordered stage.

Each migration step keeps its complete raw SQLite statement constants at direct `Connection` calls and derives a SHA-256 checksum from its permanent identifier and exact statement sequence. The bounded `application_migrations` ledger stores `position`, `migration_id`, `checksum_sha256`, and `applied_at_epoch`; its insert explicitly selects SQLite `unixepoch()` with no hidden default. The coordinator validates the complete bounded position/identifier/checksum prefix before pending work, then commits each pending migration and its ledger row through a separate explicit transaction. It never mutates applied history or infers a down migration; corrections are new forward migrations.

One application-private nonblocking `flock` is acquired before database work. It coordinates only cooperating same-host processes using the same path and does not replace SQLite locking or prove distributed coordination. SQLite permits one writer at a time. MySQL implicit DDL commits and PostgreSQL command-specific transaction and lock behavior prevent this from being a portable migration recipe. See [Explicit application migrations](migrations.md), [ADR 043](decisions/043-engine-specific-application-migration-invariants.md), and [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md).

The sample `POST /accounts/{account_id:positive-int}/users` path performs four writes: one user row guarded by current actor membership, one new `account_users(user_id, account_id)` relation, one account-scoped `user.created` event, and one bounded versioned welcome-job row. Actor access remains in `account_memberships(principal_id, account_id)`; matching numeric IDs do not create a principal-to-user mapping. Every affected-row count must be one. The handler prepares its success response before beginning, commits only after all four writes, and rolls back when a failure or query-budget rejection leaves the transaction active. The job is commit-visible publication on that same SQLite connection, not an after-commit callback.

The sample `GET /users` path binds the validated last-emitted user ID, or the code-owned first-page sentinel `0`, and selects up to 51 ascending users with event counts in one aggregate statement. The handler emits at most 50 rows and uses the extra row only to prove that a continuation exists. The derived user selection applies the keyset predicate and bound before joining events, and `user_events.user_id` is indexed in the sample schema. Every accepted page receives its own one-statement budget and trace; 125-row traversal evidence proves 50/50/25 output with no gaps or duplicates in a static fixture.

## Finite application data-path proof

ADR 022 extends the application evidence with protected `GET /accounts/{account_id:positive-int}/documents`. It does not extend `Connection`. `ListDocumentsPageRequest` accepts only `order=rank_asc|rank_desc`, an optional exact `v1:<order>:<sort_rank>:<document_key>` cursor, and an optional actual query-list `categories` value with maximum cardinality three. Omitted categories mean no filter. A direct empty list or parsed `['']` shape maps to an empty selection and returns an empty page without calling the protected connection; native PHP inputs such as `?categories[]=` produce that parsed shape. An empty string mixed with a category remains invalid.

For each direction, `ListDocumentsHandler` contains four complete raw SQLite statements: no category predicate, or exactly one, two, or three category placeholders. These eight statements and their explicit named parameter arrays remain at direct `selectAllRows` call sites. They bind the requested account, resolved tenant account, principal identifier, membership tenant account, cursor-presence flag, cursor rank twice for the composite predicate, cursor document key, applicable category values, and 51-row fetch limit through distinct portable placeholder names. `cursor_is_absent=1` explicitly disables the comparison on a first page, so invalid stored rank or key representations are not silently removed before projection parsing. Nothing generates a statement or placeholder list at runtime.

The stable composite order is numeric `sort_rank`, then case-sensitive `document_key COLLATE BINARY`, both in the selected direction. The response emits at most 50 parsed `DocumentSummary` values and derives `next_cursor` from the last emitted row only when the extra row proves another result. Tests cover both directions, cursor compatibility, equal-rank tie breaks, every category cardinality, SQL-looking bound data, rejection before SQL, and one statement per non-empty page across fixture sizes.

This is SQLite-specific application evidence under the current PDO SQLite runtime, not certification of a pinned SQLite application version. The MySQL and PostgreSQL harnesses still certify the base PDO transport contract, not these statements or collation semantics. A static cursor traversal is not a snapshot under concurrent mutation. Explicit tenant predicates, PHT006, and adversarial parameter tests are complementary path evidence, not universal authorization or SQL-injection certification. See [ADR 022](decisions/022-application-owned-finite-data-paths.md).

## Query trace policy

Each request constructs one `QueryTrace` per connection with an explicit retained-fingerprint limit and passes it to `Connection` beside the `QueryBudget`. The trace performs no file or network I/O. It aggregates a SHA-256 fingerprint of each exact SQL string with execution count, failure count, and prepare/bind/execute duration in integer microseconds.

The trace never retains SQL text, parameter names or values, DSNs, credentials, exception messages, driver details, or stack traces. Different bindings for the same SQL therefore produce one fingerprint without exposing the bindings. When the fingerprint bound is full, global counts and timing continue while `truncated` and `untracked_statements` make the missing detail explicit.

`QueryTrace::snapshot()` is a versioned JSON-compatible record. Tests inspect it in memory. ADR 023 permits one application-owned terminal coordinator to derive a bounded per-source record from it; `Connection` still never writes one log entry per statement. Calls rejected by `QueryBudget` are absent because PDO was never attempted, while the source's budget state records that rejection. Timing does not include fetch or row conversion.

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
