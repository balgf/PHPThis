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
- Required extensions: `ext-session` plus {{ADDITIONAL_REQUIRED_PHP_EXTENSIONS_OR_NONE}}

## Session runtime

- Adoption: {{SESSION_ADOPTION_OR_NOT_APPLICABLE}}
- Native handler, exact effective save path, ownership, and application isolation: {{SESSION_NATIVE_FILE_STORAGE_POLICY_OR_NOT_APPLICABLE}}
- Required PHP session settings and dated verification source: {{SESSION_PHP_SETTINGS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}
- Cookie name, `Secure`, SameSite, and environment policy: {{SESSION_COOKIE_POLICY_OR_NOT_APPLICABLE}}
- Deployment topology, concurrent-request evidence, and lock assumptions: {{SESSION_TOPOLOGY_AND_CONCURRENCY_POLICY_OR_NOT_APPLICABLE}}
- Garbage collection and obsolete-file cleanup: {{SESSION_GARBAGE_COLLECTION_POLICY_OR_NOT_APPLICABLE}}

`ext-session` is an installed-framework requirement even when session state is not adopted. Adoption additionally requires the native `files` handler, an exact save path proven isolated to this application identity, the fixed runtime settings, and cleanup retention beyond the absolute session lifetime described in installed `vendor/phpthis/framework/docs/sessions.md`. Do not copy session IDs, cookie values, CSRF tokens, or snapshots into this file.

## Request-policy runtime

- Adoption or `NOT_APPLICABLE(REQUEST_POLICY)`: {{REQUEST_POLICY_RUNTIME_ADOPTION_OR_NOT_APPLICABLE}}
- Credential verifier, supported scheme, and non-secret configuration source: {{CREDENTIAL_VERIFIER_AND_CONFIGURATION_OR_NOT_APPLICABLE}}
- Authorization-header forwarding and trusted-proxy policy: {{AUTHORIZATION_HEADER_FORWARDING_POLICY_OR_NOT_APPLICABLE}}
- Credential expiry, rotation, revocation, and verifier-failure behavior: {{CREDENTIAL_LIFECYCLE_AND_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Tenant and permission source availability and failure behavior: {{TENANT_AND_AUTHORIZATION_SOURCE_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Known-denial status-only summary and unexpected-failure class-only redaction: {{REQUEST_POLICY_LOGGING_POLICY_OR_NOT_APPLICABLE}}

ADR 023 supersedes the earlier no-denial-log wording. A known denial receives only the common terminal summary's generic known-failure outcome and response status; an unexpected failure contributes only its concrete class. Never record credentials, complete sensitive identifiers, or internal policy messages, and do not add a second policy event.

## WebSocket runtime

`NOT_APPLICABLE(WEBSOCKETS)`: this template declares no listener, event-loop process, supervisor, proxy, TLS termination, connection registry, capacity, or scaling policy. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and record the exact runtime package and version, separate entrypoint and process identity, listener and trusted-proxy boundary, non-secret configuration source, startup and readiness contract, heartbeat, idle and absolute lifetime, send and close deadlines, connection and rate limits, graceful stop, forced-stop owner, restart, deployment topology, capacity, scaling, incident policy, and dated operational source. Record the redacted connection-summary destination and its backpressure and outage behavior without copying credentials, identifiers, headers, or frames.

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
- Non-secret configuration source and required extension or package: {{CACHE_CONFIGURATION_AND_DEPENDENCY_OR_NOT_APPLICABLE}}
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
- Console process identity and non-secret configuration source: {{CLI_PROCESS_IDENTITY_AND_CONFIGURATION_OR_NOT_APPLICABLE}}
- Lock-file ownership, permissions, cleanup, and filesystem topology: {{CLI_LOCK_OPERATIONS_OR_NOT_APPLICABLE}}
- Cron or supervisor frequency, timeout, forced termination, restart, and incident policy: {{CLI_SUPERVISOR_POLICY_OR_NOT_APPLICABLE}}
- Operational assumptions source and verified date: {{CLI_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Keep command, argument, exit, stream, clock, cadence, one-pass, repeated-slot, composition, and evidence facts in `.ai/cli.md`. Framework `vendor/bin/phpthis` remains the checker, not the application console. Do not add command discovery, dynamic class or service resolution, a generic scheduler facade, daemon, hidden loop, or an unrecorded second command path. A same-host file lock is topology-dependent and does not prove distributed or sequential-in-slot deduplication.

## Database migrations

- Adoption or `NOT_APPLICABLE(MIGRATIONS)`: `.ai/migrations.md`
- Migration process identity and non-secret configuration source: {{MIGRATION_PROCESS_IDENTITY_AND_CONFIGURATION_OR_NOT_APPLICABLE}}
- Lock-file ownership, permissions, cleanup, and filesystem topology: {{MIGRATION_LOCK_OPERATIONS_OR_NOT_APPLICABLE}}
- DDL timeout, maintenance window, availability, capacity, and termination policy: {{MIGRATION_EXECUTION_OPERATIONS_OR_NOT_APPLICABLE}}
- Backup, restore, failed-deployment, and incident procedure: {{MIGRATION_RECOVERY_OPERATIONS_OR_NOT_APPLICABLE}}
- Operational assumptions source and verified date: {{MIGRATION_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Keep identifier, manifest, checksum, ledger, transaction, immutable-history, output, redaction, and evidence facts in `.ai/migrations.md`. The application console is the only execution path; never migrate from HTTP startup, framework `vendor/bin/phpthis`, or dependency hooks. Shared-data migration requires separate explicit human authorization even when the command exists.

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
- Health and readiness paths: {{HEALTH_AND_READINESS_PATHS}}
- Alert or incident reference: `{{INCIDENT_REFERENCE}}`

Keep destination buffering, retention, backpressure, outage, and incident facts in `.ai/observability.md`; do not restate the installed event schema here. A sink invocation attempt is not durable delivery.

## Prohibited operational actions

- {{PROHIBITED_OPERATION_1}}
- {{PROHIBITED_OPERATION_2}}

An AI may inspect documented local state and run project checks. It must not deploy, migrate shared data, rotate credentials, contact users, or mutate external systems unless the accountable human explicitly authorizes that exact action.
