# Starter observability source boundary

This directory is application-owned starter code, not a PHPThis core namespace.

| Type | Responsibility |
| --- | --- |
| `CorrelationId` | generate or validate the closed 32-character lowercase-hex value |
| `QuerySummarySource` | pair one unique code-owned name with one distinct budget and trace |
| `RequestSummary` | construct the closed redacted version-1 payload and saturated aggregates |
| `RequestSummarySink` | expose the one application-owned destination seam |
| `ErrorLogRequestSummarySink` | JSON-encode the closed payload for the explicit starter destination |
| `TerminalRequestCoordinator` | select success or generic failure response, own `X-Request-ID`, and isolate one sink attempt |

Read `.ai/observability.md` and installed `vendor/phpthis/framework/docs/observability/README.md` before changing this path. Keep source composition explicit in `bootstrap.php`; do not add an ORM, SQL/binding helper, framework logger, middleware, facade, discovery mechanism, hidden instrumentation, or durable-delivery claim.
