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
- Database integration tests: `NOT_APPLICABLE(no database)`
- HTTP cache policy tests: `composer test`
- Cache integration tests: `NOT_APPLICABLE(CACHE)`

Focused commands shorten feedback but never replace the complete gate.

## Starter evidence

- Automated tests for every observable behavior change cover expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits.
- Test the exact health response, unknown route, unsupported method, malformed runtime input, oversized declared body, and real front controller.
- `NOT_APPLICABLE(CRUD_EVIDENCE)`: the starter has no CRUD-shaped operations. For each operation later added, test its applicable route, identifier, conflict, pagination, missing-resource, concurrency, deletion, authorization, and audit policies; keep absent operations and concerns explicitly not applicable.
- `NOT_APPLICABLE(DATABASE_EVIDENCE)`: before database adoption, add engine integration and scale tests, adversarial bound-data tests, unknown-selector and oversized-list rejection before database work, query-budget and trace assertions, and dated evidence that runtime authority excludes migration and administrative capabilities.
- `NOT_APPLICABLE(SESSION_EVIDENCE)`: before session adoption, prove anonymous access without storage, invalid, duplicated, attacker-selected, stale, and obsolete identifier rejection, exact state bounds, callback rollback, lock release, unissued-ID cleanup, delayed-response cookie safety, explicit invalidation, cookie attributes, isolated save-path enforcement, and concurrent requests. Add authentication-time regeneration, expiry, CSRF, authorization, and revocation tests only when those recorded policies apply; mark each absent concern explicitly not applicable.
- `HTTP_CACHE_EVIDENCE(NO_STORE)`: application behavior tests prove exact `Cache-Control: no-store` policy for health success, route miss, method rejection, mapped invalid and oversized input, and unknown failure. No current response is storable, so freshness, validators, conditional requests, `Vary`, and public-cache isolation tests are not applicable. Every response path added later requires exact policy evidence; deployment separately verifies that each configured intermediary preserves the field.
- `NOT_APPLICABLE(CACHE_EVIDENCE)`: before cache adoption, prove cold miss and authoritative rebuild, warm hit without hidden database work, finite expiry, bounded payload parsing, corruption handling, backend failure and recovery, environment and tenant isolation, versioned-key rollover, post-commit invalidation and invalidation failure, a concurrent miss racing an authoritative write, and the recorded stampede behavior under concurrent requests. Inspect bounded hit, miss, write, invalidation, failure, and stampede summaries without logging keys or payloads; cold-cache database tests must still prove constant query scaling.
- Do not add runtime or checker assertions for optional CRUD directory and naming choices.
- Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, tokens, or private keys into tests.
