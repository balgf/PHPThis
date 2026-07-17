# ADR 002: Visible SQL and no ORM

Status: accepted

## Context

Object relationships and fluent query abstractions can hide I/O and make query cost non-local.

## Decision

PHPThis provides a thin PDO connection only. Applications write complete SQL with named parameters. There is no ORM, Active Record, lazy loading, relationship API, or query builder.

## Consequences

SQL is reviewable and database-specific capabilities remain available. Applications own mapping and must still prevent explicit queries inside loops.

## Reconsider when

Not planned. Typed row mapping may be considered separately if it performs no I/O and does not generate SQL.
