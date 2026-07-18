# External integration contract

If this application has no external integrations, state that explicitly and remove its task-router entries.

| Integration | Boundary path | Contract source | Timeout | Retry owner | Idempotency rule |
| --- | --- | --- | --- | --- | --- |
| `{{INTEGRATION_1}}` | `{{INTEGRATION_1_PATH}}` | `{{INTEGRATION_1_CONTRACT}}` | {{INTEGRATION_1_TIMEOUT}} | {{INTEGRATION_1_RETRY_OWNER}} | {{INTEGRATION_1_IDEMPOTENCY}} |
| `{{INTEGRATION_2}}` | `{{INTEGRATION_2_PATH}}` | `{{INTEGRATION_2_CONTRACT}}` | {{INTEGRATION_2_TIMEOUT}} | {{INTEGRATION_2_RETRY_OWNER}} | {{INTEGRATION_2_IDEMPOTENCY}} |

## Side-effect rules

- {{SIDE_EFFECT_RULE_1}}
- {{SIDE_EFFECT_RULE_2}}

## Failure behavior

- Publicly safe failures: {{PUBLIC_INTEGRATION_FAILURES}}
- Unknown or internal failures: {{UNKNOWN_INTEGRATION_FAILURE_POLICY}}
- Required audit or observability event: {{INTEGRATION_OBSERVABILITY_POLICY}}

An integration call must remain visible at a named boundary. Do not add implicit retries, silent fallbacks, or success responses after an unknown failure.
