# Architecture decisions

Decision records capture constraints that an AI must not reinterpret from scratch. A record contains context, decision, consequences, and reconsideration triggers. AI may investigate and draft a proposed record; `Status: accepted` represents accountable human maintainer approval.

Proposed records:

None.

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

## Current and successor relationships

A partially superseded record remains accepted outside the exact scope named below. Follow the direct successor for that scope; use current operational guides for ordinary implementation rather than rewriting historical decision bodies.

| Accepted record | Scope superseded in part | Direct successor |
| --- | --- | --- |
| [ADR 005](005-bounded-query-tracing.md) | Temporary Phase 0 core-source ceiling | [ADR 008](008-explicit-request-boundary.md) |
| [ADR 008](008-explicit-request-boundary.md) | Separate unknown-failure log | [ADR 023](023-application-owned-terminal-request-summaries.md) |
| [ADR 008](008-explicit-request-boundary.md) | Upload and response-streaming reconsideration item | [ADR 026](026-bounded-file-transfers.md) |
| [ADR 012](012-pdo-transport-application-owned-dialects.md) | Shared request-wide query-budget option for terminal-summary database sources | [ADR 023](023-application-owned-terminal-request-summaries.md) |
| [ADR 013](013-optional-crud-reference-profile.md) | Earlier Create tree and handler-owned transaction description | [ADR 021](021-application-owned-typed-input-boundaries.md) |
| [ADR 017](017-bounded-trailing-positive-integer-routes.md) | One-trailing-parameter limit, prefix index, and one-value route metadata | [ADR 019](019-bounded-multiple-typed-routes.md) |
| [ADR 019](019-bounded-multiple-typed-routes.md) | Fixed parameter-type set before UUID and ULID | [ADR 032](032-explicit-uuid-and-ulid-route-types.md) |
| [ADR 020](020-application-owned-request-policy.md) | Denial and unknown-failure logging wording | [ADR 023](023-application-owned-terminal-request-summaries.md) |
| [ADR 021](021-application-owned-typed-input-boundaries.md) | Blanket-`400` authoring default for structured request-body content | [ADR 042](042-application-owned-input-failure-classification.md) |
| [ADR 025](025-application-owned-explicit-cli-and-scheduler.md) | Executable example's same-host schedule file lock and `schedule:run` coordination output | [ADR 028](028-application-owned-redis-cache-and-schedule-lease.md) |

ADR 013's current executable-example identifier placement is additionally refined by [ADR 046](046-canonical-executable-example-boundaries.md); the canonical current tree remains in [Optional CRUD reference profile](../crud.md#reference-placement). This refinement does not additionally supersede ADR 013's optional structure decision.
