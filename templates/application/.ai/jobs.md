# Application durable-job contract

- Adoption or `NOT_APPLICABLE(JOBS)`: {{JOBS_ADOPTION_OR_NOT_APPLICABLE}}
- Backend, exact version, database and filesystem topology: {{JOBS_BACKEND_VERSION_AND_TOPOLOGY_OR_NOT_APPLICABLE}}
- Producer transaction and commit-visible publication path: {{JOBS_PRODUCER_TRANSACTION_OR_NOT_APPLICABLE}}
- Finite envelope types, versions, payload bounds, and parser paths: {{JOBS_ENVELOPE_CONTRACT_OR_NOT_APPLICABLE}}
- Idempotency-key owner, grammar, effect boundary, and retention: {{JOBS_IDEMPOTENCY_POLICY_OR_NOT_APPLICABLE}}
- Lease duration, clock, maximum attempts, and finite backoff: {{JOBS_LEASE_RETRY_POLICY_OR_NOT_APPLICABLE}}
- Dead-letter codes, retention, inspection, replay, and cancellation: {{JOBS_DEAD_LETTER_POLICY_OR_NOT_APPLICABLE}}
- One-shot worker entrypoint, timeout, supervisor, and stop policy: {{JOBS_WORKER_LIFECYCLE_OR_NOT_APPLICABLE}}
- Query budgets, bounded traces, and behavior-test command: {{JOBS_EVIDENCE_OR_NOT_APPLICABLE}}

Before adoption, read installed `vendor/phpthis/framework/docs/jobs.md` and ADR 024. PHPThis provides no core queue or worker API. A job row may share atomicity with a business write only through the same `Connection`, transaction, and database. Record delivery as at-least-once, parse stored envelopes as untrusted input, use finite explicit dispatch, fence every transition with an unexpired opaque lease token, and make the concrete effect idempotent.

Do not add an ORM, repository, generic queue facade, event bus, automatic discovery, serialized PHP objects, transaction callback, hidden retry loop, in-process polling loop, or exactly-once external-effect claim.
