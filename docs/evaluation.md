# Development pattern evaluation

PHPThis needs executable evidence, not a claim that visible SQL or AI-oriented documentation automatically prevents mistakes.

The current harness proves specific code and execution properties. It does not yet prove that an AI will answer every framework question correctly, use the installed version instead of model memory, or surface every decision that belongs to a human.

## Current proof

Run:

```bash
composer check
```

The focused scaling harness is also available as `composer test:query-scaling`; the transaction evidence runs with the behavioral tests in `composer test`.

The Alpha 2 rollup is recorded in `docs/consumer-profile.md` and ADR 029. Its umbrella harness composes the typed account route, application-owned request policy, strict command, four fixed raw-SQL transaction statements, commit-visible durable job, rollback, scaling, and redacted correlation evidence in one sanitized consumer request. The separate CLI, file, migration, cache, and lease suites remain supporting operational proofs rather than hidden work inside that request.

The harness creates fresh SQLite databases with the same `users` and indexed `user_events` schema used by the example application. Each user has two events.

The accepted `GET /users` handler uses one bounded aggregate statement per accepted page. It returns equivalent data for a 2-user fixture and a 50-user fixture while executing exactly one statement in both cases. Its query trace contains one fingerprint executed once and is not truncated. Separate evidence traverses 125 static users as 50, 50, and 25 rows using canonical `after_user_id` values, with one fresh request budget and trace per page and no gaps or duplicates.

The negative control returns the same data by selecting users once and then counting events once per user. It executes 3 statements for 2 users and 51 statements for 50 users. The child-query fingerprint reaches 50 executions. The harness also proves that:

- `PHT003` rejects the exact negative source at its stable line;
- the invalid source is not an accepted `.php` file or autoload target;
- a query budget of 3 records three executed statements and rejects the fourth before PDO or the query trace sees it.

The account-scoped `POST /accounts/{account_id:positive-int}/users` tests provide the transaction evidence. Empty and 500-user fixtures both require four writes: user, `account_users` relation, event, and commit-visible welcome job. Actor authorization remains in the separate `account_memberships` table. A rejected relation, event, or job write rolls every preceding change back, and repeated creation across the principal-id value proves user IDs cannot collide with actor authority.

The ADR 021 input-boundary proof keeps the HTTP adapter, `CreateUserCommand`, `CreateUserOperation`, and `TransactionalCreateUser` explicit. The command accepts one exact bounded JSON object and rejects missing versus explicit-null fields, loose scalar types, nested values, unknown secret-looking fields, malformed UTF-8 including a lone surrogate, excessive depth, and its exact endpoint byte overflow. A name padded with PHP `trim`'s default ASCII control/space characters is rejected rather than normalized; other valid Unicode, including non-breaking space, is preserved because the example claims no Unicode whitespace or normalization policy. Email uses the explicit unmodified `FILTER_VALIDATE_EMAIL` behavior with no flags: representative evidence preserves case and `+` addressing and rejects Unicode-local, repeated-dot, local-domain, trailing-dot, and padded forms. Every invalid representation receives the same prebuilt generic `400 invalid_request`, except the stable generic `413` body-limit response; submitted values remain absent from public output, logs, and traces. A separate success test proves native `json_decode`'s documented repeated-key last-value behavior. A replaceable operation records zero calls for invalid input, database budgets and traces remain zero, and PHPStan verifies that accepted behavior receives only the typed authenticated principal, resolved tenant, requested account, and `CreateUserCommand`. This proves one application-owned boundary, not a framework validator, field-error schema, sanitizer, hydrator, or automatic request binder.

ADR 022 adds a protected finite document-list path. Its application handler owns eight complete raw SQLite statements for two order directions and omitted or one-to-three-category filters. A direct empty category list or parsed `['']` shape returns zero rows with zero protected SQL; native PHP inputs such as `?categories[]=` produce that parsed shape. Every non-empty statement shape fetches at most 51 returned rows, emits at most 50, binds requested account, resolved tenant account, principal membership, cursor presence and values, categories, and limit explicitly, and remains one statement across small and materially larger fixtures. Static-fixture traversal covers equal numeric ranks with the SQLite binary document-key tie-break in both directions. Malformed order, cursor, and list structure fail before protected SQL and return the protected generic cache policy; SQL-looking bound values remain data.

The first `GET /users/{user_id}` item proof accepts only the bounded typed route, immediately converts the route integer into `UserId`, parses a concrete `UserDetails` projection, returns an explicit missing response, and executes one database statement. It does not prove authorization or tenant scope.

The routing harness retains the ADR 019 evidence and adds the ADR 032 grammar: canonical positive integers, bounded case-sensitive opaque tokens, lowercase hyphenated RFC-variant UUID versions 1 through 8, and lowercase 128-bit ULIDs with a leading `0` through `7`. It rejects UUID Nil and Max, unsupported versions and variants, uppercase and alternate UUID spellings, invalid ULID alphabets and overflow, encoded input, wrong accessors, duplicate names, and every differing sibling-type declaration. Invalid UUID/ULID syntax produces 404 before handler or database work; canonical values under the wrong method retain ordered 405 behavior. Exact literals still win, no UUID/ULID failure falls back to token, and immutable type-specific delivery remains unchanged. The 20,000-parameterized-route scale shape distributes token, UUID, and ULID final transitions across literal branches while nested routes retain a reused positive-integer transition. The example's existing `AccountId` and `DocumentKey` wrapping remains application evidence; guidance separately demonstrates immediate UUID wrapping and narrower version validation without claiming object loading or hidden I/O. The benchmark records construction, memory, literal, ULID and UUID hits, misses, oversized-token misses, and allowed-method lookup without hardware-dependent pass thresholds. This proves routing syntax and lookup shape, not domain existence, authorization, tenancy, identifier generation, storage, or production latency.

