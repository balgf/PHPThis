# Application operations contract

## Local development

- Dependency install command: `{{DEPENDENCY_INSTALL_COMMAND}}`
- Local bootstrap command: `{{LOCAL_BOOTSTRAP_COMMAND}}`
- Local start command: `{{LOCAL_START_COMMAND}}`
- Local stop command: `{{LOCAL_STOP_COMMAND_OR_NOT_APPLICABLE}}`
- Required local services: {{REQUIRED_LOCAL_SERVICES}}

## Runtime

- Supported PHP version: 8.4
- Web runtime: {{WEB_RUNTIME}}
- WebSocket runtime: `NOT_APPLICABLE(WEBSOCKETS)`; `.ai/websockets.md` owns any future application-owned process and protocol decision.
- Worker runtime: {{WORKER_RUNTIME_OR_NOT_APPLICABLE}}
- Operational application console and scheduler: `.ai/cli.md`
- Database migrations: `.ai/migrations.md`
- Required extensions: `ext-pdo` and `ext-session` plus {{ADDITIONAL_REQUIRED_PHP_EXTENSIONS_OR_NONE}}

## Configuration runtime

`.ai/configuration.md` is the single writable authority for the boundary source, external names, deployment injection owner, process-specific factories and final readonly types, validation, authority separation, startup failure, rotation/restart, secret redaction, and configuration evidence. Do not duplicate those facts or placeholders here. This operations guide records only the surrounding process, supervisor, topology, capacity, and incident facts. PHPThis performs no automatic dotenv load, secret-manager lookup, or hidden reload.

## Session runtime

