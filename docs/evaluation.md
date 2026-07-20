# Development pattern evaluation

PHPThis needs executable evidence, not a claim that visible SQL or AI-oriented documentation automatically prevents mistakes.

The current harness proves specific code and execution properties. It does not yet prove that an AI will answer every framework question correctly, use the installed version instead of model memory, or surface every decision that belongs to a human.

## Current proof

Run:

```bash
composer check
```

The focused scaling harness is also available as `composer test:query-scaling`; the transaction evidence runs with the behavioral tests in `composer test`.

The harness creates fresh SQLite databases with the same `users` and indexed `user_events` schema used by the example application. Each user has two events.

The accepted `GET /users` handler uses one bounded aggregate statement per accepted page. It returns equivalent data for a 2-user fixture and a 50-user fixture while executing exactly one statement in both cases. Its query trace contains one fingerprint executed once and is not truncated. Separate evidence traverses 125 static users as 50, 50, and 25 rows using canonical `after_user_id` values, with one fresh request budget and trace per page and no gaps or duplicates.

The negative control returns the same data by selecting users once and then counting events once per user. It executes 3 statements for 2 users and 51 statements for 50 users. The child-query fingerprint reaches 50 executions. The harness also proves that:

- `PHT003` rejects the exact negative source at its stable line;
- the invalid source is not an accepted `.php` file or autoload target;
- a query budget of 3 records three executed statements and rejects the fourth before PDO or the query trace sees it.

The `POST /users` tests provide the transaction evidence. Empty and 500-user fixtures both require three writes: user, event, and commit-visible welcome job. A rejected event or job write rolls every preceding change back.

The ADR 021 input-boundary proof keeps the HTTP adapter, `CreateUserCommand`, `CreateUserOperation`, and `TransactionalCreateUser` explicit. The command accepts one exact bounded JSON object and rejects missing versus explicit-null fields, loose scalar types, nested values, unknown secret-looking fields, malformed UTF-8 including a lone surrogate, excessive depth, and its exact endpoint byte overflow. A name padded with PHP `trim`'s default ASCII control/space characters is rejected rather than normalized; other valid Unicode, including non-breaking space, is preserved because the example claims no Unicode whitespace or normalization policy. Email uses the explicit unmodified `FILTER_VALIDATE_EMAIL` behavior with no flags: representative evidence preserves case and `+` addressing and rejects Unicode-local, repeated-dot, local-domain, trailing-dot, and padded forms. Every invalid representation receives the same prebuilt generic `400 invalid_request`, except the stable generic `413` body-limit response; submitted values remain absent from public output, logs, and traces. A separate success test proves native `json_decode`'s documented repeated-key last-value behavior. A replaceable operation records zero calls for invalid input, database budgets and traces remain zero, and PHPStan verifies that accepted behavior receives only `CreateUserCommand`. This proves one application-owned boundary, not a framework validator, field-error schema, sanitizer, hydrator, or automatic request binder.

ADR 022 adds a protected finite document-list path. Its application handler owns eight complete raw SQLite statements for two order directions and omitted or one-to-three-category filters. A direct empty category list or parsed `['']` shape returns zero rows with zero protected SQL; native PHP inputs such as `?categories[]=` produce that parsed shape. Every non-empty statement shape fetches at most 51 returned rows, emits at most 50, binds requested account, resolved tenant account, principal membership, cursor presence and values, categories, and limit explicitly, and remains one statement across small and materially larger fixtures. Static-fixture traversal covers equal numeric ranks with the SQLite binary document-key tie-break in both directions. Malformed order, cursor, and list structure fail before protected SQL and return the protected generic cache policy; SQL-looking bound values remain data.

The first `GET /users/{user_id}` item proof accepts only the bounded typed route, immediately converts the route integer into `UserId`, parses a concrete `UserDetails` projection, returns an explicit missing response, and executes one database statement. It does not prove authorization or tenant scope.

The routing harness separately proves the ADR 019 grammar with a nested two-identifier route, canonical positive integers, bounded case-sensitive opaque tokens, exact-literal precedence, startup overlap rejection, type-specific immutable delivery, 404 and ordered 405 behavior, and indexed lookup across 20,000 parameterized declarations. Half of that table deliberately creates literal siblings beside one reused typed state, providing construction-scaling regression evidence as well as lookup evidence. The example registers the nested route explicitly and immediately wraps both values in concrete `AccountId` and `DocumentKey` values without object loading or hidden I/O. The routing benchmark records construction, memory, hits, misses, oversized-token misses, and allowed-method lookup without hardware-dependent pass thresholds. This proves routing syntax and lookup shape, not domain existence, authorization, tenancy, or production latency.

