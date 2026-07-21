# PHPThis

PHPThis is an experimental, checked PHP profile and minimal web framework for **AI-first authoring with human accountability**. AI is the primary code author and knowledge interface. Humans provide intent, decide consequential tradeoffs, and remain accountable for the resulting software.

It stays close to ordinary PHP and favors code that is local, literal, typed, bounded, and easy to verify over APIs optimized for typing speed.

PHPThis does not provide AI or LLM APIs. "AI" refers to the code-authoring workflow.

The working rule is simple: if a behavior cannot be found by following ordinary PHP definitions, it does not belong in the framework.

## Ask the project AI

PHPThis has no traditional framework manual. To learn how to code something, explain an existing path, or repair a diagnostic, ask the AI working in the application. Its first job is to inspect the installed PHPThis version, the application's `AGENTS.md` and `.ai/` context, and the concrete source and tests.

Useful requests include:

- `Explain how this application handles a request. Cite the installed PHPThis files and this project's wiring.`
- `Add a bounded database read using this application's canonical PHPThis pattern and prove its query count stays constant.`
- `Explain PHT005, show where this project violates it, and repair the cause.`
- `Does PHPThis currently support middleware? Check the installed version and distinguish existing behavior from a proposal.`

The versioned Markdown in this repository is not a linear tutorial. It is compact framework authority that an AI can route and a human can audit. Human-approved intent and decisions define desired application behavior; source, tests, and the complete check provide executable evidence of what was implemented.

## What makes it different

- No ORM, Active Record, lazy loading, query builder, repository, SQL/binding/placeholder helper, generic paginator, transaction callback, generated SQL, dialect abstraction, facades, global helpers, autowiring, route discovery, or runtime macros.
- SQL stays visible and engine-specific behind a thin PDO transport boundary; the base connection contract is exercised with SQLite locally, and dedicated CI is configured to certify the same contract against SQLite, MySQL, and PostgreSQL.
- Strict Profile version 2 keeps data in unique named parameters and limits direct `Connection` SQL to finite, non-blank compile-time constant statements; application-owned structural choices map to reviewed statements rather than a sanitizer or query builder.
- The finite data-path proof keeps eight complete raw SQLite document-list statements and their explicit parameter arrays at direct `Connection` call sites. It proves two orders, a composite cursor, bounded categories, parsed empty-selection zero-SQL behavior, and one statement per non-empty page without turning that evidence into a framework paginator.
- Every database connection has an explicit query budget that fails before an excessive statement executes.
- Every database connection has a bounded query trace that reports repeated SQL fingerprints, execution timing, and failures without retaining SQL or parameters.
- Every application owns one explicit terminal request-summary coordinator and sink. It generates a 128-bit lowercase-hex correlation ID, propagates `X-Request-ID`, and makes one failure-isolated sink invocation attempt with bounded per-connection budget and trace evidence; PHPThis adds no core logger, middleware, facade, or hidden instrumentation.
- External database and JSON values are parsed once into concrete final readonly projections and commands. Inbound [typed input boundaries](docs/type-safety.md) distinguish missing and null, reject non-canonical values, keep public failures generic, and permit downstream operation behavior only after complete parsing—without a generic validator or mandatory service layer.
- A versioned Strict Profile rejects legal-but-unsafe PHP with stable, repair-oriented `PHT` diagnostics.
- Consuming applications run one installed `phpthis check` binary whose maximum-level PHPStan configuration cannot be weakened by project files.
- An installed knowledge map routes framework questions to the relevant contract, decision, source, and test instead of relying on model memory.
- Handlers implement one visible `handle` method and receive dependencies through normal constructors.
- Routes are explicit method, path declaration, and already-constructed handler objects, composed from named route-area lists into one visible manifest. Paths are literal or use at most two full-segment `positive-int` or bounded `token` parameters; deterministic dispatch remains indexed and handlers keep `handle(Request)`.
- Protected routes use one application-owned action-specific adapter with visible `authenticate -> resolve tenant -> authorize -> handler` order, concrete principal and tenant values, replaceable policies, and no framework middleware or request-context mechanism.
- An optional feature-first [CRUD reference profile](docs/crud.md) gives AI-authored Create, List, and bounded item-Get work one compartmentalized default without adding a generic CRUD runtime or enforcing application directories.
- One request boundary normalizes bounded PHP runtime input and maps only explicitly registered exception classes.
- Optional [file transfers](docs/file-transfers/README.md) normalize at most one PHP-visible multipart upload into a typed value and emit one concrete local file in fixed chunks; storage, authorization, retention, raw duplicate enforcement, and deployment policy remain application-owned, with no storage facade or range implementation.
- Optional session state uses one lazy native-PHP lifecycle with bounded scalar snapshots, explicit secure response cookies, short lock duration, and no session helper or session field on the request.
- Caching begins with an explicit application policy, not a framework helper: HTTP response caching and server-side data caching are separate concerns. One application-owned Redis proof keeps current authorization before a tenant-scoped document cache and uses a separate owner-token schedule lease without adding a generic framework cache or lock runtime.
- Durable deferred work begins with one application-owned SQLite recipe: the business write and versioned job row share one transaction, a fresh one-shot worker uses fenced leases and bounded retries, and one idempotent database effect is proved without adding a framework queue or exactly-once claim.
- Schema evolution begins with one application-owned SQLite migration ledger: a finite ordered unrolled manifest, checksum-locked immutable history, one transaction per migration, separate authority, and a same-host nonblocking lock—without adding a framework migration API, schema builder, discovery, or rollback inference.
- Markdown is part of the framework interface. The guardrail command requires more Markdown files than PHP files.
- The Alpha 2 core ceiling is 2,500 physical lines. After the accepted bounded multipart and local-file response slice, the reviewed implementation occupies 2,495 lines; its five-line maintenance margin does not pre-authorize any adjacent mechanism.

