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

A concern-specific skeleton or template change starts at its concern row. Add `.ai/application-context.md` only for context inventory, routing, shared authority, packaging, guard, or installed-consumer coherence.

## Simple endpoint route

Use the exact simple-endpoint definition and four task-specific files in the already-read `VISION.md`. Report fixed universal-entrypoint word and byte cost separately; neither measure affects validity or permits skipping universal safety.

An ordinary route change starts with `.ai/routing.md`; read a decision record only when reviewing or changing the decision it records.

## Task routes

| Task | Start with | Inspect next; add another guide only if entered |
| --- | --- | --- |
| Add or change a qualifying simple endpoint | `.ai/routing.md` | existing named route-area manifest, dependency-free handler, and nearest behavior test; root route composition remains unchanged |
| Explain current PHPThis behavior | `docs/knowledge-map.md` | relevant contract, source, and nearest tests |
| Change route grammar, matching, or route composition | `.ai/routing.md` | route manifest, `src/Routing/`, request delivery, and route tests |
| Change request, response, or generic response-cookie behavior | `.ai/http.md` | `src/Http/`, `src/Application.php`, and nearest behavior tests |
| Define or change a structured JSON resource success representation, including nested child data | `.ai/http.md` | public HTTP guides, exact response path, frontend decoder, and behavior evidence; add database, cache, integration, or testing guides only when entered |
| Change frontend integration or application-owned HTML rendering | `.ai/http.md` | `docs/frontend-integration.md`, exact HTTP paths, and behavior evidence; add other concern guides only when entered |
| Change email guidance or application email context | `.ai/email.md` | `docs/email.md`, integration record, selected transport, and evidence |
| Change PHP runtime ingestion or the outer boundary | `.ai/request-boundary.md` | reader, boundary, front controller, and boundary tests |
| Parse or change external values | `.ai/types.md` | operation-specific boundary value, failure map, and adversarial tests |
| Change date, time, timezone, duration, or clock behavior | `.ai/types.md` | `docs/date-time.md`, the exact operation source and representation, and boundary or clock tests |
| Add or change database behavior or PDO transport | `.ai/database.md` | direct `Connection` call, exact SQL, authority facts, and scale tests |
| Change configuration guidance, application configuration context, or value-free Composer aliases | `.ai/configuration.md` | public guide, application record, composition sites, and evidence; add static analysis only for checker work |
| Change local environment launcher guidance or its checked reference | `.ai/configuration.md` | public launcher guide and checked reference; add CLI, operations, and testing only when entered |
| Change startup, liveness, dependency health, or readiness semantics | `.ai/operations.md` | composition root, exact probe claim, synchronous destinations, and evidence |
| Change application-wide release ordering, recovery sequencing, or generic operational guidance | `.ai/operations.md` | adopted concern records and executable evidence; framework publication uses `RELEASING.md` |
| Change authentication, stateless Bearer/JWT/PAT/external-IdP policy, tenant resolution, or authorization | `.ai/request-policy.md` | `docs/stateless-authentication.md`, action-specific policy path, protected work, and denial tests |
| Change cookie-backed session state | `.ai/session.md` | typed service, lifecycle composition, transport, and policy tests |
| Change HTTP or server-side caching | `.ai/cache.md` | response path or typed cache service, backend policy, and cache tests |
| Change application-owned atomic-lock, mutex, mutual-exclusion, lease, critical-section, or coordination guidance | `.ai/operations.md` | `docs/coordination.md`, operations record, topology, and real concurrency and recovery evidence |
| Change durable deferred work | `.ai/jobs.md` | backend-neutral contract or selected checked profile and its complete real-service evidence |
| Change an application command or scheduled pass | `.ai/cli.md` | console composition, one-pass operation, and real-console tests |
| Change database migrations | `.ai/migrations.md` | command, configuration, authority, manifest, ledger, coordination, and exact-engine tests |
| Change uploads or file responses, or adopt/review Amazon S3 | `.ai/file-transfers.md` | common policy, one selected profile, and its complete evidence |
| Change correlation or terminal summaries, or adopt optional log levels and destinations | `.ai/observability.md` | coordinator, sink, finite sources, optional destination profile, and evidence |
| Change application-owned WebSockets | `.ai/websockets.md` | selected runtime, separate process, typed operation, and real socket tests |
| Change the optional development Workbench | `.ai/workbench.md` | separate package, checked bootstrap, explicit workspace, and retained tests |
| Change CRUD-shaped reference structure | `.ai/crud.md` | canonical current tree, implemented operations, routes, and behavior tests |
| Change failure mapping | `.ai/errors.md` | exact failure, registry wiring, response, and boundary tests |
| Change PHPStan or static-analysis configuration or implementation | `.ai/static-analysis.md` | affected extension, configuration, fixtures, and installed-consumer proof |
| Change the current Consumer Contract, knowledge router, context ownership, shared template/skeleton authority, or package context inventory | `.ai/application-context.md` | current contract and routes, inventories, package allowlist, guards, and installed-consumer proof |
| Add or change a Strict Profile rule | `.ai/strict-profile.md` | then inspect its enforcement owner, positive and negative fixtures, catalogue, and installed-consumer proof |
| Change maintainer tests or evidence organization | `.ai/testing.md` | applicable concern-owned test file, behavior names, and complete gate |
| Change the maintainer-only agent evaluation kit or controller | `.ai/testing.md` | `docs/evaluation.md`, explicit kit/controller files and focused self-tests; ADR 048 only when its isolation decision is entered |
| Review the consumer capability profile | `.ai/consumer-profile.md` | checked-in application proof and affected current guides |
| Prepare or publish a release | `RELEASING.md` | proposal or approved scope as applicable, explicit authority, exact candidate commits only after approval, CI, packages, and public-install proof |

Durable framework knowledge and rationale live in `docs/`. Current mutable authoring contracts live in the routed operational guides.
