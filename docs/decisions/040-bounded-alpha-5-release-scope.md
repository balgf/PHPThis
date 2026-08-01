# ADR 040: Bounded Alpha 5 release scope

Status: accepted

## Context

Alpha 4 carries Consumer Contract version 9, Strict Profile version 2, and permanent diagnostics `PHT001` through `PHT006`. Six accepted changes now exist after that boundary: a maintainer-only PHPUnit runner, Composer-first application setup clarification, and ADR 036 through ADR 039.

ADR 036 changes the installed consumer profile by advancing the Consumer Contract to version 10 and the Strict Profile to version 3 with permanent diagnostic `PHT007`. ADR 037 through ADR 039 clarify database setup scope, application-owned database authority, and recommended migration placement without adding a database runtime or abstraction. These changes need one bounded prerelease identity, an explicit Alpha 4 upgrade path, and release evidence that distinguishes checked framework behavior from application-owned policy and AI-authoring guidance.

On 2026-08-01 in Asia/Manila, the accountable human approved preparation of this bounded Alpha 5 scope and exact release identity. This approval covers the source claim and candidate preparation only. Commit, push, tags, package-host updates, dedicated-skeleton changes, GitHub releases, announcement, and other external publication operations remain separately gated by `RELEASING.md` and later explicit accountable-human authorization.

## Decision

Alpha 5 is accepted as the bounded rollup of exactly these changes after Alpha 4:

- The framework-maintainer suite uses PHPUnit 13 with independently selectable named behaviors, machine-readable CI reports, and report-only coverage. PHPUnit remains a root development dependency, requires PHP 8.4.1 or newer for framework maintenance, and is not a framework runtime dependency, Consumer Contract requirement, exported package file, or consumer test-runner choice.
- Consumer onboarding is Composer-first. A new application starts from `phpthis/skeleton`; cloning the framework repository is only for framework development or source evaluation. Tracked documentation does not claim current package availability.
- ADR 036 advances Consumer Contract version 9 to version 10 and Strict Profile version 2 to version 3 with permanent diagnostic `PHT007`. Applications keep every exact process-environment read in one application-owned PHP file, validate into process-specific final readonly values before application-controlled I/O, inject those values visibly when their consumers are adopted, and never fall back from elevated configuration to runtime configuration. The one core `#[\SensitiveParameter]` declaration reduces accidental password disclosure in stack traces; it is not encryption or complete redaction.
- ADR 037 adds a database setup scope gate for AI authoring. An ambiguous engine-selection request resolves configuration-only, existing-server connection, or project-local provisioning independently from deferred or adopted migrations before external database I/O or mutation. This is distributed guidance and isolated-consumer evidence, not a provisioning runtime, guaranteed model behavior, or duration claim.
- ADR 038 separates database and object definition source, namespace selection, namespace and object control-or-ownership, active authority, exact-engine verification, and deployment sequencing. Authority policy remains application-owned and engine-specific. The installed checker rejects only the narrow contradiction between a direct canonical `Connection::connect` call and `NOT_APPLICABLE(DATABASE)`; it does not inspect grants, activate authority, or certify runtime readiness.
- ADR 039 recommends `src/Database/Migrations/` for a newly adopting application while preserving any coherent application-owned alternative recorded in project context. It adds no path enforcement, discovery, ordering, relocation, generic database layer, or speculative multi-connection directory. A connection-owned subdivision is application-selected only when that named connection owns an independent migration history.

The exact approved identity is:

- Composer version: `0.1.0-alpha.5`
- framework tag: `v0.1.0-alpha.5`
- skeleton tag: `v0.1.0-alpha.5`

Publication state is external. This decision accepts the bounded source claim and exact identity; it does not assert or authorize either tag, either Packagist version, either GitHub prerelease, the dedicated-skeleton update, the public installation path, or an announcement. Every external operation remains subject to the ordered gates and later authorization in `RELEASING.md`.

Alpha 5 carries forward unchanged:

