# Error contract

Failures must cross named boundaries as exceptions or explicit response values.

- Do not catch `Throwable` in handlers unless the handler can fully recover.
- Do not convert database failures to empty arrays, `null`, or success responses.
- Map known domain exceptions to HTTP responses in one visible application-level registry.
- Let unknown failures reach the top-level exception handler and be logged once.
- Never include SQL credentials, parameter values, stack traces, or internal messages in public responses.

The error registry is intentionally not implemented in Phase 0; unhandled exceptions remain visible during development.
