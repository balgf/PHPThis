# Terminal summary evidence

The canonical repository proof is `tests/observability.php`; the independently checked starter also exercises its real composition and front controller. The complete project gate remains mandatory.

Evidence covers:

- generated grammar, fresh request-scoped state, and owned `X-Request-ID` replacement;
- one decodable closed event from the default error-log sink;
- success, mapped and routed known failure, applicable denial, and class-only unknown failure;
- zero, repeated-query, budget-overrun, truncated, multiple, duplicate-name, shared-state, and excessive-source cases;
- omission of synthetic request, response, credential, domain, SQL, binding, DSN, and exception secrets, including source location embedded in anonymous throwable names; and
- exactly one sink invocation attempt with unchanged success and unknown-failure responses when the sink throws.

ADR 028 additionally proves that the executable example emits schema version `2` with exactly one `document_cache` snapshot, every finite read, write, and invalidation outcome, unchanged version-1 fields, complete omission of keys, values, identities, endpoints, credentials, and exception details, and still exactly one sink invocation attempt. Cache and lease operations do not emit their own log events.

These tests prove in-process construction and attempted invocation. They do not prove durable storage, pre-coordinator bootstrap coverage, process-fatal coverage, response-emitter success, network delivery, universal injection safety, or SQL performance. Use only generated or explicitly approved synthetic values.

The installed-consumer proof executes ADR 051's exact copyable destination-record encoder without changing framework or application runtime behavior. It covers the exact outer key order, an unchanged closed version-1 and isolated version-2 summary, fixed-instant UTC conversion with six microsecond digits, every HTTP level branch and precedence, compact one-LF JSON framing, the exact 65,536-byte inclusive boundary and overflow, fixed redacted formatting and encoding failures, and coordinator response isolation after one encoder failure. It performs no file or stream write and adds no framework clock, generic level-filter, or destination API.

An adopting application still owns evidence for its selected clock source and clock-source failure, exact worst-case fit from maximum source count, each source's `QueryTrace` fingerprint-retention limit, closed extensions, and encoder overhead, plus its selected destination. HTTP proves the encoder-owned map without suppression; a non-HTTP adopter proves its closed process-specific emission policy. Daily-file adoption adds trusted-parent, regular non-symlink file, exact permissions, concurrent-writer line integrity and partial-write behavior, UTC rollover, rotation/reopen, unwritable, inode-exhausted, read-only, and disk-full outcomes, retention-owner, and local-tail evidence. Alloy adoption validates the selected configuration and synthetic discovery, position recovery, rotation lag, labels, credential exclusion, tenant/account reference, remote retention/deletion, region/data residency, access ownership, and outage/drop incident monitoring. The installed encoder-only proof exists solely to validate the accepted copyable reference: it changes no accepted runtime or request-summary claim, while sizing, file, stream, and collector evidence remains adopter-owned and absent until an application deliberately adopts the applicable profile.
