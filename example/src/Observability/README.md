# Example observability source boundary

This directory is application code and executable evidence, not framework core.

| Type | Responsibility |
| --- | --- |
| `CorrelationId` | generate or validate the closed 32-character lowercase-hex value |
| `QuerySummarySource` | pair one unique code-owned name with one distinct budget and trace |
| `RequestSummary` | construct the closed redacted version-1 payload and saturated aggregates |
| `RequestSummarySink` | expose the one application-owned destination seam |
| `ErrorLogRequestSummarySink` | JSON-encode the closed payload for the explicit example destination |
| `TerminalRequestCoordinator` | select success or generic failure response, own `X-Request-ID`, and isolate one sink attempt |

Read `example/.ai/observability.md`, `docs/logging.md`, and ADR 023 before changing this path. Preserve the source/test pairing and keep database SQL, parameters, and execution outside this directory. No ORM, binding helper, framework logger, middleware, discovery, or durable-delivery claim belongs here.
