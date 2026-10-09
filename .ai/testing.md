# Testing contract

Use `AGENTS.md` for the change workflow and the selected concern guide for required behavior. Add meaningful success, failure, validation, authorization and resource evidence as applicable. Run focused checks while editing, then the complete `composer check` gate before completion. Static analysis, manual inspection and a no-op command do not replace behavior tests.

## Select and run evidence

| Work | Focused command / owner |
| --- | --- |
| Runtime and application reference behavior | `composer test`; select `-- --filter 'exact behavior name'` or `-- --group routing` (or the applicable group) |
| Documentation routes, inventories and source structure | `composer guard`; `composer test -- --filter MaintainabilityGuardrailsTest` |
| Maintainer analysis and profile rules | `composer analyse`; `composer test:profile` |
| Installed package, skeleton and consumer controls | `composer test:consumer` |
| Database transport and query growth | `composer test:database-drivers`; `composer test:query-scaling` |
| Report-only duplication | `composer test:duplication` |
| Bounded child-process support | `composer test:process` |
| Evaluation schemas and synthetic controller | `composer test:agent-evaluation`; `composer test:agent-evaluation-controller` |

The framework uses PHPUnit 13 as a development runner. `tests/run.php` explicitly loads concern files and registers exactly 188 named behaviors; `tests/behavior-names.txt` fixes their unique order. `tests/FrameworkBehaviorTest.php` exposes selectable data sets and groups. Keep the entrypoint deterministic and support narrow. JUnit output belongs under `.phpunit.cache/`. `composer test:coverage` is report-only and needs PCOV or Xdebug; no coverage percentage determines validity.

The installed-consumer entrypoint `tools/test-consumer-project.php` owns the explicit order of its 54 proof calls, twelve declaration-only modules, outer workspace lifecycle, completion sentinels, exit/output contracts and cleanup. Source guards verify that composition. Do not introduce discovery, a registry, generated loading, a second command path or hidden process ownership to organize tests. `tools/process-support.php` owns captured native child execution. Preserve time/output limits, original-process cleanup and failure precedence.

## Behavior ownership

| Concern | Current requirements | Executable owner |
| --- | --- | --- |
| HTTP ingestion, framing, cookies and emission | `.ai/http.md`, `.ai/request-boundary.md` | `tests/http-boundary.php`, `tests/response-framing.php`, `tests/response-emitter.php` |
| Outer failure disclosure and safe SAPI settings | `.ai/errors.md`, `.ai/configuration.md` | `tests/outer-http-failure.php`, installed HTTP controls |
| Route grammar and explicit decorators | `.ai/routing.md` | `tests/routing.php`, `tests/handler-decorator.php` |
| Typed input and CRUD operation boundaries | `.ai/types.md`, `.ai/crud.md` | `tests/input-projection.php`, `tests/crud.php` |
| Authorization and tenant order | `.ai/request-policy.md` | `tests/request-policy.php`, `tests/consumer-profile.php` |
| Database transactions, transport and scaling | `.ai/database.md` | `tests/database-boundary.php`, `tools/test-database-drivers.php`, `tools/test-query-scaling.php` |
| Session lifecycle | `.ai/session.md` | `tests/session-lifecycle.php` |
| Cache and coordination | `.ai/cache.md`, `.ai/operations.md` | `tests/cache.php`, `tests/redis-coordination.php` |
| Jobs, CLI and migrations | `.ai/jobs.md`, `.ai/cli.md`, `.ai/migrations.md` | `tests/jobs.php`, `tests/cli.php`, `tests/migrations.php` |
| File transfers | `.ai/file-transfers.md` | `tests/upload-request-boundary.php`, `tests/document-files.php`, installed local/S3 reference controls |
| Correlation and logging | `.ai/observability.md` | `tests/observability.php`, installed destination-record controls |
| Application configuration and launcher | `.ai/configuration.md` | installed configuration and local-launcher controls |
| Context ownership and distribution | `.ai/application-context.md` | `tools/guidance-support.php`, `tests/MaintainabilityGuardrailsTest.php`, installed consumer proof |
| Email, WebSockets, Workbench and RAG | `.ai/email.md`, `.ai/websockets.md`, `.ai/workbench.md`, `.ai/rag.md` | Exact retained application evidence named by each guide |

Read every entered concern; a row lists owners, not permission to omit their assertions. Current public contracts govern consumer evidence. Existing executable cases remain regression requirements even when their detailed transcripts are removed from this index.

