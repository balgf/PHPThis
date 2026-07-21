# Application testing contract

## Complete validity gate

Run from the application root:

```bash
composer check
```

Its exact definition is in `composer.json`: `phpthis check` owns application-wide guardrails, the PHPThis Strict Profile, and maximum-level PHPStan; `composer test` owns behavior evidence.

## Automated behavior evidence

Every observable behavior change must add or update application-owned automated tests. The application owns the test library, runner, file placement, and organization; PHPThis does not require PHPUnit, Pest, a `tests/` directory, or a particular test category. Composer `scripts.test` must execute this evidence and return a non-zero status when it fails. Static analysis, documentation, manual verification, and a no-op test command are not behavior evidence.

## Focused commands

- Framework-owned profile and static analysis: `vendor/bin/phpthis check`
- Application behavior tests: `composer test`
- Inbound-data boundary tests: `NOT_APPLICABLE(INPUT)`
- Database integration tests: `NOT_APPLICABLE(no database)`
- Request-policy tests: `NOT_APPLICABLE(public liveness route only)`
- HTTP cache policy tests: `composer test`
- Cache integration tests: `NOT_APPLICABLE(CACHE)`
- Durable-job integration and lifecycle tests: `NOT_APPLICABLE(JOBS)`
- Application CLI and scheduler tests: `NOT_APPLICABLE(CLI)`
- Database migration tests: `NOT_APPLICABLE(MIGRATIONS)`

Focused commands shorten feedback but never replace the complete gate.

## Starter evidence

- Automated tests for every observable behavior change cover expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits.
- Test the exact health response, unknown route, unsupported method, malformed runtime input, oversized declared body, and real front controller.
- Test the terminal summary on health success, mapped failure, and unknown failure: exactly 32 lowercase-hex correlation characters, the identical `X-Request-ID`, generic outcome and status, class-only unknown failure, zero database aggregates and sources, complete request-value redaction, exactly one sink invocation attempt, and an unchanged response when the sink throws. This proves an attempt, not durable delivery or network emission.
- `NOT_APPLICABLE(INPUT_EVIDENCE)`: the health-only operation accepts no application-owned fields and constructs no typed request or command. Before adding product input, prove the exact final readonly value used by downstream behavior; absent and explicit-null behavior; unknown, wrongly typed, coercive, malformed, nested, and oversized representations; exact boolean, integer, enum, date, string, list, and object policy as applicable; every recorded preservation, rejection, or explicit normalization; stable generic secret-free failures; no operation-owned downstream I/O or mutation; and zero typed-seam calls when one exists. Record and separately bound any request-policy work ordered before parsing, and record the native JSON duplicate-key limitation unless a separately accepted parser proves rejection.
- `NOT_APPLICABLE(REQUEST_POLICY_EVIDENCE)`: before protecting a route, test unauthenticated, ordinary forbidden, cross-tenant, permitted, and unexpected policy-failure paths, exact policy order, every failing stage, zero protected queries and writes on denial, explicit principal and tenant delivery, generic disclosure and cache policy, status-only denial summaries, class-only unexpected-failure summaries, redaction from responses, summaries, and traces, and independent replacement of every policy implementation. A concrete credential parser also tests malformed and rejected input, and any policy I/O has a budget and trace distinct from protected work.
- `NOT_APPLICABLE(CRUD_EVIDENCE)`: the starter has no CRUD-shaped operations. For each operation later added, test its applicable route, identifier, conflict, pagination, missing-resource, concurrency, deletion, authorization, and audit policies; keep absent operations and concerns explicitly not applicable.
- `NOT_APPLICABLE(DATABASE_EVIDENCE)`: before database adoption, add engine integration and scale tests, adversarial bound-data tests, unknown-selector and oversized-list rejection before database work, query-budget and trace assertions, and dated evidence that runtime authority excludes migration and administrative capabilities. A cursor or bounded list additionally proves its recorded omitted and empty-input behavior, every supported cardinality and order, stable equal-key traversal in static fixtures, the recorded snapshot policy, and exact zero- versus non-zero-statement bounds. Report PHT006, tenant predicates, and adversarial bindings as path-specific rather than universal authorization or injection proof; do not treat base PDO transport evidence as application-SQL certification.
- `NOT_APPLICABLE(SESSION_EVIDENCE)`: before session adoption, prove anonymous access without storage, invalid, duplicated, attacker-selected, stale, and obsolete identifier rejection, exact state bounds, callback rollback, lock release, unissued-ID cleanup, delayed-response cookie safety, explicit invalidation, cookie attributes, isolated save-path enforcement, and concurrent requests. Add authentication-time regeneration, expiry, CSRF, authorization, and revocation tests only when those recorded policies apply; mark each absent concern explicitly not applicable.
- `HTTP_CACHE_EVIDENCE(NO_STORE)`: application behavior tests prove exact `Cache-Control: no-store` policy for health success, route miss, method rejection, and mapped invalid and oversized input, plus exact `Cache-Control: private, no-store` for unknown failure. No current response is storable, so freshness, validators, conditional requests, `Vary`, and public-cache isolation tests are not applicable. Every response path added later requires exact policy evidence; deployment separately verifies that each configured intermediary preserves the field.
- `NOT_APPLICABLE(CACHE_EVIDENCE)`: before cache adoption, prove cold miss and authoritative rebuild, warm hit without hidden database work, finite expiry, bounded payload parsing, corruption handling, backend failure and recovery, environment and tenant isolation, versioned-key rollover, post-commit invalidation and invalidation failure, a concurrent miss racing an authoritative write, and the recorded stampede behavior under concurrent requests. Inspect bounded hit, miss, write, invalidation, failure, and stampede summaries without logging keys or payloads; cold-cache database tests must still prove constant query scaling.
- `NOT_APPLICABLE(JOBS_EVIDENCE)`: before durable-job adoption, prove commit-visible publication and rollback exclusion, bounded stored-envelope parsing and finite dispatch, idle and success, exact retry delays from freshly observed failure time, completion rollback when handler time reaches lease expiry, lease expiry and stale-token fencing, final-attempt and poison dead letters, duplicate idempotent effects, real subprocess termination and post-expiry recovery, one delivery per fresh process, complete diagnostics redaction, and constant transition statement counts across queue sizes.
- `NOT_APPLICABLE(CLI_EVIDENCE)`: before an operational console or scheduler is adopted, execute the real console in fresh subprocesses and prove every finite command and exact argument bound, rejection before application I/O, exact exit and stdout/stderr bytes, every expected outcome, operational and unexpected failure redaction, explicit-clock cadence boundaries, not-due exclusion from work, nonblocking same-host overlap and lock cleanup, one-pass resource bounds, fresh HTTP and CLI state, and the recorded missed-run, catch-up, repeated-slot, supervisor, and topology limits. Do not treat Composer or `vendor/bin/phpthis check` as this evidence.
- `NOT_APPLICABLE(MIGRATION_EVIDENCE)`: before migrations are adopted, execute the real application console in fresh subprocesses and prove fresh-database manifest order, exact bounded ledger contents, unchanged no-op rerun, edited-content and malformed or overflowing ledger rejection before pending work, nonblocking same-host lock contention with no state change, per-migration rollback with earlier commits preserved, forward continuation, exact redacted output, fresh migration composition, and no schema work during HTTP startup. Do not generalize SQLite transaction or file-lock evidence to another engine or host topology.
- Do not add runtime or checker assertions for optional CRUD directory and naming choices.
- Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, tokens, or private keys into tests.
