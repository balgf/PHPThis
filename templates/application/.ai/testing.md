# Application testing contract

## Complete validity gate

Run from the application root:

```bash
{{PROJECT_CHECK_COMMAND}}
```

This command must call the installed `phpthis check` profile stage and then run application behavior tests. Record its exact definition at `{{PROJECT_CHECK_DEFINITION_PATH}}`. Do not add an application-owned PHPStan configuration, baseline, or inline suppression path.

## Focused commands

- Unit or behavior tests: `{{FOCUSED_TEST_COMMAND}}`
- Framework-owned profile and static analysis: `vendor/bin/phpthis check`
- Database integration tests: `{{DATABASE_TEST_COMMAND_OR_NOT_APPLICABLE}}`

Focused commands shorten feedback but never replace the complete validity gate.

## Required evidence

- Every change covers the expected success and failure behavior.
- Boundary parsing covers missing, unknown, wrongly typed, coercive, and oversized input as applicable.
- Authorization-sensitive behavior proves both allowed and denied outcomes.
- For each implemented CRUD-shaped operation, tests cover the applicable recorded route, identifier, conflict, pagination, missing-resource, mutation, concurrency, deletion, authorization, and audit policies; operations and concerns that do not exist are recorded as not applicable instead of receiving invented tests.
- Directory and naming choices in the optional CRUD profile are application context, not runtime or checker assertions.
- Database behavior runs against every recorded engine and version it relies on, compares small and materially larger fixtures, asserts equal statement counts, inspects distinct bounded traces per connection, and tests query-budget rejection.
- SQL-safety tests submit quotes, comment markers, operators, encoding edge cases, and other engine-relevant adversarial strings through named bindings and prove they remain data. Tests reject unknown structural selectors and unsupported or oversized list shapes before the query budget or trace changes.
- A structural choice test names the finite code-owned mapping exercised; successful sanitization or escaping is never accepted as evidence for SQL structure.
- Database operational evidence records the source and date used to verify the runtime identity's required and prohibited capabilities and its isolation from migration or administrative authority.
- Transaction tests prove rollback after the last allowed statement fails.
- External integrations test timeout, malformed response, idempotency, and retry ownership without contacting production systems.

## Test data safety

Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, access tokens, or private keys into tests or snapshots.
