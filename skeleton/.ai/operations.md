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
- Worker and scheduler: `NOT_APPLICABLE`.
- Required extensions: `ext-pdo` and `ext-session` through the installed framework; the starter application opens no database connection and configures no session lifecycle.

## Session runtime

`NOT_APPLICABLE`: the starter does not construct `SessionLifecycle` or create session storage. PHP 8.4 `ext-session` remains an installed framework platform requirement. Before adoption, record the native file handler and save-path ownership, exact PHP settings and dated source, cookie policy, deployment topology and concurrency evidence, and garbage collection in this section.

## Deployment

`NOT_APPLICABLE`: the skeleton defines no environment, release, rollback, or production runtime policy. Add verified operational sources before deployment work.

## Logging and observability

- Unknown failures use `PHPThis\Http\UnknownFailureBoundary` and remain generic to clients.
- `GET /health` is the starter liveness path; no readiness path exists.
- Query summaries are `NOT_APPLICABLE(no database)`.

Logs must not contain credentials, tokens, session identifiers, cookie values, CSRF tokens, session snapshots, request bodies, SQL parameters, customer data, or unknown exception messages.

## Prohibited operational actions

The skeleton authorizes no deployment, shared-data migration, credential rotation, user contact, or external-system mutation. An AI may inspect documented local state and run project checks, but it must not perform any of those actions unless the human explicitly authorizes that exact action after the application records the relevant operational policy.
