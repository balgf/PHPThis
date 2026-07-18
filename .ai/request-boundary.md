# Request boundary contract

`RequestBoundary` is the one outer transport boundary. It calls `RequestReader`, delegates the resulting immutable request to one `RequestHandler`, and consults `ErrorResponseRegistry` only when a failure crosses that path.

Rules:

- Read PHP superglobals only in `example/public/index.php` and pass them explicitly to the boundary.
- Configure the maximum body bytes and `php://input` URI explicitly in the composition root.
- Keep the fixed metadata bounds: 8,192 request-target bytes, 64 top-level query parameters, 64 headers, and 8,192 bytes per header value.
- Strip only the query suffix from `REQUEST_URI`. Do not URL-decode, collapse slashes, resolve dot segments, or reinterpret an absolute URI.
- Normalize CGI header names once to lowercase hyphenated names. Collapse identical CGI/HTTP duplicates, but reject conflicting collisions, non-string values, control bytes, and invalid tokens.
- Preserve query values as `mixed`; an endpoint-specific named boundary must parse any values it uses.
- Keep error registration literal and exact-class-only. Unknown failures must be rethrown to the front controller.
- Pass an unknown failure to `UnknownFailureBoundary`; it logs once without the message, SQL, parameters, or trace, then returns the generic 500 response.

Do not turn this boundary into middleware, an event pipeline, a request helper bag, automatic input binding, or a service locator.
