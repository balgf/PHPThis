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
- Required extensions: `ext-pdo` through the installed framework; the starter application opens no connection.

## Deployment

`NOT_APPLICABLE`: the skeleton defines no environment, release, rollback, or production runtime policy. Add verified operational sources before deployment work.

## Logging and observability

- Unknown failures use `PHPThis\Http\UnknownFailureBoundary` and remain generic to clients.
- `GET /health` is the starter liveness path; no readiness path exists.
- Query summaries are `NOT_APPLICABLE(no database)`.

Logs must not contain credentials, tokens, request bodies, SQL parameters, customer data, or unknown exception messages.
