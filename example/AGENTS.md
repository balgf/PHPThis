# Example application instructions

This directory is the executable consumer-facing PHPThis proof. It is application code, not framework core and not a traditional manual.

Before changing it:

1. Read `../docs/consumer-contract.md` and `../docs/knowledge-map.md`.
2. Read `../VISION.md`, this file, and `.ai/README.md`.
3. Load only the `.ai/` guide routed for the task.
4. Inspect the concrete route, handler, SQL call site, projection or command, and test.

Keep manual construction, typed boundaries, exact routes, query budgets, bounded traces, and complete raw engine-specific SQL visible. For database work, call `Connection` directly from the handler or the one independently justified transaction owner. Write the complete SQL string and its explicit named parameter array together at that call site.

Keep terminal observability in `src/Observability/` application-owned and explicitly composed in `bootstrap.php`. Preserve one generated 128-bit lowercase-hex correlation ID, the single `X-Request-ID` response header, five unique finite database sources represented by `QuerySummarySource` with distinct budgets and traces, the closed redacted event, and exactly one sink invocation attempt whose failure cannot alter the response. Do not claim durable delivery or move these types into framework core.

Do not add or use an ORM, Active Record, query builder, repository, generic paginator, SQL helper, binding helper, placeholder helper, generated or dynamic SQL, transaction callback, dialect abstraction, logging facade, middleware logger, discovery, global helper, or service container. Do not move example policy into framework `src/`.

The document-list SQL is SQLite-specific application evidence. Do not describe it as MySQL or PostgreSQL application-SQL support; the three-driver harness certifies only the framework PDO transport boundary.

Run `composer check` from the repository root. Report the exact behavior, query cost, engine scope, and proof limit; do not infer production authorization, injection safety, snapshot pagination, or query-plan guarantees from a passing example.
