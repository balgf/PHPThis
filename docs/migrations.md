# Explicit application migrations

PHPThis defines [universal application-owned migration invariants](decisions/043-engine-specific-application-migration-invariants.md) and provides no core or cross-engine migration runtime. [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md) remains the first executable proof. Its SQLite transaction, rollback, ledger-definition, timestamp, and same-host `flock` choices belong to that example; consumers adopt only the invariants plus a separately reviewed decision for their exact engine and topology.

Migrations are specialized application-owned database evolution, not an independent framework subsystem. Their dedicated `.ai/migrations.md` context keeps engine, history, authority, coordination, and per-history compatibility, handoff, failure, and recovery constraints focused while `docs/database.md` remains the entry point for the broader database boundary. `.ai/operations.md` alone owns release and cross-history recovery execution sequencing.

## Recommended application structure

For a new application that adopts migrations, PHPThis recommends:

```text
src/
  Database/
    Migrations/
```

Use the matching namespace under the application's Composer root, such as `App\Database\Migrations`, and record the actual path and namespace in `.ai/migrations.md`. A consumer may choose any coherent alternative. Its recorded project context is authoritative; PHPThis does not enforce a path through the checker or Strict Profile, discover executable work from a directory, or silently relocate established migration source. An AI needs explicit human approval before changing an established placement and must account for namespace, autoloading, console composition, tests, and deployment references.

A database-free skeleton creates no empty migration directory. The directory is created only when the application adopts migrations and only at its selected location.

If multiple named database connections own separately tracked migration histories, the application may choose and record an explicit connection-owned subdivision for each adopted history. Record each connection's engine/version decision, source path, namespace, finite command ownership within the sole application console, typed-configuration/process-identity reference to `.ai/configuration.md`, database-authority reference to `.ai/data.md`, coordinator, manifest, ledger, atomicity, coordination, recovery, output, per-history compatibility and handoff constraints, and exact-engine evidence. `.ai/operations.md` alone owns the application-wide release sequence and operational runbooks. Apply every adoption field below separately to each history; no field inherits implicitly and credentials are never combined. Call histories independent only after proving their managed objects, data, authority transitions, and coordination domains are disjoint. Histories that intersect require an explicit dependency order, one shared exclusion or serialization boundary, every cross-history partially completed state, and application-wide forward recovery. PHPThis recommends no subdivision spelling: do not create speculative connection directories for a single-database application or for a connection without its own migration history.

This recommendation is deliberately limited to migration source. It does not recommend a generic `Database/Queries` directory, repository, query-object layer, or alternate SQL execution boundary. Request-time SQL remains in the handler or the one justified narrowly named concrete operation that owns its transaction. See [ADR 039](decisions/039-recommended-database-migration-structure.md).

## Adoption boundary

An application with no database or no application-owned migration path records `NOT_APPLICABLE(MIGRATIONS)`. Before adoption, the accountable human approves and the application records:

- the exact engine and supported version, accepted engine-specific database/catalog/schema/attachment namespace model, namespace and object control-or-ownership or non-applicability, DDL, authority, ledger-consistency, coordination and recovery decision, and integration-test command;
- the exact accepted initial state for each history, including every externally pre-provisioned database, catalog, schema, role, authority, or coordination object, every application-managed object, effect, or ledger row already present, and every state that must be absent;
- the selected migration source path and application namespace;
- the sole application console plus each separately tracked history's finite explicit command spelling, typed-configuration/process-identity reference to `.ai/configuration.md`, and database-authority reference to `.ai/data.md`;
- the permanent identifier grammar, finite manifest maximum, canonical order, and checksum byte format for statements plus code-owned binding values or finite binding-derivation policies;
- the ledger name, maximum rows, parser bounds, required position/identifier/checksum representation, any selected bounded operational metadata and its source, and finite exact-engine definition-verification surface, including accepted and rejected columns, types, nullability, defaults, keys, constraints, indexes, triggers, rules, policies, ownership, and authority as applicable;
- the exact SQL and ledger-consistency transition for bootstrap and every migration, including transaction or implicit-commit behavior and every observable non-atomic intermediate state across checksum-covered DDL, data, and authority effects as applicable;
- the accountable authority-activation and deactivation owner plus authority facts and evidence in `.ai/data.md`, and the selected transition implementation source and complete non-HTTP path in `.ai/migrations.md`, including whether engine-specific `GRANT` or `REVOKE` SQL is supported, selected, and checksum-covered by a migration or the transition runs as a separately authorized versioned stage;
- the per-history runtime-authority activation handoff constraints, with the application-wide gate and execution order in `.ai/operations.md` and engine-specific effective-authority resolution evidence in `.ai/data.md`;
- every supported writer topology and its concrete coordination mechanism, stable application/environment/history identifier or namespace and collision policy, owner, exact creation, acquisition, use, and release permissions or authority, protected or serialized interval, contention, timeout, loss, expiry, session, process, crash, bypass-denial and recovery behavior as applicable, plus what it does not coordinate and how all topologies that can reach one history share exclusion or are authority-gated against overlap;
- DDL locks, timeouts, busy behavior, expected duration, maintenance-window and availability policy;
- immutable-history, forward-correction, authority deactivation, failed-deployment, backup, restore, and recovery policy;
- each history's compatibility and handoff constraints plus the `.ai/operations.md`-owned application order among adopted histories, migration, authority activation, verification, application rollout, traffic enablement, later deactivation, and namespace removal, including the operational state and forward-recovery sequence when one history completes and another fails;
- exact exits, stdout and stderr objects, finite diagnostic vocabulary, and redaction; and
- automated exact-initial-state, rerun, drift, same- and cross-topology concurrency, cross-history behavior where applicable, every applicable non-atomic or crash-visible partial state, recovery, bounded-ledger, real-console, and exact-engine runtime-authority evidence.

