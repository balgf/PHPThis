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
