# PHPThis application instructions

AI authors and explains this application; the human supplies intent and accepts consequential product, architecture, security, data, migration, deployment and external-side-effect decisions. Ground claims in the installed framework version, current application facts, source and tests. Cite evidence and distinguish framework behavior, application policy and proposals.

## Authority and scope

Installed Consumer Contract v18 (`vendor/phpthis/framework/docs/consumer-contract.md`) and Strict Profile v4 define the minimum requirements. Application rules may strengthen them. Preserve the framework contract when instructions conflict and report the conflict. Never edit installed dependencies to customize behavior or silence findings.

Resolve missing facts and authority before acting; do not repeat choices already made. Keep unrequested I/O, provisioning and data mutation outside the task. Never place credentials, customer data, production payloads or other secrets in context, source, fixtures, logs or reports. A not-applicable record describes current behavior; adoption requires the selected concern's policy and evidence.

## Working route

Read this file, choose the current guide below, then inspect the relevant source and nearest tests. For a first contribution or work involving product vocabulary, invariants or ownership, read `.ai/project.md` and the relevant architecture/context records before implementation. Before feature work, replace generic starter facts or template placeholders with verified facts or explicit not-applicable records. Preserve the recorded dependency direction and domain terms. Every additional concern keeps its guide, even when this exceeds a locality example.

Framework paths below are relative to installed `vendor/phpthis/framework/`. Use the actual Composer vendor directory. If dependencies are missing, follow `.ai/operations.md` to install them before making framework claims. Read ADRs when reviewing their decision and upgrade history when upgrading. For explanation-only requests, inspect and cite evidence without editing files.

| Task | First current guide |
| --- | --- |
| Product facts or a first contribution | `.ai/project.md`, then the selected concern |
| Application structure or dependencies | `.ai/architecture.md` |
| A framework question without a known concern | installed `docs/knowledge-map.md` |
| New project, contract adoption, upgrade or a contract conflict | installed `docs/consumer-contract.md` |
| Endpoint, route, request or response | installed `docs/request-handling.md` |
| External input or domain values | installed `docs/type-safety.md` |
| JSON representations or frontend integration | installed `docs/frontend-integration.md` |
| Database setup, process configuration or a local launcher | `.ai/configuration.md` |
| SQL or persistent data | `.ai/data.md` |
| Authentication, tenants or authorization | `.ai/request-policy.md` |
| Uploads or file delivery | `.ai/file-transfers.md` |
| External services, email or RAG | `.ai/integrations.md` |
| Deployment, probes, coordination or recovery | `.ai/operations.md` |
| Correlation, summaries or logging | `.ai/observability.md` |
| Sessions; caching | installed `docs/sessions.md`; installed `docs/caching.md` respectively |
| Jobs; commands; migrations | `.ai/jobs.md`; `.ai/cli.md`; `.ai/migrations.md` respectively |
| WebSockets; Workbench | `.ai/websockets.md`; `.ai/workbench.md` respectively |
| Dates or clocks; failure mapping | installed `docs/date-time.md`; installed `docs/errors.md` respectively |
| CRUD reference organization | installed `docs/crud.md` |
| Diagnostics, new PHP entrypoints or source discovery | installed `docs/static-analysis.md` |
| Test infrastructure or evidence organization | `.ai/testing.md` |

Select the matching guide within a grouped row. Application-owned concern records route to their installed framework contract before adoption or changes. Existing explicit HTTP composition, generic-first failure handling, terminal summaries, emission rules and effective web-SAPI safety remain mandatory; follow the HTTP, errors and operations guides when entering those boundaries.

## Universal implementation rules

- Use PHP 8.4.x, `declare(strict_types=1);`, final named classes and interfaces for extension. Construct dependencies visibly; keep handlers on `RequestHandler::handle(Request): Response` and routes in finite explicit lists.
- Validate and narrow external `mixed` before use; casts are not validation and unvalidated arrays cannot cross a named boundary. Parse it once into a bounded final readonly operation-specific value with a private constructor; identifiers retain their recorded constructor convention.
- Keep I/O, failures and bounds explicit. Use direct `Connection` calls with finite non-blank compile-time-constant engine-specific SQL and a distinct exact case-sensitive named binding per placeholder occurrence. Reject unknown structural selectors before I/O. Never call the database inside a loop or recursive traversal.
- Read process inputs in one file through exact literal `\getenv` calls and inject validated process-specific values. Composer aliases remain value-free for adopted inputs.
- Preserve PHT001–PHT008 and maximum-level analysis. Do not add baselines, ignores, consumer PHPStan overrides or comment exemptions.
- Do not add discovery, reflection wiring or hydration, containers, service location, ORMs, Active Record, lazy loading, query builders, generic repositories/services, generated SQL, positional parameters, data interpolation, SQL sanitizers, `SELECT *`, unbounded reads, mass assignment, string class resolution, dynamic properties, macros, facades, hidden globals, proxies or magic methods other than `__construct`.
- Keep one canonical execution pattern; no hidden I/O, undocumented side effects, implicit retries, silent exception conversion, fallback credentials or success after failure. Declaration-local native and selected development-tool attributes do not permit hidden composition.

## Complete a change

State the observable behavior, entrypoint, data and side effects. Reuse the current pattern and add meaningful success/failure tests, including applicable validation, authorization and resource bounds. For database work, compare materially different fixture sizes and prove constant statement count. Update the affected application context.

Run focused checks while editing, then `composer check`. Its installed profile stage and application-owned behavior tests must both pass. Report the evidence and remaining limits; static analysis or a no-op test command cannot replace behavior tests.
