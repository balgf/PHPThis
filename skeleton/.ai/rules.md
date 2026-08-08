# Universal application rules

These rules supplement installed PHPThis Consumer Contract v10 and Strict Profile v3. They apply to every change in this application; concern-specific contracts live in the current guide routed by `.ai/README.md`.

## Required

- Preserve the canonical terms in `.ai/project.md` and the dependency direction in `.ai/architecture.md`.
- Write direct, typed PHP with `declare(strict_types=1);`.
- Every named class is final. Express extension points with interfaces, never non-final classes.
- Construct dependencies manually at the visible composition root and keep I/O visible in method names and call sites.
- Keep routes explicit and handlers on `RequestHandler::handle(Request): Response`.
- Parse external `mixed` once into one bounded concrete final readonly boundary value before downstream behavior.
- An operation-specific request, command, or projection parsed from external `mixed` uses a private constructor. This requirement does not set identifier constructor visibility; an application-owned identifier follows its recorded coherent convention.
- Execute application SQL only through direct `Connection` calls with finite compile-time-constant engine-specific SQL and distinct named bindings.
- Map structural choices to finite code-owned values and reject unknown choices before I/O.
- Never execute a database call inside `for`, `foreach`, `while`, `do`, or recursive traversal.
- Keep every external side effect, failure path, and resource bound named in current application context.
- Add or update application-owned automated tests for every observable behavior change and run `composer check`.

## Forbidden

- Invented product behavior, schema meaning, limits, human approval, or external-service semantics.
- Runtime discovery, reflection wiring, string class resolution, dynamic properties, macros, facades, service location, hidden globals, or magic methods except `__construct`.
- ORM, Active Record, lazy loading, query builders, repositories, generic service layers, generated SQL, positional parameters, interpolation, SQL sanitizers, `SELECT *`, or unbounded collection reads.
- Hidden I/O, undocumented side effects, implicit retries, silent exception conversion, fallback credentials, default success after failure, or a second execution path.
- Scalar casts used as validation, repeated parsing of the same inbound representation, reflection hydration, mass assignment, or unvalidated arrays crossing a named boundary.
- Generic mechanisms that hide middleware, policy, configuration, cache, queue, logging, migration, storage, WebSocket, console, or Workbench behavior. Follow the routed concern guide's explicit application-owned boundary.
- Baselines, inline ignores, wildcard exclusions, broad ignores, comment exemptions, or a weaker analysis level.
- Credentials, tokens, private keys, customer data, production payloads, or other secrets in context, code, fixtures, logs, or reports.

If a task requires a forbidden mechanism or a new consequential decision, stop at that boundary and request accountable-human judgment.
