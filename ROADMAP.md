# Roadmap

## Phase 0: foundation - complete

- Product principles and AI context map.
- Exact-path router and explicit handler interface.
- Request, response, and response emitter.
- Thin PDO connection with named parameters and a query budget.
- Bounded, redacted query tracing with a versioned JSON-compatible snapshot.
- Strict database-projection and JSON-command boundary examples with coercion tests.
- Strict Profile v0 with stable rules for mixed scalar coercion, final named classes, and database calls in loops.
- Maximum-level PHPStan analysis with strict rules and no baseline.
- Zero-dependency checks and example application.

## Phase 1: prove the development pattern

- Complete: one bounded aggregate read endpoint and one transactional write endpoint against a sample schema.
- Complete: dataset-size query-count tests and an executable N+1 negative control rejected by `PHT003`.
- Complete: one bounded PHP runtime request reader with immutable normalized headers.
- Complete: one exact-class error-to-response registry and generic unknown-failure boundary.
- Complete: consumer contract v0 and a project-owned application AI context template.
- Complete: consumer contract v1 with a framework-owned, non-configurable application profile checker.
- Complete: separately packageable runnable skeleton and isolated consumer-install proof.
- Complete: AI-first authoring and human-accountability contract with an installed framework knowledge map instead of a traditional manual.
- Complete: PDO base-transport contract and certification harness, with local SQLite proof and a dedicated SQLite/MySQL/PostgreSQL CI gate without a dialect abstraction.
- Complete: optional feature-first CRUD reference profile with a documented application-owned alternative and no runtime or filesystem enforcement.
- Complete: Consumer Contract and Strict Profile version 2 with PHT006 finite direct SQL, adversarial bound-data evidence, and explicit application-owned database-authority policy.
- Complete: Consumer Contract version 3 with explicit response cookies and one optional lazy native-file session lifecycle; authentication, authorization, expiry, revocation, and CSRF remain application policy.
- Complete: accepted cache policy that separates explicit application-owned HTTP response caching from server-side derived-data caching while adding no pre-Alpha generic cache mechanism or Consumer Contract version.
- Current: specify typed path parameters before implementing dynamic routes.
- After typed item routes: prove Get, Update, and Delete only after the example records pagination, concurrency, deletion, authorization, and conflict decisions.

## Phase 2: type-aware AI guardrails

- Move type-sensitive architecture checks into tested PHPStan extensions.
- Detect unbounded reads with low false-positive rates and measure PHT006 gaps before considering any broader SQL provenance rule.
- Detect raw mixed arrays escaping named boundaries and add profile rules only after measuring false positives.
- Produce a machine-readable route report from the same explicit route list.
- Measure how many files and tokens common changes require.
- Build grounded-answer evaluations across installed revisions and measure unsupported claims, citation accuracy, uncertainty, and correct escalation of human decisions.
- Export `skeleton/` as its own package root, remove the pre-alpha VCS repository override, replace `dev-main` with the Packagist alpha constraint, commit its lockfile, and publish both prerelease packages.
- Install the actual Packagist-preferred framework dist, compare it with `tools/package-files.txt`, and prove the documented `composer create-project --stability=alpha` path before announcing alpha.

## Phase 3: production evaluation

- Define request IDs, structured request/query-summary log emission, security headers, streaming, uploads, and worker behavior explicitly.
- Benchmark routing and database boundaries against equivalent base PHP.
- Run the same endpoint tasks across several AI models and classify mistakes.
- Prove one application-owned backend-specific typed cache-aside path, including cold-cache query scaling, invalidation failure, isolation, eviction, and concurrent-miss evidence; do not promote a generic framework API from one application.
- Stabilize the public API only after evidence from real applications.

## Deferred by design

Authentication, authorization, CSRF policy, custom or shared session storage, middleware, a generic cache runtime, queues, templating, validation, migrations, and dependency packages are not accepted merely because conventional frameworks include them. Each needs a problem statement, an explicit execution path, a cost model, and a decision record. The accepted native session transport does not imply those adjacent capabilities. ADR 016 accepts cache policy, not a cache transport or universal API; the first backend-specific typed cache-aside proof remains application-owned and post-Alpha.
