# Global implementation rules

## Required

- Write direct, typed PHP with strict types.
- Prefer public data in immutable request/response values and explicit methods for behavior.
- Construct dependencies manually where the application starts.
- Keep I/O visible in method names and call sites.
- Parse external `mixed` values once into concrete final readonly projections or commands.
- Pass the complete Strict Profile; PHP execution without `composer check` is not sufficient verification.
- Add a test for success, expected failure, and resource bounds when relevant.
- Use one stable term for each concept: route, handler, connection, request, response, query budget, query trace.

## Forbidden

- Runtime discovery, reflection-based wiring, dynamic properties, macros, facades, service location, hidden globals, and magic methods except constructors.
- Database calls in loops or property access that can perform I/O.
- Positional SQL parameters, interpolated values, `SELECT *`, and unbounded collection reads.
- Silent exception conversion, implicit retries, or default success values after failure.
- Scalar casts or conversion functions used as validation for `mixed` input.
- Reflection hydration, generic domain collections, and unvalidated arrays crossing a boundary.
- Aliases or shortcuts that provide a second spelling for existing behavior.
- Baselines, inline ignores, wildcard exclusions, or comment exemptions for Strict Profile findings.
- Invented product intent, inferred human approval, or claims about PHPThis behavior unsupported by the current checkout.

If a task appears to require a forbidden mechanism, stop and propose a decision record describing the concrete need and a more explicit alternative.
