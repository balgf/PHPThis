# PHPThis application bootstrap contract

PHPThis is experimental prerelease software. The repository contains the independently checked `phpthis/skeleton` package source under `skeleton/`; its separate Composer package is governed by the Alpha release gate.

This file defines reproducible setup and application-context requirements. It is not a framework programming manual. After bootstrap, the normal learning and authoring interface is the AI working in the application, grounded by `AGENTS.md`, `.ai/`, the installed PHPThis contract and knowledge map, source, and tests.

The template establishes how that AI learns the application before feature work begins. It is context scaffolding, not generated knowledge: every placeholder must be replaced with a verified project fact.

The intended first request is:

> Bootstrap this PHPThis application. Read `AGENTS.md`, inspect the installed PHPThis version, replace generic application context only with facts supported by this project, run the complete check, and report any product or operational decisions that require my approval.

## Start from the checked skeleton

When Packagist lists the approved `phpthis/skeleton` Alpha, the canonical installation path is:

```bash
composer create-project --stability=alpha phpthis/skeleton my-app
cd my-app
composer check
```

For source-checkout evaluation, including when the package is not available, use the public repository's `skeleton/` directory:

```bash
git clone https://github.com/balgf/PHPThis.git phpthis-source
cp -R phpthis-source/skeleton my-app
cd my-app
composer install
composer check
```

Do not copy the framework-maintainer root, `example/`, `tests/`, or root `.ai/` directory into the application.

The skeleton supplies:

- a Composer project with explicit runtime and development dependencies;
- one bootstrap, front controller, root route manifest, health route, and handler;
- project-owned `AGENTS.md` and `.ai/` context with no unresolved template tokens;
- the framework-owned `phpthis check` profile stage and application-owned behavior tests;
- a CI workflow that calls the installed checker directly and runs behavior tests.

Every observable behavior change must add or update automated tests. The consumer chooses the test library, runner, and file placement, but Composer `scripts.test` must execute that evidence and fail when a test fails. Static analysis, documentation, manual verification, and a no-op test command do not replace behavior tests.

Replace the skeleton's generic project facts with verified product, architecture, data, integration, authorization, and operational facts before feature work.

## Add context to an existing application

For an existing application adopting the context template and, when applicable, the runtime:

1. When application code executes PHPThis classes, declare `phpthis/framework` under Composer `require`, not only `require-dev`. Declare `phpstan/phpstan:^2.1` and `phpstan/phpstan-strict-rules:^2.0` under `require-dev`.
2. Copy the contents of `vendor/phpthis/framework/templates/application/` into the new application root, preserving the hidden `.ai/` directory. When evaluating from a PHPThis source checkout instead, use its `templates/application/` directory.
3. Replace every `{{PLACEHOLDER}}` in `AGENTS.md` and `.ai/`.
4. Add the application's accepted architectural decisions to `docs/decisions/README.md`.
5. Use the contract-version-9 Composer scripts, remove consumer-owned PHPStan configuration and copied guard runners, resolve PHT006 findings with finite direct SQL and bound data, replace any separate unknown-failure global log with the application-owned terminal summary path, audit identifier-shaped `token` routes and select `positive-int`, `uuid`, `ulid`, or genuinely opaque `token` by the narrowest-type rule, remove generic middleware mechanisms, record any optional route-local application-owned request-handler decorators with their complete visible order and bounds, record input, session, and file-transfer policy as verified or not applicable, confirm PHP 8.4.x, and run `composer check`.
6. Commit the completed application context before asking the project AI to implement the first feature.

The template contains representative rows for terms, datasets, integrations, and constraints. Delete unused optional rows or replace the relevant section with `NOT_APPLICABLE(reason)`; never invent filler merely to remove a placeholder.

When PHPThis is installed in Composer's default vendor directory, one contract path referenced by the template is:

```text
vendor/phpthis/framework/docs/consumer-contract.md
```

The copied `AGENTS.md` and `.ai/` guides contain multiple `vendor/phpthis/framework/` routes to installed contracts and knowledge. If the project uses a different vendor directory, update every occurrence together rather than correcting only the consumer-contract link. For an existing application, merge the template deliberately; never overwrite established project instructions or decisions.

Review the complete assumption with:

```bash
rg -n 'vendor/phpthis/framework/' AGENTS.md .ai
```

From the application root, this command should return no matches after customization:

```bash
rg -n '\{\{[A-Z0-9_]+\}\}' AGENTS.md .ai
```

## What the application must supply

PHPThis already defines framework mechanics. The application context should add only facts that alter implementation decisions:

