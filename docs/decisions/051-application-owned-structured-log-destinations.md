# ADR 051: Application-owned structured log destinations

Status: accepted

## Context

ADR 023 requires one application-owned terminal request summary and one failure-isolated sink invocation attempt for every request that reaches the coordinator and selects a response. Its version-1 `application.request_summary` payload is closed. ADR 028 preserves that payload and advances only the executable Redis proof to a closed version-2 payload with one `document_cache` snapshot. Neither record defines a log severity, wall-clock occurrence time, daily-file lifecycle, managed-stream contract, or remote collector.

The starter sink calls PHP's deployment-configured `error_log()` destination. That remains a useful explicit starter boundary, but PHP may prefix file output and may send unrelated runtime diagnostics to the same destination. It therefore cannot by itself establish a pure JSON-lines file, an application occurrence timestamp, a finite level, a rotation policy, or successful remote delivery.

Applications need a bounded way to retain request summaries in daily files or managed streams and optionally forward them to Loki for Grafana inspection. Adding `level` or `occurred_at` directly to ADR 023's closed payload would require a new request-summary schema and Consumer Contract migration. A framework logger, PSR-3 dependency, facade, helper, middleware, event bus, automatic instrumentation, or synchronous request-path network sink would instead obscure the application-owned boundary and failure cost.

On 2026-08-12 in Asia/Manila, the accountable human approved this optional application-owned destination profile, its checked copyable encoder, its operational guidance, and its bounded installed-consumer evidence. That same approval covers the additive three-environment path convention recorded below; it creates no additional release authority. This approval changes no release identity and authorizes no commit, push, tag, package-host, GitHub-release, or announcement operation. It does not adopt the profile in the health-only skeleton or executable example.

## Decision

PHPThis recommends one optional application-owned structured destination profile. The profile changes neither the terminal request-summary value nor the sink interface: the explicitly injected sink still receives exactly the closed ADR 023 version-1 or ADR 028 version-2 `RequestSummary`. An adopting destination places that unchanged value in this exact envelope and serializes the keys in this order:

```json
{
  "record_schema_version": 1,
  "occurred_at": "2026-08-12T00:00:00.123456Z",
  "level": "info",
  "summary": {
    "schema_version": 1,
    "event": "application.request_summary"
  }
}
```

`summary` above is abbreviated only for presentation. The emitted value contains the complete unchanged closed version-1 or version-2 payload, with no omitted or additional summary field. `record_schema_version` is the integer `1` and versions only the destination envelope; it does not replace or reinterpret `summary.schema_version`.

The complete encoded record, including its final LF byte, is at most 65,536 bytes. It is one compact UTF-8 JSON object encoded with throwing JSON behavior and unescaped slashes, followed by exactly one LF and no BOM or other prefix or suffix. An oversized or unencodable record fails the sink attempt; it is not truncated, split, rewritten, or sent through a fallback.

Before adoption, the application proves that its worst-case valid record fits this limit. The calculation includes its exact maximum database-source count, every source's selected `QueryTrace` retained-fingerprint limit, every closed summary extension, the fixed destination envelope, JSON escaping, and the final LF. The post-encoding 65,536-byte rejection remains a defense against drift or an invalid assumption; it is not a substitute for choosing compatible upstream bounds.

### Occurrence time

`occurred_at` is one UTC wall-clock instant captured inside the sink invocation attempt after the immutable response and closed summary are fixed. Its exact representation is `Y-m-d\TH:i:s.u\Z`: a four-digit year, whole date and time, exactly six fractional digits, and literal `Z`. It describes the destination record attempt, not request start, monotonic duration, response emission, client receipt, collector ingestion, or durable storage.

The adopting application owns a narrow explicit clock source and deterministic fixed-clock evidence. It does not use the process-default timezone or derive the value from collector time. A clock or formatting failure is a sink failure inside the same one invocation attempt and leaves the already-selected response unchanged, without retry, fallback, or another event.

### Finite levels

The complete level vocabulary is exactly:

1. `debug`
2. `info`
3. `warning`
4. `error`
5. `critical`

This deliberately smaller vocabulary does not adopt PSR-3 or add `psr/log`. `event` continues to identify what happened, `outcome` continues to describe the operation result, and `level` describes operational severity. No caller, request, exception, stored value, or arbitrary string selects a level.

The terminal HTTP summary mapping is mechanical and ordered:

1. `error` when `outcome` is `unknown_failure` or `response_status` is at least `500`;
2. otherwise `warning` when `query_failures` is greater than zero or `query_budget_exceeded` is true; and
3. otherwise `info`, including expected validation, authentication, authorization, routing, conflict, and missing-resource responses represented only by their existing generic status and `known_failure` outcome.

