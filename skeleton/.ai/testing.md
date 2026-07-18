# Application testing contract

## Complete validity gate

Run from the application root:

```bash
composer check
```

Its exact definition is in `composer.json`: `phpthis check` owns application-wide guardrails, the PHPThis Strict Profile, and maximum-level PHPStan; `composer test` owns behavior evidence.

## Focused commands

- Framework-owned profile and static analysis: `vendor/bin/phpthis check`
- Application behavior tests: `composer test`
- Database integration tests: `NOT_APPLICABLE(no database)`

Focused commands shorten feedback but never replace the complete gate.

## Starter evidence

- Test the exact health response, unknown route, unsupported method, malformed runtime input, oversized declared body, and real front controller.
- `NOT_APPLICABLE(CRUD_EVIDENCE)`: the starter has no CRUD-shaped operations. For each operation later added, test its applicable route, identifier, conflict, pagination, missing-resource, concurrency, deletion, authorization, and audit policies; keep absent operations and concerns explicitly not applicable.
- `NOT_APPLICABLE(DATABASE_EVIDENCE)`: before database adoption, add engine integration and scale tests, adversarial bound-data tests, unknown-selector and oversized-list rejection before database work, query-budget and trace assertions, and dated evidence that runtime authority excludes migration and administrative capabilities.
- Do not add runtime or checker assertions for optional CRUD directory and naming choices.
- Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, tokens, or private keys into tests.
