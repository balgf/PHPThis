# ADR 023: Application-owned terminal request summaries

Status: accepted

## Context

PHPThis already keeps request handling on one visible path, maps only exact known failures, returns a generic response for an unknown failure, gives every database connection a statement budget, and retains a bounded redacted `QueryTrace` snapshot. The previous front-controller path wrote a separate class-only unknown-failure line through PHP's global error log. Known authentication and authorization denials wrote no line, while successful requests and database evidence had no request-level correlation.

Emitting one line per query would create noisy I/O and increase disclosure risk. A framework logger, facade, service locator, middleware pipeline, event dispatcher, automatic discovery mechanism, or hidden `Connection` instrumentation would obscure ownership and failure behavior. The core also has no evidence for a universal operational destination or delivery guarantee, and its 2,300-line Alpha 2 ceiling must not be expanded for an application policy.

## Decision

Every application owns one explicit terminal request-summary coordinator and one sink at its front-controller composition boundary. This decision adds no framework event, sink, coordinator, middleware, logger, or database-I/O type. Consumer Contract version 5 carries Strict Profile version 2 forward unchanged.

During request-scoped composition and before `TerminalRequestCoordinator::handle` begins bounded request ingestion, the application generates 128 random bits with the runtime's cryptographically secure source and encodes them as exactly 32 lowercase hexadecimal characters matching `[0-9a-f]{32}`. It does not derive this identifier from request data. The same value replaces any case-insensitive application response spelling and becomes the single `X-Request-ID` header in the final selected response, including mapped failures and the generic unknown-failure `500` response. Accepting an upstream identifier would require a separate application trust decision and cannot replace this required generated identifier.

The coordinator starts monotonic timing before bounded request ingestion. It receives the final immutable `Response` selected by normal handling, exact known-failure mapping, or the generic unknown-failure boundary. After any configured session finalization has selected that response, and before response emission, it builds one closed application-owned event with this schema:

```json
{
  "schema_version": 1,
  "event": "application.request_summary",
  "correlation_id": "00000000000000000000000000000000",
  "duration_us": 1250,
  "response_status": 200,
  "outcome": "success",
  "unknown_failure_class": null,
  "query_count": 1,
  "query_failures": 0,
  "query_execute_duration_us": 300,
  "query_budget_exceeded": false,
  "database_sources": [
    {
      "name": "primary",
      "budget_limit": 4,
      "budget_used": 1,
      "budget_exceeded": false,
      "query_trace": {
        "schema_version": 1,
        "event": "database.query_summary",
        "statements": 1,
        "failures": 0,
        "tracked_fingerprints": 1,
        "repeated_fingerprints": 0,
        "maximum_executions_per_fingerprint": 1,
        "total_execute_duration_us": 300,
        "slowest_execute_duration_us": 300,
        "truncated": false,
        "untracked_statements": 0,
        "queries": [
          {
            "fingerprint": "sha256:0000000000000000000000000000000000000000000000000000000000000000",
            "executions": 1,
            "failures": 0,
            "total_execute_duration_us": 300,
            "max_execute_duration_us": 300
          }
        ]
      }
    }
  ]
}
```

Every top-level key is present. `schema_version` is the integer `1`; `event` is exactly `application.request_summary`; `correlation_id` has the generated grammar above; `duration_us` is a non-negative integer from monotonic elapsed time; and `response_status` is the selected response status. `outcome` is `unknown_failure` when an unknown `Throwable` selected the generic response, otherwise `success` when `response_status < 400`, and otherwise `known_failure`. `unknown_failure_class` is `null` unless that unknown `Throwable` was caught. A named throwable contributes only its concrete class name. An anonymous throwable contributes its nearest named parent class because PHP's anonymous-class runtime name embeds its source path and line. An unknown failure contributes no message, code, previous exception, file, line, or stack. A known authentication, authorization, validation, routing, or other mapped failure uses only the generic `known_failure` outcome and its response status: it has no denial type, failure class, policy reason, resource, principal, or other denial-specific field.

`query_count`, `query_failures`, and `query_execute_duration_us` are non-negative sums across the listed sources, saturating at `PHP_INT_MAX` rather than overflowing. `query_budget_exceeded` is true when any listed budget rejected an attempted statement. These aggregate fields remain present and zero or false when the application registers no database source.

