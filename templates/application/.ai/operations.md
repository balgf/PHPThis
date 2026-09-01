# Application operations contract

## Local development

- Dependency install command: `{{DEPENDENCY_INSTALL_COMMAND}}`
- Local bootstrap command: `{{LOCAL_BOOTSTRAP_COMMAND}}`
- Local start command with required explicit web-SAPI settings: `{{LOCAL_START_COMMAND}}`
- Local stop command: `{{LOCAL_STOP_COMMAND_OR_NOT_APPLICABLE}}`
- Required local services: {{REQUIRED_LOCAL_SERVICES}}
- Local environment launcher PHP CLI invocation, absolute project-root/`PHP_BINARY`/private-child resolution, working-directory policy, and owner or `NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`: {{LOCAL_ENVIRONMENT_LAUNCHER_OPERATIONS_OR_NOT_APPLICABLE}}
- Production configuration delivery path and explicit non-use of the local launcher and file: {{PRODUCTION_CONFIGURATION_DELIVERY_OR_NOT_APPLICABLE}}

## Runtime

- Supported PHP version: 8.4
- Web runtime: {{WEB_RUNTIME}}
- WebSocket runtime: `NOT_APPLICABLE(WEBSOCKETS)`; `.ai/websockets.md` owns any future application-owned process and protocol decision.
- Worker runtime: {{WORKER_RUNTIME_OR_NOT_APPLICABLE}}
- Operational application console and scheduler: `.ai/cli.md`
- Database migrations: `.ai/migrations.md`
- Required extensions: `ext-pdo` and `ext-session` plus {{ADDITIONAL_REQUIRED_PHP_EXTENSIONS_OR_NONE}}

## Outer HTTP failure and web-SAPI runtime

- Sole HTTP front-controller path and dependency authority: `.ai/architecture.md`; an alternate deployed HTTP adapter is ineligible pending a separate accepted decision.
- Effective deployed web-SAPI settings, source, and verification date: {{OUTER_HTTP_EFFECTIVE_SAPI_SETTINGS_SOURCE_AND_VERIFIED_DATE}}
- Private controlled PHP error destination, identity, access, retention, capacity, and incident owner: {{OUTER_HTTP_PHP_ERROR_DESTINATION_AND_POLICY}}
- `DEVELOPMENT_DETAILS` isolated local/development/test access, data, authority, and topology proof, or `NOT_APPLICABLE(DEVELOPMENT_DETAILS)`: {{OUTER_HTTP_DEVELOPMENT_DETAILS_ISOLATION_OR_NOT_APPLICABLE}}

Every HTTP runtime, including an isolated detailed-development profile, must prove `(error_reporting() & E_ALL) === E_ALL`, `display_errors=Off`, `display_startup_errors=Off`, `log_errors=On`, and `zend.exception_ignore_args=On`. A built-in-server command supplies the corresponding explicit `-d error_reporting=-1 -d display_errors=0 -d display_startup_errors=0 -d log_errors=1 -d zend.exception_ignore_args=1` settings; inherited stderr is its local operator-controlled error destination. Application `ini_set()` calls and intended configuration files do not prove startup, front-controller, autoload, or deployed-SAPI behavior. Record dated effective values from each real deployed web SAPI and keep staging and production in `GENERIC`.

## Configuration runtime

`.ai/configuration.md` is the single writable authority for the boundary source, external names, outer failure disclosure/profile selection, deployment injection owner, process-specific factories and final readonly types, validation, profile, input-name, and credential separation without inheritance, combined credentials, or fallback, startup failure, rotation/restart, secret redaction, and configuration evidence. Do not duplicate those facts or placeholders here. This operations guide records only the surrounding process, SAPI, supervisor, topology, capacity, and incident facts. PHPThis performs no automatic dotenv load, secret-manager lookup, or hidden reload.

When a local environment launcher is adopted, this guide records only its explicit PHP CLI invocation, absolute project-root/`PHP_BINARY`/private-child resolution, working-directory behavior, owner, and production non-use. `.ai/configuration.md` remains authoritative for the shared canonical reader, file/profile/key, and source-precedence facts, `.ai/cli.md` for command handoff, and `.ai/testing.md` for evidence. Every production process receives configuration from its explicitly selected supervisor, container, service manager, or other deployment path; it does not invoke the local launcher or read its ignored file.

## Session runtime

