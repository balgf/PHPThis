# PHPThis knowledge map

This is the installed framework knowledge router for an AI working in a PHPThis application. It is not a tutorial manual. Start with `docs/consumer-contract.md`, combine it with the application's own `AGENTS.md` and `.ai/` context, and load only the smallest relevant row below.

Framework contracts define what is supported. Human-approved application decisions and context define desired local policy and may strengthen the framework contract, but cannot weaken it. Concrete source and tests establish what the installed version and application actually do.

Within each row, begin with the first current operational guide. Read a conditional guide only when the task enters that concern. Read an ADR only when reviewing or changing the decision it records; ordinary implementation does not load historical rationale. Contract upgrades and historical review additionally load `docs/consumer-contract-upgrades.md`.

A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision. After universal entrypoints, a simple-endpoint change has exactly four task-specific files: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test. That guide is `docs/request-handling.md`. Measure or report universal context separately; its cost is never hidden inside or used to weaken that four-file routing claim.

## Question and task routing

| Question or task | First current guide | Add only when the task enters this concern |
| --- | --- | --- |
| Explain PHPThis's purpose or whether a mechanism belongs | `VISION.md` | `ROADMAP.md` for planned or unsupported capability; a decision record only when reviewing or changing that decision. |
| Start or validate an application | `docs/getting-started.md` | `docs/guardrails.md` for package, skeleton, checker, or distribution proof. |
| Change application architecture or composition | `docs/architecture.md` | The current guide for each entered runtime concern and the application's `.ai/architecture.md`; no container, discovery, reflection wiring, facade, or hidden lifecycle is implied. |
| Review security, disclosure, credentials, or application threat policy | `docs/security.md` | The current guide for the concrete authentication, authorization, transport, data, integration, or deployment concern. Do not invent product policy or a generic framework security mechanism. |
| Select or set up a database engine | `docs/configuration.md` | `docs/database.md` after connection scope is selected; `docs/migrations.md` only after migration scope is selected. Local-development context alone authorizes no external I/O, provisioning, or mutation. |
| Add, explain, or review configuration or secrets | `docs/configuration.md` | `docs/security.md` for credential or disclosure policy; `docs/configuration/local-environment-launcher.md` only for an adopted local launcher. |
| Adopt, change, or review a local development environment launcher | `docs/configuration/local-environment-launcher.md` | `docs/cli.md` only when exposing an operational application command. |
| Define or review startup, liveness, dependency health, or readiness | `docs/configuration.md` | The guide for each dependency actually exercised; application `.ai/operations.md` owns the exact operational claim. |
| Assess or prepare a proposed PHPThis release | `RELEASING.md` | `ROADMAP.md`, `README.md`, and current decisions accepted after the latest recorded tag only for the release slice being assessed. A proposal is not approval or external-write authorization. |
| Prove or publish an approved PHPThis candidate | `RELEASING.md` | The exact human-approved candidate scope, release notes, and `SECURITY.md`; keep preparation, commit, push, tags, package, prerelease, and announcement as separately authorized actions. |
| Inspect an installed or historical PHPThis release | The exact installed version or requested tag and its matching release notes | Matching tagged contract, Strict Profile, source, and package inventory; live GitHub and Packagist state only when publication is in scope. Do not substitute later `main` guidance. |
| Assess the Alpha 2 consumer profile or a capability exit | `docs/consumer-profile.md` | The current guide for the specific capability; its ADR only when reviewing or changing the recorded decision. |
| Add a simple application endpoint | `docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged, and this is the complete four-file task-specific set after universal entrypoints |
| Add a typed application route | `docs/request-handling.md` | `docs/type-safety.md` for another external value boundary. |
| Review or change framework routing behavior | `docs/request-handling.md` | `docs/architecture.md`, exact `src/Routing/` or `src/Http/` source and focused tests; the matching routing ADR only for decision review. |
| Add or review reusable route-level request behavior | `docs/request-handling.md` | `docs/architecture.md` only for framework composition; no framework middleware or generic pipeline is implied. |
| Add or review authentication, tenant resolution, or authorization | `docs/stateless-authentication.md` | `docs/request-policy.md`, then `docs/security.md` and `docs/errors.md` for the entered policy and failure boundaries. PHPThis supplies no authentication runtime or identity provider. |
| Add or explain a CRUD-shaped feature | `docs/crud.md` | `docs/database.md` only when the operation performs database work. The reference structure is not a generic persistence layer. |
| Define or review a structured JSON resource success representation | `docs/frontend-integration.md` | `docs/request-handling.md`; `docs/database.md` only when nested data enters an I/O plan. No serializer, relationship loader, paginator, or generator is implied. |
| Design or review frontend integration, CORS, static assets, or application-owned HTML | `docs/frontend-integration.md` | Only the specific HTTP, security, deployment, or testing guide routed there. PHPThis supplies no frontend runtime, CORS middleware, renderer, template engine, asset engine, or client generator. |
| Compose, deliver, or review transactional email | `docs/email.md` | Jobs, configuration, security, logging, or observability guides only when that concern is entered. PHPThis supplies no mailer, renderer, queue, worker, or webhook receiver. |
| Propose, add, or review a WebSocket path | `docs/websockets.md` | `docs/security.md`; the WebSocket ADR only when reviewing or changing the application-owned profile. Frames never become PHPThis HTTP values, and no framework WebSocket runtime exists. |
| Read runtime request data, return a response, or change terminal summaries | `docs/request-handling.md` | `docs/errors.md`, `docs/logging.md`, or `docs/observability/README.md` only for the entered concern. |
| Configure or review outer HTTP failure disclosure | `docs/errors.md#outer-http-failures` | `docs/configuration.md#http-failure-disclosure-selection`, `docs/request-handling.md#outer-http-failure-boundary`, and `docs/security.md`; application `.ai/operations.md` owns effective web-SAPI and isolation evidence. Native PHP display remains off in every profile. |
| Adopt or review log levels, destination records, files, streams, or Grafana delivery | `docs/logging.md` | The matching guide under `docs/observability/`; application `.ai/operations.md` only for surrounding deployment or probe policy. No framework logger or delivery guarantee is implied. |
| Construct, emit, or review a response cookie | `docs/request-handling.md` | `docs/security.md` only when the cookie carries authentication or other sensitive state. |
| Adopt, secure, store, or return a file, including Amazon S3 | `docs/file-transfers/README.md` | `docs/security.md` and `docs/performance.md`; the selected profile's guide and decision only for adoption or decision review. Select exactly one profile and infer no generic storage or lifecycle API. |
| Add or secure cookie-backed session state | `docs/sessions.md` | `docs/security.md` for application policy. Session transport supplies no authentication, authorization, expiry, revocation, or CSRF policy. |
| Choose HTTP or server-side derived-data caching | `docs/caching.md` | The exact backend guide only after application policy is recorded. PHPThis supplies no cache mechanism. |
| Add or review an atomic lock, lease, or operation-coordination boundary | `docs/coordination.md` | The current owner of the protected CLI, migrations, jobs, cache, session, or file-transfer concern. PHPThis supplies no portable lock, lease, fencing, or distributed guarantee. |
| Adopt or review the optional Redis cache and schedule-lease recipe | `docs/redis-coordination.md` | `docs/redis/README.md`; `docs/caching.md` for governing cache policy. Do not generalize the recipe into a framework mechanism. |
| Adopt or review backend-neutral durable jobs | `docs/jobs/README.md` | `docs/jobs.md` and `docs/jobs/verification.md`; `docs/security.md` when payload, backend, or privileged-operation risk is entered. PHPThis supplies no queue, worker, adapter, validator, or exactly-once guarantee. |
| Adopt or review the checked SQLite durable-job recipe | `docs/jobs/sqlite.md` | `docs/jobs/operations.md`, `docs/jobs/testing.md`, and `docs/security.md`; its ADR only for decision review. Do not generalize SQLite transaction, lease, or one-shot evidence to another backend. |
| Add or assess an operational command or scheduled pass | `docs/cli.md` | `docs/coordination.md` for overlap policy and `docs/jobs/README.md` when invoking deferred work. `vendor/bin/phpthis` remains the checker, not an application console. |
| Adopt, use, or review PHPThis Workbench | `docs/workbench.md` | Security, data, integrations, operations, database, jobs, or CLI guides only for side effects actually exposed. Workbench is not a sandbox, production shell, container, discovery system, or alternate publisher. |
| Add, apply, or recover a database migration | `docs/migrations.md` | `docs/database.md` and `docs/security.md`; configuration, data-authority, and operations application context only for their owned facts. No framework migration runtime, portable DDL, or HTTP migration path is implied. |
| Connect to, read, write, or assess SQL safety or database authority | `docs/database.md` | `docs/security.md`, `docs/performance.md`, or `docs/logging.md` only for the entered concern; `docs/static-analysis.md` for PHT006/PHT008 proof limits. Exact-engine runtime evidence remains mandatory. |
| Adopt or review the checked SQLite protected document-list recipe | `docs/database.md` | `docs/crud.md` and `docs/request-policy.md`; its ADR only for decision review. Do not generalize its schema, cursor, filters, or policy model. |
| Add an application-owned cursor or bounded list filter | `docs/database.md` | `docs/crud.md`. Record the operation's own grammar, finite statements, ordering, bounds, and exact-engine evidence. |
| Adopt or review the versioned document cursor and category-filter recipe | `docs/database.md` | `docs/crud.md`; its ADR only for decision review. The recipe is not a generic paginator or filter contract. |
| Parse, persist, calculate, schedule, or test date and time behavior | `docs/date-time.md` | `docs/type-safety.md` for external values and `docs/cli.md` for a scheduled pass. |
| Parse JSON, query parameters, database rows, or other external values | `docs/type-safety.md` | `docs/errors.md`; `docs/request-handling.md` for HTTP representation and `docs/static-analysis.md` for type-proof boundaries. |
| Define or review public failure mapping and disclosure | `docs/errors.md` | `docs/type-safety.md` for rejected external values and the current transport or policy guide for the entered failure. |
| Adopt or review field-addressable value issues | `docs/errors.md#optional-application-owned-field-issues` | `docs/type-safety.md`, `docs/frontend-integration.md`, or `docs/request-handling.md` only for the entered boundary. No validator, error bag, wrapper, renderer, generator, or universal schema is implied. |
| Explain or repair a `PHT` diagnostic | `docs/strict-profile.md` | `docs/static-analysis.md`, the named rule implementation, failing file, and nearest passing framework pattern. |
| Review a possible duplication advisory | `docs/static-analysis.md` | The reported application locations and complete project check. Do not infer invalidity or automatic refactoring. |
| Assess performance, scale behavior, or resource limits | `docs/performance.md` | The current database, cache, external-I/O, or transport guide whose bounded behavior is being measured. |
| Change project-specific architecture, data, integrations, or operations | The matching application `.ai/` guide | `docs/consumer-contract.md`, accepted application decisions, concrete source, and tests. |
| Upgrade across Consumer Contract versions or review contract history | `docs/consumer-contract-upgrades.md` | The exact version decisions and affected concern guides. Updating PHPThis never grants permission to overwrite application-owned context. |
| Ask for a capability without a supported current pattern | `ROADMAP.md` | `VISION.md` and the nearest current concern guide; read decisions only to review their recorded boundary. State unsupported behavior before proposing an application decision. |

## Answer protocol

When answering how PHPThis works or how code should be written:

1. Identify the installed PHPThis version or exact dependency revision when available.
2. Inspect the consumer contract, the relevant application guide, and the concrete source and tests. Do not treat model memory as framework authority.
3. Distinguish current framework behavior, application-owned policy, and a new proposal. Never present a proposal as an existing feature.
4. Name the files, symbols, diagnostics, or checks that support the answer so a human can audit it.
5. State when PHPThis deliberately has no canonical mechanism instead of borrowing a pattern from another framework.
6. If implementation was requested, run the complete application gate and report the evidence. If only an explanation was requested, do not change the repository.

Do not invent missing product requirements, schema meaning, authorization policy, production limits, or external-service behavior. Surface those decisions to the accountable human.
