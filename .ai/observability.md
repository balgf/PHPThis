# Terminal request-summary contract

Read ADR 023 and `docs/logging.md` before changing request correlation or operational summaries.

Rules:

- Keep the coordinator, event construction, and sink application-owned and explicitly wired at the front-controller composition boundary. Add no core event, sink, coordinator, logger, middleware, facade, helper, discovery mechanism, or hidden instrumentation.
- Generate 128 random bits during request-scoped composition before bounded request ingestion and encode exactly 32 lowercase hexadecimal characters. Propagate that value as `correlation_id` and the single `X-Request-ID` response header; never echo an arbitrary incoming identifier.
- Preserve ADR 023's mandatory closed version-1 `application.request_summary` schema with monotonic duration, selected response status, generic outcome, nullable class-only unknown failure, aggregate query evidence, and at most eight finite code-owned database sources. The executable Redis example alone advances to version `2`, preserving those fields and adding exactly one bounded `document_cache` snapshot from `DocumentDetailsCacheTrace`; the health-only skeleton remains version `1`.
- Give each source a bounded lower-ASCII label, explicit `QueryBudget`, and distinct bounded `QueryTrace`. Preserve raw engine-specific SQL and explicit named parameter arrays at direct `Connection` call sites; do not add an ORM or binding helper to produce observability.
- Known denials have only the generic known-failure outcome and selected status. Named unknown failures contribute only their concrete class; anonymous throwables use the nearest named parent because their runtime class name embeds source location. Omit every request, credential, domain, response-body, SQL, binding, DSN, driver, message, source-location, and stack value.
- Make exactly one sink invocation attempt after the response and event are fixed. Cache and lease operations add no separate log write. Swallow sink failure without retry, fallback, another event, or response mutation. Never claim durable delivery or successful response emission.

Tests cover success, mapped failure, known denial where applicable, unknown failure, identifier grammar and propagation, zero and multiple sources, duplicate-query aggregation, budget overrun, trace truncation, complete redaction, exactly one invocation attempt, and a throwing sink that leaves the response unchanged. The Redis proof also covers the exact version-2 `document_cache` shape and all finite cache outcomes without keys, values, identities, endpoints, credentials, or exception details.
