# Guardrails

`php tools/guardrails.php` currently enforces repository-level invariants without third-party packages:

- every PHP file declares strict types;
- every named repository class is final (`PHT002`);
- magic methods other than constructors are absent;
- dangerous dynamic mechanisms such as `eval` and variable variables are absent;
- calls to `selectAllRows`, `selectOneRow`, and `executeStatement` do not occur inside loop headers or bodies (`PHT003`);
- exact route matching and allowed-method lookup do not contain request-time loops;
- PHP superglobals are read only in `example/public/index.php`;
- the consumer contract, installed knowledge map, and every required application context template file remain present;
- the optional CRUD reference profile, its accepted decision, and its AI routing context remain present;
- the vision, consumer contract, skeleton, and application template preserve the AI authoring and human-accountability route;
- the canonical check and CI workflow preserve the SQLite, MySQL, and PostgreSQL PDO transport certification path;
- Markdown files outnumber PHP files;
- core source stays within 900 physical lines during Phase 1;
- PHPStan baseline files are absent;
- `phpstan.neon` keeps strict-rules, every strict rule, and the PHPThis extension enabled without `ignoreErrors`.

Runtime query budgets enforce a separate limit before each statement executes. Request-scoped query traces add bounded, redacted evidence about executed statement shapes, repetition, timing, and failures.

The CRUD profile guard checks only that the installed authority and context route remain available. It deliberately does not inspect consumer directory names: an application may record one canonical alternative structure while remaining subject to the hard consumer contract and Strict Profile.

PHPStan runs separately at maximum level with strict rules. It owns static type correctness; `PHT005` resolves literal, imported, aliased, fully qualified, and typed dynamic PDO construction so the framework connection remains the sole boundary. The repository guardrail retains only lightweight structure checks until equivalent type-aware PHPStan rules exist.

`php tools/test-strict-profile.php` exercises passing and failing fixtures against the same syntax guard and PHPStan extension used by the canonical check. PHPThis-owned rule IDs are permanent and have no suppression mechanism.

`php tools/test-database-drivers.php` defaults to a temporary SQLite database and fails when any explicitly requested driver or connection configuration is unavailable. The dedicated CI job requests SQLite, MySQL, and PostgreSQL against real services. Its deliberately narrow common SQL proves PDO transport behavior without creating a runtime dialect abstraction; application SQL remains engine-specific.

Consuming applications use `vendor/bin/phpthis check`. It discovers PHP across the application rather than accepting a fixed list of conventional source roots, rejects symlinked checked source, and passes one manifest to both syntax guardrails and a temporary framework-owned PHPStan configuration. Normal runs use a persistent profile-owned cache and parallelize when the host allows PHPStan's loopback coordinator; restricted hosts fall back to serial analysis. `PHT004` rejects consumer PHPStan configuration, baselines, and inline PHPStan ignores before analysis.

`php tools/test-consumer-project.php` builds the real framework release archive, compares every entry with `tools/package-files.txt`, installs that archive into a fresh temporary copy of `skeleton/`, and runs both the installed profile and application behavior stages. Composer and Git export-exclusion policies must be identical; a clean Git checkout also archives `HEAD` and compares that complete inventory. Its controls cover unconventional and extensionless source paths, unsupported PHP suffixes, obscured magic methods, PHT001, PHT002, PHT004, PHT005 construction forms, dependency exclusion, configuration symlinks, source symlinks, Composer-gate drift, persistent normal-mode caching, explicit debug output, and the pre-alpha-to-tagged skeleton repository boundary. Inventory failure prevents maintainer dependencies, cache data, examples, or harness files from silently entering the consumer package.

That local proof establishes the source-controlled Composer and Git export policies; it does not claim that an uncreated hosting-provider archive is identical. The alpha release gate must install the actual Packagist-preferred dist artifact, compare its complete framework inventory with `tools/package-files.txt`, and run the public skeleton creation command before the release is announced.

`php tools/test-query-scaling.php` verifies that the accepted read remains constant at one query and explicitly submits its `.php.fixture` N+1 negative control to `PHT003`. The fixture is not accepted repository PHP; proving its rejection is part of the test.

The loop check is deliberately narrow and syntax-aware. It covers the canonical database methods; review still has to reject aliases, dynamic calls, and inefficient single statements. Guardrails should remain deterministic, fast, and locally runnable.
