# Terminal event schema authority

ADR 023 defines the only accepted version-1 `application.request_summary` schema. `docs/consumer-contract.md` carries its mandatory application rules; do not invent aliases, optional payload bags, or a second event shape here.

When changing the schema, update together:

- ADR 023 and the Consumer Contract version decision;
- the application-owned `RequestSummary` closed PHPStan shape and `toArray()` order;
- exact success, known-failure, unknown-failure, aggregate, redaction, and serialization tests; and
- application AI context that records destination and source composition without copying the schema.

Outcome classification remains mechanical: caught unknown `Throwable`, otherwise status below 400, otherwise status 400 or above. Known denials gain no denial-specific field. A named unknown failure gains no exception-derived value beyond concrete class; an anonymous throwable uses its nearest named parent because the runtime anonymous-class name contains source location.
