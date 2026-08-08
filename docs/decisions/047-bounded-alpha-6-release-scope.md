# ADR 047: Bounded Alpha 6 release scope

Status: accepted

## Context

Alpha 5 carries Consumer Contract version 10, Strict Profile version 3, and permanent diagnostics `PHT001` through `PHT007`. Accepted work after that boundary now includes ADR 041 through ADR 046, one bounded public-prerelease API removal, a correction to the existing `eval` prohibition, application-owned identifier and CRUD guidance, and maintainer-only proof-suite and guardrail refactors.

ADR 045 changes framework runtime behavior and advances the Consumer Contract to version 11. The remaining accepted work clarifies optional application-owned patterns or strengthens maintainer evidence without adding another consumer-validity rule. These changes need one bounded prerelease identity, an explicit Alpha 5 upgrade path, and release evidence that keeps application guidance and maintainer organization distinct from framework runtime and checker behavior.

On 2026-08-09 in Asia/Manila, the accountable human approved this bounded Alpha 6 scope, exact release identity, planned date, release notes, candidate-specific announcement draft, and source preparation. This approval does not approve an exact framework or skeleton candidate commit and does not authorize commit, push, tag, package-host, dedicated-skeleton, GitHub-prerelease, or announcement operations. Those operations remain separately gated by `RELEASING.md` and the external release work item.

## Decision

The exact approved identity is:

- Composer version: `0.1.0-alpha.6`
- framework tag: `v0.1.0-alpha.6`
- skeleton tag: `v0.1.0-alpha.6`
- planned release date: `2026-08-09` (Asia/Manila)
- release notes: `docs/releases/0.1.0-alpha.6.md`

The framework receives its Composer version from the Git tag; the root `composer.json` does not gain a `version` field. The exact framework and skeleton candidate commits remain pending and are recorded as `PENDING` until their respective preparation and proof gates pass. The planned date remains distinct from every observed external-operation timestamp.

Alpha 6 is accepted as the bounded rollup of the following changes after Alpha 5.

### Runtime, public API, and consumer validity

- ADR 045 advances Consumer Contract version 10 to version 11 while retaining Strict Profile version 3 and permanent diagnostics `PHT001` through `PHT007`.
- Ordinary `Response` construction rejects final `1xx` statuses, every `Transfer-Encoding` header, non-canonical or body-mismatched `Content-Length`, and body or length framing on `204`, `205`, and `304`. `HEAD` remains an explicit application-owned route with no inferred `GET` fallback, body suppression, or representation length.
- Native-session cleanup preserves the exact original failure when cleanup succeeds. When cleanup also fails, the redacted `SessionCleanupFailed` retains both failures and cannot be selected through `ErrorResponseRegistry`; cleanup remains bounded and adds no logger, retry, or response conversion.
- The redundant public-prerelease `PathParameters::onePositiveInteger($name, $value)` factory is removed. Consumers construct the same immutable value with `PathParameters::fromValues([$name => $value], [])`; route grammar, matching, and the `positiveInteger()` accessor remain unchanged.
- The existing forbidden-`eval` check is narrowed to the actual language construct so legal same-named identifiers pass. Actual `eval(...)` remains rejected. This correction adds no diagnostic, Strict Profile version, or consumer API.

### Optional application-owned guidance

- ADR 041 records optional PHPThis Workbench integration as a separate development-only package. It adds no Workbench code, dependency, discovery, generic dispatch, production shell, or validity shortcut to framework core.
- Configuration guidance adds copyable application-owned child-process evidence for exact valid, missing, empty, malformed, maximum-plus-one, captured-output non-disclosure, and profile-separation cases. It passes a bounded synthetic environment explicitly to a short-lived fixed-output child and does not add a framework process runner, test library, secret detector, or production configuration mechanism.
- Startup and probe guidance makes eager composition and synchronous dependencies explicit: `Connection::connect()` constructs PDO immediately and may perform driver- and DSN-specific I/O, while even the database-free starter health path synchronously invokes its deployment-configured terminal sink. Each application records the exact liveness or readiness claim, dependencies, limits, owner, and evidence without a framework probe API, hidden bypass, lazy connection, or universal readiness definition.
- ADR 042 records a deterministic structured-request-body default: malformed shape or native types map to generic `400`; values rejected only after the complete shape and native-type pass use an exact application-owned generic `422`. An application may retain another explicitly recorded and tested finite status contract.
- ADR 043 separates engine-neutral application-owned migration invariants from the SQLite-specific transaction and same-host `flock` proof. Every selected engine owns its ledger-consistency, coordination, non-atomic failure, recovery, authority, and exact-engine evidence without a cross-engine migration runtime.
- ADR 044 keeps universal AI context small, routes ordinary work through one current concern guide, and defines the exact four-file simple-endpoint metric without weakening required context or evidence.
- ADR 046 gives the executable example one exact-class error-response owner, one semantic user identifier, cadence-before-dependency scheduled preflight, and valid-UTF-8 authoritative projections without turning those choices into framework services.
- Current guidance recommends coherent application-owned semantic identifier types, canonical UUID acceptance across versions 1 through 8 unless a narrower domain rule is recorded, an explicitly versioned generation policy with version 7 recommended for new database row identifiers only when its embedded approximate-time disclosure is acceptable, and one CRUD feature tree with explicit subdivisions when public and administrative access surfaces differ. Version 4 remains the random-only alternative and version 5 the recorded deterministic alternative. It adds no identifier base class, trait, interface, UUID runtime or library mandate, model binding, generic CRUD runtime, or filesystem enforcement.
- Consumer guidance recommends a small deterministic application-owned test or validation entrypoint with cohesive concern modules as the suite grows. It prescribes no library, directory layout, discovery mechanism, or consumer-validity rule.
- Application `AGENTS.md`, `.ai/` context, source layout, and decisions remain project-owned. A framework upgrade is reconciled deliberately and never overwrites or relocates those files.

