# ADR 005: Bounded query tracing

Status: accepted

## Context

Query budgets stop excessive statement counts but do not explain which SQL shape repeated, how long execution took, or whether PDO failed. Writing every query directly to a log would add noisy I/O and risk exposing SQL or parameter values.

## Decision

Every `Connection` requires a request-scoped `QueryTrace`. It aggregates SHA-256 fingerprints of exact SQL strings, execution counts, failures, and prepare/bind/execute duration without retaining SQL, parameters, exceptions, or credentials. Retained fingerprints have an explicit bound; overflow remains visible in the snapshot.

The stable snapshot is an in-memory, JSON-compatible record. Tests inspect it directly. The database layer performs no logging I/O.

ADR 023 later accepts one application-owned terminal request summary that derives a bounded per-source record from this snapshot. `Connection` still emits nothing, and the framework adds no logger, sink, event, coordinator, or hidden instrumentation.

The temporary core-source limit increased from 500 to 550 physical lines so this behavior remained direct and typed instead of compressed or hidden behind a dependency. ADR 008 supersedes that Phase 0 limit for the explicit request boundary.

## Consequences

Repetitive statements become machine-readable while sensitive values stay absent. Trace memory is bounded, and database failures continue unchanged. Timing covers prepare, bind, and execute; it does not claim to include fetching or row conversion.

## Reconsider when

A production application proves that the snapshot schema, fingerprint bound, or application-owned terminal summary needs a different explicit contract.
