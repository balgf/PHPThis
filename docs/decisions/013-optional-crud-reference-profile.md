# ADR 013: Optional CRUD reference profile

Status: accepted

## Context

Create, read, update, and delete work is a recurring shape in database-backed PHP applications. Giving that work a predictable source layout reduces the number of plausible placements an AI must infer, but treating CRUD as one generic operation erases important differences between its use cases. Create, list, item read, update, and delete can have different input types, authorization rules, query shapes, transaction boundaries, concurrency behavior, conflicts, and response semantics.

PHPThis has executable evidence for two collection operations: a transactional Create path and a bounded List handler whose query count is tested at materially different fixture sizes. The example List now records and proves its own keyset contract: optional canonical `after_user_id`, ascending identifiers, fixed 50-row pages, one-row lookahead, a canonical string continuation or `null`, and one statement per accepted page. ADR 017 adds the single bounded trailing positive-integer route shape and a first item Get proof with immediate concrete-identifier conversion, explicit missing behavior, and one query. These examples do not choose another application's pagination contract, tenant scope, update concurrency policy, deletion semantics, authorization rules, or conflict behavior.

Consumers need a clear PHPThis-shaped default without making their directories part of framework runtime behavior or preventing a project from selecting a better structure for its domain.

## Decision

PHPThis publishes an optional, feature-first CRUD reference profile. The CRUD reference profile is optional application structure. The PHPThis consumer contract and Strict Profile remain mandatory.

The reference placement groups each use case under the feature it changes:

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

Feature and operation names use the application's domain vocabulary. Route lists remain explicit. A handler keeps the complete request path and SQL visible. When HTTP adaptation and an independently meaningful business transaction need separate ownership, one narrowly typed operation interface and one concrete implementation may directly own that transaction's complete SQL without adding a service, repository, query object, or helper layer. Operation requests, commands, and projections stay specific to one boundary instead of becoming generic records, models, or collections.

ADR 021 supersedes this record only where the earlier Create tree showed `CreateUserCommand` and `CreateUserHandler` alone and where its transaction was described as handler-owned. The current path adds `CreateUserOperation` and `TransactionalCreateUser` because HTTP adaptation and the independently meaningful transaction have distinct responsibilities; ADR 024 later adds the third commit-visible job statement. Rejected-input exclusion is evidence for that boundary, not its sole reason to exist. List remains handler-local after parsing its concrete `ListUsersPageRequest`; no automatic handler split is authorized.

ADR 029 later makes the example Create path account-scoped and adds the fourth `account_users` relation write. That newer application evidence does not change this decision's optional placement rule or authorize a shared identity or persistence layer.

An application may use this reference placement or record one coherent alternate placement and naming rule in its `.ai/architecture.md`. That selection guides authoring within the application; it does not add a second framework runtime API. An alternate structure may strengthen the installed consumer contract but cannot weaken its typing, explicit routing, visible SQL, bounded database work, analysis, or verification requirements.

PHPThis does not add a CRUD base handler, generic repository, resource registration API, automatic routes, mass assignment, SQL generation, runtime discovery, filesystem enforcement, or code-generation requirement. The framework dispatches the explicitly constructed objects supplied by the application regardless of their directories.

Create and List have partial executable evidence for structure, boundary parsing, transaction shape, and query cost. List additionally has bounded executable evidence for the narrow example-owned continuation contract above, but not authorization or snapshot consistency during concurrent writes. Create still lacks complete authorization and identity/conflict policy. The first Get slice proves only the bounded typed route, immediate `UserId` conversion, explicit missing response, concrete projection, and one-query cost; authorization and tenant policy remain application-owned and unresolved. Update and Delete have no executable reference and do not become supported merely because their names appear in the CRUD vocabulary. They require accountable application decisions and executable evidence for concurrency, deletion, authorization, conflicts, and related behavior.

## Consequences

An AI receives one compact default for compartmentalizing common database work, while application owners retain control over source organization. A project-specific alternative becomes explicit and auditable instead of being inferred file by file.

CRUD operations may contain some deliberate repetition because their behavior and safety decisions remain visible. The profile cannot be used as evidence that full CRUD, generic persistence, or an application-specific policy exists. Runtime cost and routing behavior are unchanged because PHPThis does not inspect the source layout.

## Reconsider when

Evidence from consuming applications shows that the feature-first placement increases task context or creates repeated ambiguity, typed item routes expose a necessary structural change, or several real operations prove a smaller explicit abstraction without hiding SQL, authorization, transactions, query cost, or product policy. Do not reconsider only to reduce file count or imitate another framework's resource API.
