# Optional CRUD reference profile

Use this guide when adding or changing CRUD-shaped application examples, the public CRUD profile, or its application-context template. Read `docs/crud.md` and ADR 013 first, then inspect the concrete route manifest, feature area, database path, and behavior tests.

## Boundary of the profile

- The CRUD reference profile recommends application-owned directories, names, and operation boundaries. It is not a framework runtime feature.
- A consuming application may adopt the profile or record one coherent alternate organization in its own `.ai/architecture.md`.
- An organization override never weakens the installed consumer contract or Strict Profile. Explicit routes, typed boundaries, visible SQL, query budgets, authorization, and complete behavior evidence still apply.
- Do not add a generic CRUD controller, base repository, automatic resource routing, mass assignment, route discovery, reflection-based hydration, query abstraction, or checker rule for directories and names.
- Keep create, get, list, update, and delete as separately named operations. Do not hide their different input, authorization, transaction, concurrency, or lifecycle semantics behind one reusable operation.

## Recommended organization

Prefer a resource area containing its explicit route list and one directory per operation:

```text
src/
  Users/
    UserRoutes.php
    CreateUser/
      CreateUserCommand.php
      CreateUserHandler.php
    GetUser/
      GetUserHandler.php
      UserDetails.php
    ListUsers/
      ListUsersHandler.php
      UserActivitySummary.php
    UpdateUser/
      UpdateUserCommand.php
      UpdateUserHandler.php
    DeleteUser/
      DeleteUserHandler.php
```

Include only files the operation needs. SQL may stay in a narrowly scoped handler or move to a narrowly named query object when that makes the operation clearer. The application may record another layout without adding a second way to perform the same task inside that application.

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

The current executable reference provides partial structural, boundary, transaction, and query-cost evidence for Create and List; it does not prove their authorization, identity/conflict, or continuation policies. Do not present Get, Update, or Delete as supported examples until their typed item-route design and application-owned semantics have executable evidence.
