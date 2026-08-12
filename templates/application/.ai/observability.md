# Application terminal-summary context

Read installed `vendor/phpthis/framework/docs/observability/README.md`, `vendor/phpthis/framework/docs/logging.md`, and `vendor/phpthis/framework/docs/decisions/023-application-owned-terminal-request-summaries.md` before replacing these placeholders. This file records project facts only; it does not redefine the installed event schema.

- Coordinator path: `{{TERMINAL_REQUEST_SUMMARY_COORDINATOR_PATH}}`
- Correlation generation and response propagation: {{CORRELATION_ID_AND_RESPONSE_PROPAGATION}}
- Sink interface, implementation, and destination: {{TERMINAL_REQUEST_SUMMARY_SINK_AND_DESTINATION}}
- Every executable request-scoped database source, or empty list: {{TERMINAL_SUMMARY_DATABASE_SOURCES_OR_EMPTY}}
- Destination buffering, retention, backpressure, and outage policy: {{TERMINAL_SUMMARY_DESTINATION_POLICY}}
- Focused response, redaction, source-bound, and throwing-sink tests: `{{TERMINAL_SUMMARY_TEST_COMMAND}}`

Record at most eight unique non-sensitive source names with distinct budgets and traces. One invocation attempt never means durable delivery. Do not move terminal observability into an application-owned request-handler decorator or add framework observability types, generic or framework logging middleware, facades, global helpers, per-query events, hidden instrumentation, an ORM, or an SQL/binding helper.

## Optional operational log-record profile

ADR 051 accepts this optional application-owned profile. Resolve this section to `NOT_APPLICABLE(OPERATIONAL_LOG_RECORD)` unless an accountable application decision adopts it without changing its exact record envelope or the accepted request-summary versions.

- Adoption and accountable decision reference: {{OPERATIONAL_LOG_RECORD_ADOPTION_OR_NOT_APPLICABLE}}
- Every adopted process's exact closed summary type, finite event/outcome vocabulary, and outcome-to-`debug|info|warning|error|critical` map: {{OPERATIONAL_LOG_SUMMARY_AND_LEVEL_MAP_OR_NOT_APPLICABLE}}
- Every adopted non-HTTP process's finite code-owned emission policy, including whether a named `debug` event exists and in which named environments: {{OPERATIONAL_LOG_PROCESS_EMISSION_POLICY_OR_NOT_APPLICABLE}}
- Exact record version `1`, sink-time UTC microsecond clock, `record_schema_version`/`occurred_at`/`level`/`summary` key order, and 65,536-byte LF-inclusive enforcement path: {{OPERATIONAL_LOG_RECORD_ENVELOPE_AND_BOUND_OR_NOT_APPLICABLE}}
- Worst-case record-size calculation from exact maximum database sources, each source's `QueryTrace` fingerprint-retention limit, all closed summary extensions, JSON escaping, encoder overhead, and LF: {{OPERATIONAL_LOG_WORST_CASE_RECORD_SIZE_PROOF_OR_NOT_APPLICABLE}}
- One selected process destination, exact stream or absolute non-public file path resolved through typed configuration, process identity, buffering, blocking bound, and sink-failure behavior: {{OPERATIONAL_LOG_PRIMARY_DESTINATION_OR_NOT_APPLICABLE}}
- Daily-file stable-active-filename and external UTC rotation policy, deployment pre-creation, owner/group/modes, regular non-symlink enforcement, writer topology, append/lock, rotation/reopen, retention, compression/deletion, quota, low-space/disk-full, and cleanup owner: {{OPERATIONAL_LOG_DAILY_FILE_POLICY_OR_NOT_APPLICABLE}}
- Stdout/stderr supervisor, collection, rotation, retention, capacity/drop, restart, access, and incident policy while preserving exact application-console streams: {{OPERATIONAL_LOG_STREAM_POLICY_OR_NOT_APPLICABLE}}
- Grafana Alloy source and version, absolute path or stream discovery, positions storage, collector lag, TLS/credential owner, batching/retry/backpressure/outage, Loki or Grafana Cloud Logs destination and stable non-secret tenant/account reference, remote retention/deletion, region/data residency, Grafana and log-store access owner, incident owner, default stable service/environment/static-deployment labels, any measured finite static process-role label, prohibited dynamic process/PID/replica labels, JSON/structured-metadata fields, and measured promotion policy: {{OPERATIONAL_LOG_GRAFANA_DELIVERY_OR_NOT_APPLICABLE}}
- Exact ignored local directory and inspection command or N/A: {{OPERATIONAL_LOG_LOCAL_TAIL_WORKFLOW_OR_NOT_APPLICABLE}}
- Loss, duplicate, order, durability, audit, alerting, and incident limits: {{OPERATIONAL_LOG_DELIVERY_AND_MONITORING_LIMITS_OR_NOT_APPLICABLE}}
- Focused envelope, level, redaction, destination, rollover, failure, and collector evidence: `{{OPERATIONAL_LOG_TEST_COMMAND_OR_NOT_APPLICABLE}}`

For a local daily-file adoption, start with `<project-root>/var/log/application.jsonl`, generated inside the checkout but outside the public document root, never committed, and ignored by the exact project-root `.gitignore` pattern `/var/log/` (not the host `/var/log` directory). Resolve it to an absolute path through typed configuration and record the exact project-root command `tail -F var/log/application.jsonl`. For a host or virtual machine, start with `/var/log/<application>/application.jsonl` or an equivalent dedicated persistent mount, where `<application>` is one static non-sensitive deployment identifier. For a container, prefer the selected stdout or stderr profile over a file on its ephemeral writable layer. Deployment, not the application runtime or an HTTP request, pre-creates the trusted directory and active regular non-symlink file. POSIX directory mode `0750` and file mode `0640` are recommended only when one writer identity and one collector group fit the recorded topology; otherwise record the explicit least-privilege ownership and access policy. `tail -F` follows active-path replacement for human inspection but proves neither writer reopen nor collector delivery.

For HTTP, the encoder-owned accepted map is fixed: `error` for `unknown_failure` or status at least `500`; otherwise `warning` for query failure or budget rejection; otherwise `info`. Every valid HTTP summary produces one record line; HTTP uses neither `debug` nor `critical` and has no configurable minimum, generic threshold, duplicate sink map, or suppression path. Timestamp creation through a narrow explicit clock, encoder-owned level derivation, encoding, bound enforcement, and destination I/O remain inside the existing single sink attempt. Do not call `mkdir`, `touch`, `chmod`, or `chown` for a log destination inside an HTTP request. Do not add a free-form message or context bag, a second destination or fallback, direct request-path Loki/Grafana I/O, a generic logger or level API, a runtime dependency, or a durable-delivery claim.
