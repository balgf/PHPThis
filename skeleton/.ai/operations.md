# Application operations contract

## Local development

- Dependency install command: `composer install`
- Complete check command: `composer check`
- Local start command: `php -S 127.0.0.1:8080 -t public`
- Local stop action: stop the foreground development server.
- Required local services: none.

## Runtime

- Supported PHP version: 8.4
- Web runtime: PHP's built-in server for local verification only.
- WebSocket runtime: `NOT_APPLICABLE(WEBSOCKETS)`; `.ai/websockets.md` owns any future application-owned process and protocol decision.
- Worker runtime: `NOT_APPLICABLE(JOBS)`; `.ai/jobs.md` owns any future durable-job lifecycle decision.
- Operational application console and scheduler: `NOT_APPLICABLE(CLI)`; `.ai/cli.md` owns any future adoption.
- Database migrations: `NOT_APPLICABLE(MIGRATIONS)`; `.ai/migrations.md` owns any future adoption.
- Required extensions: `ext-pdo` and `ext-session` through the installed framework; the starter application opens no database connection and configures no session lifecycle.

## Configuration runtime

`.ai/configuration.md` is the single writable configuration authority and currently records `NOT_APPLICABLE(CONFIGURATION)`: the starter has no deployment input, secret-delivery path, rotation, reload, or configuration-startup failure. Record any later source, factory, validation, injection, authority, failure, rotation/restart, redaction, and configuration-test facts there rather than duplicating them in this operations guide. PHPThis performs no automatic dotenv load, secret-manager lookup, or hidden reload.

## Session runtime

`NOT_APPLICABLE`: the starter does not construct `SessionLifecycle` or create session storage. PHP 8.4 `ext-session` remains an installed framework platform requirement. Before adoption, record the native file handler and save-path ownership, exact PHP settings and dated source, cookie policy, deployment topology and concurrency evidence, and garbage collection in this section.

## Request-policy runtime

`NOT_APPLICABLE(REQUEST_POLICY)`: the public health-only starter accepts no credential and has no identity, tenant, authorization, credential verifier, expiry, rotation, revocation, or policy-source dependency. Before protecting a route, record those runtime facts, authorization-header forwarding, fail-closed dependency behavior, status-only known-denial summaries, and class-only unexpected-failure redaction without copying secrets or sensitive identifiers.

## WebSocket runtime

`NOT_APPLICABLE(WEBSOCKETS)`: the starter declares no listener, event-loop process, supervisor, proxy, TLS termination, connection registry, capacity, or scaling policy. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and record here the exact runtime package and version, separate entrypoint and process identity, listener and trusted-proxy boundary, startup and readiness contract, heartbeat, idle and absolute lifetime, send and close deadlines, connection and rate limits, graceful stop, forced-stop owner, deployment topology, capacity, scaling, incident policy, and dated operational source. Record WebSocket process configuration only in `.ai/configuration.md`, and record the connection-summary destination and its backpressure and outage behavior in the appropriate observability context without copying credentials, identifiers, headers, or frames.

## HTTP cache runtime

`HTTP_CACHE_POLICY(NO_STORE)`: every currently shipped response includes the `no-store` directive. Application behavior tests assert exact `Cache-Control: no-store` for health, route miss, method rejection, and mapped client failure, and exact `Cache-Control: private, no-store` for unknown failure. The starter records no production reverse-proxy, gateway, or CDN topology; before deployment, verify that every intermediary preserves the field. New response paths require an explicit application-owned policy and test.

## Server-side cache runtime

`NOT_APPLICABLE(CACHE)`: the starter configures no cache backend, client, extension, package, storage, or server-side caching. Before adoption, record here the backend product and supported version, dependency, deployment topology and environment isolation, capacity and eviction behavior, finite TTL policy, invalidation and stale-refill behavior, backend failure and recovery behavior, stampede owner and bounded lock or lease behavior, and dated operational source. Record cache process configuration only in `.ai/configuration.md`. Cache availability must not establish application correctness.

## Durable-job runtime

