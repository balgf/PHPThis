# Terminal event schema authority

ADR 023 defines the mandatory version-1 `application.request_summary` schema. `docs/consumer-contract.md` carries its application rules. ADR 028 advances only the executable Redis proof to version `2`: it preserves every version-1 field and adds exactly one `document_cache` object containing the finite `read`, `write`, and `invalidation` outcomes defined in `docs/redis/observability.md`. It does not add an optional payload bag or a second event.

When changing the mandatory schema, update together:

- ADR 023 and the Consumer Contract version decision;
- the application-owned `RequestSummary` closed PHPStan shape and `toArray()` order;
- exact success, known-failure, unknown-failure, aggregate, redaction, and serialization tests; and
- application AI context that records destination and source composition without copying the schema.

When changing the Redis proof's version-2 extension, update ADR 028, `docs/redis/`, the application's `DocumentDetailsCacheTrace` snapshot, request-summary shape and serialization, exact cache and observability tests, and example AI context together. Applications that have not accepted that backend-specific proof retain version `1`.

Outcome classification remains mechanical: caught unknown `Throwable`, otherwise status below 400, otherwise status 400 or above. Known denials gain no denial-specific field. A named unknown failure gains no exception-derived value beyond concrete class; an anonymous throwable uses its nearest named parent because the runtime anonymous-class name contains source location.