Do not copy the SQLite proof into another engine by changing its DSN. Record a separate engine-specific decision first.

## Universal application-owned invariants

Each separately tracked adopted history keeps one finite unrolled source manifest, immutable checksum-covered history, one bounded inspectable ledger, finite redacted outcomes, and recorded per-history compatibility, handoff, and recovery constraints. `.ai/operations.md` alone owns the application-wide release and cross-history recovery execution sequence. The history's finite explicit command runs through the sole application console with history-scoped configuration and migration authority, validates the complete bounded history before pending work, never executes ledger content, never silently repairs or manufactures history, and corrects committed history only through a new forward migration or another separately human-authorized recovery action.

Ledger consistency is universal; one transaction shape is not. A completed ledger row must correspond to every checksum-covered migration effect the application accepts as applied, including DDL, data, and authority changes when the step owns them. The engine-specific decision states whether those effects and the ledger row can commit atomically and separately records the selected engine's DDL atomicity limits. If they cannot commit together, enumerate every state between each effect and ledger update, detect those states before later work, stop rather than claim success, and name the reviewed forward reconciliation, restore, or other recovery action. Never write an applied row before all corresponding effects exist or claim rollback unsupported by the selected engine and statements.

Concurrency coverage is universal; one lock is not. Record every migration-writer topology, one concrete coordination mechanism and owner for each, the stable application/environment/history coordination identifier or namespace and its collision policy, exact creation, acquisition, use, and release permissions or authority, the protected or serialized interval, contention behavior, and what process, session, host, filesystem, cluster, expiry, loss, or crash boundary limits it. All topologies that can reach one history participate in one shared exclusion domain or use explicit authority gating that makes cross-topology overlap impossible; proving each topology only against itself is insufficient. Application- or database-owned coordination becomes effective before ledger bootstrap plus authoritative definition and history validation and remains effective through all checksum-covered migration effects and either accepted ledger completion or a finite fail-closed failure outcome. Detected coordination loss stops before the next mutation. If ownership can expire or be lost, a successor must also be unable to mutate until any in-flight statement from the prior owner is fenced, the prior database session and work are confirmed invalidated or terminated, or another exact-engine exclusion boundary proves termination; a post-statement ownership check or exited client process alone is insufficient. A partial state is never recorded as applied; after loss or process termination, the next owner reacquires coordination and re-detects the exact engine state before later work. Deployment or external serialization surrounds the complete console invocation, retains the same prior-owner database-work termination obligation when a runner disappears, and must prevent a bypassing invocation from obtaining migration configuration or authority. Prove same- and cross-topology exclusion at the mechanism's owning boundary. PHPThis neither chooses nor abstracts the mechanism.

## One finite source manifest

Keep every migration in ordinary application source. Each separately tracked history's final concrete coordinator names its migration steps in permanent order and invokes pending private step methods explicitly. A migration step owns its permanent identifier, complete engine-specific SQL constants, direct `Connection` calls, and SHA-256 checksum source.

