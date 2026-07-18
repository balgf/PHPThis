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

Use application vocabulary for the feature and operation names. For a `Users` feature whose proven operations are Create and List:

```text
src/
  Users/
    UserRoutes.php
    CreateUser/
      CreateUserCommand.php
      CreateUserHandler.php
    ListUsers/
      ListUsersHandler.php
      UserActivitySummary.php
```

The feature route list explicitly constructs literal routes for already-constructed handlers. Each operation directory contains only the boundary values and behavior needed by that use case:

- a Create command parses and validates external input before database work;
- a Create handler owns the visible transaction, write SQL, expected failure behavior, and response;
- a List handler owns a bounded, deterministically ordered read and its response;
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

Every database operation still uses engine-specific visible SQL through `Connection`, distinct portable placeholder names, an explicit `QueryBudget`, a bounded `QueryTrace`, concrete row parsing, and scale-sensitive tests. No structure choice relaxes those requirements.

## Current partial executable evidence

The framework repository's runnable example currently proves these structural and query-cost properties:

- `POST /users`: a concrete command, explicit transactional Create handler, named SQL parameters, and a statement count that remains constant as pre-existing data grows;
- `GET /users`: a bounded List handler with concrete projections and aggregate SQL whose statement count remains constant as the fixture grows.

This is not complete Create or List policy evidence. The example has no authorization behavior, Create still lacks a named identity/conflict contract, and List returns one fixed first page without continuation. Get, Update, and Delete have no executable reference. Item operations wait for the typed item-route decision and implementation, and every operation still requires application-owned decisions for pagination, concurrency, deletion, authorization, and conflict behavior as applicable; PHPThis does not invent those policies.

## Selecting an alternate structure

An application that does not adopt the reference placement records its one canonical alternative in `.ai/architecture.md`, including:

- feature or area grouping rule;
- route-list placement;
- handler placement and naming;
- command and projection placement;
- dependency direction; and
- the source and test paths an AI must inspect for each operation.

Use that selected structure consistently. A project-owned authoring rule may replace the optional directory profile, but it does not override the installed consumer contract, Strict Profile, runtime API, or complete project check.
