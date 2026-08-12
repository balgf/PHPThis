# Terminal request summaries

ADR 023 requires one application-owned terminal request summary for every request that reaches the application coordinator and selects a `Response`. PHPThis supplies no logging framework, sink, coordinator, facade, global helper, middleware, discovery hook, or hidden database instrumentation. The application writes the small coordinator and sink contract, wires them through ordinary constructors in its composition root, and keeps the path visible in its front controller.

ADR 033 does not move this terminal responsibility into an application-owned request-handler decorator. A route-local decorator cannot wrap the terminal coordinator, build the terminal request summary, invoke its sink, claim emission, or replace its failure isolation. A separately justified route-level business side effect still needs its own narrowly named bounded policy and evidence and is not the terminal summary.

PHPThis makes only the closed terminal request summary below mandatory. ADR 051 separately accepts an optional application-owned profile for a sink-time UTC timestamp, finite level, and one selected daily-file or stdout/stderr destination, including downstream Grafana delivery; an application must deliberately adopt it. PHPThis still supplies no general operational event API or logging runtime.

The starter's current `error_log()` sink is not a pure JSONL daily-file profile. PHP and its deployment may prefix the encoded summary or mix unrelated runtime diagnostics into the selected error-log destination.

## Optional operational record and destinations

[ADR 051](decisions/051-application-owned-structured-log-destinations.md) accepts an optional application-owned profile. It leaves Consumer Contract version 12 and the accepted request-summary versions unchanged. It frames one already-closed summary in exactly `record_schema_version`, sink-time UTC `occurred_at`, finite `level`, and `summary`; bounds the complete newline-delimited JSON record to 65,536 bytes including LF; and keeps timestamp construction inside the existing sink attempt.

The accepted HTTP mapping uses `error` for an unknown failure or status at least `500`, otherwise `warning` for observed query failure or budget rejection, otherwise `info`. HTTP summaries never use `debug` or `critical`. Other processes own their own finite event and level maps rather than receiving a generic logger.

See [Operational log levels](observability/log-levels.md) for the exact five-level vocabulary, the [checked destination-record reference](observability/destination-record.md) for the application-owned final encoder only, and [Operational log destination profiles](observability/destination-profiles.md) for stdout or stderr, a daily file, and Grafana Alloy to Loki or Grafana Cloud Logs to Grafana. An application must deliberately adopt the optional profile; acceptance alone changes neither its runtime behavior nor the closed request summary. The encoder proof performs no destination I/O and is not adopter-specific worst-case sizing, file, stream, collector, or delivery evidence.

## Correlation and response propagation

Generate 128 random bits during request-scoped composition before bounded request ingestion and encode them as exactly 32 lowercase hexadecimal characters matching `[0-9a-f]{32}`. Do not derive the identifier from request data. Replace any case-insensitive application response spelling with that same `correlation_id` value as the single `X-Request-ID` header on success, mapped failure, and generic unknown-failure `500` paths.

An application may separately decide whether a trusted infrastructure identifier is useful, but it does not substitute arbitrary incoming `X-Request-ID` data for the required generated identifier. Do not echo an unverified caller-controlled identifier.

## One closed bounded event

ADR 023's mandatory application event uses schema version `1` and event name `application.request_summary`. It contains:

- `correlation_id`, monotonic `duration_us`, `response_status`, and the generic `success`, `known_failure`, or `unknown_failure` outcome;
- nullable `unknown_failure_class`, populated only with the concrete class name for an unknown failure;
- aggregate `query_count`, `query_failures`, `query_execute_duration_us`, and `query_budget_exceeded`; and
- at most eight explicitly registered `database_sources`, each with one bounded non-sensitive code-owned name, `budget_limit`, `budget_used`, `budget_exceeded`, and its bounded redacted `QueryTrace` version-1 snapshot.

ADR 028 advances only the executable Redis proof to schema version `2`. Version `2` preserves every version-1 field and adds exactly one top-level `document_cache` object with `read`, `write`, and `invalidation` fields. Their complete vocabularies are:

- `read`: `not_attempted`, `hit`, `miss`, `corrupt`, or `backend_unavailable`;
- `write`: `not_attempted`, `stored`, `payload_rejected`, or `backend_unavailable`; and
- `invalidation`: `not_attempted`, `deleted`, `absent`, or `backend_unavailable`.

The snapshot contains no cache key, payload, tenant, document, title, endpoint, credential, exception, or timing value. It travels through the same single terminal sink attempt; Redis operations do not write separate events. An application that has not accepted this application-owned cache proof retains the version-1 contract instead of inventing empty extension fields.

Each source label is unique, matches `[a-z][a-z0-9_]{0,31}`, and must not encode a DSN, host, database, tenant, account, credential, or customer identifier. No two sources share a budget or trace. A budget rejection sets its exceeded state and the aggregate flag but does not enter the query trace because PDO was not attempted. Query timing retains its existing prepare, bind, and execute scope; it does not claim fetch, projection, or whole-request time.

The outcome is `unknown_failure` only when the coordinator caught an unknown `Throwable`; otherwise a selected status below 400 is `success`, and a status of 400 or above is `known_failure`.

The SQL fingerprint is SHA-256 of exact SQL text. It lets an operator identify repetition without retaining SQL, but it is a pseudonymous identifier rather than encryption or anonymity. Keep fingerprints server-side and bounded by the existing trace limit.

For a known authentication or authorization denial, the terminal event carries only the generic `known_failure` outcome and selected response status. It has no denial type, class, reason, principal, tenant, resource, or credential field. For a named unknown failure, its concrete class name is the only exception-derived value. For an anonymous throwable, use its nearest named parent class because PHP's anonymous-class runtime name embeds source path and line. Never include its message, code, previous exception, file, line, or stack.

The complete event also omits request method, target, path, query names and values, all request headers, cookies, authorization data, body, response body, session and CSRF data, cache keys and values, principal, tenant, resource and customer identifiers, SQL, parameter names and values, DSNs, credentials, and driver details. Pass the closed event values to the sink, never the `Request`, `Response`, `Throwable`, database connection, budget, query trace, cache trace, or cache client objects.

## Attempt semantics and failure isolation

After the immutable response and event have been selected, but before response emission, make exactly one sink invocation attempt. Catch any `Throwable` from the sink and keep the same response unchanged. Do not retry, call a fallback logger, emit a second failure event, or mutate its status, body, headers, or cookies.

Exactly one invocation attempt is not durable delivery. The summary may be lost, and any buffering, transport, retention, backpressure, or destination outage remains application operational policy. The event records application response selection, not successful network delivery. Bootstrap failures before the coordinator, unhandled process-fatal errors, and response-emitter or network failures are outside this contract until separate evidence accepts them.

The former separate `phpthis.request.unhandled` error-log line is superseded. There is no separate denial log, unknown-failure log, per-query log, per-cache-operation log, or sink-failure fallback. See [ADR 023](decisions/023-application-owned-terminal-request-summaries.md) for the mandatory version-1 schema and [ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md) for the executable example's closed version-2 extension.
