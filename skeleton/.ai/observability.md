# Application terminal-summary context

The starter follows installed ADR 023 with application types under `src/Observability/`, manually composed in `bootstrap.php` and invoked by `public/index.php`.

- Correlation: `CorrelationId::generate()` supplies 16 random bytes encoded as 32 lowercase hexadecimal characters; `TerminalRequestCoordinator` owns the single `X-Request-ID` response field.
- Sink: `ErrorLogRequestSummarySink` is the explicitly injected starter destination. The coordinator isolates its one invocation attempt from the final response; delivery is not guaranteed.
- Database sources: `NOT_APPLICABLE(no database)`. The list is empty and aggregate query evidence is zero.
- Scope: coordinator handling through response selection and the sink attempt. Earlier composition failures, process-fatal errors, response emission, and network delivery are outside the claim.
- Evidence: `composer test` exercises real composition, generated IDs, mapped input failure, unknown failure, and request-scoped freshness; the framework repository's observability proof covers the complete schema and throwing-sink cases.

Before database adoption, register every executable request-scoped connection in at most eight unique sources with distinct budgets and traces. Do not add an ORM, query builder, SQL/binding helper, framework logger, middleware, facade, global helper, per-query event, or hidden instrumentation.
