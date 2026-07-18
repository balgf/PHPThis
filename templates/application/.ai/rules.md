# Application rules

These rules supplement installed PHPThis Consumer Contract v3 and Strict Profile v2. They must not alias or weaken those framework rules.

## Required

- Use the canonical domain terms defined in `.ai/project.md`.
- Preserve the dependency direction and boundaries defined in `.ai/architecture.md`.
- Apply the data and resource limits defined in `.ai/data.md`.
- Keep every SQL structural selector, bounded-list shape, runtime capability, prohibited capability, migration-authority boundary, and verification source current in `.ai/data.md`.
- Make every external side effect and failure path named in `.ai/integrations.md` visible in the execution path.
- Preserve the identity, tenant, and authorization boundaries defined in `.ai/architecture.md`.
- Keep adopted session state behind typed application services and the deployment policy recorded in `.ai/architecture.md` and `.ai/operations.md`; mutate only owned keys and preserve every unowned key from the supplied snapshot.
- Keep session mutation callbacks bounded and side-effect-free; finish fallible work before the final immediately committed mutation.
- Run the complete application validity gate defined in `.ai/testing.md` before reporting completion.

## Forbidden

- Do not invent missing product behavior, schema meaning, production limits, or external-service semantics.
- Do not introduce an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not invent human approval or claim unsupported framework or application behavior.
- Do not bypass authentication, authorization, validation, audit, or data-retention requirements to simplify a change.
- Do not read `$_SESSION`, call native `session_*` functions, manually emit the framework session cookie, add a generic session helper, or treat stored identity as authorization.
- Do not accept SQL text from external input, invent an SQL sanitizer, interpolate data, or grant the runtime database identity migration or administrative authority to simplify a change.
- Do not copy secrets or real customer data into code, instructions, fixtures, logs, or reports.
- Do not add a second spelling or execution path for an existing application operation.

## Additional project constraints

- {{PROJECT_RULE_1}}
- {{PROJECT_RULE_2}}
- {{PROJECT_RULE_3}}
