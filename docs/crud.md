# Optional CRUD reference profile

This is compact authoring authority for common database-backed operations, not a generic CRUD engine or a claim that PHPThis implements complete CRUD.

The CRUD reference profile is optional application structure. The PHPThis consumer contract and Strict Profile remain mandatory.

## Authority boundary

| Layer | Requirement |
| --- | --- |
| PHPThis consumer contract and Strict Profile | Mandatory for every PHPThis application; application instructions cannot weaken them. |
| Feature-first CRUD reference profile | Recommended placement and naming for applications that choose the PHPThis default. |
| Application-owned alternative | One coherent placement and naming rule recorded in `.ai/architecture.md`; it replaces only the optional structure. |

PHPThis never discovers or validates a feature from its directory name. Routes, handlers, dependencies, SQL, commands, projections, budgets, traces, and tests remain explicit ordinary PHP. Do not add a base CRUD handler, ORM, generic repository, query builder, generic paginator, SQL/binding/placeholder helper, automatic resource routes, mass assignment, generated or dynamic SQL, transaction callback, dialect abstraction, or filesystem enforcement.

## Reference placement

Use application vocabulary for the feature and operation names. For the `Users` feature whose current proof covers Create, List, and a first Get slice:

```text
src/
  Users/
    UserRoutes.php
    CreateUser/
      CreateUserCommand.php
      CreateUserHandler.php
      CreateUserOperation.php
      TransactionalCreateUser.php
    GetUser/
      GetUserHandler.php
      UserDetails.php
      UserId.php
    ListUsers/
      ListUsersHandler.php
      ListUsersPageRequest.php
      UserActivitySummary.php
```

The feature route list explicitly constructs literal or bounded typed routes for already-constructed handlers. Under Consumer Contract version 9, carrying ADR 032 forward, each resource chooses the narrowest fixed route type: `positive-int`, lowercase canonical `uuid`, lowercase canonical `ulid`, or `token` only for a genuinely opaque bounded identifier. Routing neither normalizes nor looks up the value and never falls back between types. Each operation directory contains only the boundary values and behavior needed by that use case:

- a Create command parses and validates the complete external input before typed use-case entry;
- a Create handler owns HTTP media and parsing order, response encoding, and delegation through the concrete command;
- the example-owned `CreateUserOperation` interface separates HTTP adaptation from the independently meaningful Create transaction and accepts only the authenticated principal, resolved tenant, requested account, and final command;
- `TransactionalCreateUser` owns the visible transaction, direct `Connection` calls, write SQL, and expected database failure behavior;
- a Get handler immediately wraps its validated path parameter in a route-specific application identifier, applies any narrower domain rule before database work, owns one bounded item query and explicit missing behavior, and parses a concrete projection; the current user proof specifically wraps `positive-int` in `UserId`;
- a List page request parses its exact query-parameter contract before database work;
- a List handler owns a bounded, deterministically ordered read, continuation behavior, and response;
- a List projection parses each selected row into a concrete final readonly value;
- SQL stays in its handler unless an independently meaningful transaction needs a separate concrete operation. `TransactionalCreateUser` directly owns the complete Create transaction SQL because that transaction is separate from HTTP adaptation; the resulting rejection proof does not authorize a generic service, repository, query object, or helper layer.

Do not create a generic feature record shared across write input, selected rows, and responses. Those boundaries change for different reasons and require their own concrete types.

The protected document proof uses the same optional feature-first shape without creating a shared paginator or query layer:

```text
src/
  Accounts/
    AccountId.php
    AuthenticatedPrincipal.php
    ResolvedTenant.php
    AuthenticateAccountRequest.php
    ResolveAccountTenant.php
  Documents/
    DocumentRoutes.php
    DocumentKey.php
    GetDocument/
      GetDocumentHandler.php
      AuthorizeGetDocument.php
      DocumentDetails.php
      RetrieveAuthorizedDocument.php
      SelectAuthorizedDocument.php
    ListDocuments/
      ListDocumentsHandler.php
      ListDocumentsPageRequest.php
      AuthorizeListDocuments.php
      DocumentSummary.php
```

Shared account boundary values carry only stable application meaning. Create, document Get, and document List retain action-specific authorization and data behavior. The List handler itself owns its eight complete raw SQLite statements and explicit parameter arrays; no repository, query object, generic paginator, or binding helper sits below it.

