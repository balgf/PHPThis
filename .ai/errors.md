# Error contract

Failures must cross named boundaries as exceptions or explicit response values.

- Do not catch `Throwable` in handlers unless the handler can fully recover.
- Do not convert database failures to empty arrays, `null`, or success responses.
- Map only deliberately named exception classes to immutable responses in one visible `ErrorResponseRegistry` at the composition root.
- Use exact class matching. Never register broad built-ins such as `Throwable`, `RuntimeException`, `PDOException`, `JsonException`, or `UnexpectedValueException`.
- Let unknown failures reach the top-level exception handler and be logged once.
- Never include SQL credentials, parameter values, stack traces, or internal messages in public responses.
- Keep input failures deterministic and generic. Submitted field names and values, including secret-looking unknown fields, must not enter response bodies, headers, logs, or traces.

The sample maps `InvalidRequest` to 400, `RequestBodyTooLarge` to 413, and `UnsupportedMediaType` to 415. Unknown failures are logged once by class name without their message and receive a generic 500 response. Add a new mapping only with a named failure, exact public response, and tests proving that internal messages do not escape.

ADR 021 retains those prebuilt generic responses. A field-addressable error schema or different status is an application API decision, not permission to make `ErrorResponseRegistry` dynamic.
