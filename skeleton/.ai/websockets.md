# Application WebSocket contract

`NOT_APPLICABLE(WEBSOCKETS)`: the health-only starter has no WebSocket dependency, listener, route, event loop, connection state, message contract, or WebSocket process.

Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md`, obtain accountable human approval for the consequential security and operational choices, and replace this marker with verified application-owned facts. Name the accepted decision record and record:

- the exact mature third-party runtime package and version, separate process entrypoint and composition root, event-loop model, listener, non-secret configuration source, and blocking-I/O policy;
- the exact raw handshake request target, accepted URI form, path-normalization and query policy, method, origin, raw credential grammar before parser normalization, expiry, revocation, disclosure, and protocol-failure policy;
- current authentication and authorization before the typed operation and at every later protected effect or emitted frame where authority may have changed;
- the accepted text or binary policy, frame and aggregate-message byte limits, encoding, parser depth, exact fields, absent-versus-null and unknown-field policy, canonical representations, duplicate-key proof limit, final readonly command, and finite dispatch;
- global and per-principal connection limits, inbound frame and byte rates, accepted-command rate, command concurrency, outbound frame and in-flight bounds, send deadline, backpressure, idle, heartbeat, absolute lifetime, close, shutdown, restart, and supervisor behavior;
- ordering, duplicate processing, reconnect, retry, replay, acknowledgement, resume, and delivery semantics; and
- one bounded redacted connection-summary attempt, its allowed finite fields and counters, destination and failure isolation, plus the values that must never be recorded.

Keep every WebSocket type and the selected runtime application-owned and manually composed. Frames never become PHPThis HTTP `Request` or `Response` values and never enter the HTTP router, request boundary, response emitter, or terminal request-summary schema. Parse one complete bounded message into one operation-specific final readonly command, then invoke one narrowly named typed application operation after current policy succeeds. The existing `GET /health` path remains an independent HTTP path.

Application-owned automated tests must include pure parser, policy, operation, encoding, resource-bound, and redaction evidence plus real child-process and socket evidence for startup, accepted and denied handshakes, ordered messages, malformed and oversized traffic, rate and connection limits, heartbeat, idle and absolute lifetime, backpressure, reconnect, listener failure, signal shutdown, bounded exit, and cleanup. Record the exact tested topology and do not generalize it to production TLS, proxy, supervisor, capacity, scaling, or availability behavior.

Do not add PHPThis WebSocket primitives, HTTP adaptation, a generic middleware, gateway, channel, room, broadcaster, pub/sub abstraction, event bus, service locator, context bag, discovery, application send queue, hidden retry, replay, acknowledgement, resume, or exactly-once claim.
