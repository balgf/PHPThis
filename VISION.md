# Vision

## Problem

Many mature frameworks improve human development speed through implicit behavior: lazy relations, facades, runtime discovery, generated proxies, convention-only bindings, and broad helper APIs. Those features enlarge the amount of non-local context an AI must infer. The resulting code can be syntactically convincing while being operationally wrong.

PHPThis reduces that inference surface. It does not attempt to make AI infallible. It makes mistakes easier to prevent, detect, and explain.

## Design principles

1. **Local:** a change should require a small, named set of files.
2. **Literal:** executed behavior is represented by ordinary PHP calls and values.
3. **One-way:** each common task has one canonical implementation pattern.
4. **Typed:** all files use strict types; inputs and outputs cross explicit boundaries.
5. **Bounded:** database work, dependency depth, and core size have measurable limits.
6. **Verified:** important rules are executable checks, not prose alone.
7. **Inspectable:** SQL, routes, dependencies, errors, and side effects remain visible.
8. **Checked:** accepted PHP is a versioned subset with stable, executable diagnostics.

## Performance-obscuring shorthand

PHPThis does not reject every convenience method. It rejects shorthand when its cost depends on hidden I/O, hidden iteration, runtime discovery, or mutable global state. A small response constructor is acceptable; a property access that may execute SQL is not.

## Success measures

- An AI can add a simple endpoint after reading at most four targeted guide/code files.
- The request path can be traced in four application hops: route, handler, database, response.
- Database tests compare small and large fixtures and assert a constant query count.
- PHPStan passes at `level: max` with strict rules and no baseline.
- Every PHPThis-owned profile rule has a permanent identifier and passing and failing fixtures.
- All framework PHP files pass the strict-types and no-magic guardrails.
- Markdown files continue to outnumber PHP files.
- Core source remains at or below the documented phase limit; Phase 1 permits at most 900 physical lines.

## Non-goals

- Recreating a convention-heavy full-stack framework with different names.
- Hiding SQL behind models or a fluent query language.
- Supporting multiple equivalent styles for the same task.
- Eliminating normal PHP knowledge or database design work.
- Claiming that raw SQL by itself prevents inefficient access patterns.
