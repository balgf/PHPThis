# CLI composition

HTTP and CLI share only immutable application configuration and visible construction code. Every HTTP request and console process receives fresh connections, budgets, traces, correlation or clock state, and operation objects appropriate to that boundary. Request, session, and other mutable invocation state never cross entrypoints.

The accepted example's `ApplicationComposition` retains explicit immutable application configuration. `http()` creates the complete fresh HTTP graph, including cache-scoped Redis state only for the protected cache path. The CLI composition returns one explicit `ApplicationCommands` boundary, which creates fresh Redis lease and job-scoped state only when a due command reaches the one-job operation and fresh separately authorized migration-scoped state only when `database:migrate` runs. No migration state is constructed or executed from HTTP startup, and no Redis object is shared between request and command processes.

The composition object is not injected into behavior and is not a container, service locator, registry, generic factory, framework extension point, or global. Submitted command text is matched only against the finite application-owned command enum.

See [the complete guide](../cli.md), [ADR 025](../decisions/025-application-owned-explicit-cli-and-scheduler.md), [ADR 027](../decisions/027-application-owned-explicit-sqlite-migrations.md), and [ADR 028](../decisions/028-application-owned-redis-cache-and-schedule-lease.md).
