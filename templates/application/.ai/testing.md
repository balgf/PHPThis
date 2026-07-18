# Application testing contract

## Complete validity gate

Run from the application root:

```bash
{{PROJECT_CHECK_COMMAND}}
```

This command must cover maximum-level PHPStan analysis, the PHPThis Strict Profile, application guardrails, and behavior tests. Record its exact definition at `{{PROJECT_CHECK_DEFINITION_PATH}}`.

## Focused commands

- Unit or behavior tests: `{{FOCUSED_TEST_COMMAND}}`
- Static analysis: `{{STATIC_ANALYSIS_COMMAND}}`
- Profile and architecture guardrails: `{{GUARDRAIL_COMMAND}}`
- Database integration tests: `{{DATABASE_TEST_COMMAND_OR_NOT_APPLICABLE}}`

Focused commands shorten feedback but never replace the complete validity gate.

## Required evidence

- Every change covers the expected success and failure behavior.
- Boundary parsing covers missing, unknown, wrongly typed, coercive, and oversized input as applicable.
- Authorization-sensitive behavior proves both allowed and denied outcomes.
- Database behavior compares small and materially larger fixtures, asserts equal statement counts, inspects bounded query traces, and tests query-budget rejection.
- Transaction tests prove rollback after the last allowed statement fails.
- External integrations test timeout, malformed response, idempotency, and retry ownership without contacting production systems.

## Test data safety

Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, access tokens, or private keys into tests or snapshots.