The ADR 020 request-policy proof places one application-owned adapter on that nested route. It exercises a stateless authentication boundary, tenant resolution, current per-request authorization, and protected operation delivery in one fixed visible order. Unauthenticated, ordinary forbidden, and cross-tenant paths stop before protected queries and writes; ordinary and cross-tenant denial share one generic `403`. The proof policies are deliberately I/O-free, while protected retrieval has its own one-statement budget and trace and stays constant across small and larger fixtures. Alternate implementations replace each policy through manual constructor wiring. Error and unknown-failure evidence submits synthetic credentials and identifiers and proves that they do not enter public responses, terminal summaries, or trace snapshots. The checked-in composition is deny-all and PHPThis supplies no credential parser or verifier. The selected protected operation is read-only, so its denial-side write evidence is zero retrieval entry and zero protected statements; a later mutation must additionally assert unchanged persistent state. This establishes the application composition shape, not a production identity provider, tenant model, permission store, audit system, or framework middleware. A policy that reads storage still needs a separate named connection, budget, trace, and failure proof.

ADR 023 adds application-owned terminal request-summary evidence without a core logging API. The tests prove generated and canonical 32-character lowercase-hex IDs, ownership of the single `X-Request-ID` response header, one closed success event, status-only mapped and routed failures, class-only unknown failures, repeated-fingerprint aggregation without SQL or binding retention, budget-overrun visibility before the rejected PDO attempt, the eight-source bound, unique labels, distinct observation state, fresh state across sequential requests, complete synthetic request/response/database secret exclusion, and exactly one sink invocation attempt. A throwing sink leaves success and generic unknown-failure responses, headers, bodies, and cookies unchanged. This proves in-process construction and attempted invocation, not durable destination delivery, bootstrap or fatal-error coverage, response-emitter success, or network delivery.

## CRUD reference-profile evidence

The optional CRUD reference profile is an authoring and placement convention, not a runtime CRUD abstraction. The example proves its current executable scope with separate `Users/CreateUser`, `Users/ListUsers`, and `Users/GetUser` use-case directories, one explicit route-area manifest, direct constructor wiring, visible SQL, strict command, identifier, and projection boundaries, and operation-specific query budgets and traces.

User Create and List retain the behavior and scaling evidence above after adopting that structure. User List adds a concrete page-request boundary that rejects malformed or unknown continuation input before database work; this remains one example-owned policy rather than a generic paginator. User Get adds only the narrow typed-route, concrete-identifier, explicit-missing, projection, and one-query proof; its authorization and tenant policy remain unresolved. Document List adds a second application-owned pagination policy under a protected route without creating shared pagination, repository, SQL, binding, placeholder, transaction, or dialect utilities. The framework does not inspect application directories, and this repository does not claim complete item CRUD: Update and Delete still require application-owned decisions and executable evidence for authorization, concurrency, conflicts, deletion, and retention semantics.

## Database transport certification

`composer test:database-drivers` runs the same narrow PDO transport probe for every driver selected by `PHPTHIS_DATABASE_TEST_DRIVERS`. Local and complete repository checks default to SQLite. The dedicated CI job supplies real SQLite, MySQL 8.4, and PostgreSQL 17 drivers and services; an unavailable requested driver or missing DSN fails rather than skips.

The probe proves native connection, unique named string/integer/boolean/null bindings, associative one-row and collection fetches, one-row insert and delete counts, commit, rollback, visibility from a second connection, independent traces, budget rejection before PDO, and rethrown database failures recorded by the trace. Its SQL deliberately uses only the tiny common subset needed to exercise transport. It is not a dialect translation layer.

The bound-string probe includes quotes, a semicolon, and an SQL comment marker and must round-trip exactly without changing the statement fingerprint or row count. Harness table names are fixed code-owned constants so PHT006 can prove their finite shape. Because the harness creates and drops those tables, MySQL and PostgreSQL runs require a disposable or dedicated test database; their DDL-capable fixture credentials do not model production runtime authority.

## SQL-safety evidence

Strict Profile version 2's PHT006 fixtures prove that the three direct canonical `Connection` calls accept literals, native constants, non-interpolated statement text, named arguments, and finite constant-string choices while rejecting arbitrary or blank strings, dynamic interpolation, argument unpacking, PHPDoc-only narrowing, and callable indirection. Runtime behavior separately proves that SQL-looking bound text remains one unchanged data value and is absent from query traces.

