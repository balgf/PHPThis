# PHPThis application bootstrap contract

PHPThis is experimental prerelease software. The repository contains the independently checked `phpthis/skeleton` package source under `skeleton/`; its separate Composer package is governed by the Alpha release gate.

This file defines reproducible setup and application-context requirements. It is not a framework programming manual. After bootstrap, the normal learning and authoring interface is the AI working in the application, grounded by `AGENTS.md`, `.ai/`, the installed PHPThis contract and knowledge map, source, and tests.

The template establishes how that AI learns the application before feature work begins. It is context scaffolding, not generated knowledge: every placeholder must be replaced with a verified project fact.

The intended first request is:

> Bootstrap this PHPThis application. Read `AGENTS.md`, inspect the installed PHPThis version, replace generic application context only with facts supported by this project, run the complete check, and report any product or operational decisions that require my approval.

## Start from a proved published skeleton

Select the exact `phpthis/skeleton` version whose clean public `create-project` proof is complete in the release evidence. Do not use an unpinned prerelease constraint during partial publication: it can select a newly indexed skeleton before that public-install path has been proved.

Alpha 5 is currently the last coordinated framework/skeleton pair with complete clean public-install evidence:

```bash
composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.5'
cd my-app
composer check
```

This creates the application root from the exact proved `phpthis/skeleton` version and installs its matching `phpthis/framework` under `vendor/phpthis/framework`. Consumers do not clone or copy the PHPThis framework repository. Before selecting a later prerelease, verify its exact skeleton version and clean public-install evidence in the release work item, GitHub, and Packagist.

## Framework source evaluation only

Use this fallback only to evaluate unpublished framework source when the Composer package is unavailable. It is not the normal consumer installation path. Use the public repository's `skeleton/` directory:

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
5. Use the contract-version-11 Composer scripts, remove consumer-owned PHPStan configuration and copied guard runners, centralize process-environment reads under PHT007 and complete `.ai/configuration.md`, resolve PHT006 findings with finite direct SQL and bound data, replace any separate unknown-failure global log with the application-owned terminal summary path, audit identifier-shaped `token` routes and select `positive-int`, `uuid`, `ulid`, or genuinely opaque `token` by the narrowest-type rule, remove generic middleware mechanisms, record any optional route-local application-owned request-handler decorators with their complete visible order and bounds, record input, session, response-framing, and file-transfer policy as verified or not applicable, confirm PHP 8.4.x, and run `composer check`.
6. Commit the completed application context before asking the project AI to implement the first feature.

The template contains representative rows for terms, datasets, integrations, and constraints. Delete unused optional rows or replace the relevant section with `NOT_APPLICABLE(reason)`; never invent filler merely to remove a placeholder. A database-free application uses the exact standalone line `NOT_APPLICABLE(DATABASE)` in `.ai/data.md`. Replace that declaration before adding a direct canonical `PHPThis\Database\Connection::connect` call; configuration-only scope may keep it.

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
- the one configuration-boundary PHP file or `NOT_APPLICABLE(CONFIGURATION)`, exact external input names without values, adopted process-specific final readonly types, validation bounds, profile, input-name, and credential separation without inheritance, combined credentials, or fallback, visible injection sites for adopted infrastructure or explicitly deferred connection composition, failure before application-controlled I/O, rotation/restart, redaction, and child-process parser or adopted-entrypoint tests;
- each inbound operation's raw representation, effective byte/depth/field/list/scalar bounds, exact field types and representations, absent-versus-null and unknown-field behavior, explicit normalization or rejection policy, final readonly request or command, downstream typed behavior or one justified typed operation seam, parser position relative to request policy, generic failure disclosure, duplicate-key proof limit, and adversarial exclusion from operation-owned work; for structured request-body content, the complete structural phase before value rules and default generic `400` structural versus exact application-owned generic `422` unacceptable-value split, or another explicit finite API contract; query, header, route, and transport representations retain separately recorded contracts; or `NOT_APPLICABLE(INPUT)` while no operation accepts application-owned fields;
- whether the application adopts the installed CRUD reference structure or records one canonical alternative, plus each identifier's type, public representation, generation owner, narrowest fixed route type, immediate route-specific wrapper and any narrower domain validation, pagination, mutation-concurrency, missing-record, deletion, authorization, and audit policies; `token` remains only for genuinely opaque identifiers;
- every database connection's engine and version, PDO extension, non-secret configuration reference, database and object definition source, database/catalog/schema/attachment namespace selection and qualification as supported, namespace and object control-or-ownership model or explicit non-applicability, dialect authority, large or sensitive tables, result bounds, query budgets, index expectations, integration command, and connection-local transaction constraints;
- every operation's variable SQL-structure choices or an explicit static-only statement policy, plus the finite reviewed complete-statement mapping, explicit parameter-array ownership, bounded-list omitted/empty/cardinality behavior, cursor tie-break and snapshot policy, and rejection behavior;
- each named database operation's complete statement source, runtime identity, exact required objects and actions, selected prohibited actions, engine-specific effective-authority resolution using only applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other sources, exact-engine positive and negative evidence source and date, and one accountable non-HTTP owner plus authoritative implementation reference for every adopted authority activation and deactivation; configuration, connectivity, object existence, and migration completion do not activate authority;
- separately, either `NOT_APPLICABLE(MIGRATIONS)` in `.ai/migrations.md` or one accepted engine-specific migration policy following ADR 043: name the sole application console, each separately tracked history's finite command plus typed-configuration/process-identity reference to `.ai/configuration.md` and database-authority reference to `.ai/data.md`, selected transition implementation and complete non-HTTP path, any supported and selected checksum-covered `GRANT` or `REVOKE` SQL, finite ordered unrolled manifest, permanent identifiers and checksums, bounded ledger and definition verification, engine-specific ledger-consistency boundary and every non-atomic state, every supported coordination topology with its exact creation/acquisition/use/release permissions, protected interval, and bypass denial, immutable forward recovery, runtime-authority handoff constraints, finite redacted output, and real-console plus coordination-owner and no-HTTP-startup evidence; record the application-wide sequence through exact-engine verification, rollout, traffic enablement, later deactivation, and namespace removal only in `.ai/operations.md`; ADR 027's per-migration transaction, rollback, and same-host `flock` are required only when adopting its SQLite reference boundary;
- external services, timeouts, idempotency requirements, retry ownership, and observable side effects;
- when WebSockets are proposed or adopted, either record non-adoption or own a reviewed application decision naming the pinned mature runtime, separate composition root, exact handshake and message boundary, current authorization, connection/rate/lifetime/backpressure/delivery limits, redaction, shutdown and deployment ownership, and real process/socket evidence; never adapt frames to PHPThis HTTP `Request` or `Response` values or add a framework WebSocket mechanism;
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
- Use `.ai/README.md` as a task router and begin ordinary implementation with one current operational guide. Read another guide only when the selected concern requires it, and read an ADR only when reviewing or changing its decision.
- Preserve the installed knowledge map's four-file simple-endpoint route after universal entrypoints: current guide, existing named route-area manifest, dependency-free handler, and nearest behavior test. The root route manifest remains unchanged for that narrow case.
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
- `Inspect the accepted application-owned WebSocket profile, then list the runtime, authentication, authorization, resource, delivery, and deployment decisions this application must own before implementation.`