`NOT_APPLICABLE(JOBS)`: the starter has no job table, worker process, supervisor, timeout, forced termination, restart, clean-stop, capacity, retention, dead-letter inspection, replay, or incident policy. Before adoption, record those verified application-specific facts here and the transaction, envelope, idempotency, lease, retry, redaction, and evidence contract in `.ai/jobs.md`. Repetition must come from a supervisor starting fresh one-delivery processes, never an in-process polling or retry loop.

## Application CLI and scheduler

`NOT_APPLICABLE(CLI)`: the starter exposes no operational application console or scheduled pass. `composer check`, `composer test`, and `vendor/bin/phpthis check` are development and validity commands; they are not an application command map.

Before adoption, read installed `vendor/phpthis/framework/docs/cli.md` and replace `.ai/cli.md` with the sole console path, every finite command and operation, exact typed argument grammar and bounds, exit and stdout/stderr JSON contract, fresh composition, explicit clock and timezone, cadence, one-pass maximum, missed-run and catch-up policy, external invocation frequency, application-private same-host lock path and permissions, filesystem topology, contention and lock-failure behavior, timeout, restart, output redaction, and incident owner. Record CLI process configuration, startup failure, rotation/restart, and secret redaction only in `.ai/configuration.md`. Keep distributed coordination explicitly not applicable unless a separate backend-specific decision and evidence establish it.

## Database migrations

`NOT_APPLICABLE(MIGRATIONS)`: the starter has no migration process, elevated identity, database or lock path, authority-transition owner or activation stage, data-definition timeout, maintenance window, release sequence, backup, restore, or recovery procedure. HTTP startup performs no data-definition or authority-transition work.

- Accepted database-authority and release decision reference: `NOT_APPLICABLE(DATABASE)`.
- Pre-traffic authority gate, exact evidence, and accountable owner: `NOT_APPLICABLE(DATABASE)`; the starter has no connection, authority transition, dependent rollout stage, or database traffic.

Before adoption, read installed `vendor/phpthis/framework/docs/migrations.md` and complete `.ai/migrations.md` with the exact engine, accepted engine-specific decision, sole console command, exact required and prohibited migration capabilities, authority activation and deactivation owner and path, manifest and ledger bounds, transaction and lock topology, immutable forward-recovery policy, data-definition availability and timeout behavior, backup and restore requirements, exact finite output redaction, operational source and verification date, and real-console plus exact-engine authority evidence. Record `GRANT` and `REVOKE` only where the engine supports them. Record migration process configuration, startup failure, rotation/restart, and secret redaction only in `.ai/configuration.md`. Record here the application-owned order and compatibility among migration, authority activation, exact-engine verification, application rollout, traffic enablement, later authority deactivation, dependent-code drain, and namespace/object removal. Activation or verification failure stops its dependent stage. Shared-data execution still requires separate explicit human authorization.

## Deployment

`NOT_APPLICABLE`: the skeleton defines no environment, release, rollback, database-authority activation, traffic enablement, later authority deactivation, or production runtime policy. Add verified operational sources before deployment work. No universal deployment order is inferred; an adopted database path must activate and verify required authority before dependent traffic and drain dependent code before authority deactivation or namespace/object removal.

## Logging and observability

- Terminal request-summary runtime authority: `.ai/observability.md`; do not duplicate its correlation, sink, source, scope, or delivery facts here.
- `GET /health` is the starter liveness path; no readiness path exists.
- Database sources are `NOT_APPLICABLE(no database)`; query aggregates remain zero and the source list remains empty.
- HTTP cache storage and revalidation metrics are `NOT_APPLICABLE(no-store responses only)`; behavior tests verify the emitted policy, while production intermediary verification remains deployment-owned.
- Cache-operation summaries and hit, miss, failure, invalidation, and stampede metrics are `NOT_APPLICABLE(CACHE)`.

The sink invocation attempt does not guarantee durable delivery. `.ai/observability.md` owns the current destination and redaction facts.

## Prohibited operational actions

The skeleton authorizes no deployment, shared-data migration, credential rotation, user contact, or external-system mutation. An AI may inspect documented local state and run project checks, but it must not perform any of those actions unless the human explicitly authorizes that exact action after the application records the relevant operational policy.
