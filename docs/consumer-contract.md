# PHPThis application contract

Contract version: 17

This is the canonical contract for an application built with the installed PHPThis version. It defines the minimum development rules supplied by that version. Application instructions may add stricter rules and project-specific facts, but they must not weaken this contract.

The root `AGENTS.md` and `.ai/` directory in the PHPThis framework repository are maintainer instructions. They are not an application template. A consuming application owns its own `AGENTS.md` and `.ai/` directory.

## Authority and read order

For application work:

1. Read this contract from the installed PHPThis package.
2. Use the installed `docs/knowledge-map.md` to route the framework question or task.
3. Read the application's root `AGENTS.md` and `.ai/README.md`.
4. Read the application's `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`.
5. Start with the one current operational guide selected by `.ai/README.md`.
6. Inspect the concrete source and tests on the execution path.

Ordinary implementation starts with the current operational guide selected by those routers. Read another guide only when that guide routes the chosen concern there. Read a decision record only when reviewing or changing the decision it records; historical rationale is not ordinary implementation context. Load [the contract upgrade and history companion](consumer-contract-upgrades.md) only when upgrading an application across contract versions, reviewing contract evolution, or changing that history.

The PHPThis Strict Profile and executable application checks are the hard floor. Tests demonstrate behavior but do not authorize a contract violation. When application instructions conflict with this contract, preserve the contract and report the conflict.

Files under `vendor/` belong to installed dependencies. Do not edit them to customize application behavior or silence a finding; change application-owned code or propose an upstream framework change.

## AI authoring and human accountability

AI is the expected primary code author and knowledge interface for a PHPThis application. This does not make AI output authoritative and does not exclude human-authored contributions. When asked how PHPThis works or how application code should be written, the AI must inspect the installed version, this contract, the matching application context, and the relevant source and tests. Model memory alone is not evidence.

An answer must distinguish:

- behavior and constraints supplied by the installed PHPThis version;
- policy and facts owned by this application; and
- a proposed capability or decision that does not exist yet.

Name the supporting paths, symbols, diagnostics, or check output. Report missing or conflicting evidence instead of inventing framework behavior, product intent, schema meaning, authorization policy, production limits, or external contracts.

Humans direct the work and remain accountable for outcomes. Consequential product, architecture, security, data, migration, deployment, and external-side-effect choices must be made visible for human judgment. An AI may investigate options and draft a decision record, but it cannot approve its own consequential choice or infer authorization from silence. After explicit accountable-human approval, the AI may record the decision as accepted.

For an ambiguous database setup request, inspect the prompt and existing project state first. Unless another environment is named, treat the request as local development context, not authority to connect to or probe a server, mutate local services, or change data. Ask all unresolved choices in one concise message: database scope is configuration only, connection to an existing server, or project-local server provisioning; schema scope is deferred migrations or an application-owned migration foundation. Do not perform external database I/O, provision or mutate a server, infer migrations from database-engine selection, or include production hardening, backups, high availability, deployment credentials, recovery, or unrelated operations unless requested. A current not-applicable marker describes present behavior and does not resolve a new adoption request; do not repeat a choice resolved by the prompt or an explicit accepted project decision.

PHPThis therefore has no traditional framework manual as its canonical knowledge interface. Its current contract, routed guides, source, diagnostics, and tests ground ordinary work. Decision records and upgrade history remain human-readable evidence but are conditional context.

## Program validity

A PHPThis application must:

