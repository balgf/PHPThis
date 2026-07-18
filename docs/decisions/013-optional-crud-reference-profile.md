# ADR 013: Optional CRUD reference profile

Status: accepted

## Context

Create, read, update, and delete work is a recurring shape in database-backed PHP applications. Giving that work a predictable source layout reduces the number of plausible placements an AI must infer, but treating CRUD as one generic operation erases important differences between its use cases. Create, list, item read, update, and delete can have different input types, authorization rules, query shapes, transaction boundaries, concurrency behavior, conflicts, and response semantics.

PHPThis currently has executable evidence for two collection operations: a transactional Create handler and a bounded List handler whose query count is tested at materially different fixture sizes. The literal-path router does not yet provide typed item routes. The framework also cannot choose an application's pagination contract, update concurrency policy, deletion semantics, authorization rules, or conflict behavior.

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
    ListUsers/
      ListUsersHandler.php
      UserActivitySummary.php
```

Feature and operation names use the application's domain vocabulary. Route lists remain explicit. A handler keeps the complete request path visible and may move SQL only into a narrowly named query object when that responsibility no longer remains clear in the handler. Commands and projections stay specific to one boundary instead of becoming generic records, models, or collections.

An application may use this reference placement or record one coherent alternate placement and naming rule in its `.ai/architecture.md`. That selection guides authoring within the application; it does not add a second framework runtime API. An alternate structure may strengthen the installed consumer contract but cannot weaken its typing, explicit routing, visible SQL, bounded database work, analysis, or verification requirements.

PHPThis does not add a CRUD base handler, generic repository, resource registration API, automatic routes, mass assignment, SQL generation, runtime discovery, filesystem enforcement, or code-generation requirement. The framework dispatches the explicitly constructed objects supplied by the application regardless of their directories.

Create and List have partial executable evidence for structure, boundary parsing, transaction shape, and query cost; they do not yet have complete authorization, identity/conflict, or continuation policy. Get, Update, and Delete have no executable reference and do not become supported merely because their names appear in the CRUD vocabulary. The reference profile will add item-operation examples only after typed item routes exist and the example application records accountable decisions for pagination, concurrency, deletion, authorization, and conflict behavior.

## Consequences

An AI receives one compact default for compartmentalizing common database work, while application owners retain control over source organization. A project-specific alternative becomes explicit and auditable instead of being inferred file by file.

CRUD operations may contain some deliberate repetition because their behavior and safety decisions remain visible. The profile cannot be used as evidence that full CRUD, generic persistence, or an application-specific policy exists. Runtime cost and routing behavior are unchanged because PHPThis does not inspect the source layout.

## Reconsider when

Evidence from consuming applications shows that the feature-first placement increases task context or creates repeated ambiguity, typed item routes expose a necessary structural change, or several real operations prove a smaller explicit abstraction without hiding SQL, authorization, transactions, query cost, or product policy. Do not reconsider only to reduce file count or imitate another framework's resource API.
