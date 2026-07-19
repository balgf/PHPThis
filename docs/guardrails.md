# Guardrails

`php tools/guardrails.php` currently enforces repository-level invariants without third-party packages:

- every PHP file declares strict types;
- every named repository class is final (`PHT002`);
- magic methods other than constructors are absent;
- dangerous dynamic mechanisms such as `eval` and variable variables are absent;
- calls to `selectAllRows`, `selectOneRow`, and `executeStatement` do not occur inside loop headers or bodies (`PHT003`);
- literal and bounded typed route lookup do not scan the route table at request time;
- the accepted trailing positive-integer route decision, immutable match/parameter delivery, literal precedence, and ambiguity rejection remain covered by repository tests;
- PHP runtime-input superglobals are read only in the example and skeleton front controllers, and `$_SESSION` is read only in `src/Session/SessionLifecycle.php`;
- native `session_*` calls occur only in `SessionLifecycle`, with the same restriction enforced in consuming applications;
- the consumer contract, installed knowledge map, and every required application context template file remain present;
- the optional CRUD reference profile, its accepted decision, and its AI routing context remain present;
- the cache policy guide, policy-before-mechanism decision, AI route, required HTTP policy fields, and explicit server-side adoption or not-applicable fields remain present;
- the vision, consumer contract, skeleton, and application template preserve the AI authoring and human-accountability route;
- the canonical check and CI workflow preserve the SQLite, MySQL, and PostgreSQL PDO transport certification path;
- the SQL data-versus-finite-structure decision, Contract version 3, Strict Profile version 2, application authority template, and PHT006 implementation remain present;
- Markdown files outnumber PHP files;
- core source stays within the 2,050-physical-line ceiling enforced for Phase 1;
- PHPStan baseline files are absent;
- `phpstan.neon` keeps strict-rules, every strict rule, and the PHPThis extension enabled without `ignoreErrors`.

Runtime query budgets enforce a separate limit before each statement executes. Request-scoped query traces add bounded, redacted evidence about executed statement shapes, repetition, timing, and failures.

The Phase 1 cap increases are scoped to ADR 015's explicit cookie/native-session slice and ADR 017's bounded trailing positive-integer routing slice. The reviewed implementation occupies 2,010 of the 2,050 allowed physical lines; the remaining 40-line maintenance margin does not authorize a general dynamic router or another mechanism. Repository checks also retain both accepted decisions, their knowledge routes, Consumer Contract version 3, Strict Profile version 2, and the application context fields that keep adjacent policy application-owned.

The routing lookup check follows direct helper calls from `Router::match` and `Router::allowedMethodsForPath`. It rejects explicit loops and reviewed array-traversal functions anywhere in that reachable method graph, while leaving constructor-only indexing helpers outside it. Stable negative fixtures prove that a helper loop and PHP 8.4 `array_find` or `array_filter` traversal fail; a construction-only loop fixture proves that startup indexing remains allowed.

The cache guard retains documentation and application-context policy, not a cache implementation. It keeps the current absence of a generic framework cache explicit, preserves separate HTTP and application data-cache decisions, requires the health-only skeleton to record its explicit `no-store` response policy, and resolves only server-side caching as not applicable. Framework and behavior tests cover explicit `no-store` on the current 404, 405, 500, skeleton, and example paths; arbitrary application responses remain application-owned. This does not claim that an application has adopted a backend or that PHPThis certifies one.

The CRUD profile guard checks only that the installed authority and context route remain available. It deliberately does not inspect consumer directory names: an application may record one canonical alternative structure while remaining subject to the hard consumer contract and Strict Profile.

PHPStan runs separately at maximum level with strict rules. It owns static type correctness; `PHT005` resolves literal, imported, aliased, fully qualified, and typed dynamic PDO construction so the framework connection remains the sole boundary. `PHT006` resolves the native SQL-expression type at direct `Connection` database calls and rejects non-finite, blank, annotation-only, unpacked, or indirectly invoked SQL. The repository guardrail retains only lightweight repository-shape checks rather than attempting SQL parsing or taint analysis.

`php tools/test-strict-profile.php` exercises passing and failing fixtures against the same syntax guard and PHPStan extension used by the canonical check. PHPThis-owned rule IDs are permanent and have no suppression mechanism.

`php tools/test-database-drivers.php` defaults to a temporary SQLite database and fails when any explicitly requested driver or connection configuration is unavailable. The dedicated CI job requests SQLite, MySQL, and PostgreSQL against real services. Its deliberately narrow common SQL proves PDO transport behavior without creating a runtime dialect abstraction; application SQL remains engine-specific. Its PHT006-compatible fixed table names are created and dropped, so non-SQLite runs require a disposable or dedicated test database and intentionally DDL-capable fixture credentials.

Consuming applications use `vendor/bin/phpthis check`. It discovers PHP across the application rather than accepting a fixed list of conventional source roots, rejects symlinked checked source, and passes one manifest to both syntax guardrails and a temporary framework-owned PHPStan configuration. Normal runs use a persistent profile-owned cache and parallelize when the host allows PHPStan's loopback coordinator; restricted hosts fall back to serial analysis. `PHT004` rejects consumer PHPStan configuration, baselines, and inline PHPStan ignores before analysis. Contract version 3's structural stage rejects `$_SESSION`, direct or imported native session calls, and literal indirect references in every application file, including the front controller; dynamically obscured calls remain forbidden by contract rather than becoming an accepted escape hatch.

`php tools/test-consumer-project.php` builds the real framework release archive, compares every entry with `tools/package-files.txt`, installs that archive into a fresh temporary copy of `skeleton/`, and runs both the installed profile and application behavior stages. Composer and Git export-exclusion policies must be identical; a clean Git checkout also archives `HEAD` and compares that complete inventory. Its controls cover unconventional and extensionless source paths, unsupported PHP suffixes, obscured magic methods, PHT001, PHT002, PHT004, PHT005 construction forms, direct/imported/literal-indirect native session access, dependency exclusion, configuration symlinks, source symlinks, Composer-gate drift, persistent normal-mode caching, explicit debug output, and the pre-alpha-to-tagged skeleton repository boundary. Inventory failure prevents maintainer dependencies, cache data, examples, or harness files from silently entering the consumer package.

That local proof establishes the source-controlled Composer and Git export policies; it does not claim that an uncreated hosting-provider archive is identical. The alpha release gate in `RELEASING.md` must install the actual Packagist-preferred dist artifact, compare its complete framework inventory with `tools/package-files.txt`, and run the public skeleton creation command before the release is announced.

`php tools/test-query-scaling.php` verifies that the accepted read remains constant at one query and explicitly submits its `.php.fixture` N+1 negative control to `PHT003`. The fixture is not accepted repository PHP; proving its rejection is part of the test.

The loop check is deliberately narrow and syntax-aware. It covers the canonical database methods; review still has to reject recursive I/O and inefficient single statements. PHT006 narrows direct SQL construction but does not prove authorization, stored-procedure behavior, database grants, migration-credential isolation, or universal injection safety. Guardrails should remain deterministic, fast, and locally runnable.
