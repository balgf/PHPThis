# Application AI context index

This directory is owned by `{{PROJECT_NAME}}`. It grounds the AI that explains and authors this project; it is not a framework manual. It supplies project facts that the PHPThis consumer contract cannot know. Keep it committed, current, concise, and free of secrets.

Consumer Contract v8 and Strict Profile v2 remain mandatory. Application guidance may strengthen them but may not weaken them.

Always read:

1. `.ai/rules.md`
2. `.ai/change-workflow.md`
3. `.ai/project.md`

Then read only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Explain framework or application behavior | installed PHPThis knowledge map, matching application guide | installed framework source, application execution path, and tests |
| Change application structure or dependencies | `.ai/architecture.md` | composition root and affected source boundary named there |
| Add or change a route | installed `vendor/phpthis/framework/docs/request-handling.md`, `.ai/architecture.md`, `.ai/testing.md` | route manifest, narrowest identifier declaration, matching `PathParameters` accessor, application-owned identifier wrapper, relevant route area, handler, and behavior tests |
| Add or change inbound operation data | installed `vendor/phpthis/framework/docs/type-safety.md`, `.ai/architecture.md`, `.ai/testing.md` | raw representation and bounds, operation-specific parser factory, final readonly request or command, downstream typed behavior or justified seam, request-policy order, public error mapping, and adversarial tests |
| Add or change a file upload or download | installed `vendor/phpthis/framework/docs/file-transfers/README.md`, `.ai/file-transfers.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | front controller, composition root, exact route and handler, concrete file path, response emission, failure mapping, and transfer tests |
| Protect a route or change identity, tenant, or authorization policy | installed `vendor/phpthis/framework/docs/request-policy.md`, `.ai/request-policy.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/operations.md`, `.ai/testing.md` | composition root, action-specific policy adapter, concrete principal and tenant values, policy and protected connections, exact denial registrations, and order, denial, redaction, and replacement tests |
| Add or change cookie-backed session state | installed `vendor/phpthis/framework/docs/sessions.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | composition root, typed key ownership, isolated save path, mandatory transport evidence, and each applicable security-policy test |
| Set or change HTTP response cache policy | installed `vendor/phpthis/framework/docs/caching.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | response-producing path, explicit `no-store`, `private`, or `public` policy, freshness or revalidation, validators, `Vary`, intermediary topology, and behavior tests |
| Add or change server-side cached data | installed `vendor/phpthis/framework/docs/caching.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, `.ai/testing.md` | composition root, narrowly named typed cache service, authoritative data path, backend boundary, key and tenant ownership, bounds, invalidation, observability, and cold, warm, failure, and concurrency tests |
| Add or change durable deferred work | installed `vendor/phpthis/framework/docs/jobs.md`, `.ai/jobs.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, `.ai/testing.md` | producer transaction, complete job SQL, versioned envelope parser, finite dispatch, idempotent effect, lease and retry policy, one-shot worker composition, and crash plus redaction tests |
| Add or change an operational application command or scheduled pass | installed `vendor/phpthis/framework/docs/cli.md`, `.ai/cli.md`, `.ai/operations.md`, `.ai/testing.md`, and `.ai/jobs.md` when invoking durable work | sole application console, finite command map, typed argument boundary, exact exit and stream contract, explicit clock and cadence, one-pass operation, same-host overlap lock, supervisor, composition root, and real-console tests |
| Add or change database migrations | installed `vendor/phpthis/framework/docs/migrations.md`, `.ai/migrations.md`, `.ai/data.md`, `.ai/operations.md`, `.ai/testing.md`, and `.ai/cli.md` | sole migration command, separate authority, finite ordered unrolled manifest, exact engine SQL and checksums, bounded ledger, per-migration transactions, same-host lock, recovery, and real-console tests |
| Add or change CRUD-shaped resource operations | installed `vendor/phpthis/framework/docs/crud.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/testing.md` | explicit resource routes, operation area, data path, and behavior tests |
| Read or write application data or map a structural SQL selector | `.ai/data.md`, `.ai/testing.md` | schema source, direct `Connection` call, finite code-owned SQL mapping, authority record, and adversarial and scale tests |
| Change an external service or side effect | `.ai/integrations.md` | client boundary, contract fixture, failure tests |
| Change runtime, logging, or deployment behavior | `.ai/operations.md` | composition root, deployment configuration, operational tests |
| Change request correlation or terminal summaries | installed `vendor/phpthis/framework/docs/observability/README.md`, `.ai/observability.md`, `.ai/architecture.md`, `.ai/operations.md`, `.ai/testing.md` | front controller, application-owned coordinator and sink, finite database sources, response propagation, redaction, budget, trace, and throwing-sink tests |
| Add or change tests | `.ai/testing.md` | nearest behavior tests and complete project check |

For every resource path identifier, choose the narrowest fixed type among `positive-int`, `uuid`, `ulid`, and genuinely opaque `token`; use the matching `PathParameters` accessor, preserve the value unchanged, immediately wrap it in an application-owned route-specific identifier, and apply narrower domain rules before database work. Routing never normalizes, binds, looks up, or falls back between types. Invalid syntax must remain a `404` with zero handler and database work; a canonical valid path with the wrong method remains a `405`.

The CRUD reference profile is optional application structure. Record its adoption or one coherent alternate placement and naming rule; neither may weaken the accepted installed Consumer Contract or Strict Profile v2.

Accepted architectural decisions and durable rationale live in `docs/decisions/`. AI may draft and update a decision record, but acceptance requires explicit approval from an accountable human. Add a narrowly named area guide when a recurring task needs context that does not fit this map; do not turn this index into a complete project description.
