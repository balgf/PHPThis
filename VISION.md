# Vision

## North star

**AI-first authoring with human accountability.**

AI is the primary code author and knowledge interface for a PHPThis application. A developer should not need to learn a separate framework manual before asking how the installed system works or requesting a change. The AI reads the installed framework contract, the application's owned context, and the concrete source and tests before it explains or implements anything.

Humans provide intent, authority, and judgment. They decide consequential product, architecture, security, data, and operational tradeoffs and remain accountable for the resulting system. PHPThis is designed to make the AI's work reviewable and verifiable, not to transfer responsibility to a model.

PHPThis therefore does not publish a traditional framework manual as its primary interface. It ships compact, versioned contracts, knowledge maps, decision records, diagnostics, source, and tests that an AI can route and a human can audit.

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

## Locality metric

A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision.

After universal entrypoints, a simple-endpoint change has exactly four task-specific files: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test.

Any report of this metric states the universal read cost separately, using the exact framework or application revision, ordered universal-file inventory, and recorded word and byte method. The four files are the task-specific authoring set, not the total context read. Universal authority remains mandatory and another concern's guide, policy, source, or evidence is never skipped to preserve either the file count or a smaller reported context size.

Query, form, or application-owned header input also makes an endpoint non-simple because it requires an additional boundary contract. External I/O in this metric means endpoint-owned work; the application's already-adopted outer request boundary and terminal request-summary path remain in force. If the task enters another concern or requires a new decision, it leaves this metric and follows the applicable routed guide instead.

The existing named route-area manifest constructs the dependency-free handler inline in its exact `Route` declaration. The root `Routes::create()` already includes that route area and remains unchanged. A handler with any constructor dependency follows ordinary root composition and is not a simple endpoint under this metric.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

## Universal success measures

- An AI can answer a framework question from the installed version and name the contract, source, test, or decision that supports its answer.
- The framework and application task routers preserve the exact simple-endpoint locality metric above.
- A completed change reports its behavior, evidence, resource cost, and any consequential decision that still belongs to a human.
- PHPStan passes at `level: max` with strict rules and no baseline.
- Every PHPThis-owned profile rule has a permanent identifier and passing and failing fixtures.
- All framework PHP files pass the strict-types and no-magic guardrails.
- Markdown files continue to outnumber PHP files.
- Core source remains at or below the 2,620-line limit enforced by repository guardrails. Unused capacity remains unallocated and does not authorize additional mechanisms.

## Conditional design detail

[Detailed design goals](docs/design-goals.md) retain the problem statement, performance-shorthand rationale, concern-specific success measures, non-goals, and core-budget history. They remain binding within their concerns; read that companion when reviewing those goals or their rationale. Ordinary implementation follows the current concern guide rather than loading every design example.