- Adoption: {{SESSION_ADOPTION_OR_NOT_APPLICABLE}}
- Native handler, exact effective save path, ownership, and application isolation: {{SESSION_NATIVE_FILE_STORAGE_POLICY_OR_NOT_APPLICABLE}}
- Required PHP session settings and dated verification source: {{SESSION_PHP_SETTINGS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}
- Exact cookie name and prefix, canonical casing, host-only scope, production `Secure`/HTTPS policy or isolated-development exception, SameSite, live no-`Expires`/no-`Max-Age` behavior, deletion scope, and TLS/HSTS owner: {{SESSION_COOKIE_POLICY_OR_NOT_APPLICABLE}}
- Deployment topology, concurrent-request evidence, and lock assumptions: {{SESSION_TOPOLOGY_AND_CONCURRENCY_POLICY_OR_NOT_APPLICABLE}}
- Garbage collection and obsolete-file cleanup: {{SESSION_GARBAGE_COLLECTION_POLICY_OR_NOT_APPLICABLE}}

`ext-pdo` and `ext-session` are installed-framework requirements even when database or session state is not adopted. A database adoption additionally records its actual `ext-pdo_*` driver. Session adoption additionally requires the native `files` handler, an exact save path proven isolated to this application identity, the fixed runtime settings, and cleanup retention beyond the absolute session lifetime described in installed `vendor/phpthis/framework/docs/sessions.md`. Production authentication/session cookies normally use `Secure` through an end-to-end reviewed HTTPS deployment; limit an insecure cookie to an explicitly isolated development profile. Prefer a canonically cased `__Host-` session name when compatible, while recording that prefix behavior depends on supporting user agents and does not isolate ports. Do not copy session IDs, cookie values, CSRF tokens, or snapshots into this file.

## Request-policy runtime

- Adoption or `NOT_APPLICABLE(REQUEST_POLICY)`: {{REQUEST_POLICY_RUNTIME_ADOPTION_OR_NOT_APPLICABLE}}
- Credential verifier and supported scheme: {{CREDENTIAL_VERIFIER_AND_SCHEME_OR_NOT_APPLICABLE}}; external configuration source and typed factory: `.ai/configuration.md`
- Authorization-header forwarding and trusted-proxy policy: {{AUTHORIZATION_HEADER_FORWARDING_POLICY_OR_NOT_APPLICABLE}}
- Credential expiry, rotation, revocation, and verifier-failure behavior: {{CREDENTIAL_LIFECYCLE_AND_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Tenant and permission source availability and failure behavior: {{TENANT_AND_AUTHORIZATION_SOURCE_FAILURE_POLICY_OR_NOT_APPLICABLE}}
- Known-denial status-only summary and only the ADR 023 safe class for unexpected-failure redaction: {{REQUEST_POLICY_LOGGING_POLICY_OR_NOT_APPLICABLE}}

ADR 023 supersedes the earlier no-denial-log wording. A known denial receives only the common terminal summary's generic known-failure outcome and response status; an unexpected failure contributes only the ADR 023 safe class. Never record credentials, complete sensitive identifiers, or internal policy messages, and do not add a second policy event.

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

## Operation-specific coordination

- Standalone operation-coordination records or `NOT_APPLICABLE(OPERATION_COORDINATION)`: {{OPERATION_COORDINATION_RECORDS_OR_NOT_APPLICABLE}}

For each adopted standalone operation, repeat one finite record keyed by a stable operation name. Include its exact resource, protected interval, namespace and collision scope; every cooperating and bypassing entrypoint; backend, client or package, exact supported versions, topology, authority and security; exact atomic mechanism; blocking or nonblocking acquisition, maximum wait, timeouts, retries and contention; owner token, TTL, renewal and owner-checked release when supported, otherwise the exact descriptor, session, transaction, process or external-serialization lifecycle or explicit non-applicability; cleanup, crash and uncertain outcomes; ownership loss, stale-owner behavior, fencing or bounded `NON_FENCING` limitation; maximum work duration or explicit `UNPROVED`, idempotency and bypass policy; bounded redacted observability, operations and incident owner; and references to the real concurrency, contention, expiry or cleanup, stale-owner, process-termination, outage, recovery and topology evidence owned by `.ai/testing.md`. Follow installed `vendor/phpthis/framework/docs/coordination.md`.

Keep a scheduled pass's mechanism in `.ai/cli.md`, a migration writer's mechanism in `.ai/migrations.md`, durable-job ownership in `.ai/jobs.md`, and cache, session, or file-transfer policy in its existing concern owner. Reference those records here only for surrounding deployment mapping, runbook, incident ownership, and evidence; do not duplicate them or infer a portable lock abstraction.

## Durable-job runtime

- Adoption or `NOT_APPLICABLE(JOBS)`: `.ai/jobs.md`
- Selected worker process shape, concurrency, prefetch or backpressure, supervisor and deployment-replacement policy: {{JOBS_PROCESS_SHAPE_SUPERVISION_AND_DEPLOYMENT_POLICY_OR_NOT_APPLICABLE}}
- Process timeout, forced termination, restart, and clean-stop policy: {{JOBS_PROCESS_LIFECYCLE_POLICY_OR_NOT_APPLICABLE}}
- Capacity, retention, selected terminal inspection, and incident policy: {{JOBS_OPERATIONS_POLICY_OR_NOT_APPLICABLE}}

Under accepted ADR 052, an adoption records the exact selected finite-work or bounded long-running process shape, concurrency, prefetch or backpressure, supervision, shutdown, resource recycling, deployment replacement, recovery, and every loop or signal behavior without importing another backend's lifecycle. Only an application deliberately adopting the current ADR 024 checked SQLite profile records repetition through fresh one-delivery processes and its externally supervised one-shot policy. Do not add a hidden polling or retry loop, mutable worker state, discovery, or unrecorded signal behavior.

## Application CLI and scheduler

- Adoption or `NOT_APPLICABLE(CLI)`: `.ai/cli.md`
- Non-migration command-to-deployment runner or supervisor mapping and incident owner, keyed by command: {{CLI_NON_MIGRATION_DEPLOYMENT_RUNNER_AND_INCIDENT_MAPPING_OR_NOT_APPLICABLE}}. Keep command-to-profile and authority references in `.ai/cli.md`, exact process identity and typed configuration in `.ai/configuration.md`, and database-authority facts in `.ai/data.md`; migration deployment mappings are recorded only in the database-migrations section below.
- Scheduled-pass overlap-mechanism ownership, namespace, cleanup or release, and topology: {{CLI_LOCK_OPERATIONS_OR_NOT_APPLICABLE}}
- Scheduled-pass cron or supervisor frequency, timeout, forced termination, restart, and incident policy: {{CLI_SUPERVISOR_POLICY_OR_NOT_APPLICABLE}}
- Operational assumptions source and verified date: {{CLI_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Keep command, argument, exit, stream, one-pass, composition, and evidence facts in `.ai/cli.md`. Record clock, cadence, repeated-slot, overlap, and supervisor facts there only for an adopted scheduled pass; otherwise mark the schedule-only fields not applicable. A migration-only console keeps writer coordination or serialization in `.ai/migrations.md`. Framework `vendor/bin/phpthis` remains the checker, not the application console. Do not add command discovery, dynamic class or service resolution, a generic scheduler facade, daemon, hidden loop, or an unrecorded second command path. A same-host file lock is topology-dependent and does not prove distributed or sequential-in-slot deduplication.

## Database migrations

- Adoption or `NOT_APPLICABLE(MIGRATIONS)`: `.ai/migrations.md`
- Accepted database-authority and release decision reference: {{DATABASE_AUTHORITY_AND_RELEASE_DECISION_SOURCE_OR_NOT_APPLICABLE}}
- Sole application console executable plus each stable history name and finite explicit command: `.ai/migrations.md`; exact process identity, configuration source, factory, and final readonly type per history: `.ai/configuration.md`; effective authority facts and accountable transition ownership: `.ai/data.md`; transition implementation and per-history handoff constraints: `.ai/migrations.md`; deployment-runner mapping keyed by stable history name: {{MIGRATION_DEPLOYMENT_RUNNER_MAPPING_OR_NOT_APPLICABLE}}
- Exact initial baseline per stable history name: `.ai/migrations.md`; do not duplicate it here. This guide records only the release-stage use of that baseline and the owning operational evidence.
- Selected coordination/serialization-boundary operator, exact runbook, and evidence reference keyed by stable history name; keep the mechanism, namespace, topology, exclusion, and lost-owner policy authoritative in `.ai/migrations.md`: {{MIGRATION_COORDINATION_RUNBOOK_AND_EVIDENCE_MAPPING_OR_NOT_APPLICABLE}}
- Maintenance window, availability, capacity, timeout, termination, and incident mapping keyed by stable history name; keep exact engine transaction, implicit-commit, DDL, and migration-effect semantics authoritative in `.ai/migrations.md`: {{MIGRATION_MAINTENANCE_CAPACITY_TERMINATION_AND_INCIDENT_MAPPING_OR_NOT_APPLICABLE}}
- Backup, restore, forward-recovery, failed-deployment, and cross-history partial-deployment runbook mapping keyed by stable history name or explicit intersecting-history set; keep detection and recovery policy authoritative in `.ai/migrations.md`: {{MIGRATION_RECOVERY_AND_CROSS_HISTORY_RUNBOOK_MAPPING_OR_NOT_APPLICABLE}}
- Authority-transition operator, release-stage runbook, and evidence reference keyed by stable history name or an explicit shared transition stage; keep the exact transition source and non-HTTP implementation path authoritative in `.ai/migrations.md` and authority facts authoritative in `.ai/data.md`; `GRANT`/`REVOKE` apply only where supported: {{DATABASE_AUTHORITY_TRANSITION_RUNBOOK_AND_EVIDENCE_MAPPING_OR_NOT_APPLICABLE}}
- Migration, authority activation, exact-engine verification, application rollout, and traffic-enablement order: {{DATABASE_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}
- Pre-traffic authority gate, exact evidence, and accountable owner: {{DATABASE_PRE_TRAFFIC_AUTHORITY_GATE_EVIDENCE_AND_OWNER_OR_NOT_APPLICABLE}}
- Old/new-code compatibility, abort or forward-correction path, dependent-code drain, later authority deactivation, and namespace/object-removal order: {{DATABASE_COMPATIBILITY_DEACTIVATION_AND_REMOVAL_POLICY_OR_NOT_APPLICABLE}}
- Operational assumptions source and verified date: {{MIGRATION_OPERATIONS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Keep each history's engine/version decision, exact initial baseline, finite explicit command, typed-configuration/process-identity and database-authority references, coordinator, manifest, checksum-covered effects, ledger, atomicity, coordination, lost-owner safety, recovery, immutable history, authority-transition implementation, output, redaction, and exact-engine evidence authoritative in `.ai/migrations.md`; exact process identity and configuration remain authoritative in `.ai/configuration.md`, and effective authority facts plus accountable transition ownership remain authoritative in `.ai/data.md`. The bullets above record only stable-history-keyed operational owners, mappings, runbooks, and evidence references; they do not restate those policies. For intersecting histories, record the dependency, serialization owner, release, partial-deployment, and application-wide recovery runbook mappings here while the underlying per-history and shared-mechanism policy remains in `.ai/migrations.md`. This guide owns the application-specific release sequence and operational runbooks; there is no universal order beyond activating and verifying required authority before dependent traffic and draining dependent code before later authority deactivation or namespace/object removal. The application console executable is the only migration execution path; never migrate or transition authority from HTTP startup, framework `vendor/bin/phpthis`, or dependency hooks. Shared-data migration requires separate explicit human authorization even when the command exists.

## Environments and deployment

- Environment names and purpose: {{ENVIRONMENT_MODEL}}
- Operational assumptions last verified from: {{OPERATIONS_SOURCE_AND_VERIFIED_DATE}}
- Deployment source of truth: `{{DEPLOYMENT_SOURCE_PATH}}`
- Release command or workflow: {{RELEASE_WORKFLOW}}
- Rollback procedure: {{ROLLBACK_PROCEDURE_REFERENCE}}

## Logging and observability

- Terminal request-summary runtime authority: `.ai/observability.md`
- Optional operational log-record, stdout/stderr or daily-file destination, rotation/retention/disk policy, and Alloy/Loki/Grafana tenant/account, remote lifecycle, data-residency, access, and incident authority or explicit N/A: `.ai/observability.md`; do not duplicate those facts here.
- HTTP cache status, revalidation, and intermediary observability: {{HTTP_CACHE_OBSERVABILITY_POLICY_OR_NOT_APPLICABLE}}
- Cache-operation summary and hit, miss, failure, invalidation, and stampede metrics: {{CACHE_OBSERVABILITY_POLICY_OR_NOT_APPLICABLE}}
- Adopted health, readiness, or non-HTTP probes; exact claim, entrypoint or composition root, inherited dependencies and synchronous destinations including the terminal summary sink, bounded work, failure response or process behavior, local or deployment operations owner or explicit N/A, and application-test or deployment evidence with verified environment/date: {{HEALTH_AND_READINESS_PATHS}}
- Alert or incident reference: `{{INCIDENT_REFERENCE}}`

`Connection::connect()` constructs PDO eagerly and, depending on the selected driver and DSN, may perform I/O or fail during composition. A route sharing a composition root that opens a required external-service connection must not be described as external-service-independent liveness. A synchronous terminal sink also remains part of the probe path even when its failure cannot alter the selected response; record its destination and latency bound before making an independence claim. Record what the selected execution path actually proves and whether a composition failure occurs before routing, response selection, or the terminal request-summary attempt. Connection construction alone does not prove exact-statement database authority or complete readiness. PHPThis does not select a probe route, dependency set, restart policy, or deployment topology, and it does not authorize a hidden bypass or second HTTP execution path.

Keep destination buffering, retention, backpressure, outage, and incident facts in `.ai/observability.md`; do not restate the installed event schema or optional record envelope here. A sink invocation attempt is not durable delivery.

## Prohibited operational actions

- {{PROHIBITED_OPERATION_1}}
- {{PROHIBITED_OPERATION_2}}

An AI may inspect documented local state and run project checks. It must not deploy, migrate shared data, rotate credentials, contact users, or mutate external systems unless the accountable human explicitly authorizes that exact action.
