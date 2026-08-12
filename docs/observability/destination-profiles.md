# Operational log destination profiles

[ADR 051](../decisions/051-application-owned-structured-log-destinations.md) accepts this optional application-owned profile. It adds no PHPThis runtime, dependency, sink, helper, or delivery guarantee and does not change the accepted request-summary schemas.

The optional profile does not change the application sink interface: the explicitly injected HTTP sink still receives exactly one closed `RequestSummary`. The encoder is the sole owner of the fixed HTTP level map and produces exactly one bounded record line before the adopting application attempts its one explicitly selected process destination. A collector or supervisor may forward the selected destination afterward. The application does not synchronously contact Grafana, Loki, Grafana Cloud, or another remote log service from the HTTP request path.

## Exact record envelope

Each newline-delimited JSON record has exactly these top-level fields in this order:

```json
{
  "record_schema_version": 1,
  "occurred_at": "2026-08-12T00:00:00.000000Z",
  "level": "info",
  "summary": {}
}
```

The empty `summary` object is abbreviated only for presentation. An emitted HTTP record contains every field of its accepted closed request-summary version and no additional summary field.

`record_schema_version` is the integer `1`. `occurred_at` is a UTC timestamp with exactly six fractional decimal digits and the literal `Z`, matching `YYYY-MM-DDTHH:MM:SS.ffffffZ`. The sink samples it through one narrow explicit application clock inside its existing single invocation attempt, after the immutable summary is fixed and before encoding and destination I/O. It does not use the process-default timezone. The value records when that sink attempt prepared the record; it does not claim request start, response emission, client receipt, collector ingestion, or durable storage time.

`level` is one of the five values in [Operational log levels](log-levels.md). `summary` is one closed bounded application-owned summary. For HTTP, it is the unchanged accepted version-1 `application.request_summary` object or the unchanged Redis proof's accepted version-2 object.

The complete compact UTF-8 JSON encoding plus its one final LF byte is at most 65,536 bytes. It uses `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES` and contains no BOM, prefix, suffix, embedded physical newline, truncation, or split record. Oversize, clock, formatting, encoding, open, lock, append, flush when selected, close, or write failure is sink failure. The HTTP coordinator contains that failure through ADR 023's existing one-attempt rule: no retry, fallback destination, second record, or response mutation occurs.

Before adoption, calculate and prove the worst-case valid record from the application's exact maximum database-source count, every source's selected `QueryTrace` retained-fingerprint limit, all closed summary extensions, the fixed envelope and JSON-escaping overhead, and the final LF. The post-encode rejection protects the boundary if those assumptions drift; it does not make silent loss of an otherwise valid maximum summary an acceptable sizing policy.

The envelope is destination framing, not a general event API. An application does not pass arbitrary arrays, messages, exception objects, request objects, or context bags to it.

The [checked destination-record reference](destination-record.md) implements only this final encoding boundary for an existing concrete HTTP `RequestSummary`. Its proof performs no destination I/O and therefore certifies none of the stream, file, collector, or delivery facts below.

## Stdout or stderr

Stdout or stderr is the preferred process destination when a container runtime, service manager, or supervisor already owns collection, rotation, retention, capacity, and access. The application records exactly which stream receives records and does not secretly duplicate them to both streams or to a file. HTTP code never uses `echo`, `print`, or response stdout for operational log delivery.

Do not infer an `info`-to-stdout and `error`-to-stderr split. Stream selection is process policy, while `level` remains record data. A machine-readable application console keeps its accepted stdout and stderr result contract free of unrelated log records; its supervisor may collect a separately selected process destination only after an explicit application decision preserves those exact command bytes.

Production evidence names the selected SAPI, every writing process, line-integrity behavior, stream collector, process identity, buffering and maximum blocking behavior, restart behavior, capacity and drop policy, retention, access controls, and incident owner. A successful write to a stream is not proof that a collector retained or delivered the record.