The AI should cite concrete paths, distinguish existing behavior from proposals, run `composer check` after changes, and surface consequential choices for human judgment. The accountable human approves accepted application decisions and owns the resulting system.

## Prerelease boundary

The bounded Alpha 5 scope is accepted in `docs/decisions/040-bounded-alpha-5-release-scope.md`; `v0.1.0-alpha.5` remains the latest complete coordinated framework, skeleton, and public-install release. That boundary carries Alpha 4 and the earlier bounded prerelease surfaces forward and rolls ADR 036 through ADR 039 into Consumer Contract version 10 and Strict Profile version 3 with permanent diagnostic `PHT007`.

The bounded Alpha 6 scope is accepted in `docs/decisions/047-bounded-alpha-6-release-scope.md`; `v0.1.0-alpha.6` is the latest immutable framework tag and source boundary. Issue #37 records the exact framework candidate, tag, and package-distribution evidence, but its Alpha 6 record remains partial and unproved pending the dedicated skeleton, clean exact `create-project` proof, both GitHub prereleases, and announcement. Framework-side evidence therefore does not establish that an exact Alpha 6 skeleton command is available. Package availability is an external fact: verify the evidence record, GitHub, and Packagist before selecting a package version. Current `main` after the framework tag contains documentation, guardrail, and maintainer-only evaluation-tooling changes, adds no core, and is not tagged Alpha 6 source. Alpha 6 adopts Consumer Contract version 11 through ADR 045 while retaining Strict Profile version 3 and diagnostics `PHT001` through `PHT007`. Construct only supported final response framing, add the applicable session-cleanup failure-precedence evidence, and treat copyable child-process configuration proof, eager composition and exact probe claims, ADR 041 through ADR 046, identifier and UUID-generation policy, CRUD access-surface structure, and evidence organization as application-owned patterns rather than framework services.

Alpha 6 removes the public-prerelease convenience factory `PathParameters::onePositiveInteger($name, $value)`. Any consumer upgrading from Alpha 5 or an earlier PHPThis revision or package must replace each call with `PathParameters::fromValues([$name => $value], [])`; an unchanged old call fails because the method no longer exists. This is a deliberate prerelease compatibility break in factory shape only; route matching, accepted positive-integer grammar, immutable delivery, and the `positiveInteger()` accessor remain unchanged. Reconcile framework guidance deliberately: the application's `AGENTS.md`, `.ai/` context, source layout, and decisions remain project-owned and are never overwritten or relocated by an upgrade. The source repository's `skeleton/` directory retains a VCS constraint and `repositories` override only as a source-evaluation bootstrap, so record the evaluated Git commit and commit the generated application lockfile.

Prerelease publication follows the complete version-neutral maintainer gate in `RELEASING.md`. Export `skeleton/` as the root of its separate package repository, remove the VCS override, replace `dev-main` with the exact approved prerelease constraint resolved from Packagist, and commit the skeleton lockfile. Both prerelease packages must be indexed before the actual Packagist-preferred dist is installed, its framework inventory is compared with `tools/package-files.txt`, and the exact `composer create-project --stability=alpha` command is proved in a clean project. A framework-only or skeleton-only publication is recorded as partial and is not announced as a complete release. This tracked guide does not itself establish that the public command is currently available. The shorter command without `--stability=alpha` belongs to a future stable release.