- Adoption: {{SESSION_ADOPTION_OR_NOT_APPLICABLE}}
- Native handler, exact effective save path, ownership, and application isolation: {{SESSION_NATIVE_FILE_STORAGE_POLICY_OR_NOT_APPLICABLE}}
- Required PHP session settings and dated verification source: {{SESSION_PHP_SETTINGS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}
- Cookie name, `Secure`, SameSite, and environment policy: {{SESSION_COOKIE_POLICY_OR_NOT_APPLICABLE}}
- Deployment topology, concurrent-request evidence, and lock assumptions: {{SESSION_TOPOLOGY_AND_CONCURRENCY_POLICY_OR_NOT_APPLICABLE}}
- Garbage collection and obsolete-file cleanup: {{SESSION_GARBAGE_COLLECTION_POLICY_OR_NOT_APPLICABLE}}

`ext-pdo` and `ext-session` are installed-framework requirements even when database or session state is not adopted. A database adoption additionally records its actual `ext-pdo_*` driver. Session adoption additionally requires the native `files` handler, an exact save path proven isolated to this application identity, the fixed runtime settings, and cleanup retention beyond the absolute session lifetime described in installed `vendor/phpthis/framework/docs/sessions.md`. Do not copy session IDs, cookie values, CSRF tokens, or snapshots into this file.

## Request-policy runtime

- Adoption or `NOT_APPLICABLE(REQUEST_POLICY)`: {{REQUEST_POLICY_RUNTIME_ADOPTION_OR_NOT_APPLICABLE}}
- Credential verifier and supported scheme: {{CREDENTIAL_VERIFIER_AND_SCHEME_OR_NOT_APPLICABLE}}; external configuration source and typed factory: `.ai/configuration.md`
- Authorization-header forwarding and trusted-proxy policy: {{AUTHORIZATION_HEADER_FORWARDING_POLICY_OR_NOT_APPLICABLE}}
- Credential expiry, rotation, revocation, and verifier-failure behavior: {{CREDENTIAL_LIFECYCLE_AND_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Tenant and permission source availability and failure behavior: {{TENANT_AND_AUTHORIZATION_SOURCE_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Known-denial status-only summary and unexpected-failure class-only redaction: {{REQUEST_POLICY_LOGGING_POLICY_OR_NOT_APPLICABLE}}

ADR 023 supersedes the earlier no-denial-log wording. A known denial receives only the common terminal summary's generic known-failure outcome and response status; an unexpected failure contributes only its concrete class. Never record credentials, complete sensitive identifiers, or internal policy messages, and do not add a second policy event.

## WebSocket runtime

`NOT_APPLICABLE(WEBSOCKETS)`: this template declares no listener, event-loop process, supervisor, proxy, TLS termination, connection registry, capacity, or scaling policy. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and record here the exact runtime package and version, separate entrypoint and process identity, listener and trusted-proxy boundary, startup and readiness contract, heartbeat, idle and absolute lifetime, send and close deadlines, connection and rate limits, graceful stop, forced-stop owner, deployment topology, capacity, scaling, incident policy, and dated operational source. Record the process configuration source, factory, final readonly type, failure, rotation/restart, and secret-redaction contract only in `.ai/configuration.md`. Record the connection-summary destination and its backpressure and outage behavior in the appropriate observability context without copying credentials, identifiers, headers, or frames.

## HTTP cache runtime

- Required runtime policy: {{HTTP_CACHE_RUNTIME_POLICY}}
- Browser, reverse-proxy, CDN, and gateway topology: {{HTTP_CACHE_INTERMEDIARY_TOPOLOGY_OR_NOT_APPLICABLE}}
- Header transformation, purge, and deployment behavior: {{HTTP_CACHE_DEPLOYMENT_POLICY_OR_NOT_APPLICABLE}}
- Operational source and dated verification: {{HTTP_CACHE_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Do not infer intermediary behavior from local responses. Verify every production cache layer and record whether it honors `Cache-Control`, validators, conditional requests, and every declared `Vary` dimension.

## Server-side cache runtime

- Adoption or `NOT_APPLICABLE(CACHE)`: {{CACHE_RUNTIME_ADOPTION_OR_NOT_APPLICABLE}}
- Backend product, supported version, and client boundary: {{CACHE_BACKEND_AND_VERSION_OR_NOT_APPLICABLE}}
- Deployment topology and application/environment isolation: {{CACHE_TOPOLOGY_AND_ISOLATION_OR_NOT_APPLICABLE}}
- Process configuration source and typed factory: `.ai/configuration.md`; required extension or package: {{CACHE_DEPENDENCY_OR_NOT_APPLICABLE}}
- Capacity, eviction, and finite TTL policy: {{CACHE_CAPACITY_EVICTION_AND_TTL_POLICY_OR_NOT_APPLICABLE}}
- Backend failure, degradation, and recovery behavior: {{CACHE_FAILURE_AND_RECOVERY_POLICY_OR_NOT_APPLICABLE}}
- Stampede owner, lock or lease bound, and loser behavior: {{CACHE_STAMPEDE_POLICY_OR_NOT_APPLICABLE}}
- Concurrent miss versus authoritative-write stale-refill policy: {{CACHE_STALE_REFILL_RUNTIME_POLICY_OR_NOT_APPLICABLE}}
- Operational source and dated verification: {{CACHE_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Cache availability never establishes application correctness. Record whether each operation bypasses the cache, fails closed, or returns an explicitly stale bounded result when the backend is unavailable; do not add an implicit fallback or unbounded retry.

## Durable-job runtime

- Adoption or `NOT_APPLICABLE(JOBS)`: `.ai/jobs.md`
- Worker supervisor and one-shot invocation policy: {{JOBS_SUPERVISOR_AND_INVOCATION_POLICY_OR_NOT_APPLICABLE}}
- Process timeout, forced termination, restart, and clean-stop policy: {{JOBS_PROCESS_LIFECYCLE_POLICY_OR_NOT_APPLICABLE}}
- Capacity, retention, dead-letter inspection, and incident policy: {{JOBS_OPERATIONS_POLICY_OR_NOT_APPLICABLE}}

The application supervisor creates repetition by starting fresh one-delivery processes. Do not add an in-process database polling loop, mutable worker container, hidden retry loop, or unrecorded signal behavior.

## Application CLI and scheduler

- Adoption or `NOT_APPLICABLE(CLI)`: `.ai/cli.md`
- Console process identity: {{CLI_PROCESS_IDENTITY_OR_NOT_APPLICABLE}}; process configuration source and typed factory: `.ai/configuration.md`
- Lock-file ownership, permissions, cleanup, and filesystem topology: {{CLI_LOCK_OPERATIONS_OR_NOT_APPLICABLE}}
- Cron or supervisor frequency, timeout, forced termination, restart, and incident policy: {{CLI_SUPERVISOR_POLICY_OR_NOT_APPLICABLE}}
- Operational assumptions source and verified date: {{CLI_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Keep command, argument, exit, stream, clock, cadence, one-pass, repeated-slot, composition, and evidence facts in `.ai/cli.md`. Framework `vendor/bin/phpthis` remains the checker, not the application console. Do not add command discovery, dynamic class or service resolution, a generic scheduler facade, daemon, hidden loop, or an unrecorded second command path. A same-host file lock is topology-dependent and does not prove distributed or sequential-in-slot deduplication.

## Database migrations

- Adoption or `NOT_APPLICABLE(MIGRATIONS)`: `.ai/migrations.md`
- Accepted database-authority and release decision reference: {{DATABASE_AUTHORITY_AND_RELEASE_DECISION_SOURCE_OR_NOT_APPLICABLE}}
- Migration process identity: {{MIGRATION_PROCESS_IDENTITY_OR_NOT_APPLICABLE}}; process configuration source and typed factory: `.ai/configuration.md`
- Lock-file ownership, permissions, cleanup, and filesystem topology: {{MIGRATION_LOCK_OPERATIONS_OR_NOT_APPLICABLE}}
- DDL timeout, maintenance window, availability, capacity, and termination policy: {{MIGRATION_EXECUTION_OPERATIONS_OR_NOT_APPLICABLE}}
- Backup, restore, failed-deployment, and incident procedure: {{MIGRATION_RECOVERY_OPERATIONS_OR_NOT_APPLICABLE}}
- Authority-transition owner, stage, and non-HTTP execution path; `GRANT`/`REVOKE` only where supported: {{DATABASE_AUTHORITY_TRANSITION_OPERATIONS_OR_NOT_APPLICABLE}}
- Migration, authority activation, exact-engine verification, application rollout, and traffic-enablement order: {{DATABASE_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}
- Pre-traffic authority gate, exact evidence, and accountable owner: {{DATABASE_PRE_TRAFFIC_AUTHORITY_GATE_EVIDENCE_AND_OWNER_OR_NOT_APPLICABLE}}
- Old/new-code compatibility, abort or forward-correction path, dependent-code drain, later authority deactivation, and namespace/object-removal order: {{DATABASE_COMPATIBILITY_DEACTIVATION_AND_REMOVAL_POLICY_OR_NOT_APPLICABLE}}
- Operational assumptions source and verified date: {{MIGRATION_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Keep identifier, manifest, checksum, ledger, transaction, immutable-history, authority-transition implementation, output, redaction, and evidence facts in `.ai/migrations.md`. This guide owns the application-specific release sequence; there is no universal order beyond activating and verifying required authority before dependent traffic and draining dependent code before later authority deactivation or namespace/object removal. The application console is the only migration execution path; never migrate or transition authority from HTTP startup, framework `vendor/bin/phpthis`, or dependency hooks. Shared-data migration requires separate explicit human authorization even when the command exists.

## Environments and deployment

- Environment names and purpose: {{ENVIRONMENT_MODEL}}
- Operational assumptions last verified from: {{OPERATIONS_SOURCE_AND_VERIFIED_DATE}}
- Deployment source of truth: `{{DEPLOYMENT_SOURCE_PATH}}`
- Release command or workflow: {{RELEASE_WORKFLOW}}
- Rollback procedure: {{ROLLBACK_PROCEDURE_REFERENCE}}

## Logging and observability

- Terminal request-summary runtime authority: `.ai/observability.md`
- HTTP cache status, revalidation, and intermediary observability: {{HTTP_CACHE_OBSERVABILITY_POLICY_OR_NOT_APPLICABLE}}
- Cache-operation summary and hit, miss, failure, invalidation, and stampede metrics: {{CACHE_OBSERVABILITY_POLICY_OR_NOT_APPLICABLE}}
- Adopted health, readiness, or non-HTTP probes; exact claim, entrypoint or composition root, inherited dependencies and synchronous destinations including the terminal summary sink, bounded work, failure response or process behavior, local or deployment operations owner or explicit N/A, and application-test or deployment evidence with verified environment/date: {{HEALTH_AND_READINESS_PATHS}}
- Alert or incident reference: `{{INCIDENT_REFERENCE}}`

`Connection::connect()` constructs PDO eagerly and, depending on the selected driver and DSN, may perform I/O or fail during composition. A route sharing a composition root that opens a required external-service connection must not be described as external-service-independent liveness. A synchronous terminal sink also remains part of the probe path even when its failure cannot alter the selected response; record its destination and latency bound before making an independence claim. Record what the selected execution path actually proves and whether a composition failure occurs before routing, response selection, or the terminal request-summary attempt. Connection construction alone does not prove exact-statement database authority or complete readiness. PHPThis does not select a probe route, dependency set, restart policy, or deployment topology, and it does not authorize a hidden bypass or second HTTP execution path.

Keep destination buffering, retention, backpressure, outage, and incident facts in `.ai/observability.md`; do not restate the installed event schema here. A sink invocation attempt is not durable delivery.

## Prohibited operational actions

- {{PROHIBITED_OPERATION_1}}
- {{PROHIBITED_OPERATION_2}}

An AI may inspect documented local state and run project checks. It must not deploy, migrate shared data, rotate credentials, contact users, or mutate external systems unless the accountable human explicitly authorizes that exact action.