Applications complete that evidence with tests for every real structural choice and safe engine-specific verification of runtime database authority. Unknown structural input must fail before database work. A test account's ability to execute the intended statements and inability to exercise selected prohibited authority is deployment evidence, not a portable framework feature.

## Future AI comparison

The current proof compares programming patterns; it does not yet prove that one AI model, prompt, or context strategy outperforms another.

A model comparison must use:

1. A frozen functional prompt that does not mention N+1 or reveal the holdout checks.
2. Fresh isolated worktrees for a base PHP condition and a PHPThis-context condition.
3. The same model identifier, settings, token budget, time budget, and available tools.
4. An external holdout scorer added only after generation.
5. At least 10 trials per condition before reporting a rate.

Record the prompt hash, model identifier, repository revision, generated diff, validity-gate output, small and large statement counts, repair turns, and token use. The primary metric is the percentage of functionally correct submissions that also keep query count constant and pass every boundary and rollback check. Timing is secondary because this SQLite fixture is a correctness experiment, not a production benchmark.

## Future knowledge-interface evaluation

Evaluate the no-traditional-manual claim separately from code generation. Freeze a set of framework explanation and implementation-planning questions across at least two installed PHPThis revisions, including questions about unsupported capabilities and deliberate version differences. Give the AI only the repository and normal project instructions.

For each answer, record:

- the exact framework revision and application-context revision;
- every cited contract, decision, source file, symbol, test, and diagnostic;
- whether the answer separates installed behavior, application policy, and proposals;
- unsupported claims, invented APIs, missed conflicts, and unjustified certainty;
- consequential choices correctly surfaced for human judgment;
- files loaded, tokens used, and repair turns after holdout review.

A passing answer must be correct for the installed revision, supported by accessible repository evidence, explicit about missing authority, and free of invented framework behavior. Human reviewers score semantic correctness; automated checks verify cited paths and symbols where practical.

## Limits

- SQLite proves the execution shape used by the repository tests, not plans or locking behavior on another database.
- The cross-driver transport probe does not prove application SQL, DDL, generated identifiers, scalar representations, update row counts, error translation, isolation, locking, plans, charset, timezone, TLS, or timeout behavior on any engine.
- PHT006 proves a finite native string type at direct canonical calls, not that the SQL is correct, nondestructive, authorized, or safe inside stored procedures or server-side dynamic SQL; reflection and other non-canonical execution paths remain review limits.
- Parameter binding does not prove tenant or record authorization, and static analysis does not verify actual database grants or migration-credential isolation.
- Adversarial bound-data tests prove only their exercised paths and are not universal SQL-injection certification.
- Multiple certified connections remain independent; the probe does not provide distributed transactions or cross-database atomicity.
- Statement budgets do not bound rows scanned or event-history fan-out; this proof detects query-count growth, not total database cost.
- The keyset traversal is not a snapshot. It can observe higher identifiers inserted between requests and other concurrent changes according to the target database's isolation rules; production evaluation must choose that policy explicitly.
- The `after_user_id` contract is one application-owned ascending-identifier policy. It does not supply filters, ordering choices, opaque cursors, total counts, or a generic framework paginator.
- The document `v1:<order>:<sort_rank>:<document_key>` cursor is another application-owned policy. Its static-fixture no-gap/no-duplicate traversal is not snapshot consistency, and SQLite `BINARY` ordering and application SQL are not certified by the MySQL or PostgreSQL PDO transport probes.
- The document tenant and membership predicates, PHT006 result, and adversarial binding probes are not universal authentication, authorization, tenant-isolation, or SQL-injection proof.
- Item Get still uses only the retained trailing positive-integer shape and lacks its own authorization and tenant policy; Update and Delete routes are not implemented. ADR 020 proves an application-owned policy composition on the nested two-identifier document path, not domain policy for every CRUD operation or a general resource-identifier pattern.
- The sample enforces JSON `Content-Type` and maps malformed input, unsupported media, and oversized bodies; database conflicts remain generic 500 failures until a reliable named translation is designed.
- The application-owned terminal summary proves response correlation and bounded in-process query evidence, not durable sink delivery, pre-bootstrap or process-fatal coverage, response-emitter success, or network delivery.
- The project-owned AI context and installed knowledge map define a grounding strategy, not proof that every model will follow it; grounded-answer trials remain future work.