The checksum covers a canonical byte sequence containing the permanent identifier, every executed statement in order, and every code-owned binding name, type, and literal value. When a binding is deliberately derived from already selected database state or another finite application-owned source, checksum the complete finite derivation policy and its input contract instead of the runtime result. Include any finite code-owned schema-shape policy that governs a conditional step. Deployment environment, command, network, or other unreviewed external input must not change a migration binding. The same statement constants, code-owned binding definitions, and derivation-policy constants used in that sequence drive the direct `selectAllRows`, `selectOneRow`, or `executeStatement` calls; data values use explicit unique named bindings. PHPStan must resolve every direct SQL argument to finite non-blank compile-time constants.

Never load executable SQL from a runtime file, ledger row, environment value, command argument, or network source. Never scan a directory, derive a class name, or discover migrations. Do not add an ORM, schema builder, migration DSL, query builder, repository, SQL/binding/placeholder helper, generic database facade, transaction callback, or method that accepts arbitrary SQL.

Do not perform a database call in a loop. The manifest is deliberately unrolled so execution order and the maximum number of calls remain visible. A finite loop may validate already fetched bounded ledger values when it performs no I/O.

The current example follows the recommendation with final `Example\Database\Migrations\SqliteApplicationMigrations`. Its seven permanent steps are `0001_create_user_schema`, `0002_create_job_schema`, `0003_prepare_document_schema`, `0004_add_document_category`, `0005_add_document_sort_rank`, `0006_create_document_access_schema`, and `0007_create_account_users`. The final step creates `account_users(user_id, account_id)` without deriving rows from principal-owned `account_memberships`. The manifest cap is 512 and the bounded ledger query uses `LIMIT 513`. Those names and limits document the proof; they are not reserved consumer migrations or a required consumer path.

## Bounded inspectable ledger

Each adopted history owns one bounded ledger with an exact engine-specific definition, manifest position, permanent identifier, checksum, finite history query, parser bounds, definition-verification policy, and any selected bounded operational metadata. Additional fields are finite, non-executable, validated, and never select migration work, define order, or authorize behavior. The application enumerates the finite metadata or catalog facts it accepts and which unrecorded or incompatible columns, types, nullability, defaults, keys, constraints, indexes, triggers, rules, policies, ownership, and authority it rejects where the engine exposes those concepts. It uses an engine-supported catalog or metadata query rather than inheriting SQLite byte comparison. A ledger row records only accepted history; it does not by itself make migration effects atomic, prove that the expected objects or data exist, or authorize the runtime identity. When checksum-covered effects and ledger insertion cannot commit together, the application's decision and tests own detection and recovery for the gap.

The accepted SQLite example stores one row per committed migration in `application_migrations`. Its columns are `position INTEGER PRIMARY KEY`, `migration_id TEXT UNIQUE`, `checksum_sha256 TEXT`, and `applied_at_epoch INTEGER`. Before reading history, it either creates that exact `STRICT` table or compares the complete stored SQLite table and automatic-index object set, including table SQL byte-for-byte, and rejects an incompatible definition or extra trigger or index. The ledger insert explicitly obtains `applied_at_epoch` from SQLite `unixepoch()` rather than a hidden default or PHP clock; it is insert-time evidence, while manifest position defines order. Each row therefore contains only:

- its expected manifest position;
- its permanent migration identifier;
- the lowercase-hex SHA-256 content checksum; and
- the explicit SQLite epoch timestamp.

The ledger stores no SQL, PHP class, callback, source path, serialized object, credentials, or migration output. Read at most the application maximum plus one row. The accepted history query selects and parses position, identifier, checksum, and every selected metadata field; in the SQLite proof, `applied_at_epoch` remains inspectable operational evidence constrained by the `STRICT` schema and is not an ordering input. Before applying any pending entry, reject overflow and unknown, duplicate, missing, reordered, malformed, or checksum-mismatched history. Do not silently repair, delete, reorder, or overwrite ledger rows.

Once recorded, a migration is immutable. Repair an incorrect historical step with a new forward migration. A down migration is neither inferred nor automatically generated; restoring a backup or executing an application-specific recovery action requires separate human authorization and operational policy.

Do not infer or manufacture a ledger prefix from tables found in an unledgered database. Every history records the exact initial baseline from which its first pending migration may run. That baseline may be an empty SQLite database or a server database with externally pre-provisioned database, catalog, schema, roles, authority, coordination objects, or an explicitly accepted ledger prefix that the migration identity validates rather than re-executes. Adopting an existing schema or history prefix requires a separate, reviewed application decision that proves its exact structure, ledger rows, checksums, and data assumptions before pending work. If a local example database is disposable, an accountable developer may instead back it up if needed and rebuild it explicitly; the migration command never deletes or silently baselines it.

