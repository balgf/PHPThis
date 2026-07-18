# Vision

## North star

**AI-first authoring with human accountability.**

AI is the primary code author and knowledge interface for a PHPThis application. A developer should not need to learn a separate framework manual before asking how the installed system works or requesting a change. The AI reads the installed framework contract, the application's owned context, and the concrete source and tests before it explains or implements anything.

Humans provide intent, authority, and judgment. They decide consequential product, architecture, security, data, and operational tradeoffs and remain accountable for the resulting system. PHPThis is designed to make the AI's work reviewable and verifiable, not to transfer responsibility to a model.

PHPThis therefore does not publish a traditional framework manual as its primary interface. It ships compact, versioned contracts, knowledge maps, decision records, diagnostics, source, and tests that an AI can route and a human can audit.

## Problem

Many mature frameworks improve human development speed through implicit behavior: lazy relations, facades, runtime discovery, generated proxies, convention-only bindings, and broad helper APIs. Those features enlarge the amount of non-local context an AI must infer. The resulting code can be syntactically convincing while being operationally wrong.

PHPThis reduces that inference surface. It does not attempt to make AI infallible. It makes mistakes easier to prevent, detect, and explain.

## Design principles

1. **AI-first:** framework knowledge and authoring workflows are routed for an AI working in the repository.
2. **Accountable:** consequential choices remain explicit for human judgment and approval.
3. **Local:** a change should require a small, named set of files.
4. **Literal:** executed behavior is represented by ordinary PHP calls and values.
5. **One-way:** each framework operation has one canonical execution pattern; optional application structure is selected and documented once.
6. **Typed:** all files use strict types; inputs and outputs cross explicit boundaries.
7. **Bounded:** database work, dependency depth, and core size have measurable limits.
8. **Verified:** important rules are executable checks, not prose alone.
9. **Inspectable:** SQL, routes, dependencies, errors, and side effects remain visible.
10. **Checked:** accepted PHP is a versioned subset with stable, executable diagnostics.

## Performance-obscuring shorthand

PHPThis does not reject every convenience method. It rejects shorthand when its cost depends on hidden I/O, hidden iteration, runtime discovery, or mutable global state. A small response constructor is acceptable; a property access that may execute SQL is not.

## Success measures

- An AI can answer a framework question from the installed version and name the contract, source, test, or decision that supports its answer.
- After the universal contract, knowledge map, and application entrypoints are loaded, an AI can add a simple endpoint after reading at most four task-specific guide or code files.
- A completed change reports its behavior, evidence, resource cost, and any consequential decision that still belongs to a human.
- The request path can be traced in four application hops: route, handler, database, response.
- Database tests compare small and large fixtures and assert a constant query count.
- The same explicit PDO transport contract passes SQLite, MySQL, and PostgreSQL certification without a dialect abstraction.
- Direct database calls resolve to finite reviewed statements, SQL-looking values remain bound data, and unknown structural choices fail before database work.
- CRUD-shaped work follows the optional feature-first reference profile or one recorded application-owned alternative without runtime discovery or filesystem enforcement.
- PHPStan passes at `level: max` with strict rules and no baseline.
- Every PHPThis-owned profile rule has a permanent identifier and passing and failing fixtures.
- All framework PHP files pass the strict-types and no-magic guardrails.
- Markdown files continue to outnumber PHP files.
- Core source remains at or below the documented phase limit; Phase 1 permits at most 1,700 physical lines after security and concurrency review of the accepted cookie and native-session slice.

## Non-goals

- Maintaining a tutorial-style framework manual as the canonical knowledge interface.
- Treating AI output as authority or removing human responsibility for software decisions and outcomes.
- Recreating a convention-heavy full-stack framework with different names.
- Hiding SQL behind models or a fluent query language.
- Treating a generic sanitizer, identifier-quoting helper, or query builder as a substitute for bound data and finite reviewed statement choices.
- Forcing an application directory layout or turning CRUD into a generic persistence API.
- Supporting multiple equivalent styles for the same task.
- Eliminating the need for PHP, database, security, and operational expertise when reviewing or operating a real system.
- Claiming that raw SQL by itself prevents inefficient access patterns.
