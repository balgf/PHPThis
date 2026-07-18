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
- Health and readiness paths: {{HEALTH_AND_READINESS_PATHS}}
- Alert or incident reference: `{{INCIDENT_REFERENCE}}`

Logs must not contain credentials, tokens, session identifiers, cookie values, CSRF tokens, session snapshots, request bodies, SQL parameters, customer data, or unknown exception messages. A separately reviewed logging contract may permit only explicitly safe structured fields that do not copy an unknown exception message.

## Prohibited operational actions

- {{PROHIBITED_OPERATION_1}}
- {{PROHIBITED_OPERATION_2}}

An AI may inspect documented local state and run project checks. It must not deploy, migrate shared data, rotate credentials, contact users, or mutate external systems unless the accountable human explicitly authorizes that exact action.