- the product purpose, users, accountable human decision roles, non-goals, and canonical domain vocabulary;
- the actual composition root, route manifest, source boundaries, and dependency direction;
- each inbound operation's raw representation, effective byte/depth/field/list/scalar bounds, exact field types and representations, absent-versus-null and unknown-field behavior, explicit normalization or rejection policy, final readonly request or command, downstream typed behavior or justified seam, parser position relative to request policy, generic failure disclosure, duplicate-key proof limit, and adversarial exclusion from operation-owned work; or `NOT_APPLICABLE(INPUT)` while no operation accepts application-owned fields;
- whether the application adopts the installed CRUD reference structure or records one canonical alternative, plus each identifier's type, public representation, generation owner, narrowest fixed route type, immediate route-specific wrapper and any narrower domain validation, pagination, mutation-concurrency, missing-record, deletion, authorization, and audit policies; `token` remains only for genuinely opaque identifiers;
- every database connection's engine and version, PDO extension, non-secret configuration source, schema and dialect ownership, large or sensitive tables, result bounds, query budgets, index expectations, integration command, and connection-local transaction constraints;
- every operation's variable SQL-structure choices or an explicit static-only statement policy, plus the finite reviewed complete-statement mapping, explicit parameter-array ownership, bounded-list omitted/empty/cardinality behavior, cursor tie-break and snapshot policy, and rejection behavior;
- each runtime connection's required objects and actions, explicitly unavailable schema or administrative authority, privilege-verification source and date, and separation from migration credentials;
- separately, either `NOT_APPLICABLE(MIGRATIONS)` in `.ai/migrations.md` or one accepted engine-specific migration policy naming the sole application command, separate migration authority, finite ordered unrolled manifest, permanent identifiers and checksums, bounded ledger, per-migration transactions, lock topology, immutable forward recovery, finite redacted output, and real-console plus no-HTTP-startup evidence;
- external services, timeouts, idempotency requirements, retry ownership, and observable side effects;
- authentication and authorization boundaries;
- session adoption or explicit non-adoption, typed state schema and key ownership, cookie policy, isolated native file-storage ownership and cleanup, deployment topology, concurrent-request evidence, and each applicable regeneration, expiry, logout, revocation, and CSRF policy with absent concerns explicitly not applicable;
- one explicit HTTP response policy covering success, mapped and unknown failure, redirect, not-found, cookie-emitting, personalized, authenticated, and sensitive paths as applicable, including exact `Cache-Control`, validator, and `Vary` behavior; framework-owned 404, 405, and 500 `no-store` behavior does not decide arbitrary application responses;
- separately, either `NOT_APPLICABLE(CACHE)` for server-side data caching or an accepted application-owned typed cache-service policy naming backend/topology, bounded versioned tenant-aware keys and payloads, finite lifetime, invalidation and stale-refill behavior, failure and stampede behavior, redacted aggregate observability, and cold-cache plus concurrency evidence;
- separately, either `NOT_APPLICABLE(JOBS)` in `.ai/jobs.md` or one accepted application-owned durable-job policy naming the exact backend and version, same-connection producer transaction, bounded versioned envelope and finite dispatch, idempotency owner, fresh-time lease fencing, maximum attempts and exact backoff, redacted dead letters, one-shot process and supervisor, retention and recovery, and complete process-failure evidence;
- runtime, deployment, worker, logging, and incident-response assumptions;
- the application-owned terminal coordinator and sink paths, generated correlation-ID policy, finite database-source names and bounds, destination behavior, and tests proving response propagation, redaction, exactly one invocation attempt, and sink-failure isolation;
- the one complete check command and any focused verification commands.

Do not restate ordinary PHP syntax or copy the framework repository's maintainer `.ai/` directory. That directory refers to PHPThis internals such as its example, framework tests, and profile tooling. The application template is intentionally separate.

## Keep it useful

- Keep `AGENTS.md` short enough to read for every task.
- Use `.ai/README.md` as a task router and load only the relevant area guide.
- State concrete limits and paths instead of broad advice.
- Link to source-of-truth schemas, contracts, and decisions instead of duplicating them.
- Update the context in the same change when a public application pattern changes.
- Convert critical prose rules into PHPStan checks, tests, or other deterministic project checks when practical.
- Remove stale statements promptly; incorrect context is worse than absent context.

The application owns these files. Framework upgrades may update the consumer contract, but they must never replace project-specific instructions automatically.

## Learn and build by asking the project AI

After setup, ask the AI to inspect the current application rather than teach from a remembered framework API. For example:

- `Explain the complete request path in this application and name every PHPThis and application file involved.`
- `Show the canonical pattern in this checkout for adding a route, then implement GET /status with tests.`
- `Inspect the installed CRUD reference profile and this application's structure policy, then show where a new Create operation belongs.`
- `Audit this database path for PHT006, unique bound data, finite SQL structure, runtime least privilege, and migration-credential separation; cite the installed contract and application evidence.`
- `Explain this PHT diagnostic from the installed profile and repair its cause.`
- `Explain the installed session lifecycle, then identify the authentication, authorization, expiry, revocation, and CSRF decisions this application still owns.`

The AI should cite concrete paths, distinguish existing behavior from proposals, run `composer check` after changes, and surface consequential choices for human judgment. The accountable human approves accepted application decisions and owns the resulting system.

## Alpha publication boundary

The bounded Alpha 3 scope is accepted in `docs/decisions/031-bounded-alpha-3-release-scope.md`; it carries forward the Alpha 2 consumer profile in ADR 029 and adds ADR 030's report-only duplication advisory, while Alpha 1 history remains in ADR 018. Acceptance of the scope is not publication. Package availability is an external fact: verify the approved repository tags and Packagist versions. The source repository's `skeleton/` directory retains a VCS constraint and `repositories` override only as a source-evaluation bootstrap, so record the evaluated Git commit and commit the generated application lockfile.

Alpha publication follows the complete maintainer gate in `RELEASING.md`. Export `skeleton/` as the root of its separate package repository, remove the VCS override, replace `dev-main` with the approved alpha constraint resolved from Packagist, and commit the skeleton lockfile. Both prerelease packages must be indexed before the actual Packagist-preferred dist is installed, its framework inventory is compared with `tools/package-files.txt`, and the exact `composer create-project --stability=alpha` command is proved in a clean project. This tracked guide does not itself establish that the public command is currently available. The shorter command without `--stability=alpha` belongs to a future stable release.
