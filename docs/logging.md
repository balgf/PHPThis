# Request-boundary logging

PHPThis currently logs only an unknown request failure. `UnknownFailureBoundary::logAndRespond` makes one `error_log` call containing the stable event `phpthis.request.unhandled` and the exception class, then returns the immutable generic 500 response.

The log deliberately omits the exception message, stack, request data, SQL, parameters, and query trace. The behavior test redirects PHP's error log to a temporary file and proves that the event appears exactly once, the private exception message is absent, and the public response is unchanged.

ADR 020's known authentication and authorization denials are exact-class mapped responses and deliberately emit no log. Its redaction proof places synthetic credentials and sensitive identifiers in internal failure data and proves they remain absent from both mapped responses and the existing class-only unknown-failure log. A later request-observability decision may add bounded denial outcome fields, but this slice adds no policy logger or payload capture.

This is a safe minimum, not the final observability design. Request IDs, a structured injected log sink, request metadata, and one bounded query-summary event remain Phase 3 work. Those additions must not introduce one log entry per SQL statement or read global request state outside the front controller.
