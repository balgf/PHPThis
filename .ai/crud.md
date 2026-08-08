# Optional CRUD reference profile

Use this guide when adding or changing CRUD-shaped application examples, the public CRUD profile, or its application-context template. Start with `docs/crud.md`, then inspect the concrete route manifest, feature area, database path, and behavior tests. Read ADR 013 only when reviewing or changing the optional CRUD-profile decision; ordinary implementation follows this current guide and `docs/crud.md` without loading that historical record.

## Boundary of the profile

- The CRUD reference profile recommends application-owned directories, names, and operation boundaries. It is not a framework runtime feature.
- A consuming application may adopt the profile or record one coherent alternate organization in its own `.ai/architecture.md`.
- An organization override never weakens the installed consumer contract or Strict Profile. Explicit routes, typed boundaries, visible SQL, query budgets, authorization, and complete behavior evidence still apply.
- Do not add a generic CRUD controller, base repository, automatic resource routing, mass assignment, route discovery, reflection-based hydration, ORM, query builder, generic paginator, SQL/binding/placeholder helper, runtime SQL generator, arbitrary SQL string, transaction callback, dialect abstraction, query abstraction, or checker rule for directories and names.
- Keep each adopted create, get, list, update, or delete behavior as a separately named operation. Do not create an absent operation merely to complete the acronym or hide adopted operations' different input, authorization, transaction, concurrency, or lifecycle semantics behind one reusable operation.

## Recommended organization

Use the [single canonical current reference tree](../docs/crud.md#reference-placement) in `docs/crud.md`. It lists only files that exist in the checked-in Create, List, and Get reference; inspect those concrete files before relying on the tree. Update and Delete remain prose-only decisions and evidence requirements until their policies are accepted and executable files exist; do not scaffold absent operations from this guide.

Include only files the operation needs. SQL stays in a narrowly scoped handler unless an independently meaningful transaction belongs to one narrowly named concrete operation that directly owns its complete statements, as `TransactionalCreateUser` does. ADR 021's accepted Create path uses `CreateUserOperation` to separate HTTP adaptation from that transaction. Rejection evidence follows from the responsibility split; it does not authorize a generic service, repository, query object, command bus, SQL helper, or automatic handler split. The application may record another layout without adding a second way to perform the same task inside that application.

## Multiple access surfaces

- Give every surface its own named route-area list with explicit route entries. Separate its action-specific policy composition when authentication, named authorization action, tenant resolution, or policy budget or trace differs. Separate its HTTP handler and boundary types when accepted input, tenant, resource or data scope, SQL, projection or disclosure, failure behavior, HTTP cache policy, handler query budget or trace, side effects, or audit effects differ. Keep its SQL owner separate when data scope or SQL differs. Do not share an existing independently meaningful typed business or transaction operation when its typed input, data scope or SQL, transaction or concurrency policy, result contract, side effects, or audit effects differ. A route or method difference alone does not require duplicating an otherwise identical handler or typed operation.
- Treat every directory, namespace, route prefix, and route-list label as authoring organization only. It never authenticates, authorizes, selects a tenant, scopes SQL, or proves a path safe for another surface.
- Share stable domain values and genuine invariants. Narrowly typed authentication, tenant-resolution, or denial implementations may be shared when their contracts are identical, while every protected named action retains its own action-specific authorization contract. Share one independently meaningful typed business operation only when its complete responsibility remains identical and each surface reaches it only after its own applicable validation and, when protected, current authorization. Keep differing HTTP adaptation, disclosure, cache, failure behavior, query budget, and trace separate.
- Do not prescribe `Admin` or `Public` names, infer that `Public` means unauthenticated, require resource-first instead of surface-first grouping, or split genuinely identical behavior only because audience labels differ. Do not put role, audience, mode, or permission branching inside a shared handler or business operation to select SQL, behavior, side effects, or disclosure. Do not add a superset projection filtered for another surface or SQL broader than the receiving surface's recorded contract. Do not add generic services or repositories, discovery, or automatic binding. Do not add directory checker enforcement.

## Required application decisions

Before implementing resource behavior, record verified policy and its authority in `.ai/architecture.md`, `.ai/data.md`, and accepted application decisions where needed:

- identifier type, generation owner, public representation, narrowest fixed route type, matching immutable accessor, immediate concrete wrapping, and any narrower domain validation before database work;
- explicit route shapes and HTTP methods;
- pagination model, maximum page size, stable ordering, and cursor or offset semantics;
- create identity generation, duplicate/conflict behavior, and idempotency ownership;
- update choice of `PUT`, `PATCH`, or both, including omitted-versus-null semantics and concurrent-write protection;
- missing-resource behavior for each read, update, and delete operation;
- hard or soft deletion, retention, restoration, and dependent-record policy;
- authorization owner and check location for each operation, plus audit-event ownership and sensitive-field rules.

Do not infer these facts from the directory name or from another application's example. Surface missing choices for accountable human approval.

## Evidence

Test every adopted behavior rather than the spelling of directories. Cover route and method matching, boundary rejection, success and missing-resource behavior, authorization denial, create conflicts, bounded and stable pagination, concurrent updates, deletion and retention policy, and required audit effects. Database-backed behavior also needs engine-specific integration evidence, explicit query budgets, bounded traces, and constant statement counts across materially different fixture sizes.

For a resource exposed through multiple access surfaces, prove that each named route-area list selects its intended handler and its applicable policy path or recorded not-applicable policy; when protected, denial performs no protected work; and no surface executes SQL or side effects or emits fields outside its recorded operation contract and, when applicable, named authorization action and tenant or resource scope.

The current executable user reference provides partial structural, boundary, transaction, and query-cost evidence for Create and List. Account-scoped Create proves explicit authentication, tenant resolution, action authorization, exact command parsing, zero rejected-input operation calls and database work, and the visible four-statement user, account-user relation, event, and commit-visible job transaction. Authenticated principals remain distinct from users: actor access uses `account_memberships`, while created-user association uses `account_users`, and migration 0007 performs no ID-based backfill. One application-owned `Users\UserId` carries the same positive `users.id` invariant through Get and List projections and the accepted List continuation while every operation-specific projection remains separate. User List proves one application-owned keyset contract: optional canonical `after_user_id`, ascending identifiers, a fixed 50-row page, one up-to-51-row lookahead statement, and a canonical string continuation or `null`. It does not provide a generic pagination policy, and user List authorization remains unresolved. Create still lacks a named identity/conflict policy. The first user Get slice proves the typed trailing route, immediate `UserId` conversion, explicit missing response, concrete projection, and one bounded query, but not authorization or tenant scope. Update and Delete remain absent.

ADR 022 adds a distinct protected document-list proof, not a framework paginator: two finite sort choices, a versioned numeric-rank/binary-key cursor, omitted, parsed-empty-selection, and one-to-three-category behavior, explicit tenant and membership bindings, and one raw complete SQLite statement per non-empty page. Its SQL and parameter arrays stay together in `ListDocumentsHandler`; the parsed `['']` convention, produced by native PHP inputs such as `?categories[]=`, performs zero protected SQL. The example does not certify that application SQL on MySQL or PostgreSQL and does not claim snapshot traversal or universal authorization or injection safety.
