# Observability authority map

This directory is a compact AI routing layer, not a tutorial or a second contract. ADR 023 is the mandatory request-summary decision, ADR 028 owns the executable Redis proof's closed version-2 extension, `docs/consumer-contract.md` is the application validity floor, and `docs/logging.md` is the consolidated operational contract. The log-level, destination-record, and destination-profile pages are accepted optional ADR 051 guidance; they change no existing application runtime unless that application deliberately adopts the profile.

| Task | Read | Inspect |
| --- | --- | --- |
| Change event fields or outcome classification | [Event schema](event-schema.md), ADR 023 | application `RequestSummary`, serialization, and schema tests |
| Change request correlation | [Correlation ID](correlation-id.md), ADR 023 | application `CorrelationId`, coordinator header replacement, and response tests |
| Add or change a database source | [Database evidence](database-evidence.md), `docs/database.md` | application composition, direct raw-SQL call sites, distinct budgets and traces, and query tests |
| Change sink failure isolation | [Sink failure](sink-failure.md), application `.ai/observability.md` | application sink, coordinator catch boundary, one-attempt behavior, and throwing-sink test |
| Adopt or review finite log levels | [Operational log levels](log-levels.md), ADR 051 | encoder-owned HTTP map or closed process-specific map and emission policy, redaction, and level evidence; preserve request-summary v1/v2 and current Contract v18 |
| Adopt or review the exact destination-record encoder | [Destination-record reference](destination-record.md), [Operational log levels](log-levels.md), ADR 051 | exact copied application-owned encoder, closed summary, fixed-instant UTC conversion, level map, byte bound, failure isolation, and installed proof; add no generic logger or writer |
| Adopt or review stdout/stderr, daily-file, or Grafana delivery | [Operational log destination profiles](destination-profiles.md), [Sink failure](sink-failure.md), ADR 051 | application `.ai/observability.md`, selected process destination, file or supervisor operations, Alloy/Loki policy, and destination evidence; do not mistake the encoder proof for destination-I/O certification |
| Change Redis cache evidence | [Event schema](event-schema.md), [Redis observability](../redis/observability.md), ADR 028 | application `DocumentDetailsCacheTrace`, version-2 request summary, cache tests, redaction tests, and single-sink-attempt proof |
| Review required proof | [Testing](testing.md), `docs/evaluation.md` | `tests/observability.php`, skeleton behavior test, and complete project gate |

Do not infer a framework or generic application logger, level enum, event bus, middleware, facade, ORM, query builder, SQL/binding helper, delivery guarantee, or hidden instrumentation from these routes.
