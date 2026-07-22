# ADR 034: Application-owned WebSocket integration

Status: proposed

## Context

Some applications need a long-lived bidirectional connection, but PHPThis's core runtime is an HTTP request/response foundation. Turning WebSocket frames into PHPThis `Request` and `Response` values would blur transport lifetimes, authorization timing, backpressure, delivery, and shutdown responsibilities. A framework-owned WebSocket server, event loop, connection registry, broadcaster, or process supervisor would also add a second runtime model and third-party runtime dependencies to the core.

The narrower question is whether a PHPThis consumer can keep ordinary HTTP behavior unchanged while hosting one explicit WebSocket process through a mature third-party runtime. The integration must remain locally inspectable, typed after parsing, bounded under malformed or slow peers, and honest about best-effort delivery and deployment limits.

## Proposed decision

WebSocket integration remains application-owned. An adopting application selects and pins a mature third-party WebSocket runtime, creates a separate visible composition root, records its process and deployment topology, and passes an accepted message only to one narrowly named typed application operation. PHPThis adds no core WebSocket server, client, frame, connection, event-loop, daemon, supervisor, channel, broadcaster, pub/sub, retry, replay, acknowledgement, or delivery API and no runtime dependency.

WebSocket handshakes and frames never become PHPThis HTTP `Request` or `Response` values. They do not enter `Application`, `Router`, `RequestBoundary`, `RequestHandler`, an application-owned request-handler decorator, the HTTP terminal coordinator, or `ResponseEmitter`. Shared domain operations may be called only through ordinary explicit typed arguments after the WebSocket process has performed its own parsing, current authentication, and current authorization.

An adopting application records and tests:

- the exact raw request target and URI-normalization policy, origin policy, credential source and raw format before parser normalization, expiry and revocation behavior, and current per-command authorization;
- frame, aggregate-message, JSON depth, field, command-rate, byte-rate, connection, per-principal, concurrent-work, and outbound-frame bounds;
- application-message idle time, absolute connection lifetime, ping/pong policy, send deadline, close deadline, and maximum shutdown-join time;
- one-at-a-time or otherwise explicitly bounded operation execution and outbound backpressure without an unbounded application queue;
- ordering scope, reconnect behavior, duplicate processing, retry ownership, acknowledgement meaning, and the absence of unsupported replay or exactly-once claims;
- one finite redacted connection-summary attempt, including the difference between an invocation attempt and durable observability delivery;
- startup readiness and failure output, signal shutdown, listener release, restart ownership, forced-stop deadline, and the unproved production TLS, proxy, supervisor, scaling, affinity, broker, capacity, and availability concerns; and
- pure boundary tests plus real child-process and socket tests for success, denial, malformed input, resource bounds, slow consumers, reconnect, startup failure, and bounded cleanup.

Do not add generic channels, rooms, broadcasting, pub/sub, an event bus, middleware, a request or connection context bag, service location, automatic discovery, hidden binding, implicit retry, replay, deduplication, acknowledgement, reconnection, or exactly-once behavior. Do not adapt WebSocket work into the HTTP execution path to reuse its types.

The exact numeric limits belong to the adopting application's reviewed recipe and deployment evidence, not to the universal PHPThis contract. A different application must select values from its message sizes, traffic, runtime behavior, deployment topology, and failure budget, then prove them with its pinned dependency versions.

Consumer Contract version 9 and Strict Profile version 2 remain unchanged. This proposal adds no core PHP, Composer dependency, checker rule, `PHT` diagnostic, application directory requirement, or increase to the 2,600-line core ceiling.

## Independent consumer evidence

On 2026-07-22, a separate consumer was created from the public Packagist `phpthis/skeleton` `0.1.0-alpha.3` package, using the public `phpthis/framework` `0.1.0-alpha.3` package without a path or VCS repository override. The consumer pinned `amphp/websocket-server` `4.0.0` and kept its existing PHPThis `GET /health` path separate from one loopback-only `bin/websocket-server.php` process.

That recipe used one exact raw request target checked before URI normalization and one exact origin; a stateless cookie credential with strict raw-pair syntax checked at handshake and again after upgrade; current expiry and exact-resource authorization before each command and emitted frame; text-only 4,096-byte messages; JSON depth 4; one fixed versioned command; two global and two per-principal connections; eight transport frames and 8,192 transport bytes per second; five accepted commands per rolling second; eight sequential status frames at 20-millisecond intervals plus one completion frame; a 10-second application idle bound; a 15-second absolute lifetime; a five-second heartbeat; a 250-millisecond send deadline; a 500-millisecond close period; one pending connection-summary write with a 50-millisecond drop deadline; and a one-second application-handler shutdown join.

The complete consumer `composer check` passed maximum-level PHPStan and 365 application-owned assertions. Real child processes and sockets proved readiness, canonical raw-target rejection, generic handshake denial, handler-level current-authorization order, ordered streaming, malformed and oversized input handling, command and connection bounds, current credential expiry, heartbeat and application idle behavior, absolute lifetime, reconnect without replay, a saturated-socket slow-consumer deadline, bounded shutdown with non-draining summary output, occupied-port failure, signal shutdown, handler-summary completion, process cleanup, and listener release. Focused boundary evidence separately proved exact raw-cookie syntax and immediate rejection of a simultaneous second summary write. The proof also established that no WebSocket path referenced PHPThis HTTP request or response types and no framework source was changed.

Those numbers and Amp-specific behaviors are one reproducible application recipe. They are evidence that the boundary is viable, not framework defaults, production capacity evidence, a guarantee for another runtime version, or authorization to expose the process publicly. The consumer decision remains proposed pending accountable-human review of its exact limits, so this framework decision remains proposed as well.

## Consequences

PHPThis stays a zero-third-party-runtime-dependency HTTP foundation while applications can adopt a WebSocket runtime deliberately. The separate process makes event-loop constraints, credentials, resource bounds, delivery semantics, and operational ownership visible instead of hiding them behind an apparently familiar HTTP or middleware abstraction.

Applications repeat some protocol-specific composition and testing. That cost is intentional: the selected runtime, version, deployment topology, and message policy materially affect correctness. The framework documents a review profile and one measured recipe without pretending those facts are portable defaults.

HTTP and WebSocket entry points may call the same narrowly typed application operation, but neither transport may smuggle its own request, response, connection, or context object into that operation. Shared typed behavior does not imply shared transport lifecycle or error mapping.

## Reconsider when

At least two independent applications with different WebSocket behaviors and deployment topologies demonstrate the same irreducible typed primitive that cannot remain explicit in application code or in a mature runtime without causing a concrete correctness or review failure. Reconsider only that smallest proven primitive. Repetition of composition, a desire for channel-style convenience, or one runtime's API shape is not sufficient evidence for a framework WebSocket runtime.
