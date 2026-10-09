# PHPThis maintainer instructions

PHPThis uses AI-first authoring with human accountability. Ground answers and changes in this checkout's current guides, source, and tests. Cite that evidence and distinguish implemented behavior, application policy, and proposals.

## Authority and scope

The human supplies intent and accepts consequential product, architecture, security, data, release, and operational decisions. Resolve missing authority before implementation; reuse decisions already made. Keep unrequested side effects outside the task. Never place credentials, customer data, production payloads, or other secrets in source, context, fixtures, logs, or reports.

The current Consumer Contract and Strict Profile remain binding. Preserve them when instructions conflict and report the conflict. An application-owned recipe proves only its recorded scope; it does not imply a framework API or another backend's behavior.

## Working route

Read this file, select the current guide below, then inspect the relevant source and nearest tests. Add another guide when its concern is entered. Read ADRs for decision review and history, rather than ordinary implementation. For an explanation, inspect and cite evidence without editing files.

| Task | First guide |
| --- | --- |
| Purpose, design or locality | `VISION.md` |
| A framework question without a known concern | `docs/knowledge-map.md` |
| Routes or a simple endpoint | `.ai/routing.md` |
| HTTP values, JSON representations or frontend integration | `.ai/http.md` |
| Runtime request ingestion or the outer boundary | `.ai/request-boundary.md` |
| External input, domain values, dates or clocks | `.ai/types.md` |
| Configuration, database setup or a local launcher | `.ai/configuration.md` |
| SQL, connections or database transport | `.ai/database.md` |
| Authentication, tenant scope or authorization | `.ai/request-policy.md` |
| Sessions; caching | `.ai/session.md`; `.ai/cache.md` respectively |
| Uploads or file delivery | `.ai/file-transfers.md` |
| Jobs; commands; migrations | `.ai/jobs.md`; `.ai/cli.md`; `.ai/migrations.md` respectively |
| Operations, probes or coordination | `.ai/operations.md` |
| Correlation, summaries or logging | `.ai/observability.md` |
| Email; RAG; WebSockets; Workbench | `.ai/email.md`; `.ai/rag.md`; `.ai/websockets.md`; `.ai/workbench.md` respectively |
| CRUD reference structure; failure mapping | `.ai/crud.md`; `.ai/errors.md` respectively |
| Diagnostics or static-analysis implementation | `.ai/static-analysis.md` |
| Change a Strict Profile rule | `.ai/strict-profile.md` |
| Tests or evaluation tooling | `.ai/testing.md` |
| Contracts, context ownership, templates or distribution | `.ai/application-context.md` |
| Consumer capability assessment | `.ai/consumer-profile.md` |
| Release preparation or publication | `RELEASING.md` |

Select the matching guide within a grouped row. A cross-concern task follows every applicable guide. Database setup starts with configuration scope before connection or provisioning. New behavior requires its current owner and evidence even when that adds reading. Report universal and task-specific context separately; never skip necessary context to claim four files.

## Universal implementation rules

- Use PHP 8.4, strict types, final named classes, interfaces for extension, immutable boundary values, and visible manual composition. Keep routes finite and handlers on `RequestHandler::handle(Request): Response`.
- Validate and narrow external `mixed` before use; casts are not validation and unvalidated arrays cannot cross a named boundary. Parse it once into a bounded final readonly operation-specific value with a private constructor; identifiers keep their recorded constructor convention.
- Make I/O, failures and resource bounds explicit. Execute SQL through direct `Connection` calls with finite, non-blank compile-time constants and a distinct exact case-sensitive named binding per placeholder occurrence. Reject unknown structural choices before I/O. Never call the database in a loop or recursive traversal.
- Read application environment inputs in one file through exact literal `\getenv` calls, validate typed process-specific values and inject them visibly. Follow the configuration guide before adopting or changing those inputs.
- Keep one canonical execution pattern and stable terms. Generic mechanisms must not hide optional application concerns. Native declaration-local attributes and selected development-tool attributes are allowed; they do not authorize discovery, reflection wiring or hydration.
- Do not add containers, service location, ORMs, Active Record, lazy loading, query builders, generic repositories/services, generated SQL, positional parameters, data interpolation, SQL sanitizers, `SELECT *`, or unbounded reads.
- Do not hide behavior behind string class resolution, dynamic properties, macros, facades, globals, proxies, mass assignment, implicit retries, silent exception conversion, success after failure, or magic methods other than `__construct`.
- Keep maximum-level analysis and PHT001–PHT008 effective. Fix findings at their cause; never introduce baselines, ignores, exclusions or comment exemptions.

## Complete a change

State the observable change and its side effects. Reuse the current pattern, add meaningful success/failure and applicable security/resource tests, then implement the smallest direct change. Name the documentation owner and update it, or explain why no public update applies. Runtime changes also explain need, API/dependency impact, execution path and complexity shifted to tools or applications.

Run `composer check` before reporting completion. Focused tests support iteration; the complete gate remains required. Report proven behavior, unresolved decisions and material limits. The accountable maintainer reviews the evidence before merge. File and line counts are informational.
