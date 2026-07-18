# Application rules

These rules supplement installed PHPThis Consumer Contract v3 and Strict Profile v2. They may not alias or weaken framework rules.

## Required

- Preserve the dependency direction and boundaries in `.ai/architecture.md`.
- Resolve missing product, scale, authorization, and external-contract facts before implementation.
- Keep every external side effect and failure path visible at a named boundary.
- Before database adoption, verify and record finite SQL-structure choices, bounded-list shapes, and isolated least-privileged runtime authority in `.ai/data.md`.
- Run `composer check` before reporting completion.

## Forbidden

- Do not invent schema meaning, production limits, authorization, or external-service behavior.
- Do not add an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not invent human approval or claim unsupported framework or application behavior.
- Do not copy secrets or real customer data into code, context, fixtures, logs, or reports.
- Do not add runtime-built SQL, an SQL sanitizer, or a runtime database identity with migration or administrative authority.
- Do not read `$_SESSION`, call native `session_*` functions, manually emit a framework session cookie, or add a generic session helper.
- Do not add a second spelling or execution path for an existing operation.

## Starter constraints

- Keep `GET /health` exact until the project deliberately changes its liveness contract.
- Keep session state not applicable until its typed key ownership, cookie, isolated file-storage, and concurrency policy are recorded, together with each applicable identity, expiry, revocation, and CSRF concern or explicit non-applicability.
- When session state is adopted, keep mutation callbacks bounded and side-effect-free and complete fallible work before the final immediately committed mutation.
- Replace these starter constraints with verified product constraints before feature work.
