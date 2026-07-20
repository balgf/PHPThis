# Request boundary contract

`RequestBoundary` is the one framework transport boundary. The application-owned terminal coordinator wraps it without becoming framework middleware. `RequestBoundary` calls `RequestReader`, optionally begins one `SessionLifecycle`, delegates the resulting immutable request to one `RequestHandler`, and consults `ErrorResponseRegistry` only when a failure crosses that path.

Rules:

- Read PHP superglobals only in `example/public/index.php`, pass them explicitly to the application-owned terminal coordinator, and let it call the one bounded `RequestBoundary`.
- Configure the maximum body bytes and `php://input` URI explicitly in the composition root.
- Keep the fixed metadata bounds: 8,192 request-target bytes, 64 top-level query parameters, 64 headers, and 8,192 bytes per header value.
- Strip only the query suffix from `REQUEST_URI`. Do not URL-decode, collapse slashes, resolve dot segments, or reinterpret an absolute URI.
- Normalize CGI header names once to lowercase hyphenated names. Collapse identical CGI/HTTP duplicates, but reject conflicting collisions, non-string values, control bytes, and invalid tokens.
- Preserve query values as `mixed`; an endpoint-specific named boundary must parse any values it uses.
- Keep error registration literal and exact-class-only. Unknown failures must be rethrown to the front controller.
- When the application-owned terminal coordinator catches an unknown failure, retain it only long enough to record its concrete class and call `UnknownFailureBoundary::respond()` without passing the failure to select the generic 500 response.
- When sessions are configured, call `SessionLifecycle::begin` only after successful request parsing, finish normal and registered-error responses, and abort before rethrowing an unknown failure.
- Beginning a lifecycle records the cookie header only. Do not start native storage or emit a cookie unless application code explicitly reads or mutates session state.
- A protected matched route may delegate to one application-owned action-specific request-policy adapter. That adapter executes authentication, tenant resolution, and authorization inside the explicit handler path; it does not change `RequestBoundary`, `Application`, `Request`, or the routing contract.

Do not put session, principal, tenant, or authorization state on `Request` or turn this boundary into middleware, an event pipeline, a request helper bag, automatic input binding, a policy registry, or a service locator.

ADR 023 changes no `RequestBoundary` responsibility. The application front-controller path around it owns generated correlation, `X-Request-ID`, finite budget/trace observation, the terminal event, and one failure-isolated sink invocation attempt. Read `.ai/observability.md`; do not move that work into the framework boundary or `Connection`.