## Engine-specific ledger-consistency path

An engine-specific decision names the exact transition for ledger bootstrap and each pending migration. When the selected statements and engine support atomic checksum-covered migration effects and ledger work and the application chooses that boundary, keep it visible:

```text
begin
  concrete migration-step direct SQL calls
  exact ledger insert
commit
finally rollback only if still active
```

When every checksum-covered DDL, data, or authority effect cannot commit with the ledger insert, do not imitate this sequence or rely on `rollBack()`. Keep the actual implicit commits and durable intermediate states explicit, verify the resulting state before any reconciliation, and stop before later manifest work. Recovery remains the reviewed engine-specific forward action; PHPThis does not infer it.

### SQLite reference transaction

The ADR 027 SQLite proof acquires its application-private nonblocking same-host migration lock before database work. After bounded ledger bootstrap and complete history validation, each pending migration uses the visible transaction above. The SQLite migration statements and ledger insert commit together. A failed migration leaves neither its schema changes nor its row when the selected SQLite statement supports the exercised transaction. Earlier migrations remain committed. Do not wrap the manifest in one transaction, hide cleanup in a callback, retry implicitly, or continue after a failure.

Use a fresh migration-scoped `Connection`, `QueryBudget`, and `QueryTrace`. The accepted seven-step example uses a 23-statement budget and trace plus PDO SQLite timeout 5; applying only 0007 to a valid six-step history uses four statements, and an unchanged run uses two. The migration command has a separately composed process entrypoint and configuration unavailable to HTTP; record its effective SQLite file/process authority overlap and residual schema-mutation risk instead of claiming table- or DDL-capability separation the engine cannot express. Do not expose migration configuration through HTTP or compose the coordinator during request startup.

## Authority transition and release handoff

Migration success proves the migration path only. It does not prove that a separately configured runtime identity can use newly created objects. Configuration and source presence likewise do not activate authority.

If a migration owns engine-specific `GRANT` or `REVOKE` SQL that the selected engine supports and uses, keep each complete statement visible at its direct `Connection` call and include it in the migration's canonical checksum sequence. If another authorized application or external provisioning stage owns the transition, name that stage, its version or source, its authority, its ordering relative to the migration, and its exact-engine verification. Never leave the transition implicit or run it through HTTP startup.

Before dependent code receives traffic, positive evidence executes its exact runtime statements under the runtime identity and negative evidence safely checks selected prohibited namespace, DDL, identity or role, authority-management, administrative, migration-ledger, and unrelated-object actions. Record how effective authority resolves using only applicable engine sources, such as direct, role or inherited, public or default, database or global, ownership-chain, IAM, or filesystem and process authority. On shared or production data, prefer safe engine-supported authority inspection to destructive denial probes.

The application records old-code and new-code compatibility, each abort gate, and whether a failure uses binary rollback, a new forward migration, a separately authorized restore, or another reviewed recovery action. When several histories participate in one release, it also records their dependency order and the application-wide state and forward recovery after any earlier history completes and a later one fails. PHPThis does not prescribe migration-first or code-first rollout. A failed migration, authority transition, or authority verification stops its dependent stage; remove or drain dependent code before deactivating its authority or removing a namespace. See [ADR 038](decisions/038-application-owned-database-authority-lifecycle.md).

## Command and output

Add each separately tracked history's finite migration command to the application's sole console and compose only that history's scoped configuration and authority. Do not add it to framework `vendor/bin/phpthis`, Composer dependency hooks, the front controller, or an automatically discovered command map. A deployment may call the explicit application command only after its own human-approved release policy has authorized that external mutation.

Record one finite stable exit and stdout/stderr contract for every success and failure. The ADR 027 example exits `0`, emits one newline-terminated JSON object to stdout, and writes nothing to stderr for its `applied` and `up_to_date` outcomes. Its migration failures exit `1`, leave stdout empty, and write `{"error":"migration_failed","reason":"<reason>","migration":<code-owned-id-or-null>}\n` to stderr, with exactly `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`. The migration field is one code-owned manifest identifier or `null`; stored or submitted identifiers are never reflected. Invalid command syntax retains that application's exit-2 input contract. Another application may record different finite code-owned bytes; it must not inherit the example vocabulary accidentally.

Never include paths, DSNs, credentials, SQL, bindings, exception details, ledger contents, schema contents, or application data in command output. Operational diagnosis belongs in a separately reviewed bounded destination; diagnostics must not create a second migration outcome channel.

