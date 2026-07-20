# Example terminal-summary context

The executable example follows ADR 023 with application types under `src/Observability/`, manual composition in `bootstrap.php`, and invocation from `public/index.php`.

- Correlation: one `CorrelationId::generate()` per request-scoped composition; the coordinator replaces any application response spelling with the generated `X-Request-ID`.
- Sink: one injected `ErrorLogRequestSummarySink`; sink failure cannot alter the selected response and one attempt is not durable delivery.
- Sources: `list_users`, `get_user`, `create_user`, `get_document`, and `list_documents`. Each owns the exact operation connection's distinct budget and trace.
- Event: the closed ADR 023 schema only. Denials carry generic known-failure outcome and status; unknown failures add only concrete class.
- Evidence: `tests/observability.php`, included by the repository test runner, owns schema, correlation, source, budget, redaction, freshness, and throwing-sink proof.

All SQL remains complete raw SQLite text with explicit named parameter arrays at direct `Connection` call sites. Do not add an ORM, repository, query builder, paginator, SQL/binding/placeholder helper, generated SQL, framework logger, middleware, facade, per-query event, or hidden instrumentation.
