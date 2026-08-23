# Operational log levels

[ADR 051](../decisions/051-application-owned-structured-log-destinations.md) accepts this optional application-owned profile. It left Consumer Contract version 12 unchanged; Contract version 14 carries version 13, Strict Profile version 3, and the accepted version-1 and version-2 request-summary schemas forward unchanged.

An application that adopts this profile uses exactly five lower-ASCII levels: `debug`, `info`, `warning`, `error`, and `critical`. A code-owned event type has one fixed level or one finite code-owned mapping from its closed outcomes to those levels. Caller input, exception text, response content, stored values, and arbitrary strings never select a level.

Levels answer operational urgency. They are separate from an event name, an HTTP response status, and the terminal request summary's `success`, `known_failure`, or `unknown_failure` outcome:

- `debug` is a named diagnostic event emitted only under a closed process-specific policy. It is absent from HTTP request-summary records.
- `info` is an expected lifecycle or completed operation outcome, including an ordinary handled client failure.
- `warning` is a named degraded condition from which the current operation can still produce its recorded result.
- `error` is a failed operation or selected server failure that needs investigation but does not by itself mean the whole service is unusable.
- `critical` is a named condition in which a process or required service cannot safely continue. An ordinary request `500` is not `critical`.

Do not add `notice`, `alert`, `emergency`, custom synonyms, numeric aliases, or a caller-selectable level. A different vocabulary requires a separate application decision and cannot be presented as this profile.

## HTTP request-summary mapping

The destination record derives the HTTP level mechanically, in this precedence order:

1. `error` when `outcome` is `unknown_failure` or `response_status` is at least `500`;
2. otherwise `warning` when `query_failures` is greater than zero or `query_budget_exceeded` is `true`; and
3. otherwise `info`.

HTTP request summaries never use `debug` or `critical`. A `4xx` response is not automatically a warning or error, and a handled `5xx` remains `error` even when its outcome is `known_failure`. The mapping consumes only fields already present in the closed request summary; it adds no denial reason, exception message, request value, SQL, binding, or other field.

The level belongs to the optional destination record, not to the accepted `application.request_summary` object. The request summary remains exactly version `1`, or exactly the accepted Redis proof's version `2`, inside the record's `summary` field.

The destination-record encoder is the sole owner of this HTTP mapping. Every valid request summary passed to it produces exactly one `info`, `warning`, or `error` record line before the selected destination write. HTTP has no configurable minimum level, generic severity filter, or second sink-owned copy of the mapping.

## Other process summaries

A CLI, worker, scheduler, WebSocket, or integration process owns its own finite event types and level mapping. It does not reuse HTTP status rules, accept a free-form message/context bag, or route every internal action through a generic logger. Existing command stdout and stderr contracts are process results, not operational log records, and remain unchanged unless a separate application decision explicitly composes a bounded log destination around that process.

Each adopted process records:

- the exact closed summary type and maximum serialized bytes;
- the finite event and outcome vocabulary;
- the complete outcome-to-level mapping and code-owned emission policy;
- whether a named `debug` event exists and in which named environments it is emitted;
- whether a `critical` record terminates the process or merely reports an already selected termination; and
- the exact sink, failure behavior, and automated evidence.

A non-HTTP process emission policy remains local to that closed process record. It does not expose a generic level threshold, accept caller-selected severity, or alter the unconditional HTTP map above.

This profile adds no framework or generic application logger, level enum, facade, helper, middleware, event bus, context bag, discovery mechanism, runtime dependency, or hidden instrumentation.