### Maintainer-only hardening

- The framework behavior suite is split into explicit concern-owned modules while retaining one duplicate-checked stable behavior inventory and complete entrypoint.
- Repository guardrails retain `php tools/guardrails.php` as one deterministic command while their checks live in five cohesive concern modules with stable aggregate diagnostic order.
- Repeated proof helpers are consolidated without conflating source evidence, installed-distribution evidence, or adversarial controls.
- Installed-guide path and vendor-root escape proof are repaired, PDO certification versions become executable evidence, ADR successor relationships are explicit, and release preparation becomes version-neutral.

These refactors add no consumer test-library requirement, runtime mechanism, dependency, checker discovery system, framework API, Consumer Contract change beyond ADR 045, Strict Profile change, or new diagnostic.

Alpha 6 carries forward unchanged:

- every accepted Alpha 5 boundary and the complete earlier prerelease surface not changed above;
- PHP 8.4.x as the framework Composer runtime range `~8.4.0`; PHP 8.5 remains outside the reviewed range;
- zero third-party framework runtime dependencies;
- the 2,600-line core ceiling and current 2,595-line implementation, whose five-line margin authorizes no adjacent mechanism;
- explicit manual composition, immutable HTTP values, finite route declarations, direct visible engine-specific SQL through `Connection`, distinct named bindings, compile-time-constant SQL structure, query budgets and traces, and separately certified PDO transport; and
- application ownership of product policy, configuration meaning, source layout, semantic identifiers, UUID generation version, CRUD access surfaces, authentication, authorization, tenancy, database dialects and authority, migrations, deployment, Workbench adoption, test organization, and operational limits.

Alpha 6 does not add or permit an ORM, Active Record, lazy loading, model or repository layer, query builder, generated SQL, binding or placeholder helper, dialect abstraction, generic database layer, service container, facade, global helper, automatic discovery, generic middleware, validation engine, migration runtime, UUID runtime or library mandate, framework configuration service, WebSocket runtime, generic cache, queue, scheduler, production shell, or automatic application-context rewrite.

An Alpha 5 consumer upgrades in this order:

1. Review Consumer Contract version 10 to version 11. Strict Profile version 3 and diagnostics `PHT001` through `PHT007` remain unchanged.
2. Replace each `PathParameters::onePositiveInteger($name, $value)` call with `PathParameters::fromValues([$name => $value], [])`.
3. Replace every final `1xx`, `Transfer-Encoding`, mismatched ordinary `Content-Length`, or body or length on `204`, `205`, or `304` with one explicitly supported response. Keep `HEAD` explicit and application-owned.
4. When native sessions are adopted, prove that successful cleanup preserves the exact primary failure, a second cleanup failure retains both causes through redacted `SessionCleanupFailed`, and the aggregate cannot select a registered response.
5. For structured request-body content, record and prove either the default complete-shape/type `400` versus unacceptable-value `422` precedence or another finite application-owned contract. Mixed structural and value failures remain structural regardless of property order.
6. Reconcile installed framework guidance with project-owned `AGENTS.md`, `.ai/` context, source layout, and decisions without overwriting or relocating them. Keep Workbench adoption optional and separate.
7. Run the complete application gate on PHP 8.4.x before adopting the exact Alpha 6 prerelease.

Before either tag is created, one exact clean pushed candidate must pass every applicable local and CI gate in `RELEASING.md`, including maximum-level PHPStan, permanent profile fixtures, framework behavior tests, installed-consumer proof, exact package inventory, Git-export comparison, and SQLite/MySQL/PostgreSQL PDO transport certification. The framework distribution must be proved before the dedicated skeleton is updated and locked to the exact prerelease. Both public artifacts and one clean Packagist-preferred `composer create-project` installation must pass before either release is announced.

The candidate-specific announcement draft approved for this scope remains the exact conditional draft recorded in the external Alpha 6 release work item. It may be used only after both tags, both Packagist artifacts, the clean public-install proof, both separately authorized GitHub prereleases, and separate final-announcement authorization succeed.

## Consequences

Alpha 6 gives consumers stricter response and session failure boundaries, a direct upgrade for one removed prerelease convenience factory, and clearer application-owned guidance for inputs, migrations, AI context, identifiers, CRUD access surfaces, and optional development inspection. Maintainer evidence becomes easier to navigate without changing the framework's one canonical consumer execution path.

The release remains experimental evaluation software. It makes no production-readiness, backward-compatibility, support-SLA, security-response-SLA, complete-CRUD, universal AI-compliance, secret-detection, grant-validation, cross-engine application-SQL or DDL, deployment, capacity, or exactly-once-effect claim.

Publication state is external. This decision accepts the bounded source claim, identity, planned date, notes, announcement draft, and source preparation only; it does not assert that either approved tag, either Packagist version, either GitHub prerelease, the dedicated-skeleton candidate, the public installation path, or the announcement exists.

## Reconsider when

A supported PHP minor, Strict Profile version, permanent diagnostic, runtime dependency, core ownership boundary, or Consumer Contract requirement changes; independent consumer evidence demonstrates that an accepted Alpha 6 boundary causes a concrete correctness or review failure; or publication evidence reveals a mismatch among approved source, package inventories, public distributions, and the installation path. Reconsider the smallest affected contract and publish a separately approved identity rather than moving either Alpha 6 tag or silently expanding this scope.
