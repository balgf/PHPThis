# Application AI context index

This directory owns current context for the checked health-only starter. Replace only the guides entered by a task with verified project facts before adding product behavior. Keep context committed, concise, and free of secrets.

Consumer Contract v11 and Strict Profile v3 remain mandatory. Application guidance may strengthen them but may not weaken them.

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
| Select or set up a database engine | `.ai/change-workflow.md` | prompt and current configuration/data facts before any external action |
| Change deployment configuration | `.ai/configuration.md` | one environment-reading file, typed values, composition, and parser tests |
| Change a non-simple route or request input | installed `vendor/phpthis/framework/docs/request-handling.md` | route manifest and only the application guides for concerns actually entered |
| Change application data or SQL | `.ai/data.md` | direct call site, authority, exact-engine, adversarial, and scale evidence |
| Change authentication, tenant, or authorization policy | `.ai/request-policy.md` | action-specific composition, protected work, and denial tests |
| Change file transfer | `.ai/file-transfers.md` | front controller, storage operation, response path, and transfer tests |
| Change an external side effect | `.ai/integrations.md` | named client boundary, failure policy, and contract tests |
| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |
| Change correlation or terminal summaries | `.ai/observability.md` | coordinator, sink, finite database sources, and summary tests |
| Change cookie-backed session state | installed `vendor/phpthis/framework/docs/sessions.md` | architecture, operations, and testing facts only when adopted |
| Change HTTP or server-side caching | installed `vendor/phpthis/framework/docs/caching.md` | response path or data, integration, operations, and testing facts |
| Change durable deferred work | `.ai/jobs.md` | configuration, producer, worker, operations, and lifecycle tests |
| Change an application command or scheduled pass | `.ai/cli.md` | console, typed arguments, operations, and real-console tests |
| Change database migrations | `.ai/migrations.md` | configuration, authority, manifest, ledger, operations, and exact-engine tests |
| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, configuration, operation, and socket tests |
| Change the development Workbench | `.ai/workbench.md` | approved package, checked bootstrap, explicit workspace, and retained tests |
| Change CRUD-shaped operations | installed `vendor/phpthis/framework/docs/crud.md` | implemented routes plus architecture, data, and testing facts actually affected |
| Add or change tests | `.ai/testing.md` | nearest behavior test and complete project gate |

Accepted application decisions live in `docs/decisions/`. Read one only when the task reviews or changes its underlying decision.
