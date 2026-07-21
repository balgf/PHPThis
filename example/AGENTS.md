# Example application instructions

This directory is the executable consumer-facing PHPThis proof. It is application code, not framework core and not a traditional manual.

Before changing it:

1. Read `../docs/consumer-contract.md` and `../docs/knowledge-map.md`.
2. Read `../VISION.md`, this file, and `.ai/README.md`.
3. Load only the `.ai/` guide routed for the task.
4. Inspect the concrete route, handler, SQL call site, projection or command, and test.

Keep manual construction, typed boundaries, exact routes, query budgets, bounded traces, and complete raw engine-specific SQL visible. For database work, call `Connection` directly from the handler or the one independently justified transaction owner. Write the complete SQL string and its explicit named parameter array together at that call site.

Keep terminal observability in `src/Observability/` application-owned and explicitly composed in `bootstrap.php`. Preserve one generated 128-bit lowercase-hex correlation ID, the single `X-Request-ID` response header, five unique finite database sources represented by `QuerySummarySource` with distinct budgets and traces, the closed redacted event, and exactly one sink invocation attempt whose failure cannot alter the response. Do not claim durable delivery or move these types into framework core.

Keep the ADR 028 Redis proof application-owned. Run document authentication, tenant resolution, and authorization before cache access; require canonical bounded versioned tenant-owned JSON; fall back once to authoritative SQLite; commit before invalidation; and expose only bounded redacted cache evidence. Keep cache at the default `127.0.0.1:6379/0` process and the schedule lease at its separate default `127.0.0.1:6380/0` `noeviction` process unless explicit test configuration replaces both. Preserve one fresh owner token, explicit `SET NX PX`, renewal and release calls, and the bounded schedule coordination output. Do not add a generic cache or Redis wrapper, transparent caching, hidden retry or renewal, authorization caching, fencing-token claim, or exactly-once claim.

Keep durable jobs in `src/Jobs/` application-owned and SQLite-specific. The Create transaction publishes one versioned welcome job through the same connection; one fresh worker invocation claims and finalizes at most one delivery through finite raw SQL, fenced leases, bounded retries, redacted dead letters, and one idempotent database effect. Do not add a generic queue, worker loop, discovery, event bus, transaction callback, or external exactly-once claim.

Keep `bin/console.php` as the sole application operational console. Its finite `jobs:run-one` and `schedule:run` commands use the argument, exit, stream, explicit-clock, one-pass, and Redis owner-token lease contracts in `.ai/cli.md`. Preserve fresh `ApplicationComposition::http()` and `ApplicationComposition::commands()` graphs over explicit immutable configuration. Do not add another entrypoint, command discovery, a service container, scheduler facade, daemon, persistent slot ledger, catch-up, or generic distributed-coordination API.

Keep `database:migrate` as the sole application migration command and final `Example\Migrations\SqliteApplicationMigrations` as its application-owned SQLite coordinator. Preserve its seven permanent ordered migration steps and unrolled private step methods, 512-entry manifest cap, `LIMIT 513` ledger bound, checksum validation before pending work, per-migration transactions, `.migration.lock` same-host nonblocking exclusion, and finite redacted failures in `.ai/migrations.md`. Migration 0007 creates `account_users` without inferring any mapping from principal-owned `account_memberships`. `tools/setup-example.php` may delegate to that exact coordinator before seeding; it must not duplicate schema SQL. Do not add framework migration types, per-migration classes, a schema builder or DSL, discovery, runtime `.sql` loading, stored executable SQL or classes, a generic database facade, transaction callback, inferred rollback, database calls in loops, HTTP-startup migration, or cross-engine DDL claims.

Do not add or use an ORM, Active Record, query builder, repository, generic paginator, SQL helper, binding helper, placeholder helper, generated or dynamic SQL, transaction callback, dialect abstraction, logging facade, middleware logger, discovery, global helper, or service container. Do not move example policy into framework `src/`.

The document-list SQL is SQLite-specific application evidence. Do not describe it as MySQL or PostgreSQL application-SQL support; the three-driver harness certifies only the framework PDO transport boundary.

Run `composer check` from the repository root. Report the exact behavior, query cost, SQLite and Redis topology scope, and proof limit; do not infer production authorization, injection safety, snapshot pagination, query-plan guarantees, sequential schedule deduplication, Redis failover safety, fencing, or exactly-once coordination from a passing example.
