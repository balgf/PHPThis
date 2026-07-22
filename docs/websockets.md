# Application-owned WebSocket integration

PHPThis has no native WebSocket runtime or API. An application that needs WebSockets owns a separate process and pins a mature third-party runtime. This guide is a proposed evidence profile based on the independent Alpha 3 consumer recorded in [ADR 034](decisions/034-application-owned-websocket-integration.md); it does not change Consumer Contract version 9 or Strict Profile version 2.

## Keep the transport boundary separate

Use one visible WebSocket composition root, separate from the PHPThis HTTP front controller. The selected runtime owns the listener, handshake protocol, frames, event loop, connections, ping/pong, and protocol close behavior. Application code owns endpoint policy, credentials, parsing, authorization, typed operation entry, limits, redaction, and operational evidence.

```text
WebSocket process
  -> pinned third-party runtime
    -> exact raw handshake target, origin, and credential policy
    -> bounded frame and aggregate-message ingestion
    -> operation-specific parser
      -> final readonly command
        -> current authentication and authorization
          -> one narrowly named typed application operation
            -> bounded sequential outbound frames
    -> one redacted connection-summary attempt
```

A frame is not a PHPThis HTTP `Request`, and an outbound message is not a PHPThis HTTP `Response`. Do not route frames through `Application`, `Router`, `RequestBoundary`, `RequestHandler`, a route-local request-handler decorator, the HTTP terminal coordinator, or `ResponseEmitter`. When HTTP and WebSocket paths share business behavior, call the same narrow typed operation with ordinary application values after each transport has completed its own boundary work.

Do not introduce generic channels, rooms, broadcasting, pub/sub, an event bus, middleware, a pipeline, a connection or request context bag, service location, automatic discovery, string class dispatch, reflection hydration, or hidden binding. The third-party protocol implementation is a selected application dependency, not a PHPThis extension surface.

## Record the application contract before implementation

The accountable human reviews a concrete application decision covering:

- the pinned runtime package and version, PHP version, process entry point, host and port, public or private exposure, and exact readiness and startup-failure bytes;
- the exact raw handshake request target, accepted URI form, path-normalization and query behavior, allowed origins, proxy-trust boundary, credential location and raw grammar before runtime normalization, issuance owner, cookie attributes where applicable, expiry, rotation, logout, revocation, and denial disclosure;
- current authentication after upgrade and current authorization before each command and any continuing or emitted work that depends on authorization;
- accepted frame kinds, aggregate-message bytes, frame bytes, UTF-8 policy, parser depth, exact fields, missing/null/unknown/duplicate-field behavior, command grammar, and finite dispatch;
- global and per-principal connection counts, raw frame and byte rates, accepted-command rate, concurrent work per connection, and outbound frame size and in-flight bounds;
- application-message idle, absolute lifetime, heartbeat, send, close, handler-join, and external forced-stop deadlines;
- ordering scope, duplicate behavior, reconnect behavior, acknowledgement meaning, retry or replay ownership, persistence, and delivery guarantees;
- terminal connection outcomes, finite counters, redacted fields, sink failure, and whether one invocation attempt is buffered or durably delivered; and
- TLS termination, reverse proxy, process supervision, restart, graceful and forced shutdown, listener release, memory/capacity limits, load balancing, affinity, multi-process state, broker topology, scaling, and incident recovery.

Credentials, cookies, headers, origins, paths, payloads, request or resource identifiers, principal values, frame contents, exception messages, stacks, and source locations do not belong in connection summaries. If an identifier is operationally necessary, define a code-owned correlation value rather than recording an externally supplied identifier.

## Parse once and authorize current state

Bound the transport frame and the complete reassembled message independently. Accept only the message kinds required by the operation. Parse external data once into a final readonly operation-specific command, reject missing, `null`, unknown, coercive, non-canonical, malformed, oversized, or too-deep input, and prove rejection invokes no application operation or downstream side effect.

Handshake authentication alone is insufficient for a long-lived connection. Authenticate explicitly after upgrade even when the handshake also rejects an invalid credential, and re-evaluate expiry, revocation, tenant, permission, and resource scope at the points required by the application's risk and message duration. Pass only narrow typed identity and resource values; do not store a current authorization decision in generic connection metadata, a global, a cache, or a PHPThis request.

