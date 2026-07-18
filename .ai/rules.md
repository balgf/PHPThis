# Global implementation rules

## Required

- Write direct, typed PHP with strict types.
- Prefer public data in immutable request/response values and explicit methods for behavior.
- Construct dependencies manually where the application starts.
- Keep I/O visible in method names and call sites.
- Parse external `mixed` values once into concrete final readonly projections or commands.
- Execute application SQL only through direct `Connection` calls, bind every data value with a distinct named placeholder per occurrence, and keep the final SQL a finite non-blank compile-time constant.
- Map external SQL-structure selectors to finite reviewed code-owned choices and reject unknown selectors before database work.
- Pass the complete Strict Profile; PHP execution without `composer check` is not sufficient verification.
- Add a test for success, expected failure, and resource bounds when relevant.
- Use one stable term for each concept: route, handler, connection, request, response, response cookie, session lifecycle, session snapshot, session unavailable, query budget, query trace.

## Forbidden

- Runtime discovery, reflection-based wiring, dynamic properties, macros, facades, service location, hidden globals, and magic methods except constructors.
- Database calls in loops or property access that can perform I/O.
- Positional SQL parameters, interpolated data values, runtime-built SQL structure, SQL sanitizers, `SELECT *`, and unbounded collection reads.
- A runtime database identity with migration, schema-change, user-management, or other authority not required by its application paths.
- Silent exception conversion, implicit retries, or default success values after failure.
- Scalar casts or conversion functions used as validation for `mixed` input.
- Reflection hydration, generic domain collections, and unvalidated arrays crossing a boundary.
- Aliases or shortcuts that provide a second spelling for existing behavior.
- Direct application access to `$_SESSION`, native `session_*` calls, generic session helpers, or authentication state stored without a typed application boundary.
- Baselines, inline ignores, wildcard exclusions, or comment exemptions for Strict Profile findings.
- Invented product intent, inferred human approval, or claims about PHPThis behavior unsupported by the current checkout.

If a task appears to require a forbidden mechanism, stop and propose a decision record describing the concrete need and a more explicit alternative.
