# Application terminal-summary context

The starter follows installed ADR 023 with application types under `src/Observability/`, manually composed in `bootstrap.php` and invoked by `public/index.php`.

- Correlation: `CorrelationId::generate()` supplies 16 random bytes encoded as 32 lowercase hexadecimal characters; `TerminalRequestCoordinator` owns the single `X-Request-ID` response field.
- Sink: `ErrorLogRequestSummarySink` is the explicitly injected starter boundary and calls deployment-configured `error_log` synchronously before the coordinator returns. The coordinator isolates its one invocation attempt from the selected response, but the starter records no destination or latency bound; delivery is not guaranteed, and external-service independence is not established.
- Database sources: `NOT_APPLICABLE(no database)`. The list is empty and aggregate query evidence is zero.
- Scope: coordinator handling through response selection and the sink attempt. Earlier composition failures, process-fatal errors, response emission, and network delivery are outside the claim.
- Evidence: `composer test` exercises real composition, generated IDs, success and mapped input summaries, unknown-failure class-only capture, exactly one sink attempt, throwing-sink response isolation, request-scoped freshness, and the real front controller. The framework repository's observability proof covers the complete schema.

Before database adoption, register every executable request-scoped connection in at most eight unique sources with distinct budgets and traces. Do not move terminal observability into an application-owned request-handler decorator or add an ORM, query builder, SQL/binding helper, framework logger, generic or framework logging middleware, facade, global helper, per-query event, or hidden instrumentation.
