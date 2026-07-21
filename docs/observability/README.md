# Observability authority map

This directory is a compact AI routing layer, not a tutorial or a second contract. ADR 023 is the mandatory request-summary decision, ADR 028 owns the executable Redis proof's closed version-2 extension, `docs/consumer-contract.md` is the application validity floor, and `docs/logging.md` is the consolidated operational contract.

| Task | Read | Inspect |
| --- | --- | --- |
| Change event fields or outcome classification | [Event schema](event-schema.md), ADR 023 | application `RequestSummary`, serialization, and schema tests |
| Change request correlation | [Correlation ID](correlation-id.md), ADR 023 | application `CorrelationId`, coordinator header replacement, and response tests |
| Add or change a database source | [Database evidence](database-evidence.md), `docs/database.md` | application composition, direct raw-SQL call sites, distinct budgets and traces, and query tests |
| Change a sink or destination | [Sink failure](sink-failure.md), application `.ai/operations.md` | application sink, coordinator catch boundary, and throwing-sink test |
| Change Redis cache evidence | [Event schema](event-schema.md), [Redis observability](../redis/observability.md), ADR 028 | application `DocumentDetailsCacheTrace`, version-2 request summary, cache tests, redaction tests, and single-sink-attempt proof |
| Review required proof | [Testing](testing.md), `docs/evaluation.md` | `tests/observability.php`, skeleton behavior test, and complete project gate |

Do not infer a framework logger, event bus, middleware, facade, ORM, query builder, SQL/binding helper, delivery guarantee, or hidden instrumentation from these routes.
