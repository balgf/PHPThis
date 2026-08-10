# External integration contract

If this application has no external integrations, state that explicitly and remove its task-router entries.

| Integration | Boundary path | Contract source | Timeout | Retry owner | Idempotency rule |
| --- | --- | --- | --- | --- | --- |
| `{{INTEGRATION_1}}` | `{{INTEGRATION_1_PATH}}` | `{{INTEGRATION_1_CONTRACT}}` | {{INTEGRATION_1_TIMEOUT}} | {{INTEGRATION_1_RETRY_OWNER}} | {{INTEGRATION_1_IDEMPOTENCY}} |
| `{{INTEGRATION_2}}` | `{{INTEGRATION_2_PATH}}` | `{{INTEGRATION_2_CONTRACT}}` | {{INTEGRATION_2_TIMEOUT}} | {{INTEGRATION_2_RETRY_OWNER}} | {{INTEGRATION_2_IDEMPOTENCY}} |

## Transactional email boundary

- Adoption or `NOT_APPLICABLE(EMAIL)`: {{EMAIL_ADOPTION_OR_NOT_APPLICABLE}}
- Operation-specific final readonly message or view model and finite template selection: {{EMAIL_COMPOSITION_BOUNDARY_OR_NOT_APPLICABLE}}
- Pinned maintained mail or MIME package, exact version, supported contract, and update policy: {{EMAIL_PACKAGE_CONTRACT_OR_NOT_APPLICABLE}}
- Intentional UTF-8 `text/plain`, optional `text/html`, semantic parity, locale, encoding, rendering, compilation, cache, and development-versus-production policy: {{EMAIL_RENDERING_POLICY_OR_NOT_APPLICABLE}}
- Context-sensitive encoding, deliberate raw boundaries, typed addresses, envelope sender, visible `From`, `Reply-To`, and canonical absolute HTTPS link policy: {{EMAIL_OUTPUT_AND_ADDRESS_POLICY_OR_NOT_APPLICABLE}}
- Recipient, header, body, attachment, inline-image, total encoded byte, and execution bounds or unsupported features: {{EMAIL_RESOURCE_BOUNDS_OR_NOT_APPLICABLE}}
- One named transport boundary, provider or SMTP contract, endpoint and TLS owner, connect/operation/total timeouts, rate limits, provider identifiers, and redacted failures: {{EMAIL_TRANSPORT_POLICY_OR_NOT_APPLICABLE}}
- Business-event and provider idempotency, durable request and receipt identifiers, ambiguous timeout, finite retry/backoff, dead letter, reconciliation, compensation, and operator replay: {{EMAIL_DELIVERY_POLICY_OR_NOT_APPLICABLE}}
- Bounce, complaint, suppression, unsubscribe, and delivery-status webhook adoption or explicit non-applicability: {{EMAIL_WEBHOOK_POLICY_OR_NOT_APPLICABLE}}
- Sender/domain verification, consent/legal, retention, reputation, provider-account operations, security, observability, and evidence owners: {{EMAIL_OPERATIONS_AND_EVIDENCE_OR_NOT_APPLICABLE}}

When email is adopted, read installed `vendor/phpthis/framework/docs/email.md`. Record exact input names without values and typed configuration in `.ai/configuration.md`, durable deferred intent in `.ai/jobs.md`, deployment and reconciliation in `.ai/operations.md`, and evidence in `.ai/testing.md`. PHPThis provides no mail transport or email-rendering runtime; the application owns composition, dependencies, credentials, templates, sender identity, delivery policy, and evidence. Do not use native `mail()`, hand-build MIME, or add a framework mailer, renderer, notification system, queue, worker, webhook receiver, provider, or hidden retry.

## Cache backend boundary

- Adoption or `NOT_APPLICABLE(CACHE)`: {{CACHE_BACKEND_INTEGRATION_OR_NOT_APPLICABLE}}
- Named client boundary and contract/version source: {{CACHE_BACKEND_CLIENT_AND_CONTRACT_OR_NOT_APPLICABLE}}
- Connect, operation, and total timeout policy: {{CACHE_BACKEND_TIMEOUT_POLICY_OR_NOT_APPLICABLE}}
- Retry owner and maximum attempts: {{CACHE_BACKEND_RETRY_POLICY_OR_NOT_APPLICABLE}}
- Backend failure and authoritative-data fallback behavior: {{CACHE_BACKEND_FAILURE_POLICY_OR_NOT_APPLICABLE}}

A remote cache backend is an external integration even though cached data is disposable. Its failure path remains visible and bounded; do not silently retry, silently serve stale data, or report a cache failure as an authoritative-data miss unless the recorded application policy explicitly permits that outcome.

## Optional WebSocket runtime dependency

`NOT_APPLICABLE(WEBSOCKETS)`: no WebSocket runtime package or protocol integration is adopted by default. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and record the selected mature third-party package and exact supported version, contract source, integration-failure ownership, update policy, and any external authentication, broker, proxy, or TLS boundary in `.ai/websockets.md` and `.ai/operations.md`. Record process configuration only in `.ai/configuration.md`. Keep retries, replay, acknowledgement, delivery, and backend-failure behavior explicit; do not invent a generic gateway, channel, broadcaster, pub/sub, or event-bus abstraction.

## Side-effect rules

- {{SIDE_EFFECT_RULE_1}}
- {{SIDE_EFFECT_RULE_2}}

## Failure behavior

- Publicly safe failures: {{PUBLIC_INTEGRATION_FAILURES}}
- Unknown or internal failures: {{UNKNOWN_INTEGRATION_FAILURE_POLICY}}
- Required audit or observability event: {{INTEGRATION_OBSERVABILITY_POLICY}}

An integration call must remain visible at a named boundary. Do not add implicit retries, silent fallbacks, or success responses after an unknown failure.
