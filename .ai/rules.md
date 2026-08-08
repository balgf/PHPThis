# Universal implementation rules

These rules apply to every framework-maintainer change. Concern-specific contracts live in the current guide routed by `.ai/README.md`.

## Required

- Write direct, typed PHP with `declare(strict_types=1);`.
- Every named class is final. Express extension points with interfaces, never non-final classes.
- Construct dependencies manually at the visible composition root; keep I/O visible in method names and call sites.
- Use immutable request and response values and explicit behavior methods. Keep handlers on `RequestHandler::handle(Request): Response`.
- Keep routes in explicit finite lists and use only the documented exact literal or typed full-segment declarations.
- Parse external `mixed` once into one bounded concrete final readonly boundary value before downstream behavior.
- An operation-specific request, command, or projection parsed from external `mixed` uses a private constructor. This requirement does not set identifier constructor visibility; an application-owned identifier follows its recorded coherent convention.
- Execute application SQL only through direct `Connection` calls. Keep SQL engine-specific, finite, non-blank, and PHPStan-resolvable to compile-time constants; bind every data value with a distinct named placeholder per occurrence.
- Map every external structural selector to finite reviewed code-owned choices and reject unknown choices before I/O.
- Never execute a database call inside `for`, `foreach`, `while`, `do`, or recursive traversal.
- Preserve one canonical execution pattern for each framework task and one stable term for each concept.
- Add or update automated tests for observable behavior and run `composer check` before reporting completion.

## Forbidden

- Runtime discovery, reflection-based wiring, string class resolution, dynamic properties, macros, facades, service location, hidden globals, and magic methods except `__construct`.
- ORM, Active Record, lazy loading, query builders, repository or generic service layers, generated SQL, positional parameters, interpolated data, SQL sanitizers, `SELECT *`, and unbounded collection reads.
- Hidden I/O, implicit retry, silent exception conversion, default success after failure, or a second spelling or execution path for existing behavior.
- Scalar casts or conversion functions used as validation for external `mixed`; reflection hydration, mass assignment, or unvalidated arrays crossing a named boundary.
- Generic framework or application abstractions that hide optional middleware, policy, configuration, cache, queue, logging, migration, storage, WebSocket, console, or Workbench behavior. Use the routed concern guide's explicit application-owned boundary.
- Baselines, inline ignores, wildcard exclusions, broad ignore patterns, comment exemptions, or a weaker PHPStan level for Strict Profile findings.
- Invented intent, approval, external-system facts, or behavior unsupported by the current checkout.
- Credentials, tokens, private keys, customer data, production payloads, or other secrets in AI context, source comments, fixtures, logs, or reports.

If a task appears to require a forbidden mechanism or a new consequential decision, stop implementation at that boundary and present the concrete need for accountable-human judgment.
