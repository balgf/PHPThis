# Application terminal-summary context

The starter follows installed ADR 023 with application types under `src/Observability/`, manually composed in `bootstrap.php` and invoked by `public/index.php`.

- Correlation: `CorrelationId::generate()` supplies 16 random bytes encoded as 32 lowercase hexadecimal characters; `TerminalRequestCoordinator` owns the single `X-Request-ID` response field.
- Sink: `ErrorLogRequestSummarySink` is the explicitly injected starter boundary and calls deployment-configured `error_log` synchronously before the coordinator returns. The coordinator isolates its one invocation attempt from the selected response, but the starter records no destination or latency bound; delivery is not guaranteed, and external-service independence is not established.
- Database sources: `NOT_APPLICABLE(no database)`. The list is empty and aggregate query evidence is zero.
- Scope: coordinator handling through response selection and the sink attempt. The separate outer event below covers only a catchable pre-coordinator failure class; process-fatal errors, response emission, and network delivery remain outside the claim.
- Evidence: `composer test` exercises real composition, generated IDs, success and mapped input summaries, unknown-failure capture of only the ADR 023 safe class, exactly one sink attempt, throwing-sink response isolation, request-scoped freshness, and the real front controller. The framework repository's observability proof covers the complete schema.

Before database adoption, register every executable request-scoped connection in at most eight unique sources with distinct budgets and traces. Do not move terminal observability into an application-owned request-handler decorator or add an ORM, query builder, SQL/binding helper, framework logger, generic or framework logging middleware, facade, global helper, per-query event, or hidden instrumentation.

## Outer HTTP failure event

`public/index.php` makes at most one best-effort `error_log` attempt with the exact line `application.http_outer_failure failure_class=<ADR023-safe-class>` after a catchable bootstrap, composition, or coordinator failure and before response emission. It records only that fixed event name and the ADR 023 safe class. It omits anonymous source suffixes, messages, paths, lines, traces, modes, request values, configuration, SQL/bindings, credentials, and dependency text. Failure of the attempt cannot alter the prebuilt generic response, and the attempt is not durable delivery. This event creates no `X-Request-ID` or terminal summary and does not expand under a detailed profile, which the starter does not adopt.

## Optional operational log-record profile

`NOT_APPLICABLE(OPERATIONAL_LOG_RECORD)`: ADR 051's accepted optional profile is not adopted or implemented by this starter.

- Record and levels: `ErrorLogRequestSummarySink` encodes the accepted version-1 summary directly. It adds no `record_schema_version`, `occurred_at`, `level`, or `summary` envelope and has no `debug`, `info`, `warning`, `error`, or `critical` policy.
- Daily file: `NOT_APPLICABLE(DAILY_LOG_FILE)`. The skeleton creates, reserves, and ignores no log directory. No path, UTC filename boundary, permissions, writer topology, rotation, retention, compression, quota, disk-full, or local tail workflow is selected. An adopter that selects the recommended local `var/log` path adds the exact project-root `/var/log/` ignore at that time.
- Stdout/stderr: `NOT_APPLICABLE(SELECTED_LOG_STREAM)`. PHP's deployment-configured `error_log` destination is not evidence that this application selected either stream or that a supervisor collects it.
- Grafana: `NOT_APPLICABLE(GRAFANA_LOG_DELIVERY)`. No Alloy, Loki, Grafana Cloud Logs tenant/account, remote retention/deletion, region/data-residency, labels, positions, collector credentials, access owner, retry, backpressure, outage, incident owner, or alert policy is selected.

Adoption requires an accountable application decision plus application-owned code and evidence. Do not infer these optional capabilities from the existing sink or change the request-summary schema, framework runtime, dependencies, or one-attempt failure boundary.
