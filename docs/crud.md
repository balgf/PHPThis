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

Use application vocabulary for the feature and operation names. For the checked-in `example/src/Users` reference, this is the single canonical current tree. It lists every current Create, List, and Get source file and contains no speculative Update or Delete scaffold:

```text
src/
  Users/
    UserId.php
    UserRoutes.php
    CreateUser/
      AuthorizeCreateUser.php
      CreateUserCommand.php
      CreateUserHandler.php
      CreateUserOperation.php
      TransactionalCreateUser.php
      UnacceptableCreateUserValues.php
    GetUser/
      GetUserHandler.php
      UserDetails.php
    ListUsers/
      ListUsersHandler.php
      ListUsersPageRequest.php
      UserActivitySummary.php
      UserSummary.php
```

The feature route list explicitly constructs literal or bounded typed routes for already-constructed handlers. Under Consumer Contract version 14, carrying ADR 032 forward, each resource chooses the narrowest fixed route type: `positive-int`, lowercase canonical `uuid`, lowercase canonical `ulid`, or `token` only for a genuinely opaque bounded identifier. Routing neither normalizes nor looks up the value and never falls back between types. The feature-scoped application-owned `UserId` carries the stable positive `users.id` invariant across operations without becoming a framework identifier, binding helper, or record lookup. Each operation directory contains only the boundary values and behavior needed by that use case:

- a Create command parses and validates the complete external input before typed use-case entry;
- a Create handler owns HTTP media and parsing order, response encoding, and delegation through the concrete command;
- the example-owned typed operation seam `CreateUserOperation` separates HTTP adaptation from the independently meaningful Create transaction and accepts only the authenticated principal, resolved tenant, requested account, and final command;
- `TransactionalCreateUser` owns the visible transaction, direct `Connection` calls, write SQL, and expected database failure behavior;
- a Get handler immediately wraps its validated path parameter in a concrete application identifier, applies any narrower domain rule before database work, owns one bounded item query and explicit missing behavior, and parses a concrete projection; the current user proof specifically wraps `positive-int` in `UserId`;
- a List page request parses its exact query-parameter contract into the same semantic identifier before database work;
- a List handler owns a bounded, deterministically ordered read, continuation behavior, and response;
- each Get or List projection remains operation-specific while parsing the selected row's identifier through the shared stable `UserId` invariant;
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

## Multiple access surfaces

When the same resource is exposed through more than one access surface, keep those surfaces explicit. Give every surface its own named route-area list with explicit route entries. Separate its action-specific policy composition when authentication, named authorization action, tenant resolution, or policy budget or trace differs. Separate its HTTP handler and boundary types when accepted input, tenant, resource or data scope, SQL, projection or disclosure, failure behavior, HTTP cache policy, handler query budget or trace, side effects, or audit effects differ. Keep its SQL owner separate when data scope or SQL differs. Do not share an existing independently meaningful typed business or transaction operation, including any typed operation seam, when its typed input, data scope or SQL, transaction or concurrency policy, result contract, side effects, or audit effects differ. A route or method difference alone does not require duplicating an otherwise identical handler or operation when every explicit route and policy path remains visible.

Use contract differences, not audience labels, to decide what remains separate:

| Observed relationship | Recommended organization |
| --- | --- |
| Every distinct access surface | Give it its own named route-area list with explicit route entries. |
| A difference in authentication, named authorization action, tenant resolution, or policy budget or trace | Keep each surface's applicable action-specific policy composition separate. If the post-policy handler contract remains identical, this difference alone does not require duplicating it. |
| A difference in accepted input, projection or disclosure, failure behavior, HTTP cache policy, or handler query budget or trace | Keep its boundary types and handler separate. Do not add a SQL seam solely for surface organization. |
| A difference in tenant, resource, or data scope, or in SQL | Keep its handler and SQL owner separate. |
| A difference in typed input, transaction or concurrency policy, result contract, side effects, or audit effects | Keep its handler and any existing independently meaningful typed business or transaction operation, including any typed operation seam, separate. |
| One existing independently meaningful typed business or transaction operation has the same complete responsibility after each surface's applicable validation and, when protected, current authorization | Surface-specific paths may call that operation, including through an existing typed operation seam; do not add a seam merely to share behavior. |
| Only the route, method, or audience label differs and the complete behavior and authority contract is identical | Keep route-area lists explicit; the handler and any existing typed business or transaction operation need not be duplicated. |

The table selects no directory hierarchy. An application may record one coherent resource-first, surface-first, or capability-first organization in `.ai/architecture.md`. Use application vocabulary for each surface; a name such as `PublicApi` does not establish that its routes are unauthenticated.