## SQLite topology and other engines

The accepted proof is SQLite-specific. SQLite permits only one writer at a time, so the application still records its transaction mode, busy timeout, journal and synchronization settings, local filesystem behavior, and maintenance-window policy. The example appends `.migration.lock` to the canonical database path, sets mode `0600`, and verifies that the opened regular single-link inode still matches the path before and after taking a nonblocking same-host `flock`. The parent directory must prevent an untrusted principal from unlinking or replacing that path; file mode alone cannot provide that guarantee. The lock coordinates only cooperating processes using that exact path and does not coordinate multiple hosts or processes that bypass it. See [SQLite transactions](https://www.sqlite.org/lang_transaction.html).

MySQL DDL commonly commits implicitly and cannot generally share atomicity with a ledger insert. PostgreSQL permits many transactional DDL operations but has command-specific exceptions and server-side lock semantics. See [MySQL implicit commits](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html), [MySQL atomic DDL](https://dev.mysql.com/doc/refman/8.4/en/atomic-ddl.html), [PostgreSQL explicit locking](https://www.postgresql.org/docs/current/explicit-locking.html), [`CREATE DATABASE`](https://www.postgresql.org/docs/current/sql-createdatabase.html), and [`CREATE INDEX`](https://www.postgresql.org/docs/current/sql-createindex.html).

Those are warnings, not alternative PHPThis recipes. Each non-SQLite adoption applies ADR 043's invariants through its own reviewed statements, ledger-consistency boundary, coordination topology, privilege boundary, partial-failure recovery, and integration evidence.

## Required evidence

Each adopted history executes its real application console command in fresh subprocesses and proves the command-owned behavior below against the exact engine and version. Application- or database-owned coordination is exercised through concurrent real commands. When more than one writer topology can reach the history, evidence also crosses those topologies or proves the authority gate that prevents their overlap. Deployment or externally owned serialization is exercised at that owning boundary around the complete command, including denial of a bypassing invocation that lacks migration configuration or authority and proof that a disappeared runner cannot leave unfenced database work active while a successor mutates.

- starting from the exact recorded initial baseline, including any externally pre-provisioned objects, already-present application-managed state, and deliberately absent state, the command validates the accepted ledger prefix and applies only the complete pending manifest in its exact order;
- the exact finite engine-specific ledger metadata surface is accepted and every selected incompatible or extra definition is rejected before history parsing or pending work;
- the exact bounded ledger contains each expected identifier, position, checksum, and every selected valid bounded metadata value;
- rerunning unchanged history returns the application's recorded unchanged-history success outcome and executes no pending manifest step;
- editing previously applied checksum-covered content rejects drift before later migration work;
- an unknown, duplicate, missing, reordered, malformed, or overflowing ledger fails closed;
- same- and cross-topology concurrent migration writers encounter the recorded contention, shared exclusion, or authority-gating behavior without silently advancing the history, and every expiring or losable mechanism prevents a successor mutation while prior-owner work may remain active;
- every applicable transaction, implicit-commit, non-atomic, and crash-visible intermediate state across checksum-covered DDL, data, and authority effects is detected before later work and reaches only its reviewed forward recovery, restore, or other human-authorized outcome;
- when several histories participate in one release, their disjointness or shared coordination is proved and failure after an earlier history completes reaches only the recorded application-wide recovery;
- recovery preserves immutable committed history and allows a later explicitly authorized invocation to continue only from a completely validated state;
- every adopted authority activation or deactivation is visible and ordered, every intended runtime statement succeeds under the runtime identity before traffic, selected prohibited actions remain unavailable, and HTTP composition cannot obtain elevated credentials;
- HTTP startup and ordinary requests never create the ledger or execute migration SQL;
- every success and failure has exact exit, stream exclusivity, bytes, finite vocabulary, and complete redaction; and
- the complete project gate passes with PHT006 and maximum-level PHPStan.

The ADR 027 SQLite proof additionally holds the application-private same-host `flock`, proves immediate nonblocking contention with no database change, rolls back the failed migration and its ledger insert together, preserves earlier committed migrations, and continues after the still-unapplied condition is corrected. Those are SQLite reference requirements, not substitutes for another engine's exact coordination and partial-failure evidence.

No proof establishes production coordination duration or loss behavior, availability, free-space behavior, crash recovery, backup restore, live effective authority, release ordering, or another engine unless those properties were exercised explicitly. Rehearse them against the exact deployment before migration or authority-transition capability is used on shared data.
