# Application-owned WebSocket integration contract

WebSockets are an optional consuming-application capability, not a PHPThis runtime feature. Read `docs/websockets.md` and ADR 034 before proposing, adopting, or changing a WebSocket path. An application that does not use WebSockets records `NOT_APPLICABLE(WEBSOCKETS)` in its own `.ai/websockets.md`.

## Ownership and composition

- Select one mature third-party runtime and record its exact supported package and version, process entrypoint, composition root, event-loop model, and non-secret configuration source in application context.
- Keep the runtime, connection lifecycle, parser, authentication, authorization, operation, outbound encoder, and observability types application-owned and manually composed.
- Keep WebSocket messages outside PHPThis HTTP `Request`, `Response`, `Router`, `RequestBoundary`, `ResponseEmitter`, and terminal request-summary types. A frame becomes one operation-specific final readonly command, not an HTTP request.
- Invoke one narrowly named typed application operation only after the complete message is parsed and current authentication and authorization succeed.
- Keep blocking database, filesystem, network-client, sleep, and subprocess work out of event-loop callbacks unless the selected runtime documents and the application proves an explicit nonblocking boundary.

## Required application decisions

Before adoption, replace `NOT_APPLICABLE(WEBSOCKETS)` with verified facts for:

- exact raw handshake request target, accepted URI form, path-normalization and query policy, method, origin policy, credential source, raw credential grammar before parser normalization, expiry, revocation, disclosure, and protocol-level failure behavior;
- current authentication and authorization before operation work and at every later point where stale authority could expose or mutate protected state;
- text or binary policy, frame and aggregate-message byte limits, encoding, parser depth, exact fields, absent-versus-null and unknown-field behavior, canonical representations, finite dispatch, and duplicate-key proof limit;
- global and per-principal connection limits, inbound frame and byte rates, accepted-command rate, command concurrency, outbound frame size, queue or in-flight bound, send deadline, and backpressure behavior;
- application idle, protocol heartbeat, absolute lifetime, close deadline, shutdown join, restart, supervisor, listener, proxy, TLS, load-balancer, scaling, and capacity ownership;
- ordering, duplicate processing, reconnect, replay, retry, acknowledgement, resume, and delivery semantics without implying guarantees the runtime and application do not prove; and
- one bounded redacted connection-summary attempt, allowed finite counters and outcomes, destination behavior, failure isolation, and prohibited credential, identifier, header, payload, frame, exception-message, and stack-trace data.

## Verification

Application-owned automated tests must prove pure parser, policy, authorization, operation, encoding, bound, and redaction behavior. Real child-process and socket tests must additionally exercise startup readiness without a blind sleep, accepted and denied handshakes, successful ordered messages, malformed and oversized traffic, transport close behavior, connection and rate limits, heartbeat, idle and absolute lifetime, backpressure, reconnect without unrecorded replay, occupied-listener failure, signal-driven shutdown, bounded process exit, and cleanup in `finally`.

Record the exact local or integration topology tested. Do not generalize loopback evidence to production TLS, proxy correctness, supervisor recovery, public capacity, multi-process state, horizontal scaling, or availability.

## Forbidden direction

Do not add framework-owned WebSocket, event-loop, connection-manager, daemon, or supervisor primitives. Do not add a generic middleware, gateway, channel, room, broadcaster, pub/sub abstraction, event bus, service locator, context bag, discovery mechanism, application send queue, hidden retry, replay, acknowledgement, resume, or exactly-once claim. Reconsider shared PHPThis behavior only after separate consuming applications prove the same irreducible need.