A directory, namespace, route prefix, or route-list name is an authoring and review aid, never an authorization mechanism. Every protected route retains explicit composition through its named action-specific request-policy adapter, current authorization, tenant- and resource-scoped protected work, and denial evidence. For a resource exposed through multiple access surfaces, prove that each named route-area list selects its intended handler and its applicable policy path or recorded not-applicable policy; when protected, denial performs no protected work; and no surface executes SQL or side effects or emits fields outside its recorded operation contract and, when applicable, named authorization action and tenant or resource scope.

Stable resource identifiers and genuine domain invariants may remain shared. Narrowly typed authentication, tenant-resolution, or denial implementations may be shared when their contracts are identical, while every protected named action retains its own action-specific authorization contract. Share one existing independently meaningful typed business or transaction operation, including any typed operation seam, only when its complete responsibility remains identical and each surface reaches it only after its own applicable validation and, when protected, current authorization. Each surface still owns any differing input adaptation, response projection, disclosure, cache policy, failure mapping, query budget, or trace.

Do not put role, audience, mode, or permission branching inside a shared handler or business operation to select SQL, behavior, side effects, or disclosure. Do not add a superset projection filtered for another surface or SQL broader than the receiving surface's recorded contract. Do not add a generic CRUD controller, service, repository, query layer, route registry, discovery mechanism, or automatic binding to obtain reuse. Prefer small explicit repetition when sharing would obscure authority, data scope, disclosure, or effects. Conversely, do not split genuinely identical behavior merely because two routes carry different audience labels.

## Operation-specific decisions

The letters in CRUD are a classification, not permission to infer behavior. Before implementing an operation, read the application's `.ai/architecture.md`, `.ai/data.md`, security context, accepted decisions, and nearest tests. Surface any missing policy for accountable human judgment.

| Operation | Decisions and evidence required |
| --- | --- |
| Create | accepted fields, authorization, uniqueness and conflict response, transaction boundary, generated-identifier behavior, and a constant statement count at different existing-data sizes |
| List | filters, stable ordering, maximum page size, pagination contract, authorization scope, bounded result size, aggregate SQL shape, and constant query count at different fixture sizes |
| Get | typed item identity, authorization and tenant scope, not-found behavior, selected projection, and a bounded statement count |
| Update | typed item identity, replace-versus-patch semantics, allowed fields, authorization, optimistic or locking policy, not-found and conflict behavior, and transaction boundary |
| Delete | typed item identity, authorization, hard-versus-soft deletion, retention and dependent-data behavior, concurrency, external side effects, and not-found or repeated-delete behavior |

Every database operation still uses complete engine-specific visible SQL and explicit parameter arrays through direct `Connection` calls, PHT006-finite statement choices, distinct portable placeholder names for all data, an explicit `QueryBudget`, a bounded `QueryTrace`, concrete row parsing, and scale-sensitive tests. A variable identifier, ordering, operator, bounded-list cardinality, or other SQL structure is an operation-specific typed choice mapped to finite reviewed complete statements, never an ORM, repository, generic sanitizer, query builder, generic paginator, SQL/binding/placeholder helper, generated or dynamic SQL, transaction callback, or dialect abstraction. Runtime database authority and, when migrations are adopted, migration-credential separation remain application-owned obligations. No CRUD structure choice relaxes those requirements.

## Current partial executable evidence

The framework repository's runnable example currently proves these structural and query-cost properties:

- `POST /accounts/{account_id:positive-int}/users`: a concrete command after explicit account authentication, tenant resolution, and action authorization; a handler that admits only typed authority and that command to `CreateUserOperation`; explicit `TransactionalCreateUser` SQL and transaction ownership; generic safe failures; named SQL parameters; zero rejected-input operation calls; and a four-statement count that remains constant as pre-existing data grows;
- `GET /users`: a bounded List handler with a concrete page request and projections. Its example-owned contract accepts only optional canonical `after_user_id`, carries accepted and projected identities as `Users\UserId`, orders by ascending user ID, returns at most 50 users, probes one extra row, emits the last returned ID as the next canonical string or `null`, and keeps every page to one aggregate statement;
- `GET /users/{user_id:positive-int}`: the declared trailing positive-integer route, immediate `UserId` conversion, a concrete `UserDetails` projection, explicit missing response, and one bounded database statement.
- `GET /accounts/{account_id:positive-int}/documents`: a protected SQLite-only List handler with `order=rank_asc|rank_desc`, an exact versioned rank/key cursor, omitted, parsed `['']` empty-selection (produced by native PHP inputs such as `?categories[]=`), and one-to-three-category behavior, eight complete raw statements, explicit account/tenant/membership and page bindings, at most 50 returned rows from a 51-row lookahead, and one statement per non-empty page.

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
