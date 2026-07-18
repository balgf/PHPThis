# Application rules

These rules supplement the installed PHPThis consumer contract. They may not repeat, alias, or weaken framework rules.

## Required

- Preserve the dependency direction and boundaries in `.ai/architecture.md`.
- Resolve missing product, scale, authorization, and external-contract facts before implementation.
- Keep every external side effect and failure path visible at a named boundary.
- Run `composer check` before reporting completion.

## Forbidden

- Do not invent schema meaning, production limits, authorization, or external-service behavior.
- Do not add an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not invent human approval or claim unsupported framework or application behavior.
- Do not copy secrets or real customer data into code, context, fixtures, logs, or reports.
- Do not add a second spelling or execution path for an existing operation.

## Starter constraints

- Keep `GET /health` exact until the project deliberately changes its liveness contract.
- Replace these starter constraints with verified product constraints before feature work.
