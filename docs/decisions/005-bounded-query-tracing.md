# ADR 005: Bounded query tracing

Status: accepted

## Context

Query budgets stop excessive statement counts but do not explain which SQL shape repeated, how long execution took, or whether PDO failed. Writing every query directly to a log would add noisy I/O and risk exposing SQL or parameter values.

## Decision

Every `Connection` requires a request-scoped `QueryTrace`. It aggregates SHA-256 fingerprints of exact SQL strings, execution counts, failures, and prepare/bind/execute duration without retaining SQL, parameters, exceptions, or credentials. Retained fingerprints have an explicit bound; overflow remains visible in the snapshot.

The stable snapshot is an in-memory, JSON-compatible record. Tests inspect it directly. The request boundary does not yet add request context or emit the snapshot; the database layer performs no logging I/O.

The temporary core-source limit increased from 500 to 550 physical lines so this behavior remained direct and typed instead of compressed or hidden behind a dependency. ADR 008 supersedes that Phase 0 limit for the explicit request boundary.

## Consequences

Repetitive statements become machine-readable while sensitive values stay absent. Trace memory is bounded, and database failures continue unchanged. Timing covers prepare, bind, and execute; it does not claim to include fetching or row conversion.

## Reconsider when

A production application proves that the snapshot schema, fingerprint bound, or request-boundary emission needs a different explicit contract.