Use real value objects and an in-memory database only when it matches the engine feature under test. Do not mock fluent APIs or framework internals, or introduce an ORM, query builder, repository, paginator, SQL generator, transaction callback, dialect/schema abstraction or permission helper for test convenience. Compare materially different fixture sizes; bound statement and collection growth, failure side effects and disclosure. For nested resources, also compare cache/external-call counts and prove mapping/encoding adds no I/O.

Keep deliberately invalid source under `.php.fixture`, outside accepted PHP/autoload paths. Pass the exact source through the real profile checker, assert its diagnostic, then execute it only in an isolated subprocess when runtime comparison is needed. Never exempt an invalid `.php` file. The N+1 control must demonstrate growing count and budget rejection; it does not make PHT003 detect indirect I/O.

Database-specific claims require the exact engine/version and real authority evidence. `composer test:database-drivers` defaults to SQLite; dedicated CI requires SQLite, MySQL and PostgreSQL versions from `docs/database.md#pdo-transport-certification-matrix`. Keep the pre-DDL version mismatch/cleanup/recovery control. An unconfigured driver or local version is not matrix certification. Budgets, constant SQL and bound values do not prove grants, plans, isolation or cross-database atomicity.

Real-process, web-SAPI, service and concurrency requirements stay with their concern. In particular, preserve redacted failure/output controls, effective SAPI settings, zero protected work on denial, session cleanup precedence, local-file resource bounds, Redis policy separation and real engine/service evidence. A synthetic non-adopter marked `REFERENCE_ONLY` proves verifier structure only. No test may present that as application adoption, remote-service behavior, durability, production readiness or client receipt.

## Documentation review and guard coverage

`tools/guidance-support.php` checks explicit references and readable destinations across maintainer, skeleton and installed template context. `MaintainabilityGuardrailsTest` runs real guards on disposable copies: equivalent wording, consolidation and harmless size changes pass; missing/empty guides, broken routes or anchors, incorrect CRUD trees, package omissions and invalid strict types fail. Full `composer test:consumer` separately proves actual installation and the complete inventory. A clean committed tree must report `git-export-parity=verified`; `skipped-dirty` is useful development evidence only.

The #86 routing assertion audit remains applicable after #84's consolidation:

| Requirement | Evidence retained |
| --- | --- |
| Universal authority, safety, task selection, complete simple-endpoint scope and separate context cost | Readable entrypoints and concrete destinations plus maintainer semantic review; no substring check proves meaning. |
| Installed contract/profile identities, upgrade history and package ownership | Identity metadata, versioned upgrade anchors, required paths and full archive/export comparison. |
| Final classes, no database loops and typed boundaries | Positive/negative diagnostics and actual boundary behavior; parser/identifier distinction remains in the current guide. |
| Historical decisions, calibrations and releases | Accepted metadata, frozen artifacts and explicit history links; no current sentence must copy a historical finding. |
| CRUD reference completeness | Parse one canonical source tree and compare all current files; preserve starter composition and installed runtime tests. |

For #84, current routes replace duplicated prose assertions only on the consolidated surfaces. Concern-source, diagnostic, security, runtime and frozen-evaluation controls remain in place. Do not pin entire paragraphs, table rows or unused headings just to enforce ownership. New behavior still needs its documentation owner and automated evidence; a broken guide cannot be repaired by an unrelated empty Markdown file.

## Evaluation infrastructure and history

`docs/evaluation.md` maps current claims and limitations. `tools/agent-evaluation/README.md` owns tasks, schemas and record validity; `tools/agent-evaluation-controller/README.md` owns execution and diagnostics. Ordinary checks use synthetic controls with no paid request, credential, Docker requirement or AI-authored candidate code. Separate deterministic OCI tests establish only their tested isolation/transport properties. Real trials require exact approved settings, resources, spending and operations.

Preserve explicit kit/controller module order, the sole process owner, source/effective-prompt and workspace-policy binding, protected candidate/dependency hashes, admissibility, score/run/ledger consistency, bounded redacted diagnostics, pending reservations after ambiguous usage, post-freeze scorer separation and verified cleanup. Calibration revisions retain their own budgets, identities and claims; comparison rates require the fixed comparison protocol. Human semantic review remains separate. See the actual self-tests for individual failure and boundary cases; do not duplicate their transcripts here.

Frozen revisions and receipts remain in `tools/agent-evaluation/`, `tools/agent-evaluation-preparation/` and `tools/consumer-lifecycle/`. The [testing guide before consolidation](https://github.com/balgf/PHPThis/blob/884e7154984d774f9da8f595c820c2b978c8118a/.ai/testing.md) preserves the original assertion inventory and revision narrative at its exact source revision. Reproduce historical inputs there; do not rewrite fixtures, prompt hashes, retained attempts or published history to match current instructions.