- run on PHP 8.4.x and declare strict types in every application-owned PHP file; the supported Composer range excludes PHP 8.5 until separately reviewed and tested;
- declare `phpthis/framework` as a runtime Composer dependency under `require` when application code executes framework classes;
- provide `ext-session` required by the installed framework, even when the application does not configure session state;
- require `phpstan/phpstan` at `^2.1` and `phpstan/phpstan-strict-rules` at `^2.0` as development dependencies, then run the framework-owned analysis configuration at maximum level;
- use the installed `phpthis check` binary to enforce Strict Profile version 4;
- give every application PHP file a case-insensitive `.php` extension, except that an extensionless executable may begin only with byte-zero lowercase `<?php`, or byte-zero exact `#!/usr/bin/env php`, one PHP-PCRE newline sequence, and immediate lowercase `<?php`; in either form the long tag is followed by EOF or ASCII HT, LF, CR, or space;
- keep every non-`.php` regular file readable and decisively non-PHP within the first 4,096 bytes when its bounded start could otherwise remain a direct or post-launcher PHP preamble, and do not place symlinks in the application tree outside the resolved Composer dependency and VCS exclusions;
- expose one documented project check command that runs static analysis, profile checks, and behavior tests;
- keep every application-owned named class final and expose an interface when an extension point is required;
- use ordinary constructors and a visible composition root instead of runtime discovery or service location;
- own one application configuration boundary that immediately returns process-specific final readonly typed values, or record configuration as not applicable;
- keep every direct process-environment read in one application-owned PHP file and use only `\getenv('EXACT_LITERAL_KEY')` as required by PHT007;
- keep root Composer script command text free of assignments or direct mutations of those adopted application configuration inputs;
- keep each optional application-owned request-handler decorator route-local, explicitly ordered, and limited to exactly one downstream `RequestHandler`;
- own one explicit terminal request-summary coordinator and one sink at the front-controller composition boundary, without adding framework logging types or hidden instrumentation;
- begin every ordinary or local-file response emission with headers unsent and no pending bytes in any active PHP-managed output-buffer level; empty active buffers remain valid, and application code fixes early output at its owner rather than cleaning or incorporating it;
- keep one canonical spelling and execution pattern for each framework operation;
- own every required application-context file listed below and resolve every template placeholder before feature work; and
- fix findings at their cause rather than adding baselines, broad ignores, consumer PHPStan configuration, or comment suppressions.

Composer does not inherit a dependency's root scripts or development dependencies. An application therefore declares `phpstan/phpstan`, `phpstan/phpstan-strict-rules`, its behavior-test command, and this canonical sequence itself:

```json
{
  "scripts": {
    "profile": "phpthis check",
    "test": "php tests/run.php",
    "check": ["@profile", "@test"]
  }
}
```

The `php tests/run.php` value is the skeleton's concrete example, not a required command or path. The contractual structure is the exact `profile` and `check` values plus a non-empty application-owned `test` script.

A Composer script may invoke a recorded application entrypoint, including a value-free long-running development command, but its command text must not contain assignment or recognized mutation spellings for an input name canonically read by application PHP. Supply one complete selected process profile at the outer process boundary or through the explicitly adopted application-owned local environment launcher. The cross-platform lexical check is case-insensitive and conservatively rejects the adopted `KEY=` spelling, including case variants, even when intended as inert argument or example text. `phpthis check` identifies only the input name; it never repeats a script name, command, or assigned value.

Applications must not add PHPStan configuration artifacts named `phpstan*.neon`, `phpstan*.neon.dist`, or `phpstan*baseline*.php`, or add `@phpstan-ignore` comments. This reserved filename family includes the usual `phpstan.neon`, `phpstan.neon.dist`, and PHPStan baseline variants. These create a second apparent definition of valid code and are rejected as `PHT004`. Project-specific static-analysis customization remains deliberately unsupported in Contract version 17.

## Automated behavior evidence

Every observable behavior change must add or update application-owned automated tests. The evidence covers expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits. Static analysis, documentation, manual verification, and a test command that merely exits successfully do not satisfy this obligation.

The application owns its test library, runner, file placement, and organization. PHPThis does not require PHPUnit, Pest, a `tests/` directory, or a particular distinction between unit, integration, and end-to-end tests. Composer `scripts.test` must execute the application's automated behavior evidence and return a non-zero status when that evidence fails. The complete project check must run it after `phpthis check`; an implementation task is incomplete until both stages pass.

When an application-owned test or validation entrypoint spans unrelated concerns or becomes difficult to review, prefer a small deterministic entrypoint that invokes cohesive concern-owned modules in an explicit order, with narrowly shared support. Preserve deterministic execution and failure behavior, and keep focused evidence directly runnable where the selected tool allows. Keep that composition explicit; do not introduce runtime discovery or a plugin framework merely to organize the runner. Modularize only application-owned code; do not copy, replace, or modularize the installed `vendor/bin/phpthis check` entrypoint. This is advisory organization guidance, not a validity rule: PHPThis sets no line-count threshold, prescribes no directory layout, test library, or module interface, and adds no checker rule. The application owns whether and how to split the entrypoint; its documented complete project check remains the authoritative gate.

