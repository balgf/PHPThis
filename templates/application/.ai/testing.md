# Application testing contract

## Complete validity gate

Run from the application root:

```bash
{{PROJECT_CHECK_COMMAND}}
```

This command must call the installed `phpthis check` profile stage and then run application behavior tests. Record its exact definition at `{{PROJECT_CHECK_DEFINITION_PATH}}`. Do not add an application-owned PHPStan configuration, baseline, or inline suppression path.

## Automated behavior evidence

Every observable behavior change must add or update application-owned automated tests. The application owns the test library, runner, file placement, and organization; PHPThis does not require PHPUnit, Pest, a `tests/` directory, or a particular test category. Composer `scripts.test` must execute this evidence and return a non-zero status when it fails. Static analysis, documentation, manual verification, and a no-op test command are not behavior evidence.

## Focused commands

- Unit or behavior tests: `{{FOCUSED_TEST_COMMAND}}`
- Framework-owned profile and static analysis: `vendor/bin/phpthis check`
- Database integration tests: `{{DATABASE_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- HTTP cache policy tests: `{{HTTP_CACHE_TEST_COMMAND}}`
- Cache integration tests: `{{CACHE_TEST_COMMAND_OR_NOT_APPLICABLE}}`

Focused commands shorten feedback but never replace the complete validity gate.

## Required evidence

- Automated tests for every observable behavior change cover expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits.
- Boundary parsing covers missing, unknown, wrongly typed, coercive, and oversized input as applicable.
- Authorization-sensitive behavior proves both allowed and denied outcomes.
- Adopted session transport proves anonymous access without storage, invalid, duplicated, attacker-selected, stale, and obsolete identifier rejection, exact state bounds, callback rollback, lock release, unissued-ID cleanup, delayed-response cookie safety, explicit invalidation, secure cookie attributes, isolated save-path enforcement, and concurrent requests under the recorded native file-storage topology. Authentication-time regeneration, idle and absolute expiry, CSRF, authorization, and revocation tests are required when those policies apply; each absent concern is explicitly not applicable. Session-free applications record only session transport and session-backed concerns as not applicable.
- For each implemented CRUD-shaped operation, tests cover the applicable recorded route, identifier, conflict, pagination, missing-resource, mutation, concurrency, deletion, authorization, and audit policies; operations and concerns that do not exist are recorded as not applicable instead of receiving invented tests.
- Directory and naming choices in the optional CRUD profile are application context, not runtime or checker assertions.
- Database behavior runs against every recorded engine and version it relies on, compares small and materially larger fixtures, asserts equal statement counts, inspects distinct bounded traces per connection, and tests query-budget rejection.
- SQL-safety tests submit quotes, comment markers, operators, encoding edge cases, and other engine-relevant adversarial strings through named bindings and prove they remain data. Tests reject unknown structural selectors and unsupported or oversized list shapes before the query budget or trace changes.
- A structural choice test names the finite code-owned mapping exercised; successful sanitization or escaping is never accepted as evidence for SQL structure.
- Database operational evidence records the source and date used to verify the runtime identity's required and prohibited capabilities and its isolation from migration or administrative authority.
- Transaction tests prove rollback after the last allowed statement fails.
- HTTP cache policy evidence proves exact `Cache-Control` behavior for every success, redirect, route miss, method rejection, mapped client error, unknown server error, authenticated response, and cookie-emitting response the application can produce. New and unreviewed paths prove `no-store`; every storable response proves finite freshness or revalidation, validators and `304` behavior where applicable, every `Vary` dimension, and the recorded browser or intermediary behavior. Public-cache tests prove that one identity, tenant, encoding, or representation cannot receive another's response.
- Adopted cache behavior proves cold miss and authoritative rebuild, warm hit without hidden database work, finite expiry, bounded payload parsing, corruption handling, backend failure and recovery, environment and tenant isolation, versioned-key rollover, post-commit invalidation and invalidation failure, a concurrent miss racing an authoritative write, and the recorded stampede behavior under concurrent requests. Cold-cache database tests still prove constant query scaling; a warm cache cannot conceal N+1 access.
- Cache evidence inspects bounded operation summaries for hits, misses, writes, invalidations, failures, and stampede outcomes without logging keys or payloads.
- External integrations test timeout, malformed response, idempotency, and retry ownership without contacting production systems.

## Test data safety

Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, access tokens, or private keys into tests or snapshots.
