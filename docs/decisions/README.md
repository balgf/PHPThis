# Architecture decisions

Decision records capture constraints that an AI must not reinterpret from scratch. A record contains context, decision, consequences, and reconsideration triggers. AI may investigate and draft a proposed record; `Status: accepted` represents accountable human maintainer approval.

Proposed records:

- None.

Accepted records:

- `001-manual-composition-root.md`
- `002-visible-sql-no-orm.md`
- `003-query-budgets.md`
- `004-one-canonical-pattern.md`
- `005-bounded-query-tracing.md`
- `006-strict-boundary-parsing.md`
- `007-checked-php-profile.md`
- `008-explicit-request-boundary.md`
- `009-project-owned-ai-context.md`
- `010-framework-owned-consumer-check.md`
- `011-ai-first-authoring.md`
- `012-pdo-transport-application-owned-dialects.md`
- `013-optional-crud-reference-profile.md`
- `014-sql-data-and-finite-structure.md`
- `015-explicit-native-session-lifecycle.md`
- `016-cache-policy-before-cache-mechanism.md`
- `017-bounded-trailing-positive-integer-routes.md`
- `018-bounded-alpha-1-release-scope.md`
- `019-bounded-multiple-typed-routes.md`
- `020-application-owned-request-policy.md`
- `021-application-owned-typed-input-boundaries.md`
- `022-application-owned-finite-data-paths.md`
- `023-application-owned-terminal-request-summaries.md`
- `024-application-owned-sqlite-durable-jobs.md`
- `025-application-owned-explicit-cli-and-scheduler.md`
- `026-bounded-file-transfers.md`
- `027-application-owned-explicit-sqlite-migrations.md`
- `028-application-owned-redis-cache-and-schedule-lease.md`
- `029-alpha-2-consumer-profile-rollup.md`
- `030-report-only-consumer-duplication-advisory.md`
- `031-bounded-alpha-3-release-scope.md`
- `032-explicit-uuid-and-ulid-route-types.md`
- `033-application-owned-request-handler-decorators.md`
- `034-application-owned-websocket-integration.md`
- `035-bounded-alpha-4-release-scope.md`
- `036-one-typed-application-configuration-boundary.md`
- `037-database-setup-scope-gate.md`
- `038-application-owned-database-authority-lifecycle.md`
- `039-recommended-database-migration-structure.md`
- `040-bounded-alpha-5-release-scope.md`
- `041-optional-development-workbench.md`
- `042-application-owned-input-failure-classification.md`
- `043-engine-specific-application-migration-invariants.md`
- `044-bounded-task-routed-ai-context.md`
- `045-bounded-session-cleanup-and-response-framing.md`
- `046-canonical-executable-example-boundaries.md`
- `047-bounded-alpha-6-release-scope.md`
- `048-isolated-agent-evaluation-controller.md`
- `049-bounded-response-cookie-profile.md`
- `050-application-owned-local-environment-launcher.md`
- `051-application-owned-structured-log-destinations.md`
- `052-backend-neutral-application-owned-durable-jobs.md`
- `053-application-owned-amazon-s3-file-transfers.md`
- `054-bounded-alpha-7-release-scope.md`
- `055-value-free-composer-configuration-scripts.md`
- `056-bounded-request-target-and-path-bytes.md`

## Current and successor relationships

A partially superseded record remains accepted outside the exact scope named below. Follow the direct successor for that scope; use current operational guides for ordinary implementation rather than rewriting historical decision bodies.

