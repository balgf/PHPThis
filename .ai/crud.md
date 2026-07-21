# Optional CRUD reference profile

Use this guide when adding or changing CRUD-shaped application examples, the public CRUD profile, or its application-context template. Read `docs/crud.md` and ADR 013 first, then inspect the concrete route manifest, feature area, database path, and behavior tests.

## Boundary of the profile

- The CRUD reference profile recommends application-owned directories, names, and operation boundaries. It is not a framework runtime feature.
- A consuming application may adopt the profile or record one coherent alternate organization in its own `.ai/architecture.md`.
- An organization override never weakens the installed consumer contract or Strict Profile. Explicit routes, typed boundaries, visible SQL, query budgets, authorization, and complete behavior evidence still apply.
- Do not add a generic CRUD controller, base repository, automatic resource routing, mass assignment, route discovery, reflection-based hydration, ORM, query builder, generic paginator, SQL/binding/placeholder helper, runtime SQL generator, arbitrary SQL string, transaction callback, dialect abstraction, query abstraction, or checker rule for directories and names.
- Keep create, get, list, update, and delete as separately named operations. Do not hide their different input, authorization, transaction, concurrency, or lifecycle semantics behind one reusable operation.

## Recommended organization

Prefer a resource area containing its explicit route list and one directory per operation:

```text
src/
  Accounts/
    AccountId.php
    AuthenticatedPrincipal.php
    ResolvedTenant.php
    AuthenticateAccountRequest.php
    ResolveAccountTenant.php
  Users/
    UserRoutes.php
    CreateUser/
      CreateUserCommand.php
      CreateUserHandler.php
      CreateUserOperation.php
      AuthorizeCreateUser.php
      TransactionalCreateUser.php
    GetUser/
      GetUserHandler.php
      UserDetails.php
      UserId.php
    ListUsers/
      ListUsersHandler.php
      ListUsersPageRequest.php
      UserActivitySummary.php
    UpdateUser/
      UpdateUserCommand.php
      UpdateUserHandler.php
    DeleteUser/
      DeleteUserHandler.php
```

Include only files the operation needs. SQL stays in a narrowly scoped handler unless an independently meaningful transaction belongs to one narrowly named concrete operation that directly owns its complete statements, as `TransactionalCreateUser` does. ADR 021's accepted Create path uses `CreateUserOperation` to separate HTTP adaptation from that transaction. Rejection evidence follows from the responsibility split; it does not authorize a generic service, repository, query object, command bus, SQL helper, or automatic handler split. The application may record another layout without adding a second way to perform the same task inside that application.

## Required application decisions

Before implementing resource behavior, record verified policy and its authority in `.ai/architecture.md`, `.ai/data.md`, and accepted application decisions where needed:

- identifier type, generation owner, public representation, and route binding;
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

The current executable user reference provides partial structural, boundary, transaction, and query-cost evidence for Create and List. Account-scoped Create proves explicit authentication, tenant resolution, action authorization, exact command parsing, zero rejected-input operation calls and database work, and the visible four-statement user, account-user relation, event, and commit-visible job transaction. Authenticated principals remain distinct from users: actor access uses `account_memberships`, while created-user association uses `account_users`, and migration 0007 performs no ID-based backfill. User List proves one application-owned keyset contract: optional canonical `after_user_id`, ascending identifiers, a fixed 50-row page, one up-to-51-row lookahead statement, and a canonical string continuation or `null`. It does not provide a generic pagination policy, and user List authorization remains unresolved. Create still lacks a named identity/conflict policy. The first user Get slice proves the typed trailing route, immediate `UserId` conversion, explicit missing response, concrete projection, and one bounded query, but not authorization or tenant scope. Update and Delete remain absent.

ADR 022 adds a distinct protected document-list proof, not a framework paginator: two finite sort choices, a versioned numeric-rank/binary-key cursor, omitted, parsed-empty-selection, and one-to-three-category behavior, explicit tenant and membership bindings, and one raw complete SQLite statement per non-empty page. Its SQL and parameter arrays stay together in `ListDocumentsHandler`; the parsed `['']` convention, produced by native PHP inputs such as `?categories[]=`, performs zero protected SQL. The example does not certify that application SQL on MySQL or PostgreSQL and does not claim snapshot traversal or universal authorization or injection safety.
