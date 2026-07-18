# Application rules

These rules supplement the installed PHPThis consumer contract. They must not repeat, alias, or weaken framework rules.

## Required

- Use the canonical domain terms defined in `.ai/project.md`.
- Preserve the dependency direction and boundaries defined in `.ai/architecture.md`.
- Apply the data and resource limits defined in `.ai/data.md`.
- Make every external side effect and failure path named in `.ai/integrations.md` visible in the execution path.
- Preserve the identity, tenant, and authorization boundaries defined in `.ai/architecture.md`.
- Run the complete application validity gate defined in `.ai/testing.md` before reporting completion.

## Forbidden

- Do not invent missing product behavior, schema meaning, production limits, or external-service semantics.
- Do not introduce an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not bypass authentication, authorization, validation, audit, or data-retention requirements to simplify a change.
- Do not copy secrets or real customer data into code, instructions, fixtures, logs, or reports.
- Do not add a second spelling or execution path for an existing application operation.

## Additional project constraints

- {{PROJECT_RULE_1}}
- {{PROJECT_RULE_2}}
- {{PROJECT_RULE_3}}
