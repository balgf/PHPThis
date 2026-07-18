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
- Use generated or explicitly approved synthetic fixtures. Never copy production payloads, credentials, customer identifiers, tokens, or private keys into tests.
