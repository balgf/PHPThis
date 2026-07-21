# Example terminal-summary context

The executable example follows ADR 023 with application types under `src/Observability/`, manual composition in `bootstrap.php`, and invocation from `public/index.php`.

- Correlation: one `CorrelationId::generate()` per request-scoped composition; the coordinator replaces any application response spelling with the generated `X-Request-ID`.
- Sink: one injected `ErrorLogRequestSummarySink`; sink failure cannot alter the selected response and one attempt is not durable delivery.
- Sources: `list_users`, `get_user`, `create_user`, `get_document`, and `list_documents`. Each owns the exact operation connection's distinct budget and trace.
- Event: request-summary schema version `2`, carrying ADR 023's closed request fields plus one bounded `document_cache` snapshot from `DocumentDetailsCacheTrace`. Denials carry generic known-failure outcome and status; unknown failures add only concrete class.
- Evidence: `tests/observability.php`, included by the repository test runner, owns schema, correlation, source, budget, redaction, freshness, and throwing-sink proof.

The application console is not an HTTP request and does not enter this terminal request-summary event. `example/bin/console.php` emits only one-line stdout success or one-line stderr error. `.ai/cli.md` owns command, stream, cadence, Redis coordination, and console-redaction facts; `.ai/jobs.md` owns job outcomes, durable diagnostics, and worker lifecycle. Do not place an argument, database or migration-lock path, Redis endpoint, key, value, owner token, raw reply, envelope, payload, idempotency key, SQL, binding, request value, or exception detail in any channel.

ADR 028 cache evidence is bounded and redacted within the existing terminal request-summary attempt; it does not add a per-operation log or second sink invocation. The exact `document_cache` snapshot exposes only finite `read`, `write`, and `invalidation` outcomes. It omits complete keys, values, tenant and document identities, endpoints, credentials, and Redis errors. Every schedule success adds a bounded `coordination` list after `command` and `outcome`; a Redis operational failure adds that list after `error: command_failed`. `not_due` uses an empty list, while contention and owned work expose only code-owned lifecycle outcomes. Owner tokens and Redis configuration never enter either channel.

All SQL remains complete raw SQLite text with explicit named parameter arrays at direct `Connection` call sites. Do not add an ORM, repository, query builder, paginator, SQL/binding/placeholder helper, generated SQL, framework logger, middleware, facade, per-query event, or hidden instrumentation.