## Daily file

A daily-file profile is valid for a local, virtual-machine, or other explicitly recorded filesystem topology. It is not a universal production default and must not be inferred for an ephemeral container filesystem.

Use these starting conventions unless the application's recorded topology requires a different explicit policy:

- for local development, use `<project-root>/var/log/application.jsonl`, add the exact project-root `.gitignore` entry `/var/log/`, and have typed configuration resolve the destination to an absolute path before serving requests;
- for a host or virtual-machine deployment, use `/var/log/<application>/application.jsonl` or an equivalent dedicated persistent mount outside the release tree; and
- for a container, prefer the selected stdout or stderr profile above rather than a file on its ephemeral writable layer.

Only an application that adopts this local-file profile adds the directory and ignore rule. The generated `var/log` directory is inside the project checkout but outside the public document root; its generated contents are ignored and never committed. The leading slash in the project-root `.gitignore` syntax `/var/log/` anchors the pattern to that checkout and does not refer to or ignore the host's `/var/log` directory. `<application>` is one static non-sensitive deployment identifier, not request, tenant, user, resource, or other per-record input. A different local filename or persistent production mount is valid when the application records the exact reason, ownership, access, collection, and lifecycle policy. File configuration resolves to a validated absolute path so the sink does not depend on the process working directory.

Use a dedicated active file for this envelope. Do not describe PHP's shared deployment-configured `error_log` as pure JSONL: the runtime may prefix output and may send unrelated diagnostics to the same destination.

Record all of these application and deployment facts:

- one dedicated active path selected through typed application configuration, with its absolute directory outside the public document root, plus the exact generated local path that is ignored and never committed;
- the filename policy, UTC day boundary, owner, group, directory mode, file mode, and process authority;
- regular-file and non-symlink enforcement before append, including ownership of creation and replacement;
- local-filesystem or other exact topology, every concurrent writer, append/locking behavior, maximum write latency, and line-integrity evidence;
- one stable active filename with external daily rotation, or one UTC-dated filename with tested midnight selection and reopen behavior;
- for rename-and-reopen or another rotation mechanism, the exact signal/reopen owner and race behavior; do not assume `copytruncate`, rename, or deletion is lossless;
- finite retention by days or count, compression timing, deletion and legal-hold policy, quota, low-space alert, disk-full behavior, and cleanup owner; and
- whether a collector must finish a rotated file before compression or deletion and how lag is observed.

The deployment pre-creates the trusted parent directory and active regular non-symlink file before an application process starts; an untrusted principal cannot replace or relink either path. The application runtime and HTTP request path never create or repair the log directory or call `mkdir`, `touch`, `chmod`, or `chown` for the destination. When one application writer identity and one collector group describe the topology, POSIX mode `0750` for the directory and `0640` for the file is the recommended least-privilege starting point. Multiple writer identities, a collector outside that group, a non-POSIX filesystem, or a platform-managed mount requires a separately recorded ownership and access policy rather than assumed modes.

A stable active filename plus an external UTC daily rotator is the preferred starting point because the deployment owns the rotation boundary, and the application performs no rename, rotation, compression, retention deletion, or cleanup inside an HTTP request. A UTC-dated filename remains valid when every writer derives the same name, opens or reopens it under a tested finite policy, and the deployment proves line integrity across midnight. Neither shape is durable delivery.

For the recommended local path, record this exact project-root workflow:

```sh
tail -F var/log/application.jsonl
```

An application using another selected non-sensitive path records the corresponding exact command. `tail -F` follows replacement of the active path for human inspection; it proves neither that an application writer reopened its descriptor nor that a collector delivered the record. The command is a debugging workflow, not a production health check or retention proof. Never commit generated log files.

## Grafana delivery

The recommended topology is:

```text
application -> selected file or stdout/stderr -> Grafana Alloy -> Loki or Grafana Cloud Logs -> Grafana
```

