# Durable-job envelope and dispatch

The stored envelope is bounded untrusted input. It contains an explicit integer version, one finite code-owned type, an application-generated idempotency key, and only the operation-specific payload fields required by that version.

The application owns one named parser for each accepted shape. It rejects invalid JSON, excess bytes or nesting, missing or unknown fields, coercive scalar types, unsupported versions, unsupported types, and invalid payload values before any handler is selected. The parser returns a concrete final readonly value; arbitrary arrays do not cross the dispatch boundary.

Dispatch is an exhaustive finite `match` over supported version and type combinations. Storage never supplies a PHP class name, service identifier, callback, or serialized object. Reflection, discovery, a service container, an event bus, and fallback handler lookup are outside the accepted recipe.

Changing an envelope requires an explicit compatibility decision for already stored rows, updated parser and dispatch code, bounds, migration or retirement policy, and tests for both accepted and rejected versions. Native `json_decode` keeps the last repeated object key; duplicate-key rejection requires a separately accepted parser and proof.

See [the complete durable-jobs guide](../jobs.md) and [ADR 024](../decisions/024-application-owned-sqlite-durable-jobs.md).
