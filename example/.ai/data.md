# Example data contract

This file records only the checked-in example's current data-path decisions and evidence. It does not define a reusable consumer policy.

## Engine and execution boundary

- Engine: SQLite through the current `ext-pdo_sqlite` runtime used by `tools/setup-example.php` and the isolated behavior fixtures. The repository does not pin an exact SQLite application version; a consuming application records and tests its deployed version.
- Execution: direct `PHPThis\Database\Connection` calls with operation-specific `QueryBudget` and `QueryTrace` values.
- SQL ownership: complete raw SQLite statements remain visible in their handler or the one independently justified Create transaction owner.
- Bindings: explicit named parameter arrays remain beside each direct call; each placeholder occurrence has a distinct name.
- Forbidden: ORM, Active Record, query builder, repository, generic paginator, SQL helper, binding helper, placeholder helper, generated or dynamic SQL, transaction callback, and dialect abstraction.
- Driver claim: MySQL and PostgreSQL are certified only for the base PDO transport harness. No document-list application SQL is certified on those engines.
- Runtime authority: the local SQLite file/process boundary is evaluation evidence only; it is not production least-privilege proof.

## Protected document list

Route: `GET /accounts/{account_id:positive-int}/documents`.

Policy order is authenticate, resolve tenant, authorize the list action, then parse and execute protected list work. The requested account, resolved tenant account, and principal membership identity are passed and bound separately. The checked-in composition remains deny-all; permitted behavior is exercised with explicit test policies.

| Input | Accepted policy | Database result |
| --- | --- | --- |
| `order` omitted | `rank_asc` | ascending composite page |
| `order=rank_asc` | ascending numeric `sort_rank`, then `document_key COLLATE BINARY` | one finite statement |
| `order=rank_desc` | descending numeric `sort_rank`, then `document_key COLLATE BINARY` | one finite statement |
| `cursor` omitted | first page | `cursor_is_absent=1` explicitly disables the bound composite comparison |
| `cursor` present | exact `v1:<order>:<sort_rank>:<document_key>` composite compatible with selected order | keyset predicate in the selected complete statement |
| `categories` omitted | no category filter | one no-filter statement |
| direct `categories=[]` or parsed `categories=['']` | explicit empty selection; native PHP inputs such as `?categories[]=` produce the latter shape | empty page, zero protected SQL |
| one, two, or three categories | exact bounded list with no duplicates | one complete statement with exactly one, two, or three category placeholders |
| more than three categories or malformed structure | rejected | zero protected SQL |

Each accepted non-empty category is an exact 1–64-byte string, valid UTF-8, and free of ASCII control bytes and DEL. Exact duplicates are rejected and values are not normalized. The parsed `['']` shape is reserved for the empty-selection convention and is not an accepted category value.

Each non-empty page requests 51 rows, returns at most 50, and exposes a next cursor only when lookahead finds another row. The page order uses numeric `sort_rank` and the SQLite `BINARY` document-key tie-break. An absent cursor binds `cursor_is_absent=1` plus inert rank `0` and empty-key values, so no stored row is silently excluded before projection parsing. A continued page binds `cursor_is_absent=0` and applies the rank/key comparison. Accepted document keys are non-empty ASCII tokens and ranks are bounded from `0` through `1_000_000`. The cursor is opaque outside this application contract, and traversal is not a snapshot when rows change between requests.

The finite statement family is selected in ordinary typed PHP, and every complete SQL statement is written beside its explicit parameter array at its direct call. No SQL fragment is assembled, generated, sanitized, quoted, or supplied by input. Unknown order values, malformed or order-mismatched cursors, duplicate or non-canonical categories, and unsupported list cardinalities fail before the protected query budget or trace changes. Because parsing follows authorization, those failures use the protected generic `400` response with `private, no-store`.

## Evidence and limits

- Every accepted non-empty statement shape uses exactly one protected statement for small and materially larger fixtures.
- Equal-rank traversal proves no gaps or duplicates in each static fixture for both directions.
- Missing, empty, and one-to-three-category behavior is distinct and tested.
- SQL-looking category values remain bound data, document-key values are also bound, and identifier-shaped structural attacks are rejected before protected SQL.
- Denied policy paths execute no protected document SQL.
- `TransactionalCreateUser` remains the transaction and rollback proof; it is not duplicated here.
- The existing user-list N+1 negative control remains the growth-detection proof; it is not duplicated here.

Explicit tenant predicates and membership bindings do not prove universal authorization. PHT006 and adversarial values do not prove universal injection safety. One statement does not prove bounded rows scanned or a production execution plan. SQLite evidence does not certify this application SQL on another engine.