| Accepted record | Scope superseded in part | Direct successor |
| --- | --- | --- |
| [ADR 005](005-bounded-query-tracing.md) | Temporary Phase 0 core-source ceiling | [ADR 008](008-explicit-request-boundary.md) |
| [ADR 008](008-explicit-request-boundary.md) | Separate unknown-failure log | [ADR 023](023-application-owned-terminal-request-summaries.md) |
| [ADR 008](008-explicit-request-boundary.md) | Upload and response-streaming reconsideration item | [ADR 026](026-bounded-file-transfers.md) |
| [ADR 012](012-pdo-transport-application-owned-dialects.md) | Shared request-wide query-budget option for terminal-summary database sources | [ADR 023](023-application-owned-terminal-request-summaries.md) |
| [ADR 013](013-optional-crud-reference-profile.md) | Earlier Create tree and handler-owned transaction description | [ADR 021](021-application-owned-typed-input-boundaries.md) |
| [ADR 015](015-explicit-native-session-lifecycle.md) | Cookie validation, duplicate-name, prefix, size, expiration, and lifetime wording | [ADR 049](049-bounded-response-cookie-profile.md) |
| [ADR 017](017-bounded-trailing-positive-integer-routes.md) | One-trailing-parameter limit, prefix index, and one-value route metadata | [ADR 019](019-bounded-multiple-typed-routes.md) |
| [ADR 019](019-bounded-multiple-typed-routes.md) | Fixed parameter-type set before UUID and ULID | [ADR 032](032-explicit-uuid-and-ulid-route-types.md) |
| [ADR 020](020-application-owned-request-policy.md) | Denial and unknown-failure logging wording | [ADR 023](023-application-owned-terminal-request-summaries.md) |
| [ADR 021](021-application-owned-typed-input-boundaries.md) | Blanket-`400` authoring default for structured request-body content | [ADR 042](042-application-owned-input-failure-classification.md) |
| [ADR 026](026-bounded-file-transfers.md) | Remote-object-store and pre-signed-delivery exclusion, only when an application explicitly selects `AMAZON_S3_ADR053`; `LOCAL_ADR026` remains unchanged | [ADR 053](053-application-owned-amazon-s3-file-transfers.md) |
| [ADR 025](025-application-owned-explicit-cli-and-scheduler.md) | Executable example's same-host schedule file lock and `schedule:run` coordination output | [ADR 028](028-application-owned-redis-cache-and-schedule-lease.md) |

ADR 013's current executable-example identifier placement is additionally refined by [ADR 046](046-canonical-executable-example-boundaries.md); the canonical current tree remains in [Optional CRUD reference profile](../crud.md#reference-placement). This refinement does not additionally supersede ADR 013's optional structure decision.

Accepted [ADR 050](050-application-owned-local-environment-launcher.md) adds an optional development-only environment-delivery recommendation to ADR 036 without superseding its one typed PHP configuration boundary. It is authoritative for the checked application-owned launcher pattern but adds no framework runtime, automatic loading, configuration cache, or skeleton adoption.

Accepted [ADR 051](051-application-owned-structured-log-destinations.md) adds one optional application-owned destination envelope and operational profile around ADR 023's closed version-1 request summary and ADR 028's closed version-2 Redis proof without superseding either record. It is authoritative for the checked destination-record encoder and optional profile; it left Consumer Contract version 12 and Strict Profile version 3 unchanged, and Contract version 14 carries it forward without framework runtime, dependency, or existing application behavior changes. The health-only skeleton and executable example remain explicit non-adopters.

Accepted [ADR 052](052-backend-neutral-application-owned-durable-jobs.md) adds current optional backend-neutral application-owned durable-job guidance without superseding ADR 024. ADR 024 remains the first and only checked backend-specific profile under its existing SQLite evidence; the health-only skeleton remains `NOT_APPLICABLE(JOBS)` and the executable example remains `SQLITE_ADR024_REFERENCE`. ADR 052 left Consumer Contract version 12 unchanged, and Contract version 14 carries its guidance, Strict Profile version 3, diagnostics `PHT001` through `PHT007`, framework runtime, dependencies, and checker validity forward unchanged.

Accepted [ADR 053](053-application-owned-amazon-s3-file-transfers.md) adds the optional `AMAZON_S3_ADR053` application-owned direct-S3 profile and coordinates Consumer Contract version 13. It partially supersedes ADR 026 only for the remote-object-store and pre-signed-delivery exclusion when that profile is explicitly selected. `LOCAL_ADR026`, the health-only skeleton's `NOT_APPLICABLE(FILE_TRANSFER)`, and the executable example's local public reference remain unchanged. The accepted direct-S3 response is a fixed `application/octet-stream` attachment without a guaranteed `nosniff` header; that narrow exception adds no framework AWS dependency, storage abstraction, Strict Profile rule, or checker diagnostic.

Accepted [ADR 055](055-value-free-composer-configuration-scripts.md) adds one ordinary installed-checker consistency rule for the existing outer-boundary configuration policy. It correlates canonical PHT007 input names with fail-conservative assignment and bounded mutation spellings in Composer command text, keeps diagnostics value-free, and leaves Consumer Contract version 13, Strict Profile version 3, diagnostics `PHT001` through `PHT007`, framework runtime, and dependencies unchanged.

Accepted [ADR 056](056-bounded-request-target-and-path-bytes.md) coordinates Consumer Contract version 14 and rejects raw bytes `0x00` through `0x20` and `0x7F` in the complete runtime request target and in direct request and route paths. It preserves raw percent spellings and the existing no-decoding and route-grammar decisions, leaves Strict Profile version 3 and diagnostics `PHT001` through `PHT007` unchanged, and keeps the 2,618-line core under the accepted 2,620-line ceiling.
