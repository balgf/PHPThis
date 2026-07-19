# Application rules

These rules supplement installed PHPThis Consumer Contract v4 and Strict Profile v2. They must not alias or weaken those framework rules.

## Required

- Use the canonical domain terms defined in `.ai/project.md`.
- Preserve the dependency direction and boundaries defined in `.ai/architecture.md`.
- Apply the data and resource limits defined in `.ai/data.md`.
- Keep every SQL structural selector, bounded-list shape, runtime capability, prohibited capability, migration-authority boundary, and verification source current in `.ai/data.md`.
- Make every external side effect and failure path named in `.ai/integrations.md` visible in the execution path.
- Preserve the identity, tenant, and authorization boundaries defined in `.ai/architecture.md`.
- Keep adopted session state behind typed application services and the deployment policy recorded in `.ai/architecture.md` and `.ai/operations.md`; mutate only owned keys and preserve every unowned key from the supplied snapshot.
- Keep session mutation callbacks bounded and side-effect-free; finish fallible work before the final immediately committed mutation.
- Keep adopted server-side caching behind narrowly named typed services with explicit hit, miss, authoritative-read, write, and invalidation paths; apply the key, payload, TTL, tenant, invalidation, stale-refill, failure, stampede, and observability policies recorded in `.ai/data.md` and `.ai/operations.md`.
- Treat cached payloads as untrusted derived data, parse them into bounded typed projections, and preserve correctness when an entry is absent or evicted.
- Keep HTTP response caching separate from server-side data caching and give each response-producing path an explicit `no-store`, `private`, or `public` policy with finite freshness or revalidation, validators, and complete `Vary` behavior where applicable.
- Start every new or unreviewed response path with an explicit `Cache-Control: no-store` header, including success, redirect, mapped client-error, unknown server-error, and cookie-emitting responses; change that path only after its recorded policy and tests support reuse.
- Run the complete application validity gate defined in `.ai/testing.md` before reporting completion.

## Forbidden

- Do not invent missing product behavior, schema meaning, production limits, or external-service semantics.
- Do not introduce an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not add a generic cache service, global cache helper, hidden cache-aside behavior, automatic query caching, implicit forever TTL, or arbitrary PHP object deserialization.
- Do not use cached data as a source of truth or cache sessions, authentication state, authorization decisions, permissions, credentials, secrets, or another class prohibited by `.ai/architecture.md`.
- Do not infer that `Set-Cookie`, a server-side cache miss, or a server-side cache hit makes an HTTP response safely private, uncacheable, or public.
- Do not add a cache helper, middleware default, or response post-processor to hide which response-producing path owns its HTTP cache policy.
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