## Operation-specific decisions

The letters in CRUD are a classification, not permission to infer behavior. Before implementing an operation, read the application's `.ai/architecture.md`, `.ai/data.md`, security context, accepted decisions, and nearest tests. Surface any missing policy for accountable human judgment.

| Operation | Decisions and evidence required |
| --- | --- |
| Create | accepted fields, authorization, uniqueness and conflict response, transaction boundary, generated-identifier behavior, and a constant statement count at different existing-data sizes |
| List | filters, stable ordering, maximum page size, pagination contract, authorization scope, bounded result size, aggregate SQL shape, and constant query count at different fixture sizes |
| Get | typed item identity, authorization and tenant scope, not-found behavior, selected projection, and a bounded statement count |
| Update | typed item identity, replace-versus-patch semantics, allowed fields, authorization, optimistic or locking policy, not-found and conflict behavior, and transaction boundary |
| Delete | typed item identity, authorization, hard-versus-soft deletion, retention and dependent-data behavior, concurrency, external side effects, and not-found or repeated-delete behavior |

Every database operation still uses complete engine-specific visible SQL and explicit parameter arrays through direct `Connection` calls, PHT006-finite statement choices, distinct portable placeholder names for all data, an explicit `QueryBudget`, a bounded `QueryTrace`, concrete row parsing, and scale-sensitive tests. A variable identifier, ordering, operator, bounded-list cardinality, or other SQL structure is an operation-specific typed choice mapped to finite reviewed complete statements, never an ORM, repository, generic sanitizer, query builder, generic paginator, SQL/binding/placeholder helper, generated or dynamic SQL, transaction callback, or dialect abstraction. Runtime database authority and migration-credential separation remain application-owned obligations. No CRUD structure choice relaxes those requirements.

## Current partial executable evidence

The framework repository's runnable example currently proves these structural and query-cost properties:

- `POST /accounts/{account_id:positive-int}/users`: a concrete command after explicit account authentication, tenant resolution, and action authorization; a handler that admits only typed authority and that command to `CreateUserOperation`; explicit `TransactionalCreateUser` SQL and transaction ownership; generic safe failures; named SQL parameters; zero rejected-input operation calls; and a four-statement count that remains constant as pre-existing data grows;
- `GET /users`: a bounded List handler with a concrete page request and projections. Its example-owned contract accepts only optional canonical `after_user_id`, orders by ascending user ID, returns at most 50 users, probes one extra row, emits the last returned ID as the next canonical string or `null`, and keeps every page to one aggregate statement;
- `GET /users/{user_id}`: the declared trailing positive-integer route, immediate `UserId` conversion, a concrete `UserDetails` projection, explicit missing response, and one bounded database statement.
- `GET /accounts/{account_id}/documents`: a protected SQLite-only List handler with `order=rank_asc|rank_desc`, an exact versioned rank/key cursor, omitted, parsed `['']` empty-selection (produced by native PHP inputs such as `?categories[]=`), and one-to-three-category behavior, eight complete raw statements, explicit account/tenant/membership and page bindings, at most 50 returned rows from a 51-row lookahead, and one statement per non-empty page.

This is not complete Create, List, or Get policy evidence. Account-scoped Create now proves visible policy order and tenant-bound mutation but still lacks a named identity/conflict contract, and user Get does not establish authorization or tenant scope. Each List proves only its specific continuation contract; neither becomes a framework default or provides snapshot consistency during concurrent writes. The tenant predicates and adversarial binding probes are not universal authorization or injection proof, and the application SQL is only SQLite-specific evidence under the current unpinned PDO SQLite runtime. Update and Delete have no executable reference. Every operation still requires the relevant application-owned decisions for pagination, concurrency, deletion, authorization, tenant scope, and conflict behavior; PHPThis does not invent those policies.

## Selecting an alternate structure

An application that does not adopt the reference placement records its one canonical alternative in `.ai/architecture.md`, including:

- feature or area grouping rule;
- route-list placement;
- handler placement and naming;
- operation-request, command, and projection placement;
- dependency direction; and
- the source and test paths an AI must inspect for each operation.

Use that selected structure consistently. A project-owned authoring rule may replace the optional directory profile, but it does not override the installed consumer contract, Strict Profile, runtime API, or complete project check.
