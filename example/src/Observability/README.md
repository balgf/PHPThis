# Example observability source boundary

This directory is application code and executable evidence, not framework core.

| Type | Responsibility |
| --- | --- |
| `CorrelationId` | generate or validate the closed 32-character lowercase-hex value |
| `QuerySummarySource` | pair one unique code-owned name with one distinct budget and trace |
| `RequestSummary` | construct the closed redacted version-2 payload, `document_cache` snapshot, and saturated aggregates |
| `RequestSummarySink` | expose the one application-owned destination seam |
| `ErrorLogRequestSummarySink` | JSON-encode the closed payload for the explicit example destination |
| `TerminalRequestCoordinator` | select success or generic failure response, own `X-Request-ID`, and isolate one sink attempt |

`Documents/GetDocument/DocumentDetailsCacheTrace` owns the finite cache snapshot beside the concrete cache-aside operation; observability consumes only its redacted array. Read `example/.ai/observability.md`, `docs/logging.md`, ADR 023, and ADR 028 before changing this path. Preserve the source/test pairing and keep database SQL, parameters, Redis calls, and execution outside this directory. No ORM, binding helper, framework logger, middleware, discovery, or durable-delivery claim belongs here.