Record the native parser's duplicate-field behavior. A runtime that keeps the last duplicate JSON object key has not proved duplicate-key rejection; either accept and document that precise limit or select a parser that can reject duplicates.

## Bound backpressure and lifetime

Await outbound sends and give each send a finite deadline. Keep the number of in-flight frames and queued application messages explicit and finite. Do not use an unbounded gateway or send queue. A slow peer receives the recorded close outcome; there is no hidden retry or alternate buffer.

Bound both application inactivity and absolute connection lifetime. Protocol ping/pong traffic does not necessarily prove the application is active, and a heartbeat implementation's exact queued-ping semantics belong to the pinned runtime version. A hard lifetime also limits retained parser, connection, and application state when another operation is suspended.

An orderly process stop closes listeners and clients, waits only a recorded finite period for active application handlers and their final summary attempts, then exits. An external supervisor owns restart and the final forced-stop deadline. Test an occupied port and a released port rather than assuming startup and shutdown worked.

## State delivery semantics exactly

Sequential awaited sends can prove application send order on one connection. They do not prove that a peer processed a message. A completion frame is not an acknowledgement unless the peer sends and the application durably records an explicit acknowledgement under a separately reviewed protocol.

Default to best-effort delivery with no replay across reconnects. State explicitly that duplicates may be processed again, ordering does not cross connections, and retry, deduplication, replay, resume, acknowledgement, and exactly-once delivery do not exist unless the application implements and proves each one. Do not hide any of them in a generic framework helper.

## Evidence required

The complete application gate covers pure boundary behavior and real process/socket behavior. Include, as applicable:

- successful handshake, typed dispatch, exact ordered output, and clean peer close;
- non-canonical raw targets before runtime URI normalization, including the application's chosen slash, query, and URI-form cases; absent/duplicate/wrong origin; and missing/duplicate/malformed/rejected/expired credential before credential-parser normalization;
- malformed JSON, wrong frame kind, invalid UTF-8, excessive nesting, missing/null/unknown/coercive fields, non-canonical values, and oversized frame and aggregate message;
- zero operation work after rejected input or authorization and current authorization during a live connection;
- global, per-principal, frame, byte, command, concurrent-work, outbound-size, and in-flight bounds;
- heartbeat, application idle, absolute lifetime, send deadline under a genuinely saturated socket, and exact close classifications;
- reconnect with the recorded replay behavior, duplicate behavior, and ordering only within its claimed scope;
- bounded redacted summary output and unchanged connection behavior when its sink fails;
- readiness without a blind startup sleep, occupied-port failure without hidden retry, signal shutdown, handler join, bounded child cleanup, and listener release; and
- a source-level proof that WebSocket code does not accept or construct PHPThis HTTP request or response values and that no framework source was changed.

Use real sockets for transport claims and a real child process for process claims. Fakes remain useful for parser, authorization, operation, redaction, and deadline units, but they cannot establish OS socket backpressure, signal handling, port ownership, or process cleanup.

## One measured recipe, not universal defaults

The independent public-Alpha-3-package consumer pinned Amp WebSocket Server 4.0.0 and proved one loopback-only endpoint with 4,096-byte text messages, JSON depth 4, two global and per-principal connections, eight raw frames and 8,192 raw bytes per second, five accepted commands per rolling second, one command at a time, eight sequential status frames at 20-millisecond intervals, a 10-second application idle limit, 15-second absolute lifetime, five-second heartbeat, 250-millisecond send deadline, 500-millisecond close period, one pending connection-summary write with a 50-millisecond drop deadline, and one-second handler join.

Its complete `composer check` passed maximum-level PHPStan and 365 application-owned assertions, including real subprocess/socket evidence, handler-level current-authorization ordering, canonical raw-target rejection, focused raw-cookie boundary evidence, simultaneous-summary-write rejection, saturated slow-consumer output, and non-draining summary-output shutdown. These values describe that synthetic local proof only. They are not PHPThis defaults, production recommendations, capacity findings, or evidence for another package version, network, proxy, supervisor, operation, payload, or threat model.

An application may choose different values or another mature runtime. It must record why those choices fit its workload and deployment, pin the exact dependency versions, keep the same explicit ownership boundaries, and rerun the corresponding real integration and resource evidence.