Removing an ORM does **not** prove that N+1 queries are impossible. PHPThis combines visible SQL with query budgets and scale-sensitive tests so that query count cannot silently grow with result size.

Finite SQL and parameter binding do not prove authorization or least privilege. Consuming applications record and verify the objects and actions available to each runtime connection and keep schema-owner, migration, and administrative credentials outside the web runtime.

## Current state

**Status: experimental prerelease evaluation software.** Framework APIs may change without backward compatibility while the development pattern is being proven. Do not use PHPThis in production.

The bounded [Alpha 1 release scope](docs/decisions/018-bounded-alpha-1-release-scope.md) is accepted. Package availability and current release state are external facts: verify the approved tags and Packagist packages rather than inferring publication from this immutable source file. Alpha 1 freezes the checked installation and authoring surface; it does not claim production readiness, backward compatibility, complete CRUD, authentication, authorization, or tenancy. The [release gate](RELEASING.md) must prove both packages and the clean public installation path before Alpha 1 is announced.

This is a zero third-party runtime-dependency framework foundation. The current proof slice supports bounded runtime request ingestion, one typed bounded multipart upload, one fixed-chunk local-file response, immutable headers and validated response cookies, optional lazy native-file sessions, exact error mapping, directly indexed literal routes, and one deterministic state index for paths with at most two full-segment positive-integer or opaque-token parameters, plus explicit handlers and instrumented PDO access. It does not include a framework cache client, cache interface, cache helper, lock or lease interface, general automatic HTTP cache policy, framework queue runtime, framework migration runtime, generic storage abstraction, or byte ranges. Framework-generated 404, 405, and 500 responses and every current skeleton/example response path explicitly include the `no-store` directive; protected responses additionally use `private`. Arbitrary application handler responses remain application-owned. The sample application includes a bounded `GET /users` List operation with one explicit application-owned keyset continuation, a transactional `POST /users` Create operation that commit-publishes one SQLite welcome job, the first bounded `GET /users/{user_id}` item proof, nested protected document Get and List routes, public `POST`/`GET /document-files` transfer evidence with generated local identities, and an application-owned `database:migrate` SQLite proof with immutable checksummed history. ADR 024 adds a SQLite-specific one-delivery worker proof with a versioned envelope, finite dispatch, fenced leases, bounded retries, redacted dead letters, process-crash recovery, and one idempotent database effect. ADR 028 adds one application-owned Redis document-cache and schedule-lease proof with current authorization, explicit fallback, bounded stale refill, separate cache and `noeviction` lease endpoints, and owner-token-safe renewal and release. The Redis owner token is not a fencing token and the lease does not replace SQLite job correctness. ADR 020 establishes the explicit stateless authentication boundary, tenant resolution, current per-request authorization, denial bounds, and replaceable I/O-free application policies. ADR 022 adds the SQLite-only document collection proof with `rank_asc`/`rank_desc`, exact `v1:<order>:<sort_rank>:<document_key>` cursors, omitted/empty/one-to-three categories, and one statement per non-empty page. These proofs do not supply a credential parser, identity provider, credential lifecycle, policy engine, universal tenant model, generic paginator, snapshot guarantee, cross-engine application SQL, cross-engine queues or migrations, generic cache or lease APIs, Redis failover guarantees, or exactly-once external effects. Update and Delete are not yet claimed. Session transport is not an authentication, authorization, expiry, or CSRF implementation; applications own those policies.

The executable query-scaling proof holds every accepted page at one statement while traversing 125 users as 50, 50, and 25 rows without gaps or duplicates. Its isolated N+1 negative control grows from 3 statements for 2 users to 51 for 50 users; `PHT003` rejects that implementation, and a query budget stops it before statement 4.

```text
PHP runtime -> application terminal coordinator -> RequestBoundary -> optional lazy SessionLifecycle -> Application -> Router -> RouteMatch -> Request copy -> route policy adapter -> protected Handler -> Response + X-Request-ID -> one sink attempt -> ResponseEmitter
```

## Try it

