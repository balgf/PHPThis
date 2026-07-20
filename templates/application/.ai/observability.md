# Application terminal-summary context

Read installed `vendor/phpthis/framework/docs/observability/README.md`, `vendor/phpthis/framework/docs/logging.md`, and `vendor/phpthis/framework/docs/decisions/023-application-owned-terminal-request-summaries.md` before replacing these placeholders. This file records project facts only; it does not redefine the installed event schema.

- Coordinator path: `{{TERMINAL_REQUEST_SUMMARY_COORDINATOR_PATH}}`
- Correlation generation and response propagation: {{CORRELATION_ID_AND_RESPONSE_PROPAGATION}}
- Sink interface, implementation, and destination: {{TERMINAL_REQUEST_SUMMARY_SINK_AND_DESTINATION}}
- Every executable request-scoped database source, or empty list: {{TERMINAL_SUMMARY_DATABASE_SOURCES_OR_EMPTY}}
- Destination buffering, retention, backpressure, and outage policy: {{TERMINAL_SUMMARY_DESTINATION_POLICY}}
- Focused response, redaction, source-bound, and throwing-sink tests: `{{TERMINAL_SUMMARY_TEST_COMMAND}}`

Record at most eight unique non-sensitive source names with distinct budgets and traces. One invocation attempt never means durable delivery. Do not add framework observability types, middleware, facades, global helpers, per-query events, hidden instrumentation, an ORM, or an SQL/binding helper.