A terminal HTTP summary never infers `debug` or `critical`. One request-level unknown failure remains `error`; it does not by itself claim a process- or service-wide critical condition.

Separately adopted process-specific records may use the same finite vocabulary only through their own closed code-owned mapping: `debug` for explicitly enabled local diagnostic evidence, `info` for ordinary lifecycle activity, `warning` for recoverable degradation or a scheduled retry, `error` for one failed operation, and `critical` for a process or service that cannot safely start or continue. This does not create a generic event, message, or context bag.

The HTTP encoder is the sole owner of the mapping above. Every adopted HTTP summary that reaches it produces exactly one `info`, `warning`, or `error` record line before the selected destination write; there is no configurable minimum level, generic threshold API, or second copy of the mapping in the sink. A separately adopted non-HTTP closed process record owns its own finite code-owned emission policy, including whether a named `debug` record exists in a named environment. That policy does not become a shared level threshold or caller-selectable filter.

### Daily JSON file profile

An adopting daily-file destination uses one dedicated active path selected through typed application configuration and resolved to an absolute path before serving requests. It does not describe the shared PHP runtime error log as JSONL. The advisory starting convention is:

- local development after adoption: `<project-root>/var/log/application.jsonl`, generated inside the checkout but outside the public document root, ignored through the exact project-root `.gitignore` entry `/var/log/`, and never committed;
- host or virtual-machine production: `/var/log/<application>/application.jsonl` or an equivalent dedicated persistent mount outside the release tree; and
- containers: one explicitly selected stdout or stderr stream rather than a file on the ephemeral writable layer.

The leading slash in the project-root `.gitignore` pattern `/var/log/` anchors that ignored directory to the application checkout; it does not name or ignore the host's `/var/log` directory. `<application>` is one static non-sensitive deployment identifier, not a request, tenant, user, resource, or other per-record value. A deployment may select another explicit path when it records the same ownership, access, collection, and lifecycle facts.

The deployment pre-provisions a trusted parent directory and active regular non-symlink file with recorded owner, group, mode, writer identity, collector identity, and least-privilege read access. An untrusted principal must not be able to replace or relink the path. The application runtime and HTTP request path never create or repair the log directory or call `mkdir`, `touch`, `chmod`, or `chown` for this destination.

The preferred profile keeps one stable active filename and makes a deployment-owned process rotate it daily on an explicit UTC boundary. The application performs no rename, rotation, retention deletion, or compression inside an HTTP request. A date-named active file requires separate midnight, clock, concurrent-writer, and collector-discovery evidence rather than becoming a second default.

The application and deployment record and prove the exact append and lock behavior for every concurrently writing process; partial-write handling; descriptor reopen after rotation; collector position behavior; finite retention by days or file count; delayed compression; deletion and any backup or legal-hold policy; maximum disk use; low-space, inode-exhaustion, permission-loss, and read-only-filesystem behavior; and bounded synchronous write latency. A failed open, lock, write, flush when selected, close, or size check fails only the one sink attempt. It does not retry, switch destination, or alter the response.

For the recommended local path, project-root inspection uses `tail -F var/log/application.jsonl` under the same least-privilege access policy. `tail -F` follows a replaced active path for human inspection; it proves neither that an application writer reopened its descriptor nor that a collector delivered the record. The closed summary and envelope still contain no request values, credentials, customer data, SQL, bindings, exception messages, source paths, or stacks.

### Managed stdout or stderr profile

An adopting managed-stream destination writes the same one-LF-terminated envelope to one explicitly selected deployment stream. The supervisor, container runtime, or platform owns collection, rotation, retention, buffering, backpressure, outage behavior, and loss monitoring. The application proves its selected SAPI and multi-process line behavior rather than assuming that one write is atomic everywhere.

HTTP code never uses `echo`, `print`, or response stdout for log delivery. Existing application console stdout and stderr are exact command protocols under ADR 025 and related decisions; an application does not append log records to either stream without separately changing and testing that command contract. CLI, worker, scheduler, migration, bootstrap, fatal-runtime, web-server, response-emitter, and client-connection diagnostics remain separate process and event decisions rather than being silently routed through the HTTP terminal summary.

### Optional Grafana delivery

The recommended remote path is:

```text
PHPThis application
  -> dedicated daily JSON file or managed stream
  -> Grafana Alloy
  -> Loki or Grafana Cloud Logs
  -> Grafana
```

The application does not call Loki, Grafana, or Grafana Cloud synchronously from each request. Alloy owns file positions or stream collection, JSON parsing, timestamp extraction, batching, retry, credentials, TLS, network outage, backpressure, and collector monitoring. Rotated files remain available long enough for the collector to finish them; compression timing follows the recorded collector behavior.

