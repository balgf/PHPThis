# Application AI context index

This directory owns current context for `{{PROJECT_NAME}}`. Resolve every template token before feature work. Keep context committed, concise, and free of secrets.

Consumer Contract v12 and Strict Profile v3 remain mandatory. Application guidance may strengthen them but may not weaken them.

## Universal entrypoints

After `AGENTS.md` and the installed Consumer Contract and knowledge map, always read:

1. `.ai/rules.md`
2. `.ai/change-workflow.md`
3. `.ai/project.md`

Then start with exactly one current operational guide from the table. Add another guide only when the task actually enters its concern.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

## Simple endpoint route

Use the exact simple-endpoint definition and four-file locality metric in the already-read installed `vendor/phpthis/framework/docs/knowledge-map.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.

An ordinary route change starts with installed `vendor/phpthis/framework/docs/request-handling.md`; read a decision record only when reviewing or changing the decision it records.

## Task routes

| Task | Start with | Inspect next; add another guide only if entered |
| --- | --- | --- |
| Add or change a qualifying simple endpoint | installed `vendor/phpthis/framework/docs/request-handling.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |
| Explain current framework or application behavior | `.ai/architecture.md` | installed knowledge route, execution path, and nearest tests |
| Change application structure or dependencies | `.ai/architecture.md` | composition root and affected boundary |
| Define or change a structured JSON resource success representation, including nested child data | installed `vendor/phpthis/framework/docs/frontend-integration.md`, then installed `vendor/phpthis/framework/docs/request-handling.md`; add installed `vendor/phpthis/framework/docs/database.md` when nested data enters an I/O plan | exact application response construction and `Content-Type`, operation-owned `data` and optional `meta` fields, semantic relationship names, fixed bounded query/cache/external-call counts independent of parent-page cardinality, I/O-free mapping and encoding, parent pagination, exact nested decoder fixtures, compatibility decision, `.ai/data.md`, and `.ai/testing.md`; add no generic wrapper, relationship loader, serializer, paginator, or generator |
| Build or change frontend integration or application-owned HTML rendering | installed `vendor/phpthis/framework/docs/frontend-integration.md` | `.ai/architecture.md`, `.ai/testing.md`, and exact HTTP paths; add other concern guides only when entered |
| Compose or deliver transactional email | installed `vendor/phpthis/framework/docs/email.md` | `.ai/integrations.md` and the operation-specific composer and transport; add configuration, jobs, operations, and testing context only when entered |
| Select or set up a database engine | `.ai/change-workflow.md` | prompt and current configuration/data facts before any external action |
| Change deployment configuration | `.ai/configuration.md` | one environment-reading file, typed values, composition, and parser tests |
| Adopt or change a local development environment launcher | installed `vendor/phpthis/framework/docs/configuration/local-environment-launcher.md`; add installed `vendor/phpthis/framework/docs/cli.md` when an operational command is exposed | `.ai/configuration.md`, `.ai/operations.md`, `.ai/cli.md`, `.ai/testing.md`, exact PHP launcher and shared canonical `ApplicationEnvironment` reader, ignored local file and profile/key map, array-form private-child handoff with an exact environment and inherited standard streams, production non-use, and real-launcher evidence |
| Change a non-simple route or request input | installed `vendor/phpthis/framework/docs/request-handling.md` | route manifest and only the application guides for concerns actually entered |
| Change application data or SQL | `.ai/data.md` | direct call site, authority, exact-engine, adversarial, and scale evidence |
| Change date, time, timezone, duration, or clock behavior | installed `vendor/phpthis/framework/docs/date-time.md` | exact operation representation; add architecture, configuration, data, CLI, operations, and testing context only when entered |
| Change authentication, stateless Bearer/JWT/PAT/external-provider, tenant, or authorization policy | `.ai/request-policy.md` | installed `vendor/phpthis/framework/docs/stateless-authentication.md`, action-specific composition, protected work, lifecycle, and denial tests |
| Adopt, secure, or change file transfer | `.ai/file-transfers.md` as the single authoritative policy, then installed `vendor/phpthis/framework/docs/file-transfers/README.md` | effective pre-PHP ingress, request-policy order, temporary and durable roots, concrete storage/content/quota/lifecycle operations, response path, and transfer tests |
| Change an external side effect | `.ai/integrations.md` | named client boundary, failure policy, and contract tests |
| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |
| Change correlation or terminal summaries, or adopt optional log levels and destinations | `.ai/observability.md` | coordinator, sink, finite database sources, summary tests, and `NOT_APPLICABLE(OPERATIONAL_LOG_RECORD)` or the explicitly adopted finite record, destination, lifecycle, collector, and evidence facts without changing installed request-summary v1/v2 |
| Change cookie-backed session state | installed `vendor/phpthis/framework/docs/sessions.md` | architecture, operations, and testing facts only when adopted |
| Change HTTP or server-side caching | installed `vendor/phpthis/framework/docs/caching.md` | response path or data, integration, operations, and testing facts |
| Change durable deferred work | `.ai/jobs.md` | configuration, producer, worker, operations, and lifecycle tests |
| Change an application command or scheduled pass | `.ai/cli.md` | console, typed arguments, operations, and real-console tests |
| Change database migrations | `.ai/migrations.md` | configuration, authority, manifest, ledger, operations, and exact-engine tests |
| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, configuration, operation, and socket tests |
| Change the development Workbench | `.ai/workbench.md` | approved package, checked bootstrap, explicit workspace, and retained tests |
| Change CRUD-shaped operations | installed `vendor/phpthis/framework/docs/crud.md` | implemented routes plus architecture, data, and testing facts actually affected |
| Add or change tests | `.ai/testing.md` | nearest behavior test and complete project gate |

Accepted application decisions live in `docs/decisions/`. Read one only when the task reviews or changes its underlying decision. Add a narrowly named guide when a recurring task needs current context that does not fit this map.
