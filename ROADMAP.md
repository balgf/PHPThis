# Roadmap

## Current priorities

PHPThis's current boundary is Consumer Contract 18, Strict Profile 4 and PHT001–PHT008. Alpha 8 is the latest recorded source tag; [RELEASING.md](RELEASING.md) owns release state, exact approval rules and the route to publication receipts. Changes after that tag are unreleased.

- Keep the retained consumer useful through real feature changes and upgrades. [The lifecycle report](https://github.com/balgf/PHPThis/tree/main/tools/consumer-lifecycle) owns its public Alpha 8 repetition, failures, repairs and optional RAG exercise. Its next maintenance trigger is the next matching public release.
- Review maintainability through behavior, documentation ownership and executable evidence. [ADR 063](docs/decisions/063-maintainability-review-over-size-proxies.md) makes file and line counts informational. [ADR 064](docs/decisions/064-one-entrypoint-per-audience.md) makes `AGENTS.md` the universal entrypoint and routes detail by task.
- Use consumer evidence to prioritize changes. Outside a demonstrated release blocker, correctness defect or security defect in the accepted surface, runtime/checker expansion needs a reproducible consumer problem.
- Continue the bounded work tracked in [open issues](https://github.com/balgf/PHPThis/issues): evaluate dynamic SQL guidance, security-sensitive ownership and comparative evidence within each issue's accepted scope. A backlog item authorizes neither a new framework mechanism nor a paid run.

## Evidence and open claims

The framework gate checks runtime behavior, profile diagnostics, source/distribution contracts, installed consumers and query scaling. [Evaluation](docs/evaluation.md) maps each claim to its evidence and limits. The recorded #69 campaigns do not establish comparative success rates. #70 has individual accepted code-change and explanation observations, not general model reliability or a measured advantage over ordinary PHP. The fixed comparison protocol remains available for a separately approved campaign.

The checked CRUD reference implements bounded List, Create and item Get at `/users/{user_id:positive-int}`. Update and Delete are absent; [the canonical tree](docs/crud.md#reference-placement) is the source inventory. Item Get has no application authorization or tenant policy. The retained WebSocket, Redis, SQLite-job and RAG recipes prove only their recorded application scope.

## Deferred by design

Framework-owned authentication/authorization, credential lifecycle, CSRF policy, custom session stores, WebSockets/event loops, generic middleware, cache/lock/queue runtimes, templating, generic validation, migration runtimes and new runtime dependencies need a concrete problem, visible execution path, cost model and accepted decision. Current application-owned recipes do not imply these capabilities.

[The knowledge map](docs/knowledge-map.md) routes current boundaries for request policy, typed input, explicit handler decorators, sessions, file transfers, jobs, commands, migrations, coordination, WebSockets and the separate development Workbench. Select an exact supported profile and prove its application behavior; do not infer another engine, backend, topology, production deployment or delivery guarantee.

## Completed work and historical scope

[Decision records](docs/decisions/README.md) and versioned notes in `docs/releases/` preserve the accepted Alpha 1–8 scope. [Issue #81](https://github.com/balgf/PHPThis/issues/81) retains Alpha 8 package/public-install and subsequent operation evidence; [#53](https://github.com/balgf/PHPThis/issues/53) retains Alpha 7, and [#37](https://github.com/balgf/PHPThis/issues/37) retains Alpha 6.

The [phase-by-phase roadmap at the pre-consolidation revision](https://github.com/balgf/PHPThis/blob/884e7154984d774f9da8f595c820c2b978c8118a/ROADMAP.md) preserves the original completion dates, source-preparation checkpoints, deferred details and numerical limits in force at those dates. Those historical limits and `PENDING` records are not current policy or publication state. Current priorities live above; evidence remains with the decision, release or evaluation that produced it.