By default, only stable static low-cardinality deployment dimensions such as service and environment become indexed Loki labels. A finite static process role such as `web`, `worker`, or `scheduler` may become a label only after measured query, volume, and cardinality evidence proves it is a bounded deployment dimension. A process identifier, PID, replica identifier, or another dynamic process value never becomes a label. Level and event name remain in the bounded JSON record or structured metadata unless the same measured evidence justifies splitting streams. Correlation and request identifiers, users, tenants, resources, paths, SQL fingerprints, and other high-cardinality or sensitive values remain in the bounded JSON record or structured metadata when permitted at all; they never become labels. Collector credentials remain outside PHP source, application output, AI context, fixtures, and committed configuration.

An adopter also records the selected Loki or Grafana Cloud tenant or account through a stable non-secret reference, remote retention and deletion policy, hosting region and data-residency decision, Grafana and log-store access owner, and incident owner. These are application and deployment facts, not record fields, labels, or framework defaults.

### Compatibility and ownership

This optional envelope adds destination guidance, not a new mandatory request event. ADR 023's mandatory version-1 payload and ADR 028's executable version-2 payload remain unchanged. Consumer Contract version 12 and Strict Profile version 3 remain current, with diagnostics `PHT001` through `PHT007` unchanged. The decision adds no framework source, runtime dependency, core line, logger, sink, coordinator, clock, configuration API, checker rule, or diagnostic.

An application adopting the destination profile records its exact envelope compatibility, worst-case record-size calculation, clock, HTTP level mapping or process-specific emission policy, destination, path or stream, permissions, concurrency, rotation, local and remote retention and deletion, compression, quota, outage, collector, selected Loki or Grafana Cloud tenant/account reference, region and data residency, labels, access owner, incident owner, and evidence in its project-owned context. The health-only skeleton and executable example retain their current deployment-configured `error_log()` sink and explicitly record that they have not adopted the pure JSONL daily-file or managed-stream profile.

## Evidence

The checked guidance and installed-consumer encoder proof preserve the unchanged version-1 and version-2 summary field sets while asserting the envelope's exact key order, fixed-instant UTC conversion and timestamp format, every HTTP level-mapping branch and precedence, compact JSON and one-LF framing, the 65,536-byte inclusive bound and overflow failure, complete redaction, exactly one sink invocation attempt, and coordinator response isolation after representative encoding, formatting, or size failure. It adds no framework clock abstraction, throwing-clock fixture, generic level-threshold or destination API, and does not certify a file, stream, collector, or deployment.

Each adopting application owns evidence for its selected clock source and clock-source failure, its exact worst-case record-size calculation, and its selected destination's open, write, flush when selected, close, latency, concurrency, backpressure, and failure behavior. HTTP adoption proves the fixed encoder-owned mapping without a suppression path; a non-HTTP adopter proves its closed process-specific emission policy. A file adopter additionally owns real regular-file and symlink rejection, permission and trusted-parent assumptions, concurrent writers, complete writes, UTC rotation boundary, rename/recreate and reopen behavior, collector handoff, finite retention and delayed compression, quota and disk-full behavior, and cleanup. A stream adopter owns real selected-SAPI and supervisor evidence without response-body or exact-console-stream corruption. Grafana delivery evidence names the selected tenant/account reference, remote retention/deletion, region/data residency, access owner, and incident owner. Documentation-only deployment advice must identify what remains unproved rather than presenting it as framework certification.

## Consequences

Consumers gain one bounded structured record that can be tailed locally and collected without altering the existing request-summary schema or adding a framework logger. Operators can distinguish ordinary requests, query degradation, and request failures through a finite level while retaining the current outcome, redaction, and one-attempt behavior.

The envelope adds application and deployment work. A synchronous file or stream write remains part of request latency, and response isolation does not establish bounded latency or delivery. Daily rotation, retention, collector lag, disk exhaustion, process-fatal coverage, response-emitter coverage, and remote availability remain operational facts that each adopter must own and prove. The profile is not an audit log or a durable, exactly-once, complete-process, or successful-response-delivery guarantee.

## Reconsider when

At least two independent applications prove that the same smaller destination-neutral record can cover HTTP and another process without a generic logger or arbitrary context; production evidence requires a different line bound, timestamp precision, or severity mapping; a selected runtime cannot preserve the recorded append or stream behavior; or an operational system requires durable delivery that cannot remain outside the request. Reconsider only the smallest proved application-owned boundary and do not infer a framework logger, hidden instrumentation, or request-path network transport.
