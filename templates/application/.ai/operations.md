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
- Worker runtime: {{WORKER_RUNTIME_OR_NOT_APPLICABLE}}
- Scheduler: {{SCHEDULER_OR_NOT_APPLICABLE}}
- Required extensions: `ext-session` plus {{ADDITIONAL_REQUIRED_PHP_EXTENSIONS_OR_NONE}}

## Session runtime

- Adoption: {{SESSION_ADOPTION_OR_NOT_APPLICABLE}}
- Native handler, exact effective save path, ownership, and application isolation: {{SESSION_NATIVE_FILE_STORAGE_POLICY_OR_NOT_APPLICABLE}}
- Required PHP session settings and dated verification source: {{SESSION_PHP_SETTINGS_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}
- Cookie name, `Secure`, SameSite, and environment policy: {{SESSION_COOKIE_POLICY_OR_NOT_APPLICABLE}}
- Deployment topology, concurrent-request evidence, and lock assumptions: {{SESSION_TOPOLOGY_AND_CONCURRENCY_POLICY_OR_NOT_APPLICABLE}}
- Garbage collection and obsolete-file cleanup: {{SESSION_GARBAGE_COLLECTION_POLICY_OR_NOT_APPLICABLE}}

`ext-session` is an installed-framework requirement even when session state is not adopted. Adoption additionally requires the native `files` handler, an exact save path proven isolated to this application identity, the fixed runtime settings, and cleanup retention beyond the absolute session lifetime described in installed `vendor/phpthis/framework/docs/sessions.md`. Do not copy session IDs, cookie values, CSRF tokens, or snapshots into this file.

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

## Environments and deployment

- Environment names and purpose: {{ENVIRONMENT_MODEL}}
- Operational assumptions last verified from: {{OPERATIONS_SOURCE_AND_VERIFIED_DATE}}
- Deployment source of truth: `{{DEPLOYMENT_SOURCE_PATH}}`
- Release command or workflow: {{RELEASE_WORKFLOW}}
- Rollback procedure: {{ROLLBACK_PROCEDURE_REFERENCE}}

## Logging and observability

- Request identifier policy: {{REQUEST_IDENTIFIER_POLICY}}
- Structured log destination: {{LOG_DESTINATION}}
- Query-summary policy: {{QUERY_SUMMARY_POLICY}}
- HTTP cache status, revalidation, and intermediary observability: {{HTTP_CACHE_OBSERVABILITY_POLICY_OR_NOT_APPLICABLE}}
- Cache-operation summary and hit, miss, failure, invalidation, and stampede metrics: {{CACHE_OBSERVABILITY_POLICY_OR_NOT_APPLICABLE}}
- Health and readiness paths: {{HEALTH_AND_READINESS_PATHS}}
- Alert or incident reference: `{{INCIDENT_REFERENCE}}`

Logs must not contain credentials, tokens, session identifiers, cookie values, CSRF tokens, session snapshots, cache keys or payloads, request bodies, SQL parameters, customer data, or unknown exception messages. A separately reviewed logging contract may permit only explicitly safe structured fields that do not copy an unknown exception message.

## Prohibited operational actions

- {{PROHIBITED_OPERATION_1}}
- {{PROHIBITED_OPERATION_2}}

An AI may inspect documented local state and run project checks. It must not deploy, migrate shared data, rotate credentials, contact users, or mutate external systems unless the accountable human explicitly authorizes that exact action.
