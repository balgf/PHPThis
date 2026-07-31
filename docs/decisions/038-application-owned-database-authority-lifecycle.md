# ADR 038: Application-owned database authority lifecycle

Status: accepted

## Context

A database connection can authenticate successfully while its identity cannot use an application schema or table. Migration success likewise proves only that the migration path completed; it does not prove that a separately configured runtime identity can execute the statements required by a newly deployed application path.

PHPThis already separates runtime configuration from migration or administrative configuration and requires least-privileged engine-specific evidence. The application context did not yet separate database and object definition source, namespace selection, namespace and object control-or-ownership, effective-authority resolution, authority-transition ownership, and release ordering clearly enough. That omission can leave a secure-looking deployment unusable, or encourage broad authority added only to make the first failed request pass.

On 2026-08-01 in Asia/Manila, the accountable human approved Issue #19 and this application-owned database authority lifecycle clarification.

## Decision

Configuration and source presence do not activate database authority. Database and object definition source; database/catalog/schema/attachment namespace selection and qualification as supported; namespace and object control-or-ownership; and active authority are separate application facts.

For each adopted database identity, the application records:

- the exact engine and supported version;
- the database/catalog/schema/attachment namespace selection and qualification policy supported by that engine;
- the namespace and object control-or-ownership model, explicitly not applicable when the engine has no ownership concept;
- each named operation's required objects and actions;
- explicitly prohibited actions;
- how effective authority resolves under the selected engine, recording only applicable sources such as direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process authority;
- the identity or deployment owner allowed to activate and deactivate authority; and
- the non-secret verification source and date.

PHPThis neither requires nor discourages an application-specific database, catalog, schema, attachment namespace, or equivalent. An engine-default namespace and a separately named application namespace are both valid when the application records the engine-specific choice, control-or-ownership model or explicit non-applicability, qualification behavior, and evidence. PHPThis selects no namespace, identity topology, default authority, or permission set.

Withholding all runtime object access is valid before a named application operation exists. Once an operation is introduced, its exact runtime statements must be authorized and verified before dependent code receives traffic. Connectivity, a trivial query, migration completion, or configuration separation alone is not runtime-readiness evidence.

Each adopted authority activation or deactivation has one explicit application-owned path. It may be:

- visible engine-specific `GRANT` or `REVOKE` SQL, when supported and selected, included in a migration and covered by that migration's checksum;
- a separately authorized application console or deployment operation; or
- a named external provisioning source with its own review and evidence.

PHPThis chooses none of these paths. The application records the selected owner, inputs, output and redaction behavior, failure handling, future-object or default-authority policy where applicable, and the exact-engine verification that follows it. No authority activation or deactivation, identity management, namespace management, migration, or administrative operation runs from HTTP request handling.

The application records one release sequence that relates provisioning, migration, authority activation, verification, application rollout, and traffic enablement. PHPThis does not prescribe migration-first, code-first, rolling, or maintenance-window deployment. The recorded sequence must state old-code and new-code compatibility, abort gates, and whether recovery uses binary rollback, forward correction, an explicitly authorized restore, or another reviewed action. A failed migration, authority transition, or exact-engine verification stops the next dependent stage. Dependent code is removed or drained before its required authority is deactivated or its namespace is removed.

Authority evidence runs against the exact engine and version. Positive evidence executes the intended application statements. Negative evidence safely verifies selected prohibited namespace, DDL, identity or role, authority-management, administrative, and unrelated-object actions. Safe engine-supported authority inspection may replace a destructive negative probe on shared or production data. The evidence also proves that HTTP composition cannot obtain elevated configuration. SQLite records applicable file ownership, permissions, attachment boundaries, and process isolation rather than inventing roles or grants.

The installed application checker adds one deliberately narrow context-consistency check: a direct canonical `PHPThis\Database\Connection::connect` call cannot coexist with `.ai/data.md` declaring `NOT_APPLICABLE(DATABASE)`. It emits no `PHT` diagnostic and does not connect to a database, inspect SQL, validate identities or grants, or establish readiness. Configuration-only scope may continue to record connection composition as deferred without introducing a connection call.

No framework runtime type or dependency is added. Identity topology, namespaces, control or ownership, authority activation and deactivation, migrations, deployment ordering, and verification remain application-owned and engine-specific. This policy applies to PDO-backed engines an application explicitly adopts and verifies; it does not expand PHPThis's certified transport set or include non-PDO databases.

## Consequences

An AI can distinguish a reachable database from a usable least-privileged runtime and can identify the exact stage that activates each operation. Reviewers can see whether new objects will remain inaccessible, receive excessive default access, or be exposed before verification.

Applications do more explicit operational work. That cost is intentional: PHPThis will not hide database authority behind an ORM, schema builder, migration framework, permission or grant helper, role registry, service container, automatic discovery, portable DDL abstraction, runtime privilege introspection, or automatic deployment hook.

Existing applications with database access update their project context and release evidence when they adopt this clarification. The consumer contract and Strict Profile versions remain unchanged because no PHP language shape or `PHT` rule changes.

## Reconsider when

Repeated independent consumer evidence shows that the context fields or one-way consistency check produce material false positives, fail to expose real authority-ordering defects, or cannot represent an engine's privilege model. Reconsider the documentation and evidence vocabulary before adding a framework mechanism.
