# External integration contract

If this application has no external integrations, state that explicitly and remove its task-router entries.

| Integration | Boundary path | Contract source | Timeout | Retry owner | Idempotency rule |
| --- | --- | --- | --- | --- | --- |
| `{{INTEGRATION_1}}` | `{{INTEGRATION_1_PATH}}` | `{{INTEGRATION_1_CONTRACT}}` | {{INTEGRATION_1_TIMEOUT}} | {{INTEGRATION_1_RETRY_OWNER}} | {{INTEGRATION_1_IDEMPOTENCY}} |
| `{{INTEGRATION_2}}` | `{{INTEGRATION_2_PATH}}` | `{{INTEGRATION_2_CONTRACT}}` | {{INTEGRATION_2_TIMEOUT}} | {{INTEGRATION_2_RETRY_OWNER}} | {{INTEGRATION_2_IDEMPOTENCY}} |

## Cache backend boundary

- Adoption or `NOT_APPLICABLE(CACHE)`: {{CACHE_BACKEND_INTEGRATION_OR_NOT_APPLICABLE}}
- Named client boundary and contract/version source: {{CACHE_BACKEND_CLIENT_AND_CONTRACT_OR_NOT_APPLICABLE}}
- Connect, operation, and total timeout policy: {{CACHE_BACKEND_TIMEOUT_POLICY_OR_NOT_APPLICABLE}}
- Retry owner and maximum attempts: {{CACHE_BACKEND_RETRY_POLICY_OR_NOT_APPLICABLE}}
- Backend failure and authoritative-data fallback behavior: {{CACHE_BACKEND_FAILURE_POLICY_OR_NOT_APPLICABLE}}

A remote cache backend is an external integration even though cached data is disposable. Its failure path remains visible and bounded; do not silently retry, silently serve stale data, or report a cache failure as an authoritative-data miss unless the recorded application policy explicitly permits that outcome.

## Optional WebSocket runtime dependency

`NOT_APPLICABLE(WEBSOCKETS)`: no WebSocket runtime package or protocol integration is adopted by default. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and record the selected mature third-party package and exact supported version, contract source, non-secret configuration, failure ownership, update policy, and any external authentication, broker, proxy, or TLS boundary in `.ai/websockets.md` and `.ai/operations.md`. Keep retries, replay, acknowledgement, delivery, and backend-failure behavior explicit; do not invent a generic gateway, channel, broadcaster, pub/sub, or event-bus abstraction.

## Side-effect rules

- {{SIDE_EFFECT_RULE_1}}
- {{SIDE_EFFECT_RULE_2}}

## Failure behavior

- Publicly safe failures: {{PUBLIC_INTEGRATION_FAILURES}}
- Unknown or internal failures: {{UNKNOWN_INTEGRATION_FAILURE_POLICY}}
- Required audit or observability event: {{INTEGRATION_OBSERVABILITY_POLICY}}

An integration call must remain visible at a named boundary. Do not add implicit retries, silent fallbacks, or success responses after an unknown failure.