The installed checker can verify the canonical gate wiring, but it cannot determine the semantic adequacy of an arbitrary test suite. The AI implementing a change must name the automated tests added or updated and the behavior they prove. The accountable human decides whether that evidence is sufficient for the requested outcome and risk.

The checker discovery boundary, permanent diagnostics, conservative limitations, and report-only duplication advisory are owned by [Static analysis](static-analysis.md) and the installed [Strict Profile](strict-profile.md). A duplication report never establishes invalidity or automatically requires refactoring. Visible SQL, unrolled bounded operations, security sequencing, and independent tests are not presumed defective merely because they repeat. Any future promotion of that advisory into validity requires a separate framework decision and consumer migration evidence.

## Universal safety and unsupported claims

- Never write credentials, tokens, private keys, customer data, production payloads, or other secrets into source, committed AI context, examples, fixtures, logs, traces, exception text, or reports. A name or obviously non-secret placeholder is not a value.
- Keep I/O visible and bounded. Execute application SQL only through direct `Connection` calls with finite compile-time-constant engine-specific SQL accepted by `PHT006` and one distinct exact named binding per placeholder occurrence as required by `PHT008`. Never execute a database call inside a loop or recursive traversal.
- Do not add runtime discovery, reflection wiring, a service container, ORM, Active Record, lazy loading, query builder, repository layer, facade, global helper, macro system, dynamic proxy, hidden fallback, or a second execution pattern.
- Do not use magic methods other than `__construct`, weaken maximum-level analysis, or suppress a Strict Profile finding with a baseline, ignore, exclusion, or comment exemption.
- An optional application-owned profile is not a PHPThis runtime capability. Adopt it only through its routed current guide, record the exact application choice, and prove its stated boundary. A not-applicable marker remains valid until the application deliberately enters that concern.
- Do not generalize one checked engine, backend, provider, topology, schema, policy, or example into a portable framework guarantee. State an unsupported boundary before proposing a new application decision.

## Mandatory application context

Every application must complete and commit:

```text
AGENTS.md
.ai/
  README.md
  rules.md
  change-workflow.md
  configuration.md
  project.md
  architecture.md
  data.md
  integrations.md
  observability.md
  operations.md
  testing.md
docs/
  decisions/
    README.md
```

The application context records facts the framework cannot infer: domain vocabulary, accountable human decision roles, real source paths, architectural boundaries, data scale, resource limits, external side effects, runtime assumptions, verification commands, and prohibited operations. Each concern has one current application owner selected by `.ai/README.md`; other guides link to that owner rather than copying it. Optional concern files such as `.ai/cli.md`, `.ai/file-transfers.md`, `.ai/jobs.md`, `.ai/migrations.md`, `.ai/request-policy.md`, and `.ai/workbench.md` may already be committed with an explicit not-applicable record. Before adopting the concern, add a missing file or replace and complete that record; retain a valid non-adopter record while the concern remains unadopted.

Keep current context compact and task-routed. Do not load every concern guide, historical decision, or this contract's upgrade companion for every change. Do not store credentials, tokens, private keys, customer data, production payloads, or other secrets in AI instructions. Detailed rationale and decision history belong in the application's `docs/decisions/` directory.

## Normative concern routing

The current guide named in the second column owns the full normative requirements and evidence for that concern. Start there, add only the guides it explicitly routes to, then inspect the concrete source and nearest tests. [The installed knowledge map](knowledge-map.md) provides the task-level first route without duplicating each guide's inspection checklist.

