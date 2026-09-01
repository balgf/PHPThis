# Example terminal-summary context

The executable example follows ADR 023 with application types under `src/Observability/`, manual composition in `bootstrap.php`, and invocation from `public/index.php`.

- Correlation: one `CorrelationId::generate()` per request-scoped composition; the coordinator replaces any application response spelling with the generated `X-Request-ID`.
- Sink: one injected `ErrorLogRequestSummarySink`; sink failure cannot alter the selected response and one attempt is not durable delivery.
- Sources: `list_users`, `get_user`, `create_user`, `get_document`, and `list_documents`. Each owns the exact operation connection's distinct budget and trace.
- Event: request-summary schema version `2`, carrying ADR 023's closed request fields plus one bounded `document_cache` snapshot from `DocumentDetailsCacheTrace`. Denials carry generic known-failure outcome and status; unknown failures add only the ADR 023 safe class.
- Evidence: `tests/observability.php`, included by the repository test runner, owns schema, correlation, source, budget, redaction, freshness, and throwing-sink proof.

The application console is not an HTTP request and does not enter this terminal request-summary event. `example/bin/console.php` emits only one-line stdout success or one-line stderr error. `.ai/cli.md` owns command, stream, cadence, Redis coordination, and console-redaction facts; `.ai/jobs.md` owns job outcomes, durable diagnostics, and worker lifecycle. Do not place an argument, database or migration-lock path, Redis endpoint, key, value, owner token, raw reply, envelope, payload, idempotency key, SQL, binding, request value, or exception detail in any channel.

## Outer HTTP failure event

`public/index.php` makes at most one best-effort `error_log` attempt with the exact line `application.http_outer_failure failure_class=<ADR023-safe-class>` after a catchable bootstrap, composition, or coordinator failure and before response emission. It records only that fixed event name and the ADR 023 safe class; anonymous source suffixes, messages, paths, lines, traces, modes, request values, configuration, SQL/bindings, credentials, and dependency text are excluded. Failure of the attempt cannot alter the prebuilt generic response, and one attempt is not durable delivery. This pre-coordinator event has no correlation or terminal-summary guarantee and does not expand under `DEVELOPMENT_DETAILS`, which the example does not adopt.

ADR 028 cache evidence is bounded and redacted within the existing terminal request-summary attempt; it does not add a per-operation log or second sink invocation. The exact `document_cache` snapshot exposes only finite `read`, `write`, and `invalidation` outcomes. It omits complete keys, values, tenant and document identities, endpoints, credentials, and Redis errors. Every schedule success adds a bounded `coordination` list after `command` and `outcome`; a Redis operational failure adds that list after `error: command_failed`. `not_due` uses an empty list, while contention and owned work expose only code-owned lifecycle outcomes. Owner tokens and Redis configuration never enter either channel.

All SQL remains complete raw SQLite text with explicit named parameter arrays at direct `Connection` call sites. Do not add an ORM, repository, query builder, paginator, SQL/binding/placeholder helper, generated SQL, framework logger, middleware, facade, per-query event, or hidden instrumentation.

## Optional operational log-record profile

`NOT_APPLICABLE(OPERATIONAL_LOG_RECORD)`: ADR 051's accepted optional profile is not adopted or implemented by the executable example. `ErrorLogRequestSummarySink` still encodes the exact accepted version-2 request summary directly, with no destination envelope or level field.

- Daily file and local tail: `NOT_APPLICABLE(DAILY_LOG_FILE)`; no path, permissions, UTC rollover, writer topology, rotation, retention, compression, quota, disk-full, or inspection command is selected.
- Stdout/stderr collection: `NOT_APPLICABLE(SELECTED_LOG_STREAM)`; deployment-configured `error_log` does not prove a selected stream or supervisor policy, and the application console's exact stdout/stderr result bytes remain separate.
- Grafana: `NOT_APPLICABLE(GRAFANA_LOG_DELIVERY)`; no Alloy, positions store, Loki or Grafana Cloud Logs tenant/account, remote retention/deletion, region/data-residency, label policy, credential or access owner, retry/backpressure/outage behavior, incident owner, or alert is selected.

Do not infer this optional capability from the existing sink or add a framework logger, generic application logger, message/context bag, direct remote sink, runtime dependency, or delivery guarantee.
