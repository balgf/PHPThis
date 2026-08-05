# Error contract

Failures must cross named boundaries as exceptions or explicit response values.

- Do not catch `Throwable` in handlers unless the handler can fully recover.
- Do not convert database failures to empty arrays, `null`, or success responses.
- Map only deliberately named exception classes to immutable responses in one visible `ErrorResponseRegistry` at the composition root.
- Use exact class matching. Never register broad built-ins such as `Throwable`, `RuntimeException`, `PDOException`, `JsonException`, or `UnexpectedValueException`.
- Let unknown failures reach the top-level handler, select the generic 500 response, and contribute only their concrete class to the one application-owned terminal summary attempt.
- Never include SQL credentials, parameter values, stack traces, or internal messages in public responses.
- Keep input failures deterministic and generic. For application-owned structured request-body content, complete the entire shape and native-type pass before value validation: malformed, missing, unknown, nullability, native-type, and nested-shape failures default to `400`, while a correctly shaped and typed payload with an unacceptable operation value defaults through its exact application-owned failure to `422`. Query, header, route, and transport representations retain their separately recorded contracts. Submitted field names and values, including secret-looking unknown fields, must not enter response bodies, headers, terminal summaries, logs, or traces.
- An application-owned request-handler decorator does not catch, wrap, translate, suppress, retry, or replace an exception from its own visible work or its one downstream handler. The exact exception continues to the existing error boundary; do not add decorator-owned fallback behavior or a second error-mapping path.

The sample maps `InvalidRequest` to generic `400 invalid_request`; application-owned `UnacceptableCreateUserValues` to generic `422 unprocessable_content` with message `Request content is unacceptable.`; `RequestBodyTooLarge` to 413; and `UnsupportedMediaType` to 415. Unknown failures receive a generic 500 response and put only their class name in the one common terminal event. Add a new mapping only with a named failure, exact public response, status-only terminal summary, and tests proving that internal messages do not escape. Never infer `409` or `422` directly from a database exception.

For file uploads, malformed multipart shape, partial input, and missing required file use the generic 400; total or operation file overflow uses 413; disabled or route-incompatible multipart uses 415. PHP temporary-storage, extension, provenance, move, permission, unknown upload-code, and other operational failures remain generic 500 outcomes. `ResponseEmissionFailed` occurs after request response selection and belongs to the visible front-controller emission boundary, not `ErrorResponseRegistry`.

ADR 042 supersedes ADR 021 only for this canonical status default. A mixed unacceptable value and wrong-typed field remains `400`, proving the complete structural phase takes precedence. An application may explicitly record and prove a different finite public status contract. A field-addressable error schema or localized response remains an application API decision, not permission to make `ErrorResponseRegistry` dynamic or to add a core exception, validator, or renderer.