| Concern | First current guide | Boundary that must remain explicit |
| --- | --- | --- |
| Architecture and visible composition | `docs/architecture.md` | Ordinary constructors, explicit route and process composition, and one canonical path; no service container, runtime discovery, reflection wiring, facade, or hidden lifecycle. |
| Security, disclosure, and application threat policy | `docs/security.md` | Application-owned authentication, authorization, transport, credential, data, and deployment decisions; no inferred product policy or generic framework security mechanism. |
| Configuration, secrets, database-setup scope, startup and probes | `docs/configuration.md` | One typed application boundary; no framework configuration runtime, hidden fallback, implicit dotenv loading, or authority inferred from local-development context. |
| Local environment launcher | `docs/configuration/local-environment-launcher.md` | Application-owned and development-only; no automatic framework loader or production secret-delivery claim. |
| Requests, routes, typed input, responses, cookies, file-body emission, and terminal request summaries | `docs/request-handling.md` | One front controller and explicit immutable flow; no automatic binding, middleware pipeline, response inference, or delivery claim. |
| External values and public failures | `docs/type-safety.md` or `docs/errors.md`, according to the entered concern | Operation-owned parsing, stable bounded disclosure-safe failures, and downstream typed use; no generic validator, error bag, renderer, or automatic response mapping. |
| Route-local request-handler decorators | `docs/request-handling.md#application-owned-request-handler-decorators` | Optional final application classes with one downstream handler and visible order; no framework middleware path. |
| Authentication and authorization policy | `docs/stateless-authentication.md` or `docs/request-policy.md`, according to the entered concern | Current application policy and explicit order; PHPThis supplies no identity provider, credential verifier, permission store, or middleware. |
| SQL, database authority, query budgets, and finite data paths | `docs/database.md` | Direct complete engine-specific SQL and explicit bindings; no ORM, repository, query builder, portable-dialect, implicit authority, or cross-engine evidence claim. |
| Database migrations | `docs/migrations.md` | Optional application-owned exact-engine histories and coordination; no framework migration runtime, schema builder, discovery, portable DDL, or HTTP execution. |
| Sessions | `docs/sessions.md` | Optional typed state over the single native lifecycle; transport does not supply authentication, authorization, expiry, revocation, CSRF, or deployment policy. |
| Caching and operation coordination | `docs/caching.md` or `docs/coordination.md` | Application policy before mechanism; no framework cache, lock, lease, fencing, or distributed guarantee. |
| Durable jobs | `docs/jobs/README.md` | Optional application-owned backend contract; no framework queue, worker, adapter, discovery, or exactly-once claim. |
| Operational CLI, scheduler, and one-pass commands | `docs/cli.md` | One application console with finite commands; framework `bin/phpthis` remains the checker, not an application console or scheduler. |
| File transfers, including local storage and Amazon S3 | `docs/file-transfers/README.md` | Select exactly one adopted profile; no generic storage facade, automatic lifecycle, client-metadata trust, or provider-neutral guarantee. |
| WebSockets | `docs/websockets.md` | A separate application-owned third-party runtime; frames never become PHPThis HTTP values and no framework WebSocket runtime exists. |
| Development Workbench | `docs/workbench.md` | Separate optional `require-dev` package with arbitrary-PHP authority; no sandbox, production shell, discovery, generic dispatch, or retained-evidence shortcut. |
| CRUD-shaped features and bounded lists | `docs/crud.md` | Optional reference structure and application-specific operations; no generic persistence layer, generated SQL, registration, or inferred policy. |
| Frontend handoff and transactional email | `docs/frontend-integration.md` or `docs/email.md` | Application-owned boundaries and pinned integrations; no framework frontend, renderer, mailer, queue, client generator, or delivery guarantee. |
| Logging and observability | `docs/logging.md` or `docs/observability/README.md` | Bounded redacted application events and destinations; no framework logger, hidden instrumentation, or durable-delivery claim. |
| Date and time | `docs/date-time.md` | Application-owned concept, representation, clock, timezone, persistence, and boundary evidence; no implicit temporal policy. |
| Performance and resource bounds | `docs/performance.md` | Measure the selected execution path at materially different sizes; no unbounded read, N+1 traversal, or benchmark-based portability claim. |
| Static analysis, `PHT` diagnostics, and duplication review | `docs/static-analysis.md` and `docs/strict-profile.md` | Maximum-level framework-owned validity; duplication remains report-only and no baseline, ignore, consumer configuration, or automatic refactor is permitted. |
| Contract upgrade or historical review | `docs/consumer-contract-upgrades.md` | Conditional context only; updating PHPThis never authorizes overwriting application-owned context or external release actions. |

A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision. After the universal entrypoints above, a simple-endpoint change has exactly four task-specific files: `docs/request-handling.md`, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test. Report universal context cost separately from that four-file task-specific metric; no size result permits skipping authority, safety, or evidence.

Contract version 17 and Strict Profile version 4 remain current. Version 17 carries version 16's source-discovery boundary forward and rejects response emission when headers are already sent or any active PHP-managed output-buffer level has pending bytes, without adding a Strict Profile rule or `PHT` diagnostic.