PHP 8.4 with PDO, PDO SQLite, ext-session, and Composer are required for the framework checks. The repository's application-owned Redis integration proof additionally declares `ext-redis ^6.3`, tests Redis server `>=7.4` and `<9.0`, and requires two processes by default: cache at `127.0.0.1:6379` database `0`, and lease at `127.0.0.1:6380` database `0`. PHPStan and the PHPThis Strict Profile are mandatory development components and do not affect the framework runtime.

```bash
git clone https://github.com/balgf/PHPThis.git
cd PHPThis
composer install
composer check
php example/bin/console.php database:migrate
php -S 127.0.0.1:8080 -t example/public
curl -i http://127.0.0.1:8080/health
curl -i http://127.0.0.1:8080/users
curl -i 'http://127.0.0.1:8080/users?after_user_id=1'
curl -i http://127.0.0.1:8080/users/1
curl -i http://127.0.0.1:8080/accounts/42/documents/Doc_9-z
curl -i 'http://127.0.0.1:8080/accounts/42/documents?order=rank_asc'
curl -i -X POST http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' \
  --data '{"name":"Katherine Johnson","email":"katherine@example.com"}'
php example/bin/console.php jobs:run-one
php example/bin/console.php schedule:run
```

## Start an application

The independently checked `phpthis/skeleton` package source lives under `skeleton/`. It contains a runnable health application, project-owned AI context, the installed profile gate, behavior tests, and CI. The [getting-started guide](docs/getting-started.md) defines both the Packagist installation path and source-checkout evaluation while requiring current package availability to be verified externally.

Every application must own and commit a thin root `AGENTS.md` and a task-routed `.ai/` directory. These files record project-specific domain, scale, integration, operational, and verification facts without copying PHPThis's maintainer instructions.

Ask the project AI to follow the [application bootstrap contract](docs/getting-started.md), the installed [consumer contract](docs/consumer-contract.md), and the [knowledge map](docs/knowledge-map.md). Existing applications can still adopt the documentation-only context under `templates/application/` deliberately.

## Authority and project status

- [Vision](VISION.md) defines AI-first authoring with human accountability.
- [Consumer contract](docs/consumer-contract.md) is the portable application validity floor.
- [Knowledge map](docs/knowledge-map.md) routes an AI to the smallest relevant installed source of authority.
- [CRUD reference profile](docs/crud.md) defines the optional feature-first application structure and its current evidence boundary.
- [Finite data paths](docs/decisions/022-application-owned-finite-data-paths.md) records the application-owned raw-SQL collection proof and its SQLite-only limits.
- [Terminal request summaries](docs/logging.md) and [ADR 023](docs/decisions/023-application-owned-terminal-request-summaries.md) define application-owned correlation, redaction, bounded database evidence, one-attempt semantics, and sink-failure isolation.
- [Application CLI and scheduler](docs/cli.md), historical [ADR 025](docs/decisions/025-application-owned-explicit-cli-and-scheduler.md), and current [ADR 028](docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md) define one application-owned console, finite command and output contracts, explicit UTC cadence, and the executable example's Redis owner-token overlap boundary without adding a core CLI or scheduler API.
- [Explicit application migrations](docs/migrations.md) and [ADR 027](docs/decisions/027-application-owned-explicit-sqlite-migrations.md) define one application-owned SQLite ledger, finite unrolled migration manifest, immutable checksummed history, per-migration transactions, and same-host overlap boundary without adding a core migration API.
- [Security baseline](docs/security.md) defines SQL data/structure separation, least-privilege obligations, and the limits of automated proof.
- [Session state](docs/sessions.md) defines the optional native lifecycle, explicit cookie contract, deployment requirements, and application-policy boundary.
- [Request policy](docs/request-policy.md) defines the application-owned authentication, tenant-resolution, and authorization composition.
- [Caching policy](docs/caching.md) separates HTTP response caching from application data caching and defines the evidence required before either is adopted.
- [Redis cache and schedule coordination](docs/redis-coordination.md) and [ADR 028](docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md) define the one application-owned backend proof and its non-fencing limits.
- [Durable jobs](docs/jobs.md) and [ADR 024](docs/decisions/024-application-owned-sqlite-durable-jobs.md) define the application-owned SQLite recipe and its at-least-once boundary.
- [Architecture decisions](docs/decisions/README.md) preserve accepted rationale and reconsideration triggers.
- [Alpha 1 scope](docs/decisions/018-bounded-alpha-1-release-scope.md) defines the bounded first-prerelease claim, and the [release process](RELEASING.md) defines the public-artifact gate.
- [Evaluation](docs/evaluation.md) defines evidence and future AI-comparison work.
- [Roadmap](ROADMAP.md), [contribution gate](CONTRIBUTING.md), and [security policy](SECURITY.md) communicate the experimental project's current boundaries.

The source repository contains a maintainer-only `.ai/README.md` that routes changes to PHPThis itself. It is intentionally excluded from the Composer package; installed consumers use the [knowledge map](docs/knowledge-map.md).

## License

PHPThis is open-source software licensed under the [MIT License](LICENSE).
