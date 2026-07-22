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
- Inbound-data boundary tests: `{{INPUT_BOUNDARY_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- Database integration tests: `{{DATABASE_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- Request-policy tests: `{{REQUEST_POLICY_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- Application-owned request-handler decorator tests: `{{REQUEST_HANDLER_DECORATOR_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- HTTP cache policy tests: `{{HTTP_CACHE_TEST_COMMAND}}`
- Cache integration tests: `{{CACHE_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- Durable-job integration and lifecycle tests: `{{JOBS_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- Application CLI and scheduler tests: `{{CLI_TEST_COMMAND_OR_NOT_APPLICABLE}}`
- Database migration tests: `{{MIGRATION_TEST_COMMAND_OR_NOT_APPLICABLE}}`

Focused commands shorten feedback but never replace the complete validity gate.

Terminal request-summary evidence covers success, mapped failure, known denial where applicable, unknown failure, exactly 32 lowercase-hex correlation characters, the identical `X-Request-ID`, zero and multiple distinct finite database sources, duplicate-query aggregation, budget overrun, trace truncation, status-only denials, class-only unknown failures, omission of every request and SQL value, exactly one sink invocation attempt, and an unchanged response when the sink throws. It does not claim durable delivery or successful network emission.

## Required evidence

- Automated tests for every observable behavior change cover expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits.
- Every resource identifier route proves its canonical `positive-int`, `uuid`, `ulid`, or genuinely opaque `token` representation reaches the handler through the matching `PathParameters` accessor. Invalid, normalized-only, alternate, or type-fallback spellings select `404` with zero handler and database work; a canonical valid path with the wrong method selects `405`. Successful handlers immediately wrap the unchanged value in an application-owned route-specific identifier and apply narrower domain checks before database work.
- Every inbound operation proves that downstream behavior uses its exact final readonly request or command after successful parsing. When a separate typed operation seam exists, PHPStan verifies its signature and runtime evidence verifies exact delivery; do not add that seam only to satisfy a test shape.
- Boundary parsing separately covers absent and explicitly null fields, unknown fields, wrongly typed values, coercive strings, boolean and integer traps, overflow, enum case, invalid or rolled dates, non-list arrays, nested arrays and objects, malformed UTF-8 or JSON, and exceeded byte, depth, field, list, item, and scalar bounds as applicable.
- Tests prove the recorded policy for representative padded, differently cased, Unicode, or otherwise variant input: preserve it unchanged, reject it, or apply one explicit normalization with tested ordering, pre- and post-transform bounds, collision outcome, and retained canonical value. No representation is silently changed or declared non-canonical without application policy.
- Every invalid representation proves no operation-owned downstream database work, session or cache mutation, filesystem or network action, or other business side effect. When a separate typed operation seam exists, assert zero calls. Record and separately bound any transport, session, authentication, tenant, authorization, or policy work deliberately ordered before parsing. The terminal summary carries only the generic known-failure outcome and status and never includes submitted values, credentials, or internal messages. The public response is finite, stable, generic, machine-readable, and free of submitted values and internal messages.
- A native JSON parser records and tests only the behavior it can prove. Do not claim repeated-object-key rejection when `json_decode` retains the final value; a duplicate-key-aware contract requires a separately accepted parser decision.
- Authorization-sensitive behavior proves both allowed and denied outcomes.
- Every protected route distinguishes unauthenticated, ordinary forbidden, cross-tenant, permitted, and unexpected policy-failure paths. Tests assert exact `authenticate -> resolve tenant -> authorize -> handler` order, zero later calls after failure, zero protected queries and writes on denial, explicit principal and tenant delivery to the protected operation, generic response and challenge policy, `private, no-store`, status-only denial summaries, class-only unexpected-failure summaries, redaction from responses, summaries, and traces, and independent replacement of every policy implementation. A concrete credential parser additionally tests absent, malformed, wrong-scheme, oversized, expired, revoked, and rejected credentials. Any policy I/O has a named budget and trace distinct from protected handler work; I/O-free policies record and prove that limit explicitly.
- Every adopted application-owned request-handler decorator proves its final class and one downstream `RequestHandler`, direct route-local construction and complete unrolled order, zero-or-one downstream invocation, the exact same immutable `Request` instance, applicable early-return and delegated outcomes, unchanged exception identity, and complete field preservation in any explicit immutable `Response` replacement. Assert every named side effect's attempt, byte, time, statement, query-budget, and ordering bound as applicable, and prove a short circuit performs no downstream query, mutation, or external effect. Also prove the decorator does not wrap or own `Application`, `RequestBoundary`, the terminal coordinator, `ResponseEmitter`, sessions, cache policy, request policy, or terminal observability.
- Adopted session transport proves anonymous access without storage, invalid, duplicated, attacker-selected, stale, and obsolete identifier rejection, exact state bounds, callback rollback, lock release, unissued-ID cleanup, delayed-response cookie safety, explicit invalidation, secure cookie attributes, isolated save-path enforcement, and concurrent requests under the recorded native file-storage topology. Authentication-time regeneration, idle and absolute expiry, CSRF, authorization, and revocation tests are required when those policies apply; each absent concern is explicitly not applicable. Session-free applications record only session transport and session-backed concerns as not applicable.
- For each implemented CRUD-shaped operation, tests cover the applicable recorded route, identifier, conflict, pagination, missing-resource, mutation, concurrency, deletion, authorization, and audit policies; operations and concerns that do not exist are recorded as not applicable instead of receiving invented tests.
- Directory and naming choices in the optional CRUD profile are application context, not runtime or checker assertions.
- Database behavior runs against every recorded engine and version it relies on, compares small and materially larger fixtures, asserts equal statement counts, inspects distinct bounded traces per connection, and tests query-budget rejection.
- SQL-safety tests submit quotes, comment markers, operators, encoding edge cases, and other engine-relevant adversarial strings through named bindings and prove they remain data. Tests reject unknown structural selectors and unsupported or oversized list shapes before the query budget or trace changes.
- A structural choice test names the finite code-owned mapping exercised; successful sanitization or escaping is never accepted as evidence for SQL structure.
- Every adopted cursor or bounded list proves its recorded omitted and empty-input behavior, every supported cardinality and order, stable equal-key traversal across static fixtures, rejection before database work, and the recorded snapshot or non-snapshot policy. Exact zero- and non-zero-statement expectations are asserted separately.
- PHT006, explicit tenant predicates, and adversarial binding probes are reported as path-specific evidence, not universal authorization, tenant-isolation, or SQL-injection proof. Application SQL is tested only on the engines and versions explicitly recorded in `.ai/data.md`; base PDO transport certification is not application-SQL certification.
- Database operational evidence records the source and date used to verify the runtime identity's required and prohibited capabilities and its isolation from migration or administrative authority.
- Transaction tests prove rollback after the last allowed statement fails.
- HTTP cache policy evidence proves exact `Cache-Control` behavior for every success, redirect, route miss, method rejection, mapped client error, unknown server error, authenticated response, and cookie-emitting response the application can produce. New and unreviewed paths prove `no-store`; every storable response proves finite freshness or revalidation, validators and `304` behavior where applicable, every `Vary` dimension, and the recorded browser or intermediary behavior. Public-cache tests prove that one identity, tenant, encoding, or representation cannot receive another's response.
- Adopted cache behavior proves cold miss and authoritative rebuild, warm hit without hidden database work, finite expiry, bounded payload parsing, corruption handling, backend failure and recovery, environment and tenant isolation, versioned-key rollover, post-commit invalidation and invalidation failure, a concurrent miss racing an authoritative write, and the recorded stampede behavior under concurrent requests. Cold-cache database tests still prove constant query scaling; a warm cache cannot conceal N+1 access.
- Cache evidence inspects bounded operation summaries for hits, misses, writes, invalidations, failures, and stampede outcomes without logging keys or payloads.
- Adopted durable jobs prove commit-visible publication and rollback exclusion, bounded stored-envelope parsing and finite dispatch, idle and success, exact retry delays from freshly observed failure time, completion rollback when handler time reaches lease expiry, lease expiry and stale-token fencing, final-attempt and poison dead letters, duplicate idempotent effects, real subprocess termination and post-expiry recovery, one delivery per fresh process, complete diagnostics redaction, and constant transition statement counts across small and materially larger queues.
- An adopted operational console executes in fresh subprocesses and proves every finite command and argument bound, rejection before application I/O, exact exit and stdout/stderr bytes, every expected outcome, operational and unexpected failure redaction, explicit-clock cadence boundaries, not-due exclusion from scheduled work, nonblocking same-host overlap and lock cleanup, one-pass resource bounds, fresh HTTP and CLI state, and every recorded missed-run, catch-up, repeated-slot, supervisor, and topology limit. A same-host lock is not distributed-coordination evidence.
- Adopted migrations execute through the real application console in fresh subprocesses and prove fresh-database manifest order, exact bounded ledger identifiers, positions, checksums, and application timestamps, unchanged no-op rerun, edited-content and malformed or overflowing ledger rejection before pending work, nonblocking same-host lock contention with no database state change, per-migration rollback with earlier commits preserved, forward continuation, exact exits and stream bytes, complete redaction, fresh migration composition, and no schema work during HTTP startup. SQLite transaction and file-lock evidence is not another engine or host-topology claim.
- External integrations test timeout, malformed response, idempotency, and retry ownership without contacting production systems.

## Test data safety

Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, access tokens, or private keys into tests or snapshots.