Grafana is the query and visualization surface; Loki or Grafana Cloud Logs stores the collected records. Grafana Alloy owns file or stream collection, credentials, TLS, positions, batching, retries, remote backpressure, outage behavior, and delivery monitoring. Those concerns do not become an application request retry or a second sink attempt. Use Grafana's current [Collect logs with Alloy](https://grafana.com/docs/grafana-cloud/observe-and-act/send-data/logs/collect-logs-with-alloy/) guidance for the selected Grafana Cloud deployment rather than embedding a mutable universal collector configuration in application source.

For a file destination, Alloy's [`loki.source.file`](https://grafana.com/docs/alloy/latest/reference/components/loki/loki.source.file/) forwards entries after their LF terminator, uses an absolute path, retains read positions under its configured storage path, and supports built-in glob discovery through the recommended `file_match` block. Daily rotation and retention must leave a rotated file available long enough for the recorded collector-lag bound. Compression requires a separately tested delayed decompression policy; do not compress or delete a file merely because the application crossed midnight.

Default indexed Loki labels to stable service, environment, and selected static deployment dimensions only. A finite static process role such as `web`, `worker`, or `scheduler` may become a label only after measured query frequency, volume, and cardinality evidence proves it is a bounded deployment dimension. A process identifier, PID, replica identifier, or another dynamic process value never becomes a label. Keep finite `level` and event name in the JSON record or bounded structured metadata by default; promote either to an indexed label only after the same measured evidence justifies the resulting stream split. Grafana's [label guidance](https://grafana.com/docs/loki/latest/get-started/labels/bp-labels/) specifically warns against dynamic labels and notes that even `level` need not be indexed. Keep `correlation_id`, request, user, tenant, resource, path, SQL fingerprint, and every other per-record identifier out of indexed labels. Leave an already permitted value in the JSON line or deliberately extract it as bounded [structured metadata](https://grafana.com/docs/loki/latest/get-started/labels/structured-metadata/) only when the adopted Loki version, schema, limits, and query need justify that choice.

Record the selected Loki or Grafana Cloud tenant or account through a stable non-secret reference; the remote retention and deletion policy; the hosting region and data-residency decision; the Grafana and log-store access owner; and the incident owner. These facts stay in application and deployment context. They are not record fields, labels, credentials, or framework-selected defaults.

Do not put Loki or Grafana credentials in PHP source, application AI context, record fields, filenames, or labels. Do not recommend Promtail: Loki 3.7.3 [removed Promtail and merged its code into Grafana Alloy](https://grafana.com/docs/loki/latest/release-notes/v3-7/).

## Failure, redaction, and evidence boundary

The record inherits its closed summary's redaction and adds only a code-owned schema version, sink-time timestamp, and finite level. It adds no message, stack, source path, request value, credential, SQL, binding, response body, principal, tenant, resource, or customer data. A log destination is not an audit ledger; file append and collector forwarding do not prove durable, unique, ordered, or exactly-once delivery.

Application evidence proves exact envelope keys and order, fixed-clock UTC timestamp grammar, the encoder-owned HTTP level mapping and precedence, the adopter's exact worst-case record-size calculation, compact encoding, the 65,536-byte limit including LF, one complete JSON object per line, complete redaction, and destination failure behavior. A non-HTTP adopter separately proves its closed process-specific emission policy. An adopted daily file additionally proves trusted parent and path, permissions, non-symlink regular-file handling, parallel-writer line integrity and partial-write handling, UTC rollover, rotation/reopen, unwritable, inode-exhausted, read-only, and full-disk behavior, retention ownership, and the recorded local tail workflow with synthetic data. An adopted Alloy path validates its configuration and proves synthetic line discovery, positions across restart, rotation and lag behavior, low-cardinality labels, credential exclusion, the selected tenant/account reference, remote retention/deletion, region/data residency, access ownership, and outage/drop incident monitoring. None of that evidence expands ADR 023's one-attempt or delivery claim.
