# AI context index

This directory routes framework-maintainer work to current operational context. Answer from this checkout, not model memory, and do not load every guide by default.

## Universal entrypoints

After `AGENTS.md`, always read:

1. `VISION.md`
2. `.ai/rules.md`
3. `.ai/change-workflow.md`
4. `.ai/strict-profile.md`

Then start with exactly one current operational guide from the table. Add another guide only when the task actually enters its concern.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

## Simple endpoint route

Use the exact simple-endpoint definition and four-file locality metric in the already-read `VISION.md`. A qualifying endpoint fits an existing named route-area manifest whose dependency-free handler is constructed inline, so root route composition remains unchanged.

An ordinary route change starts with `.ai/routing.md`; read a decision record only when reviewing or changing the decision it records.

## Task routes

| Task | Start with | Inspect next; add another guide only if entered |
| --- | --- | --- |
| Add or change a qualifying simple endpoint | `.ai/routing.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |
| Explain current PHPThis behavior | `docs/knowledge-map.md` | relevant contract, source, and nearest tests |
| Change route grammar, matching, or route composition | `.ai/routing.md` | route manifest, `src/Routing/`, request delivery, and route tests |
| Change request or response behavior | `.ai/http.md` | `src/Http/`, `src/Application.php`, and nearest behavior tests |
| Change frontend integration or application-owned HTML rendering | `.ai/http.md` | `docs/frontend-integration.md`, exact HTTP paths, and behavior evidence; add other concern guides only when entered |
| Change email guidance or application email context | `.ai/application-context.md` | `docs/email.md`, task routes, integration context, package inventory, focused guardrails, and installed-consumer evidence |
| Change PHP runtime ingestion or the outer boundary | `.ai/request-boundary.md` | reader, boundary, front controller, and boundary tests |
| Parse or change external values | `.ai/types.md` | operation-specific boundary value, failure map, and adversarial tests |
| Change date, time, timezone, duration, or clock behavior | `.ai/types.md` | `docs/date-time.md`, the exact operation source and representation, and boundary or clock tests |
| Add or change database behavior or PDO transport | `.ai/database.md` | direct `Connection` call, exact SQL, authority facts, and scale tests |
| Change configuration, consumer context, checker, skeleton, or template | `.ai/application-context.md` | affected contract, template, checker, and installed-consumer evidence |
| Change startup, liveness, dependency health, or readiness semantics | `.ai/application-context.md` | bootstrap, front controller, exact probe claim, and behavior tests; add `.ai/database.md` only when a database dependency is entered |
| Change authentication, tenant resolution, or authorization | `.ai/request-policy.md` | action-specific policy path, protected work, and denial tests |
| Change cookie-backed session state | `.ai/session.md` | typed service, lifecycle composition, transport, and policy tests |
| Change HTTP or server-side caching | `.ai/cache.md` | response path or typed cache service, backend policy, and cache tests |
| Change durable deferred work | `.ai/jobs.md` | producer transaction, worker path, and lifecycle tests |
| Change an application command or scheduled pass | `.ai/cli.md` | console composition, one-pass operation, and real-console tests |
| Change database migrations | `.ai/migrations.md` | command, configuration, authority, manifest, ledger, coordination, and exact-engine tests |
| Change uploads or local-file responses | `.ai/file-transfers.md` | boundary, storage operation, emitter path, and transfer tests |
| Change correlation or terminal summaries | `.ai/observability.md` | front-controller coordinator, sink, finite sources, and summary tests |
| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, typed operation, and real socket tests |
| Change the optional development Workbench | `.ai/workbench.md` | separate package, checked bootstrap, explicit workspace, and retained tests |
| Change CRUD-shaped reference structure | `.ai/crud.md` | canonical current tree, implemented operations, routes, and behavior tests |
| Change failure mapping | `.ai/errors.md` | exact failure, registry wiring, response, and boundary tests |
| Change PHPStan or static-analysis configuration or implementation | `.ai/static-analysis.md` | affected extension, configuration, fixtures, and installed-consumer proof |
| Add or change a Strict Profile rule | `.ai/strict-profile.md` | then inspect its enforcement owner, positive and negative fixtures, catalogue, and installed-consumer proof |
| Change maintainer tests or evidence organization | `.ai/testing.md` | applicable concern-owned test file, behavior names, and complete gate |
| Review the consumer capability profile | `.ai/consumer-profile.md` | checked-in application proof and affected current guides |
| Prepare or publish a release | `RELEASING.md` | approved scope, exact candidate commits, CI, packages, and public-install proof |

Durable framework knowledge and rationale live in `docs/`. Current mutable authoring contracts live in the routed operational guides.
