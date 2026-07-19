# Optional CRUD reference profile

This is compact authoring authority for common database-backed operations, not a generic CRUD engine or a claim that PHPThis implements complete CRUD.

The CRUD reference profile is optional application structure. The PHPThis consumer contract and Strict Profile remain mandatory.

## Authority boundary

| Layer | Requirement |
| --- | --- |
| PHPThis consumer contract and Strict Profile | Mandatory for every PHPThis application; application instructions cannot weaken them. |
| Feature-first CRUD reference profile | Recommended placement and naming for applications that choose the PHPThis default. |
| Application-owned alternative | One coherent placement and naming rule recorded in `.ai/architecture.md`; it replaces only the optional structure. |

PHPThis never discovers or validates a feature from its directory name. Routes, handlers, dependencies, SQL, commands, projections, budgets, traces, and tests remain explicit ordinary PHP. Do not add a base CRUD handler, generic repository, automatic resource routes, mass assignment, generated SQL, or filesystem enforcement.

## Reference placement

Use application vocabulary for the feature and operation names. For the `Users` feature whose current proof covers Create, List, and a first Get slice:

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
      UserId.php
    ListUsers/
      ListUsersHandler.php
      ListUsersPageRequest.php
      UserActivitySummary.php
```

The feature route list explicitly constructs literal or bounded typed routes for already-constructed handlers. Each operation directory contains only the boundary values and behavior needed by that use case:

- a Create command parses and validates external input before database work;
- a Create handler owns the visible transaction, write SQL, expected failure behavior, and response;
- a Get handler immediately wraps its validated positive-integer path parameter in `UserId`, owns one bounded item query and explicit missing behavior, and parses a concrete `UserDetails` projection;
- a List page request parses its exact query-parameter contract before database work;
- a List handler owns a bounded, deterministically ordered read, continuation behavior, and response;
- a List projection parses each selected row into a concrete final readonly value;
- a narrowly named query object is introduced only when visible SQL no longer remains clear in its handler.

Do not create a generic feature record shared across write input, selected rows, and responses. Those boundaries change for different reasons and require their own concrete types.

## Operation-specific decisions

The letters in CRUD are a classification, not permission to infer behavior. Before implementing an operation, read the application's `.ai/architecture.md`, `.ai/data.md`, security context, accepted decisions, and nearest tests. Surface any missing policy for accountable human judgment.

| Operation | Decisions and evidence required |
| --- | --- |
| Create | accepted fields, authorization, uniqueness and conflict response, transaction boundary, generated-identifier behavior, and a constant statement count at different existing-data sizes |
| List | filters, stable ordering, maximum page size, pagination contract, authorization scope, bounded result size, aggregate SQL shape, and constant query count at different fixture sizes |
| Get | typed item identity, authorization and tenant scope, not-found behavior, selected projection, and a bounded statement count |
| Update | typed item identity, replace-versus-patch semantics, allowed fields, authorization, optimistic or locking policy, not-found and conflict behavior, and transaction boundary |
| Delete | typed item identity, authorization, hard-versus-soft deletion, retention and dependent-data behavior, concurrency, external side effects, and not-found or repeated-delete behavior |

Every database operation still uses engine-specific visible SQL through direct `Connection` calls, PHT006-finite statement choices, distinct portable placeholder names for all data, an explicit `QueryBudget`, a bounded `QueryTrace`, concrete row parsing, and scale-sensitive tests. A variable identifier, ordering, operator, or other SQL structure is an operation-specific typed choice mapped to finite reviewed statements, never a generic sanitizer or query builder. Runtime database authority and migration-credential separation remain application-owned obligations. No CRUD structure choice relaxes those requirements.

## Current partial executable evidence

The framework repository's runnable example currently proves these structural and query-cost properties:

- `POST /users`: a concrete command, explicit transactional Create handler, named SQL parameters, and a statement count that remains constant as pre-existing data grows;
- `GET /users`: a bounded List handler with a concrete page request and projections. Its example-owned contract accepts only optional canonical `after_user_id`, orders by ascending user ID, returns at most 50 users, probes one extra row, emits the last returned ID as the next canonical string or `null`, and keeps every page to one aggregate statement;
- `GET /users/{user_id}`: the declared trailing positive-integer route, immediate `UserId` conversion, a concrete `UserDetails` projection, explicit missing response, and one bounded database statement.

This is not complete Create, List, or Get policy evidence. The example has no authorization behavior, Create still lacks a named identity/conflict contract, and Get does not establish authorization or tenant scope. List proves only the specific continuation contract above; it does not make that policy a framework default or provide snapshot consistency during concurrent writes. Update and Delete have no executable reference. Every operation still requires the relevant application-owned decisions for pagination, concurrency, deletion, authorization, tenant scope, and conflict behavior; PHPThis does not invent those policies.

## Selecting an alternate structure

An application that does not adopt the reference placement records its one canonical alternative in `.ai/architecture.md`, including:

- feature or area grouping rule;
- route-list placement;
- handler placement and naming;
- operation-request, command, and projection placement;
- dependency direction; and
- the source and test paths an AI must inspect for each operation.

Use that selected structure consistently. A project-owned authoring rule may replace the optional directory profile, but it does not override the installed consumer contract, Strict Profile, runtime API, or complete project check.
