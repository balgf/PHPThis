# Bounded AI-context routing review

Review date: 2026-08-24, Asia/Manila
Decision: [ADR 058](decisions/058-concern-local-ai-context-routing.md)
Scope: the accepted Issue #59 source reorganization

This is a frozen repository-grounded route review, not a model comparison or a claim that every agent will follow the written route. The reviewer followed the current maintainer and installed-application routers for each exact prompt, inspected the named first owner and conditional additions, and checked the resulting answer boundary against the unsupported-claim list below. Distribution and consumer tests separately prove that the reviewed files are packaged and coherent.

## Frozen prompts and results

| # | Exact prompt and audience | Expected first current owner | Conditional additions permitted by the prompt | Unrelated context that must stay unloaded | Reviewed route and result |
| ---: | --- | --- | --- | --- | --- |
| 1 | Application: “Add a dependency-free `GET /ping` literal endpoint beside the existing health route.” | installed `docs/request-handling.md` | existing named route-area manifest, dependency-free handler, nearest behavior test | upgrade history and every optional concern guide | Selected the expected owner and exact four task-specific files after universal entrypoints; no skipped safety or evidence and no unsupported claim. |
| 2 | Application: “Upgrade this application from Consumer Contract 14 and Strict Profile 3 to the installed current contract.” | installed `docs/consumer-contract-upgrades.md` | only concern guides selected by the recorded version migration, application source, tests, and complete gate | unrelated older migrations and maintainer-checkout current prose as installed-version authority | Selected upgrade history first, then the affected current concern owners; no ordinary implementation route was presented as history and no unsupported claim was found. |
| 3 | Maintainer: “Change application configuration guidance and the value-free Composer command policy.” | `.ai/configuration.md` | `.ai/static-analysis.md`, `.ai/testing.md`, and `.ai/application-context.md` only if shared consumer/template routing changes | jobs, migrations, file transfer, WebSockets, release procedure | Selected configuration; retained literal-name, value-free, typed-boundary, redaction, and consumer-proof limits without inventing automatic loading. |
| 4 | Application: “Adopt the local development environment launcher and add a readiness probe for its database dependency.” | installed `docs/configuration/local-environment-launcher.md` | application `.ai/configuration.md`, `.ai/operations.md`, `.ai/cli.md`, `.ai/testing.md`, and installed configuration/database guidance actually entered | jobs, migrations unless separately requested, release history | Selected launcher configuration plus the entered operations and database-probe concerns; did not claim dotenv loading, provisioning authority, or production readiness. |
| 5 | Maintainer: “Change the backend-neutral durable-job adoption contract.” | `.ai/jobs.md` | exact selected public job guides, security, configuration, operations, and testing concerns | application-context umbrella, migration and file-transfer guides | Selected jobs; preserved application ownership and rejected a framework queue, adapter, automatic discovery, or exactly-once claim. |
| 6 | Maintainer: “Change application-owned PostgreSQL migration guidance.” | `.ai/migrations.md` | configuration, database authority, coordination, operations, exact-engine evidence, and ADR review because the decision itself is being changed | jobs, file transfers, WebSockets, upgrade history | Selected migrations and the explicitly entered supporting concerns; did not generalize SQLite transactions, locks, DDL, or recovery to PostgreSQL. |
| 7 | Maintainer: “Review whether a consumer may switch its adopted local file profile to Amazon S3.” | `.ai/file-transfers.md` | installed file-transfer index, security, testing, and ADR 053 because the profile decision is under review | jobs, WebSockets, generic application context | Selected the single file-transfer owner and required an explicit profile decision; did not invent a storage abstraction or treat static guidance as AWS/deployment proof. |
| 8 | Maintainer: “Change the application-owned WebSocket guidance.” | `.ai/websockets.md` | selected runtime, security, configuration, operations, and real process/socket evidence | HTTP adaptation, jobs, generic application-context detail | Selected WebSockets; retained the separate-process boundary and made no framework WebSocket, event-loop, channel, retry, or delivery guarantee claim. |
| 9 | Maintainer: “Change transactional-email guidance and its application context template.” | `.ai/email.md` | `docs/email.md`, template `.ai/integrations.md`, configuration, jobs, operations, observability, and testing only when entered | migrations, file transfers, WebSockets, release state | Selected email; preserved application-owned package/provider composition and made no framework mailer, renderer, queue, worker, or webhook claim. |
| 10 | Maintainer: “Add configuration for an S3-backed durable job and document its operational probe.” | `.ai/configuration.md` first for the named configuration change | `.ai/jobs.md`, `.ai/file-transfers.md`, `.ai/operations.md`, security, testing, and application-context only if shared routing/inventory also changes | upgrade history, WebSockets, Workbench, unrelated ADRs | Selected every entered concern explicitly instead of falling back to the former umbrella; no concern was omitted to minimize context and no unsupported claim was found. |

## Unsupported-claim checklist

Each reviewed route was checked for these failures:

- invented PHPThis runtime, API, automatic discovery, hidden context loading, or generated policy;
- generic configuration, queue, storage, authentication, coordination, migration, or integration abstractions not accepted by the selected owner;
- static guidance, a checker pass, or synthetic evidence presented as production adoption, deployment readiness, external-service behavior, or security certification;
- skipped safety, authorization, exact-engine/service, behavior, resource-bound, or complete-gate evidence;
- weakened Consumer Contract, Strict Profile, permanent diagnostic, or application-owned policy;
- upgrade or decision history presented as ordinary current implementation authority; or
- a proposal presented as installed behavior or as an accepted consequential decision.

Findings: 0 unsupported claims across 10 fixed routes. This result establishes only that the accepted written routers provide an unambiguous safe route for these prompts at this source revision. It does not measure answer quality, token use, compliance probability, or comparative model performance.
