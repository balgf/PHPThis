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
- Required extensions: {{REQUIRED_PHP_EXTENSIONS}}

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

Logs must not contain credentials, tokens, request bodies, SQL parameters, customer data, or unknown exception messages. A separately reviewed logging contract may permit only explicitly safe structured fields that do not copy an unknown exception message.

## Prohibited operational actions

- {{PROHIBITED_OPERATION_1}}
- {{PROHIBITED_OPERATION_2}}

An AI may inspect documented local state and run project checks. It must not deploy, migrate shared data, rotate credentials, contact users, or mutate external systems unless the accountable human explicitly authorizes that exact action.
