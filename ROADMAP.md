# Roadmap

## Phase 0: foundation - complete

- Product principles and AI context map.
- Directly indexed literal router and explicit handler interface.
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
- Complete: one bounded trailing `{name:positive-int}` route grammar, immutable `RouteMatch` and `PathParameters` delivery without changing the handler interface, literal precedence, ambiguity rejection, and indexed request-time lookup.
- Complete: first bounded `GET /users/{user_id}` proof with immediate concrete-identifier conversion; this does not claim complete authorization or tenant policy.
- Complete: application-owned `GET /users` keyset continuation with strict `after_user_id` parsing, fixed 50-row pages, one-statement lookahead, and no-gap/no-duplicate scaling evidence.
- Complete: accepted bounded Alpha 1 release scope; complete CRUD, authentication, authorization, and tenancy are explicitly not publication prerequisites.

## Phase 2: Alpha 1 publication and type-aware AI guardrails

- Complete: Consumer Contract version 4 and ADR 019 with at most two full-segment `positive-int` or bounded `token` parameters, deterministic indexed matching, exact-literal precedence, overlap rejection, and immutable type-specific delivery.
- Complete: inspectable immutable `Route::segments()` metadata derived from the same explicit route declaration; no generated route source, persisted route cache, or second routing API.
- Complete: ADR 020 application-owned protected-request composition with visible stateless authentication, tenant resolution, current per-request authorization, replaceable policies, denial bounds, and no new core runtime contract.
- Complete: ADR 021 application-owned typed input parsing with exact canonical representations, generic safe failures, PHPStan-verified command delivery, and executable Create proof that rejected input never enters its typed operation or performs Create-owned side effects; no generic validator or core contract was added.
- Complete: ADR 022 application-owned finite document-list data path with eight complete raw SQLite statements, explicit named parameter arrays, two orders, a versioned composite cursor, bounded category cardinalities, explicit-empty zero-SQL behavior, and constant one-statement non-empty pages; no core, ORM, repository, helper, paginator, or dialect mechanism was added.
- Complete: Consumer Contract version 5 and ADR 023 application-owned terminal request summaries with generated 128-bit correlation IDs, `X-Request-ID` propagation, bounded per-connection budget and trace evidence, status-only known denials, class-only unknown failures, one sink invocation attempt, and sink-failure isolation; Strict Profile version 2 and the 2,300-line core ceiling remain unchanged.
- Alpha 1 publication state is external; `RELEASING.md` defines the proof without embedding mutable tag, package-host, or announcement state in the tagged artifact.
- Alpha 1 requires the contents of `skeleton/` to be exported as their own package root, the source-evaluation VCS override to be removed, `dev-main` to be replaced with the approved Packagist alpha constraint, and the resulting lockfile to be committed.
- Alpha 1 public proof installs the actual Packagist-preferred framework dist, compares it with `tools/package-files.txt`, and proves the documented `composer create-project --stability=alpha` path before announcement.
- Move type-sensitive architecture checks into tested PHPStan extensions.
- Detect unbounded reads with low false-positive rates and measure PHT006 gaps before considering any broader SQL provenance rule.
- Detect raw mixed arrays escaping named boundaries and add profile rules only after measuring false positives.
- Measure how many files and tokens common changes require.
- Build grounded-answer evaluations across installed revisions and measure unsupported claims, citation accuracy, uncertainty, and correct escalation of human decisions.
- After Alpha 1: finish the example's Create identity/conflict and extend the accepted application-owned request-policy evidence to additional applicable Create/List/Get operations without presenting domain policy as framework behavior.
- After those decisions: prove Update and Delete only after the example also records mutation concurrency, deletion, authorization, and conflict behavior.

## Phase 3: production evaluation

- Evaluate destination-specific terminal-summary buffering, retention, backpressure, and outage behavior without converting one sink invocation attempt into a durable-delivery claim; define security headers, streaming, and uploads explicitly.
- Complete: ADR 024 accepts one application-owned SQLite durable-job proof with commit-visible publication, finite envelopes, one-shot workers, fenced leases, bounded retries, redacted dead letters, and one idempotent database effect; it adds no framework queue API or cross-engine claim.
- Complete: ADR 025 accepts one application-owned explicit console and cron-friendly scheduled pass with typed bounded arguments, stable exit and stream behavior, an explicit UTC clock, fresh composition, and nonblocking same-host overlap; it adds no framework CLI, scheduler, daemon, slot ledger, catch-up, or distributed-coordination API.
- Benchmark literal and bounded typed routing plus database boundaries against equivalent base PHP.
- Run the same endpoint tasks across several AI models and classify mistakes.
- Prove one application-owned backend-specific typed cache-aside path, including cold-cache query scaling, invalidation failure, isolation, eviction, and concurrent-miss evidence; do not promote a generic framework API from one application.
- Stabilize the public API only after evidence from real applications.

## Deferred by design

Framework-owned authentication or authorization engines, credential issuance and lifecycle, CSRF policy, custom or shared session storage, middleware, a generic cache runtime, a generic or cross-engine queue runtime, templating, a generic validation engine, migrations, and dependency packages are not accepted by implication. Each needs a problem statement, an explicit execution path, a cost model, and a decision record. ADR 020 accepts an application-owned request-policy composition, not universal identity, tenant, permission, middleware, or request-context contracts. ADR 021 accepts operation-owned typed input boundaries and evidence, not a validator, sanitizer, hydrator, or automatic binder. ADR 024 accepts one SQLite-specific application recipe, not core job, worker, dispatcher, broker, or exactly-once contracts. ADR 025 accepts one application-owned console and single-host scheduled pass, not core CLI, scheduler, daemon, persistent slot, catch-up, process-manager, or distributed-coordination contracts. The accepted native session transport does not imply those adjacent capabilities. ADR 016 accepts cache policy, not a cache transport or universal API; the first backend-specific typed cache-aside proof remains application-owned and post-Alpha.