- the complete Alpha 4 surface and every earlier accepted ownership boundary not changed by ADR 036 through ADR 039;
- PHP 8.4.x as the framework runtime Composer range `~8.4.0`;
- zero third-party framework runtime dependencies;
- the 2,600-line core ceiling and current 2,593-line implementation, whose seven-line margin authorizes no adjacent mechanism;
- explicit manual composition, immutable HTTP values, finite route declarations, direct visible SQL through `Connection`, bound data, compile-time-constant SQL structure, query budgets and traces, and separately certified PDO transport for SQLite, MySQL 8.4, and PostgreSQL 17; and
- application ownership of product policy, configuration meaning, database dialects, namespaces, identities, authority, migrations, deployment order, operational limits, and behavior tests.

Alpha 5 does not add or permit an ORM, Active Record, lazy loading, model or repository layer, query builder, generated SQL, binding or placeholder helper, dialect abstraction, generic database layer, configuration service or bag, global configuration helper, service container, facade, automatic discovery, dotenv loader, secret-manager abstraction, provisioning API, schema builder, migration framework or DSL, permission helper, role registry, framework-owned generic or request-time privilege introspection, portable DDL, automatic deployment hook, generic middleware, native WebSocket runtime, or another hidden execution path. Application-owned deployment and test evidence may still use safe engine-supported authority inspection as required by ADR 038.

An Alpha 4 consumer upgrades in this order:

1. Review the Consumer Contract version 9 to version 10 and Strict Profile version 2 to version 3 changes.
2. Add `.ai/configuration.md`, or use the documented configuration-free state only when the application genuinely reads no process environment.
3. Inventory process-environment reads and centralize them in one application-owned PHP file as direct `\getenv('EXACT_LITERAL_KEY')` calls. Validate before application-controlled I/O into narrowly named final readonly configuration values.
4. Keep adopted runtime, worker, migration, and administrative inputs and types separate without credential fallback. Record and prove parsing, failure, redaction, rotation, and visible composition or explicit composition deferral.
5. When database access is adopted, record the exact engine and version, namespace policy, control-or-ownership model, each named operation's required and prohibited actions, applicable effective-authority sources, activation and deactivation owner and path, exact-engine positive and negative evidence, and release, abort, and recovery ordering.
6. When migrations are adopted, record the actual source path and namespace. Do not move a coherent established layout merely to match the new-application recommendation. Record connection-owned subdivisions only for independently adopted histories.
7. Keep the application's existing automated test library unless the application independently chooses another one; PHPUnit 13 is not imposed on consumers.
8. Update to the exact Alpha 5 prerelease only after reviewing these changes, then run the complete application gate on PHP 8.4.x.

Before either tag is created, one clean pushed candidate must pass every applicable local and CI gate in `RELEASING.md`, including maximum-level PHPStan, permanent profile fixtures, framework behavior tests, installed-consumer proof, exact package inventory, Git-export comparison, and SQLite/MySQL/PostgreSQL PDO transport certification. The framework distribution must be proved before the dedicated skeleton is updated and locked to the exact prerelease. Both public artifacts and one clean Packagist-preferred `composer create-project` installation must pass before either release is announced.

## Consequences

Alpha 5 gives an installed AI one checked configuration boundary and clearer database decision routing without turning configuration, provisioning, privileges, migrations, or deployment into framework runtime behavior. Consumers receive a concrete upgrade path for the only new permanent diagnostic and can distinguish a reachable database from one whose runtime authority is actually ready for a named operation.

The release remains experimental evaluation software. It makes no production-readiness, backward-compatibility, support-SLA, security-response-SLA, complete-CRUD, universal AI-compliance, secret-detection, grant-validation, cross-engine application-SQL or DDL, deployment, capacity, or exactly-once-effect claim.

## Reconsider when

A supported PHP minor, Strict Profile version, permanent diagnostic, runtime dependency, core ownership boundary, or Consumer Contract requirement changes; independent consumer evidence demonstrates that an accepted Alpha 5 boundary causes a concrete correctness or review failure; or publication evidence reveals a mismatch between approved source, package inventories, public distributions, and the installation path. Reconsider the smallest affected contract and publish a separately approved identity rather than moving either Alpha 5 tag or silently expanding this scope.
