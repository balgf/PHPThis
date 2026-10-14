# Swoole stateless HTTP proposal — issue 100

Status: the maintainer approved the isolated proof on 2026-10-14. Consumer-contract adoption remains a separate decision after reviewing the code and measurements.

## Proposed direction

Permit one explicitly selected HTTP deployment transport for an adopting application: the conventional PHP front controller or a stateless Swoole listener. Both use the existing PHPThis request parser, boundary, router, handlers, response values and terminal-summary path. Keep the runtime extension and adapter application-owned; add no framework server, event loop, container, global request context or runtime dependency.

The inspected prototype uses PHP 8.4.19 and Swoole 6.2.3. Its listener passes native metadata and a bounded body to the existing parser, then sends the immutable response through an explicit Swoole emitter. Bounded in-memory `data://` input reuses `RequestReader` without modifying the core API. The application constructs a fresh PDO connection, budget, trace, correlation ID, handlers and coordinator on every request. None is shared between coroutines. Runtime hooks and connection pooling are disabled.

The first eligible slice would cover bounded stateless JSON operations and existing routing/error responses. Sessions, uploaded or downloaded files, streaming, WebSockets, HTTP/2, TLS and production deployment need their own evidence; this experiment supplies none of those claims. The fixture's caller-selected tenant number only exercises partition/request-state isolation. It supplies no authentication or authorization policy.

## Equivalent boundaries required before adoption

- Preserve request-target, header and body limits and parser rejection before operation SQL; keep normalization and collision handling visible. Document native-parser transformations rather than promising raw bytes already lost upstream.
- Select the generic response before per-request composition. Keep unknown failures private, retain one correlation ID and terminal summary after successful coordinator construction, and keep emission failure separate. Verify effective native error-disclosure settings in the actual PHP-FPM and Swoole processes.
- Keep query budgets, traces, transactions and application identity state scoped to one request. Resolve blocking I/O, timeouts, connection authority and cleanup explicitly. Coroutines alone do not turn synchronous PDO into nonblocking I/O.
- Record finite listener, worker, connection, coroutine, input/output and shutdown limits. Prove failure recovery, repeated/concurrent isolation, bounded shutdown and complete owned-resource cleanup.
- Keep framework diagnostics and maximum-level analysis. A successful prototype is not a passing Contract 18 application check: that contract expressly excludes an alternate HTTP adapter until its replacement policy is accepted.

## Experiment and decision

The checked fixture compares the same handlers, constant SQL and response semantics through Nginx to one PHP-FPM worker and one Swoole worker. Both use the same image, PHP build, OPcache/JIT policy, fresh-connection policy, local SQLite fixture and summary destination. A separate one-CPU client runs alternating-order repetitions at concurrency 1 and 16, with 2- and 50-row responses. It reports request-level median/p95/p99, throughput, client CPU consumption and raw samples. The fixed local read-heavy workload cannot establish an application-wide or production speedup.

The adoption decision should use the observed correctness, complexity and measurements. If worthwhile, accept a narrowly scoped replacement for Contract 18's sole HTTP adapter clause, update its current owners and installed-consumer evidence, and verify the supported deployment profile before calling Swoole an application option. Otherwise retain this proof as evidence and keep the conventional path.