`database_sources` is an ordered finite list of at most eight entries declared in code at composition time. It registers every request-scoped `Connection` that can execute inside this coordinator path; an application does not omit a connection merely because one route does not use it. Each application-owned `name` is a unique non-sensitive lower-ASCII label matching `[a-z][a-z0-9_]{0,31}`, not a DSN, host, database name, account, tenant, or credential. Each element observes one distinct connection's `QueryBudget` and `QueryTrace`; no two sources share either observation object, and traces are never shared across connections. It always contains positive `budget_limit`, non-negative `budget_used`, boolean `budget_exceeded`, and its bounded `query_trace`. `budget_exceeded` becomes true when a call is rejected at the limit, while `budget_used` remains the number of statements admitted. The rejected call is absent from `QueryTrace` because PDO was not attempted.

`query_trace` embeds the existing bounded version-1 `QueryTrace::snapshot()` unchanged. Its SHA-256 values fingerprint exact SQL text and are pseudonymous operational identifiers, not encryption or anonymity. Timing still covers prepare, bind, and execute only; it excludes fetching, projection parsing, and the terminal sink. The application gives each connection a finite retained-fingerprint bound and keeps the existing deterministic truncation evidence. Applications without database work emit an empty `database_sources` list.

The event omits request method, target, path, query names and values, all headers, cookies, authorization data, body, response body and headers other than the separately propagated identifier, session and CSRF data, cache keys and values, principal, tenant, resource and customer identifiers, SQL text, parameter names and values, DSNs, credentials, driver details, exception messages and codes, source locations, and stack traces. The sink accepts only the application-owned immutable summary value exposing this closed scalar and bounded-array payload; it does not receive `Request`, `Response`, `Throwable`, `Connection`, `QueryBudget`, or `QueryTrace` objects.

For each request that reaches this application coordinator and selects a `Response`, the coordinator makes exactly one sink invocation attempt with the completed event. The sink call happens after the response and event are fixed. Any `Throwable` from that call is swallowed at this boundary: there is no retry, fallback log, second sink, response replacement, status change, or body/header mutation. Exactly one invocation attempt does not mean durable delivery. The event may be lost before, during, or after the sink call, and the sink owns any destination-specific buffering, transport, retention, backpressure, and outage policy outside the request contract.

This terminal scope does not claim to observe parse or bootstrap failures that occur before the coordinator starts, process-fatal errors that PHP cannot convert into the handled path, failures after response selection in a web server or client connection, or `ResponseEmitter` delivery. Those require separate explicit runtime evidence. The response summary records application response selection, not successful network delivery.

The former separate `phpthis.request.unhandled` global-error-log line is superseded. Known denials are no longer described as unlogged: they produce the same single terminal summary attempt as every other selected response, carrying status only. There is no additional denial, unknown-failure, per-query, or fallback event.

## Consequences

One request can be correlated with its returned `X-Request-ID`, terminal status, bounded elapsed time, and the explicitly registered per-connection query evidence without exposing request or SQL values. Repetitive query shapes, a budget rejection, and trace truncation remain deterministic machine-readable facts. The same event shape covers success, known failure, and unknown failure without a failure-specific logging path.

Applications must write and wire their coordinator and sink explicitly in the front controller or its ordinary composition root and must test success, mapped failure, known denial where applicable, unknown failure, zero and multiple separately named connections, duplicate-query aggregation, budget overrun, trace truncation, identifier grammar and response propagation, complete redaction, one sink invocation attempt, and a throwing sink that cannot alter the selected response. The sink test proves attempted invocation, not durable delivery.

The application repeats a small amount of explicit composition instead of receiving a universal observability API. No ORM, repository, query builder, SQL generator, SQL/binding/placeholder helper, logger facade, global helper, middleware, event pipeline, discovery mechanism, or hidden database instrumentation is accepted by this decision. Complete raw SQL and explicit named parameter arrays remain at direct `Connection` call sites.

## Reconsider when

At least two independent applications prove the same smaller sink boundary and destination-neutral delivery behavior under materially different runtimes, or a deployed runtime demonstrates that response selection cannot be summarized safely from one explicit application path. Reconsider one narrow evidence-backed contract without moving policy, request state, SQL values, or delivery claims into framework core.
