# Instructions for AI coding agents

## Read order

1. Read `VISION.md`.
2. Read `.ai/README.md`.
3. Read `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/strict-profile.md`.
4. Read only the area guide named by `.ai/README.md`.
5. Inspect the concrete route, handler, and test involved in the task.

## Mandatory rules

- Use PHP 8.4 and `declare(strict_types=1);` in every PHP file.
- Make classes `final` unless a documented extension point requires otherwise.
- Use ordinary constructor injection wired manually in a composition root.
- Use `RequestHandler::handle`; do not make handlers invokable.
- Keep routes in an explicit list. Do not add discovery, attributes, reflection, or string class resolution.
- Keep SQL in the handler or its narrowly named query object. Use `Connection` with named parameters.
- Give every request an explicit `QueryBudget` and bounded `QueryTrace`; do not write one log line per query.
- Parse external `mixed` data once through a named factory into a concrete final readonly projection or command.
- Reject missing and unknown fields and validate before conversion; never use a scalar cast as validation.
- Treat `composer check` as the PHPThis validity gate and repair diagnostics by their stable profile rule or PHPStan identifier.
- Do not suppress a profile rule with a baseline, inline ignore, wildcard exclusion, or comment exemption.
- Never execute a database statement inside `for`, `foreach`, `while`, or recursive traversal.
- Do not add an ORM, Active Record, lazy loading, query builder, service container, facade, global helper, macro system, or dynamic proxy.
- Do not use magic methods other than `__construct`.
- Do not introduce a second way to perform an existing framework task.
- Update the relevant Markdown guide with any public behavior change.
- Keep PHPStan at `level: max`; do not add a baseline, broad ignore pattern, or weaker analysis level.

## Verification

Install development dependencies once, then run the canonical check from the repository root:

```bash
composer install
composer check
```

`composer check` runs repository guardrails, maximum-level PHPStan analysis with strict rules, and tests.

For database behavior, also prove that query count stays constant when fixture cardinality increases and inspect the structured query trace for repetition. A small fixture passing under a query budget is not enough evidence.
