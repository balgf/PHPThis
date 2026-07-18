# Request-boundary logging

PHPThis currently logs only an unknown request failure. `UnknownFailureBoundary::logAndRespond` makes one `error_log` call containing the stable event `phpthis.request.unhandled` and the exception class, then returns the immutable generic 500 response.

The log deliberately omits the exception message, stack, request data, SQL, parameters, and query trace. The behavior test redirects PHP's error log to a temporary file and proves that the event appears exactly once, the private exception message is absent, and the public response is unchanged.

This is a safe minimum, not the final observability design. Request IDs, a structured injected log sink, request metadata, and one bounded query-summary event remain Phase 3 work. Those additions must not introduce one log entry per SQL statement or read global request state outside the front controller.
