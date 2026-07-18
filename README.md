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
- Routes are explicit method, path, and already-constructed handler objects, composed from named route-area lists into one visible manifest.
- An optional feature-first [CRUD reference profile](docs/crud.md) gives AI-authored Create and List work one compartmentalized default without adding a generic CRUD runtime or enforcing application directories.
- One request boundary normalizes bounded PHP runtime input and maps only explicitly registered exception classes.
- Optional session state uses one lazy native-PHP lifecycle with bounded scalar snapshots, explicit secure response cookies, short lock duration, and no session helper or session field on the request.
- Markdown is part of the framework interface. The guardrail command requires more Markdown files than PHP files.
- The core is intentionally capped at 1,700 physical lines for Phase 1 after security and concurrency review of the accepted cookie and native-session decision; that margin does not pre-authorize another mechanism.

Removing an ORM does **not** prove that N+1 queries are impossible. PHPThis combines visible SQL with query budgets and scale-sensitive tests so that query count cannot silently grow with result size.

Finite SQL and parameter binding do not prove authorization or least privilege. Consuming applications record and verify the objects and actions available to each runtime connection and keep schema-owner, migration, and administrative credentials outside the web runtime.

## Current state

**Status: experimental pre-alpha.** Framework APIs may change without backward compatibility while the development pattern is being proven. Do not use PHPThis in production.

This is a zero third-party runtime-dependency foundation. The current proof slice supports bounded runtime request ingestion, immutable headers and validated response cookies, optional lazy native-file sessions, exact error mapping, exact-path routing, explicit handlers, and instrumented PDO access. Its sample application includes a bounded `GET /users` List operation and a transactional `POST /users` Create operation. Get, Update, and Delete are not yet claimed; item operations wait for typed path parameters and application-owned policy decisions. Session transport is not an authentication, authorization, expiry, or CSRF implementation; applications own those policies.

The executable query-scaling proof holds the accepted read at one statement as its fixture grows from 2 to 50 users. An isolated N+1 negative control produces the same JSON response body while growing from 3 to 51 statements; `PHT003` rejects that implementation, and a query budget stops it before statement 4.

```text
PHP runtime -> RequestBoundary -> optional lazy SessionLifecycle -> Application -> Router -> Handler -> Response
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
curl -i -X POST http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' \
  --data '{"name":"Katherine Johnson","email":"katherine@example.com"}'
```

## Start an application

The independently checked `phpthis/skeleton` package source now lives under `skeleton/`. It contains a runnable health application, project-owned AI context, the installed profile gate, behavior tests, and CI. The separate Composer package will be published with the first alpha; until then, [the getting-started guide](docs/getting-started.md) describes source evaluation without pretending `composer create-project` is already available.

Every application must own and commit a thin root `AGENTS.md` and a task-routed `.ai/` directory. These files record project-specific domain, scale, integration, operational, and verification facts without copying PHPThis's maintainer instructions.

Ask the project AI to follow the [application bootstrap contract](docs/getting-started.md), the installed [consumer contract](docs/consumer-contract.md), and the [knowledge map](docs/knowledge-map.md). Existing applications can still adopt the documentation-only context under `templates/application/` deliberately.

## Authority and project status

- [Vision](VISION.md) defines AI-first authoring with human accountability.
- [Consumer contract](docs/consumer-contract.md) is the portable application validity floor.
- [Knowledge map](docs/knowledge-map.md) routes an AI to the smallest relevant installed source of authority.
- [CRUD reference profile](docs/crud.md) defines the optional feature-first application structure and its current evidence boundary.
- [Security baseline](docs/security.md) defines SQL data/structure separation, least-privilege obligations, and the limits of automated proof.
- [Session state](docs/sessions.md) defines the optional native lifecycle, explicit cookie contract, deployment requirements, and application-policy boundary.
- [Architecture decisions](docs/decisions/README.md) preserve accepted rationale and reconsideration triggers.
- [Evaluation](docs/evaluation.md) defines evidence and future AI-comparison work.
- [Roadmap](ROADMAP.md), [contribution gate](CONTRIBUTING.md), and [security policy](SECURITY.md) communicate the pre-alpha project's current boundaries.

The maintainer [AI context index](.ai/README.md) routes changes to PHPThis itself. It is not copied into applications.

## License

PHPThis is open-source software licensed under the [MIT License](LICENSE).
