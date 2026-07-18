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
- Current: specify typed path parameters before implementing dynamic routes.

## Phase 2: type-aware AI guardrails

- Move type-sensitive architecture checks into tested PHPStan extensions.
- Detect unbounded reads and interpolated SQL with low false-positive rates.
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
- Stabilize the public API only after evidence from real applications.

## Deferred by design

Middleware, caching, queues, templating, validation, migrations, and dependency packages are not accepted merely because conventional frameworks include them. Each needs a problem statement, an explicit execution path, a cost model, and a decision record.
