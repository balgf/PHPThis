# CLI composition

HTTP and CLI share only immutable application configuration and visible construction code. Every HTTP request and console process receives fresh connections, budgets, traces, correlation or clock state, and operation objects appropriate to that boundary. Request, session, and other mutable invocation state never cross entrypoints.

The accepted example's `ApplicationComposition` retains one canonical immutable database path. `http()` creates the complete fresh HTTP graph. `commands(UserWelcomeJobClock)` returns one explicit `ApplicationCommands` boundary, which creates the fresh job connection, budget, trace, and worker only when a direct or due command reaches the one-job operation.

The composition object is not injected into behavior and is not a container, service locator, registry, generic factory, framework extension point, or global. Submitted command text is matched only against the finite application-owned command enum.

See [the complete guide](../cli.md) and [ADR 025](../decisions/025-application-owned-explicit-cli-and-scheduler.md).
