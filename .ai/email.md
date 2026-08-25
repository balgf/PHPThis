# Application-owned email contract

Start transactional-email composition or delivery work with `docs/email.md` and this current guide. Add `.ai/configuration.md`, `.ai/jobs.md`, `.ai/operations.md`, `.ai/request-policy.md`, `.ai/observability.md`, or `.ai/testing.md` only when the task enters that concern.

## Ownership and adoption

- Keep transactional email composition and delivery application-owned. PHPThis provides no framework mailer, renderer, notification system, queue, worker, webhook receiver, provider, or email runtime dependency.
- Require the application's `.ai/integrations.md` to record exactly `NOT_APPLICABLE(EMAIL)` or one adopted policy. Do not add another always-read application context file.
- An adoption records the operation-specific final readonly message or view model, finite templates, pinned maintained mail or MIME package and exact version, one named transport boundary and provider contract, typed addresses and canonical links, text and optional HTML policy, size and execution bounds, timeouts, rate limits, idempotency, receipts, ambiguous failure, redaction, dependency-update policy, and evidence.
- Keep configuration, durable publication and jobs, operations, request policy and security, observability, and testing in their existing guides. Reference those owners rather than copying their policy into `.ai/integrations.md` or this guide.
- Preserve the starter's no-external-service default and the checked welcome-delivery database-effect proof. That proof does not establish a provider, delivery, queue, or production-readiness claim.

## Verification

Test bounded deterministic composition separately from the selected transport. An adoption proves exact template selection, address and canonical-link construction, text and HTML policy, size bounds, timeout and rate behavior, duplicate and ambiguous outcomes, receipt handling, redaction, and its selected real-provider or contract evidence. Keep credentials, message bodies containing sensitive data, provider secrets, and production payloads out of context, fixtures, logs, and reports.
