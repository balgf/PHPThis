# ADR 039: Recommended database migration structure

Status: accepted

## Context

Migrations change database state, but an application-level directory such as `src/Migrations/` can make them appear to be an independent business subsystem. A predictable new-application location would help an AI find the engine-specific history, coordinator, and tests without turning directory layout into framework behavior.

PHPThis also permits an application to own its architecture. Existing consumers may already use a coherent location such as `database/migrations/`, an infrastructure namespace, or a database-specific source tree. Silently moving that source would create namespace, autoload, operational-command, review, and deployment risk without changing migration correctness.

On 2026-08-01 in Asia/Manila, the accountable human approved Issue #20 and this clarification of migration ownership and recommended application structure.

## Decision

Migrations are specialized application-owned database evolution. They remain part of the database concern while retaining the dedicated `.ai/migrations.md` policy because ordering, checksums, ledgers, authority, locking, rollout, and recovery require focused evidence.

For a new application that adopts migrations, PHPThis recommends:

```text
src/
  Database/
    Migrations/
```

The PHP namespace follows the application's own Composer root, for example `App\Database\Migrations`. The application records its actual source path and namespace in `.ai/migrations.md`. The recommendation identifies ownership and gives an AI a deterministic starting point; it does not select an engine, migration mechanism, manifest shape, command, ledger, SQL, or authority policy.

A consumer may instead record any coherent application-owned path and namespace. That recorded project context is authoritative for the application. PHPThis does not reject an alternative, enforce this directory through the checker or Strict Profile, discover or order migrations from filesystem placement, or automatically relocate an established structure. An AI may propose a relocation, but it must preserve the current structure unless an accountable human explicitly approves that architecture change and its namespace, autoload, command, test, and deployment consequences.

When multiple named database connections genuinely own independent migration histories, the application may record an explicit connection-owned subdivision for each adopted history. Each subdivision owns its source path, namespace, command, manifest, ledger, authority, and exact-engine evidence. PHPThis prescribes no subdivision spelling and does not create speculative connection directories for a single-database application or a connection without its own migration history.

The database-free skeleton does not create an empty migration directory. It records migrations as not applicable until the application adopts them; adoption creates only the selected structure.

The `Database/Migrations` recommendation does not establish a generic database layer. It does not recommend `Database/Queries`, repositories, generic persistence services, query objects, or a second SQL execution boundary. Request-time SQL remains in the handler or, when HTTP adaptation and an independently meaningful transaction are separated, in the one narrowly named concrete operation that directly owns that transaction. Migration SQL remains in the application-owned migration path under the engine-specific decision.

ADR 027's accepted SQLite coordinator, finite unrolled manifest, and non-discovery constraints remain unchanged. ADR 038's engine-neutral database-authority lifecycle remains unchanged. Consumer Contract version 10 and Strict Profile version 3 remain unchanged because this decision changes neither accepted PHP syntax nor checker behavior.

## Consequences

An AI starting a new database-backed application has one clearly named recommended place to inspect or create migration source. Reviewers can distinguish database evolution from business operations without learning a mandatory filesystem convention.

Existing consumers incur no relocation requirement. Alternative layouts remain valid when their source path, namespace, command, and ownership are explicit. PHPThis gains no migration runtime, schema builder, filesystem scanner, path diagnostic, automatic namespace mapping, or relocation tool.

The recommendation deliberately stops at migration placement. It must not become a reason to centralize runtime SQL away from operation ownership or to introduce a repository or generic query layer.

## Reconsider when

Independent consumer evidence shows that the recommendation materially impedes application organization or that applications consistently need a narrower multi-database placement convention. Reconsider documentation first. Do not add checker enforcement, discovery, relocation, or a generic persistence abstraction without a separate human-approved decision and evidence.
