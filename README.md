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

- No ORM, Active Record, lazy loading, query builder, facades, global helpers, autowiring, route discovery, or runtime macros.
- SQL stays visible and engine-specific behind a thin PDO transport boundary; the base connection contract is exercised with SQLite locally, and dedicated CI is configured to certify the same contract against SQLite, MySQL, and PostgreSQL.
- Strict Profile version 2 keeps data in unique named parameters and limits direct `Connection` SQL to finite, non-blank compile-time constant statements; application-owned structural choices map to reviewed statements rather than a sanitizer or query builder.
- Every database connection has an explicit query budget that fails before an excessive statement executes.
- Every database connection has a bounded query trace that reports repeated SQL fingerprints, execution timing, and failures without retaining SQL or parameters.
- External database and JSON values are parsed once into concrete final readonly projections and commands before entering typed code.
- A versioned Strict Profile rejects legal-but-unsafe PHP with stable, repair-oriented `PHT` diagnostics.
- Consuming applications run one installed `phpthis check` binary whose maximum-level PHPStan configuration cannot be weakened by project files.
- An installed knowledge map routes framework questions to the relevant contract, decision, source, and test instead of relying on model memory.
- Handlers implement one visible `handle` method and receive dependencies through normal constructors.
- Routes are explicit method, path declaration, and already-constructed handler objects, composed from named route-area lists into one visible manifest. Paths are literal or use the one bounded trailing `{name:positive-int}` form; dispatch remains indexed and handlers keep `handle(Request)`.
- An optional feature-first [CRUD reference profile](docs/crud.md) gives AI-authored Create, List, and bounded item-Get work one compartmentalized default without adding a generic CRUD runtime or enforcing application directories.
- One request boundary normalizes bounded PHP runtime input and maps only explicitly registered exception classes.
- Optional session state uses one lazy native-PHP lifecycle with bounded scalar snapshots, explicit secure response cookies, short lock duration, and no session helper or session field on the request.
- Caching begins with an explicit application policy, not a framework helper: HTTP response caching and server-side data caching are separate concerns, and PHPThis currently provides no generic cache runtime.
- Markdown is part of the framework interface. The guardrail command requires more Markdown files than PHP files.
- The Phase 1 core ceiling is 2,050 physical lines after review of the cookie/session and bounded typed-routing slices. The reviewed implementation occupies 2,010 lines; the remaining 40-line maintenance margin does not pre-authorize any adjacent mechanism.

Removing an ORM does **not** prove that N+1 queries are impossible. PHPThis combines visible SQL with query budgets and scale-sensitive tests so that query count cannot silently grow with result size.

Finite SQL and parameter binding do not prove authorization or least privilege. Consuming applications record and verify the objects and actions available to each runtime connection and keep schema-owner, migration, and administrative credentials outside the web runtime.

## Current state

**Status: experimental prerelease evaluation software.** Framework APIs may change without backward compatibility while the development pattern is being proven. Do not use PHPThis in production.

The bounded [Alpha 1 release scope](docs/decisions/018-bounded-alpha-1-release-scope.md) is accepted. Package availability and current release state are external facts: verify the approved tags and Packagist packages rather than inferring publication from this immutable source file. Alpha 1 freezes the checked installation and authoring surface; it does not claim production readiness, backward compatibility, complete CRUD, authentication, authorization, or tenancy. The [release gate](RELEASING.md) must prove both packages and the clean public installation path before Alpha 1 is announced.

This is a zero third-party runtime-dependency foundation. The current proof slice supports bounded runtime request ingestion, immutable headers and validated response cookies, optional lazy native-file sessions, exact error mapping, directly indexed literal routes, one indexed trailing positive-integer route shape, explicit handlers, and instrumented PDO access. It does not include a cache client, cache interface, cache helper, or general automatic HTTP cache policy. Framework-generated 404, 405, and 500 responses and the current skeleton/example response paths explicitly use `Cache-Control: no-store`; arbitrary application handler responses remain application-owned. The sample application includes a bounded `GET /users` List operation with one explicit application-owned keyset continuation, a transactional `POST /users` Create operation, and the first bounded `GET /users/{user_id}` item proof. That Get slice proves typed routing, concrete identifier conversion, and bounded query cost, not complete authorization or tenant policy. Update and Delete are not yet claimed. Session transport is not an authentication, authorization, expiry, or CSRF implementation; applications own those policies.

The executable query-scaling proof holds every accepted page at one statement while traversing 125 users as 50, 50, and 25 rows without gaps or duplicates. Its isolated N+1 negative control grows from 3 statements for 2 users to 51 for 50 users; `PHT003` rejects that implementation, and a query budget stops it before statement 4.

```text
PHP runtime -> RequestBoundary -> optional lazy SessionLifecycle -> Application -> Router -> RouteMatch -> Request copy -> Handler -> Response
```

## Try it

PHP 8.4 with PDO, PDO SQLite, ext-session, and Composer are required for the complete development checks. PHPStan and the PHPThis Strict Profile are mandatory development components and do not affect the framework runtime.

```bash
git clone https://github.com/balgf/PHPThis.git
cd PHPThis
composer install
composer check
composer example:setup
php -S 127.0.0.1:8080 -t example/public
curl -i http://127.0.0.1:8080/health
curl -i http://127.0.0.1:8080/users
curl -i 'http://127.0.0.1:8080/users?after_user_id=1'
curl -i http://127.0.0.1:8080/users/1
curl -i -X POST http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' \
  --data '{"name":"Katherine Johnson","email":"katherine@example.com"}'
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
- [Security baseline](docs/security.md) defines SQL data/structure separation, least-privilege obligations, and the limits of automated proof.
- [Session state](docs/sessions.md) defines the optional native lifecycle, explicit cookie contract, deployment requirements, and application-policy boundary.
- [Caching policy](docs/caching.md) separates HTTP response caching from application data caching and defines the evidence required before either is adopted.
- [Architecture decisions](docs/decisions/README.md) preserve accepted rationale and reconsideration triggers.
- [Alpha 1 scope](docs/decisions/018-bounded-alpha-1-release-scope.md) defines the bounded first-prerelease claim, and the [release process](RELEASING.md) defines the public-artifact gate.
- [Evaluation](docs/evaluation.md) defines evidence and future AI-comparison work.
- [Roadmap](ROADMAP.md), [contribution gate](CONTRIBUTING.md), and [security policy](SECURITY.md) communicate the experimental project's current boundaries.

The source repository contains a maintainer-only `.ai/README.md` that routes changes to PHPThis itself. It is intentionally excluded from the Composer package; installed consumers use the [knowledge map](docs/knowledge-map.md).

## License

PHPThis is open-source software licensed under the [MIT License](LICENSE).
