# ADR 022: Application-owned finite data paths

Status: accepted

## Context

PHPThis already keeps PDO execution behind `Connection`, limits statements with `QueryBudget`, records bounded redacted evidence with `QueryTrace`, exposes explicit transaction methods, and requires PHT006-finite SQL at every direct database call. At this decision's acceptance, the existing user examples proved one fixed-order collection, a two-statement transaction and rollback, and an isolated N+1 negative control; ADR 024 later adds a third commit-visible job statement to that transaction. They did not yet prove a protected tenant-owned collection with more than one ordering, a composite cursor, and a bounded list filter.

ADR 029 later adds explicit account policy and a fourth `account_users` relation statement to the Create transaction. The two- and three-statement descriptions above remain chronological context for this decision rather than current Create guidance.

That proof must not become an ORM, query builder, repository, SQL generator, binding or placeholder helper, generic paginator, transaction callback, or dialect abstraction. Each of those would move statement shape, parameter ownership, pagination cost, or transaction control away from the operation an AI and human need to review.

## Decision

The protected document-list proof remains entirely application-owned. It adds no framework `src/` file or runtime dependency. Consumer Contract version 4 and Strict Profile version 2 remain unchanged. `Connection`, `QueryBudget`, `QueryTrace`, explicit `beginTransaction`, `commit`, and `rollBack`, and PHT006 remain the complete framework database surface used by the example.

The example adds `GET /accounts/{account_id:positive-int}/documents`. It reuses the application-owned authentication and tenant-resolution boundaries accepted by ADR 020, then authorizes the distinct list action before parsing and executing protected list work. Concrete principal, requested account, and resolved tenant values remain explicit. Every non-empty list statement includes separate named bindings for the requested account, resolved tenant account, and principal membership check even where the current proof values coincide. Authorization does not install an implicit tenant scope.

`ListDocumentsPageRequest` owns this exact query contract:

- `order` accepts only `rank_asc` or `rank_desc` and defaults to `rank_asc` when omitted;
- `cursor` is absent for the first page or carries the exact `v1:<order>:<sort_rank>:<document_key>` composite of the selected order, last emitted numeric `sort_rank`, and case-sensitive `document_key`;
- stable order is `sort_rank` followed by `document_key COLLATE BINARY`, both ascending for `rank_asc` and both descending for `rank_desc`;
- each accepted non-empty page asks SQLite for 51 rows, returns at most 50, and derives the next cursor only from the last returned row when lookahead proves another row exists;
- `categories` omitted means no category predicate;
- `categories` present as an explicit empty list means an empty page and zero protected SQL; native PHP query inputs such as `?categories[]=` normalize to `['']`, which this operation also maps to that empty selection, while an empty string mixed with any category is rejected;
- one, two, or three exact unique category strings select one of three complete constant statements with exactly that many category placeholders; each category is 1–64 bytes, valid UTF-8, and free of ASCII control bytes and DEL, with no normalization; and
- more than three categories, an unknown query field, an unknown order, a malformed or order-mismatched cursor, a duplicate category, or a non-canonical category value is rejected before protected database work.

The cursor grammar and category grammar are application policy, not framework APIs. Cursor traversal is not a snapshot: inserts, deletes, or updates between pages can affect later results under SQLite's transaction and isolation behavior. The cursor version allows this application to reject incompatible future encodings rather than silently reinterpret them.

Every one of the eight statements retains the composite predicate and binds `cursor_is_absent` separately from the cursor rank and key. A first page binds `cursor_is_absent=1`, so the cursor predicate is explicitly inactive; code-owned rank `0` and empty-key values satisfy the fixed binding shape but do not filter stored rows. A continued page binds `cursor_is_absent=0` and applies the selected composite comparison. This keeps one visible statement shape per order and category cardinality without assuming every stored row already satisfies the projection's `0..1_000_000` rank and non-empty ASCII document-key contract; an invalid selected row reaches `DocumentSummary` and fails parsing.

`ListDocumentsHandler` owns the complete SQLite SQL strings. Every accepted structural combination maps to a finite reviewed complete statement; there is no runtime SQL assembly, generated SQL, dynamic SQL, fragment joiner, SQL/binding/placeholder helper, or query object beneath the handler. Each explicit named parameter array is written beside its direct `Connection::selectAllRows` call, every placeholder occurrence has a distinct portable name, and no value is interpolated. `DocumentSummary` immediately parses each returned row before response construction.

The four queried category cardinalities and two order directions form eight complete application-owned statements: omitted, one, two, or three categories for each direction. The explicit empty selection performs no statement. This repetition is intentional evidence that an AI can inspect the final SQL, tenant predicates, cursor-presence and composite predicates, ordering, limit, and bindings at each call site.

Every non-empty accepted page has a one-statement protected-data budget and a bounded one-fingerprint trace. Tests compare small and materially larger fixtures for every statement shape, traverse equal-rank rows without gaps or duplicates, exercise both directions and every supported category cardinality, and prove statement count remains one per page. Unsupported structure and the explicit empty selection leave that budget and trace unused. Malformed query input is parsed only after authorization and therefore returns the protected generic `400` with `private, no-store`. SQL-looking category values remain bound data, and document keys are also submitted through bindings.

The application SQL in this decision is exercised only as SQLite-specific evidence by the repository's current PDO SQLite runtime; the repository does not pin or certify an exact SQLite application version. The framework's SQLite, MySQL, and PostgreSQL database-driver harness certifies the base PDO transport contract only. It does not claim that this SQLite SQL, `BINARY` collation behavior, cursor ordering, query plan, or returned scalar representation works on MySQL or PostgreSQL.

Existing evidence remains authoritative instead of being duplicated: `TransactionalCreateUser` continues to prove explicit transaction rollback after a budget rejection, and the user-list N+1 negative control continues to prove query-growth detection and PHT003 rejection. This decision adds no shared transaction, pagination, SQL, or test utility.

## Evidence limits

The protected statements' explicit tenant and membership predicates prove only the exercised example path and bindings. They are not universal authentication, authorization, tenant-isolation, or row-security proof. PHT006 proves finite native string types at direct calls, not SQL correctness or safety. Adversarial binding probes prove the tested values remain data on SQLite; they are not universal SQL-injection certification.

A one-statement page does not bound rows scanned, guarantee an appropriate production plan, or prove indexes for a different schema or fixture cardinality. Cursor traversal is not snapshot pagination. Production adoption still requires the actual engine and version, schema, indexes, execution-plan evidence, runtime authority, concurrency policy, and integration tests to be recorded by the consuming application.

## Consequences

The example contains more repeated SQL and parameter arrays than a fluent or generated implementation would. That repetition is deliberate: all supported shapes, tenant conditions, ordering, cursor behavior, and bindings remain local and reviewable. Adding another sort, filter cardinality, engine, or cursor version requires an explicit application decision, complete statements, and corresponding behavior and cost evidence.

No ORM, query builder, repository, generic paginator, SQL/binding/placeholder helper, transaction callback, dialect abstraction, generated SQL, or dynamic SQL is accepted by this decision. No framework core, dependency, Consumer Contract version, Strict Profile version, or diagnostic changes.

## Reconsider when

At least two independent applications prove the same smaller contract across materially different schemas and engines without hiding complete SQL, explicit bindings, tenant predicates, cursor semantics, or query cost; or the finite statement family becomes too large to review safely. Reconsider one narrow evidence-backed contract, not a general persistence or pagination layer.