ADR 033 adds test-only and installed-consumer evidence for the optional application-owned request-handler decorator pattern without adding a core implementation. The proof composes named decorators directly beside a route around one terminal `RequestHandler`; it covers explicit nesting order, early return with zero downstream entry, one downstream call with the exact immutable `Request`, unchanged throwable identity, unchanged response return, and explicit immutable response replacement that preserves all unrelated status, header, body, cookie, and local-file-body fields. Bounded side-effect traces prove finite visible decorator work and short-circuit non-entry. This establishes the constrained application composition shape, not a framework middleware interface, pipeline, registry, priority system, discovery mechanism, `$next` callable, request context, or automatic composition.

The ADR 020 request-policy proof places one application-owned adapter on that nested route. It exercises a stateless authentication boundary, tenant resolution, current per-request authorization, and protected operation delivery in one fixed visible order. Unauthenticated, ordinary forbidden, and cross-tenant paths stop before protected queries and writes; ordinary and cross-tenant denial share one generic `403`. The proof policies are deliberately I/O-free, while protected retrieval has its own one-statement budget and trace and stays constant across small and larger fixtures. Alternate implementations replace each policy through manual constructor wiring. Error and unknown-failure evidence submits synthetic credentials and identifiers and proves that they do not enter public responses, terminal summaries, or trace snapshots. The checked-in composition is deny-all and PHPThis supplies no credential parser or verifier. ADR 029 adds the protected Create mutation proof: every policy or input denial leaves user, account-user, event, and job state unchanged, while direct SQL tests also reject mismatched tenant context and missing actor membership. This establishes the application composition shape, not a production identity provider, tenant model, permission store, audit system, or framework middleware. A policy that reads storage still needs a separate named connection, budget, trace, and failure proof.

ADR 023 adds application-owned terminal request-summary evidence without a core logging API. The tests prove generated and canonical 32-character lowercase-hex IDs, ownership of the single `X-Request-ID` response header, one closed success event, status-only mapped and routed failures, class-only unknown failures, repeated-fingerprint aggregation without SQL or binding retention, budget-overrun visibility before the rejected PDO attempt, the eight-source bound, unique labels, distinct observation state, fresh state across sequential requests, complete synthetic request/response/database secret exclusion, and exactly one sink invocation attempt. A throwing sink leaves success and generic unknown-failure responses, headers, bodies, and cookies unchanged. This proves in-process construction and attempted invocation, not durable destination delivery, bootstrap or fatal-error coverage, response-emitter success, or network delivery.

ADR 026 adds a bounded file-transfer proof. Boundary tests normalize only one flat multipart upload into `RequestUpload`, retain ordinary-body behavior, exercise canonical length and boundary limits, reject text, nested, normalized-multiple, chunked, malformed, and control-bearing input, preserve uploads through route copying, and distinguish recognized upload errors from an unknown runtime code. The application proof maps every error, verifies its 1 MiB operation limit before storage, checks PHP provenance and actual size on success, ignores hostile client filename and media type, generates its own storage identity, and retains explicit move and permission ownership. Response tests require exact file framing, reject duplicate/control/range headers and prior output, verify regular-file type and current length before headers, emit exact fixed chunks, and keep failures path-free. A real PHP built-in SAPI and `curl` flow proves exact-limit, oversized, missing, normalized-multiple, duplicate-scalar normalization, hostile-metadata, private modes, stored-hash, redacted unavailable storage, complete-download, and range-deferral behavior with client output written to files. This establishes the fixed application read-allocation shape and concrete local-filesystem path, not raw scalar duplicate rejection, injected kernel move/chmod or mid-read failures, end-to-end memory, proxy buffering, production authorization, retention, content safety, or delivery.

## CRUD reference-profile evidence

The optional CRUD reference profile is an authoring and placement convention, not a runtime CRUD abstraction. The example proves its current executable scope with separate `Users/CreateUser`, `Users/ListUsers`, and `Users/GetUser` use-case directories, one explicit route-area manifest, direct constructor wiring, visible SQL, strict command, identifier, and projection boundaries, and operation-specific query budgets and traces.

User Create and List retain the behavior and scaling evidence above after adopting that structure. User Create now adds ADR 029's account-scoped actor-policy proof and a separate created-user-to-account relation while leaving identity/conflict translation application-owned and unresolved. User List adds a concrete page-request boundary that rejects malformed or unknown continuation input before database work; this remains one example-owned policy rather than a generic paginator. User Get adds only the narrow typed-route, concrete-identifier, explicit-missing, projection, and one-query proof; its authorization and tenant policy remain unresolved. Document List adds a second application-owned pagination policy under a protected route without creating shared pagination, repository, SQL, binding, placeholder, transaction, or dialect utilities. The framework does not inspect application directories, and this repository does not claim complete item CRUD: Update and Delete still require application-owned decisions and executable evidence for authorization, concurrency, conflicts, deletion, and retention semantics.

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
- The local-file proof bounds emitter reads to 8,192 bytes and exercises one real SAPI, but it does not establish PHP/server/proxy buffering, peak process memory, concurrent throughput, filesystem durability, malware safety, remote storage, authorization, retention, client receipt, or byte ranges.
- The project-owned AI context and installed knowledge map define a grounding strategy, not proof that every model will follow it; grounded-answer trials remain future work.
